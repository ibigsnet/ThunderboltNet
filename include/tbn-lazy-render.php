<?php
/**
 * Lazy HTML for Network Settings → Thunderbolt (overview panels or tbnN iface).
 * Network Settings embeds every sibling .page in one request; keep those shells light
 * and load this only when the Thunderbolt / tbnN tab is shown.
 */
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$docroot = $_SERVER['DOCUMENT_ROOT'] ?? '';
if ($docroot === '' || !is_dir($docroot)) {
  $docroot = '/usr/local/emhttp';
}
$_SERVER['DOCUMENT_ROOT'] = $docroot;
$helpers = $docroot . '/webGui/include/Helpers.php';
if (is_file($helpers)) {
  require_once $helpers;
}

require_once '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-page-boot.php';

$panel = isset($_GET['panel']) ? preg_replace('/[^a-z]/', '', strtolower((string)$_GET['panel'])) : '';
$iface = isset($_GET['iface']) ? (string)$_GET['iface'] : '';

if ($iface !== '') {
  if (!preg_match('/^thunderbolt\d+$/', $iface)) {
    http_response_code(400);
    echo '<p class="tbn-muted">Invalid interface.</p>';
    exit;
  }
  $tbn_if = $iface;
  if (preg_match('/^thunderbolt(\d+)$/', $iface, $m)) {
    $tbn_label = 'tbn' . $m[1];
  } else {
    $tbn_label = $iface;
  }
  // After cable/peer change: re-apply Saved peer plan onto this path so the
  // form matches the device that just came back (survives tbn renumber).
  $resync = isset($_GET['resync']) && (string)$_GET['resync'] === '1';
  if ($resync && function_exists('tbn_link_summaries') && function_exists('tbn_apply_peer_plan_to_iface')) {
    try {
      $peers = function_exists('tbn_load_peers_memory') ? tbn_load_peers_memory() : [];
      foreach (tbn_link_summaries() as $L) {
        if (($L['iface'] ?? '') !== $iface) {
          continue;
        }
        $key = function_exists('tbn_peer_key_from_link')
          ? tbn_peer_key_from_link($L, $peers)
          : trim((string)($L['remote']['unique_id'] ?? ''));
        if ($key !== '') {
          @tbn_apply_peer_plan_to_iface($key, $iface);
        }
        break;
      }
    } catch (Throwable $e) {
      // non-fatal — still render whatever is on flash
    }
  }
  // Full iface form (same as TbnN.page used to require directly)
  require '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-iface-page.php';
  exit;
}

$map = [
  'status' => 'tbn-tab-status.php',
  'peers' => 'tbn-tab-peers.php',
  'hardware' => 'tbn-tab-hardware.php',
  'settings' => 'tbn-tab-settings.php',
];
if ($panel === '' || !isset($map[$panel])) {
  http_response_code(400);
  echo '<p class="tbn-muted">Unknown panel.</p>';
  exit;
}

// Heavy boot only when a panel is actually requested
tbn_page_boot_full();
include '/usr/local/emhttp/plugins/ThunderboltNet/include/' . $map[$panel];
