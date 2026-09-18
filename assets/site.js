/**
 * انیمه موزیک — اسکریپت مشترک سایت (نسخه ۱)
 * بدون وابستگی؛ در همه صفحات لود می‌شود.
 *
 * امکانات:
 *   ۱) انیمیشن ورود هنگام اسکرول (reveal)
 *   ۲) دکمه «بازگشت به بالا»
 *   ۳) «ادامه مطلب / بستن» برای بخش‌های طولانی (کلاس .clamp)
 *   ۴) افکت عمق ملایم کارت‌ها فقط در دسکتاپ
 */
(function () {
  'use strict';

  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- ۰) واژه‌نامه کوچک رابط (برای دکمه‌های ساخته‌شده با JS) ---------- */
  var L = {
    backToTop:   { fa: 'بازگشت به بالا', en: 'Back to top', ja: 'トップへ戻る', es: 'Volver arriba', pt: 'Voltar ao topo', fr: 'Haut de page', de: 'Nach oben', ar: 'العودة إلى الأعلى', hi: 'ऊपर जाएं', th: 'กลับขึ้นบน', ko: '맨 위로' },
    readMore:    { fa: 'مشاهده ادامه', en: 'View more', ja: '続きを見る', es: 'Ver más', pt: 'Ver mais', fr: 'Voir plus', de: 'Mehr anzeigen', ar: 'عرض المزيد', hi: 'और देखें', th: 'ดูเพิ่มเติม', ko: '더 보기' },
    close:       { fa: 'بستن', en: 'Close', ja: '閉じる', es: 'Cerrar', pt: 'Fechar', fr: 'Fermer', de: 'Schließen', ar: 'إغلاق', hi: 'बंद करें', th: 'ปิด', ko: '닫기' }
  };
  function amJS(key) {
    var lang = (document.documentElement.getAttribute('lang') || 'fa').split('-')[0];
    var entry = L[key];
    if (!entry) return key;
    return entry[lang] || entry.en || entry.fa || key;
  }

  /* ---------- ۱) انیمیشن ورود هنگام اسکرول ---------- */
  var revealSel = '.section, .card, .category-card, .plan-card, .benefit-item, ' +
                  '.sponsor-item, .exchange-item, .auth-card, .related-item, ' +
                  '.search-container, .lyrics-section, .related-section, ' +
                  '.player-section, .filters, .plans-section, .lyrics-box';

  function initReveal() {
    var els = document.querySelectorAll(revealSel);
    if (!('IntersectionObserver' in window) || reduced) {
      els.forEach(function (el) { el.classList.add('am-revealed'); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('am-revealed');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.08, rootMargin: '0px 0px -30px 0px' });

    els.forEach(function (el, i) {
      // تاخیر پلکانی خیلی سبک
      if (!el.classList.contains('am-revealed')) {
        el.style.transitionDelay = Math.min(i % 6, 5) * 40 + 'ms';
      }
      io.observe(el);
    });
  }

  /* ---------- ۲) دکمه بازگشت به بالا ---------- */
  function initBackToTop() {
    if (document.getElementById('am-to-top')) return;
    var btn = document.createElement('button');
    btn.id = 'am-to-top';
    btn.title = amJS('backToTop');
    btn.setAttribute('aria-label', amJS('backToTop'));
    btn.innerHTML = '<i class="bi bi-arrow-up-short"></i>';
    document.body.appendChild(btn);
    btn.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: reduced ? 'auto' : 'smooth' });
    });
    var toggle = function () {
      btn.classList.toggle('show', window.scrollY > 600);
    };
    window.addEventListener('scroll', toggle, { passive: true });
    toggle();
  }

  /* ---------- ۳) «ادامه مطلب» برای متن‌های بلند ---------- */
  function initClamp() {
    var els = document.querySelectorAll('.clamp');
    els.forEach(function (el) {
      if (reduced) return;
      el.classList.add('is-clamped');
      // اگر متن کوتاه بود اصلاً دکمه نمی‌سازیم
      if (el.scrollHeight <= el.clientHeight + 8) {
        el.classList.remove('is-clamped');
        el.classList.add('am-revealed');
        return;
      }
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'readmore-btn';
      btn.setAttribute('aria-expanded', 'false');
      btn.innerHTML = '<i class="bi bi-chevron-down"></i> ' + amJS('readMore');
      btn.addEventListener('click', function () {
        var open = el.classList.toggle('is-open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        btn.innerHTML = open
          ? '<i class="bi bi-chevron-up"></i> ' + amJS('close')
          : '<i class="bi bi-chevron-down"></i> ' + amJS('readMore');
      });
      el.parentNode.insertBefore(btn, el.nextSibling);
    });
  }

  /* ---------- ۴) افکت عمق ملایم کارت‌ها (فقط دسکتاپ) ---------- */
  function initTilt() {
    if (reduced) return;
    var fine = window.matchMedia('(hover: hover) and (pointer: fine)');
    if (!fine.matches) return;
    document.querySelectorAll('.content-card, .category-card, .related-item').forEach(function (card) {
      card.addEventListener('mousemove', function (e) {
        var r = card.getBoundingClientRect();
        var x = (e.clientX - r.left) / r.width - 0.5;
        var y = (e.clientY - r.top) / r.height - 0.5;
        card.style.transform =
          'perspective(700px) rotateX(' + (-y * 4).toFixed(2) + 'deg) rotateY(' + (x * 5).toFixed(2) + 'deg) translateY(-3px)';
      });
      card.addEventListener('mouseleave', function () {
        card.style.transform = '';
      });
    });
  }

  /* ---------- ۵) منوی کشویی انتخاب زبان ---------- */
  function initLangMenu() {
    var menues = document.querySelectorAll('details.lang-menu');
    menues.forEach(function (menu) {
      var summary = menu.querySelector('summary.lang-trigger');
      var syncAria = function () {
        if (summary) summary.setAttribute('aria-expanded', menu.open ? 'true' : 'false');
      };
      if (summary) summary.setAttribute('aria-expanded', 'false');
      menu.addEventListener('toggle', syncAria);

      // بستن هنگام کلیک روی یکی از زبان‌ها (ناوبری به آن زبان)
      menu.querySelectorAll('a.lang-item').forEach(function (item) {
        item.addEventListener('click', function () {
          menu.removeAttribute('open');
          syncAria();
        });
      });

      // بستن با کلیک بیرون از منو
      document.addEventListener('click', function (e) {
        if (!menu.contains(e.target)) {
          menu.removeAttribute('open');
          syncAria();
        }
      });

      // بستن با کلید Escape
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
          menu.removeAttribute('open');
          syncAria();
        }
      });
    });
  }

  /* اجرا */
  function boot() {
    // فعال کردن فقط وقتی JS در دسترس است تا بدون JS محتوا مخفی نماند
    document.documentElement.classList.add('js-anim');
    initReveal();
    initBackToTop();
    initClamp();
    initTilt();
    initLangMenu();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
