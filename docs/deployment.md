# Deployment

Nebulodactyl has two parts: the panel (web UI and API) and Comet (the node
daemon that runs the actual game servers). You can deploy them three ways.

## Topologies

**Panel only.** Just the panel, usually to manage nodes on other machines.
This is what `install.sh` sets up.

**Hybrid.** Panel and Comet on the same machine. Run the compose stack for the
panel, then install Comet on the host as described below. Works fine for a
single box lab or small setup.

**Node only.** Just Comet, enrolled against a panel running somewhere else.
This is how you scale out: each game server host runs Comet and nothing else.

## Panel

### Compose (podman or docker)

```
curl -fsSL https://raw.githubusercontent.com/Sudo-Ivan/Nebulodactyl/master/install.sh | bash
```

The installer prefers podman and falls back to docker. It asks for the install
directory and panel URL, writes a compose file and `.env`, starts the stack,
and prints your first-run setup link.

Manual compose install: copy `docker-compose.example.yml` to
`docker-compose.yml`, copy `.env.example` to `.env`, fill in the required
values, then `podman compose up -d` (or `docker compose up -d`).

### k3s / Kubernetes

Manifests live in `deploy/k8s/`:

```
kubectl apply -f deploy/k8s/namespace.yaml
kubectl apply -f deploy/k8s/secrets.yaml    # copy from secrets.example.yaml first
kubectl apply -f deploy/k8s/mariadb.yaml
kubectl apply -f deploy/k8s/redis.yaml
kubectl apply -f deploy/k8s/panel.yaml
```

Edit the host in the IngressRoute and `APP_URL` before applying. On k3s the
bundled Traefik picks up the IngressRoute automatically. Point external
databases at your own instances by changing the env values instead of
deploying the bundled MariaDB and Redis.

The first-run setup link is printed in the pod logs on boot:

```
kubectl -n nebulodactyl logs deploy/panel | grep setup
```

## Comet nodes

Comet is a single Go binary. On the node machine:

1. In the panel admin area, create a node and copy the auto-deploy command.
   It looks like:

   ```
   cd /etc/comet && comet configure --panel-url https://panel.example.com --token <token> --node <id>
   ```

   The token is a deployment token issued by the panel, it lets Comet fetch
   its own config and write `/etc/comet/config.yml`.

2. Start Comet. Pick one:

   - systemd: `install comet/deploy/comet.service` then
     `systemctl enable --now comet`
   - rootless podman: `install comet/deploy/comet-rootless.service` to
     `~/.config/systemd/user/`, enable lingering, then
     `systemctl --user enable --now comet`
   - Quadlet: drop `comet/deploy/comet.container` into
     `/etc/containers/systemd/` or `~/.config/containers/systemd/`
   - compose: `podman compose -f docker-compose.example.yml up -d`

3. The node shows as online (green) in the panel once Comet connects back.

## Podman

Comet uses the Docker API, which podman provides through its socket. System
podman exposes `/run/podman/podman.sock` (enable with
`systemctl enable --now podman.socket`), rootless podman uses
`/run/user/<uid>/podman/podman.sock`.

Point Comet at the socket in `/etc/comet/config.yml`:

```yaml
docker:
  host: unix:///run/user/1000/podman/podman.sock
```

or set `DOCKER_HOST`. Comet detects podman automatically and adjusts network
options and the container log driver. When the engine reports rootless mode,
Comet switches its file ownership model to match automatically.

Rootless notes:

- Ports below 1024 cannot be bound by game server containers unless you raise
  `net.ipv4.ip_unprivileged_port_start` sysctl.
- The `docker.userns_mode` and force-outgoing-IP features are Docker only.
- Volume mounts into game containers need `:U` or matching ownership with the
  container user.

## Linking a node over Nebula

If the panel and nodes live on a Nebula overlay, set the node's FQDN to its
overlay address (or enroll the node via the Nebula settings in the admin
area). Panel-to-node traffic then never needs public addresses. See
`docs/networking.md` for the overlay setup.

## Signed artifacts

Images on GHCR are signed with cosign keyless signing and carry SBOM and
SLSA provenance attestations:

```
cosign verify ghcr.io/sudo-ivan/nebulodactyl:canary \
  --certificate-identity-regexp 'https://github.com/Sudo-Ivan/Nebulodactyl' \
  --certificate-oidc-issuer https://token.actions.githubusercontent.com
```

## Error reporting

The panel, web frontend, and Comet daemon all support Sentry-protocol error
reporting, which covers Sentry itself as well as self-hosted compatible
backends like GlitchTip and Bugsink.

Set `SENTRY_DSN` in the panel environment to enable backend and frontend
reporting, and export the same variable for the `comet` process (for example
in the systemd unit or quadlet) to capture daemon errors. `send_default_pii`
stays off by default, so no IPs or user data are attached unless you opt in
with `SENTRY_SEND_DEFAULT_PII=true`.

## Single sign-on

The panel supports OpenID Connect login for providers like Authentik,
Keycloak, Authelia, Zitadel, or Dex. Register the panel at your provider
with the redirect URI `{APP_URL}/auth/oidc/callback`, then set
`OIDC_ENABLED=true`, `OIDC_ISSUER`, `OIDC_CLIENT_ID`, and
`OIDC_CLIENT_SECRET` in the panel environment. The login page then shows
a "Continue with SSO" button alongside the normal password form. Users
are matched to existing accounts by the provider subject claim or email,
and new accounts are created automatically unless `OIDC_AUTO_REGISTER`
is set to false.
