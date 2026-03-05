const REPORTS = [
  {
    id: 1,
    name: "Securite Rapport-project-alpha",
    repo: "username/project-alpha",
    type: "rapport complet",
    typeColor: "cyan",
    date: "01-03-2026",
    size: "2.4 MB",
  },
  {
    id: 2,
    name: "Securite Rapport-webapp-beta",
    repo: "username/webapp-beta",
    type: "rapport complet",
    typeColor: "cyan",
    date: "21-02-2026",
    size: "1.8 MB",
  },
  {
    id: 3,
    name: "Conformite OWASP - api-services",
    repo: "username/api-service",
    type: "conformite",
    typeColor: "purple",
    date: "12-02-2026",
    size: "3.2 MB",
  },
  {
    id: 4,
    name: "Resume executif-code-existant",
    repo: "username/mobile-app",
    type: "resume executif",
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

function escapeHtml(str) {
  return String(str)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function renderTable(data) {
  if (!elTable || !elInfo) return;

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

    const pdfBtn = row.querySelector(".pdf");
    if (pdfBtn) {
      pdfBtn.addEventListener("click", () => {
        alert(`Telechargement: ${r.name}`);
      });
    }

    elTable.appendChild(row);
  });

  elInfo.textContent = `Voir 1-${Math.min(3, data.length)} sur ${Math.max(24, data.length)} rapports`;
}

function applyFilters() {
  const name = (elFilterName?.value || "").trim().toLowerCase();
  const repo = (elFilterRepo?.value || "").trim().toLowerCase();
  const type = elFilterType?.value || "";

  const filtered = REPORTS.filter((r) => {
    const okName = !name || r.name.toLowerCase().includes(name);
    const okRepo = !repo || r.repo.toLowerCase().includes(repo);
    const okType = !type || r.type === type;
    return okName && okRepo && okType;
  });

  renderTable(filtered);
}

function bindLogout() {
  document.querySelectorAll(".sb-out").forEach((el) => {
    el.addEventListener("click", (e) => {
      e.preventDefault();
      if (typeof Auth !== "undefined" && typeof Auth.logout === "function") {
        Auth.logout();
        return;
      }
      window.location.href = "login.html";
    });
  });
}

async function hydrateUserInfo() {
  if (typeof loadUserInfo === "function") {
    await loadUserInfo();
  }

  let user = typeof getUser === "function" ? getUser() : null;
  if (!user && typeof Auth !== "undefined" && typeof Auth.me === "function") {
    try {
      user = await Auth.me();
    } catch (_) {
      user = null;
    }
  }
  if (!user) return;

  const email = user.email || "";
  const pseudo = email.split("@")[0] || "utilisateur";
  const initial = pseudo.charAt(0).toUpperCase() || "U";

  const avatar = document.querySelector(".avatar");
  if (avatar) avatar.textContent = initial;

  const accountName = document.querySelector(".account-name");
  if (accountName) accountName.textContent = pseudo;

  const accountMail = document.querySelector(".account-mail");
  if (accountMail) accountMail.textContent = email;

  const topbarName = document.querySelector(".user-btn span:last-child");
  if (topbarName) topbarName.textContent = pseudo;
}

const checks = Array.from(document.querySelectorAll(".section-check"));
const elCount = document.getElementById("sectionsCount");

function updateSelectedCount() {
  if (!elCount) return;
  const n = checks.filter((c) => c.checked).length;
  elCount.textContent = `${n} section${n > 1 ? "s" : ""} selectionne${n > 1 ? "es" : ""}`;
}

async function initPage() {
  if (typeof requireAuth === "function" && !requireAuth()) return;

  bindLogout();
  await hydrateUserInfo();

  if (elFilterName) elFilterName.addEventListener("input", applyFilters);
  if (elFilterRepo) elFilterRepo.addEventListener("input", applyFilters);
  if (elFilterType) elFilterType.addEventListener("change", applyFilters);

  checks.forEach((c) => c.addEventListener("change", updateSelectedCount));

  const genBtn = document.getElementById("genBtn");
  if (genBtn) {
    genBtn.addEventListener("click", () => {
      const n = checks.filter((c) => c.checked).length;
      alert(`Rapport genere avec ${n} section${n > 1 ? "s" : ""}.`);
    });
  }

  renderTable(REPORTS);
  updateSelectedCount();
}

initPage();
