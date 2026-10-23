# System architecture

## Product purpose

SkillVLT is a Laravel 12 application for defining reusable, versioned Blueprints and executing a selected revision with input and context. A Blueprint is the reusable definition; an Execution is a stateful record of one run. The separation allows the same approved behavior to be reused without rewriting its definition for every run.

## Main layers

### Domain — app/Domain

Contains business concepts and invariants without depending on HTTP controllers. The Blueprint aggregate owns its revisions, lifecycle state, current revision, and ownership metadata. The Execution entity enforces legal status transitions and consistency between status, output, and error.

Important types include:

- Blueprint and BlueprintRevision entities.
- Execution entity.
- LifecycleStatus and ExecutionStatus enums.
- Value objects for IDs, names, namespaces, revision numbers, and behavior digests.
- Repository interfaces and domain exceptions.

### Application — app/Application

Coordinates use cases and domain operations. Blueprint commands create a Blueprint, add a revision, freeze a revision, promote a revision, and transition lifecycle state. The ExecuteBlueprint command loads a Blueprint through the execution-aware repository path, invokes the engine, and persists the resulting Execution.

The application layer also validates behavior contracts and inputs and runs behavior steps.

### Infrastructure — app/Infrastructure

Implements repository interfaces using Eloquent and the database. Persistence code reconstitutes domain entities from stored rows and protects identity, revision relationships, lifecycle state, and concurrent writes.

### HTTP adapters — app/Http and routes

Controllers translate HTTP requests into application calls and translate results into JSON or views. Form Requests validate request shapes. Routes and middleware provide authentication boundaries. Authorization policies make resource-level access decisions after authentication.

### Product-specific teacher tools

Teacher-facing tools are a product integration built on top of the general Blueprint model. The teacher tool registry maps a public slug to a Blueprint namespace and web view. Discovery, API execution, and web view resolution use the same registry. The registry can be overridden through an audited administrative API.

### Frontend assets

Blade views live under resources/views. Vite builds resources/js/app.js and resources/css/app.css. The teacher-tool JavaScript has a Node test suite at tests/JavaScript/teacher-tools.test.js.

## Core request flows

### Blueprint authoring

1. An authenticated client sends a create request.
2. The HTTP adapter validates the request and assigns ownership server-side.
3. The application command creates a domain Blueprint in draft state.
4. A revision is added with contracts, logic, outputs, and policies.
5. The revision is frozen, then promoted to current.
6. Lifecycle commands can activate, deprecate, or sunset the Blueprint, subject to domain invariants.

### Execution

1. The request passes authentication middleware.
2. The controller loads the Blueprint and applies the central authorization policy.
3. ExecuteBlueprint loads a concurrency-safe snapshot through findForExecution.
4. The engine verifies revision membership, frozen state, current-revision identity, and active lifecycle status.
5. Input validators run, the behavior runner evaluates steps, and an Execution reaches completed or failed status.
6. The execution repository persists the record with the initiating user as owner.
7. The response returns an execution identifier, status, and result data appropriate to the endpoint.

### Teacher tool flow

1. Teacher discovery obtains generally discoverable Blueprints.
2. The teacher registry filters the results to registered slugs and expected namespaces.
3. The UI receives only teacher-facing fields such as slug, title, purpose, target level, and version.
4. Teacher execution verifies the slug against the same registry and requires an active, current, frozen, public system Blueprint.
5. The endpoint returns a teacher-facing result without exposing internal execution identifiers beyond the intended execution ID.

## Architectural invariants

- Blueprint definitions and Execution records have separate lifecycles.
- Every Execution points to one Blueprint and one Revision belonging to that Blueprint.
- Only a frozen current revision of an active Blueprint can execute.
- Promotion advances to a newer revision; it is not a rollback operation.
- Frozen revisions are treated as immutable behavior snapshots.
- Authorization is not a domain invariant: policy decides who may attempt an operation, while the domain/application layer still enforces revision and lifecycle rules.
- The teacher domain must not become hard-coded into the core Blueprint domain.

## Repository map

- app/Domain — entities, enums, value objects, repository contracts, exceptions.
- app/Application — commands, validation, execution engine, runtime behavior.
- app/Infrastructure/Persistence/Eloquent — persistence adapters.
- app/Authorization — visibility, access policies, teacher registry, and audit logger.
- app/Http/Controllers — HTTP entry points.
- app/Http/Requests — request validation.
- routes — web and API route declarations.
- database/migrations — schema evolution.
- database/seeders — built-in teacher-tool Blueprints.
- tests/Feature — HTTP, persistence, authorization, and product flows.
- tests/Unit — focused domain and service behavior.
- docs — architecture, security, API, operations, and project tracking.
