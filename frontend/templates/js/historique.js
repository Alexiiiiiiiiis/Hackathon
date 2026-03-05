// ============================================================
// CONFIG API
// ↓ Remplacez par l'URL de base de votre backend Symfony
// ============================================================
const API_BASE = '/api';
const ENDPOINTS = {
  history: API_BASE + '/history',   // GET → liste des analyses
                                    // Params: ?page=1&search=&date_from=&date_to=&status=
  stats:   API_BASE + '/dashboard', // GET → stats globales (réutilise le dashboard)
};

// ============================================================
// FORMAT ATTENDU PAR GET /api/history :
// {
//   "total": 24,
//   "page": 1,
//   "per_page": 4,
//   "analyses": [
//     {
//       "id": "abc123",
//       "project_id": 1,
//       "repository": "username/project-alpha",
//       "date": "01-03-2026",
//       "score": 72,
//       "failles": 23,
//       "status": "Complété"
//     }
//   ]
// }
// ============================================================

const MOCK = {
  total: 24,
  page: 1,
  per_page: 4,
  analyses: [
    { id:'v1', project_id:1, repository:'username/project-alpha',  date:'01-03-2026', score:72, failles:23, status:'Complété' },
    { id:'v2', project_id:2, repository:'username/webapp-beta',    date:'21-02-2026', score:85, failles:12, status:'Complété' },
    { id:'v3', project_id:3, repository:'username/api-service',    date:'12-02-2026', score:68, failles:31, status:'Complété' },
    { id:'v4', project_id:4, repository:'username/mobile-app',     date:'03-02-2026', score:91, failles:5,  status:'Complété' },
  ]
};

const MOCK_STATS = {
  total_analyses: 24,
  this_month: 1,
  average_score: 73,
  total_failles: 140,
};

// ── État ─────────────────────────────────────────────────────
let currentPage  = 1;
let totalPages   = 1;
let searchVal    = '';
let dateVal      = '';
let statusVal    = '';

function bindLogout() {
  document.querySelectorAll('.sb-out').forEach(el => {
    el.addEventListener('click', e => {
      e.preventDefault();
      if (typeof Auth !== 'undefined' && typeof Auth.logout === 'function') {
        Auth.logout();
        return;
      }
      window.location.href = 'login.html';
    });
  });
}

// ── Fetch ─────────────────────────────────────────────────────
async function fetchHistory(page = 1) {
  const params = new URLSearchParams({
    page,
    search:    searchVal,
    date_from: dateVal,
    status:    statusVal,
  });
  try {
    const res = await fetch(`${ENDPOINTS.history}?${params}`, {
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer ' + (sessionStorage.getItem('auth_token') || '')
      }
    });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    return await res.json();
  } catch (e) {
    console.warn('[SecureScan] API indisponible, données de démo.', e.message);
    // Filtre côté client sur le mock
    let filtered = MOCK.analyses.filter(a =>
      a.repository.toLowerCase().includes(searchVal.toLowerCase()) &&
      (statusVal === '' || a.status.toLowerCase().includes(statusVal.toLowerCase()))
    );
    const perPage = MOCK.per_page;
    const start   = (page - 1) * perPage;
    return {
      total:    filtered.length,
      page,
      per_page: perPage,
      analyses: filtered.slice(start, start + perPage),
    };
  }
}

async function fetchStats() {
  try {
    const res = await fetch(ENDPOINTS.stats, {
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer ' + (sessionStorage.getItem('auth_token') || '')
      }
    });
    if (!res.ok) throw new Error();
    return await res.json();
  } catch (e) {
    return MOCK_STATS;
  }
}

// ── Rendu stats ───────────────────────────────────────────────
function renderStats(d) {
  document.getElementById('stat-total').textContent    = d.total_analyses ?? d.score ?? 24;
  document.getElementById('stat-month').textContent    = d.this_month ?? 1;
  document.getElementById('stat-score').innerHTML      = (d.average_score ?? d.score ?? 73) + '<span>/100</span>';
  document.getElementById('stat-failles').textContent  = d.total_failles ?? (d.critical + d.high + d.medium + d.low) ?? 140;
}

// ── Rendu table ───────────────────────────────────────────────
function scoreClass(score) {
  if (score >= 80) return 'score-good';
  if (score >= 60) return 'score-ok';
  return 'score-bad';
}

function renderTable(data) {
  const tbody = document.getElementById('history-tbody');
  if (!data.analyses.length) {
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px;color:var(--tl)">Aucun résultat</td></tr>';
    return;
  }
  tbody.innerHTML = data.analyses.map(a => `
    <tr>
      <td>
        <div class="repo-name">${a.repository}</div>
        <div class="repo-status">${a.status}</div>
      </td>
      <td>
        <div class="date-cell">
          <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          ${a.date}
        </div>
      </td>
      <td><span class="score-badge ${scoreClass(a.score)}">${a.score}/100</span></td>
      <td><span class="failles">${a.failles} problèmes</span></td>
      <td>
        <div class="actions-cell">
          <a class="btn-voir" href="dashboard.html?id=${a.id}">Voir</a>
          <a class="btn-rapport" href="/api/report/${a.project_id}?format=pdf" target="_blank">Rapport</a>
        </div>
      </td>
    </tr>
  `).join('');

  // Pagination
  totalPages = Math.ceil(data.total / data.per_page) || 1;
  document.getElementById('pag-info').textContent =
    `Voir ${Math.min((currentPage - 1) * data.per_page + 1, data.total)}–${Math.min(currentPage * data.per_page, data.total)} sur ${data.total} analyses`;

  renderPagination();
}

// ── Pagination ────────────────────────────────────────────────
function renderPagination() {
  const container = document.getElementById('pag-pages');
  let html = '';
  for (let i = 1; i <= Math.min(totalPages, 5); i++) {
    html += `<button class="pag-btn ${i === currentPage ? 'active' : ''}" onclick="goToPage(${i})">${i}</button>`;
  }
  container.innerHTML = html;
  document.getElementById('pag-prev').disabled = currentPage === 1;
  document.getElementById('pag-next').disabled = currentPage === totalPages;
}

async function goToPage(page) {
  currentPage = page;
  const data = await fetchHistory(page);
  renderTable(data);
}

// ── Filtres ───────────────────────────────────────────────────
let debounceTimer;
function handleSearch(val) {
  searchVal = val;
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(async () => {
    currentPage = 1;
    const data = await fetchHistory(1);
    renderTable(data);
  }, 300);
}

async function handleFilter() {
  dateVal   = document.getElementById('filter-date').value;
  statusVal = document.getElementById('filter-status').value;
  currentPage = 1;
  const data = await fetchHistory(1);
  renderTable(data);
}

// ── Init ──────────────────────────────────────────────────────
async function init() {
  if (typeof requireAuth === 'function' && !requireAuth()) return;
  bindLogout();
  if (typeof loadUserInfo === 'function') {
    await loadUserInfo();
  }
  const [stats, history] = await Promise.all([fetchStats(), fetchHistory(1)]);
  renderStats(stats);
  renderTable(history);
}

init();
