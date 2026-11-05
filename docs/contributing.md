---
title: "Contributing"
description: "How the project is built, setting up a development environment, coding conventions, and making pull requests."
group: "Contributing"
order: 40
updated_at: "2026-11-04"
---
# Contributing

Thanks for helping improve Investbrain! This guide covers how the project is put together, how to set up a development environment, the conventions the codebase follows, and how to get a pull request merged.

- [Ways to contribute](#ways-to-contribute)
- [Before you start](#before-you-start)
- [How the project is built](#how-the-project-is-built)
- [Setting up a development environment](#setting-up-a-development-environment)
- [Running the tests](#running-the-tests)
- [Coding conventions](#coding-conventions)
- [Working on specific areas](#working-on-specific-areas)
- [Using AI coding assistants](#using-ai-coding-assistants)
- [Making a pull request](#making-a-pull-request)

## Ways to contribute

You don't have to write code to help. Some good places to start:

- **Report bugs** with clear steps to reproduce them
- **Improve the documentation** in this `docs` folder
- **Add translations** or fix existing ones in `lang/`

If you want to write code, you can:
- **Add a market data provider** (see [Developing market data providers](market-data-providers.md))
- **Improve the UI**, accessibility, or mobile layout
- **Fix bugs** from the [issue tracker](https://github.com/investbrainapp/investbrain/issues)

## Before you start

- **Search the issues** to see if someone is already working on it.
- **Open an issue first for anything big**, such as a new feature, a change to how gains or currencies are calculated, a database redesign, or a new dependency. Agreeing on the approach up front saves you from reworking a finished pull request.
- **Small fixes don't need an issue.** Typos, small bugs, and doc improvements can go straight to a pull request.
- **New dependencies need approval.** Don't add Composer or npm packages without discussing it in an issue first.

## How the project is built

Investbrain is a [Laravel](https://laravel.com) application.

| Area | Technology |
| --- | --- |
| Language | PHP 8.4 (the version the Docker image runs) with `declare(strict_types=1)` everywhere |
| Framework | Laravel 13 |
| Interface | [Livewire 4](https://livewire.laravel.com) with [Volt](https://livewire.laravel.com/docs/volt) single-file components, [Alpine.js](https://alpinejs.dev), and [Filament Tables](https://filamentphp.com/docs/tables) for data tables |
| Styling | [Tailwind CSS 4](https://tailwindcss.com) and [daisyUI 5](https://daisyui.com), built with [Vite](https://vitejs.dev) |
| Charts | [ApexCharts](https://apexcharts.com) |
| Authentication | [Fortify](https://laravel.com/docs/fortify) (login, registration, 2FA), [Socialite](https://laravel.com/docs/socialite) (social login), [Passport](https://laravel.com/docs/passport) (API tokens and OAuth) |
| AI | [Laravel AI SDK](https://github.com/laravel/ai) for AI chat, [Laravel MCP](https://github.com/laravel/mcp) for the MCP server |
| Import and export | [Laravel Excel](https://laravel-excel.com) |
| API filtering | [spatie/laravel-query-builder](https://spatie.be/docs/laravel-query-builder) |
| Database | PostgreSQL in production, in-memory SQLite in tests |
| Cache, sessions, queue | Redis |
| Tests | PHPUnit |

### Where things live

| Path | What's there |
| --- | --- |
| `app/Actions/` | Business logic shared between the web app, the API, and the MCP server (for example `Transaction/CreateTransaction.php`) |
| `app/Models/` | Eloquent models. `Transaction`, `Holding`, `Portfolio`, `Dividend`, `Split`, `MarketData`, and `CurrencyRate` hold most of the domain logic. |
| `app/Interfaces/MarketData/` | Market data providers and the fallback mechanism |
| `app/Http/ApiControllers/` | REST API controllers (routes in `routes/api.php`) |
| `app/Http/Controllers/` | Web controllers (routes in `routes/web.php`) |
| `app/Http/Resources/` | JSON shapes used by both the API and the MCP tools |
| `app/Mcp/` | The MCP server and its tools (route in `routes/ai.php`) |
| `app/Ai/Agents/` | AI chat agents |
| `app/Console/Commands/` | Artisan commands; the schedule is in `routes/console.php` |
| `app/Imports/`, `app/Exports/` | Spreadsheet import and export |
| `app/Livewire/Tables/` | Filament-powered holdings and transactions tables |
| `resources/views/` | Blade views and Volt components. Most interactive pieces, such as `portfolio/manage-portfolio-form.blade.php`, are Volt components with their PHP class at the top of the file. |
| `resources/views/components/ui/` | Reusable UI components (`<x-ui.button>`, `<x-ui.modal>`, and so on). Check here before writing new markup. |
| `lang/` | Translations (`en.json`, `es.json`, `fr.json`) |
| `database/` | Migrations, factories, and seeders |
| `tests/` | All tests, in a flat folder under the `Tests` namespace, plus `tests/Api` and `tests/Mcp` |
| `docker/` | The Dockerfile, entrypoint script, Nginx, and Supervisor configuration for the official image |
| `docs/` | This documentation |
| `docs/openapi.json` | The REST API specification |

## Setting up a development environment

These steps run the app directly on your machine with PHP, and use Docker only for PostgreSQL and Redis. That gives you the fastest edit-and-reload loop.

### Prerequisites

- **PHP 8.4** with the `gd`, `intl`, `mbstring`, `zip`, `pdo_pgsql`, and `pdo_sqlite` extensions. This is the version the official Docker image uses, so developing on it avoids surprises when your change runs in the container.
- **[Composer](https://getcomposer.org)**
- **Node.js 20 or newer** and npm
- **[Docker](https://docs.docker.com/engine/install/)**, for PostgreSQL and Redis (or install them locally if you prefer)
- **Git**

On macOS, [Herd](https://herd.laravel.com) or [Yerd](https://yerd.app/) installs PHP, Composer, and Node for you (choose PHP 8.4 in its settings). On Linux, install PHP and the extensions from your distribution's packages.

### 1. Fork and clone

Fork [investbrainapp/investbrain](https://github.com/investbrainapp/investbrain) on GitHub, then:

```bash
git clone https://github.com/<your-username>/investbrain.git
cd investbrain
git remote add upstream https://github.com/investbrainapp/investbrain.git
```

### 2. Install dependencies

```bash
composer install
npm install
```

### 3. Create your `.env` file

```bash
cp .env.example .env
```

Then change these values so the app talks to services on your machine instead of inside the Docker network:

```bash
APP_ENV=local
APP_DEBUG=true
APP_URL="http://localhost:8000"

DB_HOST=127.0.0.1
REDIS_HOST=127.0.0.1

MAIL_MAILER=log
```

### 4. Start PostgreSQL and Redis

The compose file doesn't publish either service to the host, so run separate containers for them:

```bash
docker run -d --name investbrain-dev-pgsql -p 127.0.0.1:5432:5432 -e POSTGRES_DB=investbrain -e POSTGRES_USER=investbrain -e POSTGRES_PASSWORD=investbrain postgres:15-alpine
```

```bash
docker run -d --name investbrain-dev-redis -p 127.0.0.1:6379:6379 redis:alpine
```

The database credentials match the defaults in `.env.example` (`investbrain` / `investbrain` / `investbrain`).

Use PostgreSQL for development rather than SQLite. Some features, such as search, rely on database full-text search that SQLite doesn't support.

### 5. Set up the application

```bash
php artisan key:generate
php artisan passport:keys
php artisan migrate
php artisan storage:link
```

Optionally, seed a test user (`test@example.com` with the password `password`) and a list of common symbols:

```bash
php artisan db:seed
php artisan db:seed --class=MarketDataSeeder
```

### Keeping your fork up to date

```bash
git fetch upstream
git checkout main
git merge upstream/main
composer install && npm install && php artisan migrate
```

### Testing the Docker image

If you're changing anything in `docker/`, build the image from your checkout:

```bash
docker build -f docker/Dockerfile -t investbrain:dev .
```

Then change `image:` in `docker-compose.yml` to `investbrain:dev` (don't commit that change) and run `docker compose up`.

### Developing a package locally

To work on a package Investbrain depends on, such as `investbrainapp/frankfurter-client`, alongside the app, clone it into the `packages/` folder (which Git ignores):

```bash
git clone https://github.com/investbrainapp/frankfurter-client.git packages/frankfurter-client
```

Then create a `composer.local.json` file in the project root. Git ignores this file too. The [Composer merge plugin](https://github.com/wikimedia/composer-merge-plugin) merges it into `composer.json` when Composer runs, so the main `composer.json` stays the same:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "packages/*",
            "options": { "symlink": true }
        }
    ],
    "require": {
        "investbrainapp/frankfurter-client": "@dev"
    }
}
```

Then update the package so Composer symlinks your local copy into `vendor/`:

```bash
composer update investbrainapp/frankfurter-client
```

Changes you make in `packages/frankfurter-client` now take effect straight away. When you're done, delete `composer.local.json` and run the same `composer update` command again to go back to the published version. Don't commit the `composer.lock` changes this makes.

## Running the tests

Tests use an in-memory SQLite database, the `array` cache, a synchronous queue, and the `fake` market data provider (see [`phpunit.xml`](https://github.com/investbrainapp/investbrain/blob/main/phpunit.xml)). They don't need PostgreSQL, Redis, or a network connection.

```bash
# The whole suite
php artisan test --compact

# One file
php artisan test --compact tests/TransactionsTest.php

# One test
php artisan test --compact --filter=test_cannot_sell_more_than_owned
```

There's no CI test run on pull requests at the moment, so **run the full suite before you open a pull request** and mention in the description that it passes.

### Writing tests

- Every change to behaviour needs a new or updated test. Pure copy, styling, or layout changes don't.
- Test the behaviour you changed and its important failure cases, not everything around it.
- Create test data with the model factories in `database/factories` (`User::factory()`, `Portfolio::factory()`, `Transaction::factory()`) and use their existing states where they fit.
- Put new test files directly in `tests/` with the `Tests` namespace, or in `tests/Api` or `tests/Mcp` for those areas. Name them after the feature, such as `SharePortfolioFormTest.php`.
- Never call real external services. Use `Http::fake()` for HTTP calls; the fake market data provider is already active.
- Look at a neighbouring test file before writing a new one and follow its style.

## Coding conventions

Follow the patterns already in the codebase. When you add or change a file, look at its siblings for structure, naming, and approach.

### Formatting

Investbrain uses [Laravel Pint](https://laravel.com/docs/pint) with the Laravel preset plus `declare_strict_types`. Before committing, format the PHP files you changed:

```bash
vendor/bin/pint --dirty
```

Editor settings (four-space indents, LF line endings, trailing newline) are in `.editorconfig`.

### PHP style

- Start every PHP file with `declare(strict_types=1);` (Pint adds it).
- Always use curly braces, even for one-line `if` bodies.
- Declare parameter and return types on every method.
- Use constructor property promotion: `public function __construct(public Portfolio $portfolio) {}`.
- Prefer PHPDoc blocks to inline comments, and use array shapes for array types (`@return array<string, mixed>`). Keep inline comments for genuinely tricky logic.
- Use descriptive names: `isRegisteredForDiscounts`, not `discount()`.
- Use TitleCase for enum cases.
- Generate new classes with Artisan (`php artisan make:model`, `make:migration`, `make:class`, and so on) with `--no-interaction`.
- Link to pages with named routes: `route('portfolio.show', ['portfolio' => $portfolio->id])`.

### Keep the web app, API, and MCP server in step

Creating, updating, and deleting portfolios, transactions, and holdings goes through the classes in `app/Actions/`. The API controllers and MCP tools call the same actions, and `app/Actions/Transaction/TransactionRules.php` holds the shared transaction validation rules. When you change a rule or a behaviour, change it there so all three entry points stay consistent, and add tests for each entry point that's affected.

### Translations

Wrap every user-facing string in `__()`, for example `{{ __('Create Portfolio') }}`. When you add or change a string, add it to **all three** files in `lang/` (`en.json`, `es.json`, and `fr.json`), not just English. If you can't translate it yourself, say so in the pull request so someone else can.

## Working on specific areas

**Interface (Blade and Volt).** Interactive pieces are Volt single-file components in `resources/views`. Look at an existing component, such as `resources/views/portfolio/manage-portfolio-form.blade.php`, and follow the same class-based style. Reuse the components in `resources/views/components/ui/` rather than writing raw markup. Authorize actions in the component (`$this->authorize('fullAccess', $portfolio)`), the same way you would in a controller. If your changes don't show up, or you see "Unable to locate file in Vite manifest", make sure `npm run dev` is running (or run `npm run build`).

**Database.** Create migrations with `php artisan make:migration`. They must run on both PostgreSQL (production) and SQLite (tests). New models need a factory, and a seeder where it's useful.

**REST API.** Routes are in `routes/api.php`, controllers in `app/Http/ApiControllers/`, and the JSON shapes in `app/Http/Resources/`. If you add or change an endpoint, field, filter, or sort, update [`docs/openapi.json`](https://github.com/investbrainapp/investbrain/blob/main/docs/openapi.json) and [`docs/rest-api.md`](rest-api.md), and add tests in `tests/Api/`. `tests/Api/OpenApiSpecTest.php` fails if `docs/openapi.json` is missing a route, or if its documented sorts and filters don't match what an endpoint actually allows.

**MCP server.** Tools are in `app/Mcp/Tools/` and are registered in `app/Mcp/Servers/InvestbrainServer.php`. Tools should call the shared actions and return the same resources as the API. Add tests in `tests/Mcp/` and update the tool table in [`docs/mcp.md`](mcp.md).

**Market data providers.** See [Developing market data providers](market-data-providers.md).

**Documentation.** User-facing changes should update the relevant page in `docs/` and, where it applies, the root `README.md`. Screenshots go in the [`docs/images/`](https://github.com/investbrainapp/investbrain/tree/main/docs/images) folder.

## Using AI coding assistants

You're welcome to use AI tools. You're still responsible for the code. Read and understand everything you submit, make sure it follows the conventions above, and run the tests yourself.

## Making a pull request

### 1. Create a branch

Branch from an up-to-date `main` and give it a short, descriptive name:

```bash
git checkout main
git pull upstream main
git checkout -b fix/same-day-sell-validation
```

### 2. Make focused changes

- Keep each pull request to one fix or feature. Several small pull requests are easier to review than one large one.
- Don't mix in unrelated reformatting, renames, or refactors.
- Don't commit local changes to `.env`, `docker-compose.yml`, or build output.

### 3. Check your work

Before pushing, make sure:

- [ ] `php artisan test --compact` passes
- [ ] `vendor/bin/pint --dirty` has been run
- [ ] New or changed behaviour has tests
- [ ] New strings are in `en.json`, `es.json`, and `fr.json`
- [ ] Docs, `README.md`, and `openapi.json` are updated if the change affects users or the API
- [ ] You've tried the change in the browser, if it touches the interface
- [ ] No new dependencies, unless they were agreed in an issue

### 4. Write good commit messages

Use a short summary line in the imperative mood, such as "Count same-day purchases when validating sell quantity", followed by a blank line and more detail if it's needed.

### 5. Open the pull request

Push your branch to your fork and open a pull request against `investbrainapp/investbrain:main`. In the description, include:

- **What** the change does and **why**
- A link to the related issue (for example `Fixes #123`)
- **How you tested it**, including confirmation that the test suite passes
- **Screenshots or a short recording** for interface changes
- Anything reviewers should look at closely, or any follow-up work you've left out

Mark the pull request as a draft if you'd like early feedback before it's finished.

### 6. Review

A maintainer will review your pull request and may ask for changes. Push follow-up commits to the same branch; there's no need to open a new pull request. Please be patient; this is a community project maintained in people's spare time.

