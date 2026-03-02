# 🎨 SecureScan — Frontend

Interface utilisateur développée avec **React + Vite**. Elle permet de soumettre un projet, visualiser les résultats d'analyse de sécurité et valider les corrections automatisées.

---

## 📦 Stack technique

- **Framework** : React 18 (Vite)
- **Routage** : React Router v6
- **Graphiques** : Recharts
- **Requêtes HTTP** : Axios
- **Style** : Tailwind CSS

---

## 🚀 Installation

### Prérequis

```bash
node -v   # >= 18
npm -v
```

### Installation du projet

```bash
cd frontend
npm install
cp .env.example .env
```

### Configuration `.env`

```env
VITE_API_URL=http://localhost:8000
```

### Lancer le serveur de développement

```bash
npm run dev
```

L'application est accessible sur `http://localhost:5173`

### Build de production

```bash
npm run build
```

---

## 📁 Structure du projet

```
src/
├── pages/
│   ├── Home.jsx           # Formulaire de soumission (URL Git ou ZIP)
│   ├── Dashboard.jsx      # Score global + graphiques + résumé OWASP
│   ├── VulnList.jsx       # Liste détaillée des vulnérabilités avec filtres
│   └── FixReview.jsx      # Interface de validation/rejet des corrections
├── components/
│   ├── ScoreCard.jsx      # Score de sécurité global (A/B/C/D/F)
│   ├── OwaspChart.jsx     # Graphique de distribution OWASP (Recharts)
│   ├── SeverityChart.jsx  # Graphique critique / haute / moyenne / basse
│   ├── VulnCard.jsx       # Carte d'une vulnérabilité (fichier, ligne, desc.)
│   └── FixDiff.jsx        # Affichage avant/après d'une correction
├── services/
│   └── api.js             # Toutes les appels Axios vers le backend
├── App.jsx
└── main.jsx
```

---

## 🗺️ Pages de l'application

### `/` — Home
Formulaire de soumission d'un projet. L'utilisateur entre une URL GitHub/GitLab ou upload une archive ZIP. Au submit, une requête `POST /api/projects` est envoyée au backend.

### `/dashboard/:id` — Dashboard
Page principale après analyse. Affiche :
- Le score de sécurité global (A → F)
- Le graphique de répartition par sévérité (critique / haute / moyenne / basse)
- Le graphique de distribution par catégorie OWASP Top 10
- Un bouton pour accéder à la liste complète des vulnérabilités

### `/vulnerabilities/:id` — Liste des vulnérabilités
Liste complète des findings avec : fichier, ligne, description, sévérité, catégorie OWASP. Filtres disponibles par sévérité, outil source, catégorie OWASP.

### `/fix/:id` — Revue des corrections
Pour chaque vulnérabilité avec une correction disponible, l'utilisateur voit le code original vs le code corrigé (diff). Il peut valider ✅ ou rejeter ❌ chaque correction avant le push Git.

---

## 🔌 Appels API (services/api.js)

```javascript
// Soumettre un projet
POST /api/projects        { gitUrl: "https://github.com/..." }

// Lancer l'analyse
POST /api/scan/{id}/launch

// Récupérer les résultats
GET /api/scan/{id}/results
GET /api/scan/{id}/owasp

// Valider/rejeter une correction
POST /api/fix/{vulnId}/apply
POST /api/fix/{vulnId}/reject

// Générer le rapport
GET /api/report/{id}
```

---

## 📊 Composants graphiques (Recharts)

```jsx
// Graphique sévérité — BarChart
import { BarChart, Bar, XAxis, YAxis, Tooltip } from 'recharts';

// Graphique OWASP — RadarChart ou BarChart horizontal
import { RadarChart, Radar, PolarGrid } from 'recharts';
```

---

## 🔗 Variables d'environnement

| Variable | Description | Valeur par défaut |
|----------|-------------|-------------------|
| `VITE_API_URL` | URL de l'API Symfony | `http://localhost:8000` |