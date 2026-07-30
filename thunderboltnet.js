/* ThunderboltNet — Settings page helpers */
(function () {
  'use strict';

  /**
   * Switch to a Network Settings tab by title text (e.g. "tbn0", "Thunderbolt").
   * Unraid keeps siblings as tabs under /Settings/NetworkSettings; deep links like
   * /Settings/Tbn0 leave the tab strip context. Prefer clicking the existing tab.
   */
  function tbnFindTabButton(needle) {
    var tabs = document.querySelectorAll('.tabs [role="tab"], .tabs a, #menu a, .nav-item');
    var want = (needle || '').toLowerCase();
    if (!want) {
      return null;
    }
    for (var i = 0; i < tabs.length; i++) {
      var t = (tabs[i].textContent || tabs[i].innerText || '').replace(/\s+/g, ' ').trim().toLowerCase();
      if (t.indexOf(want) !== -1) {
        return tabs[i];
      }
    }
    return null;
  }

  window.tbnGotoNetTab = function (needle, evt) {
    if (evt && evt.preventDefault) {
      evt.preventDefault();
    }
    var tab = tbnFindTabButton(needle);
    if (tab) {
      tab.click();
      try {
        tab.scrollIntoView({ block: 'nearest', inline: 'nearest' });
      } catch (e) { /* ignore */ }
      return false;
    }
    // Not on Network Settings (or tabs not ready): remember target and open parent
    try {
      sessionStorage.setItem('tbnWantTab', needle);
    } catch (e2) { /* ignore */ }
    window.location.href = '/Settings/NetworkSettings';
    return false;
  };

  // After navigating to Network Settings, activate requested tab once
  function tbnApplyWantedTab() {
    var want = null;
    try {
      want = sessionStorage.getItem('tbnWantTab');
    } catch (e) {
      return;
    }
    if (!want) {
      return;
    }
    var tab = tbnFindTabButton(want);
    if (tab) {
      try {
        sessionStorage.removeItem('tbnWantTab');
      } catch (e2) { /* ignore */ }
      // Defer so Unraid's own tab cookie init runs first
      setTimeout(function () {
        tab.click();
      }, 50);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', tbnApplyWantedTab);
  } else {
    tbnApplyWantedTab();
  }
  // Unraid may paint tabs slightly later
  setTimeout(tbnApplyWantedTab, 200);
  setTimeout(tbnApplyWantedTab, 600);

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
