# Dashboard tile (thunderbolt / tbn)

Stock Unraid **Dashboard → Interface** only lists `eth*`, `bond*`, `wlan*` and `lo`. Thunderbolt
Net adds its own **Thunderbolt** tile instead of changing that list.

The tile shows every Thunderbolt Net interface that exists right now:

| Kernel iface | Shown as |
|--------------|----------|
| `thunderbolt0`, `thunderbolt1`, … | **tbn0**, **tbn1**, … |
| `bond-tb0` | `bond-tb0` |
| `br-tb0` | `br-tb0` |

For each one:

- **Link:** trained Thunderbolt rate from sysfs (`rx_speed` / `tx_speed` on the Thunderbolt
  device). Equal rates show once; asymmetric links show `Rx … / Tx …`. `down` when there is no
  carrier. Bonds and bridges show `up` / `down`.
- **Rx / Tx:** current rate in bits/s, from `/sys/class/net/<iface>/statistics` byte counters
  (refreshed every 3 seconds while the Dashboard is visible).

Move or hide the tile like any other Dashboard tile. The gear icon opens Network Settings.

Data comes from `/plugins/ThunderboltNet/include/tbn-dash.php`, which only reads sysfs and never
writes anything.

## Older versions

Up to 2026.09.28aa the plugin edited two stock files (`dynamix/nchan/update_3` and
`dynamix/DashStats.page`) to add Thunderbolt ports to the stock Interface list. That is gone.
See [dashboard-ports-and-clock.md](dashboard-ports-and-clock.md) for how upgrade and remove put
those files back.
