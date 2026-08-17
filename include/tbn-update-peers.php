<?php
/**
 * Peers tab actions: forget known peers, save/capture peer L3 plans.
 *
 * POST tbn_peer_action:
 *   forget          — remove selected keys from peers.json only
 *   save_plan       — write L3 plan fields for tbn_peer_key
 *   capture_plan    — copy current path (tbn_iface) settings onto live peer
 *   apply_plan      — apply peer plan to live iface if online
 *
 * Does not touch Unraid Interface Rules / network.cfg eth names.
 */
$docroot = $docroot ?? ($_SERVER['DOCUMENT_ROOT'] ?? '/usr/local/emhttp');
require_once '/usr/local/emhttp/plugins/ThunderboltNet/include/tbn-lib.php';

$save = false;
$action = strtolower(trim((string)($_POST['tbn_peer_action'] ?? '')));

if ($action === 'forget') {
  $keys = [];
  if (!empty($_POST['tbn_forget_keys']) && is_array($_POST['tbn_forget_keys'])) {
    foreach ($_POST['tbn_forget_keys'] as $k) {
      $k = trim((string)$k);
      if ($k !== '') {
        $keys[] = $k;
      }
    }
  }
  // Single key fallback
  $one = trim((string)($_POST['tbn_peer_key'] ?? ''));
  if ($one !== '' && !in_array($one, $keys, true)) {
    $keys[] = $one;
  }
  tbn_forget_peers($keys);
  tbn_sync_iface_pages();
  return;
}

if ($action === 'capture_plan') {
  $if = trim((string)($_POST['tbn_iface'] ?? ''));
  if (preg_match('/^thunderbolt\d+$/', $if)) {
    tbn_capture_peer_plan_from_iface($if);
  }
  tbn_sync_iface_pages();
  return;
}

if ($action === 'save_plan' || $action === 'apply_plan') {
  $key = trim((string)($_POST['tbn_peer_key'] ?? ''));
  if ($key === '') {
    return;
  }
  $plan = [
    'auto' => (strtolower(trim((string)($_POST['plan_auto'] ?? 'yes'))) === 'no') ? 'no' : 'yes',
    'USE_DHCP' => (strtolower(trim((string)($_POST['plan_use_dhcp'] ?? 'no'))) === 'yes') ? 'yes' : 'no',
    'IPADDR' => trim((string)($_POST['plan_ipaddr'] ?? '')),
    'NETMASK' => trim((string)($_POST['plan_netmask'] ?? '24')),
    'GATEWAY' => trim((string)($_POST['plan_gateway'] ?? '')),
    'DEFAULT_ROUTE' => (strtolower(trim((string)($_POST['plan_default_route'] ?? 'no'))) === 'yes') ? 'yes' : 'no',
    'MTU_MODE' => trim((string)($_POST['plan_mtu_mode'] ?? 'default')),
    'MTU' => trim((string)($_POST['plan_mtu'] ?? '1500')),
    'PROTOCOL' => 'ipv4',
  ];
  if ($plan['NETMASK'] === '') {
    $plan['NETMASK'] = '24';
  }
  $listen = null;
  if (isset($_POST['plan_listening'])) {
    $listen = (strtolower(trim((string)$_POST['plan_listening'])) === 'yes') ? 'yes' : 'no';
  }
  tbn_save_peer_plan($key, $plan, $listen);

  if ($action === 'apply_plan') {
    $peers = tbn_load_peers_memory();
    $if = trim((string)($peers[$key]['last_iface'] ?? ''));
    // Prefer live iface from summaries
    foreach (tbn_link_summaries() as $L) {
      $peers2 = tbn_load_peers_memory();
      if (tbn_peer_key_from_link($L, $peers2) === $key) {
        $if = $L['iface'] ?? $if;
        break;
      }
    }
    if ($if !== '' && preg_match('/^thunderbolt\d+$/', $if) && is_dir('/sys/class/net/' . $if)) {
      tbn_apply_peer_plan_to_iface($key, $if);
    }
  }
  tbn_sync_iface_pages();
  return;
}
