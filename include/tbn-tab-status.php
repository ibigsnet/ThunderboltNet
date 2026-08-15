<?php /* Entry banner lives in ThunderboltNet.page (all sub-tabs). */ ?>
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
    <p class="tbn-notice-issue">
      If that seems wrong, copy the plugin diagnostics below and open an issue:<br>
      <a href="<?= htmlspecialchars($issues_url) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($issues_url) ?></a>
    </p>
    <p class="tbn-notice-tools">
      For a full Unraid package (logs, lspci, etc.), also run
      <a href="/Tools/Diagnostics">Tools → Diagnostics</a>
      and attach that zip when filing the issue.
    </p>
    <label class="tbn-diag-label" for="tbn-diagnostics">Plugin diagnostics</label>
    <textarea id="tbn-diagnostics" class="tbn-diag" readonly rows="14" spellcheck="false"><?= htmlspecialchars($diag) ?></textarea>
    <p class="tbn-actions">
      <input type="button" value="Copy diagnostics" onclick="tbnCopyDiagnostics()">
      <input type="button" value="Refresh" onclick="location.reload()">
      <input type="button" value="Done" onclick="done()">
    </p>
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
        </td>
      </tr>
    </table>
    <blockquote class="inline_help">
      <code>thunderbolt</code> is the host controller stack.
      <code>thunderbolt_net</code> creates <code>thunderboltN</code> interfaces (configured on <strong>tbnN</strong> tabs).
      Both should be loaded for host-to-host IP. Separate from OpenFabric/FRR and USB4STREAM
      (<?= tbn_docs_more_html('docs/usb4stream.md', 'USB4STREAM') ?>).
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
        <p>
          Install <strong>Fabric Routing</strong> from CA or
          <a href="<?= htmlspecialchars($fabricrouting['install_url'] ?? 'https://raw.githubusercontent.com/ibigsnet/FabricRouting/main/fabricrouting.plg') ?>" target="_blank" rel="noopener">raw .plg</a>.
        </p>
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
        <p>
          Install <strong>NBD Export</strong> from CA or
          <a href="https://raw.githubusercontent.com/ibigsnet/NBDExport/stable/nbd.plg" target="_blank" rel="noopener">raw .plg</a>.
        </p>
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
