# USB4STREAM and ThunderboltNet

USB4STREAM is a **Linux kernel** path (`thunderbolt_stream`) for **raw host↔host data** over a USB4 / Thunderbolt cable **without** the IP stack. It is **not** InfiniBand and **not** a replacement for tbn IP (`thunderbolt_net`).

Thunderbolt Net **creates, persists, and copies** streams through ConfigFS when the running kernel has the module.

---

## Contents

- [Kernel vs Unraid version](#kernel-vs-unraid-version)
- [What the plugin does](#what-the-plugin-does)
- [Stream tab](#stream-tab)
- [Both ends](#both-ends)
- [Copy](#copy)
- [CLI](#cli)
- [What it is not](#what-it-is-not)
- [Related](#related)

---

## Kernel vs Unraid version

| Version you might see | Example | Related to USB4STREAM? |
|-----------------------|---------|-------------------------|
| **Unraid product** | Unraid **7.3.2** | **No.** Bumping Unraid does not by itself enable stream. |
| **Linux kernel** (`uname -r`) | **6.18.38**-Unraid | **Yes.** `modinfo thunderbolt_stream` must succeed on *this* kernel. |
| **Mainline when the feature landed** | kernel.org **Linux 7.2** | Historical: when upstream merged USB4STREAM. |

```bash
uname -r
modinfo thunderbolt_stream
```

If `modinfo` errors “not found”, this Unraid build does not have USB4STREAM yet. Stream tab settings stay; Create reports that until Lime ships the module (or a lab kernel includes it).

---

## What the plugin does

| Piece | Behavior |
|-------|----------|
| **Settings → Enable USB4STREAM** | On Apply, `modprobe thunderbolt_stream` if present, then recreate **saved** streams |
| **Stream tab** | Create / tear down ConfigFS groups; list `/dev/tbstreamN`; copy |
| **Flash** | `/boot/config/plugins/ThunderboltNet/streams.json` |
| **Boot / hotplug** | `tbn-stream-reapply` after array start and Thunderbolt device add |
| **tbn IP** | Unchanged. Do not unload `thunderbolt_net` to “try stream only” unless you accept losing SMB/SSH on Thunderbolt |

ConfigFS shape (upstream):

```text
/sys/kernel/config/thunderbolt/stream/<domain-route.index>/<name>/
  in_hopid  out_hopid  index  ring_size  busy_poll
```

`<domain-route.index>` is a Thunderbolt **service** id (e.g. `0-1.0`), not `tbn0`. Stream **name** is 1–8 characters (letter, then `A–Z a–z 0–9 _ -`). HopID **−1** = automatic allocation.

Character device: `/dev/tbstreamN` from the `index` attribute.

---

## Stream tab

**Network Settings → Thunderbolt → Stream**

1. Set **Enable USB4STREAM = Yes** on Settings (so boot recreates saved streams).
2. On the **receive** host: path + name (default `data`) → **Create stream**.
3. On the **send** host: same name (path is that host’s service id) → **Create stream**.
4. Start **Receive** copy on the receiver, then **Send** on the sender.
5. **Tear down selected** when finished (`rmdir` the ConfigFS groups).

If the module is missing, Create fails with a clear error. The fields stay.

---

## Both ends

Both kernels must ship `thunderbolt_stream`. One Unraid with the module and one without cannot form a stream.

Receive side first so data is not dropped. Closing the device sends a CLOSE packet (EOF on the reader).

USB4STREAM rings use kernel end-to-end credits on the **stream** path. That is separate from Thunderbolt Net’s **E2E flow control** setting on `thunderbolt_net`.

---

## Copy

| Mode | What |
|------|------|
| **Receive** | `dd if=/dev/tbstreamN of=<file> bs=256k` |
| **Send** | `dd if=<file> of=/dev/tbstreamN bs=256k` |
| **File** | Absolute path under `/mnt` or `/tmp` |
| **Raw** | File is a block device (`/dev/nvme0n1`, `/dev/sda`, …). Destructive. Confirm in the UI. |

Whole-disk imaging over **IP** remains **NBD Export**. Raw stream copy is the USB4STREAM equivalent of Intel’s `dd` examples.

---

## CLI

```bash
/usr/local/emhttp/plugins/ThunderboltNet/scripts/tbn-stream status
/usr/local/emhttp/plugins/ThunderboltNet/scripts/tbn-stream create 0-1.0 data
/usr/local/emhttp/plugins/ThunderboltNet/scripts/tbn-stream destroy 0-1.0 data
/usr/local/emhttp/plugins/ThunderboltNet/scripts/tbn-stream apply
/usr/local/emhttp/plugins/ThunderboltNet/scripts/tbn-stream copy receive /dev/tbstream0 /mnt/user/backup/peer.bin
```

JSON status is also in `tbn-status` / `get-status.php` under `usb4stream`.

---

## What it is not

- Not faster sticker bandwidth — still limited by trained lanes.
- Not OpenFabric / FRR.
- Not InfiniBand / ibverbs.
- Not a substitute for tbn static IP, SMB, or NBD on kernels without the module.

---

## Related

- [driver-options.md](driver-options.md) — Enable USB4STREAM
- [settings-reference.md](settings-reference.md)
- [standards-and-speeds.md](standards-and-speeds.md)
- Upstream: kernel admin-guide Thunderbolt / USB4STREAM; Phoronix USB4STREAM in Linux 7.2
