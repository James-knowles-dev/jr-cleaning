/* Header: mobile menu toggle + home scrolled state */
(function () {
  var burger = document.getElementById('jrc-burger');
  var drawer = document.getElementById('jrc-mobile-nav');

  // Flag desktop dropdown parents for assistive tech.
  Array.prototype.forEach.call(document.querySelectorAll('.site-nav .menu-item-has-children > a'), function (a) {
    a.setAttribute('aria-haspopup', 'true');
  });

  if (burger && drawer) {
    var bar = document.querySelector('.site-header__bar');

    function isOpen() {
      return drawer.classList.contains('is-open');
    }

    // Everything reachable by keyboard while the drawer is open. The burger is part of
    // the set because it stays visible above the drawer and is how the menu is closed —
    // trapping it out would leave a keyboard user with no way back to it. Read fresh on
    // every call: the submenu toggles below are injected after this runs, and expanding
    // a submenu changes what is visible.
    function focusables() {
      var nodes = drawer.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])');
      var out = [burger];
      Array.prototype.forEach.call(nodes, function (el) {
        // offsetParent is null for anything inside a collapsed submenu.
        if (el.offsetParent !== null) { out.push(el); }
      });
      return out;
    }

    function setOpen(open) {
      drawer.classList.toggle('is-open', open);
      burger.classList.toggle('is-open', open);
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
      burger.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
      drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
      document.body.classList.toggle('menu-open', open);

      // Anchor the scrollable drawer directly below the header bar.
      if (open && bar) {
        drawer.style.setProperty('--mobile-nav-top', Math.round(bar.getBoundingClientRect().bottom) + 'px');
      }

      // Collapse any open submenus when the drawer closes.
      if (!open) {
        Array.prototype.forEach.call(drawer.querySelectorAll('.menu-item-has-children.is-expanded'), function (li) {
          li.classList.remove('is-expanded');
          var t = li.querySelector('.mobile-nav__toggle');
          if (t) { t.setAttribute('aria-expanded', 'false'); }
        });
      }

      // Hand focus to the first item in the drawer so a keyboard or screen reader user
      // lands inside the menu they just opened rather than continuing through the page
      // behind it. The drawer follows the header in the DOM, so this is a step forward,
      // not a jump.
      if (open) {
        var first = focusables()[1];
        if (first) { first.focus(); }
      } else if (drawer.contains(document.activeElement)) {
        // The drawer is display:none once closed, so focus sitting inside it would be
        // dropped to <body> and the next Tab would restart from the top of the page.
        // Covers the resize path below; Escape already returns focus explicitly.
        burger.focus();

        // On the resize-to-desktop path the burger is itself display:none by now, and
        // focus() on a hidden element is a no-op — fall back to the logo, the first
        // thing in the header either way.
        if (drawer.contains(document.activeElement)) {
          var brand = document.querySelector('.site-header__brand');
          if (brand) { brand.focus(); }
        }
      }
    }

    // Keep Tab inside the drawer while it is open — the rest of the page is behind a
    // full-screen overlay, so tabbing into it strands focus somewhere invisible.
    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Tab' || !isOpen()) { return; }

      var items = focusables();
      if (!items.length) { return; }

      var first = items[0];
      var last = items[items.length - 1];
      var active = document.activeElement;

      // Focus escaped the set entirely (a click on the backdrop, say) — pull it back.
      if (items.indexOf(active) === -1) {
        e.preventDefault();
        (e.shiftKey ? last : first).focus();
        return;
      }
      if (e.shiftKey && active === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && active === last) {
        e.preventDefault();
        first.focus();
      }
    });

    burger.addEventListener('click', function () {
      setOpen(!isOpen());
    });

    // Escape closes the drawer and hands focus back to the control that opened it.
    document.addEventListener('keydown', function (e) {
      if ((e.key === 'Escape' || e.key === 'Esc') && isOpen()) {
        setOpen(false);
        burger.focus();
      }
    });

    // Close when the viewport grows to desktop. The burger is hidden there, so a
    // drawer left open would otherwise be stuck on screen with no way to dismiss it.
    var resizeTimer;
    window.addEventListener('resize', function () {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function () {
        if (isOpen() && window.getComputedStyle(burger).display === 'none') {
          setOpen(false);
        }
      }, 150);
    });

    // Accordion: inject a caret toggle beside each parent item so its children
    // expand in place (the parent link itself still navigates).
    Array.prototype.forEach.call(drawer.querySelectorAll('.mobile-nav__menu .menu-item-has-children'), function (li) {
      var sub  = li.querySelector('.sub-menu');
      var link = li.querySelector('a');
      if (!sub || !link) { return; }
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'mobile-nav__toggle';
      btn.setAttribute('aria-label', 'Toggle ' + link.textContent.trim() + ' submenu');
      btn.setAttribute('aria-expanded', 'false');
      li.insertBefore(btn, sub);
      btn.addEventListener('click', function () {
        var exp = li.classList.toggle('is-expanded');
        btn.setAttribute('aria-expanded', exp ? 'true' : 'false');
      });
    });
  }

  // Add .scrolled to body once past the hero (used by the transparent home header).
  if (document.body.classList.contains('is-home')) {
    var ticking = false;
    var onScroll = function () {
      if (ticking) { return; }
      ticking = true;
      window.requestAnimationFrame(function () {
        document.body.classList.toggle('scrolled', window.scrollY > 40);
        ticking = false;
      });
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }
})();
