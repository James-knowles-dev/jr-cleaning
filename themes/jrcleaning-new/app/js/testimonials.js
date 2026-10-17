/* Testimonials slider: infinite loop (clones + silent recenter), 3 per view desktop / 1 mobile.
   Native swipe + prev/next arrows. No dependencies. */
(function () {
  document.querySelectorAll('[data-slider]').forEach(function (slider) {
    var track = slider.querySelector('.testimonials__track');
    if (!track) { return; }
    var prev = slider.querySelector('[data-dir="prev"]');
    var next = slider.querySelector('[data-dir="next"]');
    var originals = Array.prototype.slice.call(track.children);

    // Nothing to scroll → static (hide arrows).
    if (track.scrollWidth <= track.clientWidth + 4) { slider.classList.add('is-static'); return; }

    var W = 0, idle, rz;

    function setInstant(px) {
      var prevBehavior = track.style.scrollBehavior;
      track.style.scrollBehavior = 'auto';
      track.scrollLeft = px;
      // force reflow so the next smooth scroll starts from here
      void track.offsetWidth;
      track.style.scrollBehavior = prevBehavior;
    }

    function build() {
      // remove old clones, keep originals
      track.querySelectorAll('[data-clone]').forEach(function (c) { c.remove(); });
      var before = document.createDocumentFragment();
      var after = document.createDocumentFragment();
      originals.forEach(function (o) {
        var a = o.cloneNode(true); a.setAttribute('data-clone', '1'); a.setAttribute('aria-hidden', 'true'); after.appendChild(a);
        var b = o.cloneNode(true); b.setAttribute('data-clone', '1'); b.setAttribute('aria-hidden', 'true'); before.appendChild(b);
      });
      track.appendChild(after);
      track.insertBefore(before, track.firstChild);
      W = originals[0].offsetLeft;     // width of the leading clone block
      setInstant(W);                   // start on the first real card
    }

    // After scrolling settles, jump by exactly one set-width if we've drifted
    // into a clone region. Clones are identical, so the jump is invisible.
    function recenter() {
      if (track.scrollLeft >= 2 * W) { setInstant(track.scrollLeft - W); }
      else if (track.scrollLeft < W) { setInstant(track.scrollLeft + W); }
    }

    function step(dir) { track.scrollBy({ left: dir * track.clientWidth, behavior: 'smooth' }); }

    build();
    track.addEventListener('scroll', function () { clearTimeout(idle); idle = setTimeout(recenter, 90); }, { passive: true });
    if (prev) { prev.addEventListener('click', function () { step(-1); }); }
    if (next) { next.addEventListener('click', function () { step(1); }); }
    window.addEventListener('resize', function () { clearTimeout(rz); rz = setTimeout(build, 150); });
  });
})();
