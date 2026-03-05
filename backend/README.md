# SecureScan Backend

Guide backend court et cible.
Pour la documentation complete du projet, lire le README racine:
- [`../README.md`](../README.md)

## Stack

- Symfony 7.4
- PHP >= 8.2
- Doctrine ORM/Migrations
- PostgreSQL
- JWT (Lexik)
- OAuth GitHub (KnpU + league/oauth2-github)
- Dompdf

## Installation rapide backend

```bash
cd backend
composer install --no-dev
```

Si PHP 8.3+, vous pouvez utiliser `composer install`.

## Variables minimales (`.env.local`)

```env
APP_ENV=dev
APP_SECRET=change_me
DEFAULT_URI=http://127.0.0.1:8000

DATABASE_URL="postgresql://postgres:password@127.0.0.1:5432/securescan?serverVersion=17&charset=utf8"
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'

JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=change_me
```

Option OAuth GitHub:

```env
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
FRONTEND_GITHUB_SUCCESS_URL=http://127.0.0.1:5500/frontend/templates/dashboard.html
```

## JWT + migrations

```bash
php bin/console lexik:jwt:generate-keypair --overwrite
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:schema:validate
```

## Lancer l API

```bash
php -S 127.0.0.1:8000 -t public
```

## Endpoints principaux

- `POST /api/auth/register`
- `POST /api/auth/login` (firewall Symfony)
- `GET /api/auth/me`
- `GET /api/auth/github`
- `GET /api/auth/github/callback`
- `POST /api/scan/project`
- `POST /api/upload/zip`
- `GET /api/history`
- `GET /api/reports`
- `GET /api/report/{projectId}`

## Commandes utiles

```bash
php bin/console debug:router
php bin/console doctrine:migrations:status
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

## Note importante

Ne pas lancer `php backend/bin/console ...` depuis le dossier `backend`.
Depuis `backend/`, la bonne commande est:

```bash
php bin/console ...
```
