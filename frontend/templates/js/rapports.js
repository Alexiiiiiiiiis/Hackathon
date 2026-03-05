const DEFAULT_PER_PAGE = 10;
const FILTER_DEBOUNCE_MS = 300;

const elTable = document.getElementById("reportsTable");
const elInfo = document.getElementById("tableInfo");
const elFilterName = document.getElementById("filterName");
const elFilterRepo = document.getElementById("filterRepo");
const elFilterType = document.getElementById("filterType");
const elPrevBtn = document.getElementById("prevBtn");
const elNextBtn = document.getElementById("nextBtn");
const elPager = document.querySelector(".pager");

const checks = Array.from(document.querySelectorAll(".section-check"));
const elCount = document.getElementById("sectionsCount");

const state = {
  page: 1,
  perPage: DEFAULT_PER_PAGE,
  total: 0,
  totalPages: 1,
  debounceTimer: null,
  lastRequestId: 0,
  loading: false,
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
  const safeProjectId = Number(projectId);
  if (!Number.isInteger(safeProjectId) || safeProjectId <= 0) {
    throw new Error("Identifiant de projet invalide.");
  }

  const params = new URLSearchParams();

  if (options.scanId) params.set("scan_id", String(options.scanId));
  if (options.download) params.set("download", "1");
  if (options.format) params.set("format", String(options.format));

  const query = params.toString();
  const suffix = query ? `?${query}` : "";
  return `${getApiBaseUrl()}/api/report/${safeProjectId}${suffix}`;
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
  const file = String(value || "rapport-securite")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-zA-Z0-9._-]/g, "-")
    .replace(/-+/g, "-")
    .replace(/^-|-$/g, "")
    .toLowerCase();
  return (file || "rapport-securite").slice(0, 80);
}

function showTableMessage(message, color = "#64748b") {
  if (!elTable) return;

  elTable.innerHTML = "";
  const row = document.createElement("div");
  row.className = "trow tbody-row";

  const cell = document.createElement("div");
  cell.style.gridColumn = "1 / -1";
  cell.style.textAlign = "center";
  cell.style.padding = "12px 0";
  cell.style.color = color;
  cell.textContent = String(message || "");

  row.appendChild(cell);
  elTable.appendChild(row);
}

function updateTableInfo(total, page, perPage, currentCount) {
  if (!elInfo) return;

  const safeTotal = Number(total) || 0;
  const safePage = Math.max(1, Number(page) || 1);
  const safePerPage = Math.max(1, Number(perPage) || DEFAULT_PER_PAGE);
  const safeCurrentCount = Math.max(0, Number(currentCount) || 0);

  if (safeTotal <= 0 || safeCurrentCount <= 0) {
    elInfo.textContent = "Voir 0-0 sur 0 rapports";
    return;
  }

  const from = (safePage - 1) * safePerPage + 1;
  const to = Math.min(from + safeCurrentCount - 1, safeTotal);
  elInfo.textContent = `Voir ${from}-${to} sur ${safeTotal} rapports`;
}

function setFiltersDisabled(disabled) {
  if (elFilterName) elFilterName.disabled = disabled;
  if (elFilterRepo) elFilterRepo.disabled = disabled;
  if (elFilterType) elFilterType.disabled = disabled;
}

function setPaginationDisabled(disabled) {
  if (elPrevBtn) elPrevBtn.disabled = disabled;
  if (elNextBtn) elNextBtn.disabled = disabled;
  document.querySelectorAll(".pnum").forEach((btn) => {
    btn.disabled = disabled;
  });
}

function toUserMessage(error, fallback) {
  const raw = String(error?.message || "").trim();
  if (!raw) return fallback;
  if (/session|auth|401|non authent/i.test(raw)) return "Session expiree, reconnecte-toi.";
  if (/network|failed to fetch|load failed/i.test(raw)) return "Serveur inaccessible. Verifie que le backend tourne.";
  return fallback;
}

function setButtonBusy(button, busy) {
  if (!button) return () => {};
  const originalText = button.textContent;
  button.disabled = Boolean(busy);
  if (busy) button.textContent = "...";
  return () => {
    button.disabled = false;
    button.textContent = originalText;
  };
}

function clampPage(page) {
  const safePage = Number(page) || 1;
  if (safePage < 1) return 1;
  if (safePage > state.totalPages) return state.totalPages;
  return safePage;
}

function getPagesWindow(current, totalPages) {
  if (totalPages <= 5) {
    return Array.from({ length: totalPages }, (_, i) => i + 1);
  }

  if (current <= 3) return [1, 2, 3, 4, 5];
  if (current >= totalPages - 2) {
    return [totalPages - 4, totalPages - 3, totalPages - 2, totalPages - 1, totalPages];
  }

  return [current - 2, current - 1, current, current + 1, current + 2];
}

function renderPagination() {
  if (!elPager || !elPrevBtn || !elNextBtn) return;

  document.querySelectorAll(".pnum").forEach((btn) => btn.remove());

  if (state.total <= 0) {
    elPrevBtn.disabled = true;
    elNextBtn.disabled = true;
    return;
  }

  const pages = getPagesWindow(state.page, state.totalPages);
  pages.forEach((page) => {
    const btn = document.createElement("button");
    btn.className = `pnum${page === state.page ? " active" : ""}`;
    btn.type = "button";
    btn.dataset.page = String(page);
    btn.textContent = String(page);
    btn.disabled = state.loading;
    btn.addEventListener("click", () => goToPage(page));
    elPager.insertBefore(btn, elNextBtn);
  });

  elPrevBtn.disabled = state.loading || state.page <= 1;
  elNextBtn.disabled = state.loading || state.page >= state.totalPages;
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
    const repo = escapeHtml(item.repository || "-");
    const typeLabel = escapeHtml(formatType(item.type));
    const typeClass = typeToPill(item.type);
    const date = escapeHtml(item.date || "-");
    const size = escapeHtml(item.size_label || "-");
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
        openReportPreview(projectId, scanId, viewBtn);
      });
    }

    const pdfBtn = row.querySelector(".report-pdf-btn");
    if (pdfBtn) {
      pdfBtn.addEventListener("click", () => {
        if (!canOpen) return;
        downloadReportFile(projectId, scanId, item.name || "rapport-securite", pdfBtn);
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

async function openReportPreview(projectId, scanId, actionButton) {
  const releaseButton = setButtonBusy(actionButton, true);
  const previewTab = window.open("about:blank", "_blank");
  if (!previewTab) {
    releaseButton();
    alert("Autorise les popups pour ouvrir le rapport.");
    return;
  }
  try {
    previewTab.document.title = "Chargement du rapport...";
  } catch (_) {
    // ignore
  }

  try {
    const response = await fetchReportResponse(projectId, {
      scanId,
      format: "html",
    });
    const html = await response.text();

    const blob = new Blob([html], { type: "text/html;charset=utf-8" });
    const blobUrl = window.URL.createObjectURL(blob);
    previewTab.location.replace(blobUrl);
    setTimeout(() => window.URL.revokeObjectURL(blobUrl), 120000);
  } catch (error) {
    const message = toUserMessage(error, "Impossible d'ouvrir le rapport.");
    const fallbackUrl = `data:text/plain;charset=utf-8,${encodeURIComponent(message)}`;
    try {
      previewTab.location.replace(fallbackUrl);
    } catch (_) {
      // ignore
    }
    alert(message);
  } finally {
    releaseButton();
  }
}

async function downloadReportFile(projectId, scanId, reportName, actionButton) {
  const releaseButton = setButtonBusy(actionButton, true);
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
    alert(toUserMessage(error, "Impossible de telecharger le rapport."));
  } finally {
    releaseButton();
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

  if (response.status === 401) {
    if (typeof Auth !== "undefined" && typeof Auth.logout === "function") {
      Auth.logout();
    }
    throw new Error("Session expiree.");
  }

  if (!response.ok) {
    throw new Error("Erreur serveur (" + response.status + ")");
  }

  const text = await response.text();
  if (!text) return { total: 0, page, per_page: state.perPage, items: [] };

  try {
    return JSON.parse(text);
  } catch (_) {
    throw new Error("Reponse API invalide.");
  }
}

async function loadReports(page = 1) {
  const requestId = ++state.lastRequestId;
  state.loading = true;
  setFiltersDisabled(true);
  setPaginationDisabled(true);
  showTableMessage("Chargement des rapports...");
  if (elInfo) elInfo.textContent = "Chargement...";

  try {
    const data = await fetchReports(clampPage(page));
    if (requestId !== state.lastRequestId) return;
    const items = Array.isArray(data?.items) ? data.items : [];

    state.total = Number(data?.total || 0);
    state.page = Math.max(1, Number(data?.page || page));
    state.perPage = Math.max(1, Number(data?.per_page || state.perPage));
    state.totalPages = Math.max(1, Math.ceil(state.total / state.perPage));

    if (state.page > state.totalPages) {
      await loadReports(state.totalPages);
      return;
    }

    renderTable(items);
    updateTableInfo(state.total, state.page, state.perPage, items.length);
    renderPagination();
  } catch (error) {
    if (requestId !== state.lastRequestId) return;
    state.total = 0;
    state.totalPages = 1;
    state.page = 1;
    showTableMessage(toUserMessage(error, "Impossible de charger les rapports."), "#ef4444");
    if (elInfo) {
      elInfo.textContent = "Erreur de chargement";
    }
    if (elPrevBtn) elPrevBtn.disabled = true;
    if (elNextBtn) elNextBtn.disabled = true;
    document.querySelectorAll(".pnum").forEach((btn) => btn.remove());
  } finally {
    if (requestId === state.lastRequestId) {
      state.loading = false;
      setFiltersDisabled(false);
      renderPagination();
    }
  }
}

async function goToPage(page) {
  if (state.loading) return;
  const target = clampPage(page);
  if (target === state.page) return;
  await loadReports(target);
}

function scheduleFilterRefresh() {
  if (state.loading) return;
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

function bindPagination() {
  if (elPrevBtn) {
    elPrevBtn.addEventListener("click", () => {
      goToPage(state.page - 1);
    });
  }

  if (elNextBtn) {
    elNextBtn.addEventListener("click", () => {
      goToPage(state.page + 1);
    });
  }

  document.querySelectorAll(".pnum").forEach((btn) => {
    btn.addEventListener("click", () => {
      const page = Number(btn.dataset.page || btn.textContent || "1");
      goToPage(page);
    });
  });
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
  bindPagination();
  bindBuilder();
  updateSelectedCount();
  await loadReports(1);
}

initPage();
