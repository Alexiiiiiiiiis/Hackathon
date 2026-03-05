# SecureScan Frontend

Guide frontend court et cible.
Pour la documentation complete du projet, lire le README racine:
- [`../README.md`](../README.md)

## Structure frontend

- `templates/`: pages HTML/CSS/JS utilisees en equipe
- `templates/js/api.js`: client API central
- `src/`: workspace React/Vite (present, mais flux actuel base sur `templates`)

## Lancer le frontend templates

Depuis la racine du repo:

```bash
php -S 127.0.0.1:5500 -t .
```

Puis ouvrir:
- `http://127.0.0.1:5500/frontend/templates/login.html`
- `http://127.0.0.1:5500/frontend/templates/register.html`

Important:
- ne pas ouvrir `file:///...`
- OAuth GitHub et certaines redirections exigent HTTP

## URL API

Par defaut, `templates/js/api.js` calcule automatiquement:
- `http://<host>:8000`

Pour surcharger explicitement:

```html
<script>
  window.__API_BASE__ = "http://127.0.0.1:8000";
</script>
```

## Auth front

- JWT stocke dans `localStorage` (`ss_token`)
- utilisateur stocke dans `localStorage` (`ss_user`)
- `requireAuth()` redirige vers `login.html` si token absent

## Workspace React/Vite (optionnel)

Si vous travaillez sur `frontend/src`:

```bash
cd frontend
npm install
npm run dev
```

Configurer `frontend/.env`:

```env
VITE_API_URL=http://127.0.0.1:8000
```
