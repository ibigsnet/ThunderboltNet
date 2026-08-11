# Contributing to Thunderbolt Net

Thunderbolt Net is developed in the open for Unraid users who need serious Thunderbolt/USB4 **host networking**, including multi-peer underlay and optional **FRR/OpenFabric** multi-host fabrics (rings, meshes, mixed Proxmox/Unraid labs).

## Ways to help

| Area | Examples |
|------|----------|
| **Lab reports** | Peer OS, controller, trained rate/lanes, dual-cable behavior, ring diagrams |
| **OpenFabric / FRR** | Conf snippets that interop with our generated markers; metric edge cases |
| **UnraidFRR packages** | Slackware/Unraid-compatible FRR `.txz` builds (separate repo: [UnraidFRR](https://github.com/ibigsnet/UnraidFRR)) |
| **Device classes** | Strix Halo, Gorgon Halo, DGX Spark (and similar) hot-plug notes |
| **Bonding** | Cases where two `thunderbolt*` netdevs appear to the same peer; bond mode results |
| **UI / docs** | Clearer helpers, fixes to [docs/](docs/), translations later |
| **Code** | PHP/shell against Unraid 6.12+; keep absolute paths in `.page` files |

## Development norms

1. **Honesty over marketing** — trained rate ≠ sticker; dual-cable same-peer limits are documented, not denied.  
2. **Multi-peer underlay is real** — each `thunderboltN` is typically one peer path; multiple peers ⇒ multiple tbn tabs. Not a “single-link only” product.  
3. **OpenFabric** — implemented when FRR is present (conf generate/apply, UI); full multi-hop needs live FRR (often via UnraidFRR). Stages remain for packages, neighbors UI, peer restore.  
4. **Bonding** — available when ≥2 live TB netdevs; same-peer dual-cable multi-path is **roadmap**, not a non-goal.  
5. **Defaults favor interconnect** — OpenFabric **on** when FRR is available; pure static always available.  
6. **Do not break br0** — default route stays on main LAN unless the user opts in on a tbn tab.  
7. **Versioning** — Unraid uses lexicographic plugin versions; see [RELEASES.md](RELEASES.md). No empty-file pushes.  
8. **Markers for generated FRR** — only edit inside `BEGIN/END ThunderboltNet OpenFabric` (or coordinated plugin APIs). Never `require` UnraidFRR PHP from this plugin.
## Local notes

Machine-local Grok/lab notes may live in `.grok-notes/` (gitignored). Design that should ship to users goes in `docs/` or `DOCS.md`.

## Process

1. Fork or branch from `main`.
2. Prefer focused PRs (docs, OpenFabric stage, bonding, …).
3. Test on real TB hardware when changing apply/FRR paths.
4. Update [docs/routing-openfabric.md](docs/routing-openfabric.md) or [RELEASES.md](RELEASES.md) when user-facing behavior changes.
5. Open a GitHub issue for design questions (rings, metrics, packaging) before huge refactors.

## Contact / support

- Issues: https://github.com/ibigsnet/ThunderboltNet/issues  
- Forum: see plugin Support link / [RELEASES.md](RELEASES.md)  
