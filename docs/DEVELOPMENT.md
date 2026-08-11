# Thunderbolt Net — development plan

## Product pillars (stable)

1. Honest trained rate / no dual-lane fantasy  
2. Static underlay always works without FRR  
3. OpenFabric **on** when FRR available; off override first-class  
4. FRR **packages** live in **UnraidFRR**, not this plugin  
5. Dual-cable bonding = roadmap when two netdevs exist, not a non-goal  
6. Rings/meshes with sum-of-metrics SPF (document + implement)  

## Stages (current)

| Stage | Area | Status | Lab? |
|-------|------|--------|------|
| Done | Host-net UI, peers memory, docs, standards | Shipped | Ongoing |
| Done | OpenFabric conf generate, detect, UI, degrade | Scaffold on main | Need FRR live |
| Done | UnraidFRR companion pointer | Docs + detect | — |
| **Next** | Lab: FRR present + 2-node OpenFabric adjacency | | **Yes** |
| Next | Peer last-plan restore | | Light |
| Next | Neighbors/routes panel from vtysh | | Yes |
| Later | Bond multi-path; Halo/Spark hot-plug notes | | Yes |
| Later | Ring 3–5 node metric failover | | **Yes** |

## Lab asks (when you’re ready)

1. **Smoke Latest** — install `main` plg; OpenFabric section; no br0 breakage  
2. **UnraidFRR idle** — companion alone, empty packages  
3. **FRR packages** — when we have a `.txz`, install + `vtysh`  
4. **2-node TB** — static then OpenFabric adjacency  
5. **Ring** — C–D slower link cost story (optional)  

No need to re-hook hardware until stages marked **Lab? Yes** are ready to run.

## Related

- [routing-openfabric.md](routing-openfabric.md)  
- [UnraidFRR](https://github.com/ibigsnet/UnraidFRR)  
- [RELEASES.md](../RELEASES.md)  
