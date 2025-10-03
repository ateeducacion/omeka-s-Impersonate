# Impersonate for Omeka S

![Screenshot of the 3D Viewer](https://raw.githubusercontent.com/ateeducacion/omeka-s-Impersonate/refs/heads/main/.github/assets/screenshot.png)

Impersonate lets trusted staff sign in as another user to reproduce issues or provide support, then return to their own account safely.

## What it does

- Adds a quick “switch to” link next to usernames on Admin → Users (and an icon in row actions).
- Supports a GET shortcut on any admin URL: append `?login_as=<userId>`.
- Shows a sticky banner at the very top with a “Return to admin” link.
- Blocks self‑impersonation and only allows switching to strictly lower roles.
- Settings: choose the minimum role that can impersonate (default: Global Administrator).

## Install

1. Copy to `omeka-s/modules/Impersonate` (directory name must be `Impersonate`).
2. In Omeka S, go to Modules → Impersonate → Install.

## Settings

- Minimum role that can impersonate: users with this role or higher can impersonate lower roles.

## Usage

- From Admin → Users, click “· switch to” next to a user (non‑admin roles only).
- Or use `/admin/anything?login_as=<id>` to switch directly.
- A banner appears at the very top; click “Return to admin” to revert.

## Permissions & compatibility

- Registers resource `impersonate` with privilege `manage_impersonation`.
- Default allowed role: `global_admin` (configurable via Settings above).

## Local Development with Docker

This repository includes a **Makefile** and a `docker-compose.yml` for quick local development using [erseco/alpine-omeka-s](https://github.com/erseco/alpine-omeka-s).

### Quick start

```bash
make up
```

Then open [http://localhost:8080](http://localhost:8080).

### Preconfigured users

The environment automatically creates several users with different roles:

| Email                                                   | Role         | Password        |
| ------------------------------------------------------- | ------------ | --------------- |
| [admin@example.com](mailto:admin@example.com)           | global_admin | PLEASE_CHANGEME |
| [siteadmin@example.com](mailto:siteadmin@example.com)   | site_admin   | 1234            |
| [editor@example.com](mailto:editor@example.com)         | editor       | 1234            |
| [author@example.com](mailto:author@example.com)         | author       | 1234            |
| [reviewer@example.com](mailto:reviewer@example.com)     | reviewer     | 1234            |
| [researcher@example.com](mailto:researcher@example.com) | researcher   | 1234            |

The **Impersonate module** is automatically enabled, so you can start testing right away.

### Useful Make commands

* `make up` – Start Docker containers in interactive mode
* `make upd` – Start in detached mode (background)
* `make down` – Stop and remove containers
* `make shell` – Open a shell inside the Omeka S container
* `make lint` – Run PHP_CodeSniffer
* `make fix` – Auto-fix coding style issues
* `make package VERSION=1.2.3` – Build a `.zip` release of the module
* `make test` – Run PHPUnit tests

Run `make help` for a full list.

