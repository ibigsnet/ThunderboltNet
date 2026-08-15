# Thunderbolt Net — install & releases

## Install

### Community Applications (recommended)

1. Unraid **Apps** → search **Thunderbolt Net**
2. **Install** or **Update**
3. Hard-refresh the browser, then **Settings → Network Settings → Thunderbolt**

CA is fed from [unraid-templates](https://github.com/ibigsnet/unraid-templates). Updates may lag a short time after a GitHub push.

### Manual install (raw plugin URL)

**Plugins → Install Plugin** → paste a **raw** URL ending in `.plg` (not a GitHub “blob” page):

| Channel | Use when | URL |
|---------|----------|-----|
| **Production (`stable`)** | Normal install / CA channel | `https://raw.githubusercontent.com/ibigsnet/ThunderboltNet/stable/thunderboltnet.plg` |
| **Lab (`main`)** | Newest development tree | `https://raw.githubusercontent.com/ibigsnet/ThunderboltNet/main/thunderboltnet.plg` |
| **Recommended freeze** | Known-good pin | `https://raw.githubusercontent.com/ibigsnet/ThunderboltNet/stable-recommended-2026.08.13ad/thunderboltnet.plg` |
| **Pinned version** | Install or roll back to a fixed tag | `https://raw.githubusercontent.com/ibigsnet/ThunderboltNet/vVERSION/thunderboltnet.plg` |

- **`stable`** — what CA installs; production updates.
- **`main`** — lab only; can be ahead of CA.
- **Tags / freezes** — exact trees that never change.

### Recommended freeze

| | |
|--|--|
| **Version** | **2026.08.13ad** |
| **Tag** | [`stable-recommended-2026.08.13ad`](https://github.com/ibigsnet/ThunderboltNet/releases/tag/stable-recommended-2026.08.13ad) (also `v2026.08.13ad`) |
| **Install** | `https://raw.githubusercontent.com/ibigsnet/ThunderboltNet/stable-recommended-2026.08.13ad/thunderboltnet.plg` |

OpenFabric policy when FRR is present, Fabric Routing companion, link quality UX, USB4STREAM detect-only. Older pin: `v2026.08.05ad`.

### After install

- Use a Thunderbolt/USB4 host-to-host cable (not SuperSpeed-only USB-C).
- Set addressing on the **tbnN** tab for the live interface (often `thunderbolt0`).
- Docs: [DOCS.md](DOCS.md) · [standards and speeds](docs/standards-and-speeds.md)

### Roll back

Paste a freeze or `vVERSION` raw `.plg` URL under **Plugins → Install Plugin**, then hard-refresh.

---

## Version numbers

Plugin versions look like `2026.08.14aq` (date + two-letter suffix). Unraid compares them as plain strings for “update available.”

Changelog bullets ship in the plugin’s **Plugins** page (from the `.plg`), and optionally as [GitHub Releases](https://github.com/ibigsnet/ThunderboltNet/releases).

---

## Links

| | |
|--|--|
| **GitHub** | https://github.com/ibigsnet/ThunderboltNet |
| **Releases** | https://github.com/ibigsnet/ThunderboltNet/releases |
| **Forum support** | https://forums.unraid.net/topic/200065-plugin-thunderbolt-net-host-to-host-networking-over-thunderbolt-345-and-usb44v2/ |
| **Docs** | [DOCS.md](DOCS.md) · [docs/](docs/) |
| **Fabric Routing** (optional FRR packages) | https://github.com/ibigsnet/FabricRouting |
