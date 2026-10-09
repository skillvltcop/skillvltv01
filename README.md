# SkillVLT

SkillVLT is a Laravel 12 application built around reusable **Blueprints**: versioned definitions that can be executed with input and context to produce outputs. The application separates Blueprint design and revision history from individual execution state.

## Core concepts

- **Blueprint** — a named, namespaced definition with ownership, metadata, and a lifecycle.
- **Revision** — an immutable-in-practice version of a Blueprint's behavior, including contracts, logic, outputs, and policies. Revisions form a linear history; a new revision advances from the latest one.
- **Execution** — a stateful run of a specific Blueprint revision. It records input, context, status, output, and any error.
- **Runtime** — validates and runs behavior steps through the execution engine.

The domain and application layers live under `app/Domain` and `app/Application`; persistence and HTTP adapters are kept in the infrastructure and presentation layers.

## Requirements

- PHP 8.2+
- Composer
- A database supported by the application (SQLite for local development; MySQL 8 is also exercised in CI)
- Node.js and npm for frontend asset builds

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configure the database settings in `.env`, then run migrations:

```bash
php artisan migrate
```

To build frontend assets:

```bash
npm install
npm run build
```

Start the local development environment with:

```bash
composer run dev
```

## Running tests

Run the full test suite locally:

```bash
php artisan test
```

GitHub Actions runs the suite on PHP 8.2 against both SQLite and MySQL. MySQL-specific integration tests cover database locking and concurrency behavior; SQLite skips tests that require MySQL semantics.

## API overview

The API routes are defined in `routes/api.php` and are protected by Laravel Sanctum authentication, except for login.

The main resource groups include:

- Authentication: `/api/auth/login`, `/api/auth/me`, and `/api/auth/logout`
- Blueprint discovery and reading: `/api/blueprints`, discovery endpoints, and revision endpoints
- Blueprint lifecycle and revision operations: create, add revision, freeze, promote, activate, deprecate, and sunset
- Execution: execute a Blueprint or teacher tool, then retrieve an execution by ID

Consult `routes/api.php` for the authoritative route definitions.

## Design constraints

- Blueprint definitions and execution records have separate lifecycles.
- Executions reference a specific revision, preserving which behavior was run.
- Revision promotion moves forward to a newer revision; it is not a rollback mechanism.
- Concurrent writes must preserve lifecycle and execution-state invariants.

## License

See the repository's license and dependency notices before redistributing the application.
