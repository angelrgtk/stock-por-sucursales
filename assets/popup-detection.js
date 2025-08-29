/**
 * Popup detection script for Stock por Sucursales
 * Shows Elementor popup when no sucursal is selected
 */

jQuery(document).ready(function ($) {
  // Check if we have the necessary data
  if (typeof stockSucursalesPopup === "undefined") {
    return;
  }

  // Check if popup ID is configured
  if (!stockSucursalesPopup.popupId) {
    console.log("Stock Sucursales: No popup ID configured");
    return;
  }

  // Check if user has selected a sucursal
  if (stockSucursalesPopup.hasSelectedSucursal) {
    console.log("Stock Sucursales: Sucursal already selected, no popup needed");
    return;
  }

  // Function to show popup
  function showSucursalPopup() {
    // Check if Elementor Pro frontend is available
    if (
      typeof elementorProFrontend !== "undefined" &&
      elementorProFrontend.modules &&
      elementorProFrontend.modules.popup
    ) {
      console.log(
        "Stock Sucursales: Showing popup with ID:",
        stockSucursalesPopup.popupId
      );

      elementorProFrontend.modules.popup.showPopup({
        id: stockSucursalesPopup.popupId,
      });
    } else {
      console.log("Stock Sucursales: Elementor Pro popup module not available");
    }
  }

  // Show popup after a short delay to ensure page is fully loaded
  setTimeout(function () {
    showSucursalPopup();
  }, 1000);

  // Optional: Listen for sucursal selection events to hide popup
  $(document).on("change", 'select[name="selected_sucursal"]', function () {
    var selectedValue = $(this).val();
    if (selectedValue) {
      console.log(
        "Stock Sucursales: Sucursal selected, popup should close automatically"
      );
    }
  });
});
