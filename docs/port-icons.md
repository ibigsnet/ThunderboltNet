# Port icons and silkscreen (do I have Thunderbolt?)

Use the marks **printed next to the physical ports** on the motherboard rear I/O or laptop side. The plugin’s **Identify ports on your case** table (Thunderbolt tab) shows the same ideas with simple icons — it appears whether or not Linux found a controller (so machines **with** and **without** TB both see it).

This is a **field guide**, not legal trademark artwork. Thunderbolt is a trademark of Intel; SuperSpeed-style “SS” marks follow USB‑IF conventions.

---

## Quick rule

| You see… | Host-to-host Thunderbolt / USB4 networking? |
|----------|-----------------------------------------------|
| Lightning-style mark and/or **40** next to Type‑C | **Yes** — TB4 / USB4 class (use these for this plugin) |
| Lightning alone (older TB3/TB4 silkscreen) | **Usually yes** — confirm in the board manual |
| **SS²⁰** only | **No** — USB SuperSpeed 20 Gb/s data only |
| **SS¹⁰** only (Type‑C or red USB‑A) | **No** — USB SuperSpeed 10 Gb/s |
| **SS⁵** or plain USB | **No** |
| DisplayPort **D** alone on Type‑C | **Not enough** — DP Alt Mode ≠ Thunderbolt by itself |

If your only high-speed Type‑C ports are **SS¹⁰** / **SS²⁰** with **no** lightning / **40**, you almost certainly do **not** have a Thunderbolt/USB4 host controller for `thunderbolt_net`. Ordinary USB will not create `thunderbolt0`.

---

## Common marks

### Thunderbolt / USB4 · 40 Gb/s class

- Lightning bolt (Intel Thunderbolt-family style mark)
- Often a printed **40** (40 Gb/s class)
- USB‑C receptacle
- Sometimes a small DisplayPort **D** as well (video over the same port)

These are the ports this plugin is about when Linux exposes the host router.

### USB SuperSpeed only

| Silkscreen | Typical class | Rate (bits) |
|------------|---------------|-------------|
| SS<sup>20</sup> | USB 3.2 Gen 2×2 | 20 Gb/s |
| SS<sup>10</sup> | USB 3.2 Gen 2 | 10 Gb/s |
| SS<sup>5</sup> | USB 3.x Gen 1 | 5 Gb/s |

These are excellent for disks and hubs. They are **not** the same as dual-lane ~40 Gb/s Thunderbolt host networking.

### DisplayPort “D”

Means the Type‑C port can do DisplayPort Alt Mode (monitors). It does **not** by itself prove Thunderbolt. Look for lightning / **40** next to the same port.

---

## Examples

**NIROG-style board (ROG with ⚡40 + SS²⁰ + SS¹⁰):**  
The **40** + lightning Type‑C ports are TB/USB4. SS²⁰ / SS¹⁰ are USB SuperSpeed banks. Ethernet 10G/2.5G is separate.

**PLUSH-style host (no TB controller):**  
You may only see SS¹⁰ / USB‑A / Ethernet. The plugin empty state is expected; use the icon table to confirm silkscreen matches “USB only.”

---

## Software still has to agree

Silkscreen is the **hardware intent**. Linux must still see:

- Thunderbolt NHI / USB4 host (`lspci`, sysfs `0-0`)
- Modules `thunderbolt` + `thunderbolt_net` when you want networking

BIOS can disable Thunderbolt or set security modes that block devices. See [requirements.md](requirements.md) and [troubleshooting.md](troubleshooting.md).

---

## Related

- [standards-and-speeds.md](standards-and-speeds.md) — generations, Gb/s, cables  
- [requirements.md](requirements.md) — hardware checklist  
