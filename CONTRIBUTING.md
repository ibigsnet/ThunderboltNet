# Contributing to Thunderbolt Net

Thanks for helping improve Thunderbolt/USB4 host networking on Unraid.

## Ways to help

| Area | Examples |
|------|----------|
| **Lab reports** | Peer OS, controller, trained rate/lanes, dual-cable behavior |
| **OpenFabric / FRR** | Interop notes with Fabric Routing or other FRR installs |
| **Docs / UI** | Clearer help text, fixes under [docs/](docs/) |
| **Code** | PHP/shell against Unraid 6.12+ |

## Guidelines

1. Prefer honesty over marketing (trained rate ≠ sticker bandwidth).  
2. Do not break Unraid’s main LAN (`br0`) unless the user opts in.  
3. Keep this plugin independent of Fabric Routing PHP (detect FRR; don’t hard-require it).  
4. Test on real Thunderbolt hardware when changing apply / FRR paths.  
5. Open an issue for large design changes before huge refactors.

## Branches

| Branch | Role |
|--------|------|
| `main` | Development |
| `stable` | Production / Community Applications |

Install channels: [RELEASES.md](RELEASES.md).

## License

By contributing, you agree that your contributions are licensed under the **GNU GPLv3 or later**. Copyright for the project is held by **ibigs, LLC**.

## Contact

- Issues: https://github.com/ibigsnet/ThunderboltNet/issues  
- Forum: see the plugin Support link on the Unraid Apps page  
