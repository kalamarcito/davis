#!/bin/sh
# One-shot helper for the migration away from fixed container_name values.
# Safe to re-run: only removes the OLD global names if they still exist.
# Does NOT touch volumes (database, davis_www).
set -eu

for name in mysql davis nginx; do
  if podman container exists "$name" 2>/dev/null \
    || docker container inspect "$name" >/dev/null 2>&1; then
    echo "Removing leftover container: $name"
    podman rm -f "$name" 2>/dev/null || docker rm -f "$name" 2>/dev/null || true
  fi
done

echo "Done. Volumes (database, davis_www) were not touched."
echo "Next: docker-compose up -d   (from this directory)"
