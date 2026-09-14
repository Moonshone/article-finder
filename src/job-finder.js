"use strict";

const $ = (selector) => document.querySelector(selector);
const csrf = $('meta[name="csrf-token"]')?.content || "";
const state = { uploading: false, searching: false, profileReady: false, statusTimer: null };
const cvForm = $("#cvForm");
const cvFile = $("#cvFile");
const dropZone = $("#dropZone");
const uploadButton = $("#uploadButton");
const criteriaPanel = $("#criteriaPanel");
const criteriaFields = $("#criteriaFields");
const searchForm = $("#jobSearchForm");
const searchButton = $("#jobSearchButton");

function message(element, text, type = "error") {
  element.textContent = text;
  element.className = `message${text ? ` ${type}` : ""}`;
}

function validateFile(file) {
  if (!file) return "Bitte wähle eine PDF aus.";
  if (file.size > 5 * 1024 * 1024) return "Die PDF darf maximal 5 MB groß sein.";
  if (!file.name.toLowerCase().endsWith(".pdf") || (file.type && file.type !== "application/pdf")) return "Bitte wähle ausschließlich eine PDF aus.";
  return "";
}

function selectFile(file) {
  const error = validateFile(file);
  $("#cvError").textContent = error;
  $("#fileName").textContent = file ? file.name : "Noch keine Datei ausgewählt";
  uploadButton.disabled = Boolean(error) || !file || state.uploading;
}

cvFile.addEventListener("change", () => selectFile(cvFile.files[0]));
["dragenter", "dragover"].forEach((name) => dropZone.addEventListener(name, (event) => { event.preventDefault(); dropZone.classList.add("is-dragging"); }));
["dragleave", "drop"].forEach((name) => dropZone.addEventListener(name, (event) => { event.preventDefault(); dropZone.classList.remove("is-dragging"); }));
dropZone.addEventListener("drop", (event) => {
  const file = event.dataTransfer.files[0];
  if (!file) return;
  const transfer = new DataTransfer(); transfer.items.add(file); cvFile.files = transfer.files; selectFile(file);
});

async function apiRequest(url, options, timeout = 125000) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeout);
  try {
    const response = await fetch(url, { ...options, signal: controller.signal, credentials: "same-origin", headers: { ...(options.headers || {}), "X-CSRF-Token": csrf } });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || data.success !== true) throw new Error(data.error || "Die Anfrage konnte nicht abgeschlossen werden.");
    return data;
  } catch (error) {
    if (error.name === "AbortError") throw new Error("Die Anfrage hat zu lange gedauert. Bitte versuche es erneut.");
    throw error;
  } finally { clearTimeout(timer); }
}

cvForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  if (state.uploading) return;
  const file = cvFile.files[0]; const error = validateFile(file);
  if (error) { $("#cvError").textContent = error; return; }
  state.uploading = true; uploadButton.disabled = true; uploadButton.textContent = "Lebenslauf wird verarbeitet …"; message($("#uploadMessage"), "", "");
  try {
    const body = new FormData(); body.append("lebenslauf", file, file.name);
    await apiRequest("/api/job-finder-upload.php", { method: "POST", body });
    state.profileReady = true; criteriaFields.disabled = false; criteriaPanel.classList.remove("is-locked"); criteriaPanel.removeAttribute("aria-disabled");
    message($("#uploadMessage"), "✓ Lebenslauf erfolgreich verarbeitet", "success");
  } catch (requestError) { state.profileReady = false; message($("#uploadMessage"), requestError.message || "Der Lebenslauf konnte nicht verarbeitet werden."); }
  finally { state.uploading = false; uploadButton.disabled = false; uploadButton.textContent = "Lebenslauf erneut verarbeiten"; }
});

function startStatus() {
  const items = [...document.querySelectorAll("#statusList li")]; let active = 0;
  items.forEach((item) => item.className = ""); items[0].classList.add("active");
  state.statusTimer = setInterval(() => { if (active >= items.length - 1) return; items[active].className = "done"; active += 1; items[active].className = "active"; }, 8000);
}

function text(tag, value, className = "") { const node = document.createElement(tag); node.className = className; node.textContent = String(value ?? "–"); return node; }
function cleanString(value, fallback = "–", max = 300) { return typeof value === "string" && value.trim() ? value.trim().slice(0, max) : fallback; }
function safeList(value) { return Array.isArray(value) ? value.filter((item) => typeof item === "string").slice(0, 10) : []; }
function safeUrl(value) { try { const url = new URL(value); return ["https:", "http:"].includes(url.protocol) ? url.href : null; } catch { return null; } }

function parseOccupations(value) {
  const occupations = [];
  const seen = new Set();
  String(value).split(/[,\r\n]+/).forEach((part) => {
    const occupation = part.trim();
    const key = occupation.toLocaleLowerCase("de-DE");
    if (occupation && !seen.has(key)) { seen.add(key); occupations.push(occupation); }
  });
  return occupations;
}

function renderJobs(jobs) {
  const list = Array.isArray(jobs) ? jobs.slice(0, 100) : [];
  const cards = $("#jobCards"); cards.replaceChildren();
  list.forEach((job) => {
    if (!job || typeof job !== "object") return;
    const score = Math.max(0, Math.min(100, Number(job.match_score ?? job.score) || 0));
    const suppliedCategory = typeof job.kategorie === "string" ? job.kategorie.toUpperCase() : "";
    const category = ["A", "B", "C"].includes(suppliedCategory) ? suppliedCategory : score >= 80 ? "A" : score >= 65 ? "B" : "C";
    const card = document.createElement("article"); card.className = "job-card";
    card.append(text("span", `${category} · ${Math.round(score)} % Match`, `match-badge match-${category.toLowerCase()}`));
    card.append(text("p", cleanString(job.berufsbereich, "Berufsbereich nicht angegeben", 80), "job-source"));
    card.append(text("h3", cleanString(job.stellenbezeichnung, "Stellenangebot", 150)));
    card.append(text("div", cleanString(job.company ?? job.unternehmen, "Unternehmen nicht angegeben", 150), "job-company"));
    card.append(text("p", [job.ort, job.remote, job.beschaeftigungsart].map((v) => cleanString(v, "", 80)).filter(Boolean).join(" · "), "job-meta"));
    const details = document.createElement("div"); details.className = "job-details";
    [["Warum es passt", safeList(job.warum_passend)], ["Mögliche Lücken", safeList(job.luecken)]].forEach(([heading, values]) => {
      const block = document.createElement("div"); block.append(text("h4", heading)); const ul = document.createElement("ul");
      (values.length ? values : ["Keine Angaben"]).forEach((value) => ul.append(text("li", cleanString(value, "–", 300)))); block.append(ul); details.append(block);
    }); card.append(details);
    const sourceBits = [job.quelle, job.veroeffentlicht].map((v) => cleanString(v, "", 80)).filter(Boolean);
    sourceBits.push(job.verifiziert === true ? "✓ verifiziert" : "nicht verifiziert");
    card.append(text("p", sourceBits.join(" · "), "job-source"));
    const url = safeUrl(job.url); if (url) { const link = text("a", "STELLENANZEIGE ÖFFNEN", "job-link"); link.href = url; link.target = "_blank"; link.rel = "noopener noreferrer"; card.append(link); }
    cards.append(card);
  });
  const count = cards.childElementCount; $("#jobCount").textContent = `${count} ${count === 1 ? "Stelle" : "Stellen"}`;
  $("#jobResults").hidden = false;
  if (!count) cards.append(text("p", "Für diese Kriterien wurden keine passenden Stellen gefunden.", "job-company"));
}

searchForm.addEventListener("submit", async (event) => {
  event.preventDefault(); if (state.searching || !state.profileReady) return;
  const ort = $("#ort").value.trim();
  const jobs = parseOccupations($("#jobs").value);
  let jobsError = "";
  if (!jobs.length) jobsError = "Bitte gib mindestens einen Job oder eine Tätigkeit ein.";
  else if (jobs.length > 8) jobsError = "Bitte gib maximal 8 Jobs oder Tätigkeiten ein.";
  else if (jobs.some((job) => [...job].length > 80)) jobsError = "Jeder Eintrag darf maximal 80 Zeichen lang sein.";
  $("#jobsError").textContent = jobsError; $("#ortError").textContent = ort ? "" : "Bitte gib einen Ort ein.";
  if (jobsError || !ort) { (jobsError ? $("#jobs") : $("#ort")).focus(); return; }
  const data = new FormData(searchForm);
  const payload = { jobs, ort, radius: data.get("radius") === "egal" ? "egal" : Number(data.get("radius")), remote: data.get("remote"), beschaeftigungsart: data.get("beschaeftigungsart"), webseiten: data.getAll("webseiten"), ausschluesse: String(data.get("ausschluesse") || "").split(",").map((v) => v.trim()).filter(Boolean) };
  state.searching = true; searchButton.disabled = true; searchButton.textContent = "Suche läuft …"; message($("#searchError"), "", "");
  $("#emptyState").hidden = true; $("#jobResults").hidden = true; $("#statusPanel").hidden = false; startStatus();
  try { const result = await apiRequest("/api/job-finder-search.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload) }, 190000); renderJobs(result.ergebnisse); }
  catch (requestError) { message($("#searchError"), requestError.message || "Die Job-Suche konnte momentan nicht abgeschlossen werden."); $("#emptyState").hidden = false; }
  finally { clearInterval(state.statusTimer); document.querySelectorAll("#statusList li").forEach((item) => item.className = "done"); setTimeout(() => { $("#statusPanel").hidden = true; }, 500); state.searching = false; searchButton.disabled = false; searchButton.textContent = "Jobs suchen"; }
});
