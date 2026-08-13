# Thunderbolt Net — development plan

## Product pillars (stable)

1. Honest trained rate / no dual-lane fantasy  
2. **Multi-peer underlay** — not a single-link-only product; one netdev per peer path  
3. Static underlay always works without FRR  
4. OpenFabric **on** (intent) when FRR available; off override first-class  
5. FRR **packages** live in **Fabric Routing**, not this plugin  
6. Dual-cable bonding = roadmap when two netdevs exist, not a non-goal  
7. Rings/meshes with sum-of-metrics SPF (document + implement)  
8. **Mixed fabric** Unraid + Proxmox/Debian FRR — conf parity with apt FRR  

## Stages (current)

| Stage | Area | Status | Lab? |
|-------|------|--------|------|
| Done | Host-net UI, peers memory, docs, standards | Shipped | Ongoing |
| Done | OpenFabric conf generate, detect, UI, degrade | Scaffold on main | Need FRR live |
| Done | FabricRouting companion pointer | Docs + detect | — |
| Done | Mixed Proxmox/Unraid fabric reference doc | [fabric-proxmox-unraid.md](fabric-proxmox-unraid.md) | — |
| **Next** | FRR live + **L2/L3** heterogeneous adjacency | | **Yes** |
| Next | Peer last-plan restore | | Light |
| Next | Neighbors/routes panel from vtysh | | Yes |
| Later | Bond multi-path; Halo/Spark hot-plug notes | | Yes |
| Later | **L5** multi-node ring/mesh failover | | **Yes** |

## Lab phases (general)

| Phase | What | Minimum gear |
|-------|------|----------------|
| L0 | TBN Latest smoke; OpenFabric UI; br0 OK | 1 Unraid |
| L1 | FabricRouting idle → packages / `vtysh` | 1 Unraid |
| L2 | Linux/Proxmox ↔ Unraid **static** TB | 1 Linux + 1 Unraid |
| L3 | Same pair **OpenFabric** + loopbacks | Same + FRR both sides |
| L4 | Multi-hop (3+ nodes) | 3+ hosts |
| L5 | Ring/mesh failover | Topology with alternate path |
| L6 | Mixed trained rates / metrics | Multi-link lab |

Reference design: [fabric-proxmox-unraid.md](fabric-proxmox-unraid.md).
## Related

- [routing-openfabric.md](routing-openfabric.md)  
- [fabric-proxmox-unraid.md](fabric-proxmox-unraid.md)  
- [FabricRouting](https://github.com/ibigsnet/FabricRouting)  
- [RELEASES.md](../RELEASES.md)  
