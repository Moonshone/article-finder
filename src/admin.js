"use strict";

document.querySelectorAll(".delete-form").forEach((form) => {
  form.addEventListener("submit", (event) => {
    if (!window.confirm("Story wirklich löschen?")) event.preventDefault();
  });
});

const richEditor = document.querySelector(".rich-editor");
const editorSource = document.querySelector(".editor-source");

if (richEditor && editorSource) {
  let savedRange = null;
  const allowedSizeClasses = ["story-text-small", "story-text-normal", "story-text-large", "story-text-xlarge"];
  const allowedAlignClasses = ["story-align-left", "story-align-center", "story-align-right"];

  const rememberSelection = () => {
    const selection = window.getSelection();
    if (selection.rangeCount && richEditor.contains(selection.anchorNode)) savedRange = selection.getRangeAt(0).cloneRange();
  };
  const restoreSelection = () => {
    if (!savedRange) return null;
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(savedRange);
    return savedRange;
  };
  const wrapSelection = (tagName, className = "") => {
    const range = restoreSelection();
    if (!range || range.collapsed) return;
    const wrapper = document.createElement(tagName);
    if (className) wrapper.className = className;
    try {
      range.surroundContents(wrapper);
    } catch (_) {
      const fragment = range.extractContents();
      wrapper.append(fragment);
      range.insertNode(wrapper);
    }
    savedRange = range.cloneRange();
    syncSource();
  };
  const syncSource = () => {
    const clone = richEditor.cloneNode(true);
    clone.querySelectorAll("div").forEach((element) => {
      const paragraph = document.createElement("p");
      while (element.firstChild) paragraph.append(element.firstChild);
      element.replaceWith(paragraph);
    });
    editorSource.value = clone.innerHTML;
  };

  document.addEventListener("selectionchange", rememberSelection);
  richEditor.addEventListener("input", syncSource);
  richEditor.addEventListener("paste", (event) => {
    event.preventDefault();
    document.execCommand("insertText", false, event.clipboardData.getData("text/plain"));
  });
  document.querySelectorAll("[data-inline-tag]").forEach((button) => {
    button.addEventListener("mousedown", (event) => event.preventDefault());
    button.addEventListener("click", () => wrapSelection(button.dataset.inlineTag));
  });
  document.querySelector(".editor-size").addEventListener("change", (event) => {
    if (allowedSizeClasses.includes(event.target.value)) wrapSelection("span", event.target.value);
    event.target.value = "story-text-normal";
  });
  document.querySelectorAll("[data-align]").forEach((button) => {
    button.addEventListener("mousedown", (event) => event.preventDefault());
    button.addEventListener("click", () => {
      const range = restoreSelection();
      if (!range) return;
      const blocks = [...richEditor.querySelectorAll("p, li")].filter((block) => range.intersectsNode(block));
      const closest = range.startContainer.nodeType === Node.ELEMENT_NODE ? range.startContainer : range.startContainer.parentElement;
      if (!blocks.length && closest) {
        const block = closest.closest("p, li");
        if (block && richEditor.contains(block)) blocks.push(block);
      }
      blocks.forEach((block) => {
        block.classList.remove(...allowedAlignClasses);
        block.classList.add(button.dataset.align);
      });
      syncSource();
    });
  });
  richEditor.closest("form").addEventListener("submit", syncSource);
}
