# Share host uplink (NAT)

**Optional. Default: No.** Typical Thunderbolt (copy / SMB between two hosts on a private TB address) does not need this.

**Yes:** the peer keeps a private Thunderbolt IP and reaches the internet **through Unraid** (Unraid NATs toward whatever carries Unraid’s uplink — often `br0`, `eth0`, or `wlan0`). Set the peer’s default gateway to Unraid’s tbn IP.

**Not the same as Enable bridging** (join an existing Unraid bridge). Bridging puts the peer on that bridge’s network; NAT keeps a separate TB subnet and forwards via Unraid. The UI will not allow both Yes at once.

After enabling NAT, click **Apply** while the peer is linked so Saved remembers it for that peer UUID (survives cable moves / tbn renumber).

---

## Contents

- [When to use it](#when-to-use-it)
- [When not to use it](#when-not-to-use-it)
- [How it works](#how-it-works)
- [Settings](#settings)
- [Peer side (gateway)](#peer-side-gateway)
- [Choosing an uplink](#choosing-an-uplink)
- [Persistence](#persistence)
- [vs Enable default route](#vs-enable-default-route)
- [Safety](#safety)
- [Related](#related)

## When to use it

| Situation | Use NAT? |
|-----------|----------|
| Peer has no Wi‑Fi or viable Ethernet of its own (tablet, laptop on TB only, appliance, etc.); Unraid can share its uplink | **Yes** |
| Peer needs packages / updates / DNS via Unraid while staying on a private TB address | **Yes** |
| Two hosts only exchanging data on the TB underlay (`10.255.N.x`) | **No** |
| Bridging is already Yes (peer addresses on the bridge’s network) | **No** — pick bridging **or** NAT, not both |

Example underlay: Unraid `10.255.N.1/24`, peer `10.255.N.2/24`, peer default gateway = Unraid’s TB IP, NAT toward Unraid’s uplink iface.

## When not to use it

- Do **not** use NAT as a substitute for unique underlay addressing ([addressing.md](addressing.md)).
- Do **not** enable bridging **and** NAT on the same path — pick one model.
- Do **not** point NAT “uplink” at another `thunderbolt*` interface.

## How it works

```text
  Peer
       │  underlay 10.255.N.2/24
       │  default via Unraid’s TB IP
       ▼
  Unraid thunderboltN  10.255.N.1/24
       │
       │  ip_forward + MASQUERADE
       ▼
  Unraid uplink (br0 / eth0 / wlan0 / …)
       │
       ▼
  LAN / internet
```

Unraid enables `net.ipv4.ip_forward`, installs marked `iptables` rules for **this link’s prefix only**, and leaves Docker/libvirt NAT alone.

## Settings

On **Network Settings → Thunderbolt → tbnN** (IPv4 static block):

| Field | Default | Meaning |
|-------|---------|---------|
| **Share host uplink (NAT)** | **No** | Yes = MASQUERADE this underlay toward an uplink iface |
| **NAT uplink interface** | **Auto** | Auto = iface of Unraid’s current IPv4 default route; or pick `br0` / `wlan0` / … |

Also shown (read-only):

- Path line — underlay · peer · `NAT off`, or `underlay → uplink (ip) → internet`

Full field list: [settings-reference.md](settings-reference.md).

## Peer side (gateway)

The plugin **cannot** configure the peer OS. On the peer, set:

- Address on its TB iface: same subnet (e.g. `10.255.N.2/24`)
- **Default gateway:** Unraid’s TB IP (e.g. `10.255.N.1`)
- DNS: any reachable resolver (public DNS or your LAN resolver)

Any peer OS that can set a static gateway works (Linux, another Unraid tbn tab, etc.).

## Choosing an uplink

| Uplink | When |
|--------|------|
| **br0** (or bond0) | Preferred when Unraid’s default route is on the bridge |
| **eth0** | Fine if that is the routed iface (no bridge) |
| **wlan0** | Fine when Unraid’s uplink is Wi‑Fi |
| **Auto** | Follows whatever currently owns the IPv4 default route |

If the wired bridge is down and Wi‑Fi carries default, Auto picks `wlan0`. When Unraid’s default later moves to `br0`, Apply again (or reboot) so Auto refreshes — or set **NAT uplink** explicitly.

## Persistence

| Piece | Where |
|-------|--------|
| Enable / uplink choice | Flash `ifaces/thunderboltN.cfg` **and** Saved peer plan (UUID) on **Apply** while linked |
| Live iptables rules | Installed on Apply / array start / **netdev add**; **removed on netdev remove** |
| `ip_forward` | Set on Apply; hint file under plugin flash / `sysctl.d` when possible |

**Unplug:** udev `remove` clears this link’s tagged NAT rules immediately.  
**Replug same peer:** udev `add` reapply loads that peer’s Saved plan (including NAT) onto whichever `thunderboltN` they land on.  
**Different peer on the same tbnN:** does **not** inherit the previous peer’s NAT — only what is in *that* peer’s Saved plan (legacy plans without NAT keys → NAT off until you Apply once while linked).

Disabling NAT in the UI (or disabling the interface) also removes this plugin’s marked rules for that tbnN.

## vs Enable default route

| Control | Direction |
|---------|-----------|
| **Enable default route** on tbnN | Unraid may send *its own* default traffic **out** the Thunderbolt link (rare; can steal LAN/WAN) |
| **Share host uplink (NAT)** | Thunderbolt *peers* send traffic **to** Unraid, which NATs out Unraid’s uplink |

Use NAT when the **peer** should reach the internet via Unraid. Leave default route **No** unless Unraid itself should use Thunderbolt as WAN.

## Safety

- Default **off**.
- Rules carry comment tags `ThunderboltNet-NAT-thunderboltN` — only those are removed on clear.
- Does not create or join bridges.
- IPv6 NAT not included in this feature.
- OpenFabric / Fabric Routing are orthogonal; NAT does not enroll eth/br into the fabric.

## Related

- [addressing.md](addressing.md) — unique `/24` per link, `.1`/`.2` habit  
- [settings-reference.md](settings-reference.md) — field catalog  
- [troubleshooting.md](troubleshooting.md) — NAT / peer gateway checks  
- [routing-openfabric.md](routing-openfabric.md) — multi-hop (not a substitute for NAT)  
