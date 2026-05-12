# Marca-Page

Bibliotheque personnelle : cataloguez vos livres depuis l'API Google Books, suivez
vos lectures (statut, progression, note), gardez vos notes et citations, rangez vos
livres sur des etageres. Multi-utilisateurs, sans pub, sans algorithme.

Stack : Symfony 8, AssetMapper + `symfonycasts/sass-bundle` (styles custom en SCSS,
superposes a Bootstrap), Stimulus + Turbo, Doctrine ORM 3 / PostgreSQL 16, FrankenPHP.

> **Toutes les commandes (php, composer, console, node, phpunit) s'executent dans le
> conteneur applicatif `php`** : `docker compose exec php <commande>`.

## Prerequis

- Docker + Docker Compose.

## Demarrage

```bash
# Construire l'image et lancer la stack (php / database / mailer)
docker compose build
docker compose up -d

# Installer les dependances et appliquer les migrations
docker compose exec php composer install
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
```

L'application est disponible sur **http://localhost:8080**.

Creez un compte sur `/register`, puis ajoutez un livre via le bouton « Ajouter un
livre » (recherche dans le catalogue Google Books).

## Cle API Google Books (optionnelle)

Les requetes anonymes vers l'API Google Books fonctionnent mais ont un quota faible.
Pour utiliser une cle, ajoutez-la dans `.env.local` (jamais dans `.env`, qui est
versionne) :

```dotenv
GOOGLE_BOOKS_API_KEY=votre-cle
```

## Tests

La base de test (`app_test`) doit exister et avoir le schema :

```bash
docker compose exec php bin/console --env=test doctrine:database:create --if-not-exists
docker compose exec php bin/console --env=test doctrine:migrations:migrate --no-interaction
docker compose exec php bin/console sass:build   # genere var/sass/app.output.css, requis par les tests fonctionnels
docker compose exec php bin/phpunit
```

(Les tests fonctionnels utilisent `dama/doctrine-test-bundle` : chaque test s'execute
dans une transaction annulee, donc la base de test n'est pas polluee.)

## Lint

Le code PHP suit PSR-12 (regles dans `phpcs.xml.dist`, applique a `src/` et `tests/`) :

```bash
docker compose exec php composer lint        # verifie
docker compose exec php composer lint:fix    # corrige automatiquement (phpcbf)
```

Le workflow GitHub Actions (`.github/workflows/ci.yml`) execute le lint et les tests a
chaque push sur `main` et sur les pull requests.

## Assets

En dev, AssetMapper sert les assets a la volee (le SCSS est compile par `sass-bundle`).
Si jamais `public/assets/` contient des fichiers compiles, supprimez-le pour que les
modifications soient prises en compte :

```bash
docker compose exec php sh -c 'rm -rf public/assets'
```

Pour compiler les assets (utile en prod) : `docker compose exec php bin/console asset-map:compile`.

## Conventions

- Code, identifiants et commits en anglais.
- Aucun CSS en ligne dans les fichiers Twig : tout style non couvert par Bootstrap vit
  dans `assets/styles/*.scss`. Les couleurs de couverture des livres sans vignette
  passent par les classes `cover--theme-0` … `cover--theme-11`.
