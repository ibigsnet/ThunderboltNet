#!/usr/bin/env bash
# Build ThunderboltNet-VERSION-x86_64-1.txz (runtime only; docs stay on GitHub).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
VERSION="${1:-$(sed -n 's/.*ENTITY version "\([^"]*\)".*/\1/p' thunderboltnet.plg | head -1)}"
PKG="ThunderboltNet-${VERSION}-x86_64-1"
STAGE=$(mktemp -d); trap 'rm -rf "$STAGE"' EXIT
DEST="$STAGE/usr/local/emhttp/plugins/ThunderboltNet"
mkdir -p "$DEST"/{include,scripts,event,udev}
cp -a "$ROOT/ThunderboltNet.page" "$ROOT/default.cfg" "$ROOT/default-iface.cfg" \
  "$ROOT/README.md" "$ROOT/thunderboltnet.css" "$ROOT/thunderboltnet.js" \
  "$ROOT/ThunderboltNet-RECOVERY.txt" "$DEST/"
cp -a "$ROOT"/include/* "$DEST/include/"
# only runtime scripts
for s in tbn-status tbn-dashboard-ports tbn-openfabric tbn-net-reapply; do
  [ -f "$ROOT/scripts/$s" ] && cp -a "$ROOT/scripts/$s" "$DEST/scripts/"
done
cp -a "$ROOT"/event/* "$DEST/event/" 2>/dev/null || true
[ -d "$ROOT/udev" ] && cp -a "$ROOT"/udev/* "$DEST/udev/" 2>/dev/null || true
rm -f "$DEST/scripts/pack-txz.sh" 2>/dev/null || true
chmod 755 "$DEST/scripts/"* "$DEST/event/"* 2>/dev/null || true
mkdir -p "$ROOT/archive"
OUT="$ROOT/archive/${PKG}.txz"
rm -f "$OUT"
( cd "$STAGE" && tar --owner=0 --group=0 --numeric-owner -cJf "$OUT" . )
ls -la "$OUT"
echo "files: $(tar -tJf "$OUT" | wc -l)"
