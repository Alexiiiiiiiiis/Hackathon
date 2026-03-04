// ============================================================
// CONFIG API
// ↓ Remplacez par l'URL de base de votre backend Symfony
// ============================================================
const API_BASE = '/api';
const ENDPOINTS = {
  result:  API_BASE + '/analyse/{id}/result',        // GET  → résultat de la vulnérabilité
  fix:     API_BASE + '/analyse/{id}/fix',           // POST → appliquer la correction
  reject:  API_BASE + '/analyse/{id}/reject',        // POST → rejeter la correction
};

// ============================================================
// FORMAT ATTENDU PAR GET /api/analyse/{id}/result :
// {
//   "id": "abc123",
//   "file": "auth.js",
//   "line": 42,
//   "severity": "High",
//   "owasp": "A03:2021-Injection",
//   "vulnerable_code": [
//     { "ln": 38, "code": "function authenticateUser() {", "highlight": false },
//     { "ln": 42, "code": "  user_name = \"\" AND ...", "highlight": true }
//   ],
//   "fixed_code": [
//     { "ln": 38, "code": "function authenticateUser() {", "highlight": false },
//     { "ln": 42, "code": "  AND password = ?;", "highlight": true }
//   ],
//   "problem_title": "Problème de sécurité",
//   "problem_desc": "Faile d'injection SQL...",
//   "solution_title": "Solution",
//   "solution_desc": "L'utilisation de requêtes paramétrées...",
//   "impact": { "files": 1, "lines": 7, "risk_reduction": -15 }
// }
// ============================================================

const MOCK_RESULT = {
  id: 'demo-123',
  file: 'auth.js',
  line: 42,
  severity: 'High',
  owasp: 'A03:2021-Injection',
  vulnerable_code: [
    { ln:38, code:"function authenticateUser() {",            highlight:false },
    { ln:39, code:"  const query =",                          highlight:false },
    { ln:40, code:'  "SELECT * FROM users',                   highlight:false },
    { ln:41, code:'  WHERE username = \'" +',                 highlight:false },
    { ln:42, code:'  user_name + "\'" AND',                   highlight:true  },
    { ln:43, code:'  password = \'" +',                       highlight:true  },
    { ln:44, code:'  user_password + "\'";',                  highlight:true  },
    { ln:45, code:'  db.execute(query);',                     highlight:false },
    { ln:46, code:'}',                                        highlight:false },
  ],
  fixed_code: [
    { ln:38, code:"function authenticateUser() {",            highlight:false },
    { ln:39, code:"  const query =",                          highlight:false },
    { ln:40, code:'  "SELECT * FROM users',                   highlight:false },
    { ln:41, code:'  WHERE username = ?',                     highlight:false },
    { ln:42, code:'  AND password = ?";',                     highlight:true  },
    { ln:43, code:'  return db.execute(query, {',             highlight:true  },
    { ln:44, code:'    user_name,',                           highlight:true  },
    { ln:45, code:'    user_password',                        highlight:true  },
    { ln:46, code:'  });',                                    highlight:true  },
    { ln:47, code:'}',                                        highlight:false },
  ],
  problem_title: 'Problème de sécurité',
  problem_desc: "Faille d'injection SQL : les entrées utilisateur sont intégrées directement dans la chaîne de requête sans protection, ce qui peut permettre à un attaquant d'exécuter des instructions SQL non autorisées.",
  solution_title: 'Solution',
  solution_desc: "L'utilisation de requêtes paramétrées permet de prévenir les injections SQL car les données saisies par l'utilisateur sont automatiquement traitées et sécurisées par le moteur de base de données.",
  impact: { files: 1, lines: 7, risk_reduction: -15 }
};

// ── Utilitaires ──────────────────────────────────────────────
function getAnalysisId() {
  return new URLSearchParams(window.location.search).get('id') || 'demo-123';
}

function escapeHtml(str) {
  return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showToast(msg, color) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.style.background = color || '#1a1f2e';
  t.style.display = 'block';
  setTimeout(() => { t.style.display = 'none'; }, 3000);
}

// ── Fetch résultat ────────────────────────────────────────────
async function fetchResult() {
  const id = getAnalysisId();
  const url = ENDPOINTS.result.replace('{id}', id);
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
    return MOCK_RESULT;
  }
}

// ── Rendu ─────────────────────────────────────────────────────
function renderResult(d) {
  // Banner
  document.getElementById('vb-file').textContent     = d.file;
  document.getElementById('vb-line').textContent     = d.line;
  document.getElementById('vb-owasp').textContent    = d.owasp;

  const sevEl = document.getElementById('vb-severity');
  sevEl.textContent = d.severity;
  const sevColors = { High:'#f97316', Critical:'#ef4444', Medium:'#eab308', Low:'#22c55e' };
  sevEl.style.color = sevColors[d.severity] || '#fff';

  // Code vulnérable
  document.getElementById('code-vulnerable').innerHTML = d.vulnerable_code.map(l =>
    `<div class="code-line">
      <span class="ln">${l.ln}</span>
      <span class="lc${l.highlight ? ' highlight-red' : ''}">${escapeHtml(l.code)}</span>
    </div>`
  ).join('');

  // Code corrigé
  document.getElementById('code-fixed').innerHTML = d.fixed_code.map(l =>
    `<div class="code-line">
      <span class="ln">${l.ln}</span>
      <span class="lc${l.highlight ? ' highlight-green' : ''}">${escapeHtml(l.code)}</span>
    </div>`
  ).join('');

  // Descriptions
  document.getElementById('problem-title').textContent = d.problem_title;
  document.getElementById('problem-desc').textContent  = d.problem_desc;
  document.getElementById('solution-title').textContent = d.solution_title;
  document.getElementById('solution-desc').textContent  = d.solution_desc;

  // Impact
  document.getElementById('impact-files').textContent  = d.impact.files;
  document.getElementById('impact-lines').textContent  = d.impact.lines;
  document.getElementById('impact-risk').textContent   = d.impact.risk_reduction + ' pts';
}

// ── Appliquer la correction ───────────────────────────────────
async function handleFix() {
  const id = getAnalysisId();
  setLoading(true);
  try {
    const res = await fetch(ENDPOINTS.fix.replace('{id}', id), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + (sessionStorage.getItem('auth_token') || '')
      }
    });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    showToast('✅ Correction appliquée avec succès !', '#16a34a');
    setTimeout(() => { window.location.href = 'dashboard.html'; }, 1500);
  } catch (e) {
    console.warn('[SecureScan] Backend non connecté, simulation.');
    showToast('✅ Correction appliquée (démo)', '#16a34a');
    setTimeout(() => { window.location.href = 'dashboard.html'; }, 1500);
  } finally {
    setLoading(false);
  }
}

// ── Rejeter la correction ─────────────────────────────────────
async function handleReject() {
  const id = getAnalysisId();
  try {
    await fetch(ENDPOINTS.reject.replace('{id}', id), {
      method: 'POST',
      headers: { 'Authorization': 'Bearer ' + (sessionStorage.getItem('auth_token') || '') }
    });
  } catch (e) {
    console.warn('[SecureScan] Backend non connecté.');
  }
  showToast('Correction rejetée.', '#4a5568');
  setTimeout(() => { window.location.href = 'dashboard.html'; }, 1500);
}

function setLoading(on) {
  document.getElementById('btn-fix-text').style.display  = on ? 'none' : 'inline';
  document.getElementById('btn-fix-icon').style.display  = on ? 'none' : 'block';
  document.getElementById('btn-spinner').style.display   = on ? 'block' : 'none';
  document.getElementById('btn-fix').disabled            = on;
}

// ── Init ──────────────────────────────────────────────────────
async function init() {
  const data = await fetchResult();
  renderResult(data);
}

init();
