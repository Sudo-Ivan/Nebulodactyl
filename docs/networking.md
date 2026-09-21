# Networking

Nebulodactyl separates browser-facing traffic from panel-to-node traffic.
The two do not have to use the same network, and nodes do not need public
addresses.

## Traffic overview

| Traffic | Ports | Notes |
| --- | --- | --- |
| Browser to panel | 80/443 TCP | Behind your reverse proxy or direct |
| Panel to node daemon | 8080 TCP | Game server control, console WebSocket |
| Client SFTP | 2022 TCP | Users' file transfers, terminates on the node |
| Nebula overlay | 4242 UDP | Only when NEBULA_ENABLED is set |
| nebulod status API | 9770 TCP | Localhost or overlay only |

## Public deployment

The standard setup. Give the panel a domain, put a reverse proxy in front,
and point node FQDNs at public IPs.

1. Set `APP_URL` to the public URL users open, for example
   `https://panel.example.com`.
2. Terminate TLS at the proxy or let the container's built-in Let's Encrypt
   flow handle it by setting `LE_EMAIL` in `.env`.
3. Nodes need reachable `fqdn` addresses on their daemon port.

## Reverse proxy

The panel ships a `/healthz` endpoint for load balancer and uptime checks
and honors `TRUSTED_PROXIES` for real client IPs and HTTPS detection.

```dotenv
APP_URL=https://panel.example.com
TRUSTED_PROXIES=10.0.0.2        # your proxy IP, or a comma separated CIDR list
SESSION_SECURE_COOKIE=true
```

Use `TRUSTED_PROXIES=*` only when the panel port cannot be reached except
through the proxy. A ready-made Compose stack with Traefik labels lives in
`examples/coolify/`.

Whatever proxy you use must forward WebSocket upgrades. The server console
and live stats are WebSocket connections to the node daemon.

## Private networks and VPNs

Nodes on a LAN, WireGuard, Tailscale, or any other private network work
without the overlay: set the node's `internal_fqdn` (or `fqdn`) to its
private address and the panel will connect to it directly. The address users
see for SFTP can differ from the address the panel uses for daemon traffic.

In this layout you typically want:

- The panel bound to localhost or a private interface, reached through a
  reverse proxy.
- Node daemon ports firewalled to the panel host only.
- `APP_URL` still set to the URL users type in their browser, even if that
  is an internal name.

## Nebula overlay

For fleets spread across sites, NATs, or providers, enable the built-in
Nebula support. The panel runs a certificate authority and signs a host
certificate for each node. Nodes enroll through the existing daemon token
authentication, so no extra credentials are needed.

Panel side:

```bash
php artisan nebula:init-ca
```

```dotenv
NEBULA_ENABLED=true
NEBULA_OVERLAY_CIDR=10.42.0.0/16
NEBULA_LIGHTHOUSES=10.42.0.1
NEBULA_STATIC_HOSTS=10.42.0.1:203.0.113.10:4242
NEBULA_PREFER_OVERLAY=true
```

Node side, run `nebulod` from `agent/` with the node's daemon token. The
agent enrolls, writes `/etc/nebula` (or the configured path), and supervises
the local nebula process. See `agent/README.md` and
`examples/nebula/config.example.yml`.

With `NEBULA_PREFER_OVERLAY=true`, `Node::getInternalFqdn()` returns the
node's enrolled overlay IP and daemon traffic moves to the tunnel. Node
daemon ports can then be closed to the public internet entirely. The panel
falls back to `internal_fqdn` and `fqdn` when a node is not enrolled.

Nebula encrypts and authenticates the tunnel. It does not replace daemon
token authentication, and the usual guidance applies: keep certificates and
the CA key out of the image and out of version control.

## Screenshots

Regenerate the images under `showcase/` with:

```bash
tools/screenshots/screenshot.sh
```

The tool builds a throwaway sqlite database, seeds a demo fleet, and
captures the login, dashboard, and console views with headless Chromium.
