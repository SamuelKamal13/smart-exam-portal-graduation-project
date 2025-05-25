/**
 * Modal Enhancements for Smart Exam Portal
 * This file contains JavaScript enhancements for all modals across the application
 */

document.addEventListener("DOMContentLoaded", function () {
  // Add modal-confirm class to confirmation modals
  const confirmationModals = document.querySelectorAll(
    "#submitConfirmModal, #deleteConfirmModal, #deleteExamModal, " +
      "#deleteStudentModal, #deleteInstructorModal, #deleteCourseModal, " +
      "#deleteExamResultModal, #deleteMultipleResultsModal, #deleteMultipleCoursesModal"
  );

  confirmationModals.forEach((modal) => {
    modal.classList.add("modal-confirm");
  });

  // Add modal-warning class to warning modals
  const warningModals = document.querySelectorAll(
    "#warningModal, #timeUpModal"
  );
  warningModals.forEach((modal) => {
    modal.classList.add("modal-warning");
  });

  // Add modal-danger class to delete modals
  const dangerModals = document.querySelectorAll(
    '[id^="deleteExamModal"], [id^="deleteStudentModal"], [id^="deleteInstructorModal"], ' +
      '[id^="deleteCourseModal"], #deleteExamResultModal, #deleteMultipleResultsModal, ' +
      "#deleteMultipleCoursesModal, #deleteModal"
  );

  dangerModals.forEach((modal) => {
    modal.classList.add("modal-danger");
  });

  // Fix Bootstrap 5 compatibility issues with data-dismiss
  document.querySelectorAll('[data-dismiss="modal"]').forEach((button) => {
    button.addEventListener("click", function () {
      const target = document.querySelector(
        this.getAttribute("data-target") || ".modal.show"
      );
      if (target) {
        const bsModal = bootstrap.Modal.getInstance(target);
        if (bsModal) {
          bsModal.hide();
        }
      }
    });
  });

  // Add animation effects to modal openings
  document.querySelectorAll(".modal").forEach((modal) => {
    modal.addEventListener("show.bs.modal", function () {
      setTimeout(() => {
        const dialog = this.querySelector(".modal-dialog");
        if (dialog) {
          dialog.classList.add("animate__animated", "animate__fadeInDown");
        }
      }, 50);
    });

    modal.addEventListener("hide.bs.modal", function () {
      const dialog = this.querySelector(".modal-dialog");
      if (dialog) {
        dialog.classList.remove("animate__animated", "animate__fadeInDown");
      }
    });
  });

  // Enhance modal buttons with icons if missing
  document
    .querySelectorAll(".modal-footer .btn-primary:not(:has(i))")
    .forEach((button) => {
      if (
        button.textContent.toLowerCase().includes("submit") ||
        button.textContent.toLowerCase().includes("save")
      ) {
        button.innerHTML = '<i class="fas fa-save"></i> ' + button.textContent;
      } else if (
        button.textContent.toLowerCase().includes("delete") ||
        button.textContent.toLowerCase().includes("remove")
      ) {
        button.innerHTML =
          '<i class="fas fa-trash-alt"></i> ' + button.textContent;
      } else if (
        button.textContent.toLowerCase().includes("confirm") ||
        button.textContent.toLowerCase().includes("yes")
      ) {
        button.innerHTML = '<i class="fas fa-check"></i> ' + button.textContent;
      }
    });

  document
    .querySelectorAll(".modal-footer .btn-secondary:not(:has(i))")
    .forEach((button) => {
      if (
        button.textContent.toLowerCase().includes("cancel") ||
        button.textContent.toLowerCase().includes("close")
      ) {
        button.innerHTML = '<i class="fas fa-times"></i> ' + button.textContent;
      }
    });

  // Make modals draggable (if jQuery UI is available)
  if (typeof jQuery !== "undefined" && typeof jQuery.ui !== "undefined") {
    jQuery(".modal-dialog").draggable({
      handle: ".modal-header",
    });
  }

  // Add responsive behavior for modals
  const adjustModalMaxHeight = () => {
    const modals = document.querySelectorAll(".modal");
    const windowHeight = window.innerHeight;

    modals.forEach((modal) => {
      const modalBody = modal.querySelector(".modal-body");
      if (modalBody) {
        const modalHeader = modal.querySelector(".modal-header");
        const modalFooter = modal.querySelector(".modal-footer");

        const headerHeight = modalHeader ? modalHeader.offsetHeight : 0;
        const footerHeight = modalFooter ? modalFooter.offsetHeight : 0;

        // Set max height with some padding
        const maxHeight = windowHeight - headerHeight - footerHeight - 40;
        modalBody.style.maxHeight = maxHeight + "px";
        modalBody.style.overflowY = "auto";
      }
    });
  };

  // Adjust modal heights on window resize
  window.addEventListener("resize", adjustModalMaxHeight);

  // Adjust modal heights when any modal is shown
  document.querySelectorAll(".modal").forEach((modal) => {
    modal.addEventListener("shown.bs.modal", adjustModalMaxHeight);
  });

  // Initialize tooltips within modals
  document.querySelectorAll(".modal").forEach((modal) => {
    modal.addEventListener("shown.bs.modal", function () {
      const tooltipTriggerList = [].slice.call(
        modal.querySelectorAll('[data-bs-toggle="tooltip"]')
      );
      tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
      });
    });
  });
});
