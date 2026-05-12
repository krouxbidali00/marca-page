# Marca-Page — conception

> Date : 2026-05-12
> Statut : valide (en attente de relecture)

## Contexte

Application personnelle de gestion de bibliotheque, batie sur un Symfony 8 neuf
(AssetMapper, Stimulus, Turbo, Bootstrap et Alpine.js deja installes via le dossier
`assets/vendor/`). Cinq maquettes HTML statiques servent de reference : accueil,
connexion, inscription, liste des livres, detail d'un livre. L'application s'appelle
**Marca-Page** (les maquettes utilisent encore le nom provisoire « MonRayon » — a
renommer partout).

Les donnees des livres proviennent de l'**API Google Books** : l'utilisateur recherche
un livre, le selectionne, et ses metadonnees sont importees dans sa bibliotheque.

## Objectifs

- Application multi-utilisateurs fonctionnelle (inscription / connexion reelles, chaque
  utilisateur a une bibliotheque isolee).
- Import de livres depuis Google Books, puis suivi personnel : statut de lecture, statut
  d'achat, note, notes libres, citations, etagere.
- Liste de bibliotheque avec recherche, filtres, tri et pagination.
- Page de detail d'un livre fidele a la maquette (onglets resume / notes / citations /
  details, actions, livres similaires).
- Aucun CSS en ligne dans les fichiers Twig : tout passe par Bootstrap ou par des
  fichiers SCSS organises, compiles via `symfonycasts/sass-bundle`.
- Toutes les commandes (php, console, composer, node) passent par un conteneur Docker
  dedie a l'application (FrankenPHP).

## Hors perimetre (v1)

- OAuth Google / Apple (boutons decoratifs uniquement).
- Verification d'email, reinitialisation de mot de passe (lien « mot de passe oublie »
  decoratif).
- Scan ISBN, import CSV, autocompletion de metadonnees hors Google Books.
- Page « Statistiques », page de gestion des etageres.
- Fixtures / jeu de donnees de demonstration.

## Infrastructure Docker

Le `compose.yaml` actuel ne contient que `database` (Postgres 16) et `mailer` (Mailpit).
On ajoute le conteneur applicatif via le recipe Docker officiel Symfony (FrankenPHP) :

- `Dockerfile` multi-stage base sur `dunglas/frankenphp`, PHP 8.4, extensions
  `pdo_pgsql`, `intl`, `opcache`, `zip` ; Composer ; Node.js (pour AssetMapper /
  d'eventuels outils front) ; etape `dev` avec Xdebug optionnel.
- `frankenphp/Caddyfile`, `frankenphp/conf.d/` (config php prod/dev), `frankenphp/docker-entrypoint.sh`.
- `compose.yaml` : ajout du service `php` (`build: .`, `depends_on: database`, volume
  `caddy_data`/`caddy_config`, healthcheck), expose 80/443.
- `compose.override.yaml` : surcharge dev (montage du code source en bind mount, ports
  exposes, `XDEBUG_MODE`, `APP_ENV=dev`).
- `.dockerignore`.
- `DATABASE_URL` cote conteneur pointe sur l'hote `database` (et non `127.0.0.1`).

Une fois le conteneur en place, toutes les commandes de la suite du projet sont
executees via `docker compose exec -T php <commande>` (composer, `bin/console`,
`bin/phpunit`, node…).

## Modele de donnees

Approche retenue : **une ligne `Book` par utilisateur**. A l'import, les metadonnees
Google Books sont copiees dans une ligne `Book` qui appartient a l'utilisateur et porte
aussi ses donnees personnelles. Pas de table pivot ; les metadonnees peuvent etre
dupliquees entre utilisateurs (acceptable ici).

### Entites (`src/Entity/`)

**User** — implemente `UserInterface`, `PasswordAuthenticatedUserInterface`.
- `id`, `email` (unique), `password` (hash), `displayName`, `roles` (json, defaut `["ROLE_USER"]`), `createdAt`.
- `OneToMany` : `books`, `shelves`.

**Book** — appartient a un `User`.
- `id`, `owner` (ManyToOne User, non null).
- Metadonnees Google Books : `googleVolumeId` (string nullable), `title`, `subtitle`
  (nullable), `authors` (json — tableau de chaines), `publisher` (nullable),
  `publishedDate` (string nullable, ex. « 1942 » ou « 1942-06-01 »), `pageCount`
  (int nullable), `language` (string nullable, ex. « fr »), `description` (text nullable),
  `categories` (json nullable), `isbn10` (nullable), `isbn13` (nullable),
  `thumbnailUrl` (nullable).
- `coverTheme` (smallint, 0–11) : theme de couverture CSS deterministe a partir du
  titre, utilise quand `thumbnailUrl` est absent.
- Donnees personnelles : `readingStatus` (enum `ReadingStatus` : ToRead / Reading /
  Finished / Abandoned, defaut ToRead), `currentPage` (int nullable), `purchaseStatus`
  (enum `PurchaseStatus` : ToBuy / Bought / Lent, defaut ToBuy), `purchasedAt` (date
  nullable), `purchaseFormat` (string nullable, ex. « poche »), `rating` (int nullable
  1–5), `personalNotes` (text nullable), `shelf` (ManyToOne `Shelf` nullable).
- `addedAt` (datetime_immutable), `updatedAt` (datetime_immutable).
- `OneToMany` : `quotes` (cascade persist + remove).

**Quote** — appartient a un `Book`.
- `id`, `book` (ManyToOne Book, non null), `text` (text), `page` (int nullable),
  `createdAt` (datetime_immutable).

**Shelf** — appartient a un `User`.
- `id`, `owner` (ManyToOne User, non null), `name` (string), `createdAt`.
- `OneToMany` : `books` (a la suppression d'une etagere, `book.shelf` repasse a null).

### Enums (`src/Enum/`)

- `ReadingStatus` (backed string) : `to_read`, `reading`, `finished`, `abandoned` ;
  methodes `label(): string` (FR), `pillClass(): string` (classe de pastille).
- `PurchaseStatus` (backed string) : `to_buy`, `bought`, `lent` ; idem `label()`,
  `pillClass()`.

Mappes en `enumType` Doctrine.

## Couvertures de livres

- Si `thumbnailUrl` present : `<img class="cover-img" …>`.
- Sinon : `<div class="cover cover--theme-N">…</div>` ou N est `coverTheme`. 12 classes
  `cover--theme-0` … `cover--theme-11` sont definies en SCSS, reproduisant la palette
  des maquettes (#3A2A33, #E0D4F7, #8A3A5C, #2E5C3E, #FFE5B4, #4A3C7A, #7A4A3A,
  #1E3A5F, #5C2E2E, #F8C8DC, #2A4A3E, #C8E6D0) avec leurs degrades. Aucun `style=""`
  dynamique cote Twig.
- Le partial `templates/_partials/book_cover.html.twig` encapsule ce choix (parametres :
  `book`, taille via classe de variante).

## Services (`src/Service/`)

**GoogleBooksClient** — depend de `Symfony\Contracts\HttpClient\HttpClientInterface`.
- `search(string $query, int $maxResults = 20): GoogleBookResult[]`
- `getVolume(string $volumeId): GoogleBookResult`
- Mappe le JSON Google (`volumeInfo`, `industryIdentifiers`, `imageLinks`) vers le DTO.
- Cle API optionnelle : parametre `%env(GOOGLE_BOOKS_API_KEY)%` (vide → requetes
  anonymes, suffisant en faible volume). Gestion d'erreur : exceptions reseau /
  HTTP capturees et transformees en `GoogleBooksException` ; le controleur affiche un
  message d'erreur convivial.

**BookImporter** — `importFromGoogle(User $user, string $volumeId): Book`.
- Recupere le volume via `GoogleBooksClient`, cree le `Book`, calcule `coverTheme` via
  `CoverThemePicker`, le rattache a l'utilisateur, persiste.
- Si l'utilisateur possede deja ce `googleVolumeId` : ne reimporte pas, renvoie le
  livre existant (le controleur informe l'utilisateur).

**CoverThemePicker** — `pick(string $seed): int` (0–11), deterministe (`crc32`).

### DTO (`src/Dto/`)

- `GoogleBookResult` : `volumeId`, `title`, `subtitle`, `authors[]`, `publisher`,
  `publishedDate`, `pageCount`, `language`, `description`, `categories[]`, `isbn10`,
  `isbn13`, `thumbnailUrl`.
- `LibraryFilter` : construit depuis la requete HTTP — `query` (texte), `readingStatuses[]`,
  `purchaseStatuses[]`, `categories[]`, `minRating`, `maxPages`, `shelfId`, `sort`
  (`recent` | `title_asc` | `author` | `rating` | `published`), `page`.

## Controleurs et routes (`src/Controller/`)

| Controleur | Route | Acces |
|---|---|---|
| `HomeController::index` | `GET /` | public ; si connecte → redirige `/library` |
| `SecurityController::login` | `GET\|POST /login` | public |
| `SecurityController::logout` | `GET /logout` | gere par le firewall |
| `RegistrationController::register` | `GET\|POST /register` | public |
| `LibraryController::index` | `GET /library` | ROLE_USER |
| `BookSearchController::search` | `GET /books/search` | ROLE_USER |
| `BookSearchController::import` | `POST /books/import` | ROLE_USER |
| `BookController::show` | `GET /books/{id}` | ROLE_USER + proprietaire |
| `BookController::delete` | `POST /books/{id}/delete` | ROLE_USER + proprietaire |
| `BookActionController::readingProgress` | `POST /books/{id}/reading-progress` | ROLE_USER + proprietaire |
| `BookActionController::purchase` | `POST /books/{id}/purchase` | ROLE_USER + proprietaire |
| `BookActionController::rating` | `POST /books/{id}/rating` | ROLE_USER + proprietaire |
| `BookActionController::notes` | `POST /books/{id}/notes` | ROLE_USER + proprietaire |
| `BookActionController::shelf` | `POST /books/{id}/shelf` | ROLE_USER + proprietaire |
| `QuoteController::add` | `POST /books/{id}/quotes` | ROLE_USER + proprietaire |
| `QuoteController::delete` | `POST /books/{id}/quotes/{quote}/delete` | ROLE_USER + proprietaire |
| `ShelfController::create` | `POST /shelves` | ROLE_USER |

- Verification de propriete : voter `BookVoter` (attribut `BOOK_OWN`) ou simple
  comparaison `book.owner === user` dans le controleur (le voter est privilegie).
- Les actions `BookActionController` et `QuoteController` repondent en **Turbo Stream**
  quand la requete l'accepte, sinon redirigent vers le detail du livre. CSRF sur chaque
  formulaire (token verifie via `isCsrfTokenValid`).
- `/library` lit les filtres depuis la query string, delegue a
  `BookRepository::paginateForLibrary(User, LibraryFilter)` qui renvoie un objet de
  pagination maison (`App\Pagination\Page`) base sur `Doctrine\ORM\Tools\Pagination\Paginator`
  (pas de bundle supplementaire). Taille de page : 24.
- `/books/search` : la recherche live est faite cote client (Stimulus → `GET /books/search`
  avec `?q=` en `format=turbo_stream` ou fragment HTML rendu dans une Turbo Frame).
  Choix d'implementation precise au moment du plan ; principe : resultats Google Books
  affiches avec un bouton « Ajouter » par resultat (POST vers `/books/import`).

## Securite

- `config/packages/security.yaml` :
  - provider : `entity` sur `App\Entity\User`, propriete `email`.
  - password hashers : `auto`.
  - firewall `main` : `lazy: true`, `form_login` (`login_path: app_login`,
    `check_path: app_login`, `default_target_path: app_library`), `logout`
    (`path: app_logout`), `remember_me` optionnel.
  - `access_control` : `^/$`, `^/login`, `^/register` → `PUBLIC_ACCESS` ; `^/` →
    `ROLE_USER`.
- `RegistrationFormType` : `displayName`, `email`, `plainPassword` (RepeatedType,
  contraintes : longueur ≥ 8, au moins une majuscule, un chiffre), `agreeTerms`
  (checkbox `IsTrue`). A la soumission valide : hash du mot de passe, persist, login
  programmatique, redirection vers `/library`.

## Templates (`templates/`)

- `base.html.twig` : `<head>` avec `<link>` polices Google (Fraunces + Inter),
  `<link>` Bootstrap CSS, `<link>` Bootstrap Icons CSS, `{{ importmap('app') }}` ;
  `<body>` avec blocs `navbar`, `body`, `footer`, `javascripts`. Aucun `<style>` inline.
- Partials (`templates/_partials/`) : `navbar_public.html.twig` (Se connecter / Creer un
  compte), `navbar_app.html.twig` (liens app + recherche + menu avatar + « Ajouter un
  livre »), `footer.html.twig`, `footer_compact.html.twig`, `flash_messages.html.twig`,
  `book_cover.html.twig`, `book_card.html.twig` (reutilise : grille bibliotheque,
  carrousel accueil, livres similaires), `pagination.html.twig`,
  `reading_status_badge.html.twig`, `purchase_status_badge.html.twig`.
- Vues : `home/index.html.twig` ; `security/login.html.twig` ;
  `registration/register.html.twig` ; `library/index.html.twig` (+ `_filters.html.twig`,
  `_grid.html.twig`, `_active_chips.html.twig`) ; `book/show.html.twig` (+ partials
  d'onglets : `_tab_summary.html.twig`, `_tab_notes.html.twig`, `_tab_quotes.html.twig`,
  `_tab_details.html.twig`) ; `book/search.html.twig` (+ `_results.html.twig`).
- Le contenu marketing de l'accueil (compteurs, couvertures flottantes) reste statique
  et decoratif ; les classes de couleurs / animations sont en SCSS, pas en `style=""`.

Regle stricte : **aucun `<style>` ni attribut `style=""` dans les Twig**. Tout style
non couvert par Bootstrap vit dans `assets/styles/*.scss`.

## Front : SCSS et Stimulus

### SCSS (`assets/styles/`, compile par sass-bundle)

- `app.scss` — point d'entree, `@use` des partials ci-dessous. Remplace l'ancien
  `assets/styles/app.css` (supprime).
- `_tokens.scss` — bloc `:root { … }` : surcharges des variables Bootstrap (`--bs-primary`,
  `--bs-body-bg`, `--bs-border-radius*`, `--bs-link-*`, `--bs-box-shadow*`, `--bs-focus-ring-*`)
  + palette custom (`--rose-soft`, `--rose-powder`, `--lavande`, `--peche`, `--sauge`,
  `--ink`, `--mute`, `--line`, `--cream`).
- `_typography.scss` — `.font-serif`, `.eyebrow`, `.text-mute`, `.text-ink`, reglages
  `html/body`.
- `_buttons.scss` — surcharges `.btn-primary` / `.btn-outline-primary`, `.btn-soft`,
  `.btn-link`.
- `_navbar.scss` — `.navbar-mr`, `.brand-mark`.
- `_book-cover.scss` — `.cover`, `.spine`, `.deco`, `.body`, `.title-c`, `.author-c`,
  `.pub`, `.cover-stage`, `.cover-img`, et les 12 `.cover--theme-N`.
- `_book-card.scss` — `.book-card`, effets de survol.
- `_pills.scss` — `.pill`, `.pill-lavande/peche/sauge/rose/outline`, `.chip`, `.chip-active`,
  `.chip .x`.
- `_components.scss` — `.stars`, `.avatar`, `.grain`, `.blob`, `.step-num`, `.scroll-row`.
- `_filters.scss` — `.filter-card`, `.filter-check`, `.search-input`, `.search-wrap`,
  `.sort-btn`, `.view-toggle`.
- `_book-detail.scss` — `.note-area`, `.quote-callout`, surcharges `.nav-pills`,
  `.sticky-cover`, `.action-row`, `.sim-card`.
- `_landing.scss` — `.hero-stack`, `.float-a/b/c`, `@keyframes floata`, `.section`,
  `.cta-card`.
- `_auth.scss` — `.illus-panel`, `.teacup`, `.quote-c`, `.strength` (+ etats `.s1`–`.s4`),
  `.step-dot`, `.step-bar`, `.feature-row`, surcharges `.form-control` / `.form-floating`.

Bootstrap reste consomme sous forme de CSS compile (le fichier deja present dans
`assets/vendor/`, ou via importmap), inchange ; nos SCSS se superposent en surchargeant
les variables CSS — exactement la technique des maquettes. Bootstrap Icons : ajoute via
`<link>` CDN dans `base.html.twig` (ou via AssetMapper si trivial) — ce n'est pas du CSS
inline.

### Stimulus (`assets/controllers/`)

- `rating_controller.js` — etoiles cliquables, POST vers `/books/{id}/rating` (fetch),
  mise a jour optimiste de l'affichage.
- `reading-status_controller.js` — boutons « Marquer comme lu » / « J'ai achete »,
  POST vers les actions correspondantes, bascule de l'apparence.
- `notes-autosave_controller.js` — sauvegarde debouncee (≈1,5 s) du `<textarea>` vers
  `/books/{id}/notes`, affiche « Sauvegarde il y a … ».
- `book-search_controller.js` — saisie debouncee, requete vers `/books/search`, rendu
  des resultats Google Books, bouton « Ajouter ».
- `password-strength_controller.js` — jauge de robustesse (page inscription).
- `password-toggle_controller.js` — afficher / masquer le mot de passe.
- `view-toggle_controller.js` — bascule grille / liste / etagere sur `/library`
  (changement de classe sur le conteneur ; rechargement avec parametre si besoin).
- `chip-remove_controller.js` — retrait d'une puce de filtre → met a jour la query string
  et recharge.

Les controllers JS inline des maquettes sont supprimes et reportes dans ces controllers.
Le `csrf_protection_controller.js` et `hello_controller.js` existants : on conserve le
premier, on supprime `hello_controller.js`.

## Configuration et environnement

- `.env` : ajout de `GOOGLE_BOOKS_API_KEY=` (vide par defaut). `DATABASE_URL` ajuste
  pour pointer sur `database` depuis le conteneur. `APP_SECRET` genere.
- `config/services.yaml` : binding de `%env(GOOGLE_BOOKS_API_KEY)%` sur le service
  `GoogleBooksClient` (ou via `bind`).
- Migration Doctrine : une migration initiale generee apres creation des entites
  (`make:migration`), executee dans le conteneur.

## Tests (PHPUnit)

- Unitaires :
  - `CoverThemePickerTest` — determinisme et bornes (0–11).
  - `GoogleBooksClientTest` — `MockHttpClient` ; verifie le mapping JSON → `GoogleBookResult`
    (titre, auteurs, ISBN, vignette) et la gestion d'une reponse vide / d'une erreur HTTP.
  - `LibraryFilterTest` — construction depuis une `Request`, valeurs par defaut, bornes.
- Fonctionnels (`WebTestCase`) :
  - accueil accessible sans authentification (200).
  - `/library` redirige vers `/login` si non authentifie.
  - parcours d'inscription : POST `/register` valide → utilisateur cree → redirige
    `/library`.
  - parcours de connexion : POST `/login` avec un utilisateur existant → redirige
    `/library`.
  - import d'un livre : `GoogleBooksClient` remplace par un double dans le conteneur de
    test ; POST `/books/import` → `Book` cree pour l'utilisateur, visible sur `/library`.

Les tests fonctionnels utilisent une base de test (schema cree via Doctrine en setUp ou
base SQLite de test selon ce qui est le plus simple dans le conteneur — a trancher au
plan).

## Risques / points ouverts

- L'API Google Books sans cle a un quota faible ; suffisant en dev, documente dans le
  README. Une cle pourra etre ajoutee via `GOOGLE_BOOKS_API_KEY`.
- Construction de l'image FrankenPHP + Node : premier build un peu long (acceptable).
- Vignettes Google Books servies en `http://` parfois → forcer `https://` au mapping
  pour eviter le mixed-content.
