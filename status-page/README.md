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

## Admin-managed configuration

Administrators can manage the safe public presentation settings under **MyMate → Settings → Network status**. These include the display name, subtitle, polling interval, public enable/disable switch, aggregate display options, and subscription eligibility.

The private connection token is intentionally not managed as a readable UI field. For Docker deployments, enter it only in a protected host environment file:

```dotenv
STATUS_API_TOKEN=replace-with-a-random-64-character-hex-token
MYMATE_STATUS_TOKEN=replace-with-the-same-token
```

Use owner-only permissions (`chmod 600`) and load the file through Compose `env_file`. Never place either variable in browser configuration, `config.js`, HTML, Git, or a public API response. The status proxy reports only safe configuration values through the authenticated aggregate response.

For a separate HTTPS origin, add the exact status-page origin to MyMate's protected configuration:

```dotenv
CORS_ALLOWED_ORIGINS=https://status.example.com
```

Prefer a same-origin reverse-proxy path when practical. On a Synology deployment, point `https://status.example.com` at the private host port `8788`, enable HTTPS, and keep the container bound to the LAN host only.

## Product boundary

MyMate remains the monitoring/control plane. This container is the reusable presentation layer. Branding, API origin, polling interval, and display text are deployment configuration, not Z4-specific source code.
