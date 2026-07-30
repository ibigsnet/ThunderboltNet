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

  /** Show/hide fabric service + domain rows (default collapsed). */
  window.tbnToggleFabricDetails = function (btn) {
    if (!btn) {
      return;
    }
    var rows = document.querySelectorAll('.tbn-fabric tr.tbn-fabric-detail');
    if (!rows.length) {
      return;
    }
    var hide = !rows[0].classList.contains('tbn-hidden');
    for (var i = 0; i < rows.length; i++) {
      if (hide) {
        rows[i].classList.add('tbn-hidden');
      } else {
        rows[i].classList.remove('tbn-hidden');
      }
    }
    btn.value = hide
      ? (btn.getAttribute('data-show') || 'Show services & domain')
      : (btn.getAttribute('data-hide') || 'Hide services & domain');
  };

  /**
   * Unraid HelpButton toggles $('.inline_help'). BodyInlineJS also makes the matching
   * <dt> clickable when blockquote.inline_help sits as the next sibling after a <dl>.
   * Network Settings can re-paint tab content after our script runs — re-bind once for
   * ThunderboltNet blockquotes that still lack a helpinfo id.
   */
  function tbnBindInlineHelp() {
    if (typeof window.jQuery === 'undefined' && typeof window.$ === 'undefined') {
      return;
    }
    var $ = window.jQuery || window.$;
    var base = 0;
    $('blockquote.inline_help[id^="helpinfo"]').each(function () {
      var m = String(this.id || '').match(/helpinfo(\d+)/);
      if (m) {
        base = Math.max(base, parseInt(m[1], 10) + 1);
      }
    });
    $('.tbn-wrap blockquote.inline_help').each(function () {
      var $bq = $(this);
      if ($bq.attr('id') && String($bq.attr('id')).indexOf('helpinfo') === 0) {
        return;
      }
      var id = 'helpinfo' + base++;
      $bq.attr('id', id);
      var pin = $bq.prev();
      if (!pin.prop('nodeName')) {
        pin = $bq.parent().prev();
      }
      while (pin.prop('nodeName') && String(pin.prop('nodeName')).search(/(table|dl)/i) === -1) {
        pin = pin.prev();
      }
      pin.find('tr:first, dt:last').each(function () {
        var node = $(this);
        var name = String(node.prop('nodeName') || '').toLowerCase();
        if (name === 'dt') {
          while (
            !node.html() ||
            String(node.html()).search(/(<input|<select|nbsp;)/i) >= 0 ||
            name !== 'dt'
          ) {
            if (name === 'dt' && node.is(':first-of-type')) {
              break;
            }
            node = node.prev();
            name = String(node.prop('nodeName') || '').toLowerCase();
          }
          node.css('cursor', 'help').off('click.tbnHelp').on('click.tbnHelp', function () {
            $('#' + id).toggle('slow');
          });
        } else if (node.html() && (name !== 'tr' || node.children('td:first').html())) {
          node.css('cursor', 'help').off('click.tbnHelp').on('click.tbnHelp', function () {
            $('#' + id).toggle('slow');
          });
        }
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      setTimeout(tbnBindInlineHelp, 100);
      setTimeout(tbnBindInlineHelp, 500);
    });
  } else {
    setTimeout(tbnBindInlineHelp, 100);
    setTimeout(tbnBindInlineHelp, 500);
  }

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
