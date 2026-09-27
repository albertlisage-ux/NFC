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
})();
