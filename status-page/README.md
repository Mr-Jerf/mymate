# Reusable MyMate status page

This is a standalone, brand-neutral static status website. It reads the aggregate MyMate status contract through its same-origin `/api/status` proxy. The MyMate token is held only by the status container and is never sent to browser JavaScript.

## Configure

Set these environment variables in `docker-compose.yml` or an external protected environment file:

- `STATUS_BRAND_NAME` — business name shown in the header/footer.
- `STATUS_SUBTITLE` — short header description.
- `MYMATE_API_URL` — MyMate origin used by the server-side Nginx proxy.
- `MYMATE_STATUS_TOKEN` — private deployment-specific token; store it in a protected environment file and never commit it.
- `STATUS_POLL_MS` — browser refresh interval, normally `30000`.

The aggregate MyMate endpoint requires the deployment-specific status token. The browser only calls the same-origin proxy and never receives that token.

## Run locally

```bash
docker compose up --build
```

Open `http://127.0.0.1:8788`.

## MyMate setup

For a separate HTTPS origin, add the exact status-page origin to MyMate's protected configuration:

```dotenv
CORS_ALLOWED_ORIGINS=https://status.example.com
```

Prefer a same-origin reverse-proxy path when practical. On a Synology deployment, point `https://status.example.com` at the private host port `8788`, enable HTTPS, and keep the container bound to the LAN host only.

## Product boundary

MyMate remains the monitoring/control plane. This container is the reusable presentation layer. Branding, API origin, polling interval, and display text are deployment configuration, not Z4-specific source code.
