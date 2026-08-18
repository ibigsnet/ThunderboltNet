/* ThunderboltNet — Settings page helpers */
(function () {
  'use strict';

  /**
   * Fleet standard (TBN / FabricRouting / NBD Export):
   * Open a Network Settings *sibling tab* by Title text (e.g. "Thunderbolt",
   * "Fabric Routing", "tbn0"). Never deep-link /Settings/ThunderboltNet or
   * /Settings/FabricRouting — those are standalone CA launch pages without the strip.
   *
   * sessionStorage: ibigsWantTab (canonical) + tbnWantTab (legacy).
   * API: ibigsGotoNetTab(needle, evt) — aliases: tbnGotoNetTab, frrGotoNetTab, nbdGotoNetTab.
   */
  var IBIGS_WANT = 'ibigsWantTab';
  var IBIGS_WANT_LEGACY = 'tbnWantTab';
  var IBIGS_NET_SETTINGS = '/Settings/NetworkSettings';

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

  function ibigsSetWantTab(needle) {
    try {
      sessionStorage.setItem(IBIGS_WANT, needle);
      sessionStorage.setItem(IBIGS_WANT_LEGACY, needle);
    } catch (e) { /* ignore */ }
  }

  function ibigsGetWantTab() {
    try {
      return sessionStorage.getItem(IBIGS_WANT) || sessionStorage.getItem(IBIGS_WANT_LEGACY);
    } catch (e) {
      return null;
    }
  }

  function ibigsClearWantTab() {
    try {
      sessionStorage.removeItem(IBIGS_WANT);
      sessionStorage.removeItem(IBIGS_WANT_LEGACY);
    } catch (e) { /* ignore */ }
  }

  function ibigsGotoNetTabImpl(needle, evt) {
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
    ibigsSetWantTab(needle);
    window.location.href = IBIGS_NET_SETTINGS;
    return false;
  }

  // Canonical + legacy aliases (first plugin to load wins for implementation)
  if (typeof window.ibigsGotoNetTab !== 'function') {
    window.ibigsGotoNetTab = ibigsGotoNetTabImpl;
  }
  window.tbnGotoNetTab = function (needle, evt) {
    return window.ibigsGotoNetTab(needle, evt);
  };
  if (typeof window.frrGotoNetTab !== 'function') {
    window.frrGotoNetTab = function (needle, evt) {
      return window.ibigsGotoNetTab(needle, evt);
    };
  }
  if (typeof window.nbdGotoNetTab !== 'function') {
    window.nbdGotoNetTab = function (needle, evt) {
      return window.ibigsGotoNetTab(needle, evt);
    };
  }

  // After navigating to Network Settings, activate requested tab once
  function tbnApplyWantedTab() {
    var want = ibigsGetWantTab();
    if (!want) {
      return;
    }
    var tab = tbnFindTabButton(want);
    if (tab) {
      ibigsClearWantTab();
      // Defer so Unraid's own tab cookie init runs first
      setTimeout(function () {
        tab.click();
      }, 50);
    }
  }

  /**
   * Progressive disclosure for OpenFabric / USB4STREAM advanced panels.
   * Orange toggle buttons; default open from data-tbn-default-open (server-side).
   */
  function tbnInitAdvancedPanels() {
    var panels = document.querySelectorAll('.tbn-advanced[data-tbn-advanced]');
    for (var i = 0; i < panels.length; i++) {
      (function (panel) {
        var key = panel.getAttribute('data-tbn-advanced') || '';
        var body = panel.querySelector('.tbn-advanced-body');
        var btn = panel.querySelector('[data-tbn-adv-toggle]');
        if (!body || !btn) {
          return;
        }
        var defOpen = panel.getAttribute('data-tbn-default-open') === '1';
        var stored = null;
        try {
          stored = sessionStorage.getItem('tbnAdv:' + key);
        } catch (e) { /* ignore */ }
        var open = stored === '1' ? true : stored === '0' ? false : defOpen;
        function apply() {
          if (open) {
            body.removeAttribute('hidden');
            btn.value = btn.getAttribute('data-hide') || 'Hide';
            btn.textContent = btn.getAttribute('data-hide') || 'Hide';
          } else {
            body.setAttribute('hidden', 'hidden');
            btn.value = btn.getAttribute('data-show') || 'Show';
            btn.textContent = btn.getAttribute('data-show') || 'Show';
          }
        }
        apply();
        btn.addEventListener('click', function (ev) {
          if (ev && ev.preventDefault) {
            ev.preventDefault();
          }
          open = !open;
          try {
            sessionStorage.setItem('tbnAdv:' + key, open ? '1' : '0');
          } catch (e2) { /* ignore */ }
          apply();
          return false;
        });
      })(panels[i]);
    }
  }

  /**
   * Jump links to #tbn-companion-frr (chip "needs FRR packages", CTA, status table).
   * Scroll into view and briefly highlight the Multi-hop companion card.
   */
  function tbnFlashCompanion(id) {
    var el = document.getElementById(id || 'tbn-companion-frr');
    if (!el) {
      return;
    }
    try {
      el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } catch (e) {
      el.scrollIntoView(true);
    }
    el.classList.remove('tbn-companion-flash');
    // reflow so re-click retriggers animation
    void el.offsetWidth;
    el.classList.add('tbn-companion-flash');
    try {
      el.focus({ preventScroll: true });
    } catch (e2) {
      try { el.focus(); } catch (e3) { /* ignore */ }
    }
    window.setTimeout(function () {
      el.classList.remove('tbn-companion-flash');
    }, 1800);
  }

  function tbnInitCompanionJumps() {
    document.addEventListener('click', function (ev) {
      var a = ev.target;
      while (a && a !== document && !(a.tagName === 'A' && a.getAttribute('href'))) {
        a = a.parentNode;
      }
      if (!a || a === document) {
        return;
      }
      var href = a.getAttribute('href') || '';
      if (href.indexOf('#tbn-companion-') !== 0) {
        return;
      }
      var id = href.slice(1);
      if (!document.getElementById(id)) {
        return;
      }
      if (ev.preventDefault) {
        ev.preventDefault();
      }
      tbnFlashCompanion(id);
      try {
        if (history && history.replaceState) {
          history.replaceState(null, '', href);
        } else {
          location.hash = href;
        }
      } catch (e) { /* ignore */ }
      return false;
    });
    if (location.hash && location.hash.indexOf('#tbn-companion-') === 0) {
      window.setTimeout(function () {
        tbnFlashCompanion(location.hash.slice(1));
      }, 80);
    }
  }

  /**
   * Entry banner (above Status/Peers/Hardware/Settings) is for standalone
   * CA / Plugins launch (/Settings/ThunderboltNet or /Apps/Settings/…) where the
   * eth0 · Thunderbolt · tbn… strip is missing. Hide when already on Network Settings
   * or when those sibling tabs are visible.
   */
  function tbnInNetworkSettingsStrip() {
    var path = (location.pathname || '').toLowerCase();
    if (path.indexOf('/settings/networksettings') !== -1) {
      return true;
    }
    // Explicit standalone plugin page (CA Settings, Plugins list launch)
    if (path.indexOf('thunderboltnet') !== -1 && path.indexOf('networksettings') === -1) {
      return false;
    }
    // Embedded / unusual paths: eth0 (or eth) + Thunderbolt tabs present
    if (tbnFindTabButton('Thunderbolt') && (tbnFindTabButton('eth0') || tbnFindTabButton('eth'))) {
      return true;
    }
    return false;
  }

  function tbnInitEntryBanner() {
    var banner = document.getElementById('tbn-entry-banner');
    if (!banner) {
      return;
    }
    if (tbnInNetworkSettingsStrip()) {
      banner.setAttribute('hidden', 'hidden');
      banner.style.display = 'none';
    } else {
      banner.removeAttribute('hidden');
      banner.style.display = '';
    }
  }

  /** Status companion cards: expand manual .plg URL + copy to clipboard */
  function tbnInitInstallBoxes() {
    document.querySelectorAll('[data-tbn-plg-panel]').forEach(function (btn) {
      if (btn.getAttribute('data-tbn-bound') === '1') return;
      btn.setAttribute('data-tbn-bound', '1');
      btn.addEventListener('click', function (ev) {
        ev.preventDefault();
        var box = btn.closest('.tbn-install-box');
        if (!box) return;
        var panel = box.querySelector('.tbn-plg-panel');
        if (!panel) return;
        var hide = !panel.hasAttribute('hidden') && !panel.classList.contains('tbn-hidden');
        if (hide) {
          panel.setAttribute('hidden', 'hidden');
          panel.classList.add('tbn-hidden');
        } else {
          panel.removeAttribute('hidden');
          panel.classList.remove('tbn-hidden');
        }
      });
    });
    document.querySelectorAll('[data-tbn-copy-plg]').forEach(function (btn) {
      if (btn.getAttribute('data-tbn-bound') === '1') return;
      btn.setAttribute('data-tbn-bound', '1');
      btn.addEventListener('click', function (ev) {
        ev.preventDefault();
        var box = btn.closest('.tbn-install-box') || btn.closest('.tbn-plg-panel');
        var input = box ? box.querySelector('.tbn-plg-url') : null;
        if (!input) return;
        var text = input.value || '';
        function ok() {
          var prev = btn.textContent;
          btn.textContent = 'Copied';
          setTimeout(function () { btn.textContent = prev; }, 1600);
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(ok).catch(function () {
            input.select();
            try { document.execCommand('copy'); ok(); } catch (e) { /* ignore */ }
          });
        } else {
          input.select();
          try { document.execCommand('copy'); ok(); } catch (e2) { /* ignore */ }
        }
      });
    });
  }

  function tbnOnReady() {
    tbnInitEntryBanner();
    tbnApplyWantedTab();
    tbnInitAdvancedPanels();
    tbnInitCompanionJumps();
    tbnInitInstallBoxes();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', tbnOnReady);
  } else {
    tbnOnReady();
  }
  // Unraid may paint tabs slightly later
  setTimeout(tbnInitEntryBanner, 200);
  setTimeout(tbnApplyWantedTab, 200);
  setTimeout(tbnApplyWantedTab, 600);
  setTimeout(tbnInitAdvancedPanels, 250);

  /**
   * Jump to Thunderbolt → Settings → Show Fabric reports (Enable fabric reports).
   * Works when already on Network Settings; otherwise navigates there first.
   */
  window.tbnGotoFabricReportsSettings = function (evt) {
    if (evt && evt.preventDefault) {
      evt.preventDefault();
    }
    try {
      sessionStorage.setItem('tbnInnerTab', 'settings');
      sessionStorage.setItem('tbnWantExpand', 'mesh');
      sessionStorage.setItem(IBIGS_WANT, 'Thunderbolt');
      sessionStorage.setItem(IBIGS_WANT_LEGACY, 'Thunderbolt');
    } catch (e) { /* ignore */ }

    function openInner() {
      var ov = document.getElementById('tbn-overview')
        || document.querySelector('.tbn-wrap[data-tbn-overview="1"]');
      if (typeof window.tbnActivateInnerTab === 'function') {
        window.tbnActivateInnerTab('settings');
      } else {
        var btn = (ov || document).querySelector('[data-tbn-tab="settings"]');
        if (btn) {
          btn.click();
        }
      }
      setTimeout(function () {
        var scope = ov || document;
        var toggle = scope.querySelector('[data-tbn-adv-toggle="mesh"]');
        var body = scope.querySelector('#tbn-adv-mesh')
          || document.getElementById('tbn-adv-mesh');
        if (toggle && body && body.hasAttribute('hidden')) {
          toggle.click();
        }
        if (body) {
          try {
            body.scrollIntoView({ block: 'nearest' });
          } catch (e2) { /* ignore */ }
        }
      }, 60);
    }

    var strip = tbnInNetworkSettingsStrip();
    var thunder = tbnFindTabButton('Thunderbolt');
    if (strip && thunder) {
      thunder.click();
      setTimeout(openInner, 40);
      return false;
    }
    window.location.href = IBIGS_NET_SETTINGS;
    return false;
  };

  /**
   * Same pattern as eth0 networkInfo(): jQuery $.post (CSRF via Unraid ajaxSend) + swal.
   * Raw fetch POST is rejected by local_prepend.php (missing csrf_token → empty exit).
   */
  window.tbnNetworkInfo = function (port) {
    if (!port || typeof $ === 'undefined' || typeof $.post !== 'function') {
      return;
    }
    $.post('/plugins/ThunderboltNet/include/tbn-network-info.php', { port: port }, function (text) {
      if (typeof swal === 'function') {
        swal({
          title: 'Network Info',
          text: text,
          animation: 'none',
          html: true,
          confirmButtonText: 'Ok'
        });
      } else {
        window.alert(String(text).replace(/<[^>]+>/g, ' '));
      }
    });
  };
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
  window.tbnFormSync = function (form, ev) {
    if (!form) {
      return;
    }
    var slave = form.getAttribute('data-tbn-slave') === '1';
    var src = ev && ev.target ? ev.target.name : '';
    var proto = (form.PROTOCOL && form.PROTOCOL.value) || 'ipv4';
    var show4 = proto === 'ipv4' || proto === 'ipv4+ipv6';
    var show6 = proto === 'ipv6' || proto === 'ipv4+ipv6';
    tbnShow(form.querySelector('.tbn-proto-ipv4'), show4 && !slave);
    tbnShow(form.querySelector('.tbn-proto-ipv6'), show6 && !slave);

    var dhcp4 = form.USE_DHCP ? form.USE_DHCP.value : 'no';
    var dhcp6 = form.USE_DHCP6 ? form.USE_DHCP6.value : 'no';
    var showAddr4 = show4 && (dhcp4 === 'no' || dhcp4 === 'server') && !slave;
    tbnShow(form.querySelector('.tbn-ipv4-addr'), showAddr4);
    tbnShow(form.querySelector('.tbn-static-ipv4'), show4 && dhcp4 === 'no' && !slave);
    tbnShow(form.querySelector('.tbn-dhcp-server-v4'), show4 && dhcp4 === 'server' && !slave);
    tbnShow(form.querySelector('.tbn-static-ipv6'), show6 && dhcp6 === 'no' && !slave);
    tbnShow(form.querySelector('.tbn-dhcp-server-v6'), show6 && dhcp6 === 'server' && !slave);

    // Toggle static vs server labels/help on the shared address row
    var isServer = dhcp4 === 'server';
    tbnShow(form.querySelector('.tbn-ipv4-addr-label-static'), showAddr4 && !isServer);
    tbnShow(form.querySelector('.tbn-ipv4-addr-label-server'), showAddr4 && isServer);
    tbnShow(form.querySelector('.tbn-ipv4-addr-help-static'), showAddr4 && !isServer);
    tbnShow(form.querySelector('.tbn-ipv4-addr-help-server'), showAddr4 && isServer);

    // Autofill Unraid/pool defaults when switching into DHCP server (keep user edits)
    var addrBox = form.querySelector('.tbn-ipv4-addr');
    var ip4 = form.IPADDR;
    var nm4 = form.NETMASK;
    if (ip4 && ip4.tagName === 'INPUT') {
      ip4.disabled = false;
    }
    if (nm4 && (nm4.tagName === 'SELECT' || nm4.tagName === 'INPUT')) {
      nm4.disabled = false;
    }
    if (isServer && addrBox && src === 'USE_DHCP') {
      var dip = addrBox.getAttribute('data-dhcp-default-ip') || '';
      var dps = addrBox.getAttribute('data-dhcp-default-pool-start') || '';
      var dpe = addrBox.getAttribute('data-dhcp-default-pool-end') || '';
      var dpfx = addrBox.getAttribute('data-dhcp-default-prefix') || '24';
      if (ip4 && dip && (!ip4.value || /\.2$/.test(ip4.value))) {
        ip4.value = dip;
      }
      if (nm4 && dpfx) {
        nm4.value = dpfx;
      }
      if (form.DHCP_POOL_START && dps && !form.DHCP_POOL_START.value) {
        form.DHCP_POOL_START.value = dps;
      }
      if (form.DHCP_POOL_END && dpe && !form.DHCP_POOL_END.value) {
        form.DHCP_POOL_END.value = dpe;
      }
    }

    var bond = form.BONDING ? form.BONDING.value : 'no';
    tbnShow(form.querySelector('.tbn-bond-opts'), bond === 'yes' && !slave);

    // NAT vs bridging: mutually exclusive. Last control the user changed wins.
    var natEn = form.NAT_ENABLE ? form.NAT_ENABLE.value : 'no';
    var bridge = form.BRIDGING ? form.BRIDGING.value : 'no';
    if (src === 'NAT_ENABLE' && natEn === 'yes' && form.BRIDGING) {
      form.BRIDGING.value = 'no';
      bridge = 'no';
    } else if (src === 'BRIDGING' && bridge === 'yes' && form.NAT_ENABLE) {
      form.NAT_ENABLE.value = 'no';
      natEn = 'no';
    } else if (natEn === 'yes' && bridge === 'yes') {
      // Both Yes in cfg (legacy): keep NAT, clear bridging
      form.BRIDGING.value = 'no';
      bridge = 'no';
    }
    tbnShow(form.querySelector('.tbn-section-bridge'), natEn !== 'yes' && !slave);
    tbnShow(form.querySelector('.tbn-bridge-opts'), bridge === 'yes' && natEn !== 'yes' && !slave);
    tbnShow(form.querySelector('.tbn-nat-section'), bridge !== 'yes' && !slave);
    tbnShow(form.querySelector('.tbn-nat-uplink-opts'), natEn === 'yes' && bridge !== 'yes' && !slave);

    var vlan = form.VLAN_ENABLE ? form.VLAN_ENABLE.value : 'no';
    tbnShow(form.querySelector('.tbn-vlan-opts'), vlan === 'yes' && !slave);

    // eth0-like: Desired MTU number + Enable jumbo frames checkbox
    var mtuInput = form.querySelector('input.tbn-mtu-input') || form.MTU;
    var jumboCb = form.querySelector('input.tbn-ctl-mtu[type="checkbox"]');
    var mtuModeEl = form.querySelector('input.tbn-mtu-mode');
    if (jumboCb && mtuInput) {
      var jumbo = !slave && jumboCb.checked;
      mtuInput.disabled = !jumbo;
      if (jumbo && !mtuInput.value) {
        mtuInput.value = '9000';
      }
      if (!jumbo) {
        mtuInput.value = '';
      }
      if (mtuModeEl) {
        if (!jumbo) {
          mtuModeEl.value = 'default';
        } else if (String(mtuInput.value) === '9000') {
          mtuModeEl.value = '9000';
        } else {
          mtuModeEl.value = 'custom';
        }
      }
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
      list[i].addEventListener('input', function (ev) {
        tbnUnlockApply(form);
        tbnFormSync(form, ev);
      });
      list[i].addEventListener('change', function (ev) {
        tbnUnlockApply(form);
        tbnFormSync(form, ev);
      });
    }
    form.addEventListener('submit', function () {
      tbnSyncBondMembers(form);
      // eth0: when jumbo off, clear/disable MTU so Apply stores default 1500
      var jumboCb = form.querySelector('input.tbn-ctl-mtu[type="checkbox"]');
      var mtuInput = form.querySelector('input.tbn-mtu-input') || form.MTU;
      var mtuModeEl = form.querySelector('input.tbn-mtu-mode');
      if (jumboCb && mtuInput && !jumboCb.checked) {
        mtuInput.disabled = false;
        mtuInput.value = '';
        if (mtuModeEl) {
          mtuModeEl.value = 'default';
        }
      } else if (jumboCb && mtuInput && jumboCb.checked && mtuModeEl) {
        mtuModeEl.value = String(mtuInput.value) === '9000' ? '9000' : 'custom';
      }
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
    // After Apply (progressFrame), refresh kernel IP rows so "live" matches Apply
    form.addEventListener('submit', function () {
      setTimeout(tbnLivePoll, 800);
      setTimeout(tbnLivePoll, 2000);
      setTimeout(tbnLivePoll, 4000);
    });
  };

  /**
   * Known peers toolbar: checkbox selection + one form outside the table.
   * Nested forms inside the peer-plan column broke layout (invalid HTML).
   */
  window.tbnPeersToolbarSubmit = function (form, evt) {
    var submitter = (evt && evt.submitter) || (document.activeElement && document.activeElement.form === form
      ? document.activeElement
      : null);
    var action = '';
    if (submitter && submitter.getAttribute) {
      action = submitter.getAttribute('data-tbn-action') || '';
    }
    if (!action) {
      // Fallback: first checked-looking submit
      var btns = form.querySelectorAll('[data-tbn-action]');
      for (var i = 0; i < btns.length; i++) {
        if (btns[i] === document.activeElement) {
          action = btns[i].getAttribute('data-tbn-action') || '';
          break;
        }
      }
    }
    var hidden = form.querySelector('#tbn_peer_action') || form.querySelector('input[name="tbn_peer_action"]');
    if (hidden) {
      hidden.value = action;
    }
    var boxes = document.querySelectorAll('input.tbn-peer-sel[form="tbn-peers-action-form"]:checked, input.tbn-peer-sel:checked');
    // Prefer form-associated checkboxes
    if (!boxes.length) {
      boxes = document.querySelectorAll('input[name="tbn_peer_keys[]"]:checked');
    }
    if (!boxes.length) {
      alert('Select at least one peer (checkbox) first.');
      return false;
    }
    if (action === 'forget') {
      return confirm(
        'Forget selected peers?\n\n' +
        'Removes them from this list (including Saved addresses).\n' +
        'Does not delete tbn tab configs or eth Interface Rules.'
      );
    }
    if (action === 'capture_plan') {
      var anyOnline = false;
      for (var c = 0; c < boxes.length; c++) {
        if (boxes[c].getAttribute('data-online') === '1') {
          anyOnline = true;
          break;
        }
      }
      if (!anyOnline) {
        alert('Remember current needs a selected Online peer.');
        return false;
      }
      return confirm('Copy Current (IP/MTU/services) into Saved for each selected online peer?');
    }
    if (action === 'apply_plan') {
      var anyPlan = false;
      for (var a = 0; a < boxes.length; a++) {
        if (boxes[a].getAttribute('data-online') === '1' && boxes[a].getAttribute('data-has-plan') === '1') {
          anyPlan = true;
          break;
        }
      }
      if (!anyPlan) {
        alert('Apply saved needs a selected Online peer that already has a Saved address.');
        return false;
      }
      return confirm('Apply Saved onto the live path for each selected online peer?');
    }
    alert('Unknown action.');
    return false;
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

  /** True if element is shown in the Network Settings tab strip (not display:none ancestors). */
  function tbnIsShown(el) {
    if (!el) {
      return false;
    }
    var cur = el;
    while (cur && cur.nodeType === 1) {
      var st = window.getComputedStyle(cur);
      if (st.display === 'none' || st.visibility === 'hidden') {
        return false;
      }
      cur = cur.parentElement;
    }
    return true;
  }

  /** Thunderbolt overview or a tbnN lazy shell is the visible Network Settings tab. */
  function tbnThunderboltUiVisible() {
    var ov = document.getElementById('tbn-overview');
    if (ov && tbnIsShown(ov)) {
      return true;
    }
    var ifaces = document.querySelectorAll('.tbn-lazy-iface, .tbn-iface-form');
    for (var i = 0; i < ifaces.length; i++) {
      if (tbnIsShown(ifaces[i])) {
        return true;
      }
    }
    return false;
  }

  function tbnLazyFetch(url, target, done) {
    if (!target || target.getAttribute('data-tbn-lazy-loaded') === '1') {
      if (done) {
        done(false);
      }
      return;
    }
    if (target.getAttribute('data-tbn-lazy-loading') === '1') {
      return;
    }
    target.setAttribute('data-tbn-lazy-loading', '1');
    fetch(url, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) {
        if (!r.ok) {
          throw new Error('HTTP ' + r.status);
        }
        return r.text();
      })
      .then(function (html) {
        target.innerHTML = html;
        target.setAttribute('data-tbn-lazy-loaded', '1');
        target.removeAttribute('data-tbn-lazy-loading');
        // Drop nested chrome duplicates from iface render
        var nestedCss = target.querySelectorAll('link[href*="thunderboltnet.css"]');
        for (var c = 0; c < nestedCss.length; c++) {
          nestedCss[c].remove();
        }
        var nestedJs = target.querySelectorAll('script[src*="thunderboltnet.js"]');
        for (var s = 0; s < nestedJs.length; s++) {
          nestedJs[s].remove();
        }
        // Unwrap extra .tbn-wrap from iface page if present
        var innerWrap = target.querySelector(':scope > .tbn-wrap');
        if (innerWrap && target.classList.contains('tbn-lazy-iface')) {
          while (innerWrap.firstChild) {
            target.insertBefore(innerWrap.firstChild, innerWrap);
          }
          innerWrap.remove();
        }
        tbnWireAllForms();
        tbnBindInlineHelp();
        tbnInitAdvancedPanels();
        tbnInitCompanionJumps();
        tbnInitInstallBoxes();
        if (done) {
          done(true);
        }
      })
      .catch(function () {
        target.removeAttribute('data-tbn-lazy-loading');
        target.innerHTML =
          '<p class="tbn-muted tbn-lazy-placeholder">Could not load this panel. Click the tab again or Refresh.</p>';
        if (done) {
          done(false);
        }
      });
  }

  window.tbnLazyEnsurePanel = function (name) {
    var ov = document.getElementById('tbn-overview');
    if (!ov || !tbnIsShown(ov)) {
      return;
    }
    var panel = ov.querySelector('[data-tbn-lazy-panel="' + name + '"]');
    if (!panel) {
      return;
    }
    tbnLazyFetch(
      '/plugins/ThunderboltNet/include/tbn-lazy-render.php?panel=' + encodeURIComponent(name),
      panel,
      function (ok) {
        if (ok) {
          tbnLivePoll();
        }
      }
    );
  };

  function tbnLazyEnsureIfaces() {
    var nodes = document.querySelectorAll('.tbn-lazy-iface[data-tbn-lazy-iface]');
    for (var i = 0; i < nodes.length; i++) {
      if (!tbnIsShown(nodes[i])) {
        continue;
      }
      var ifc = nodes[i].getAttribute('data-tbn-lazy-iface');
      if (!ifc) {
        continue;
      }
      tbnLazyFetch(
        '/plugins/ThunderboltNet/include/tbn-lazy-render.php?iface=' + encodeURIComponent(ifc),
        nodes[i],
        function (ok) {
          if (ok) {
            tbnLivePoll();
          }
        }
      );
    }
  }

  function tbnLazyTick() {
    if (!tbnThunderboltUiVisible()) {
      return;
    }
    var ov = document.getElementById('tbn-overview');
    if (ov && tbnIsShown(ov)) {
      var activeBtn = ov.querySelector('.tbn-subtabs button.is-active[data-tbn-tab]');
      var name = activeBtn ? activeBtn.getAttribute('data-tbn-tab') : 'status';
      window.tbnLazyEnsurePanel(name);
    }
    tbnLazyEnsureIfaces();
  }

  /** Light live refresh: activity / IPs without full page reload. */
  function tbnLivePoll() {
    if (!tbnThunderboltUiVisible()) {
      return;
    }
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
    tbnLazyTick();
    if (!tbnThunderboltUiVisible()) {
      return;
    }
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
  // Re-check visibility (Unraid tab clicks) + poll only while Thunderbolt UI is shown
  setInterval(function () {
    tbnLazyTick();
    if (!tbnThunderboltUiVisible()) {
      return;
    }
    tbnWireAllForms();
    tbnLivePoll();
  }, 5000);
  // Catch tab switches sooner than the interval
  document.addEventListener(
    'click',
    function () {
      setTimeout(tbnLazyTick, 30);
      setTimeout(tbnLazyTick, 200);
    },
    true
  );
})();
