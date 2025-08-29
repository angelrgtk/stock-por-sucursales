<?php

/**
 * Compatibility handler for Stock por Sucursales
 *
 * @package Stock_Por_Sucursales
 */

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Compatibility class
 */
class Stock_Sucursales_Compatibility
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));
    }

    /**
     * Declare HPOS compatibility
     */
    public function declare_hpos_compatibility()
    {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                STOCK_SUCURSALES_PLUGIN_FILE,
                true
            );
        }
    }

    /**
     * Check if WooCommerce is active
     */
    public static function is_woocommerce_active()
    {
        return class_exists('WooCommerce');
    }

    /**
     * WooCommerce missing notice
     */
    public static function woocommerce_missing_notice()
    {
        echo '<div class="notice notice-error"><p>';
        echo __('Stock por Sucursales requiere que WooCommerce esté instalado y activado.', 'stock-sucursales');
        echo '</p></div>';
    }
}
