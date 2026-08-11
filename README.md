**Thunderbolt Net**

Host-to-host networking over Thunderbolt-family ports on Unraid — **Thunderbolt 3 / 4 / 5** and **USB4 / USB4 v2** wherever Linux exposes `thunderbolt` + `thunderbolt_net`. Eth-like **per-link** settings (`tbn0`, `tbn1`, …) for one or more peers, fabric overview, trained rate and lanes, services on TB IPs, known peers, activity. Optional **OpenFabric/FRR** multi-host fabric (rings/meshes). Reports **trained** rate and lanes honestly — not a promise of full sticker dual-lane TCP. Dual-cable bonding only when multiple netdevs exist (same-peer multi-path is a roadmap item).
