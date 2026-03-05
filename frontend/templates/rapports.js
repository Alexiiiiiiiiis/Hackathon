// Données (en français comme sur le screen)
const REPORTS = [
  {
    id: 1,
    name: "Sécurité Rapport-project-alpha",
    repo: "username/project-alpha",
    type: "rapport complet",
    typeColor: "cyan",
    date: "01-03-2026",
    size: "2.4 MB",
  },
  {
    id: 2,
    name: "Sécurité Rapport-webapp-beta",
    repo: "username/webapp-beta",
    type: "rapport complet",
    typeColor: "cyan",
    date: "21-02-2026",
    size: "1.8 MB",
  },
  {
    id: 3,
    name: "Conformité OWASP - api-services",
    repo: "username/api-service",
    type: "conformité",
    typeColor: "purple",
    date: "12-02-2026",
    size: "3.2 MB",
  },
  {
    id: 4,
    name: "Résumé exécutif-code-existant",
    repo: "username/mobile-app",
    type: "résumé exécutif",
    typeColor: "green",
    date: "03-02-2026",
    size: "1.5 MB",
  },
];

const elTable = document.getElementById("reportsTable");
const elInfo = document.getElementById("tableInfo");

const elFilterName = document.getElementById("filterName");
const elFilterRepo = document.getElementById("filterRepo");
const elFilterType = document.getElementById("filterType");

function pillClass(color) {
  if (color === "purple") return "pill purple";
  if (color === "green") return "pill green";
  return "pill cyan";
}

function renderTable(data) {
  elTable.innerHTML = "";

  data.forEach((r) => {
    const row = document.createElement("div");
    row.className = "trow tbody-row";

    row.innerHTML = `
      <div>
        <div class="report-name">${escapeHtml(r.name)}</div>
        <div class="report-sub">Format PDF</div>
      </div>
      <div class="repo">${escapeHtml(r.repo)}</div>
      <div><span class="${pillClass(r.typeColor)}">${escapeHtml(r.type)}</span></div>
      <div class="date"><span class="cal">📅</span><span>${escapeHtml(r.date)}</span></div>
      <div class="size">${escapeHtml(r.size)}</div>
      <div class="tcenter"><button class="pdf" type="button">PDF</button></div>
    `;

    // (Optionnel) clic PDF
    row.querySelector(".pdf").addEventListener("click", () => {
      alert(`Téléchargement: ${r.name}`);
    });

    elTable.appendChild(row);
  });

  // Info en bas (simple)
  elInfo.textContent = `Voir 1-${Math.min(3, data.length)} sur ${Math.max(24, data.length)} rapports`;
}

function applyFilters() {
  const name = elFilterName.value.trim().toLowerCase();
  const repo = elFilterRepo.value.trim().toLowerCase();
  const type = elFilterType.value;

  const filtered = REPORTS.filter((r) => {
    const okName = !name || r.name.toLowerCase().includes(name);
    const okRepo = !repo || r.repo.toLowerCase().includes(repo);
    const okType = !type || r.type === type;
    return okName && okRepo && okType;
  });

  renderTable(filtered);
}

function escapeHtml(str) {
  return String(str)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

// Builder: compteur de sections
const checks = Array.from(document.querySelectorAll(".section-check"));
const elCount = document.getElementById("sectionsCount");

function updateSelectedCount() {
  const n = checks.filter((c) => c.checked).length;
  elCount.textContent = `${n} section${n > 1 ? "s" : ""} sélectionné${n > 1 ? "es" : ""}`;
}

checks.forEach((c) => c.addEventListener("change", updateSelectedCount));

elFilterName.addEventListener("input", applyFilters);
elFilterRepo.addEventListener("input", applyFilters);
elFilterType.addEventListener("change", applyFilters);

document.getElementById("genBtn").addEventListener("click", () => {
  const n = checks.filter((c) => c.checked).length;
  alert(`Rapport généré avec ${n} section${n > 1 ? "s" : ""}.`);
});

// init
renderTable(REPORTS);
updateSelectedCount();