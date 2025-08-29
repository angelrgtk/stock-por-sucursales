<?php
/*
Plugin Name: Stock por Sucursales
Plugin URI: https://github.com/alemanydev/stock-por-sucursales
Description: Plugin para gestionar el stock por sucursales de Bodega Boutique. Sistema modular y escalable para WooCommerce.
Version: 1.0.0
Author: https://www.linkedin.com/in/alemanydev/
Author URI: https://www.linkedin.com/in/alemanydev/
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: stock-sucursales
Domain Path: /languages
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
WC requires at least: 4.0
WC tested up to: 8.0
Network: false
*/

// Prevent direct access
defined('ABSPATH') || exit;

// Define plugin constants
define('STOCK_SUCURSALES_VERSION', '1.0.0');
define('STOCK_SUCURSALES_PLUGIN_FILE', __FILE__);
define('STOCK_SUCURSALES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('STOCK_SUCURSALES_PLUGIN_URL', plugin_dir_url(__FILE__));
define('STOCK_SUCURSALES_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required files
require_once STOCK_SUCURSALES_PLUGIN_DIR . 'includes/class-database.php';
require_once STOCK_SUCURSALES_PLUGIN_DIR . 'includes/class-activator.php';
require_once STOCK_SUCURSALES_PLUGIN_DIR . 'includes/class-compatibility.php';
require_once STOCK_SUCURSALES_PLUGIN_DIR . 'includes/class-assets-manager.php';
require_once STOCK_SUCURSALES_PLUGIN_DIR . 'includes/class-stock-api.php';
require_once STOCK_SUCURSALES_PLUGIN_DIR . 'includes/class-sucursal-selector.php';
require_once STOCK_SUCURSALES_PLUGIN_DIR . 'includes/class-admin-product-fields.php';
require_once STOCK_SUCURSALES_PLUGIN_DIR . 'includes/class-admin-pages.php';
require_once STOCK_SUCURSALES_PLUGIN_DIR . 'includes/class-frontend-filters.php';
require_once STOCK_SUCURSALES_PLUGIN_DIR . 'includes/class-rest-api.php';

// Register activation and deactivation hooks
register_activation_hook(__FILE__, array('Stock_Sucursales_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('Stock_Sucursales_Activator', 'deactivate'));

/**
 * Main plugin class
 */
class Stock_Por_Sucursales
{
    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * Module instances
     */
    private $compatibility;
    private $assets_manager;
    private $stock_api;
    private $sucursal_selector;
    private $admin_product_fields;
    private $admin_pages;
    private $frontend_filters;
    private $rest_api;

    /**
     * Get plugin instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        add_action('plugins_loaded', array($this, 'init'));
    }

    /**
     * Initialize plugin
     */
    public function init()
    {
        // Check if WooCommerce is active
        if (!Stock_Sucursales_Compatibility::is_woocommerce_active()) {
            add_action('admin_notices', array('Stock_Sucursales_Compatibility', 'woocommerce_missing_notice'));
            return;
        }

        // Load text domain
        load_plugin_textdomain('stock-sucursales', false, dirname(plugin_basename(__FILE__)) . '/languages');

        // Initialize modules
        $this->init_modules();
    }

    /**
     * Initialize all plugin modules
     */
    private function init_modules()
    {
        // Core modules
        $this->compatibility = new Stock_Sucursales_Compatibility();
        $this->assets_manager = new Stock_Sucursales_Assets_Manager();
        $this->stock_api = new Stock_Sucursales_Stock_API();
        $this->sucursal_selector = new Stock_Sucursales_Sucursal_Selector();

        // Admin modules
        $this->admin_product_fields = new Stock_Sucursales_Admin_Product_Fields($this->stock_api);
        $this->admin_pages = new Stock_Sucursales_Admin_Pages();

        // Frontend modules
        $this->frontend_filters = new Stock_Sucursales_Frontend_Filters($this);

        // REST API
        $this->rest_api = new Stock_Sucursales_REST_API();
    }

    /**
     * Get stock API instance
     */
    public function get_stock_api()
    {
        return $this->stock_api;
    }

    /**
     * Get sucursal selector instance
     */
    public function get_sucursal_selector()
    {
        return $this->sucursal_selector;
    }

    /**
     * Get stock for a product in a specific sucursal
     * @deprecated Use get_stock_api()->get_stock() instead
     */
    public function get_stock($product_id, $sucursal_slug)
    {
        return $this->stock_api->get_stock($product_id, $sucursal_slug);
    }

    /**
     * Update stock for a product in a specific sucursal
     * @deprecated Use get_stock_api()->update_stock() instead
     */
    public function update_stock($product_id, $sucursal_slug, $stock_quantity)
    {
        return $this->stock_api->update_stock($product_id, $sucursal_slug, $stock_quantity);
    }

    /**
     * Get total stock for a product across all sucursales
     * @deprecated Use get_stock_api()->get_total_stock() instead
     */
    public function get_total_stock($product_id)
    {
        return $this->stock_api->get_total_stock($product_id);
    }

    /**
     * Get all stock data for a product
     * @deprecated Use get_stock_api()->get_product_stock_by_sucursales() instead
     */
    public function get_product_stock_by_sucursales($product_id)
    {
        return $this->stock_api->get_product_stock_by_sucursales($product_id);
    }

    /**
     * Get currently selected sucursal
     * @deprecated Use get_sucursal_selector()->get_selected_sucursal() instead
     */
    public function get_selected_sucursal()
    {
        return $this->sucursal_selector->get_selected_sucursal();
    }
}

// Initialize plugin
function stock_por_sucursales()
{
    return Stock_Por_Sucursales::get_instance();
}

// Start the plugin
stock_por_sucursales();
