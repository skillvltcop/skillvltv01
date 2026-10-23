# SkillVLT documentation

This handbook documents the current repository, its architecture, runtime behavior, API, authorization model, data model, development workflow, and operational requirements. It deliberately distinguishes implemented behavior from future product ideas.

## Reading order

1. [System overview](architecture/overview.md) — application layers, core components, and request flow.
2. [Blueprint lifecycle and execution](architecture/blueprint-lifecycle.md) — revisions, freezing, promotion, lifecycle states, and executions.
3. [Behavior runtime](architecture/behavior-runtime.md) — logic format, supported steps, value paths, contracts, and failure behavior.
4. [Authorization and security](security/authorization.md) — roles, named permissions, visibility rules, audit events, and privileged administration.
5. [API reference](api/reference.md) — routes, authentication, request contracts, response shapes, and error codes.
6. [Data model](data-model.md) — tables, relationships, constraints, and retention behavior.
7. [Development and testing](development/testing.md) — local setup, commands, test suites, and CI.
8. [Deployment and operations](operations/deployment.md) — production configuration, initial administrator provisioning, migrations, and security checks.
9. [Glossary](glossary.md) — English technical terms with Arabic explanations.

## Sources of truth

- Routes are defined in routes/api.php and routes/web.php.
- Database structure is defined by database/migrations.
- Domain invariants are implemented under app/Domain.
- Use cases and orchestration live under app/Application.
- Eloquent persistence adapters live under app/Infrastructure/Persistence.
- HTTP adapters live under app/Http/Controllers and app/Http/Requests.
- Authorization policy is implemented under app/Authorization and the admin API controllers.
- Tests under tests/ are the executable evidence for behavior.
- The authorization worklog records decisions and review evidence; it is not a substitute for source code or tests.

If this handbook conflicts with executable code or a test, treat the code and test as the current behavior, report the discrepancy, and update the handbook after the behavior is resolved.
