/* Carousel dot indicators for mobile sliders. Progressive enhancement — the
   carousels work without JS; this adds clickable position dots.
   Wiring: put `js-slider-dots` on an (initially empty) dots container placed
   immediately after its scroller element. */
(function () {
  function initDots(dotsWrap) {
    var slider = dotsWrap.previousElementSibling;
    if (!slider) { return; }

    var cards = Array.prototype.slice.call(slider.children);
    if (cards.length < 2) { dotsWrap.remove(); return; }

    // Distance between two cards (card width + gap). The difference of offsetLeft
    // cancels the shared offset parent, so it's correct regardless of layout.
    function step() {
      return ( cards[1].offsetLeft - cards[0].offsetLeft ) || slider.clientWidth;
    }

    var dots = cards.map(function (card, i) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'slider-dot';
      b.setAttribute('role', 'tab');
      b.setAttribute('aria-label', 'Go to slide ' + ( i + 1 ));
      b.addEventListener('click', function () {
        slider.scrollTo({ left: i * step(), behavior: 'smooth' });
      });
      dotsWrap.appendChild(b);
      return b;
    });

    function setActive() {
      var i = Math.round( slider.scrollLeft / step() );
      if (i < 0) { i = 0; }
      if (i > dots.length - 1) { i = dots.length - 1; }
      dots.forEach(function (d, di) {
        var on = di === i;
        d.classList.toggle('is-active', on);
        d.setAttribute('aria-selected', on ? 'true' : 'false');
      });
    }

    var ticking = false;
    slider.addEventListener('scroll', function () {
      if (ticking) { return; }
      ticking = true;
      window.requestAnimationFrame(function () { setActive(); ticking = false; });
    }, { passive: true });

    setActive();
  }

  document.querySelectorAll('.js-slider-dots').forEach(initDots);
})();
