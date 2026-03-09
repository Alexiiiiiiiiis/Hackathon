<<<<<<< HEAD
# SecureScan

Plateforme web d analyse de securite de code source.

Ce README est la reference principale du projet et regroupe:
- backend Symfony (API, auth JWT, OAuth GitHub, scan, rapports)
- frontend templates HTML/CSS/JS (pages utilisees en equipe)
- frontend React/Vite (espace present mais non prioritaire dans le flux actuel)

## 1. Description du projet

SecureScan permet de:
- soumettre un depot Git ou un ZIP
- lancer des analyses de securite automatiques
- centraliser les vulnerabilites (severite + categorie OWASP)
- visualiser les resultats (dashboard, historique, rapports)
- proposer et appliquer/rejeter des corrections
- generer un rapport HTML/PDF

## 2. Stack technique

### Backend
- Symfony 7.4
- PHP >= 8.2 (PHP 8.3 recommande)
- Doctrine ORM + Doctrine Migrations
- PostgreSQL (config actuelle)
- JWT: `lexik/jwt-authentication-bundle`
- OAuth GitHub: `knpuniversity/oauth2-client-bundle` + `league/oauth2-github`
- CORS: `nelmio/cors-bundle`
- PDF: `dompdf/dompdf`

### Frontend
- Pages statiques dans `frontend/templates` (HTML/CSS/JS)
- API JS centralisee dans `frontend/templates/js/api.js`
- Workspace React/Vite present dans `frontend/src` (optionnel)

### Outils de scan (CLI)
- Semgrep
- npm audit (via Node.js/npm)
- TruffleHog
- ESLint (+ plugin security) pour les regles JS de securite

## 3. Architecture du repo

```text
## 3. Architecture du repo

```text
SECURE-SCAN-GROUPE-2/
├── backend/                 # API REST - Symfony (PHP)
│   ├── bin/                 # Exécutables Symfony
│   ├── config/              # Configuration (Routes, Sécurité, Doctrine)
│   ├── migrations/          # Fichiers de migration de la base de données (Supabase)
│   ├── public/              # Point d'entrée web Backend
│   ├── src/                 # Code métier (Controllers, Services d'analyse, Entités)
│   └── tests/               # Tests unitaires et fonctionnels
├── frontend/                # Interface Client
│   ├── public/              # Assets statiques
│   ├── src/                 # Base React/Vite (Expérimental / Composants)
│   └── templates/           # Vues HTML natives (Interface principale)
│       ├── css/             # Feuilles de style
│       ├── js/              # Scripts Vanilla JS (Intégration API & Logique)
│       └── *.html           # Fichiers vues (dashboard, login, rapports...)
├── livrables/               # Documents de rendu pour le jury
│
└── README.md                # Point d'entrée et documentation globale
```

## 4. Prerequis

- Git
- PHP 8.2+ (`php -v`)
- Composer (`composer -V`)
- PostgreSQL 16/17
- Node.js 18+ (`node -v`)
- npm (`npm -v`)
- Semgrep (`semgrep --version`)
- TruffleHog (`trufflehog --version`)
- ESLint (`eslint -v`) et plugin security

### Installation rapide des scanners (suggestion)

```bash
# Semgrep
pip install semgrep

# TruffleHog
# Voir binaire officiel: https://github.com/trufflesecurity/trufflehog

# ESLint securite (global)
npm install -g eslint eslint-plugin-security
```

Notes:
- si un scanner n est pas installe, il est ignore et le scan continue avec les autres.
- `npm audit` utilise npm, donc Node.js/npm doivent etre presents.

## 5. Installation et lancement (dev)

### 5.1 Cloner le repo

```bash
git clone <url-du-repo>
cd secure-scan-groupe-2
```

### 5.2 Backend: installer les dependances

```bash
cd backend
```

Si vous etes en PHP 8.3+:

```bash
composer install
```

Si vous etes en PHP 8.2 (cas courant sur ce projet):

```bash
composer install --no-dev
```

Note: les dependances de test (PHPUnit 12) demandent PHP >= 8.3.

### 5.3 Backend: configurer l environnement

Creer/editer `backend/.env.local` avec vos valeurs locales. Exemple minimal:

```env
APP_ENV=dev
APP_SECRET=change_me
DEFAULT_URI=http://127.0.0.1:8000

DATABASE_URL="postgresql://postgres:password@127.0.0.1:5432/securescan?serverVersion=17&charset=utf8"

CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'

JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=change_me

# OAuth GitHub (optionnel mais recommande si login GitHub)
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
FRONTEND_GITHUB_SUCCESS_URL=http://127.0.0.1:5500/frontend/templates/dashboard.html
```

### 5.4 Backend: generer les cles JWT

```bash
php bin/console lexik:jwt:generate-keypair --overwrite
```

### 5.5 Backend: base de donnees et migrations

```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:schema:validate
```

### 5.6 Backend: lancer l API

Depuis `backend/`:

```bash
php -S 127.0.0.1:8000 -t public
```

API disponible sur `http://127.0.0.1:8000`.

### 5.7 Frontend templates: lancer un serveur statique

Depuis la racine du repo:

```bash
php -S 127.0.0.1:5500 -t .
```

Ouvrir:
- `http://127.0.0.1:5500/frontend/templates/login.html`
- `http://127.0.0.1:5500/frontend/templates/register.html`

Important:
- ne pas ouvrir les pages en `file:///...`
- la logique OAuth GitHub et certains boutons necessitent une page servie en HTTP

## 6. Configuration OAuth GitHub (optionnel)

Dans GitHub > Settings > Developer settings > OAuth Apps:
- Authorization callback URL:
  `http://127.0.0.1:8000/api/auth/github/callback`

Puis renseigner dans `backend/.env.local`:
- `GITHUB_CLIENT_ID`
- `GITHUB_CLIENT_SECRET`
- `FRONTEND_GITHUB_SUCCESS_URL=http://127.0.0.1:5500/frontend/templates/dashboard.html`

Test rapide:

```bash
curl -i http://127.0.0.1:8000/api/auth/github
```

Attendu: HTTP 302 vers `github.com/login/oauth/authorize`.

## 7. Endpoints API principaux

Auth:
- `POST /api/auth/register`
- `POST /api/auth/login` (gere par `security.yaml`)
- `GET /api/auth/me`
- `GET /api/auth/github`
- `GET /api/auth/github/callback`

Projets/scan:
- `POST /api/projects`
- `GET /api/projects`
- `GET /api/projects/{id}`
- `DELETE /api/projects/{id}`
- `POST /api/scan/project`
- `POST /api/scan/{id}/launch`
- `GET /api/scan/{id}`
- `GET /api/scan/{id}/owasp`
- `GET /api/scan/project/{projectId}/latest`
- `POST /api/upload/zip`

Fix/notifications:
- `POST /api/fix/generate/{vulnId}`
- `POST /api/fix/{id}/apply`
- `POST /api/fix/{id}/reject`
- `GET /api/notifications/count`

Historique/rapports:
- `GET /api/history`
- `GET /api/history/stats`
- `GET /api/reports`
- `GET /api/report/{projectId}`
  - supporte `scan_id`, `format=html|pdf`, `download=1`
  - supporte `sections=executive_summary,technical_details,...`

## 8. Commandes utiles

Depuis `backend/`:

```bash
php bin/console debug:router
php bin/console doctrine:migrations:status
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
php bin/console doctrine:schema:validate
php bin/console doctrine:schema:update --dump-sql
```

## 9. Troubleshooting

- Erreur `Could not open input file: backend/bin/console`:
  - vous etes deja dans `backend/`; utilisez `php bin/console ...`

- `doctrine:schema:validate` indique `schema is not in sync`:
  - creer une migration (`doctrine:migrations:diff`) puis migrer

- `composer install` echoue avec phpunit et PHP 8.2:
  - utilisez `composer install --no-dev` ou passez en PHP 8.3

- OAuth GitHub `redirect_uri is not associated`:
  - verifier exactement l URL callback GitHub

- Erreur JWT `verify your configuration (private key/passphrase)`:
  - regenerer les cles JWT et verifier `JWT_PASSPHRASE`

- Bouton "Voir" ouvre `about:blank`:
  - verifier backend actif sur `127.0.0.1:8000`
  - verifier popup non bloquee
  - verifier token JWT present

## 10. Notes de securite et bonnes pratiques

- Ne pas commiter de secrets (`.env.local`, tokens, cles privees).
- Utiliser des valeurs locales dans `.env.local`.
- Eviter de partager les URLs contenant des tokens JWT.
- En equipe, synchroniser les versions de PHP et des outils CLI.

## 11. README secondaires

- `backend/README.md`: guide backend cible
- `frontend/README.md`: guide frontend cible

Le present fichier reste la reference globale du projet.
=======
# Hackathon
>>>>>>> 4a5bce0ee55edcafdb7984b90f81b2ae832a1fc8
