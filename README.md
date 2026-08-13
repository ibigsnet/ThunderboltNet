**Thunderbolt Net**

Host-to-host networking over Thunderbolt-family ports on Unraid — **Thunderbolt 3 / 4 / 5** and **USB4 / USB4 v2** wherever Linux exposes `thunderbolt` + `thunderbolt_net`. Eth-like **per-link** settings (`tbn0`, `tbn1`, …) for one or more peers, fabric overview, trained rate and lanes, services on Thunderbolt IPs, known peers, activity. Optional **OpenFabric** multi-host fabric (rings / multi-hop / Proxmox interop) when FRR is installed via companion [FabricRouting](https://github.com/ibigsnet/FabricRouting) — not required for static single-cable peers. Shows the **trained** link rate from the kernel (often below the port’s marketing class). Dual-cable bonding only when multiple netdevs exist (same-peer multi-path is a roadmap item).

## License

GNU General Public License v3.0 or later — copyright **ibigs, LLC** (Author: RifleJock). See [LICENSE](LICENSE) and [SECURITY.md](SECURITY.md).

## Install channel

**Production / CA:** `https://raw.githubusercontent.com/ibigsnet/ThunderboltNet/stable/thunderboltnet.plg`  
**Development:** work on `main`; ship by merging to `stable`.
