#!/bin/sh
set -eu
: "${STATUS_BRAND_NAME:=Network Status}"
: "${STATUS_SUBTITLE:=Live service health}"
: "${STATUS_POLL_MS:=30000}"
: "${STATUS_PUBLIC_ORIGIN:=https://status.example.com}"
: "${MYMATE_API_URL:=http://127.0.0.1:8000}"
: "${MYMATE_STATUS_TOKEN:?MYMATE_STATUS_TOKEN is required}"
envsubst '${STATUS_BRAND_NAME} ${STATUS_SUBTITLE} ${STATUS_POLL_MS}' \
  < /usr/share/nginx/html/config.js.template \
  > /usr/share/nginx/html/config.js
envsubst '${MYMATE_API_URL} ${MYMATE_STATUS_TOKEN} ${STATUS_PUBLIC_ORIGIN}' \
  < /etc/nginx/conf.d/default.conf.template \
  > /etc/nginx/conf.d/default.conf
exec "$@"
