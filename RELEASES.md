# Thunderbolt Net releases

## How to install or update

You can get the plugin in **either** of these ways. Both install the same Unraid plugin.

### Option A — Community Applications (recommended for most users)

1. On Unraid, open the **Apps** tab (Community Applications).
2. Search for **Thunderbolt Net** (or “Thunderbolt”).
3. Open the app and click **Install** (or **Update** if already installed).
4. Hard-refresh the browser (**Ctrl+Shift+R** / **Cmd+Shift+R**).
5. Open **Settings → Network Settings → Thunderbolt** (and **tbn0** / **tbn1** tabs when links exist).

**Support in CA:** use the app’s **Support** menu — **Support** goes to the [Unraid forum thread](https://forums.unraid.net/topic/200065-plugin-thunderbolt-net-host-to-host-networking-over-thunderbolt-345-and-usb44v2/), **Project** goes to [GitHub](https://github.com/ibigsnet/ThunderboltNet).

CA is fed from the [unraid-templates](https://github.com/ibigsnet/unraid-templates) repo; updates may lag a short time after a GitHub release.

### Option B — Plugins → Install Plugin (raw URL)

1. On Unraid: **Plugins → Install Plugin**.
2. Paste a **raw** `.plg` URL (must end in `.plg` — not a GitHub “blob” page).
3. Click **Install**.
4. Hard-refresh the browser, then open **Settings → Network Settings → Thunderbolt**.

| Track | When to use | URL |
|-------|-------------|-----|
| **Latest (`main`)** | Always get the newest published tree | `https://raw.githubusercontent.com/ibigsnet/ThunderboltNet/main/thunderboltnet.plg` |
| **This release (pinned)** | Install/rollback to a fixed version | `https://raw.githubusercontent.com/ibigsnet/ThunderboltNet/v2026.08.05ac/thunderboltnet.plg` |

After install, you can confirm the version under **Plugins** or by checking the plugin’s version string on disk.

### After install

- Plug a Thunderbolt/USB4 host-to-host cable (not SuperSpeed-only USB-C).
- Configure addressing on the **tbnN** tab for the live iface (kernel name is often `thunderbolt0`).
- Optional: **Dashboard → Interface** may list `thunderbolt0` for throughput/errors (after this release’s dashboard port patch).
- Read [DOCS.md](https://github.com/ibigsnet/ThunderboltNet/blob/main/DOCS.md) and [docs/standards-and-speeds.md](https://github.com/ibigsnet/ThunderboltNet/blob/main/docs/standards-and-speeds.md) (directionality & bandwidth table).

---

## Stable baselines (Git tags)

Plugin versions use lexicographic date strings (`YYYY.MM.DD` then `aa`, `ab`, … same day).  
A **Git tag** pins the full tree so you can reinstall that exact build.

| Tag | Plugin version | Notes |
|-----|----------------|--------|
| [`v2026.08.05ac`](https://github.com/ibigsnet/ThunderboltNet/releases/tag/v2026.08.05ac) | **2026.08.05ac** | First tagged release: directionality/bandwidth docs, USB4STREAM awareness, Dashboard TB ports, forum + GitHub support links |

### Roll back to a tag

1. **Plugins → Install Plugin** → paste that tag’s raw `.plg` URL (table above).  
2. Hard-refresh the browser.

---

## Links

| | |
|--|--|
| **GitHub repo** | https://github.com/ibigsnet/ThunderboltNet |
| **GitHub releases** | https://github.com/ibigsnet/ThunderboltNet/releases |
| **Unraid forum (support)** | https://forums.unraid.net/topic/200065-plugin-thunderbolt-net-host-to-host-networking-over-thunderbolt-345-and-usb44v2/ |
| **Docs** | [DOCS.md](https://github.com/ibigsnet/ThunderboltNet/blob/main/DOCS.md) · [docs/](https://github.com/ibigsnet/ThunderboltNet/tree/main/docs) |
