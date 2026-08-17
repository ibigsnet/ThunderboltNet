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
- Multi-host fabric reports / mesh export: **token required**, **private IP only**, rate-limited (see Advanced settings). Prefer the `X-Tbn-Mesh-Token` header over `?token=` (query strings may appear in proxy logs).
- This plugin does **not** install FRR packages (that is Fabric Routing).
- Dashboard port list patch (`dynamix` `update_3` / DashStats) runs only when Thunderbolt hardware or a `thunderbolt*` netdev is present, or when already patched (self-heal). See [docs/dashboard-ports-and-clock.md](docs/dashboard-ports-and-clock.md).

## Install channel

Production / Community Applications: GitHub branch **`stable`**.  
Lab / development: branch **`main`**.

## Uninstall

- Stops udev reapply, strips Thunderbolt entries from `network-extra.cfg` (including `br-tb*`), restores Dashboard port list patches when backups exist, removes emhttp plugin tree.
- **Removes all flash state** under `/boot/config/plugins/ThunderboltNet/` (cfg, peers, iface plans, stock backups) so reinstall is clean.
- Does **not** remove Fabric Routing or NBD Export.
- Does **not** leave a recovery file after full uninstall (copy settings out first if you need them). Safe Mode recovery notes remain in docs while the plugin is installed.

## Safe Mode / no plugins

Unraid **Safe Mode** does not load this plugin.

- Manual recovery while installed: [docs/safe-mode-recovery.md](docs/safe-mode-recovery.md).

## Contact

- **Support (forum):** https://forums.unraid.net/topic/200065-plugin-thunderbolt-net-host-to-host-networking-over-thunderbolt-345-and-usb44v2/  
- **Project:** https://github.com/ibigsnet/ThunderboltNet  
