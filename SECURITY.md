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
- **Peer link check** (default on once a token exists): the beacon (`php -S`, TCP 10807) binds only Thunderbolt IPs (and optional `mesh_eth_ifaces`), never `0.0.0.0`/br0. **Token required** (`X-Tbn-Mesh-Token`), private/link-local sources only by default (this includes your LAN), rate-limited to 30 requests/min per source, no access log. The poller sends the token in clear HTTP to each polled peer, follows no redirects, caps snapshots at 256 KiB, and polls neighbours only inside the link's own subnet. Results stay in RAM. See [docs/fabric-link-map.md](docs/fabric-link-map.md).
- **DHCP client on a Thunderbolt link** (`dhcpcd -b -G`): no default route, no static/classless routes, no `resolv.conf` / hostname / NTP changes from the peer. No `dhclient` fallback.
- **IPv6 router advertisements** from the peer are ignored (`accept_ra=0` per Thunderbolt link) unless you set **Accept router advertisements = Yes** on that tbn tab.
- **Forwarding** (NAT, OpenFabric) is turned on per interface only; the global `net.ipv4.ip_forward` / `net.ipv6.conf.all.forwarding` switches are not touched. Values the plugin changed are restored when the feature is turned off or the plugin is removed.
- **Share host uplink (NAT)**: default **Internet and LAN** gives the peer the same reach as the uplink (your LAN included). **Internet only** drops private destinations. See [docs/nat-share-uplink.md](docs/nat-share-uplink.md).
- **Peer identity**: the remote fabric UUID is chosen by the far host. After each boot, saved **bridging**, **Unraid services (listening)** and **NAT** for a reconnecting peer stay off until you confirm that peer once (tbn **Apply**, or **Peers → Apply saved**). Addresses/MTU are applied automatically. See [docs/peers-and-plans.md](docs/peers-and-plans.md#peer-confirmation-each-boot).
- **USB4STREAM raw receive** refuses the boot flash, array (`md*`) and pool members, mounted or swap devices, and disks with holders.
- This plugin does **not** install FRR packages (that is Fabric Routing). With Fabric Routing installed it only writes its own fragment on flash and calls Fabric Routing's reload hook; it never runs `vtysh write memory`.
- The plugin does not edit stock Unraid webGUI files. It does write Unraid config it shares with you: Thunderbolt entries in `/boot/config/network-extra.cfg` (other lines kept), `/boot/config/modprobe.d/thunderbolt_net.conf`, a plugin cron file, and — only without Fabric Routing — a marked block in `/etc/frr/frr.conf` plus `fabricd=yes` in `/etc/frr/daemons`. Upgrading from 2026.09.28aa or older restores the two Dashboard files the old versions patched. See [docs/dashboard-ports-and-clock.md](docs/dashboard-ports-and-clock.md).
- Settings, peers and per-link files on flash are written only when their content changes, via temp file + rename. One plugin lock serialises Apply, boot/udev reapply, teardown and polls.

## Install channel

Production / Community Applications: GitHub branch **`main`**.  
WIP: branch **`testing`**.

## Uninstall

- Undoes live changes first: stops the link-check beacon, removes every `ThunderboltNet-NAT-*` iptables rule and the forwarding it enabled, removes the OpenFabric fragment (Fabric Routing reload hook, or the marked `frr.conf` block without it), removes the router-id `/32` on `lo`, stops plugin DHCP servers, removes the link-check cron (`update_cron`).
- Strips Thunderbolt entries (`thunderbolt*`, `bond-tb*`, `br-tb*`) from `network-extra.cfg` and keeps every other line; asks Unraid to refresh service listeners.
- Removes the udev rule, the `thunderbolt_net` modprobe options it wrote, restores Dashboard files patched by old versions, removes the emhttp plugin tree.
- **Removes all flash state** under `/boot/config/plugins/ThunderboltNet/` (cfg, peers, iface plans) and RAM state under `/var/local/thunderboltnet`, so reinstall is clean.
- Does **not** remove Fabric Routing or NBD Export.
- Does **not** leave a recovery file after full uninstall (copy settings out first if you need them). Safe Mode recovery notes remain in docs while the plugin is installed.

## Safe Mode / no plugins

Unraid **Safe Mode** does not load this plugin.

- Manual recovery while installed: [docs/safe-mode-recovery.md](docs/safe-mode-recovery.md).

## Contact

- **Support (forum):** https://forums.unraid.net/topic/200065-plugin-thunderbolt-net-host-to-host-networking-over-thunderbolt-345-and-usb44v2/  
- **Project:** https://github.com/ibigsnet/ThunderboltNet  
