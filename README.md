# SkillVLT

SkillVLT is a Laravel 12 application built around reusable, versioned **Blueprints**. A Blueprint defines behavior; a Revision records a version of that behavior; an Execution records one run of a specific revision. The design separates reusable definitions from run-specific input, context, status, and output.

## Start here

The full handbook is maintained under [docs/README.md](docs/README.md). It includes the architecture, lifecycle, API reference, security model, data model, deployment instructions, and an Arabic glossary.

## Core concepts

- **Blueprint** — a namespaced definition with ownership, metadata, lifecycle, and revisions.
- **Revision** — a behavior snapshot containing contracts, logic, outputs, and policies. Revisions advance in a linear history; promotion is not rollback.
- **Execution** — a stateful run of one Blueprint revision, storing input, context, status, output, error, and owner where available.
- **Authorization policy** — central rules that decide who can read, manage, or execute a resource after authentication.
- **Teacher tool registry** — a product-specific allowlist used consistently by teacher discovery, execution, and view resolution.

## Requirements

- PHP 8.2+
- Composer 2
- SQLite for local development or a supported production database; CI tests SQLite and MySQL 8
- Node.js 22 and npm for frontend assets and JavaScript tests

## Local setup

    composer install
    cp .env.example .env
    php artisan key:generate

Configure the database in .env, then run:

    php artisan migrate
    php artisan db:seed
    npm install
    npm run build

The seeders register built-in assessment tools. They do not create a default user or administrator account. Follow the secure provisioning instructions in [the security guide](docs/security/authorization.md).

Start the local development environment with:

    composer run dev

## Tests

    php artisan test
    node --test tests/JavaScript/teacher-tools.test.js
    npm run build

GitHub Actions runs the PHP suite on SQLite and MySQL 8. Both jobs must pass before a pull request is merged.

## API and security

Most API routes require Sanctum authentication. Public discovery is still behind authentication in the current MVP. Normal users can discover and execute only eligible public system Blueprints; internal revision details and private-resource exceptions are protected by the central authorization policy. Privileged exceptions require a reason and are audited.

See [the API reference](docs/api/reference.md) and [authorization guide](docs/security/authorization.md) before integrating with the API.

## Design constraints

- Blueprint definitions and executions have separate lifecycles.
- Executions reference the exact revision that ran.
- A system Blueprint is public only when system-owned, active, and pointing to a frozen current revision whose visibility policy is public.
- Revision promotion advances to a newer revision; it is not a rollback mechanism.
- Domain invariants remain in force even for administrators.
- Avoid storing unnecessary personal data in execution input and context.

## License

See the repository's license and dependency notices before redistributing the application.
