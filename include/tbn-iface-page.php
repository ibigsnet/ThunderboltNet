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
    · <?= htmlspecialchars(($rx ?: '—') . ' / ' . ($tx ?: '—')) ?>
      (lanes <?= htmlspecialchars(($rl ?: '—') . '/' . ($tl ?: '—')) ?>)
<?php endif; ?>
  </p>

  <p class="tbn-note">
    Click a field label (cursor becomes a help pointer) or the page <strong>Help</strong> button for blue inline details — same pattern as eth0 and Storage Guard.
  </p>

  <form method="POST" action="/update.php" target="progressFrame" id="tbn-form-<?= htmlspecialchars($label) ?>">
    <input type="hidden" name="#file" value="ThunderboltNet/ifaces/<?= htmlspecialchars($if) ?>.cfg">
    <input type="hidden" name="#include" value="/plugins/ThunderboltNet/include/tbn-update-iface.php">
    <input type="hidden" name="#arg[1]" value="<?= htmlspecialchars($if) ?>">
    <input type="hidden" name="tbn_iface" value="<?= htmlspecialchars($if) ?>">
    <input type="hidden" name="BR_NAME" value="<?= htmlspecialchars($cfg['BR_NAME'] ?? 'br-tb') ?>">

    <?php /* One <dl> per field, then blockquote.inline_help as next sibling — required for Unraid HelpButton + clickable dt labels (BodyInlineJS). */ ?>

    <dl>
      <dt>Interface description:</dt>
      <dd>
        <input type="text" name="DESCRIPTION" maxlength="80" autocomplete="off" spellcheck="false"
          value="<?= htmlspecialchars($cfg['DESCRIPTION'] ?? '') ?>">
      </dd>
    </dl>
    <blockquote class="inline_help">
      Optional label for this link (not the OS hostname). Useful when you have several Thunderbolt peers
      (e.g. “Holo workstation”, “laptop”). Stored only in this plugin’s per-iface config.
    </blockquote>

    <dl>
      <dt>MAC address:</dt>
      <dd><span class="tbn-live"><?= htmlspecialchars(strtoupper($mac)) ?></span></dd>
    </dl>
    <blockquote class="inline_help">
      Hardware address of kernel interface <code><?= htmlspecialchars($if) ?></code>. Read-only from sysfs.
      Each Thunderbolt netdev has its own MAC; do not expect it to match eth0.
    </blockquote>

    <dl>
      <dt>Enable interface:</dt>
      <dd>
        <select name="ENABLE">
          <?= mk_option($cfg['ENABLE'] ?? 'yes', 'yes', 'Yes') ?>
          <?= mk_option($cfg['ENABLE'] ?? 'yes', 'no', 'No') ?>
        </select>
      </dd>
    </dl>
    <blockquote class="inline_help">
      <strong>Yes</strong> (default) — Apply brings the link <code>up</code> and applies addressing options below.<br>
      <strong>No</strong> — Apply sets the interface <code>down</code> and skips IP/bond apply for this iface.
    </blockquote>

    <dl>
      <dt>Enable bonding:</dt>
      <dd>
        <select name="BONDING">
          <?= mk_option($cfg['BONDING'] ?? 'no', 'no', 'No') ?>
          <?= mk_option($cfg['BONDING'] ?? 'no', 'yes', 'Yes') ?>
        </select>
      </dd>
    </dl>
    <blockquote class="inline_help">
      Builds a <strong>Thunderbolt-only</strong> Linux bond (not Unraid’s main <code>bond0</code> on eth ports).
      Members are live <code>thunderbolt*</code> interfaces. Only useful when you have <em>two</em> separate
      TB network interfaces to the same peer fabric (two host paths). Two cables to the same peer often
      still enumerate as one path — bonding cannot invent a second netdev.
    </blockquote>

    <dl>
      <dt>Bonding mode:</dt>
      <dd>
        <select name="BONDING_MODE">
          <?= mk_option($cfg['BONDING_MODE'] ?? 'balance-rr', 'balance-rr', 'balance-rr (0)') ?>
          <?= mk_option($cfg['BONDING_MODE'] ?? 'balance-rr', 'active-backup', 'active-backup (1)') ?>
          <?= mk_option($cfg['BONDING_MODE'] ?? 'balance-rr', 'balance-xor', 'balance-xor (2)') ?>
          <?= mk_option($cfg['BONDING_MODE'] ?? 'balance-rr', '802.3ad', '802.3ad (4)') ?>
        </select>
      </dd>
    </dl>
    <blockquote class="inline_help">
      Linux bonding mode for the TB bond. <strong>balance-rr</strong> stripes packets (can reorder);
      <strong>active-backup</strong> fails over; <strong>802.3ad</strong> needs LACP on both ends (unusual for host↔host TB).
      Applied only when Enable bonding is Yes.
    </blockquote>

    <dl>
      <dt>Bond name:</dt>
      <dd>
        <input type="text" name="BOND_NAME" class="narrow" maxlength="16"
          value="<?= htmlspecialchars($cfg['BOND_NAME'] ?? 'bond-tb') ?>">
      </dd>
    </dl>
    <blockquote class="inline_help">
      Netdev name for the TB bond (default <code>bond-tb</code>). Keep it short and unique; do not reuse Unraid’s
      <code>bond0</code> name.
    </blockquote>

    <dl>
      <dt>Enable bridging:</dt>
      <dd>
        <select name="BRIDGING">
          <?= mk_option($cfg['BRIDGING'] ?? 'no', 'no', 'No') ?>
          <?= mk_option($cfg['BRIDGING'] ?? 'no', 'yes', 'Yes') ?>
        </select>
      </dd>
    </dl>
    <blockquote class="inline_help">
      Reserved for a future TB bridge (e.g. <code>br-tb</code>). <strong>Not applied automatically yet</strong> —
      leave No unless you are testing manual bridge setup yourself.
    </blockquote>

    <dl>
      <dt>Network protocol:</dt>
      <dd>
        <select name="PROTOCOL">
          <?= mk_option($cfg['PROTOCOL'] ?? 'ipv4', 'ipv4', 'IPv4 only') ?>
          <?= mk_option($cfg['PROTOCOL'] ?? 'ipv4', 'ipv4+ipv6', 'IPv4 + IPv6') ?>
        </select>
      </dd>
    </dl>
    <blockquote class="inline_help">
      Which address families Apply configures. IPv6 handling is limited today; most host-to-host TB setups use
      <strong>IPv4 only</strong> with static addresses on both peers.
    </blockquote>

    <dl>
      <dt>IPv4 address assignment:</dt>
      <dd>
        <select name="USE_DHCP">
          <?= mk_option($cfg['USE_DHCP'] ?? 'no', 'no', 'Static') ?>
          <?= mk_option($cfg['USE_DHCP'] ?? 'no', 'yes', 'Automatic') ?>
        </select>
      </dd>
    </dl>
    <blockquote class="inline_help">
      <strong>Static</strong> (default) — use the IPv4 address and netmask below. Recommended for Thunderbolt
      peer links (no DHCP server on the cable).<br>
      <strong>Automatic</strong> — best-effort DHCP on this iface (often fails on pure host↔host links).
    </blockquote>

    <dl>
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
    </dl>
    <blockquote class="inline_help">
      Static IPv4 and prefix for this link. Each <code>tbnN</code> should use its <strong>own subnet</strong>
      (e.g. tbn0 = <code>10.255.0.2/24</code>, tbn1 = <code>10.255.1.2/24</code>) so dual peers do not share one route.<br>
      <strong>/24 (Small LAN)</strong> — room for VMs/aliases on the peer; product-friendly default.<br>
      <strong>/30 (point-to-point)</strong> — two usable hosts only; pure host↔host pipe.<br>
      Put the peer on the matching network (often <code>.1</code> if Unraid is <code>.2</code>).<br><br>
      <strong>Examples:</strong> Unraid↔Linux or Mac — Unraid <code>10.255.0.2/24</code>, peer <code>10.255.0.1/24</code>.
      Two peers — use <code>10.255.0.0/24</code> on tbn0 and <code>10.255.1.0/24</code> on tbn1.
      A dock’s RJ45 is usually USB Ethernet (eth), not this address.
      <?= tbn_help_docs_footer('docs/addressing.md', 'Addressing guide') ?>
      · <?= tbn_docs_more_html('docs/peer-scenarios.md', 'Peer scenarios ↗') ?>
    </blockquote>

    <dl>
      <dt>IPv4 default gateway:</dt>
      <dd>
        <input type="text" name="GATEWAY" maxlength="15" value="<?= htmlspecialchars($cfg['GATEWAY'] ?? '') ?>">
      </dd>
    </dl>
    <blockquote class="inline_help">
      Optional next hop on this Thunderbolt link. Leave empty for normal peer-to-peer (you only need on-link
      routes to the other host). Set a gateway only if the peer is intentionally routing for you.
    </blockquote>

    <dl>
      <dt>Enable default route:</dt>
      <dd>
        <select name="DEFAULT_ROUTE">
          <?= mk_option($cfg['DEFAULT_ROUTE'] ?? 'no', 'no', 'No') ?>
          <?= mk_option($cfg['DEFAULT_ROUTE'] ?? 'no', 'yes', 'Yes') ?>
        </select>
      </dd>
    </dl>
    <blockquote class="inline_help">
      <strong>No</strong> (default) — do not install a system-wide default route via this Thunderbolt iface.
      Keep general internet traffic on eth0/br0; use TB only for peer subnets.
      Recommended for Unraid↔Linux, Mac, Windows, and multi-peer labs so Wi‑Fi/Ethernet WAN is unchanged.<br>
      <strong>Yes</strong> — rare; only if this link should become the machine’s default route for all traffic.
      <?= tbn_help_docs_footer('docs/addressing.md', 'Addressing & routing') ?>
    </blockquote>

    <dl>
      <dt>Desired MTU:</dt>
      <dd>
        <input type="number" name="MTU" class="narrow" min="68" max="9198" placeholder="1500"
          value="<?= htmlspecialchars($cfg['MTU'] ?? '') ?>">
        <select name="USE_MTU" class="narrow">
          <?= mk_option($cfg['USE_MTU'] ?? 'no', 'no', 'Default') ?>
          <?= mk_option($cfg['USE_MTU'] ?? 'no', 'yes', 'Custom') ?>
        </select>
      </dd>
    </dl>
    <blockquote class="inline_help">
      <strong>Default</strong> — leave the kernel/driver MTU alone (usually 1500).<br>
      <strong>Custom</strong> — Apply sets the number you enter (68–9198). Both ends should match; jumbo frames
      only help if the path and peer allow them.
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
      When <strong>Yes</strong>, adds this interface to Unraid’s <code>network-extra.cfg</code>
      <code>include_interfaces</code> list so services that honor that list may bind/listen on it
      (SMB, etc., depending on Unraid settings). Leave <strong>No</strong> if TB is only for private
      peer traffic and you do not want shares advertised on this link.
    </blockquote>

    <dl>
      <dt>IPv4 (live):</dt>
      <dd><span class="tbn-live"><?= htmlspecialchars($addrs ? implode(', ', $addrs) : '—') ?></span></dd>
    </dl>
    <blockquote class="inline_help">
      Addresses currently assigned on the live netdev (from <code>ip addr</code>). Compare with the static
      fields above after Apply. Empty usually means the link is down or no address was applied yet.
    </blockquote>

    <dl>
      <dt>Bond / bridge membership:</dt>
      <dd>
        <span class="tbn-live"><?= htmlspecialchars($membership ? implode(' ', $membership) : ($master !== '' ? $master : 'none')) ?></span>
      </dd>
    </dl>
    <blockquote class="inline_help">
      Read-only view of whether this interface is enslaved to a bond or bridge right now.
    </blockquote>

    <p class="tbn-actions">
      <input type="submit" name="#apply" value="Apply">
      <input type="submit" name="#apply" value="Reset" onclick="return tbnConfirmReset(this.form);">
      <input type="button" value="Done" onclick="done()">
    </p>
  </form>

  <p class="tbn-note">
    Controller, fabric, driver options (including <strong>host-wide E2E</strong>), and PCI/IOMMU:
    <a href="/Settings/NetworkSettings" onclick="return tbnGotoNetTab('Thunderbolt', event)">Thunderbolt</a>
    · <?= tbn_docs_more_html('docs/driver-options.md', 'Driver options docs ↗') ?>
    · <?= tbn_docs_more_html('docs/peer-scenarios.md', 'Peer scenarios ↗') ?>
  </p>

  <?= tbn_docs_bar_html('iface') ?>

<?php endif; ?>

</div>
<script src="/plugins/ThunderboltNet/thunderboltnet.js?v=<?= htmlspecialchars($ver) ?>"></script>
