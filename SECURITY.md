# Security / CA review notes — Thunderbolt Net

Copyright (c) 2026 ibigs, LLC · Author: RifleJock · License: GPL-3.0-or-later

## Privilege model

- Plugin runs as root (Unraid plugin model).
- Configures **Thunderbolt / USB4 host-net interfaces** (`thunderbolt_net` / `tbnN`) only.
- Does **not** rewrite Unraid management `eth0` / `br0` or `/boot/config/network.cfg` as a general LAN manager.
- Optional OpenFabric policy is **opt-in** and expects FRR from companion Fabric Routing when used.

## Defaults / blast radius

- Single-cable static peers work without FRR.
- Management internet stays on eth0/br0 unless the user deliberately enables TB as default route (default No).
- Multi-host fabric reports / mesh export: **token required**, **private IP only**, rate-limited (see Advanced settings + docs).
- No FRR package install from this plugin (that is Fabric Routing).

## Install / update supply chain

- PluginURL: `https://raw.githubusercontent.com/ibigsnet/ThunderboltNet/stable/thunderboltnet.plg`
- FILE sources: GitHub `stable` branch
- Development on `main`; CA / production installs use `stable`

## Uninstall

- Removes emhttp plugin tree.
- **Preserves** known peers (`peers.json`) and per-iface settings on flash for reinstall.
- Does not remove Fabric Routing or NBD Export.

## What to read (5 minutes)

1. `thunderboltnet.plg` — install / Method=remove
2. `include/tbn-lib.php` — iface apply (not network.cfg rewrite)
3. `include/tbn-mesh-export.php` — token + private-only export
4. This file

## Safe Mode / no plugins

Unraid **Safe Mode** (no community plugins) does **not** load this plugin. There is no supported way to run the full Thunderbolt Net UI or OpenFabric hooks in that mode.

- **Manual recovery** (modprobe + static IP, single-cable discipline): [docs/safe-mode-recovery.md](docs/safe-mode-recovery.md).  
- **Persistent auto-bootstrap** for every boot environment is **not recommended** as a default (easy to forget, multi-cable races, route risk). Prefer manual steps for rare recovery boots.

## Contact

- Support: Unraid forum thread (CA Support link)
- Project: https://github.com/ibigsnet/ThunderboltNet
