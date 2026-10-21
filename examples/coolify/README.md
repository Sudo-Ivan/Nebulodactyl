# Running the panel behind a reverse proxy

The panel works behind any proxy that forwards the standard
`X-Forwarded-*` headers: Coolify (Traefik), nginx, Caddy, Cloudflare, or a
kubernetes ingress.

## Required settings

| Setting | Value |
| --- | --- |
| `APP_URL` | The public https URL, for example `https://panel.example.com` |
| `TRUSTED_PROXIES` | `*` or a comma separated list of proxy IPs/CIDRs |
| `SESSION_SECURE_COOKIE` | `true` when the proxy terminates TLS |

`TRUSTED_PROXIES=*` trusts whatever proxy connects directly to the panel.
That is correct on platforms like Coolify where only the internal proxy
can reach the container. If the panel is directly exposed as well, list
the proxy addresses explicitly instead so clients cannot spoof
`X-Forwarded-For`.

## Coolify

1. Create a new resource from `docker-compose.yml` in this directory, or
   point Coolify at this repository.
2. Set `APP_URL` to the domain assigned to the service, `APP_KEY`, and
   `HASHIDS_SALT`.
3. Coolify handles TLS and routing automatically. The container exposes a
   `/healthz` endpoint that Coolify uses for health checks, and the image
   carries a matching `HEALTHCHECK` instruction. Set Ports Exposes to
   `8080`: that is the port nginx listens on inside the container.

## WebSockets

The console websocket goes from the browser to the node daemon directly,
not through the panel, so the node FQDN must be reachable over `wss` with
a valid certificate from the browser. An http node cannot be used from an
https panel because browsers block mixed content.

If the daemon is proxied by Traefik or Coolify too, mark the node Behind
Proxy, set the node's Daemon Port in the panel to the public port (443),
and keep the daemon's own listen port in its local config. Traefik
forwards `Upgrade` headers by default, so no extra labels are needed. The
Comet daemon sends a ping every 25 seconds, so idle consoles stay open
behind proxies with idle timeouts.

## SQLite

For small deployments you can drop the database service entirely:

```env
DB_CONNECTION=sqlite
DB_DATABASE=/app/database/database.sqlite
```

Mount a volume at `/app/database` so the file survives rebuilds, and
create the file once with `touch` inside the volume before migrating.
