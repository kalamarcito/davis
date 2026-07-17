#!/bin/sh
set -e

# Re-seed the shared code volume on every start.
#
# The compose stack shares the application code between nginx (serves public/
# and proxies PHP over FastCGI) and this php-fpm container through the named
# volume `davis_www` mounted at /var/www/davis. A named volume only copies the
# image contents the *first* time it is empty; on every later deploy it keeps
# serving the old code, so a rebuilt image would never reach the running
# container. The pristine code is baked at build time into /opt/davis (a path
# the volume does not shadow); we mirror it into the volume here so that each
# rebuilt image is actually served.
if [ -d /opt/davis ]; then
    cp -a /opt/davis/. /var/www/davis/
fi

# var/ (cache + logs) is written at runtime and must be owned by the fpm user.
mkdir -p /var/www/davis/var
chown -R "${FPM_USER}" /var/www/davis/var

# Drop root and hand off to the base php image entrypoint (which execs "$@").
exec su-exec "${FPM_USER}" docker-php-entrypoint "$@"
