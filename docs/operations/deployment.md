# Deployment and operations

## Environment

Set production environment variables securely. At minimum:
- APP_ENV=production
- APP_DEBUG=false
- APP_KEY set to a strong generated key
- APP_URL set to the canonical HTTPS URL
- database connection and credentials set for the production database
- session, cache, queue, mail, and filesystem settings explicitly reviewed for the deployment

Never commit .env or place production credentials in source control. Restrict access to the deployment shell, database credentials, Tinker, and logs.

## Deployment sequence

1. Deploy the reviewed code and locked dependencies.
2. Install PHP dependencies with the production Composer flags appropriate to the hosting environment.
3. Build frontend assets with npm ci and npm run build.
4. Back up the database before schema changes.
5. Run migrations in production mode:

       php artisan migrate --force

6. Seed built-in Blueprint tools only when appropriate for the release:

       php artisan db:seed --force

   The seeders create the three built-in assessment Blueprints; they do not create user accounts.
7. Clear/rebuild Laravel caches according to the deployment strategy and verify the login page, teacher tools, API health, and logs.
8. Confirm both application and database backups are recoverable.

Review each migration before deployment. Do not roll back migrations blindly when the application has already written data using the new schema.

## Provisioning the first super administrator

No default administrator is created. Provision a normal account through the trusted account-creation process, then promote the verified account using the procedure in docs/security/authorization.md. Keep this step restricted and record the operational change according to your organization’s procedures.

Do not expose Tinker publicly or run role promotion based on an unverified email address.

## Sanctum and transport security

- Serve the application over HTTPS.
- In production, set `SESSION_SECURE_COOKIE=true` so the session cookie is sent only over HTTPS; keep `SESSION_HTTP_ONLY=true` and use `SESSION_SAME_SITE=lax` for the same-site browser application unless the reviewed deployment topology requires a different value.
- Keep `SESSION_DOMAIN` unset (host-only cookie) unless sharing the session across subdomains is an explicit requirement. If cross-site SPA cookies are required, review `SameSite=None`, `Secure`, allowed origins, credentials, and `SANCTUM_STATEFUL_DOMAINS` together; never broaden the stateful domain list with wildcards.
- Protect bearer tokens and revoke tokens when an account is compromised.
- Include the canonical browser host and port in SANCTUM_STATEFUL_DOMAINS so the same-origin authorization dashboard can authenticate its stateful API requests. Keep the domain list narrow and do not use wildcards.
- Keep login and password-reset rate limits enabled.
- Verify CSRF enforcement against a deployed/staging HTTP stack: a stateful session-authenticated mutation without a valid CSRF token should be rejected (typically HTTP 419), while the same request with the session cookie and matching CSRF token should succeed. Laravel's normal feature-test environment bypasses CSRF validation, so ordinary feature tests alone do not prove this end-to-end behavior.
- Do not return role/permission data from general user serialization.
- Keep public API projections narrow and review new fields for sensitive data.
- Do not expose execution input, context, output, or error to another user without an explicit audited support permission.

## Audit operations

Review authorization_audit_events for:
- private Blueprint or revision reads;
- private system or user Blueprint execution;
- system Blueprint management exceptions;
- role and permission changes;
- teacher registry changes;
- support reads of execution records.

The reason should explain the operational need without including sensitive payloads. Audit records preserve actor identifiers even if a user is deleted.

## User deletion and execution retention

Delete users through the application/Eloquent model so the deletion hook redacts execution payloads before owner_id becomes null. Direct SQL deletion bypasses that hook. If operational procedures require direct database deletion, implement and test an equivalent database-level redaction procedure before using it.

Back up and protect authorization audit data. Define organization-specific retention periods and access controls before production launch; the application currently retains audit rows without a scheduled purge.

## Release checklist

- Both SQLite and MySQL CI jobs are green for the exact commit to be deployed.
- Migrations have been reviewed and a backup exists.
- APP_DEBUG is false and HTTPS is enabled.
- A secure super administrator exists; no default password is used.
- Role assignments follow least privilege.
- Registry entries refer to existing views and public eligible Blueprints.
- Audit events are written for privileged operations.
- User deletion is performed through the model path.
- Logs, backups, and audit data have restricted access.
