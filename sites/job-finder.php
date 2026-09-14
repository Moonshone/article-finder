<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/job-finder.php';
security_headers();
job_finder_session();
if (!isset($_SESSION['job_finder_csrf'])) $_SESSION['job_finder_csrf'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="de"><head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Job-Finder: Finde passende Stellen auf Basis deines anonymisierten Lebenslaufs.">
  <meta name="csrf-token" content="<?= e($_SESSION['job_finder_csrf']) ?>">
  <title>Job-Finder</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../styles/style.css"><link rel="stylesheet" href="../styles/job-finder.css">
</head><body><div class="page-shell job-shell">
  <header class="site-header"><nav class="main-nav" aria-label="Hauptnavigation"><a class="nav-link" href="../index.html">Home</a><a class="nav-link" href="kunst.html">Kunst</a><a class="nav-link active" href="ai-me.html">AI &amp; Me</a></nav></header>
  <main class="job-finder">
    <section class="hero job-hero" aria-labelledby="page-title"><span class="eyebrow">AI&amp;me · Job-Finder</span><h1 id="page-title">Dein Profil.<br><span>Deine passenden Stellen.</span></h1><p>Vom anonymisierten Lebenslauf zu nachvollziehbar bewerteten Job-Matches – ruhig, sicher und fokussiert.</p><ol class="job-steps" aria-label="Ablauf"><li><b>01</b> Lebenslauf</li><li><b>02</b> Suchkriterien</li><li><b>03</b> Stellen</li></ol></section>
    <div id="jobWorkspace" class="job-workspace">
      <div class="job-controls">
        <section class="job-panel" aria-labelledby="cv-heading"><div class="card-heading"><span class="step">01</span><div><h2 id="cv-heading">Lebenslauf</h2><p>Anonymisierte PDF sicher verarbeiten.</p></div></div>
          <form id="cvForm" novalidate><label id="dropZone" class="drop-zone" for="cvFile"><span class="drop-icon" aria-hidden="true">↑</span><strong>PDF hier ablegen</strong><span>oder</span><span class="file-button">PDF auswählen</span></label><input id="cvFile" class="visually-hidden" name="lebenslauf" type="file" accept="application/pdf,.pdf" aria-describedby="cvHint cvError"><p id="fileName" class="file-name">Noch keine Datei ausgewählt</p><p id="cvHint" class="privacy-note">Bitte entferne vor dem Hochladen Name, vollständige Adresse, Telefonnummer, E-Mail-Adresse, Geburtsdatum und Bewerbungsfoto.<br><b>Nur PDF · maximal 5 MB</b></p><p id="cvError" class="field-error" role="alert"></p><button id="uploadButton" class="primary-button" type="submit" disabled>Lebenslauf verarbeiten</button><div id="uploadMessage" class="message" role="status" aria-live="polite"></div></form>
        </section>
        <section id="criteriaPanel" class="job-panel is-locked" aria-labelledby="criteria-heading" aria-disabled="true"><div class="card-heading"><span class="step">02</span><div><h2 id="criteria-heading">Was suchst du?</h2><p>Lege deine wichtigsten Kriterien fest.</p></div></div>
          <form id="jobSearchForm" novalidate><fieldset id="criteriaFields" disabled>
            <fieldset class="choice-field"><legend>Suchmodus</legend><div class="choice-row"><label><input type="radio" name="suchmodus" value="mehrere_berufsbereiche" checked><span>Mehrere Berufe</span></label><label><input type="radio" name="suchmodus" value="bestimmter_job"><span>Bestimmter Job</span></label></div></fieldset>
            <div id="areasField" class="field"><label for="areas">Berufe / Tätigkeiten *</label><textarea id="areas" name="berufsbereiche_eingabe" rows="4" placeholder="z. B. Busfahrer, Elektriker, Verkäufer" aria-describedby="areasHint areasError" required></textarea><span id="areasHint" class="field-hint">Mehrere Berufe mit Komma oder Zeilenumbruch trennen.</span><span id="areasError" class="field-error"></span></div>
            <div id="jobField" class="field" hidden><label for="job">Job / Tätigkeit *</label><input id="job" name="job" type="text" maxlength="120" placeholder="z. B. gewünschte Tätigkeit" autocomplete="organization-title"><span id="jobError" class="field-error"></span></div>
            <div class="form-grid"><div class="field"><label for="ort">Ort *</label><input id="ort" name="ort" type="text" maxlength="100" placeholder="Hannover" autocomplete="address-level2" required><span id="ortError" class="field-error"></span></div><div class="field"><label for="radius">Umkreis</label><select id="radius" name="radius"><option value="10">10 km</option><option value="25">25 km</option><option value="50" selected>50 km</option><option value="100">100 km</option><option value="egal">egal</option></select></div></div>
            <fieldset class="choice-field"><legend>Arbeitsform</legend><div class="choice-row"><label><input type="radio" name="remote" value="egal" checked><span>Egal</span></label><label><input type="radio" name="remote" value="remote"><span>Remote</span></label><label><input type="radio" name="remote" value="hybrid"><span>Hybrid</span></label><label><input type="radio" name="remote" value="vor_ort"><span>Vor Ort</span></label></div></fieldset>
            <fieldset class="choice-field"><legend>Beschäftigungsart</legend><div class="choice-row"><label><input type="radio" name="beschaeftigungsart" value="egal" checked><span>Egal</span></label><label><input type="radio" name="beschaeftigungsart" value="Vollzeit"><span>Vollzeit</span></label><label><input type="radio" name="beschaeftigungsart" value="Teilzeit"><span>Teilzeit</span></label><label><input type="radio" name="beschaeftigungsart" value="Freelancer"><span>Freelancer</span></label></div></fieldset>
            <fieldset class="choice-field"><legend>Jobquellen / Webseiten</legend><div class="source-grid"><label><input type="checkbox" name="webseiten" value="unternehmensseiten" checked> Unternehmensseiten</label><label><input type="checkbox" name="webseiten" value="stepstone.de" checked> StepStone</label><label><input type="checkbox" name="webseiten" value="arbeitsagentur.de" checked> Bundesagentur für Arbeit</label><label><input type="checkbox" name="webseiten" value="indeed.com"> Indeed</label><label><input type="checkbox" name="webseiten" value="linkedin.com"> LinkedIn</label></div></fieldset>
            <div class="field"><label for="ausschluesse">Ausschlusskriterien</label><textarea id="ausschluesse" name="ausschluesse" maxlength="500" rows="3" placeholder="Senior, Führungskraft, Personalverantwortung"></textarea><span class="field-hint">Mit Kommas trennen</span></div>
            <button id="jobSearchButton" class="primary-button" type="submit">Jobs suchen</button>
          </fieldset><div id="searchError" class="message" role="alert"></div></form>
        </section>
      </div>
      <div class="job-output">
        <section id="statusPanel" class="job-panel status-panel" aria-labelledby="status-heading" hidden><span class="eyebrow">Suche läuft</span><h2 id="status-heading">Passende Stellen werden gesucht …</h2><ul id="statusList" class="status-list"><li>Lebenslauf wird analysiert</li><li>Berufliche Alternativen werden ermittelt</li><li>Aktuelle Stellen werden recherchiert</li><li>Stellen werden bewertet</li><li>Ergebnisse werden vorbereitet</li></ul></section>
        <section id="jobResults" aria-labelledby="results-heading" hidden><div class="results-heading"><div><span class="eyebrow">Deine Matches</span><h2 id="results-heading">Passende Stellen</h2></div><span id="jobCount" class="results-count"></span></div><div id="jobCards" class="job-cards"></div></section>
        <section id="emptyState" class="job-empty"><span class="step">03</span><h2>Passende Stellen</h2><p>Nach der Suche erscheinen hier deine bewerteten Job-Matches.</p></section>
      </div>
    </div>
  </main><footer><span>© 2026 Job-Finder</span><span>Datensparsam · Sicher · Nachvollziehbar</span></footer>
</div><script src="../src/job-finder.js" defer></script></body></html>
