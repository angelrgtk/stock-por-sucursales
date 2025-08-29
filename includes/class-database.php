<?php

/**
 * Database operations for Stock por Sucursales
 *
 * @package Stock_Por_Sucursales
 */

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Database operations class
 */
class Stock_Sucursales_Database
{
    /**
     * Database table name
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'sucursal_stock';
    }

    /**
     * Get table name
     */
    public function get_table_name()
    {
        return $this->table_name;
    }

    /**
     * Create database table
     */
    public function create_table()
    {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Check if table already exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $this->table_name
        ));

        if ($table_exists !== $this->table_name) {
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE {$this->table_name} (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                product_id bigint(20) unsigned NOT NULL,
                sucursal_slug varchar(100) NOT NULL,
                stock_quantity int(11) NOT NULL DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY unique_product_sucursal (product_id, sucursal_slug),
                KEY idx_product_id (product_id),
                KEY idx_sucursal_slug (sucursal_slug),
                FOREIGN KEY (product_id) REFERENCES {$wpdb->posts}(ID) ON DELETE CASCADE
            ) $charset_collate;";
            dbDelta($sql);

            // Verify table was created
            $table_created = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $this->table_name
            ));

            if ($table_created === $this->table_name) {
                update_option('stock_sucursales_db_version', STOCK_SUCURSALES_VERSION);
                return true;
            }
        }

        return false;
    }

    /**
     * Get stock for a product in a specific sucursal
     */
    public function get_stock($product_id, $sucursal_slug)
    {
        global $wpdb;

        $stock = $wpdb->get_var($wpdb->prepare(
            "SELECT stock_quantity FROM {$this->table_name} 
             WHERE product_id = %d AND sucursal_slug = %s",
            $product_id,
            $sucursal_slug
        ));

        return $stock !== null ? (int) $stock : 0;
    }

    /**
     * Update stock for a product in a specific sucursal
     */
    public function update_stock($product_id, $sucursal_slug, $stock_quantity)
    {
        global $wpdb;

        $result = $wpdb->replace(
            $this->table_name,
            array(
                'product_id' => $product_id,
                'sucursal_slug' => $sucursal_slug,
                'stock_quantity' => $stock_quantity
            ),
            array('%d', '%s', '%d')
        );

        return $result !== false;
    }

    /**
     * Get total stock for a product across all sucursales
     */
    public function get_total_stock($product_id)
    {
        global $wpdb;

        $total_stock = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(stock_quantity) FROM {$this->table_name} 
             WHERE product_id = %d",
            $product_id
        ));

        return $total_stock !== null ? (int) $total_stock : 0;
    }

    /**
     * Get all stock data for a product
     */
    public function get_product_stock_by_sucursales($product_id)
    {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT sucursal_slug, stock_quantity FROM {$this->table_name} 
             WHERE product_id = %d",
            $product_id
        ), ARRAY_A);

        $stock_data = array();
        foreach ($results as $row) {
            $stock_data[$row['sucursal_slug']] = (int) $row['stock_quantity'];
        }

        return $stock_data;
    }

    /**
     * Delete all stock data for a product
     */
    public function delete_product_stock($product_id)
    {
        global $wpdb;

        return $wpdb->delete(
            $this->table_name,
            array('product_id' => $product_id),
            array('%d')
        );
    }

    /**
     * Get stock data for multiple products
     */
    public function get_multiple_products_stock($product_ids)
    {
        global $wpdb;

        if (empty($product_ids)) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT product_id, sucursal_slug, stock_quantity FROM {$this->table_name} 
             WHERE product_id IN ($placeholders)",
            $product_ids
        ), ARRAY_A);

        $stock_data = array();
        foreach ($results as $row) {
            $stock_data[$row['product_id']][$row['sucursal_slug']] = (int) $row['stock_quantity'];
        }

        return $stock_data;
    }

    /**
     * Get products with available stock in a specific sucursal (considering minimum stock)
     */
    public function get_products_with_available_stock($sucursal_slug)
    {
        global $wpdb;

        // Get all products with stock in this sucursal
        $products_with_stock = $wpdb->get_results($wpdb->prepare(
            "SELECT product_id, stock_quantity FROM {$this->table_name} 
             WHERE sucursal_slug = %s 
             AND stock_quantity > 0",
            $sucursal_slug
        ), ARRAY_A);

        $available_products = array();

        foreach ($products_with_stock as $row) {
            $product_id = (int) $row['product_id'];
            $stock_quantity = (int) $row['stock_quantity'];

            // Get minimum stock from product meta
            $minimum_stock = (int) get_post_meta($product_id, '_minimum_stock_quantity', true);

            // Check if available stock (stock - minimum) > 0
            if ($stock_quantity > $minimum_stock) {
                $available_products[] = $product_id;
            }
        }

        return $available_products;
    }

    /**
     * Drop table (for uninstall)
     */
    public function drop_table()
    {
        global $wpdb;

        return $wpdb->query("DROP TABLE IF EXISTS {$this->table_name}");
    }
}
