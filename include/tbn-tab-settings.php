<?php
if (!$has_hw):
?>
  <div class="tbn-notice" role="status">
    <p>No Thunderbolt host controller — see the <strong>Status</strong> tab for diagnostics.</p>
  </div>
<?php else: ?>
  <form method="POST" action="/update.php" target="progressFrame">
      <input type="hidden" name="#file" value="ThunderboltNet/ThunderboltNet.cfg">
      <input type="hidden" name="#include" value="/plugins/ThunderboltNet/include/tbn-update.php">
      <input type="hidden" name="tbn_defaults" value="">
      <input type="hidden" name="include_listening" value="<?= htmlspecialchars($cfg['include_listening'] ?? 'no') ?>">
      <input type="hidden" name="manage_ip" value="<?= htmlspecialchars($cfg['manage_ip'] ?? 'no') ?>">
      <input type="hidden" name="ip_addr" value="<?= htmlspecialchars($cfg['ip_addr'] ?? '10.255.0.2') ?>">
      <input type="hidden" name="ip_cidr" value="<?= htmlspecialchars($cfg['ip_cidr'] ?? '24') ?>">
      <input type="hidden" name="ip_gateway" value="<?= htmlspecialchars($cfg['ip_gateway'] ?? '') ?>">
      <input type="hidden" name="never_default" value="<?= htmlspecialchars($cfg['never_default'] ?? 'yes') ?>">
      <input type="hidden" name="iface_primary" value="<?= htmlspecialchars($cfg['iface_primary'] ?? 'thunderbolt0') ?>">
      <input type="hidden" name="iface_secondary" value="<?= htmlspecialchars($cfg['iface_secondary'] ?? 'thunderbolt1') ?>">
      <input type="hidden" name="bond_enable" value="<?= htmlspecialchars($cfg['bond_enable'] ?? 'no') ?>">
      <input type="hidden" name="bond_name" value="<?= htmlspecialchars($cfg['bond_name'] ?? 'bond-tb0') ?>">
      <input type="hidden" name="bond_mode" value="<?= htmlspecialchars($cfg['bond_mode'] ?? 'active-backup') ?>">
      <input type="hidden" name="ignore_warnings" value="<?= htmlspecialchars($cfg['ignore_warnings'] ?? '') ?>">
      <input type="hidden" name="address_plan" value="<?= htmlspecialchars($cfg['address_plan'] ?? 'small-lan') ?>">
      <input type="hidden" name="openfabric_auto_install_frr" value="<?= htmlspecialchars($cfg['openfabric_auto_install_frr'] ?? 'yes') ?>">

  <div class="tbn-section tbn-section-routing tbn-advanced"
       data-tbn-advanced="openfabric"
       data-tbn-default-open="<?= $of_expand_default ? '1' : '0' ?>">
    <div class="tbn-advanced-head">
      <h3>Advanced: multi-hop routing (OpenFabric)</h3>
      <button type="button" class="tbn-advanced-toggle" data-tbn-adv-toggle="openfabric"
        data-show="Show OpenFabric settings"
        data-hide="Hide OpenFabric settings">Show OpenFabric settings</button>
<?php if ($frr_present): ?>
      <span class="tbn-advanced-chip tbn-chip-ok"><?= htmlspecialchars($of_mode_label) ?></span>
<?php elseif ($of_mode === 'openfabric-want-frr'): ?>
      <a class="tbn-advanced-chip tbn-chip-warn tbn-chip-link" href="#tbn-companion-frr"
         title="Jump to Multi-hop companion card (install Fabric Routing packages)"><?= htmlspecialchars($of_mode_label) ?> ↗</a>
<?php else: ?>
      <span class="tbn-advanced-chip tbn-chip-muted"><?= htmlspecialchars($of_mode_label) ?></span>
<?php endif; ?>
    </div>
    <p class="tbn-note tbn-advanced-summary">
      Optional <strong>IP routing fabric</strong> for <strong>rings, multi-hop, and mixed Proxmox/Unraid labs</strong>
      (reach a host that is not directly cabled; failover when a link drops).
      <strong>Skip this</strong> if you only use one peer cable with static tbn IPs — that always works without FRR.
    </p>
<?php if (!$frr_present): ?>
    <p class="tbn-note tbn-frr-cta">
      <strong>Needs FRR packages</strong> (this plugin does not install them).
      <a href="#tbn-companion-frr" class="tbn-jump-frr">↑ Jump to Multi-hop companion</a>
      for install status and the Fabric Routing link — then return here for OpenFabric policy (area, metrics, participate).
      <?= tbn_docs_more_html('docs/routing-openfabric.md', 'When to use OpenFabric ↗') ?>
    </p>
<?php else: ?>
    <p class="tbn-note tbn-advanced-summary">
      FRR is present — packages/daemons at <a href="/Settings/NetworkSettings" onclick="return ibigsGotoNetTab('Fabric Routing', event)">Network Settings → Fabric Routing</a>.
      Policy (area, metrics, participate) is on this page.
      <?= tbn_docs_more_html('docs/routing-openfabric.md', 'OpenFabric guide ↗') ?>
      · <?= tbn_docs_more_html('docs/fabric-proxmox-unraid.md', 'Proxmox + Unraid fabric ↗') ?>
    </p>
<?php endif; ?>
    <div class="tbn-advanced-body" id="tbn-adv-openfabric"<?= $of_expand_default ? '' : ' hidden' ?>>
    <dl>
      <dt>OpenFabric status:</dt>
      <dd class="tbn-muted">FRR · router-id · metrics · participate</dd>
    </dl>
    <blockquote class="inline_help">
      <strong>What this is:</strong> OpenFabric (FRR <code>fabricd</code>) is an IS-IS–derived <em>IP routing</em> protocol.
      Thunderbolt Net generates conf, auto <strong>metrics</strong> from trained rate (prefer faster ring hops),
      and applies when FRR is installed. It is <em>not</em> Thunderbolt domain security and not a kernel module.<br><br>
      <strong>When you want it:</strong> 3+ hosts, full/partial <strong>rings</strong>, multi-hop “through a neighbor”,
      hot-plug labs, or interop with <strong>Proxmox/Debian FRR</strong> peers
      (<?= tbn_docs_more_html('docs/fabric-proxmox-unraid.md', 'mixed fabric guide') ?>).
      <strong>When you can skip it:</strong> two hosts, one cable, static IPs only — leave packages uninstalled.<br><br>
      <strong>Who installs FRR?</strong> Not this plugin. Optional companion
      <a href="#tbn-companion-frr">Fabric Routing</a> (Network Settings → Fabric Routing; packages + daemons), or your own FRR.
      <?= tbn_help_docs_footer('docs/routing-openfabric.md', 'FRR / OpenFabric (pros, cost, topologies)') ?>
    </blockquote>
    <?php
      if (!function_exists('tbn_of_status_html')) {
        $__of = '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-openfabric.php';
        if (is_file($__of)) {
          require_once $__of;
        }
      }
      echo function_exists('tbn_of_status_html') ? tbn_of_status_html() : '<p class="tbn-muted">OpenFabric module not installed yet — update plugin files.</p>';
    ?>

    <?php /* Unraid help: one <dl> per setting, then <blockquote class="inline_help"> as next sibling. */ ?>

    <dl>
      <dt>Enable OpenFabric:</dt>
      <dd>
        <select name="openfabric_enable">
          <?= mk_option($cfg['openfabric_enable'] ?? 'yes', 'yes', 'Yes (default — fabric when FRR available)') ?>
          <?= mk_option($cfg['openfabric_enable'] ?? 'yes', 'no', 'No — static underlay only') ?>
        </select>
      </dd>
    </dl>
    <blockquote class="inline_help">
      <strong>Yes</strong> (product default) — generate OpenFabric config, enable IP forwarding for Thunderbolt multi-hop,
      ensure router-id on <code>lo</code>, and start/reload FRR <code>fabricd</code> when present.
      If FRR is missing, behavior degrades to static underlay with a clear status line.<br><br>
      <strong>No</strong> — never manage fabricd for this plugin; classic tbn static only.
      Per-link participate/metric remain on each <strong>tbnN</strong> tab when Yes.
      <?= tbn_help_docs_footer('docs/routing-openfabric.md', 'Should I enable OpenFabric?') ?>
    </blockquote>

    <dl>
      <dt>OpenFabric IPv6:</dt>
      <dd>
        <select name="openfabric_ipv6">
          <?= mk_option($cfg['openfabric_ipv6'] ?? 'yes', 'yes', 'Yes') ?>
          <?= mk_option($cfg['openfabric_ipv6'] ?? 'yes', 'no', 'IPv4 only') ?>
        </select>
      </dd>
    </dl>
    <blockquote class="inline_help">
      When Yes, generated conf also runs <code>ipv6 router openfabric</code> on participating ifaces and enables IPv6 forwarding.
      Use IPv4 only if your fabric peers are v4-only.
    </blockquote>

    <dl>
      <dt>OpenFabric area:</dt>
      <dd>
        <input type="text" name="openfabric_area" maxlength="16" class="narrow"
          value="<?= htmlspecialchars($cfg['openfabric_area'] ?? '1') ?>" placeholder="1">
      </dd>
    </dl>
    <blockquote class="inline_help">
      FRR <code>router openfabric &lt;area&gt;</code> tag. Default <strong>1</strong>. All nodes in the same fabric should match.
    </blockquote>

    <dl>
      <dt>Router ID (/32 on lo):</dt>
      <dd>
        <input type="text" name="openfabric_router_id" maxlength="15" class="narrow"
          value="<?= htmlspecialchars($cfg['openfabric_router_id'] ?? '') ?>"
          placeholder="auto (10.254.x.y)">
      </dd>
    </dl>
    <blockquote class="inline_help">
      Stable IPv4 router-id installed on <code>lo</code> as <code>/32</code> (passive in OpenFabric).
      Empty = auto from machine-id hash in <code>10.254.0.0/16</code> (lab fabric space — not your LAN).
      Set explicitly for multi-Unraid labs so peers can document fixed IDs.
    </blockquote>

    <dl>
      <dt>OpenFabric NET (advanced):</dt>
      <dd>
        <input type="text" name="openfabric_net" maxlength="64" style="width:28em"
          value="<?= htmlspecialchars($cfg['openfabric_net'] ?? '') ?>"
          placeholder="auto from router-id">
      </dd>
    </dl>
    <blockquote class="inline_help">
      Full IS-IS style NET (e.g. <code>49.0001.xxxx.xxxx.xxxx.00</code>). Empty = generated from router-id.
      Only override if you are aligning with an existing fabric design (CCIE/lab).
    </blockquote>

    <dl>
      <dt>Metric reference (Mb/s):</dt>
      <dd>
        <input type="text" name="openfabric_metric_reference_mbps" maxlength="12" class="narrow"
          value="<?= htmlspecialchars($cfg['openfabric_metric_reference_mbps'] ?? '20000') ?>"
          placeholder="20000">
      </dd>
    </dl>
    <blockquote class="inline_help">
      Auto interface metric ≈ <code>reference / trained_Mbps</code> (min 1) — same idea as OSPF auto-cost reference bandwidth.
      Faster trained Thunderbolt links get <strong>lower</strong> cost so rings prefer them.
      Default <strong>20000</strong> (~20&nbsp;Gbit/s — typical Linux Thunderbolt host-net one-way class).
      Auto cost ≈ reference ÷ trained Mb/s (floor 1). Links at or above ~20G share metric <strong>1</strong>;
      use <strong>manual</strong> metric on a tbn tab if a 40/80/100G path (e.g. DAC) must win over 20G Thunderbolt.
      Per-link mode/metric: each <strong>tbn</strong> tab.
      <?= tbn_help_docs_footer('docs/routing-openfabric.md', 'Path cost and metrics') ?>
    </blockquote>
    </div><!-- .tbn-advanced-body openfabric -->
  </div>

  <div class="tbn-section tbn-advanced"
       data-tbn-advanced="mesh"
       data-tbn-default-open="<?= (($cfg['mesh_report'] ?? 'no') === 'yes') ? '1' : '0' ?>">
    <div class="tbn-advanced-head">
      <h3>Advanced: Fabric reports (multi-host)</h3>
      <button type="button" class="tbn-advanced-toggle" data-tbn-adv-toggle="mesh"
        data-show="Show Fabric reports"
        data-hide="Hide Fabric reports">Show Fabric reports</button>
<?php if (($cfg['mesh_report'] ?? 'no') === 'yes'): ?>
      <span class="tbn-advanced-chip tbn-chip-ok">Export on</span>
<?php else: ?>
      <span class="tbn-advanced-chip tbn-chip-muted">Export off</span>
<?php endif; ?>
    </div>
    <div class="tbn-advanced-body" id="tbn-adv-mesh"<?= (($cfg['mesh_report'] ?? 'no') === 'yes') ? '' : ' hidden' ?>>
      <p class="tbn-note">
        Optional peer-to-peer link snapshots between <strong>your</strong> Unraid hosts that run this plugin
        (same LAN or other private path both can reach). Stays on those hosts only —
        nothing is sent to the internet, GitHub, or plugin developers.
        See <?= tbn_docs_more_html('docs/fabric-link-map.md', 'Fabric link map ↗') ?>.
      </p>
      <dl>
        <dt>Enable fabric reports:</dt>
        <dd>
          <select name="mesh_report">
            <?= mk_option($cfg['mesh_report'] ?? 'no', 'no', 'No (default)') ?>
            <?= mk_option($cfg['mesh_report'] ?? 'no', 'yes', 'Yes — export + poll peers') ?>
          </select>
        </dd>
      </dl>
      <blockquote class="inline_help">
        Each Unraid with Thunderbolt Net can export a small JSON snapshot of <em>its</em> links and poll peers
        for <em>theirs</em> (green / orange / red compare).<br><br>
        <strong>Paths:</strong> any private IP both plugins can reach — usually Thunderbolt (<code>tbnN</code>) IPs;
        optionally private Ethernet (field below). A live Thunderbolt cable is not required if you only list eth fabric
        interfaces and peer IPs.<br><br>
        Settings live in this plugin UI; both hosts need the plugin. <strong>Not</strong> a cloud or telemetry service.<br><br>
        <strong>Yes</strong> — peers with the same token may fetch this host’s snapshot; this host polls peers.
        <strong>No</strong> — export off. Orange “unverified” is normal when off.
      </blockquote>
      <dl>
        <dt>Shared token:</dt>
        <dd>
          <input type="text" name="mesh_token" maxlength="64" style="width:28em"
            value="<?= htmlspecialchars($cfg['mesh_token'] ?? '') ?>"
            placeholder="auto-generated on Apply if empty and Yes"
            autocomplete="off">
        </dd>
      </dl>
      <blockquote class="inline_help">
        Secret shared only among your Unraid peers (LAN password). Same value on every host.
        Empty + Yes → generated on Apply. Used host-to-host as <code>X-Tbn-Mesh-Token</code> —
        not uploaded and not printed in diagnostics.
      </blockquote>
      <dl>
        <dt>Poll interval (seconds):</dt>
        <dd>
          <input type="text" name="mesh_poll_secs" maxlength="6" class="narrow"
            value="<?= htmlspecialchars($cfg['mesh_poll_secs'] ?? '60') ?>" placeholder="60">
        </dd>
      </dl>
      <dl>
        <dt>Private IPs only:</dt>
        <dd>
          <select name="mesh_private_only">
            <?= mk_option($cfg['mesh_private_only'] ?? 'yes', 'yes', 'Yes (default) — reject public source IPs') ?>
            <?= mk_option($cfg['mesh_private_only'] ?? 'yes', 'no', 'No — allow any source (not recommended)') ?>
          </select>
        </dd>
      </dl>
      <blockquote class="inline_help">
        Default <strong>Yes</strong> keeps export/poll on private addresses (lab LAN / fabric), not the public internet.
      </blockquote>
      <dl>
        <dt>Mesh peer IPs (optional):</dt>
        <dd>
          <input type="text" name="mesh_peer_ips" maxlength="256" style="width:36em"
            value="<?= htmlspecialchars($cfg['mesh_peer_ips'] ?? '') ?>"
            placeholder="10.255.0.2 192.168.254.4">
        </dd>
      </dl>
      <blockquote class="inline_help">
        Extra private IPv4s to poll (space-separated) — Thunderbolt or Ethernet fabric IPs on peer Unraids.
        Direct Thunderbolt neighbors are often found via <code>ip neigh</code>; add IPs for eth-only peers or multi-hop.
      </blockquote>
      <dl>
        <dt>Also report Ethernet ifaces (optional):</dt>
        <dd>
          <input type="text" name="mesh_eth_ifaces" maxlength="128" style="width:28em"
            value="<?= htmlspecialchars($cfg['mesh_eth_ifaces'] ?? '') ?>"
            placeholder="eth0">
        </dd>
      </dl>
      <blockquote class="inline_help">
        Include private fabric Ethernet in this host’s snapshot (e.g. lab <code>eth0</code> between Unraids).
        Thunderbolt links are included when present. Do not list <code>br0</code> or house Wi‑Fi.
      </blockquote>
      <p class="tbn-muted">
        <a class="tbn-btn-link" href="/plugins/ThunderboltNet/include/tbn-mesh-poll.php?force=1" target="progressFrame">Refresh fabric reports now</a>
        · Host id:
        <code><?= htmlspecialchars(function_exists('tbn_mesh_host_id') ? tbn_mesh_host_id() : '—') ?></code>
      </p>
      <input type="hidden" name="mesh_stale_secs" value="<?= htmlspecialchars($cfg['mesh_stale_secs'] ?? '300') ?>">
      <input type="hidden" name="mesh_holdoff_secs" value="<?= htmlspecialchars($cfg['mesh_holdoff_secs'] ?? '120') ?>">
    </div>
  </div>

  <div class="tbn-section tbn-section-drivers">
    <h3>Driver options</h3>
    <p class="tbn-note">Module load and E2E when you Apply here — not eth0, Docker, or VM IPs (those are on tbnN / Unraid network).</p>
    <dl>
      <dt>Driver options:</dt>
      <dd class="tbn-muted">Modules · E2E flow control · optional USB4STREAM</dd>
    </dl>
    <blockquote class="inline_help">
      Apply updates host drivers, E2E, USB4STREAM load (if enabled), and OpenFabric <strong>policy</strong> above.
      FRR <strong>packages</strong> are managed under <a href="/Settings/NetworkSettings" onclick="return ibigsGotoNetTab('Fabric Routing', event)">Network Settings → Fabric Routing</a> when Fabric Routing is installed.
      Per-link addresses and bonding are on each <strong>tbnN</strong> tab.
      <?= tbn_help_docs_footer('docs/driver-options.md', 'Driver options guide') ?>
    </blockquote>

      <dl>
        <dt>Load modules on Apply:</dt>
        <dd>
          <select name="load_modules">
            <?= mk_option($cfg['load_modules'] ?? 'yes', 'yes', 'Yes') ?>
            <?= mk_option($cfg['load_modules'] ?? 'yes', 'no', 'No') ?>
          </select>
        </dd>
      </dl>
      <blockquote class="inline_help">
        <strong>Yes</strong> (default) — when you click Apply, the plugin runs
        <code>modprobe thunderbolt</code> and <code>modprobe thunderbolt_net</code> so the controller and
        host-to-host network function can appear.<br><br>
        This does <strong>not</strong> unbind the Thunderbolt NHI (host interface) from the host driver.
        Unbinding NHI can hard-wedge some controllers (e.g. Maple Ridge) until reboot.<br><br>
        <strong>No</strong> — only if you already load these modules yourself (for example in
        <code>/boot/config/go</code>) and do not want Apply to touch them.
        <?= tbn_help_docs_footer('docs/driver-options.md', 'Driver options guide') ?>
      </blockquote>

      <dl>
        <dt>E2E flow control:</dt>
        <dd>
          <select name="e2e_flow_control">
            <?= mk_option($cfg['e2e_flow_control'] ?? 'no', 'no', 'No (e2e=0)') ?>
            <?= mk_option($cfg['e2e_flow_control'] ?? 'no', 'yes', 'Yes (driver default)') ?>
          </select>
        </dd>
      </dl>
      <blockquote class="inline_help">
        <strong>Host-wide</strong> setting for the whole Unraid machine — not per tbn0/tbn1 cable.
        It is the <code>thunderbolt_net</code> module parameter <code>e2e</code>
        (<strong>USB4NET end-to-end flow control</strong>). One value applies to every
        <code>thunderboltN</code> interface. You cannot turn it on for one peer and off for another.<br><br>
        <strong>What it does:</strong> when <strong>Yes</strong>, the driver uses full Thunderbolt end-to-end
        credit/flow control on the network tunnel (kernel default is on). When <strong>No (e2e=0)</strong>,
        that path is disabled via <code>options thunderbolt_net e2e=0</code>.<br><br>
        <strong>Product default is No</strong> because many cross-host links (especially Unraid ↔ Linux)
        are more reliable with e2e off: fewer flaky bring-ups, stuck “ThunderboltIP login”, or one-way
        traffic. This is not Ethernet pause on eth0 and not TCP windowing.<br><br>
        <strong>Leave No for:</strong>
        Linux peers, Unraid↔Unraid, multi-peer hosts, or any flaky bring-up with e2e on.
        Default No is the normal starting point for every peer OS — if it works, leave it.<br><br>
        <strong>Try Yes only after No fails:</strong>
        macOS Thunderbolt Bridge or Windows (OEM stacks vary), or specific upstream guidance.
        Change <em>only</em> E2E, Apply, confirm <code>/sys/module/thunderbolt_net/parameters/e2e</code>
        (reboot if it did not flip). Then <strong>reseat the cable</strong> — Thunderbolt often re-trains only when
        the physical path drops. If several Thunderbolt cables were plugged in (especially two to the same peer),
        unplug <em>all</em> first, then plug one known-good cable back in. Retest every peer (host-wide setting).<br><br>
        <strong>Docks / hubs with RJ45:</strong> those ports are usually USB Ethernet, not this setting —
        configure them like eth, not tbn.
        <?= tbn_help_docs_footer('docs/driver-options.md', 'E2E & modules guide') ?>
        · <?= tbn_docs_more_html('docs/peer-scenarios.md', 'Peer scenarios (Mac / Linux / Windows / docks) ↗') ?>
      </blockquote>

      <div class="tbn-advanced tbn-advanced-nested"
           data-tbn-advanced="usb4stream"
           data-tbn-default-open="<?= $stream_expand_default ? '1' : '0' ?>">
        <div class="tbn-advanced-head">
          <h4>Advanced: USB4STREAM (kernel raw path)</h4>
          <button type="button" class="tbn-advanced-toggle" data-tbn-adv-toggle="usb4stream"
            data-show="Show USB4STREAM"
            data-hide="Hide USB4STREAM">Show USB4STREAM</button>
          <span class="tbn-advanced-chip<?= $stream_ready ? ' tbn-chip-ok' : ' tbn-chip-muted' ?>">
            <?= $stream_ready ? htmlspecialchars($mod_stream) : 'not in this kernel' ?>
          </span>
        </div>
        <p class="tbn-note tbn-advanced-summary tbn-muted">
          Optional. Needs a kernel that includes <code>thunderbolt_stream</code>
          (mainline Linux <strong>kernel</strong> ~7.2+ — <em>not</em> Unraid 7.2.x product version).
          Running: <code><?= htmlspecialchars($usb4stream['kernel'] ?? php_uname('r')) ?></code>.
          <strong>Not InfiniBand.</strong> <strong>Not Fabric Routing.</strong>
        </p>
        <div class="tbn-advanced-body" id="tbn-adv-usb4stream"<?= $stream_expand_default ? '' : ' hidden' ?>>
      <dl>
        <dt>Enable USB4STREAM:</dt>
        <dd>
          <select name="enable_usb4stream" <?= empty($usb4stream['available']) ? 'title="Module not in this kernel"' : '' ?>>
            <?= mk_option($cfg['enable_usb4stream'] ?? 'no', 'no', 'No (default)') ?>
            <?= mk_option($cfg['enable_usb4stream'] ?? 'no', 'yes', 'Yes — load thunderbolt_stream when available') ?>
          </select>
        </dd>
      </dl>
      <blockquote class="inline_help">
        <strong>USB4STREAM</strong> is a separate Linux <em>kernel</em> path from <code>thunderbolt_net</code> (tbn0 IP networking).
        When the <strong>running kernel</strong> ships the module, it can expose raw stream devices
        (<code>/dev/tbstream*</code>) for high-speed host↔host transfers without the IP stack.<br><br>
        <strong>“~7.2” = Linux kernel version</strong> (kernel.org / <code>uname -r</code>),
        <strong>not Unraid 7.2.x</strong> and not Slackware package numbering.
        Unraid 7.x can still run a 6.x kernel with no module — check status below, not the Unraid marketing version.
        Do not upgrade Unraid expecting stream unless the new kernel actually includes <code>thunderbolt_stream</code>.<br><br>
        <strong>This is not InfiniBand.</strong> IB-style RDMA over Thunderbolt is research / out-of-tree
        (e.g. thunderbolt-ibverbs demos) — the plugin does not load those modules.<br><br>
        <strong>This is not Fabric Routing.</strong> Fabric Routing installs FRR routing packages (<code>fabricd</code>, etc.);
        it never provides <code>thunderbolt_stream</code>.<br><br>
        <strong>Status on this host:</strong>
        <?= htmlspecialchars($usb4stream['note'] ?? 'unknown') ?><br><br>
        <strong>No</strong> (default) — only use IP host-net (tbn tabs). Correct when the module is missing.<br>
        <strong>Yes</strong> — on Apply, also <code>modprobe thunderbolt_stream</code> if the kernel has it;
        ignored cleanly if the module is missing. Peer must support stream too for a useful path.
        <?= tbn_help_docs_footer('docs/usb4stream.md', 'USB4STREAM guide') ?>
      </blockquote>
        </div><!-- usb4stream body -->
      </div>

      <p class="tbn-actions">
        <input type="submit" name="#apply" value="Apply">
        <input type="button" value="Done" onclick="done()">
      </p>
      <p class="tbn-muted tbn-apply-caption">
        Apply = drivers, E2E, USB4STREAM load, OpenFabric policy, fabric reports.
        FRR packages → <a href="/Settings/NetworkSettings" onclick="return ibigsGotoNetTab('Fabric Routing', event)">Network Settings → Fabric Routing</a>.
        Per-link IPs → top-level <strong>tbnN</strong> tabs on Network Settings (not nested here).
      </p>
  </div>
  </form>

  <dl>
    <dt>Cables and safety:</dt>
    <dd class="tbn-muted">One cable · never unbind NHI</dd>
  </dl>
  <blockquote class="inline_help">
    Prefer a single certified <strong>Thunderbolt / USB4-class</strong> cable that matches both hosts
    (Thunderbolt 3 40G-class, Thunderbolt 4, Thunderbolt 5 / USB4 v2 80G-class when both ends support it — not SS-only SuperSpeed USB).
    Plugin works on any generation Linux exposes as a Thunderbolt host + <code>thunderbolt_net</code>.
    Sticker <strong>40&nbsp;Gb/s</strong> is not PCIe full-duplex 40 each way: lanes are <strong>simplex</strong>, so 40G-class ≈ <strong>full-duplex ~20&nbsp;G</strong> (20 each direction at once).
    <em>Single-lane</em> / 20&nbsp;Gb/s · 1-lane is a common <em>trained</em> result on dual-capable hosts under Linux host-to-host — not “this port is only 20G” and not a failed install.
    Sticker rates (40&nbsp;Gb/s, 80&nbsp;Gb/s, …) are capability class; <strong>trained</strong> rate and lanes are what you get.
    <?= tbn_docs_more_html('docs/standards-and-speeds.md', 'Directionality & speeds ↗') ?><br><br>
    <strong>Never</strong> unbind the Thunderbolt NHI to “fix” networking — that can wedge the controller until reboot.
    <?= tbn_help_docs_footer('docs/standards-and-speeds.md', 'Standards & speeds') ?>
    · <?= tbn_docs_more_html('docs/troubleshooting.md', 'Troubleshooting ↗') ?>
  </blockquote>
<?php endif; /* has_hw */ ?>
