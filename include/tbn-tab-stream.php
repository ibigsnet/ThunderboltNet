<?php
echo function_exists('tbn_vfio_warning_banner_html') ? tbn_vfio_warning_banner_html($pci ?? null, $cfg ?? null) : '';
$st = function_exists('tbn_usb4stream_status') ? tbn_usb4stream_status() : ($usb4stream ?? []);
$available = !empty($st['available']);
$loaded = !empty($st['loaded']);
$live = $st['live'] ?? [];
$saved = $st['saved'] ?? [];
$services = $st['services'] ?? [];
$devs = $st['devices'] ?? [];
$kver = $st['kernel'] ?? php_uname('r');
$enable = ($cfg['enable_usb4stream'] ?? 'no') === 'yes';
?>
  <div class="tbn-section">
    <h3>USB4STREAM</h3>
    <p class="tbn-note">
      Raw host↔host path (<code>/dev/tbstreamN</code>) — not IP, not FRR, not InfiniBand.
      Create the <strong>receive</strong> side first, then the same stream name on the sender.
    </p>

    <table class="tbn-table tbn-summary">
      <tr>
        <td>Kernel</td>
        <td><code><?= htmlspecialchars($kver) ?></code></td>
      </tr>
      <tr>
        <td>Module</td>
        <td>
<?php if ($loaded): ?>
          <span class="tbn-companion-status tbn-status-ok">loaded</span>
<?php elseif ($available): ?>
          <span class="tbn-companion-status tbn-status-warn">available, not loaded</span>
<?php else: ?>
          <span class="tbn-companion-status tbn-status-warn">not in this kernel</span>
<?php endif; ?>
          · Enable on <strong>Settings</strong> is <?= $enable ? 'Yes' : 'No' ?>
        </td>
      </tr>
      <tr>
        <td>ConfigFS</td>
        <td><code><?= htmlspecialchars(($st['configfs_root'] ?? '') !== ''
          ? $st['configfs_root']
          : (!empty($st['configfs']) ? tbn_stream_configfs_root() : '(not present)')) ?></code></td>
      </tr>
      <tr>
        <td>Devices</td>
        <td><code><?= htmlspecialchars($devs ? implode(' ', $devs) : '(none)') ?></code></td>
      </tr>
    </table>
    <blockquote class="inline_help">
      USB4STREAM is a Linux <em>kernel</em> module (<code>thunderbolt_stream</code>), merged in mainline
      kernel ~7.2 — not Unraid 7.2.x. This tab creates and tears down streams through ConfigFS and
      can copy with <code>dd</code> to <code>/dev/tbstreamN</code>.
      Both peers need the module. IP/tbn still works without it.
      <?= function_exists('tbn_help_docs_footer') ? tbn_help_docs_footer('docs/usb4stream.md', 'USB4STREAM guide') : '' ?>
    </blockquote>
  </div>

  <div class="tbn-section">
    <h3>Live streams</h3>
<?php if (!$live): ?>
    <p class="tbn-muted">No live ConfigFS streams.</p>
<?php else: ?>
    <form method="POST" action="/plugins/ThunderboltNet/include/tbn-update-stream.php" target="progressFrame" id="tbn-stream-live-form">
      <?= function_exists('tbn_csrf_field') ? tbn_csrf_field() : '' ?>
      <input type="hidden" name="tbn_stream_action" value="destroy">
      <table class="tbn-table tbn-wide">
        <thead>
          <tr>
            <th></th>
            <th>Path</th>
            <th>Name</th>
            <th>Device</th>
            <th>in_hopid</th>
            <th>out_hopid</th>
          </tr>
        </thead>
        <tbody>
<?php foreach ($live as $row):
  $key = ($row['path'] ?? '') . '/' . ($row['name'] ?? '');
?>
          <tr>
            <td>
              <input type="checkbox" name="tbn_stream_keys[]" value="<?= htmlspecialchars($key) ?>"
                form="tbn-stream-live-form">
            </td>
            <td><code><?= htmlspecialchars($row['path'] ?? '') ?></code></td>
            <td><code><?= htmlspecialchars($row['name'] ?? '') ?></code></td>
            <td><code><?= htmlspecialchars($row['node'] ?: ($row['device'] ?? '')) ?></code></td>
            <td><code><?= htmlspecialchars((string)($row['in_hopid'] ?? '')) ?></code></td>
            <td><code><?= htmlspecialchars((string)($row['out_hopid'] ?? '')) ?></code></td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
      <p class="tbn-actions">
        <input type="submit" value="Tear down selected">
      </p>
    </form>
<?php endif; ?>
    <blockquote class="inline_help">
      Each row is one named stream on a Thunderbolt service path
      (<code>domain-route.index</code>, e.g. <code>0-1.0</code>).
      Tear down removes the ConfigFS group and the saved row.
    </blockquote>
  </div>

  <div class="tbn-section">
    <h3>Create stream</h3>
    <form method="POST" action="/plugins/ThunderboltNet/include/tbn-update-stream.php" target="progressFrame">
      <?= function_exists('tbn_csrf_field') ? tbn_csrf_field() : '' ?>
      <input type="hidden" name="tbn_stream_action" value="create">
      <dl>
        <dt>Peer path:</dt>
        <dd>
<?php if ($services): ?>
          <select name="tbn_stream_path">
<?php foreach ($services as $svc):
  $lab = $svc['id'];
  if (($svc['parent_name'] ?? '') !== '') {
    $lab .= ' — ' . $svc['parent_name'];
  }
?>
            <?= function_exists('mk_option') ? mk_option('', $svc['id'], $lab) : '' ?>
<?php if (!function_exists('mk_option')): ?>
            <option value="<?= htmlspecialchars($svc['id']) ?>"><?= htmlspecialchars($lab) ?></option>
<?php endif; ?>
<?php endforeach; ?>
          </select>
<?php else: ?>
          <input type="text" name="tbn_stream_path" placeholder="0-1.0" style="width:12em"
            pattern="[0-9]+-[0-9A-Fa-f]+\.[0-9]+">
<?php endif; ?>
        </dd>
      </dl>
<?php if ($services): ?>
      <dl>
        <dt>Path override:</dt>
        <dd>
          <input type="text" name="tbn_stream_path_override" value="" placeholder="leave empty to use the list"
            style="width:12em" pattern="[0-9]+-[0-9A-Fa-f]+\.[0-9]+">
        </dd>
      </dl>
<?php endif; ?>
      <dl>
        <dt>Stream name:</dt>
        <dd>
          <input type="text" name="tbn_stream_name" value="data" maxlength="8" style="width:8em"
            pattern="[A-Za-z][A-Za-z0-9_-]{0,7}" required>
        </dd>
      </dl>
      <dl>
        <dt>in_hopid:</dt>
        <dd>
          <input type="number" name="tbn_stream_in_hopid" value="-1" style="width:6em">
        </dd>
      </dl>
      <dl>
        <dt>out_hopid:</dt>
        <dd>
          <input type="number" name="tbn_stream_out_hopid" value="-1" style="width:6em">
        </dd>
      </dl>
      <blockquote class="inline_help">
        <strong>Path</strong> is the Thunderbolt service id (sysfs name), not tbn0.
        Same name on both hosts. <strong>−1</strong> allocates HopIDs automatically
        (receiver first; sender can copy advertised ids). Name max 8 characters.
        Saved on flash and recreated on Apply / array start when Enable USB4STREAM is Yes.
      </blockquote>
      <p class="tbn-actions">
        <input type="submit" value="Create stream">
      </p>
    </form>
    <form method="POST" action="/plugins/ThunderboltNet/include/tbn-update-stream.php" target="progressFrame" style="margin-top:0.4em">
      <?= function_exists('tbn_csrf_field') ? tbn_csrf_field() : '' ?>
      <input type="hidden" name="tbn_stream_action" value="apply">
      <input type="submit" value="Apply saved streams">
    </form>
  </div>

<?php if ($saved): ?>
  <div class="tbn-section">
    <h3>Saved streams</h3>
    <table class="tbn-table tbn-wide">
      <thead>
        <tr>
          <th>Path</th>
          <th>Name</th>
          <th>HopIDs</th>
          <th>Enabled</th>
        </tr>
      </thead>
      <tbody>
<?php foreach ($saved as $row): ?>
        <tr>
          <td><code><?= htmlspecialchars($row['path'] ?? '') ?></code></td>
          <td><code><?= htmlspecialchars($row['name'] ?? '') ?></code></td>
          <td><code><?= htmlspecialchars((string)($row['in_hopid'] ?? '')) ?> / <?= htmlspecialchars((string)($row['out_hopid'] ?? '')) ?></code></td>
          <td><?= !empty($row['enabled']) ? 'Yes' : 'No' ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

  <div class="tbn-section">
    <h3>Copy</h3>
    <form method="POST" action="/plugins/ThunderboltNet/include/tbn-update-stream.php" target="progressFrame" id="tbn-stream-copy-form">
      <?= function_exists('tbn_csrf_field') ? tbn_csrf_field() : '' ?>
      <input type="hidden" name="tbn_stream_action" value="copy">
      <dl>
        <dt>Direction:</dt>
        <dd>
          <select name="tbn_stream_copy_dir">
            <?= function_exists('mk_option') ? mk_option('receive', 'receive', 'Receive (stream → file)') : '' ?>
            <?= function_exists('mk_option') ? mk_option('receive', 'send', 'Send (file → stream)') : '' ?>
<?php if (!function_exists('mk_option')): ?>
            <option value="receive">Receive (stream → file)</option>
            <option value="send">Send (file → stream)</option>
<?php endif; ?>
          </select>
        </dd>
      </dl>
      <dl>
        <dt>Stream device:</dt>
        <dd>
          <select name="tbn_stream_copy_dev">
<?php
  $devopts = $devs;
  foreach ($live as $row) {
    if (!empty($row['device']) && !in_array($row['device'], $devopts, true)) {
      $devopts[] = $row['device'];
    }
  }
  if (!$devopts):
?>
            <option value="">(no /dev/tbstream* yet)</option>
<?php else:
    foreach ($devopts as $d):
      $val = (strpos($d, '/dev/') === 0) ? $d : ('/dev/' . $d);
?>
            <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($val) ?></option>
<?php
    endforeach;
  endif;
?>
          </select>
        </dd>
      </dl>
      <dl>
        <dt>File:</dt>
        <dd>
          <input type="text" name="tbn_stream_copy_file" style="width:min(32em,100%)"
            placeholder="/mnt/user/backup/peer.bin">
        </dd>
      </dl>
      <dl>
        <dt>Overwrite destination:</dt>
        <dd>
          <select name="tbn_stream_copy_overwrite">
            <?= function_exists('mk_option') ? mk_option('no', 'no', 'No') : '' ?>
            <?= function_exists('mk_option') ? mk_option('no', 'yes', 'Yes') : '' ?>
<?php if (!function_exists('mk_option')): ?>
            <option value="no">No</option>
            <option value="yes">Yes</option>
<?php endif; ?>
          </select>
        </dd>
      </dl>
      <dl>
        <dt>Raw block device:</dt>
        <dd>
          <select name="tbn_stream_copy_raw">
            <?= function_exists('mk_option') ? mk_option('no', 'no', 'No (file under /mnt or /tmp)') : '' ?>
            <?= function_exists('mk_option') ? mk_option('no', 'yes', 'Yes — File is a /dev disk (destructive)') : '' ?>
<?php if (!function_exists('mk_option')): ?>
            <option value="no">No (file under /mnt or /tmp)</option>
            <option value="yes">Yes — File is a /dev disk (destructive)</option>
<?php endif; ?>
          </select>
        </dd>
      </dl>
      <blockquote class="inline_help">
        Start <strong>Receive</strong> on this host, then Send on the peer with the same stream name.
        Files must be under <code>/mnt</code> or <code>/tmp</code>.
        Raw Yes treats File as a block device (<code>/dev/nvme0n1</code>, <code>/dev/sda</code>, …) — that can wipe a disk.
        Whole-disk imaging over IP remains <strong>NBD Export</strong>.
      </blockquote>
      <p class="tbn-actions">
        <input type="submit" value="Start copy">
      </p>
    </form>
  </div>
<script>
(function () {
  var form = document.getElementById('tbn-stream-copy-form');
  if (!form || form.getAttribute('data-tbn-stream-copy') === '1') return;
  form.setAttribute('data-tbn-stream-copy', '1');
  form.addEventListener('submit', function (ev) {
    var raw = form.querySelector('[name="tbn_stream_copy_raw"]');
    if (raw && raw.value === 'yes') {
      if (!window.confirm('Raw block copy can destroy data on that device. Continue?')) {
        ev.preventDefault();
      }
    }
  });
  var ov = form.querySelector('[name="tbn_stream_path_override"]');
  var sel = form.querySelector('[name="tbn_stream_path"]');
  /* override lives on the create form, not copy */
})();
</script>
<script>
(function () {
  var create = document.querySelector('input[name="tbn_stream_action"][value="create"]');
  if (!create) return;
  var form = create.form;
  if (!form || form.getAttribute('data-tbn-stream-create') === '1') return;
  form.setAttribute('data-tbn-stream-create', '1');
  form.addEventListener('submit', function () {
    var ov = form.querySelector('[name="tbn_stream_path_override"]');
    var sel = form.querySelector('select[name="tbn_stream_path"]');
    if (ov && sel && String(ov.value || '').trim() !== '') {
      sel.setAttribute('name', 'tbn_stream_path_list');
      ov.setAttribute('name', 'tbn_stream_path');
    }
  });
})();
</script>
