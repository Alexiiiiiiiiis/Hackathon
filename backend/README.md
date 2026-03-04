# ðŸ”§ SecureScan â€” Backend

API REST dÃ©veloppÃ©e avec **Symfony 7** (PHP 8.2+). Elle orchestre les outils de sÃ©curitÃ©, parse leurs rÃ©sultats, effectue le mapping OWASP et gÃ¨re l'intÃ©gration Git.

---

## ðŸ“¦ Stack technique

- **Framework** : Symfony 7
- **Langage** : PHP 8.2
- **Base de donnÃ©es** : MySQL / PostgreSQL (via Doctrine ORM)
- **Outils de sÃ©curitÃ©** : Semgrep, npm audit, TruffleHog (lancÃ©s via `symfony/process`)
- **Git API** : Octokit (ou git CLI via `child_process`)
- **CORS** : `nelmio/cors-bundle`

---

## ðŸš€ Installation

### PrÃ©requis

VÃ©rifier que ces outils sont bien installÃ©s sur la machine :

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

### Base de donnÃ©es

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

## ðŸ“ Structure du projet

```
src/
â”œâ”€â”€ Controller/
â”‚   â”œâ”€â”€ ProjectController.php     # POST /api/projects, GET /api/projects/{id}
â”‚   â”œâ”€â”€ ScanController.php        # POST /api/scan/{id}/launch
â”‚   â””â”€â”€ FixController.php         # POST /api/fix/{id}/apply
â”œâ”€â”€ Entity/
â”‚   â”œâ”€â”€ Project.php               # id, gitUrl, language, status, createdAt
â”‚   â”œâ”€â”€ Vulnerability.php         # id, file, line, description, severity, owaspCategory
â”‚   â”œâ”€â”€ ScanResult.php            # id, project, tool, rawJson, createdAt
â”‚   â””â”€â”€ Fix.php                   # id, vulnerability, suggestion, status
â””â”€â”€ Service/
    â”œâ”€â”€ GitCloneService.php       # Clone le repo via symfony/process
    â”œâ”€â”€ ScannerService.php        # Orchestre Semgrep / npm audit / TruffleHog
    â”œâ”€â”€ ParserService.php         # Parse les JSON de sortie â†’ Vulnerability
    â”œâ”€â”€ OwaspMappingService.php   # Classe chaque vulnÃ©rabilitÃ© A01-A10
    â”œâ”€â”€ FixService.php            # Templates de corrections automatisÃ©es
    â””â”€â”€ GitPushService.php        # CrÃ©e branche + applique fix + push GitHub
```

---

## Endpoints API

| Methode | Route | Description |
|---------|-------|-------------|
| `POST` | `/api/projects` | Creer un projet a scanner |
| `GET` | `/api/projects` | Lister les projets |
| `GET` | `/api/projects/{id}` | Recuperer les infos d un projet |
| `DELETE` | `/api/projects/{id}` | Supprimer un projet |
| `POST` | `/api/scan/project` | Creer un projet + lancer un scan direct |
| `POST` | `/api/scan/{id}/launch` | Lancer l analyse de securite |
| `GET` | `/api/scan/{id}` | Recuperer un scan (format principal) |
| `GET` | `/api/scan/{id}/results` | Recuperer les vulnerabilites trouvees |
| `GET` | `/api/scan/{id}/owasp` | Recuperer le mapping OWASP |
| `GET` | `/api/scan/project/{projectId}/latest` | Recuperer le dernier scan d un projet |
| `POST` | `/api/fix/generate/{vulnId}` | Generer une proposition de correction |
| `POST` | `/api/fix/{id}/apply` | Appliquer une correction |
| `POST` | `/api/fix/{id}/reject` | Rejeter une correction |
| `GET` | `/api/notifications/count` | Compter les corrections en attente |
| `GET` | `/api/report/{id}` | Generer le rapport HTML de securite |

---
## ðŸ› ï¸ Outils de sÃ©curitÃ© intÃ©grÃ©s

### Semgrep (SAST)
```bash
semgrep --config=auto /path/to/repo --json
```
DÃ©tecte : injections (A05), mauvaises pratiques de code (A06), mauvaises configurations (A02)

### npm audit (DÃ©pendances)
```bash
cd /path/to/repo && npm audit --json
```
DÃ©tecte : dÃ©pendances avec CVE connues (A03)

### TruffleHog (Secrets)
```bash
trufflehog filesystem /path/to/repo --json
```
DÃ©tecte : clÃ©s API, tokens, mots de passe dans le code (A04, A02)

---

## ðŸ—‚ï¸ CatÃ©gories OWASP couvertes

| Code | CatÃ©gorie | Outil(s) |
|------|-----------|----------|
| A02 | Security Misconfiguration | Semgrep, TruffleHog |
| A03 | Software Supply Chain Failures | npm audit |
| A04 | Cryptographic Failures | TruffleHog, Semgrep |
| A05 | Injection | Semgrep |
| A09 | Logging & Alerting Failures | Semgrep |

---

## ðŸ”— Variables d'environnement

| Variable | Description | Exemple |
|----------|-------------|---------|
| `DATABASE_URL` | URL de connexion BDD | `mysql://user:pass@localhost/securescan` |
| `GITHUB_TOKEN` | Token GitHub pour push via API | `ghp_xxxx` |
| `CORS_ALLOW_ORIGIN` | URL autorisÃ©e pour le frontend | `http://localhost:5173` |
| `REPOS_CLONE_PATH` | Dossier de clonage temporaire | `/tmp/securescan_repos` |
