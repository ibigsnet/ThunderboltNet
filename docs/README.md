# Thunderbolt Net — topic index

Full product intro: [../DOCS.md](../DOCS.md).

## How the pieces fit

```text
Physical Thunderbolt/USB4 path  →  kernel thunderbolt_net (thunderboltN)
                         →  Thunderbolt Net underlay (tbnN IP, MTU, listening)
                         →  optional OpenFabric/FRR (multi-hop / ring / mesh)
                         →  FRR packages on Unraid: companion Fabric Routing (not this plugin)
```

| You want… | Start here |
|-----------|------------|
| Install / version / tags | [../RELEASES.md](../RELEASES.md) |
| First plug / defaults | [../DOCS.md](../DOCS.md) |
| Why is my rate “only 20G · 1-lane”? | [standards-and-speeds.md](standards-and-speeds.md) |
| Dual cable / multi-peer / ring | [links-and-topology.md](links-and-topology.md) |
| Multi-hop routing / FRR | [routing-openfabric.md](routing-openfabric.md) |
| Unraid + Proxmox (or other Linux) fabric | [fabric-proxmox-unraid.md](fabric-proxmox-unraid.md) |
| Every Settings field | [settings-reference.md](settings-reference.md) |
| Something broke | [troubleshooting.md](troubleshooting.md) |

## All topics

| Doc | Contents |
|-----|----------|
| [driver-options.md](driver-options.md) | Load modules, **E2E flow control** (host-wide), when to change |
| [peer-scenarios.md](peer-scenarios.md) | Unraid ↔ Linux, Proxmox, macOS, Windows, docks/hubs |
| [addressing.md](addressing.md) | `/24` vs `/30`, unique subnets, multi-peer examples |
| [standards-and-speeds.md](standards-and-speeds.md) | **Directionality**, bandwidth **table**, **mixing** gens/cables/lanes, FAQ |
| [port-icons.md](port-icons.md) | Rear-panel silkscreen: lightning/40 vs SS¹⁰/SS²⁰ — do I have Thunderbolt? |
| [mtu-and-throughput.md](mtu-and-throughput.md) | MTU 1500 vs 9000, packet-rate overhead, both-ends jumbo |
| [dashboard-network.md](dashboard-network.md) | Dashboard Interface list: thunderboltN / bond-tbN graphs & errors |
| [usb4stream.md](usb4stream.md) | USB4STREAM raw path vs tbn net vs IB research; plugin support |
| [requirements.md](requirements.md) | Hardware, BIOS, kernel modules, security |
| [links-and-topology.md](links-and-topology.md) | Per-path model, multi-peer, dual cable, bonding, rings |
| [routing-openfabric.md](routing-openfabric.md) | FRR / OpenFabric — defaults, metrics, FabricRouting split, pros/cons |
| [fabric-proxmox-unraid.md](fabric-proxmox-unraid.md) | Mixed fabric: Proxmox/Debian + Unraid (example ring, lab phases) |
| [settings-reference.md](settings-reference.md) | Every global and tbnN field |
| [troubleshooting.md](troubleshooting.md) | Reseating cables, multi-cable recovery, common failures |
| [DEVELOPMENT.md](DEVELOPMENT.md) | Engineering stages and lab phases (maintainers / advanced) |
| [../RELEASES.md](../RELEASES.md) | Install URLs, version strings, **git tag / ship checklist** |
| [../CONTRIBUTING.md](../CONTRIBUTING.md) | How to contribute |
