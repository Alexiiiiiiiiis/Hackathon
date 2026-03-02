# 🔐 SecureScan

Plateforme web d'analyse de qualité et de sécurité de code source, développée dans le cadre du Hackathon Bachelor Dev IPSSI — Semaine du 2 au 6 mars 2026.

---

## 📖 Description

SecureScan est une plateforme interne développée pour **CyberSafe Solutions**, une startup française spécialisée en cybersécurité pour les PME. Elle permet d'analyser automatiquement un repository Git, de détecter les vulnérabilités, de les mapper sur l'**OWASP Top 10 : 2025**, et de proposer des corrections automatisées avec push Git.

---

## ⚙️ Fonctionnalités principales

- Soumission d'un repository Git (URL GitHub/GitLab) ou upload ZIP
- Analyse automatisée via 3 outils de sécurité open source :
  - **Semgrep** — analyse statique du code (SAST)
  - **npm audit** — détection de dépendances vulnérables (CVE)
  - **TruffleHog** — détection de secrets et tokens exposés
- Mapping des vulnérabilités sur l'**OWASP Top 10 : 2025** (5 catégories minimum)
- Dashboard de visualisation : score global, graphiques par sévérité et par catégorie OWASP
- Système de corrections automatisées (template-based)
- Création automatique d'une branche Git et push des corrections via l'API GitHub (Octokit)
- Génération d'un rapport de sécurité HTML

---

## 🏗️ Architecture

```
securescan/
├── backend/       # API REST — Symfony (PHP)
├── frontend/      # Interface utilisateur — React (Vite)
├── .gitignore
└── README.md
```

### Flux de données

```
[React] Soumet une URL GitHub
        ↓
[Symfony] Clone le repo en local
        ↓
[Symfony] Lance Semgrep + npm audit + TruffleHog
        ↓
[Symfony] Parse les sorties JSON → entités Vulnerability
        ↓
[Symfony] Mappe chaque vulnérabilité → catégorie OWASP
        ↓
[React] Affiche le Dashboard (score + graphiques + liste)
        ↓
[React] Utilisateur valide une correction proposée
        ↓
[Symfony] Crée une branche fix/securescan → applique le fix → push GitHub
```

---

## 🚀 Lancement rapide

### Prérequis

- PHP 8.2+
- Composer
- Node.js 18+
- npm
- MySQL ou PostgreSQL
- Semgrep CLI (`pip install semgrep`)
- TruffleHog (`brew install trufflehog` ou binaire GitHub)

### Installation

```bash
# Cloner le repo
git clone https://github.com/votre-groupe/securescan.git
cd securescan

# Lancer le backend
cd backend
cp .env .env.local   # puis renseigner les variables
composer install
php bin/console doctrine:migrations:migrate
symfony serve

# Lancer le frontend (autre terminal)
cd ../frontend
npm install
cp .env.example .env   # puis renseigner VITE_API_URL
npm run dev
```

L'application est accessible sur `http://localhost:5173`
L'API backend tourne sur `http://localhost:8000`

---

## 👥 Équipe

| Membre | Rôle |
|--------|------|
| [Prénom NOM] | Backend Core (Symfony — API, entités, BDD) |
| [Prénom NOM] | Backend Sécurité (outils sécu, parsing, mapping OWASP) |
| [Prénom NOM] | Frontend (React — Dashboard, UI, graphiques) |
| [Prénom NOM] | Full Stack (Fix automatisé + intégration Git API) |

---

## 📋 Livrables

- [x] Repository Git avec historique de commits et README
- [ ] Maquettes / wireframes
- [ ] Diagrammes UML
- [ ] Application fonctionnelle
- [ ] Dashboard de visualisation OWASP
- [ ] Rapport de sécurité généré (HTML)
- [ ] Présentation PowerPoint
- [ ] Documentation technique

---

## 📄 Licence

Projet académique — IPSSI 2026