// ============================================================
// CONFIG API
// ↓ Remplacez par l'URL de base de votre backend Symfony
// ============================================================
const API_BASE = '/api';
const ENDPOINTS = {
  status:  API_BASE + '/analyse/{id}/status', // GET → statut de l'analyse en cours
  cancel:  API_BASE + '/analyse/{id}/cancel', // POST → annuler l'analyse
  result:  'dashboard.html',                  // Redirection vers le dashboard après analyse
};

// ============================================================
// FORMAT ATTENDU PAR GET /api/analyse/{id}/status :
// {
//   "id": "abc123",
//   "progress": 65,                     // 0-100
//   "status": "running",                // "running" | "completed" | "failed"
//   "repo_url": "github.com/user/repo",
//   "time_remaining": "~3 min restants",
//   "current_step": "Scanning fichiers et les dépendances...",
//   "completed_steps": 3,
//   "total_steps": 4,
//   "tools": [
//     {
//       "id": "sast",
//       "name": "SAST",
//       "description": "Analyse statique de sécurité du code",
//       "status": "completed",           // "completed" | "running" | "waiting"
//       "detail": "Complété dans 40s"
//     },
//     ...
//   ]
// }
// ============================================================

// ── Données de démo ─────────────────────────────────────────
const MOCK_STATUS = {
  id: 'demo-123',
  progress: 65,
  status: 'running',
  repo_url: 'github.com/username/repository',
  time_remaining: '~3 min restants',
  current_step: 'Scanning fichiers et les dépendances...',
  completed_steps: 3,
  total_steps: 4,
  tools: [
    { id:'sast',     name:'SAST',                  description:'Analyse statique de sécurité du code', status:'completed', detail:'Complété dans 40s' },
    { id:'deps',     name:'Analyses des dépendances', description:'détection des vulnérabilité tierces', status:'completed', detail:'Complété dans 32s' },
    { id:'secrets',  name:'Détection de code secret', description:'Clés API et identifiants\nProcessing files...', status:'running',   detail:'' },
    { id:'quality',  name:'Qualité du code',         description:'Complexité et maintenabilité\nen attente...', status:'waiting',   detail:'' },
  ]
};

// ── Récupère l'ID de l'analyse depuis l'URL ─────────────────
function getAnalysisId() {
  const params = new URLSearchParams(window.location.search);
  return params.get('id') || 'demo-123';
}

// ── Fetch statut ─────────────────────────────────────────────
async function fetchStatus() {
  const id = getAnalysisId();
  const url = ENDPOINTS.status.replace('{id}', id);
  try {
    const res = await fetch(url, {
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer ' + (sessionStorage.getItem('auth_token') || '')
      }
    });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    return await res.json();
  } catch (e) {
    console.warn('[SecureScan] API indisponible, données de démo.', e.message);
    return MOCK_STATUS;
  }
}

// ── Rendu ────────────────────────────────────────────────────
function renderStatus(data) {
  // Repo
  document.getElementById('repo-url').textContent      = data.repo_url || '—';
  document.getElementById('repo-time').textContent     = data.time_remaining || '';

  // Progression
  document.getElementById('prog-pct').textContent      = data.progress + '%';
  document.getElementById('prog-fill').style.width     = data.progress + '%';
  document.getElementById('prog-step').textContent     = data.current_step || '';
  document.getElementById('prog-steps').textContent    = data.completed_steps + ' of ' + data.total_steps + ' outils complété';

  // Outils
  renderTools(data.tools || []);

  // Bouton résultat
  const btnResult = document.getElementById('btn-result');
  if (data.status === 'completed') {
    btnResult.classList.add('ready');
    document.getElementById('prog-step').textContent = 'Analyse terminée !';
    clearInterval(pollingInterval);
  } else if (data.status === 'failed') {
    document.getElementById('prog-step').textContent = 'Analyse échouée.';
    clearInterval(pollingInterval);
  }
}

function renderTools(tools) {
  const container = document.getElementById('tools-list');
  container.innerHTML = tools.map(tool => {
    let iconClass, badgeClass, badgeText, iconSvg, progBar = '';

    if (tool.status === 'completed') {
      iconClass  = 'ti-done';
      badgeClass = 'tb-done';
      badgeText  = 'Complété';
      iconSvg    = '<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>';
    } else if (tool.status === 'running') {
      iconClass  = 'ti-running';
      badgeClass = 'tb-running';
      badgeText  = 'En cours...';
      iconSvg    = '<svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>';
      progBar    = '<div class="tool-prog"><div class="tool-prog-fill"></div></div>';
    } else {
      iconClass  = 'ti-waiting';
      badgeClass = 'tb-waiting';
      badgeText  = 'En attente';
      iconSvg    = '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
    }

    const itemClass = tool.status === 'running' ? 'tool-item running' : tool.status === 'waiting' ? 'tool-item waiting' : 'tool-item';

    return `
      <div class="${itemClass}">
        <div class="tool-icon ${iconClass}">${iconSvg}</div>
        <div class="tool-info">
          <div class="tool-name">${tool.name}</div>
          <div class="tool-desc">${tool.description.split('\n')[0]}</div>
          ${tool.detail ? '<div class="tool-sub">' + tool.detail + '</div>' : ''}
          ${tool.status === 'running' ? '<div class="tool-sub">' + (tool.description.split('\n')[1] || '') + '</div>' : ''}
          ${progBar}
        </div>
        <div class="tool-badge ${badgeClass}">${badgeText}</div>
      </div>`;
  }).join('');
}

// ── Annuler l'analyse ────────────────────────────────────────
async function handleCancel() {
  if (!confirm('Voulez-vous vraiment annuler l\'analyse ?')) return;
  const id = getAnalysisId();
  const url = ENDPOINTS.cancel.replace('{id}', id);
  try {
    await fetch(url, {
      method: 'POST',
      headers: { 'Authorization': 'Bearer ' + (sessionStorage.getItem('auth_token') || '') }
    });
  } catch (e) {
    console.warn('[SecureScan] Annulation non envoyée au backend.');
  }
  window.location.href = 'nouvelle_analyse.html';
}

// ── Voir le résultat ─────────────────────────────────────────
function handleResult() {
  window.location.href = ENDPOINTS.result;
}

// ── Polling toutes les 3 secondes ────────────────────────────
let pollingInterval;

async function init() {
  const data = await fetchStatus();
  renderStatus(data);

  // Si déjà terminé, pas de polling
  if (data.status === 'completed' || data.status === 'failed') return;

  pollingInterval = setInterval(async () => {
    const updated = await fetchStatus();
    renderStatus(updated);
  }, 3000);
}

init();
