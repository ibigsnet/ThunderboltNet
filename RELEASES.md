# Thunderbolt Net — install & releases

## Install

### Community Applications (current)

1. Unraid **Apps** → search **Thunderbolt Net** (author RifleJock)
2. **Install** or **Update**
3. Hard-refresh the browser → **Settings → Network Settings → Thunderbolt**

CA catalog: [unraid-templates](https://github.com/ibigsnet/unraid-templates)  
**PluginURL (today):** `main` branch raw `.plg` (not a separate “stable-only” feed).

### Manual install

```
https://raw.githubusercontent.com/ibigsnet/ThunderboltNet/main/thunderboltnet.plg
```

| Channel | Role |
|---------|------|
| **`main`** | Current CA + production install URL |
| **`stable`** | Optional mirror branch (same tree when we sync it); may lag if forgotten — prefer **main** until we re-document dual-channel |

### Why two branches felt “broken”

- Unraid stores **one** plugin version string (e.g. `2026.08.17aa`). Switching CA from `stable` → `main` **does not** change the package **filename** if the version is identical — only which GitHub path hosts the `.plg` / `.txz`.
- If one branch is missing `archive/….txz` for that version, install fails mid-upgrade.
- The Plugins page **CHANGES** list is **every `###` heading inside the `.plg`**, not GitHub Releases. Keep about **7 summarized notes** (bundle rapid micro-ships as version **ranges** with a short generalized line). Final block: **Older releases** → full [`CHANGELOG.md`](CHANGELOG.md). See `~/.grok/rules/plugin-changes-and-changelog.md`.

Pinned tags (`vYYYY.MM.DDxx`, `stable-recommended-…`) remain installable by raw URL when we cut them.

### Roll back

Paste an older raw `.plg` URL (tag or commit) under **Plugins → Install Plugin**, hard-refresh.

Full history: [CHANGELOG.md](CHANGELOG.md)
