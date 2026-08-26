const GALLERY_ANIMATION_DURATION = 500;

function createGalleryFrame(modifier) {
  const frame = document.createElement("div");
  frame.className = `artist-gallery__frame artist-gallery__frame--${modifier}`;
  const image = document.createElement("img");
  image.className = "artist-gallery__image";
  image.alt = "";
  frame.append(image);
  return frame;
}

function createGalleryButton(modifier, label, symbol) {
  const button = document.createElement("button");
  button.className = `artist-gallery__button artist-gallery__button--${modifier}`;
  button.type = "button";
  button.setAttribute("aria-label", label);
  button.textContent = symbol;
  return button;
}

function createMovingArtwork(image, start, end) {
  const layer = document.createElement("div");
  layer.className = "artist-gallery__animation-layer";
  Object.assign(layer.style, {
    top: `${start.top}px`, left: `${start.left}px`,
    width: `${start.width}px`, height: `${start.height}px`
  });
  layer.append(image.cloneNode());
  document.body.append(layer);
  const animation = layer.animate([
    { top: `${start.top}px`, left: `${start.left}px`, width: `${start.width}px`, height: `${start.height}px` },
    { top: `${end.top}px`, left: `${end.left}px`, width: `${end.width}px`, height: `${end.height}px` }
  ], { duration: GALLERY_ANIMATION_DURATION, easing: "cubic-bezier(.4, 0, .2, 1)", fill: "forwards" });
  animation.finished.finally(() => layer.remove());
  return animation.finished;
}

async function initializeArtistGallery(gallery) {
  const status = gallery.querySelector(".artist-slideshow-status");

  try {
    const response = await fetch(`/api/artworks.php?artist=${encodeURIComponent(gallery.dataset.artist)}`);
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const data = await response.json();
    if (!Array.isArray(data.images) || data.images.length === 0) throw new Error("Keine Werke");

    const previousFrame = createGalleryFrame("preview");
    const currentFrame = createGalleryFrame("current");
    const nextFrame = createGalleryFrame("preview");
    const previousButton = createGalleryButton("previous", "Vorheriges Kunstwerk anzeigen", "→");
    const nextButton = createGalleryButton("next", "Nächstes Kunstwerk anzeigen", "←");
    gallery.replaceChildren(previousFrame, previousButton, currentFrame, nextButton, nextFrame);

    const frames = [previousFrame, currentFrame, nextFrame];
    const images = frames.map((frame) => frame.querySelector("img"));
    let currentIndex = 0;
    let isAnimating = false;
    gallery.dataset.artworkCount = `${data.images.length}`;

    function render() {
      const last = data.images.length - 1;
      const indexes = [(currentIndex - 1 + data.images.length) % data.images.length, currentIndex, (currentIndex + 1) % data.images.length];
      images.forEach((image, index) => { image.src = data.images[indexes[index]]; });
      images[1].alt = `Kunstwerk ${currentIndex + 1} von ${data.images.length}`;
      previousButton.disabled = data.images.length < 2;
      nextButton.disabled = data.images.length < 2;
      gallery.dataset.currentArtwork = `${currentIndex + 1}`;
      gallery.dataset.lastArtwork = `${last + 1}`;
    }

    async function move(direction) {
      if (isAnimating || data.images.length < 2) return;
      isAnimating = true;
      previousButton.disabled = true;
      nextButton.disabled = true;
      const rects = frames.map((frame) => frame.getBoundingClientRect());
      const incoming = direction > 0 ? 2 : 0;
      const outgoingTarget = direction > 0 ? 0 : 2;

      if (rects[incoming].width === 0) {
        await currentFrame.animate([
          { opacity: 1, transform: "translateX(0) scale(1)" },
          { opacity: 0, transform: `translateX(${-direction * 16}px) scale(.96)` }
        ], { duration: GALLERY_ANIMATION_DURATION / 2, easing: "ease-in", fill: "forwards" }).finished;
        currentIndex = (currentIndex + direction + data.images.length) % data.images.length;
        render();
        await currentFrame.animate([
          { opacity: 0, transform: `translateX(${direction * 16}px) scale(.96)` },
          { opacity: 1, transform: "translateX(0) scale(1)" }
        ], { duration: GALLERY_ANIMATION_DURATION / 2, easing: "ease-out" }).finished;
        isAnimating = false;
        return;
      }

      images.forEach((image) => { image.style.visibility = "hidden"; });

      await Promise.all([
        createMovingArtwork(images[incoming], rects[incoming], rects[1]),
        createMovingArtwork(images[1], rects[1], rects[outgoingTarget])
      ]);

      currentIndex = (currentIndex + direction + data.images.length) % data.images.length;
      render();
      images.forEach((image) => { image.style.visibility = ""; });
      isAnimating = false;
    }

    previousButton.addEventListener("click", () => move(-1));
    nextButton.addEventListener("click", () => move(1));
    render();
  } catch (error) {
    status.textContent = "Derzeit sind keine Werke verfügbar.";
  }
}

document.querySelectorAll(".artist-gallery").forEach(initializeArtistGallery);
