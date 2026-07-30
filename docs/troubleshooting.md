# Troubleshooting

## Slow rsync / SMB despite high trained Gb/s

1. Check overview **Local iface state** — if you still see `MTU 1500 (kernel default)`, you are paying full Ethernet-sized packet rates on a multi‑20&nbsp;G path.  
2. Set **Desired MTU → 9000** on the tbn tab **and** the same MTU on the peer (Unraid cannot set the peer’s NIC).  
3. See [mtu-and-throughput.md](mtu-and-throughput.md) for PPS tables and peer commands.  
4. Also verify storage (array parity, single-disk limits) and CPU — MTU is not the only bottleneck.

---

## Reseating the cable (why it matters)

Software Apply (modules, E2E, static IP) does **not** always force a full Thunderbolt domain re-train. The fabric often keeps the previous xdomain / service state until the **physical path** drops.

**Reseating** (unplug both ends of that cable, wait a few seconds, plug back in) is valuable because it:

- Forces link training again (rate, lanes, authorization)  
- Recreates or restarts the host-to-host network service that becomes `thunderboltN`  
- Clears half-up states (“iface exists but no ping”, stuck login, one-way traffic) after driver option changes  
- Separates **cable/port** faults from **IP/config** faults — if reseat with a known-good cable still fails, look at software; if a different cable works, it was path/cable  

### Good habit after changing E2E or modules

1. Confirm the module parameter actually changed (`/sys/module/thunderbolt_net/parameters/e2e`).  
2. Reseat **one** cable (see multi-cable below).  
3. Then re-check addresses and ping.

Reseat is cheap; NHI unbind is not. Prefer reseat over “reset the controller” tricks.

---

## Multi-cable situations

| Setup | What often happens |
|-------|--------------------|
| **One** cable, two hosts | One peer path, one `thunderbolt0` (each side) — simplest to debug |
| **Two** cables to **two different** peers | Often `thunderbolt0` + `thunderbolt1` |
| **Two** cables to the **same** peer | Frequently still **one** network path (or a confused domain) — not automatic dual netdevs / 2× bandwidth |

### Highly recommended recovery steps (wedged domain / dual-cable mess)

Especially after dual-cable experiments or a half-connected second port:

1. Unplug **every** Thunderbolt / USB4 host cable from **both** machines (all ports).  
2. Wait several seconds (10+ is fine).  
3. Plug **one** known-good cable only (one port pair).  
4. Wait for a single peer + `thunderbolt0`; set IPs; ping.  
5. Only then try a second cable/peer if needed.

Leaving a second cable half-connected while “testing” often keeps the fabric in a bad state so reseating **one** cable is not enough — clear **all** first.

More topology detail: [links-and-topology.md](links-and-topology.md).

---

## No Thunderbolt hardware detected

- Board/CPU may lack a host controller, or it is disabled in BIOS.  
- Controller bound to **vfio-pci** — check PCI panel; return NHI to host driver if you want host networking.  
- Copy diagnostics from the empty-state page when filing an issue.

## Interface never appears (`thunderbolt0` missing)

- Peer not connected or not authorizing the link (security mode).  
- Cable is charge-only / not TB-capable.  
- Peer OS has no Thunderbolt networking stack (common on some Windows SKUs).  
- Modules not loaded — Driver options **Load modules = Yes**, Apply.  
- Multi-cable mess — use the recovery steps above (all cables out → one cable in).  
- Still dead after that — try another port pair and another cable before chasing software.

## Link trains but no ping

1. Confirm addresses same subnet, unique hosts ([addressing.md](addressing.md)).  
2. `ip link` shows UP, carrier 1.  
3. Peer firewall (Windows especially).  
4. Unraid E2E **No**; **reseat** once; retest ([driver-options.md](driver-options.md)).  
5. Keep default route **No** so you aren’t blackholing other traffic while testing.

## 20G · 1-lane on a capable host

- **Most likely cable or port path** — try certified 40&nbsp;Gbps short cable, other rear ports.  
- Not usually “the peer OS is capping you” when both ends are high-gen hosts.  
- Details: [standards-and-speeds.md](standards-and-speeds.md).

## Two cables, still one interface

Expected often for the **same** two hosts — see [links-and-topology.md](links-and-topology.md#dual-cable-between-the-same-pair-of-pcs).

## One-way traffic / flaky after reboot

- Re-check E2E (`cat /sys/module/thunderbolt_net/parameters/e2e`) is still off if you want e2e=0.  
- Ensure modprobe.d conf persisted under `/boot/config/modprobe.d/`.  
- Reseat cable after confirming the parameter.  
- Peer NetworkManager profiles “never default” / wrong metric.

## Do not

- Unbind Thunderbolt **NHI** to “reset” networking (can wedge until reboot).  
- Put two TB links on the **same** IPv4 prefix.  
- Expect a dock RJ45 to show up as `thunderbolt0`.  
- Debug with three variables at once (E2E + new cable + dual plug) — change one thing, then reseat.

## Peer-specific notes

[peer-scenarios.md](peer-scenarios.md)
