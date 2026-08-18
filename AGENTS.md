# ThunderboltNet — agent notes

## Lab / ship channels

- **NIROG** (`192.168.1.3`, Extreme): tracks **main** (Plugins tab). Lab test first.
- **HoloX3D** (`192.168.254.4`, Gene): **CA / stable only**. Do not promote untested UI.
- **10.1.0.1**: production Unraid — **read-only** reference (eth0 layout, older plugins). Never write.

Prefer GitHub push + UI update; avoid routine SSH file edits on the boxes.

**main:** OK to push often (small UI/docs/help fixes included) — easier snapshots/restore on NIROG.
**stable / CA:** never promote casually; only when asked after lab soak on main.

When bumping `&version;`: always `pack-txz.sh` **and commit** `archive/ThunderboltNet-&version;-x86_64-1.txz` in the **same** push as the `.plg`. A version bump without the txz on GitHub raw → Plugins “Invalid URL / Server error” and the box stays on the old version.

## CHANGELOG vs CA-facing `<CHANGES>`

- **`CHANGELOG.md`**: rolling full history — keep every ship documented.
- **`.plg` `<CHANGES>`**: ~**7** recent notes for Plugins/CA. Bundle rapid micro-ships into **version ranges** with a short generalized summary (e.g. `###2026.08.17ag–ak` blue help / eth0 tweaks). Keep standout fixes as their own `###`.
- **8th block:** `### Older releases` → link `CHANGELOG.md` on GitHub.
- Details: `~/.grok/rules/plugin-changes-and-changelog.md`.
## Network Settings (tbnN) UI

- **Match eth0** (`/usr/local/emhttp/plugins/dynamix/Eth0.page` on NIROG or read-only 10.1.0.1): description+Info → MAC → bonding → bridging → protocol/addressing → MTU → VLANs.
- Live kernel addresses / link details → **Info** dialog (`tbn-network-info.php`), not permanent form rows with commentary.
- **No essay prose in the form.** No tip spans, no “live on the interface…” asides, no unverified claims in option labels (`— least bad`, `— usually fails…`). Verified caveats → `docs/` or blue `inline_help` after testing.
- **Never blank verified facts** when cleaning UI (MTU 1500 default, jumbo 9000 both ends, driver max ~65522, live MTU line). Remove narration only.
- Bonding WIP notice: only when **Enable bonding = Yes**.
- Bond name field may stay for now; default `bond-tb0` (not Unraid `bond0`).

## Network Info

Use eth0’s pattern only: `$.post('/plugins/ThunderboltNet/include/tbn-network-info.php', {port}, …)` + `swal`.
Unraid `$(document).ajaxSend` appends `csrf_token`. Raw `fetch` POST without CSRF exits empty and looks “stuck loading.”

## Promoting main → stable (CA)

**Required every time** (do not skip):

1. Merge the intended main commit onto `stable`.
2. **Pin** `pluginURL` / `raw` / `readme` to `…/stable/…` (never leave `main` in those entities).
3. Verify `archive/ThunderboltNet-<ver>-x86_64-1.txz` exists on the branch.
4. Push `stable`. Confirm with curl that stable `.plg` entities say `stable`.

Use `scripts/promote-stable.sh` — it fails closed if pin is wrong.

**Why:** Unraid stores the downloaded `.plg` on flash. **Update** always follows that file’s `pluginURL`, not the CA XML. If stable’s `.plg` content still says `main`, a CA install permanently joins the main update loop (Holo got `ap` this way after an earlier main-channel install / unpinned promote).
