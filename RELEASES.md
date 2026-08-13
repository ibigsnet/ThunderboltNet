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

| Track | When to use | URL pattern |
|-------|-------------|-------------|
| **Latest (`main`)** | Always get the newest published tree | `https://raw.githubusercontent.com/ibigsnet/ThunderboltNet/stable/thunderboltnet.plg` |
| **Recommended freeze** | Known-good before multi-host map work | `https://raw.githubusercontent.com/ibigsnet/ThunderboltNet/stable-recommended-2026.08.13ad/thunderboltnet.plg` |
| **Pinned tag** | Install/rollback to any fixed version | `https://raw.githubusercontent.com/ibigsnet/ThunderboltNet/vVERSION/thunderboltnet.plg` |

### Recommended freeze (2026-08-13)

| | |
|--|--|
| **Label** | **Recommended** (fleet freeze before multi-host fabric map) |
| **Plugin version** | **`2026.08.13ad`** |
| **Tag** | [`stable-recommended-2026.08.13ad`](https://github.com/ibigsnet/ThunderboltNet/releases/tag/stable-recommended-2026.08.13ad) |
| **Also** | `v2026.08.13ad` |
| **Install / rollback** | `https://raw.githubusercontent.com/ibigsnet/ThunderboltNet/stable-recommended-2026.08.13ad/thunderboltnet.plg` |

Includes OpenFabric policy when FRR is present, Fabric Routing companion naming, Thunderbolt wording (not ambiguous “TB”), link quality UX, USB4STREAM detect-only. **`main` may move ahead** after this pin for multi-host reporting work.

Older pin (pre-OpenFabric era): `v2026.08.05ad`.

After install, confirm the version under **Plugins** (or the plugin version string on disk).

### After install

- Plug a Thunderbolt/USB4 host-to-host cable (not SuperSpeed-only USB-C).
- Configure addressing on the **tbnN** tab for the live iface (kernel name is often `thunderbolt0`).
- Optional: **Dashboard → Interface** may list `thunderbolt0` for throughput/errors.
- Read [DOCS.md](https://github.com/ibigsnet/ThunderboltNet/blob/main/DOCS.md) and [docs/standards-and-speeds.md](https://github.com/ibigsnet/ThunderboltNet/blob/main/docs/standards-and-speeds.md).

---

## Version strings (plugin / Unraid)

Unraid plugin updates use **lexicographic `strcmp()`**, not PHP `version_compare()`.

| Form | Meaning |
|------|---------|
| `YYYY.MM.DD` | First ship that **calendar day** |
| `YYYY.MM.DDaa` | 2nd ship same day, then `ab` … `az`, `ba`, `bb`, … |

### Calendar day (do not skip)

The date in the version string is the **lab wall-clock calendar day**, not UTC and not “yesterday’s line + 1”.

| Do | Don’t |
|----|--------|
| Read **lab host** date before bumping (`date` on Unraid; timezone **America/Chicago** for this fleet) | Use the agent/CI machine UTC date if it differs from lab |
| Use **today’s** date on that clock | Invent **tomorrow** (`…14` while lab is still the 13th) |
| Same calendar day → next **two-letter** suffix (`aa`, `ab`, …) | Jump the day number to “make room” for more ships |
| If a wrong future date already shipped, **stay on that line** for strcmp and note the mistake in CHANGES — do not rewind | Mint an older date after a newer one is installed (updates will not offer) |

**Historical miss:** bare `2026.08.14` / `14aa` / `14ab` were cut while lab was still **2026-08-13** (continued a day-ahead TBN line instead of checking lab `date`). Same class of bug as keeping letter suffixes on an old day (Storage Guard once had to “roll to calendar date”).

### Other hard rules

- No hyphens in the version string.
- After the bare date, **two-letter** suffixes only — never single `a`–`z` (strcmp treats `"aa"` as **older** than `"z"`).
- Bump **only** `<!ENTITY version "…">` in the `.plg`; asset URLs use `?v=&version;`.
- Add a `###&version;` block under `<CHANGES>` in the same ship.

### Pre-ship version checklist (agents + humans)

1. On lab: `date` → record `YYYY-MM-DD` in lab TZ (America/Chicago).
2. Read current `<!ENTITY version>` on the branch you ship.
3. Same lab date as version prefix → next two-letter suffix only.
4. Lab date newer → first ship that day = bare `YYYY.MM.DD` (if it sorts after current; else `…aa`).
5. Lab date older than a mistaken future version already out → **do not rewind**; continue suffixes on the shipped date.
6. Never set version by “latest string + one day” without looking at the lab clock.


### Cross-plugin UI links (fleet standard)

Network Settings uses an **xmenu tab strip** (eth0 · Thunderbolt · Fabric Routing · tbn…).  
Deep links like `/Settings/ThunderboltNet` or `/Settings/FabricRouting` open the **standalone CA launch page** and drop the strip.

| Do | Don’t |
|----|--------|
| `href="/Settings/NetworkSettings"` + `onclick="return ibigsGotoNetTab('Thunderbolt', event)"` | `href="/Settings/ThunderboltNet"` |
| `ibigsGotoNetTab('Fabric Routing', event)` | `href="/Settings/FabricRouting"` |
| Tab needle = page **Title** (`Thunderbolt`, `Fabric Routing`, `tbn0`, …) | Guessing single-letter version suffixes |

Shared API (any of these aliases work; first loaded plugin defines the impl):

- **`ibigsGotoNetTab(needle, event)`** — canonical  
- `tbnGotoNetTab` / `frrGotoNetTab` / `nbdGotoNetTab` — aliases  

`sessionStorage`: `ibigsWantTab` (canonical) and `tbnWantTab` (legacy).  
Network Services → **NBD** is a separate menu (`/Settings/NbdExport`); not a Network Settings tab.

---

## Git tags and GitHub Releases

### What a tag is for

| Artifact | Role |
|----------|------|
| **Plugin version** (`2026.08.11aa` in `.plg`) | What Unraid compares for “update available” |
| **Git tag** (`v2026.08.11aa`) | Pins the **full tree** so a raw `.plg` URL never drifts |
| **GitHub Release** (optional but preferred) | Human-readable notes; same tag as the pin |

Tag name = `v` + exact plugin version entity (example: plugin `2026.08.05ac` → tag `v2026.08.05ac`).

### Next major line of work (after this freeze)

**Multi-host Thunderbolt link map (planned — not in 2026.08.13ad):** share local trained TX/RX with peer Unraid hosts running Thunderbolt Net (plus FRR OpenFabric context), show neighbor-reported links in a second color band, green when both ends agree (including valid asymmetric Thunderbolt 5-style 20/60 vs 60/20), yellow when only local data, red when both plugins disagree. Design notes live in maintainer planning; implement after this recommended freeze.

### When to tag

Tag when you want a **reproducible install/rollback** point — typically after a tested ship to `main`, not for every intermediate commit.

- **Do tag:** user-facing fix, new feature, defaults change, docs that ship with a version bump.
- **Skip tag:** pure mid-work commits on a branch before merge; experiment that never hits `main`.

If `main` has already bumped the `.plg` past the last tag (e.g. `ad` on main, last tag `ac`), either:

1. Tag the current `main` tip as `v…ad` once you are happy with it, **or**
2. Leave it untagged until the next intentional ship (Latest URL still works).

### Maintainer checklist (ship a release)

Do this on a clean tree after the feature is on `main` and smoke-tested (or immediately before push if you ship in one step).

1. **Version**
   - Set `<!ENTITY version "YYYY.MM.DDxx">` in `thunderboltnet.plg`.
   - Update `<CHANGES>` for that version (short, user-facing bullets).
2. **Commit**
   - Prefer: `YYYY.MM.DDxx: short description` for the product change, then  
     `YYYY.MM.DDxx: plg version/changelog for …` if split (existing style).
   - Verify the diff: **never** push emptied `.plg` / `.page` / `.css` / large PHP (history has accidental empty-file restores).
3. **Push `main`**
   - `git push origin main`
   - Latest install URL now serves this tree.
4. **Tag** (annotated)
   ```bash
   git tag -a "vYYYY.MM.DDxx" -m "Thunderbolt Net YYYY.MM.DDxx"
   git push origin "vYYYY.MM.DDxx"
   ```
5. **GitHub Release** (recommended)
   - Create a release for that tag.
   - Paste the same changelog bullets; link DOCS / forum as needed.
6. **RELEASES.md table**
   - Add a row under [Stable baselines](#stable-baselines-git-tags).
   - Point the “pinned” example URL at the new tag if it is the current recommended pin.
7. **Community Applications** (when you want Apps to refresh)
   - CA reads [unraid-templates](https://github.com/ibigsnet/unraid-templates) (`plugins/thunderboltnet.xml`).
   - Usually no XML change if `PluginURL` already tracks `main`; bump Overview/text only when product story changes.
   - Allow a short CA cache lag after GitHub updates.
8. **Forum** (optional)
   - Note the version on the support thread for heavy changes.

### Roll back to a tag

1. **Plugins → Install Plugin** → paste that tag’s raw `.plg` URL.  
2. Hard-refresh the browser.

### Tracks at a glance

```text
develop on branch → merge to main → bump .plg version + CHANGES
       → push main          (= Latest URL)
       → git tag vVERSION   (= pin / rollback URL)
       → GitHub Release + RELEASES.md row
       → CA templates if Overview needs a refresh
```

---

## Stable baselines (Git tags)

| Tag | Plugin version | Notes |
|-----|----------------|--------|
| `main` (Latest) | **2026.08.11ak** | Links empty-state fix; uninstall keeps peers.json; entry banner only on CA launch; OpenFabric FRR chip |
| [`v2026.08.05ad`](https://github.com/ibigsnet/ThunderboltNet/releases/tag/v2026.08.05ad) | **2026.08.05ad** | **Stable pin** before OpenFabric work: standards guide (directionality, bandwidth table, mixing, FAQ) |
| [`v2026.08.05ac`](https://github.com/ibigsnet/ThunderboltNet/releases/tag/v2026.08.05ac) | **2026.08.05ac** | First GitHub Release: USB4STREAM awareness, Dashboard Thunderbolt ports, forum + GitHub support links |

Prefer **`v2026.08.05ad`** for freeze/rollback. Prefer **Latest** for newest (OpenFabric settings work when FRR is present; multi-hop needs FRR via [FabricRouting](https://github.com/ibigsnet/FabricRouting) or other install).
### Roll back to a tag

1. **Plugins → Install Plugin** → paste that tag’s raw `.plg` URL (see table above / GitHub tags).  
2. Hard-refresh the browser.

---

## Links

| | |
|--|--|
| **GitHub repo** | https://github.com/ibigsnet/ThunderboltNet |
| **GitHub releases** | https://github.com/ibigsnet/ThunderboltNet/releases |
| **Unraid forum (support)** | https://forums.unraid.net/topic/200065-plugin-thunderbolt-net-host-to-host-networking-over-thunderbolt-345-and-usb44v2/ |
| **Docs** | [DOCS.md](https://github.com/ibigsnet/ThunderboltNet/blob/main/DOCS.md) · [docs/](https://github.com/ibigsnet/ThunderboltNet/tree/main/docs) |
| **OpenFabric / FRR (LTS design)** | [docs/routing-openfabric.md](docs/routing-openfabric.md) |
| **FabricRouting companion** (optional FRR packages) | https://github.com/ibigsnet/FabricRouting |
