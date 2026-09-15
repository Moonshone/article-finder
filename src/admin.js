"use strict";

document.querySelectorAll(".delete-form").forEach((form) => {
  form.addEventListener("submit", (event) => {
    if (!window.confirm("Story wirklich löschen?")) event.preventDefault();
  });
});

const allowedSizeClasses = [12, 14, 16, 18, 20, 22, 24, 26, 28, 30, 32, 36, 40, 44, 48].map((size) => `story-font-${size}`);
const allowedAlignClasses = ["story-align-left", "story-align-center", "story-align-right"];

document.querySelectorAll("[data-rich-editor]").forEach((field) => {
  const richEditor = field.querySelector(".rich-editor");
  const editorSource = field.querySelector(".editor-source");
  let savedRange = null;
  const syncSource = () => {
    const clone = richEditor.cloneNode(true);
    clone.querySelectorAll("div").forEach((element) => {
      const paragraph = document.createElement("p");
      while (element.firstChild) paragraph.append(element.firstChild);
      element.replaceWith(paragraph);
    });
    editorSource.value = clone.innerHTML;
  };
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
    wrapper.append(range.extractContents());
    range.insertNode(wrapper);
    range.selectNodeContents(wrapper);
    savedRange = range.cloneRange();
    syncSource();
  };
  document.addEventListener("selectionchange", rememberSelection);
  richEditor.addEventListener("input", syncSource);
  richEditor.addEventListener("paste", (event) => {
    event.preventDefault();
    document.execCommand("insertText", false, event.clipboardData.getData("text/plain"));
  });
  field.querySelectorAll("[data-inline-tag]").forEach((button) => {
    button.addEventListener("mousedown", (event) => event.preventDefault());
    button.addEventListener("click", () => wrapSelection(button.dataset.inlineTag));
  });
  field.querySelector(".editor-size").addEventListener("change", (event) => {
    if (allowedSizeClasses.includes(event.target.value)) wrapSelection("span", event.target.value);
    event.target.value = "";
  });
  field.querySelectorAll("[data-align]").forEach((button) => {
    button.addEventListener("mousedown", (event) => event.preventDefault());
    button.addEventListener("click", () => {
      const range = restoreSelection();
      if (!range) return;
      const blocks = [...richEditor.querySelectorAll("p, li")].filter((block) => range.intersectsNode(block));
      const origin = range.startContainer.nodeType === Node.ELEMENT_NODE ? range.startContainer : range.startContainer.parentElement;
      const currentBlock = origin && origin.closest("p, li");
      if (!blocks.length && currentBlock && richEditor.contains(currentBlock)) blocks.push(currentBlock);
      if (!blocks.length) {
        const paragraph = document.createElement("p");
        paragraph.append(...richEditor.childNodes);
        richEditor.append(paragraph);
        blocks.push(paragraph);
      }
      blocks.forEach((block) => {
        block.classList.remove(...allowedAlignClasses);
        block.classList.add(button.dataset.align);
      });
      syncSource();
    });
  });
  richEditor.closest("form").addEventListener("submit", syncSource);
});
