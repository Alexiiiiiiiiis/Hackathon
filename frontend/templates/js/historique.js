// ============================================================
// CONFIG API
// ============================================================
const API_BASE = '/api';
const ENDPOINTS = {
  history: API_BASE + '/history',
  stats: API_BASE + '/dashboard',
};

// Etat
let currentPage = 1;
let totalPages = 1;
let searchVal = '';
let dateVal = '';
let statusVal = '';
let debounceTimer;

function bindLogout() {
  document.querySelectorAll('.sb-out').forEach((el) => {
    el.addEventListener('click', (e) => {
      e.preventDefault();
      if (typeof Auth !== 'undefined' && typeof Auth.logout === 'function') {
        Auth.logout();
        return;
      }
      window.location.href = 'login.html';
    });
  });
}

function getAuthorizationHeader() {
  const token = (typeof getToken === 'function')
    ? getToken()
    : localStorage.getItem('ss_token');

  return token ? { Authorization: 'Bearer ' + token } : {};
}

async function fetchHistory(page = 1) {
  const params = new URLSearchParams({
    page,
    search: searchVal,
    date_from: dateVal,
    status: statusVal,
  });

  const path = `${ENDPOINTS.history}?${params.toString()}`;

  if (typeof apiRequest === 'function') {
    return apiRequest('GET', path);
  }

  const res = await fetch(path, {
    headers: {
      Accept: 'application/json',
      ...getAuthorizationHeader(),
    },
  });

  if (!res.ok) {
    throw new Error('HTTP ' + res.status);
  }

  return res.json();
}

async function fetchStats() {
  if (typeof apiRequest === 'function') {
    return apiRequest('GET', ENDPOINTS.stats);
  }

  const res = await fetch(ENDPOINTS.stats, {
    headers: {
      Accept: 'application/json',
      ...getAuthorizationHeader(),
    },
  });

  if (!res.ok) {
    throw new Error('HTTP ' + res.status);
  }

  return res.json();
}

function renderStats(data) {
  const total = data?.total_analyses ?? data?.score ?? '—';
  const month = data?.this_month ?? '—';
  const score = data?.average_score ?? data?.score ?? '—';

  const hasBreakdown = ['critical', 'high', 'medium', 'low']
    .every((k) => typeof data?.[k] === 'number');
  const breakdown = hasBreakdown
    ? data.critical + data.high + data.medium + data.low
    : null;
  const failles = data?.total_failles ?? breakdown ?? '—';

  document.getElementById('stat-total').textContent = total;
  document.getElementById('stat-month').textContent = month;
  document.getElementById('stat-score').innerHTML = `${score}<span>/100</span>`;
  document.getElementById('stat-failles').textContent = failles;
}

function renderStatsUnavailable() {
  document.getElementById('stat-total').textContent = '—';
  document.getElementById('stat-month').textContent = '—';
  document.getElementById('stat-score').innerHTML = '—<span>/100</span>';
  document.getElementById('stat-failles').textContent = '—';
}

function scoreClass(score) {
  if (score >= 80) return 'score-good';
  if (score >= 60) return 'score-ok';
  return 'score-bad';
}

function renderTableError(message) {
  const tbody = document.getElementById('history-tbody');
  tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:20px;color:#ef4444;">${message}</td></tr>`;

  document.getElementById('pag-info').textContent = 'Erreur de chargement';
  document.getElementById('pag-pages').innerHTML = '';
  document.getElementById('pag-prev').disabled = true;
  document.getElementById('pag-next').disabled = true;
}

function toUserMessage(error, fallback) {
  const detail = error && error.message ? String(error.message).trim() : '';
  return detail ? `${fallback} (${detail})` : fallback;
}

function renderTable(data) {
  const tbody = document.getElementById('history-tbody');
  const analyses = Array.isArray(data?.analyses) ? data.analyses : [];

  if (!analyses.length) {
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px;color:var(--tl)">Aucun resultat</td></tr>';
    document.getElementById('pag-info').textContent = '0 resultat';
    document.getElementById('pag-pages').innerHTML = '';
    document.getElementById('pag-prev').disabled = true;
    document.getElementById('pag-next').disabled = true;
    return;
  }

  tbody.innerHTML = analyses.map((a) => `
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
      <td><span class="failles">${a.failles} problemes</span></td>
      <td>
        <div class="actions-cell">
          <a class="btn-voir" href="dashboard.html?id=${a.id}">Voir</a>
          <a class="btn-rapport" href="/api/report/${a.project_id}?format=pdf" target="_blank">Rapport</a>
        </div>
      </td>
    </tr>
  `).join('');

  const total = Number(data?.total ?? analyses.length);
  const perPage = Number(data?.per_page ?? analyses.length || 1);
  totalPages = Math.max(1, Math.ceil(total / perPage));

  const from = Math.min((currentPage - 1) * perPage + 1, total);
  const to = Math.min(currentPage * perPage, total);
  document.getElementById('pag-info').textContent = `Voir ${from}-${to} sur ${total} analyses`;

  renderPagination();
}

function renderPagination() {
  const container = document.getElementById('pag-pages');
  let html = '';

  for (let i = 1; i <= Math.min(totalPages, 5); i += 1) {
    html += `<button class="pag-btn ${i === currentPage ? 'active' : ''}" onclick="goToPage(${i})">${i}</button>`;
  }

  container.innerHTML = html;
  document.getElementById('pag-prev').disabled = currentPage === 1;
  document.getElementById('pag-next').disabled = currentPage === totalPages;
}

async function goToPage(page) {
  if (page < 1 || page > totalPages) return;
  currentPage = page;

  try {
    const data = await fetchHistory(page);
    renderTable(data);
  } catch (e) {
    renderTableError(toUserMessage(e, "Impossible de charger l'historique."));
  }
}

function handleSearch(val) {
  searchVal = val;
  clearTimeout(debounceTimer);

  debounceTimer = setTimeout(async () => {
    currentPage = 1;
    try {
      const data = await fetchHistory(1);
      renderTable(data);
    } catch (e) {
      renderTableError(toUserMessage(e, "Impossible de charger l'historique."));
    }
  }, 300);
}

async function handleFilter() {
  dateVal = document.getElementById('filter-date').value;
  statusVal = document.getElementById('filter-status').value;
  currentPage = 1;

  try {
    const data = await fetchHistory(1);
    renderTable(data);
  } catch (e) {
    renderTableError(toUserMessage(e, "Impossible de charger l'historique."));
  }
}

async function init() {
  if (typeof requireAuth === 'function' && !requireAuth()) return;

  bindLogout();

  if (typeof loadUserInfo === 'function') {
    await loadUserInfo();
  }

  try {
    const stats = await fetchStats();
    renderStats(stats);
  } catch (e) {
    renderStatsUnavailable();
  }

  try {
    const history = await fetchHistory(1);
    renderTable(history);
  } catch (e) {
    renderTableError(toUserMessage(e, "Impossible de charger l'historique."));
  }
}

init();
