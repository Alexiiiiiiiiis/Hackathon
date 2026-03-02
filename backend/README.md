# 🔧 SecureScan — Backend

API REST développée avec **Symfony 7** (PHP 8.2+). Elle orchestre les outils de sécurité, parse leurs résultats, effectue le mapping OWASP et gère l'intégration Git.

---

## 📦 Stack technique

- **Framework** : Symfony 7
- **Langage** : PHP 8.2
- **Base de données** : MySQL / PostgreSQL (via Doctrine ORM)
- **Outils de sécurité** : Semgrep, npm audit, TruffleHog (lancés via `symfony/process`)
- **Git API** : Octokit (ou git CLI via `child_process`)
- **CORS** : `nelmio/cors-bundle`

---

## 🚀 Installation

### Prérequis

Vérifier que ces outils sont bien installés sur la machine :

```bash
php -v          # >= 8.2
composer -v
semgrep --version
trufflehog --version
node -v         # pour npm audit
```

### Installation du projet

```bash
cd backend
composer install
cp .env .env.local
```

### Configuration `.env.local`

```env
DATABASE_URL="mysql://user:password@127.0.0.1:3306/securescan"
GITHUB_TOKEN=ghp_xxxxxxxxxxxxxxxxxxxx
CORS_ALLOW_ORIGIN=http://localhost:5173
```

### Base de données

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

### Lancer le serveur

```bash
symfony serve
# ou
php -S localhost:8000 -t public/
```

---

## 📁 Structure du projet

```
src/
├── Controller/
│   ├── ProjectController.php     # POST /api/projects, GET /api/projects/{id}
│   ├── ScanController.php        # POST /api/scan/{id}/launch
│   └── FixController.php         # POST /api/fix/{id}/apply
├── Entity/
│   ├── Project.php               # id, gitUrl, language, status, createdAt
│   ├── Vulnerability.php         # id, file, line, description, severity, owaspCategory
│   ├── ScanResult.php            # id, project, tool, rawJson, createdAt
│   └── Fix.php                   # id, vulnerability, suggestion, status
└── Service/
    ├── GitCloneService.php       # Clone le repo via symfony/process
    ├── ScannerService.php        # Orchestre Semgrep / npm audit / TruffleHog
    ├── ParserService.php         # Parse les JSON de sortie → Vulnerability
    ├── OwaspMappingService.php   # Classe chaque vulnérabilité A01-A10
    ├── FixService.php            # Templates de corrections automatisées
    └── GitPushService.php        # Crée branche + applique fix + push GitHub
```

---

## 🔌 Endpoints API

| Méthode | Route | Description |
|---------|-------|-------------|
| `POST` | `/api/projects` | Soumettre un projet (URL Git ou ZIP) |
| `GET` | `/api/projects/{id}` | Récupérer les infos d'un projet |
| `POST` | `/api/scan/{id}/launch` | Lancer l'analyse de sécurité |
| `GET` | `/api/scan/{id}/results` | Récupérer les vulnérabilités trouvées |
| `GET` | `/api/scan/{id}/owasp` | Récupérer le mapping OWASP |
| `POST` | `/api/fix/{vulnId}/apply` | Valider et appliquer une correction |
| `POST` | `/api/fix/{vulnId}/reject` | Rejeter une correction proposée |
| `GET` | `/api/report/{id}` | Générer le rapport HTML de sécurité |

---

## 🛠️ Outils de sécurité intégrés

### Semgrep (SAST)
```bash
semgrep --config=auto /path/to/repo --json
```
Détecte : injections (A05), mauvaises pratiques de code (A06), mauvaises configurations (A02)

### npm audit (Dépendances)
```bash
cd /path/to/repo && npm audit --json
```
Détecte : dépendances avec CVE connues (A03)

### TruffleHog (Secrets)
```bash
trufflehog filesystem /path/to/repo --json
```
Détecte : clés API, tokens, mots de passe dans le code (A04, A02)

---

## 🗂️ Catégories OWASP couvertes

| Code | Catégorie | Outil(s) |
|------|-----------|----------|
| A02 | Security Misconfiguration | Semgrep, TruffleHog |
| A03 | Software Supply Chain Failures | npm audit |
| A04 | Cryptographic Failures | TruffleHog, Semgrep |
| A05 | Injection | Semgrep |
| A09 | Logging & Alerting Failures | Semgrep |

---

## 🔗 Variables d'environnement

| Variable | Description | Exemple |
|----------|-------------|---------|
| `DATABASE_URL` | URL de connexion BDD | `mysql://user:pass@localhost/securescan` |
| `GITHUB_TOKEN` | Token GitHub pour push via API | `ghp_xxxx` |
| `CORS_ALLOW_ORIGIN` | URL autorisée pour le frontend | `http://localhost:5173` |
| `REPOS_CLONE_PATH` | Dossier de clonage temporaire | `/tmp/securescan_repos` |