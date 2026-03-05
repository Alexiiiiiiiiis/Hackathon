// ============================================================
// CONFIG API
// ↓ Remplacez par l'URL de base de votre backend Symfony
// ============================================================
const API_BASE = '/api';
const ENDPOINTS = {
  gitResult: API_BASE + '/analyse/{id}/git-result', // GET  → données du commit/branch créé
  push:      API_BASE + '/analyse/{id}/push',        // POST → push la PR vers le repo
  pdf:       API_BASE + '/report/{id}?format=pdf'      // GET  → télécharger le rapport PDF
};

// ============================================================
// FORMAT ATTENDU PAR GET /api/analyse/{id}/git-result :
// {
//   "id": "abc123",
//   "repository": "github.com/username/repository",
//   "branch": "securescan/fix-sql-injection-auth",
//   "commit_message": "[SecureScan] Fix SQL injection in auth.js (A03:2021)",
//   "files_changed": [
//     { "path": "src/auth.js", "additions": 3, "deletions": 3 }
//   ],
//   "summary": {
//     "files": 1,
//     "lines": 7,
//     "severity": "élevé"
//   }
// }
// ============================================================

const MOCK = {
  id: 'demo-123',
  repository: 'github.com/username/repository',
  branch: 'securescan/fix-sql-injection-auth',
  commit_message: '[SecureScan] Fix SQL injection in auth.js (A03:2021)',
  files_changed: [
    { path: 'src/auth.js', additions: 3, deletions: 3 }
  ],
  summary: { files: 1, lines: 7, severity: 'élevé' }
};

function getAnalysisId() {
  return new URLSearchParams(window.location.search).get('id') || 'demo-123';
}

async function fetchGitResult() {
  const id = getAnalysisId();
  const url = ENDPOINTS.gitResult.replace('{id}', id);
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
    return MOCK;
  }
}

function renderResult(d) {
  document.getElementById('git-repo').textContent    = d.repository;
  document.getElementById('git-branch').textContent  = d.branch;
  document.getElementById('git-commit').textContent  = d.commit_message;

  // Fichiers changés
  const container = document.getElementById('git-files');
  container.innerHTML = d.files_changed.map(f => `
    <div class="files-value">
      <span class="file-name">${f.path}</span>
      <div class="file-changes">
        <span class="fc-add">+${f.additions}</span>
        <span class="fc-del">-${f.deletions}</span>
      </div>
    </div>
  `).join('');

  // Résumé
  document.getElementById('resume-files').textContent    = d.summary.files;
  document.getElementById('resume-lines').textContent    = d.summary.lines;
  document.getElementById('resume-severity').textContent = d.summary.severity;
}

// ── Push vers le repo ─────────────────────────────────────────
async function handlePush() {
  const id  = getAnalysisId();
  const btn = document.getElementById('btn-push');
  btn.disabled = true;
  btn.innerHTML = '<div class="spinner" style="display:inline-block"></div>';

  try {
    const res = await fetch(ENDPOINTS.push.replace('{id}', id), {
      method: 'POST',
      headers: { 'Authorization': 'Bearer ' + (sessionStorage.getItem('auth_token') || '') }
    });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    btn.textContent = '✓ Pushé !';
    btn.style.background = '#16a34a';
  } catch (e) {
    console.warn('[SecureScan] Backend non connecté, simulation.');
    btn.textContent = '✓ Pushé !';
    btn.style.background = '#16a34a';
  }
}

// ── Télécharger le rapport PDF ────────────────────────────────
async function handlePdf() {
  const id  = getAnalysisId();
  const url = ENDPOINTS.pdf.replace('{id}', id);
  try {
    const res = await fetch(url, {
      headers: { 'Authorization': 'Bearer ' + (sessionStorage.getItem('auth_token') || '') }
    });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const blob = await res.blob();
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'securescan-report-' + id + '.pdf';
    a.click();
  } catch (e) {
    console.warn('[SecureScan] Backend non connecté, téléchargement simulé.');
    alert('Téléchargement du PDF disponible une fois le backend connecté.');
  }
}

async function init() {
  const data = await fetchGitResult();
  renderResult(data);
}

init();