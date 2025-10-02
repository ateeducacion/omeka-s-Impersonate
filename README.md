# Impersonate Module for Omeka S

Impersonate lets trusted administrators securely sign in as another user without knowing that user's password. Sessions remain auditable and reversible so staff can investigate issues or provide support while maintaining an immutable record of each impersonation event.

## Features

- Adds a **Login as** action to the admin users table for roles with the `manage_impersonation` capability.
- Switches the active session to the target user while preserving the original administrator in session storage.
- Shows a persistent banner across the admin UI so administrators can quickly return to their own account.
- Records every start/end event in an `impersonation_audit` table with administrator, target, timestamp, action and source IP.
- Provides an audit log page under the admin navigation for quick review of historical events.

## Requirements

- Omeka S 4.0 or later.
- PHP 7.4 or later.
- An administrator role allowed to use the `manage_impersonation` privilege (default: `global_admin`).

## Installation

1. Copy this repository into `omeka-s/modules/Impersonate` (the directory name must match the module).
2. In the Omeka S admin area go to **Modules → Impersonate → Install**.
3. The installer creates the `impersonation_audit` table automatically using Doctrine DBAL. A matching SQL script is available at `data/migrations/20250101000000_create_impersonation_audit.sql` if you prefer manual migrations.

## Uninstall

1. In the Omeka S admin area go to **Modules → Impersonate → Uninstall**.
2. The uninstall routine removes the `impersonation_audit` table. If you prefer to retain audit history, back up the data before uninstalling.

## Usage

- Navigate to **Admin → Users**. Roles with `manage_impersonation` will see a **Login as** button next to each non-super administrator.
- Clicking the button sends a POST request with a CSRF token to `/admin/impersonate/start`. If approved, your session switches to the target user and a gold banner appears at the top of every admin screen.
- Use the **Return to admin** button in the banner (POST `/admin/impersonate/end`) to revert to your original account.
- Review activity in **Admin → Impersonation audit**, where each start/end event is listed with timestamps and IP information.

## Permission model

- The module registers the resource `impersonate` with privilege `manage_impersonation`.
- By default, only the `global_admin` role is granted this capability. Grant additional roles via Omeka's ACL configuration if required.
- Impersonation is blocked for users with the `global_admin` or `super` roles to avoid privilege escalation.

## Security notes

- All state-changing endpoints require HTTP POST and a valid CSRF token.
- The original administrator identity and session metadata are stored in the session namespace `impersonate` and cleared on revert.
- Audit records are append-only; entries are never updated or deleted by the module.
- UI output is HTML-escaped to avoid leaking sensitive information.

## Running tests

```bash
composer dump-autoload
./vendor/bin/phpunit --configuration test/phpunit.xml
```

The test suite covers impersonation start/end behaviour, permission checks, CSRF validation and audit logging interactions. A lightweight set of Laminas/Omeka stubs is bundled under `test/Stubs` so the tests can run without a full Omeka S installation. Install PHPUnit (e.g. `composer install --dev`) before running the suite.

## QA examples

Start impersonation:

```bash
curl -X POST -H "Content-Type: application/x-www-form-urlencoded" \\
  --data "target_user_id=42&csrf=<token>" \\
  https://<your-omeka>/admin/impersonate/start -b cookiejar
```

End impersonation:

```bash
curl -X POST --data "csrf=<token>" https://<your-omeka>/admin/impersonate/end -b cookiejar
```

## License

Released under the GNU GPL v3 or later.
