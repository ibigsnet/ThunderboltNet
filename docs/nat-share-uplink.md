# Share host uplink (NAT)

Give Thunderbolt peers a path to the **house LAN / internet** by MASQUERADEing this underlay toward Unraid’s normal uplink (`br0`, `eth0`, `wlan0`, …).

This is **optional** and **off** by default. Peer-to-peer file copy / SMB on the TB IP alone does **not** need it.

---

## Contents

- [When to use it](#when-to-use-it)
- [When not to use it](#when-not-to-use-it)
- [How it works](#how-it-works)
- [Settings](#settings)
- [Peer side (gateway)](#peer-side-gateway)
- [br0 vs wlan0 vs eth0](#br0-vs-wlan0-vs-eth0)
- [Persistence](#persistence)
- [vs Enable default route](#vs-enable-default-route)
- [Safety](#safety)
- [Related](#related)

## When to use it

| Situation | Use NAT? |
|-----------|----------|
| Proxmox / Debian / another Unraid on TB needs `apt` / updates / DNS via Unraid | **Yes** |
| Lab peer has no Wi‑Fi of its own; Unraid has the only uplink | **Yes** |
| Two hosts only exchanging data on `10.255.N.x` | **No** |
| You already joined the TB path into house `br0` (peer uses LAN IPs) | **No** — peer is already on LAN |

Typical lab: Unraid `10.255.1.1/24` on tbn0, peer `10.255.1.2/24`, peer default gateway `10.255.1.1`, NAT toward Unraid’s `br0` or `wlan0`.

## When not to use it

- Do **not** use NAT as a substitute for unique underlay addressing ([addressing.md](addressing.md)).
- Do **not** bridge Thunderbolt into `br0` *and* enable NAT on the same path without a clear design — pick one model.
- Do **not** point NAT “uplink” at another `thunderbolt*` interface.

## How it works

```text
  Peer (e.g. Proxmox)
       │  underlay 10.255.1.2/24
       │  default via 10.255.1.1
       ▼
  Unraid thunderboltN  10.255.1.1/24
       │
       │  ip_forward + MASQUERADE
       ▼
  Unraid uplink (br0 / eth0 / wlan0 / …)
       │
       ▼
  House LAN / internet
```

Unraid enables `net.ipv4.ip_forward`, installs marked `iptables` rules for **this link’s prefix only**, and leaves Docker/libvirt NAT alone.

## Settings

On **Network Settings → Thunderbolt → tbnN** (IPv4 static block):

| Field | Default | Meaning |
|-------|---------|---------|
| **Share host uplink (NAT)** | **No** | Yes = MASQUERADE this underlay toward an uplink iface |
| **NAT uplink interface** | **Auto** | Auto = iface of Unraid’s current IPv4 default route; or pick `br0` / `wlan0` / … |

Also shown (read-only):

- **Address schema** — underlay CIDR, plan, this host IP, suggested peer IP, NAT on/off.

Full field list: [settings-reference.md](settings-reference.md).

## Peer side (gateway)

The plugin **cannot** configure the peer OS. On the peer, set:

- Address on its TB iface: same subnet (e.g. `10.255.1.2/24`)
- **Default gateway:** Unraid’s TB IP (e.g. `10.255.1.1`)
- DNS: any reachable resolver (e.g. `1.1.1.1` or your LAN DNS)

Examples: Proxmox `/etc/network/interfaces`, Debian ifupdown/Netplan, another Unraid’s own tbn tab (gateway + default route there is the *peer’s* choice).

## br0 vs wlan0 vs eth0

| Uplink | When |
|--------|------|
| **br0** (or bond0) | Preferred when Unraid’s house LAN / default route is on the bridge |
| **eth0** | Fine if that is the routed iface (no bridge) |
| **wlan0** | Fine for lab boxes that only have Wi‑Fi uplink (same NAT feature) |
| **Auto** | Follows whatever currently owns the IPv4 default route |

If `br0` is down and Wi‑Fi carries default, Auto correctly picks `wlan0`. When you later move Unraid’s default to `br0`, Apply again (or reboot) so Auto refreshes — or set **NAT uplink** to `br0` explicitly.

## Persistence

| Piece | Where |
|-------|--------|
| Enable / uplink choice | Flash `ifaces/thunderboltN.cfg` (`NAT_ENABLE`, `NAT_UPLINK`) |
| Live iptables rules | Reinstalled on **Apply**, **array start**, and **Thunderbolt hotplug** (`tbn-net-reapply` → `tbn_apply_iface`) |
| `ip_forward` | Set on Apply; hint file under plugin flash / `sysctl.d` when possible |

Disabling NAT (or disabling the interface) **removes** this plugin’s marked rules for that tbnN only.

## vs Enable default route

| Control | Direction |
|---------|-----------|
| **Enable default route** on tbnN | Unraid may send *its own* default traffic **out** the Thunderbolt link (rare; can steal LAN/WAN) |
| **Share host uplink (NAT)** | Thunderbolt *peers* send traffic **to** Unraid, which NATs out br0/eth0/wlan0 |

Use NAT for “peer needs internet via Unraid.” Leave default route **No** unless Unraid itself should use TB as WAN.

## Safety

- Default **off**.
- Rules carry comment tags `ThunderboltNet-NAT-thunderboltN` — only those are removed on clear.
- Does not create or join `br0`.
- IPv6 NAT not included in this feature.
- OpenFabric / Fabric Routing are orthogonal; NAT does not enroll eth/br into the fabric.

## Related

- [addressing.md](addressing.md) — unique `/24` per link, `.1`/`.2` habit  
- [fabric-proxmox-unraid.md](fabric-proxmox-unraid.md) — mixed Proxmox + Unraid labs  
- [routing-openfabric.md](routing-openfabric.md) — multi-hop (not a substitute for NAT)  
- [settings-reference.md](settings-reference.md) — field catalog  
- [troubleshooting.md](troubleshooting.md) — NAT / peer gateway checks  
