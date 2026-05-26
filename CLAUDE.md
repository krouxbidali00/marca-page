# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Marca-Page — a personal book library web app: catalogue books from the Google Books
API, track reading status / progress / rating, keep notes and quotes, organise books
on named shelves. Multi-user. Server-rendered Symfony app (Twig + Turbo), no SPA.

Stack: Symfony 8, PHP 8.4, FrankenPHP, PostgreSQL 16, Doctrine ORM 3, AssetMapper +
`symfonycasts/sass-bundle`, Stimulus + Turbo, Bootstrap 5.3 (loaded via CDN).

## Everything runs in the `php` container

**All `php`, `composer`, `bin/console`, `node`, `phpunit` commands run inside the
`php` Docker service** — prefix with `docker compose exec php`.

```bash
docker compose build && docker compose up -d          # php (FrankenPHP :8080/:8443), database, mailer (Mailpit)
docker compose exec php composer install
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
```

App: http://localhost:8080. FrankenPHP dev mode watches files and hot-reloads.

Common console commands: `bin/console debug:router`, `debug:container`, `make:migration`,
`asset-map:compile` (prod only — in dev AssetMapper serves on the fly; if `public/assets/`
ever has compiled files, `rm -rf public/assets` so changes are picked up).

## Tests

PHPUnit 13. The test DB (`app_test`) must exist with the schema applied:

```bash
docker compose exec php bin/console --env=test doctrine:database:create --if-not-exists
docker compose exec php bin/console --env=test doctrine:migrations:migrate --no-interaction
docker compose exec php bin/console sass:build                        # required: functional tests resolve var/sass/app.output.css
docker compose exec php bin/phpunit                                   # all tests
docker compose exec php bin/phpunit tests/Controller/BookImportTest.php   # one file
docker compose exec php bin/phpunit --filter testImportAddsBookToLibrary  # one test
```

`dama/doctrine-test-bundle` wraps every test in a rolled-back transaction, so the
test DB is never polluted. Functional tests extend `WebTestCase` and persist their
own `User` then `$client->loginUser($user)`. In the test env, passwords use the
`plaintext` hasher (see `config/packages/security.yaml`).

## Lint

PHP_CodeSniffer, PSR-12 — config in `phpcs.xml.dist`, applied to `src/` and `tests/`
(line-length soft limit raised to 160):

```bash
docker compose exec php composer lint        # check
docker compose exec php composer lint:fix    # auto-fix (phpcbf)
```

`.editorconfig` sets the broader formatting baseline (4-space indent, LF, trailing
newline; 2-space for compose files).

## CI

`.github/workflows/ci.yml` runs two jobs — **Lint (PHPCS)** and **Tests (PHPUnit)** — on
pushes to `main` and on pull requests. CI runs PHP 8.4 natively (`shivammathur/setup-php`)
with a `postgres:16-alpine` service for the test job; it does not build the FrankenPHP image.

## Architecture

**Domain model.** `User` owns `Book`s and `Shelf`s. `Book` is the central entity: it
holds the Google Books metadata (title, authors, ISBNs, thumbnail, …) *and* the user's
personal state (reading status + current page, purchase status + format + date, rating,
free-text notes, assigned shelf). `Quote` belongs to a `Book` (cascade-removed). `Shelf`
is just a name owned by a `User`; deleting a shelf nulls `Book.shelf` (`onDelete: SET NULL`).
`Book` has denormalised lowercase `authorsText` / `categoriesText` columns kept in sync
by `setAuthors()` / `setCategories()` — used for `LIKE` search and sorting. `ReadingStatus`
and `PurchaseStatus` are string-backed enums with `label()` and `pillClass()` helpers
consumed directly by Twig.

**Google Books integration.** `GoogleBooksClientInterface` → `GoogleBooksClient` (real
HTTP client; reads `GOOGLE_BOOKS_API_KEY` env var, falls back to anonymous low-quota
requests; retries broad queries with an `intitle:` qualifier on 5xx; restricts results to
`GOOGLE_BOOKS_LANG_RESTRICT` via the `langRestrict` param when that env var is non-empty).
`CachedGoogleBooksClient` decorates it (`#[AsDecorator]`) to cache `search()` / `getVolume()`
results (see **Caching**). In the test env `services.yaml` swaps in
`App\Tests\Double\FakeGoogleBooksClient`. `BookImporter` maps a `GoogleBookResult` DTO into a
`Book` and dedupes on `(owner, googleVolumeId)`.
`CoverThemePicker` derives a stable theme `0..11` from the title (`crc32 % 12`) so books
without a thumbnail render a palette-matching CSS cover (`.cover--theme-N`).

**Controllers / routing.** Attribute-based routing, one controller per area
(`HomeController`, `LibraryController`, `BookController`, `BookActionController`,
`BookSearchController`, `QuoteController`, `ShelfController`, `RegistrationController`,
`SecurityController`). `BookActionController` is `#[Route('/books/{id}', methods: ['POST'])]`
with sub-routes for each editable facet (reading-progress, purchase, rating, notes, shelf);
the rating and notes actions also return JSON when `X-Requested-With` is set.

**Authorization.** Form login (`config/packages/security.yaml`): `access_control` makes
everything except `/`, `/login`, `/register` require `ROLE_USER`, and controllers also
declare `#[IsGranted('ROLE_USER')]`. Per-book ownership goes through `BookVoter` — every
book-scoped action calls `denyAccessUnlessGranted(BookVoter::OWN, $book)`. Mutating
endpoints additionally validate a per-book CSRF token (`book_action_<id>`, `delete_book_<id>`,
`import_book`, `create_shelf`); CSRF is **session-based** so forms work without JS.

**Library listing.** `LibraryFilter` (readonly DTO) is built from request query params via
`fromRequest()` and validated/clamped there; it drives `BookRepository::paginateForLibrary()`,
which returns a generic `Page<Book>` value object (page math, ranges, prev/next). `toQueryParams()`
rebuilds the current filter state for pagination links and "remove this filter" chips.

**Caching.** Three filesystem pools (`config/packages/cache.yaml`): `cache.google_books`
(24 h TTL, untagged) and the tag-aware `cache.library` and `cache.book_detail` (1 h TTL each).
`CachedGoogleBooksClient` caches Google Books `search()` / `getVolume()` (xxh128 keys).
`BookRepository::paginateForLibrary()` is served through the `library` pool, keyed by
`LibraryFilter::cacheSignature()` (deterministic, order-insensitive) and tagged
`user.{id}.library`; `findCachedForDetail()` caches a book's detail tagged `book.{id}`. The
`LibraryCacheInvalidator` facade (`invalidateLibrary()` / `invalidateBook()`) is called from
mutating book / shelf / quote actions and on import to drop the relevant tags. `BookVoter`
compares owner **by ID** (not object identity) so entities served from cache still pass
authorization. `HomeController` sets a public `Cache-Control` (`setSharedMaxAge(3600)`,
`setMaxAge(600)`) for anonymous visitors.

**Frontend.** AssetMapper + importmap (`importmap.php`) — no JS bundler/build step. Stimulus
controllers live in `assets/controllers/` (`password-toggle`, `password-strength`, `rating`,
`notes-autosave`, `book-search` live search). Turbo is enabled (`turbo-core`, eager fetch).
SCSS is compiled by `symfonycasts/sass-bundle`; **all custom styles live in `assets/styles/*.scss`**
(partials `@use`d from `app.scss`) — no inline styles in Twig templates. Twig: `templates/base.html.twig`
is the layout; `templates/_partials/` holds shared fragments (navbars, badges, pagination, stars,
flash messages); fragment templates prefixed `_` (e.g. `book/_search_results.html.twig`,
`library/_grid.html.twig`) are rendered for Turbo/AJAX partial updates.

## Conventions

- Code, identifiers, and commit messages in English. UI text and `.md` docs in French.
- Don't commit `.env.local` / secrets. Put a real `GOOGLE_BOOKS_API_KEY` in `.env.local`,
  never in `.env` (which is versioned).
- Commit messages here do **not** include a Claude / co-author trailer.
