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
$parent = @realpath('/sys/class/net/' . $if . '/device');
if ($parent) {
  $up = dirname($parent);
  $peer = tbn_sysfs_str($up . '/device_name');
  if ($peer === '') {
    $peer = tbn_sysfs_str(dirname($up) . '/device_name');
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

  <p class="tbn-note">
    <code><?= htmlspecialchars($label) ?></code>
    · <code><?= htmlspecialchars($if) ?></code>
    · <?= htmlspecialchars($oper) ?> / carrier <?= htmlspecialchars($carrier === '' ? 'n/a' : $carrier) ?>
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
<?php if ($is_bond_slave): ?>
    · <strong>member of <?= htmlspecialchars($master) ?></strong>
<?php endif; ?>
  </p>

<?php if ($is_bond_slave): ?>
  <div class="tbn-notice" role="status">
    <h4>Bond member</h4>
    <p>
      This interface is enslaved to <code><?= htmlspecialchars($master) ?></code>.
      Addressing and VLAN setup are done on the bond (or leave bonding managed from the tab that created it),
      same idea as eth0 when it is in bond0.
    </p>
  </div>
<?php endif; ?>

  <form method="POST" action="/update.php" target="progressFrame"
    id="tbn-form-<?= htmlspecialchars($label) ?>" class="tbn-iface-form"
    data-tbn-slave="<?= $is_bond_slave ? '1' : '0' ?>">
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
      </dd>
    </dl>
    <blockquote class="inline_help">Optional label for this link (stored in plugin config only).</blockquote>

    <dl>
      <dt>MAC address:</dt>
      <dd><span class="tbn-live"><?= htmlspecialchars(strtoupper($mac)) ?></span></dd>
    </dl>
    <blockquote class="inline_help">Read-only kernel address for <code><?= htmlspecialchars($if) ?></code>.</blockquote>

    <dl>
      <dt>Enable interface:</dt>
      <dd>
        <select name="ENABLE" <?= $is_bond_slave ? 'disabled' : '' ?>>
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
  $bond_want = ($cfg['BONDING'] ?? 'no') === 'yes';
  // Hide bond UI unless ≥2 live thunderbolt* or user already enabled bonding
  $show_bond_section = $n_tb_live >= 2 || $bond_want || $is_bond_slave;
?>
<?php if ($show_bond_section): ?>
    <div class="tbn-section-bond">
      <dl>
        <dt>Enable bonding:</dt>
        <dd>
          <select name="BONDING" class="tbn-ctl-bond" <?= $is_bond_slave ? 'disabled' : '' ?>>
            <?= mk_option($cfg['BONDING'] ?? 'no', 'no', 'No') ?>
            <?= mk_option($cfg['BONDING'] ?? 'no', 'yes', 'Yes — when ≥2 live Thunderbolt netdevs') ?>
          </select>
        </dd>
      </dl>
      <blockquote class="inline_help">
        Thunderbolt-only Linux bond (<code>bond-tb0</code>, …), not Unraid eth <code>bond0</code>.
        Needs <strong>two or more live</strong> <code>thunderbolt*</code> netdevs already present
        (today usually two <em>different</em> peers). Two cables to the <em>same</em> peer often still yield
        <strong>one</strong> netdev — bonding cannot invent a second slave, and Thunderbolt slaves may reject
        <code>set_mac</code> (many bond modes fail). Apply with fewer than two members is ignored.<br><br>
        <strong>Roadmap:</strong> better dual-path detection and bond+OpenFabric metrics when the kernel
        exposes two usable paths — dual-cable bonding is <em>not</em> a non-goal.
        <?= tbn_help_docs_footer('docs/links-and-topology.md', 'Links, bonding & topology') ?>
      </blockquote>

      <div class="tbn-bond-opts tbn-hidden">
        <dl>
          <dt>Bonding mode:</dt>
          <dd>
            <select name="BONDING_MODE">
              <?= mk_option($cfg['BONDING_MODE'] ?? 'active-backup', 'active-backup', 'active-backup (1) — least bad') ?>
              <?= mk_option($cfg['BONDING_MODE'] ?? 'active-backup', 'balance-rr', 'balance-rr (0)') ?>
              <?= mk_option($cfg['BONDING_MODE'] ?? 'active-backup', 'balance-xor', 'balance-xor (2)') ?>
              <?= mk_option($cfg['BONDING_MODE'] ?? 'active-backup', '802.3ad', '802.3ad (4) — usually fails on Thunderbolt') ?>
            </select>
          </dd>
        </dl>
        <blockquote class="inline_help">
          Prefer <strong>active-backup</strong> if you truly have two Thunderbolt netdevs. 802.3ad/LACP is a poor fit
          for <code>thunderbolt_net</code> (no set_mac, flaky MII).
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
          Default <code>bond-tb0</code> (then <code>bond-tb1</code>, …). Do not reuse Unraid eth <code>bond0</code>.
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
          Live <code>thunderbolt*</code> only. Select at least two. Same-peer dual-cable does not create two members.
        </blockquote>
      </div>
    </div>
<?php else: ?>
    <input type="hidden" name="BONDING" value="no">
    <blockquote class="inline_help">
      <strong>Bonding:</strong> not offered with a single live <code>thunderbolt*</code> path (the common case).
      Same-peer dual-cable often still yields one netdev today; multi-path bonding is a roadmap item when
      two paths appear. If the Thunderbolt domain wedges after dual-cable tests: unplug <em>all</em> Thunderbolt cables on
      <em>both</em> machines, wait, plug one cable only.
      <?= tbn_help_docs_footer('docs/links-and-topology.md', 'Bonding roadmap') ?>
    </blockquote>
<?php endif; ?>

    <div class="tbn-section-openfabric">
      <dl>
        <dt>OpenFabric participate:</dt>
        <dd>
          <select name="OPENFABRIC_PARTICIPATE" <?= $is_bond_slave ? 'disabled' : '' ?>>
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
          <select name="OPENFABRIC_METRIC_MODE" <?= $is_bond_slave ? 'disabled' : '' ?>>
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
            placeholder="auto" <?= $is_bond_slave ? 'disabled' : '' ?>>
        </dd>
      </dl>
      <blockquote class="inline_help">
        Used only when metric mode is Manual. Leave empty for auto even if mode is manual (falls back to auto).
      </blockquote>
    </div>

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
        Reserved: shows name field when Yes. Full auto-bridge apply is still limited — prefer manual
        <code>br-tb0</code> if you need a bridge today.
      </blockquote>
      <div class="tbn-bridge-opts tbn-hidden">
        <dl>
          <dt>Bridge name:</dt>
          <dd>
            <input type="text" name="BR_NAME" class="tbn-ifname" maxlength="15"
              value="<?= htmlspecialchars($cfg['BR_NAME'] ?? 'br-tb0') ?>"
              placeholder="br-tb0">
          </dd>
        </dl>
        <blockquote class="inline_help">
          Default <code>br-tb0</code> (then <code>br-tb1</code>, …). Unraid management stays on <code>br0</code> —
          never reuse that name. Only shown when bridging is Yes. Auto-create is still limited.
        </blockquote>
      </div>
    </div>

    <div class="tbn-addressing <?= $is_bond_slave ? 'tbn-disabled-block' : '' ?>">
      <dl>
        <dt>Network protocol:</dt>
        <dd>
          <select name="PROTOCOL" class="tbn-ctl-proto" <?= $is_bond_slave ? 'disabled' : '' ?>>
            <?= mk_option($cfg['PROTOCOL'] ?? 'ipv4', 'ipv4', 'IPv4 only') ?>
            <?= mk_option($cfg['PROTOCOL'] ?? 'ipv4', 'ipv6', 'IPv6 only') ?>
            <?= mk_option($cfg['PROTOCOL'] ?? 'ipv4', 'ipv4+ipv6', 'IPv4 + IPv6') ?>
          </select>
        </dd>
      </dl>
      <blockquote class="inline_help">
        Same idea as eth0: choosing IPv4, IPv6, or both shows the matching assignment fields (no page reload).
      </blockquote>

      <div class="tbn-proto-ipv4 tbn-hidden">
        <dl>
          <dt>IPv4 address assignment:</dt>
          <dd>
            <select name="USE_DHCP" class="tbn-ctl-dhcp4">
              <?= mk_option($cfg['USE_DHCP'] ?? 'no', 'no', 'Static') ?>
              <?= mk_option($cfg['USE_DHCP'] ?? 'no', 'yes', 'Automatic') ?>
            </select>
          </dd>
        </dl>
        <blockquote class="inline_help">
          <strong>Static</strong> (usual for Thunderbolt P2P). <strong>Automatic</strong> tries DHCP (often no server on the cable).
        </blockquote>
        <div class="tbn-static-ipv4 tbn-hidden">
          <dl>
            <dt>IPv4 address:</dt>
            <dd class="tbn-cidr-row">
              <input type="text" name="IPADDR" class="tbn-ip" maxlength="15" value="<?= htmlspecialchars($cfg['IPADDR'] ?? '') ?>">
              <span class="tbn-cidr-slash">/</span>
              <?php tbn_render_netmask_select('NETMASK', $nm_dotted, $masks); ?>
            </dd>
          </dl>
          <blockquote class="inline_help">
            Unique subnet per tbnN (e.g. tbn0 <code>10.255.0.2/24</code>, tbn1 <code>10.255.1.2/24</code>).
            <?= tbn_help_docs_footer('docs/addressing.md', 'Addressing') ?>
          </blockquote>
          <dl>
            <dt>IPv4 default gateway:</dt>
            <dd>
              <input type="text" name="GATEWAY" class="tbn-ip" maxlength="15" value="<?= htmlspecialchars($cfg['GATEWAY'] ?? '') ?>">
            </dd>
          </dl>
          <blockquote class="inline_help">Usually empty for peer-to-peer.</blockquote>
          <dl>
            <dt>Enable default route (IPv4):</dt>
            <dd>
              <select name="DEFAULT_ROUTE">
                <?= mk_option($cfg['DEFAULT_ROUTE'] ?? 'no', 'no', 'No') ?>
                <?= mk_option($cfg['DEFAULT_ROUTE'] ?? 'no', 'yes', 'Yes') ?>
              </select>
            </dd>
          </dl>
          <blockquote class="inline_help">Default No — keep internet on eth0/br0.</blockquote>
        </div>
      </div>

      <div class="tbn-proto-ipv6 tbn-hidden">
        <dl>
          <dt>IPv6 address assignment:</dt>
          <dd>
            <select name="USE_DHCP6" class="tbn-ctl-dhcp6">
              <?= mk_option($cfg['USE_DHCP6'] ?? 'no', 'no', 'Static') ?>
              <?= mk_option($cfg['USE_DHCP6'] ?? 'no', 'yes', 'Automatic') ?>
            </select>
          </dd>
        </dl>
        <blockquote class="inline_help">
          Thunderbolt is Ethernet-like: IPv6 works when both ends configure it. Static is typical for P2P.
        </blockquote>
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
          <blockquote class="inline_help">Usually empty for peer-to-peer.</blockquote>
          <dl>
            <dt>Enable default route (IPv6):</dt>
            <dd>
              <select name="DEFAULT_ROUTE6">
                <?= mk_option($cfg['DEFAULT_ROUTE6'] ?? 'no', 'no', 'No') ?>
                <?= mk_option($cfg['DEFAULT_ROUTE6'] ?? 'no', 'yes', 'Yes') ?>
              </select>
            </dd>
          </dl>
          <blockquote class="inline_help">Default No.</blockquote>
        </div>
      </div>
    </div>

    <div class="tbn-section-vlan <?= $is_bond_slave ? 'tbn-disabled-block' : '' ?>">
      <dl>
        <dt>Enable VLANs:</dt>
        <dd>
          <select name="VLAN_ENABLE" class="tbn-ctl-vlan" <?= $is_bond_slave ? 'disabled' : '' ?>>
            <?= mk_option($cfg['VLAN_ENABLE'] ?? 'no', 'no', 'No') ?>
            <?= mk_option($cfg['VLAN_ENABLE'] ?? 'no', 'yes', 'Yes') ?>
          </select>
        </dd>
      </dl>
      <blockquote class="inline_help">
        Creates Linux VLAN subinterfaces <code><?= htmlspecialchars($if) ?>.VID</code> (802.1Q), similar to eth trunk ports.
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
          Space- or comma-separated VLAN IDs (1–4094). Apply creates/updates each
          <code><?= htmlspecialchars($if) ?>.ID</code>. Change list and Apply to rebuild rows’ saved addresses.
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
<?php if ($vlan_ids && ($cfg['VLAN_ENABLE'] ?? 'no') === 'yes'): ?>
        <p class="tbn-hint">After changing the VLAN list, Apply once, reopen this tab to edit addresses for new IDs.</p>
<?php endif; ?>
      </div>
    </div>

<?php
  $mtu_mode = tbn_normalize_mtu_mode($cfg);
  $mtu_lim = tbn_iface_mtu_limits($if);
  $mtu_live = ($live['mtu'] ?? '') !== ''
    ? $live['mtu']
    : tbn_sysfs_str('/sys/class/net/' . $if . '/mtu');
  $mtu_custom = ($cfg['MTU'] ?? '') !== '' ? (string)$cfg['MTU'] : '9000';
  if ($mtu_mode === '9000') {
    $mtu_custom = '9000';
  }
?>
    <dl>
      <dt>Desired MTU:</dt>
      <dd>
        <select name="MTU_MODE" class="tbn-ctl-mtu" <?= $is_bond_slave ? 'disabled' : '' ?>>
          <?= mk_option($mtu_mode, 'default', '1500 — kernel default (compatible first plug)') ?>
          <?= mk_option($mtu_mode, '9000', '9000 — jumbo (set on both ends)') ?>
          <?= mk_option($mtu_mode, 'custom', 'Custom…') ?>
        </select>
        <span class="tbn-mtu-custom-wrap <?= $mtu_mode === 'custom' ? '' : 'tbn-hidden' ?>">
          <input type="number" name="MTU" class="narrow tbn-mtu-custom" min="<?= (int)$mtu_lim['min'] ?>"
            max="<?= (int)$mtu_lim['max'] ?>" placeholder="9000"
            value="<?= htmlspecialchars($mtu_custom) ?>" <?= $is_bond_slave ? 'disabled' : '' ?>>
        </span>
        <input type="hidden" name="USE_MTU" value="<?= $mtu_mode === 'default' ? 'no' : 'yes' ?>">
      </dd>
    </dl>
    <blockquote class="inline_help">
      Live: <strong><?= htmlspecialchars(tbn_format_mtu_live($mtu_live, $mtu_mode)) ?></strong>
      · driver allows <?= (int)$mtu_lim['min'] ?>–<?= (int)$mtu_lim['max'] ?>.
      Product default is <strong>1500</strong> so first plug works with any peer still on 1500.<br><br>
      MTU is <strong>not negotiated</strong> to the other host — setting 9000 here does not make the peer use 9000.
      In our testing, jumbo did not move TCP much past ~13–14&nbsp;Gbit/s on a 1-lane path; it can cut retrans/CPU a bit
      if <strong>both ends</strong> match. Mismatch (9000 vs 1500) can drop or stall traffic.
      Optional: set <strong>9000 on both ends</strong> for bulk if you care about fewer packets/s.
      <?= tbn_help_docs_footer('docs/mtu-and-throughput.md', 'MTU & throughput') ?>
    </blockquote>

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
      Puts this <strong>netdev</strong> (<code><?= htmlspecialchars($if) ?></code>) into Unraid
      <code>network-extra.cfg</code> <em>include_interfaces</em> so host SMB / NFS / SSH / web UI can bind on
      its address. This is an interface list — not a peer list.<br><br>
      Day-to-day: prefer overview <strong>Known peers → Unraid services</strong> (remembers Yes/No per host and
      re-applies when that peer reconnects). This tab control is the live override for this iface (useful when
      the IP lives on the Thunderbolt interface itself). Bond/bridge names (<code>bond-tb0</code> / <code>br-tb0</code>)
      are reserved for when addressing sits on those masters; host-service include today keys off
      <code>thunderboltN</code> via peers/tabs.
    </blockquote>

    <dl>
      <dt>IPv4 (live):</dt>
      <dd><span class="tbn-live" data-tbn-live-ip4="<?= htmlspecialchars($if) ?>"><?= htmlspecialchars($addrs ? implode(', ', $addrs) : '—') ?></span></dd>
    </dl>
    <dl>
      <dt>IPv6 (live):</dt>
      <dd><span class="tbn-live" data-tbn-live-ip6="<?= htmlspecialchars($if) ?>"><?= htmlspecialchars($addrs6 ? implode(', ', $addrs6) : '—') ?></span></dd>
    </dl>
    <dl>
      <dt>Bond / bridge membership:</dt>
      <dd>
        <span class="tbn-live"><?= htmlspecialchars($membership ? implode(' ', $membership) : ($master !== '' ? $master : 'none')) ?></span>
      </dd>
    </dl>
    <blockquote class="inline_help">Read-only live membership.</blockquote>

    <p class="tbn-actions">
      <input type="submit" name="#apply" value="Apply" <?= $is_bond_slave ? '' : 'disabled' ?>>
      <input type="submit" name="#apply" value="Reset" onclick="return tbnConfirmReset(this.form);">
      <input type="button" value="Done" onclick="done()">
    </p>
  </form>

  <p class="tbn-note">
    Overview / fabric / driver options:
    <a href="/Settings/NetworkSettings" onclick="return ibigsGotoNetTab('Thunderbolt', event)">Thunderbolt</a>
  </p>

  <?= tbn_docs_bar_html('iface') ?>

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
