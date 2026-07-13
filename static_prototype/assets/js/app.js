// Static prototype only — vanilla JS, no framework, no build step.
// Provides just enough interactivity to demo the workflow: tab switching
// and simple modal open/close. None of this ships into the real app.

document.addEventListener("DOMContentLoaded", () => {
  // Tab switching: any element with [data-tabs] wraps [data-tab-btn] triggers
  // and [data-tab-panel] targets, matched by a shared data-tab value.
  document.querySelectorAll("[data-tabs]").forEach((wrapper) => {
    const buttons = wrapper.querySelectorAll("[data-tab-btn]");
    const panels = wrapper.querySelectorAll("[data-tab-panel]");

    buttons.forEach((btn) => {
      btn.addEventListener("click", (e) => {
        e.preventDefault();
        const target = btn.getAttribute("data-tab-btn");

        buttons.forEach((b) => b.classList.remove("active"));
        btn.classList.add("active");

        panels.forEach((p) => {
          p.style.display = p.getAttribute("data-tab-panel") === target ? "" : "none";
        });
      });
    });
  });

  // Modal open/close: [data-open-modal="id"] shows #id, [data-close-modal] hides its ancestor .modal-overlay.
  document.querySelectorAll("[data-open-modal]").forEach((trigger) => {
    trigger.addEventListener("click", (e) => {
      e.preventDefault();
      const id = trigger.getAttribute("data-open-modal");
      const modal = document.getElementById(id);
      if (modal) modal.style.display = "flex";
    });
  });

  document.querySelectorAll("[data-close-modal]").forEach((btn) => {
    btn.addEventListener("click", (e) => {
      e.preventDefault();
      btn.closest(".modal-overlay").style.display = "none";
    });
  });

  document.querySelectorAll(".modal-overlay").forEach((overlay) => {
    overlay.addEventListener("click", (e) => {
      if (e.target === overlay) overlay.style.display = "none";
    });
  });
});
