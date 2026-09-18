<?php
/**
 * انیمه موزیک — پردازش مرحله‌ای (چانک) یک پروژه واردات.
 * این فایل مستقیماً توسط admin‌ها باز نمی‌شود؛ از import_worker.php (یا CLI) اجرا می‌شود.
 */

require_once __DIR__ . '/import_common.php';
require_once __DIR__ . '/../../includes/headless.php';

/**
 * ترجمه عنوان به فارسی از طریق سرویس ترجمه گوگل (بدون کلید).
 * در صورت نبود cURL یا خطای شبکه، متن اصلی برگردانده می‌شود تا واردات متوقف نشود.
 */
function am_import_translate_fa($text) {
    $text = trim((string)$text);
    if ($text === '') return $text;
    if (!function_exists('curl_init')) return $text;
    try {
        $url = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl=auto&tl=fa&dt=t&q=' . urlencode($text);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
            CURLOPT_TIMEOUT => 6,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        if ($response) {
            $data = json_decode((string)$response, true);
            if (isset($data[0]) && is_array($data[0])) {
                $out = '';
                foreach ($data[0] as $segment) {
                    if (isset($segment[0])) $out .= $segment[0];
                }
                if ($out !== '') return $out;
            }
        }
    } catch (Exception $e) {
        error_log('[IMPORT] translate error: ' . $e->getMessage());
    }
    return $text; // در صورت خطا، متن اصلی
}

/**
 * اجرای «یک قدم» از فرآیند واردات.
 * @return array وضعیت به‌روز (برای پاسخ JSON)
 */
function am_import_step($job, $secondsBudget = 15, $chunk = 60) {
    am_import_bootstrap();
    $st = am_import_status_load($job);
    if (!$st) {
        return ['status' => 'failed', 'message' => 'پروژه پیدا نشد', 'done' => true, 'failed' => true, 'busy' => false,
                'total' => 0, 'processed' => 0, 'progress' => 100,
                'new_anime' => 0, 'dup_anime' => 0, 'new_music' => 0, 'dup_music' => 0, 'new_singers' => 0, 'dup_singers' => 0, 'errors' => 0];
    }

    $work = am_import_work_file($job);
    if (!is_file($work)) {
        // فایل میانی ناپدید شده — پروژه را جمع‌وجور ببندیم
        am_import_current_set(null);
        @unlink(am_import_status_file($job));
        @unlink(am_import_lock_file($job));
        return am_import_output($st + ['status' => 'done', 'busy' => false]);
    }

    $data = json_decode((string)@file_get_contents($work), true);
    if (!is_array($data)) {
        @unlink($work);
        am_import_current_set(null);
        am_import_status_save($job, ['status' => 'failed', 'message' => 'فایل کاری خراب است', 'total' => 0, 'processed' => 0, 'errors' => 1, 'busy' => false]);
        return am_import_output(['status' => 'failed', 'message' => 'فایل کاری خراب است', 'total' => 0, 'processed' => 0, 'errors' => 1, 'busy' => false, 'done' => true, 'failed' => true, 'progress' => 100]);
    }

    $total  = isset($st['total']) && (int)$st['total'] > 0 ? (int)$st['total'] : count($data);
    $cursor = (int)($st['cursor'] ?? 0);
    if ($cursor > $total) $cursor = $total;

    $newA = (int)($st['new_anime'] ?? 0);   $dupA = (int)($st['dup_anime'] ?? 0);
    $newM = (int)($st['new_music'] ?? 0);   $dupM = (int)($st['dup_music'] ?? 0);
    $newS = (int)($st['new_singers'] ?? 0); $dupS = (int)($st['dup_singers'] ?? 0);
    $errs = (int)($st['errors'] ?? 0);

    $db = am_db_content();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // برقراری امنیت ID — رقابت همزمان یا ترمیم ناقص گذشته را اصلاح می‌کند
    foreach (['anime_series', 'singers', 'anime_contents'] as $tbl) {
        try {
            $db->exec("UPDATE sqlite_sequence SET seq = (SELECT COALESCE(MAX(id), 0) FROM {$tbl}) WHERE name = '{$tbl}'");
        } catch (PDOException $e) {
            // در نبود AUTOINCREMENT/sequence مشکلی نیست
        }
    }

    $start = microtime(true);
    $i = $cursor;

    for (; $i < $total; $i++) {
        $anime_item = $data[$i] ?? null;
        try {
            if (!is_array($anime_item) || empty($anime_item['name'])) continue;

            $title_en = trim((string)$anime_item['name']);
            if ($title_en === '') continue;

            // ۱) پیدا کردن انیمه بر اساس نام انگلیسی (بدون حساسیت به حروف)
            $stm = $db->prepare("SELECT id FROM anime_series WHERE title_en = ? COLLATE NOCASE");
            $stm->execute([$title_en]);
            $anime_id = $stm->fetchColumn();

            if ($anime_id) {
                $dupA++;
            } else {
                // ۲) ترجمه و بررسی نام فارسی (فقط برای انیمه‌هایی که جدید به نظر می‌رسند)
                $title_fa = am_import_translate_fa($title_en);
                $stm = $db->prepare("SELECT id FROM anime_series WHERE title_fa = ? COLLATE NOCASE");
                $stm->execute([$title_fa]);
                $anime_id = $stm->fetchColumn();
                if ($anime_id) {
                    $dupA++;
                } else {
                    try {
                        $stm = $db->prepare("INSERT INTO anime_series (title_fa, title_en, poster_image_url, description) VALUES (?, ?, ?, ?)");
                        $stm->execute([$title_fa, $title_en, $anime_item['main_image_url'] ?? '', '']);
                        $anime_id = $db->lastInsertId();
                        $newA++;
                    } catch (PDOException $e) {
                        // رقابت همزمان — دوباره پیدا کن
                        $stm = $db->prepare("SELECT id FROM anime_series WHERE title_en = ? COLLATE NOCASE OR title_fa = ? COLLATE NOCASE");
                        $stm->execute([$title_en, $title_fa]);
                        $anime_id = $stm->fetchColumn();
                        if ($anime_id) $dupA++; else throw $e;
                    }
                }
            }

            $seasons = (isset($anime_item['seasons']) && is_array($anime_item['seasons'])) ? $anime_item['seasons'] : [];
            foreach ($seasons as $season_index => $season) {
                if (!is_array($season)) continue;
                $themes = (isset($season['themes']) && is_array($season['themes'])) ? $season['themes'] : [];
                foreach ($themes as $theme) {
                    if (!is_array($theme) || empty($theme['title'])) continue;
                    $theme_title = trim((string)$theme['title']);
                    if ($theme_title === '') continue;

                    // نوع موزیک (OP پیش‌فرض)
                    $music_type_id = 1;
                    $t = $theme['type'] ?? '';
                    if ($t === 'ED') $music_type_id = 2;
                    elseif ($t === 'IN') $music_type_id = 3;

                    // خواننده‌ها
                    $singer_ids = [];
                    if (!empty($theme['artist'])) {
                        $parts = explode(',', $theme['artist']);
                        foreach ($parts as $artist_name) {
                            $artist_name = trim($artist_name);
                            if ($artist_name === '') continue;
                            $stm = $db->prepare("SELECT id FROM singers WHERE name = ? COLLATE NOCASE");
                            $stm->execute([$artist_name]);
                            $sid = $stm->fetchColumn();
                            if (!$sid) {
                                try {
                                    $stm = $db->prepare("INSERT INTO singers (name) VALUES (?)");
                                    $stm->execute([$artist_name]);
                                    $sid = $db->lastInsertId();
                                    $newS++;
                                } catch (PDOException $e) {
                                    $stm = $db->prepare("SELECT id FROM singers WHERE name = ? COLLATE NOCASE");
                                    $stm->execute([$artist_name]);
                                    $sid = $stm->fetchColumn();
                                    if ($sid) $dupS++; else { $errs++; continue; }
                                }
                            } else {
                                $dupS++;
                            }
                            $singer_ids[] = $sid;
                        }
                    }

                    // انتخاب بهترین ویدیو
                    $best_video = null;
                    if (isset($theme['videos']) && is_array($theme['videos'])) {
                        foreach ($theme['videos'] as $video) {
                            if (!$best_video ||
                                ((int)$video['resolution'] > (int)$best_video['resolution']) ||
                                (isset($video['nc']) && $video['nc'] === false)) {
                                $best_video = $video;
                            }
                        }
                    }

                    $season_number = $season_index + 1;
                    $episode_number = 1;
                    if (isset($theme['episodes'])) {
                        $episodes = (string)$theme['episodes'];
                        if (strpos($episodes, '-') !== false) {
                            $episode_number = (int)explode('-', $episodes)[0];
                            if ($episode_number <= 0) $episode_number = 1;
                        } else {
                            $episode_number = (int)$episodes ?: 1;
                        }
                    }
                    $video_url = $best_video ? $best_video['url'] : '';

                    // اثر انگشت تکراری (انیمه + نوع + عنوان + فصل/قسمت)
                    $exists = false;
                    $stm = $db->prepare("
                        SELECT id FROM anime_contents
                        WHERE anime_id = ? AND music_type_id = ?
                          AND title = ? COLLATE NOCASE
                          AND season_number = ?
                          AND COALESCE(episode_number, 0) = ?
                        LIMIT 1
                    ");
                    $stm->execute([$anime_id, $music_type_id, $theme_title, $season_number, (int)$episode_number]);
                    if ($stm->fetchColumn()) $exists = true;

                    // اثر انگشت تکراری (لینک یکسان)
                    if (!$exists && $video_url !== '') {
                        $stm = $db->prepare("SELECT id FROM anime_contents WHERE music_file_url = ? OR video_file_url = ? LIMIT 1");
                        $stm->execute([$video_url, $video_url]);
                        if ($stm->fetchColumn()) $exists = true;
                    }

                    if ($exists) { $dupM++; continue; }

                    try {
                        $stm = $db->prepare("
                            INSERT INTO anime_contents
                            (anime_id, music_type_id, title, music_file_url, video_file_url, image_url, season_number, episode_number)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stm->execute([
                            $anime_id, $music_type_id, $theme_title,
                            $video_url, $video_url,
                            $season['image_url'] ?? $season['large_image_url'] ?? ($anime_item['main_image_url'] ?? ''),
                            $season_number, $episode_number
                        ]);
                        $content_id = $db->lastInsertId();
                        $newM++;

                        foreach ($singer_ids as $sid) {
                            $stm = $db->prepare("INSERT INTO content_singers (content_id, singer_id) VALUES (?, ?)");
                            try {
                                $stm->execute([$content_id, $sid]);
                            } catch (PDOException $e) { /* پیوند تکراری مشکلی نیست */ }
                        }
                    } catch (PDOException $e) {
                        $errs++;
                        error_log('[IMPORT] insert error: ' . $e->getMessage());
                    }
                }
            }
        } catch (Exception $e) {
            $errs++;
            error_log('[IMPORT] item error: ' . $e->getMessage());
        }

        // نقطه‌ی ذخیره‌ی میانی (برای پیشرفت زنده و رفرش)
        if (($i + 1) % 10 === 0) {
            am_import_status_save($job, [
                'status' => 'running', 'busy' => true,
                'total' => $total, 'cursor' => $i + 1, 'processed' => $i + 1,
                'new_anime' => $newA, 'dup_anime' => $dupA,
                'new_music' => $newM, 'dup_music' => $dupM,
                'new_singers' => $newS, 'dup_singers' => $dupS,
                'errors' => $errs, 'message' => ''
            ]);
        }
        if ((microtime(true) - $start) > $secondsBudget) break;
    }

    $nextCursor = ($i >= $total) ? $total : ($i + 1);
    $done = ($nextCursor >= $total);

    // اگر پروژه وسط همین قدم لغو شده باشد (current پاک/تغییر کرده)، چیزی ذخیره نمی‌کنیم
    $nowCur = am_import_current();
    if (!$nowCur || ($nowCur['job'] ?? '') !== $job) {
        @unlink($work);
        @unlink(am_import_status_file($job));
        @unlink(am_import_lock_file($job));
        return am_import_output(['status' => 'idle', 'done' => false, 'failed' => false, 'busy' => false,
                                 'total' => 0, 'processed' => 0, 'progress' => 0,
                                 'new_anime' => $newA, 'dup_anime' => $dupA, 'new_music' => $newM, 'dup_music' => $dupM,
                                 'new_singers' => $newS, 'dup_singers' => $dupS, 'errors' => $errs, 'message' => '']);
    }

    if ($done) {
        @unlink($work);
        $last = am_import_status_save($job, [
            'status' => 'done', 'busy' => false,
            'total' => $total, 'cursor' => $total, 'processed' => $total,
            'new_anime' => $newA, 'dup_anime' => $dupA,
            'new_music' => $newM, 'dup_music' => $dupM,
            'new_singers' => $newS, 'dup_singers' => $dupS,
            'errors' => $errs,
            'message' => "پردازش کامل شد: $newM جدید، $dupM تکراری رد شد"
        ]);
    } else {
        // پایان قدم: پردازنده دوباره آزاد شده تا قدم بعدی اجرا شود
        $last = am_import_status_save($job, [
            'status' => 'running', 'busy' => false,
            'total' => $total, 'cursor' => $nextCursor, 'processed' => $nextCursor,
            'new_anime' => $newA, 'dup_anime' => $dupA,
            'new_music' => $newM, 'dup_music' => $dupM,
            'new_singers' => $newS, 'dup_singers' => $dupS,
            'errors' => $errs, 'message' => ''
        ]);
    }

    am_import_current_set($done ? null : $job);
    return am_import_output($last + ['done' => $done, 'failed' => false, 'busy' => false]);
}
