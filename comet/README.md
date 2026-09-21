# Comet

Comet is the node daemon for [Nebulodactyl](https://github.com/Sudo-Ivan/Nebulodactyl).
It runs the game servers: it creates and supervises containers, serves the file
management and console APIs, and handles backups, transfers and SFTP access.

Comet is a fork of [Pterodactyl Wings](https://github.com/pterodactyl/wings)
(vendored at v1.13.3) and keeps wire compatibility with the panel daemon API,
so existing nodes and tooling continue to work.

## Runtime support

Comet talks to the container engine over the Docker API. Both engines work:

- Podman (preferred). Rootless podman is supported, set `docker.host` to the
  user socket and `system.user.rootless.enabled: true`.
- Docker, including rootless docker.

Point Comet at a non-default socket with `docker.host` in `config.yml`:

```yaml
docker:
  host: unix:///run/user/1000/podman/podman.sock
```

The `DOCKER_HOST` environment variable works too.

## Install

Get the configure command from the panel: create a node in the admin area and
copy the auto-deploy command. It looks like:

```
cd /etc/comet && comet configure --panel-url https://panel.example.com --token <token> --node <id>
systemctl enable --now comet
```

Unit files and a Podman Quadlet live in `deploy/`, a compose example is in
`docker-compose.example.yml`.

## Features on top of Wings

- Podman and rootless engine detection with automatic adjustments
- Resumable file uploads over HTTP using Content-Range offsets, plus range
  request support on file downloads
- Renamed paths, binaries and service units (config in `/etc/comet`, data in
  `/var/lib/comet`)

## License

MIT, same as upstream Wings. Copyright 2018 Dane Everitt & Contributors.
