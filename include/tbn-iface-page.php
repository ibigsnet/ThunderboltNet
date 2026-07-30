<?php
/**
 * Per-link tab (Thunderbolt tbnN) — eth0-style dl/dt/dd form layout.
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
$master = $live['master'] ?? tbn_iface_master($if);
$membership = $live['masters'] ?? tbn_iface_membership_labels($if, $master);

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
    · <?= htmlspecialchars(($rx ?: '—') . ' / ' . ($tx ?: '—')) ?>
      (lanes <?= htmlspecialchars(($rl ?: '—') . '/' . ($tl ?: '—')) ?>)
<?php endif; ?>
  </p>

  <form method="POST" action="/update.php" target="progressFrame" id="tbn-form-<?= htmlspecialchars($label) ?>">
    <input type="hidden" name="#file" value="ThunderboltNet/ifaces/<?= htmlspecialchars($if) ?>.cfg">
    <input type="hidden" name="#include" value="/plugins/ThunderboltNet/include/tbn-update-iface.php">
    <input type="hidden" name="#arg[1]" value="<?= htmlspecialchars($if) ?>">
    <input type="hidden" name="tbn_iface" value="<?= htmlspecialchars($if) ?>">
    <input type="hidden" name="BR_NAME" value="<?= htmlspecialchars($cfg['BR_NAME'] ?? 'br-tb') ?>">

    <dl>
      <dt>Interface description:</dt>
      <dd>
        <input type="text" name="DESCRIPTION" maxlength="80" autocomplete="off" spellcheck="false"
          value="<?= htmlspecialchars($cfg['DESCRIPTION'] ?? '') ?>">
      </dd>

      <dt>MAC address:</dt>
      <dd><span class="tbn-live"><?= htmlspecialchars(strtoupper($mac)) ?></span></dd>

      <dt>Enable interface:</dt>
      <dd>
        <select name="ENABLE">
          <?= mk_option($cfg['ENABLE'] ?? 'yes', 'yes', 'Yes') ?>
          <?= mk_option($cfg['ENABLE'] ?? 'yes', 'no', 'No') ?>
        </select>
      </dd>

      <dt>Enable bonding:</dt>
      <dd>
        <select name="BONDING">
          <?= mk_option($cfg['BONDING'] ?? 'no', 'no', 'No') ?>
          <?= mk_option($cfg['BONDING'] ?? 'no', 'yes', 'Yes') ?>
        </select>
        <span class="tbn-hint">TB-only bond (not Unraid bond0). Members = live thunderbolt* interfaces.</span>
      </dd>

      <dt>Bonding mode:</dt>
      <dd>
        <select name="BONDING_MODE">
          <?= mk_option($cfg['BONDING_MODE'] ?? 'balance-rr', 'balance-rr', 'balance-rr (0)') ?>
          <?= mk_option($cfg['BONDING_MODE'] ?? 'balance-rr', 'active-backup', 'active-backup (1)') ?>
          <?= mk_option($cfg['BONDING_MODE'] ?? 'balance-rr', 'balance-xor', 'balance-xor (2)') ?>
          <?= mk_option($cfg['BONDING_MODE'] ?? 'balance-rr', '802.3ad', '802.3ad (4)') ?>
        </select>
      </dd>

      <dt>Bond name:</dt>
      <dd>
        <input type="text" name="BOND_NAME" class="narrow" maxlength="16"
          value="<?= htmlspecialchars($cfg['BOND_NAME'] ?? 'bond-tb') ?>">
      </dd>

      <dt>Enable bridging:</dt>
      <dd>
        <select name="BRIDGING">
          <?= mk_option($cfg['BRIDGING'] ?? 'no', 'no', 'No') ?>
          <?= mk_option($cfg['BRIDGING'] ?? 'no', 'yes', 'Yes') ?>
        </select>
        <span class="tbn-hint">Reserved — not applied automatically yet.</span>
      </dd>

      <dt>Network protocol:</dt>
      <dd>
        <select name="PROTOCOL">
          <?= mk_option($cfg['PROTOCOL'] ?? 'ipv4', 'ipv4', 'IPv4 only') ?>
          <?= mk_option($cfg['PROTOCOL'] ?? 'ipv4', 'ipv4+ipv6', 'IPv4 + IPv6') ?>
        </select>
      </dd>

      <dt>IPv4 address assignment:</dt>
      <dd>
        <select name="USE_DHCP">
          <?= mk_option($cfg['USE_DHCP'] ?? 'no', 'no', 'Static') ?>
          <?= mk_option($cfg['USE_DHCP'] ?? 'no', 'yes', 'Automatic') ?>
        </select>
      </dd>

      <dt>IPv4 address:</dt>
      <dd>
        <input type="text" name="IPADDR" maxlength="15" value="<?= htmlspecialchars($cfg['IPADDR'] ?? '') ?>">
        /
        <select name="NETMASK" class="narrow">
<?php foreach ($masks as $mask => $pref): ?>
          <?= mk_option($nm_dotted, $mask, $pref) ?>
<?php endforeach; ?>
        </select>
      </dd>

      <dt>IPv4 default gateway:</dt>
      <dd>
        <input type="text" name="GATEWAY" maxlength="15" value="<?= htmlspecialchars($cfg['GATEWAY'] ?? '') ?>">
      </dd>

      <dt>Enable default route:</dt>
      <dd>
        <select name="DEFAULT_ROUTE">
          <?= mk_option($cfg['DEFAULT_ROUTE'] ?? 'no', 'no', 'No') ?>
          <?= mk_option($cfg['DEFAULT_ROUTE'] ?? 'no', 'yes', 'Yes') ?>
        </select>
        <span class="tbn-hint">
          Default is No. Thunderbolt links are usually peer-to-peer; keep routing local to this link.
          Only set Yes if you intentionally want this interface to carry the system default route
          for general network traffic (rare).
        </span>
      </dd>

      <dt>Desired MTU:</dt>
      <dd>
        <input type="number" name="MTU" class="narrow" min="68" max="9198" placeholder="1500"
          value="<?= htmlspecialchars($cfg['MTU'] ?? '') ?>">
        <select name="USE_MTU" class="narrow">
          <?= mk_option($cfg['USE_MTU'] ?? 'no', 'no', 'Default') ?>
          <?= mk_option($cfg['USE_MTU'] ?? 'no', 'yes', 'Custom') ?>
        </select>
      </dd>

      <dt>Include listening interface:</dt>
      <dd>
        <select name="INCLUDE_LISTENING">
          <?= mk_option($cfg['INCLUDE_LISTENING'] ?? 'no', 'no', 'No') ?>
          <?= mk_option($cfg['INCLUDE_LISTENING'] ?? 'no', 'yes', 'Yes') ?>
        </select>
        <span class="tbn-hint">Adds this interface to network-extra include list.</span>
      </dd>

      <dt>IPv4 (live):</dt>
      <dd><span class="tbn-live"><?= htmlspecialchars($addrs ? implode(', ', $addrs) : '—') ?></span></dd>

      <dt>Bond / bridge membership:</dt>
      <dd>
        <span class="tbn-live"><?= htmlspecialchars($membership ? implode(' ', $membership) : ($master !== '' ? $master : 'none')) ?></span>
      </dd>
    </dl>

    <p class="tbn-actions">
      <input type="submit" name="#apply" value="Apply">
      <input type="submit" name="#apply" value="Reset" onclick="return tbnConfirmReset(this.form);">
      <input type="button" value="Done" onclick="done()">
    </p>
  </form>

  <p class="tbn-note">
    Controller, fabric, and PCI/IOMMU:
    <a href="/Settings/NetworkSettings" onclick="return tbnGotoNetTab('Thunderbolt', event)">Thunderbolt</a>
  </p>

<?php endif; ?>

</div>
<script src="/plugins/ThunderboltNet/thunderboltnet.js?v=<?= htmlspecialchars($ver) ?>"></script>
