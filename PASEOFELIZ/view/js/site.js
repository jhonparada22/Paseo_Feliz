// ============================================================
// PASEO FELIZ — interacciones compartidas por todo el sitio
// Progressive enhancement: si algo falla, el sitio sigue funcionando
// igual (nada de esto es necesario para navegar o comprar).
// ============================================================
(function () {
  var prefersReducedMotion = window.matchMedia &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // ── Revelado al hacer scroll ────────────────────────────────
  var revealEls = document.querySelectorAll('.reveal');
  if (prefersReducedMotion || !('IntersectionObserver' in window)) {
    revealEls.forEach(function (el) { el.classList.add('is-visible'); });
  } else {
    revealEls.forEach(function (el, i) {
      el.style.setProperty('--pf-i', i % 6);
    });
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
    revealEls.forEach(function (el) { io.observe(el); });

    // Red de seguridad: si por lo que sea (viewport atípico, error del
    // observer) algo se queda sin revelar, se muestra igual. El contenido
    // nunca debe depender 100% de que el usuario haga scroll a tiempo.
    window.addEventListener('load', function () {
      setTimeout(function () {
        revealEls.forEach(function (el) { el.classList.add('is-visible'); });
      }, 2500);
    });
  }

  // ── Header: se compacta al hacer scroll ─────────────────────
  var header = document.getElementById('pfHeader');
  if (header) {
    var onScroll = function () {
      if (window.scrollY > 12) {
        header.classList.add('pf-scrolled');
      } else {
        header.classList.remove('pf-scrolled');
      }
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  // ── Anclas suaves (ej. "Descubre más" del hero) ─────────────
  document.querySelectorAll('a[href^="#pf-"]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      var target = document.querySelector(a.getAttribute('href'));
      if (!target) return;
      e.preventDefault();
      target.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth', block: 'start' });
    });
  });
})();
