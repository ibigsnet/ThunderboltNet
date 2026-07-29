# Thunderbolt Net releases

## Install / update (Unraid web UI)

**Plugins → Install Plugin** → paste the raw `.plg` URL → **Install**.

| Track | URL |
|-------|-----|
| **Latest (`main`)** | `https://raw.githubusercontent.com/ibigsnet/ThunderboltNet/main/thunderboltnet.plg` |

Must be the **raw** URL ending in `.plg` (not a GitHub repo or blob page). After install, hard-refresh **Settings → Network Services → Thunderbolt Net**.

Unraid plugin updates use **string version** (`YYYY.MM.DD…`) via `strcmp`. Normal installs track **`main`**.

## Stable baselines (rollback targets)

When a build is marked **stable**, pin a Git tag so that exact tree can be reinstalled later.

| Tag | Plugin version | Notes |
|-----|----------------|--------|
| *(none yet)* | `2026.07.29ac` on `main` | First intentional public line was `aa`. `ac` = StorageGuard-parity install wipe + clean remove. Bare `2026.07.29` was a mistaken first ship — do not reintroduce. Next same-day: `ad`. |

### Roll back (when a stable tag exists)

1. Paste that tag’s raw `.plg` URL into **Plugins → Install Plugin**.  
2. Hard-refresh the browser.  
3. Optional confirm on the host:  
   `grep 'ENTITY version' /boot/config/plugins/thunderboltnet.plg`

## How we mark a stable

1. Host verified on a specific plugin version.  
2. Git commit of that tree noted.  
3. Annotated tag `stable-recommended-<version>` (optional branch `release/…`).  
4. For the tag, keep FILE URLs from drifting to newer `main` if pinning is required.  
5. Row added to this file.
