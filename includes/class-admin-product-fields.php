<?php

/**
 * Admin product fields for Stock por Sucursales
 *
 * @package Stock_Por_Sucursales
 */

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Admin product fields class
 */
class Stock_Sucursales_Admin_Product_Fields
{
    /**
     * Stock API instance
     */
    private $stock_api;

    /**
     * Constructor
     */
    public function __construct($stock_api)
    {
        $this->stock_api = $stock_api;

        // Only run in admin
        if (is_admin()) {
            add_action('woocommerce_product_options_inventory_product_data', array($this, 'add_sucursal_stock_fields'));
            add_action('woocommerce_process_product_meta', array($this, 'save_sucursal_stock_fields'));
        }
    }

    /**
     * Add sucursal stock fields to product edit page
     */
    public function add_sucursal_stock_fields()
    {
        global $post;

        if (!$post || $post->post_type !== 'product') {
            return;
        }

        $options = get_option('stock_sucursales_options', array());
        $sucursales = isset($options['sucursales']) ? $options['sucursales'] : array();

        if (empty($sucursales)) {
            return;
        }

        echo '<div class="options_group stock-sucursales-fields">';
        echo '<h4>' . __('Stock por Sucursales', 'stock-sucursales') . '</h4>';

        // Single minimum stock field for the entire product
        $minimum_stock = $this->stock_api->get_minimum_stock($post->ID);
        woocommerce_wp_text_input(array(
            'id' => '_minimum_stock_quantity',
            'label' => __('Stock Mínimo para Venta', 'stock-sucursales'),
            'desc_tip' => true,
            'description' => __('Cantidad mínima que debe mantenerse en inventario. El stock disponible para venta será: Stock Real - Stock Mínimo', 'stock-sucursales'),
            'type' => 'number',
            'class' => 'minimum-stock-field',
            'custom_attributes' => array(
                'step' => '1',
                'min' => '0'
            ),
            'value' => $minimum_stock
        ));

        echo '<hr style="margin: 15px 0;">';

        foreach ($sucursales as $slug => $name) {
            $current_stock = $this->stock_api->get_stock($post->ID, $slug);

            echo '<div style="display: flex; gap: 15px; align-items: end; margin-bottom: 10px;">';

            // Stock quantity field
            woocommerce_wp_text_input(array(
                'id' => 'stock_sucursal_' . $slug,
                'label' => sprintf(__('Stock en %s', 'stock-sucursales'), $name),
                'desc_tip' => true,
                'description' => sprintf(__('Cantidad física disponible en %s', 'stock-sucursales'), $name),
                'type' => 'number',
                'class' => 'sucursal-stock-field',
                'wrapper_class' => 'form-field',
                'custom_attributes' => array(
                    'step' => '1',
                    'min' => '0',
                    'style' => 'width: 80px;'
                ),
                'value' => $current_stock
            ));

            // Available stock display
            $available_stock = max(0, $current_stock - $minimum_stock);
            echo '<div class="form-field" style="margin: 0;">';
            echo '<label style="font-weight: normal; color: #666; font-size: 12px;">' . __('Disponible para venta:', 'stock-sucursales') . '</label>';
            echo '<div style="font-weight: bold; font-size: 14px; color: ' . ($available_stock > 0 ? '#0073aa' : '#d63638') . ';">' . $available_stock . ' unidades</div>';
            echo '</div>';

            echo '</div>';
        }

        // Show total calculated stock
        $total_stock = $this->stock_api->get_total_stock($post->ID);
        echo '<p class="form-field">';
        echo '<label><strong>' . __('Stock Total Calculado:', 'stock-sucursales') . '</strong></label>';
        echo '<span id="total-calculated-stock" style="font-weight: bold; color: #0073aa;">' . $total_stock . '</span>';
        echo '<small style="display: block; color: #666;">' . __('Este valor se actualiza automáticamente como la suma de todas las sucursales', 'stock-sucursales') . '</small>';
        echo '</p>';

        echo '</div>';
    }

    /**
     * Save sucursal stock fields
     */
    public function save_sucursal_stock_fields($post_id)
    {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'update-post_' . $post_id)) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $options = get_option('stock_sucursales_options', array());
        $sucursales = isset($options['sucursales']) ? $options['sucursales'] : array();

        $stock_updated = false;

        // Update minimum stock (single field for the entire product)
        if (isset($_POST['_minimum_stock_quantity'])) {
            $minimum_stock = max(0, intval($_POST['_minimum_stock_quantity'])); // Ensure non-negative
            $this->stock_api->update_minimum_stock($post_id, $minimum_stock);
            $stock_updated = true;
        }

        // Update stock quantities by sucursal
        foreach ($sucursales as $slug => $name) {
            $stock_field_name = 'stock_sucursal_' . $slug;

            if (isset($_POST[$stock_field_name])) {
                $stock_quantity = max(0, intval($_POST[$stock_field_name])); // Ensure non-negative
                $this->stock_api->update_stock($post_id, $slug, $stock_quantity);
                $stock_updated = true;
            }
        }

        // Force update aggregate stock if any stock was updated
        if ($stock_updated) {
            $this->stock_api->update_aggregate_stock($post_id);
        }
    }
}
