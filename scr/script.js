"use strict";

// ======================================================
// n8n Webhooks
// ======================================================

const SEARCH_WEBHOOK_URL =
  "https://appwbs.app.n8n.cloud/webhook/article-finder";

const STATUS_WEBHOOK_URL =
  "https://appwbs.app.n8n.cloud/webhook/article-finder-status";

const EMAIL_WEBHOOK_URL =
  "https://appwbs.app.n8n.cloud/webhook/article-finder-email";


// ======================================================
// Polling
// ======================================================

// Alle 3 Sekunden nachfragen, ob n8n fertig ist.
const POLL_INTERVAL_MS = 3000;

// Nach 10 Minuten abbrechen.
const POLL_TIMEOUT_MS = 10 * 60 * 1000;


// ======================================================
// Radius
// ======================================================

const RADIUS_MIN = 50;
const RADIUS_MAX = 10000;
const RADIUS_STEP = 50;

// UI speichert Radius in Metern.
let radius = 1000;


// ======================================================
// Aktueller Zustand
// ======================================================

let currentResults = [];
let lastSearchData = null;

// Verhindert, dass ein alter Suchlauf einen neueren überschreibt.
let currentSearchId = 0;


// ======================================================
// HTML-Elemente
// ======================================================

const elements = {
  form: document.querySelector("#searchForm"),

  article: document.querySelector("#article"),

  postalCode: document.querySelector("#postalCode"),

  email: document.querySelector("#email"),

  radiusDisplay: document.querySelector("#radiusDisplay"),

  decreaseRadius: document.querySelector("#decreaseRadius"),

  increaseRadius: document.querySelector("#increaseRadius"),

  searchButton: document.querySelector("#searchButton"),

  searchMessage: document.querySelector("#searchMessage"),

  resultsSection: document.querySelector("#resultsSection"),

  resultsList: document.querySelector("#resultsList"),

  resultsCount: document.querySelector("#resultsCount"),

  emailButton: document.querySelector("#emailButton"),

  emailMessage: document.querySelector("#emailMessage")
};


// ======================================================
// Radius formatieren
// ======================================================

function formatRadius(meters) {

  if (meters < 1000) {
    return `${meters} m`;
  }

  return `${new Intl.NumberFormat("de-DE", {
    maximumFractionDigits: 2
  }).format(meters / 1000)} km`;
}


function updateRadiusDisplay() {

  const formattedRadius = formatRadius(radius);

  if ("value" in elements.radiusDisplay) {
    elements.radiusDisplay.value = formattedRadius;
  }

  elements.radiusDisplay.textContent = formattedRadius;

  elements.decreaseRadius.disabled =
    radius === RADIUS_MIN;

  elements.increaseRadius.disabled =
    radius === RADIUS_MAX;
}


function increaseRadius() {

  radius = Math.min(
    radius + RADIUS_STEP,
    RADIUS_MAX
  );

  updateRadiusDisplay();
}


function decreaseRadius() {

  radius = Math.max(
    radius - RADIUS_STEP,
    RADIUS_MIN
  );

  updateRadiusDisplay();
}


// ======================================================
// Formularvalidierung
// ======================================================

function setFieldError(field, message) {

  if (!field) {
    return;
  }

  field.setAttribute(
    "aria-invalid",
    String(Boolean(message))
  );

  const errorElement =
    document.querySelector(`#${field.id}Error`);

  if (errorElement) {
    errorElement.textContent = message;
  }
}


function isValidEmail(value) {

  return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/i.test(value);
}


function validateForm() {

  const article =
    elements.article.value.trim();

  const postalCode =
    elements.postalCode.value.trim();


  setFieldError(
    elements.article,
    article
      ? ""
      : "Bitte gib einen Artikel ein."
  );


  setFieldError(
    elements.postalCode,
    /^\d{5}$/.test(postalCode)
      ? ""
      : "Bitte gib eine gültige deutsche Postleitzahl mit 5 Ziffern ein."
  );


  const firstInvalid =
    elements.form.querySelector(
      '[aria-invalid="true"]'
    );


  if (firstInvalid) {
    firstInvalid.focus();
  }


  return !firstInvalid;
}


// ======================================================
// Meldungen
// ======================================================

function showMessage(
  element,
  text = "",
  type = ""
) {

  if (!element) {
    return;
  }

  element.textContent = text;

  element.className =
    `message${type ? ` ${type}` : ""}`;
}


// ======================================================
// Ladezustand
// ======================================================

function setLoadingState(isLoading) {

  elements.searchButton.disabled =
    isLoading;

  elements.searchButton.classList.toggle(
    "is-loading",
    isLoading
  );

  elements.searchButton.setAttribute(
    "aria-busy",
    String(isLoading)
  );
}


// ======================================================
// Kleine Pause für Polling
// ======================================================

function wait(milliseconds) {

  return new Promise(
    (resolve) =>
      setTimeout(resolve, milliseconds)
  );
}


// ======================================================
// Hilfsfunktion für Tabellenzellen
// ======================================================

function createTextElement(
  tag,
  className,
  text
) {

  const element =
    document.createElement(tag);

  element.className =
    className;

  element.textContent =
    String(text ?? "–");

  return element;
}


// ======================================================
// Preis formatieren
// ======================================================

function formatPrice(
  value,
  currency = "EUR"
) {

  const number =
    Number(value);


  if (!Number.isFinite(number)) {
    return "–";
  }


  const formatted =
    new Intl.NumberFormat(
      "de-DE",
      {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      }
    ).format(number);


  if (
    !currency ||
    String(currency).toUpperCase() === "EUR"
  ) {
    return `${formatted} €`;
  }


  return `${formatted} ${currency}`;
}


// ======================================================
// Entfernung formatieren
// ======================================================

function formatDistance(meters) {

  const number =
    Number(meters);


  if (!Number.isFinite(number)) {
    return "–";
  }


  if (number < 1000) {
    return `${Math.round(number)} m`;
  }


  return `${new Intl.NumberFormat(
    "de-DE",
    {
      maximumFractionDigits: 2
    }
  ).format(number / 1000)} km`;
}


// ======================================================
// Menge formatieren
// ======================================================

function formatQuantity(offer) {

  if (
    offer.menge === null ||
    offer.menge === undefined
  ) {
    return "–";
  }


  return `${offer.menge}${
    offer.einheit
      ? ` ${offer.einheit}`
      : ""
  }`;
}


// ======================================================
// Produktname formatieren
// ======================================================

function formatProduct(offer) {

  const produkt =
    String(
      offer.produkt || ""
    ).trim();

  const variante =
    String(
      offer.variante || ""
    ).trim();


  if (
    !produkt &&
    !variante
  ) {
    return "–";
  }


  if (!variante) {
    return produkt;
  }


  if (
    produkt
      .toLowerCase()
      .includes(
        variante.toLowerCase()
      )
  ) {
    return produkt;
  }


  return `${produkt} ${variante}`.trim();
}


// ======================================================
// Ergebnisse anzeigen
// ======================================================

function renderResults(results) {

  currentResults =
    Array.isArray(results)
      ? results
      : [];


  elements.resultsList.replaceChildren();


  elements.resultsSection.hidden =
    currentResults.length === 0;


  if (!currentResults.length) {

    showMessage(
      elements.searchMessage,
      "Leider wurden in diesem Umkreis keine passenden Angebote gefunden. Vergrößere den Radius oder ändere den Artikelnamen.",
      "error"
    );

    updateEmailState();

    return;
  }


  showMessage(
    elements.searchMessage,
    `${currentResults.length} passende Angebote gefunden.`,
    "success"
  );


  elements.resultsCount.textContent =
    `${currentResults.length} Angebote`;


  currentResults.forEach(
    (offer) => {

      const row =
        document.createElement("tr");


      const productCell =
        createTextElement(
          "td",
          "result-product",
          formatProduct(offer)
        );


      productCell.append(
        createTextElement(
          "span",
          "result-store",
          offer.supermarkt ||
          offer.haendler ||
          "–"
        )
      );


      row.append(

        productCell,

        createTextElement(
          "td",
          "",
          formatQuantity(offer)
        ),

        createTextElement(
          "td",
          "result-price",
          formatPrice(
            offer.preis,
            offer.waehrung
          )
        ),

        createTextElement(
          "td",
          "",
          formatDistance(
            offer.entfernung_m
          )
        ),

        createTextElement(
          "td",
          "",
          offer.adresse || "–"
        )
      );


      elements.resultsList.append(row);
    }
  );


  updateEmailState();


  elements.resultsSection.scrollIntoView({
    behavior: "smooth",
    block: "start"
  });
}


// ======================================================
// JSON-Antwort lesen
// ======================================================

async function readJsonResponse(response) {

  const text =
    await response.text();


  if (!response.ok) {

    throw new Error(
      `HTTP ${response.status}: ${
        text ||
        response.statusText
      }`
    );
  }


  if (!text) {
    return {};
  }


  try {

    return JSON.parse(text);

  } catch {

    throw new Error(
      "Die n8n-Antwort ist kein gültiges JSON."
    );
  }
}


// ======================================================
// Payload normalisieren
// ======================================================

function normalizePayload(data) {

  if (
    Array.isArray(data) &&
    data.length > 0
  ) {
    return data[0];
  }

  return data;
}


// ======================================================
// Suchauftrag an n8n senden
// ======================================================

async function startSearchJob(searchData) {

  const response =
    await fetch(
      SEARCH_WEBHOOK_URL,
      {
        method: "POST",

        headers: {
          "Content-Type":
            "application/json"
        },

        body:
          JSON.stringify(
            searchData
          )
      }
    );


  const data =
    normalizePayload(
      await readJsonResponse(response)
    );


  if (
    !data ||
    !data.job_id
  ) {

    throw new Error(
      "n8n hat keine job_id zurückgegeben."
    );
  }


  return String(data.job_id);
}


// ======================================================
// Status eines Jobs von n8n laden
// ======================================================

async function getJobStatus(jobId) {

  const url =
    `${STATUS_WEBHOOK_URL}?job_id=${encodeURIComponent(jobId)}`;


  const response =
    await fetch(
      url,
      {
        method: "GET",

        headers: {
          "Accept":
            "application/json"
        },

        cache: "no-store"
      }
    );


  const data =
    normalizePayload(
      await readJsonResponse(response)
    );


  if (!data) {

    throw new Error(
      "Keine Status-Antwort von n8n."
    );
  }


  return data;
}


// ======================================================
// Polling:
// auf fertiges n8n-Ergebnis warten
// ======================================================

async function waitForSearchResult(
  jobId,
  searchId
) {

  const startedAt =
    Date.now();


  while (true) {

    // Eine neue Suche wurde inzwischen gestartet.
    if (
      searchId !== currentSearchId
    ) {
      throw new Error(
        "SEARCH_REPLACED"
      );
    }


    // Sicherheits-Timeout
    if (
      Date.now() - startedAt >
      POLL_TIMEOUT_MS
    ) {

      throw new Error(
        "POLL_TIMEOUT"
      );
    }


    const data =
      await getJobStatus(jobId);


    // Job existiert nicht
    if (
      data.status === "not_found"
    ) {

      throw new Error(
        "Der Suchauftrag wurde in n8n nicht gefunden."
      );
    }


    // Workflow ist fertig
    if (
      data.status === "done"
    ) {

      return Array.isArray(data.results)
        ? data.results
        : [];
    }


    // Optional für zukünftige Fehlerstatus
    if (
      data.status === "error" ||
      data.status === "failed"
    ) {

      throw new Error(
        "Der n8n-Workflow wurde mit einem Fehler beendet."
      );
    }


    // Workflow läuft noch
    showMessage(
      elements.searchMessage,
      "Angebote werden gesucht … Bitte einen Moment warten.",
      ""
    );


    await wait(
      POLL_INTERVAL_MS
    );
  }
}


// ======================================================
// Suche starten
// ======================================================

async function searchArticle(event) {

  event.preventDefault();


  showMessage(
    elements.searchMessage
  );

  showMessage(
    elements.emailMessage
  );


  if (!validateForm()) {
    return;
  }


  // Jede Suche bekommt intern eine eigene ID.
  currentSearchId += 1;

  const searchId =
    currentSearchId;


  currentResults = [];

  elements.resultsSection.hidden =
    true;

  updateEmailState();


  /*
    UI:
    Radius wird in Metern gespeichert.

    n8n:
    erwartet Kilometer.

    Beispiel:
    3750 m -> 3.75
  */

  lastSearchData = {

    artikel:
      elements.article
        .value
        .trim(),

    plz:
      elements.postalCode
        .value
        .trim(),

    radius:
      radius / 1000
  };


  setLoadingState(true);


  showMessage(
    elements.searchMessage,
    "Suchauftrag wird gestartet …",
    ""
  );


  try {

    // ------------------------------------------
    // 1. n8n startet den langen Workflow
    //    und antwortet sofort mit job_id
    // ------------------------------------------

    const jobId =
      await startSearchJob(
        lastSearchData
      );


    if (
      searchId !== currentSearchId
    ) {
      return;
    }


    console.log(
      "Article Finder Job gestartet:",
      jobId
    );


    showMessage(
      elements.searchMessage,
      "Angebote werden gesucht … Bitte einen Moment warten.",
      ""
    );


    // ------------------------------------------
    // 2. Alle 3 Sekunden Status prüfen
    // ------------------------------------------

    const results =
      await waitForSearchResult(
        jobId,
        searchId
      );


    if (
      searchId !== currentSearchId
    ) {
      return;
    }


    // ------------------------------------------
    // 3. Fertige Angebote darstellen
    // ------------------------------------------

    renderResults(results);

  } catch (error) {

    if (
      error.message ===
      "SEARCH_REPLACED"
    ) {
      return;
    }


    console.error(
      "Suche fehlgeschlagen:",
      error
    );


    currentResults = [];


    elements.resultsSection.hidden =
      true;


    updateEmailState();


    if (
      error.message ===
      "POLL_TIMEOUT"
    ) {

      showMessage(
        elements.searchMessage,
        "Die Suche dauert ungewöhnlich lange. Bitte versuche es erneut.",
        "error"
      );

    } else {

      showMessage(
        elements.searchMessage,
        "Die Suche konnte nicht durchgeführt werden. Bitte versuche es erneut.",
        "error"
      );
    }

  } finally {

    if (
      searchId === currentSearchId
    ) {

      setLoadingState(false);
    }
  }
}


// ======================================================
// Ergebnisse per E-Mail senden
// ======================================================

async function sendResultsByEmail() {

  const email =
    elements.email
      .value
      .trim();


  setFieldError(
    elements.email,

    isValidEmail(email)
      ? ""
      : "Bitte gib eine gültige E-Mail-Adresse ein."
  );


  if (
    !currentResults.length ||
    !lastSearchData ||
    !isValidEmail(email)
  ) {

    elements.email.focus();

    return;
  }


  elements.emailButton.disabled =
    true;


  elements.emailButton.classList.add(
    "is-loading"
  );


  showMessage(
    elements.emailMessage
  );


  const payload = {

    ...lastSearchData,

    email,

    results:
      currentResults
  };


  try {

    const response =
      await fetch(
        EMAIL_WEBHOOK_URL,
        {

          method: "POST",

          headers: {
            "Content-Type":
              "application/json"
          },

          body:
            JSON.stringify(
              payload
            )
        }
      );


    if (!response.ok) {

      const errorText =
        await response.text();


      throw new Error(
        `HTTP ${response.status}: ${
          errorText ||
          response.statusText
        }`
      );
    }


    showMessage(
      elements.emailMessage,
      "Die Ergebnisse wurden erfolgreich an deine E-Mail-Adresse gesendet.",
      "success"
    );

  } catch (error) {

    console.error(
      "E-Mail-Versand fehlgeschlagen:",
      error
    );


    showMessage(
      elements.emailMessage,
      "Die E-Mail konnte nicht gesendet werden.",
      "error"
    );

  } finally {

    elements.emailButton.disabled =
      false;


    elements.emailButton.classList.remove(
      "is-loading"
    );
  }
}


// ======================================================
// E-Mail-Button
// ======================================================

function updateEmailState() {

  const email =
    elements.email
      .value
      .trim();


  elements.emailButton.disabled =
    !currentResults.length ||
    !isValidEmail(email);


  if (
    !email ||
    isValidEmail(email)
  ) {

    setFieldError(
      elements.email,
      ""
    );
  }
}


// ======================================================
// Event Listener
// ======================================================

if (elements.form) {

  elements.decreaseRadius.addEventListener(
    "click",
    decreaseRadius
  );


  elements.increaseRadius.addEventListener(
    "click",
    increaseRadius
  );


  elements.form.addEventListener(
    "submit",
    searchArticle
  );


  elements.emailButton.addEventListener(
    "click",
    sendResultsByEmail
  );


  elements.postalCode.addEventListener(
    "input",
    () => {

      elements.postalCode.value =
        elements.postalCode.value
          .replace(/\D/g, "")
          .slice(0, 5);
    }
  );


  [
    elements.article,
    elements.postalCode
  ].forEach(
    (field) => {

      field.addEventListener(
        "input",
        () => {

          setFieldError(
            field,
            ""
          );
        }
      );
    }
  );


  elements.email.addEventListener(
    "input",
    updateEmailState
  );


  updateRadiusDisplay();

  updateEmailState();
}

const SLIDESHOW_INTERVAL = 6000;

async function initializeArtistSlideshow(slideshow) {
  const artist = slideshow.dataset.artist;
  const status = slideshow.querySelector(".artist-slideshow-status");

  try {
    const response = await fetch(`/api/artworks.php?artist=${encodeURIComponent(artist)}`);
    if (!response.ok) throw new Error(`HTTP ${response.status}`);

    const data = await response.json();
    if (!Array.isArray(data.images) || data.images.length === 0) throw new Error("Keine Werke");

    const image = document.createElement("img");
    image.className = "artist-slideshow-image";
    image.alt = "";
    image.src = data.images[0];
    slideshow.replaceChildren(image);

    if (data.images.length === 1) return;

    let currentImage = 0;
    window.setInterval(() => {
      currentImage = (currentImage + 1) % data.images.length;
      image.src = data.images[currentImage];
    }, SLIDESHOW_INTERVAL);
  } catch (error) {
    status.textContent = "Derzeit sind keine Werke verfügbar.";
  }
}

document.querySelectorAll(".artist-slideshow").forEach(initializeArtistSlideshow);
