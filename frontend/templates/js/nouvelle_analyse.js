if (!getToken()) window.location.href = 'login.html';

document.addEventListener('DOMContentLoaded', () => {
  loadUserInfo();
  loadSidebarStats();
  const logoutBtn = document.getElementById('btn-logout');
  if (logoutBtn) logoutBtn.addEventListener('click', () => Auth.logout());
  document.querySelectorAll('.sb-out').forEach(el =>
    el.addEventListener('click', e => { e.preventDefault(); Auth.logout(); })
  );
});


let selectedFile = null;

function handleDragOver(e)   { e.preventDefault(); document.getElementById('dropzone').classList.add('drag'); }
function handleDragLeave()   { document.getElementById('dropzone').classList.remove('drag'); }
function handleDrop(e)       { e.preventDefault(); document.getElementById('dropzone').classList.remove('drag'); const f = e.dataTransfer.files[0]; if (f) setFile(f); }
function handleFileSelect(e) { const f = e.target.files[0]; if (f) setFile(f); }

function setFile(file) {
  if (file.size > 100 * 1024 * 1024) { showMsg('Le fichier dépasse 100MB.', 'error'); return; }
  selectedFile = file;
  document.getElementById('file-name').textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(1) + ' MB)';
  document.getElementById('file-selected').style.display = 'flex';
  document.getElementById('git-url').value = '';
}
function removeFile() {
  selectedFile = null;
  document.getElementById('file-selected').style.display = 'none';
  document.getElementById('file-input').value = '';
}

//  Messages 
function showMsg(text, type) {
  const box = document.getElementById('msg-box');
  box.textContent = text;
  box.className   = 'msg-box msg-' + type;
  box.style.display = 'block';
  if (type === 'success') setTimeout(() => { box.style.display = 'none'; }, 4000);
}

//  Loading 
function setLoading(on) {
  document.getElementById('btn-text').style.display    = on ? 'none'  : 'inline';
  document.getElementById('btn-spinner').style.display = on ? 'block' : 'none';
  document.getElementById('btn-commencer').disabled    = on;
}

//  Progress overlay 
const STEPS = [
  "Initialisation de l'analyse…",
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

// Lancer l'analyse 
async function handleAnalyse() {
  document.getElementById('msg-box').style.display = 'none';
  document.getElementById('git-url').classList.remove('error');

  const gitUrl = document.getElementById('git-url').value.trim();

  if (!gitUrl && !selectedFile) {
    showMsg('Veuillez fournir une URL Git ou un fichier ZIP.', 'error');
    document.getElementById('git-url').classList.add('error');
    return;
  }
  if (gitUrl && !gitUrl.match(/^https?:\/\/.+/i)) {
    showMsg("L'URL Git semble invalide.", 'error');
    document.getElementById('git-url').classList.add('error');
    return;
  }

  setLoading(true);

  try {
    let scanResultId;

    if (selectedFile) {
      const projectName = selectedFile.name.replace('.zip', '');
      const result = await Upload.zip(selectedFile, projectName);
      scanResultId = result.scanResultId;
    } else {
      const projectName = gitUrl.replace(/\.git$/, '').split('/').pop() || 'Nouveau projet';
      const result = await Scans.submitGit(gitUrl, projectName);
      scanResultId = result.scanResultId || result.id;
    }

    // Progression visuelle pendant que le scan tourne en back
    await showProgress();

    // Redirection vers le dashboard avec l'ID du scan → affichage immédiat
    window.location.href = scanResultId
      ? 'dashboard.html?scan=' + scanResultId
      : 'dashboard.html';

  } catch (err) {
    document.getElementById('progress-overlay').style.display = 'none';
    showMsg(err.message || "Une erreur est survenue lors de l'analyse.", 'error');
    setLoading(false);
  }
}

//  Stats sidebar (utilise les vrais endpoints) 
async function loadSidebarStats() {
  try {
    const projects = await Projects.list();
    if (!projects || projects.length === 0) return;

    // Même logique que dashboard.js : tri par id décroissant, préférence scan avec vulnérabilités
    projects.sort((a, b) => b.id - a.id);

    let scan = null;
    let fallback = null;
    for (const p of projects) {
      try {
        const s = await Scans.latest(p.id);
        if (!s) continue;
        if (!fallback) fallback = s;
        if ((s.vulns && s.vulns.length > 0) || (s.stats?.score ?? s.stats?.globalScore ?? 100) < 100) {
          scan = s;
          break;
        }
      } catch { /* essayer suivant */ }
    }
    if (!scan) scan = fallback;
    if (!scan) return;

    const vulns = scan.vulns || [];
    const P     = { critical:20, high:12, medium:7, low:3, info:1 };
    const score = scan.stats?.score ?? scan.stats?.globalScore ?? Math.max(0, 100 - vulns.reduce((a, v) => a + (P[v.severity] || 1), 0));

    const el = id => document.getElementById(id);
    if (el('sw-score')) el('sw-score').textContent = score;
    if (el('sw-bar'))   el('sw-bar').style.width    = score + '%';
    if (el('sw-total')) el('sw-total').textContent  = vulns.length;
    if (el('sw-crit'))  el('sw-crit').textContent   = vulns.filter(v => (v.severity || '').toLowerCase() === 'critical').length;
    if (el('sw-high'))  el('sw-high').textContent   = vulns.filter(v => (v.severity || '').toLowerCase() === 'high').length;
  } catch { /* silencieux */ }
}