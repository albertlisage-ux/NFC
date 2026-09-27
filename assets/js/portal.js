/**
 * Progressive enhancement only. Every page works with JavaScript disabled:
 * forms post normally, links navigate normally.
 */
(function () {
  'use strict';

  // Mobile navigation
  var toggle = document.querySelector('.nav-toggle');
  var mobile = document.getElementById('nav-mobile');
  if (toggle && mobile) {
    toggle.addEventListener('click', function () {
      var open = mobile.classList.toggle('is-open');
      mobile.hidden = !open;
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  // Asset type switch: show only the metadata fields of the selected type
  var typeInputs = document.querySelectorAll('input[name="type"]');
  if (typeInputs.length) {
    var syncGroups = function () {
      var selected = document.querySelector('input[name="type"]:checked');
      var key = selected ? selected.getAttribute('data-type-key') : null;
      document.querySelectorAll('[data-meta-group]').forEach(function (group) {
        group.classList.toggle('is-active', group.getAttribute('data-meta-group') === key);
      });
    };
    typeInputs.forEach(function (input) { input.addEventListener('change', syncGroups); });
    syncGroups();
  }

  // Copy buttons
  document.querySelectorAll('[data-copy]').forEach(function (button) {
    button.addEventListener('click', function () {
      var value = button.getAttribute('data-copy');
      var label = button.querySelector('span') || button;
      var original = label.textContent;
      var done = function () {
        label.textContent = button.getAttribute('data-copy-label') || 'Copied';
        window.setTimeout(function () { label.textContent = original; }, 1800);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(value).then(done, done);
      } else {
        var area = document.createElement('textarea');
        area.value = value;
        document.body.appendChild(area);
        area.select();
        try { document.execCommand('copy'); } catch (error) { /* ignore */ }
        document.body.removeChild(area);
        done();
      }
    });
  });

  // Confirmation guards
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!window.confirm(form.getAttribute('data-confirm'))) {
        event.preventDefault();
      }
    });
  });

  // Character counter
  document.querySelectorAll('[data-counter]').forEach(function (field) {
    var output = document.querySelector(field.getAttribute('data-counter'));
    if (!output) { return; }
    var max = parseInt(field.getAttribute('maxlength') || '0', 10) || 0;
    var update = function () {
      output.textContent = max ? field.value.length + ' / ' + max : String(field.value.length);
    };
    field.addEventListener('input', update);
    update();
  });

  /**
   * Asset type tabs on the use cases page.
   *
   * Visibility is handled by CSS through the URL fragment, so the page already
   * works without this script. Here we only add the keyboard and screen reader
   * behaviour of a real tab list and keep the ARIA state in step with the hash.
   */
  document.querySelectorAll('[data-tabs]').forEach(function (root) {
    var tabs = Array.prototype.slice.call(root.querySelectorAll('[role="tab"]'));
    if (!tabs.length) {
      return;
    }

    var panelIdOf = function (tab) {
      return tab.getAttribute('aria-controls');
    };

    var activeIndex = function () {
      var hash = window.location.hash.replace('#', '');
      var index = tabs.findIndex(function (tab) {
        return tab.getAttribute('href') === '#' + hash;
      });
      return index === -1 ? 0 : index;
    };

    var sync = function () {
      var current = activeIndex();
      tabs.forEach(function (tab, index) {
        var selected = index === current;
        tab.setAttribute('aria-selected', selected ? 'true' : 'false');
        tab.setAttribute('tabindex', selected ? '0' : '-1');
        var panel = document.getElementById(panelIdOf(tab));
        if (panel) {
          panel.setAttribute('aria-hidden', selected ? 'false' : 'true');
        }
      });
    };

    var activate = function (index, moveFocus) {
      var tab = tabs[(index + tabs.length) % tabs.length];
      if (!tab) {
        return;
      }
      if (window.location.hash !== tab.getAttribute('href')) {
        window.location.hash = tab.getAttribute('href');
      }
      sync();
      if (moveFocus) {
        tab.focus();
        var panel = document.getElementById(panelIdOf(tab));
        if (panel && panel.scrollIntoView) {
          panel.scrollIntoView({ block: 'nearest' });
        }
      }
    };

    tabs.forEach(function (tab, index) {
      tab.addEventListener('click', function () {
        sync();
      });
      tab.addEventListener('keydown', function (event) {
        var key = event.key;
        if (key === 'ArrowRight' || key === 'ArrowDown') {
          event.preventDefault();
          activate(index + 1, true);
        } else if (key === 'ArrowLeft' || key === 'ArrowUp') {
          event.preventDefault();
          activate(index - 1, true);
        } else if (key === 'Home') {
          event.preventDefault();
          activate(0, true);
        } else if (key === 'End') {
          event.preventDefault();
          activate(tabs.length - 1, true);
        }
      });
    });

    window.addEventListener('hashchange', sync);
    sync();
  });
})();
