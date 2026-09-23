# Reusable MyMate public status page

This directory contains an optional, standalone, brand-neutral public status page for MyMate. It is a presentation layer only: MyMate remains the monitoring and administration system, while this container renders the safe aggregate status API.

The repository is public and MIT licensed. The status page is reusable by any MyMate deployment; no Z4-specific hostnames, customers, devices, or credentials are required.

## Architecture and privacy boundary

```text
MyMate application ── private status API ──> status-page Nginx proxy ──> browser
       STATUS_API_TOKEN                    MYMATE_STATUS_TOKEN
```

- The browser calls only the same-origin `/api/status` route.
- The proxy adds the private `X-Status-Api-Key` header server-side.
- No MyMate credential is written to `config.js`, HTML, JavaScript, logs, Git, or an API response.
- Public data contains only explicitly configured public sites, aggregate health, uptime, impact percentages, incident summaries, maintenance windows, and bounded history.
- Device names, management IPs, customer information, topology, credentials, and raw monitoring errors are not public.

## 1. Configure MyMate

Set a dedicated random token in the MyMate application's protected environment:

```dotenv
STATUS_API_TOKEN=replace-with-a-random-64-character-hex-token
```

Do not reuse a personal MyMate API key. Keep the value server-side and use owner-only permissions for the environment file.

In **MyMate → Settings → Network status**:

1. Enable the public status page.
2. Configure the public brand name, subtitle, colors, and polling interval.
3. Create the public sites you want to expose. Only explicitly configured sites with valid state assignments are published.
4. Assign monitored devices to sites and assign each site to a public state.
5. Keep site-name and device-count visibility disabled unless you intentionally want those aggregate fields shown.
6. Configure maintenance windows and incident notification preferences as needed.

The public API endpoint is:

```text
GET https://mymate.example.com/api/public/status
```

It requires the `X-Status-Api-Key` header and should not be exposed directly to browsers.

## 2. Configure the status container

Copy the example environment file and edit it on the status-page host:

```bash
cp status.env.example status.env
chmod 600 status.env
```

Set:

- `STATUS_BRAND_NAME` — name shown in the page header and footer.
- `STATUS_SUBTITLE` — short description below the name.
- `STATUS_POLL_MS` — browser refresh interval, normally `30000`.
- `STATUS_PUBLIC_ORIGIN` — the public HTTPS origin used by the bundled HTTP-to-HTTPS redirect, for example `https://status.example.com`.
- `MYMATE_API_URL` — the MyMate origin reachable from the status container.
- `MYMATE_STATUS_TOKEN` — exactly the same dedicated token as MyMate's `STATUS_API_TOKEN`.

Keep `status.env` outside Git. The `.gitignore` should continue to exclude local environment files.

## 3. Run with Docker Compose

From this directory:

To validate the Compose file without creating a real secret file, run from this directory:

```bash
STATUS_ENV_FILE=./status.env.example docker compose --env-file status.env.example config --quiet
```

For the actual container, use the copied protected file:

```bash
docker compose --env-file status.env up -d --build
```

The sample compose file binds the page to loopback ports:

- `127.0.0.1:8788` — HTTP status page
- `127.0.0.1:8789` — redirect listener used by the bundled configuration

For a public deployment, put HTTPS at a reverse proxy or tunnel in front of the loopback port. Preserve the same-origin `/api/status` proxy route and do not inject `MYMATE_STATUS_TOKEN` into the browser. The bundled Nginx configuration also proxies subscription verification, unsubscribe, and preference-management links.

To update a running checkout:

```bash
git pull --ff-only
docker compose --env-file status.env up -d --build
```

Do not run destructive volume cleanup commands during an update. Back up the MyMate database and the deployment configuration before production changes.

## 4. Verify the deployment

Check the page and proxy from the public origin:

```bash
curl -fsS -o /dev/null -w 'page=%{http_code}\n' https://status.example.com/
curl -fsS -o /dev/null -w 'status=%{http_code}\n' https://status.example.com/api/status
```

Expected values are `200` for both. HTTP should redirect to HTTPS if your reverse proxy is configured to do so. A direct request to MyMate's protected public-status endpoint without its header must remain unauthorized.

## Optional subscriptions

If subscriptions are enabled in MyMate, the status-page proxy supports the public subscription POST endpoint and the verification, unsubscribe, and preference-management links used in notification emails. Responses are intentionally neutral to avoid email enumeration, and subscribers must confirm by email.

## Product boundary

MyMate is the monitoring/control plane. This container is the reusable presentation layer. Branding, API origin, polling interval, public-site selection, and display settings are deployment configuration—not hard-coded customer or provider data.
