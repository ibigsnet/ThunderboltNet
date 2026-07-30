# Thunderbolt Net releases

## Install / update (Unraid web UI)

**Plugins → Install Plugin** → paste the raw `.plg` URL → **Install**.

| Track | URL |
|-------|-----|
| **Latest (`main`)** | `https://raw.githubusercontent.com/ibigsnet/ThunderboltNet/main/thunderboltnet.plg` |

Use the **raw** URL ending in `.plg` (not a GitHub repo or blob page). After install, hard-refresh **Settings → Network Settings → Thunderbolt Net**.

Plugin versions use a date string (`YYYY.MM.DD…`). Normal installs track **`main`**.

## Stable baselines (rollback targets)

When a build is marked **stable**, a Git tag pins that tree so it can be reinstalled without pulling newer assets from `main`.

| Tag | Plugin version | Notes |
|-----|----------------|--------|
| *(none yet)* | — | Development on `main` until a host-verified baseline is tagged. |

### Roll back (when a stable tag exists)

1. Paste that tag’s raw `.plg` URL into **Plugins → Install Plugin**.  
2. Hard-refresh the browser.
