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
exit 1
