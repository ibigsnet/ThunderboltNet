# USB4STREAM and ThunderboltNet

USB4STREAM is a **Linux kernel** feature (merged for **~7.2**) for **raw host↔host data** over a USB4 / Thunderbolt cable **without** the IP stack. It is **not** InfiniBand and **not** a replacement for Unraid’s normal Ethernet/`thunderboltN` networking.

ThunderboltNet **detects and can load** the stream module when present. Full “one-click bulk copy” tooling is phased — see roadmap below.

---

## Three different things people confuse

| Path | Kernel / software | What you get | Plugin role |
|------|-------------------|--------------|-------------|
| **USB4NET** (`thunderbolt_net`) | Long stable | `thunderbolt0`… IP NIC (tbn tabs) | **Primary** — already supported |
| **USB4STREAM** (`thunderbolt_stream`) | Linux **~7.2+** | Raw stream, often `/dev/tbstream*` | **Detect + optional load** now; apply helpers later |
| **IB verbs over TB** | Research / out-of-tree | Fake InfiniBand for RDMA/AI | **Out of scope** for this plugin |

Reference write-ups: [Phoronix USB4STREAM in 7.2](https://www.phoronix.com/news/USB4STREAM-In-Linux-7.2), kernel admin-guide Thunderbolt section, experimental [thunderbolt-ibverbs](https://blog.hellas.ai/blog/thunderbolt-ibverbs/) (not shipped here).

---

## Will it help Holo ↔ Unraid bulk copies?

| | |
|--|--|
| **Same cable class** | Yes — same USB4/TB ports you already use |
| **Faster than TB-net + TCP?** | Possibly (less stack); still limited by **trained lanes/speed** (e.g. 20G×1) |
| **Works on Unraid 6.12 / 7.x with 6.1x kernels?** | **No** until the **Unraid kernel** includes `thunderbolt_stream` |
| **Works if only Holo has 7.2+?** | **No** — both ends need a compatible stream path |

Until both peers have the module, keep using **tbn + IP** (NBD, rsync, NFS, etc.).

---

## What the plugin does today

1. **Status** — reports whether the module is **in the kernel**, **loaded**, and any `/dev/tbstream*` devices.  
2. **Config** — `enable_usb4stream=yes|no` (default **no**).  
3. **Apply** — if Yes **and** `modinfo` finds the module, runs `modprobe thunderbolt_stream` (and hyphenated alias). Missing module = **no error** (net path unchanged).  
4. **Diagnostics** — includes USB4STREAM lines in the copy/paste blob.  
5. **Dashboard ports** — still only for **netdevs** (`thunderboltN`); stream devices are **not** Ethernet and do not appear in dynamix Interface graphs.

---

## ConfigFS sketch (when both kernels support it)

Upstream shape (names may evolve — check your kernel docs):

```bash
modprobe thunderbolt-stream   # or thunderbolt_stream
# peer path trained (e.g. 0-1) …
mkdir -p /sys/kernel/config/thunderbolt/stream/0-1.0/data
# hopids / enable per docs — then use /dev/tbstream*
```

Plugin **does not** yet auto-create configfs streams or replace NBD/rsync. That is a later milestone.

---

## Roadmap (plugin)

| Phase | Feature |
|-------|---------|
| **Now** | Detect · optional modprobe · UI status · docs |
| **Next** | Per-peer “stream ready” badge; CLI `tbn-status --json` stream block; helper script to create/tear down a stream for a known xdomain id |
| **Later** | Optional high-speed copy helper (e.g. `tbn-stream-copy`) when `/dev/tbstream*` exists; never break tbn IP |
| **Not planned** | Shipping experimental IB-verbs modules; replacing Unraid eth/bond UI |

---

## Operator tips

- Leave **Enable USB4STREAM = No** on production Unraid until the **running kernel** shows the module under status.  
- Do **not** unload `thunderbolt_net` to “try stream only” unless you accept losing IP/SMB/SSH on TB.  
- Dual-lane / 40G training is still a **fabric** issue; stream does not fix single-lane 20G cables.
