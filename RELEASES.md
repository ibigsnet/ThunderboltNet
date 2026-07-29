# Thunderbolt Net releases

## Stable baselines (rollback targets)

Unraid plugin updates use **string version** (`YYYY.MM.DD…`) and normally track **`main`**.  
When a build is marked **stable**, pin a Git tag so that exact tree can be reinstalled later.

| Tag | Plugin version | Notes |
|-----|----------------|--------|
| *(none yet)* | — | Scaffold on `main` only until a host-verified baseline exists. |

### Normal updates (latest)

```bash
plugin install https://raw.githubusercontent.com/ibigsnet/ThunderboltNet/main/thunderboltnet.plg
```

Hard-refresh **Settings → Thunderbolt Net** after install. Confirm:

```bash
grep 'ENTITY version' /boot/config/plugins/thunderboltnet.plg
```

## How we mark a stable

1. Host verified on a specific plugin version.  
2. Git commit of that tree noted.  
3. Annotated tag `stable-recommended-<version>` (optional branch `release/…`).  
4. For the tag, keep FILE URLs from drifting to newer `main` if pinning is required.  
5. Row added to this file.
