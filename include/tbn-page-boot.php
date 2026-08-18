<?php
/**
 * Shared bootstrap for Thunderbolt Net UI.
 * Absolute paths only — Unraid .page eval breaks __DIR__.
 *
 * Network Settings embeds every sibling page in one PHP request. Keep the
 * default boot *light* so eth0 is not blocked by Thunderbolt diagnostics.
 * Full status/PCI/mesh work runs in tbn_page_boot_full() via tbn-lazy-render.php
 * when the Thunderbolt / tbnN tab is actually shown.
 */
require_once '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-lib.php';

$plugin = 'ThunderboltNet';
$cfg = function_exists('parse_plugin_cfg') ? parse_plugin_cfg($plugin) : [];
if (!is_array($cfg)) {
  $cfg = [];
}
$cfg = array_merge(tbn_load_cfg(), $cfg);
$ver = tbn_plugin_version();
$issues_url = 'https://github.com/ibigsnet/ThunderboltNet/issues';

// Do not sync iface pages or mint mesh tokens on every Network Settings paint.
// sync: event/startup + Apply paths. Token mint: Settings panel lazy load / Apply.

/**
 * Heavy boot for lazy-rendered panels (Status / Peers / Hardware / Settings).
 * Populates globals expected by tbn-tab-*.php.
 */
function tbn_page_boot_full() {
  global $plugin, $cfg, $ver, $issues_url;
  global $status, $mods, $devices, $netdevs, $include, $has_hw, $diag, $pci, $ifaces;
  global $mod_tb, $mod_net, $usb4stream, $mod_stream, $stream_ready;
  global $of_st, $of_mode, $frr_present, $fabricrouting, $nbd_present;
  global $of_expand_default, $stream_expand_default, $peer_count, $of_mode_label;
  global $links, $local_ctrl, $local_mfg, $peers_mem;

  if (is_file('/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-mesh.php')) {
    require_once '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-mesh.php';
  }
  if (function_exists('tbn_mesh_ensure_token') && function_exists('tbn_write_global_cfg')) {
    $before = trim((string)($cfg['mesh_token'] ?? ''));
    tbn_mesh_ensure_token($cfg);
    $after = trim((string)($cfg['mesh_token'] ?? ''));
    if ($before === '' && $after !== '' && ($cfg['mesh_report'] ?? 'no') === 'yes') {
      tbn_write_global_cfg($cfg);
    }
  }

  // Safe here: user opened Thunderbolt UI; keep tbnN Menu entries in sync
  if (function_exists('tbn_sync_iface_pages')) {
    tbn_sync_iface_pages();
  }

  $status = tbn_status();
  $mods = $status['modules'];
  $devices = $status['devices'];
  $netdevs = $status['netdevs'];
  $include = $status['include_interfaces'];
  $has_hw = !empty($status['has_hardware']);
  $diag = tbn_diagnostics_text();
  $pci = tbn_list_pci_iommu();
  $ifaces = tbn_list_tb_iface_names();

  $mod_tb = !empty($mods['thunderbolt']) ? 'loaded' : 'not loaded';
  $mod_net = !empty($mods['thunderbolt_net']) ? 'loaded' : 'not loaded';
  $usb4stream = $status['usb4stream'] ?? (function_exists('tbn_usb4stream_status') ? tbn_usb4stream_status() : []);
  $mod_stream = !empty($usb4stream['loaded']) ? 'loaded' : (!empty($usb4stream['available']) ? 'available (not loaded)' : 'not in this kernel');
  $stream_ready = !empty($usb4stream['available']) || !empty($usb4stream['loaded']);

  if (!function_exists('tbn_of_status') && is_file('/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-openfabric.php')) {
    require_once '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-openfabric.php';
  }
  $of_st = function_exists('tbn_of_status') ? tbn_of_status() : [];
  $of_mode = $of_st['mode'] ?? 'static-only';
  $frr_present = !empty($of_st['frr']['present']);
  $fabricrouting = function_exists('tbn_of_fabricrouting_companion') ? tbn_of_fabricrouting_companion() : [];
  $nbd_present = function_exists('tbn_nbdexport_present') && tbn_nbdexport_present();
  $of_expand_default = $frr_present && ($of_mode === 'openfabric-ready' || $of_mode === 'openfabric-running');
  $stream_expand_default = $stream_ready;
  $peer_count = 0;
  foreach ($devices as $d) {
    if (preg_match('/^\d+-\d+$/', $d['id']) && $d['id'] !== '0-0' && ($d['device_name'] !== '' || $d['rx_speed'] !== '')) {
      $peer_count++;
    }
  }
  $of_mode_label = [
    'openfabric-running' => 'OpenFabric running',
    'openfabric-ready' => 'FRR ready',
    'openfabric-want-frr' => 'needs FRR packages',
    'static-only' => 'static underlay',
  ][$of_mode] ?? $of_mode;

  $links = $status['links'] ?? tbn_link_summaries();
  $local_ctrl = $status['local_controller'] ?? tbn_sysfs_str('/sys/bus/thunderbolt/devices/0-0/device_name');
  $local_mfg = $status['local_manufacturer'] ?? tbn_sysfs_str('/sys/bus/thunderbolt/devices/0-0/vendor_name');

  $peers_mem = $status['peers_memory'] ?? tbn_load_peers_memory();
  if (!$peers_mem && function_exists('tbn_load_peers_memory')) {
    $peers_mem = tbn_load_peers_memory();
  }
  if (is_file('/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-mesh.php')) {
    require_once '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-mesh.php';
  }
}

/** Open shared chrome for a sub-tab. */
function tbn_page_open($active = 'status') {
  global $ver;
  echo '<link rel="stylesheet" href="/plugins/ThunderboltNet/thunderboltnet.css?v=' . htmlspecialchars($ver) . '">' . "\n";
  echo '<div class="tbn-wrap">' . "\n";
  echo tbn_docs_bar_html($active) . "\n";
}

function tbn_page_close() {
  global $ver;
  echo '</div>' . "\n";
  echo '<script src="/plugins/ThunderboltNet/thunderboltnet.js?v=' . htmlspecialchars($ver) . '"></script>' . "\n";
}
