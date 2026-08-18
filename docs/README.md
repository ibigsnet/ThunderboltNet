# Thunderbolt Net — topic index

Full product intro: [../DOCS.md](../DOCS.md).

## How the pieces fit

```text
Physical Thunderbolt/USB4 path
  →  kernel thunderbolt_net (thunderboltN)
  →  Thunderbolt Net underlay (tbnN IP, MTU, listening)
  →  optional NAT (share Unraid br0/eth0/wlan0 with TB peers)
  →  optional OpenFabric/FRR (multi-hop / ring / mesh)
  →  FRR packages: companion Fabric Routing (not this plugin)
```

| You want… | Start here |
|-----------|------------|
| Install / update | [../RELEASES.md](../RELEASES.md) |
| First plug / defaults | [../DOCS.md](../DOCS.md) |
| Known peers, Current/Saved, Forget, renumber | [peers-and-plans.md](peers-and-plans.md) |
| Why is my rate “only 20G · 1-lane”? | [standards-and-speeds.md](standards-and-speeds.md) |
| Dual cable / multi-peer / ring | [links-and-topology.md](links-and-topology.md) |
| Multi-hop routing / FRR | [routing-openfabric.md](routing-openfabric.md) |
| Peer needs internet via Unraid (NAT) | [nat-share-uplink.md](nat-share-uplink.md) |
| Unraid + Proxmox (or other Linux) fabric | [fabric-proxmox-unraid.md](fabric-proxmox-unraid.md) |
| Every Settings field | [settings-reference.md](settings-reference.md) |
| Something broke | [troubleshooting.md](troubleshooting.md) |
| Safe Mode / no plugins — manual TB up | [safe-mode-recovery.md](safe-mode-recovery.md) |

## All topics

| Doc | Contents |
|-----|----------|
| [driver-options.md](driver-options.md) | Load modules, E2E flow control (host-wide), when to change |
| [peer-scenarios.md](peer-scenarios.md) | Unraid ↔ Linux, Proxmox, macOS, Windows, docks/hubs |
| [peers-and-plans.md](peers-and-plans.md) | UUID peers, Current/Saved, Forget, vs Interface Rules |
| [addressing.md](addressing.md) | `/24` vs `/30`, assignment autofill (static/DHCP), path cfg vs Saved, join `br0` |
| [nat-share-uplink.md](nat-share-uplink.md) | Share Unraid uplink (NAT) with TB peers; vs default route |
| [standards-and-speeds.md](standards-and-speeds.md) | Directionality, bandwidth table, mixing gens/cables/lanes, FAQ |
| [port-icons.md](port-icons.md) | Rear-panel silkscreen: lightning/40 vs SS — do I have Thunderbolt? |
| [mtu-and-throughput.md](mtu-and-throughput.md) | MTU 1500 default; optional jumbo for CPU/packet cost |
| [dashboard-network.md](dashboard-network.md) | Dashboard Interface list: thunderboltN / bond-tbN |
| [dashboard-ports-and-clock.md](dashboard-ports-and-clock.md) | What we patch on Dashboard; clock incident; heal/status |
| [usb4stream.md](usb4stream.md) | USB4STREAM raw path vs tbn net (kernel feature, not this plugin) |
| [requirements.md](requirements.md) | Hardware, BIOS, kernel modules, security |
| [fabric-link-map.md](fabric-link-map.md) | Multi-host peer link validation |
| [links-and-topology.md](links-and-topology.md) | Per-path model, multi-peer, dual cable, bonding, rings |
| [routing-openfabric.md](routing-openfabric.md) | FRR / OpenFabric — defaults, metrics, Fabric Routing split |
| [fabric-proxmox-unraid.md](fabric-proxmox-unraid.md) | Mixed fabric: Proxmox/Debian + Unraid |
| [settings-reference.md](settings-reference.md) | Every global and tbnN field |
| [troubleshooting.md](troubleshooting.md) | Common failures and recovery |
| [safe-mode-recovery.md](safe-mode-recovery.md) | Safe Mode / no plugins: manual modprobe + IP |
| [../RELEASES.md](../RELEASES.md) | Install URLs (`stable` / `main` / freezes) |
| [../CONTRIBUTING.md](../CONTRIBUTING.md) | How to contribute |
