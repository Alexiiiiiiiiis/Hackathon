const DEFAULT_PER_PAGE = 10;
const FILTER_DEBOUNCE_MS = 300;

const elTable = document.getElementById("reportsTable");
const elInfo = document.getElementById("tableInfo");
const elFilterName = document.getElementById("filterName");
const elFilterRepo = document.getElementById("filterRepo");
const elFilterType = document.getElementById("filterType");

const checks = Array.from(document.querySelectorAll(".section-check"));
const elCount = document.getElementById("sectionsCount");

const state = {
  page: 1,
  perPage: DEFAULT_PER_PAGE,
  total: 0,
  debounceTimer: null,
};

function getApiBaseUrl() {
  if (typeof API_BASE_URL === "string" && API_BASE_URL) return API_BASE_URL;
  if (window.location.protocol === "file:") return "http://127.0.0.1:8000";
  return `${window.location.protocol}//${window.location.hostname}:8000`;
}

function getAuthToken() {
  if (typeof getToken === "function") return getToken();
  return localStorage.getItem("ss_token");
}

function buildReportApiUrl(projectId, options = {}) {
  const params = new URLSearchParams();

  if (options.scanId) params.set("scan_id", String(options.scanId));
  if (options.download) params.set("download", "1");
  if (options.format) params.set("format", String(options.format));

  const query = params.toString();
  const suffix = query ? `?${query}` : "";
  return `${getApiBaseUrl()}/api/report/${projectId}${suffix}`;
}

function canonicalType(value) {
  return String(value || "")
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .trim()
    .replace(/\s+/g, " ");
}

function normalizeTypeFilter(value) {
  const type = canonicalType(value);
  if (!type) return "";
  if (type.includes("resume")) return "resume executif";
  if (type.includes("conformite")) return "conformite";
  if (type.includes("rapport")) return "rapport complet";
  return "";
}

function typeToPill(typeValue) {
  const type = canonicalType(typeValue);
  if (type.includes("resume")) return "pill green";
  if (type.includes("conformite")) return "pill purple";
  return "pill cyan";
}

function formatType(typeValue) {
  const type = canonicalType(typeValue);
  if (type === "resume executif") return "resume executif";
  if (type === "conformite") return "conformite";
  if (type === "rapport complet") return "rapport complet";
  return String(typeValue || "—");
}

function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function safeFilename(value) {
  return String(value || "rapport-securite")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-zA-Z0-9._-]/g, "-")
    .replace(/-+/g, "-")
    .replace(/^-|-$/g, "")
    .toLowerCase() || "rapport-securite";
}

function showTableMessage(message, color = "#64748b") {
  if (!elTable) return;
  elTable.innerHTML = `
    <div class="trow tbody-row">
      <div style="grid-column:1 / -1;text-align:center;padding:12px 0;color:${escapeHtml(color)};">
        ${escapeHtml(message)}
      </div>
    </div>
  `;
}

function updateTableInfo(total, page, perPage, currentCount) {
  if (!elInfo) return;

  if (total <= 0 || currentCount <= 0) {
    elInfo.textContent = "Voir 0-0 sur 0 rapports";
    return;
  }

  const from = (page - 1) * perPage + 1;
  const to = Math.min(from + currentCount - 1, total);
  elInfo.textContent = `Voir ${from}-${to} sur ${total} rapports`;
}

function renderTable(items) {
  if (!elTable) return;

  if (!Array.isArray(items) || items.length === 0) {
    showTableMessage("Aucun rapport disponible.");
    updateTableInfo(0, 1, state.perPage, 0);
    return;
  }

  elTable.innerHTML = "";

  items.forEach((item) => {
    const row = document.createElement("div");
    row.className = "trow tbody-row";

    const name = escapeHtml(item.name || "Rapport");
    const repo = escapeHtml(item.repository || "—");
    const typeLabel = escapeHtml(formatType(item.type));
    const typeClass = typeToPill(item.type);
    const date = escapeHtml(item.date || "—");
    const size = escapeHtml(item.size_label || "—");
    const projectId = Number(item.project_id || 0);
    const scanId = Number(item.scan_id || 0);
    const canOpen = Number.isInteger(projectId) && projectId > 0;

    row.innerHTML = `
      <div>
        <div class="report-name">${name}</div>
        <div class="report-sub">Format PDF</div>
      </div>
      <div class="repo">${repo}</div>
      <div><span class="${typeClass}">${typeLabel}</span></div>
      <div class="date"><span class="cal">📅</span><span>${date}</span></div>
      <div class="size">${size}</div>
      <div class="tcenter">
        <button class="pdf report-view-btn" type="button"${canOpen ? "" : " disabled"}>Voir</button>
        <button class="pdf report-pdf-btn" type="button" style="margin-left:6px;"${canOpen ? "" : " disabled"}>PDF</button>
      </div>
    `;

    const viewBtn = row.querySelector(".report-view-btn");
    if (viewBtn) {
      viewBtn.addEventListener("click", () => {
        if (!canOpen) return;
        openReportPreview(projectId, scanId);
      });
    }

    const pdfBtn = row.querySelector(".report-pdf-btn");
    if (pdfBtn) {
      pdfBtn.addEventListener("click", () => {
        if (!canOpen) return;
        downloadReportFile(projectId, scanId, item.name || "rapport-securite");
      });
    }

    elTable.appendChild(row);
  });
}

async function fetchReportResponse(projectId, options = {}) {
  const token = getAuthToken();
  if (!token) {
    if (typeof Auth !== "undefined" && typeof Auth.logout === "function") {
      Auth.logout();
    }
    throw new Error("Session expirée.");
  }

  const response = await fetch(buildReportApiUrl(projectId, options), {
    method: "GET",
    headers: {
      Accept: "text/html,application/pdf,*/*",
      Authorization: "Bearer " + token,
    },
  });

  if (response.status === 401) {
    if (typeof Auth !== "undefined" && typeof Auth.logout === "function") {
      Auth.logout();
    }
    throw new Error("Session expirée.");
  }

  if (!response.ok) {
    throw new Error("Erreur serveur (" + response.status + ")");
  }

  return response;
}

async function openReportPreview(projectId, scanId) {
  const previewTab = window.open("about:blank", "_blank");
  if (!previewTab) {
    alert("Autorise les popups pour ouvrir le rapport.");
    return;
  }

  try {
    const response = await fetchReportResponse(projectId, {
      scanId,
      format: "html",
    });
    const html = await response.text();
    previewTab.document.open();
    previewTab.document.write(html);
    previewTab.document.close();
  } catch (error) {
    previewTab.close();
    alert(error?.message || "Impossible d'ouvrir le rapport.");
  }
}

async function downloadReportFile(projectId, scanId, reportName) {
  try {
    const response = await fetchReportResponse(projectId, {
      scanId,
      format: "pdf",
      download: true,
    });

    const contentType = String(response.headers.get("content-type") || "");
    const isPdf = contentType.includes("application/pdf");
    const ext = isPdf ? "pdf" : "html";
    const fileName = `${safeFilename(reportName)}.${ext}`;

    const blob = await response.blob();
    const blobUrl = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = blobUrl;
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(blobUrl);
  } catch (error) {
    alert(error?.message || "Impossible de telecharger le rapport.");
  }
}

function buildReportsPath(page = 1) {
  const params = new URLSearchParams();
  params.set("page", String(page));
  params.set("per_page", String(state.perPage));

  const name = (elFilterName?.value || "").trim();
  const repo = (elFilterRepo?.value || "").trim();
  const type = normalizeTypeFilter(elFilterType?.value || "");

  if (name) params.set("name", name);
  if (repo) params.set("repo", repo);
  if (type) params.set("type", type);

  return `/api/reports?${params.toString()}`;
}

function getAuthorizationHeader() {
  const token = (typeof getToken === "function")
    ? getToken()
    : localStorage.getItem("ss_token");

  return token ? { Authorization: "Bearer " + token } : {};
}

async function fetchReports(page = 1) {
  const path = buildReportsPath(page);

  if (typeof apiRequest === "function") {
    return apiRequest("GET", path);
  }

  const response = await fetch(path, {
    headers: {
      Accept: "application/json",
      ...getAuthorizationHeader(),
    },
  });

  if (!response.ok) {
    throw new Error("HTTP " + response.status);
  }

  return response.json();
}

async function loadReports(page = 1) {
  showTableMessage("Chargement des rapports...");

  try {
    const data = await fetchReports(page);
    const items = Array.isArray(data?.items) ? data.items : [];

    state.total = Number(data?.total || 0);
    state.page = Number(data?.page || page);
    state.perPage = Number(data?.per_page || state.perPage);

    renderTable(items);
    updateTableInfo(state.total, state.page, state.perPage, items.length);
  } catch (error) {
    showTableMessage("Impossible de charger les rapports.", "#ef4444");
    if (elInfo) {
      elInfo.textContent = "Erreur de chargement";
    }
  }
}

function scheduleFilterRefresh() {
  clearTimeout(state.debounceTimer);
  state.debounceTimer = setTimeout(() => {
    state.page = 1;
    loadReports(1);
  }, FILTER_DEBOUNCE_MS);
}

function bindFilters() {
  if (elFilterName) elFilterName.addEventListener("input", scheduleFilterRefresh);
  if (elFilterRepo) elFilterRepo.addEventListener("input", scheduleFilterRefresh);
  if (elFilterType) {
    elFilterType.addEventListener("change", () => {
      state.page = 1;
      loadReports(1);
    });
  }
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

function updateSelectedCount() {
  if (!elCount) return;
  const selected = checks.filter((c) => c.checked).length;
  elCount.textContent = `${selected} section${selected > 1 ? "s" : ""} selectionne${selected > 1 ? "es" : ""}`;
}

function bindBuilder() {
  checks.forEach((c) => c.addEventListener("change", updateSelectedCount));

  const genBtn = document.getElementById("genBtn");
  if (genBtn) {
    genBtn.addEventListener("click", () => {
      const selected = checks.filter((c) => c.checked).length;
      alert(`Rapport genere avec ${selected} section${selected > 1 ? "s" : ""}.`);
    });
  }
}

async function initPage() {
  if (typeof requireAuth === "function" && !requireAuth()) return;

  bindLogout();
  await hydrateUserInfo();
  bindFilters();
  bindBuilder();
  updateSelectedCount();
  await loadReports(1);
}

initPage();
