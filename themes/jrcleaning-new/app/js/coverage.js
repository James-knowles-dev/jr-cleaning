/* Coverage checker: validate a UK postcode and test it against served areas */
(function () {
  var input = document.getElementById('cv-postcode');
  var btn   = document.getElementById('cv-check');
  if (!input || !btn) { return; }

  var yes  = document.getElementById('cv-yes');
  var no   = document.getElementById('cv-no');
  var bad  = document.getElementById('cv-invalid');
  var area = document.getElementById('cv-area');

  // Outward-code areas served, mapped to a county name.
  // Editable via Coverage page (ACF) -> window.JRC_COVERAGE; falls back to defaults.
  var COVERED = (window.JRC_COVERAGE && Object.keys(window.JRC_COVERAGE).length) ? window.JRC_COVERAGE : {
    WR: 'Worcestershire', DY: 'Worcestershire', B96: 'Worcestershire', B97: 'Worcestershire', B98: 'Worcestershire',
    GL: 'Gloucestershire',
    CV: 'Warwickshire', B49: 'Warwickshire', B50: 'Warwickshire', B95: 'Warwickshire', B80: 'Warwickshire'
  };
  var UK_RE = /^[A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2}$/i;

  function hideAll() {
    [yes, no, bad].forEach(function (el) { if (el) el.classList.remove('is-visible'); });
  }

  function check() {
    var raw = (input.value || '').trim().toUpperCase();
    hideAll();
    if (!UK_RE.test(raw)) { if (bad) bad.classList.add('is-visible'); return; }

    var outward = raw.replace(/\s+/g, '').slice(0, -3);
    var keys = [outward, outward.replace(/\d.*$/, '')];
    var letters = outward.match(/^[A-Z]+/);
    if (letters) { keys.push(letters[0]); }

    var match = null;
    for (var i = 0; i < keys.length; i++) {
      if (Object.prototype.hasOwnProperty.call(COVERED, keys[i])) { match = COVERED[keys[i]]; break; }
    }

    if (match) { if (area) area.textContent = match; if (yes) yes.classList.add('is-visible'); }
    else { if (no) no.classList.add('is-visible'); }
  }

  btn.addEventListener('click', check);
  input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); check(); } });
})();
