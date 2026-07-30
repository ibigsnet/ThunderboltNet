/* ThunderboltNet — Settings page helpers */
(function () {
  'use strict';

  window.tbnConfirmReset = function (form) {
    if (!form) {
      return false;
    }
    if (!window.confirm('Reset this Thunderbolt interface to plugin defaults?')) {
      return false;
    }
    // Mark reset for #include handler
    var h = form.querySelector('input[name="tbn_reset"]');
    if (!h) {
      h = document.createElement('input');
      h.type = 'hidden';
      h.name = 'tbn_reset';
      form.appendChild(h);
    }
    h.value = '1';
    return true;
  };

  window.tbnTogglePciDetails = function (btn) {
    if (!btn) {
      return;
    }
    var id = btn.getAttribute('data-target');
    var el = id ? document.getElementById(id) : null;
    if (!el) {
      return;
    }
    var hidden = el.classList.contains('tbn-hidden');
    if (hidden) {
      el.classList.remove('tbn-hidden');
      btn.value = btn.getAttribute('data-hide') || 'Hide PCI / IOMMU details';
    } else {
      el.classList.add('tbn-hidden');
      btn.value = btn.getAttribute('data-show') || 'Show PCI / IOMMU details';
    }
  };

  window.tbnCopyDiagnostics = function () {
    var el = document.getElementById('tbn-diagnostics');
    if (!el) {
      return;
    }
    var text = el.value || el.textContent || '';
    function ok() {
      try {
        el.focus();
        el.select();
      } catch (e) { /* ignore */ }
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(ok).catch(function () {
        ok();
        try {
          document.execCommand('copy');
        } catch (e2) { /* ignore */ }
      });
    } else {
      ok();
      try {
        document.execCommand('copy');
      } catch (e3) { /* ignore */ }
    }
  };
})();
