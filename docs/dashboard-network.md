# Dashboard network throughput (thunderbolt / tbn)

Unraid **Dashboard → Interface** builds its port list in two places:

| File | Role |
|------|------|
| `plugins/dynamix/nchan/update_3` | Live RX/TX rates, counters, **errors/drops/overruns**, chart samples |
| `plugins/dynamix/DashStats.page` | Port **dropdown** (which iface the graph follows) |

Stock pattern only matches:

```text
^(bond|eth|wlan)\d+$
```

plus hardcoded `lo`. That **omits** kernel names used by ThunderboltNet:

| Kernel iface | Settings tab label |
|--------------|--------------------|
| `thunderbolt0` | **tbn0** |
| `thunderbolt1` | **tbn1** |
| `bond-tb0` | experimental Thunderbolt bond |
| `br-tb0` | experimental Thunderbolt bridge |

## What this plugin does

On install (and on array **started**), `scripts/tbn-dashboard-ports apply` rewrites the stock grep to:

```text
^(bond|eth|wlan|thunderbolt)\d+$|^(bond-tb|br-tb)\d+$
```

So the Dashboard shows the same **General info / Counters / Errors / Network traffic** views for Thunderbolt links as for eth/bond/wlan.

Uninstall runs `remove` and restores the stock files from a one-time backup under:

```text
/boot/config/plugins/ThunderboltNet/dashboard-ports-backup/
```

## Manual (SSH)

```bash
/usr/local/emhttp/plugins/ThunderboltNet/scripts/tbn-dashboard-ports status
/usr/local/emhttp/plugins/ThunderboltNet/scripts/tbn-dashboard-ports apply
/usr/local/emhttp/plugins/ThunderboltNet/scripts/tbn-dashboard-ports remove
```

After apply: hard-refresh the Dashboard and pick **`thunderbolt0`** (not “tbn0”) in the Interface dropdown — the UI uses the **kernel** name.

## Notes

- Chart scale is the same as other ports (bits/s from byte counters).
- Link mode line for Thunderbolt uses the stock **default** path (sysfs `speed` / `duplex` / MTU) — typically “20 Gbps, full duplex, mtu …” when the link is up.
- OS upgrades may replace `update_3` / `DashStats.page`; `event/started` re-applies the patch.
