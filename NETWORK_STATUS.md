# Public Network Status API

MyMate exposes an authenticated, aggregate-only status feed for an optional public status page:

```text
GET /api/public/status
Header: X-Status-Api-Key: <dedicated deployment token>
```

The standalone [`status-page/`](status-page/) container keeps that token server-side and proxies a same-origin `/api/status` request. The browser never receives the token. See [status-page/README.md](status-page/README.md) for complete setup instructions.

## Public data contract

The response contains:

- `overall` — current overall state.
- `sites` — only explicitly configured, publicly enabled sites with valid state assignments.
- `history_7d`, `history_daily`, and `history_60d` — aggregate daily history.
- `maintenance` — scheduled, active, or recently completed public maintenance windows.
- bounded incident summaries and lifecycle updates.
- `generated_at` and safe presentation configuration.

Each public site may include a stable public key, administrator-selected display name, state metadata, current `status`, `uptime_60d`, aggregate device fields when enabled, `impact_percent`, and daily history.

`impact_percent` is calculated from the currently down monitored devices divided by monitored devices, rounded to one decimal place. It is nullable when no devices are confirmed down or no monitored devices are available. It is aggregate-only and never identifies a device or customer.

## Status policy

- `operational` — all monitored devices at the site are up.
- `degraded` — a site has a mixture of up/down or up/unknown devices.
- `outage` — all monitored devices at the site are down.
- `unknown` — no monitored devices are assigned, or all assigned devices are unknown.

`uptime_60d` is `null` whenever monitoring is unknown. Unassigned devices and private sites are excluded from public rollups. Administrators control site-name and device-count visibility in **Settings → Network status**.

## Incident and maintenance privacy

The public payload intentionally excludes device names, management addresses, customer data, credentials, internal topology, exact failure details, raw monitoring errors, internal notes, and private incident bodies. Public incident activity is grouped by public site and bounded for history display.

Maintenance scopes may target all devices, one or more public sites, a device type, a map, or specific devices. Public output resolves the scope only to affected public sites; a maintenance window that affects only private sites is omitted.

A day containing an outage, degraded incident, or maintenance event is marked as an event in history. If outage and maintenance overlap on the same day, the status page displays stacked red and purple bar segments so neither event is hidden.

Incident severity and lifecycle are separate: an outage can be resolved while retaining its red Outage type badge and a green Resolved badge. Maintenance uses Scheduled, In progress, or Resolved lifecycle labels with its purple, blue, or green treatment.

## Subscriptions

When enabled, the public status page can submit subscriptions through the same-origin proxy. Subscription email addresses are encrypted at rest and looked up with an HMAC/hash value. Requests use neutral responses to avoid email enumeration, require double opt-in, and provide opaque verification, unsubscribe, and preference-management links.

## Security requirements

- Generate a dedicated random status token; do not reuse a personal MyMate API key.
- Store `STATUS_API_TOKEN` only in MyMate's protected server environment.
- Store the matching `MYMATE_STATUS_TOKEN` only in the status container's protected environment.
- Never place either token in browser JavaScript, `config.js`, HTML, Docker layers, Git, logs, or public API responses.
- Prefer same-origin proxying and HTTPS at the public edge.
- Keep status-page environment files owner-readable (`chmod 600`).
- Verify that direct requests to MyMate's protected endpoint without the header return `401`.

The API sends short-lived cache headers for aggregate output. MyMate recomputes the public rollup server-side for each request.
