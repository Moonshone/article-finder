<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
security_headers();
$pageImages = home_page_images();
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="NEMA — Kunst, Mensch und künstliche Intelligenz.">
  <title>NEMA — Art · Human · AI</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
  <link rel="stylesheet" href="styles/style.css">
</head>
<body class="home-page">
  <main aria-label="Home">
    <section class="home-hero" aria-label="ART · HUMAN · AI">
      <div class="home-hero__background" aria-hidden="true">
<?php if ($pageImages['home'] !== ''): ?>
<?php for ($shape = 1; $shape <= 6; $shape++): ?>
        <img class="home-hero__shape home-hero__shape--<?= $shape ?>" src="<?= e($pageImages['home']) ?>" alt="" draggable="false" data-page-image>
<?php endfor; ?>
<?php endif; ?>
      </div>
      <div class="home-hero__content">
        <p>ART <span aria-hidden="true">·</span> HUMAN <span aria-hidden="true">·</span> AI</p>
        <a class="enter-link" href="#art">
          <span>Enter</span>
          <span class="enter-link__arrow" aria-hidden="true">↓</span>
        </a>
      </div>
    </section>

    <div class="home-editorial" id="art">
      <section class="home-feature reveal" aria-labelledby="art-title">
        <div class="home-feature__heading">
          <h2 id="art-title">ART</h2>
          <span class="home-feature__number" aria-hidden="true">01</span>
        </div>
<?php if ($pageImages['home_art'] !== ''): ?>
        <a class="home-feature__media" href="sites/kunst.php" aria-label="Kunst entdecken"><img src="<?= e($pageImages['home_art']) ?>" alt="" loading="lazy" data-page-image></a>
<?php endif; ?>
        <div class="home-feature__footer">
          <p>Artists, works and visual stories</p>
          <a class="explore-link" href="sites/kunst.php">Explore <span aria-hidden="true">→</span></a>
        </div>
      </section>

      <section class="home-feature home-feature--reverse reveal" aria-labelledby="ai-title">
        <div class="home-feature__heading">
          <h2 id="ai-title">AI &amp; ME</h2>
          <span class="home-feature__number" aria-hidden="true">02</span>
        </div>
<?php if ($pageImages['home_ai'] !== ''): ?>
        <a class="home-feature__media" href="sites/ai-me.html" aria-label="AI &amp; Me entdecken"><img src="<?= e($pageImages['home_ai']) ?>" alt="" loading="lazy" data-page-image></a>
<?php endif; ?>
        <div class="home-feature__footer">
          <p>Experiments between human imagination and machines</p>
          <a class="explore-link" href="sites/ai-me.html">Explore <span aria-hidden="true">→</span></a>
        </div>
      </section>

      <section class="home-feature reveal" aria-labelledby="stories-title">
        <div class="home-feature__heading">
          <h2 id="stories-title">STORIES</h2>
          <span class="home-feature__number" aria-hidden="true">03</span>
        </div>
<?php if ($pageImages['home_stories'] !== ''): ?>
        <a class="home-feature__media" href="stories/" aria-label="Stories entdecken"><img src="<?= e($pageImages['home_stories']) ?>" alt="" loading="lazy" data-page-image></a>
<?php endif; ?>
        <div class="home-feature__footer">
          <p>Images, films and narratives</p>
          <a class="explore-link" href="stories/">Explore <span aria-hidden="true">→</span></a>
        </div>
      </section>
    </div>
  </main>

  <script src="src/script.js"></script>
</body>
</html>
