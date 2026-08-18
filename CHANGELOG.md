## 2026.08.18ao

- **UI:** IPv6 DHCP server orange notice → field + blue helper note (same idea as
  IPv4 DHCP pool).

## 2026.08.18an

- **UI:** IPv4 address / Unraid IPv4 address `inline_help` no longer forced open by
  form sync (static or DHCP server). Hints follow Unraid Help / label click only.
- **UI:** DHCP pool orange status box converted to the same blue helper note
  (host / pool / iface plan text).

## 2026.08.18am

- **Fix:** NAT uplink choice loop no longer overwrites the tbn tab `$label` (broke
  `tbnInitIfaceForm` → looked up `tbn-form-tailscale1`).
- **Fix:** Peer plan capture preserves `USE_DHCP=server` (was collapsed to `no`).

## 2026.08.18al

- **UI:** After cable changes, the open tbnN config form **reloads from the Saved peer**
  when that link/UUID is restored (or swapped). Live poll detects peer identity change,
  re-applies the peer plan, and refreshes form fields (IP, DHCP, NAT, MTU, etc.).

## 2026.08.18ak

- **NAT lifecycle:** udev **remove** clears tagged NAT for that `thunderboltN`; **add**
  reapply stands it back up from the peer’s Saved plan.
- **NAT follows peer UUID:** Saved plan stores `NAT_ENABLE` / `NAT_UPLINK`. A different
  device on the same tbnN does not inherit the previous peer’s NAT (legacy plans → NAT off
  until Apply while linked).

## 2026.08.18aj

- **Fix:** Peer-plan reapply no longer resets path-slot policy (NAT, DHCP pool,
  bridging/bonding, OpenFabric participate). Hotplug/`tbn-net-reapply` keeps NAT on.

## 2026.08.18ai

- **Fix:** DHCP server default pool excludes the Unraid host address even when the
  host is not `.1` (pick largest contiguous usable range).

## 2026.08.18ah

- **UI:** IPv4 assignment labels — Static (Manual User Config), DHCP Client
  (Automatic Assignment), DHCP Server (Unraid-Managed); bullet help + docs footer.
- **DHCP server:** editable Unraid address/mask and DHCP pool start–end (defaults
  still `.1` / `.2–.254`); shown only in server mode.
- **Lead path line:** compact same-row path — `NAT off` or
  `underlay → uplink (ip) → internet` (uplink iface address; no public-IP lookup).

## 2026.08.18ag

- **UI:** NAT and Enable bridging are mutually exclusive (last change wins; Apply
  reconciles flash). Hide the other section while one is Yes.
- **Copy:** Drop “house LAN” phrasing; prefer optional / default No and concrete examples.

## 2026.08.18af

- **Help:** Shorter NAT hints — default No; usual P2P does not need it; Yes = peer on private TB
  subnet behind Unraid’s LAN (e.g. Proxmox needing updates via Unraid).

## 2026.08.18ae

- **UI:** Hide **NAT uplink interface** unless Share host uplink (NAT) is Yes.
- **Info:** Drop Address schema from the eth0-style Info dialog (schema stays on the tbn form lead only).
- NAT off on Apply still tears down marked iptables for that link.

## 2026.08.18ad

- **Feature:** Per-link **Share host uplink (NAT)** — MASQUERADE the tbn underlay toward
  Unraid’s LAN/WAN (`br0` / `eth0` / `wlan0` / Auto). Opposite of “Enable default route.”
  Flash cfg + reapply on Apply / array-start / hotplug; marked iptables tags for clean remove.
- **UI:** NAT enable + uplink picker; read-only **Address schema** line; Info dialog NAT status.
- **Docs:** [nat-share-uplink.md](docs/nat-share-uplink.md); settings-reference, addressing,
  troubleshooting, DOCS/README indexes.

## 2026.08.18ac

- **Docs/UI:** Recommend **MTU 1500** as the easy default. Jumbo (often 9000 both ends) is
  optional — useful to cut packet/CPU overhead on older or weaker hosts, not required for
  Thunderbolt bulk. Rewrote [mtu-and-throughput.md](docs/mtu-and-throughput.md) and matching help.

## 2026.08.18ab

- **Docs:** Remove public `steam-and-lan-discovery.md` and Steam-specific troubleshooting /
  index entries for now (kept in local lab notes). Bridging copy points at house LAN /
  [addressing.md](docs/addressing.md) only.

## 2026.08.18aa

- **Perf:** Network Settings first paint no longer runs full Thunderbolt diagnostics for every
  sibling tab. Thunderbolt overview and each `tbnN` tab are light shells; Status / Peers /
  Hardware / Settings / iface forms load when that tab is shown. Live `get-status` polling runs
  only while a Thunderbolt UI tab is visible.
- **UI:** Fabric report **Age** column formats durations (`53836s` → `14h 57m`; days round to `Nd`).

## 2026.08.17bd

- **Docs / bridge help:** Steam Local Network transfers discover the **house LAN subnet**, not a
  separate Thunderbolt `10.255.x` island — turning Wi‑Fi off on the library PC can make Steam
  fall back to another LAN PC. Steam/LAN discovery notes (later shelved from public docs).
- Unraid **join `br0`** is the supported way to put a TB peer on house LAN for Steam/SMB discovery;
  settings-reference no longer calls bridging “reserved.” Peer should use LAN addressing on its TB iface.

## 2026.08.17bc

- **Docs:** Align user-facing docs with Peers **Current** / **Saved** (and toolbar Remember current /
  Apply saved). Retire Live IPv4 / Peer plan phrasing in DOCS.md and docs/*. Historical CHANGELOG
  entries unchanged.

## 2026.08.17bb

- **Docs:** Saved auto-fills on first **tbn Apply** (Remember current optional); troubleshooting for
  Online/IP/rates but no ping (E2E flash persist); controller probe hang → full power-off recovery.
  Settings reference + Peers help aligned. No lab hostnames.

## 2026.08.17ba

- **Fix:** Post-reboot Thunderbolt path looked healthy (Online, IPs, trained rates) but ARP/ping
  failed. Root cause: `e2e_flow_control=no` was only written to RAM `/etc/modprobe.d/`, so reboot
  loaded kernel default `thunderbolt_net e2e=1` (flaky cross-host). Now persist to
  `/boot/config/modprobe.d/thunderbolt_net.conf` + `/etc`, and `event/startup` / reapply
  **reload** the module when the live bit disagrees.
- Peers: quiet **No carrier** / **No reply** under Current when Online but underlay is silent.
- Status: show live `e2e=` next to modules.

## 2026.08.17az

- **Peers wording:** columns **Current** / **Saved** (was Live IPv4 / Peer plan); toolbar
  **Remember current** / **Apply saved**. Dropped per-cell tip lines; quiet highlight when
  Current ≠ Saved. Docs/settings-reference aligned.

## 2026.08.17ay

- **Peer link-check:** only validate the local Thunderbolt link that belongs to the fetched peer
  (match remote `fabric_uuids` / hostname). Prevents painting FLOWZ orange/green from a Holo poll.

## 2026.08.17ax

- **Peer link-check matching:** pair links when the peer’s export has `peer_unique_id` equal to
  a local controller UUID (sysfs `*-0`). Fixes Unverified when sysfs `device_name` is a board
  product string instead of the Unraid hostname.

## 2026.08.17aw

- **Peer link-check host_id:** derive from `/etc/machine-id` **plus** Unraid `NAME`/hostname.
  Cloned flash sticks often share machine-id; identical ids made polls report `self_host_id` and stay Unverified.

## 2026.08.17av

- **Peer link-check:** mesh export via local **php -S beacon on TCP 10807** (token + private-IP gated).
  Polls no longer use `/plugins/.../tbn-mesh-export.php` (Unraid `auth_request` → 302 without login).
  Beacon starts when mesh report + token are enabled (Settings apply, array start/startup, poll).
- Mesh snapshot schema 2: include `dhcp_servers[]` for future conflict awareness; `beacon_port`.

## 2026.08.17au

- `.plg` `<CHANGES>`: trim back to ~**7** summarized entries (range-bundle micro-ships) before Older → CHANGELOG.

## 2026.08.17at

- **DHCP server:** stop emitting empty `option:router` / `option:dns-server` in dnsmasq conf.
  Those empty options made Unraid **dhcpcd** clear `/etc/resolv.conf` on underlay clients
  (Plugins/CA “no connectivity” / DNS failure) while eth0/wlan default route stayed fine.
- **DHCP client:** start `dhcpcd` with `-G` and `--nohook resolv.conf` on Thunderbolt underlays.

## 2026.08.17as

- DHCP server: if iface `IPADDR` is `10.255.X.Y`, serve **`10.255.X.0/24`** (host `.1`, pool `.2–.254`)
  instead of always using the thunderboltN index — so two Unraids can DHCP toward one dual-homed
  client on different /24s (e.g. NIROG `10.255.1.0/24`, Holo `10.255.2.0/24`).

## 2026.08.17ar

- **Bridging:** join an **existing** Unraid bridge (`br0`, `br0.10`, …) from the tbn tab.
  Select from live bridges; Apply enslaves the Thunderbolt iface (no own IP on the member).
  Does not create/destroy Unraid bridges; reapply on boot/hotplug when Bridging=Yes.
  Mutual exclusion with bonding on that path; underlay DHCP server skipped while bridged.

## 2026.08.17aq

- DHCP server Settings notice: print host / pool / netdev **once** (removed duplicate “Ready: …” line).

## 2026.08.17ap

- Addressing orange banner: **only** confirmed ARP duplicate (same IP + foreign MAC) — not “still on seed .2”.
- Peer link check **Last poll** uses same date display as Known peers **Last seen**.
- Docs: short **Unraid↔Unraid addressing** note (`.1`/`.2` or DHCP server).

## 2026.08.17ao

- **DHCP server** on Thunderbolt underlays (dnsmasq): assignment **Static / DHCP client / DHCP server**.
  Unraid hosts **`.1`**, serves **`.2–.254`** on that tbn (or bond-tb/br-tb); never eth0/br0.
  Optional IPv6 **DHCP server (RA)**. Safety probe blocks if `.1` is ARP-claimed.
- **Collision hints** on tbn + Peers when both ends use the same static seed (`.2`) — explains Peer
  link check Unverified with matching tokens.
- Docs/addressing.md updated; forum CTA for DHCP diagnostics.

## 2026.08.17an

- **Peer link check** panel: span full width of Peers tables again; slim horizontal band
  (drop tall centered card / 52rem caps); tighten legend copy.

## 2026.08.17am

- **Dashboard:** call `tbn-dashboard-ports ensure` on plugin install/upgrade so thunderboltN
  reappears in the Interface graph dropdown (patch was only re-applied on array start).
- **Help:** default-route text (+ advanced); DHCP client vs future server note; drop “(no page reload)”.
- **Docs:** Unraid `.1` / peer `.2` recommended stance; /24 vs /30 retained; lanes UI math
  (`rx_lanes`/`tx_lanes` + controller max) documented in standards-and-speeds.
- **Peers UI:** Known peers `<details>` marker inset; Peer link check panel full-width + centered.

## 2026.08.17al

- **CA / Plugins `<CHANGES>`:** ~7 summarized notes; bundle rapid ships as version ranges
  (e.g. ag–ak blue help / eth0 polish); Older releases → full CHANGELOG.md. Agent rule:
  `plugin-changes-and-changelog.md`.

## 2026.08.17ak

- **IPv4/IPv6 default gateway** help: leave empty for peer-to-peer; only for intentional uplink (rare).
- Docs/addressing.md: gateway + default-route section expanded (metric / topology caution).

## 2026.08.17aj

- **Enable default route** help (IPv4/IPv6): Thunderbolt often gets a much lower metric than eth0/br0,
  so Yes can steal the host default and mess up topology. Leave No unless tbn is intentionally the
  internet path (rare).

## 2026.08.17ai

- Restore MTU blue-help facts removed in the eth0-control pass: live MTU, driver min/max
  (often ~65522), product default **1500**, typical jumbo **9000** on both ends, not negotiated.
  Keep eth0-style number + Enable jumbo frames; input max uses driver max (not eth 9198 cap).

## 2026.08.17ah

- **Network Info:** use the same `$.post` + `swal` path as eth0. Raw `fetch` POST omitted
  Unraid’s CSRF token (`local_prepend.php` exits empty) so the custom modal stuck on Loading….
  Not a separate service — still `include/tbn-network-info.php`, just the stock Unraid AJAX pattern.

## 2026.08.17ag

- **tbnN closer to eth0:** Desired MTU number field + **Enable jumbo frames** checkbox (not a mode
  dropdown); **Enable VLANs** after MTU; bonding orange WIP only when bonding is Yes; clean mode
  labels (no unverified “least bad” / “usually fails”); remove on-form live-IP essay rows — live
  addresses stay in the **Info** dialog.
- **main** only — test on NIROG before CA/stable.

## 2026.08.17af

- **Peers cleanup:** Link check legend printed twice (standalone + reports panel) — keep one panel only.
- **tbnN:** remove stray overview footer; kernel-now vs form IP notes; bonding WIP always shown; Info dialog.
- Shipped to **main** for lab test first (not auto-promoted to stable).

## 2026.08.17ae

- **Fix Status/Peers/Hardware/Settings sub-tabs:** after moving tbnN before the Thunderbolt overview, Network Settings’ single DOM made `querySelector('.tbn-wrap')` bind the first **tbnN** page. Overview now uses `#tbn-overview` / `data-tbn-overview` so in-page tabs always target the correct panels. CA users: update via Apps (stable).

## 2026.08.17ad

- Packaging: Mbps→Gb/s heuristic in `tbn_format_gbps` for large numeric speeds (sysfs strings unchanged).

## 2026.08.17ac

- **Network Settings tab order:** tbnN tabs at `1010+N` (after stock Wireless `1000`); Thunderbolt overview at `1080` (after tbnN, before Interface Extra). Overview icon **bolt** / `fa-bolt`; tbnN keeps sitemap.
- **Peers clarity:** Live IPv4 vs Peer plan wording; toolbar hints; link rate “using N of M lanes”; LOCAL lanes = host capability, REMOTE = trained path (both sides can show 1 of 2); Link check legend + reports on Peers (removed from Hardware); Unverified/Waiting labels (not stuck “Checking…”); online rows soft blue.
- **Status companions:** Install Fabric Routing / NBD via **from CA** (Apps) + expandable manual .plg with Copy + Open Plugins.
- tbnN lead line closer to eth-style status strip; defaults still `10.255.N.2/24` static, services No until opted in.

## 2026.08.17ab

- Mesh: never poll self as peer; Link check — when token missing; softer online row tint.
- CA/install channel documented as **main** for now; plg CHANGES trimmed (Plugins UI was showing every ### block).
- Auto-mint mesh token when mesh_report=yes and token empty.

## 2026.08.17aa

- **Audit harden:** dashboard-ports `ensure` (skip stock patch without TB; heal if already patched); uninstall `br-tb*`; network-extra preserves unknown keys; Peers toolbar (checkbox actions outside table); Last seen uses Date and Time format; SECURITY + recovery sheet match full flash wipe; Safe Mode sheet notes Vinney findings live in normal-boot docs.

# Changelog — ThunderboltNet

User-facing history for this plugin. The `.plg` file (Community Applications / Plugins page) shows only the **most recent releases**; this file is the complete record.

**Install channels:** production/CA uses branch `stable`; lab uses `main`. See [RELEASES.md](RELEASES.md).

---

###2026.08.16ag
- **Dashboard ports harden:** heal modes from stock backups for both patched dynamix files;
  `tbn-dashboard-ports status|heal`; full incident write-up in docs/dashboard-ports-and-clock.md.

###2026.08.16af
- **Dashboard clock:** `tbn-dashboard-ports` no longer drops `+x` on dynamix `nchan/update_3`
  (that poller publishes Dashboard date/time). Restore mode after patch and restart the worker.

###2026.08.16ae
- **Boot lifecycle:** install udev + L3 reapply on plugin **`startup`** (Unraid up; array not
  required) and again on array **`started`** (Normal or Maintenance). Fixes TB config when the
  array is stopped/delayed or when users need the path before array Online. Hotplug still via udev.

###2026.08.16ad
- **Peer plans (UUID-keyed L3):** desired local IPv4 follows the remote host across
  `thunderbolt0`/`tbn0` renumber. Saved on tbn Apply (or Peers → Save live path as peer plan);
  reapplied on hotplug/boot before path-slot iface cfg. **Forget selected peers** drops Known
  peers memory/plans only — does not touch Unraid Interface Rules or eth. Visits column removed
  from the main Peers table (was a refresh counter, not reconnects).

###2026.08.16ac
- **Known peers:** unplug/replug no longer leaves a second Offline row (blank peer name) next
  to the real peer. Root cause: hotplug can briefly omit fabric `unique_id`, which created an
  `iface:thunderboltN` key; reconnect with UUID then showed two rows. Dedupe/merge into the
  UUID peer and reuse remembered UUID when sysfs UUID is empty for that iface.

###2026.08.16ab
- **Install/upgrade hygiene:** prepare always `removepkg`s every prior `ThunderboltNet-*`
  package and wipes emhttp plugin dirs (canonical + legacy casings) before the new `.txz`.
  Prevents mixed leftover files when Unraid updates across `YYYY.MM.DDxx` package names.
  Finish step fails loudly if core files did not land.

###2026.08.16aa
- Fabric devices table: sysfs IDs and netdev names share one monospace style on peer and
  service/domain rows (detail rows no longer shrink identifiers). Service netdev shows
  **same as peer** so `thunderbolt0` is not read as two different NICs.

###2026.08.15an
- OpenFabric default **off**; multi-hop settings hidden until FRR is installed (companion CTA only).
  When FRR packages appear, OpenFabric is **auto-enabled** (user can set No to opt out permanently until Yes again).

###2026.08.15am
- Peer link check defaults **on**; Peers column renamed **Link check** with clearer Match / Checking / Mismatch labels.
  Shared token still required on both hosts for a green match (not FRR).

###2026.08.15al
- Known peers: save on Apply / reapply (not only when Peers tab loads status). Empty-state copy: no lab “Machine B” placeholder.

###2026.08.15ak
- **Persist addresses after reboot and link drop:** kill dhcpcd/dhclient when applying Static
  (stops stacked 169.254.x.x); re-apply saved per-link flash config at array start; udev rule
  on thunderbolt* net add for hotplug. Thanks to **Vinney** on the Unraid forums for the bug
  report, root cause analysis, and workaround notes that shaped this fix.

###2026.08.15aj
- Companion card Block export (NBD): Not installed / Install from CA or raw .plg (aligned with Multi-hop FRR card).

###2026.08.15ai
- CA entry banner: plain user wording (no tab inventory / maintainer-style copy).

###2026.08.15ah
- CA / standalone Settings: orange Network Settings redirect banner on all sub-tabs (Status · Peers · Hardware · Settings), not only Status.

###2026.08.15ag
- Uninstall: full flash wipe (peers/ifaces/recovery no longer preserved).

###2026.08.15ac
- Changelog: Plugins page shows recent entries only; full history on GitHub <code>CHANGELOG.md</code>.

###2026.08.14ap
- **Fix:** Cables and safety help link showed literal “Directionality &amp; speeds” (double-escaped
  label). Same for Peers / MTU / bonding doc footers that used pre-encoded ampersands.

###2026.08.14ao
- Fabric reports: clear LAN peer-to-peer wording (not cloud/telemetry); TB and optional eth paths;
  off-message path + clickable jump to Settings → Show Fabric reports.

###2026.08.14an
- Fabric reports off message path + <code>tbnGotoFabricReportsSettings</code> jump.

###2026.08.14am
- Status: restore summary table rows; short per-field Help text (no meta UI notes in user copy).

###2026.08.14al
- Status: per-field inline_help for This host.

###2026.08.14ak
- **Fix:** in-page Thunderbolt tabs must not use ARIA <code>role=tab</code> /
  <code>role=tabpanel</code> — Unraid Network Settings selects all of those and
  broke Interface Extra / Routing Table (and tab switching). Use data-* only.

###2026.08.14aj
- Install: re-fetch ThunderboltNet.page if empty xmenu stub left behind.

###2026.08.14ai
- Ensure ThunderboltNet.page body ships (in-page Status/Peers/Hardware/Settings tabs).

###2026.08.14ah
- **UI:** in-page **Status · Peers · Hardware · Settings** tabs on Thunderbolt (Network Settings
  sibling). Nested Unraid xmenu left an empty Thunderbolt tab — fixed. **tbnN** remains on the
  top Network Settings strip (not nested).

###2026.08.14ag
- Attempted Unraid nested xmenu sub-pages (empty parent tab under Network Settings).

###2026.08.14af
- **Fabric reports (multi-host):** opt-in mesh export/poll — peer Unraid plugin validation (green/orange/red), Known peers last validation, legend (orange ≠ degraded). Private IP + shared token; eth allowlist optional.
- Docs: [fabric-link-map.md](docs/fabric-link-map.md).
- Offline recovery: single flash file **ThunderboltNet-RECOVERY.txt** (Thunderbolt in the name).
  Discover with `find /boot -iname '*Thunderbolt*'`. Left after uninstall.
- WebUI: Recovery section + docs bar note the flash path while plugins work.
- Drop SAFE-MODE-RECOVERY naming; clean old filenames on install.

###2026.08.14ae
- Recovery sheet: **single** flash path only
  `/boot/config/plugins/ThunderboltNet/SAFE-MODE-RECOVERY.txt` (left after uninstall).
- Drop multi-copy under `/boot/` and `/boot/config/` root — discover with `find` or `man -l` on that file.
- Docs: no system manpage/alias/profile hooks (would be core-ish); optional `man -l` local file only.

###2026.08.14ad
- Offline recovery discoverability: same sheet also at flash root and
  `/boot/config/` as `ThunderboltNet-SAFE-MODE-RECOVERY.txt`; file header
  teaches `ls /boot/*RECOVERY*` / `find /boot -iname '*RECOVERY*'`.
- First-run notify and install log mention the short flash paths.

###2026.08.14ac
- **Offline recovery sheet:** ship `SAFE-MODE-RECOVERY.txt` to emhttp + copy to flash
  (`/boot/config/plugins/ThunderboltNet/SAFE-MODE-RECOVERY.txt`) so Safe Mode can
  `cat` recovery steps without GitHub. Intentionally left on flash after uninstall.

###2026.08.14ab
- **Lab channel:** On branch `main`, PluginURL + raw FILE sources point at `main` (lab uninstall/reinstall testing).
- Branch `stable` remains the CA/production pin Production channel is branch `stable` (CA PluginURL).

###2026.08.14aa
- **License:** GNU GPLv3 or later (copyright ibigs, LLC; Author: RifleJock).
- **Release channel:** PluginURL + raw sources on branch `stable`. Development continues on `main`.
- SECURITY.md: host networking, mesh export token/private-IP notes.

###2026.08.13ad
- Wording: spell out **Thunderbolt** instead of ambiguous “TB” in UI/docs (terabyte confusion).
- Companion rename: **Fabric Routing** / id **FabricRouting** (was UnraidFRR — trademark). Detects legacy install paths.
- Docs/UI: public sanitization — no personal lab hostnames (use peer/Machine B language).

###2026.08.13aa
- Fleet standard: **`ibigsGotoNetTab`** for all Network Settings sibling links (Fabric Routing, tbnN, …); aliases `tbnGotoNetTab` / `frrGotoNetTab` / `nbdGotoNetTab`; `sessionStorage.ibigsWantTab` (+ legacy `tbnWantTab`).
- Docs: RELEASES cross-plugin UI link table (never deep-link `/Settings/FabricRouting` or `/Settings/ThunderboltNet`).

###2026.08.13
- UI: “Network Settings → Fabric Routing” via tbnGotoNetTab, not standalone `/Settings/FabricRouting`.
- Versioning: bare `2026.08.13` after historical single-letter `12a`.

###2026.08.12a
- OpenFabric: metric reference default **20000** (~20G Thunderbolt host-net class), not 100G; docs explain DAC/manual override.
- UI/docs: less “Not USB4STREAM” noise — point to usb4stream.md once.
- Docs: Contents/TOC on large guides (routing-openfabric, standards, DOCS, …).
- Docs: spell out Thunderbolt (not Thunderbolt) next to multi-terabyte imaging context.
- Companion name: **Fabric Routing** / CA **Fabric Routing (FRR)** (FabricRouting packages tab).
- Note: single-letter `12a` was non-standard (use two-letter suffixes after bare date).

###2026.08.11al
- Companion copy: FRR packages live under Network Settings → Fabric Routing (FabricRouting tab).

###2026.08.11ak
- Links empty state: no broken one-column LOCAL compare table; clean host summary when no peers online. Recovery moved out of Links body.
- Uninstall keeps peers.json + ifaces/ on flash so known peers survive remove/reinstall.

###2026.08.11aj
- Entry banner: hide when already on Network Settings (tabs visible); show only on standalone CA / Plugins launch.

###2026.08.11ai
- OpenFabric UX: **needs FRR packages** chip + CTAs jump to Multi-hop companion card (install Fabric Routing path); clearer when/why (rings, multi-hop, Proxmox) and plugin roles (TBN policy vs FRR packages vs USB4STREAM).
- Docs: routing-openfabric “when do I need this”, ecosystem table in DOCS.md, fabric-proxmox intro, troubleshooting chip path.

###2026.08.11ah
- Dashboard ports patch: no python3 (use bash replace) so thunderboltN appears on Unraid stock kernels without python.

###2026.08.11ag
- Wording pass: peer stream check uses module presence; CA Overview trained vs sticker class.

###2026.08.11af
- USB4STREAM: “Linux ~7.2” = **kernel** version (uname -r), not Unraid 7.2.x; show running kernel in status; docs warn upgrades alone do not enable stream.

###2026.08.11ae
- UX: Network Settings entry banner; companion strip (FRR / NBD / USB4STREAM); OpenFabric + USB4STREAM collapsed behind orange Show when not ready.
- Wording: IP host-net modules separate from thunderbolt_stream; FabricRouting ≠ USB4STREAM; first-run green notify + clearer install path.

###2026.08.11ad
- Known peers / quality: link rate shows **full-duplex** when RX≈TX (e.g. 20 Gb/s full-duplex · 1-lane); asymmetric shows TX (to peer) · RX (from peer).
- Services help: NBD Export is separate from listening Yes (bind under Network Services → NBD).

###2026.08.11ac
- Docs: full consistency pass — multi-peer underlay model, OpenFabric vs FabricRouting roles, doc index map, troubleshooting FRR, RELEASES version table.

###2026.08.11ab
- Wording: drop “single-link only” product framing — multi-peer tbn paths and optional OpenFabric multi-host fabric; honest trained rates; dual-cable bonding only when multiple netdevs exist.

###2026.08.11aa
- OpenFabric: point at companion plugin FabricRouting for FRR package install (separate, opt-in, not required for static Thunderbolt); status UI shows companion presence.

###2026.08.11
- OpenFabric / FRR: defaults On when FRR available; generate conf, auto metrics (ref/trained), router-id on lo, Apply + array-start hooks; degrades to static if FRR missing.
- UI: Routing (OpenFabric) section on overview; per-tbn participate / metric mode.
- Docs: FRR/OpenFabric LTS (pros/cons, path cost, rings, hot-plug Strix Halo / Gorgon Halo / DGX Spark class); bonding dual-cable as roadmap not non-goal; CONTRIBUTING; release tagging checklist.

###2026.08.05ad
- Docs: full standards guide — directionality, bandwidth table, mixing gens/cables/lanes, FAQ.

###2026.08.05ac
- Docs: planning table — expected bandwidth by Thunderbolt/USB4 class (each direction vs sticker sum vs TCP).

###2026.08.05ab
- Docs/UI: directionality — USB4/Thunderbolt simplex lanes vs PCIe full-duplex; 40G-class ≈ 20G each way, not 40 each way.

###2026.08.05aa
- Support link → Unraid forum thread; Project stays GitHub (CA shows both).

###2026.08.05
- USB4STREAM awareness: detect thunderbolt_stream, optional load on Apply, status/diagnostics, docs (not InfiniBand; needs kernel ~7.2+).
- Dashboard network: include thunderboltN (tbnN), bond-tbN, br-tbN in Interface dropdown, throughput graph, counters, and errors (same poller as eth/bond/wlan).

###2026.08.03
- Dashboard network: include thunderboltN (tbnN), bond-tbN, br-tbN in Interface dropdown, throughput graph, counters, and errors (same poller as eth/bond/wlan).

###2026.07.30bd
- Clean defaults: 10.255.0.2/24 + address_plan small-lan; bond_mode active-backup; MTU 1500; document legacy global keys.

###2026.07.30bc
- Default IPv4 10.255.0.2/24 for thunderbolt0 (not 10.255.1.2); matches tbnN → 10.255.N.2 plan.

###2026.07.30bb
- MTU product default back to 1500 for compatibility; 9000 optional both ends (not negotiated to peer; little TCP gain on 1-lane in lab).

###2026.07.30ba
- Clarify support across Thunderbolt 3/4/5 and USB4 / USB4 v2 (20G–80G class when Linux exposes host-net); trained rate ≠ sticker; not Thunderbolt 4-only.
- Docs, README, UI cable/recovery help: generation-agnostic wording.

###2026.07.30az
- Promise single-link host-net: honest trained/lanes copy; Single-lane badge (not “Below max = fix cable”); recovery help for dual-cable wedge (unplug all both boxes).
- Bonding experimental: hidden with one thunderbolt*; refuse Apply with &lt;2 members; active-backup preferred.
- Docs/README match lab reality (~10–15 Gbit/s 1-lane normal).

###2026.07.30ay
- Restore Unraid form grid on tbn tabs; only IP/CIDR rows forced horizontal (not whole form left-smashed).

- Remove in-UI port icon legend (docs-only port-icons guide); restore simple empty-state layout.

- tbn form: IPv4/IPv6 address rows left-aligned (eth0-like); override full-width inputs.

- Links LOCAL RX/TX and lanes show host Max ceiling; trained rates stay on REMOTE columns.

- Simple-then-help copy: brief section notes; detail in blue helpers; domain security phone-lock analogy.

- Port legend: Thunderbolt mark + SuperSpeed icons; no-Thunderbolt layout error|legend; half-width bottom-right on Thunderbolt hosts; Links after Known peers; column widths mid-size.

- Port silkscreen legend (Thunderbolt lightning/40 vs SS10/SS20) on overview with or without Thunderbolt hardware.

- Link quality: compact columns, Gb/s notation, Thunderbolt ports boxed (max vs trained), USB SuperSpeed collapsed; clearer remote badges.

- Link quality LOCAL: host Max + brief port list (Thunderbolt peers, 10G/20G USB-C SuperSpeed roots with counts).

- Link quality LOCAL column: host max potential (e.g. Max ~40G · 2-lane) so yellow 20G has a ceiling to compare.

- MTU modes (1500 default / 9000 recommended / custom); overview labels; docs MTU PPS tables for high-speed bulk.

- Fix stacked Thunderbolt addresses/routes: flush L3 before Apply; drop stale defaults; listen form no longer pollutes iface cfg.

- IPv4/IPv6 CIDR row alignment; bond-tb0/br-tb0 name fields + legacy bond-tb/br-tb migrate; listening help.

- Link down / no carrier: note says fine to unplug (not "usually").

- Fix activity sampling (no more too-close samples); compact services row; live activity poll.

- Known peers: services column with instant save; remove duplicate host-services table.

- Static IP flush (no stacked subnets); CIDR row align; eth-like show/hide animation; bond/bridge notes.

- Fix Network Settings blank (eth0): move netmask helper out of per-tab include (redeclare fatal).

- eth-like dynamic IPv4/IPv6 (static/auto), bonding members, VLANs; Apply enable fix.

- Host services per peer (SMB/NFS/web UI listen); remember Yes/No; Harden all to No.

- Default Thunderbolt bond name bond-tb0 (bond-tbN pattern; avoid Unraid bond0).

- Fix blank tbn1/tbnN tabs: use require (not require_once) so each tab re-renders the shared form.

###2026.07.30ab
- Link quality: short complete lead lines (no mid-sentence cutoffs); richer fabric role help with examples.
- Install fix follow-up: ensure clean plg for Unraid wget (stale compressed main cache).

###2026.07.30aa
- Install fix follow-up: ensure clean plg for Unraid wget (stale compressed main cache).
- Fix plg XML parse error (bare ampersand in CHANGES broke Install Plugin).
- Fabric devices: hide service/domain rows by default; compact link quality cells.

###2026.07.30
- Fix plg XML parse error (bare ampersand in CHANGES broke Install Plugin).
- Fabric devices: hide service/domain rows by default; compact link quality cells.

###2026.07.29bb
- Fabric devices: hide service/domain rows by default (expand for detail); link quality cells compact + wrap.

###2026.07.29ba
- Empty-state: clearer Thunderbolt 3–5/USB4 wording, spacing, link to Tools → Diagnostics.
- Docs: standards matrix (Thunderbolt 3–5, USB4/v2; not USB3-only; no “USB5” brand).

###2026.07.29az
- Docs: reseating cables and multi-cable recovery; drop confusing E2E “already know” row; no “most common” peer claim.

###2026.07.29ay
- Plug-and-play groundwork: remembered peers, activity / safe-unplug hints; Plugins blurb updated.
- Docs (DOCS.md + docs/*), Unraid blue helpers, LOCAL|REMOTE|REMOTE table, link quality cable advice.
- Clearer description: Thunderbolt-compatible host networking (not one generation only).

###2026.07.29ax
- Clearer Plugins description: Thunderbolt-compatible host networking (not one USB/Thunderbolt generation only).

###2026.07.29aw
- Docs treasure trove (DOCS.md + docs/*): driver E2E, peer scenarios (Linux/Mac/Windows/docks), addressing, speeds.
- Unraid blue inline_help on driver and tbn fields; Documentation bar links to GitHub guides.
- Link quality advice: likely cable limit + upgrade suggestion; LOCAL|REMOTE|REMOTE comparison table.

###2026.07.29av
- Compact Remote peers table (one row per tbnN); link quality badge vs controller capability.

- Clearer local vs remote identity (hostname, product, manufacturer/stack); unified link table; more driver help and spacing.

- Wording: Enable default route (default No) instead of Never default route Yes.

- Settings forms use Unraid-style dl/dt/dd (eth0-like label column).

- Link to tbnN switches Network Settings tab instead of leaving the tab strip.

- Remove version/GitHub footer from Settings pages.

- Drop version/GitHub footer from Settings pages (Plugins page already shows version).

- Summary: local hostname/controller, listening includes, bond/bridge membership; per-link local↔remote (peer name, RX/TX, lanes); optional LLDP if tools exist.

###2026.07.29am
- Thunderbolt tab vs tbnN eth-style settings; cleaner form layout.

- Neutral settings copy (remove personal use-case marketing from the UI).
- Fix tbnN tabs showing raw PHP: set Markdown=false on generated pages.

###2026.07.29aj
- Fix blank Settings tab (absolute plugin paths).
- Overview **Thunderbolt** plus per-link **tbn0** / **tbn1** tabs; eth-like per-iface settings; IOMMU/VFIO info.

###2026.07.29ai
- Empty state when no Thunderbolt hardware is detected.

###2026.07.29ah
- Thorough uninstall cleanup.

###2026.07.29ag
- Network Settings placement and host-to-host Thunderbolt LAN UX.

###2026.07.29ae
- CLI/JSON status over SSH.

###2026.07.29aa
- Initial public release.
