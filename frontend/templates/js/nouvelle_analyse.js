/**
 * SecureScan – nouvelle_analyse.js
 * Branche la page sur les vrais endpoints Symfony via api.js
 */

// ── Guard auth ────────────────────────────────────────────────
if (!getToken()) window.location.href = 'login.html';

// ── Charger infos utilisateur ─────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  loadUserInfo();
  loadSidebarStats();

  // Logout
  const logoutBtn = document.getElementById('btn-logout');
  if (logoutBtn) logoutBtn.addEventListener('click', () => Auth.logout());
});

// ── Drag & Drop ───────────────────────────────────────────────
let selectedFile = null;

function handleDragOver(e) {
  e.preventDefault();
  document.getElementById('dropzone').classList.add('drag');
}
function handleDragLeave() {
  document.getElementById('dropzone').classList.remove('drag');
}
function handleDrop(e) {
  e.preventDefault();
  document.getElementById('dropzone').classList.remove('drag');
  const file = e.dataTransfer.files[0];
  if (file) setFile(file);
}
function handleFileSelect(e) {
  const file = e.target.files[0];
  if (file) setFile(file);
}
function setFile(file) {
  if (file.size > 100 * 1024 * 1024) {
    showMsg('Le fichier dépasse la taille maximum de 100MB.', 'error');
    return;
  }
  selectedFile = file;
  document.getElementById('file-name').textContent =
    file.name + ' (' + (file.size / 1024 / 1024).toFixed(1) + ' MB)';
  document.getElementById('file-selected').style.display = 'flex';
  document.getElementById('git-url').value = '';
}
function removeFile() {
  selectedFile = null;
  document.getElementById('file-selected').style.display = 'none';
  document.getElementById('file-input').value = '';
}

// ── Messages ──────────────────────────────────────────────────
function showMsg(text, type) {
  const box = document.getElementById('msg-box');
  box.textContent = text;
  box.className = 'msg-box msg-' + type;
  box.style.display = 'block';
  if (type === 'success') setTimeout(() => { box.style.display = 'none'; }, 4000);
}

// ── Loading ───────────────────────────────────────────────────
function setLoading(on) {
  document.getElementById('btn-text').style.display = on ? 'none' : 'inline';
  document.getElementById('btn-spinner').style.display = on ? 'block' : 'none';
  document.getElementById('btn-commencer').disabled = on;
}

// ── Progress overlay ──────────────────────────────────────────
const STEPS = [
  'Initialisation de l\'analyse…',
  'Clonage du repository…',
  'Analyse statique (SAST)…',
  'Analyse des dépendances…',
  'Détection des secrets…',
  'Analyse de qualité du code…',
  'Génération du rapport…',
];

function showProgress() {
  document.getElementById('progress-overlay').style.display = 'flex';
  const fill  = document.getElementById('progress-fill');
  const label = document.getElementById('progress-label');
  let i = 0;
  return new Promise(resolve => {
    const iv = setInterval(() => {
      if (i >= STEPS.length) { clearInterval(iv); resolve(); return; }
      label.textContent = STEPS[i];
      fill.style.width  = ((i + 1) / STEPS.length * 100) + '%';
      i++;
    }, 700);
  });
}

// ── Lancer l'analyse ──────────────────────────────────────────
async function handleAnalyse() {
  document.getElementById('msg-box').style.display = 'none';
  document.getElementById('git-url').classList.remove('error');

  const gitUrl = document.getElementById('git-url').value.trim();

  // Validation : au moins une source
  if (!gitUrl && !selectedFile) {
    showMsg('Veuillez fournir une URL Git ou un fichier ZIP.', 'error');
    document.getElementById('git-url').classList.add('error');
    return;
  }

  // Validation URL git
  if (gitUrl && !gitUrl.match(/^https?:\/\/.+/i)) {
    showMsg('L\'URL Git semble invalide.', 'error');
    document.getElementById('git-url').classList.add('error');
    return;
  }

  setLoading(true);

  try {
    let scanResultId;

    if (selectedFile) {
      // ── Upload ZIP ────────────────────────────────────────
      const projectName = selectedFile.name.replace('.zip', '');
      const result = await Upload.zip(selectedFile, projectName);
      scanResultId = result.scanResultId;

    } else {
      // ── Git URL ───────────────────────────────────────────
      const projectName = gitUrl.replace(/\.git$/, '').split('/').pop() || 'Nouveau projet';
      const result = await Scans.submitGit(gitUrl, projectName);
      scanResultId = result.scanResultId || result.id;
    }

    // Afficher la progression pendant que le scan tourne
    await showProgress();

    // Redirection vers le dashboard avec l'ID du scan
    if (scanResultId) {
      window.location.href = 'dashboard.html?scan=' + scanResultId;
    } else {
      window.location.href = 'dashboard.html';
    }

  } catch (err) {
    document.getElementById('progress-overlay').style.display = 'none';
    showMsg(err.message || 'Une erreur est survenue lors de l\'analyse.', 'error');
    setLoading(false);
  }
}

// ── Stats sidebar ─────────────────────────────────────────────
async function loadSidebarStats() {
  try {
    const d = await apiRequest('GET', '/api/dashboard/stats');
    const score = d.score ?? 100;
    const total = (d.criticalVulns ?? 0) + (d.highVulns ?? 0) + (d.mediumVulns ?? 0) + (d.lowVulns ?? 0);

    const scoreEl = document.getElementById('sw-score');
    const barEl   = document.getElementById('sw-bar');
    const totalEl = document.getElementById('sw-total');
    const critEl  = document.getElementById('sw-crit');
    const highEl  = document.getElementById('sw-high');

    if (scoreEl) scoreEl.textContent   = score;
    if (barEl)   barEl.style.width     = score + '%';
    if (totalEl) totalEl.textContent   = total;
    if (critEl)  critEl.textContent    = d.criticalVulns ?? 0;
    if (highEl)  highEl.textContent    = d.highVulns ?? 0;
  } catch {
    // Silencieux si pas de données
  }
}