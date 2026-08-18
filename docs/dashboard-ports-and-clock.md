# Dashboard ports patch and the clock incident

What Thunderbolt Net changes on the Unraid **Dashboard**, what broke, who was affected, and how we prevent regressions.

---

## Contents

- [What the plugin patches (only these)](#what-the-plugin-patches-only-these)
- [What else the plugin touches (not DashStats)](#what-else-the-plugin-touches-not-dashstats)
- [Bug: missing / frozen Dashboard clock](#bug-missing--frozen-dashboard-clock)
- [Impact](#impact)
- [Fix and self-heal](#fix-and-self-heal)
- [Permissions checklist](#permissions-checklist)
- [Operator commands](#operator-commands)
- [Related](#related)

## What the plugin patches (only these)

On array `event/started`, `scripts/tbn-dashboard-ports **ensure**` runs (not a blind apply):

| Situation | Behavior |
|-----------|----------|
| Already patched | Heal file modes + restart `update_3` worker if needed |
| Thunderbolt bus or `thunderbolt*` netdev present | Apply the port-list patch |
| No Thunderbolt hardware | **Skip** — leave stock dynamix alone |

Manual: `tbn-dashboard-ports apply|remove|status|heal|ensure`.

When it does apply, it edits **exactly two** LimeTech files, each by replacing **one** `grep -Po` pattern that builds the network Interface list:

| File | Role | Why we touch it |
|------|------|-----------------|
| `/usr/local/emhttp/plugins/dynamix/nchan/update_3` | Nchan PHP worker | Publishes **port throughput** *and* **current date/time** for the Dashboard |
| `/usr/local/emhttp/plugins/dynamix/DashStats.page` | Dashboard page | Same port list for the Interface dropdown |

**Content change:** allow `thunderboltN`, `bond-tbN`, `br-tbN` in addition to stock `bond|eth|wlan`.

**Not patched:** `update_1`, `update_2`, other nchan workers, DefaultPageLayout, themes, `network.cfg`, etc.

Stock copies are stored once under:

```text
/boot/config/plugins/ThunderboltNet/dashboard-ports-backup/
  update_3.stock
  DashStats.page.stock
```

**Remove** plugin or `tbn-dashboard-ports remove` restores those backups when present.

## What else the plugin touches (not DashStats)

| Path | Action | Notes |
|------|--------|--------|
| `/usr/local/emhttp/plugins/ThunderboltNet/**` | Package install | Own plugin only |
| `/boot/config/plugins/ThunderboltNet/**` | Config, peers, iface plans, backups | Plugin flash tree |
| `/etc/udev/rules.d/99-thunderboltnet-net.rules` | Copy from plugin (RAM root each boot) | L3 reapply on netdev add |
| `/boot/config/network-extra.cfg` | May add/remove `thunderbolt*` in `include_interfaces` | Listening / services Yes |
| `/etc/modprobe.d/thunderbolt_net.conf` | Optional e2e= options on Apply | Host-wide driver option |
| Live `ip` / modules | Apply L3, modprobe | No stock file rewrite |

## Bug: missing / frozen Dashboard clock

### Symptom

- Dashboard **System** tile date/time empty, frozen, or not updating.  
- Often noticed after Thunderbolt Net install/update or array start.  
- Network Interface dropdown may still list interfaces or also look stale (same worker).

### Root cause (plugin defect, fixed in **2026.08.16af** / hardened **16ag**)

1. `tbn-dashboard-ports apply` rewrote `update_3` via **temp file + replace**.  
2. Early implementation used patterns that **did not preserve file mode**.  
3. `update_3` dropped from **`755` → non-executable / restrictive mode**.  
4. Apply **stopped** the nchan `update_3` process and did not always leave a healthy worker.  
5. Dashboard JS fills `#current_time` / `#current_date` from **nchan `update3` messages** published only by that worker → clock dies.

This is **unrelated** to NTP or wrong system time. Host clock can be correct while the UI tile stays blank.

### Not the cause

- Manual one-off edits outside the plugin (incident reproduced by **normal plugin apply**).  
- Saved addresses / udev L3 reapply (different subsystem).  
- Stock Unraid “hide clock” setting (clock markup remains; feed stops).

## Impact

| Who | Effect |
|-----|--------|
| Any Unraid that successfully ran **dashboard-ports apply** on a vulnerable build | Could lose live Dashboard clock (and `update_3` net stats) |
| Hosts that never applied the patch | Unaffected |
| Severity | UI regression; networking/array otherwise OK |

## Fix and self-heal

| Version | Change |
|---------|--------|
| **2026.08.16af** | Preserve mode when rewriting; force `755` on `update_3`; restart worker with `php -q` |
| **2026.08.16ag** | Heal modes from stock backup for **both** patched files; `status` reports mode + worker; `heal` subcommand |

On every **apply** / **heal** / **status** path that touches `update_3`, the script restores executable mode when needed.

## Permissions checklist

After a healthy apply (typical Unraid):

| File | Expected mode | Executable |
|------|---------------|------------|
| `nchan/update_3` | **755** (or same as stock backup, but **must be runnable**) | **yes** |
| `DashStats.page` | **644** (or stock backup mode; not a script) | no |
| Other `nchan/*` | Unchanged **755** | yes — plugin must not rewrite them |

Verify:

```bash
tbn-dashboard-ports status
# or full path:
bash /usr/local/emhttp/plugins/ThunderboltNet/scripts/tbn-dashboard-ports status

ls -la /usr/local/emhttp/plugins/dynamix/nchan/update_3
ps aux | grep 'nchan/update_3' | grep -v grep
```

## Operator commands

```bash
# Report patch state, modes, worker
bash /usr/local/emhttp/plugins/ThunderboltNet/scripts/tbn-dashboard-ports status

# Fix modes + restart clock/net poller without changing patch content
bash /usr/local/emhttp/plugins/ThunderboltNet/scripts/tbn-dashboard-ports heal

# Re-apply TB port list (also heals modes)
bash /usr/local/emhttp/plugins/ThunderboltNet/scripts/tbn-dashboard-ports apply

# Restore stock dynamix files from plugin backup
bash /usr/local/emhttp/plugins/ThunderboltNet/scripts/tbn-dashboard-ports remove
```

Then hard-refresh the Dashboard in the browser.

## Related

- [troubleshooting.md](troubleshooting.md)  
- [dashboard-network.md](dashboard-network.md)  
- [CHANGELOG.md](../CHANGELOG.md) — **2026.08.16af**, **2026.08.16ag**  
