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
    btn.title = 'بازگشت به بالا';
    btn.setAttribute('aria-label', 'بازگشت به بالا');
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
      btn.innerHTML = '<i class="bi bi-chevron-down"></i> مشاهده ادامه';
      btn.addEventListener('click', function () {
        var open = el.classList.toggle('is-open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        btn.innerHTML = open
          ? '<i class="bi bi-chevron-up"></i> بستن'
          : '<i class="bi bi-chevron-down"></i> مشاهده ادامه';
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

  /* اجرا */
  function boot() {
    // فعال کردن فقط وقتی JS در دسترس است تا بدون JS محتوا مخفی نماند
    document.documentElement.classList.add('js-anim');
    initReveal();
    initBackToTop();
    initClamp();
    initTilt();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
