<?php

/**
 * Admin pages handler
 * 
 * @package Stock_Por_Sucursales
 * @since 1.0.0
 */

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Stock_Sucursales_Admin_Pages class
 */
class Stock_Sucursales_Admin_Pages
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_sync_stock_manual', array($this, 'handle_manual_sync'));
        add_action('wp_ajax_format_skus', array($this, 'handle_format_skus'));
        add_action('admin_post_clear_stock_logs', array($this, 'handle_clear_logs'));
        add_action('admin_post_save_popup_settings', array($this, 'handle_save_popup_settings'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        add_menu_page(
            __('Stock por Sucursales', 'stock-sucursales'),
            __('Stock Sucursales', 'stock-sucursales'),
            'manage_woocommerce',
            'stock-sucursales',
            array($this, 'admin_page_callback'),
            'dashicons-store',
            56
        );
    }

    /**
     * Handle manual sync AJAX request
     */
    public function handle_manual_sync()
    {
        // Verificar nonce de seguridad
        if (!wp_verify_nonce($_POST['nonce'], 'stock_manual_sync')) {
            wp_die('Error de seguridad');
        }

        // Verificar permisos
        if (!current_user_can('manage_woocommerce')) {
            wp_die('No tienes permisos para realizar esta acción');
        }

        // Incluir el script de sincronización
        require_once plugin_dir_path(dirname(__FILE__)) . 'scripts/sync-stock.php';

        // Ejecutar sincronización manual
        sync_branch_stock_run(true);

        // Obtener logs
        $logs = get_stock_sync_logs();

        wp_send_json_success(array(
            'message' => 'Sincronización completada',
            'logs' => $logs
        ));
    }

    /**
     * Handle format SKUs AJAX request
     */
    public function handle_format_skus()
    {
        // Verificar nonce de seguridad
        if (!wp_verify_nonce($_POST['nonce'], 'format_skus_action')) {
            wp_die('Error de seguridad');
        }

        // Verificar permisos
        if (!current_user_can('manage_woocommerce')) {
            wp_die('No tienes permisos para realizar esta acción');
        }

        // Obtener todos los productos de WooCommerce
        $products = wc_get_products(array(
            'limit' => -1,
            'status' => 'publish'
        ));

        $formatted_count = 0;
        $logs = array();
        $logs[] = '[' . date('Y-m-d H:i:s') . '] Iniciando formateo de SKUs...';
        $logs[] = '[' . date('Y-m-d H:i:s') . '] Total de productos encontrados: ' . count($products);

        foreach ($products as $product) {
            $sku = $product->get_sku();

            // Solo procesar si tiene SKU
            if (!empty($sku)) {
                // Verificar si el SKU es numérico y tiene menos de 6 dígitos
                if (is_numeric($sku) && strlen($sku) < 6) {
                    // Formatear a 6 dígitos con ceros a la izquierda
                    $formatted_sku = str_pad($sku, 6, '0', STR_PAD_LEFT);

                    // Actualizar el SKU del producto
                    $product->set_sku($formatted_sku);
                    $product->save();

                    $formatted_count++;
                    $logs[] = '[' . date('Y-m-d H:i:s') . '] Producto ID ' . $product->get_id() . ': SKU "' . $sku . '" → "' . $formatted_sku . '"';
                }
            }
        }

        $logs[] = '[' . date('Y-m-d H:i:s') . '] Formateo completado. Total de SKUs formateados: ' . $formatted_count;

        // Guardar logs en una opción temporal
        update_option('stock_sucursales_format_logs', $logs);

        wp_send_json_success(array(
            'message' => 'Formateo de SKUs completado. ' . $formatted_count . ' SKUs fueron formateados.',
            'formatted_count' => $formatted_count,
            'logs' => $logs
        ));
    }

    /**
     * Handle save popup settings request
     */
    public function handle_save_popup_settings()
    {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['popup_settings_nonce'], 'save_popup_settings')) {
            wp_die('Error de seguridad');
        }

        // Verificar permisos
        if (!current_user_can('manage_woocommerce')) {
            wp_die('No tienes permisos para realizar esta acción');
        }

        // Obtener opciones actuales
        $options = get_option('stock_sucursales_options', array());

        // Actualizar el ID del popup
        $popup_id = isset($_POST['popup_id']) ? intval($_POST['popup_id']) : '';
        $options['popup_id'] = $popup_id;

        // Guardar opciones
        update_option('stock_sucursales_options', $options);

        // Redirigir de vuelta con mensaje de éxito
        wp_redirect(admin_url('admin.php?page=stock-sucursales&popup_saved=1'));
        exit;
    }

    /**
     * Handle clear logs request
     */
    public function handle_clear_logs()
    {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['stock_clear_logs_nonce'], 'stock_clear_logs')) {
            wp_die('Error de seguridad');
        }

        // Verificar permisos
        if (!current_user_can('manage_woocommerce')) {
            wp_die('No tienes permisos para realizar esta acción');
        }

        // Limpiar logs
        delete_option('stock_sucursales_sync_logs');

        // Redirigir de vuelta
        wp_redirect(admin_url('admin.php?page=stock-sucursales&logs_cleared=1'));
        exit;
    }

    /**
     * Admin page callback
     */
    public function admin_page_callback()
    {
        // Incluir funciones de logs
        require_once plugin_dir_path(dirname(__FILE__)) . 'scripts/sync-stock.php';
        $sync_logs = get_stock_sync_logs();
?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php if (isset($_GET['logs_cleared'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Logs eliminados correctamente.', 'stock-sucursales'); ?></p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['popup_saved'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Configuración del popup guardada correctamente.', 'stock-sucursales'); ?></p>
                </div>
            <?php endif; ?>

            <div class="card">
                <h2 class="title"><?php _e('🔄 Sincronización de Inventario', 'stock-sucursales'); ?></h2>

                <div class="inside">
                    <p><?php _e('Sincroniza manualmente el inventario desde la API externa. El sistema se sincroniza automáticamente cada 5 minutos, pero puedes forzar una sincronización inmediata.', 'stock-sucursales'); ?></p>

                    <button type="button" id="sync-stock-btn" class="button button-primary" style="background-color: #0073aa; border-color: #0073aa;">
                        <span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
                        <?php _e('Sincronizar Inventario Ahora', 'stock-sucursales'); ?>
                    </button>

                    <div id="sync-status" style="margin-top: 15px;"></div>
                </div>
            </div>

            <div class="card">
                <h2 class="title"><?php _e('📜 Logs de Sincronización', 'stock-sucursales'); ?></h2>

                <div class="inside">
                    <?php if (!empty($sync_logs)) : ?>
                        <pre id="sync-logs" style="background: #2a4a2a; padding: 15px; color: #fff; border-radius: 5px; max-height: 400px; overflow-y: auto; font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.4;">
<?php echo esc_html(implode("\n", $sync_logs)); ?>
</pre>

                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="margin-top: 15px;">
                            <input type="hidden" name="action" value="clear_stock_logs">
                            <?php wp_nonce_field('stock_clear_logs', 'stock_clear_logs_nonce'); ?>
                            <button type="submit" class="button button-secondary" style="background-color: #d9534f; color: white; border-color: #d43f3a;">
                                <span class="dashicons dashicons-trash" style="margin-top: 3px;"></span>
                                <?php _e('Eliminar Logs', 'stock-sucursales'); ?>
                            </button>
                        </form>
                    <?php else : ?>
                        <p><?php _e('No hay registros de sincronización recientes.', 'stock-sucursales'); ?></p>
                        <p><em><?php _e('Los logs aparecerán aquí después de ejecutar una sincronización.', 'stock-sucursales'); ?></em></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <h2 class="title"><?php _e('🏷️ Formatear SKUs', 'stock-sucursales'); ?></h2>

                <div class="inside">
                    <p><?php _e('Formatea todos los SKUs de productos para que tengan exactamente 6 dígitos, agregando ceros a la izquierda cuando sea necesario. Esta función es útil para sincronizar con sistemas externos que requieren SKUs con formato específico.', 'stock-sucursales'); ?></p>
                    <p><strong><?php _e('Ejemplos:', 'stock-sucursales'); ?></strong></p>
                    <ul style="margin-left: 20px;">
                        <li>64 → 000064</li>
                        <li>1 → 000001</li>
                        <li>213 → 000213</li>
                        <li>610291 → 610291 (sin cambios)</li>
                    </ul>

                    <button type="button" id="format-skus-btn" class="button button-secondary" style="background-color: #f39c12; border-color: #e67e22; color: white;">
                        <span class="dashicons dashicons-tag" style="margin-top: 3px;"></span>
                        <?php _e('Formatear SKUs', 'stock-sucursales'); ?>
                    </button>

                    <div id="format-status" style="margin-top: 15px;"></div>
                </div>
            </div>

            <div class="card">
                <h2 class="title"><?php _e('Shortcodes Disponibles', 'stock-sucursales'); ?></h2>

                <div class="inside">
                    <p><?php _e('Este plugin proporciona varios shortcodes para mostrar información de stock por sucursales en tu sitio web:', 'stock-sucursales'); ?></p>

                    <h3><?php _e('1. Selector de Sucursal', 'stock-sucursales'); ?></h3>
                    <p><strong>Shortcode:</strong> <code>[sucursal_selector]</code></p>
                    <p><?php _e('Muestra un botón elegante con el nombre de la sucursal actual. Al hacer clic, abre el popup de selección de sucursales.', 'stock-sucursales'); ?></p>
                    <p><strong><?php _e('Características:', 'stock-sucursales'); ?></strong></p>
                    <ul style="margin-left: 20px;">
                        <li><?php _e('Icono de ubicación 📍', 'stock-sucursales'); ?></li>
                        <li><?php _e('Muestra la sucursal actualmente seleccionada', 'stock-sucursales'); ?></li>
                        <li><?php _e('Abre el popup configurado al hacer clic', 'stock-sucursales'); ?></li>
                        <li><?php _e('Diseño responsive y moderno', 'stock-sucursales'); ?></li>
                    </ul>
                    <p><strong><?php _e('Ejemplo de uso:', 'stock-sucursales'); ?></strong></p>
                    <pre><code>[sucursal_selector]</code></pre>

                    <hr>

                    <h3><?php _e('2. Stock por Sucursal', 'stock-sucursales'); ?></h3>
                    <p><strong>Shortcode:</strong> <code>[stock_sucursal]</code></p>
                    <p><?php _e('Muestra el stock disponible de un producto específico en la sucursal seleccionada.', 'stock-sucursales'); ?></p>
                    <p><strong><?php _e('Parámetros:', 'stock-sucursales'); ?></strong></p>
                    <ul>
                        <li><strong>product_id:</strong> <?php _e('ID del producto (opcional, por defecto usa el producto actual)', 'stock-sucursales'); ?></li>
                        <li><strong>sucursal:</strong> <?php _e('Slug de la sucursal específica (opcional, por defecto usa la sucursal seleccionada)', 'stock-sucursales'); ?></li>
                    </ul>
                    <p><strong><?php _e('Ejemplos de uso:', 'stock-sucursales'); ?></strong></p>
                    <pre><code>[stock_sucursal]</code></pre>
                    <pre><code>[stock_sucursal product_id="123"]</code></pre>
                    <pre><code>[stock_sucursal product_id="123" sucursal="centro"]</code></pre>

                    <hr>

                    <h3><?php _e('3. Stock Total', 'stock-sucursales'); ?></h3>
                    <p><strong>Shortcode:</strong> <code>[stock_total]</code></p>
                    <p><?php _e('Muestra el stock total de un producto sumando todas las sucursales.', 'stock-sucursales'); ?></p>
                    <p><strong><?php _e('Parámetros:', 'stock-sucursales'); ?></strong></p>
                    <ul>
                        <li><strong>product_id:</strong> <?php _e('ID del producto (opcional, por defecto usa el producto actual)', 'stock-sucursales'); ?></li>
                    </ul>
                    <p><strong><?php _e('Ejemplos de uso:', 'stock-sucursales'); ?></strong></p>
                    <pre><code>[stock_total]</code></pre>
                    <pre><code>[stock_total product_id="123"]</code></pre>

                    <hr>

                    <h3><?php _e('4. Lista de Stock por Sucursales', 'stock-sucursales'); ?></h3>
                    <p><strong>Shortcode:</strong> <code>[stock_list]</code></p>
                    <p><?php _e('Muestra una lista completa del stock de un producto en todas las sucursales.', 'stock-sucursales'); ?></p>
                    <p><strong><?php _e('Parámetros:', 'stock-sucursales'); ?></strong></p>
                    <ul>
                        <li><strong>product_id:</strong> <?php _e('ID del producto (opcional, por defecto usa el producto actual)', 'stock-sucursales'); ?></li>
                        <li><strong>show_empty:</strong> <?php _e('Mostrar sucursales sin stock (true/false, por defecto false)', 'stock-sucursales'); ?></li>
                    </ul>
                    <p><strong><?php _e('Ejemplos de uso:', 'stock-sucursales'); ?></strong></p>
                    <pre><code>[stock_list]</code></pre>
                    <pre><code>[stock_list product_id="123"]</code></pre>
                    <pre><code>[stock_list product_id="123" show_empty="true"]</code></pre>
                </div>
            </div>

            <div class="card">
                <h2 class="title"><?php _e('🎯 Configuración de Popup', 'stock-sucursales'); ?></h2>

                <div class="inside">
                    <p><?php _e('Configura el popup que se mostrará cuando los usuarios no tengan una sucursal seleccionada.', 'stock-sucursales'); ?></p>

                    <?php
                    $options = get_option('stock_sucursales_options', array());
                    $popup_id = isset($options['popup_id']) ? $options['popup_id'] : '';
                    ?>

                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="popup_id"><?php _e('ID del Popup de Elementor', 'stock-sucursales'); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="popup_id" name="popup_id" value="<?php echo esc_attr($popup_id); ?>" class="regular-text" />
                                    <p class="description">
                                        <?php _e('Ingresa el ID del popup de Elementor Pro que se mostrará cuando no hay sucursal seleccionada. Puedes encontrar el ID en Elementor → Plantillas → Popups.', 'stock-sucursales'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <input type="hidden" name="action" value="save_popup_settings">
                        <?php wp_nonce_field('save_popup_settings', 'popup_settings_nonce'); ?>

                        <p class="submit">
                            <button type="submit" class="button button-primary">
                                <span class="dashicons dashicons-saved" style="margin-top: 3px;"></span>
                                <?php _e('Guardar Configuración', 'stock-sucursales'); ?>
                            </button>
                        </p>
                    </form>

                    <?php if (!empty($popup_id)): ?>
                        <div style="margin-top: 20px; padding: 15px; background: #e8f5e8; border-left: 4px solid #46b450; border-radius: 3px;">
                            <p><strong><?php _e('✅ Popup configurado:', 'stock-sucursales'); ?></strong> ID <?php echo esc_html($popup_id); ?></p>
                            <p><?php _e('El popup se mostrará automáticamente cuando los usuarios visiten el sitio sin tener una sucursal seleccionada.', 'stock-sucursales'); ?></p>
                        </div>
                    <?php else: ?>
                        <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 3px;">
                            <p><strong><?php _e('⚠️ Popup no configurado', 'stock-sucursales'); ?></strong></p>
                            <p><?php _e('Para activar la funcionalidad de popup, ingresa el ID del popup de Elementor Pro arriba.', 'stock-sucursales'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <h2 class="title"><?php _e('🔗 API de Selección de Sucursal', 'stock-sucursales'); ?></h2>

                <div class="inside">
                    <p><?php _e('Puedes usar enlaces directos para que los usuarios seleccionen una sucursal específica automáticamente.', 'stock-sucursales'); ?></p>

                    <h3><?php _e('Endpoint disponible:', 'stock-sucursales'); ?></h3>
                    <pre><code><?php echo home_url(); ?>/wp-json/stock-sucursales/v1/select-sucursal?selected_sucursal={slug}</code></pre>

                    <h3><?php _e('Parámetros:', 'stock-sucursales'); ?></h3>
                    <ul style="margin-left: 20px;">
                        <li><strong>selected_sucursal</strong> (requerido): <?php _e('Slug de la sucursal a seleccionar', 'stock-sucursales'); ?></li>
                        <li><strong>redirect_to</strong> (opcional): <?php _e('URL de redirección personalizada', 'stock-sucursales'); ?></li>
                    </ul>

                    <h3><?php _e('Ejemplos de uso:', 'stock-sucursales'); ?></h3>
                    <pre><code><?php echo home_url(); ?>/wp-json/stock-sucursales/v1/select-sucursal?selected_sucursal=stock_espana</code></pre>
                    <pre><code><?php echo home_url(); ?>/wp-json/stock-sucursales/v1/select-sucursal?selected_sucursal=stock_sanber&redirect_to=<?php echo urlencode(home_url('/tienda')); ?></code></pre>

                    <div style="margin-top: 20px; padding: 15px; background: #e8f4f8; border-left: 4px solid #00a0d2; border-radius: 3px;">
                        <p><strong><?php _e('💡 Funcionalidad:', 'stock-sucursales'); ?></strong></p>
                        <ul style="margin-left: 20px; margin-bottom: 0;">
                            <li><?php _e('Guarda la selección en cookie (30 días)', 'stock-sucursales'); ?></li>
                            <li><?php _e('Guarda en perfil de usuario si está logueado', 'stock-sucursales'); ?></li>
                            <li><?php _e('Redirige a la página de origen o URL personalizada', 'stock-sucursales'); ?></li>
                            <li><?php _e('Valida que la sucursal exista antes de guardar', 'stock-sucursales'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2 class="title"><?php _e('Configuración de Sucursales', 'stock-sucursales'); ?></h2>

                <div class="inside">
                    <p><?php _e('Para configurar las sucursales disponibles, ve a:', 'stock-sucursales'); ?></p>
                    <p><strong>WooCommerce → Productos → Atributos</strong></p>
                    <p><?php _e('Busca el atributo "sucursal" y agrega los términos necesarios para cada sucursal.', 'stock-sucursales'); ?></p>

                    <p><strong><?php _e('Nota:', 'stock-sucursales'); ?></strong> <?php _e('Cada término debe tener un slug único que será utilizado internamente por el plugin.', 'stock-sucursales'); ?></p>
                </div>
            </div>
        </div>

        <style>
            .card {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                margin: 20px 0;
                max-width: 100%;
                box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
            }

            .card .title {
                margin: 0;
                padding: 12px 20px;
                border-bottom: 1px solid #eee;
                font-size: 14px;
                line-height: 1.4;
            }

            .card .inside {
                padding: 20px;
            }

            .card pre {
                background: #f1f1f1;
                padding: 10px;
                border-radius: 3px;
                overflow-x: auto;
            }

            .card code {
                background: #f1f1f1;
                padding: 2px 4px;
                border-radius: 3px;
                font-family: Consolas, Monaco, monospace;
            }

            .card pre code {
                background: transparent;
                padding: 0;
            }

            .card ul {
                padding-left: 20px;
            }

            .card hr {
                margin: 30px 0;
                border: none;
                border-top: 1px solid #eee;
            }

            #sync-status.loading,
            #format-status.loading {
                color: #0073aa;
                font-weight: bold;
            }

            #sync-status.success,
            #format-status.success {
                color: #46b450;
                font-weight: bold;
            }

            #sync-status.error,
            #format-status.error {
                color: #dc3232;
                font-weight: bold;
            }

            .sync-spinner {
                display: inline-block;
                width: 16px;
                height: 16px;
                border: 2px solid #f3f3f3;
                border-top: 2px solid #0073aa;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin-right: 8px;
            }

            @keyframes spin {
                0% {
                    transform: rotate(0deg);
                }

                100% {
                    transform: rotate(360deg);
                }
            }
        </style>

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Sincronización de inventario
                $('#sync-stock-btn').on('click', function() {
                    var $btn = $(this);
                    var $status = $('#sync-status');
                    var $logs = $('#sync-logs');

                    // Deshabilitar botón y mostrar loading
                    $btn.prop('disabled', true);
                    $btn.html('<span class="sync-spinner"></span><?php _e("Sincronizando...", "stock-sucursales"); ?>');
                    $status.removeClass('success error').addClass('loading');
                    $status.html('🔄 Iniciando sincronización...');

                    // Hacer petición AJAX
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sync_stock_manual',
                            nonce: '<?php echo wp_create_nonce('stock_manual_sync'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $status.removeClass('loading error').addClass('success');
                                $status.html('✅ ' + response.data.message);

                                // Actualizar logs si existen
                                if (response.data.logs && response.data.logs.length > 0) {
                                    var logsHtml = response.data.logs.join('\n');
                                    if ($logs.length > 0) {
                                        $logs.text(logsHtml);
                                    } else {
                                        // Si no había logs antes, recargar la página para mostrar la sección completa
                                        setTimeout(function() {
                                            location.reload();
                                        }, 2000);
                                    }
                                }
                            } else {
                                $status.removeClass('loading success').addClass('error');
                                $status.html('❌ Error: ' + (response.data || 'Error desconocido'));
                            }
                        },
                        error: function(xhr, status, error) {
                            $status.removeClass('loading success').addClass('error');
                            $status.html('❌ Error de conexión: ' + error);
                        },
                        complete: function() {
                            // Rehabilitar botón
                            $btn.prop('disabled', false);
                            $btn.html('<span class="dashicons dashicons-update" style="margin-top: 3px;"></span><?php _e("Sincronizar Inventario Ahora", "stock-sucursales"); ?>');
                        }
                    });
                });

                // Formateo de SKUs
                $('#format-skus-btn').on('click', function() {
                    var $btn = $(this);
                    var $status = $('#format-status');

                    // Confirmar acción
                    if (!confirm('¿Estás seguro de que quieres formatear todos los SKUs? Esta acción modificará los SKUs existentes y no se puede deshacer.')) {
                        return;
                    }

                    // Deshabilitar botón y mostrar loading
                    $btn.prop('disabled', true);
                    $btn.html('<span class="sync-spinner"></span><?php _e("Formateando SKUs...", "stock-sucursales"); ?>');
                    $status.removeClass('success error').addClass('loading');
                    $status.html('🏷️ Procesando productos...');

                    // Hacer petición AJAX
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'format_skus',
                            nonce: '<?php echo wp_create_nonce('format_skus_action'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $status.removeClass('loading error').addClass('success');
                                $status.html('✅ ' + response.data.message);

                                // Mostrar logs detallados
                                if (response.data.logs && response.data.logs.length > 0) {
                                    var logsHtml = '<div style="margin-top: 15px;"><strong>Detalles del formateo:</strong></div>';
                                    logsHtml += '<pre style="background: #2a4a2a; padding: 15px; color: #fff; border-radius: 5px; max-height: 200px; overflow-y: auto; font-family: \'Courier New\', monospace; font-size: 12px; line-height: 1.4; margin-top: 10px;">';
                                    logsHtml += response.data.logs.join('\n');
                                    logsHtml += '</pre>';
                                    $status.append(logsHtml);
                                }
                            } else {
                                $status.removeClass('loading success').addClass('error');
                                $status.html('❌ Error: ' + (response.data || 'Error desconocido'));
                            }
                        },
                        error: function(xhr, status, error) {
                            $status.removeClass('loading success').addClass('error');
                            $status.html('❌ Error de conexión: ' + error);
                        },
                        complete: function() {
                            // Rehabilitar botón
                            $btn.prop('disabled', false);
                            $btn.html('<span class="dashicons dashicons-tag" style="margin-top: 3px;"></span><?php _e("Formatear SKUs", "stock-sucursales"); ?>');
                        }
                    });
                });
            });
        </script>
<?php
    }
}
