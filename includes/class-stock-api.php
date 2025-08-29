<?php

/**
 * Stock API for Stock por Sucursales
 *
 * @package Stock_Por_Sucursales
 */

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Stock API class
 */
class Stock_Sucursales_Stock_API
{
    /**
     * Database handler
     */
    private $database;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->database = new Stock_Sucursales_Database();
    }

    /**
     * Get stock for a product in a specific sucursal
     */
    public function get_stock($product_id, $sucursal_slug)
    {
        return $this->database->get_stock($product_id, $sucursal_slug);
    }

    /**
     * Update stock for a product in a specific sucursal
     */
    public function update_stock($product_id, $sucursal_slug, $stock_quantity)
    {
        $result = $this->database->update_stock($product_id, $sucursal_slug, $stock_quantity);

        // Update aggregate stock in WooCommerce
        if ($result) {
            $this->update_aggregate_stock($product_id);
        }

        return $result;
    }

    /**
     * Get total stock for a product across all sucursales
     */
    public function get_total_stock($product_id)
    {
        return $this->database->get_total_stock($product_id);
    }

    /**
     * Get all stock data for a product
     */
    public function get_product_stock_by_sucursales($product_id)
    {
        return $this->database->get_product_stock_by_sucursales($product_id);
    }

    /**
     * Update aggregate stock in WooCommerce
     */
    public function update_aggregate_stock($product_id)
    {
        $options = get_option('stock_sucursales_options', array());

        if (!isset($options['sync_aggregate_stock']) || !$options['sync_aggregate_stock']) {
            return;
        }

        $total_stock = $this->get_total_stock($product_id);
        update_post_meta($product_id, '_stock', $total_stock);

        // Update stock status
        if ($total_stock > 0) {
            update_post_meta($product_id, '_stock_status', 'instock');
        } else {
            update_post_meta($product_id, '_stock_status', 'outofstock');
        }

        // Trigger WooCommerce stock change hooks
        $product = wc_get_product($product_id);
        if ($product) {
            do_action('woocommerce_product_set_stock', $product);
        }
    }

    /**
     * Get minimum stock for a product (from product meta)
     */
    public function get_minimum_stock($product_id)
    {
        return (int) get_post_meta($product_id, '_minimum_stock_quantity', true);
    }

    /**
     * Update minimum stock for a product (as product meta)
     */
    public function update_minimum_stock($product_id, $minimum_stock)
    {
        return update_post_meta($product_id, '_minimum_stock_quantity', (int) $minimum_stock);
    }

    /**
     * Get available stock (stock_quantity - minimum_stock) for a product in a specific sucursal
     */
    public function get_available_stock($product_id, $sucursal_slug)
    {
        $stock_quantity = $this->get_stock($product_id, $sucursal_slug);
        $minimum_stock = $this->get_minimum_stock($product_id);

        $available = $stock_quantity - $minimum_stock;
        return max(0, $available); // Never return negative stock
    }

    /**
     * Get products with available stock in a specific sucursal (considering minimum stock)
     */
    public function get_products_with_available_stock($sucursal_slug)
    {
        return $this->database->get_products_with_available_stock($sucursal_slug);
    }

    /**
     * Get complete stock data for a product including minimum stock
     */
    public function get_product_complete_stock_data($product_id)
    {
        $stock_by_sucursales = $this->get_product_stock_by_sucursales($product_id);
        $minimum_stock = $this->get_minimum_stock($product_id);

        $complete_data = array();
        foreach ($stock_by_sucursales as $sucursal_slug => $stock_quantity) {
            $complete_data[$sucursal_slug] = array(
                'stock_quantity' => $stock_quantity,
                'minimum_stock' => $minimum_stock,
                'available_stock' => max(0, $stock_quantity - $minimum_stock)
            );
        }

        return $complete_data;
    }

    /**
     * Check if a product has sufficient available stock in a specific sucursal
     */
    public function has_sufficient_stock($product_id, $sucursal_slug, $required_quantity = 1)
    {
        $available_stock = $this->get_available_stock($product_id, $sucursal_slug);
        return $available_stock >= $required_quantity;
    }
}
