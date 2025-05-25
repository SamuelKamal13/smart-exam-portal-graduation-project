/**
 * Responsive Sidebar JavaScript
 * Enhances the Bootstrap sidebar toggle functionality
 */
document.addEventListener("DOMContentLoaded", function () {
  // Get sidebar element
  const sidebar = document.getElementById("sidebarMenu");
  const sidebarToggle = document.querySelector(
    '[data-bs-toggle="collapse"][data-bs-target="#sidebarMenu"]'
  );

  // Add click event listener to the document to close sidebar when clicking outside on mobile
  document.addEventListener("click", function (event) {
    // Only apply this behavior on mobile screens
    if (window.innerWidth < 768) {
      // Check if sidebar is open and click is outside sidebar and not on the toggle button
      if (
        sidebar &&
        sidebar.classList.contains("show") &&
        !sidebar.contains(event.target) &&
        !sidebarToggle.contains(event.target)
      ) {
        // Create a new bootstrap collapse instance and hide the sidebar
        const bsCollapse = new bootstrap.Collapse(sidebar);
        bsCollapse.hide();
      }
    }
  });

  // Add click event listeners to sidebar links on mobile to close sidebar when clicked
  if (sidebar) {
    const sidebarLinks = sidebar.querySelectorAll("a.nav-link");
    sidebarLinks.forEach(function (link) {
      link.addEventListener("click", function () {
        // Only apply this behavior on mobile screens
        if (window.innerWidth < 768 && sidebar.classList.contains("show")) {
          const bsCollapse = new bootstrap.Collapse(sidebar);
          bsCollapse.hide();
        }
      });
    });
  }
});
