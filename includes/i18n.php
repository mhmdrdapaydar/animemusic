<?php
/**
 * انیمه موزیک — موتور چندزبانگی (۱۱ زبان)
 *
 * نحوه‌ی کار:
 *  1) زبان از «پیشوند مسیر» تعیین می‌شود:  /en/… /ja/… /es/… و …
 *     (مسیرهای بدون پیشوند = فارسی، دقیقاً همان آدرس‌های فعلی سایت)
 *  2) ترجمه‌ی رابط کاربری (دکمه‌ها، منوها) از آرایه‌های داخل همین فایل خوانده می‌شود.
 *  3) عنوان انیمه/موزیک «ترجمه نمی‌شود»؛ بر اساس انتخاب کاربر از عنوان انگلیسی
 *     یا فارسی موجود در دیتابیس استفاده می‌شود (بدون وابستگی به اینترنت).
 *
 * فایل‌های رابط زبان (مثل /en/index.php) فقط شامل:
 *     $AM_LANG = 'en';
 *     chdir(__DIR__);
 *     require '../EN_TARGET.php';
 * که target ترجمه می‌کند و خروجی می‌دهد.
 */

if (!defined('AM_I18N_DEFINED')) {
    define('AM_I18N_DEFINED', 1);

    if (!defined('AM_ROOT')) {
        define('AM_ROOT', dirname(__DIR__));
    }

    /** جدول کامل زبان‌ها: کد => [نام بومی، جهت، ht-suffix، نام انگلیسی، نام انگلیسی برای سئو] */
    function am_languages() {
        static $langs = null;
        if ($langs === null) {
            $langs = [
                'fa' => ['فارسی',            'rtl', '',       'Persian',   'fa'],
                'en' => ['English',           'ltr', '-en',    'English',   'en'],
                'ja' => ['日本語',            'ltr', '-ja',    'Japanese',  'ja'],
                'es' => ['Español',           'ltr', '-es',    'Spanish',   'es'],
                'pt' => ['Português',         'ltr', '-pt',    'Portuguese','pt'],
                'fr' => ['Français',          'ltr', '-fr',    'French',    'fr'],
                'de' => ['Deutsch',           'ltr', '-de',    'German',    'de'],
                'ar' => ['العربية',           'rtl', '-ar',    'Arabic',    'ar'],
                'hi' => ['हिन्दी',           'ltr', '-hi',    'Hindi',     'hi'],
                'th' => ['ไทย',               'ltr', '-th',    'Thai',      'th'],
                'ko' => ['한국어',            'ltr', '-ko',    'Korean',    'ko'],
            ];
        }
        return $langs;
    }

    /** جهت متن زبان: 'rtl' یا 'ltr' */
    function am_lang_dir($lang = null) {
        if ($lang === null) $lang = am_current_lang();
        $langs = am_languages();
        return isset($langs[$lang]) ? $langs[$lang][1] : 'rtl';
    }

    /** کد زبان جاری (پیش‌فرض fa) */
    function am_current_lang() {
        global $AM_LANG;
        $l = is_string($AM_LANG ?? null) ? $AM_LANG : 'fa';
        $langs = am_languages();
        return isset($langs[$l]) ? $l : 'fa';
    }

    function am_lang() { return am_current_lang(); }

    /**
     * ترجمه‌ی یک کلید رابط کاربری.
     * هر کلید کوتاه (fa,en,ja,…) یا پیام کامل دارد یا با انگلیسی (en) پر می‌شود.
     */
    function am_t($key) {
        static $dict = null;
        if ($dict === null) {
            $dict = am_ui_dict();
        }
        $l = am_current_lang();
        if (isset($dict[$key][$l]) && $dict[$key][$l] !== '') return $dict[$key][$l];
        if (isset($dict[$key]['en']) && $dict[$key]['en'] !== '') return $dict[$key]['en'];
        return isset($dict[$key]['fa']) ? $dict[$key]['fa'] : $key;
    }

    /** معادل `am_t` با خروجی امن HTML */
    function am_te($key) {
        return am_e(am_t($key));
    }

    /**
     * تبدیل مسیر داخلی (index.php, content.php?id=5) به مسیر زبان‌دار برای زبان جاری.
     * خروجی همیشه با «/» شروع می‌شود تا از هر زیرپوشه‌ای درست حل شود.
     */
    function am_lang_url($path = '') {
        $l = am_current_lang();
        $p = ltrim((string)$path, '/');
        if ($l === 'fa') return '/' . $p;
        return '/' . $l . '/' . $p;
    }

    /**
     * تگ <base href="/"> برای صفحات زبان (غیرفارسی) تا لینک‌های نسبی
     * (CSS، تصاویر، لینک‌ها) از زیرپوشه‌ی زبان هم از ریشه حل شوند.
     */
    function am_lang_base_tag() {
        return am_lang() !== 'fa' ? '<base href="/" />' : '';
    }

    /**
     * مسیر استاتیک (CSS/JS/تصویر) که همیشه از ریشه‌ی سایت حل می‌شود،
     * مستقل از زبان — چون فایل‌های استاتیک در همه زبان‌ها یکسان هستند.
     * اگر آدرس مطلق (http/https) یا data: باشد، بدون تغییر برمی‌گرداند.
     */
    function am_lang_asset($path) {
        $p = (string)$path;
        if ($p === '') return '';
        if (preg_match('#^(https?:)?//#i', $p)) return $p;
        if (strpos($p, 'data:') === 0) return $p;
        return '/' . ltrim($p, '/');
    }

    /** پیوند مطلق سایت (برای hreflang / سایت‌مپ) برای یک زبان */
    function am_abs_url($path = '', $lang = null) {
        if ($lang === null) $lang = am_current_lang();
        $base = defined('AM_SITE_URL') ? rtrim(AM_SITE_URL, '/') : '';
        $p = ltrim((string)$path, '/');
        if ($lang === 'fa') return $base . ($p !== '' ? '/' . $p : '');
        return $base . '/' . $lang . ($p !== '' ? '/' . $p : '');
    }

    /**
     * قالب hreflang برای تگ <head> جهت اعلام نسخه‌های زبانی به گوگل.
     * @param string $path مسیر داخلی بدون زبان (مثلا index, content.php?id=5)
     */
    function am_hreflang_links($path = '') {
        // canonical: آدرس نسخه فعلی (بدون زبان برای فارسی، با پیشوند برای بقیه)
        $out = '<link rel="canonical" href="' . am_e(am_abs_url($path)) . '" />' . "\n  ";
        foreach (am_languages() as $code => $meta) {
            $out .= '<link rel="alternate" hreflang="' . $code . '" href="' . am_e(am_abs_url($path, $code)) . '" />' . "\n  ";
        }
        // نسخه پیش‌فرض (بدون زبان) = فارسی
        $out .= '<link rel="alternate" hreflang="x-default" href="' . am_e(am_abs_url($path, 'fa')) . '" />';
        return $out;
    }

    /**
     * فلگ و انتخاب‌گر زبان (برای قرار دادن بالای صفحه).
     * آیکون پرچم با ایموجی؛ زبان فعال هایلایت می‌شود.
     */
    function am_lang_switcher_flags($basePath = '') {
        $cur = am_current_lang();
        $flag = [
            'fa' => '🇮🇷', 'en' => '🇬🇧', 'ja' => '🇯🇵', 'es' => '🇪🇸', 'pt' => '🇧🇷',
            'fr' => '🇫🇷', 'de' => '🇩🇪', 'ar' => '🇸🇦', 'hi' => '🇮🇳', 'th' => '🇹🇭', 'ko' => '🇰🇷',
        ];
        $html = '<div class="lang-switcher" aria-label="Language">';
        foreach (am_languages() as $code => $meta) {
            $name = $meta[0];
            $href = ($code === 'fa')
                ? ('/' . ltrim((string)$basePath, '/'))
                : ('/' . $code . '/' . ltrim((string)$basePath, '/'));
            $active = ($code === $cur) ? ' active' : '';
            $html .= '<a href="' . am_e($href) . '" class="lang-flag' . $active . '" title="' . am_e($name) . '" data-lang="' . $code . '">'
                   . ($flag[$code] ?? '') . '</a>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * عنوان انیمه/موزیک مناسب برای زبان جاری — بدون ترجمه، بر اساس داده‌های موجود.
     * ایندکس سئو برای زبان‌های لاتین از عنوان انگلیسی می‌آید.
     */
    function am_lang_content_title($titleEn, $titleFa = '') {
        if (am_current_lang() === 'fa') {
            return $titleFa !== '' ? $titleFa : (string)$titleEn;
        }
        return (string)$titleEn !== '' ? (string)$titleEn : (string)$titleFa;
    }

    /**
     * دیکشنری رابط کاربری. فرمت:
     *   'key' => ['fa'=>…, 'en'=>…, 'ja'=>…, …]
     * هر زبانی که خالی بماند به انگلیسی برمی‌گردد.
     */
    function am_ui_dict() {
        return [
            'home'            => ['fa'=>'خانه','en'=>'Home','ja'=>'ホーム','es'=>'Inicio','pt'=>'Início','fr'=>'Accueil','de'=>'Startseite','ar'=>'الرئيسية','hi'=>'होम','th'=>'หน้าแรก','ko'=>'홈'],
            'categories'      => ['fa'=>'دسته‌ها','en'=>'Categories','ja'=>'カテゴリ','es'=>'Categorías','pt'=>'Categorias','fr'=>'Catégories','de'=>'Kategorien','ar'=>'التصنيفات','hi'=>'श्रेणियाँ','th'=>'หมวดหมู่','ko'=>'카테고리'],
            'search'          => ['fa'=>'جستجو','en'=>'Search','ja'=>'検索','es'=>'Buscar','pt'=>'Pesquisar','fr'=>'Rechercher','de'=>'Suche','ar'=>'بحث','hi'=>'खोज','th'=>'ค้นหา','ko'=>'검색'],
            'about'           => ['fa'=>'درباره ما','en'=>'About','ja'=>'概要','es'=>'Acerca de','pt'=>'Sobre','fr'=>'À propos','de'=>'Über uns','ar'=>'حول','hi'=>'हमारे बारे में','th'=>'เกี่ยวกับ','ko'=>'소개'],
            'profile'         => ['fa'=>'پروفایل','en'=>'Profile','ja'=>'プロフィール','es'=>'Perfil','pt'=>'Perfil','fr'=>'Profil','de'=>'Profil','ar'=>'الملف الشخصي','hi'=>'प्रोफ़ाइल','th'=>'โปรไฟล์','ko'=>'프로필'],
            'login'           => ['fa'=>'ورود','en'=>'Login','ja'=>'ログイン','es'=>'Iniciar sesión','pt'=>'Entrar','fr'=>'Connexion','de'=>'Anmelden','ar'=>'تسجيل الدخول','hi'=>'लॉगिन','th'=>'เข้าสู่ระบบ','ko'=>'로그인'],
            'signup'          => ['fa'=>'ثبت‌نام','en'=>'Sign up','ja'=>'登録','es'=>'Registrarse','pt'=>'Cadastrar','fr'=>"S'inscrire",'de'=>'Registrieren','ar'=>'التسجيل','hi'=>'साइन अप','th'=>'สมัคร','ko'=>'가입'],
            'logout'          => ['fa'=>'خروج','en'=>'Logout','ja'=>'ログアウト','es'=>'Cerrar sesión','pt'=>'Sair','fr'=>'Déconnexion','de'=>'Abmelden','ar'=>'تسجيل الخروج','hi'=>'लॉगआउट','th'=>'ออกจากระบบ','ko'=>'로그아웃'],
            'popular'         => ['fa'=>'محبوب‌ترین‌ها','en'=>'Most Popular','ja'=>'人気','es'=>'Más populares','pt'=>'Mais populares','fr'=>'Les plus populaires','de'=>'Beliebteste','ar'=>'الأكثر شعبية','hi'=>'सबसे लोकप्रिय','th'=>'ยอดนิยม','ko'=>'인기'],
            'latest'          => ['fa'=>'جدیدترین‌ها','en'=>'Latest','ja'=>'最新','es'=>'Más recientes','pt'=>'Mais recentes','fr'=>'Récents','de'=>'Neueste','ar'=>'الأحدث','hi'=>'नवीनतम','th'=>'ล่าสุด','ko'=>'최신'],
            'openings'        => ['fa'=>'اوپنینگ‌ها','en'=>'Openings','ja'=>'オープニング','es'=>'Openings','pt'=>'Aberturas','fr'=>'Génériques','de'=>'Openings','ar'=>'الشارات الافتتاحية','hi'=>'ओपनिंग','th'=>'เพลงเปิด','ko'=>'오프닝'],
            'endings'         => ['fa'=>'اندینگ‌ها','en'=>'Endings','ja'=>'エンディング','es'=>'Endings','pt'=>'Encerramentos','fr'=>'Génériques de fin','de'=>'Endings','ar'=>'الشارات الختامية','hi'=>'एंडिंग','th'=>'เพลงปิด','ko'=>'엔딩'],
            'osts'            => ['fa'=>'موسیقی متن (OST)','en'=>'Soundtracks (OST)','ja'=>'サウンドトラック','es'=>'Bandas sonoras','pt'=>'Trilhas sonoras','fr'=>'Bandes originales','de'=>'Soundtracks','ar'=>'الموسيقى التصويرية','hi'=>'साउंडट्रैक','th'=>'เพลงประกอบ','ko'=>'OST'],
            'view_all'        => ['fa'=>'مشاهده همه','en'=>'View all','ja'=>'すべて見る','es'=>'Ver todo','pt'=>'Ver tudo','fr'=>'Tout voir','de'=>'Alle ansehen','ar'=>'عرض الكل','hi'=>'सभी देखें','th'=>'ดูทั้งหมด','ko'=>'전체 보기'],
            'download'        => ['fa'=>'دانلود','en'=>'Download','ja'=>'ダウンロード','es'=>'Descargar','pt'=>'Baixar','fr'=>'Télécharger','de'=>'Herunterladen','ar'=>'تحميل','hi'=>'डाउनलोड','th'=>'ดาวน์โหลด','ko'=>'다운로드'],
            'play'            => ['fa'=>'پخش','en'=>'Play','ja'=>'再生','es'=>'Reproducir','pt'=>'Reproduzir','fr'=>'Lire','de'=>'Abspielen','ar'=>'تشغيل','hi'=>'चलाएं','th'=>'เล่น','ko'=>'재생'],
            'read_more'       => ['fa'=>'ادامه مطلب','en'=>'Read more','ja'=>'続きを読む','es'=>'Leer más','pt'=>'Leia mais','fr'=>'Lire la suite','de'=>'Weiterlesen','ar'=>'اقرأ المزيد','hi'=>'और पढ़ें','th'=>'อ่านเพิ่มเติม','ko'=>'더 읽기'],
            'season'          => ['fa'=>'فصل','en'=>'Season','ja'=>'シーズン','es'=>'Temporada','pt'=>'Temporada','fr'=>'Saison','de'=>'Staffel','ar'=>'الموسم','hi'=>'सीज़न','th'=>'ซีซัน','ko'=>'시즌'],
            'episode'         => ['fa'=>'قسمت','en'=>'Episode','ja'=>'話','es'=>'Episodio','pt'=>'Episódio','fr'=>'Épisode','de'=>'Folge','ar'=>'الحلقة','hi'=>'एपिसोड','th'=>'ตอน','ko'=>'에피소드'],
            'views'           => ['fa'=>'بازدید','en'=>'views','ja'=>'回視聴','es'=>'vistas','pt'=>'visualizações','fr'=>'vues','de'=>'Aufrufe','ar'=>'مشاهدة','hi'=>'दृश्य','th'=>'การดู','ko'=>'조회'],
            'search_ph'       => ['fa'=>'جستجوی انیمه، موزیک، خواننده...','en'=>'Search anime, music, singer...','ja'=>'アニメ、音楽、歌手を検索...','es'=>'Buscar anime, música, cantante...','pt'=>'Buscar anime, música, cantor...','fr'=>'Rechercher anime, musique, chanteur...','de'=>'Anime, Musik, Sänger suchen...','ar'=>'ابحث عن أنمي، موسيقى، مغني...','hi'=>'एनीमे, संगीत, गायक खोजें...','th'=>'ค้นหาอนิเมะ เพลง นักร้อง...','ko'=>'애니메이션, 음악, 가수 검색...'],
            'results'         => ['fa'=>'نتیجه','en'=>'results','ja'=>'件','es'=>'resultados','pt'=>'resultados','fr'=>'résultats','de'=>'Ergebnisse','ar'=>'نتيجة','hi'=>'परिणाम','th'=>'ผลลัพธ์','ko'=>'결과'],
            'not_found'       => ['fa'=>'محتوا یافت نشد','en'=>'Content not found','ja'=>'コンテンツが見つかりません','es'=>'Contenido no encontrado','pt'=>'Conteúdo não encontrado','fr'=>'Contenu introuvable','de'=>'Inhalt nicht gefunden','ar'=>'المحتوى غير موجود','hi'=>'सामग्री नहीं मिली','th'=>'ไม่พบเนื้อหา','ko'=>'콘텐츠를 찾을 수 없습니다'],
            'prev_page'       => ['fa'=>'قبلی','en'=>'Previous','ja'=>'前へ','es'=>'Anterior','pt'=>'Anterior','fr'=>'Précédent','de'=>'Zurück','ar'=>'السابق','hi'=>'पिछला','th'=>'ก่อนหน้า','ko'=>'이전'],
            'next_page'       => ['fa'=>'بعدی','en'=>'Next','ja'=>'次へ','es'=>'Siguiente','pt'=>'Próximo','fr'=>'Suivant','de'=>'Weiter','ar'=>'التالي','hi'=>'अगला','th'=>'ถัดไป','ko'=>'다음'],
            'site_name'       => ['fa'=>'انیمه موزیک','en'=>'Anime Music','ja'=>'アニメミュージック','es'=>'Anime Music','pt'=>'Anime Music','fr'=>'Anime Music','de'=>'Anime Music','ar'=>'أنمي ميوزك','hi'=>'एनीमे म्यूजिक','th'=>'อนิเมะมิวสิค','ko'=>'애니메이션 뮤직'],
            'buy_vip'         => ['fa'=>'خرید اشتراک VIP','en'=>'Buy VIP subscription','ja'=>'VIPサブスク購入','es'=>'Comprar suscripción VIP','pt'=>'Comprar assinatura VIP','fr'=>"Acheter l'abonnement VIP",'de'=>'VIP-Abo kaufen','ar'=>'شراء اشتراك VIP','hi'=>'VIP सदस्यता खरीदें','th'=>'ซื้อแพ็กเกจ VIP','ko'=>'VIP 구독 구매'],
            'music_types'     => ['fa'=>'انواع موزیک','en'=>'Music types','ja'=>'曲のタイプ','es'=>'Tipos de música','pt'=>'Tipos de música','fr'=>'Types de musique','de'=>'Musikarten','ar'=>'أنواع الموسيقى','hi'=>'संगीत के प्रकार','th'=>'ประเภทเพลง','ko'=>'음악 유형'],
            'animes'          => ['fa'=>'انیمه‌ها','en'=>'Anime','ja'=>'アニメ','es'=>'Animes','pt'=>'Animes','fr'=>'Animés','de'=>'Animes','ar'=>'الأنميات','hi'=>'एनीमे','th'=>'อนิเมะ','ko'=>'애니메이션'],
            'singers'         => ['fa'=>'خوانندگان','en'=>'Singers','ja'=>'歌手','es'=>'Cantantes','pt'=>'Cantores','fr'=>'Chanteurs','de'=>'Sänger','ar'=>'المغنون','hi'=>'गायक','th'=>'นักร้อง','ko'=>'가수'],
            'items'           => ['fa'=>'مورد','en'=>'items','ja'=>'件','es'=>'elementos','pt'=>'itens','fr'=>'éléments','de'=>'Einträge','ar'=>'عنصر','hi'=>'आइटम','th'=>'รายการ','ko'=>'개'],
            'content_filter_h2' => ['fa'=>'آثار','en'=>'Works','ja'=>'作品','es'=>'Obras','pt'=>'Obras','fr'=>'Œuvres','de'=>'Werke','ar'=>'الأعمال','hi'=>'कार्य','th'=>'ผลงาน','ko'=>'작품'],
            'no_results_for'  => ['fa'=>'هیچ نتیجه‌ای برای','en'=>'No results for','ja'=>'結果がありません','es'=>'Sin resultados para','pt'=>'Nenhum resultado para','fr'=>"Aucun résultat pour",'de'=>'Keine Ergebnisse für','ar'=>'لا توجد نتائج لـ','hi'=>'के लिए कोई परिणाम नहीं','th'=>'ไม่พบผลลัพธ์สำหรับ','ko'=>'검색 결과 없음'],
            'try_other_search'=> ['fa'=>'لطفاً عبارت جستجوی خود را تغییر دهید یا فیلترها را جایگزین کنید.','en'=>'Please change your search term or replace the filters.','ja'=>'検索語を変更するか、フィルターを置き換えてください。','es'=>'Cambie el término de búsqueda o reemplace los filtros.','pt'=>'Altere o termo de pesquisa ou substitua os filtros.','fr'=>'Modifiez votre terme de recherche ou remplacez les filtres.','de'=>'Bitte ändern Sie den Suchbegriff oder ersetzen Sie die Filter.','ar'=>'يرجى تغيير عبارة البحث أو استبدال عوامل التصفية.','hi'=>'कृपया अपना खोज शब्द बदलें या फ़िल्टर बदलें।','th'=>'โปรดเปลี่ยนคำค้นหาหรือตัวกรอง','ko'=>'검색어를 변경하거나 필터를 교체하세요.'],
            'showing'         => ['fa'=>'نمایش','en'=>'Showing','ja'=>'表示','es'=>'Mostrando','pt'=>'Exibindo','fr'=>'Affichage','de'=>'Anzeige','ar'=>'عرض','hi'=>'दिखा रहा है','th'=>'แสดง','ko'=>'표시'],
            'to_word'         => ['fa'=>'تا','en'=>'to','ja'=>'〜','es'=>'a','pt'=>'a','fr'=>'à','de'=>'bis','ar'=>'إلى','hi'=>'से','th'=>'ถึง','ko':'~'],
            'of_word'         => ['fa'=>'از','en'=>'of','ja'=>'件中','es'=>'de','pt'=>'de','fr'=>'sur','de'=>'von','ar'=>'من أصل','hi'=>'में से','th'=>'จาก','ko'=>'중'],
            'result_word'     => ['fa'=>'نتیجه','en'=>'results','ja'=>'件','es'=>'resultados','pt'=>'resultados','fr'=>'résultats','de'=>'Ergebnisse','ar'=>'نتيجة','hi'=>'परिणाम','th'=>'ผลลัพธ์','ko'=>'결과'],
            'for_word'        => ['fa'=>'برای','en'=>'for','ja'=>'の検索結果','es'=>'para','pt'=>'para','fr'=>'pour','de'=>'für','ar'=>'عن','hi'=>'के लिए','th'=>'สำหรับ','ko'=>'에 대한'],
            'all_types'       => ['fa'=>'همه انواع','en'=>'All types','ja'=>'すべてのタイプ','es'=>'Todos los tipos','pt'=>'Todos os tipos','fr'=>'Tous les types','de'=>'Alle Typen','ar'=>'كل الأنواع','hi'=>'सभी प्रकार','th'=>'ทุกประเภท','ko'=>'모든 유형'],
            'all_singers'     => ['fa'=>'همه خوانندگان','en'=>'All singers','ja'=>'すべての歌手','es'=>'Todos los cantantes','pt'=>'Todos os cantores','fr'=>'Tous les chanteurs','de'=>'Alle Sänger','ar'=>'كل المطربين','hi'=>'सभी गायक','th'=>'นักร้องทั้งหมด','ko'=>'모든 가수'],
            'opening_type'    => ['fa'=>'اوپنینگ','en'=>'Opening','ja'=>'オープニング','es'=>'Opening','pt'=>'Abertura','fr'=>'Opening','de'=>'Opening','ar'=>'شارة البداية','hi'=>'ओपनिंग','th'=>'เพลงเปิด','ko'=>'오프닝'],
            'ending_type'     => ['fa'=>'اندینگ','en'=>'Ending','ja'=>'エンディング','es'=>'Ending','pt'=>'Encerramento','fr'=>'Ending','de'=>'Ending','ar'=>'شارة النهاية','hi'=>'एंडिंग','th'=>'เพลงปิด','ko'=>'엔딩'],
            'ost_type'        => ['fa'=>'موسیقی زمینه','en'=>'OST','ja'=>'OST','es'=>'BSO','pt'=>'OST','fr'=>'OST','de'=>'OST','ar'=>'الموسيقى التصويرية','hi'=>'OST','th'=>'เพลงประกอบ','ko'=>'OST'],
            'concert_type'    => ['fa'=>'کنسرت','en'=>'Concert','ja'=>'コンサート','es'=>'Concierto','pt'=>'Concerto','fr'=>'Concert','de'=>'Konzert','ar'=>'حفلة','hi'=>'कॉन्सर्ट','th'=>'คอนเสิร์ต','ko'=>'콘서트'],
            'char_song_type'  => ['fa'=>'آهنگ شخصیت','en'=>'Character Song','ja'=>'キャラクターソング','es'=>'Canción de personaje','pt'=>'Música de personagem','fr'=>'Chanson de personnage','de'=>'Charaktersong','ar'=>'أغنية الشخصية','hi'=>'कैरेक्टर सॉन्ग','th'=>'เพลงตัวละคร','ko'=>'캐릭터 송'],
            'about_intro'      => ['fa'=>'به انیمه موزیک، مرجع تخصصی موسیقی انیمه، خوش آمدید.','en'=>'Welcome to Anime Music, your dedicated source for anime music.','ja'=>'アニメミュージックへようこそ。アニメ音楽の専門サイトです。','es'=>'Bienvenido a Anime Music, tu fuente dedicada de música anime.','pt'=>'Bem-vindo ao Anime Music, sua fonte dedicada de música de anime.','fr'=>'Bienvenue sur Anime Music, votre référence pour la musique d\'anime.','de'=>'Willkommen bei Anime Music, Ihrer Quelle für Anime-Musik.','ar'=>'مرحبًا بك في أنمي ميوزك، مصدرك المتخصص لموسيقى الأنمي.','hi'=>'एनीमे म्यूजिक में आपका स्वागत है, एनीमे संगीत का आपका समर्पित स्रोत।','th'=>'ยินดีต้อนรับสู่ Ani-Music แหล่งเพลงอนิเมะของคุณ','ko'=>'애니메이션 음악 전문 사이트, 애니 뮤직에 오신 것을 환영합니다.'],
            'our_platform'    => ['fa'=>'تمایزهای پلتفرم ما','en'=>'What makes our platform different','ja'=>'私たちのプラットフォームの特徴','es'=>'Qué diferencia a nuestra plataforma','pt'=>'O que diferencia nossa plataforma','fr'=>'Ce qui différencie notre plateforme','de'=>'Was unsere Plattform auszeichnet','ar'=>'ما يميز منصتنا','hi'=>'हमारा प्लेटफ़ॉर्म क्या अलग बनाता है','th'=>'สิ่งที่ทำให้แพลตฟอร์มของเราแตกต่าง','ko'=>'우리 플랫폼의 차별점'],
            'about_platform'  => ['fa'=>'سایت انیمه موزیک با بهره‌گیری از فناوری‌های روز، امکاناتی گسترده و کاربرمحور را در دو سطح دسترسی عمومی و اختصاصی (VIP) فراهم نموده است.','en'=>'Anime Music offers broad, user-focused features at two access levels: free and VIP.','ja'=>'アニメミュージックは、無料とVIPの2つのレベルで幅広い機能を提供しています。','es'=>'Anime Music ofrece amplias funciones en dos niveles de acceso: gratuito y VIP.','pt'=>'O Anime Music oferece amplos recursos em dois níveis de acesso: gratuito e VIP.','fr'=>'Anime Music propose de nombreuses fonctions à deux niveaux d\'accès : gratuit et VIP.','de'=>'Anime Music bietet umfangreiche Funktionen in zwei Stufen: kostenlos und VIP.','ar'=>'تقدم أنمي ميوزك ميزات واسعة بمستويي وصول: مجاني وVIP.','hi'=>'एनीमे म्यूजिक दो स्तरों पर व्यापक सुविधाएँ देता है: निःशुल्क और VIP।','th'=>'Ani-Music มีฟีเจอร์กว้างขวางสองระดับ: ฟรีและ VIP','ko'=>'애니 뮤직은 무료와 VIP 두 단계로 다양한 기능을 제공합니다.'],
            'public_features' => ['fa'=>'امکانات همگانی','en'=>'Free features','ja'=>'無料機能','es'=>'Funciones gratuitas','pt'=>'Recursos gratuitos','fr'=>'Fonctions gratuites','de'=>'Kostenlose Funktionen','ar'=>'الميزات المجانية','hi'=>'निःशुल्क सुविधाएँ','th'=>'ฟีเจอร์ฟรี','ko'=>'무료 기능'],
            'about_free'      => ['fa'=>'دسترسی به هسته اصلی سرویس برای همه کاربران به صورت رایگان فراهم است؛ شامل پخش آنلاین، جستجو و فیلتر پیشرفته، دسته‌بندی هوشمند، محبوب‌ترین‌ها، تم دارک و لایت و حمایت مالی.','en'=>'Core access is free for everyone: online streaming, advanced search and filters, smart categories, most popular, dark/light theme and donations.','ja'=>'オンライン再生、高度な検索・フィルター、スマートなカテゴリ、人気ランキング、ダーク/ライトテーマ、寄付など、基本機能は誰でも無料です。','es'=>'El acceso principal es gratuito para todos: streaming en línea, búsqueda y filtros avanzados, categorías inteligentes, más populares, tema oscuro/claro y donaciones.','pt'=>'O acesso principal é gratuito: streaming online, busca e filtros avançados, categorias inteligentes, mais populares, tema escuro/claro e doações.','fr'=>'L\'accès principal est gratuit : streaming en ligne, recherche et filtres avancés, catégories intelligentes, les plus populaires, thème sombre/clair et dons.','de'=>'Der Kernzugang ist für alle kostenlos: Online-Streaming, erweiterte Suche und Filter, intelligente Kategorien, Beliebteste, Dark/Light-Theme und Spenden.','ar'=>'الوصول الأساسي مجاني للجميع: البث عبر الإنترنت، البحث والتصفية المتقدمة، التصنيفات الذكية، الأكثر شعبية، السمة الداكنة/الفاتحة والتبرعات.','hi'=>'मुख्य सुविधाएँ सभी के लिए मुफ़्त: ऑनलाइन स्ट्रीमिंग, उन्नत खोज और फ़िल्टर, स्मार्ट श्रेणियाँ, सर्वाधिक लोकप्रिय, डार्क/लाइट थीम और दान।','th'=>'การเข้าถึงหลักฟรีสำหรับทุกคน','ko'=>'온라인 스트리밍, 고급 검색·필터, 스마트 분류, 인기 순위, 다크/라이트 테마, 후원 등 핵심 기능은 누구나 무료입니다.'],
            'vip_features_title' => ['fa'=>'امکانات اشتراک VIP','en'=>'VIP subscription features','ja'=>'VIPサブスク機能','es'=>'Funciones de la suscripción VIP','pt'=>'Recursos da assinatura VIP','fr'=>'Fonctions de l\'abonnement VIP','de'=>'VIP-Abo-Funktionen','ar'=>'ميزات اشتراك VIP','hi'=>'VIP सदस्यता सुविधाएँ','th'=>'ฟีเจอร์สมาชิก VIP','ko'=>'VIP 구독 기능'],
            'about_vip'       => ['fa'=>'کاربران ویژه از دانلود موزیک با بالاترین کیفیت، مدیریت لیست پخش شخصی، نمایش متن اصلی و ترجمه، حذف تمامی تبلیغات، پروفایل و اشتراک‌گذاری و آرشیو ویدیویی بهره‌مند می‌شوند.','en'=>'VIP members enjoy highest-quality downloads, personal playlists, original lyrics and translations, no ads, profile and sharing, and the video archive.','ja'=>'VIP会員は最高品質のダウンロード、プレイリスト、原詞と翻訳、広告非表示、プロフィール共有、ビデオアーカイブを利用できます。','es'=>'Los miembros VIP disfrutan de descargas de máxima calidad, listas personales, letras originales y traducciones, sin anuncios, perfil y compartir, y el archivo de vídeos.','pt'=>'Membros VIP têm downloads em alta qualidade, playlists pessoais, letras originais e traduções, sem anúncios, perfil e compartilhamento, e o arquivo de vídeos.','fr'=>'Les membres VIP profitent de téléchargements haute qualité, playlists personnelles, paroles originales et traductions, sans publicité, profil et partage, et l\'archive vidéo.','de'=>'VIP-Mitglieder genießen Downloads in höchster Qualität, persönliche Playlists, Originaltexte und Übersetzungen, keine Werbung, Profil und Teilen sowie das Videoarchiv.','ar'=>'يتمتع أعضاء VIP بتنزيلات بأعلى جودة، وقوائم تشغيل شخصية، وكلمات أصلية وترجمات، وبدون إعلانات، وملف شخصي ومشاركة، وأرشيف الفيديو.','hi'=>'VIP सदस्य उच्चतम गुणवत्ता डाउनलोड, व्यक्तिगत प्लेलिस्ट, मूल बोल और अनुवाद, विज्ञापन-मुक्त, प्रोफ़ाइल साझाकरण और वीडियो संग्रह का आनंद लेते हैं।','th'=>'สมาชิก VIP ได้ดาวน์โหลดคุณภาพสูงสุด เพลย์ลิสต์ เนื้อเพลงและคำแปล ไม่มีโฆษณา','ko'=>'VIP 회원은 최고 품질 다운로드, 개인 재생목록, 원어 가사와 번역, 광고 제거, 프로필 공유, 영상 아카이브를 이용합니다.'],
            'our_commitment'  => ['fa'=>'تعهد ما','en'=>'Our commitment','ja'=>'私たちの約束','es'=>'Nuestro compromiso','pt'=>'Nosso compromisso','fr'=>'Notre engagement','de'=>'Unser Engagement','ar'=>'التزامنا','hi'=>'हमारी प्रतिबद्धता','th'=>'ความมุ่งมั่นของเรา','ko'=>'우리의 약속'],
            'about_commitment'=> ['fa'=>'تیم انیمه موزیک با تمرکز بر کیفیت محتوا، تجربه کاربری برتر و پشتیبانی پاسخگو، متعهد به توسعه مستقل این پلتفرم بر اساس بازخوردهای جامعه کاربری خود است.','en'=>'The Anime Music team is committed to quality content, great user experience and responsive support, evolving the platform with our community\'s feedback.','ja'=>'アニメミュージックチームは、質の高いコンテンツ、優れたUX、迅速なサポートに尽力し、コミュニティの声で進化します。','es'=>'El equipo de Anime Music está comprometido con contenido de calidad, gran experiencia de usuario y soporte ágil, evolucionando con la comunidad.','pt'=>'A equipe do Anime Music está comprometida com conteúdo de qualidade, ótima experiência e suporte ágil, evoluindo com a comunidade.','fr'=>'L\'équipe d\'Anime Music s\'engage à fournir un contenu de qualité, une excellente expérience et un support réactif, en évoluant avec la communauté.','de'=>'Das Anime-Music-Team steht für Qualität, großartige UX und reaktionsschnellen Support und entwickelt die Plattform mit der Community weiter.','ar'=>'يلتزم فريق أنمي ميوزك بجودة المحتوى وتجربة استخدام رائعة ودعم سريع، مع التطور مع المجتمع.','hi'=>'एनीमे म्यूजिक टीम गुणवत्तापूर्ण सामग्री, बेहतरीन अनुभव और त्वरित सहायता के लिए प्रतिबद्ध है।','th'=>'ทีม Anime Music มุ่งมั่นในเนื้อหาคุณภาพ ประสบการณ์ที่ดี และการสนับสนุนที่รวดเร็ว','ko'=>'애니 뮤직 팀은 양질의 콘텐츠, 뛰어난 사용자 경험, 빠른 지원으로 커뮤니티와 함께 성장합니다.'],
            'team_active_members' => ['fa'=>'برخی از عضو های فعال تیم','en'=>'Some active team members','ja'=>'活躍中のチームメンバー','es'=>'Algunos miembros activos del equipo','pt'=>'Alguns membros ativos da equipe','fr'=>'Quelques membres actifs de l\'équipe','de'=>'Einige aktive Teammitglieder','ar'=>'بعض الأعضاء النشطين في الفريق','hi'=>'कुछ सक्रिय टीम सदस्य','th'=>'สมาชิกทีมที่ทำงานอยู่','ko'=>'활동 중인 팀원들'],
            'book_ad_space'   => ['fa'=>'فضای تبلیغاتی خود را در رسانه من رزرو کنید','en'=>'Book your ad space on our media','ja'=>'当メディアで広告枠を予約しましょう','es'=>'Reserva tu espacio publicitario en nuestro medio','pt'=>'Reserve seu espaço publicitário em nossa mídia','fr'=>'Réservez votre espace publicitaire sur notre média','de'=>'Buchen Sie Ihren Werbeplatz auf unserem Medium','ar'=>'احجز مساحتك الإعلانية على وسيلتنا','hi'=>'हमारे मीडिया पर अपना विज्ञापन स्थान बुक करें','th'=>'จองพื้นที่โฆษณาบนสื่อของเรา','ko'=>'저희 미디어에 광고 공간을 예약하세요'],
            'book_ad'         => ['fa'=>'رزرو تبلیغات','en'=>'Book ads','ja'=>'広告を予約','es'=>'Reservar anuncios','pt'=>'Reservar anúncios','fr'=>'Réserver des pubs','de'=>'Werbung buchen','ar'=>'حجز الإعلانات','hi'=>'विज्ञापन बुक करें','th'=>'จองโฆษณา','ko'=>'광고 예약'],
            'our_sponsors'    => ['fa'=>'حامیان ما','en'=>'Our sponsors','ja'=>'スポンサー','es'=>'Nuestros patrocinadores','pt'=>'Nossos patrocinadores','fr'=>'Nos sponsors','de'=>'Unsere Sponsoren','ar'=>'رعاتنا','hi'=>'हमारे प्रायोजक','th'=>'ผู้สนับสนุนของเรา','ko'=>'후원사'],
            'exchanges'       => ['fa'=>'تبادلات و همکاری‌ها','en'=>'Exchanges & partnerships','ja'=>'交流・提携','es'=>'Intercambios y alianzas','pt'=>'Parcerias','fr'=>'Échanges et partenariats','de'=>'Austausch & Partnerschaften','ar'=>'التبادلات والشراكات','hi'=>'आदान-प्रदान और साझेदारी','th'=>'การแลกเปลี่ยนและความร่วมมือ','ko'=>'교류 및 제휴'],
            'user_panel'      => ['fa'=>'پنل کاربری','en'=>'User panel','ja'=>'ユーザーパネル','es'=>'Panel de usuario','pt'=>'Painel do usuário','fr'=>"Panneau utilisateur",'de'=>'Benutzerbereich','ar'=>'لوحة المستخدم','hi'=>'उपयोगकर्ता पैनल','th'=>'แผงผู้ใช้','ko'=>'사용자 패널'],
            'buy_vip_title'   => ['fa'=>'خرید اشتراک VIP','en'=>'Buy VIP subscription','ja'=>'VIPサブスクリプション購入','es'=>'Comprar suscripción VIP','pt'=>'Comprar assinatura VIP','fr'=>"Acheter l'abonnement VIP",'de'=>'VIP-Abo kaufen','ar'=>'شراء اشتراك VIP','hi'=>'VIP सदस्यता खरीदें','th'=>'ซื้อแพ็กเกจ VIP','ko'=>'VIP 구독 구매'],
            'login_title'     => ['fa'=>'ورود','en'=>'Login','ja'=>'ログイン','es'=>'Iniciar sesión','pt'=>'Entrar','fr'=>'Connexion','de'=>'Anmelden','ar'=>'تسجيل الدخول','hi'=>'लॉगिन','th'=>'เข้าสู่ระบบ','ko'=>'로그인'],
            'register_title'  => ['fa'=>'ثبت‌نام','en'=>'Sign up','ja'=>'登録','es'=>'Registrarse','pt'=>'Cadastrar','fr'=>"S'inscrire",'de'=>'Registrieren','ar'=>'التسجيل','hi'=>'साइन अप','th'=>'สมัคร','ko'=>'가입'],
            'complete_purchase' => ['fa'=>'تکمیل خرید','en'=>'Complete purchase','ja'=>'購入完了','es'=>'Completar compra','pt'=>'Concluir compra','fr'=>'Finaliser l\'achat','de'=>'Kauf abschließen','ar'=>'إكمال الشراء','hi'=>'खरीदारी पूर्ण करें','th'=>'ทำรายการให้เสร็จ','ko'=>'구매 완료'],
            'free_vip_request'=> ['fa'=>'درخواست اشتراک رایگان','en'=>'Request free subscription','ja'=>'無料サブスク申し込み','es'=>'Solicitar suscripción gratuita','pt'=>'Solicitar assinatura grátis','fr'=>'Demander un abonnement gratuit','de'=>'Kostenloses Abo anfordern','ar'=>'طلب اشتراك مجاني','hi'=>'निःशुल्क सदस्यता अनुरोध','th'=>'ขอรับแพ็กเกจฟรี','ko'=>'무료 구독 신청'],
            'back_home'       => ['fa'=>'بازگشت به صفحه اصلی','en'=>'Back to home','ja'=>'ホームに戻る','es'=>'Volver al inicio','pt'=>'Voltar ao início','fr'=>"Retour à l'accueil",'de'=>'Zurück zur Startseite','ar'=>'العودة إلى الرئيسية','hi'=>'होम पर वापस','th'=>'กลับหน้าแรก','ko'=>'홈으로 돌아가기'],
            'more_info'       => ['fa'=>'اطلاعات بیشتر','en'=>'More info','ja'=>'詳細','es'=>'Más información','pt'=>'Mais informações','fr'=>"Plus d'infos",'de'=>'Mehr Infos','ar'=>'مزيد من المعلومات','hi'=>'अधिक जानकारी','th'=>'ข้อมูลเพิ่มเติม','ko'=>'더 알아보기'],
            'skip_ad'         => ['fa'=>'رد کردن تبلیغ','en'=>'Skip ad','ja'=>'広告をスキップ','es'=>'Saltar anuncio','pt'=>'Pular anúncio','fr'=>'Passer la pub','de'=>'Werbung überspringen','ar'=>'تخطي الإعلان','hi'=>'विज्ञापन छोड़ें','th'=>'ข้ามโฆษณา','ko'=>'광고 건너뛰기'],
            'enable_sound'    => ['fa'=>'🔊 فعال کردن صدا','en'=>'🔊 Enable sound','ja'=>'🔊 音を有効化','es'=>'🔊 Activar sonido','pt'=>'🔊 Ativar som','fr'=>'🔊 Activer le son','de'=>'🔊 Ton aktivieren','ar'=>'🔊 تفعيل الصوت','hi'=>'🔊 ध्वनि चालू करें','th'=>'🔊 เปิดเสียง','ko'=>'🔊 소리 켜기'],
            'download_vip'    => ['fa'=>'دانلود (VIP)','en'=>'Download (VIP)','ja'=>'ダウンロード (VIP)','es'=>'Descargar (VIP)','pt'=>'Baixar (VIP)','fr'=>'Télécharger (VIP)','de'=>'Herunterladen (VIP)','ar'=>'تحميل (VIP)','hi'=>'डाउनलोड (VIP)','th'=>'ดาวน์โหลด (VIP)','ko'=>'다운로드 (VIP)'],
            'lyrics_vip_msg'  => ['fa'=>'برای مشاهده متن آهنگ باید اشتراک VIP داشته باشید','en'=>'You need a VIP subscription to view lyrics','ja'=>'歌詞を見るにはVIPサブスクリプションが必要です','es'=>'Necesitas suscripción VIP para ver la letra','pt'=>'Você precisa de assinatura VIP para ver a letra','fr'=>"Abonnement VIP requis pour voir les paroles",'de'=>'VIP-Abo erforderlich, um den Text zu sehen','ar'=>'تحتاج اشتراك VIP لعرض الكلمات','hi'=>'बोल देखने के लिए VIP सदस्यता आवश्यक है','th'=>'ต้องสมัคร VIP เพื่อดูเนื้อเพลง','ko'=>'가사를 보려면 VIP 구독이 필요합니다'],
            'description_title' => ['fa'=>'خلاصه توضیحات','en'=>'Description','ja'=>'説明','es'=>'Descripción','pt'=>'Descrição','fr'=>'Description','de'=>'Beschreibung','ar'=>'الوصف','hi'=>'विवरण','th'=>'คำอธิบาย','ko'=>'설명'],
            'upgrade_vip'     => ['fa'=>'ارتقاء به VIP','en'=>'Upgrade to VIP','ja'=>'VIPへアップグレード','es'=>'Mejorar a VIP','pt'=>'Assinar VIP','fr'=>'Passer VIP','de'=>'VIP werden','ar'=>'الترقية إلى VIP','hi'=>'VIP बनें','th'=>'อัปเกรดเป็น VIP','ko'=>'VIP로 업그레이드'],
            'login_signup'    => ['fa'=>'ورود / ثبت‌نام','en'=>'Login / Sign up','ja'=>'ログイン / 登録','es'=>'Acceder / Registrarse','pt'=>'Entrar / Cadastrar','fr'=>'Connexion / Inscription','de'=>'Anmelden / Registrieren','ar'=>'تسجيل الدخول / الاشتراك','hi'=>'लॉगिन / साइन अप','th'=>'เข้าสู่ระบบ / สมัคร','ko'=>'로그인 / 가입'],
            'download_music'  => ['fa'=>'دانلود موزیک','en'=>'Download music','ja'=>'音楽をダウンロード','es'=>'Descargar música','pt'=>'Baixar música','fr'=>'Télécharger la musique','de'=>'Musik herunterladen','ar'=>'تحميل الموسيقى','hi'=>'संगीत डाउनलोड करें','th'=>'ดาวน์โหลดเพลง','ko'=>'음악 다운로드'],
            'add_favorite'    => ['fa'=>'افزودن به علاقه‌مندی','en'=>'Add to favorites','ja'=>'お気に入りに追加','es'=>'Añadir a favoritos','pt'=>'Adicionar aos favoritos','fr'=>'Ajouter aux favoris','de'=>'Zu Favoriten hinzufügen','ar'=>'إضافة إلى المفضلة','hi'=>'पसंदीदा में जोड़ें','th'=>'เพิ่มในรายการโปรด','ko'=>'즐겨찾기에 추가'],
            'remove_favorite' => ['fa'=>'حذف از علاقه‌مندی','en'=>'Remove from favorites','ja'=>'お気に入りから削除','es'=>'Quitar de favoritos','pt'=>'Remover dos favoritos','fr'=>'Retirer des favoris','de'=>'Aus Favoriten entfernen','ar'=>'إزالة من المفضلة','hi'=>'पसंदीदा से हटाएं','th'=>'ลบออกจากรายการโปรด','ko'=>'즐겨찾기에서 제거'],
            'add_playlist'    => ['fa'=>'افزودن به پلی‌لیست','en'=>'Add to playlist','ja'=>'プレイリストに追加','es'=>'Añadir a la lista','pt'=>'Adicionar à playlist','fr'=>'Ajouter à la playlist','de'=>'Zur Playlist hinzufügen','ar'=>'إضافة إلى قائمة التشغيل','hi'=>'प्लेलिस्ट में जोड़ें','th'=>'เพิ่มในเพลย์ลิสต์','ko'=>'재생목록에 추가'],
            'lyrics'          => ['fa'=>'متن آهنگ','en'=>'Lyrics','ja'=>'歌詞','es'=>'Letra','pt'=>'Letra','fr'=>'Paroles','de'=>'Liedtext','ar'=>'كلمات الأغنية','hi'=>'बोल','th'=>'เนื้อเพลง','ko'=>'가사'],
            'original_lyrics' => ['fa'=>'متن اصلی','en'=>'Original lyrics','ja'=>'原詞','es'=>'Letra original','pt'=>'Letra original','fr'=>'Paroles originales','de'=>'Originaltext','ar'=>'الكلمات الأصلية','hi'=>'मूल बोल','th'=>'เนื้อเพลงต้นฉบับ','ko'=>'원어 가사'],
            'lyrics_translation' => ['fa'=>'ترجمه فارسی','en'=>'Translation','ja'=>'翻訳','es'=>'Traducción','pt'=>'Tradução','fr'=>'Traduction','de'=>'Übersetzung','ar'=>'الترجمة','hi'=>'अनुवाद','th'=>'คำแปล','ko'=>'번역'],
            'singer'          => ['fa'=>'خواننده','en'=>'Singer','ja'=>'歌手','es'=>'Cantante','pt'=>'Cantor','fr'=>'Chanteur','de'=>'Sänger','ar'=>'المغني','hi'=>'गायक','th'=>'นักร้อง','ko'=>'가수'],
            'choose_music'    => ['fa'=>'انتخاب','en'=>'Select','ja'=>'選択','es'=>'Elegir','pt'=>'Escolher','fr'=>'Choisir','de'=>'Auswählen','ar'=>'اختيار','hi'=>'चुनें','th'=>'เลือก','ko'=>'선택'],
            'related'         => ['fa'=>'مطالب مرتبط','en'=>'Related','ja'=>'関連','es'=>'Relacionados','pt'=>'Relacionados','fr'=>'Contenus liés','de'=>'Ähnliche','ar'=>'محتوى ذو صلة','hi'=>'संबंधित','th'=>'เกี่ยวข้อง','ko'=>'관련'],
        ];
    }
}
