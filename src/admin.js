"use strict";

document.querySelectorAll(".delete-form").forEach((form) => {
  form.addEventListener("submit", (event) => {
    if (!window.confirm("Story wirklich löschen?")) event.preventDefault();
  });
});
