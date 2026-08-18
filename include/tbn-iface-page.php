<?php
/**
 * Per-link tab (Thunderbolt tbnN) — eth0-style dynamic form:
 * protocol (ipv4 / ipv6 / both), automatic/static, bonding members, VLANs.
 */
if (!isset($tbn_if) || $tbn_if === '') {
  echo '<p class="tbn-muted">Missing interface context.</p>';
  return;
}
require_once '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-lib.php';

$if = $tbn_if;
$label = $tbn_label ?? tbn_label_for_iface($if);
$cfg = tbn_load_iface_cfg($if);
$ver = tbn_plugin_version();
$present = is_dir('/sys/class/net/' . $if);

$live = null;
foreach (tbn_list_netdevs() as $n) {
  if ($n['iface'] === $if) {
    $live = $n;
    break;
  }
}
$mac = $live['address'] ?? tbn_sysfs_str('/sys/class/net/' . $if . '/address');
$oper = $live['operstate'] ?? tbn_sysfs_str('/sys/class/net/' . $if . '/operstate');
$carrier = $live['carrier'] ?? tbn_sysfs_str('/sys/class/net/' . $if . '/carrier');
$addrs = $live['addrs'] ?? tbn_iface_addrs($if);
$addrs6 = tbn_iface_addrs6($if);
$master = $live['master'] ?? tbn_iface_master($if);
$membership = $live['masters'] ?? tbn_iface_membership_labels($if, $master);
$is_bond_slave = ($master !== '' && (
  preg_match('/^bond-tb/', $master) || is_dir('/sys/class/net/' . $master . '/bonding')
));
$is_bridge_slave = ($master !== '' && function_exists('tbn_is_bridge_netdev') && tbn_is_bridge_netdev($master));
$is_slave = $is_bond_slave || $is_bridge_slave;
$system_bridges = function_exists('tbn_list_system_bridges') ? tbn_list_system_bridges() : [];
$br_cfg = (string)($cfg['BR_NAME'] ?? '');
// Keep configured bridge in the list even if momentarily missing (e.g. mid-boot)
if ($br_cfg !== '' && !in_array($br_cfg, $system_bridges, true) && preg_match('/^[A-Za-z0-9_.-]+$/', $br_cfg)) {
  $system_bridges[] = $br_cfg;
  natcasesort($system_bridges);
  $system_bridges = array_values($system_bridges);
}
$tb_ifaces = tbn_list_tb_iface_names();
$bond_members_sel = tbn_parse_bond_members($cfg['BOND_MEMBERS'] ?? '', false);
if (!$bond_members_sel && ($cfg['BOND_MEMBERS'] ?? '') === '') {
  // empty means “all” for display check-all
  $bond_members_sel = $tb_ifaces;
}
$vlan_ids = preg_split('/[\s,]+/', trim((string)($cfg['VLAN_LIST'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
$vlan_ids = array_values(array_filter($vlan_ids, function ($v) {
  return preg_match('/^\d+$/', $v) && (int)$v >= 1 && (int)$v <= 4094;
}));

$peer = $rx = $tx = $rl = $tl = '';
$bound_peer_uuid = '';
$parent = @realpath('/sys/class/net/' . $if . '/device');
if ($parent) {
  $up = dirname($parent);
  $peer = tbn_sysfs_str($up . '/device_name');
  if ($peer === '') {
    $peer = tbn_sysfs_str(dirname($up) . '/device_name');
  }
  $bound_peer_uuid = tbn_sysfs_str($up . '/unique_id');
  if ($bound_peer_uuid === '') {
    $bound_peer_uuid = tbn_sysfs_str(dirname($up) . '/unique_id');
  }
  foreach ([dirname($parent), dirname(dirname($parent))] as $c) {
    if (tbn_sysfs_str($c . '/rx_speed') !== '') {
      $rx = tbn_sysfs_str($c . '/rx_speed');
      $tx = tbn_sysfs_str($c . '/tx_speed');
      $rl = tbn_sysfs_str($c . '/rx_lanes');
      $tl = tbn_sysfs_str($c . '/tx_lanes');
      break;
    }
  }
}

$masks = [
  '255.0.0.0' => '8', '255.255.0.0' => '16', '255.255.128.0' => '17', '255.255.192.0' => '18',
  '255.255.224.0' => '19', '255.255.240.0' => '20', '255.255.248.0' => '21', '255.255.252.0' => '22',
  '255.255.254.0' => '23', '255.255.255.0' => '24', '255.255.255.128' => '25', '255.255.255.192' => '26',
  '255.255.255.224' => '27', '255.255.255.240' => '28', '255.255.255.248' => '29', '255.255.255.252' => '30',
];
$nm = $cfg['NETMASK'] ?? '24';
if (strpos($nm, '.') === false) {
  $nm_dotted = array_search($nm, $masks, true);
  if ($nm_dotted === false) {
    $nm_dotted = '255.255.255.0';
  }
} else {
  $nm_dotted = $nm;
}

?>
<link rel="stylesheet" href="/plugins/ThunderboltNet/thunderboltnet.css?v=<?= htmlspecialchars($ver) ?>">

<div class="tbn-wrap">
<?= tbn_docs_bar_html('iface') ?>

<?php if (!$present): ?>
  <div class="tbn-notice">
    <h4>Interface not present</h4>
    <p><code><?= htmlspecialchars($if) ?></code> is not available. Connect a link, then Refresh.</p>
    <p class="tbn-actions">
      <input type="button" value="Refresh" onclick="location.reload()">
      <input type="button" value="Done" onclick="done()">
    </p>
  </div>
<?php else: ?>

  <div class="tbn-iface-lead" role="status">
    <strong><?= htmlspecialchars($label) ?></strong>
    · <code><?= htmlspecialchars($if) ?></code>
    · <?= htmlspecialchars($oper) ?>
    · carrier <?= htmlspecialchars($carrier === '' ? 'n/a' : $carrier) ?>
<?php if ($peer !== ''): ?>
    · peer <strong><?= htmlspecialchars($peer) ?></strong>
<?php endif; ?>
<?php if ($rx !== '' || $tx !== ''): ?>
<?php
  $iface_rate = function_exists('tbn_format_link_rate')
    ? tbn_format_link_rate($rx, $tx, ['rx_lanes' => $rl, 'tx_lanes' => $tl])
    : (($rx ?: '—') . ' / ' . ($tx ?: '—') . ' (lanes ' . ($rl ?: '—') . '/' . ($tl ?: '—') . ')');
?>
    · <?= htmlspecialchars($iface_rate !== '' ? $iface_rate : '—') ?>
<?php endif; ?>
<?php if ($is_slave): ?>
    · <strong>member of <?= htmlspecialchars($master) ?></strong>
<?php endif; ?>
    <span class="tbn-muted"><br>Same idea as eth0: static IP on this path, Apply. Defaults use <code>10.255.N.2/24</code> for thunderboltN.<?php
  if (function_exists('tbn_nat_schema_line')) {
    $schema = tbn_nat_schema_line($if, $cfg);
    if ($schema !== '') {
      echo ' · <code class="tbn-schema-path">' . htmlspecialchars($schema) . '</code>';
    }
  }
?></span>
  </div>

<?php if ($is_bond_slave): ?>
  <div class="tbn-notice" role="status">
    <h4>Bond member</h4>
    <p>
      This interface is enslaved to <code><?= htmlspecialchars($master) ?></code>.
      Addressing and VLAN setup are done on the bond (or leave bonding managed from the tab that created it),
      same idea as eth0 when it is in bond0.
    </p>
  </div>
<?php elseif ($is_bridge_slave): ?>
  <div class="tbn-notice" role="status">
    <h4>Bridge member</h4>
    <p>
      This interface is enslaved to <code><?= htmlspecialchars($master) ?></code>.
      Addressing stays on the bridge (e.g. Unraid <code>br0</code> / VLAN <code>br0.10</code>) —
      same idea as eth0 when bridging is enabled in Network Settings.
    </p>
  </div>
<?php endif; ?>

  <form method="POST" action="/update.php" target="progressFrame"
    id="tbn-form-<?= htmlspecialchars($label) ?>" class="tbn-iface-form"
    data-tbn-slave="<?= $is_slave ? '1' : '0' ?>"
    data-tbn-bound-peer="<?= htmlspecialchars($bound_peer_uuid) ?>">
    <input type="hidden" name="#file" value="ThunderboltNet/ifaces/<?= htmlspecialchars($if) ?>.cfg">
    <input type="hidden" name="#include" value="/plugins/ThunderboltNet/include/tbn-update-iface.php">
    <input type="hidden" name="#arg[1]" value="<?= htmlspecialchars($if) ?>">
    <input type="hidden" name="tbn_iface" value="<?= htmlspecialchars($if) ?>">
    <input type="hidden" name="BOND_MEMBERS" id="tbn-bond-members-hidden" value="<?= htmlspecialchars(trim((string)($cfg['BOND_MEMBERS'] ?? ''))) ?>">

    <dl>
      <dt>Interface description:</dt>
      <dd>
        <input type="text" name="DESCRIPTION" maxlength="80" autocomplete="off" spellcheck="false"
          value="<?= htmlspecialchars($cfg['DESCRIPTION'] ?? '') ?>">
        <span class="inline-block">
          <input type="button" class="form" value="Info" onclick="tbnNetworkInfo('<?= htmlspecialchars($if, ENT_QUOTES) ?>')">
        </span>
      </dd>
    </dl>
    <blockquote class="inline_help">Optional label (plugin config). <strong>Info</strong> shows live link / addresses like eth0.</blockquote>

    <dl>
      <dt>MAC address:</dt>
      <dd><span class="tbn-live"><?= htmlspecialchars(strtoupper($mac)) ?></span></dd>
    </dl>
    <blockquote class="inline_help">Read-only kernel address for <code><?= htmlspecialchars($if) ?></code> (often changes each Thunderbolt link).</blockquote>

    <dl>
      <dt>Enable interface:</dt>
      <dd>
        <select name="ENABLE" <?= $is_slave ? 'disabled' : '' ?>>
          <?= mk_option($cfg['ENABLE'] ?? 'yes', 'yes', 'Yes') ?>
          <?= mk_option($cfg['ENABLE'] ?? 'yes', 'no', 'No') ?>
        </select>
      </dd>
    </dl>
    <blockquote class="inline_help">
      Yes brings the link up and applies addressing. No sets it down (skipped when this iface is a bond member).
    </blockquote>

<?php
  $n_tb_live = count($tb_ifaces);
?>
    <div class="tbn-section-bond">
      <dl>
        <dt>Enable bonding:</dt>
        <dd>
          <select name="BONDING" class="tbn-ctl-bond" <?= $is_slave ? 'disabled' : '' ?>>
            <?= mk_option($cfg['BONDING'] ?? 'no', 'no', 'No') ?>
            <?= mk_option($cfg['BONDING'] ?? 'no', 'yes', 'Yes') ?>
          </select>
        </dd>
      </dl>
      <blockquote class="inline_help">
        Thunderbolt-only bond (<code>bond-tb0</code>, …), not Unraid eth <code>bond0</code>.
        Needs two or more live <code>thunderbolt*</code> members.
        <?= tbn_help_docs_footer('docs/links-and-topology.md', 'Links, bonding & topology') ?>
      </blockquote>

      <div class="tbn-bond-opts tbn-hidden">
        <div class="tbn-notice tbn-bond-wip" role="status">
          <strong>Bonding — experimental / WIP.</strong>
          Prefer one cable per peer until multi-member paths are verified.
          <?= tbn_help_docs_footer('docs/links-and-topology.md', 'Bonding notes') ?>
        </div>
        <dl>
          <dt>Bonding mode:</dt>
          <dd>
            <select name="BONDING_MODE">
              <?= mk_option($cfg['BONDING_MODE'] ?? 'active-backup', 'active-backup', 'active-backup (1)') ?>
              <?= mk_option($cfg['BONDING_MODE'] ?? 'active-backup', 'balance-rr', 'balance-rr (0)') ?>
              <?= mk_option($cfg['BONDING_MODE'] ?? 'active-backup', 'balance-xor', 'balance-xor (2)') ?>
              <?= mk_option($cfg['BONDING_MODE'] ?? 'active-backup', '802.3ad', '802.3ad (4)') ?>
            </select>
          </dd>
        </dl>
        <blockquote class="inline_help">
          Same mode names as eth0. See docs for Thunderbolt bonding caveats.
        </blockquote>

        <dl>
          <dt>Bond name:</dt>
          <dd>
            <input type="text" name="BOND_NAME" class="tbn-ifname" maxlength="15"
              value="<?= htmlspecialchars($cfg['BOND_NAME'] ?? 'bond-tb0') ?>"
              placeholder="bond-tb0">
          </dd>
        </dl>
        <blockquote class="inline_help">
          Default <code>bond-tb0</code>. Do not reuse Unraid eth <code>bond0</code>.
        </blockquote>

        <dl>
          <dt>Bond members:</dt>
          <dd class="tbn-bond-members">
<?php if ($n_tb_live < 2): ?>
            <span class="tbn-muted">Need two+ live thunderbolt* interfaces (currently <?= (int)$n_tb_live ?>).</span>
<?php else: ?>
<?php foreach ($tb_ifaces as $mif):
  $checked = in_array($mif, $bond_members_sel, true);
  $mlb = tbn_label_for_iface($mif);
?>
            <label class="tbn-check">
              <input type="checkbox" class="tbn-bond-member" value="<?= htmlspecialchars($mif) ?>"
                <?= $checked ? 'checked' : '' ?>>
              <code><?= htmlspecialchars($mlb) ?></code> (<code><?= htmlspecialchars($mif) ?></code>)
            </label>
<?php endforeach; ?>
<?php endif; ?>
          </dd>
        </dl>
        <blockquote class="inline_help">
          Live <code>thunderbolt*</code> only. Select at least two.
        </blockquote>
      </div>
    </div>
<?php
  $tbn_frr_live = false;
  if (function_exists('tbn_of_frr_detect')) {
    $tbn_frr_live = !empty(tbn_of_frr_detect()['present']);
  } elseif (is_file('/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-openfabric.php')) {
    require_once '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-openfabric.php';
    $tbn_frr_live = function_exists('tbn_of_frr_detect') && !empty(tbn_of_frr_detect()['present']);
  }
?>
<?php if ($tbn_frr_live): ?>
    <div class="tbn-section-openfabric">
      <dl>
        <dt>OpenFabric participate:</dt>
        <dd>
          <select name="OPENFABRIC_PARTICIPATE" <?= $is_slave ? 'disabled' : '' ?>>
            <?= mk_option($cfg['OPENFABRIC_PARTICIPATE'] ?? 'yes', 'yes', 'Yes (default)') ?>
            <?= mk_option($cfg['OPENFABRIC_PARTICIPATE'] ?? 'yes', 'passive', 'Passive') ?>
            <?= mk_option($cfg['OPENFABRIC_PARTICIPATE'] ?? 'yes', 'no', 'No') ?>
          </select>
        </dd>
      </dl>
      <blockquote class="inline_help">
        When global OpenFabric is On: <strong>Yes</strong> runs this link in the fabric;
        <strong>Passive</strong> advertises without adjacency hellos (rare on Thunderbolt underlay);
        <strong>No</strong> keeps pure static on this cable only.
        <?= tbn_help_docs_footer('docs/routing-openfabric.md', 'OpenFabric / FRR') ?>
      </blockquote>

      <dl>
        <dt>OpenFabric metric mode:</dt>
        <dd>
          <select name="OPENFABRIC_METRIC_MODE" <?= $is_slave ? 'disabled' : '' ?>>
            <?= mk_option($cfg['OPENFABRIC_METRIC_MODE'] ?? 'auto', 'auto', 'Auto from trained rate') ?>
            <?= mk_option($cfg['OPENFABRIC_METRIC_MODE'] ?? 'auto', 'manual', 'Manual') ?>
          </select>
        </dd>
      </dl>
      <blockquote class="inline_help">
        <strong>Auto</strong> — metric ≈ reference_Mbps / trained_Mbps (faster path = lower cost on rings).
        <strong>Manual</strong> — set integer metric below (IS-IS style: lower preferred).
      </blockquote>

      <dl>
        <dt>OpenFabric metric (manual):</dt>
        <dd>
          <input type="text" name="OPENFABRIC_METRIC" class="narrow" maxlength="8"
            value="<?= htmlspecialchars($cfg['OPENFABRIC_METRIC'] ?? '') ?>"
            placeholder="auto" <?= $is_slave ? 'disabled' : '' ?>>
        </dd>
      </dl>
      <blockquote class="inline_help">
        Used only when metric mode is Manual. Leave empty for auto even if mode is manual (falls back to auto).
      </blockquote>
    </div>
<?php else: ?>
    <input type="hidden" name="OPENFABRIC_PARTICIPATE" value="<?= htmlspecialchars($cfg['OPENFABRIC_PARTICIPATE'] ?? 'yes') ?>">
    <input type="hidden" name="OPENFABRIC_METRIC_MODE" value="<?= htmlspecialchars($cfg['OPENFABRIC_METRIC_MODE'] ?? 'auto') ?>">
    <input type="hidden" name="OPENFABRIC_METRIC" value="<?= htmlspecialchars($cfg['OPENFABRIC_METRIC'] ?? '') ?>">
<?php endif; ?>

    <div class="tbn-section-bridge">
      <dl>
        <dt>Enable bridging:</dt>
        <dd>
          <select name="BRIDGING" class="tbn-ctl-bridge" <?= $is_bond_slave ? 'disabled' : '' ?>>
            <?= mk_option($cfg['BRIDGING'] ?? 'no', 'no', 'No') ?>
            <?= mk_option($cfg['BRIDGING'] ?? 'no', 'yes', 'Yes') ?>
          </select>
        </dd>
      </dl>
      <blockquote class="inline_help">
        Optional. Default <strong>No</strong>. Join this Thunderbolt interface into an
        <strong>existing</strong> Unraid bridge (<code>br0</code>, <code>br0.10</code>, …) —
        same idea as eth0 when bridged. Does not create or delete bridges.
        Cannot combine with <strong>Share host uplink (NAT)</strong>.
        <?= tbn_help_docs_footer('docs/addressing.md', 'Addressing / bridging') ?>
      </blockquote>
      <div class="tbn-bridge-opts tbn-hidden">
        <dl>
          <dt>Join bridge:</dt>
          <dd>
<?php if (!$system_bridges): ?>
            <input type="text" name="BR_NAME" class="tbn-ifname" maxlength="15"
              value="<?= htmlspecialchars($br_cfg !== '' ? $br_cfg : 'br0') ?>"
              placeholder="br0" <?= $is_bond_slave ? 'disabled' : '' ?>>
            <p class="tbn-muted" style="margin:0.35em 0 0">No bridges detected yet — type an existing name (e.g. <code>br0</code>) or enable bridging on eth0 first.</p>
<?php else: ?>
            <select name="BR_NAME" class="tbn-ctl-br-name" <?= $is_bond_slave ? 'disabled' : '' ?>>
<?php
  $sel_br = $br_cfg;
  if ($sel_br === '' || !in_array($sel_br, $system_bridges, true)) {
    $sel_br = in_array('br0', $system_bridges, true) ? 'br0' : $system_bridges[0];
  }
  foreach ($system_bridges as $br):
?>
              <?= mk_option($sel_br, $br, $br) ?>
<?php endforeach; ?>
            </select>
<?php endif; ?>
          </dd>
        </dl>
        <blockquote class="inline_help">
          Member has no own IP — addressing stays on the bridge.
          On the <strong>peer</strong>, put a house-LAN address on its Thunderbolt iface
          (LAN DHCP or static in the LAN range) — not a separate <code>10.255.x</code> island.
          Prefer <strong>one</strong> end joining <code>br0</code> unless you understand L2 loops.
          DHCP server on this tab is disabled while joined.
        </blockquote>
      </div>
    </div>

    <div class="tbn-addressing <?= $is_slave ? 'tbn-disabled-block' : '' ?>">
      <dl>
        <dt>Network protocol:</dt>
        <dd>
          <select name="PROTOCOL" class="tbn-ctl-proto" <?= $is_slave ? 'disabled' : '' ?>>
            <?= mk_option($cfg['PROTOCOL'] ?? 'ipv4', 'ipv4', 'IPv4 only') ?>
            <?= mk_option($cfg['PROTOCOL'] ?? 'ipv4', 'ipv6', 'IPv6 only') ?>
            <?= mk_option($cfg['PROTOCOL'] ?? 'ipv4', 'ipv4+ipv6', 'IPv4 + IPv6') ?>
          </select>
        </dd>
      </dl>
      <blockquote class="inline_help">
        Same idea as eth0: choosing IPv4, IPv6, or both shows the matching assignment fields.
      </blockquote>

      <div class="tbn-proto-ipv4 tbn-hidden">
<?php
  $use_dhcp4 = $cfg['USE_DHCP'] ?? 'no';
  if (!in_array($use_dhcp4, ['no', 'yes', 'server'], true)) {
    $use_dhcp4 = 'no';
  }
  $dhcp_safe = (function_exists('tbn_dhcp_server_safe'))
    ? tbn_dhcp_server_safe($if, $cfg)
    : null;
  $coll_hints = function_exists('tbn_underlay_collision_hints')
    ? tbn_underlay_collision_hints($if)
    : [];
?>
<?php if ($coll_hints): ?>
        <div class="tbn-notice tbn-notice-warn" role="status">
          <strong>Duplicate IP:</strong> <?= htmlspecialchars($coll_hints[0]) ?>
          <?= tbn_help_docs_footer('docs/addressing.md', 'Unraid↔Unraid addressing') ?>
        </div>
<?php endif; ?>
<?php
  // Defaults for DHCP server autofill (same plan used by dnsmasq)
  $dhcp_plan_ui = (is_array($dhcp_safe) && !empty($dhcp_safe['plan']))
    ? $dhcp_safe['plan']
    : (function_exists('tbn_dhcp_server_plan') ? tbn_dhcp_server_plan($if, $cfg) : []);
  $dhcp_def_ip = (string)($dhcp_plan_ui['ip'] ?? '10.255.0.1');
  $dhcp_def_pfx = (string)($dhcp_plan_ui['prefix'] ?? '24');
  $dhcp_def_ps = (string)($dhcp_plan_ui['pool_start'] ?? '');
  $dhcp_def_pe = (string)($dhcp_plan_ui['pool_end'] ?? '');
  $pool_start_val = trim((string)($cfg['DHCP_POOL_START'] ?? ''));
  $pool_end_val = trim((string)($cfg['DHCP_POOL_END'] ?? ''));
  if ($pool_start_val === '') {
    $pool_start_val = $dhcp_def_ps;
  }
  if ($pool_end_val === '') {
    $pool_end_val = $dhcp_def_pe;
  }
  // When already in server mode, show host IP from plan if cfg still has seed .2
  $server_ip_val = (string)($cfg['IPADDR'] ?? '');
  if ($use_dhcp4 === 'server' && $server_ip_val === '') {
    $server_ip_val = $dhcp_def_ip;
  }
?>
        <dl>
          <dt>IPv4 address assignment:</dt>
          <dd>
            <select name="USE_DHCP" class="tbn-ctl-dhcp4">
              <?= mk_option($use_dhcp4, 'no', 'Static (Manual User Config)') ?>
              <?= mk_option($use_dhcp4, 'yes', 'DHCP Client (Automatic Assignment)') ?>
              <?= mk_option($use_dhcp4, 'server', 'DHCP Server (Unraid-Managed)') ?>
            </select>
          </dd>
        </dl>
        <blockquote class="inline_help">
          <ul style="margin:0.25em 0 0 1.2em;padding:0">
            <li><strong>Static (Manual User Config)</strong> — you set the IPv4 on this Thunderbolt path. Usual for two known hosts.</li>
            <li><strong>DHCP Client (Automatic Assignment)</strong> — this Unraid asks the far end for an address (may get 169.254/16 if nothing is serving).</li>
            <li><strong>DHCP Server (Unraid-Managed)</strong> — this Unraid hosts DHCP on this Thunderbolt underlay only (not eth0/br0). Clients get addresses from the pool below.</li>
          </ul>
          <?= tbn_help_docs_footer('docs/addressing.md', 'Addressing') ?>
        </blockquote>

        <div class="tbn-ipv4-addr tbn-hidden"
          data-dhcp-default-ip="<?= htmlspecialchars($dhcp_def_ip) ?>"
          data-dhcp-default-prefix="<?= htmlspecialchars($dhcp_def_pfx) ?>"
          data-dhcp-default-pool-start="<?= htmlspecialchars($dhcp_def_ps) ?>"
          data-dhcp-default-pool-end="<?= htmlspecialchars($dhcp_def_pe) ?>">
          <dl>
            <dt class="tbn-ipv4-addr-label-static">IPv4 address:</dt>
            <dt class="tbn-ipv4-addr-label-server tbn-hidden">Unraid IPv4 address:</dt>
            <dd class="tbn-cidr-row">
              <input type="text" name="IPADDR" class="tbn-ip" maxlength="15" value="<?= htmlspecialchars($use_dhcp4 === 'server' ? ($server_ip_val !== '' ? $server_ip_val : $dhcp_def_ip) : ($cfg['IPADDR'] ?? '')) ?>">
              <span class="tbn-cidr-slash">/</span>
              <?php tbn_render_netmask_select('NETMASK', $use_dhcp4 === 'server' ? ($dhcp_def_pfx === '24' && strpos((string)($cfg['NETMASK'] ?? ''), '.') === false ? ($cfg['NETMASK'] ?? '24') : ($cfg['NETMASK'] ?? $dhcp_def_pfx)) : $nm_dotted, $masks); ?>
            </dd>
          </dl>
          <blockquote class="inline_help tbn-ipv4-addr-help-static">
            Unique subnet per tbnN. Recommended: Unraid <code>.1</code>, peer <code>.2</code>
            (e.g. tbn0 <code>10.255.0.1/24</code>). Same <code>.2</code> on both Unraids breaks Peer link check.
            <?= tbn_help_docs_footer('docs/addressing.md', 'Addressing') ?>
          </blockquote>
          <blockquote class="inline_help tbn-ipv4-addr-help-server tbn-hidden">
            Address Unraid uses on this Thunderbolt underlay while serving DHCP (default <code>.1</code> on the link subnet).
            Clients do not use this address; they get leases from the pool.
          </blockquote>
        </div>

        <div class="tbn-dhcp-server-v4 tbn-hidden">
          <dl>
            <dt>DHCP pool:</dt>
            <dd class="tbn-cidr-row">
              <input type="text" name="DHCP_POOL_START" class="tbn-ip" maxlength="15"
                value="<?= htmlspecialchars($pool_start_val) ?>" placeholder="<?= htmlspecialchars($dhcp_def_ps) ?>">
              <span class="tbn-cidr-slash">—</span>
              <input type="text" name="DHCP_POOL_END" class="tbn-ip" maxlength="15"
                value="<?= htmlspecialchars($pool_end_val) ?>" placeholder="<?= htmlspecialchars($dhcp_def_pe) ?>">
            </dd>
          </dl>
<?php
  $dhcp_help_bits = [
    'First and last address handed out on this underlay (same subnet as Unraid’s address above).',
    'Default fills <code>.2</code>–<code>.254</code> when Unraid is <code>.1</code>. Must not include Unraid’s own address.',
  ];
  if (is_array($dhcp_safe)) {
    $st = $dhcp_safe['status'] ?? 'ok';
    $plan = $dhcp_safe['plan'] ?? [];
    $status_lbl = $st === 'block' ? ' — blocked' : ($st === 'warn' ? ' — caution' : '');
    $dhcp_help_bits[] =
      '<strong>DHCP server' . $status_lbl . '</strong>'
      . ' — host <code>' . htmlspecialchars($plan['ip'] ?? '') . '/'
      . htmlspecialchars((string)($plan['prefix'] ?? 24)) . '</code>'
      . ' · pool <code>' . htmlspecialchars(($plan['pool_start'] ?? '') . '–' . ($plan['pool_end'] ?? '')) . '</code>'
      . ' on <code>' . htmlspecialchars($dhcp_safe['netdev'] ?? $if) . '</code>.';
    $dhcp_msgs = array_values(array_filter(array_map('strval', $dhcp_safe['messages'] ?? [])));
    if ($dhcp_msgs) {
      $dhcp_help_bits[] = htmlspecialchars(implode(' ', $dhcp_msgs));
    }
    if ($st === 'block' && function_exists('tbn_dhcp_forum_help_html')) {
      $dhcp_help_bits[] = tbn_dhcp_forum_help_html();
    }
  }
?>
          <blockquote class="inline_help">
            <?= implode(' ', $dhcp_help_bits) ?>
          </blockquote>
        </div>

        <div class="tbn-static-ipv4 tbn-hidden">
          <dl>
            <dt>IPv4 default gateway:</dt>
            <dd>
              <input type="text" name="GATEWAY" class="tbn-ip" maxlength="15" value="<?= htmlspecialchars($cfg['GATEWAY'] ?? '') ?>">
            </dd>
          </dl>
          <blockquote class="inline_help">
            Leave empty for normal peer-to-peer. A gateway on tbn is only useful if this link is an uplink
            (or you have specific routes via that peer). Same risk as default route: wrong gateway/routing here
            can pull traffic off eth0/br0. Rare for host↔host Thunderbolt.
          </blockquote>
          <dl>
            <dt>Enable default route (IPv4):</dt>
            <dd>
              <select name="DEFAULT_ROUTE">
                <?= mk_option($cfg['DEFAULT_ROUTE'] ?? 'no', 'no', 'No') ?>
                <?= mk_option($cfg['DEFAULT_ROUTE'] ?? 'no', 'yes', 'Yes') ?>
              </select>
            </dd>
          </dl>
          <blockquote class="inline_help">
            Default <strong>No</strong> — keep internet on eth0/br0.
            Turning this <strong>Yes</strong> can steal the host default route: Thunderbolt often gets a
            <em>much lower</em> metric (high bandwidth), so traffic that should use LAN/WAN may leave via tbn instead
            and break normal topology. Only enable if this link is intentionally your path to the internet (rare)
            or you know what you are doing (advanced).
            Not the same as <strong>Share host uplink (NAT)</strong> below.
          </blockquote>
<?php
  $nat_en = (($cfg['NAT_ENABLE'] ?? 'no') === 'yes') ? 'yes' : 'no';
  $nat_up = function_exists('tbn_nat_normalize_uplink')
    ? tbn_nat_normalize_uplink($cfg['NAT_UPLINK'] ?? 'auto')
    : (string)($cfg['NAT_UPLINK'] ?? 'auto');
  $nat_choices = function_exists('tbn_nat_uplink_choices') ? tbn_nat_uplink_choices() : ['auto', 'br0', 'eth0', 'wlan0'];
  // Bridging and NAT are mutually exclusive (reconciled again on Apply).
  if (($cfg['BRIDGING'] ?? 'no') === 'yes') {
    $nat_en = 'no';
  }
?>
          <div class="tbn-nat-section">
          <dl>
            <dt>Share host uplink (NAT):</dt>
            <dd>
              <select name="NAT_ENABLE" class="tbn-ctl-nat">
                <?= mk_option($nat_en, 'no', 'No') ?>
                <?= mk_option($nat_en, 'yes', 'Yes') ?>
              </select>
            </dd>
          </dl>
          <blockquote class="inline_help">
            Optional. Default <strong>No</strong> — typical Thunderbolt (copy / SMB between two hosts) does not need it.
            <strong>Yes</strong>: peer keeps a private Thunderbolt address and reaches the internet
            <em>through Unraid</em> (NAT toward <code>br0</code>/<code>eth0</code>/<code>wlan0</code>).
            Example: Proxmox on TB with no other uplink, needs <code>apt update</code>.
            Peer gateway = this Unraid’s tbn IP. Cannot combine with Enable bridging.
            <?= tbn_help_docs_footer('docs/nat-share-uplink.md', 'NAT / share uplink') ?>
          </blockquote>
          <div class="tbn-nat-uplink-opts<?= $nat_en === 'yes' ? '' : ' tbn-hidden' ?>">
            <dl>
              <dt>NAT uplink interface:</dt>
              <dd>
                <select name="NAT_UPLINK">
<?php
  foreach ($nat_choices as $ch) {
    $nat_opt_label = ($ch === 'auto') ? 'Auto (default route iface)' : $ch;
    echo mk_option($nat_up, $ch, $nat_opt_label);
  }
?>
                </select>
              </dd>
            </dl>
            <blockquote class="inline_help">
              Unraid interface that already reaches the internet (or your upstream LAN).
              <strong>Auto</strong> = whatever carries Unraid’s default route.
            </blockquote>
          </div>
          </div>
        </div>
      </div>

      <div class="tbn-proto-ipv6 tbn-hidden">
<?php
  $use_dhcp6 = $cfg['USE_DHCP6'] ?? 'no';
  if (!in_array($use_dhcp6, ['no', 'yes', 'server'], true)) {
    $use_dhcp6 = 'no';
  }
?>
        <dl>
          <dt>IPv6 address assignment:</dt>
          <dd>
            <select name="USE_DHCP6" class="tbn-ctl-dhcp6">
              <?= mk_option($use_dhcp6, 'no', 'Static (Manual User Config)') ?>
              <?= mk_option($use_dhcp6, 'yes', 'DHCP Client (Automatic Assignment)') ?>
              <?= mk_option($use_dhcp6, 'server', 'DHCP Server (Unraid-Managed RA)') ?>
            </select>
          </dd>
        </dl>
        <blockquote class="inline_help">
          <ul style="margin:0.25em 0 0 1.2em;padding:0">
            <li><strong>Static (Manual User Config)</strong> — you set the IPv6 on this path.</li>
            <li><strong>DHCP Client (Automatic Assignment)</strong> — ask the far end for an address.</li>
            <li><strong>DHCP Server (Unraid-Managed RA)</strong> — same dnsmasq as IPv4 server: router advertisements on this underlay only.</li>
          </ul>
          <?= tbn_help_docs_footer('docs/addressing.md', 'Addressing') ?>
        </blockquote>
        <div class="tbn-dhcp-server-v6 tbn-hidden">
          <dl>
            <dt>IPv6 DHCP server:</dt>
            <dd>Uses dnsmasq on this underlay (same engine as IPv4 DHCP server).</dd>
          </dl>
          <blockquote class="inline_help">
            Prefer enabling <strong>IPv4 DHCP Server (Unraid-Managed)</strong> together,
            or IPv6-only RA if you know you need it without v4.
            <?= tbn_help_docs_footer('docs/addressing.md', 'Addressing') ?>
          </blockquote>
        </div>
        <div class="tbn-static-ipv6 tbn-hidden">
          <dl>
            <dt>IPv6 address:</dt>
            <dd class="tbn-cidr-row">
              <input type="text" name="IPADDR6" class="tbn-ip6" maxlength="39" value="<?= htmlspecialchars($cfg['IPADDR6'] ?? '') ?>"
                placeholder="fd00:…">
              <span class="tbn-cidr-slash">/</span>
              <input type="number" name="NETMASK6" class="narrow" min="1" max="128"
                value="<?= htmlspecialchars($cfg['NETMASK6'] ?? '64') ?>">
            </dd>
          </dl>
          <blockquote class="inline_help">Prefix length (e.g. 64). Use a unique ULA/prefix per link when dual-peer.</blockquote>
          <dl>
            <dt>IPv6 default gateway:</dt>
            <dd>
              <input type="text" name="GATEWAY6" class="tbn-ip6" maxlength="39" value="<?= htmlspecialchars($cfg['GATEWAY6'] ?? '') ?>">
            </dd>
          </dl>
          <blockquote class="inline_help">
            Leave empty for peer-to-peer. Only set if tbn is an intentional IPv6 uplink/path via that peer (rare).
          </blockquote>
          <dl>
            <dt>Enable default route (IPv6):</dt>
            <dd>
              <select name="DEFAULT_ROUTE6">
                <?= mk_option($cfg['DEFAULT_ROUTE6'] ?? 'no', 'no', 'No') ?>
                <?= mk_option($cfg['DEFAULT_ROUTE6'] ?? 'no', 'yes', 'Yes') ?>
              </select>
            </dd>
          </dl>
          <blockquote class="inline_help">
            Default <strong>No</strong>. Same caution as IPv4: a Thunderbolt default route can outrank eth0/br0 on metric
            and mess up host routing. Leave No unless tbn is meant to carry internet (rare).
          </blockquote>
        </div>
      </div>
    </div>

    <dl>
      <dt>Include listening interface:</dt>
      <dd>
        <select name="INCLUDE_LISTENING">
          <?= mk_option($cfg['INCLUDE_LISTENING'] ?? 'no', 'no', 'No') ?>
          <?= mk_option($cfg['INCLUDE_LISTENING'] ?? 'no', 'yes', 'Yes') ?>
        </select>
      </dd>
    </dl>
    <blockquote class="inline_help">
      Adds <code><?= htmlspecialchars($if) ?></code> to Unraid <code>include_interfaces</code> (SMB/NFS/SSH/UI).
      Prefer overview <strong>Known peers → Unraid services</strong> for day-to-day.
    </blockquote>

<?php
  $mtu_mode = tbn_normalize_mtu_mode($cfg);
  $mtu_lim = tbn_iface_mtu_limits($if);
  $mtu_live = ($live['mtu'] ?? '') !== ''
    ? $live['mtu']
    : tbn_sysfs_str('/sys/class/net/' . $if . '/mtu');
  $mtu_use = ($mtu_mode !== 'default');
  $mtu_val = '';
  if ($mtu_use) {
    if ($mtu_mode === '9000') {
      $mtu_val = '9000';
    } elseif (($cfg['MTU'] ?? '') !== '') {
      $mtu_val = (string)$cfg['MTU'];
    }
  }
  // eth0-style tooltip on the jumbo checkbox; Thunderbolt driver max is typically ~65522
  $jumbo_title = "Optional: frames larger than 1500 (common jumbo: 9000).\n"
    . "Can reduce packet/CPU overhead on older or weaker hosts during bulk copies.\n"
    . "Driver range on this iface: " . (int)$mtu_lim['min'] . "–" . (int)$mtu_lim['max'] . ".\n"
    . "Both ends must match; mismatch can drop or stall traffic.";
?>
    <dl>
      <dt>Desired MTU:</dt>
      <dd>
        <input type="number" name="MTU" class="narrow tbn-mtu-input" min="<?= (int)$mtu_lim['min'] ?>"
          max="<?= (int)$mtu_lim['max'] ?>" placeholder="1500"
          value="<?= htmlspecialchars($mtu_val) ?>"
          <?= ($mtu_use && !$is_slave) ? '' : 'disabled' ?>>
        <span>
          <input type="hidden" name="USE_MTU" value="no">
          <input type="checkbox" name="USE_MTU" value="yes" class="tbn-ctl-mtu"
            <?= $mtu_use ? 'checked' : '' ?> <?= $is_slave ? 'disabled' : '' ?>>
          Enable jumbo frames
          <i class="fa fa-info-circle blue-text hand" title="<?= htmlspecialchars($jumbo_title) ?>"></i>
        </span>
        <input type="hidden" name="MTU_MODE" class="tbn-mtu-mode" value="<?= htmlspecialchars($mtu_mode) ?>">
      </dd>
    </dl>
    <blockquote class="inline_help">
      Live: <strong><?= htmlspecialchars(tbn_format_mtu_live($mtu_live, $mtu_mode)) ?></strong>
      · driver allows <?= (int)$mtu_lim['min'] ?>–<?= (int)$mtu_lim['max'] ?>
      (thunderbolt_net often ~65522).<br>
      Default <strong>1500</strong> — leave it unless you need jumbo. Optional <strong>9000</strong> on
      <em>both</em> ends can cut packet/CPU cost on older or weaker hosts; MTU is not negotiated.
      Mismatch (9000 vs 1500) can drop or stall traffic.
      <?= tbn_help_docs_footer('docs/mtu-and-throughput.md', 'MTU & throughput') ?>
    </blockquote>

    <div class="tbn-section-vlan <?= $is_slave ? 'tbn-disabled-block' : '' ?>">
      <dl>
        <dt>Enable VLANs:</dt>
        <dd>
          <select name="VLAN_ENABLE" class="tbn-ctl-vlan" <?= $is_slave ? 'disabled' : '' ?>>
            <?= mk_option($cfg['VLAN_ENABLE'] ?? 'no', 'no', 'No') ?>
            <?= mk_option($cfg['VLAN_ENABLE'] ?? 'no', 'yes', 'Yes') ?>
          </select>
        </dd>
      </dl>
      <blockquote class="inline_help">
        Creates <code><?= htmlspecialchars($if) ?>.VID</code> (802.1Q), similar to eth trunk ports.
      </blockquote>
      <div class="tbn-vlan-opts tbn-hidden">
        <dl>
          <dt>VLAN list:</dt>
          <dd>
            <input type="text" name="VLAN_LIST" class="tbn-vlan-list" maxlength="120"
              value="<?= htmlspecialchars($cfg['VLAN_LIST'] ?? '') ?>"
              placeholder="e.g. 10 20 30">
          </dd>
        </dl>
        <blockquote class="inline_help">
          Space- or comma-separated VLAN IDs (1–4094). Apply, then reopen this tab to edit new IDs.
        </blockquote>
<?php foreach ($vlan_ids as $vid):
  $p = 'VLAN_' . $vid . '_';
  $v4 = $cfg[$p . 'IPADDR'] ?? '';
  $vnm = $cfg[$p . 'NETMASK'] ?? '24';
  if (strpos($vnm, '.') === false) {
    $vnm_d = array_search($vnm, $masks, true);
    if ($vnm_d === false) {
      $vnm_d = '255.255.255.0';
    }
  } else {
    $vnm_d = $vnm;
  }
?>
        <div class="tbn-vlan-card">
          <h4>VLAN <?= (int)$vid ?> · <code><?= htmlspecialchars($if . '.' . $vid) ?></code></h4>
          <dl>
            <dt>IPv4 assignment:</dt>
            <dd>
              <select name="<?= htmlspecialchars($p) ?>USE_DHCP">
                <?= mk_option($cfg[$p . 'USE_DHCP'] ?? 'no', 'no', 'Static') ?>
                <?= mk_option($cfg[$p . 'USE_DHCP'] ?? 'no', 'yes', 'Automatic') ?>
              </select>
            </dd>
          </dl>
          <dl>
            <dt>IPv4 address:</dt>
            <dd class="tbn-cidr-row">
              <input type="text" name="<?= htmlspecialchars($p) ?>IPADDR" class="tbn-ip" maxlength="15" value="<?= htmlspecialchars($v4) ?>">
              <span class="tbn-cidr-slash">/</span>
              <?php tbn_render_netmask_select($p . 'NETMASK', $vnm_d, $masks); ?>
            </dd>
          </dl>
          <dl>
            <dt>IPv6 address:</dt>
            <dd class="tbn-cidr-row">
              <input type="text" name="<?= htmlspecialchars($p) ?>IPADDR6" class="tbn-ip6" maxlength="39"
                value="<?= htmlspecialchars($cfg[$p . 'IPADDR6'] ?? '') ?>">
              <span class="tbn-cidr-slash">/</span>
              <input type="number" name="<?= htmlspecialchars($p) ?>NETMASK6" class="narrow" min="1" max="128"
                value="<?= htmlspecialchars($cfg[$p . 'NETMASK6'] ?? '64') ?>">
            </dd>
          </dl>
        </div>
<?php endforeach; ?>
      </div>
    </div>

    <p class="tbn-actions">
      <input type="submit" name="#apply" value="Apply" <?= $is_slave ? '' : 'disabled' ?>>
      <input type="submit" name="#apply" value="Reset" onclick="return tbnConfirmReset(this.form);">
      <input type="button" value="Done" onclick="done()">
    </p>
  </form>

<?php endif; ?>

</div>
<script src="/plugins/ThunderboltNet/thunderboltnet.js?v=<?= htmlspecialchars($ver) ?>"></script>
<script>
(function () {
  if (typeof tbnInitIfaceForm === 'function') {
    tbnInitIfaceForm(document.getElementById('tbn-form-<?= htmlspecialchars($label, ENT_QUOTES) ?>'));
  }
})();
</script>
