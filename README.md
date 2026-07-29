# ThunderboltNet

Unraid plugin for **Thunderbolt / USB4 host networking**: status, optional static IP, and optional inclusion in Unraid **listening interfaces** (`network-extra.cfg`).

Same workflow as [StorageGuard](https://github.com/ibigsnet/StorageGuard):

1. Develop **only** on a workstation (`~/projects/ThunderboltNet`)
2. Commit + push to `main` (bump `version` in `thunderboltnet.plg` when shipping)
3. Wait briefly for `raw.githubusercontent.com` if needed
4. On Unraid:  
   `plugin install https://raw.githubusercontent.com/ibigsnet/ThunderboltNet/main/thunderboltnet.plg`  
   (or a commit-SHA URL if CDN lags)
5. Hard-refresh **Settings → Thunderbolt Net**

## What it does

| Feature | Notes |
|---------|--------|
| Status | Sysfs peers, speeds, lanes, `thunderbolt*` netdevs, carrier, IPv4 |
| Load modules | `thunderbolt` + `thunderbolt_net` (optional `e2e=0`) |
| Include listening | Adds **live** TB ifaces to `include_interfaces` (like Tailscale) |
| Static IP | Optional CLI `ip addr` on primary iface (no DHCP); never-default recommended |

## What it does **not** do

- Create Thunderbolt fabric if the cable/controller is down
- Unbind/rebind Maple Ridge NHI (can wedge the controller)
- Replace Unraid Network Settings for `eth0` / `br0` / VFIO NICs

## Requirements

- Unraid **6.12+**
- Kernel with `thunderbolt` + `thunderbolt_net` (stock modern Unraid)
- Real **Thunderbolt 4 / USB4** cables for host↔host (not USB 3.2-only C cables)

## CLI (after install)

```bash
php /usr/local/emhttp/plugins/ThunderboltNet/include/get-status.php
/usr/local/emhttp/plugins/ThunderboltNet/scripts/tbn-status
```

## SSH policy (same as StorageGuard)

**OK:** read-only checks, logs, `get-status.php`, confirm version  

**Not** the normal ship path: writing files under `/usr/local/emhttp/plugins/ThunderboltNet/` by SCP/curl  

## Author

RifleJock · https://github.com/ibigsnet/ThunderboltNet
