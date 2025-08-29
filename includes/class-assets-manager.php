<?php

/**
 * Assets manager for Stock por Sucursales
 *
 * @package Stock_Por_Sucursales
 */

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Assets manager class
 */
class Stock_Sucursales_Assets_Manager
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook)
    {
        // Only load on product edit pages
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }

        global $post;
        if (!$post || $post->post_type !== 'product') {
            return;
        }

        wp_enqueue_script(
            'stock-sucursales-admin',
            STOCK_SUCURSALES_PLUGIN_URL . 'assets/admin.js',
            array('jquery'),
            STOCK_SUCURSALES_VERSION,
            true
        );

        wp_enqueue_style(
            'stock-sucursales-admin',
            STOCK_SUCURSALES_PLUGIN_URL . 'assets/admin.css',
            array(),
            STOCK_SUCURSALES_VERSION
        );
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts()
    {
        wp_enqueue_style(
            'stock-sucursales-frontend',
            STOCK_SUCURSALES_PLUGIN_URL . 'assets/frontend.css',
            array(),
            STOCK_SUCURSALES_VERSION
        );
    }
}
