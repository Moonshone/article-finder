"use strict";

// n8n-Anbindung: Diese beiden Platzhalter später durch produktive Webhook-URLs ersetzen.
const SEARCH_WEBHOOK_URL = "DEINE_N8N_SEARCH_WEBHOOK_URL";
const EMAIL_WEBHOOK_URL = "DEINE_N8N_EMAIL_WEBHOOK_URL";

const RADIUS_MIN = 50;
const RADIUS_MAX = 10000;
const RADIUS_STEP = 50;
let radius = 1000;
let currentResults = [];
let lastSearchData = null;

// Ausschließlich lokale Demo-Daten für den Betrieb ohne verbundenen n8n-Workflow.
const demoResults = [
  { store: "REWE", product: "Coca-Cola Zero Sugar 1,5 l", quantity: "1 Flasche", price: "1,49 €", distance: "850 m", address: "Musterstraße 10, 10115 Berlin" },
  { store: "EDEKA", product: "Coca-Cola Zero Sugar 1,5 l", quantity: "6 Flaschen", price: "1,59 €", distance: "1,3 km", address: "Beispielstraße 22, 10115 Berlin" },
  { store: "Kaufland", product: "Coca-Cola Zero Sugar 1,5 l", quantity: "1 Flasche", price: "1,39 €", distance: "2,1 km", address: "Testweg 5, 10115 Berlin" }
];

const elements = {
  form: document.querySelector("#searchForm"), article: document.querySelector("#article"),
  postalCode: document.querySelector("#postalCode"), email: document.querySelector("#email"),
  radiusDisplay: document.querySelector("#radiusDisplay"), decreaseRadius: document.querySelector("#decreaseRadius"),
  increaseRadius: document.querySelector("#increaseRadius"), searchButton: document.querySelector("#searchButton"),
  searchMessage: document.querySelector("#searchMessage"), resultsSection: document.querySelector("#resultsSection"),
  resultsList: document.querySelector("#resultsList"), resultsCount: document.querySelector("#resultsCount"),
  emailButton: document.querySelector("#emailButton"),
  emailMessage: document.querySelector("#emailMessage")
};

function formatRadius(meters) {
  if (meters < 1000) return `${meters} m`;
  return `${new Intl.NumberFormat("de-DE", { maximumFractionDigits: 2 }).format(meters / 1000)} km`;
}

function updateRadiusDisplay() {
  elements.radiusDisplay.value = formatRadius(radius);
  elements.radiusDisplay.textContent = formatRadius(radius);
  elements.decreaseRadius.disabled = radius === RADIUS_MIN;
  elements.increaseRadius.disabled = radius === RADIUS_MAX;
}

function increaseRadius() { radius = Math.min(radius + RADIUS_STEP, RADIUS_MAX); updateRadiusDisplay(); }
function decreaseRadius() { radius = Math.max(radius - RADIUS_STEP, RADIUS_MIN); updateRadiusDisplay(); }

function setFieldError(field, message) {
  field.setAttribute("aria-invalid", String(Boolean(message)));
  document.querySelector(`#${field.id}Error`).textContent = message;
}

function isValidEmail(value) { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/i.test(value); }

function validateForm() {
  const article = elements.article.value.trim();
  const postalCode = elements.postalCode.value.trim();
  setFieldError(elements.article, article ? "" : "Bitte gib einen Artikel ein.");
  setFieldError(elements.postalCode, /^\d{5}$/.test(postalCode) ? "" : "Bitte gib eine gültige deutsche Postleitzahl mit 5 Ziffern ein.");
  const firstInvalid = elements.form.querySelector('[aria-invalid="true"]');
  if (firstInvalid) firstInvalid.focus();
  return !firstInvalid;
}

function showMessage(element, text = "", type = "") {
  element.textContent = text;
  element.className = `message${type ? ` ${type}` : ""}`;
}

function setLoadingState(isLoading) {
  elements.searchButton.disabled = isLoading;
  elements.searchButton.classList.toggle("is-loading", isLoading);
  elements.searchButton.setAttribute("aria-busy", String(isLoading));
}

function createTextElement(tag, className, text) {
  const element = document.createElement(tag);
  element.className = className;
  element.textContent = String(text ?? "–");
  return element;
}

function renderResults(results) {
  currentResults = Array.isArray(results) ? results : [];
  elements.resultsList.replaceChildren();
  elements.resultsSection.hidden = currentResults.length === 0;
  if (!currentResults.length) {
    showMessage(elements.searchMessage, "Leider wurden in diesem Umkreis keine passenden Angebote gefunden. Vergrößere den Radius oder ändere den Artikelnamen.", "error");
    return;
  }
  elements.resultsCount.textContent = `${currentResults.length} Angebote`;

  currentResults.forEach((offer) => {
    const row = document.createElement("tr");
    const productCell = createTextElement("td", "result-product", offer.product);
    productCell.append(createTextElement("span", "result-store", offer.store));
    row.append(productCell, createTextElement("td", "", offer.quantity), createTextElement("td", "result-price", offer.price), createTextElement("td", "", offer.distance), createTextElement("td", "", offer.address));
    elements.resultsList.append(row);
  });

  updateEmailState();
  elements.resultsSection.scrollIntoView({ behavior: "smooth", block: "start" });
}

function isPlaceholder(url) { return url.startsWith("DEINE_N8N_"); }
function demoDelay() { return new Promise((resolve) => setTimeout(resolve, 850)); }

async function searchArticle(event) {
  event.preventDefault();
  showMessage(elements.searchMessage);
  showMessage(elements.emailMessage);
  if (!validateForm()) return;
  lastSearchData = { article: elements.article.value.trim(), postalCode: elements.postalCode.value.trim(), radius };
  setLoadingState(true);
  try {
    let results;
    if (isPlaceholder(SEARCH_WEBHOOK_URL)) {
      await demoDelay(); results = demoResults;
    } else {
      const response = await fetch(SEARCH_WEBHOOK_URL, { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(lastSearchData) });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const data = await response.json();
      if (data.success !== true || !Array.isArray(data.results)) throw new Error("Ungültige Webhook-Antwort");
      results = data.results;
    }
    renderResults(results);
  } catch (error) {
    console.error("Suche fehlgeschlagen:", error);
    showMessage(elements.searchMessage, "Die Suche konnte nicht durchgeführt werden. Bitte versuche es erneut.", "error");
  } finally { setLoadingState(false); }
}

async function sendResultsByEmail() {
  const email = elements.email.value.trim();
  setFieldError(elements.email, isValidEmail(email) ? "" : "Bitte gib eine gültige E-Mail-Adresse ein.");
  if (!currentResults.length || !lastSearchData || !isValidEmail(email)) { elements.email.focus(); return; }
  elements.emailButton.disabled = true; elements.emailButton.classList.add("is-loading");
  showMessage(elements.emailMessage);
  const payload = { ...lastSearchData, email: elements.email.value.trim(), results: currentResults };
  try {
    if (isPlaceholder(EMAIL_WEBHOOK_URL)) await demoDelay();
    else {
      const response = await fetch(EMAIL_WEBHOOK_URL, { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload) });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
    }
    showMessage(elements.emailMessage, "Die Ergebnisse wurden erfolgreich an deine E-Mail-Adresse gesendet.", "success");
  } catch (error) {
    console.error("E-Mail-Versand fehlgeschlagen:", error);
    showMessage(elements.emailMessage, "Die E-Mail konnte nicht gesendet werden.", "error");
  } finally { elements.emailButton.disabled = false; elements.emailButton.classList.remove("is-loading"); }
}

function updateEmailState() {
  const email = elements.email.value.trim();
  elements.emailButton.disabled = !currentResults.length || !isValidEmail(email);
  if (!email || isValidEmail(email)) setFieldError(elements.email, "");
}

// Die Formularlogik nur auf der Seite initialisieren, die das Suchformular enthält.
if (elements.form) {
  elements.decreaseRadius.addEventListener("click", decreaseRadius);
  elements.increaseRadius.addEventListener("click", increaseRadius);
  elements.form.addEventListener("submit", searchArticle);
  elements.emailButton.addEventListener("click", sendResultsByEmail);
  elements.postalCode.addEventListener("input", () => { elements.postalCode.value = elements.postalCode.value.replace(/\D/g, "").slice(0, 5); });
  [elements.article, elements.postalCode].forEach((field) => field.addEventListener("input", () => setFieldError(field, "")));
  elements.email.addEventListener("input", updateEmailState);
  updateRadiusDisplay();
}
