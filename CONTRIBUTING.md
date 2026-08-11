# Contributing to Thunderbolt Net

Thunderbolt Net is developed in the open for Unraid users who need serious Thunderbolt/USB4 **host networking**, and longer-term **FRR/OpenFabric** multi-host fabrics (rings, meshes, hot-plug peers).

## Ways to help

| Area | Examples |
|------|----------|
| **Lab reports** | Peer OS, controller, trained rate/lanes, dual-cable behavior, ring diagrams |
| **OpenFabric / FRR** | Conf snippets that interop with our generated markers; metric edge cases; packaging FRR for Unraid |
| **Device classes** | Strix Halo, Gorgon Halo, DGX Spark (and similar) hot-plug notes |
| **Bonding** | Cases where two `thunderbolt*` netdevs appear to the same peer; bond mode results |
| **UI / docs** | Clearer helpers, fixes to [docs/](docs/), translations later |
| **Code** | PHP/shell against Unraid 6.12+; keep absolute paths in `.page` files |

## Development norms

1. **Honesty over marketing** — trained rate ≠ sticker; dual-cable limits are documented, not denied; bonding and OpenFabric are **roadmaps with stages**, not vapor.
2. **Defaults favor interconnect** — OpenFabric **on** when FRR is available; static **off** is always first-class.
3. **Do not break br0** — default route stays on main LAN unless the user opts in on a tbn tab.
4. **Versioning** — Unraid uses lexicographic plugin versions; see [RELEASES.md](RELEASES.md). No empty-file pushes.
5. **Markers for generated FRR** — only edit inside `BEGIN/END ThunderboltNet OpenFabric` (or coordinated plugin APIs).

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
