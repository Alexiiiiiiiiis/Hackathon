/**
 * SecureScan – dashboard.js
 */

bootstrapOAuthTokenFromUrl();
requireAuth();

let allVulns = [];

document.addEventListener('DOMContentLoaded', async () => {
  loadUserInfo();
  bindLogout();
  injectModal();
  await loadDashboard();
  await loadNotifications();
});

function bindLogout() {
  document.querySelectorAll('.sb-out').forEach(el =>
    el.addEventListener('click', e => { e.preventDefault(); Auth.logout(); })
  );
}

// ── Normalisation scan ────────────────────────────────────────
function normalizeScan(scan) {
  if (!scan) return scan;

  // Normalise les vulnérabilités : vulnerabilities → vulns
  if (!scan.vulns && scan.vulnerabilities) {
    scan.vulns = scan.vulnerabilities.map(v => ({
      id:            v.id,
      ruleId:        v.ruleId || v.rule_id,
      message:       v.message || v.description,
      severity:      (v.severity || 'info').toLowerCase(),
      filePath:      v.filePath || v.file_path,
      line:          v.line,
      codeSnippet:   v.codeSnippet || v.code_snippet,
      owaspCategory: v.owaspCategory || v.owasp_category,
      owaspLabel:    v.owaspLabel   || v.owasp_label || v.owaspCategory || v.owasp_category,
      toolSource:    v.toolSource   || v.tool_source,
      fixStatus:     v.fixStatus    || v.fix_status,
    }));
  }
  if (!scan.vulns) scan.vulns = [];

  // Normalise le score
  if (!scan.stats) {
    const score = scan.globalScore ?? scan.global_score ?? computeScore(scan.vulns);
    scan.stats = { score, globalScore: score };
  }

  return scan;
}

// ── Modal HTML ────────────────────────────────────────────────
function injectModal() {
  if (document.getElementById('fix-modal')) return;
  document.body.insertAdjacentHTML('beforeend', `
    <div id="fix-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);
      align-items:center;justify-content:center;">
      <div style="background:#fff;border-radius:16px;padding:24px;max-width:560px;width:90%;max-height:85vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);position:relative;">
        <button onclick="closeModal()" style="position:absolute;top:14px;right:18px;background:none;border:none;
          font-size:1.4rem;cursor:pointer;color:#9aa3b5;">✕</button>

        <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;">
          <span style="font-size:1.5rem;">🛡️</span>
          <h3 style="margin:0;font-size:1.1rem;color:#1a202c;" id="modal-title">Correction automatique</h3>
        </div>

        <div id="modal-vuln-info" style="background:#f8fafc;border-radius:8px;padding:14px;margin-bottom:16px;font-size:0.85rem;color:#4a5568;line-height:1.6;"></div>

        <div id="modal-fix-section" style="display:none;">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
            <span style="font-size:1rem;">✨</span>
            <strong style="font-size:0.9rem;color:#1a202c;">Correction proposée</strong>
          </div>
          <div id="modal-fix-content" style="background:#0f172a;border-radius:8px;padding:14px;font-family:monospace;
            font-size:0.8rem;color:#e2e8f0;white-space:pre-wrap;max-height:180px;overflow-y:auto;margin-bottom:16px;"></div>
          <div id="modal-explanation" style="font-size:0.83rem;color:#4a5568;line-height:1.6;margin-bottom:18px;
            padding:12px;background:#fffbeb;border-left:3px solid #f59e0b;border-radius:0 8px 8px 0;"></div>
        </div>

        <div id="modal-loading" style="text-align:center;padding:20px;display:none;">
          <div style="display:inline-block;width:32px;height:32px;border:3px solid #e2e8f0;border-top-color:#3b82f6;
            border-radius:50%;animation:spin 0.8s linear infinite;"></div>
          <p style="color:#9aa3b5;margin-top:10px;font-size:0.85rem;">Génération de la correction…</p>
        </div>

        <div id="modal-actions" style="display:flex;gap:10px;justify-content:flex-end;">
          <button onclick="closeModal()" id="modal-btn-cancel"
            style="padding:9px 20px;border-radius:8px;border:1px solid #e2e8f0;background:#fff;
                   color:#4a5568;font-size:0.85rem;cursor:pointer;font-family:inherit;">
            Annuler
          </button>
          <button onclick="rejectFix()" id="modal-btn-reject"
            style="padding:9px 20px;border-radius:8px;border:1px solid #fca5a5;background:#fef2f2;
                   color:#dc2626;font-size:0.85rem;cursor:pointer;font-family:inherit;display:none;">
            ✕ Rejeter
          </button>
          <button onclick="applyFixConfirm()" id="modal-btn-apply"
            style="padding:9px 20px;border-radius:8px;border:none;background:#3b82f6;
                   color:#fff;font-size:0.85rem;cursor:pointer;font-family:inherit;display:none;">
            ✓ Appliquer la correction
          </button>
        </div>
      </div>
    </div>
    <style>
      @keyframes spin { to { transform: rotate(360deg); } }
    </style>
  `);
}

let currentFixId   = null;
let currentFixBtn  = null;

function openModal(vuln) {
  const m = document.getElementById('fix-modal');
  m.style.display = 'flex';

  document.getElementById('modal-fix-section').style.display  = 'none';
  document.getElementById('modal-loading').style.display      = 'block';
  document.getElementById('modal-btn-apply').style.display    = 'none';
  document.getElementById('modal-btn-reject').style.display   = 'none';
  document.getElementById('modal-btn-cancel').textContent     = 'Annuler';
  document.getElementById('modal-fix-content').textContent    = '';
  document.getElementById('modal-explanation').textContent    = '';

  document.querySelectorAll('#modal-fix-section > div[style]').forEach(el => {
    const bg = el.getAttribute('style') || '';
    if (bg.includes('dcfce7') || bg.includes('fef2f2')) el.remove();
  });
  const applyBtn = document.getElementById('modal-btn-apply');
  applyBtn.textContent = '✓ Appliquer la correction';
  applyBtn.disabled    = false;

  const sev = (vuln.severity || 'info').toLowerCase();
  const SEV_COLOR = { critical:'#ef4444', high:'#f97316', medium:'#eab308', low:'#22c55e', info:'#3b82f6' };
  document.getElementById('modal-vuln-info').innerHTML = `
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
      <div><span style="color:#9aa3b5;font-size:0.75rem;">FICHIER</span><br><b style="word-break:break-all;">${esc(vuln.filePath || '—')}</b></div>
      <div><span style="color:#9aa3b5;font-size:0.75rem;">LIGNE</span><br><b>${vuln.line || '—'}</b></div>
      <div><span style="color:#9aa3b5;font-size:0.75rem;">SÉVÉRITÉ</span><br>
        <b style="color:${SEV_COLOR[sev]||'#9aa3b5'};text-transform:capitalize;">${esc(sev)}</b></div>
      <div><span style="color:#9aa3b5;font-size:0.75rem;">OWASP</span><br><b>${esc(vuln.owaspCategory || '—')}</b></div>
    </div>
    <div style="margin-top:10px;padding-top:10px;border-top:1px solid #e2e8f0;">
      <span style="color:#9aa3b5;font-size:0.75rem;">DESCRIPTION</span><br>
      ${esc(vuln.message || '—')}
    </div>`;

  return m;
}

function closeModal() {
  document.getElementById('fix-modal').style.display = 'none';
  if (currentFixBtn && (currentFixBtn.textContent === '…' || currentFixBtn.disabled)) {
    currentFixBtn.textContent = 'Corriger';
    currentFixBtn.disabled    = false;
  }
  currentFixId  = null;
  currentFixBtn = null;
}

async function applyFix(vulnId, btn) {
  const vuln = allVulns.find(v => v.id === vulnId) || { id: vulnId };

  btn.disabled    = true;
  btn.textContent = '…';
  currentFixBtn   = btn;

  openModal(vuln);

  try {
    const fix = await Fixes.generate(vulnId);
    currentFixId = fix.id;

    document.getElementById('modal-loading').style.display     = 'none';
    document.getElementById('modal-fix-section').style.display = 'block';
    document.getElementById('modal-fix-content').textContent   = fix.fixedCode || fix.suggestedFix || 'Correction générée.';
    document.getElementById('modal-explanation').textContent   = fix.explanation || fix.message || '';
    document.getElementById('modal-btn-apply').style.display   = 'inline-block';
    document.getElementById('modal-btn-reject').style.display  = 'inline-block';
    document.getElementById('modal-btn-cancel').textContent    = 'Fermer';
  } catch (err) {
    document.getElementById('modal-loading').style.display  = 'none';
    document.getElementById('modal-vuln-info').innerHTML   += `
      <div style="margin-top:12px;padding:10px;background:#fef2f2;border-radius:8px;color:#dc2626;font-size:0.83rem;">
        ⚠️ ${esc(err.message)}
      </div>`;
    if (currentFixBtn) { currentFixBtn.textContent = 'Corriger'; currentFixBtn.disabled = false; }
  }
}

async function applyFixConfirm() {
  if (!currentFixId) return;
  const btn = document.getElementById('modal-btn-apply');
  btn.textContent = '…';
  btn.disabled    = true;
  try {
    await Fixes.apply(currentFixId);
    if (currentFixBtn) {
      currentFixBtn.textContent        = '✓ Appliqué';
      currentFixBtn.style.background   = '#dcfce7';
      currentFixBtn.style.color        = '#166534';
      currentFixBtn.style.borderColor  = '#86efac';
    }
    document.getElementById('modal-fix-section').insertAdjacentHTML('afterbegin', `
      <div style="padding:12px;background:#dcfce7;border-radius:8px;color:#166534;font-size:0.85rem;margin-bottom:14px;">
        ✓ Correction appliquée avec succès !
      </div>`);
    document.getElementById('modal-btn-apply').style.display  = 'none';
    document.getElementById('modal-btn-reject').style.display = 'none';
  } catch (err) {
    btn.textContent = 'Appliquer';
    btn.disabled    = false;
    document.getElementById('modal-explanation').insertAdjacentHTML('beforebegin', `
      <div style="padding:10px;background:#fef2f2;border-radius:8px;color:#dc2626;font-size:0.83rem;margin-bottom:10px;">
        ⚠️ ${esc(err.message)}
      </div>`);
  }
}

async function rejectFix() {
  if (!currentFixId) return closeModal();
  try { await Fixes.reject(currentFixId); } catch { /* silencieux */ }
  if (currentFixBtn) { currentFixBtn.textContent = 'Corriger'; currentFixBtn.disabled = false; }
  closeModal();
}

// ── Chargement principal ──────────────────────────────────────
async function loadDashboard() {
  try {
    const urlParams     = new URLSearchParams(window.location.search);
    const scanIdFromUrl = urlParams.get('scan');
    let scan = null;

    if (scanIdFromUrl) {
      try {
        scan = normalizeScan(await Scans.results(scanIdFromUrl));
        // Ne pas effacer le ?scan= de l'URL pour pouvoir recharger
      } catch { /* fallback */ }
    }

    if (!scan) {
      const projects = await Projects.list();
      if (!projects || projects.length === 0) { showEmpty(); return; }

      projects.sort((a, b) => b.id - a.id);

      let fallback = null;
      for (const p of projects) {
        try {
          const s = normalizeScan(await Scans.latest(p.id));
          if (!s) continue;
          if (!fallback) fallback = s;
          if ((s.vulns && s.vulns.length > 0) || (s.stats?.score ?? 100) < 100) {
            scan = s;
            break;
          }
        } catch { /* essayer le projet suivant */ }
      }

      if (!scan) scan = fallback;
    }

    if (!scan) { showEmpty(); return; }

    renderScore(scan);
    renderSeverityGrid(scan);
    renderOwaspChart(scan);
    renderVulnTable(scan.vulns || []);
    renderSidebarStats(scan);

    const reportLink = document.getElementById('report-link');
    if (reportLink && scan.id) {
      const projectId = scan.projectId || scan.project?.id || scan.project_id;
      if (projectId) reportLink.href = `http://127.0.0.1:8000/api/report/${projectId}?format=pdf`;
    }

  } catch (err) {
    console.error('[Dashboard]', err);
    showError(err.message);
  }
}

// ── Score circulaire ──────────────────────────────────────────
function renderScore(scan) {
  const vulns = scan.vulns || [];
  const score = scan.stats?.score ?? scan.stats?.globalScore ?? computeScore(vulns);
  const crit  = countBy(vulns, 'critical');
  const high  = countBy(vulns, 'high');
  const med   = countBy(vulns, 'medium');
  const total = vulns.length;

  set('score-val', score);

  const circ = 2 * Math.PI * 34;
  const fill = document.getElementById('sc-fill');
  if (fill) {
    fill.style.strokeDasharray  = circ;
    fill.style.strokeDashoffset = score === 0 ? circ - 2 : circ - (score / 100) * circ;
    fill.style.stroke = score >= 75 ? '#22c55e' : score >= 50 ? '#eab308' : '#ef4444';
  }

  const badge = document.getElementById('score-badge');
  if (badge) {
    if (score >= 85)      { badge.textContent = 'Excellent';             badge.style.cssText = 'background:#dcfce7;color:#166534;padding:3px 10px;border-radius:99px;font-size:0.75rem;font-weight:600;'; }
    else if (score >= 65) { badge.textContent = "Besoin d'attention";    badge.style.cssText = 'background:#fef9c3;color:#854d0e;padding:3px 10px;border-radius:99px;font-size:0.75rem;font-weight:600;'; }
    else                  { badge.textContent = "Besoin d'amélioration"; badge.style.cssText = 'background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:99px;font-size:0.75rem;font-weight:600;'; }
  }

  const pct = n => (total ? Math.round((n / total) * 100) : 0) + '%';
  set('cnt-c', crit); barWidth('bar-c', pct(crit));
  set('cnt-h', high); barWidth('bar-h', pct(high));
  set('cnt-m', med);  barWidth('bar-m', pct(med));
}

// ── Grille gravité ────────────────────────────────────────────
function renderSeverityGrid(scan) {
  const vulns = scan.vulns || [];
  set('g-c', countBy(vulns, 'critical'));
  set('g-h', countBy(vulns, 'high'));
  set('g-m', countBy(vulns, 'medium'));
  set('g-l', countBy(vulns, 'low'));
}

// ── Donut OWASP ───────────────────────────────────────────────
function renderOwaspChart(scan) {
  const vulns   = scan.vulns || [];
  const byOwasp = {};
  vulns.forEach(v => {
    const k = v.owaspLabel || v.owaspCategory || 'Inconnu';
    byOwasp[k] = (byOwasp[k] || 0) + 1;
  });

  const total  = vulns.length;
  const svg    = document.getElementById('owasp-svg');
  const legend = document.getElementById('owasp-legend');
  set('owasp-total', total);
  if (!svg || !legend || total === 0) return;

  svg.querySelectorAll('.owasp-arc').forEach(e => e.remove());

  const COLORS  = ['#3b82f6','#22d3ee','#8b5cf6','#f97316','#ef4444','#22c55e','#eab308','#ec4899','#14b8a6','#a78bfa'];
  const entries = Object.entries(byOwasp).sort((a, b) => b[1] - a[1]);
  const circ    = 2 * Math.PI * 38;
  let   offset  = 0;

  entries.forEach(([label, count], i) => {
    const pct   = count / total;
    const color = COLORS[i % COLORS.length];
    const arc   = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
    arc.setAttribute('class',             'owasp-arc');
    arc.setAttribute('cx',                '55');
    arc.setAttribute('cy',                '55');
    arc.setAttribute('r',                 '38');
    arc.setAttribute('fill',              'none');
    arc.setAttribute('stroke',            color);
    arc.setAttribute('stroke-width',      '18');
    arc.setAttribute('stroke-dasharray',  `${pct * circ} ${circ}`);
    arc.setAttribute('stroke-dashoffset', -(offset * circ - circ * 0.25));
    svg.insertBefore(arc, document.getElementById('owasp-cover'));
    offset += pct;
  });

  legend.innerHTML = entries.map(([label, count], i) => `
    <div style="display:flex;align-items:center;gap:6px;font-size:0.72rem;color:#4a5568;margin-bottom:5px;">
      <span style="width:10px;height:10px;border-radius:2px;background:${COLORS[i % COLORS.length]};flex-shrink:0;"></span>
      <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${esc(label)}">${esc(label)}</span>
      <b>${count}</b>
    </div>`).join('');
}

// ── Tableau vulnérabilités ────────────────────────────────────
function renderVulnTable(vulns) {
  allVulns = vulns;
  const tbody = document.getElementById('vuln-tbody');
  if (!tbody) return;
  if (vulns.length === 0) {
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px;color:#9aa3b5;">Aucune vulnérabilité détectée 🎉</td></tr>';
    return;
  }
  drawTable(vulns);
}

function drawTable(vulns) {
  const tbody = document.getElementById('vuln-tbody');
  if (!tbody) return;
  const SEV_COLOR = { critical:'#ef4444', high:'#f97316', medium:'#eab308', low:'#22c55e', info:'#3b82f6' };
  tbody.innerHTML = vulns.map(v => {
    const sev   = (v.severity || 'info').toLowerCase();
    const color = SEV_COLOR[sev] || '#9aa3b5';
    const file  = v.filePath ? v.filePath.split(/[\\/]/).pop() : '—';
    return `<tr>
      <td title="${esc(v.filePath || '')}">${esc(file)}</td>
      <td>${v.line || '—'}</td>
      <td><span style="color:${color};font-weight:600;text-transform:capitalize;">${esc(sev)}</span></td>
      <td title="${esc(v.owaspLabel || '')}">${esc(v.owaspCategory || '—')}</td>
      <td>
        <button onclick="applyFix(${v.id}, this)"
          style="padding:4px 10px;border-radius:6px;border:1px solid #3b82f6;background:#eff6ff;
                 color:#2563eb;font-size:0.72rem;cursor:pointer;font-family:inherit;">
          Corriger
        </button>
      </td>
    </tr>`;
  }).join('');
}

function filterTable() {
  const ff = (document.getElementById('ff')?.value || '').toLowerCase();
  const fs = (document.getElementById('fs')?.value || '').toLowerCase();
  const fo = (document.getElementById('fo')?.value || '').toLowerCase();
  drawTable(allVulns.filter(v =>
    (!ff || (v.filePath || '').toLowerCase().includes(ff)) &&
    (!fs || (v.severity || '').toLowerCase().includes(fs)) &&
    (!fo || (v.owaspCategory || '').toLowerCase().includes(fo) || (v.owaspLabel || '').toLowerCase().includes(fo))
  ));
}

// ── Sidebar stats ─────────────────────────────────────────────
function renderSidebarStats(scan) {
  const vulns = scan.vulns || [];
  const score = scan.stats?.score ?? scan.stats?.globalScore ?? computeScore(vulns);
  set('sw-score', score);
  set('sw-total', vulns.length);
  set('sw-crit',  countBy(vulns, 'critical'));
  set('sw-high',  countBy(vulns, 'high'));
  barWidth('sw-bar', score + '%');
}

// ── Notifications ─────────────────────────────────────────────
async function loadNotifications() {
  try {
    const data  = await Notifications.count();
    const badge = document.getElementById('notif-badge');
    if (badge && data.count > 0) { badge.textContent = data.count; badge.style.display = 'flex'; }
  } catch { /* silencieux */ }
}

// ── Helpers ───────────────────────────────────────────────────
function computeScore(vulns) {
  if (!vulns.length) return 100;
  const P = { critical:20, high:12, medium:7, low:3, info:1 };
  return Math.max(0, 100 - vulns.reduce((a, v) => a + (P[v.severity] || 1), 0));
}
function countBy(vulns, sev)  { return vulns.filter(v => (v.severity || '').toLowerCase() === sev).length; }
function esc(str)             { return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function set(id, val)         { const el = document.getElementById(id); if (el) el.textContent = val; }
function barWidth(id, w)      { const el = document.getElementById(id); if (el) el.style.width  = w;  }

function showEmpty() {
  const tbody = document.getElementById('vuln-tbody');
  if (tbody) tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:20px;color:#9aa3b5;">
    Aucun projet analysé. <a href="nouvelle_analyse.html" style="color:#3b82f6;">Lancer une analyse →</a>
  </td></tr>`;
  ['score-val','g-c','g-h','g-m','g-l','owasp-total','cnt-c','cnt-h','cnt-m','sw-score','sw-total','sw-crit','sw-high']
    .forEach(id => set(id, '0'));
  set('score-val', '100'); set('sw-score', '100');
}

function showError(msg) {
  const tbody = document.getElementById('vuln-tbody');
  if (tbody) tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:20px;color:#ef4444;">Erreur : ${esc(msg)}</td></tr>`;
}

function bootstrapOAuthTokenFromUrl() {
  const queryParams = new URLSearchParams(window.location.search);
  const hashRaw = window.location.hash.startsWith('#') ? window.location.hash.slice(1) : '';
  const hashParams = new URLSearchParams(hashRaw);

  const tokenFromHash  = (hashParams.get('token')  || '').trim();
  const tokenFromQuery = (queryParams.get('token') || '').trim();
  const token = tokenFromHash || tokenFromQuery;

  if (token) saveToken(token);

  let changed = false;
  if (tokenFromQuery) { queryParams.delete('token'); changed = true; }
  if (tokenFromHash)  { hashParams.delete('token');  changed = true; }

  if (changed && window.history && window.history.replaceState) {
    const query = queryParams.toString();
    const hash  = hashParams.toString();
    const cleanUrl = window.location.pathname
      + (query ? ('?' + query) : '')
      + (hash  ? ('#' + hash)  : '');
    window.history.replaceState({}, document.title, cleanUrl);
  }
}