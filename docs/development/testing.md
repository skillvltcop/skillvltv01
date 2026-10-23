# Development and testing

## Requirements

- PHP 8.2 or newer compatible with the composer constraints.
- Composer 2.
- A supported database. Local tests default to in-memory SQLite; CI also runs the suite against MySQL 8.
- Node.js 22 and npm for frontend assets and teacher-tool JavaScript tests.

The application uses Laravel 12, Laravel Sanctum, Pest/PHPUnit, Vite, and Tailwind CSS.

## Local setup

From the repository root:

    composer install
    cp .env.example .env
    php artisan key:generate

Configure DB_CONNECTION and the database credentials in .env. For local SQLite, create the database file if your environment does not already create it, then configure DB_CONNECTION=sqlite and DB_DATABASE to the file path.

Run migrations and seed the built-in teacher tools:

    php artisan migrate
    php artisan db:seed

Build frontend assets:

    npm install
    npm run build

Start the local development stack:

    composer run dev

This command starts the Laravel development server, queue listener, log viewer, and Vite development server. The execution request path itself currently runs the Blueprint behavior synchronously through the application engine.

## First user and administrator

The seeder intentionally does not create a default user. Create the first account through the trusted provisioning process. To grant administrative access, follow the secure Tinker procedure in the security guide. Never commit a default password or seed a production super administrator automatically.

## Running tests

Run the PHP test suite:

    php artisan test

Or run Pest directly:

    vendor/bin/pest

Run the teacher-tool JavaScript tests:

    node --test tests/JavaScript/teacher-tools.test.js

Build the frontend:

    npm run build

## CI policy

GitHub Actions runs:
- JavaScript tests for teacher tools.
- PHP 8.2 tests with SQLite.
- PHP 8.2 tests with MySQL 8.

Both database jobs must pass before a PR is merged. MySQL covers locking and database behavior that may differ from SQLite. Some tests may be skipped on SQLite where the behavior requires MySQL semantics; a skip is not evidence that the MySQL-specific behavior passed.

## Authorization test strategy

Security tests should cover both allowed and denied cases:
- unauthenticated requests;
- owner versus another user;
- system-owned versus user-owned Blueprints;
- public versus private visibility;
- draft, active, deprecated, and sunset lifecycle states;
- frozen/current versus non-current revisions;
- missing, malformed, and valid authorization reasons;
- explicit admin permissions and super_admin-only role changes;
- audit event creation;
- teacher registry membership across discovery, API execution, and web view resolution;
- execution ownership and redaction after user deletion.

Do not weaken an invariant or change runtime behavior solely to satisfy a test without first confirming the intended domain contract. When a test expectation is wrong, update the test with evidence; when behavior changes, update tests and documentation together.
