<?php

/**
 * Plugin activation and deactivation
 *
 * @package Stock_Por_Sucursales
 */

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Plugin activator class
 */
class Stock_Sucursales_Activator
{
    /**
     * Plugin activation
     */
    public static function activate()
    {
        // Check WooCommerce
        self::check_woocommerce();

        // Create database table
        $database = new Stock_Sucursales_Database();
        $database->create_table();

        // Set default options
        self::set_default_options();

        // Set activation flag
        update_option('stock_sucursales_activated', true);
    }

    /**
     * Plugin deactivation
     */
    public static function deactivate()
    {
        // Clean up if needed
        // Note: We don't drop the table on deactivation to preserve data
        delete_option('stock_sucursales_activated');
    }

    /**
     * Check if WooCommerce is active before plugin activation
     */
    private static function check_woocommerce()
    {
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(plugin_basename(STOCK_SUCURSALES_PLUGIN_FILE));
            wp_die(
                __('Stock por Sucursales requiere que WooCommerce esté instalado y activado.', 'stock-sucursales'),
                __('Plugin desactivado', 'stock-sucursales'),
                array('back_link' => true)
            );
        }
    }

    /**
     * Set default plugin options
     */
    private static function set_default_options()
    {
        $default_options = array(
            'sucursales' => array(
                'stock_espana' => 'Asunción',
                'stock_sanber' => 'San Bernardino'
            ),
            'sync_aggregate_stock' => true,
            'show_sucursal_stock_in_product' => true
        );

        add_option('stock_sucursales_options', $default_options);
    }
}
