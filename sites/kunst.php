<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/artists.php';

security_headers();

$artists = [
    ['id' => 2, 'name' => 'Laleh Barzegar', 'page' => 'laleh-barzegar.html'],
    ['id' => 3, 'name' => 'Hassan Keivan', 'page' => 'hassan-keivan.html'],
    ['id' => 4, 'name' => 'Shabrokh Golbaz', 'page' => 'shabrokh-golbaz.html'],
];

try {
    $databaseImages = load_artist_images(array_column($artists, 'id'));
} catch (Throwable $exception) {
    error_log('Artist overview database query failed: ' . $exception->getMessage());
    $databaseImages = [];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Künstlerinnen und Künstler im Überblick.">
  <title>Kunst</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../styles/style.css">
</head>
<body>
  <div class="page-shell">
    <header class="site-header">
      <nav class="main-nav" aria-label="Hauptnavigation">
        <a class="nav-link" href="../index.html">Home</a>
        <a class="nav-link active" href="kunst.html" aria-current="page">Kunst</a>
        <a class="nav-link" href="ai-me.html">AI &amp; Me</a>
      </nav>
    </header>
    <main class="art-page">
      <section class="art-intro" aria-labelledby="page-title">
        <span class="eyebrow">Kunst</span>
        <h1 id="page-title">Künstlerinnen und Künstler</h1>
      </section>

      <section class="artist-grid" aria-label="Künstlerübersicht">
<?php foreach ($artists as $artist): ?>
<?php $imageUrl = $databaseImages[$artist['id']] ?? null; ?>
        <article class="artist-card">
          <a class="artist-image-link" href="<?= e($artist['page']) ?>" aria-label="Zur Künstlerseite von <?= e($artist['name']) ?>">
<?php if ($imageUrl !== null): ?>
            <img src="<?= e($imageUrl) ?>" alt="<?= e($artist['name']) ?>">
<?php endif; ?>
          </a>
          <p class="artist-name"><?= e($artist['name']) ?></p>
        </article>

<?php endforeach; ?>
        <article class="artist-card artist-card-placeholder">
          <span>Künstlerkarte</span>
        </article>
      </section>
    </main>
  </div>
  <script src="../src/script.js"></script>
</body>
</html>
