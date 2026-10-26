(function () {
  'use strict';

  var body = document.body;
  var toggle = document.getElementById('navToggle');
  var scrim = document.getElementById('appScrim');
  var sidebar = document.getElementById('appSidebar');

  
  function setNav(open) {
    body.classList.toggle('nav-open', open);
    if (toggle) {
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    }
  }

  if (toggle && sidebar) {
    toggle.addEventListener('click', function () {
      setNav(!body.classList.contains('nav-open'));
    });
    if (scrim) {
      scrim.addEventListener('click', function () { setNav(false); });
    }
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && body.classList.contains('nav-open')) {
        setNav(false);
        toggle.focus();
      }
    });
    window.matchMedia('(min-width: 901px)').addEventListener('change', function (e) {
      if (e.matches) { setNav(false); }
    });
  }

  
  document.querySelectorAll('.flash').forEach(function (el) {
    function dismiss() {
      el.classList.add('is-leaving');
      setTimeout(function () { el.remove(); }, 300);
    }
    var close = el.querySelector('.flash-close');
    if (close) { close.addEventListener('click', dismiss); }
    if (el.classList.contains('flash-success')) { setTimeout(dismiss, 6000); }
  });
})();
