#!/bin/sh
# Pick the right nginx config template based on PROXY_SSL_ENABLED.
# Runs before nginx's 20-envsubst-on-templates.sh so the final
# /etc/nginx/templates/default.conf.template contains the chosen variant.
set -eu

SOURCE_DIR=/etc/nginx/conf-available
TARGET=/etc/nginx/templates/default.conf.template

# The base nginx:alpine image doesn't ship /etc/nginx/templates/, but its
# upstream entrypoint envsubsts files from that directory into /etc/nginx/conf.d.
mkdir -p "$(dirname "$TARGET")"

if [ "${PROXY_SSL_ENABLED:-0}" = "1" ]; then
    cp "${SOURCE_DIR}/ssl.conf.template" "${TARGET}"
else
    cp "${SOURCE_DIR}/http.conf.template" "${TARGET}"
fi
