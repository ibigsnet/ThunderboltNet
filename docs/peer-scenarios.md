# Peer scenarios

How Thunderbolt Net behaves with different **far-side** systems.  
Unraid side always uses Linux `thunderbolt` + `thunderbolt_net` (`thunderboltN` ifaces).

**Driver defaults on Unraid:** Load modules **Yes**, E2E **No (`e2e=0`)** unless a row below says to experiment.  
E2E is **host-wide** — see [driver-options.md](driver-options.md).

---

## Contents

- [At a glance](#at-a-glance)
- [Unraid ↔ Linux (desktop, laptop, or server)](#unraid-linux-desktop-laptop-or-server)
- [Unraid ↔ Unraid](#unraid-unraid)
- [Unraid ↔ macOS](#unraid-macos)
- [Unraid ↔ Windows](#unraid-windows)
- [Unraid ↔ Thunderbolt / USB4 dock or hub with Ethernet](#unraid-thunderbolt-usb4-dock-or-hub-with-ethernet)
- [Unraid ↔ generic USB4 hub (no NIC, no second host)](#unraid-generic-usb4-hub-no-nic-no-second-host)
- [Multiple peers on one Unraid](#multiple-peers-on-one-unraid)
- [Example address plan (copy/paste)](#example-address-plan-copypaste)
- [Related](#related)

## At a glance

| Peer | What appears on Unraid | Unraid E2E | Addressing | Notes |
|------|------------------------|------------|------------|-------|
| **Linux** (desktop/server) | `thunderboltN` + peer name in fabric | **No** first | Static /24 or /30 both ends | Best documented path for this plugin |
| **Proxmox** (Debian + FRR) | Same as Linux Thunderbolt netdev | **No** first | Static underlay + **OpenFabric** for multi-hop | Mixed fabric with Unraid — [fabric-proxmox-unraid.md](fabric-proxmox-unraid.md) |
| **Another Unraid** | Same as Linux | **No** first | Static, unique subnet per link; OpenFabric when FRR present | Same stack both sides |
| **macOS** | Often works as Thunderbolt network peer | **No** first; try **Yes** only if needed | Static or macOS Thunderbolt Bridge DHCP/self-assigned | “Thunderbolt Bridge” on Mac; behavior varies by macOS version |
| **Windows** | OEM-dependent | **No** first | Static if a Thunderbolt network adapter appears | Many PCs expose Thunderbolt for displays/docks only; networking not guaranteed |
| **Thunderbolt/USB4 dock with RJ45** | Usually **USB Ethernet**, not `thunderbolt_net` | N/A for that NIC | Configure like eth | Different device class — see below |
| **Thunderbolt hub (no host peer)** | May show devices, **no** host netdev | N/A | — | Hubs don’t create Unraid↔hub “LAN” via thunderbolt_net by themselves |

---

## Unraid ↔ Linux (desktop, laptop, or server)

Well-supported with this plugin (same kernel family both sides). Not necessarily the only or “most common” peer for Unraid users — Mac and Windows setups are covered below.

### Unraid

1. Thunderbolt tab: modules **Yes**, E2E **No**.  
2. When `thunderbolt0` exists → **tbn0**: Enable **Yes**, Static, e.g. `10.255.0.2/24`, default route **No**.  
3. Apply.

### Linux peer (example)

- Interface name may be `thunderbolt0` or similar.  
- NetworkManager: manual IPv4 `10.255.0.1/24`, never-default / ignore automatically obtained routes if you use the main Wi‑Fi/Ethernet for internet.  
- Or: `ip link set thunderbolt0 up` and `ip addr add 10.255.0.1/24 dev thunderbolt0`.

### Tips

- Match **prefix** both ends (`/24` with `/24`, not one `/24` and one `/30` unless you understand the overlap).  
- Peer **name** in the Unraid table is often the Linux hostname; **stack** may show `Linux` or a vendor string — not always the PC brand.  
- If traffic is flaky with E2E **Yes**, return to **No**, confirm sysfs, then **reseat** the cable (all extra Thunderbolt cables unplugged first if you had more than one).

---

## Unraid ↔ Unraid

Treat as Linux ↔ Linux.

- Give each direction a clear static plan (e.g. host A `10.255.0.1/24`, host B `10.255.0.2/24`).  
- Only one side needs “include listening” if you want SMB on the Thunderbolt link.  
- Dual cables between the **same** two hosts often still yield **one** peer path — see [links-and-topology.md](links-and-topology.md).

---

## Unraid ↔ macOS

Apple’s product name is typically **Thunderbolt Bridge** (network over Thunderbolt).

### What to expect

- A trained host path can create a `thunderboltN` on Unraid with the Mac’s advertised name.  
- On the Mac: System Settings → Network → Thunderbolt Bridge (wording varies by macOS version).  
- Configure IPv4 **Manually** to match Unraid’s subnet (e.g. Mac `10.255.0.1`, Unraid `10.255.0.2`, mask `255.255.255.0`), or use a small manual plan both sides agree on.

### E2E

| Step | Action |
|------|--------|
| 1 | Unraid **E2E = No**, test ping and large transfers |
| 2 | If the link trains but IP is unreliable, reconnect, confirm Mac firewall, re-check addresses |
| 3 | Only then try Unraid **E2E = Yes**, Apply, reload/reboot if needed, retest |

macOS versions and Apple silicon vs Intel differ; treat E2E as an experiment, not a fixed rule.

### Tips

- Prefer a **Thunderbolt 4/USB4 40&nbsp;Gbps** cable; Macs are picky about passive cable length/class.  
- Don’t confuse Thunderbolt Bridge with a dock’s **Ethernet** port (that’s a separate NIC on the Mac).  
- Keep **Enable default route = No** on Unraid so macOS Wi‑Fi remains the Mac’s internet path and Unraid keeps eth0/br0 for WAN.

---

## Unraid ↔ Windows

### Reality check

Windows Thunderbolt **networking** is **not** as uniform as Ethernet:

- Some OEM stacks expose a Thunderbolt network adapter when another computer is connected.  
- Others only support docks, storage, or displays — **no** host-to-host IP.  
- Drivers and “Thunderbolt Control Center” / OEM utilities matter.

### If a Thunderbolt network adapter appears on Windows

1. Unraid: E2E **No**, static IP on tbnN.  
2. Windows: set a static IP on that adapter in the same subnet.  
3. Allow ICMPv4 in Windows Firewall for private networks if ping is blocked.  
4. If unstable, try Unraid E2E **Yes** once; if still dead, the Windows side may not support this mode.

### If no Thunderbolt network adapter appears

Thunderbolt Net on Unraid cannot force Windows to implement host networking. Use:

- 10G Ethernet, or  
- A dock/NIC both machines share on a normal LAN, or  
- SMB/NFS over the existing Ethernet/Wi‑Fi path.

---

## Unraid ↔ Thunderbolt / USB4 dock or hub with Ethernet

This is the most common confusion.

### Dock RJ45 / USB Ethernet

| Path | Unraid device | Configure with |
|------|---------------|----------------|
| Cable Unraid → **dock Ethernet** | Often `ethN` / USB NIC | Unraid **Network Settings** eth tab (not tbn) |
| Cable Unraid → **peer PC** over Thunderbolt | `thunderboltN` | Thunderbolt Net **tbnN** tab |

A hub “with network ports” usually means **the hub contains a USB Ethernet chip**. That is **not** the same as `thunderbolt_net` host-to-host tunneling.

### When the dock is between two computers

Topologies vary. You may get:

- USB Ethernet on one host only, or  
- Daisy-chain effects, or  
- Still a single host-to-host Thunderbolt path if both are hosts.

Do not assume the dock is an L2 switch for ThunderboltIP. Prefer a **direct host↔host cable** for Thunderbolt Net, and use the dock’s RJ45 as ordinary Ethernet when you need switch-like LAN.

### Unraid plugged only into a dock (no second computer)

You may see Thunderbolt devices (storage, GPU enclosure, USB controllers) under **Fabric devices**, but **no** `thunderboltN` network interface until another **host** with networking service appears. That is expected.

---

## Unraid ↔ generic USB4 hub (no NIC, no second host)

- Fabric may list the hub.  
- **No** Thunderbolt network iface for “hub LAN.”  
- Downstream USB Ethernet adapters appear as normal USB NICs if present.

---

## Multiple peers on one Unraid

Example: tbn0 → Linux workstation, tbn1 → laptop (when the kernel exposes two netdevs).

| Setting | Guidance |
|---------|----------|
| E2E | Still **one** host value — keep **No** |
| IPv4 | **Different subnet per peer path** (`10.255.0.0/24`, `10.255.1.0/24`) |
| Default route | **No** on all Thunderbolt links |
| Bonding | Only if you truly have two netdevs to the **same** peer path design — rare |
| Known peers | **One row per remote UUID**; **Saved** follows that host if path renumbers |
| tbn1 inherit tbn0? | **No** — each peer needs its own plan / Apply |

Cable **order** can change which remote is `thunderbolt0` vs `thunderbolt1`. Use **Saved** (Apply while each is linked) so L3 sticks to the host, not the slot name. See [peers-and-plans.md](peers-and-plans.md).

---

## Example address plan (copy/paste)

| Link | Unraid | Peer |
|------|--------|------|
| tbn0 ↔ Linux workstation | `10.255.0.2/24` | `10.255.0.1/24` |
| tbn1 ↔ Mac laptop | `10.255.1.2/24` | `10.255.1.1/24` |

Ping: `ping 10.255.0.1` from Unraid, etc.

More on masks: [addressing.md](addressing.md).

---

## Related

- [driver-options.md](driver-options.md)  
- [links-and-topology.md](links-and-topology.md)  
- [troubleshooting.md](troubleshooting.md)  
