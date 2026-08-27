async function loadArtistDescription(descriptionElement) {
  const artistId = descriptionElement.dataset.artistId;

  try {
    const response = await fetch(`/api/artist-infos.php?id=${encodeURIComponent(artistId)}`, {
      cache: "no-store"
    });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);

    const data = await response.json();
    if (typeof data.description !== "string") throw new Error("Ungültige Beschreibung");

    descriptionElement.textContent = data.description;
  } catch (error) {
    // Keep the existing description unchanged if it cannot be refreshed.
  }
}

document.querySelectorAll("[data-artist-id]").forEach(loadArtistDescription);
