/**
 * SecureScan – dashboard.js
 * Charge les projets, le dernier scan, les stats et le tableau de vulnérabilités
 */

requireAuth();

let allVulns = [];

document.addEventListener('DOMContentLoaded', async () => {
  loadUserInfo();
  bindLogout();
  await loadDashboard();
  await loadNotifications();
});

function bindLogout() {
  document.querySelectorAll('.sb-out').forEach(el => {
    el.addEventListener('click', e => { e.preventDefault(); Auth.logout(); });
  });
}

// ── Chargement principal ──────────────────────────────────────
async function loadDashboard() {
  try {
    const projects = await Projects.list();
    if (!projects || projects.length === 0) { showEmpty(); return; }

    projects.sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));

    let scan = null;
    for (const p of projects) {
      try { scan = await Scans.latest(p.id); break; } catch { /* essayer suivant */ }
    }
    if (!scan) { showEmpty(); return; }

    renderScore(scan);
    renderSeverityGrid(scan);
    renderOwaspChart(scan);
    renderVulnTable(scan.vulns || []);
    renderSidebarStats(scan);

  } catch (err) {
    console.error('[Dashboard]', err);
    showError(err.message);
  }
}

// ── Score circulaire ──────────────────────────────────────────
function renderScore(scan) {
  const vulns = scan.vulns || [];
  const score = computeScore(vulns);
  const crit  = countBy(vulns, 'critical');
  const high  = countBy(vulns, 'high');
  const med   = countBy(vulns, 'medium');
  const total = vulns.length;

  set('score-val', score);

  // Arc SVG (r=34, circonférence ≈ 213.6)
  const circ = 2 * Math.PI * 34;
  const fill = document.getElementById('sc-fill');
  if (fill) {
    fill.style.strokeDasharray  = circ;
    fill.style.strokeDashoffset = circ - (score / 100) * circ;
    fill.style.stroke = score >= 75 ? '#22c55e' : score >= 50 ? '#eab308' : '#ef4444';
  }

  const badge = document.getElementById('score-badge');
  if (badge) {
    if (score >= 85)      { badge.textContent = 'Excellent';             badge.style.cssText = 'background:#dcfce7;color:#166534'; }
    else if (score >= 65) { badge.textContent = 'Besoin d\'attention';   badge.style.cssText = 'background:#fef9c3;color:#854d0e'; }
    else                  { badge.textContent = 'Besoin d\'amélioration';badge.style.cssText = 'background:#fee2e2;color:#991b1b'; }
  }

  // Barres critiques/high/medium
  const pct = (n) => (total ? Math.round((n / total) * 100) : 0) + '%';
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

  const total   = vulns.length;
  const svg     = document.getElementById('owasp-svg');
  const legend  = document.getElementById('owasp-legend');
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
    arc.setAttribute('class',            'owasp-arc');
    arc.setAttribute('cx',               '55');
    arc.setAttribute('cy',               '55');
    arc.setAttribute('r',                '38');
    arc.setAttribute('fill',             'none');
    arc.setAttribute('stroke',           color);
    arc.setAttribute('stroke-width',     '18');
    arc.setAttribute('stroke-dasharray', `${pct * circ} ${circ}`);
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
    const file  = v.filePath ? v.filePath.split('/').pop() : '—';
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

// ── Appliquer un fix ──────────────────────────────────────────
async function applyFix(vulnId, btn) {
  btn.disabled   = true;
  btn.textContent = '…';
  try {
    const fix = await Fixes.generate(vulnId);
    const msg = `Vulnérabilité : ${fix.explanation || ''}\n\nAppliquer la correction automatique ?`;
    if (confirm(msg)) {
      await Fixes.apply(fix.id);
      btn.textContent                    = '✓ Appliqué';
      btn.style.background               = '#dcfce7';
      btn.style.color                    = '#166534';
      btn.style.borderColor              = '#86efac';
    } else {
      await Fixes.reject(fix.id);
      btn.textContent = 'Rejeté';
      btn.disabled    = false;
    }
  } catch (err) {
    alert('Erreur : ' + err.message);
    btn.textContent = 'Corriger';
    btn.disabled    = false;
  }
}

// ── Sidebar stats ─────────────────────────────────────────────
function renderSidebarStats(scan) {
  const vulns = scan.vulns || [];
  const score = computeScore(vulns);
  set('sw-score', score);
  set('sw-total', vulns.length);
  set('sw-crit',  countBy(vulns, 'critical'));
  set('sw-high',  countBy(vulns, 'high'));
  barWidth('sw-bar', score + '%');
}

// ── Notifications ─────────────────────────────────────────────
async function loadNotifications() {
  try {
    const data = await Notifications.count();
    const badge = document.getElementById('notif-badge');
    if (badge && data.count > 0) {
      badge.textContent    = data.count;
      badge.style.display  = 'flex';
    }
  } catch { /* silencieux */ }
}

// ── Helpers ───────────────────────────────────────────────────
function computeScore(vulns) {
  if (!vulns.length) return 100;
  const P = { critical:20, high:12, medium:7, low:3, info:1 };
  return Math.max(0, 100 - vulns.reduce((a, v) => a + (P[v.severity] || 1), 0));
}
function countBy(vulns, sev) {
  return vulns.filter(v => (v.severity || '').toLowerCase() === sev).length;
}
function esc(str) {
  return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
function set(id, val) {
  const el = document.getElementById(id);
  if (el) el.textContent = val;
}
function barWidth(id, w) {
  const el = document.getElementById(id);
  if (el) el.style.width = w;
}

function showEmpty() {
  const tbody = document.getElementById('vuln-tbody');
  if (tbody) tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:20px;color:#9aa3b5;">
    Aucun projet analysé. <a href="nouvelle_analyse.html" style="color:#3b82f6;">Lancer une analyse →</a>
  </td></tr>`;
  ['score-val','g-c','g-h','g-m','g-l','owasp-total','cnt-c','cnt-h','cnt-m','sw-score','sw-total','sw-crit','sw-high']
    .forEach(id => set(id, '0'));
}

function showError(msg) {
  const tbody = document.getElementById('vuln-tbody');
  if (tbody) tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:20px;color:#ef4444;">Erreur : ${esc(msg)}</td></tr>`;
}