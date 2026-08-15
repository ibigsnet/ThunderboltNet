# Security — Thunderbolt Net

Copyright (c) 2026 ibigs, LLC · Author: RifleJock · License: GPL-3.0-or-later

## Privilege model

- Runs as root (Unraid plugin model).
- Configures **Thunderbolt / USB4 host-net interfaces** (`thunderbolt_net` / `tbnN`) only.
- Does **not** rewrite Unraid management `eth0` / `br0` or `/boot/config/network.cfg` as a general LAN manager.
- Optional OpenFabric policy is **opt-in** and uses FRR when Fabric Routing (or another FRR install) is present.

## Defaults / blast radius

- Single-cable static peers work without FRR.
- Management internet stays on eth0/br0 unless you deliberately enable Thunderbolt as default route (default **No**).
- Multi-host fabric reports / mesh export: **token required**, **private IP only**, rate-limited (see Advanced settings).
- This plugin does **not** install FRR packages (that is Fabric Routing).

## Install channel

Production / Community Applications: GitHub branch **`stable`**.  
Lab / development: branch **`main`**.

## Uninstall

- Removes the emhttp plugin tree.
- **Preserves** known peers and per-iface settings on flash for reinstall.
- Does not remove Fabric Routing or NBD Export.

## Safe Mode / no plugins

Unraid **Safe Mode** does not load this plugin.

- Manual recovery: [docs/safe-mode-recovery.md](docs/safe-mode-recovery.md).
- Offline sheet left after uninstall: `/boot/config/plugins/ThunderboltNet/ThunderboltNet-RECOVERY.txt`  
  (`find /boot -iname '*Thunderbolt*'`).

## Contact

- Support: Unraid forum thread on the plugin’s Apps card  
- Project: https://github.com/ibigsnet/ThunderboltNet  
