# Marca-Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build Marca-Page, a multi-user personal library app on Symfony 8 — import books from the Google Books API, track reading/purchase status, notes, quotes and shelves, with the five provided mockups ported to clean Twig + SCSS (no inline CSS) and run entirely through a dedicated FrankenPHP Docker container.

**Architecture:** Symfony 8 with AssetMapper. One `Book` row per user (Google Books metadata copied at import time + the user's own data). Custom styles compiled from SCSS via `symfonycasts/sass-bundle`, layered on top of Bootstrap by overriding `--bs-*` CSS variables (same technique as the mockups). Interactivity via Stimulus controllers. FrankenPHP serves the app; all `composer` / `bin/console` / `bin/phpunit` / node commands run via `docker compose exec -T php …`.

**Tech Stack:** PHP 8.4, Symfony 8 (security-bundle, form, validator, http-client, twig, asset-mapper, stimulus-bundle, ux-turbo), Doctrine ORM 3 + migrations, PostgreSQL 16, FrankenPHP, `symfonycasts/sass-bundle`, Bootstrap 5.3 + Bootstrap Icons, PHPUnit 13.

**Reference material:** the five mockups (`home.html`, `login.html`, `signup.html`, `books-list.html`, `book-detail.html`) provided in the brainstorming conversation are the visual source of truth. The spec lives at `docs/superpowers/specs/2026-05-12-marca-page-design.md`.

**Global rules for every task:**
- Run **all** PHP/node/composer commands inside the app container: `docker compose exec -T php <cmd>`. The container service is named `php`.
- No `<style>` tags and no `style="…"` attributes anywhere in `templates/`. Anything not provided by Bootstrap goes into `assets/styles/*.scss`. Dynamic per-book cover colours use the `cover--theme-N` classes, never inline styles.
- Code, identifiers, comments and commit messages in English. Branch/commit style: conventional commits (`feat:`, `fix:`, `chore:`, `test:`, `docs:`).
- TDD where it pays off (services, repository filters, functional flows). Templates/SCSS are verified by loading pages in the running container.
- Commit after each task.

---

## File Structure

**Docker / infra**
- `Dockerfile` — FrankenPHP multi-stage image (PHP 8.4 + extensions + Composer + Node), `frankenphp_dev` + `frankenphp_prod` targets.
- `.dockerignore`
- `frankenphp/Caddyfile`, `frankenphp/conf.d/10-app.ini`, `frankenphp/conf.d/20-app.dev.ini`, `frankenphp/docker-entrypoint.sh`
- `compose.yaml` (modify — add `php` service), `compose.override.yaml` (modify — dev overrides for `php`)
- `.env` (modify — `GOOGLE_BOOKS_API_KEY`, `DATABASE_URL`, `APP_SECRET`, `CADDY_*`/`SERVER_NAME`)
- `README.md` (create — how to run via Docker)

**Front: SCSS** (all under `assets/styles/`)
- `app.scss` (entrypoint, replaces `app.css` which is deleted)
- `_tokens.scss`, `_typography.scss`, `_buttons.scss`, `_navbar.scss`, `_book-cover.scss`, `_book-card.scss`, `_pills.scss`, `_components.scss`, `_filters.scss`, `_book-detail.scss`, `_landing.scss`, `_auth.scss`

**Front: Stimulus** (all under `assets/controllers/`)
- `rating_controller.js`, `reading-status_controller.js`, `notes-autosave_controller.js`, `book-search_controller.js`, `password-strength_controller.js`, `password-toggle_controller.js`, `view-toggle_controller.js`, `chip-remove_controller.js`
- delete `hello_controller.js`; keep `csrf_protection_controller.js`

**PHP: domain**
- `src/Entity/User.php`, `src/Entity/Book.php`, `src/Entity/Quote.php`, `src/Entity/Shelf.php`
- `src/Enum/ReadingStatus.php`, `src/Enum/PurchaseStatus.php`
- `src/Repository/UserRepository.php`, `src/Repository/BookRepository.php`, `src/Repository/QuoteRepository.php`, `src/Repository/ShelfRepository.php`
- `migrations/VersionXXXXXXXXXXXXXX.php` (generated)

**PHP: services / DTO / pagination / security**
- `src/Dto/GoogleBookResult.php`, `src/Dto/LibraryFilter.php`
- `src/Service/GoogleBooksClient.php`, `src/Service/GoogleBooksException.php`, `src/Service/BookImporter.php`, `src/Service/CoverThemePicker.php`
- `src/Pagination/Page.php`
- `src/Security/BookVoter.php`
- `src/Form/RegistrationFormType.php`
- `config/packages/security.yaml` (modify), `config/services.yaml` (modify), `config/routes.yaml` (annotations used — no change needed)

**PHP: controllers**
- `src/Controller/HomeController.php`, `src/Controller/SecurityController.php`, `src/Controller/RegistrationController.php`, `src/Controller/LibraryController.php`, `src/Controller/BookSearchController.php`, `src/Controller/BookController.php`, `src/Controller/BookActionController.php`, `src/Controller/QuoteController.php`, `src/Controller/ShelfController.php`

**Templates** (under `templates/`)
- `base.html.twig` (rewrite)
- `_partials/navbar_public.html.twig`, `_partials/navbar_app.html.twig`, `_partials/footer.html.twig`, `_partials/footer_compact.html.twig`, `_partials/flash_messages.html.twig`, `_partials/book_cover.html.twig`, `_partials/book_card.html.twig`, `_partials/pagination.html.twig`, `_partials/reading_status_badge.html.twig`, `_partials/purchase_status_badge.html.twig`, `_partials/stars.html.twig`
- `home/index.html.twig`
- `security/login.html.twig`
- `registration/register.html.twig`
- `library/index.html.twig`, `library/_filters.html.twig`, `library/_grid.html.twig`, `library/_active_chips.html.twig`
- `book/show.html.twig`, `book/_tab_summary.html.twig`, `book/_tab_notes.html.twig`, `book/_tab_quotes.html.twig`, `book/_tab_details.html.twig`, `book/_similar.html.twig`
- `book/search.html.twig`, `book/_search_results.html.twig`

**Tests**
- `tests/Service/CoverThemePickerTest.php`, `tests/Service/GoogleBooksClientTest.php`, `tests/Dto/LibraryFilterTest.php`
- `tests/Controller/HomeControllerTest.php`, `tests/Controller/SecurityFlowTest.php`, `tests/Controller/RegistrationControllerTest.php`, `tests/Controller/LibraryAccessTest.php`, `tests/Controller/BookImportTest.php`
- `tests/Factory/` helpers if needed (plain helper, no foundry)

---

## Phase 0 — Docker (FrankenPHP) infrastructure

### Task 0.1: Add the FrankenPHP Dockerfile and Caddy config

**Files:**
- Create: `Dockerfile`, `.dockerignore`, `frankenphp/Caddyfile`, `frankenphp/conf.d/10-app.ini`, `frankenphp/conf.d/20-app.dev.ini`, `frankenphp/docker-entrypoint.sh`

- [ ] **Step 1: Create `Dockerfile`** (based on the official `dunglas/symfony-docker` template, trimmed):

```dockerfile
#syntax=docker/dockerfile:1

FROM dunglas/frankenphp:1-php8.4 AS frankenphp_upstream

# --- base stage ------------------------------------------------------------
FROM frankenphp_upstream AS frankenphp_base

WORKDIR /app

# persistent / runtime deps
RUN apt-get update && apt-get install -y --no-install-recommends \
    acl file gettext git curl \
    && rm -rf /var/lib/apt/lists/*

RUN set -eux; \
    install-php-extensions \
      @composer \
      apcu \
      intl \
      opcache \
      pdo_pgsql \
      zip

# Node.js (for asset tooling)
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --link frankenphp/conf.d/10-app.ini $PHP_INI_DIR/conf.d/
COPY --link --chmod=755 frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY --link frankenphp/Caddyfile /etc/frankenphp/Caddyfile

ENTRYPOINT ["docker-entrypoint"]

HEALTHCHECK --start-period=60s CMD curl -f http://localhost:2019/metrics || exit 1
CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile" ]

# --- dev stage -------------------------------------------------------------
FROM frankenphp_base AS frankenphp_dev

ENV APP_ENV=dev XDEBUG_MODE=off
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY --link frankenphp/conf.d/20-app.dev.ini $PHP_INI_DIR/conf.d/
RUN set -eux; install-php-extensions xdebug
CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--watch" ]

# --- prod stage ------------------------------------------------------------
FROM frankenphp_base AS frankenphp_prod

ENV APP_ENV=prod
ENV FRANKENPHP_CONFIG="import worker.Caddyfile"
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --link composer.* symfony.* ./
RUN set -eux; composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress
COPY --link . ./
RUN rm -Rf frankenphp/
RUN set -eux; \
    mkdir -p var/cache var/log; \
    composer dump-autoload --classmap-authoritative --no-dev; \
    composer dump-env prod; \
    composer run-script --no-dev post-install-cmd; \
    chmod +x bin/console; sync;
```

- [ ] **Step 2: Create `frankenphp/Caddyfile`:**

```caddyfile
{
	{$CADDY_GLOBAL_OPTIONS}
	frankenphp {
		{$FRANKENPHP_CONFIG}
	}
}

{$CADDY_EXTRA_CONFIG}

{$SERVER_NAME:localhost} {
	log {
		{$CADDY_SERVER_LOG_OPTIONS}
		output stderr
		format filter {
			wrap console
			fields {
				uri query {
					replace authorization REDACTED
				}
			}
		}
	}

	root /app/public
	encode zstd br gzip

	php_server {
		try_files {path} index.php
	}
}
```

- [ ] **Step 3: Create `frankenphp/conf.d/10-app.ini`:**

```ini
expose_php = 0
date.timezone = UTC
apc.enable_cli = 1
session.use_strict_mode = 1
zend.detect_unicode = 0

; https://symfony.com/doc/current/performance.html
realpath_cache_size = 4096K
realpath_cache_ttl = 600
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.memory_consumption = 256
opcache.enable_file_override = 1
```

- [ ] **Step 4: Create `frankenphp/conf.d/20-app.dev.ini`:**

```ini
; See https://docs.frankenphp.dev/config/#caddyfile-config
opcache.validate_timestamps = 1
opcache.revalidate_freq = 0

; https://xdebug.org/docs/all_settings#mode
xdebug.mode = ${XDEBUG_MODE}
```

- [ ] **Step 5: Create `frankenphp/docker-entrypoint.sh`:**

```sh
#!/bin/sh
set -e

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
	if [ -z "$(ls -A 'vendor/' 2>/dev/null)" ]; then
		composer install --prefer-dist --no-progress --no-interaction
	fi

	if grep -q DATABASE_URL .env; then
		echo 'Waiting for database to be ready...'
		ATTEMPTS_LEFT_TO_REACH_DATABASE=60
		until [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ] || DATABASE_ERROR=$(php bin/console dbal:run-sql -q "SELECT 1" 2>&1); do
			if [ $? -eq 255 ]; then break; fi
			sleep 1
			ATTEMPTS_LEFT_TO_REACH_DATABASE=$((ATTEMPTS_LEFT_TO_REACH_DATABASE - 1))
			echo "Still waiting for database to be ready... $ATTEMPTS_LEFT_TO_REACH_DATABASE attempts left."
		done

		if [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ]; then
			echo "The database is not up or not reachable: $DATABASE_ERROR"
		else
			echo 'The database is now ready and reachable'
		fi

		if [ "$( find ./migrations -iname '*.php' -print -quit )" ]; then
			php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing
		fi
	fi

	if [ "$APP_ENV" != 'prod' ]; then
		composer dump-autoload --classmap-authoritative
	fi
fi

exec docker-php-entrypoint "$@"
```

- [ ] **Step 6: Create `.dockerignore`:**

```
.git/
.github/
.idea/
docs/
node_modules/
var/
vendor/
.dockerignore
.editorconfig
.env.local
.env.*.local
compose*.yaml
Dockerfile
```

- [ ] **Step 7: Commit**

```bash
git add Dockerfile .dockerignore frankenphp/
git commit -m "chore: add FrankenPHP Docker image and Caddy config"
```

### Task 0.2: Wire the `php` service into compose and adjust env

**Files:**
- Modify: `compose.yaml`, `compose.override.yaml`, `.env`

- [ ] **Step 1: Edit `compose.yaml`** — add the `php` service and volumes. Resulting file:

```yaml
services:
  php:
    image: marca-page-php
    build:
      context: .
      target: frankenphp_dev
    restart: unless-stopped
    environment:
      SERVER_NAME: ${SERVER_NAME:-localhost}, php:80
      DATABASE_URL: postgresql://${POSTGRES_USER:-app}:${POSTGRES_PASSWORD:-!ChangeMe!}@database:5432/${POSTGRES_DB:-app}?serverVersion=${POSTGRES_VERSION:-16}&charset=utf8
      MAILER_DSN: smtp://mailer:1025
    volumes:
      - caddy_data:/data
      - caddy_config:/config
    ports:
      - "8080:80"
      - "8443:443"
      - "8443:443/udp"
    depends_on:
      database:
        condition: service_healthy

###> doctrine/doctrine-bundle ###
  database:
    image: postgres:${POSTGRES_VERSION:-16}-alpine
    environment:
      POSTGRES_DB: ${POSTGRES_DB:-app}
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD:-!ChangeMe!}
      POSTGRES_USER: ${POSTGRES_USER:-app}
    healthcheck:
      test: ["CMD", "pg_isready", "-d", "${POSTGRES_DB:-app}", "-U", "${POSTGRES_USER:-app}"]
      timeout: 5s
      retries: 5
      start_period: 60s
    volumes:
      - database_data:/var/lib/postgresql/data:rw
###< doctrine/doctrine-bundle ###

volumes:
  caddy_data:
  caddy_config:
###> doctrine/doctrine-bundle ###
  database_data:
###< doctrine/doctrine-bundle ###
```

- [ ] **Step 2: Edit `compose.override.yaml`** — add a dev bind-mount + xdebug toggle for `php`:

```yaml
services:
  php:
    build:
      context: .
      target: frankenphp_dev
    volumes:
      - ./:/app
      - ./frankenphp/Caddyfile:/etc/frankenphp/Caddyfile:ro
    environment:
      MERCURE_EXTRA_DIRECTIVES: demo
      XDEBUG_MODE: "${XDEBUG_MODE:-off}"
    extra_hosts:
      - host.docker.internal:host-gateway
    tty: true

###> doctrine/doctrine-bundle ###
  database:
    ports:
      - "5432"
###< doctrine/doctrine-bundle ###

###> symfony/mailer ###
  mailer:
    image: axllent/mailpit
    ports:
      - "1025"
      - "8025"
    environment:
      MP_SMTP_AUTH_ACCEPT_ANY: 1
      MP_SMTP_AUTH_ALLOW_INSECURE: 1
###< symfony/mailer ###
```

- [ ] **Step 3: Edit `.env`** — under the framework section set a dev secret, and add the Google Books key block; the `DATABASE_URL` in `.env` stays as the local default but is overridden by the `php` service env. Add:

```dotenv
###> app/google-books ###
# Optional; leave empty to use anonymous (low-quota) requests.
GOOGLE_BOOKS_API_KEY=
###< app/google-books ###
```

Also set `APP_SECRET=` to a generated value (e.g. `openssl rand -hex 16`) — run `docker compose exec -T php php -r 'echo bin2hex(random_bytes(16));'` after the container is up, or just set a static dev value now.

- [ ] **Step 4: Build and start**

```bash
docker compose build php
docker compose up -d
docker compose exec -T php php -v
```
Expected: image builds; `php -v` prints PHP 8.4.x from inside the container.

- [ ] **Step 5: Smoke-test the framework**

```bash
docker compose exec -T php composer install
docker compose exec -T php php bin/console about
```
Expected: `bin/console about` prints Symfony 8 environment info. Visiting `http://localhost:8080/` should show the Symfony welcome page (no routes yet) or a 404 — either is fine at this stage.

- [ ] **Step 6: Commit**

```bash
git add compose.yaml compose.override.yaml .env
git commit -m "chore: add php (FrankenPHP) service to docker compose"
```

---

## Phase 1 — SCSS pipeline + base layout + design tokens

### Task 1.1: Install and wire `symfonycasts/sass-bundle`

**Files:**
- Modify: `composer.json`/`composer.lock` (via composer), `config/bundles.php` (auto), `config/packages/sass.yaml` (auto), `assets/app.js`, `assets/styles/app.scss` (create), delete `assets/styles/app.css`

- [ ] **Step 1: Require the bundle (inside the container)**

```bash
docker compose exec -T php composer require symfonycasts/sass-bundle
```
Expected: bundle installed, `config/packages/sass.yaml` created, Sass binary downloaded on first build.

- [ ] **Step 2: Create `assets/styles/app.scss`** with just the entrypoint imports (partials added in 1.2):

```scss
@use 'tokens';
@use 'typography';
@use 'buttons';
@use 'navbar';
@use 'book-cover';
@use 'book-card';
@use 'pills';
@use 'components';
@use 'filters';
@use 'book-detail';
@use 'landing';
@use 'auth';
```

- [ ] **Step 3: Delete `assets/styles/app.css`**, and in `assets/app.js` change the import from `./styles/app.css` to `./styles/app.scss`.

- [ ] **Step 4: Build the assets to verify Sass compiles**

```bash
docker compose exec -T php php bin/console sass:build
docker compose exec -T php php bin/console asset-map:compile
```
Expected: no errors (empty partials are OK once they exist; for now create empty `_tokens.scss` etc. or do 1.2 first — recommended to do 1.2 before running this step).

- [ ] **Step 5: Commit** (after 1.2 partials exist)

```bash
git add composer.json composer.lock config/ assets/app.js assets/styles/
git rm assets/styles/app.css
git commit -m "feat: compile custom styles from SCSS via sass-bundle"
```

### Task 1.2: Port the mockup CSS into SCSS partials

**Files:** create all `assets/styles/_*.scss` listed in the File Structure.

The mockups define everything via CSS-variable overrides + plain class rules — copy those rules verbatim into the matching partial, splitting by responsibility. Concretely:

- [ ] **Step 1: `_tokens.scss`** — the `:root { … }` block. Merge the `:root` blocks from all five mockups (they are nearly identical). It must contain: `--bs-primary` and `--bs-primary-rgb`; the custom palette (`--rose-soft #F8C8DC`, `--rose-powder #FCE4EC`, `--lavande #E0D4F7`, `--peche #FFE5B4`, `--sauge #C8E6D0`, `--ink #3A2A33`, `--mute #8A7480`, `--line #F3E3EA`, `--cream #FFF9FB`); `--bs-body-bg`, `--bs-body-color`(+`-rgb`), `--bs-secondary-color`, `--bs-border-color`, `--bs-border-color-translucent`; the `--bs-border-radius*` scale; `--bs-link-*`; `--bs-focus-ring-color`/`-width`; `--bs-box-shadow-sm/-/-lg`. Also the `html, body { background: var(--cream); color: var(--ink); }` and `body { font-family: 'Inter', … }` rules belong here or in `_typography.scss` — put them in `_typography.scss`.

- [ ] **Step 2: `_typography.scss`** — `html, body`, `body` font, `.font-serif`, `.text-mute`, `.text-ink`, `.eyebrow`, `.grain` may go here or `_components.scss` (put `.grain` in `_components.scss`).

- [ ] **Step 3: `_buttons.scss`** — `.btn-primary`, `.btn-outline-primary`, `.btn-soft`(+`:hover`), `.btn-link` (the `--bs-btn-*` override blocks from the mockups).

- [ ] **Step 4: `_navbar.scss`** — `.navbar-mr`(+`.nav-link` states), `.brand-mark`.

- [ ] **Step 5: `_book-cover.scss`** — `.cover`, `.cover .spine`, `.cover::after`, `.cover .deco`, `.cover .body`, `.cover .title-c`, `.cover .author-c`, `.cover .pub`, `.cover-stage`(+`::before`), `.sticky-cover` (move to `_book-detail.scss`), plus a `.cover-img` rule (`width:100%; height:100%; object-fit:cover; display:block;`) and **the 12 theme classes**. Each `cover--theme-N` sets `background` (the dark base colour) and contains a `.deco` background gradient — derived from the per-book inline styles in the mockups. Use this fixed list (index → base colour / deco gradient), reproducing the mockup palette:

  | N | base | deco gradient |
  |---|------|---------------|
  | 0 | `#3A2A33` | `radial-gradient(circle at 70% 25%, rgba(232,155,184,.35), transparent 60%), linear-gradient(180deg,#3A2A33,#5A3A4A)` |
  | 1 | `#E0D4F7` | `linear-gradient(180deg,#E0D4F7,#C9B9EB)` (this theme also sets `.cover .body { color:#3A2A33 }` and dimmed `.author-c`) |
  | 2 | `#8A3A5C` | `radial-gradient(circle at 50% 20%, rgba(255,229,180,.25), transparent 60%), linear-gradient(180deg,#8A3A5C,#6B2A48)` |
  | 3 | `#2E5C3E` | `radial-gradient(circle at 30% 80%, rgba(200,230,208,.35), transparent 60%), linear-gradient(180deg,#2E5C3E,#1E4A2E)` |
  | 4 | `#FFE5B4` | `linear-gradient(180deg,#FFE5B4,#F5C97D)` (dark text) |
  | 5 | `#4A3C7A` | `radial-gradient(circle at 70% 20%, rgba(224,212,247,.4), transparent 60%), linear-gradient(180deg,#4A3C7A,#332A5A)` |
  | 6 | `#7A4A3A` | `linear-gradient(180deg,#7A4A3A,#5A3322)` |
  | 7 | `#1E3A5F` | `linear-gradient(180deg,#1E3A5F,#0F2540)` |
  | 8 | `#5C2E2E` | `linear-gradient(180deg,#5C2E2E,#3D1E1E)` |
  | 9 | `#F8C8DC` | `linear-gradient(180deg,#F8C8DC,#E89BB8)` (dark text) |
  | 10 | `#2A4A3E` | `linear-gradient(180deg,#2A4A3E,#1A2F28)` |
  | 11 | `#C8E6D0` | `linear-gradient(180deg,#C8E6D0,#9DC8AB)` (text `#2E5C3E`) |

  Implement with a Sass map + `@each` to keep it DRY. Light themes (1, 4, 9, 11) get a `&.cover--light .body { color: #3A2A33; }` style applied via the same loop (store a `light` flag in the map). Also add **size variants** as classes (no inline width/height): `.cover--xs` (≈90×130 — similar-books thumb), `.cover--sm` (≈120×170 — CTA), `.cover--md` (≈160–210 — hero/auth float), `.cover--lg` (`aspect-ratio:2/3` — grid card), `.cover--hero` (`aspect-ratio:2/3; max-width:340px` — detail page). Rotation variants for the decorative stacks: `.cover--rot-left` (`rotate(-7deg)`), `.cover--rot-right` (`rotate(6deg)`), etc. — define a small set used by the landing/auth pages.

- [ ] **Step 6: `_book-card.scss`** — `.book-card`(+`:hover`, `:hover .cover`), `.sim-card`(+`:hover`) (or keep `.sim-card` in `_book-detail.scss`).

- [ ] **Step 7: `_pills.scss`** — `.pill`, `.pill-lavande/-peche/-sauge/-rose/-outline`, `.chip`, `.chip-active`, `.chip .x`(+`:hover`). Include a small-pill modifier (`.pill--sm`) for the inline `style="font-size:.7rem; padding:.2rem .55rem"` cases in the mockups.

- [ ] **Step 8: `_components.scss`** — `.stars i`/`.stars .bi-star`, `.avatar` (+ size modifiers `.avatar--sm` 28px, `.avatar--lg` 56px), `.grain`, `.blob`, `.step-num`, `.scroll-row`(+webkit scrollbar).

- [ ] **Step 9: `_filters.scss`** — `.search-input`(+`:focus`), `.search-wrap`(+`> i`, `kbd`), `.filter-card`(+`h6`), `.filter-check .form-check-input`(+`:checked`), `.filter-check label`, `.filter-count`, `.sort-btn`(+`:hover`), `.view-toggle`(+`button`, `button.active`).

- [ ] **Step 10: `_book-detail.scss`** — `.cover-stage` already in book-cover; here: `.note-area`(+`:hover`, `:focus-within`, `.form-control`, `.form-control:focus`), `.quote-callout`(+`.quote-mark`), `.nav-pills .nav-link`(+`.active`), `.sticky-cover` (`@media (min-width:992px)`), `.action-row .btn`, `.sim-card`, breadcrumb tweaks if any.

- [ ] **Step 11: `_landing.scss`** — `.hero-stack`(+responsive height, `.cover` positioning hooks, `:hover`), `.float-a/-b/-c`, `@keyframes floata`, `.section`(+responsive), `.cta-card`, `.step-num` (if not in components — keep in components). Positioning of the individual hero covers (left/top/transform) must NOT be inline in Twig: define `.hero-cover--1/-2/-3` (and the auth variants) here with their positions.

- [ ] **Step 12: `_auth.scss`** — `.illus-panel`(+`::before`), `.teacup`(+`::before`,`::after`), `.quote-c`, `.strength`(+`span`, `.s1`–`.s4`), `.step-dot`(+`.active`), `.step-bar`(+`.active`), `.feature-row`(+`.ico`,`.ico i`), `.form-control`(+`:focus`), `.form-floating > label`. Define positioned helpers `.auth-cover--1/-2/-3` for the floating covers on login/signup, plus `.illus-panel__stage`, `.teacup--placed`, `.quote-c--placed`, `.feature-list` to replace the per-element inline positioning.

- [ ] **Step 13: Build & verify**

```bash
docker compose exec -T php php bin/console sass:build
docker compose exec -T php php bin/console asset-map:compile
```
Expected: no Sass errors.

- [ ] **Step 14: Commit** (combine with 1.1 Step 5 if not yet committed)

```bash
git add assets/styles/
git commit -m "feat: port mockup styles to organized SCSS partials"
```

### Task 1.3: Base layout + shared partials

**Files:**
- Rewrite: `templates/base.html.twig`
- Create: `templates/_partials/navbar_public.html.twig`, `navbar_app.html.twig`, `footer.html.twig`, `footer_compact.html.twig`, `flash_messages.html.twig`

- [ ] **Step 1: `templates/base.html.twig`:**

```twig
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{% block title %}Marca-Page{% endblock %}</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 16 16%22><text y=%2214%22 font-size=%2214%22>📖</text></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    {% block stylesheets %}
        {{ importmap('app') }}
    {% endblock %}
</head>
<body{% block body_attr %}{% endblock %}>
    {% block navbar %}{% include '_partials/navbar_public.html.twig' %}{% endblock %}
    {% block flashes %}{% include '_partials/flash_messages.html.twig' %}{% endblock %}
    {% block body %}{% endblock %}
    {% block footer %}{% include '_partials/footer.html.twig' %}{% endblock %}
</body>
</html>
```
(Note: `importmap('app')` already pulls Bootstrap JS — `assets/vendor/bootstrap/bootstrap.index.js` is in the importmap via `controllers.json`/`app.js`. Verify with `php bin/console debug:asset-map`; if Bootstrap JS isn't bundled, add `<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>` before `</body>` in a `{% block javascripts %}`.)

- [ ] **Step 2: `flash_messages.html.twig`** — render `app.flashes` as Bootstrap alerts inside `.container.pt-3` (types `success`, `danger`, `warning`, `info`), dismissible, no inline style.

- [ ] **Step 3: `navbar_public.html.twig`** — copy the navbar from `home.html` (brand, "Accueil / Bibliothèque / Découvrir / Tarifs", "Se connecter" + "Créer un compte"). Replace `href="home.html"`→`{{ path('app_home') }}`, `href="books-list.html"`→`{{ path('app_library') }}`, `href="login.html"`→`{{ path('app_login') }}`, `href="signup.html"`→`{{ path('app_register') }}`. The decorative inline `<svg>` brand mark stays (it's SVG markup, not CSS). No `style=""`.

- [ ] **Step 4: `navbar_app.html.twig`** — copy the navbar from `books-list.html` (logged-in variant: "Accueil / Bibliothèque / Étagères / Statistiques", search button, avatar dropdown). Wire: brand→`app_home`, Bibliothèque→`app_library`, "Ajouter un livre" button→`{{ path('app_book_search') }}`, dropdown "Se déconnecter/Verrouiller"→`{{ path('app_logout') }}`. Show `app.user.displayName` and its initial in the avatar. The avatar background colour: use `.avatar` + a theme class (e.g. pick `cover--theme-{{ ... }}`-style or just a fixed `.avatar--lavande`); do not inline the colour. "Étagères"/"Statistiques" links can point to `#` for now (out of scope) — keep them but inert.

- [ ] **Step 5: `footer.html.twig`** — the rich footer from `home.html` (4 columns). Replace MonRayon→Marca-Page, links to real routes where they exist, `#` otherwise. `footer_compact.html.twig` — the slim footer from `books-list.html`/`book-detail.html`.

- [ ] **Step 6: Verify** — temporarily add a placeholder route (or wait for Phase 7). For now just run `docker compose exec -T php php bin/console lint:twig templates/`. Expected: no Twig syntax errors.

- [ ] **Step 7: Commit**

```bash
git add templates/
git commit -m "feat: base layout, navbars, footer and flash partials"
```

---

## Phase 2 — Domain: entities, enums, migration

### Task 2.1: Enums

**Files:** create `src/Enum/ReadingStatus.php`, `src/Enum/PurchaseStatus.php`

- [ ] **Step 1: `src/Enum/ReadingStatus.php`:**

```php
<?php

namespace App\Enum;

enum ReadingStatus: string
{
    case ToRead = 'to_read';
    case Reading = 'reading';
    case Finished = 'finished';
    case Abandoned = 'abandoned';

    public function label(): string
    {
        return match ($this) {
            self::ToRead => 'À lire',
            self::Reading => 'En cours',
            self::Finished => 'Terminé',
            self::Abandoned => 'Abandonné',
        };
    }

    public function pillClass(): string
    {
        return match ($this) {
            self::ToRead => 'pill-lavande',
            self::Reading => 'pill-peche',
            self::Finished => 'pill-sauge',
            self::Abandoned => 'pill-outline',
        };
    }
}
```

- [ ] **Step 2: `src/Enum/PurchaseStatus.php`:**

```php
<?php

namespace App\Enum;

enum PurchaseStatus: string
{
    case ToBuy = 'to_buy';
    case Bought = 'bought';
    case Lent = 'lent';

    public function label(): string
    {
        return match ($this) {
            self::ToBuy => 'À acheter',
            self::Bought => 'Acheté',
            self::Lent => 'Prêté',
        };
    }

    public function pillClass(): string
    {
        return match ($this) {
            self::ToBuy => 'pill-rose',
            self::Bought => 'pill-sauge',
            self::Lent => 'pill-outline',
        };
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Enum/
git commit -m "feat: reading and purchase status enums"
```

### Task 2.2: Entities + repositories

**Files:** create `src/Entity/{User,Book,Quote,Shelf}.php`, `src/Repository/{User,Book,Quote,Shelf}Repository.php`. Use `make:entity`/`make:user` as a starting point if convenient, but the canonical content is below. Delete `src/Entity/.gitignore` and `src/Repository/.gitignore` (they only existed to keep empty dirs).

- [ ] **Step 1: `src/Entity/User.php`:**

```php
<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(fields: ['email'], message: 'Un compte existe déjà avec cet email.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank, Assert\Email]
    private string $email = '';

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    private string $displayName = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Book> */
    #[ORM\OneToMany(targetEntity: Book::class, mappedBy: 'owner', orphanRemoval: true)]
    private Collection $books;

    /** @var Collection<int, Shelf> */
    #[ORM\OneToMany(targetEntity: Shelf::class, mappedBy: 'owner', orphanRemoval: true)]
    private Collection $shelves;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->books = new ArrayCollection();
        $this->shelves = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getUserIdentifier(): string { return $this->email; }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }
    public function setRoles(array $roles): static { $this->roles = $roles; return $this; }

    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): static { $this->password = $password; return $this; }

    public function getDisplayName(): string { return $this->displayName; }
    public function setDisplayName(string $displayName): static { $this->displayName = $displayName; return $this; }

    public function getInitial(): string { return mb_strtoupper(mb_substr($this->displayName !== '' ? $this->displayName : $this->email, 0, 1)); }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @return Collection<int, Book> */
    public function getBooks(): Collection { return $this->books; }

    /** @return Collection<int, Shelf> */
    public function getShelves(): Collection { return $this->shelves; }

    public function eraseCredentials(): void {}
}
```

- [ ] **Step 2: `src/Entity/Book.php`:**

```php
<?php

namespace App\Entity;

use App\Enum\PurchaseStatus;
use App\Enum\ReadingStatus;
use App\Repository\BookRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BookRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Book
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'books')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $googleVolumeId = null;

    #[ORM\Column(length: 500)]
    private string $title = '';

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $subtitle = null;

    #[ORM\Column]
    private array $authors = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $publisher = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $publishedDate = null;

    #[ORM\Column(nullable: true)]
    private ?int $pageCount = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $language = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private array $categories = [];

    #[ORM\Column(length: 13, nullable: true)]
    private ?string $isbn10 = null;

    #[ORM\Column(length: 17, nullable: true)]
    private ?string $isbn13 = null;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $thumbnailUrl = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $coverTheme = 0;

    #[ORM\Column(enumType: ReadingStatus::class)]
    private ReadingStatus $readingStatus = ReadingStatus::ToRead;

    #[ORM\Column(nullable: true)]
    private ?int $currentPage = null;

    #[ORM\Column(enumType: PurchaseStatus::class)]
    private PurchaseStatus $purchaseStatus = PurchaseStatus::ToBuy;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $purchasedAt = null;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $purchaseFormat = null;

    #[ORM\Column(nullable: true)]
    private ?int $rating = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $personalNotes = null;

    #[ORM\ManyToOne(inversedBy: 'books')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Shelf $shelf = null;

    #[ORM\Column]
    private \DateTimeImmutable $addedAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, Quote> */
    #[ORM\OneToMany(targetEntity: Quote::class, mappedBy: 'book', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $quotes;

    public function __construct()
    {
        $this->addedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->quotes = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }

    public function getId(): ?int { return $this->id; }

    public function getOwner(): ?User { return $this->owner; }
    public function setOwner(?User $owner): static { $this->owner = $owner; return $this; }

    public function getGoogleVolumeId(): ?string { return $this->googleVolumeId; }
    public function setGoogleVolumeId(?string $v): static { $this->googleVolumeId = $v; return $this; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $v): static { $this->title = $v; return $this; }

    public function getSubtitle(): ?string { return $this->subtitle; }
    public function setSubtitle(?string $v): static { $this->subtitle = $v; return $this; }

    public function getAuthors(): array { return $this->authors; }
    public function setAuthors(array $v): static { $this->authors = array_values($v); return $this; }
    public function getAuthorsLine(): string { return implode(', ', $this->authors) ?: 'Auteur inconnu'; }

    public function getPublisher(): ?string { return $this->publisher; }
    public function setPublisher(?string $v): static { $this->publisher = $v; return $this; }

    public function getPublishedDate(): ?string { return $this->publishedDate; }
    public function setPublishedDate(?string $v): static { $this->publishedDate = $v; return $this; }
    public function getPublishedYear(): ?string { return $this->publishedDate ? substr($this->publishedDate, 0, 4) : null; }

    public function getPageCount(): ?int { return $this->pageCount; }
    public function setPageCount(?int $v): static { $this->pageCount = $v; return $this; }

    public function getLanguage(): ?string { return $this->language; }
    public function setLanguage(?string $v): static { $this->language = $v; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }

    public function getCategories(): array { return $this->categories; }
    public function setCategories(array $v): static { $this->categories = array_values($v); return $this; }
    public function getPrimaryCategory(): ?string { return $this->categories[0] ?? null; }

    public function getIsbn10(): ?string { return $this->isbn10; }
    public function setIsbn10(?string $v): static { $this->isbn10 = $v; return $this; }

    public function getIsbn13(): ?string { return $this->isbn13; }
    public function setIsbn13(?string $v): static { $this->isbn13 = $v; return $this; }

    public function getThumbnailUrl(): ?string { return $this->thumbnailUrl; }
    public function setThumbnailUrl(?string $v): static { $this->thumbnailUrl = $v; return $this; }

    public function getCoverTheme(): int { return $this->coverTheme; }
    public function setCoverTheme(int $v): static { $this->coverTheme = $v; return $this; }

    public function getReadingStatus(): ReadingStatus { return $this->readingStatus; }
    public function setReadingStatus(ReadingStatus $v): static { $this->readingStatus = $v; return $this; }

    public function getCurrentPage(): ?int { return $this->currentPage; }
    public function setCurrentPage(?int $v): static { $this->currentPage = $v; return $this; }
    public function getProgressPercent(): ?int
    {
        if (!$this->pageCount || !$this->currentPage) { return null; }
        return (int) min(100, round($this->currentPage / $this->pageCount * 100));
    }

    public function getPurchaseStatus(): PurchaseStatus { return $this->purchaseStatus; }
    public function setPurchaseStatus(PurchaseStatus $v): static { $this->purchaseStatus = $v; return $this; }

    public function getPurchasedAt(): ?\DateTimeImmutable { return $this->purchasedAt; }
    public function setPurchasedAt(?\DateTimeImmutable $v): static { $this->purchasedAt = $v; return $this; }

    public function getPurchaseFormat(): ?string { return $this->purchaseFormat; }
    public function setPurchaseFormat(?string $v): static { $this->purchaseFormat = $v; return $this; }

    public function getRating(): ?int { return $this->rating; }
    public function setRating(?int $v): static { $this->rating = $v === null ? null : max(0, min(5, $v)); return $this; }

    public function getPersonalNotes(): ?string { return $this->personalNotes; }
    public function setPersonalNotes(?string $v): static { $this->personalNotes = $v; return $this; }

    public function getShelf(): ?Shelf { return $this->shelf; }
    public function setShelf(?Shelf $v): static { $this->shelf = $v; return $this; }

    public function getAddedAt(): \DateTimeImmutable { return $this->addedAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    /** @return Collection<int, Quote> */
    public function getQuotes(): Collection { return $this->quotes; }
    public function addQuote(Quote $q): static { if (!$this->quotes->contains($q)) { $this->quotes->add($q); $q->setBook($this); } return $this; }
    public function removeQuote(Quote $q): static { $this->quotes->removeElement($q); return $this; }

    public function hasThumbnail(): bool { return $this->thumbnailUrl !== null && $this->thumbnailUrl !== ''; }
}
```

- [ ] **Step 3: `src/Entity/Quote.php`:**

```php
<?php

namespace App\Entity;

use App\Repository\QuoteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: QuoteRepository::class)]
class Quote
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'quotes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Book $book = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank, Assert\Length(max: 2000)]
    private string $text = '';

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $page = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }

    public function getId(): ?int { return $this->id; }
    public function getBook(): ?Book { return $this->book; }
    public function setBook(?Book $b): static { $this->book = $b; return $this; }
    public function getText(): string { return $this->text; }
    public function setText(string $t): static { $this->text = $t; return $this; }
    public function getPage(): ?int { return $this->page; }
    public function setPage(?int $p): static { $this->page = $p; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
```

- [ ] **Step 4: `src/Entity/Shelf.php`:**

```php
<?php

namespace App\Entity;

use App\Repository\ShelfRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ShelfRepository::class)]
class Shelf
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'shelves')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank, Assert\Length(max: 80)]
    private string $name = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Book> */
    #[ORM\OneToMany(targetEntity: Book::class, mappedBy: 'shelf')]
    private Collection $books;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->books = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getOwner(): ?User { return $this->owner; }
    public function setOwner(?User $o): static { $this->owner = $o; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $n): static { $this->name = $n; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    /** @return Collection<int, Book> */
    public function getBooks(): Collection { return $this->books; }
}
```

- [ ] **Step 5: Repositories** — `src/Repository/UserRepository.php` extends `ServiceEntityRepository<User>` and implements `PasswordUpgraderInterface` (standard maker output). `QuoteRepository`, `ShelfRepository` are bare `ServiceEntityRepository` subclasses. `BookRepository` is bare for now (the `paginateForLibrary` method is added in Task 5.2). Use:

```php
<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/** @extends ServiceEntityRepository<User> */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, User::class); }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }
        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }
}
```
(Analogous bare classes for `BookRepository`, `QuoteRepository`, `ShelfRepository`.)

- [ ] **Step 6: Validate the mapping**

```bash
docker compose exec -T php php bin/console doctrine:schema:validate --skip-sync
```
Expected: "The mapping files are correct."

- [ ] **Step 7: Commit**

```bash
git add src/Entity src/Repository
git rm src/Entity/.gitignore src/Repository/.gitignore
git commit -m "feat: User, Book, Quote, Shelf entities and repositories"
```

### Task 2.3: Initial migration

**Files:** generated `migrations/Version*.php`

- [ ] **Step 1: Generate**

```bash
docker compose exec -T php php bin/console make:migration
```
Expected: a `migrations/VersionYYYYMMDDHHMMSS.php` file creating the `user`, `book`, `quote`, `shelf` tables with FKs.

- [ ] **Step 2: Review** the generated SQL (Postgres). Confirm: `user` table is quoted; enum columns are `VARCHAR`; `book.shelf_id` FK has `ON DELETE SET NULL`; `book.owner_id` / `quote.book_id` / `shelf.owner_id` `NOT NULL`.

- [ ] **Step 3: Run it**

```bash
docker compose exec -T php php bin/console doctrine:migrations:migrate --no-interaction
```
Expected: migration executed, no error.

- [ ] **Step 4: Commit**

```bash
git add migrations/
git commit -m "feat: initial database migration"
```

---

## Phase 3 — Security: provider, login, registration

### Task 3.1: Security configuration

**Files:** modify `config/packages/security.yaml`

- [ ] **Step 1: Replace `config/packages/security.yaml` with:**

```yaml
security:
    password_hashers:
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface: 'auto'
    providers:
        app_user_provider:
            entity:
                class: App\Entity\User
                property: email
    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js)/
            security: false
        main:
            lazy: true
            provider: app_user_provider
            form_login:
                login_path: app_login
                check_path: app_login
                default_target_path: app_library
                enable_csrf: true
            logout:
                path: app_logout
                target: app_home
            remember_me:
                secret: '%kernel.secret%'
                lifetime: 604800
    access_control:
        - { path: ^/$, roles: PUBLIC_ACCESS }
        - { path: ^/login, roles: PUBLIC_ACCESS }
        - { path: ^/register, roles: PUBLIC_ACCESS }
        - { path: ^/, roles: ROLE_USER }

when@test:
    security:
        password_hashers:
            Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface:
                algorithm: plaintext
```

- [ ] **Step 2: Commit**

```bash
git add config/packages/security.yaml
git commit -m "feat: security firewall with form login and entity provider"
```

### Task 3.2: SecurityController + login template

**Files:** create `src/Controller/SecurityController.php`, `templates/security/login.html.twig`

- [ ] **Step 1: `src/Controller/SecurityController.php`:**

```php
<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_library');
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): never
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
```

- [ ] **Step 2: `templates/security/login.html.twig`** — port `login.html`. Structure: `{% extends 'base.html.twig' %}`, override `{% block navbar %}{% endblock %}` to empty (the login page has its own minimal top bar) and `{% block footer %}{% endblock %}` similarly; put the page header/top-bar markup in `{% block body %}`. Replace:
  - the illustration panel (left) — keep as decorative markup, but move ALL the inline `style="..."` for floating covers, teacup, quote card into the `.auth-cover--N` / `.teacup--placed` / `.quote-c--placed` classes from `_auth.scss`.
  - the form (right): wrap in a real `<form method="post">` posting to `{{ path('app_login') }}`; fields `_username` (type=email, value=`{{ last_username }}`), `_password`; `<input type="hidden" name="_csrf_token" value="{{ csrf_token('authenticate') }}">`; remember-me checkbox `name="_remember_me"`; show `{% if error %}<div class="alert alert-danger">{{ error.messageKey|trans(error.messageData, 'security') }}</div>{% endif %}`. Google/Apple buttons stay as inert `<button type="button">`. "Mot de passe oublié" → `href="#"`. Top-bar "Créer mon coffret" → `{{ path('app_register') }}`. The password-visibility toggle button gets `data-controller="password-toggle"` etc. (wired in Phase 8) — for now the button can stay; the inline `<script>` at the bottom is removed.

- [ ] **Step 3: Verify**

```bash
docker compose exec -T php php bin/console lint:twig templates/security
docker compose up -d   # ensure running
curl -sf -o /dev/null -w '%{http_code}\n' http://localhost:8080/login
```
Expected: `200`. Visit in browser to eyeball.

- [ ] **Step 4: Commit**

```bash
git add src/Controller/SecurityController.php templates/security
git commit -m "feat: login page"
```

### Task 3.3: Registration

**Files:** create `src/Form/RegistrationFormType.php`, `src/Controller/RegistrationController.php`, `templates/registration/register.html.twig`

- [ ] **Step 1: `src/Form/RegistrationFormType.php`:**

```php
<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('displayName', TextType::class, [
                'label' => 'Comment vous appeler ?',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 80)],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'constraints' => [new Assert\NotBlank(), new Assert\Email()],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => ['label' => 'Mot de passe'],
                'second_options' => ['label' => 'Confirmer le mot de passe'],
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(min: 8, minMessage: '8 caractères minimum.'),
                    new Assert\Regex(pattern: '/[A-Z]/', message: 'Au moins une majuscule.'),
                    new Assert\Regex(pattern: '/\d/', message: 'Au moins un chiffre.'),
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'constraints' => [new Assert\IsTrue(message: 'Vous devez accepter les conditions.')],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
```

- [ ] **Step 2: `src/Controller/RegistrationController.php`:**

```php
<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
        Security $security,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_library');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($hasher->hashPassword($user, $form->get('plainPassword')->getData()));
            $em->persist($user);
            $em->flush();
            $security->login($user, 'form_login', 'main');

            return $this->redirectToRoute('app_library');
        }

        return $this->render('registration/register.html.twig', ['registrationForm' => $form]);
    }
}
```

- [ ] **Step 3: `templates/registration/register.html.twig`** — port `signup.html`. Same pattern: empty `navbar`/`footer` blocks, page markup in `body`. Render the form fields with the `registrationForm` variable (`{{ form_start }}`, `{{ form_widget }}` per field, `{{ form_errors }}`) but keep the mockup's `.form-floating` look — easiest is to **not** use `form_row` wholesale; instead render each field manually:
  ```twig
  {{ form_start(registrationForm, {attr: {class: 'd-flex flex-column gap-3'}}) }}
    <div class="form-floating">
      {{ form_widget(registrationForm.displayName, {attr: {class: 'form-control', placeholder: 'Votre prénom'}}) }}
      {{ form_label(registrationForm.displayName) }}
      {{ form_errors(registrationForm.displayName) }}
    </div>
    ... email, plainPassword.first, plainPassword.second, agreeTerms ...
    <button type="submit" class="btn btn-primary btn-lg rounded-pill fw-medium py-3 mt-2">Créer mon coffret <i class="bi bi-arrow-right ms-1"></i></button>
  {{ form_end(registrationForm) }}
  ```
  The password field gets `data-controller="password-strength"` + targets (wired in Phase 8); keep the `.strength` markup. Move ALL inline `style=""` (floating covers, feature rows, blobs) into the `_auth.scss` helper classes. Top-bar "Se connecter" → `{{ path('app_login') }}`. Remove the inline `<script>`.

- [ ] **Step 4: Verify**

```bash
docker compose exec -T php php bin/console lint:twig templates/registration
curl -sf -o /dev/null -w '%{http_code}\n' http://localhost:8080/register
```
Expected: `200`.

- [ ] **Step 5: Commit**

```bash
git add src/Form src/Controller/RegistrationController.php templates/registration
git commit -m "feat: registration with validated form"
```

---

## Phase 4 — Google Books integration

### Task 4.1: `GoogleBookResult` DTO + `CoverThemePicker`

**Files:** create `src/Dto/GoogleBookResult.php`, `src/Service/CoverThemePicker.php`, `tests/Service/CoverThemePickerTest.php`

- [ ] **Step 1: `tests/Service/CoverThemePickerTest.php`:**

```php
<?php

namespace App\Tests\Service;

use App\Service\CoverThemePicker;
use PHPUnit\Framework\TestCase;

class CoverThemePickerTest extends TestCase
{
    public function testReturnsValueInRange(): void
    {
        $picker = new CoverThemePicker();
        foreach (['L\'Étranger', '', 'Le Petit Prince', 'a', str_repeat('x', 200)] as $seed) {
            $n = $picker->pick($seed);
            self::assertGreaterThanOrEqual(0, $n);
            self::assertLessThanOrEqual(11, $n);
        }
    }

    public function testIsDeterministic(): void
    {
        $picker = new CoverThemePicker();
        self::assertSame($picker->pick('Germinal'), $picker->pick('Germinal'));
    }
}
```

- [ ] **Step 2: Run it — expect failure**

```bash
docker compose exec -T php php bin/phpunit tests/Service/CoverThemePickerTest.php
```
Expected: error — `App\Service\CoverThemePicker` not found.

- [ ] **Step 3: `src/Service/CoverThemePicker.php`:**

```php
<?php

namespace App\Service;

class CoverThemePicker
{
    public const COUNT = 12;

    public function pick(string $seed): int
    {
        return (int) (crc32($seed) % self::COUNT);
    }
}
```

- [ ] **Step 4: `src/Dto/GoogleBookResult.php`:**

```php
<?php

namespace App\Dto;

final readonly class GoogleBookResult
{
    /**
     * @param string[] $authors
     * @param string[] $categories
     */
    public function __construct(
        public string $volumeId,
        public string $title,
        public ?string $subtitle,
        public array $authors,
        public ?string $publisher,
        public ?string $publishedDate,
        public ?int $pageCount,
        public ?string $language,
        public ?string $description,
        public array $categories,
        public ?string $isbn10,
        public ?string $isbn13,
        public ?string $thumbnailUrl,
    ) {}
}
```

- [ ] **Step 5: Run tests — expect pass**

```bash
docker compose exec -T php php bin/phpunit tests/Service/CoverThemePickerTest.php
```
Expected: OK (2 tests).

- [ ] **Step 6: Commit**

```bash
git add src/Dto/GoogleBookResult.php src/Service/CoverThemePicker.php tests/Service/CoverThemePickerTest.php
git commit -m "feat: GoogleBookResult DTO and cover theme picker"
```

### Task 4.2: `GoogleBooksClient`

**Files:** create `src/Service/GoogleBooksException.php`, `src/Service/GoogleBooksClient.php`, `tests/Service/GoogleBooksClientTest.php`; modify `config/services.yaml`

- [ ] **Step 1: `tests/Service/GoogleBooksClientTest.php`:**

```php
<?php

namespace App\Tests\Service;

use App\Service\GoogleBooksClient;
use App\Service\GoogleBooksException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class GoogleBooksClientTest extends TestCase
{
    public function testSearchMapsVolumes(): void
    {
        $json = json_encode(['items' => [[
            'id' => 'abc123',
            'volumeInfo' => [
                'title' => "L'Étranger",
                'subtitle' => null,
                'authors' => ['Albert Camus'],
                'publisher' => 'Gallimard',
                'publishedDate' => '1942',
                'pageCount' => 186,
                'language' => 'fr',
                'description' => 'Meursault...',
                'categories' => ['Fiction'],
                'industryIdentifiers' => [
                    ['type' => 'ISBN_10', 'identifier' => '2070360024'],
                    ['type' => 'ISBN_13', 'identifier' => '9782070360024'],
                ],
                'imageLinks' => ['thumbnail' => 'http://books.google.com/img?id=abc123'],
            ],
        ]]]);
        $client = new GoogleBooksClient(new MockHttpClient(new MockResponse($json)), '');

        $results = $client->search('camus');

        self::assertCount(1, $results);
        $r = $results[0];
        self::assertSame('abc123', $r->volumeId);
        self::assertSame("L'Étranger", $r->title);
        self::assertSame(['Albert Camus'], $r->authors);
        self::assertSame('2070360024', $r->isbn10);
        self::assertSame('9782070360024', $r->isbn13);
        self::assertSame('https://books.google.com/img?id=abc123', $r->thumbnailUrl);
        self::assertSame(186, $r->pageCount);
    }

    public function testSearchReturnsEmptyArrayWhenNoItems(): void
    {
        $client = new GoogleBooksClient(new MockHttpClient(new MockResponse(json_encode(['totalItems' => 0]))), '');
        self::assertSame([], $client->search('zzz'));
    }

    public function testSearchThrowsOnHttpError(): void
    {
        $client = new GoogleBooksClient(new MockHttpClient(new MockResponse('boom', ['http_code' => 503])), '');
        $this->expectException(GoogleBooksException::class);
        $client->search('camus');
    }
}
```

- [ ] **Step 2: Run — expect failure** (`docker compose exec -T php php bin/phpunit tests/Service/GoogleBooksClientTest.php`). Expected: classes not found.

- [ ] **Step 3: `src/Service/GoogleBooksException.php`:**

```php
<?php

namespace App\Service;

class GoogleBooksException extends \RuntimeException
{
}
```

- [ ] **Step 4: `src/Service/GoogleBooksClient.php`:**

```php
<?php

namespace App\Service;

use App\Dto\GoogleBookResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GoogleBooksClient
{
    private const BASE = 'https://www.googleapis.com/books/v1/volumes';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(GOOGLE_BOOKS_API_KEY)%')]
        private readonly string $apiKey = '',
    ) {}

    /** @return GoogleBookResult[] */
    public function search(string $query, int $maxResults = 20): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $data = $this->request(self::BASE, [
            'q' => $query,
            'maxResults' => max(1, min(40, $maxResults)),
            'printType' => 'books',
            'orderBy' => 'relevance',
        ]);

        $items = $data['items'] ?? [];

        return array_values(array_filter(array_map(
            fn (array $item): ?GoogleBookResult => $this->mapItem($item),
            $items,
        )));
    }

    public function getVolume(string $volumeId): GoogleBookResult
    {
        $data = $this->request(self::BASE . '/' . rawurlencode($volumeId), []);
        $result = $this->mapItem($data);
        if ($result === null) {
            throw new GoogleBooksException(\sprintf('Volume "%s" is missing required data.', $volumeId));
        }

        return $result;
    }

    /** @param array<string, scalar> $params */
    private function request(string $url, array $params): array
    {
        if ($this->apiKey !== '') {
            $params['key'] = $this->apiKey;
        }

        try {
            $response = $this->httpClient->request('GET', $url, ['query' => $params, 'timeout' => 8]);
            $status = $response->getStatusCode();
            if ($status >= 400) {
                throw new GoogleBooksException(\sprintf('Google Books API returned HTTP %d.', $status));
            }

            return $response->toArray();
        } catch (GoogleBooksException $e) {
            throw $e;
        } catch (HttpExceptionInterface|\JsonException $e) {
            throw new GoogleBooksException('Could not reach the Google Books API.', 0, $e);
        }
    }

    private function mapItem(array $item): ?GoogleBookResult
    {
        $id = $item['id'] ?? null;
        $info = $item['volumeInfo'] ?? null;
        if (!\is_string($id) || !\is_array($info) || empty($info['title'])) {
            return null;
        }

        $isbn10 = $isbn13 = null;
        foreach ($info['industryIdentifiers'] ?? [] as $ident) {
            if (($ident['type'] ?? '') === 'ISBN_10') { $isbn10 = $ident['identifier'] ?? null; }
            if (($ident['type'] ?? '') === 'ISBN_13') { $isbn13 = $ident['identifier'] ?? null; }
        }

        $thumb = $info['imageLinks']['thumbnail'] ?? $info['imageLinks']['smallThumbnail'] ?? null;
        if (\is_string($thumb)) {
            $thumb = str_replace('http://', 'https://', $thumb);
        }

        return new GoogleBookResult(
            volumeId: $id,
            title: (string) $info['title'],
            subtitle: isset($info['subtitle']) ? (string) $info['subtitle'] : null,
            authors: array_values(array_map('strval', $info['authors'] ?? [])),
            publisher: isset($info['publisher']) ? (string) $info['publisher'] : null,
            publishedDate: isset($info['publishedDate']) ? (string) $info['publishedDate'] : null,
            pageCount: isset($info['pageCount']) ? (int) $info['pageCount'] : null,
            language: isset($info['language']) ? (string) $info['language'] : null,
            description: isset($info['description']) ? (string) $info['description'] : null,
            categories: array_values(array_map('strval', $info['categories'] ?? [])),
            isbn10: \is_string($isbn10) ? $isbn10 : null,
            isbn13: \is_string($isbn13) ? $isbn13 : null,
            thumbnailUrl: \is_string($thumb) ? $thumb : null,
        );
    }
}
```
(The `#[Autowire]` on a constructor default handles the API key; no `services.yaml` change strictly required. If autowiring the param fails, add an explicit bind in `config/services.yaml` under `services._defaults.bind`: `string $apiKey: '%env(GOOGLE_BOOKS_API_KEY)%'` — but prefer the attribute.)

- [ ] **Step 5: Run tests — expect pass**

```bash
docker compose exec -T php php bin/phpunit tests/Service/GoogleBooksClientTest.php
```
Expected: OK (3 tests).

- [ ] **Step 6: Commit**

```bash
git add src/Service/GoogleBooksClient.php src/Service/GoogleBooksException.php tests/Service/GoogleBooksClientTest.php config/services.yaml
git commit -m "feat: Google Books API client"
```

### Task 4.3: `BookImporter`

**Files:** create `src/Service/BookImporter.php`

- [ ] **Step 1: `src/Service/BookImporter.php`:**

```php
<?php

namespace App\Service;

use App\Entity\Book;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class BookImporter
{
    public function __construct(
        private readonly GoogleBooksClient $googleBooks,
        private readonly CoverThemePicker $coverThemePicker,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Imports a Google Books volume into the user's library. If the user already
     * owns this volume, returns the existing Book and does not create a duplicate.
     */
    public function importFromGoogle(User $user, string $volumeId): Book
    {
        $existing = $this->em->getRepository(Book::class)
            ->findOneBy(['owner' => $user, 'googleVolumeId' => $volumeId]);
        if ($existing instanceof Book) {
            return $existing;
        }

        $data = $this->googleBooks->getVolume($volumeId);

        $book = (new Book())
            ->setOwner($user)
            ->setGoogleVolumeId($data->volumeId)
            ->setTitle($data->title)
            ->setSubtitle($data->subtitle)
            ->setAuthors($data->authors)
            ->setPublisher($data->publisher)
            ->setPublishedDate($data->publishedDate)
            ->setPageCount($data->pageCount)
            ->setLanguage($data->language)
            ->setDescription($data->description)
            ->setCategories($data->categories)
            ->setIsbn10($data->isbn10)
            ->setIsbn13($data->isbn13)
            ->setThumbnailUrl($data->thumbnailUrl)
            ->setCoverTheme($this->coverThemePicker->pick($data->title));

        $this->em->persist($book);
        $this->em->flush();

        return $book;
    }
}
```

- [ ] **Step 2: Commit** (covered by functional tests in Phase 9; no unit test here as it's a thin orchestrator)

```bash
git add src/Service/BookImporter.php
git commit -m "feat: book importer from Google Books"
```

---

## Phase 5 — Library list

### Task 5.1: `LibraryFilter` DTO

**Files:** create `src/Dto/LibraryFilter.php`, `tests/Dto/LibraryFilterTest.php`

- [ ] **Step 1: `tests/Dto/LibraryFilterTest.php`:**

```php
<?php

namespace App\Tests\Dto;

use App\Dto\LibraryFilter;
use App\Enum\PurchaseStatus;
use App\Enum\ReadingStatus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class LibraryFilterTest extends TestCase
{
    public function testDefaults(): void
    {
        $f = LibraryFilter::fromRequest(new Request());
        self::assertSame('', $f->query);
        self::assertSame([], $f->readingStatuses);
        self::assertSame([], $f->purchaseStatuses);
        self::assertSame([], $f->categories);
        self::assertNull($f->minRating);
        self::assertNull($f->maxPages);
        self::assertNull($f->shelfId);
        self::assertSame('recent', $f->sort);
        self::assertSame(1, $f->page);
    }

    public function testParsesValues(): void
    {
        $f = LibraryFilter::fromRequest(new Request(query: [
            'q' => '  camus ',
            'reading' => ['reading', 'to_read', 'bogus'],
            'purchase' => ['bought'],
            'category' => ['Roman'],
            'minRating' => '3',
            'maxPages' => '500',
            'shelf' => '7',
            'sort' => 'title_asc',
            'page' => '2',
        ]));
        self::assertSame('camus', $f->query);
        self::assertSame([ReadingStatus::Reading, ReadingStatus::ToRead], $f->readingStatuses);
        self::assertSame([PurchaseStatus::Bought], $f->purchaseStatuses);
        self::assertSame(['Roman'], $f->categories);
        self::assertSame(3, $f->minRating);
        self::assertSame(500, $f->maxPages);
        self::assertSame(7, $f->shelfId);
        self::assertSame('title_asc', $f->sort);
        self::assertSame(2, $f->page);
    }

    public function testInvalidSortFallsBackToRecent(): void
    {
        $f = LibraryFilter::fromRequest(new Request(query: ['sort' => 'nonsense', 'page' => '0']));
        self::assertSame('recent', $f->sort);
        self::assertSame(1, $f->page);
    }
}
```

- [ ] **Step 2: Run — expect failure.**

- [ ] **Step 3: `src/Dto/LibraryFilter.php`:**

```php
<?php

namespace App\Dto;

use App\Enum\PurchaseStatus;
use App\Enum\ReadingStatus;
use Symfony\Component\HttpFoundation\Request;

final readonly class LibraryFilter
{
    public const SORTS = ['recent', 'title_asc', 'author', 'rating', 'published'];

    /**
     * @param ReadingStatus[]  $readingStatuses
     * @param PurchaseStatus[] $purchaseStatuses
     * @param string[]         $categories
     */
    public function __construct(
        public string $query = '',
        public array $readingStatuses = [],
        public array $purchaseStatuses = [],
        public array $categories = [],
        public ?int $minRating = null,
        public ?int $maxPages = null,
        public ?int $shelfId = null,
        public string $sort = 'recent',
        public int $page = 1,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $q = $request->query;

        $reading = [];
        foreach ((array) $q->all('reading') as $v) {
            $case = ReadingStatus::tryFrom((string) $v);
            if ($case !== null) { $reading[] = $case; }
        }
        $purchase = [];
        foreach ((array) $q->all('purchase') as $v) {
            $case = PurchaseStatus::tryFrom((string) $v);
            if ($case !== null) { $purchase[] = $case; }
        }
        $categories = array_values(array_filter(array_map(
            static fn ($v) => trim((string) $v),
            (array) $q->all('category'),
        )));

        $minRating = $q->has('minRating') ? max(1, min(5, $q->getInt('minRating'))) : null;
        $maxPages = $q->has('maxPages') && $q->getInt('maxPages') > 0 ? $q->getInt('maxPages') : null;
        $shelfId = $q->has('shelf') && $q->getInt('shelf') > 0 ? $q->getInt('shelf') : null;

        $sort = (string) $q->get('sort', 'recent');
        if (!\in_array($sort, self::SORTS, true)) { $sort = 'recent'; }

        $page = max(1, $q->getInt('page', 1));

        return new self(
            query: trim((string) $q->get('q', '')),
            readingStatuses: $reading,
            purchaseStatuses: $purchase,
            categories: $categories,
            minRating: $minRating,
            maxPages: $maxPages,
            shelfId: $shelfId,
            sort: $sort,
            page: $page,
        );
    }

    public function isEmpty(): bool
    {
        return $this->query === '' && $this->readingStatuses === [] && $this->purchaseStatuses === []
            && $this->categories === [] && $this->minRating === null && $this->maxPages === null
            && $this->shelfId === null;
    }
}
```

- [ ] **Step 4: Run tests — expect pass.**

- [ ] **Step 5: Commit**

```bash
git add src/Dto/LibraryFilter.php tests/Dto/LibraryFilterTest.php
git commit -m "feat: library filter DTO"
```

### Task 5.2: Pagination helper + `BookRepository::paginateForLibrary`

**Files:** create `src/Pagination/Page.php`; modify `src/Repository/BookRepository.php`

- [ ] **Step 1: `src/Pagination/Page.php`:**

```php
<?php

namespace App\Pagination;

/**
 * @template T
 */
final readonly class Page
{
    /** @param T[] $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {}

    public function pageCount(): int { return max(1, (int) ceil($this->total / $this->perPage)); }
    public function hasPrevious(): bool { return $this->page > 1; }
    public function hasNext(): bool { return $this->page < $this->pageCount(); }
    public function isEmpty(): bool { return $this->items === []; }

    /** @return int[] */
    public function pageRange(int $around = 2): array
    {
        $from = max(1, $this->page - $around);
        $to = min($this->pageCount(), $this->page + $around);
        return range($from, $to);
    }
}
```

- [ ] **Step 2: Add to `src/Repository/BookRepository.php`:**

```php
<?php

namespace App\Repository;

use App\Dto\LibraryFilter;
use App\Entity\Book;
use App\Entity\User;
use App\Pagination\Page;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Book> */
class BookRepository extends ServiceEntityRepository
{
    public const PER_PAGE = 24;

    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, Book::class); }

    /** @return Page<Book> */
    public function paginateForLibrary(User $owner, LibraryFilter $filter): Page
    {
        $qb = $this->createQueryBuilder('b')
            ->andWhere('b.owner = :owner')->setParameter('owner', $owner);

        if ($filter->query !== '') {
            $qb->andWhere('LOWER(b.title) LIKE :q OR LOWER(b.subtitle) LIKE :q OR LOWER(CAST(b.authors AS text)) LIKE :q')
               ->setParameter('q', '%' . mb_strtolower($filter->query) . '%');
        }
        if ($filter->readingStatuses !== []) {
            $qb->andWhere('b.readingStatus IN (:rs)')->setParameter('rs', array_map(fn ($s) => $s->value, $filter->readingStatuses));
        }
        if ($filter->purchaseStatuses !== []) {
            $qb->andWhere('b.purchaseStatus IN (:ps)')->setParameter('ps', array_map(fn ($s) => $s->value, $filter->purchaseStatuses));
        }
        if ($filter->minRating !== null) {
            $qb->andWhere('b.rating >= :minRating')->setParameter('minRating', $filter->minRating);
        }
        if ($filter->maxPages !== null) {
            $qb->andWhere('b.pageCount IS NOT NULL AND b.pageCount <= :maxPages')->setParameter('maxPages', $filter->maxPages);
        }
        if ($filter->shelfId !== null) {
            $qb->andWhere('IDENTITY(b.shelf) = :shelfId')->setParameter('shelfId', $filter->shelfId);
        }
        foreach ($filter->categories as $i => $cat) {
            $qb->andWhere(\sprintf('LOWER(CAST(b.categories AS text)) LIKE :cat%d', $i))
               ->setParameter('cat'.$i, '%' . mb_strtolower($cat) . '%');
        }

        match ($filter->sort) {
            'title_asc' => $qb->orderBy('b.title', 'ASC'),
            'rating'    => $qb->orderBy('b.rating', 'DESC')->addOrderBy('b.addedAt', 'DESC'),
            'published' => $qb->orderBy('b.publishedDate', 'DESC')->addOrderBy('b.title', 'ASC'),
            'author'    => $qb->orderBy('CAST(b.authors AS text)', 'ASC')->addOrderBy('b.title', 'ASC'),
            default     => $qb->orderBy('b.addedAt', 'DESC'),
        };

        $qb->setFirstResult(($filter->page - 1) * self::PER_PAGE)->setMaxResults(self::PER_PAGE);

        $paginator = new Paginator($qb->getQuery(), fetchJoinCollection: false);
        $total = \count($paginator);

        return new Page(iterator_to_array($paginator), $total, $filter->page, self::PER_PAGE);
    }

    /**
     * Other books from the same library that share an author or a category.
     * @return Book[]
     */
    public function findSimilar(Book $book, int $limit = 3): array
    {
        $qb = $this->createQueryBuilder('b')
            ->andWhere('b.owner = :owner')->setParameter('owner', $book->getOwner())
            ->andWhere('b.id != :id')->setParameter('id', $book->getId())
            ->orderBy('b.addedAt', 'DESC')
            ->setMaxResults($limit);

        $clauses = [];
        foreach ($book->getAuthors() as $i => $a) {
            $clauses[] = "LOWER(CAST(b.authors AS text)) LIKE :a$i";
            $qb->setParameter("a$i", '%' . mb_strtolower($a) . '%');
        }
        foreach ($book->getCategories() as $i => $c) {
            $clauses[] = "LOWER(CAST(b.categories AS text)) LIKE :c$i";
            $qb->setParameter("c$i", '%' . mb_strtolower($c) . '%');
        }
        if ($clauses !== []) {
            $qb->andWhere(implode(' OR ', $clauses));
        }

        $similar = $qb->getQuery()->getResult();
        if (\count($similar) < $limit) {
            // pad with most recent other books
            $extra = $this->createQueryBuilder('b')
                ->andWhere('b.owner = :owner')->setParameter('owner', $book->getOwner())
                ->andWhere('b.id != :id')->setParameter('id', $book->getId())
                ->orderBy('b.addedAt', 'DESC')->setMaxResults($limit)->getQuery()->getResult();
            $byId = [];
            foreach ([...$similar, ...$extra] as $b) { $byId[$b->getId()] = $b; }
            $similar = \array_slice(array_values($byId), 0, $limit);
        }

        return $similar;
    }
}
```
(Note: `CAST(... AS text)` works on Postgres for json columns via DQL `CAST`; if DQL rejects it, switch the json columns to `Types::JSON`-stored-as-text by declaring them `type: Types::JSON` (already implicit via `array`) and use a native query, OR store `authors`/`categories` as a separate searchable string. Simplest fallback if `CAST` is a problem: add `authorsText` + `categoriesText` plain string columns kept in sync in the setters, and filter on those. Decide during implementation; the `array` columns + `CAST` is the first attempt.)

- [ ] **Step 3: Verify it compiles** — `docker compose exec -T php php bin/console doctrine:schema:validate --skip-sync` (mapping still valid). No unit test for the repo here (covered functionally).

- [ ] **Step 4: Commit**

```bash
git add src/Pagination/Page.php src/Repository/BookRepository.php
git commit -m "feat: paginated library query and similar-books query"
```

### Task 5.3: `LibraryController` + templates

**Files:** create `src/Controller/LibraryController.php`, `templates/library/index.html.twig`, `templates/library/_filters.html.twig`, `templates/library/_grid.html.twig`, `templates/library/_active_chips.html.twig`, `templates/_partials/book_cover.html.twig`, `templates/_partials/book_card.html.twig`, `templates/_partials/pagination.html.twig`, `templates/_partials/stars.html.twig`, `templates/_partials/reading_status_badge.html.twig`, `templates/_partials/purchase_status_badge.html.twig`

- [ ] **Step 1: `src/Controller/LibraryController.php`:**

```php
<?php

namespace App\Controller;

use App\Dto\LibraryFilter;
use App\Entity\User;
use App\Repository\BookRepository;
use App\Repository\ShelfRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class LibraryController extends AbstractController
{
    #[Route('/library', name: 'app_library')]
    public function index(Request $request, BookRepository $books, ShelfRepository $shelves): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $filter = LibraryFilter::fromRequest($request);
        $page = $books->paginateForLibrary($user, $filter);

        return $this->render('library/index.html.twig', [
            'page' => $page,
            'filter' => $filter,
            'shelves' => $shelves->findBy(['owner' => $user], ['name' => 'ASC']),
            'totalInLibrary' => $books->count(['owner' => $user]),
        ]);
    }
}
```

- [ ] **Step 2: `_partials/book_cover.html.twig`** — encapsulates cover rendering. Params via `include` `with`: `book`, `size` (`'lg'` default → grid card; `'xs'`, `'sm'`, `'md'`, `'hero'`), optional `extra_class`.

```twig
{# @var book \App\Entity\Book #}
{% set size = size|default('lg') %}
{% if book.hasThumbnail %}
  <div class="cover cover--{{ size }} {{ extra_class|default('') }}">
    <span class="spine"></span>
    <img class="cover-img" src="{{ book.thumbnailUrl }}" alt="Couverture de {{ book.title }}" loading="lazy">
  </div>
{% else %}
  <div class="cover cover--{{ size }} cover--theme-{{ book.coverTheme }} {{ extra_class|default('') }}">
    <span class="spine"></span>
    <div class="deco"></div>
    <div class="body">
      <div class="author-c">{{ book.authors|first|default('') }}</div>
      <div>
        <div class="title-c">{{ book.title }}</div>
        {% if book.publishedYear %}<div class="pub">{{ book.publishedYear }}</div>{% endif %}
      </div>
    </div>
  </div>
{% endif %}
```
(The `.cover--theme-N` rules already set `.deco` background and text colour. Title font-size scales via the size class in SCSS, not inline.)

- [ ] **Step 3: `_partials/stars.html.twig`** — renders `value` (0–5) static stars:

```twig
{# value: int 0..5, size class optional #}
<span class="stars{{ size_class is defined ? ' ' ~ size_class : '' }}">
  {% for i in 1..5 %}<i class="bi {{ i <= value ? 'bi-star-fill' : 'bi-star' }}"></i>{% endfor %}
</span>
```

- [ ] **Step 4: `_partials/reading_status_badge.html.twig`** / `purchase_status_badge.html.twig`:

```twig
{# reading_status_badge: status = ReadingStatus #}
<span class="pill {{ status.pillClass }}">{{ status.label }}</span>
```
```twig
{# purchase_status_badge: status = PurchaseStatus #}
<span class="pill {{ status.pillClass }}">{{ status.label }}</span>
```

- [ ] **Step 5: `_partials/book_card.html.twig`** — used by the library grid, home carousel and similar list. Param: `book`, optional `size` (default `lg`). Port the card markup from `books-list.html` (`.book-card` + `.cover` + title/author + stars + status pills). Wrap in `<a href="{{ path('app_book_show', {id: book.id}) }}" class="book-card text-decoration-none text-ink d-block">`. Use `_partials/book_cover.html.twig`, `_partials/stars.html.twig`, the two badge partials. No inline styles — sizes via `.cover--*`.

- [ ] **Step 6: `_partials/pagination.html.twig`** — param `page` (`App\Pagination\Page`), `route` (route name), `params` (current query params minus `page`). Port the `.pagination` markup from `books-list.html`; build hrefs with `path(route, params|merge({page: n}))`. The "active" page styling — the mockup uses inline `style="background:#F8C8DC;…"`; instead add a `.page-link--active` class in `_components.scss` and apply it (or just rely on Bootstrap's `.active` + a SCSS rule targeting `.page-item.active .page-link`). Round circle dimensions also go to SCSS (`.pagination .page-link { width:42px; height:42px; … }`).

- [ ] **Step 7: `library/_filters.html.twig`** — the sidebar from `books-list.html`. Make it a real `<form method="get" action="{{ path('app_library') }}">` so checkboxes/range submit. Checkboxes: `name="reading[]" value="reading"` etc., `checked` when in `filter.readingStatuses`. Genres: derive the list — for v1 use a fixed list of common categories (`['Roman','Essai','Poésie','Bande dessinée','Théâtre','Sciences humaines']`) plus any present in `filter.categories`; `name="category[]"`. Min rating: a `<select name="minRating">` (1–5 or empty) styled to look like the star row, or keep the star visual and a hidden input set by a tiny inline-free Stimulus controller — for v1, simplest is a labelled `<select>`. Page range: `<input type="range" name="maxPages" min="0" max="800" value="{{ filter.maxPages ?? 800 }}">`. Shelf: `<select name="shelf">` listing `shelves`. "Tout effacer" → link to `path('app_library')`. Submit button "Appliquer". The "Astuce" card stays decorative. No inline styles — the small typographic tweaks (`font-size:.72rem; letter-spacing:.14em`) become a `.filter-label` class in `_filters.scss`.

- [ ] **Step 8: `library/_active_chips.html.twig`** — render one `.chip.chip-active` per active filter value, each as a link that removes just that value (recompute query params without it). Add `data-controller="chip-remove"` if you want JS removal; but a plain link works without JS — prefer the plain link, drop the controller (YAGNI). Keep `chip-remove_controller.js` out of scope then. *(Update the File Structure mentally: `chip-remove_controller.js` is optional/skipped.)*

- [ ] **Step 9: `library/_grid.html.twig`** — `<div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-xl-4 g-4">` of `_partials/book_card.html.twig`. Empty state: if `page.isEmpty`, show a friendly empty card with a link to `app_book_search` ("Votre bibliothèque est vide — ajoutez votre premier livre").

- [ ] **Step 10: `library/index.html.twig`** — `{% extends 'base.html.twig' %}`, `{% block navbar %}{% include '_partials/navbar_app.html.twig' %}{% endblock %}`, `{% block footer %}{% include '_partials/footer_compact.html.twig' %}{% endblock %}`. Body: the page header + search input (the search input here is the *library* search → wrap in a `<form method="get">` with `name="q"` value `filter.query`; the `⌘K` kbd stays decorative), then the `row` with `_filters.html.twig` aside (desktop) + results section (`_active_chips`, the toolbar with view-toggle + sort dropdown [sort dropdown items are links setting `?sort=`], `_grid`, `_partials/pagination`). The mobile filters offcanvas: reuse `_filters.html.twig` inside the offcanvas body. The toolbar count: "{{ page.total }} livre(s)". `data-controller="view-toggle"` on the results container (wired Phase 8) — optional; can ship without and add later. Replace the hardcoded "247 livres" header number with `{{ totalInLibrary }}`. No inline styles.

- [ ] **Step 11: Verify**

```bash
docker compose exec -T php php bin/console lint:twig templates
# create a user + log in via browser, or hit /library after registering; expect 200
```

- [ ] **Step 12: Commit**

```bash
git add src/Controller/LibraryController.php templates/library templates/_partials
git commit -m "feat: library list with filters, sorting and pagination"
```

---

## Phase 6 — Book detail, actions, quotes, shelves

### Task 6.1: `BookVoter`

**Files:** create `src/Security/BookVoter.php`

- [ ] **Step 1: `src/Security/BookVoter.php`:**

```php
<?php

namespace App\Security;

use App\Entity\Book;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, Book> */
final class BookVoter extends Voter
{
    public const OWN = 'BOOK_OWN';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::OWN && $subject instanceof Book;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        return $user instanceof User && $subject instanceof Book && $subject->getOwner() === $user;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Security/BookVoter.php
git commit -m "feat: book ownership voter"
```

### Task 6.2: `BookController` (show + delete) and similar partial

**Files:** create `src/Controller/BookController.php`, `templates/book/show.html.twig`, `templates/book/_tab_summary.html.twig`, `_tab_notes.html.twig`, `_tab_quotes.html.twig`, `_tab_details.html.twig`, `_similar.html.twig`

- [ ] **Step 1: `src/Controller/BookController.php`:**

```php
<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\User;
use App\Repository\BookRepository;
use App\Repository\ShelfRepository;
use App\Security\BookVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class BookController extends AbstractController
{
    #[Route('/books/{id}', name: 'app_book_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Book $book, BookRepository $books, ShelfRepository $shelves): Response
    {
        $this->denyAccessUnlessGranted(BookVoter::OWN, $book);
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('book/show.html.twig', [
            'book' => $book,
            'similar' => $books->findSimilar($book, 3),
            'shelves' => $shelves->findBy(['owner' => $user], ['name' => 'ASC']),
        ]);
    }

    #[Route('/books/{id}/delete', name: 'app_book_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Book $book, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(BookVoter::OWN, $book);
        if (!$this->isCsrfTokenValid('delete_book_'.$book->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $em->remove($book);
        $em->flush();
        $this->addFlash('success', 'Livre retiré de votre bibliothèque.');

        return $this->redirectToRoute('app_library');
    }
}
```

- [ ] **Step 2: `templates/book/show.html.twig`** — port `book-detail.html`. `{% extends 'base.html.twig' %}`, navbar_app, footer_compact. Body: breadcrumb (`app_home` / `app_library` / `book.title`), the hero row:
  - LEFT aside (`.sticky-cover`): big cover via `_partials/book_cover.html.twig with {book, size: 'hero'}`; action buttons "Marquer comme lu" / "J'ai acheté" wired with `data-controller="reading-status"` + forms (see Task 6.3 for the endpoints) — each button is a `<button>` inside a small `<form method="post">` posting to the action route with a CSRF token, so it works without JS; the Stimulus controller progressively enhances. Quick stats cards: Pages = `book.pageCount ?? '—'`, Durée = estimate `~{{ (book.pageCount / 50)|round }} h` or `—`, Langue = `book.language|upper ?? '—'`. The quick-share circle buttons stay decorative (`type="button"`, no behaviour) — or drop them; keep, inert.
  - RIGHT: genre pills (`book.primaryCategory`, year, language), `<h1>` title, author line (`book.authorsLine`), the personal rating row via `data-controller="rating"` with 5 `<button data-rating-value-param="N">` and a hidden form posting to `app_book_rating`; the status pills row (reading status badge with current page, purchase status badge with format/date if set, shelf badge if set); the tabs (`nav-pills` with `data-bs-toggle="pill"`): include `_tab_summary`, `_tab_notes`, `_tab_quotes`, `_tab_details`.
  Then the "Livres similaires" section: `{% include 'book/_similar.html.twig' %}`. All inline `style=""` from the mockup (cover gradients, fixed widths, the `height:2px;width:64px` divider, etc.) → SCSS classes (`.cover--hero` handles the cover; the divider becomes `.title-rule`; etc.).

- [ ] **Step 3: `book/_tab_summary.html.twig`** — `book.description|nl2br` (or a friendly "Pas de résumé." if null), the first-line quote callout only if you have one (skip the hardcoded Camus quote — instead, if `book.quotes` has entries show the most recent in the callout, else omit the callout), and the "À propos de l'auteur" card showing `book.authorsLine` (no bio available from our data → show "Aucune biographie disponible." and drop the "Voir ses N livres" link, or link to `app_library` filtered by `?q={{ book.authors|first }}`). Keep it tasteful.

- [ ] **Step 4: `book/_tab_notes.html.twig`** — the `.note-area` with a `<form method="post" action="{{ path('app_book_notes', {id: book.id}) }}" data-controller="notes-autosave">`; `<textarea name="personalNotes" data-notes-autosave-target="field">{{ book.personalNotes }}</textarea>`; CSRF token; a visible "Enregistrer" submit + a `data-notes-autosave-target="status"` span for "Sauvegardé…". The "Liste"/"Image" buttons → drop (out of scope) or keep inert. No inline styles (`min-height`, dashed border already in `.note-area`).

- [ ] **Step 5: `book/_tab_quotes.html.twig`** — list `book.quotes` as cards (`text`, `page · {{ quote.createdAt|date('d/m/Y') }}`), each with a tiny `<form method="post" action="{{ path('app_quote_delete', {id: book.id, quote: quote.id}) }}">` + CSRF + a small trash button. Below, an "Ajouter une citation" form (`text` textarea, optional `page` number) posting to `app_quote_add` with CSRF. Empty state line if no quotes.

- [ ] **Step 6: `book/_tab_details.html.twig`** — the metadata grid: ISBN (`book.isbn13 ?? book.isbn10 ?? '—'`), Éditeur (`book.publisher ?? '—'`), Format (`book.purchaseFormat ?? '—'`), Pages (`book.pageCount ?? '—'`), Première parution (`book.publishedYear ?? '—'`), Ajouté le (`book.addedAt|date('d/m/Y')`), Étagère (`book.shelf ? book.shelf.name : 'Aucune'`) + a small inline form to assign a shelf: `<select name="shelfId">` of `shelves` (+ a text input + checkbox to create a new one, or a separate "+ Nouvelle étagère" form posting to `app_shelf_create`), posting to `app_book_shelf`. Reading progress edit: a small form (status `<select>` + current page number) posting to `app_book_reading_progress`. Purchase info edit: status `<select>` + format text + date, posting to `app_book_purchase`. (These small forms can also live in the summary tab — put them in details to keep the summary clean.) No inline styles.

- [ ] **Step 7: `book/_similar.html.twig`** — port the "Livres similaires" section; loop `similar` rendering a compact card (reuse `_partials/book_card.html.twig` with `size: 'xs'`, or a dedicated compact layout matching the mockup's horizontal card). If `similar` is empty, hide the section.

- [ ] **Step 8: Verify** — `lint:twig`; load a book detail page after importing one (Phase 4/9). Expect 200.

- [ ] **Step 9: Commit**

```bash
git add src/Controller/BookController.php templates/book
git commit -m "feat: book detail page with tabs and similar books"
```

### Task 6.3: `BookActionController` (reading progress / purchase / rating / notes / shelf)

**Files:** create `src/Controller/BookActionController.php`

- [ ] **Step 1: `src/Controller/BookActionController.php`:**

```php
<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\Shelf;
use App\Entity\User;
use App\Enum\PurchaseStatus;
use App\Enum\ReadingStatus;
use App\Repository\ShelfRepository;
use App\Security\BookVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/books/{id}', requirements: ['id' => '\d+'], methods: ['POST'])]
class BookActionController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    #[Route('/reading-progress', name: 'app_book_reading_progress')]
    public function readingProgress(Book $book, Request $request): Response
    {
        $this->guard($book, 'book_action_'.$book->getId(), $request);
        $status = ReadingStatus::tryFrom((string) $request->request->get('readingStatus'));
        if ($status !== null) { $book->setReadingStatus($status); }
        $page = $request->request->get('currentPage');
        $book->setCurrentPage($page === '' || $page === null ? null : max(0, (int) $page));
        $this->em->flush();

        return $this->respond($book, $request, 'Progression mise à jour.');
    }

    #[Route('/purchase', name: 'app_book_purchase')]
    public function purchase(Book $book, Request $request): Response
    {
        $this->guard($book, 'book_action_'.$book->getId(), $request);
        $status = PurchaseStatus::tryFrom((string) $request->request->get('purchaseStatus'));
        if ($status !== null) { $book->setPurchaseStatus($status); }
        $book->setPurchaseFormat(($f = trim((string) $request->request->get('purchaseFormat', ''))) !== '' ? $f : null);
        $date = (string) $request->request->get('purchasedAt', '');
        $book->setPurchasedAt($date !== '' ? new \DateTimeImmutable($date) : null);
        $this->em->flush();

        return $this->respond($book, $request, 'Statut d\'achat mis à jour.');
    }

    #[Route('/rating', name: 'app_book_rating')]
    public function rating(Book $book, Request $request): Response
    {
        $this->guard($book, 'book_action_'.$book->getId(), $request);
        $value = $request->request->get('rating');
        $book->setRating($value === '' || $value === null ? null : (int) $value);
        $this->em->flush();

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['rating' => $book->getRating()]);
        }

        return $this->respond($book, $request, 'Note enregistrée.');
    }

    #[Route('/notes', name: 'app_book_notes')]
    public function notes(Book $book, Request $request): Response
    {
        $this->guard($book, 'book_action_'.$book->getId(), $request);
        $notes = trim((string) $request->request->get('personalNotes', ''));
        $book->setPersonalNotes($notes !== '' ? $notes : null);
        $this->em->flush();

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['ok' => true, 'savedAt' => (new \DateTimeImmutable())->format('c')]);
        }

        return $this->respond($book, $request, 'Notes enregistrées.');
    }

    #[Route('/shelf', name: 'app_book_shelf')]
    public function shelf(Book $book, Request $request, ShelfRepository $shelves): Response
    {
        $this->guard($book, 'book_action_'.$book->getId(), $request);
        /** @var User $user */
        $user = $this->getUser();
        $shelfId = $request->request->get('shelfId');
        if ($shelfId === '' || $shelfId === null || $shelfId === '0') {
            $book->setShelf(null);
        } else {
            $shelf = $shelves->findOneBy(['id' => (int) $shelfId, 'owner' => $user]);
            $book->setShelf($shelf);
        }
        $this->em->flush();

        return $this->respond($book, $request, 'Étagère mise à jour.');
    }

    private function guard(Book $book, string $tokenId, Request $request): void
    {
        $this->denyAccessUnlessGranted(BookVoter::OWN, $book);
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    private function respond(Book $book, Request $request, string $message): Response
    {
        $this->addFlash('success', $message);

        return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
    }
}
```
(All Stimulus controllers post with `_token` = `csrf_token('book_action_' ~ book.id)` rendered in a hidden input or `data-*` attribute. JS calls send `X-Requested-With: XMLHttpRequest` to get JSON; plain form posts get the redirect+flash. Turbo Stream responses are an optional later refinement — redirect is fine for v1.)

- [ ] **Step 2: Commit**

```bash
git add src/Controller/BookActionController.php
git commit -m "feat: book action endpoints (status, rating, notes, shelf)"
```

### Task 6.4: `QuoteController` + `ShelfController`

**Files:** create `src/Controller/QuoteController.php`, `src/Controller/ShelfController.php`

- [ ] **Step 1: `src/Controller/QuoteController.php`:**

```php
<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\Quote;
use App\Security\BookVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('ROLE_USER')]
class QuoteController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    #[Route('/books/{id}/quotes', name: 'app_quote_add', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function add(Book $book, Request $request, ValidatorInterface $validator): Response
    {
        $this->guard($book, $request);
        $quote = (new Quote())
            ->setBook($book)
            ->setText(trim((string) $request->request->get('text', '')))
            ->setPage(($p = $request->request->get('page')) !== null && $p !== '' ? max(1, (int) $p) : null);

        $errors = $validator->validate($quote);
        if (\count($errors) > 0) {
            $this->addFlash('danger', (string) $errors->get(0)->getMessage());
        } else {
            $this->em->persist($quote);
            $this->em->flush();
            $this->addFlash('success', 'Citation ajoutée.');
        }

        return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
    }

    #[Route('/books/{id}/quotes/{quote}/delete', name: 'app_quote_delete', requirements: ['id' => '\d+', 'quote' => '\d+'], methods: ['POST'])]
    public function delete(Book $book, #[MapEntity(id: 'quote')] Quote $quote, Request $request): Response
    {
        $this->guard($book, $request);
        if ($quote->getBook() !== $book) {
            throw $this->createNotFoundException();
        }
        $this->em->remove($quote);
        $this->em->flush();
        $this->addFlash('success', 'Citation supprimée.');

        return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
    }

    private function guard(Book $book, Request $request): void
    {
        $this->denyAccessUnlessGranted(BookVoter::OWN, $book);
        if (!$this->isCsrfTokenValid('book_action_'.$book->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
```

- [ ] **Step 2: `src/Controller/ShelfController.php`:**

```php
<?php

namespace App\Controller;

use App\Entity\Shelf;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ShelfController extends AbstractController
{
    #[Route('/shelves', name: 'app_shelf_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('create_shelf', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        /** @var User $user */
        $user = $this->getUser();
        $name = trim((string) $request->request->get('name', ''));
        if ($name !== '') {
            $em->persist((new Shelf())->setOwner($user)->setName(mb_substr($name, 0, 80)));
            $em->flush();
            $this->addFlash('success', \sprintf('Étagère « %s » créée.', $name));
        }
        $back = (string) $request->request->get('_back', '');

        return $this->redirect($back !== '' && str_starts_with($back, '/') ? $back : $this->generateUrl('app_library'));
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Controller/QuoteController.php src/Controller/ShelfController.php
git commit -m "feat: quote and shelf endpoints"
```

---

## Phase 7 — Home / landing page

### Task 7.1: `HomeController` + `BookSearchController` + templates

**Files:** create `src/Controller/HomeController.php`, `src/Controller/BookSearchController.php`, `templates/home/index.html.twig`, `templates/book/search.html.twig`, `templates/book/_search_results.html.twig`

- [ ] **Step 1: `src/Controller/HomeController.php`:**

```php
<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_library');
        }

        return $this->render('home/index.html.twig');
    }
}
```

- [ ] **Step 2: `src/Controller/BookSearchController.php`:**

```php
<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\BookImporter;
use App\Service\GoogleBooksClient;
use App\Service\GoogleBooksException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class BookSearchController extends AbstractController
{
    #[Route('/books/search', name: 'app_book_search', methods: ['GET'])]
    public function search(Request $request, GoogleBooksClient $googleBooks): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $results = [];
        $error = null;
        if ($query !== '') {
            try {
                $results = $googleBooks->search($query, 20);
            } catch (GoogleBooksException $e) {
                $error = 'La recherche Google Books est momentanément indisponible. Réessayez dans un instant.';
            }
        }

        $template = $request->query->getBoolean('fragment')
            ? 'book/_search_results.html.twig'
            : 'book/search.html.twig';

        return $this->render($template, ['query' => $query, 'results' => $results, 'error' => $error]);
    }

    #[Route('/books/import', name: 'app_book_import', methods: ['POST'])]
    public function import(Request $request, BookImporter $importer): Response
    {
        if (!$this->isCsrfTokenValid('import_book', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        /** @var User $user */
        $user = $this->getUser();
        $volumeId = trim((string) $request->request->get('volumeId', ''));
        if ($volumeId === '') {
            $this->addFlash('danger', 'Livre invalide.');
            return $this->redirectToRoute('app_book_search');
        }

        try {
            $book = $importer->importFromGoogle($user, $volumeId);
        } catch (GoogleBooksException $e) {
            $this->addFlash('danger', 'Impossible de récupérer ce livre depuis Google Books.');
            return $this->redirectToRoute('app_book_search', ['q' => $request->request->get('q', '')]);
        }

        $this->addFlash('success', \sprintf('« %s » ajouté à votre bibliothèque.', $book->getTitle()));

        return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
    }
}
```

- [ ] **Step 3: `templates/home/index.html.twig`** — port `home.html`. `{% extends 'base.html.twig' %}`, default public navbar + rich footer. Move every inline `style=""` (hero blobs, floating covers with their `left/top/transform/background`, the "currently reading" + "rating" decorative cards, the step-num backgrounds, the CTA covers) into SCSS classes (`.hero-cover--1/-2/-3`, `.hero-badge--reading`, `.hero-badge--rating`, `.step-num--1/-2/-3`, `.cta-cover--1/-2/-3`, `.blob--a/-b`, etc., all defined in `_landing.scss` / `_components.scss`). The "popular books" carousel stays static decorative (it's a landing — real data lives in `/library`). CTAs: "Créer un compte" → `app_register`, "Se connecter" → `app_login`, "Découvrir les livres" / "Ouvrir ma bibliothèque" → `app_library` (will bounce to login if not authed — that's fine), "Voir un livre" → `app_library`. The "Your library at a glance" stat band keeps representative static numbers (marketing). Remove the bottom `<script>` (Bootstrap JS comes from base).

- [ ] **Step 4: `templates/book/search.html.twig`** — `{% extends 'base.html.twig' %}`, navbar_app, footer_compact. A centered search hero reusing the `.search-wrap`/`.search-input` look, wrapped in `<form method="get" action="{{ path('app_book_search') }}">` with `name="q"` value `query`, plus `data-controller="book-search"` and a results container `data-book-search-target="results"`. Render `{% include 'book/_search_results.html.twig' %}` server-side too (so it works without JS). Include the import form pattern in the results partial.

- [ ] **Step 5: `templates/book/_search_results.html.twig`** — if `error`, show an alert. If `query` is empty, show a hint ("Cherchez un titre, un auteur ou un ISBN."). Else if `results` empty, "Aucun résultat pour « {{ query }} »." Else a grid/list of result cards: thumbnail (`result.thumbnailUrl` → `<img>`, else a neutral placeholder block with a `.cover.cover--xs.cover--theme-…` using `crc32(result.title)%12` computed in Twig via a tiny macro or just `result.title|length`... simplest: a `book_cover`-like inline block — acceptable to duplicate a small markup here), title, authors line, publisher · year, and an "Ajouter" form: `<form method="post" action="{{ path('app_book_import') }}"><input type="hidden" name="volumeId" value="{{ result.volumeId }}"><input type="hidden" name="q" value="{{ query }}"><input type="hidden" name="_token" value="{{ csrf_token('import_book') }}"><button class="btn btn-primary rounded-pill">Ajouter</button></form>`. No inline styles.

- [ ] **Step 6: Verify**

```bash
docker compose exec -T php php bin/console lint:twig templates
curl -sf -o /dev/null -w '%{http_code}\n' http://localhost:8080/
```
Expected: `200` for `/`. Log in and check `/books/search`.

- [ ] **Step 7: Commit**

```bash
git add src/Controller/HomeController.php src/Controller/BookSearchController.php templates/home templates/book/search.html.twig templates/book/_search_results.html.twig
git commit -m "feat: landing page and Google Books search/import flow"
```

---

## Phase 8 — Stimulus controllers (progressive enhancement)

> Each controller is registered automatically by StimulusBundle (files in `assets/controllers/`). They progressively enhance forms/markup that already work without JS. After writing them, run `docker compose exec -T php php bin/console asset-map:compile` and click through the pages.

### Task 8.1: Auth-page controllers

**Files:** create `assets/controllers/password-toggle_controller.js`, `assets/controllers/password-strength_controller.js`; delete `assets/controllers/hello_controller.js`; wire `data-controller` attributes in `templates/security/login.html.twig` and `templates/registration/register.html.twig`.

- [ ] **Step 1: `password-toggle_controller.js`:**

```js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'icon'];

    toggle() {
        const show = this.inputTarget.type === 'password';
        this.inputTarget.type = show ? 'text' : 'password';
        if (this.hasIconTarget) {
            this.iconTarget.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
        }
    }
}
```
Wire in `login.html.twig`: the password field's wrapper gets `data-controller="password-toggle"`, the `<input>` gets `data-password-toggle-target="input"`, the toggle button gets `data-action="password-toggle#toggle"` and its `<i>` gets `data-password-toggle-target="icon"`.

- [ ] **Step 2: `password-strength_controller.js`:**

```js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'bar', 'label'];

    connect() { this.evaluate(); }

    evaluate() {
        const v = this.hasInputTarget ? this.inputTarget.value : '';
        let s = 0;
        if (v.length >= 8) s++;
        if (/[A-Z]/.test(v)) s++;
        if (/\d/.test(v)) s++;
        if (/[^A-Za-z0-9]/.test(v) && v.length >= 12) s++;
        if (this.hasBarTarget) this.barTarget.className = 'strength s' + s;
        const labels = ['—', 'Faible', 'Correct', 'Bien', 'Excellent'];
        if (this.hasLabelTarget) this.labelTarget.textContent = labels[s];
    }
}
```
Wire in `register.html.twig`: wrapper around the password block gets `data-controller="password-strength"`, the password input gets `data-password-strength-target="input" data-action="input->password-strength#evaluate"`, the `.strength` div gets `data-password-strength-target="bar"`, the label span gets `data-password-strength-target="label"`. The label colour-by-strength from the mockup → handle via CSS (`.strength.s1 ~ … .strength-label` or just colour the bars; keep the label neutral) — no inline style set from JS.

- [ ] **Step 3:** `git rm assets/controllers/hello_controller.js`. Remove its mention from `assets/controllers.json` if present (StimulusBundle auto-discovers, so likely nothing to change).

- [ ] **Step 4: Compile + eyeball**

```bash
docker compose exec -T php php bin/console asset-map:compile
```
Visit `/login` and `/register`; toggle password, type a password — strength updates.

- [ ] **Step 5: Commit**

```bash
git add assets/controllers templates/security templates/registration
git rm assets/controllers/hello_controller.js
git commit -m "feat: auth page Stimulus controllers (password toggle + strength)"
```

### Task 8.2: Book-detail controllers

**Files:** create `assets/controllers/rating_controller.js`, `assets/controllers/reading-status_controller.js`, `assets/controllers/notes-autosave_controller.js`; wire attributes in `templates/book/show.html.twig` and `templates/book/_tab_notes.html.twig`.

- [ ] **Step 1: `rating_controller.js`** — clickable stars; on click, optimistic UI + `fetch` POST (with `X-Requested-With`) to the action URL given via a value:

```js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['star', 'output'];
    static values = { url: String, token: String, current: Number };

    connect() { this.render(this.currentValue || 0); }

    hover(event) { this.render(Number(event.params.value)); }
    reset() { this.render(this.currentValue || 0); }

    async pick(event) {
        const value = Number(event.params.value);
        this.currentValue = value;
        this.render(value);
        const body = new URLSearchParams({ rating: String(value), _token: this.tokenValue });
        try {
            await fetch(this.urlValue, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body });
        } catch (_) { /* fall back: a normal reload would show server state */ }
    }

    render(value) {
        this.starTargets.forEach((s, i) => {
            const icon = s.querySelector('i');
            icon.classList.toggle('bi-star-fill', i < value);
            icon.classList.toggle('bi-star', i >= value);
        });
        if (this.hasOutputTarget) this.outputTarget.textContent = String(value);
    }
}
```
Wire in `show.html.twig`: container `data-controller="rating" data-rating-url-value="{{ path('app_book_rating', {id: book.id}) }}" data-rating-token-value="{{ csrf_token('book_action_' ~ book.id) }}" data-rating-current-value="{{ book.rating ?? 0 }}"`; each star `<button type="button" data-rating-target="star" data-action="mouseenter->rating#hover mouseleave->rating#reset click->rating#pick" data-rating-value-param="{{ i }}">`; the numeric output span `data-rating-target="output"`. Also keep a no-JS fallback: a tiny `<form method="post">` with a `<select name="rating">` hidden behind `.d-none` or shown via `<noscript>` — simplest: render the JS stars AND a `<noscript>`-wrapped select form.

- [ ] **Step 2: `reading-status_controller.js`** — the "Marquer comme lu" / "J'ai acheté ce livre" toggle buttons. Since each button is already inside a `<form method="post">` that works without JS, the controller just submits the form on click without a full navigation, then swaps the button label/appearance:

```js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['form'];

    async submit(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const body = new FormData(form);
        body.set('_ajax', '1');
        try {
            await fetch(form.action, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body });
            // toggle a class on the button to reflect the new state
            const btn = form.querySelector('button[type=submit]');
            btn.classList.toggle('is-active');
        } catch (_) {
            form.submit(); // hard fallback
        }
    }
}
```
(For v1 it's acceptable to keep these as plain form submits with a redirect — the Stimulus enhancement is optional. If time-constrained, ship the plain forms and skip `reading-status_controller.js`; note that in the task and move its mention to "optional".) Wire `data-controller="reading-status"` on a wrapper and `data-action="reading-status#submit"` on each form's submit (or `submit->reading-status#submit` on the `<form>`).

- [ ] **Step 3: `notes-autosave_controller.js`:**

```js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['field', 'status'];
    static values = { url: String, token: String, delay: { type: Number, default: 1500 } };

    connect() { this.timer = null; }

    schedule() {
        clearTimeout(this.timer);
        if (this.hasStatusTarget) this.statusTarget.textContent = 'Modifications non enregistrées…';
        this.timer = setTimeout(() => this.save(), this.delayValue);
    }

    async save() {
        const body = new URLSearchParams({ personalNotes: this.fieldTarget.value, _token: this.tokenValue });
        try {
            await fetch(this.urlValue, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body });
            if (this.hasStatusTarget) this.statusTarget.textContent = 'Sauvegardé à l’instant';
        } catch (_) {
            if (this.hasStatusTarget) this.statusTarget.textContent = 'Échec de la sauvegarde — utilisez « Enregistrer ».';
        }
    }
}
```
Wire in `_tab_notes.html.twig`: `<form … data-controller="notes-autosave" data-notes-autosave-url-value="{{ path('app_book_notes', {id: book.id}) }}" data-notes-autosave-token-value="{{ csrf_token('book_action_' ~ book.id) }}">`; textarea `data-notes-autosave-target="field" data-action="input->notes-autosave#schedule"`; status span `data-notes-autosave-target="status"`. The explicit "Enregistrer" submit still works (normal POST → redirect+flash).

- [ ] **Step 4: Compile + eyeball** the book detail page.

- [ ] **Step 5: Commit**

```bash
git add assets/controllers templates/book
git commit -m "feat: book detail Stimulus controllers (rating, notes autosave, status)"
```

### Task 8.3: `book-search_controller.js` + `view-toggle_controller.js`

**Files:** create `assets/controllers/book-search_controller.js`, `assets/controllers/view-toggle_controller.js`; wire in `templates/book/search.html.twig` and `templates/library/index.html.twig`.

- [ ] **Step 1: `book-search_controller.js`** — debounced fetch of `app_book_search?fragment=1&q=…` into the results container:

```js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'results'];
    static values = { url: String, delay: { type: Number, default: 350 } };

    connect() { this.timer = null; }

    schedule() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.run(), this.delayValue);
    }

    async run() {
        const q = this.inputTarget.value.trim();
        const url = `${this.urlValue}?fragment=1&q=${encodeURIComponent(q)}`;
        try {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            this.resultsTarget.innerHTML = await res.text();
        } catch (_) { /* keep server-rendered results */ }
    }
}
```
Wire in `search.html.twig`: `<form … data-controller="book-search" data-book-search-url-value="{{ path('app_book_search') }}">` (keep `method="get"` so Enter still works without JS, and add `data-action="submit->book-search#run"` to intercept), input `data-book-search-target="input" data-action="input->book-search#schedule"`, results container `data-book-search-target="results"`.

- [ ] **Step 2: `view-toggle_controller.js`** — switches a CSS class on the grid container between `view--grid` / `view--list` / `view--shelf` and toggles the active button. Define those layout classes in `_filters.scss`/`_book-card.scss` (e.g. `view--list` makes cards horizontal). Persist choice in `localStorage`:

```js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['container', 'button'];
    static classes = ['grid', 'list', 'shelf'];

    connect() {
        const saved = localStorage.getItem('library-view') || 'grid';
        this.apply(saved);
    }

    pick(event) { this.apply(event.params.view); localStorage.setItem('library-view', event.params.view); }

    apply(view) {
        this.containerTarget.classList.remove('view--grid', 'view--list', 'view--shelf');
        this.containerTarget.classList.add(`view--${view}`);
        this.buttonTargets.forEach((b) => b.classList.toggle('active', b.dataset.viewToggleViewParam === view));
    }
}
```
Wire in `library/index.html.twig`: the `.view-toggle` group + grid container. (Optional for v1 — the page already works without it; ship if time allows.)

- [ ] **Step 3: Compile + eyeball.**

- [ ] **Step 4: Commit**

```bash
git add assets/controllers templates/book templates/library
git commit -m "feat: live book search and library view toggle controllers"
```

---

## Phase 9 — Tests (functional)

> Functional tests use `WebTestCase`. DB strategy: the simplest reliable approach in the container is to point the **test** env at a separate database and create the schema in a base test case. Add `when@test: doctrine: dbal: dbname_suffix: '_test%env(default::TEST_TOKEN)%'` (or just `DATABASE_URL` with `?dbname=app_test` in `.env.test`), and a `KernelTestCase`-based base class that runs `doctrine:schema:create` / drops between tests. Alternatively use `dama/doctrine-test-bundle` for transaction rollback isolation — recommended; install it.

### Task 9.1: Test DB isolation setup

**Files:** modify `.env.test`, `config/packages/` (test), `phpunit.dist.xml`; install `dama/doctrine-test-bundle`; create `tests/AppTestCase.php` (optional base helper) + a script to create the test schema.

- [ ] **Step 1: Install the test bundle**

```bash
docker compose exec -T php composer require --dev dama/doctrine-test-bundle
```

- [ ] **Step 2: Enable it for tests** — in `config/bundles.php` it's auto-registered for `test`; add to `config/packages/test/dama_doctrine_test_bundle.yaml` (created by recipe) or `phpunit.dist.xml`:

```xml
<extensions>
    <bootstrap class="DAMA\DoctrineTestBundle\PHPUnit\PHPUnitExtension"/>
</extensions>
```

- [ ] **Step 3: `.env.test`** — set a dedicated DB name, e.g. append `&dbname=app_test` or override `DATABASE_URL` to `postgresql://app:!ChangeMe!@database:5432/app_test?serverVersion=16&charset=utf8`. (When run via `docker compose exec php`, host `database` resolves.)

- [ ] **Step 4: Create the test DB + schema once**

```bash
docker compose exec -T php php bin/console --env=test doctrine:database:create --if-not-exists
docker compose exec -T php php bin/console --env=test doctrine:migrations:migrate --no-interaction
```
(Document this in README; tests assume the schema exists. With `dama/doctrine-test-bundle`, each test runs in a rolled-back transaction.)

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock config phpunit.dist.xml .env.test
git commit -m "test: isolated test database with dama/doctrine-test-bundle"
```

### Task 9.2: Smoke + access tests

**Files:** create `tests/Controller/HomeControllerTest.php`, `tests/Controller/LibraryAccessTest.php`

- [ ] **Step 1: `HomeControllerTest`:**

```php
<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HomeControllerTest extends WebTestCase
{
    public function testHomeIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Marca-Page');
    }

    public function testLoginPageRenders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="_username"]');
        self::assertSelectorExists('input[name="_password"]');
    }
}
```

- [ ] **Step 2: `LibraryAccessTest`:**

```php
<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LibraryAccessTest extends WebTestCase
{
    public function testLibraryRedirectsAnonymousToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/library');
        self::assertResponseRedirects('/login');
    }

    public function testBookSearchRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/books/search');
        self::assertResponseRedirects('/login');
    }
}
```

- [ ] **Step 3: Run**

```bash
docker compose exec -T php php bin/phpunit tests/Controller/HomeControllerTest.php tests/Controller/LibraryAccessTest.php
```
Expected: OK.

- [ ] **Step 4: Commit**

```bash
git add tests/Controller/HomeControllerTest.php tests/Controller/LibraryAccessTest.php
git commit -m "test: home smoke test and library access control"
```

### Task 9.3: Registration + login flow

**Files:** create `tests/Controller/RegistrationControllerTest.php`, `tests/Controller/SecurityFlowTest.php`

- [ ] **Step 1: `RegistrationControllerTest`:**

```php
<?php

namespace App\Tests\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RegistrationControllerTest extends WebTestCase
{
    public function testRegisterCreatesUserAndLogsIn(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/register');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Créer mon coffret')->form();
        $values = $form->getPhpValues();
        // form name is "registration_form" by default
        $name = array_key_first($values);
        $client->request('POST', '/register', [$name => array_merge($values[$name], [
            'displayName' => 'Claire',
            'email' => 'claire@example.test',
            'plainPassword' => ['first' => 'Secret123', 'second' => 'Secret123'],
            'agreeTerms' => '1',
        ])]);

        self::assertResponseRedirects('/library');
        self::assertNotNull(static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'claire@example.test']));
    }
}
```
(If extracting the form name via crawler proves fiddly, instead POST directly to `/register` with the array key `registration_form` and include `_token` read from the rendered form: `$crawler->filter('input[name="registration_form[_token]"]')->attr('value')`. Use whichever is cleaner once you see the rendered markup.)

- [ ] **Step 2: `SecurityFlowTest`** — create a user via the repository/EntityManager in the test (password hashed via the hasher service, or rely on the `plaintext` test hasher from `security.yaml`), then POST `/login`:

```php
<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityFlowTest extends WebTestCase
{
    public function testLoginRedirectsToLibrary(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail('bob@example.test')->setDisplayName('Bob')->setPassword('Secret123'); // plaintext hasher in test
        $em->persist($user);
        $em->flush();

        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Ouvrir ma bibliothèque')->form([
            '_username' => 'bob@example.test',
            '_password' => 'Secret123',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/library');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }
}
```

- [ ] **Step 3: Run**

```bash
docker compose exec -T php php bin/phpunit tests/Controller/RegistrationControllerTest.php tests/Controller/SecurityFlowTest.php
```
Expected: OK (adjust selectors/form names as needed against the actual rendered HTML).

- [ ] **Step 4: Commit**

```bash
git add tests/Controller/RegistrationControllerTest.php tests/Controller/SecurityFlowTest.php
git commit -m "test: registration and login flows"
```

### Task 9.4: Book import flow (Google client doubled)

**Files:** create `tests/Controller/BookImportTest.php`; modify `config/services_test.yaml` (or `config/services.yaml` under `when@test`) to alias `GoogleBooksClient` to a stub, OR use `MockHttpClient` bound only in test.

- [ ] **Step 1: Provide a fake Google client for tests** — simplest: in `config/services.yaml` add at the bottom:

```yaml
when@test:
    services:
        App\Service\GoogleBooksClient:
            class: App\Tests\Double\FakeGoogleBooksClient
```
and create `tests/Double/FakeGoogleBooksClient.php` extending `GoogleBooksClient` (or re-implementing its public API) returning a canned `GoogleBookResult` for `getVolume('test-vol-1')` and a one-item array for `search()`. (It must have a no-arg constructor or be autowired with a `MockHttpClient` — give it a no-arg constructor and ignore HTTP.) Note: `GoogleBooksClient` is `final` in the plan above — for testability, either remove `final`, or make `FakeGoogleBooksClient` implement a small `GoogleBooksClientInterface` that both classes implement and type-hint the interface in `BookImporter`/`BookSearchController`. **Pick the interface approach**: add `src/Service/GoogleBooksClientInterface.php` (`search(): array`, `getVolume(): GoogleBookResult`), have `GoogleBooksClient implements GoogleBooksClientInterface`, type-hint the interface everywhere it's injected. (Go back and adjust Tasks 4.2, 4.3, 7.1 hints to the interface — small change.)

- [ ] **Step 2: `tests/Double/FakeGoogleBooksClient.php`:**

```php
<?php

namespace App\Tests\Double;

use App\Dto\GoogleBookResult;
use App\Service\GoogleBooksClientInterface;

final class FakeGoogleBooksClient implements GoogleBooksClientInterface
{
    public function search(string $query, int $maxResults = 20): array
    {
        return $query === '' ? [] : [$this->volume('test-vol-1')];
    }

    public function getVolume(string $volumeId): GoogleBookResult
    {
        return $this->volume($volumeId);
    }

    private function volume(string $id): GoogleBookResult
    {
        return new GoogleBookResult(
            volumeId: $id, title: "L'Étranger", subtitle: null, authors: ['Albert Camus'],
            publisher: 'Gallimard', publishedDate: '1942', pageCount: 186, language: 'fr',
            description: 'Meursault…', categories: ['Fiction'], isbn10: '2070360024',
            isbn13: '9782070360024', thumbnailUrl: null,
        );
    }
}
```

- [ ] **Step 3: `tests/Controller/BookImportTest.php`:**

```php
<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BookImportTest extends WebTestCase
{
    public function testImportAddsBookToLibrary(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail('reader@example.test')->setDisplayName('Reader')->setPassword('Secret123');
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        // get a CSRF token from the search page
        $crawler = $client->request('GET', '/books/search?q=camus');
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('input[name="_token"]')->first()->attr('value');

        $client->request('POST', '/books/import', ['volumeId' => 'test-vol-1', '_token' => $token]);
        self::assertResponseRedirects();

        $books = static::getContainer()->get(BookRepository::class)->findBy(['owner' => $user]);
        self::assertCount(1, $books);
        self::assertSame("L'Étranger", $books[0]->getTitle());

        // and it shows up on the library page
        $client->request('GET', '/library');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', "L'Étranger");
    }
}
```

- [ ] **Step 4: Run the full suite**

```bash
docker compose exec -T php php bin/phpunit
```
Expected: all green.

- [ ] **Step 5: Commit**

```bash
git add src/Service/GoogleBooksClientInterface.php tests/Double tests/Controller/BookImportTest.php config/services.yaml
git commit -m "test: book import flow with a fake Google Books client"
```

---

## Phase 10 — Docs and final pass

### Task 10.1: README

**Files:** create `README.md`

- [ ] **Step 1: Write `README.md`** (in French, unaccented headings per project convention) covering: project description; prerequisites (Docker + Docker Compose); `docker compose build && docker compose up -d`; first-run commands (`docker compose exec php composer install`, `docker compose exec php bin/console doctrine:migrations:migrate`); URL `http://localhost:8080`; the `GOOGLE_BOOKS_API_KEY` env var (optional); how to run tests (`docker compose exec php bin/console --env=test doctrine:database:create --if-not-exists` + `doctrine:migrations:migrate --env=test`, then `docker compose exec php bin/phpunit`); how to rebuild assets (`docker compose exec php bin/console asset-map:compile`); note that ALL commands run inside the `php` container.

- [ ] **Step 2: Commit**

```bash
git add README.md
git commit -m "docs: project README (Docker-based workflow)"
```

### Task 10.2: Final verification

- [ ] **Step 1: Lint everything**

```bash
docker compose exec -T php php bin/console lint:twig templates
docker compose exec -T php php bin/console lint:yaml config
docker compose exec -T php php bin/console lint:container
```
Expected: no errors.

- [ ] **Step 2: Full test run**

```bash
docker compose exec -T php php bin/phpunit
```
Expected: green.

- [ ] **Step 3: Manual click-through in the browser** (`http://localhost:8080`): register → land on empty library → "Ajouter un livre" → search a real title → import → land on detail → set rating, write a note (autosave), add a quote, mark as read, assign a shelf → back to library → filter by status, sort, paginate → log out → log back in → state persisted. Fix any issue found, committing fixes as `fix: …`.

- [ ] **Step 4: Check no inline styles slipped in**

```bash
grep -rnE 'style="|<style' templates/ && echo "INLINE STYLE FOUND — fix it" || echo "clean"
```
Expected: `clean`. (SVG `<svg>` markup is fine; only `style="…"` attributes and `<style>` tags are forbidden.)

- [ ] **Step 5: Final commit if anything changed.**

---

## Self-Review Notes (for the plan author)

- **Spec coverage:** Docker FrankenPHP (Phase 0) ✓; SCSS via sass-bundle, no inline CSS (1.1, 1.2, 10.2 step 4) ✓; entities/enums/migration (Phase 2) ✓; multi-user security + registration (Phase 3) ✓; Google Books client/DTO/importer (Phase 4) ✓; library list with filters/sort/pagination + similar books (Phase 5, 6.2) ✓; book detail tabs + actions + quotes + shelves (Phase 6) ✓; home/landing + search/import flow (Phase 7) ✓; Stimulus controllers (Phase 8) ✓; tests (Phase 9) ✓; README (Phase 10) ✓. Out-of-scope items (OAuth, email verify, password reset, ISBN scan, CSV import, stats page, shelf-management page, fixtures) are explicitly not built — consistent with the spec.
- **Adjustments captured inline:** `chip-remove_controller.js` dropped (plain links suffice); `GoogleBooksClient` gains a `GoogleBooksClientInterface` for testability (type-hint the interface in `BookImporter` and `BookSearchController`); `reading-status_controller.js` and `view-toggle_controller.js` are optional progressive enhancements (the pages work without them).
- **Type consistency:** `ReadingStatus`/`PurchaseStatus` `pillClass()`/`label()` used in badges and enums match; `Page` methods (`pageCount`, `hasNext`, `hasPrevious`, `isEmpty`, `pageRange`) used by `pagination.html.twig`; `Book` getters used in templates (`authorsLine`, `publishedYear`, `primaryCategory`, `progressPercent`, `hasThumbnail`) all defined in Task 2.2; CSRF token ids consistent (`book_action_<id>` for all book actions and quotes, `delete_book_<id>` for delete, `import_book`, `create_shelf`, `authenticate` for login).
- **Open implementation decisions (flagged, not blocking):** json-column searching via DQL `CAST(... AS text)` vs. dedicated `authorsText`/`categoriesText` mirror columns; exact form-name handling in functional tests; whether to ship the optional Stimulus enhancements. None change the public interfaces.
