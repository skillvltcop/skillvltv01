# Authorization and security model

## Authentication is not authorization

The API uses Laravel Sanctum authentication. Most API routes require auth:sanctum; web teacher-tool pages require auth. Authentication proves which user is making a request. It does not grant that user administrative access to every Blueprint or execution.

The current role values are user, admin, and super_admin. A guest is an unauthenticated visitor, not a required database row. The users table has a role column defaulting to user and a JSON permissions column. Authorization attributes are not mass-assignable and are hidden from default User serialization.

## Sanctum bearer-token lifecycle

The API login endpoint issues a new Sanctum personal access token after each successful login. The token is returned once in the login response; treat it as a credential and never write it to logs, audit context, or support tickets. The current logout endpoint revokes only the bearer token used for that request. A stateful browser-session logout instead invalidates the web session and rotates its CSRF token.

The global personal-access-token lifetime is controlled by `SANCTUM_EXPIRATION_MINUTES`, which maps to `sanctum.expiration`. When the variable is unset or null, there is no global expiration limit; a token may remain valid until revoked unless it has its own `expires_at` value. First-party session authentication is not governed by this Sanctum token-expiration setting.

No global lifetime is imposed by default in this repository because choosing a maximum lifetime is an API compatibility and product-policy decision. Operators should set a finite value appropriate to their client and re-authentication model, then verify expiry behavior in the deployed configuration. For example, `SANCTUM_EXPIRATION_MINUTES=10080` represents seven days; this is an example, not a universal recommendation. Changing the value requires configuration-cache refresh/redeployment as appropriate.

The current login flow does not expose token naming, per-device token management, or an endpoint to revoke all of a user's tokens. Repeated successful logins can therefore create multiple active personal access tokens. During account compromise, revoke affected tokens through a trusted operational path and review whether a user-facing token-management feature is required before supporting long-lived third-party clients.

## Roles

- user — manages their own Blueprints and can discover/use eligible public system Blueprints.
- admin — receives only the named permissions explicitly stored for that account. The admin role alone grants no system-resource override.
- super_admin — can pass named permission checks and is the only role permitted to change users' roles and permission assignments. Sensitive exceptions still require a reason and are audited; domain integrity rules remain in force.
- guest — cannot call authenticated API operations or access authenticated teacher pages.

A minimal Arabic web dashboard is available at /admin/authorization for super_admin accounts. It uses the authenticated API for user/permission management, teacher registry changes, and audit review. A first super administrator must be provisioned securely by an operator; the application intentionally creates no default administrator account.

## Named permissions currently supported

| Permission | Purpose |
|---|---|
| blueprint.system.create | Create a system-owned Blueprint. |
| blueprint.system.read_private | Read internal details of a system Blueprint or revision. |
| blueprint.system.execute_private | Execute a non-public system Blueprint when its domain/lifecycle rules still permit execution. |
| blueprint.system.manage_revisions | Add, freeze, or promote revisions of a system Blueprint. |
| blueprint.system.manage_lifecycle | Change lifecycle state for a system Blueprint. |
| blueprint.user.read_private | Audited support access to another user's private Blueprint details. |
| blueprint.user.execute_private | Audited support execution of another user's private Blueprint, subject to engine invariants. |
| teacher_tools.manage_registry | List and change the teacher tool registry. |
| execution.support_read | Audited support access to an execution record. |

A permission only has an effect where the relevant policy/controller checks it. Do not infer an ability from a permission name alone; tests and the implementation are authoritative.

## Public system Blueprint eligibility

A system Blueprint is publicly discoverable and executable by a normal authenticated user only when all of these are true:

1. The ownership identity is the platform system owner.
2. lifecycle_status is active.
3. A current revision exists.
4. That current revision is frozen.
5. The same current revision has policies.visibility set to public.

The application does not add a published lifecycle state. The general discovery endpoint returns a restricted projection: canonical_name, title, description, category, tags, and version. It does not return the internal ID, namespace, ownership, lifecycle status, current revision ID, full metadata, logic, contracts, outputs, or policies.

A normal user can see public metadata but cannot read the internal revision details of a system Blueprint. Owners can read their own Blueprint details and revisions. Access to private details for another user's Blueprint requires a named permission, a non-empty authorization reason, and an audit record.

## Teacher tool registry

The teacher-facing product has a registry configured in config/teacher_tools.php with optional persisted overrides in teacher_tool_registry. Discovery, API execution, and web-view resolution use the same registry. A slug or namespace alone does not authorize a teacher execution.

Registry changes require teacher_tools.manage_registry, a reason, and an authorization audit event. Enabling a registry entry requires its view to exist. A registered slug still needs a matching, active, current, frozen, public system Blueprint to appear in discovery or execute.

## Privileged audit behavior

The authorization_audit_events table records the actor identifier, action, resource type and identifier, permission, reason, context, and timestamps. Audit rows deliberately do not have a foreign key to users, so the actor identifier remains available if that user is later deleted.

Privileged read, execution, and system-management exceptions require a non-empty authorization_reason and create an audit event. Role/permission changes and teacher-registry changes use a reason field and are also audited. Do not put secret tokens, full input payloads, student records, or other sensitive data in the reason or audit context.

## Administrative API

- GET /api/admin/permissions — super_admin only; returns the canonical grantable permission catalog and Arabic descriptions.
- GET /api/admin/users — super_admin only; paginated list of user IDs, names, emails, roles, and permissions, excluding credentials.
- PATCH /api/admin/users/{userId}/authorization — super_admin only; changes role and explicit permission list, requires reason, records an audit event, and prevents demotion of the last super administrator.
- GET /api/admin/audit-events — super_admin only; paginated audit log with optional action, resource_type, and actor_user_id filters.
- GET /api/admin/teacher-tools — requires teacher_tools.manage_registry.
- PUT /api/admin/teacher-tools/{slug} — requires teacher_tools.manage_registry and a reason; sets namespace, view, and enabled state.

Only a super administrator can grant roles and permissions. The API validates role names and an allowlist of permission identifiers. For roles other than admin, the permission list must be empty.

## Initial super administrator provisioning

There is intentionally no default admin password. Create a normal user through the trusted provisioning process, then use Tinker from a secured deployment shell to promote that known account. For example:

    php artisan tinker

Inside Tinker:

    use App\Models\User;
    $account = User::query()->where('email', 'YOUR_ADMIN_EMAIL')->firstOrFail();
    $account->forceFill(['role' => 'super_admin', 'permissions' => []])->save();

Replace the example email with the exact, verified account email. Do not place real credentials in source control, chat logs, deployment scripts, or documentation. Keep access to Tinker and production database credentials restricted.

## Execution data retention on user deletion

When a User is deleted through Eloquent, the model hook redacts execution input and context. Completed output is replaced with an empty JSON object to preserve the completed-state invariant; failed output is cleared and its error becomes a generic redaction message; pending/running output and error remain null. The execution record remains and owner_id becomes null through the foreign key's nullOnDelete behavior. Ownerless executions are not readable by ordinary users.

This hook depends on deletion through the application/Eloquent model. Direct SQL deletion bypasses model events and therefore bypasses payload redaction; do not delete users directly in SQL. A database-level deletion process would need an equivalent redaction mechanism before it is approved for operational use.

## Security checklist

- Keep APP_DEBUG=false in production and use HTTPS.
- Protect Sanctum tokens and never log them.
- Do not trust client-supplied ownership; the server sets the owner.
- Keep administrative permissions least-privilege and review them periodically.
- Require a reason for support access and inspect audit events during incident review.
- Keep SQLite and MySQL test suites green before merging authorization changes.
- Do not remove current tests or loosen invariants just to make a new authorization path pass.
