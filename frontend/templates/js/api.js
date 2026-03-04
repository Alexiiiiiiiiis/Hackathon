/**
 * ============================================================
 *  SecureScan – api.js  (charger EN PREMIER sur toutes les pages)
 *  Changez API_BASE_URL si votre backend tourne ailleurs
 * ============================================================
 */
const API_BASE_URL = resolveApiBaseUrl();

function resolveApiBaseUrl() {
  if (window.__API_BASE__) {
    return String(window.__API_BASE__).replace(/\/$/, '');
  }

  if (window.location.protocol === 'file:') {
    return 'http://127.0.0.1:8000';
  }

  return `${window.location.protocol}//${window.location.hostname}:8000`;
}

// ── Stockage JWT ──────────────────────────────────────────────
function getToken()   { return localStorage.getItem('ss_token'); }
function saveToken(t) { localStorage.setItem('ss_token', t); }
function clearAuth()  {
  localStorage.removeItem('ss_token');
  localStorage.removeItem('ss_user');
}
function saveUser(u)  { localStorage.setItem('ss_user', JSON.stringify(u)); }
function getUser()    {
  try { return JSON.parse(localStorage.getItem('ss_user') || 'null'); } catch { return null; }
}

// ── Requête générique ─────────────────────────────────────────
async function apiRequest(method, path, body = null, isFormData = false) {
  const headers = { 'Accept': 'application/json' };
  const token = getToken();
  if (token) headers['Authorization'] = 'Bearer ' + token;
  if (body && !isFormData) headers['Content-Type'] = 'application/json';

  const opts = { method, headers };
  if (body) opts.body = isFormData ? body : JSON.stringify(body);

  const res = await fetch(API_BASE_URL + path, opts);

  if (res.status === 401) {
    clearAuth();
    if (!window.location.href.includes('login')) window.location.href = 'login.html';
    throw new Error('Non authentifié');
  }

  const text = await res.text();
  let data = null;
  try { data = text ? JSON.parse(text) : null; } catch { data = { message: text }; }

  if (!res.ok) {
    const msg = data?.message
      || data?.error
      || (Array.isArray(data?.errors) ? data.errors.join(', ') : null)
      || 'Erreur serveur (' + res.status + ')';
    throw new Error(msg);
  }
  return data;
}

// ── Auth ──────────────────────────────────────────────────────
const Auth = {
  async login(email, password) {
    const data = await apiRequest('POST', '/api/auth/login', { email, password });
    saveToken(data.token);
    if (data.user) saveUser(data.user);
    return data;
  },
  async register(email, password) {
    const data = await apiRequest('POST', '/api/auth/register', { email, password });
    saveToken(data.token);
    if (data.user) saveUser(data.user);
    return data;
  },
  async me() {
    const data = await apiRequest('GET', '/api/auth/me');
    saveUser(data);
    return data;
  },
  logout() {
    clearAuth();
    window.location.href = 'login.html';
  }
};

// ── Projets ───────────────────────────────────────────────────
const Projects = {
  list()          { return apiRequest('GET', '/api/projects'); },
  get(id)         { return apiRequest('GET', `/api/projects/${id}`); },
  create(payload) { return apiRequest('POST', '/api/projects', payload); },
  delete(id)      { return apiRequest('DELETE', `/api/projects/${id}`); },
};

// ── Scans ─────────────────────────────────────────────────────
const Scans = {
  submitGit(gitUrl, name) {
    return apiRequest('POST', '/api/scan/project', {
      gitUrl,
      name: name || gitUrl.replace(/\.git$/, '').split('/').pop() || 'Nouveau projet',
      sourceType: gitUrl.includes('gitlab.com') ? 'gitlab' : 'github',
    });
  },
  launch(projectId)  { return apiRequest('POST', `/api/scan/${projectId}/launch`); },
  results(scanId)    { return apiRequest('GET',  `/api/scan/${scanId}`); },
  latest(projectId)  { return apiRequest('GET',  `/api/scan/project/${projectId}/latest`); },
  owasp(scanId)      { return apiRequest('GET',  `/api/scan/${scanId}/owasp`); },
};

// ── Upload ZIP ────────────────────────────────────────────────
const Upload = {
  zip(file, name) {
    const fd = new FormData();
    fd.append('file', file);
    fd.append('name', name || file.name.replace('.zip', ''));
    return apiRequest('POST', '/api/upload/zip', fd, true);
  }
};

// ── Fixes ─────────────────────────────────────────────────────
const Fixes = {
  generate(vulnId) { return apiRequest('POST', `/api/fix/generate/${vulnId}`); },
  apply(fixId)     { return apiRequest('POST', `/api/fix/${fixId}/apply`); },
  reject(fixId)    { return apiRequest('POST', `/api/fix/${fixId}/reject`); },
};

// ── Notifications ─────────────────────────────────────────────
const Notifications = {
  count() { return apiRequest('GET', '/api/notifications/count'); }
};

// ── Rapports ─────────────────────────────────────────────────
const Reports = {
  url(projectId) { return `${API_BASE_URL}/api/report/${projectId}`; }
};

// ── Guard auth ────────────────────────────────────────────────
function requireAuth() {
  if (!getToken()) { window.location.href = 'login.html'; return false; }
  return true;
}

// ── Remplir infos utilisateur dans sidebar/topbar ────────────
async function loadUserInfo() {
  try {
    let user = getUser();
    if (!user && getToken()) user = await Auth.me();
    if (!user) return;
    const email   = user.email || '';
    const initial = email.charAt(0).toUpperCase();
    const pseudo  = email.split('@')[0];
    document.querySelectorAll('.su-av').forEach(el => el.textContent = initial);
    document.querySelectorAll('.su-name').forEach(el => el.textContent = pseudo);
    document.querySelectorAll('.su-mail').forEach(el => el.textContent = email);
    document.querySelectorAll('.tb-user').forEach(el => {
      const svg = el.querySelector('svg');
      el.textContent = pseudo;
      if (svg) el.prepend(svg);
    });
  } catch { /* silencieux */ }
}
