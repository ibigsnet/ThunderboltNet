<?php /* Shown only on standalone /Settings/ThunderboltNet (CA launch). Hidden when already under Network Settings with eth0/tbn tabs. */ ?>
  <div class="tbn-entry-banner" id="tbn-entry-banner" role="note" hidden>
    <div class="tbn-entry-banner-main">
      <strong>Network Settings · Thunderbolt</strong>
      <span class="tbn-muted"> — sub-tabs: <strong>Status</strong> · <strong>Peers</strong> · <strong>Hardware</strong> · <strong>Settings</strong>. Per-link IPs stay on top-level <strong>tbnN</strong> tabs.</span>
    </div>
    <div class="tbn-entry-banner-actions">
      <a class="tbn-btn-link" href="/Settings/NetworkSettings"
         onclick="return ibigsGotoNetTab('Thunderbolt', event)">Open Network Settings (eth0 / Thunderbolt / tbn…)</a>
      <span class="tbn-muted tbn-entry-hint">CA <em>Settings</em> opens Thunderbolt alone; use Network Settings for eth + tbn together.</span>
    </div>
  </div>

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
?>
  <div class="tbn-section">
    <h3>This host</h3>
    <p class="tbn-note">Controller, IP host-net drivers, and who may listen on Thunderbolt IPs — host-wide. Blue help sits under each row.</p>

    <dl>
      <dt>Domain security:</dt>
      <dd><code><?= htmlspecialchars($status['security'] ?: '(none/unknown)') ?></code></dd>
    </dl>
    <blockquote class="inline_help">
      <strong>What it is:</strong> Thunderbolt domain security mode from Linux sysfs
      (<code>none</code>, <code>user</code>, <code>secure</code>, <code>dponly</code>, …).<br><br>
      <strong>What <code>none</code> means:</strong> the “authorize this device” gate is open — peers can
      train host-to-host networking without you approving each cable in firmware/UI. Common and fine for
      a private lab (two Unraids on one cable). It is <em>not</em> “no encryption on your SMB password”
      and not related to E2E flow control on the Settings tab.<br><br>
      <strong><code>user</code> / <code>secure</code>:</strong> more like a locked phone / production ACL —
      the controller expects you (or a key) to approve devices before full access. If links never appear,
      check BIOS/firmware Thunderbolt security and authorize the peer.<br><br>
      <?= tbn_help_docs_footer('docs/requirements.md', 'Requirements') ?>
    </blockquote>

    <dl>
      <dt>IP host-net modules:</dt>
      <dd>
        <code>thunderbolt</code> <?= htmlspecialchars($mod_tb) ?> ·
        <code>thunderbolt_net</code> <?= htmlspecialchars($mod_net) ?>
      </dd>
    </dl>
    <blockquote class="inline_help">
      <strong><code>thunderbolt</code></strong> — host controller / fabric stack (sees ports and devices).<br>
      <strong><code>thunderbolt_net</code></strong> — creates <code>thunderboltN</code> network interfaces
      (what <strong>tbnN</strong> tabs configure: static IP, MTU, …).<br><br>
      Both should be <strong>loaded</strong> for host-to-host IP. This is separate from
      OpenFabric/FRR and from USB4STREAM
      (<?= tbn_docs_more_html('docs/usb4stream.md', 'USB4STREAM explained') ?>).
      Load/E2E options: Thunderbolt → <strong>Settings</strong>.
    </blockquote>

    <dl>
      <dt>Listening includes:</dt>
      <dd>
        <code><?= htmlspecialchars(implode(' ', $include) ?: '(none)') ?></code>
        <span class="tbn-muted"> — SMB / NFS / SSH / web bind list</span>
      </dd>
    </dl>
    <blockquote class="inline_help">
      Names listed in Unraid <code>network-extra.cfg</code> so stock services (SMB, NFS, SSH, web UI)
      may bind on those Thunderbolt interfaces / IPs.<br><br>
      Set per peer under <strong>Peers</strong> (services Yes/No) or on each <strong>tbnN</strong> tab.
      Whole-disk imaging uses <strong>NBD Export</strong> (Network Services → NBD) bound to a Thunderbolt IP —
      that is separate from this include list.
    </blockquote>

    <dl>
      <dt>Multi-hop routing:</dt>
      <dd>
        <strong><?= htmlspecialchars($of_mode_label) ?></strong>
<?php if ($frr_present): ?>
        <span class="tbn-muted"> — FRR present · OpenFabric <strong>policy</strong> on <strong>Settings</strong> · packages via
          <a href="/Settings/NetworkSettings" onclick="return ibigsGotoNetTab('Fabric Routing', event)">Fabric Routing</a></span>
<?php else: ?>
        <span class="tbn-muted"> — static tbn IPs work without FRR · rings / multi-hop need Fabric Routing (companion below)</span>
<?php endif; ?>
      </dd>
    </dl>
    <blockquote class="inline_help">
      <strong>What it is:</strong> optional <em>IP routing</em> across multiple Thunderbolt hops (OpenFabric /
      <code>fabricd</code>), not the cable itself and not a second NIC driver.<br><br>
      <strong>Single cable, two hosts, static IPs:</strong> you do not need this — leave multi-hop alone.<br>
      <strong>Packages</strong> install under Network Settings → <strong>Fabric Routing</strong>
      (Download &amp; Install packages).<br>
      <strong>Policy</strong> (area, metrics, participate) is on Thunderbolt → <strong>Settings</strong>.
    </blockquote>

    <div class="tbn-companion-strip" aria-label="Related plugins">
      <div id="tbn-companion-frr" class="tbn-companion-card<?= $frr_present ? ' tbn-companion-ok' : '' ?>"
           tabindex="-1">
        <div class="tbn-companion-title">Multi-hop (FRR / OpenFabric)</div>
<?php if ($frr_present): ?>
        <p><span class="tbn-companion-status tbn-status-ok">Ready</span>
          FRR packages live. OpenFabric <strong>policy</strong> is on Thunderbolt → <strong>Settings</strong>.
          Package/daemon manager:
          <a href="/Settings/NetworkSettings" onclick="return ibigsGotoNetTab('Fabric Routing', event)">Network Settings → Fabric Routing</a>.</p>
        <p class="tbn-muted tbn-companion-role">
          <strong>Fabric Routing</strong> = install/start FRR (<code>zebra</code>/<code>fabricd</code>).
          <strong>Thunderbolt Net</strong> = underlay tbn IPs + OpenFabric conf/metrics — not packages.
        </p>
<?php elseif (!empty($fabricrouting['plugin_dir'])): ?>
        <p><span class="tbn-companion-status tbn-status-warn">Plugin installed · packages missing</span>
          Open <a href="/Settings/NetworkSettings" onclick="return ibigsGotoNetTab('Fabric Routing', event)">Fabric Routing</a>
          → <strong>Download &amp; Install packages</strong>. Then Thunderbolt → <strong>Settings</strong> → Apply policy.</p>
        <p class="tbn-muted tbn-companion-role">
          When this card turns green, multi-hop rings/meshes can run. Static peer IPs never needed this.
        </p>
<?php else: ?>
        <p><span class="tbn-companion-status tbn-status-warn">Not installed</span>
          Optional. Only if you need <strong>rings, multi-hop, or Proxmox/Linux FRR fabrics</strong>
          (3+ hosts / alternate paths). Single-cable static peers: skip.</p>
        <p>
          Install <strong>Fabric Routing</strong> from Community Apps, or
          <a href="<?= htmlspecialchars($fabricrouting['install_url'] ?? 'https://raw.githubusercontent.com/ibigsnet/FabricRouting/main/fabricrouting.plg') ?>" target="_blank" rel="noopener">Plugins → Install Plugin (raw .plg)</a>
          → Fabric Routing → Download &amp; Install packages.
        </p>
        <p class="tbn-muted tbn-companion-role">
          Packages only (zebra / fabricd). OpenFabric <em>policy</em> is on Thunderbolt → <strong>Settings</strong>.
          Not related to kernel USB4STREAM —
          <?= tbn_docs_more_html('docs/usb4stream.md', 'what that is ↗') ?>.
        </p>
<?php endif; ?>
      </div>
      <div id="tbn-companion-nbd" class="tbn-companion-card<?= $nbd_present ? ' tbn-companion-ok' : '' ?>">
        <div class="tbn-companion-title">Block export (NBD)</div>
<?php if ($nbd_present): ?>
        <p><span class="tbn-companion-status tbn-status-ok">Installed</span>
          <a href="/Settings/NbdExport">Network Services → NBD</a>.
          Whole-disk imaging over a private Thunderbolt IP — separate from SMB/NFS listening.</p>
<?php else: ?>
        <p class="tbn-muted">Optional. Host or pull raw disks over NBD (not a share protocol).
          Search <strong>NBD Export</strong> in CA, or
          <a href="https://github.com/ibigsnet/NbdExport" target="_blank" rel="noopener">GitHub</a>.</p>
<?php endif; ?>
      </div>
      <div id="tbn-companion-usb4stream" class="tbn-companion-card tbn-companion-muted">
        <div class="tbn-companion-title">USB4STREAM (kernel)</div>
        <p class="tbn-muted">
<?php if (!empty($usb4stream['devices'])): ?>
          Devices: <code><?= htmlspecialchars(implode(' ', $usb4stream['devices'])) ?></code>
<?php elseif ($stream_ready): ?>
          Module available — enable under Thunderbolt → <strong>Settings</strong>.
<?php else: ?>
          Not in this kernel<?= !empty($usb4stream['kernel']) ? ' (<code>' . htmlspecialchars($usb4stream['kernel']) . '</code>)' : '' ?>.
          Needs a <strong>Linux kernel</strong> that ships <code>thunderbolt_stream</code> (mainline ~7.2+) —
          <strong>not</strong> “Unraid 7.2+”, not InfiniBand, not Fabric Routing.
<?php endif; ?>
        </p>
      </div>
    </div>
    <p class="tbn-muted" style="margin:0.75em 0 0;font-size:0.9em">
      Companion cards are shortcuts only — they do not replace the blue help above.
      <?= tbn_docs_more_html('docs/requirements.md', 'Requirements ↗') ?>
      · <?= tbn_docs_more_html('docs/usb4stream.md', 'USB4STREAM ↗') ?>
    </p>
  </div>
<?php endif; /* has_hw */ ?>
