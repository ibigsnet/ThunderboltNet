<?php
/**
 * Per-interface Network Settings body for Thunderbolt tbnN (thunderboltN).
 * Included by generated TbnN.page — expects $tbn_if and $tbn_label.
 * Paths are absolute: Unraid evaluates pages with a broken __DIR__.
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
$pci = tbn_list_pci_iommu();
$present = is_dir('/sys/class/net/' . $if);

// peer hint from sysfs parent
$peer = '';
$parent = @realpath('/sys/class/net/' . $if . '/device');
if ($parent) {
  $up = dirname($parent);
  $peer = tbn_sysfs_str($up . '/device_name');
  if ($peer === '') {
    $peer = tbn_sysfs_str(dirname($up) . '/device_name');
  }
}
$rx = $tx = $rl = $tl = '';
if ($parent) {
  $cand = [dirname($parent), dirname(dirname($parent))];
  foreach ($cand as $c) {
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
// NETMASK stored as prefix "24" or dotted
$nm = $cfg['NETMASK'] ?? '24';
if (strpos($nm, '.') === false) {
  $prefix = $nm;
  $nm_dotted = array_search($nm, $masks, true);
  if ($nm_dotted === false) {
    $nm_dotted = '255.255.255.0';
  }
} else {
  $nm_dotted = $nm;
  $prefix = (string)($masks[$nm] ?? tbn_mask_to_prefix($nm));
}
?>
<link rel="stylesheet" href="/plugins/ThunderboltNet/thunderboltnet.css?v=<?= htmlspecialchars($ver) ?>">

<div class="tbn-wrap">
  <div class="tbn-hero">
    <h3 class="tbn-title">Thunderbolt <?= htmlspecialchars($label) ?> <span class="tbn-muted">(<code><?= htmlspecialchars($if) ?></code>)</span></h3>
  </div>

<?php if (!$present): ?>
  <div class="tbn-notice tbn-notice-warn">
    <h4>Interface not present</h4>
    <p><code><?= htmlspecialchars($if) ?></code> is not in <code>/sys/class/net</code> right now.
    Connect a TB4/USB4 cable and peer, then Refresh. This tab hides when the iface is gone.</p>
  </div>
<?php else: ?>

  <h3>Link status</h3>
  <table class="tbn-table tbn-summary">
    <tr><td>Kernel iface</td><td><code><?= htmlspecialchars($if) ?></code> · UI name <code><?= htmlspecialchars($label) ?></code></td></tr>
    <tr><td>State / carrier</td><td><?= htmlspecialchars($oper) ?> / <?= htmlspecialchars($carrier === '' ? 'n/a' : $carrier) ?></td></tr>
    <tr><td>MAC</td><td><code><?= htmlspecialchars(strtoupper($mac)) ?></code></td></tr>
    <tr><td>IPv4 (live)</td><td><code><?= htmlspecialchars($addrs ? implode(', ', $addrs) : '—') ?></code></td></tr>
    <tr><td>Peer</td><td><?= htmlspecialchars($peer !== '' ? $peer : '—') ?></td></tr>
    <tr><td>RX / TX · lanes</td>
      <td><?= htmlspecialchars(($rx ?: '—') . ' / ' . ($tx ?: '—')) ?>
        · <?= htmlspecialchars(($rl ?: '—') . ' / ' . ($tl ?: '—')) ?></td></tr>
  </table>

  <h3>Controller (IOMMU / driver)</h3>
  <p class="tbn-muted">Informational only — so you can see PCI IDs, IOMMU groups, and whether anything is VFIO-bound (which would hide TB from the host).</p>
<?php if (!$pci): ?>
  <p class="tbn-muted">No Thunderbolt-related PCI devices listed.</p>
<?php else: ?>
  <table class="tbn-table tbn-wide">
    <thead>
      <tr><th>PCI BDF</th><th>IOMMU group</th><th>Driver</th><th>VFIO</th><th>Description</th></tr>
    </thead>
    <tbody>
<?php foreach ($pci as $p): ?>
      <tr>
        <td><code><?= htmlspecialchars($p['bdf']) ?></code></td>
        <td><code><?= htmlspecialchars($p['iommu_group']) ?></code></td>
        <td><code><?= htmlspecialchars($p['driver']) ?></code></td>
        <td><?= htmlspecialchars($p['vfio']) ?><?= $p['vfio_boot_cfg'] === 'yes' ? ' (boot cfg)' : '' ?></td>
        <td><?= htmlspecialchars($p['description']) ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

  <h3>Interface settings</h3>
  <form markdown="1" method="POST" action="/update.php" target="progressFrame" id="tbn-iface-<?= htmlspecialchars($label) ?>">
  <input type="hidden" name="#file" value="ThunderboltNet/ifaces/<?= htmlspecialchars($if) ?>.cfg">
  <input type="hidden" name="#include" value="/plugins/ThunderboltNet/include/tbn-update-iface.php">
  <input type="hidden" name="#arg[1]" value="<?= htmlspecialchars($if) ?>">
  <input type="hidden" name="tbn_iface" value="<?= htmlspecialchars($if) ?>">

**Interface description**
: <input type="text" name="DESCRIPTION" maxlength="80" autocomplete="off" spellcheck="false" value="<?= htmlspecialchars($cfg['DESCRIPTION'] ?? '') ?>">

: Optional label for this interface.

**MAC address**
: <code><?= htmlspecialchars(strtoupper($mac)) ?></code>

: Hardware address of this Thunderbolt network function.

**Enable interface**
: <select name="ENABLE">
  <?= mk_option($cfg['ENABLE'] ?? 'yes', 'yes', 'Yes') ?>
  <?= mk_option($cfg['ENABLE'] ?? 'yes', 'no', 'No') ?>
  </select>

: When Yes, Apply brings the iface administratively up. When No, it is set down.

**Enable bonding**
: <select name="BONDING" id="tbn-bonding-<?= htmlspecialchars($label) ?>">
  <?= mk_option($cfg['BONDING'] ?? 'no', 'no', 'No') ?>
  <?= mk_option($cfg['BONDING'] ?? 'no', 'yes', 'Yes — TB-only bond (not Unraid bond0)') ?>
  </select>

: Optional bond of **all live thunderbolt*** members (e.g. dual cable). Uses a separate bond name (default <code>bond-tb</code>), not stock eth bonding. Requires both links present.

**Bonding mode**
: <select name="BONDING_MODE">
  <?= mk_option($cfg['BONDING_MODE'] ?? 'balance-rr', 'balance-rr', 'balance-rr (0)') ?>
  <?= mk_option($cfg['BONDING_MODE'] ?? 'balance-rr', 'active-backup', 'active-backup (1)') ?>
  <?= mk_option($cfg['BONDING_MODE'] ?? 'balance-rr', 'balance-xor', 'balance-xor (2)') ?>
  <?= mk_option($cfg['BONDING_MODE'] ?? 'balance-rr', '802.3ad', '802.3ad (4) — needs switch support') ?>
  </select>

: Same mode names as eth bonding. LACP (802.3ad) is rarely useful for host-to-host TB.

**Bond name**
: <input type="text" name="BOND_NAME" maxlength="16" class="narrow" value="<?= htmlspecialchars($cfg['BOND_NAME'] ?? 'bond-tb') ?>">

: Linux bond device name for TB members only.

**Enable bridging**
: <select name="BRIDGING">
  <?= mk_option($cfg['BRIDGING'] ?? 'no', 'no', 'No') ?>
  <?= mk_option($cfg['BRIDGING'] ?? 'no', 'yes', 'Yes (advanced — not recommended for peer TB)') ?>
  </select>

: Bridging a TB peer link into br0 is advanced and can create loops or surprise L2 domains. Prefer a plain routed/static TB subnet. (Apply does not auto-create a bridge yet; reserved for a later release.)

**Network protocol**
: <select name="PROTOCOL">
  <?= mk_option($cfg['PROTOCOL'] ?? 'ipv4', 'ipv4', 'IPv4 only') ?>
  <?= mk_option($cfg['PROTOCOL'] ?? 'ipv4', 'ipv4+ipv6', 'IPv4 + IPv6 (IPv6 later)') ?>
  </select>

: IPv4 is fully applied today. IPv6 fields are reserved.

**IPv4 address assignment**
: <select name="USE_DHCP">
  <?= mk_option($cfg['USE_DHCP'] ?? 'no', 'no', 'Static') ?>
  <?= mk_option($cfg['USE_DHCP'] ?? 'no', 'yes', 'Automatic (DHCP — best effort)') ?>
  </select>

: Static is recommended for host-to-host. DHCP only works if a client and server exist on the TB segment.

**IPv4 address / prefix**
: <input type="text" name="IPADDR" maxlength="15" class="narrow" value="<?= htmlspecialchars($cfg['IPADDR'] ?? '') ?>">
  /
  <select name="NETMASK" class="narrow">
<?php foreach ($masks as $mask => $pref): ?>
  <?= mk_option($nm_dotted, $mask, $pref) ?>
<?php endforeach; ?>
  </select>

: Static IPv4 and prefix length (both ends of the link must match).

**IPv4 default gateway**
: <input type="text" name="GATEWAY" maxlength="15" class="narrow" value="<?= htmlspecialchars($cfg['GATEWAY'] ?? '') ?>">

: Leave empty for a pure peer link.

**Never use as default route**
: <select name="NEVER_DEFAULT">
  <?= mk_option($cfg['NEVER_DEFAULT'] ?? 'yes', 'yes', 'Yes (recommended)') ?>
  <?= mk_option($cfg['NEVER_DEFAULT'] ?? 'yes', 'no', 'No — allow default via gateway') ?>
  </select>

: Keeps LAN/WAN default route on eth/br/wlan.

**Desired MTU**
: <input type="number" name="MTU" min="68" max="9198" class="narrow" value="<?= htmlspecialchars($cfg['MTU'] ?? '') ?>" placeholder="1500">
  <select name="USE_MTU">
  <?= mk_option($cfg['USE_MTU'] ?? 'no', 'no', 'Default') ?>
  <?= mk_option($cfg['USE_MTU'] ?? 'no', 'yes', 'Custom MTU') ?>
  </select>

: Only applied when Custom MTU is selected. Both ends should match.

**Include as listening interface**
: <select name="INCLUDE_LISTENING">
  <?= mk_option($cfg['INCLUDE_LISTENING'] ?? 'no', 'no', 'No') ?>
  <?= mk_option($cfg['INCLUDE_LISTENING'] ?? 'no', 'yes', 'Yes — network-extra include list') ?>
  </select>

: Same idea as **Interface Extra** / Tailscale: let Unraid services listen on this TB iface.

&nbsp;
: <input type="submit" name="#apply" value="Apply">
  <input type="submit" name="#apply" value="Reset" onclick="return tbnConfirmReset(this.form);">
  <input type="button" value="Refresh" onclick="location.reload()">
  <input type="button" value="Done" onclick="done()">

  </form>

  <blockquote class="inline_help">
    <p><strong>Reset</strong> restores this interface’s plugin defaults (does not uninstall the plugin).
    <strong>Apply</strong> writes settings under <code>/boot/config/plugins/ThunderboltNet/ifaces/</code> and applies them live.</p>
    <p>Do not unbind the Thunderbolt NHI from the host driver — that can wedge Maple Ridge until a cold boot.</p>
  </blockquote>
<?php endif; ?>

  <p class="tbn-muted tbn-footer">
    Version <code><?= htmlspecialchars($ver) ?></code>
    · <a href="/Settings/ThunderboltNet">Thunderbolt overview</a>
    · <a href="https://github.com/ibigsnet/ThunderboltNet" target="_blank" rel="noopener">GitHub</a>
  </p>
</div>
<script src="/plugins/ThunderboltNet/thunderboltnet.js?v=<?= htmlspecialchars($ver) ?>"></script>
