# Thunderbolt Net — development plan

## Product pillars (stable)

1. Honest trained rate / no dual-lane fantasy  
2. Static underlay always works without FRR  
3. OpenFabric **on** when FRR available; off override first-class  
4. FRR **packages** live in **UnraidFRR**, not this plugin  
5. Dual-cable bonding = roadmap when two netdevs exist, not a non-goal  
6. Rings/meshes with sum-of-metrics SPF (document + implement)  
7. **Proxmox A/B/C + Unraid D/E** one fabric — conf parity with apt FRR  

## Stages (current)

| Stage | Area | Status | Lab? |
|-------|------|--------|------|
| Done | Host-net UI, peers memory, docs, standards | Shipped | Ongoing |
| Done | OpenFabric conf generate, detect, UI, degrade | Scaffold on main | Need FRR live |
| Done | UnraidFRR companion pointer | Docs + detect | — |
| Done | Proxmox↔Unraid fabric design doc | [fabric-proxmox-unraid.md](fabric-proxmox-unraid.md) | — |
| **Next** | FRR live + **L2/L3** Proxmox↔Unraid adjacency | | **Yes** |
| Next | Peer last-plan restore | | Light |
| Next | Neighbors/routes panel from vtysh | | Yes |
| Later | Bond multi-path; Halo/Spark hot-plug notes | | Yes |
| Later | **L5** full A–E ring failover | | **Yes** |

## Lab asks (when you’re ready)

| Phase | What | Gear |
|-------|------|------|
| L0 | TBN Latest smoke; OpenFabric UI; br0 OK | 1 Unraid |
| L1 | UnraidFRR idle → packages / `vtysh` | 1 Unraid |
| L2 | Proxmox ↔ Unraid **static** TB ping | 1 PVE + 1 Unraid |
| L3 | Same pair **OpenFabric** + loopbacks | 1 PVE + 1 Unraid |
| L4 | 3-node multi-hop | +1 |
| L5 | Full A–B–C–D–E ring failover | 3 PVE + 2 Unraid |
| L6 | Mixed metrics (e.g. 10G C–D) | Full set |

Full narrative: [fabric-proxmox-unraid.md](fabric-proxmox-unraid.md).  
L0 anytime; full ring only when you re-hook the lab.

## Related

- [routing-openfabric.md](routing-openfabric.md)  
- [fabric-proxmox-unraid.md](fabric-proxmox-unraid.md)  
- [UnraidFRR](https://github.com/ibigsnet/UnraidFRR)  
- [RELEASES.md](../RELEASES.md)  
