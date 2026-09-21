# TODO

Open work for Nebulodactyl. Items are roughly ordered by priority within
each section.

## Security

- [ ] Upload session binding to file metadata: bind session ids to
  expected file size or a content hash so a resumed upload cannot
  continue into a file another writer truncated or replaced
- [ ] Orphaned partial upload cleanup: registry entries are swept, but
  partial files on disk are not; add a periodic cleanup keyed off
  upload session paths
- [ ] Subdomain endpoints use allocation.* as a shared permission;
  decide whether finer-grained keys are worth adding
- [ ] Structured audit events: remote mutations are covered; extend to
  panel-side admin mutations that still bypass the activity log

## Features

- [ ] Panel-side bandwidth metering: daemon counters are wired and
  tested end to end, but nothing aggregates or displays them in the
  panel
- [ ] Transfer progress UI: history list and active endpoints exist on
  the admin manage page; add live per-transfer progress from daemon
  stats
- [ ] Backup integrity beyond existence: verify snapshot readability
  and checksums, not just object presence; surface details in the UI
- [ ] OIDC group mapping for non-admin roles: root_admin is mapped
  from groups; extend to subuser or custom roles if needed

## UI

- [ ] More showcase screenshots: files, backups, settings, admin node
  view; current set covers login, dashboard, console, network,
  schedules
- [ ] Light mode contrast audit across remaining views
- [ ] Upload conflict indicators: resumed badge exists; add a warning
  when a same-name file is skipped or replaced

## Infra

- [ ] SLSA provenance validation step and documented verification
  commands for release artifacts
- [ ] Docker image matrix still pending on latest push; verify
  buildx matrix completes
- [ ] k3s manifests need a real deployment test, not just lint

## Done

- [x] Remote API node-binding integration tests
- [x] Upload session ids, offsets, ownership, TTL sweep
- [x] Aggregate multipart upload limit
- [x] Structured audit events for remote API mutations
- [x] Node health and overlay status on admin node page
- [x] Backup verification badges in the UI
- [x] Permission enforcement parity (wildcard routes, elytra,
  marketplace mod.* keys, dead request classes removed)
- [x] OIDC group to root_admin mapping
- [x] Upload resume indicator in file manager
- [x] Collapsed sidebar width (64px icon rail)
- [x] Live-data showcase screenshots
- [x] CI action pins on Node 24 runtimes, checksum attestation
- [x] Daemon bandwidth counter tests
