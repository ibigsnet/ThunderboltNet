<?php
if (!$has_hw):
?>
  <div class="tbn-notice" role="status">
    <p>No Thunderbolt host controller — see the <strong>Status</strong> tab for diagnostics.</p>
  </div>
<?php else: ?>
  <div class="tbn-section tbn-recovery">
    <h3>Recovery</h3>
    <?= function_exists('tbn_offline_recovery_note_html') ? tbn_offline_recovery_note_html() : '' ?>
    <dl>
      <dt>Wedged fabric / dual-cable mess:</dt>
      <dd class="tbn-muted">All cables out both boxes · wait · one cable only</dd>
    </dl>
    <blockquote class="inline_help">
      Two Thunderbolt cables to the <em>same</em> peer often still yield one netdev — and can leave the domain stuck
      (NO-CARRIER, no peer, weird addresses). Software cleanup alone may not fix it.<br><br>
      <strong>1.</strong> Unplug <em>all</em> Thunderbolt / USB4 host cables from <em>both ends on both machines</em>.<br>
      <strong>2.</strong> Wait a few seconds until peers disappear.<br>
      <strong>3.</strong> Plug back <em>exactly one</em> known-good Thunderbolt/USB4-class cable on full Thunderbolt/USB4 host ports (not SS-only USB).<br>
      <strong>4.</strong> Confirm one peer and one <code>thunderboltN</code>, then Apply IP / services on the tbn tab.<br><br>
      Soft “port power cycle” is not available; physical unplug is the reliable retrain.
      <?= tbn_help_docs_footer('docs/troubleshooting.md', 'Troubleshooting') ?>
      <?= tbn_help_docs_footer('docs/safe-mode-recovery.md', 'Safe Mode / offline recovery') ?>
    </blockquote>
  </div>

  <div class="tbn-section">
    <h3>PCI / IOMMU / VFIO</h3>
    <p class="tbn-note">Where the Thunderbolt controller sits on PCIe — and whether the host still owns it.</p>
    <dl>
      <dt>PCI / IOMMU / VFIO:</dt>
      <dd class="tbn-muted">NHI · groups · passthrough</dd>
    </dl>
    <blockquote class="inline_help">
      Shows how the Thunderbolt controller attaches to the CPU/PCIe fabric and its IOMMU group.<br><br>
      If the NHI (host interface) is bound to <code>vfio-pci</code> for a VM, Unraid cannot use host Thunderbolt networking
      until that device returns to the host driver. Warnings appear above the details control when relevant.
      <?= tbn_help_docs_footer('docs/requirements.md', 'Requirements') ?>
    </blockquote>
<?php
  $tbn_pci_uid = 'overview';
  require '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-pci-panel.php';
?>
  </div>

  <div class="tbn-section">
    <h3>Fabric devices</h3>
    <p class="tbn-note">Topology from sysfs — controller and peers by default; services hidden until expanded.</p>
<?php if (!$devices): ?>
    <p class="tbn-muted">No entries under <code>/sys/bus/thunderbolt/devices</code>.</p>
<?php else:
  $fabric_detail_n = 0;
  foreach ($devices as $d) {
    $id = $d['id'];
    if ($id === 'domain0' || preg_match('/\.\d+$/', $id)) {
      $fabric_detail_n++;
    }
  }
?>
<?php if ($fabric_detail_n > 0): ?>
    <p class="tbn-pci-toggle-row">
      <input type="button" class="tbn-fabric-toggle" value="Show services &amp; domain (<?= (int)$fabric_detail_n ?>)"
        data-show="Show services &amp; domain (<?= (int)$fabric_detail_n ?>)"
        data-hide="Hide services &amp; domain"
        onclick="tbnToggleFabricDetails(this)">
    </p>
<?php endif; ?>
    <table class="tbn-table tbn-wide tbn-fabric">
      <thead>
        <tr>
          <th>ID</th>
          <th>Role</th>
          <th>Name</th>
          <th>Manufacturer / stack</th>
          <th>Link rate</th>
          <th>Lanes</th>
          <th>Netdevs</th>
        </tr>
      </thead>
      <tbody>
<?php foreach ($devices as $d):
  $id = $d['id'];
  $role = 'node';
  $row_class = '';
  $is_detail = false;
  if ($id === 'domain0' || preg_match('/^domain\d+$/', $id)) {
    $role = 'domain';
    $row_class = 'tbn-fabric-detail tbn-hidden';
    $is_detail = true;
  } elseif ($id === '0-0') {
    $role = 'local controller';
  } elseif (preg_match('/^\d+-\d+$/', $id) && $id !== '0-0') {
    $role = 'peer';
  } elseif (preg_match('/\.\d+$/', $id)) {
    $role = 'service';
    $row_class = 'tbn-fabric-detail tbn-hidden';
    $is_detail = true;
  }
  $prose = $is_detail ? ' class="tbn-fabric-prose"' : '';
  $net_list = !empty($d['netdevs']) ? implode(', ', $d['netdevs']) : '';
?>
        <tr<?= $row_class !== '' ? ' class="' . htmlspecialchars($row_class) . '"' : '' ?>>
          <td class="tbn-col-ident"><code class="tbn-ident"><?= htmlspecialchars($id) ?></code></td>
          <td<?= $prose ?>><?= htmlspecialchars($role) ?></td>
          <td<?= $prose ?>><?= htmlspecialchars($d['device_name'] ?: '—') ?></td>
          <td<?= $prose ?>><?= htmlspecialchars($d['vendor_name'] ?: '—') ?></td>
          <td<?= $prose ?>><?php
            $d_rate = function_exists('tbn_format_link_rate')
              ? tbn_format_link_rate(
                  $d['rx_speed'] ?? '',
                  $d['tx_speed'] ?? '',
                  [
                    'rx_lanes' => $d['rx_lanes'] ?? '',
                    'tx_lanes' => $d['tx_lanes'] ?? '',
                    'show_lanes' => false,
                  ]
                )
              : trim(($d['rx_speed'] ?: '—') . ' / ' . ($d['tx_speed'] ?: '—'));
            echo htmlspecialchars($d_rate !== '' ? $d_rate : '—');
          ?></td>
          <td<?= $prose ?>><?= htmlspecialchars(trim(($d['rx_lanes'] ?: '—') . ' / ' . ($d['tx_lanes'] ?: '—'))) ?></td>
          <td class="tbn-col-ident"><?php
            if ($net_list !== '') {
              echo '<code class="tbn-ident">' . htmlspecialchars($net_list) . '</code>';
              if ($role === 'service') {
                echo ' <span class="tbn-fabric-same-netdev" title="Same host-net interface as the parent peer row — not a second NIC">same as peer</span>';
              }
            } else {
              echo '—';
            }
          ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
    <p class="tbn-note tbn-fabric-legend tbn-muted">
      <code class="tbn-ident">0-0</code> host ·
      <code class="tbn-ident">0-1</code>/<code class="tbn-ident">0-3</code> peers ·
      <code class="tbn-ident">0-1.0</code> services (expand) ·
      <code class="tbn-ident">domain0</code> domain.
      Service netdev matches the parent peer (one interface, not two).
    </p>
    <dl>
      <dt>Fabric roles:</dt>
      <dd class="tbn-muted">Controller · peer · service · domain</dd>
    </dl>
    <blockquote class="inline_help">
      <strong>Name</strong> — advertised device name.
      <strong>Manufacturer / stack</strong> — <code>vendor_name</code> (OEM on the host router; often <code>Linux</code> on a peer, not the chassis brand).<br><br>
      <strong>local controller — <code>0-0</code></strong> — this machine’s host router (board product / OEM). Rates usually live on peer rows.<br><br>
      <strong>peer — <code>0-1</code>, <code>0-3</code>, …</strong> — remote hosts after path training (topology IDs, not simple back-panel numbers).
      RX/TX, lanes, and netdev (e.g. <code>thunderbolt1</code> → tab <strong>tbn1</strong>) are on this row.<br><br>
      <strong>service — <code>0-1.0</code>, …</strong> — child protocol under a peer (host networking). Empty name is normal; netdev is the <em>same</em> interface as the parent peer (labeled “same as peer”), not a second NIC. Hidden until expanded.<br><br>
      <strong>domain — <code>domain0</code></strong> — domain container (security, etc.), not a remote PC or cable. Hidden by default.
      <?= tbn_help_docs_footer('docs/links-and-topology.md', 'Links and topology') ?>
    </blockquote>
<?php endif; ?>
  </div>

<?php /* Peer link check panel lives on Peers (with the Link check column). */ ?>
<?php endif; /* has_hw */ ?>
