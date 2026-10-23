# Data model and persistence

The schema evolves through Laravel migrations under database/migrations. The following describes the current logical model; use the migration files for exact column definitions and constraints.

## users

Stores login identity and authentication data. The authorization migration adds:
- role — defaults to user.
- permissions — nullable JSON list of explicit permission identifiers.

Role and permissions are not mass-assignable and are hidden from default model serialization. The API returns them explicitly only from the administrative authorization endpoint.

## blueprints

A Blueprint row stores a ULID primary key, canonical_name, namespace, owner_type, owner_id, lifecycle_status, current_revision_id, and timestamps. The pair namespace + canonical_name is unique.

Ownership uses a type and identifier rather than a foreign key to users because system-owned resources are not users. Ordinary create requests set ownership server-side.

## blueprint_metadata

One metadata row per Blueprint. Structured columns include taxonomy, documentation, discovery, lifecycle_metadata, and the extensible payload. Metadata is JSON and is intended for descriptive/discovery information, not as an authorization substitute. The public API now returns an explicit projection rather than the full metadata payload.

## blueprint_revisions

Each revision has a ULID, blueprint_id, revision_number, optional parent_revision_id, behavior_digest, contracts, logic, outputs, policies, frozen flag, and timestamps.

Constraints protect the Blueprint relationship, revision-number uniqueness within a Blueprint, and parent revision references. Revision behavior is treated as immutable after freezing. The current revision pointer on blueprints must point to a revision of that Blueprint.

## executions

Each execution has a ULID, blueprint_id, revision_id, nullable owner_id, input, context, status, nullable output, nullable error, and timestamps.

- blueprint_id and revision_id reference the source Blueprint and Revision.
- owner_id references users and is nullable with nullOnDelete.
- Blueprint/revision deletion is restricted while executions reference them.
- The domain validates consistency between status and output/error.

## authorization_audit_events

Stores actor_user_id as a string without a user foreign key, action, resource_type, resource_id, optional permission, reason, JSON context, and timestamps. Retaining the actor identifier without a foreign key preserves the audit reference if a user is deleted. Audit context should be minimal and must not contain tokens or full personal payloads.

## teacher_tool_registry

Stores optional administrative overrides for a teacher tool slug: namespace, view, enabled state, updated_by identifier, and timestamps. Configuration in config/teacher_tools.php provides defaults. Database overrides can add/replace entries or disable a configured slug. The registry is used by teacher discovery, execution, and web view resolution.

## Relationship and deletion rules

- Blueprint metadata cascades when its Blueprint is deleted.
- Revisions belong to a Blueprint; revision history uses a parent-revision link.
- Executions restrict Blueprint and Revision deletion to preserve execution provenance.
- User deletion through the Eloquent User model redacts execution input/context and sensitive output/error while retaining the execution row; the owner foreign key then becomes null.
- Ownerless executions cannot be read by normal users through the API.
- Direct SQL user deletion bypasses the Eloquent redaction event. Operational deletion must use the application model path or an equivalent database-level redaction workflow.

## Data minimization

Only collect input and context required for a run. They may contain student or other personal data. Do not put such data in logs or audit reasons. Before introducing a new retention period or irreversible deletion policy, document product/legal requirements and test the behavior on both SQLite and MySQL.
