# Screenshot tool

Captures the panel UI into `showcase/` at the repository root.

```bash
tools/screenshots/screenshot.sh
```

What it does:

1. Creates a throwaway sqlite database in `/tmp`.
2. Runs migrations and seeders, then `seed.php` adds a demo admin user
   (`admin@nebulodactyl.dev` / `admin`), one location, one node, and three
   servers.
3. Serves the panel with `php artisan serve`.
4. Drives headless Chromium through the login page, the server dashboard,
   and the server console with `capture.mjs` (playwright-core).
5. Writes `panel-login.png`, `panel-dark.png`, and `panel-console.png` to
   `showcase/` and deletes the database.

Requirements: PHP 8.4+ with `pdo_sqlite`, composer dependencies installed,
`pnpm build` run once, node, and a Chromium binary (`CHROMIUM_PATH` overrides
autodetection). The marketing site and README reference these images.
