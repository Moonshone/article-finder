"use strict";

const $ = (selector) => document.querySelector(selector);
const csrf = $('meta[name="csrf-token"]')?.content || "";
const state = { uploading: false, searching: false, profileReady: false, profileId: "", statusTimer: null };
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
    const result = await apiRequest("/api/job-finder-upload.php", { method: "POST", body });
    if (typeof result.profile_id !== "string" || !result.profile_id) throw new Error("Der Lebenslauf konnte nicht verarbeitet werden.");
    state.profileId = result.profile_id; state.profileReady = true; criteriaFields.disabled = false; criteriaPanel.classList.remove("is-locked"); criteriaPanel.removeAttribute("aria-disabled");
    message($("#uploadMessage"), "✓ Lebenslauf erfolgreich verarbeitet", "success");
  } catch (requestError) { state.profileReady = false; message($("#uploadMessage"), requestError.message || "Der Lebenslauf konnte nicht verarbeitet werden."); }
  finally { state.uploading = false; uploadButton.disabled = false; uploadButton.textContent = "Lebenslauf erneut verarbeiten"; }
});

function startStatus() {
  const items = [...document.querySelectorAll("#statusList li")]; let active = 0;
  items.forEach((item) => item.className = ""); items[0].classList.add("active");
  state.statusTimer = setInterval(() => { if (active >= items.length - 1) return; items[active].className = "done"; active += 1; items[active].className = "active"; }, 8000);
}

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

function renderOutput(output) {
  const cards = $("#jobCards"); cards.replaceChildren();
  const result = document.createElement("div"); result.className = "job-result-output";
  result.textContent = output; cards.append(result);
  $("#jobCount").textContent = "";
  $("#jobResults").hidden = false;
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
  const payload = { profile_id: state.profileId, jobs, ort, radius: data.get("radius") === "egal" ? "egal" : Number(data.get("radius")), remote: data.get("remote"), beschaeftigungsart: data.get("beschaeftigungsart"), webseiten: data.getAll("webseiten"), ausschluesse: String(data.get("ausschluesse") || "") };
  state.searching = true; searchButton.disabled = true; searchButton.textContent = "Suche läuft …"; message($("#searchError"), "", "");
  $("#emptyState").hidden = true; $("#jobResults").hidden = true; $("#statusPanel").hidden = false; startStatus();
  try { const result = await apiRequest("/api/job-finder-search.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload) }, 190000); renderOutput(result.output); }
  catch (requestError) { message($("#searchError"), requestError.message || "Die Job-Suche konnte momentan nicht abgeschlossen werden."); $("#emptyState").hidden = false; }
  finally { clearInterval(state.statusTimer); document.querySelectorAll("#statusList li").forEach((item) => item.className = "done"); setTimeout(() => { $("#statusPanel").hidden = true; }, 500); state.searching = false; searchButton.disabled = false; searchButton.textContent = "Jobs suchen"; }
});
