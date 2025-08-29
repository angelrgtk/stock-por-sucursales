<?php

/**
 * Frontend filters for Stock por Sucursales
 *
 * @package Stock_Por_Sucursales
 */

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Frontend filters class
 */
class Stock_Sucursales_Frontend_Filters
{
    /**
     * Main plugin instance
     */
    private $main_plugin;

    /**
     * Database handler
     */
    private $database;

    /**
     * Constructor
     */
    public function __construct($main_plugin)
    {
        $this->main_plugin = $main_plugin;
        $this->database = new Stock_Sucursales_Database();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Only run on frontend
        if (!is_admin()) {
            // Main query filter
            add_action('pre_get_posts', array($this, 'filter_products_by_sucursal_stock'), 5);

            // JetEngine hooks (for reload mode)
            add_filter('jet-engine/listings/data/object-fields-query-args', array($this, 'filter_jetengine_query'), 10, 2);
            add_filter('jet-engine/query-builder/query/final-query', array($this, 'filter_jetengine_final_query'), 10, 2);
            add_filter('jet-engine/listings/data/query-args', array($this, 'filter_jetengine_query'), 10, 2);
            add_filter('jet-engine/listing/grid/posts-query-args', array($this, 'filter_jetengine_simple_query'), 10, 1);
            add_filter('jet-engine/listings/data/posts-query-args', array($this, 'filter_jetengine_simple_query'), 10, 1);

            // JetSmartFilters hooks (for reload mode)
            add_filter('jet-smart-filters/query/final-query', array($this, 'filter_jetsmartfilters_query'), 10, 2);
            add_filter('jet-smart-filters/providers/jet-engine/query-args', array($this, 'filter_jetsmartfilters_jetengine'), 10, 2);
            add_filter('jet-smart-filters/providers/jet-engine-maps/query-args', array($this, 'filter_jetsmartfilters_jetengine'), 10, 2);
        }
    }

    /**
     * Filter products by sucursal stock availability
     */
    public function filter_products_by_sucursal_stock($query)
    {
        // Only filter main queries for products
        if (!$query->is_main_query()) {
            return;
        }

        if (is_admin()) {
            return;
        }

        // Check if it's a WooCommerce product query
        $post_type = $query->get('post_type');

        // More specific WooCommerce detection
        $is_wc_query = false;

        // Direct product post type query
        if ($post_type === 'product') {
            $is_wc_query = true;
        }
        // WooCommerce conditional tags
        elseif (function_exists('is_shop') && is_shop()) {
            $is_wc_query = true;
            $query->set('post_type', 'product');
        } elseif (function_exists('is_product_category') && is_product_category()) {
            $is_wc_query = true;
            $query->set('post_type', 'product');
        } elseif (function_exists('is_product_tag') && is_product_tag()) {
            $is_wc_query = true;
            $query->set('post_type', 'product');
        }
        // WooCommerce shortcodes
        elseif (isset($query->query_vars['wc_query']) && $query->query_vars['wc_query'] === 'product_query') {
            $is_wc_query = true;
        }

        if (!$is_wc_query) {
            return;
        }

        // Get selected sucursal
        $selected_sucursal = $this->main_plugin->get_selected_sucursal();

        // If no sucursal selected, don't filter
        if (empty($selected_sucursal)) {
            return;
        }

        // Get products with stock in selected sucursal
        $products_with_stock = $this->get_products_with_stock($selected_sucursal);

        if (empty($products_with_stock)) {
            // No products with stock - show none
            $query->set('post__in', array(0));
        } else {
            // Filter to only show products with stock
            $existing_post_in = $query->get('post__in');

            if (!empty($existing_post_in)) {
                // If there's already a post__in filter, intersect with our results
                $products_with_stock = array_intersect($existing_post_in, $products_with_stock);
            }

            $query->set('post__in', $products_with_stock);
        }
    }

    /**
     * Get products that have available stock in specified sucursal (considering minimum stock)
     */
    private function get_products_with_stock($sucursal_slug)
    {
        return $this->database->get_products_with_available_stock($sucursal_slug);
    }

    /**
     * Filter JetEngine listings query
     */
    public function filter_jetengine_query($query_args, $settings)
    {
        // Only filter product queries
        if (!isset($query_args['post_type']) || $query_args['post_type'] !== 'product') {
            return $query_args;
        }

        // Get selected sucursal
        $selected_sucursal = $this->main_plugin->get_selected_sucursal();

        if (empty($selected_sucursal)) {
            return $query_args;
        }

        // Get products with stock
        $products_with_stock = $this->get_products_with_stock($selected_sucursal);

        if (empty($products_with_stock)) {
            // No products with stock - show none
            $query_args['post__in'] = array(0);
        } else {
            // Filter to only show products with stock
            if (isset($query_args['post__in']) && !empty($query_args['post__in'])) {
                // Intersect with existing filter
                $products_with_stock = array_intersect($query_args['post__in'], $products_with_stock);
            }

            $query_args['post__in'] = $products_with_stock;
        }

        return $query_args;
    }

    /**
     * Filter JetEngine final query (Query Builder)
     */
    public function filter_jetengine_final_query($query, $query_id)
    {
        // Check if it's a product query
        if (is_object($query) && method_exists($query, 'get_query_args')) {
            $args = $query->get_query_args();

            if (isset($args['post_type']) && $args['post_type'] === 'product') {
                $selected_sucursal = $this->main_plugin->get_selected_sucursal();

                if (!empty($selected_sucursal)) {
                    $products_with_stock = $this->get_products_with_stock($selected_sucursal);

                    if (empty($products_with_stock)) {
                        $args['post__in'] = array(0);
                    } else {
                        $args['post__in'] = $products_with_stock;
                    }

                    $query->set_query_args($args);
                }
            }
        }

        return $query;
    }

    /**
     * Filter JetEngine simple query (single argument)
     */
    public function filter_jetengine_simple_query($query_args)
    {
        // Only filter product queries
        if (!isset($query_args['post_type']) || $query_args['post_type'] !== 'product') {
            return $query_args;
        }

        $selected_sucursal = $this->main_plugin->get_selected_sucursal();

        if (!empty($selected_sucursal)) {
            $products_with_stock = $this->get_products_with_stock($selected_sucursal);

            if (empty($products_with_stock)) {
                $query_args['post__in'] = array(0);
            } else {
                $query_args['post__in'] = $products_with_stock;
            }
        }

        return $query_args;
    }

    /**
     * Filter JetSmartFilters query
     */
    public function filter_jetsmartfilters_query($query, $provider = '')
    {
        if (is_object($query) && method_exists($query, 'get')) {
            $post_type = $query->get('post_type');

            if ($post_type === 'product') {
                $selected_sucursal = $this->main_plugin->get_selected_sucursal();

                if (!empty($selected_sucursal)) {
                    $products_with_stock = $this->get_products_with_stock($selected_sucursal);

                    if (empty($products_with_stock)) {
                        $query->set('post__in', array(0));
                    } else {
                        $existing_post_in = $query->get('post__in');
                        if (!empty($existing_post_in)) {
                            $products_with_stock = array_intersect($existing_post_in, $products_with_stock);
                        }

                        $query->set('post__in', $products_with_stock);
                    }
                }
            }
        }

        return $query;
    }

    /**
     * Filter JetSmartFilters + JetEngine integration
     */
    public function filter_jetsmartfilters_jetengine($query_args, $provider_settings)
    {
        // Only filter product queries
        if (!isset($query_args['post_type']) || $query_args['post_type'] !== 'product') {
            return $query_args;
        }

        $selected_sucursal = $this->main_plugin->get_selected_sucursal();

        if (!empty($selected_sucursal)) {
            $products_with_stock = $this->get_products_with_stock($selected_sucursal);

            if (empty($products_with_stock)) {
                $query_args['post__in'] = array(0);
            } else {
                if (isset($query_args['post__in']) && !empty($query_args['post__in'])) {
                    $products_with_stock = array_intersect($query_args['post__in'], $products_with_stock);
                }

                $query_args['post__in'] = $products_with_stock;
            }
        }

        return $query_args;
    }
}
