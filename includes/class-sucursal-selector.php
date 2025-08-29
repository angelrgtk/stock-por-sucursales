<?php

/**
 * Sucursal selector for Stock por Sucursales
 *
 * @package Stock_Por_Sucursales
 */

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Sucursal selector class
 */
class Stock_Sucursales_Sucursal_Selector
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_shortcode('sucursal_selector', array($this, 'sucursal_selector_shortcode'));
        add_action('init', array($this, 'handle_sucursal_selection'));
        add_action('wp_login', array($this, 'load_user_sucursal_preference'), 10, 2);
    }

    /**
     * Sucursal selector shortcode
     */
    public function sucursal_selector_shortcode($atts = array())
    {
        $options = get_option('stock_sucursales_options', array());
        $sucursales = isset($options['sucursales']) ? $options['sucursales'] : array();
        $popup_id = isset($options['popup_id']) ? $options['popup_id'] : '';

        if (empty($sucursales)) {
            return '';
        }

        // Get current selection
        $selected_sucursal = $this->get_selected_sucursal();
        $selected_name = '';

        if (!empty($selected_sucursal) && isset($sucursales[$selected_sucursal])) {
            $selected_name = $sucursales[$selected_sucursal];
        }

        ob_start();
?>
        <button type="button" class="sucursal-selector-btn" onclick="openSucursalPopup()">
            <svg class="sucursal-icon" viewBox="0 0 128 128" xmlns="http://www.w3.org/2000/svg">
                <path d="M64 1C38.8 1 18.3 21.2 18.3 46S64 127 64 127s45.7-56.2 45.7-81S89.2 1 64 1zm0 73.9c-16.6 0-30-13.2-30-29.5C34 29 47.4 15.8 64 15.8S94 29 94 45.3 80.6 74.9 64 74.9z" />
            </svg>
            <span class="sucursal-text">
                <?php if (!empty($selected_name)): ?>
                    <?php echo esc_html($selected_name); ?>
                <?php else: ?>
                    <?php _e('Seleccionar Sucursal', 'stock-sucursales'); ?>
                <?php endif; ?>
            </span>
        </button>

        <style>
            .sucursal-selector-btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                background: none;
                border: none;
                cursor: pointer;
                font-family: inherit;
                font-size: inherit;
                color: inherit;
                padding: 0;
                margin: 0;
            }

            .sucursal-icon {
                width: 16px;
                height: 16px;
                fill: currentColor;
                flex-shrink: 0;
            }

            .sucursal-text {
                white-space: nowrap;
            }
        </style>

        <script>
            function openSucursalPopup() {
                <?php if (!empty($popup_id)): ?>
                    // Check if Elementor Pro frontend is available
                    if (typeof elementorProFrontend !== 'undefined' &&
                        elementorProFrontend.modules &&
                        elementorProFrontend.modules.popup) {

                        elementorProFrontend.modules.popup.showPopup({
                            id: <?php echo intval($popup_id); ?>
                        });

                    } else {
                        console.log('Elementor Pro popup module not available');
                        alert('<?php _e("Por favor configura el ID del popup en el panel de administración.", "stock-sucursales"); ?>');
                    }
                <?php else: ?>
                    alert('<?php _e("Por favor configura el ID del popup en el panel de administración.", "stock-sucursales"); ?>');
                <?php endif; ?>
            }
        </script>
<?php
        return ob_get_clean();
    }

    /**
     * Handle sucursal selection from form
     */
    public function handle_sucursal_selection()
    {
        if (!isset($_POST['action']) || $_POST['action'] !== 'select_sucursal') {
            return;
        }

        if (!wp_verify_nonce($_POST['sucursal_nonce'], 'select_sucursal_action')) {
            return;
        }

        $selected_sucursal = sanitize_text_field($_POST['selected_sucursal']);
        $redirect_to = isset($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : home_url();

        // Save in cookie (30 days)
        setcookie('selected_sucursal', $selected_sucursal, time() + (30 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN);

        // Save in user meta if logged in
        if (is_user_logged_in()) {
            update_user_meta(get_current_user_id(), 'preferred_sucursal', $selected_sucursal);
        }

        // Redirect back to same page
        wp_redirect($redirect_to);
        exit;
    }

    /**
     * Get currently selected sucursal
     */
    public function get_selected_sucursal()
    {
        // First check cookie
        if (isset($_COOKIE['selected_sucursal'])) {
            return sanitize_text_field($_COOKIE['selected_sucursal']);
        }

        // Then check user meta if logged in
        if (is_user_logged_in()) {
            $user_preference = get_user_meta(get_current_user_id(), 'preferred_sucursal', true);
            if (!empty($user_preference)) {
                // Set cookie to match user preference
                setcookie('selected_sucursal', $user_preference, time() + (30 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN);
                return $user_preference;
            }
        }

        return '';
    }

    /**
     * Load user sucursal preference on login
     */
    public function load_user_sucursal_preference($user_login, $user)
    {
        $user_preference = get_user_meta($user->ID, 'preferred_sucursal', true);
        if (!empty($user_preference)) {
            setcookie('selected_sucursal', $user_preference, time() + (30 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN);
        }
    }
}
