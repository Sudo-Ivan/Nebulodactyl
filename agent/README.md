# nebulod

The Nebulodactyl node agent. It enrolls a host onto the panel managed
Nebula overlay network, keeps the host certificate fresh, supervises the
nebula daemon, and exposes a small status API inside the mesh.

## What it does

1. Generates (or loads) an X25519 keypair in the work directory.
2. Posts the public key to `POST /api/remote/nebula/enroll` on the panel,
   authenticated with the node daemon token. The panel signs a certificate
   with its CA and returns the overlay address, CA certificate, lighthouse
   map, and firewall rules.
3. Writes `ca.crt`, `host.crt`, `host.key` and a rendered `nebula.yml`.
4. Runs `nebula -config nebula.yml` and restarts it on failure or after a
   certificate refresh.
5. Serves `/healthz`, `/status`, and `/metrics` on the configured listen
   address.

## Requirements

- The `nebula` binary on the host (github.com/slackhq/nebula).
- The `nebula-cert` binary on the panel host (used to sign certificates).
- `NEBULA_ENABLED=true` on the panel and an initialized CA
  (`php artisan nebula:init-ca`).
- Root or CAP_NET_ADMIN on the node for the tun device.

## Setup

```sh
go build -o nebulod ./cmd/nebulod
cp nebulod.example.yml /etc/nebulod.yml
# put the node daemon token (token_id.secret) in /run/secrets/node_token
./nebulod run -config /etc/nebulod.yml
```

Other subcommands: `keygen`, `enroll`, `print-config`, `version`.

## Scaling notes

Enrollment is a single authenticated request per node and idempotent, so
adding a node is: install nebulod, drop in the daemon token, start the
service. The panel allocates overlay addresses from `NEBULA_OVERLAY_CIDR`
and tracks every issued certificate in `nebula_hosts`, so address
management and revocation auditing happen centrally. Run multiple
lighthouses for failover, list them in `NEBULA_LIGHTHOUSES`, and nodes
pick up the new map on their next re-enrollment sync.
