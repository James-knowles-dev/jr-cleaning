/* Quote wizard: auto-advance the multi-page Gravity Form when a service card is picked.

   Only advances for pointer input. Keyboard users move through a radio group with the
   arrow keys, which fires `change` on every option they pass — auto-advancing on that
   would skip them past the step before they reached their choice. They use the
   visible Next button instead. */
(function () {
  var viaPointer = false;

  document.addEventListener('pointerdown', function (e) {
    viaPointer = !!(e.target && e.target.closest && e.target.closest('.jrc-cards'));
  }, true);

  document.addEventListener('change', function (e) {
    var t = e.target;
    if (!t || !t.matches || !t.matches('.jrc-cards input[type="radio"]')) { return; }

    if (!viaPointer) { return; }
    viaPointer = false;

    var form = t.closest('form');
    if (!form) { return; }
    var next = form.querySelector('.gform_next_button');
    if (next) { setTimeout(function () { next.click(); }, 220); }
  });
})();
