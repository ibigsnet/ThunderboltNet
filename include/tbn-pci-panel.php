<?php
/**
 * Shared PCI/IOMMU panel: dismissible VFIO warnings + collapsible details table.
 * Expects $pci (array from tbn_list_pci_iommu) and optional $cfg.
 */
if (!isset($pci) || !is_array($pci)) {
  $pci = tbn_list_pci_iommu();
}
if (!isset($cfg) || !is_array($cfg)) {
  $cfg = tbn_load_cfg();
}
$tbn_pci_uid = isset($tbn_pci_uid) ? preg_replace('/[^A-Za-z0-9_-]/', '', $tbn_pci_uid) : 'main';
$table_id = 'tbn-pci-table-' . $tbn_pci_uid;
$tbn_pci_skip_banner = !empty($tbn_pci_skip_banner);
?>
<?php if (!$tbn_pci_skip_banner && function_exists('tbn_vfio_warning_banner_html')): ?>
  <?= tbn_vfio_warning_banner_html($pci, $cfg) ?>
<?php endif; ?>

<div class="tbn-pci-block">
  <p class="tbn-pci-toggle-row">
    <input type="button" class="tbn-pci-toggle" value="Show PCI / IOMMU details"
      data-target="<?= htmlspecialchars($table_id) ?>"
      data-show="Show PCI / IOMMU details"
      data-hide="Hide PCI / IOMMU details"
      onclick="tbnTogglePciDetails(this)">
  </p>
  <div id="<?= htmlspecialchars($table_id) ?>" class="tbn-pci-details tbn-hidden">
<?php if (!$pci): ?>
    <p class="tbn-muted">No Thunderbolt-related PCI devices listed.</p>
<?php else: ?>
    <table class="tbn-table tbn-wide">
      <thead>
        <tr><th>PCI BDF</th><th>IOMMU group</th><th>Driver</th><th>VFIO</th><th>Description</th></tr>
      </thead>
      <tbody>
<?php foreach ($pci as $p): ?>
        <tr class="<?= (($p['vfio'] ?? 'no') === 'yes' || ($p['driver'] ?? '') === 'vfio-pci') ? 'tbn-row-warn' : '' ?>">
          <td><code><?= htmlspecialchars($p['bdf']) ?></code></td>
          <td><code><?= htmlspecialchars($p['iommu_group']) ?></code></td>
          <td><code><?= htmlspecialchars($p['driver']) ?></code></td>
          <td><?= htmlspecialchars($p['vfio']) ?><?= ($p['vfio_boot_cfg'] ?? '') === 'yes' ? ' (boot cfg)' : '' ?></td>
          <td><?= htmlspecialchars($p['description']) ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
<?php endif; ?>
  </div>
</div>
