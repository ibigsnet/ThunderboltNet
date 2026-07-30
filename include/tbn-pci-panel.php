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
$warnings = tbn_pci_warnings($pci, $cfg);
$table_id = 'tbn-pci-table-' . $tbn_pci_uid;
?>
<?php if ($warnings): ?>
  <div class="tbn-warn-list">
<?php foreach ($warnings as $w): ?>
    <div class="tbn-notice tbn-notice-warn tbn-warn-item" data-warn-key="<?= htmlspecialchars($w['key']) ?>">
      <p class="tbn-warn-msg"><strong>VFIO:</strong> <?= htmlspecialchars($w['message']) ?></p>
      <form method="POST" action="/update.php" target="progressFrame" class="tbn-warn-form" style="display:inline">
        <input type="hidden" name="#file" value="ThunderboltNet/ThunderboltNet.cfg">
        <input type="hidden" name="#include" value="/plugins/ThunderboltNet/include/tbn-ignore-warning.php">
        <input type="hidden" name="tbn_ignore_key" value="<?= htmlspecialchars($w['key']) ?>">
        <input type="submit" name="#apply" value="Ignore">
      </form>
      <span class="tbn-muted tbn-warn-hint">Hides this warning on all Thunderbolt pages (stored in plugin settings).</span>
    </div>
<?php endforeach; ?>
  </div>
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
