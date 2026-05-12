# 📖 Marca-Page

> Votre bibliotheque personnelle : cataloguez vos livres, suivez vos lectures, gardez vos notes et citations. Sans pub, sans algorithme.

[![CI](https://github.com/krouxbidali00/marca-page/actions/workflows/ci.yml/badge.svg)](https://github.com/krouxbidali00/marca-page/actions/workflows/ci.yml)
![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)
![Symfony](https://img.shields.io/badge/Symfony-8.0-000000?logo=symfony&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-4169E1?logo=postgresql&logoColor=white)
![FrankenPHP](https://img.shields.io/badge/FrankenPHP-1-00ACD7)

Marca-Page catalogue vos livres depuis l'API Google Books, suit vos lectures (statut, progression, note), garde vos notes et citations, et range vos livres sur des etageres. Multi-utilisateurs.

## ✨ Fonctionnalites

- 🔍 **Recherche & import** depuis le catalogue Google Books (titre, auteurs, ISBN, couverture, resume...)
- 📚 **Bibliotheque** filtrable et triable : statut de lecture, statut d'achat, categorie, note, nombre de pages, etagere
- 📈 **Suivi de lecture** : a lire / en cours / termine / abandonne, page courante, progression
- 🛒 **Suivi d'achat** : a acheter / achete / prete, format, date d'achat
- ⭐ **Note** sur 5 et **notes personnelles** par livre
- ✍️ **Citations** avec numero de page
- 🗂️ **Etageres** nommees pour organiser sa collection
- 🎨 Couvertures generees automatiquement pour les livres sans vignette
- 👥 Multi-utilisateurs : chaque compte ne voit que sa propre bibliotheque

## 🧱 Stack

Symfony 8 · PHP 8.4 · [FrankenPHP](https://frankenphp.dev) · Doctrine ORM 3 / PostgreSQL 16 · AssetMapper + [`symfonycasts/sass-bundle`](https://github.com/SymfonyCasts/sass-bundle) (styles custom en SCSS, superposes a Bootstrap 5) · Stimulus + Turbo

> 💡 **Toutes les commandes** (`php`, `composer`, `bin/console`, `node`, `phpunit`) s'executent dans le conteneur applicatif `php` :
> ```bash
> docker compose exec php <commande>
> ```

## 📦 Prerequis

- Docker + Docker Compose

## 🚀 Demarrage

```bash
# 1. Construire l'image et lancer la stack (php / database / mailer)
docker compose build
docker compose up -d

# 2. Installer les dependances
docker compose exec php composer install

# 3. Appliquer les migrations
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
```

➡️ L'application tourne sur **http://localhost:8080**.

Creez un compte sur `/register`, puis cliquez sur **« Ajouter un livre »** pour rechercher dans le catalogue Google Books. 🎉

## 🔑 Cle API Google Books (optionnelle)

Les requetes anonymes vers l'API Google Books fonctionnent mais ont un quota faible. Pour utiliser une cle, ajoutez-la dans `.env.local` (jamais dans `.env`, qui est versionne) :

```dotenv
GOOGLE_BOOKS_API_KEY=votre-cle
```

## 🧪 Tests

La base de test (`app_test`) doit exister et avoir le schema :

```bash
docker compose exec php bin/console --env=test doctrine:database:create --if-not-exists
docker compose exec php bin/console --env=test doctrine:migrations:migrate --no-interaction
docker compose exec php bin/console sass:build   # genere var/sass/app.output.css, requis par les tests fonctionnels
docker compose exec php bin/phpunit
```

> Les tests fonctionnels utilisent `dama/doctrine-test-bundle` : chaque test s'execute dans une transaction annulee, donc la base de test n'est jamais polluee.

## ✅ Lint & CI

Le code PHP suit **PSR-12** (regles dans `phpcs.xml.dist`, applique a `src/` et `tests/`) :

```bash
docker compose exec php composer lint        # verifie
docker compose exec php composer lint:fix    # corrige automatiquement (phpcbf)
```

Le workflow GitHub Actions ([`.github/workflows/ci.yml`](.github/workflows/ci.yml)) execute le lint et les tests a chaque push sur `main` et sur chaque pull request.

## 🎨 Assets

En dev, AssetMapper sert les assets a la volee (le SCSS est compile par `sass-bundle`). Si `public/assets/` contient des fichiers compiles, supprimez-le pour que les modifications soient prises en compte :

```bash
docker compose exec php sh -c 'rm -rf public/assets'
```

Pour compiler les assets (utile en prod) :

```bash
docker compose exec php bin/console asset-map:compile
```

## 📐 Conventions

- 🌐 Code, identifiants et messages de commit en anglais ; documentation (`.md`) en francais, titres sans accents.
- 🚫 Aucun CSS en ligne dans les fichiers Twig : tout style non couvert par Bootstrap vit dans `assets/styles/*.scss`. Les couvertures des livres sans vignette utilisent les classes `cover--theme-0` … `cover--theme-11`.
- 🔐 Ne jamais commiter de secrets : `.env.local`, `.env.*.local`, `*.pem`, `*.key`.
