# Public Network Status API

My Mate exposes a public, aggregate-only status feed for the website status page:

```text
GET /api/public/status
```

The endpoint requires the deployment-specific `X-Status-Api-Key` header. The standalone status container keeps that token server-side and proxies a same-origin `/api/status` request to MyMate. The browser never receives the token.

## Browser integration

For a standalone status container, the browser calls its same-origin proxy:

```js
const response = await fetch('/api/status', { headers: { Accept: 'application/json' } });
```

The proxy adds `X-Status-Api-Key` privately before calling MyMate. Do not put the token in browser JavaScript. If a direct integration is required, send the exact status origin through the protected server-side proxy rather than exposing a credential to the public page.

The response includes `overall`, only the explicitly created and state-assigned MyMate sites, and `generated_at`. Each site has a public `key`, display `name`, state metadata, `status`, `uptime_60d`, `monitored_devices`, `down_devices`, `unknown_devices`, and seven-day history. Devices are associated through the MyMate hierarchy `device → site → state`; unassigned devices and sites are omitted. The `status_feed` list contains one incident thread per site, plus resolved incidents retained for up to 30 days.

## Status policy

- `operational`: all monitored devices at the site are up
- `degraded`: a site has a mixture of up/down or up/unknown devices
- `outage`: all monitored devices at the site are down
- `unknown`: no monitored devices are assigned, or all assigned devices are unknown

`uptime_60d` is `null` whenever monitoring is unknown. Unassigned sites are excluded from state rollups until an administrator assigns them in **Settings → Network status**.

The response intentionally contains only administrator-selected public site names plus aggregate status fields. It does not contain device names, management addresses, customer data, credentials, or topology. The API sends `Cache-Control: public, max-age=30, s-maxage=30`; My Mate recomputes the aggregate server-side for each request.
