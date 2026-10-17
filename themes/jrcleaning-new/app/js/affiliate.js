/* Copy a referral code to the clipboard. Wires up every .aff-code__copy button
   (affiliate dashboard + the My Account snapshot card). */
(function () {
  document.querySelectorAll('.aff-code__copy').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var code = btn.getAttribute('data-copy') || '';
      var done = function () {
        var original = btn.textContent;
        btn.textContent = 'Copied';
        btn.classList.add('is-copied');
        setTimeout(function () { btn.textContent = original; btn.classList.remove('is-copied'); }, 1600);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(code).then(done).catch(done);
      } else {
        var t = document.createElement('textarea');
        t.value = code; document.body.appendChild(t); t.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(t); done();
      }
    });
  });
})();

/* Referral attribution.
   The dashboard hands affiliates a link like /quote/?ref=JRC-TANNER. Capture that
   code, remember it for the session (so it survives navigating between pages before
   they reach the form), and drop it into the quote form.

   SETUP: add a Hidden field to the quote and contact forms with the CSS class
   `qp-ref`. Without it the code is still stored but has nowhere to be submitted,
   which is why the affiliate can always quote the code manually as a fallback. */
(function () {
  var KEY = 'jrcRef';

  function stored() {
    try { return window.sessionStorage.getItem(KEY) || ''; } catch (e) { return ''; }
  }

  function remember(code) {
    try { window.sessionStorage.setItem(KEY, code); } catch (e) {}
  }

  var fromUrl = '';
  try {
    fromUrl = (new URLSearchParams(window.location.search).get('ref') || '').trim();
  } catch (e) {}

  // Codes are of the form JRC-SURNAME; keep it tight so a junk querystring can't
  // be injected into the form.
  if (fromUrl && /^[A-Za-z0-9-]{1,32}$/.test(fromUrl)) {
    remember(fromUrl.toUpperCase());
  }

  var code = stored();
  if (!code) { return; }

  function fill() {
    document.querySelectorAll('.qp-ref').forEach(function (wrap) {
      var f = wrap.querySelector('input, select, textarea');
      if (f && !f.value) { f.value = code; }
    });
  }

  fill();
  // Gravity Forms re-renders on AJAX page changes in the multi-step quote form.
  document.addEventListener('gform_page_loaded', fill);
  document.addEventListener('gform_post_render', fill);
})();

/**
 * Job detail panels on the partner calendar.
 *
 * The panels are opened by :target, so they already work without this file. This adds
 * the behaviour a keyboard or screen-reader user expects from a dialog: Escape closes
 * it, focus moves in when it opens and back to the calendar entry when it closes, and
 * Tab stays inside while it is open.
 */
(function () {
	'use strict';

	var opener = null;

	function current() {
		if (!window.location.hash) { return null; }
		var el = document.getElementById(window.location.hash.slice(1));
		return el && el.classList.contains('aff-modal') ? el : null;
	}

	function close() {
		var open = current();
		if (!open) { return; }

		// Drop the hash without adding a history entry, so Back doesn't reopen it.
		history.replaceState(null, '', window.location.pathname + window.location.search + '#calendar');

		// Safari needs a nudge to re-evaluate :target after replaceState.
		open.style.display = 'none';
		window.setTimeout(function () { open.style.display = ''; }, 0);

		if (opener) { opener.focus(); opener = null; }
	}

	document.addEventListener('click', function (e) {
		var ev = e.target.closest('.aff-cal__ev');
		if (ev) { opener = ev; }
	});

	document.addEventListener('keydown', function (e) {
		var open = current();
		if (!open) { return; }

		if (e.key === 'Escape') {
			e.preventDefault();
			close();
			return;
		}

		if (e.key !== 'Tab') { return; }

		var focusable = open.querySelectorAll('a[href], button:not([disabled])');
		if (!focusable.length) { return; }

		// Skip the scrim, which is deliberately not tabbable.
		var list = Array.prototype.filter.call(focusable, function (el) {
			return el.getAttribute('tabindex') !== '-1';
		});
		if (!list.length) { return; }

		var first = list[0];
		var last = list[list.length - 1];

		if (e.shiftKey && document.activeElement === first) {
			e.preventDefault();
			last.focus();
		} else if (!e.shiftKey && document.activeElement === last) {
			e.preventDefault();
			first.focus();
		}
	});

	window.addEventListener('hashchange', function () {
		var open = current();
		if (!open) { return; }
		var focusTarget = open.querySelector('.aff-modal__close');
		if (focusTarget) { focusTarget.focus(); }
	});
})();
