<p align="center">
  <img alt="Nebulodactyl Banner" src=".github/banner_nebulodactyl.svg">
</p>

<br/>

<h1 align="center">Nebulodactyl</h1>

<p align="center">
  <a href="https://github.com/Sudo-Ivan/Nebulodactyl/actions/workflows/ci.yaml">
    <img src="https://shieldcn.dev/badge/Build-Passing-success.svg?logo=githubactions" alt="Build">
  </a>
  <img src="https://shieldcn.dev/badge/Formatted%20with-Biome-93c5fd.svg?logo=biome" alt="Formatted with Biome">
  <img src="https://shieldcn.dev/badge/Linted%20with-Biome-93c5fd.svg?logo=biome" alt="Linted with Biome">
</p>

<br/>

Nebulodactyl is a game server management panel forked from Hydrodactyl (itself a Pterodactyl fork) with a space-themed interface and support for joining nodes over a [Nebula](https://github.com/slackhq/nebula) overlay network.

## Features

- Space theme: a nebula-inspired color scheme with deep-space backgrounds, starfield accents, and a violet/cyan palette.
- Nebula overlay network: optional integration for running panel-to-node traffic over a mutually authenticated Nebula overlay instead of exposing daemons on public addresses. See `config/nebula.php` and `examples/nebula/`.
- Reimagined client panel for console, files, databases, backups, network, users, startup, schedules, activity, and software.
- Marketplace: a native plugin and mod installer for Minecraft servers backed by Modrinth, Hangar, and Spiget.
- Setup wizard for guided first-run configuration.
- Logo customization from the admin dashboard.
- S3-compatible backups with per-node storage on any S3-compatible provider.
- MySQL, MariaDB, or PostgreSQL for the panel database.
- OpenAPI documentation powered by Scalar.
- Laravel 13, React 19, TypeScript, and Tailwind CSS, formatted and linted with Biome.

> [!WARNING]
> **Pre-release Software:** Nebulodactyl is currently under active development. Some UI elements may appear broken and bugs may exist.
>
> **Incompatibility Notice:** Nebulodactyl is an all-in-one panel and is **not compatible** with Blueprint framework extensions.

> [!NOTE]
> Nebulodactyl inherits its documentation from upstream. Please review the Hydrodactyl documentation at [hydrodactyl.dev](https://hydrodactyl.dev/docs/hydrodactyl) before installing.

## Quick start

```bash
git clone https://github.com/Sudo-Ivan/Nebulodactyl.git
cd Nebulodactyl
cp .env.example .env
cp docker-compose.example.yml docker-compose.yml
docker compose up -d
```

See the upstream [Installation Guide](https://hydrodactyl.dev/docs/hydrodactyl/installation) and [Local Development Guide](https://hydrodactyl.dev/docs/hydrodactyl/local-development) for detailed instructions.

> [!NOTE]
> Windows is supported for **local development only**.

## License

Nebulodactyl is open-source software licensed under the **Apache License 2.0**.

You are free to use, modify, and redistribute Nebulodactyl under the terms of the license. A copy of the full license text is available in the [LICENSE](./LICENSE.md) file included in this repository.

### Copyright & Attribution

Nebulodactyl is built upon the work of previous open-source projects and their contributors:

- **Pterodactyl®**: Copyright © 2015-2022 Dane Everitt and contributors.
- **Pyrodactyl™**: Copyright © 2023-2025 Pyro Inc. and contributors.
- **Pyrodactyl™**: Copyright © 2025-2026 Pyrodactyl-oss and contributors.
- **Hydrodactyl**: Copyright © 2026-present Naterfute, Blueprint Framework, and contributors.
- **Nebulodactyl**: Copyright © 2026-present Ivan and contributors.

All original copyright notices, license notices, and attributions must remain intact when redistributing this software.

Unless explicitly stated otherwise, all source code within this repository is licensed under the **Apache License 2.0**.

## Support

- **Star the repository**: Share it with others to help more people discover Nebulodactyl.
- [GitHub Issues](https://github.com/Sudo-Ivan/Nebulodactyl/issues): Report bugs and request features.
