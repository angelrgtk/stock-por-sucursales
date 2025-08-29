/**
 * Stock por Sucursales - Admin JavaScript
 */

(function ($) {
  "use strict";

  $(document).ready(function () {
    // Initialize stock calculation
    initStockCalculation();

    // Update total when individual stock fields change
    $(".sucursal-stock-field").on("input change", function () {
      updateTotalStock();
    });

    // Handle form submission
    $("#post").on("submit", function () {
      updateTotalStock();
    });
  });

  /**
   * Initialize stock calculation functionality
   */
  function initStockCalculation() {
    // Add visual indicators
    $(".stock-sucursales-fields").prepend(
      '<div class="notice notice-info inline" style="margin: 0 0 15px 0; padding: 8px 12px;">' +
        "<p><strong>Nota:</strong> El stock total de WooCommerce se calculará automáticamente sumando el stock de todas las sucursales.</p>" +
        "</div>"
    );

    // Initial calculation
    updateTotalStock();
  }

  /**
   * Update total stock display
   */
  function updateTotalStock() {
    var total = 0;
    var hasValues = false;

    $(".sucursal-stock-field").each(function () {
      var value = parseInt($(this).val()) || 0;
      if (value > 0) {
        hasValues = true;
      }
      total += value;
    });

    // Update display
    var $totalDisplay = $("#total-calculated-stock");
    if ($totalDisplay.length) {
      var oldValue = $totalDisplay.text();
      $totalDisplay.text(total);

      // Add highlight animation if value changed
      if (oldValue !== total.toString()) {
        $totalDisplay.addClass("stock-total-updated");
        setTimeout(function () {
          $totalDisplay.removeClass("stock-total-updated");
        }, 500);
      }
    }

    // Update WooCommerce stock field if it exists
    var $wcStockField = $("#_stock");
    if ($wcStockField.length && hasValues) {
      $wcStockField.val(total);

      // Update stock status
      var $stockStatus = $("#_stock_status");
      if ($stockStatus.length) {
        if (total > 0) {
          $stockStatus.val("instock");
        } else {
          $stockStatus.val("outofstock");
        }
      }

      // Trigger change event for WooCommerce
      $wcStockField.trigger("change");
    }

    return total;
  }

  /**
   * Validate stock inputs
   */
  function validateStockInputs() {
    $(".sucursal-stock-field").each(function () {
      var $field = $(this);
      var value = parseInt($field.val());

      // Ensure non-negative values
      if (isNaN(value) || value < 0) {
        $field.val(0);
      }
    });
  }

  // Add validation on blur
  $(document).on("blur", ".sucursal-stock-field", function () {
    validateStockInputs();
    updateTotalStock();
  });

  // Add visual feedback for stock changes
  $(document).on("input", ".sucursal-stock-field", function () {
    var $field = $(this);
    var value = parseInt($field.val()) || 0;

    // Add visual indication based on stock level
    $field.removeClass("low-stock medium-stock high-stock");

    if (value === 0) {
      $field.addClass("low-stock");
    } else if (value <= 10) {
      $field.addClass("medium-stock");
    } else {
      $field.addClass("high-stock");
    }
  });
})(jQuery);
