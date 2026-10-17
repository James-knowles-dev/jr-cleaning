/* Service FAQ accordion. */
(function () {
  var expanded = [];

  function setHeight(item) {
    var panel = item.querySelector('.svc-faq__a');
    if (!panel) { return; }
    panel.style.maxHeight = item.classList.contains('is-open') ? (panel.scrollHeight + 'px') : '0px';
  }

  document.querySelectorAll('[data-faq]').forEach(function (list) {
    list.querySelectorAll('.svc-faq__q').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var item = btn.closest('.svc-faq__item');
        if (!item) { return; }
        var panel = item.querySelector('.svc-faq__a');
        if (!panel) { return; }

        var open = !item.classList.contains('is-open');
        item.classList.toggle('is-open', open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        setHeight(item);

        if (open) {
          if (expanded.indexOf(item) === -1) { expanded.push(item); }
        } else {
          expanded = expanded.filter(function (i) { return i !== item; });
        }
      });
    });
  });

  // maxHeight is a fixed pixel value, so an open answer clips when the viewport
  // reflows it taller (rotating a phone, resizing). Recompute what's open.
  var resizeTimer;
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () { expanded.forEach(setHeight); }, 150);
  });
})();
