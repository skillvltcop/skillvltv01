# Blueprint lifecycle, revisions, and execution

## Blueprint

A Blueprint is a named definition identified by a ULID. It has a canonical name, namespace, owner type and owner identifier, lifecycle status, metadata, revisions, and an optional current revision identifier. The database enforces uniqueness of the pair namespace + canonical_name.

Ownership currently distinguishes user-owned Blueprints from system-owned Blueprints. A normal create request assigns the current user as owner. Creating a system-owned Blueprint requires the explicit blueprint.system.create permission and a reason that is recorded in the authorization audit trail.

## Revision

A Revision is a versioned behavior snapshot belonging to one Blueprint. It stores:

- revision_number — a string used by the domain to enforce forward progression.
- parent_revision_id — the prior revision in the revision history, where applicable.
- behavior_digest — a digest identifying the behavior definition.
- contracts — the expected input contract and related validation information.
- logic — the declarative behavior steps executed by the runtime.
- outputs — the intended output description/schema.
- policies — revision-specific policy metadata, including visibility.
- frozen — whether the revision is frozen against further behavior mutation.

A Blueprint cannot have duplicate revision numbers. A new revision number must be greater than the latest existing revision number. A revision must be frozen before it can become current. Promotion cannot select the current revision again or move back to an older revision.

The database preserves the Blueprint-to-Revision relationship and constrains the parent-revision relationship. Execution references restrict deletion of Blueprints and revisions while execution records still depend on them.

## Lifecycle states

The LifecycleStatus enum currently contains four states. There is no separate published state.

| State | Meaning |
|---|---|
| draft | Work in progress; not eligible for public discovery or new normal-user execution. |
| active | Potentially usable, but active alone does not make a system Blueprint public. The current revision must also be frozen and have policies.visibility=public. |
| deprecated | The Blueprint is being phased out. It is excluded from public discovery and cannot be executed by normal users. Existing revision-management rules still apply where the domain allows them. |
| sunset | Retired. Domain rules prohibit adding or promoting revisions and restrict further lifecycle operations. |

Public availability is the conjunction of system ownership, active lifecycle status, an existing current revision, a frozen current revision, and visibility public on that same revision. Visibility on an old or non-current revision is not enough.

## Typical release sequence

1. Create the Blueprint in draft state.
2. Add a revision with valid contracts, logic, outputs, and policies.
3. Freeze the revision after its behavior is ready.
4. Promote it to current. Promotion moves forward only.
5. Activate the Blueprint.
6. To make a system Blueprint discoverable and executable by normal authenticated users, ensure its current frozen revision declares public visibility.
7. When the capability should be phased out, deprecate it; when it is retired, sunset it according to the allowed lifecycle transitions.

The order of activation and promotion is governed by the commands and domain rules. Clients must treat failed transitions as authoritative rather than assuming the example sequence overrides those rules.

## Execution

ExecutionStatus contains pending, running, completed, and failed. An Execution stores the Blueprint ID, Revision ID, owner ID where available, input, context, status, output, error, and timestamps.

The domain enforces status invariants:
- pending and running executions cannot have output or error;
- completed executions require output and cannot have an error;
- failed executions require an error and cannot have output.

The execution engine verifies that the selected revision belongs to the Blueprint, is frozen, is the current revision, and the Blueprint is active. Authorization checks occur before the engine is invoked; they do not replace these invariants.

## Concurrency and deletion

The Blueprint repository has an execution-aware load path that locks the Blueprint row while loading its revision graph. Persistence checks guard against stale or conflicting updates. The execution repository checks revision ownership and protects execution identity and state from stale writes.

Deleting a Blueprint is restricted while executions reference it. When a user is deleted through the Eloquent model, execution payloads are redacted while a minimal execution record remains; the owner foreign key then becomes null. See the data model and security documents for the exact retention behavior and its operational limitation.
