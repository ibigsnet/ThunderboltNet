<?php
echo function_exists('tbn_vfio_warning_banner_html') ? tbn_vfio_warning_banner_html($pci ?? null, $cfg ?? null) : '';
if (!$has_hw):
?>
  <div class="tbn-notice" role="status">
    <p>No Thunderbolt host controller — see the <strong>Status</strong> tab for diagnostics.</p>
  </div>
<?php else: ?>
  <div class="tbn-section tbn-section-peers">
    <h3>Known peers</h3>
    <p class="tbn-note">
      One row per <strong>remote host</strong> (not a rear-panel port).
      <strong>Current</strong> is on the path now;
      <strong>Saved</strong> is remembered for that host and reapplied if the path renumbers.
    </p>
<?php if (!$peers_mem): ?>
    <div class="tbn-empty-peers" role="status">
      <p class="tbn-muted" style="margin:0">
        <strong>No remembered peers yet.</strong>
        Link a peer, then open this page or <strong>Apply</strong> on its tbn tab — the host is kept on flash after unplug, and Apply also fills <strong>Saved</strong>.
      </p>
    </div>
<?php else: ?>
    <?php /* Table is not wrapped in the action form — nested forms broke layout. Checkboxes use form= */ ?>
    <div class="tbn-peers-table-wrap">
    <table class="tbn-table tbn-wide tbn-peers-memory">
      <thead>
        <tr>
          <th class="tbn-peers-sel" title="Select row for toolbar actions">
            <span class="tbn-sr-only">Select</span>
          </th>
          <th title="Online = this remote is on a live thunderboltN path right now">Status</th>
          <th title="Unraid↔Unraid trained-rate compare (shared token under Settings). Not FRR.">Link check</th>
          <th>Peer</th>
          <th title="Path slot right now — may renumber after cable order changes">Path</th>
          <th title="IPv4 on this Unraid path right now">Current</th>
          <th title="Remembered IPv4 for this remote — reapplied on reconnect (even if tbn0 becomes tbn1)">Saved</th>
          <th>Unraid services</th>
          <th title="Trained rate · using N of M lanes">Link rate</th>
          <th>Last seen</th>
        </tr>
      </thead>
      <tbody>
<?php
  uasort($peers_mem, function ($a, $b) {
    $ao = !empty($a['online']) ? 1 : 0;
    $bo = !empty($b['online']) ? 1 : 0;
    if ($ao !== $bo) {
      return $bo - $ao;
    }
    return strcmp($b['last_seen'] ?? '', $a['last_seen'] ?? '');
  });
  foreach ($peers_mem as $pkey => $p):
    $online = !empty($p['online']);
    $cap_ml = 0;
    if (function_exists('tbn_controller_capability')) {
      $cap_ml = (int)(tbn_controller_capability()['max_lanes'] ?? 0);
    }
    $rate = function_exists('tbn_format_link_rate')
      ? tbn_format_link_rate(
          $p['last_rx_speed'] ?? '',
          $p['last_tx_speed'] ?? '',
          [
            'rx_lanes' => $p['last_rx_lanes'] ?? '',
            'tx_lanes' => $p['last_tx_lanes'] ?? '',
            'max_lanes' => $cap_ml,
          ]
        )
      : trim(($p['last_rx_speed'] ?? '') . ' / ' . ($p['last_tx_speed'] ?? ''));
    $addrs = $p['last_local_addrs'] ?? [];
    if (is_array($addrs)) {
      $addrs = implode(', ', $addrs);
    }
    $if = $p['last_iface'] ?? '';
    // Prefer live addrs when online
    if ($online && $if !== '') {
      foreach ($links as $L) {
        if (($L['iface'] ?? '') === $if) {
          $la = $L['local']['addrs'] ?? [];
          if ($la) {
            $addrs = implode(', ', $la);
          }
          break;
        }
      }
    }
    $pref = (($p['include_listening'] ?? 'no') === 'yes') ? 'yes' : 'no';
    $live_on = false;
    if ($online && $if !== '') {
      $live_on = in_array($if, $include, true);
    }
    $plan_lbl = function_exists('tbn_peer_plan_label') ? tbn_peer_plan_label($p) : '—';
    $plan = is_array($p['plan'] ?? null) ? $p['plan'] : [];
    $has_plan = function_exists('tbn_peer_plan_is_usable') && tbn_peer_plan_is_usable($plan);
    $live_ip = function_exists('tbn_first_ipv4') ? tbn_first_ipv4($addrs) : '';
    $saved_ip = function_exists('tbn_first_ipv4') ? tbn_first_ipv4($plan_lbl) : '';
    $addr_mismatch = ($online && $live_ip !== '' && $saved_ip !== '' && $live_ip !== $saved_ip);
    $path_health = ['ok' => true, 'label' => '', 'detail' => ''];
    if ($online && $if !== '' && function_exists('tbn_path_underlay_health')) {
      $path_health = tbn_path_underlay_health($if, $addrs, is_array($p) ? $p : []);
    }
    $mesh_val = $p['mesh_validation'] ?? null;
    $mesh_on = function_exists('tbn_mesh_enabled') ? tbn_mesh_enabled($cfg) : false;
    $mesh_row = '';
    if ($mesh_on && is_array($mesh_val)) {
      if (($mesh_val['status'] ?? '') === 'green') $mesh_row = 'tbn-mesh-row-green';
      elseif (($mesh_val['status'] ?? '') === 'red') $mesh_row = 'tbn-mesh-row-red';
      elseif (($mesh_val['status'] ?? '') === 'orange') $mesh_row = 'tbn-mesh-row-orange';
    }
?>
        <tr class="<?= $online ? 'tbn-peer-online' : 'tbn-peer-offline' ?> <?= $pref === 'yes' ? 'tbn-listen-on' : 'tbn-listen-off' ?> <?= $mesh_row ?>"
          data-online="<?= $online ? '1' : '0' ?>"
          data-has-plan="<?= $has_plan ? '1' : '0' ?>">
          <td class="tbn-peers-sel">
            <input type="checkbox" name="tbn_peer_keys[]" value="<?= htmlspecialchars((string)$pkey) ?>"
              form="tbn-peers-action-form"
              class="tbn-peer-sel"
              data-online="<?= $online ? '1' : '0' ?>"
              data-has-plan="<?= $has_plan ? '1' : '0' ?>"
              title="Select for toolbar actions">
          </td>
          <td><?= $online ? '<span class="tbn-badge tbn-badge-ok">Online</span>' : '<span class="tbn-badge tbn-badge-unknown">Offline</span>' ?></td>
          <td class="tbn-mesh-val-cell"><?= function_exists('tbn_mesh_badge_html') ? tbn_mesh_badge_html(is_array($mesh_val) ? $mesh_val : null, $mesh_on) : '—' ?></td>
          <td>
            <strong><?= htmlspecialchars(($p['peer_name'] ?? '') !== '' ? $p['peer_name'] : '—') ?></strong>
<?php if (!empty($p['stack'])): ?>
            <span class="tbn-muted"><br><?= htmlspecialchars($p['stack']) ?></span>
<?php endif; ?>
<?php if (strpos((string)$pkey, 'iface:') !== 0): ?>
            <span class="tbn-muted tbn-uuid-short" title="<?= htmlspecialchars((string)$pkey) ?>"><br><code><?= htmlspecialchars(substr((string)$pkey, 0, 13)) ?>…</code></span>
<?php endif; ?>
          </td>
          <td>
<?php if (!empty($p['last_label'])): ?>
            <a href="/Settings/NetworkSettings" onclick="return ibigsGotoNetTab('<?= htmlspecialchars($p['last_label'], ENT_QUOTES) ?>', event)"><code><?= htmlspecialchars($p['last_label']) ?></code></a>
            <span class="tbn-muted"><br><code><?= htmlspecialchars($if) ?></code></span>
<?php else: ?>
            —
<?php endif; ?>
          </td>
          <td class="<?= $addr_mismatch ? 'tbn-addr-mismatch' : '' ?><?= empty($path_health['ok']) ? ' tbn-path-unreach' : '' ?>"<?= $addr_mismatch ? ' title="Current differs from Saved"' : (!empty($path_health['detail']) ? ' title="' . htmlspecialchars($path_health['detail'], ENT_QUOTES) . '"' : '') ?>>
            <code class="tbn-live" data-tbn-live-ip4="<?= htmlspecialchars($if) ?>"><?= htmlspecialchars($addrs !== '' ? $addrs : '—') ?></code>
<?php if ($online && empty($path_health['ok']) && ($path_health['label'] ?? '') !== ''): ?>
            <span class="tbn-muted tbn-path-unreach-lbl"><br><?= htmlspecialchars($path_health['label']) ?></span>
<?php endif; ?>
          </td>
          <td class="<?= $addr_mismatch ? 'tbn-addr-mismatch' : '' ?>"<?= $addr_mismatch ? ' title="Saved differs from Current — use Apply saved, or Remember current"' : ($has_plan ? ' title="Reapplied on reconnect"' : ' title="None yet — Apply the tbn tab while linked, or Remember current"') ?>>
<?php if ($has_plan): ?>
            <code><?= htmlspecialchars($plan_lbl) ?></code>
<?php else: ?>
            <span class="tbn-muted">—</span>
<?php endif; ?>
          </td>
          <td>
            <form method="POST" action="/update.php" target="progressFrame" class="tbn-listen-form">
              <input type="hidden" name="#file" value="ThunderboltNet/ThunderboltNet.cfg">
              <input type="hidden" name="#include" value="/plugins/ThunderboltNet/include/tbn-update-listening.php">
              <input type="hidden" name="tbn_listen_action" value="set">
              <input type="hidden" name="tbn_iface" value="<?= htmlspecialchars($if) ?>">
              <input type="hidden" name="tbn_peer_key" value="<?= htmlspecialchars((string)$pkey) ?>">
              <select name="INCLUDE_LISTENING" class="tbn-listen-select" onchange="tbnListenSave(this.form)">
                <?= mk_option($pref, 'no', 'No — IP only (default)') ?>
                <?= mk_option($pref, 'yes', 'Yes — SMB / NFS / web UI (recommended)') ?>
              </select>
              <noscript>
                <input type="submit" name="#apply" value="Apply">
              </noscript>
            </form>
<?php if ($pref !== 'yes'): ?>
            <p class="tbn-hint tbn-listen-warn">
              Shares / web UI usually unreachable on this Thunderbolt IP until <strong>Yes</strong>.
            </p>
<?php elseif ($online && !$live_on): ?>
            <p class="tbn-hint">Saved Yes — if still unreachable, check Network Extra / re-select Yes.</p>
<?php elseif ($online && $live_on): ?>
            <p class="tbn-hint">Live on include list (SMB / NFS / SSH / web).</p>
<?php else: ?>
            <p class="tbn-hint">Remembered Yes — applied when this peer is online.</p>
<?php endif; ?>
          </td>
          <td title="Equal rates = full-duplex. Asymmetric = TX to peer · RX from peer."><?= htmlspecialchars($rate !== '' && $rate !== ' / ' ? $rate : '—') ?></td>
          <td class="tbn-muted"><?= function_exists('tbn_format_when_html')
            ? tbn_format_when_html($p['last_seen'] ?? '')
            : htmlspecialchars($p['last_seen'] ?? '—') ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
    </div>

    <form method="POST" action="/update.php" target="progressFrame" id="tbn-peers-action-form" class="tbn-peers-toolbar"
      onsubmit="return tbnPeersToolbarSubmit(this, event);">
      <input type="hidden" name="#file" value="ThunderboltNet/ThunderboltNet.cfg">
      <input type="hidden" name="#include" value="/plugins/ThunderboltNet/include/tbn-update-peers.php">
      <input type="hidden" name="tbn_peer_action" id="tbn_peer_action" value="">
      <p class="tbn-actions tbn-peers-actions">
        <input type="submit" name="#apply" value="Forget selected" class="tbn-harden-btn"
          data-tbn-action="forget"
          title="Remove selected hosts from this list (Saved address included). Does not delete tbn tab configs.">
        <input type="submit" name="#apply" value="Remember current" class="tbn-btn-small"
          data-tbn-action="capture_plan"
          title="Copy Current (IP/MTU/services) into Saved for each selected online peer">
        <input type="submit" name="#apply" value="Apply saved" class="tbn-btn-small"
          data-tbn-action="apply_plan"
          title="Push Saved onto the live path for each selected online peer">
      </p>
      <p class="tbn-hint">
        Select rows, then <strong>Remember current</strong> (Current → Saved) or <strong>Apply saved</strong> (Saved → path).
        <strong>Forget</strong> removes the row only — not <code>ifaces/*.cfg</code>.
      </p>
    </form>
<?php endif; ?>

    <div class="tbn-peers-toolbar-foot">
    <form method="POST" action="/update.php" target="progressFrame" class="tbn-harden-form"
      onsubmit="return confirm('Turn OFF Unraid host services on ALL Thunderbolt links and clear remembered Yes for every peer?');">
      <input type="hidden" name="#file" value="ThunderboltNet/ThunderboltNet.cfg">
      <input type="hidden" name="#include" value="/plugins/ThunderboltNet/include/tbn-update-listening.php">
      <input type="hidden" name="tbn_listen_action" value="harden_all">
      <p class="tbn-actions">
        <input type="submit" name="#apply" value="Harden: all peers services No" class="tbn-harden-btn">
      </p>
      <p class="tbn-hint">
        Security: all peers → services <strong>No</strong>; strip Thunderbolt ifaces from network-extra include list.
      </p>
    </form>
    </div>

<?php
  // Orange only for confirmed ARP duplicate-IP (same addr, foreign MAC)
  $tbn_coll = function_exists('tbn_underlay_collision_hints') ? tbn_underlay_collision_hints(null) : [];
  if ($tbn_coll):
?>
    <div class="tbn-notice tbn-notice-warn" role="status">
      <strong>Duplicate IP:</strong> <?= htmlspecialchars($tbn_coll[0]) ?>
      <?php if (count($tbn_coll) > 1): ?>
        <span class="tbn-muted"> (+<?= count($tbn_coll) - 1 ?> more)</span>
      <?php endif; ?>
      <?= tbn_help_docs_footer('docs/addressing.md', 'Unraid↔Unraid addressing') ?>
    </div>
<?php endif; ?>

    <details class="tbn-details-help">
      <summary>How Known peers works</summary>
      <div class="tbn-details-body">
        <p><strong>Identity</strong> — remote fabric UUID (not a port, not Interface Rules / MAC).</p>
        <p><strong>Current / Saved</strong> — Current is on the path now. Saved is remembered for that host and reapplied if the path renumbers. First <strong>tbn Apply</strong> while linked fills Saved automatically. Highlight when they disagree; toolbar syncs later drift.</p>
        <p><strong>Link check</strong> — optional Unraid↔Unraid rate compare (token under Settings). Not FRR.</p>
        <?= tbn_help_docs_footer('docs/peers-and-plans.md', 'Peers & plans') ?>
        · <?= tbn_help_docs_footer('docs/settings-reference.md', 'Settings reference') ?>
      </div>
    </details>

<?php
  // One panel only: includes the Match/Unverified legend once (do not also call tbn_mesh_legend_html here).
  if (function_exists('tbn_mesh_reports_panel_html')) {
    echo tbn_mesh_reports_panel_html($cfg);
  }
?>
  </div>

  <div class="tbn-section tbn-section-links">
    <h3>Links</h3>
    <p class="tbn-note">
      <strong>LOCAL</strong> = this Unraid (host class max).
      Each <strong>REMOTE</strong> = one live peer path (tab <code>tbnN</code> / <code>thunderboltN</code>).
      Compare table appears when a peer is online.
    </p>
    <details class="tbn-details-help">
      <summary>How to read Links (LOCAL · REMOTE · lanes · quality)</summary>
      <div class="tbn-details-body">
        <p>Click a REMOTE tab name to open that tbn settings page.</p>
        <p><strong>Link rate / lanes</strong> — LOCAL is this host’s <em>capability</em> (not the live hop).
        REMOTE is the <strong>trained</strong> path on that cable. “Using 1 of 2 lanes” means the link trained single-lane
        even though the host class is dual-lane capable — common for Linux↔Linux host-net; both sides can show the same.</p>
        <p>USB4/Thunderbolt lanes are simplex: marketing 40&nbsp;Gb/s class is often ~20&nbsp;Gb/s each direction at once.</p>
        <p><strong>Link quality</strong> — trained rate first; yellow single-lane is below class max, not a failed install.
        ~10–15&nbsp;Gbit/s TCP is normal for 1-lane.</p>
        <?= tbn_docs_more_html('docs/standards-and-speeds.md', 'Directionality & speeds ↗') ?>
        · <?= tbn_help_docs_footer('docs/links-and-topology.md', 'Links & topology') ?>
        · <?= tbn_docs_more_html('docs/peer-scenarios.md', 'Peer scenarios ↗') ?>
      </div>
    </details>

<?php if (!$links):
  $tbn_cap = function_exists('tbn_controller_capability')
    ? tbn_controller_capability()
    : ['max_short' => '', 'max_lanes' => 2, 'max_gbps' => 0, 'label' => ''];
?>
    <div class="tbn-empty-links" role="status">
      <p class="tbn-empty-links-lead">
        <strong>No live peer paths</strong> — plug a Thunderbolt/USB4 host peer and Refresh.
        The LOCAL / REMOTE compare table only appears when at least one <code>thunderboltN</code> link is up.
      </p>
      <table class="tbn-table tbn-summary tbn-host-only">
        <tr><td>This host</td><td><code><?= htmlspecialchars($status['hostname'] !== '' ? $status['hostname'] : '—') ?></code></td></tr>
        <tr><td>Controller</td><td><?= htmlspecialchars($local_ctrl !== '' ? $local_ctrl : '—') ?></td></tr>
        <tr><td>Manufacturer</td><td><?= htmlspecialchars($local_mfg !== '' ? $local_mfg : '—') ?></td></tr>
        <tr><td>Host max class</td>
          <td><?php
            $mx = $tbn_cap['max_short'] ?? '';
            echo $mx !== '' ? htmlspecialchars('Max ' . $mx) : '—';
            if (!empty($tbn_cap['max_lanes'])) {
              echo ' · ' . htmlspecialchars((int)$tbn_cap['max_lanes'] . '-lane capable');
            }
          ?></td>
        </tr>
      </table>
    </div>
<?php else:
  $nLinks = count($links);
  $qualities = [];
  foreach ($links as $i => $L) {
    $qualities[$i] = tbn_link_quality($L['remote'], $status);
  }
  $tbn_cap = function_exists('tbn_controller_capability')
    ? tbn_controller_capability()
    : ['max_short' => '', 'max_lanes' => 2, 'max_gbps' => 0];
?>
    <div class="tbn-compare-scroll">
    <table class="tbn-table tbn-wide tbn-compare">
      <thead>
        <tr>
          <th class="tbn-compare-rowhead"></th>
          <th class="tbn-col-local">LOCAL</th>
<?php foreach ($links as $L): ?>
          <th class="tbn-col-remote">REMOTE</th>
<?php endforeach; ?>
        </tr>
        <tr class="tbn-compare-subhead">
          <th class="tbn-compare-rowhead"></th>
          <th class="tbn-col-local">this host</th>
<?php foreach ($links as $L): ?>
          <th class="tbn-col-remote">
            <a href="/Settings/NetworkSettings" onclick="return ibigsGotoNetTab('<?= htmlspecialchars($L['label'], ENT_QUOTES) ?>', event)"><code><?= htmlspecialchars($L['label']) ?></code></a>
            <span class="tbn-muted"><br><code><?= htmlspecialchars($L['iface']) ?></code></span>
          </th>
<?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <tr>
          <th scope="row">Name</th>
          <td class="tbn-col-local">
            <code><?= htmlspecialchars($status['hostname'] !== '' ? $status['hostname'] : '—') ?></code>
            <span class="tbn-muted"> (OS hostname)</span>
          </td>
<?php foreach ($links as $L):
  $rem = $L['remote'];
?>
          <td class="tbn-col-remote">
            <code><?= htmlspecialchars($rem['peer_name'] !== '' ? $rem['peer_name'] : '—') ?></code>
            <span class="tbn-muted"> (peer name)</span>
          </td>
<?php endforeach; ?>
        </tr>
        <tr>
          <th scope="row">Product / identity</th>
          <td class="tbn-col-local">
            <?= htmlspecialchars($local_ctrl !== '' ? $local_ctrl : '—') ?>
            <span class="tbn-muted"> (Thunderbolt controller)</span>
          </td>
<?php foreach ($links as $L):
  $rem = $L['remote'];
?>
          <td class="tbn-col-remote">
            <?= htmlspecialchars($rem['peer_name'] !== '' ? $rem['peer_name'] : '—') ?>
            <span class="tbn-muted"> (advertised name)</span>
          </td>
<?php endforeach; ?>
        </tr>
        <tr>
          <th scope="row">Manufacturer / stack</th>
          <td class="tbn-col-local">
            <?= htmlspecialchars($local_mfg !== '' ? $local_mfg : '—') ?>
            <span class="tbn-muted"> (OEM / board)</span>
          </td>
<?php foreach ($links as $L):
  $rem = $L['remote'];
?>
          <td class="tbn-col-remote">
            <?= htmlspecialchars($rem['stack'] !== '' ? $rem['stack'] : '—') ?>
            <span class="tbn-muted"> (Thunderbolt stack)</span>
          </td>
<?php endforeach; ?>
        </tr>
        <tr>
          <th scope="row">Link rate</th>
          <td class="tbn-col-local tbn-muted">
<?php
  $max_rx = !empty($tbn_cap['max_short'])
    ? $tbn_cap['max_short']
    : '—';
  // LOCAL = host ceiling; live rates are only meaningful on each REMOTE hop
  echo htmlspecialchars($max_rx !== '—' ? ('Host max ' . $max_rx . ' (class)') : '—');
?>
          </td>
<?php foreach ($links as $L):
  $rem = $L['remote'];
  $link_rate = function_exists('tbn_format_link_rate')
    ? tbn_format_link_rate(
        $rem['rx_speed'] ?? '',
        $rem['tx_speed'] ?? '',
        [
          'rx_lanes' => $rem['rx_lanes'] ?? '',
          'tx_lanes' => $rem['tx_lanes'] ?? '',
          'show_lanes' => true,
          'max_lanes' => (int)($tbn_cap['max_lanes'] ?? 0),
        ]
      )
    : '';
  if ($link_rate === '') {
    $link_rate = '—';
  }
?>
          <td class="tbn-col-remote" title="Equal = full-duplex. Asymmetric = TX to peer · RX from peer.">
            <strong><?= htmlspecialchars($link_rate) ?></strong>
          </td>
<?php endforeach; ?>
        </tr>
        <tr>
          <th scope="row">Lanes</th>
          <td class="tbn-col-local tbn-muted" title="Host class capability — not this hop’s trained lanes">
            <?= function_exists('tbn_format_lanes_cell')
              ? htmlspecialchars(tbn_format_lanes_cell('', '', (int)($tbn_cap['max_lanes'] ?? 0), 'local'))
              : (!empty($tbn_cap['max_lanes']) ? htmlspecialchars('Host capable of ' . (int)$tbn_cap['max_lanes'] . ' lanes') : '—') ?>
          </td>
<?php foreach ($links as $L):
  $rem = $L['remote'];
  $lane_txt = function_exists('tbn_format_lanes_cell')
    ? tbn_format_lanes_cell($rem['rx_lanes'] ?? '', $rem['tx_lanes'] ?? '', (int)($tbn_cap['max_lanes'] ?? 0), 'remote')
    : (($rem['rx_lanes'] !== '' ? $rem['rx_lanes'] : '—') . ' / ' . ($rem['tx_lanes'] !== '' ? $rem['tx_lanes'] : '—'));
?>
          <td class="tbn-col-remote" title="Trained on this cable. Both hosts can show 1 of 2 when Linux trains single-lane.">
            <?= htmlspecialchars($lane_txt) ?>
          </td>
<?php endforeach; ?>
        </tr>
        <tr>
          <th scope="row">Link quality</th>
          <td class="tbn-col-local tbn-cap-local">
<?php
  // LOCAL = this host’s max class + ports. REMOTE cells = trained path vs max.
  echo function_exists('tbn_controller_capability_html')
    ? tbn_controller_capability_html($tbn_cap)
    : '<span class="tbn-muted">Controller capability vs trained path</span>';
?>
          </td>
<?php foreach ($links as $i => $L):
  $q = $qualities[$i];
?>
          <td class="tbn-col-remote tbn-quality-<?= htmlspecialchars($q['level']) ?>">
            <?= tbn_link_quality_html($q) ?>
          </td>
<?php endforeach; ?>
        </tr>
        <tr>
          <th scope="row">Activity / unplug</th>
          <td class="tbn-col-local tbn-muted">
            Traffic heuristic · auto-updates every few seconds
          </td>
<?php foreach ($links as $L):
  $act = $L['activity'] ?? ['level' => 'unknown', 'label' => 'Measuring…', 'safe_unplug' => 'unknown', 'note' => '', 'bps' => null];
  $ifc = htmlspecialchars($L['iface'] ?? '');
?>
          <td class="tbn-col-remote tbn-act-<?= htmlspecialchars($act['level'] ?? 'unknown') ?>"
            data-tbn-live-act="<?= $ifc ?>">
            <?= tbn_activity_html($act) ?>
          </td>
<?php endforeach; ?>
        </tr>
        <tr>
          <th scope="row">Local iface state</th>
          <td class="tbn-col-local tbn-muted">Per-link netdev (see remote columns)</td>
<?php foreach ($links as $L):
  $loc = $L['local'];
  $if_cfg_mtu = function_exists('tbn_load_iface_cfg') ? tbn_load_iface_cfg($L['iface'] ?? '') : [];
  $mtu_mode = function_exists('tbn_normalize_mtu_mode') ? tbn_normalize_mtu_mode($if_cfg_mtu) : 'default';
  $mtu_label = function_exists('tbn_format_mtu_live')
    ? tbn_format_mtu_live($loc['mtu'] ?? '', $mtu_mode)
    : (($loc['mtu'] ?? '') !== '' ? $loc['mtu'] : '—');
?>
          <td class="tbn-col-remote">
            <?= htmlspecialchars($loc['operstate'] ?: '—') ?>
            · carrier <?= htmlspecialchars($loc['carrier'] === '' ? 'n/a' : $loc['carrier']) ?>
            · MTU <?= htmlspecialchars($mtu_label) ?>
          </td>
<?php endforeach; ?>
        </tr>
        <tr>
          <th scope="row">Local MAC</th>
          <td class="tbn-col-local tbn-muted">Per-link</td>
<?php foreach ($links as $L):
  $loc = $L['local'];
?>
          <td class="tbn-col-remote"><code><?= htmlspecialchars(strtoupper($loc['mac'] ?: '—')) ?></code></td>
<?php endforeach; ?>
        </tr>
        <tr>
          <th scope="row">Local IPv4 (live)</th>
          <td class="tbn-col-local tbn-muted">Per-link · set on tbn tab</td>
<?php foreach ($links as $L):
  $loc = $L['local'];
?>
          <td class="tbn-col-remote">
            <code><?= htmlspecialchars($loc['addrs'] ? implode(', ', $loc['addrs']) : '—') ?></code>
          </td>
<?php endforeach; ?>
        </tr>
        <tr>
          <th scope="row">Unraid services</th>
          <td class="tbn-col-local tbn-muted">See Known peers to change</td>
<?php foreach ($links as $L):
  $loc = $L['local'];
  $listening = !empty($loc['listening']);
?>
          <td class="tbn-col-remote">
<?php if ($listening): ?>
            <span class="tbn-badge tbn-badge-ok">Listening</span>
<?php else: ?>
            <span class="tbn-badge tbn-badge-warn">Not listening</span>
            <span class="tbn-muted"> · set in Known peers</span>
<?php endif; ?>
          </td>
<?php endforeach; ?>
        </tr>
        <tr>
          <th scope="row">Bond / bridge</th>
          <td class="tbn-col-local tbn-muted">Per-link</td>
<?php foreach ($links as $L):
  $loc = $L['local'];
  $mem = !empty($loc['membership'])
    ? implode(' ', $loc['membership'])
    : (!empty($loc['master']) ? $loc['master'] : 'none');
?>
          <td class="tbn-col-remote"><code><?= htmlspecialchars($mem) ?></code></td>
<?php endforeach; ?>
        </tr>
        <tr>
          <th scope="row">Fabric UUID / service</th>
          <td class="tbn-col-local tbn-muted">Local controller on Fabric devices below</td>
<?php foreach ($links as $L):
  $rem = $L['remote'];
?>
          <td class="tbn-col-remote">
<?php if ($rem['unique_id'] !== ''): ?>
            <code class="tbn-uuid"><?= htmlspecialchars($rem['unique_id']) ?></code>
            <?php if ($rem['service'] !== ''): ?><br>service <code><?= htmlspecialchars($rem['service']) ?></code><?php endif; ?>
<?php else: ?>
            <span class="tbn-muted">No peer fabric entry</span>
<?php endif; ?>
          </td>
<?php endforeach; ?>
        </tr>
        <tr>
          <th scope="row">LLDP</th>
          <td class="tbn-col-local tbn-muted">Optional; Thunderbolt fabric above is primary</td>
<?php foreach ($links as $L):
  $lldp = $L['lldp'] ?? ['summary' => ''];
?>
          <td class="tbn-col-remote tbn-muted"><?= htmlspecialchars($lldp['summary'] !== '' ? $lldp['summary'] : 'n/a') ?></td>
<?php endforeach; ?>
        </tr>
        <tr>
          <th scope="row">Settings</th>
          <td class="tbn-col-local tbn-muted">Driver options below · IPs on tbn tabs</td>
<?php foreach ($links as $L): ?>
          <td class="tbn-col-remote">
            <a href="/Settings/NetworkSettings" onclick="return ibigsGotoNetTab('<?= htmlspecialchars($L['label'], ENT_QUOTES) ?>', event)">Open <?= htmlspecialchars($L['label']) ?> tab</a>
          </td>
<?php endforeach; ?>
        </tr>
      </tbody>
    </table>
    </div>
<?php endif; ?>
  </div>
<?php endif; /* has_hw */ ?>
