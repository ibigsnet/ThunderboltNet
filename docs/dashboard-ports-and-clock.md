# Old Dashboard port patch (removed)

Plugin versions up to **2026.09.28aa** edited two stock Unraid files so Thunderbolt ports showed
in the stock Dashboard **Interface** list:

- `/usr/local/emhttp/plugins/dynamix/nchan/update_3`
- `/usr/local/emhttp/plugins/dynamix/DashStats.page`

From **2026.09.28ab** the plugin never edits these files. It has its own Dashboard tile
([dashboard-network.md](dashboard-network.md)).

## What upgrade and remove do

Install/upgrade and remove run `scripts/tbn-dashboard-restore` once:

1. If a file has no ThunderboltNet marker, it is left alone (already stock, or replaced by an
   Unraid update).
2. If it still carries the marker, our edits are stripped in place. The old backup under
   `/boot/config/plugins/ThunderboltNet/dashboard-ports-backup/` is used only when it matches
   the stripped file, or when the in-place strip could not remove every marker.
3. `update_3` keeps its execute bit, and the `update_3` nchan worker is restarted if it changed
   (it also publishes the Dashboard clock).
4. The backup directory is deleted.

Both files live in RAM, so a reboot also loads the stock versions.

## Check (SSH)

```bash
bash /usr/local/emhttp/plugins/ThunderboltNet/scripts/tbn-dashboard-restore status
```

Expect both files **stock**, `update_3` mode **755**, and no backup directory.

## Dashboard clock missing or frozen

Older builds (before 2026.08.16af) could drop the execute bit on `update_3`, which stopped the
nchan worker that also drives the Dashboard clock. The restore step above puts the mode back.
If the clock is still frozen, reboot.

## See also

- [dashboard-network.md](dashboard-network.md)
- [troubleshooting.md](troubleshooting.md)
