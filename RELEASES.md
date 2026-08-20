# Releases and install channels

## Channels (2026-08-20)

| Branch | Who | Install URL |
|--------|-----|-------------|
| **`main`** | **CA / production** | `https://raw.githubusercontent.com/ibigsnet/ThunderboltNet/main/thunderboltnet.plg` |
| **`testing`** | Lab (NIROG) / WIP | `https://raw.githubusercontent.com/ibigsnet/ThunderboltNet/testing/thunderboltnet.plg` |

~~`stable`~~ was removed. Community Applications scrapes slowly; everyone effectively tracked **main**, so **main is the release tip** and **testing** is where we push often before promote.

**CA catalog:** [unraid-templates](https://github.com/ibigsnet/unraid-templates) — `PluginURL` points at **main**.

## Promote

1. Soak on **testing** (NIROG).
2. When asked: merge **testing → main** and pin `.plg` `pluginURL` / `raw` / `readme` to **main** (never leave `/testing/` in those fields on main).
3. Matching `archive/ThunderboltNet-<ver>-x86_64-1.txz` must be on the branch.

Lab install from testing keeps **Update** on the WIP loop (flash follows `pluginURL`).

## Tags

Pinned tags (`vYYYY.MM.DDxx`) remain installable by raw URL when we cut them.
