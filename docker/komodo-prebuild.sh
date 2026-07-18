#!/bin/sh
# Restore offline vendor/ before a Komodo/docker-compose build of Davis.
# Safe to run multiple times. Used by the host docker-compose wrapper on the VM
# and can be invoked manually:
#   sh docker/komodo-prebuild.sh
#   sh docker/komodo-prebuild.sh /etc/komodo/stacks/davis
set -eu

STACK="${1:-}"
if [ -z "$STACK" ]; then
  # Resolve repo root from this script (…/docker/komodo-prebuild.sh → …/)
  SCRIPT_DIR=$(CDPATH= cd -- "$(dirname "$0")" && pwd -P)
  STACK=$(CDPATH= cd -- "$SCRIPT_DIR/.." && pwd -P)
fi

if [ ! -f "$STACK/composer.json" ] || [ ! -f "$STACK/docker/Dockerfile" ]; then
  echo "komodo-prebuild: not a Davis tree: $STACK" >&2
  exit 1
fi

if [ -f "$STACK/vendor/autoload.php" ]; then
  echo "komodo-prebuild: vendor/ already present under $STACK"
  exit 0
fi

CACHE_DIR="${DAVIS_BUILD_CACHE:-/root/davis-build-cache}"
TAR="$CACHE_DIR/vendor.tar"
RESTORE="$CACHE_DIR/restore-vendor.sh"
LOCKSHA="$CACHE_DIR/vendor.lock.sha256"

# Auto-invalidate: never restore a vendor cache built for a different
# composer.lock (that would silently ship stale dependencies — e.g. an old
# sabre/dav fork). Refuse loudly and point at the refresh script instead.
if [ -f "$LOCKSHA" ] && [ -f "$STACK/composer.lock" ] && command -v sha256sum >/dev/null 2>&1; then
  cur=$(sha256sum "$STACK/composer.lock" | awk '{print $1}')
  want=$(cat "$LOCKSHA")
  if [ "$cur" != "$want" ]; then
    echo "komodo-prebuild: ERROR — vendor cache is STALE" >&2
    echo "  composer.lock changed since the cache was built:" >&2
    echo "    cache built for lock : $want" >&2
    echo "    current composer.lock: $cur" >&2
    echo "  Refresh the cache before building (on a host that can reach the deps):" >&2
    echo "    # regenerate \$STACK/vendor for the new lock, then:" >&2
    echo "    sh $CACHE_DIR/refresh-vendor-cache.sh $STACK" >&2
    exit 1
  fi
  echo "komodo-prebuild: vendor cache matches composer.lock ($cur)"
fi

if [ -x "$RESTORE" ]; then
  exec "$RESTORE" "$STACK"
fi

if [ -f "$TAR" ]; then
  echo "komodo-prebuild: extracting $TAR → $STACK/vendor"
  tar -C "$STACK" -xf "$TAR"
  test -f "$STACK/vendor/autoload.php"
  echo "komodo-prebuild: OK"
  exit 0
fi

echo "komodo-prebuild: ERROR — no vendor/ and no cache at $TAR" >&2
echo "Create once (from a machine with working Packagist egress):" >&2
echo "  cd $STACK && composer install --no-dev --optimize-autoloader" >&2
echo "  mkdir -p $CACHE_DIR && tar -C $STACK -cf $TAR vendor" >&2
echo "  sha256sum $STACK/composer.lock | awk '{print \$1}' > $CACHE_DIR/vendor.lock.sha256" >&2
exit 1
