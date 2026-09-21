<h1 align="center">Todolist</h1>

<br/>

A living roadmap of the features and improvements planned for Nebulodactyl. Checked items reflect the current state of the `master` branch.

> [!NOTE]
> Nebulodactyl is under active development. This list is not exhaustive - check [DEV.md](./DEV.md) and the open issues on GitHub for the latest status.

## Comet Daemon

- [x] Resumable HTTP uploads with range requests
- [x] Websocket upload sessions (session ids, offsets, ownership, TTL sweep)
- [x] Aggregate multipart upload limit
- [x] Bandwidth counters (rx/tx) in stats and resource events
- [ ] Upload session binding to file metadata (size or hash) so a resumed
  upload cannot continue into a file another writer replaced
- [ ] Orphaned partial upload cleanup on disk
- [ ] Podman rootless environment support

## Panel Backend

- [x] Remote API node-binding checks on all daemon endpoints
- [x] Structured audit events for remote API mutations
- [x] OIDC group to root_admin role mapping
- [x] Aggregate multipart upload limit enforcement
- [ ] Panel-side bandwidth metering and display (daemon counters exist)
- [ ] Backup integrity verification beyond object existence
- [ ] OIDC group mapping for non-admin roles
- [ ] Extend audit events to admin mutations that bypass the activity log

## Wings Automation

- [ ] Fully automate Wings configuration
  - [ ] Automate debug mode configuration
  - [ ] Automate machine-id configuration

## Auth Pages

- [x] Login Page
- [x] Password Reset Page
- [x] 2FA Page
- [x] Password strength checker

## Homepage

- [x] Search Bar (server search, sorting & filtering)
  - [x] Design layout
  - [x] Keyboard shortcut integration (`Cmd + K`)
  - [x] Search functionality
- [x] Servers Page (server list)
- [x] API Keys Page
- [x] SSH Keys Page
- [x] Settings Page
- [x] Sidebar Navigation

## Server Pages

- [x] Sidebar Navigation
- [x] Compact collapsed sidebar (64px icon rail)

### Console

- [x] Console view
- [x] System resource graphs
- [x] Power actions
- [x] Server Features
  - [x] Minecraft EULA prompt
  - [x] Java version selector
  - [x] McLogs integration
  - [x] Hytale feature support
  - [x] Steam disk space meter

### Files

- [x] File Explorer System
  - [ ] Shift + Click range selection
  - [x] Improved path change handling (breadcrumbs)
  - [x] Context action menu
  - [x] File MIME-type icons
- [x] Upload resume indicator
- [ ] Upload conflict warning when a same-name file is skipped
- [ ] File Editor
  - [ ] Reduce editor bundle size

### Databases

- [x] New database model
- [x] Redesigned database display UI
- [x] PostgreSQL support

### Backups

- [x] Redesigned, less cluttered backup list UI
- [x] Verification badges (verified, missing, unverified)
- [ ] Admin panel setting for backup creation limits per time period
- [ ] Shift + Click range selection for backups

### Network

- [x] General UI fixes and color scheme updates
- [x] Subdomain Management (Cloudflare, Bunny.net, and more)
- [ ] Finer-grained subdomain permissions (currently shared allocation.*)

### Users

- [x] Permission enforcement parity across daemon route variants
- [ ] Permission Groups
- [ ] Permission Presets
- [ ] Clean up / de-clutter interface

### Startup

- [x] One-click copy for environment variables
- [x] Redesigned "Startup Command" field
- [x] Improved Docker Image Selector

### Schedules

- [x] De-clutter "Create New Schedule" modal
- [ ] Custom Actions system (Admins & Users)
  - [ ] Send HTTP Request action
  - [ ] Interact with another owned server
    - [ ] Add "Actions Interactable" user permission
- [ ] Failure Alert Notifications
  - [ ] Email alerts
  - [ ] Discord webhooks
  - [ ] Slack integration
  - [ ] Mattermost integration

### Activity

- [x] Filter system
- [ ] Fix search restricted to current page (enable global search)
- [ ] Improve search/filter UX & overall feel

### Software

- [x] Redesigned page with verbose configuration options
- [x] Modularized code (split into ~200-400 line components)
- [x] Simplified component logic
- [ ] Optimize page performance

## Admin Panel

- [x] Nebula node status card (overlay IP, last seen, cert expiry)
- [x] Server transfer history and active transfer display
- [ ] Live per-transfer progress from daemon stats
- [ ] Admin Panel Redesign
  - [ ] Convert Admin Panel pages to React (dashboard overview is done)
  - [ ] Redesign UI to match Client-side styling

## Marketplace

- [x] Native plugin/mod installer (Modrinth, Hangar, Spiget)
- [x] Install history and management
- [x] Marketplace client API
- [x] Per-action permission enforcement (version, loader, resolver)

## General Changes

- [x] Redesigned dropdown menus across pages
- [x] Backups Page
- [x] Files Page
- [x] Software / Shell Page
- [x] Startup Page
- [ ] Light mode contrast audit across remaining views

## Infra

- [x] CI action pins on Node 24 runtimes
- [x] Release attestation covering checksums
- [x] Automated showcase screenshots with live daemon data
- [ ] SLSA provenance validation step and documented verify commands
- [ ] k3s manifests: real deployment test, not just lint
- [ ] More showcase screenshots (files, backups, settings, admin node view)
