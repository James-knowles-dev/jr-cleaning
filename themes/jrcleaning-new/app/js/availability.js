/**
 * Request form: tell the partner a date is closed before they submit.
 *
 * <input type="date"> has no API for disabling individual days — min/max is the whole
 * of it, and every attempt to fake one means replacing the native picker with a widget
 * that is worse on a phone and worse with a screen reader. So the native control stays
 * and this reacts to the choice instead: pick a closed day and the reason appears
 * immediately, rather than after a round trip.
 *
 * This is a courtesy, not a control. functions/affiliate-requests.php re-checks every
 * submission against jrc_date_availability() regardless of what happened here.
 */
(function () {
  'use strict';

  var input = document.getElementById('req-date');
  if (!input) { return; }

  var help = document.querySelector('[data-role="date-status"]');
  if (!help) { return; }

  var closed;
  try {
    closed = JSON.parse(input.getAttribute('data-closed') || '{}');
  } catch (e) {
    return;   // Malformed payload: leave the field entirely alone.
  }

  // The default message, so clearing an invalid date restores it rather than
  // leaving the last error on screen.
  var initial = help.textContent;

  function check() {
    var value = input.value;

    if (!value) {
      help.textContent = initial;
      help.classList.remove('is-closed', 'is-ok');
      input.removeAttribute('aria-invalid');
      // setCustomValidity('') clears the block; without it the form stays
      // unsubmittable after the user corrects the date.
      input.setCustomValidity('');
      return;
    }

    var reason = closed[value];

    if (reason) {
      help.textContent = reason;
      help.classList.add('is-closed');
      help.classList.remove('is-ok');
      input.setAttribute('aria-invalid', 'true');
      // A real validity error, so the browser refuses submit and focuses the field —
      // the same behaviour as any other invalid input, for free.
      input.setCustomValidity(reason);
      return;
    }

    help.textContent = 'That date is available.';
    help.classList.add('is-ok');
    help.classList.remove('is-closed');
    input.removeAttribute('aria-invalid');
    input.setCustomValidity('');
  }

  input.addEventListener('change', check);
  input.addEventListener('input', check);

  // Run once on load: a date repopulated after a failed submit may itself be closed.
  check();
})();
