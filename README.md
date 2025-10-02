# Impersonate for Omeka S

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

No database migrations are created or required.

## Settings

- Minimum role that can impersonate: users with this role or higher can impersonate lower roles.

## Usage

- From Admin → Users, click “· switch to” next to a user (non‑admin roles only).
- Or use `/admin/anything?login_as=<id>` to switch directly.
- A banner appears at the very top; click “Return to admin” to revert.

## Permissions & compatibility

- Registers resource `impersonate` with privilege `manage_impersonation`.
- Default allowed role: `global_admin` (configurable via Settings above).
- Compatible with Omeka S 4.x and PHP ≥ 7.4 (DBAL 2/3 supported).

## Development

Run lint and tests:

```bash
make lint
make test
```

## License

GPL-3.0-or-later
