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

        if (empty($sucursales)) {
            return '';
        }

        $selected_sucursal = $this->get_selected_sucursal();
        $current_url = home_url($_SERVER['REQUEST_URI']);

        ob_start();
?>
        <form method="post" action="<?php echo esc_url($current_url); ?>" style="display: inline;">
            <select name="selected_sucursal" onchange="this.form.submit()">
                <option value=""><?php _e('Seleccionar Sucursal', 'stock-sucursales'); ?></option>
                <?php foreach ($sucursales as $slug => $name): ?>
                    <option value="<?php echo esc_attr($slug); ?>" <?php selected($selected_sucursal, $slug); ?>>
                        <?php echo esc_html($name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php wp_nonce_field('select_sucursal_action', 'sucursal_nonce'); ?>
            <input type="hidden" name="action" value="select_sucursal">
            <input type="hidden" name="redirect_to" value="<?php echo esc_url($current_url); ?>">
        </form>
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
