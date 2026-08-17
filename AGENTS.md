# ThunderboltNet — agent notes

## Lab / ship channels

- **NIROG** (`192.168.1.3`, Extreme): tracks **main** (Plugins tab). Lab test first.
- **HoloX3D** (`192.168.254.4`, Gene): **CA / stable only**. Do not promote untested UI.
- **10.1.0.1**: production Unraid — **read-only** reference (eth0 layout, older plugins). Never write.

Prefer GitHub push + UI update; avoid routine SSH file edits on the boxes.

## Network Settings (tbnN) UI

- **Match eth0** (`/usr/local/emhttp/plugins/dynamix/Eth0.page` on NIROG or read-only 10.1.0.1): description+Info → MAC → bonding → bridging → protocol/addressing → MTU → VLANs.
- Live kernel addresses / link details → **Info** dialog (`tbn-network-info.php`), not permanent form rows with commentary.
- **No essay prose in the form.** No tip spans, no “live on the interface…” asides, no unverified claims in option labels (`— least bad`, `— usually fails…`). Verified caveats → `docs/` or blue `inline_help` after testing.
- Bonding WIP notice: only when **Enable bonding = Yes**.
- Bond name field may stay for now; default `bond-tb0` (not Unraid `bond0`).

## Network Info

Use eth0’s pattern only: `$.post('/plugins/ThunderboltNet/include/tbn-network-info.php', {port}, …)` + `swal`.
Unraid `$(document).ajaxSend` appends `csrf_token`. Raw `fetch` POST without CSRF exits empty and looks “stuck loading.”
