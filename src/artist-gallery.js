const GALLERY_FADE_DURATION = 400;
const GALLERY_SLIDE_DURATION = 5000;

function synchronizeArtistProfileHeight(gallery) {
  const profile = gallery.closest(".artist-page__content")?.querySelector(".artist-profile");
  if (!profile) return;

  const desktopLayout = window.matchMedia("(min-width: 701px)");
  const syncHeight = () => {
    if (desktopLayout.matches && document.fullscreenElement !== gallery) {
      profile.style.height = `${gallery.offsetHeight}px`;
    } else if (!desktopLayout.matches) {
      profile.style.removeProperty("height");
    }
  };

  new ResizeObserver(syncHeight).observe(gallery);
  desktopLayout.addEventListener("change", syncHeight);
  document.addEventListener("fullscreenchange", syncHeight);
  syncHeight();
}

function galleryButton(className, label, content) {
  const button = document.createElement("button");
  button.type = "button";
  button.className = className;
  button.setAttribute("aria-label", label);
  button.textContent = content;
  return button;
}

function imageIsAvailable(source) {
  return new Promise((resolve) => {
    const image = new Image();
    image.onload = () => resolve(true);
    image.onerror = () => resolve(false);
    image.src = source;
  });
}

async function initializeArtistGallery(gallery) {
  const status = gallery.querySelector(".artist-slideshow-status");

  try {
    const response = await fetch(`/api/artworks.php?artist_id=${encodeURIComponent(gallery.dataset.artistId)}`);
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const data = await response.json();
    if (!Array.isArray(data.artworks) || data.artworks.length === 0) throw new Error("Keine Werke");
    const displayableArtworks = data.artworks.filter((artwork) => artwork && typeof artwork.url === "string");
    const availability = await Promise.all(displayableArtworks.map((artwork) => imageIsAvailable(artwork.url)));
    const artworks = displayableArtworks.filter((artwork, index) => availability[index]);
    if (artworks.length === 0) throw new Error("Keine erreichbaren Werke");

    const stage = document.createElement("div");
    stage.className = "artist-gallery__stage";
    const mainImage = document.createElement("img");
    mainImage.className = "artist-gallery__main-image";
    mainImage.decoding = "async";
    stage.append(mainImage);

    const toolbar = document.createElement("div");
    toolbar.className = "artist-gallery__toolbar";
    const heading = document.createElement("span");
    heading.className = "artist-gallery__heading";
    heading.textContent = "Bildergalerie";
    const controls = document.createElement("div");
    controls.className = "artist-gallery__controls";
    const previous = galleryButton("artist-gallery__control", "Vorheriges Kunstwerk", "‹");
    const next = galleryButton("artist-gallery__control", "Nächstes Kunstwerk", "›");
    const fullscreen = galleryButton("artist-gallery__control artist-gallery__fullscreen", "Galerie im Vollbild anzeigen", "⛶");
    controls.append(previous, next, fullscreen);
    toolbar.append(heading, controls);

    const filmstrip = document.createElement("div");
    filmstrip.className = "artist-gallery__filmstrip";
    const scrollPrevious = galleryButton("artist-gallery__strip-nav", "Thumbnail-Leiste nach links bewegen", "‹");
    const viewport = document.createElement("div");
    viewport.className = "artist-gallery__thumb-viewport";
    const thumbList = document.createElement("div");
    thumbList.className = "artist-gallery__thumb-strip";
    viewport.append(thumbList);
    const scrollNext = galleryButton("artist-gallery__strip-nav", "Thumbnail-Leiste nach rechts bewegen", "›");
    filmstrip.append(scrollPrevious, viewport, scrollNext);

    const caption = document.createElement("p");
    caption.className = "artist-gallery__caption";
    caption.hidden = true;
    gallery.replaceChildren(stage, toolbar, filmstrip, caption);
    gallery.tabIndex = 0;
    gallery.dataset.artworkCount = `${artworks.length}`;

    let currentIndex = 0;
    let changeSequence = 0;
    let slideshowTimer = null;
    const thumbnails = artworks.map((artwork, index) => {
      const thumbnail = galleryButton("artist-gallery__thumbnail", `Kunstwerk ${index + 1} von ${artworks.length} anzeigen`, "");
      const image = document.createElement("img");
      image.src = artwork.url;
      image.alt = "";
      image.loading = index === 0 ? "eager" : "lazy";
      image.decoding = "async";
      thumbnail.append(image);
      thumbnail.addEventListener("click", () => selectArtwork(index));
      thumbList.append(thumbnail);
      return thumbnail;
    });

    function updateSelection(index, shouldFocusThumbnail = false) {
      currentIndex = (index + artworks.length) % artworks.length;
      gallery.dataset.currentArtwork = `${currentIndex + 1}`;
      const artwork = artworks[currentIndex];
      mainImage.src = artwork.url;
      mainImage.alt = artwork.name || `Kunstwerk ${currentIndex + 1} von ${artworks.length}`;
      const details = [artwork.name, artwork.year, artwork.location].filter(
        (value) => typeof value === "string" && value.trim() !== ""
      );
      caption.textContent = details.join(" · ");
      caption.hidden = details.length === 0;
      thumbnails.forEach((thumbnail, thumbnailIndex) => {
        const active = thumbnailIndex === currentIndex;
        thumbnail.classList.toggle("artist-gallery__thumbnail--active", active);
        if (active) thumbnail.setAttribute("aria-current", "true");
        else thumbnail.removeAttribute("aria-current");
      });
      thumbnails[currentIndex].scrollIntoView({ behavior: "smooth", block: "nearest", inline: "nearest" });
      if (shouldFocusThumbnail) thumbnails[currentIndex].focus({ preventScroll: true });
    }

    function stopSlideshow() {
      if (slideshowTimer !== null) clearTimeout(slideshowTimer);
      slideshowTimer = null;
    }

    function startSlideshow() {
      stopSlideshow();
      if (artworks.length < 2) return;
      slideshowTimer = setTimeout(() => selectArtwork(currentIndex + 1), GALLERY_SLIDE_DURATION);
    }

    async function selectArtwork(index) {
      stopSlideshow();
      if (artworks.length === 0) return;
      if ((index + artworks.length) % artworks.length === currentIndex) {
        startSlideshow();
        return;
      }
      const sequence = ++changeSequence;
      mainImage.getAnimations().forEach((animation) => animation.cancel());
      try {
        await mainImage.animate([{ opacity: 1 }, { opacity: 0 }], {
          duration: GALLERY_FADE_DURATION, easing: "ease-in", fill: "forwards"
        }).finished;
      } catch (_) {
        // A newer selection deliberately cancelled this transition.
      }
      if (sequence !== changeSequence) return;
      updateSelection(index);
      mainImage.getAnimations().forEach((animation) => animation.cancel());
      mainImage.animate([{ opacity: 0 }, { opacity: 1 }], {
        duration: GALLERY_FADE_DURATION, easing: "ease-out", fill: "both"
      });
      startSlideshow();
    }

    const move = (direction) => selectArtwork(currentIndex + direction);
    previous.addEventListener("click", () => move(-1));
    next.addEventListener("click", () => move(1));
    scrollPrevious.addEventListener("click", () => viewport.scrollBy({ left: -viewport.clientWidth * 0.8, behavior: "smooth" }));
    scrollNext.addEventListener("click", () => viewport.scrollBy({ left: viewport.clientWidth * 0.8, behavior: "smooth" }));
    gallery.addEventListener("keydown", (event) => {
      if (event.key !== "ArrowLeft" && event.key !== "ArrowRight") return;
      event.preventDefault();
      move(event.key === "ArrowLeft" ? -1 : 1);
    });

    if (gallery.requestFullscreen) {
      fullscreen.addEventListener("click", () => {
        if (document.fullscreenElement === gallery) document.exitFullscreen();
        else gallery.requestFullscreen();
      });
      document.addEventListener("fullscreenchange", () => {
        const active = document.fullscreenElement === gallery;
        gallery.classList.toggle("artist-gallery--fullscreen", active);
        fullscreen.textContent = active ? "×" : "⛶";
        fullscreen.setAttribute("aria-label", active ? "Vollbildansicht schließen" : "Galerie im Vollbild anzeigen");
      });
    } else {
      fullscreen.hidden = true;
    }

    function updateStripNavigation() {
      const overflows = viewport.scrollWidth > viewport.clientWidth + 1;
      scrollPrevious.hidden = !overflows;
      scrollNext.hidden = !overflows;
      filmstrip.classList.toggle("artist-gallery__filmstrip--static", !overflows);
    }
    new ResizeObserver(updateStripNavigation).observe(viewport);
    window.addEventListener("load", updateStripNavigation, { once: true });
    const onlyOne = artworks.length === 1;
    previous.disabled = onlyOne;
    next.disabled = onlyOne;
    updateSelection(0);
    updateStripNavigation();
    startSlideshow();
  } catch (error) {
    status.textContent = "Derzeit sind keine Werke verfügbar.";
  }
}

document.querySelectorAll(".artist-gallery").forEach((gallery) => {
  synchronizeArtistProfileHeight(gallery);
  initializeArtistGallery(gallery);
});
