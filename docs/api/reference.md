# HTTP API reference

All paths below are prefixed with /api unless explicitly marked as web routes. The authoritative definitions are in routes/api.php and routes/web.php.

## Authentication

### API login

POST /api/auth/login is public and rate-limited to five attempts per minute.

Request fields:
- email — required valid email address.
- password — required string.

A successful response contains a Sanctum bearer token and a minimal user object. Send the token on later API calls as Authorization: Bearer TOKEN. Protect it as a secret. POST /api/auth/logout revokes the current token. GET /api/auth/me returns the authenticated user's ID, name, and email.

### Web authentication

- GET/POST /login — login page and submission; POST is rate-limited.
- GET/POST /forgot-password — request password reset; submission is rate-limited.
- GET /reset-password/{token} and POST /reset-password — password reset.
- POST /logout — authenticated logout.
- POST /locale — sets the session locale to a supported language.
- GET / and /teacher/tools pages require authentication.

## Blueprint routes

| Method | Path | Purpose | Authorization notes |
|---|---|---|---|
| GET | /api/blueprints | List the authenticated user's own Blueprints. | Own resources only. |
| GET | /api/blueprints/discover | Discover public system Blueprints. | Authenticated; restricted public projection. |
| GET | /api/teacher/blueprints/discover | Discover registered teacher tools. | Authenticated; registry and public eligibility both apply. |
| GET | /api/blueprints/{blueprint} | Read a Blueprint. | Owner or audited permission for full details; public system Blueprint returns limited metadata to normal users. |
| GET | /api/blueprints/{blueprint}/revisions | List revision details. | Owner or audited permission; not public merely because the Blueprint is public. |
| GET | /api/blueprints/{blueprint}/revisions/{revision} | Read revision details. | Owner or audited permission. |
| POST | /api/blueprints | Create a user-owned Blueprint. | System ownership additionally requires blueprint.system.create and authorization_reason. |
| POST | /api/blueprints/{blueprint}/revisions | Add a revision. | Owner; system management permission plus reason for system-owned Blueprint. |
| POST | /api/blueprints/{blueprint}/revisions/{revision}/freeze | Freeze a revision. | Owner or audited system management permission. |
| POST | /api/blueprints/{blueprint}/revisions/{revision}/promote | Promote a revision. | Owner or audited system management permission; forward-only promotion. |
| POST | /api/blueprints/{blueprint}/activate | Activate a Blueprint. | Owner or audited system lifecycle permission. |
| POST | /api/blueprints/{blueprint}/deprecate | Deprecate a Blueprint. | Owner or audited system lifecycle permission. |
| POST | /api/blueprints/{blueprint}/sunset | Sunset a Blueprint. | Owner or audited system lifecycle permission. |

### Public discovery response contract

The general discovery endpoint returns only canonical_name, title, description, category, tags, and version. The optional locale query parameter accepts ar, fr, or en and localizes title and description when translations are available. Internal IDs, namespace, ownership, lifecycle status, current revision ID, full metadata, logic, contracts, outputs, and policies are omitted from this projection.

Teacher discovery has a separate contract: slug, title, purpose, optional target_level, and version. It is filtered through the shared teacher tool registry and public Blueprint eligibility policy.

### Create Blueprint request

Required fields:
- canonical_name — string, maximum 255.
- namespace — string, maximum 255.
- metadata — optional object/array.

For ordinary creation, ownership is always assigned to the current user. A client cannot assign another user as owner. To request system ownership, send ownership.type = system, provide authorization_reason, and hold blueprint.system.create. The system owner identifier is assigned by the server.

### Add revision request

The request requires a string revision number plus arrays for contracts, logic, outputs, and policies. The server validates behavior contracts and domain rules. The response includes the new revision identifier, digest, definition fields, and frozen status. For system-owned Blueprints, an administrator with revision-management permission but without the separate private-read permission receives only the revision identifier, Blueprint identifier, revision number, and frozen status; private contracts, logic, outputs, policies, and digest are omitted.

### Execute Blueprint request

POST /api/blueprints/{blueprint}/execute accepts:
- revision_id — required ULID.
- input — optional array/object.
- context — optional array/object.
- authorization_reason — required only when invoking an audited private-resource exception.

The engine requires the revision to belong to the Blueprint, be frozen and current, and the Blueprint to be active. The response contains execution_id, blueprint_id, revision_id, status, input, context, output, and error. Avoid sending unnecessary personal data in input/context.

### Teacher tool execution

POST /api/teacher/tools/{slug}/execute accepts input and context. Built-in teacher slugs have dedicated Form Request validation. All registered tools still pass the revision input-contract validation in the execution engine; registering a new slug does not automatically create a slug-specific Form Request validator. The endpoint returns execution_id, status, result, and error; it intentionally omits internal Blueprint and Revision identifiers from the teacher-facing response. The slug must be in the registry, and the underlying Blueprint must be a public, active system Blueprint with a frozen current revision.

### Execution record

GET /api/executions/{execution} returns the execution's identifiers, status, input, context, output, and error to its owner. A support administrator with execution.support_read may read it only with authorization_reason; that access is audited. An execution with owner_id null is not available to ordinary users.

## Administrative routes

### List the canonical permission catalog

GET /api/admin/permissions — super_admin only. Returns the centrally defined grantable permission identifiers and Arabic descriptions used by the dashboard and API validation.

### List users for authorization management

GET /api/admin/users — super_admin only. Supports per_page pagination (1–100) and returns only id, name, email, role, and permissions. Passwords, tokens, and other credentials are never returned.

### Review authorization audit events

GET /api/admin/audit-events — super_admin only. Supports per_page pagination (1–100) and optional action, resource_type, and actor_user_id filters. It returns the recorded reason and non-payload audit context.

### Change a user's role and permissions

PATCH /api/admin/users/{userId}/authorization — super_admin only.

Request fields:
- role — one of user, admin, super_admin.
- permissions — required array of allowed permission strings.
- reason — required string, minimum 10 and maximum 1000 characters.

Only admin may have a non-empty explicit permissions list. The operation is audited. The last super administrator cannot be demoted.

### Teacher registry

GET /api/admin/teacher-tools requires teacher_tools.manage_registry and returns registered tools.

PUT /api/admin/teacher-tools/{slug} requires teacher_tools.manage_registry. Fields:
- namespace — required string.
- view — required teacher.tools.* view name; an enabled view must exist.
- enabled — required boolean.
- reason — required string, minimum 10 and maximum 1000 characters.

Registry changes are audited. Disabling a configured tool overrides the default registry entry.

## Typical HTTP status codes

- 200 — successful read or operation.
- 201 — resource created.
- 401 — missing or invalid authentication.
- 403 — authenticated but not authorized.
- 404 — resource or registered tool not found.
- 409 — concurrent update conflict where the endpoint maps that exception.
- 422 — validation, domain-rule, or authorization-reason failure.

Exact error bodies depend on the endpoint and exception handler. Treat response schemas from the controller and tests as authoritative.
