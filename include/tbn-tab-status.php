<?php /* Entry banner lives in ThunderboltNet.page (all sub-tabs). */ ?>
<?= function_exists('tbn_vfio_warning_banner_html') ? tbn_vfio_warning_banner_html($pci ?? null, $cfg ?? null) : '' ?>
<?php if (!$has_hw): ?>
  <div class="tbn-notice" role="status">
    <h4>No Thunderbolt host controller detected</h4>
    <p>
      Linux does not see a Thunderbolt host interface that can run host-to-host networking
      (Thunderbolt&nbsp;3 /&nbsp;4 /&nbsp;5 or USB4-class host controllers, when the kernel exposes them).
      Ordinary USB&nbsp;2/3 ports alone do not provide this path.
    </p>
    <p>
      This plugin is not required on this system unless you add compatible hardware.
      Rear-panel marks (Thunderbolt lightning /&nbsp;40 vs SuperSpeed SS only): see
      <a href="<?= htmlspecialchars(tbn_docs_url('docs/port-icons.md')) ?>" target="_blank" rel="noopener">port icons guide</a>.
    </p>
<?php
  $tbn_support = function_exists('tbn_support_links') ? tbn_support_links() : [
    'forum' => 'https://forums.unraid.net/topic/200065-plugin-thunderbolt-net-host-to-host-networking-over-thunderbolt-345-and-usb44v2/',
    'github' => 'https://github.com/ibigsnet/ThunderboltNet',
    'issues' => isset($issues_url) ? $issues_url : 'https://github.com/ibigsnet/ThunderboltNet/issues',
  ];
?>
    <p class="tbn-notice-issue">
      If that seems wrong, copy the plugin diagnostics below and paste them on the
      <a href="<?= htmlspecialchars($tbn_support['forum']) ?>" target="_blank" rel="noopener">Unraid support forum</a>
      or open a
      <a href="<?= htmlspecialchars($tbn_support['issues']) ?>" target="_blank" rel="noopener">GitHub issue</a>
      (<a href="<?= htmlspecialchars($tbn_support['github']) ?>" target="_blank" rel="noopener">repository</a>).
    </p>
    <p class="tbn-notice-tools">
      For a full Unraid package (logs, lspci, etc.), also run
      <a href="/Tools/Diagnostics">Tools → Diagnostics</a>
      and attach that zip when filing the report.
    </p>
    <div class="tbn-diag-layout">
      <div class="tbn-diag-col">
        <label class="tbn-diag-label" for="tbn-diagnostics">Plugin diagnostics</label>
        <textarea id="tbn-diagnostics" class="tbn-diag" readonly rows="14" spellcheck="false"><?= htmlspecialchars($diag) ?></textarea>
        <p class="tbn-actions">
          <input type="button" value="Copy diagnostics" onclick="tbnCopyDiagnostics()">
          <input type="button" value="Refresh" onclick="location.reload()">
          <input type="button" value="Done" onclick="done()">
        </p>
      </div>
      <aside class="tbn-diag-usb" aria-label="USB SuperSpeed controllers">
        <p class="tbn-diag-label">USB SuperSpeed on this host</p>
        <p class="tbn-muted tbn-diag-usb-lead">Same inventory as Peers → LOCAL. These banks do not provide Thunderbolt host-net.</p>
        <?= function_exists('tbn_usb_superspeed_html')
          ? tbn_usb_superspeed_html(['open' => true, 'show_empty' => true])
          : '' ?>
      </aside>
    </div>
  </div>
  <?= tbn_docs_bar_html('overview') ?>

<?php else: ?>

<?php
  $links = $status['links'] ?? tbn_link_summaries();
  $local_ctrl = $status['local_controller'] ?? tbn_sysfs_str('/sys/bus/thunderbolt/devices/0-0/device_name');
  $local_mfg = $status['local_manufacturer'] ?? tbn_sysfs_str('/sys/bus/thunderbolt/devices/0-0/vendor_name');
  $sec_disp = $status['security'] ?: '(none/unknown)';
?>
  <div class="tbn-section">
    <h3>This host</h3>
    <p class="tbn-note">Host-wide Thunderbolt controller and host-net status.</p>

    <table class="tbn-table tbn-summary">
      <tr>
        <td>Domain security</td>
        <td><code><?= htmlspecialchars($sec_disp) ?></code></td>
      </tr>
    </table>
    <blockquote class="inline_help">
      Thunderbolt domain mode from the controller (sysfs). Values include
      <code>none</code>, <code>user</code>, <code>secure</code>, <code>dponly</code>.
      <code>none</code> means the authorize-device gate is open (typical private lab host-to-host).
      <code>user</code> / <code>secure</code> require approving the peer in firmware/UI before full access.
      Unrelated to SMB passwords or E2E flow control on the Settings tab.
      <?= tbn_help_docs_footer('docs/requirements.md', 'Requirements') ?>
    </blockquote>

    <table class="tbn-table tbn-summary">
      <tr>
        <td>IP host-net modules</td>
        <td>
          <code>thunderbolt</code> <?= htmlspecialchars($mod_tb) ?> ·
          <code>thunderbolt_net</code> <?= htmlspecialchars($mod_net) ?>
<?php
  $e2e_live = function_exists('tbn_live_e2e_bit') ? tbn_live_e2e_bit() : '';
  $e2e_want = function_exists('tbn_desired_e2e_bit') ? tbn_desired_e2e_bit($cfg) : '0';
  if ($e2e_live !== ''):
    $e2e_mismatch = ($e2e_live !== $e2e_want);
?>
          · e2e=<code><?= htmlspecialchars($e2e_live === '1' ? '1' : '0') ?></code><?= $e2e_mismatch ? ' <span class="tbn-muted">(cfg wants ' . htmlspecialchars($e2e_want) . ')</span>' : '' ?>
<?php endif; ?>
        </td>
      </tr>
    </table>
    <blockquote class="inline_help">
      <code>thunderbolt</code> is the host controller stack.
      <code>thunderbolt_net</code> creates <code>thunderboltN</code> interfaces (configured on <strong>tbnN</strong> tabs).
      Both should be loaded for host-to-host IP. Separate from OpenFabric/FRR and USB4STREAM
      (<?= tbn_docs_more_html('docs/usb4stream.md', 'USB4STREAM') ?>).
      E2E is persisted to <code>/boot/config/modprobe.d/</code> and corrected on startup if the kernel loaded the wrong bit.
      Load / E2E: Settings tab.
    </blockquote>

    <table class="tbn-table tbn-summary">
      <tr>
        <td>Listening includes</td>
        <td>
          <code><?= htmlspecialchars(implode(' ', $include) ?: '(none)') ?></code>
          <span class="tbn-muted"> — SMB / NFS / SSH / web</span>
        </td>
      </tr>
    </table>
    <blockquote class="inline_help">
      Interfaces listed in <code>network-extra.cfg</code> so Unraid services may bind on Thunderbolt IPs.
      Per-peer Yes/No is under <strong>Peers</strong> or each <strong>tbnN</strong> tab.
      NBD Export binds separately (Network Services → NBD).
    </blockquote>

    <table class="tbn-table tbn-summary">
      <tr>
        <td>Multi-hop routing</td>
        <td>
          <strong><?= htmlspecialchars($of_mode_label) ?></strong>
<?php if ($frr_present): ?>
          <span class="tbn-muted"> — FRR present · policy on <strong>Settings</strong> · packages via
            <a href="/Settings/NetworkSettings" onclick="return ibigsGotoNetTab('Fabric Routing', event)">Fabric Routing</a></span>
<?php else: ?>
          <span class="tbn-muted"> — static tbn IPs work without FRR</span>
<?php endif; ?>
        </td>
      </tr>
    </table>
    <blockquote class="inline_help">
      Optional IP multi-hop (OpenFabric / <code>fabricd</code>), not the Thunderbolt cable driver.
      One cable + static IPs does not need this.
      Packages: Network Settings → Fabric Routing.
      Policy: Thunderbolt → Settings.
    </blockquote>

    <div class="tbn-companion-strip" aria-label="Related plugins">
      <div id="tbn-companion-frr" class="tbn-companion-card<?= $frr_present ? ' tbn-companion-ok' : '' ?>"
           tabindex="-1">
        <div class="tbn-companion-title">Multi-hop (FRR / OpenFabric)</div>
<?php if ($frr_present): ?>
        <p><span class="tbn-companion-status tbn-status-ok">Ready</span>
          FRR packages live. OpenFabric policy is on Thunderbolt → <strong>Settings</strong>.
          Packages:
          <a href="/Settings/NetworkSettings" onclick="return ibigsGotoNetTab('Fabric Routing', event)">Network Settings → Fabric Routing</a>.</p>
        <p class="tbn-muted tbn-companion-role">
          <strong>Fabric Routing</strong> = install/start FRR.
          <strong>Thunderbolt Net</strong> = underlay + OpenFabric policy — not packages.
        </p>
<?php elseif (!empty($fabricrouting['plugin_dir'])): ?>
        <p><span class="tbn-companion-status tbn-status-warn">Plugin installed · packages missing</span>
          Open <a href="/Settings/NetworkSettings" onclick="return ibigsGotoNetTab('Fabric Routing', event)">Fabric Routing</a>
          → Download &amp; Install packages, then Thunderbolt → Settings → Apply.</p>
<?php else: ?>
        <p><span class="tbn-companion-status tbn-status-warn">Not installed</span>
          Optional for rings / multi-hop / Proxmox FRR peers. Skip for a single static cable.</p>
        <?= function_exists('tbn_companion_install_html')
          ? tbn_companion_install_html(
              'Fabric Routing',
              $fabricrouting['install_url'] ?? 'https://raw.githubusercontent.com/ibigsnet/FabricRouting/stable/fabricrouting.plg',
              'Fabric Routing'
            )
          : '' ?>
<?php endif; ?>
      </div>
      <div id="tbn-companion-nbd" class="tbn-companion-card<?= $nbd_present ? ' tbn-companion-ok' : '' ?>">
        <div class="tbn-companion-title">Block export (NBD)</div>
<?php if ($nbd_present): ?>
        <p><span class="tbn-companion-status tbn-status-ok">Installed</span>
          <a href="/Settings/NBDExport">Network Services → NBD</a> — whole-disk over a Thunderbolt IP.</p>
<?php else: ?>
        <p><span class="tbn-companion-status tbn-status-warn">Not installed</span>
          Optional whole-disk export/image over a Thunderbolt or LAN IP. Skip if you only need host networking.</p>
        <?= function_exists('tbn_companion_install_html')
          ? tbn_companion_install_html(
              'NBD Export',
              'https://raw.githubusercontent.com/ibigsnet/NBDExport/stable/nbd.plg',
              'NBD Export'
            )
          : '' ?>
<?php endif; ?>
      </div>
      <div id="tbn-companion-usb4stream" class="tbn-companion-card tbn-companion-muted">
        <div class="tbn-companion-title">USB4STREAM (kernel)</div>
        <p class="tbn-muted">
<?php if (!empty($usb4stream['devices'])): ?>
          Devices: <code><?= htmlspecialchars(implode(' ', $usb4stream['devices'])) ?></code>
<?php elseif ($stream_ready): ?>
          Module available — enable under Thunderbolt → Settings.
<?php else: ?>
          Not in this kernel<?= !empty($usb4stream['kernel']) ? ' (<code>' . htmlspecialchars($usb4stream['kernel']) . '</code>)' : '' ?>.
          Needs a kernel with <code>thunderbolt_stream</code> — not Fabric Routing.
<?php endif; ?>
        </p>
      </div>
    </div>
  </div>
<?php endif; /* has_hw */ ?>
