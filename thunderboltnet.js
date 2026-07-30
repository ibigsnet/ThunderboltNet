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

  function tbnShow(el, on, animate) {
    if (!el) {
      return;
    }
    var $ = window.jQuery || window.$;
    var useAnim = animate !== false && $ && typeof $.fn.show === 'function';
    if (useAnim) {
      var $el = $(el);
      if (on) {
        $el.removeClass('tbn-hidden');
        // eth0 uses show('slow') / hide('slow') for bond/protocol blocks
        if ($el.is(':hidden') || $el.css('display') === 'none') {
          $el.stop(true, true).hide().show('slow');
        }
      } else {
        if ($el.is(':visible')) {
          $el.stop(true, true).hide('slow', function () {
            $el.addClass('tbn-hidden');
          });
        } else {
          $el.addClass('tbn-hidden').hide();
        }
      }
      return;
    }
    if (on) {
      el.classList.remove('tbn-hidden');
      el.style.display = '';
    } else {
      el.classList.add('tbn-hidden');
      el.style.display = 'none';
    }
  }

  function tbnSyncBondMembers(form) {
    if (!form) {
      return;
    }
    var hidden = form.querySelector('#tbn-bond-members-hidden') || form.querySelector('input[name="BOND_MEMBERS"]');
    if (!hidden) {
      return;
    }
    var boxes = form.querySelectorAll('input.tbn-bond-member:checked');
    var vals = [];
    for (var i = 0; i < boxes.length; i++) {
      vals.push(boxes[i].value);
    }
    hidden.value = vals.join(' ');
  }

  /**
   * eth0-like show/hide for protocol, DHCP static, bonding, bridging, VLANs.
   * Also enables Apply (Unraid greys Apply until change; Network Settings may bind late).
   */
  window.tbnFormSync = function (form) {
    if (!form) {
      return;
    }
    var slave = form.getAttribute('data-tbn-slave') === '1';
    var proto = (form.PROTOCOL && form.PROTOCOL.value) || 'ipv4';
    var show4 = proto === 'ipv4' || proto === 'ipv4+ipv6';
    var show6 = proto === 'ipv6' || proto === 'ipv4+ipv6';
    tbnShow(form.querySelector('.tbn-proto-ipv4'), show4 && !slave);
    tbnShow(form.querySelector('.tbn-proto-ipv6'), show6 && !slave);

    var dhcp4 = form.USE_DHCP ? form.USE_DHCP.value : 'no';
    var dhcp6 = form.USE_DHCP6 ? form.USE_DHCP6.value : 'no';
    tbnShow(form.querySelector('.tbn-static-ipv4'), show4 && dhcp4 === 'no' && !slave);
    tbnShow(form.querySelector('.tbn-static-ipv6'), show6 && dhcp6 === 'no' && !slave);

    var bond = form.BONDING ? form.BONDING.value : 'no';
    tbnShow(form.querySelector('.tbn-bond-opts'), bond === 'yes' && !slave);

    var bridge = form.BRIDGING ? form.BRIDGING.value : 'no';
    tbnShow(form.querySelector('.tbn-bridge-opts'), bridge === 'yes' && !slave);

    var vlan = form.VLAN_ENABLE ? form.VLAN_ENABLE.value : 'no';
    tbnShow(form.querySelector('.tbn-vlan-opts'), vlan === 'yes' && !slave);

    var mtuMode = form.MTU_MODE ? form.MTU_MODE.value : 'default';
    tbnShow(form.querySelector('.tbn-mtu-custom-wrap'), mtuMode === 'custom' && !slave);
    if (form.USE_MTU) {
      form.USE_MTU.value = mtuMode === 'default' ? 'no' : 'yes';
    }
    if (mtuMode === '9000' && form.MTU && !form.MTU.value) {
      form.MTU.value = '9000';
    }

    tbnSyncBondMembers(form);
  };

  window.tbnEnableFormApply = function (form) {
    if (!form) {
      return;
    }
    var list = form.querySelectorAll(
      'select,input[type=text],input[type=number],input[type=password],input[type=checkbox],input[type=radio],textarea'
    );
    for (var i = 0; i < list.length; i++) {
      list[i].addEventListener('input', function () {
        tbnUnlockApply(form);
        tbnFormSync(form);
      });
      list[i].addEventListener('change', function () {
        tbnUnlockApply(form);
        tbnFormSync(form);
      });
    }
    form.addEventListener('submit', function () {
      tbnSyncBondMembers(form);
    });
  };

  function tbnUnlockApply(form) {
    if (!form) {
      return;
    }
    var subs = form.querySelectorAll('input[type=submit]');
    for (var i = 0; i < subs.length; i++) {
      var v = subs[i].value || '';
      if (v === 'Apply' || v.indexOf('Apply') === 0 || v.indexOf('Harden') === 0) {
        subs[i].disabled = false;
      }
    }
  }

  window.tbnInitIfaceForm = function (form) {
    if (!form) {
      return;
    }
    tbnEnableFormApply(form);
    tbnFormSync(form);
  };

  /**
   * Known peers: save services preference on select change (no grey Apply button).
   * Must submit the Apply control so update.php runs #include.
   */
  window.tbnListenSave = function (form) {
    if (!form) {
      return false;
    }
    var btn = form.querySelector('input[name="#apply"]');
    if (!btn) {
      btn = document.createElement('input');
      btn.type = 'submit';
      btn.name = '#apply';
      btn.value = 'Apply';
      btn.style.display = 'none';
      form.appendChild(btn);
    }
    btn.disabled = false;
    if (typeof form.requestSubmit === 'function') {
      form.requestSubmit(btn);
    } else {
      btn.click();
    }
    return false;
  };

  /** Wire all ThunderboltNet forms (listening, iface, harden) for Apply enable. */
  function tbnWireAllForms() {
    var wraps = document.querySelectorAll('.tbn-wrap form');
    for (var i = 0; i < wraps.length; i++) {
      tbnEnableFormApply(wraps[i]);
      if (wraps[i].classList.contains('tbn-iface-form')) {
        tbnFormSync(wraps[i]);
      }
    }
  }

  function tbnFormatBpsClient(bps) {
    if (bps === null || bps === undefined || bps < 0) {
      return '';
    }
    if (bps < 1024) {
      return '~' + Math.round(bps) + ' B/s';
    }
    if (bps < 1024 * 1024) {
      return '~' + (bps / 1024).toFixed(1) + ' KiB/s';
    }
    return '~' + (bps / (1024 * 1024)).toFixed(2) + ' MiB/s';
  }

  function tbnActivityHtmlClient(act) {
    if (!act) {
      return '';
    }
    var level = act.level || 'unknown';
    var label = act.label || 'Measuring…';
    var safe = act.safe_unplug || 'unknown';
    var safeLabel = 'Measuring…';
    if (level === 'down') {
      safeLabel = 'OK to unplug';
    } else if (safe === 'yes') {
      safeLabel = 'OK to unplug';
    } else if (safe === 'no') {
      safeLabel = 'Keep connected';
    } else if (level !== 'unknown') {
      safeLabel = 'Check first';
    }
    var html =
      '<span class="tbn-badge tbn-badge-act-' +
      level +
      '">' +
      label +
      '</span> <span class="tbn-badge tbn-badge-safe-' +
      safe +
      '">' +
      safeLabel +
      '</span>';
    var rate = tbnFormatBpsClient(act.bps);
    if (rate) {
      html += ' <span class="tbn-muted">' + rate + '</span>';
    }
    if (act.note && level !== 'unknown') {
      html += '<p class="tbn-hint tbn-activity-note">' + act.note + '</p>';
    } else if (act.note && level === 'unknown') {
      html += '<p class="tbn-hint tbn-activity-note tbn-muted">' + act.note + '</p>';
    }
    return html;
  }

  /** Light live refresh: activity / IPs without full page reload. */
  function tbnLivePoll() {
    if (!document.querySelector('.tbn-wrap')) {
      return;
    }
    var url = '/plugins/ThunderboltNet/include/get-status.php';
    fetch(url, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (!data || !data.links) {
          return;
        }
        data.links.forEach(function (L) {
          var ifc = L.iface;
          var loc = L.local || {};
          var el4 = document.querySelectorAll('[data-tbn-live-ip4="' + ifc + '"]');
          for (var i = 0; i < el4.length; i++) {
            el4[i].textContent = loc.addrs && loc.addrs.length ? loc.addrs.join(', ') : '—';
          }
          var el6 = document.querySelectorAll('[data-tbn-live-ip6="' + ifc + '"]');
          for (var j = 0; j < el6.length; j++) {
            if (loc.addrs6) {
              el6[j].textContent = loc.addrs6.length ? loc.addrs6.join(', ') : '—';
            }
          }
          var act = L.activity;
          var elA = document.querySelectorAll('[data-tbn-live-act="' + ifc + '"]');
          if (act && elA.length) {
            var html = tbnActivityHtmlClient(act);
            for (var k = 0; k < elA.length; k++) {
              elA[k].className =
                'tbn-col-remote tbn-act-' + (act.level || 'unknown');
              elA[k].innerHTML = html;
            }
          }
        });
      })
      .catch(function () { /* ignore */ });
  }

  function tbnBootUi() {
    tbnWireAllForms();
    tbnBindInlineHelp();
    tbnLivePoll();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      setTimeout(tbnBootUi, 50);
      setTimeout(tbnBootUi, 400);
      setTimeout(tbnBootUi, 1200);
    });
  } else {
    setTimeout(tbnBootUi, 50);
    setTimeout(tbnBootUi, 400);
  }
  // Periodic: re-wire (tab paint) + light status poll for sampling/activity
  setInterval(function () {
    tbnWireAllForms();
    tbnLivePoll();
  }, 5000);
})();
