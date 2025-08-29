<?php

/**
 * REST API endpoints for Stock por Sucursales
 *
 * @package Stock_Por_Sucursales
 */

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Stock_Sucursales_REST_API class
 */
class Stock_Sucursales_REST_API
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register REST API routes
     */
    public function register_routes()
    {
        register_rest_route('stock-sucursales/v1', '/select-sucursal', array(
            'methods' => 'GET',
            'callback' => array($this, 'handle_select_sucursal'),
            'permission_callback' => '__return_true', // Public endpoint
            'args' => array(
                'selected_sucursal' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => array($this, 'validate_sucursal_param'),
                ),
                'redirect_to' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                ),
            ),
        ));
    }

    /**
     * Validate sucursal parameter
     */
    public function validate_sucursal_param($param, $request, $key)
    {
        return $this->is_valid_sucursal($param);
    }

    /**
     * Handle select sucursal request
     */
    public function handle_select_sucursal($request)
    {
        $selected_sucursal = $request->get_param('selected_sucursal');
        $redirect_to = $request->get_param('redirect_to');

        // Save in cookie (30 days)
        setcookie('selected_sucursal', $selected_sucursal, time() + (30 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN);

        // Save in user meta if logged in
        if (is_user_logged_in()) {
            update_user_meta(get_current_user_id(), 'preferred_sucursal', $selected_sucursal);
        }

        // Determine redirect URL
        $redirect_url = $this->get_redirect_url($redirect_to);

        // Redirect
        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Validate if sucursal slug exists
     */
    private function is_valid_sucursal($sucursal_slug)
    {
        $options = get_option('stock_sucursales_options', array());
        $sucursales = isset($options['sucursales']) ? $options['sucursales'] : array();

        return array_key_exists($sucursal_slug, $sucursales);
    }

    /**
     * Get redirect URL from parameter, referer or default to home
     */
    private function get_redirect_url($redirect_to = null)
    {
        // Check if there's a custom redirect parameter
        if (!empty($redirect_to)) {
            // Validate that it's a local URL for security
            if (wp_validate_redirect($redirect_to, home_url())) {
                return $redirect_to;
            }
        }

        // Check HTTP referer
        if (isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])) {
            $referer = esc_url_raw($_SERVER['HTTP_REFERER']);

            // Validate that referer is from the same domain
            if (wp_validate_redirect($referer, home_url())) {
                return $referer;
            }
        }

        // Default to home URL
        return home_url();
    }
}
