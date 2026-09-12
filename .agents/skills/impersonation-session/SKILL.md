---
name: impersonation-session
description: "Change login-as permissions, CSRF checks, session switching, or identity restoration."
---

# Impersonation session

Trace root `Module.php`, `src/Controller/`, and `src/Service/ImpersonationService.php` before changing
any entry point, including `login_as`. Inspect the actual service filename if it moves.

- Keep start/end requests POST-only with their distinct CSRF tokens. CSRF validation does not grant
  permission; authorization must also hold in the service, not only in the button or controller.
- The configured minimum role and ACL determine who may manage impersonation. Targets must be a
  strictly lower role and not the current user. Check the complete role hierarchy, not role names alone.
- Preserve the original identity in the `impersonate` session namespace, refuse nested impersonation,
  regenerate the session ID and restore the original user on exit. Handle deleted identities explicitly.
- Audit the real actor and target without logging credentials or session tokens. Do not confuse the
  effective impersonated identity with the initiating administrator.

Test allowed and denied role pairs, self/nested attempts, invalid CSRF, missing users and restoration.
Run the full PHPUnit suite after focused tests: identity and session state can leak between cases.
Verify UI visibility separately from direct endpoint denial for a security-sensitive change.
