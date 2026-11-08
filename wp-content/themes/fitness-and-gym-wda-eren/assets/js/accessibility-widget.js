(function () {
  'use strict';

  var key = 'erahAccessibilityPreferences';
  var root = document.documentElement;
  var settings = { largeText: false, contrast: false, links: false };
  try {
    var saved = JSON.parse(window.localStorage.getItem(key) || '{}');
    settings.largeText = saved.largeText === true;
    settings.contrast = saved.contrast === true;
    settings.links = saved.links === true;
  } catch (error) { /* Storage may be unavailable in private browsing. */ }

  var widget = document.createElement('div');
  widget.className = 'erah-a11y-widget';
  widget.innerHTML = '<section class="erah-a11y-panel" id="erah-a11y-panel" aria-labelledby="erah-a11y-title" hidden>' +
    '<h2 id="erah-a11y-title">Erişilebilirlik ayarları</h2>' +
    '<button type="button" data-setting="largeText" aria-pressed="false">Yazıları büyüt</button>' +
    '<button type="button" data-setting="contrast" aria-pressed="false">Yüksek kontrast modu</button>' +
    '<button type="button" data-setting="links" aria-pressed="false">Bağlantıları belirginleştir</button>' +
    '<button type="button" data-reset>Ayarları sıfırla</button>' +
    '<button type="button" data-close>Paneli kapat</button>' +
    '</section>' +
    '<button type="button" class="erah-a11y-toggle" aria-label="Erişilebilirlik panelini aç" aria-expanded="false" aria-controls="erah-a11y-panel"><span class="erah-a11y-icon" aria-hidden="true">Aa</span><span>Erişilebilirlik</span></button>';
  document.body.appendChild(widget);

  var panel = widget.querySelector('.erah-a11y-panel');
  var toggle = widget.querySelector('.erah-a11y-toggle');
  var closeButton = widget.querySelector('[data-close]');

  function apply() {
    root.classList.toggle('erah-a11y-text-large', settings.largeText);
    root.classList.toggle('erah-a11y-high-contrast', settings.contrast);
    root.classList.toggle('erah-a11y-highlight-links', settings.links);
    widget.querySelector('[data-setting="largeText"]').setAttribute('aria-pressed', String(settings.largeText));
    widget.querySelector('[data-setting="contrast"]').setAttribute('aria-pressed', String(settings.contrast));
    widget.querySelector('[data-setting="links"]').setAttribute('aria-pressed', String(settings.links));
    try { window.localStorage.setItem(key, JSON.stringify(settings)); } catch (error) { /* Keep the current page settings. */ }
  }

  function setOpen(open, returnFocus) {
    panel.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Erişilebilirlik panelini kapat' : 'Erişilebilirlik panelini aç');
    if (open) closeButton.focus();
    else if (returnFocus) toggle.focus();
  }

  toggle.addEventListener('click', function () { setOpen(panel.hidden, false); });
  closeButton.addEventListener('click', function () { setOpen(false, true); });
  widget.addEventListener('click', function (event) {
    var setting = event.target.closest('[data-setting]');
    if (setting) {
      var name = setting.getAttribute('data-setting');
      settings[name] = !settings[name];
      apply();
      setting.focus();
    }
    if (event.target.closest('[data-reset]')) {
      settings = { largeText: false, contrast: false, links: false };
      apply();
      widget.querySelector('[data-reset]').focus();
    }
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && !panel.hidden) {
      event.preventDefault();
      setOpen(false, true);
    }
  });

  apply();
}());
