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

plus hardcoded `lo`. Stock Dashboard does **not** list `br0` (only eth / bond / wlan + lo). This plugin only adds ThunderboltNet names — never `br0`.

That **omits** kernel names used by ThunderboltNet:

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

After apply: hard-refresh the Dashboard. The Interface dropdown and table rows show **`tbn0`** (short label). The option **value** and stats stay the kernel name **`thunderbolt0`**. `bond-tb*` / `br-tb*` are already short.

## Notes

- Chart scale is the same as other ports (bits/s from byte counters).
- **Mode of operation** for `thunderbolt*` does **not** use netdev `speed` (empty on `thunderbolt_net`). The patch reads trained `rx_speed` / `tx_speed` from the Thunderbolt device sysfs (netdev `device` + parents). Equal rates → `20 Gbps, full duplex, mtu …`. Asymmetric (e.g. TB5) → `Rx: 20 Gbps, Tx: 160 Gbps, mtu …` — not “half duplex”. Down → interface down.
- OS upgrades may replace `update_3` / `DashStats.page`; `event/started` re-applies the port-list, short labels, and TB mode patches.
