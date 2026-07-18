# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What is Davis

Davis is a CalDAV/CardDAV/WebDAV server with an admin dashboard, built on **sabre/dav**, **Symfony 7**, and **Bootstrap 5**. It supports Basic, IMAP, and LDAP authentication. The upstream repo is `tchapi/davis`.

## Common Commands

```bash
# Install dependencies (dev)
composer install

# Run dev server
php -S localhost:8000 -t public

# Run all tests
./bin/phpunit

# Run a single test file
./bin/phpunit tests/Functional/DavTest.php

# Run a single test method
./bin/phpunit --filter testMethodName

# Lint (fix code style)
PHP_CS_FIXER_IGNORE_ENV=True ./vendor/bin/php-cs-fixer fix

# Database migrations
bin/console doctrine:migrations:migrate

# Extract translations
bin/console translation:extract en --force --domain=messages+intl-icu

# Generate API key
bin/console api:generate

# Sync birthday calendars
bin/console dav:sync-birthday-calendar
```

## Docker Stack

Docker files are in `docker/`. The default compose uses MariaDB + nginx + PHP-FPM:

```bash
cd docker && docker compose up -d
# With LDAP (overrides add OpenLDAP + phpLDAPadmin on port 9001):
cd docker && docker compose -f docker-compose.yml -f docker-compose-overrides.yml up -d
```

Alternative compose files exist for PostgreSQL (`docker-compose-postgresql.yml`), SQLite (`docker-compose-sqlite.yml`), and standalone with Caddy (`docker-compose-standalone.yml`).

## Architecture

### Request Flow

All DAV traffic enters through `DAVController` (`src/Controller/DAVController.php`) which bootstraps the sabre/dav server with the appropriate plugins and auth backend. The controller wires CalDAV, CardDAV, and WebDAV support based on env vars.

### Key Directories

- `src/Entity/` — Doctrine ORM entities mapping to sabre/dav database tables (User, Principal, Calendar, CalendarObject, AddressBook, Card, etc.)
- `src/Controller/Admin/` — Dashboard: UserController, CalendarController, AddressBookController, DashboardController, **GroupController** (`/groups`)
- `src/Controller/Api/` — REST API controller (`ApiController.php`), authenticated via `X-Davis-API-Token` header
- `src/Plugins/` — sabre/dav server plugins: BirthdayCalendarPlugin, DavisIMipPlugin (email invites), PublicAwareDAVACLPlugin
- `src/Services/` — Auth backends (BasicAuth, IMAPAuth, LDAPAuth), LDAPManager (CRUD users in LDAP), BirthdayService
- `src/Security/` — Symfony security: LoginFormAuthenticator (admin dashboard), ApiKeyAuthenticator (API), AdminUserProvider
- `migrations/` — Doctrine migrations (the schema matches sabre/dav tables with extensions)
- `templates/groups/` — Admin UI for group CRUD + members

### Groups and sharing (feat/carddav-sharing)

**Model A (current):** group principal + live membership; **not** N expanded instances per member.

- Group URI must be **flat**: `principals/group-<slug>` (`Principal::GROUP_URI_PREFIX`). Nested `principals/groups/...` breaks sabre's principal tree.
- Membership: `groupmembers` (same table as calendar-proxy delegees). Flag `principals.is_group` (+ optional `group_source` manual|ldap).
- Share with a group = one `calendarinstances` / `addressbookinstances` row with `principaluri` = group.
- Discovery lives in the **sabre fork** (`kalamarcito/dav`, branch `feat/carddav-sharing`): `getCalendarsForUser` / `getAddressBooksForUser` JOIN `groupmembers` (only `principals/group-%`, not proxy URIs).
- If the same resource is shared to the user **and** a group they belong to: **OR** permission bits (union / most permissive). Prefer the user instance for path/principaluri.
- On share add/update/revoke, Davis **increments calendar/addressbook synctoken** so clients re-PROPFIND privileges (Thunderbird no longer needs calendar re-add).
- Pure readonly shares must **not** advertise `{DAV:}write-properties` (sabre SharedCalendar/SharedAddressBook) or TB may show edit/delete UI even though unbind is denied.
- Translations: both `calendar.share_access.{2,3}` and `addressbook.share_access.{2,3}` (2=readonly, 3=read/write).
- **LDAP group sync is not implemented yet** (phase 2).

### CardDAV client quirks (share permissions)

- **Thunderbird** — native CardDAV **and** the **CardBook** add-on — treats a
  shared address book as **binary readonly/read-write** and ignores granular
  privileges. Given *any* write privilege (even just `create`), it marks the
  whole book editable and goes **optimistic** on every operation; the server then
  denies (403) the ones without a granular privilege and TB keeps the stale local
  state until you remove and re-add the book. Practical rule: with Thunderbird,
  share **readonly or full read-write**, never partial.
- A share with **zero** write privileges (pure readonly) works fine in TB — it
  reflects server changes on sync without re-adding.
- **Evolution** reads the granular `current-user-privilege-set` and honours each
  bit; it reflects permission changes as soon as the CardDAV account is reloaded.
- The server is correct either way: it advertises exactly the granular privileges
  and persists only permitted edits (verified end-to-end with curl).

### Authentication

Three auth methods for DAV clients, selected via `AUTH_METHOD` env var:
- **Basic** — credentials checked against the `users` table
- **IMAP** — credentials validated against an IMAP server
- **LDAP** — credentials validated against an LDAP directory

The admin dashboard uses a separate login (form-based, `ADMIN_LOGIN`/`ADMIN_PASSWORD` env vars, plaintext compare — not the `users` table). The API uses a token (`API_KEY` env var).

### Configuration

All runtime configuration is via environment variables (see `.env` for defaults, override with `.env.local`). Key parameters are wired through `config/services.yaml`. The `ENV_DIR` variable can redirect where dotenv files are loaded from.

### Testing

Tests are in `tests/Functional/` and use Symfony's WebTestCase (browser-kit). PHPUnit config is in `phpunit.xml.dist`. Tests run with `APP_ENV=test`.

### Code Style

PHP-CS-Fixer with `@Symfony` ruleset (config in `.php-cs-fixer.php`). Always run the fixer before committing PHP changes.

### sabre/dav Fork

The `composer.json` points to a fork of sabre/dav (`kalamarcito/dav`) via a VCS repository, using `@dev` stability, pinned by commit reference in `composer.lock`. This fork contains the CardDAV/CalDAV group-sharing support (branch `feat/carddav-sharing`). See that repo's own `CLAUDE.md` for fork internals.
