<?php
// scripts/sync-stock.php
defined('ABSPATH') || exit;

// Variable global para logs
$sync_logs = [];

/**
 * Función para registrar mensajes de log
 */
function log_sync_message($message)
{
    global $sync_logs;
    $timestamp = current_time('H:i:s');
    $sync_logs[] = "[$timestamp] $message";

    // También escribir al error_log de WordPress
    error_log($message);
}

/**
 * Obtener logs de la última sincronización
 */
function get_stock_sync_logs()
{
    $sync_logs = get_option('stock_sucursales_sync_logs', []);
    return is_array($sync_logs) ? $sync_logs : [];
}

function sync_branch_stock_run($manual = false)
{
    global $wpdb, $sync_logs;

    // Reiniciar logs si es ejecución manual
    if ($manual) {
        $sync_logs = [];
        log_sync_message("[STOCK SYNC] Iniciando sincronización manual de inventario");
    } else {
        log_sync_message("[STOCK SYNC] Iniciando sincronización automática (cron)");
    }

    $api_url = 'https://webser.uno/webservice/boutique_api/articulo.php?apikey=2pVjqWBx2os0OQNyo2Genafgtgf9qG0v61lUxOaKpIgOBMLwgGPmWYqmpRjNg';
    $table   = $wpdb->prefix . 'sucursal_stock';
    $lookup  = $wpdb->prefix . 'wc_product_meta_lookup';

    // Sucursales desde opciones
    $opts = get_option('stock_sucursales_options');
    $branch_map = $opts['sucursales'] ?? [];
    $branches = array_keys($branch_map);
    if (empty($branches)) {
        // Fallback razonable si no hay opciones
        $branches = ['stock_espana', 'stock_sanber'];
        log_sync_message("⚠️ Usando sucursales por defecto: " . implode(', ', $branches));
    } else {
        log_sync_message("📍 Sucursales configuradas: " . implode(', ', $branches));
    }

    // Evitar solapes del cron (lock corto)
    if (get_transient('sync_branch_stock_lock') && !$manual) {
        log_sync_message("⏸️ Sincronización ya en proceso, saltando ejecución");
        return;
    }
    set_transient('sync_branch_stock_lock', 1, 60);

    try {
        // 1) Fetch API
        log_sync_message("🌐 Conectando con API externa...");
        $resp = wp_remote_get($api_url, ['timeout' => 8]);
        if (is_wp_error($resp)) throw new Exception('Error API: ' . $resp->get_error_message());
        $code = wp_remote_retrieve_response_code($resp);
        if ($code !== 200) throw new Exception("Error HTTP $code al conectar con API");
        $body = wp_remote_retrieve_body($resp);
        $data = json_decode($body, true);
        if (!is_array($data)) throw new Exception('Respuesta JSON inválida de la API');
        if (empty($data)) throw new Exception('API devolvió un array vacío');

        log_sync_message("✅ API conectada correctamente. Productos recibidos: " . count($data));

        // 2) sku -> product_id
        log_sync_message("🔍 Obteniendo mapeo SKU → Product ID...");
        $sku_rows = $wpdb->get_results("SELECT product_id, sku FROM $lookup WHERE sku <> ''", ARRAY_A);
        if ($wpdb->last_error) {
            throw new Exception('Error al consultar SKUs: ' . $wpdb->last_error);
        }
        $sku_to_pid = [];
        foreach ($sku_rows as $r) {
            $sku = trim((string)$r['sku']);
            if ($sku !== '') {
                $sku_to_pid[$sku] = (int)$r['product_id'];
            }
        }
        log_sync_message("📦 SKUs encontrados en WooCommerce: " . count($sku_to_pid));

        // 3) product_ids presentes en API
        $pids = [];
        $matched_skus = 0;
        foreach ($data as $row) {
            $sku = trim((string)($row['codigo'] ?? ''));
            if ($sku !== '' && isset($sku_to_pid[$sku])) {
                $pids[$sku_to_pid[$sku]] = true;
                $matched_skus++;
            }
        }
        $pids = array_keys($pids);
        log_sync_message("🎯 Productos que coinciden por SKU: $matched_skus de " . count($data) . " de la API");

        // 4) stock actual por pid+sucursal
        $current = []; // [pid][slug] => qty(int)
        if (!empty($pids)) {
            log_sync_message("📊 Consultando stock actual en base de datos...");
            $pid_in = implode(',', array_map('intval', $pids));
            $branch_in = "'" . implode("','", array_map('esc_sql', $branches)) . "'";
            $rows = $wpdb->get_results(
                "SELECT product_id, sucursal_slug, stock_quantity
                 FROM $table
                 WHERE product_id IN ($pid_in) AND sucursal_slug IN ($branch_in)",
                ARRAY_A
            );
            if ($wpdb->last_error) {
                throw new Exception('Error al consultar stock actual: ' . $wpdb->last_error);
            }
            foreach ($rows as $r) {
                $pid = (int)$r['product_id'];
                $slug = (string)$r['sucursal_slug'];
                $current[$pid][$slug] = (int)$r['stock_quantity'];
            }
            log_sync_message("💾 Registros de stock actuales encontrados: " . count($rows));
        }

        // 4.5) Sincronizar stock mínimo (stockmin → _minimum_stock_quantity)
        if (!empty($pids)) {
            log_sync_message("📏 Sincronizando stock mínimo...");
            $min_stock_updates = 0;

            // Obtener stock mínimo actual de todos los productos
            $current_min_stock = [];
            $meta_rows = $wpdb->get_results(
                "SELECT post_id, meta_value FROM {$wpdb->postmeta} 
                 WHERE post_id IN (" . implode(',', array_map('intval', $pids)) . ") 
                 AND meta_key = '_minimum_stock_quantity'",
                ARRAY_A
            );
            foreach ($meta_rows as $row) {
                $current_min_stock[(int)$row['post_id']] = (int)$row['meta_value'];
            }

            // Procesar cada producto de la API
            foreach ($data as $row) {
                $sku = trim((string)($row['codigo'] ?? ''));
                if ($sku === '' || !isset($sku_to_pid[$sku])) continue;

                $pid = $sku_to_pid[$sku];
                $stockmin_raw = $row['stockmin'] ?? '';

                if ($stockmin_raw === null || $stockmin_raw === '') continue;

                // Normalizar y convertir a entero
                $stockmin_num = (float)str_replace(',', '.', trim((string)$stockmin_raw));
                $stockmin = (int)round($stockmin_num);

                // Comparar con el valor actual
                $current_min = $current_min_stock[$pid] ?? 0;
                if ($current_min !== $stockmin) {
                    update_post_meta($pid, '_minimum_stock_quantity', $stockmin);
                    $min_stock_updates++;
                }
            }

            if ($min_stock_updates > 0) {
                log_sync_message("📏 Stock mínimo actualizado en $min_stock_updates productos");
            } else {
                log_sync_message("📏 Stock mínimo ya está actualizado");
            }
        }

        // 4.6) Sincronizar precios (precioventa → _regular_price, preciopromo → _sale_price)
        if (!empty($pids)) {
            log_sync_message("💰 Sincronizando precios...");
            $price_updates = 0;

            // Obtener precios actuales de todos los productos
            $current_prices = [];
            $price_meta_rows = $wpdb->get_results(
                "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} 
                 WHERE post_id IN (" . implode(',', array_map('intval', $pids)) . ") 
                 AND meta_key IN ('_regular_price', '_sale_price')",
                ARRAY_A
            );
            foreach ($price_meta_rows as $row) {
                $current_prices[(int)$row['post_id']][$row['meta_key']] = $row['meta_value'];
            }

            // Procesar cada producto de la API
            foreach ($data as $row) {
                $sku = trim((string)($row['codigo'] ?? ''));
                if ($sku === '' || !isset($sku_to_pid[$sku])) continue;

                $pid = $sku_to_pid[$sku];
                $precioventa_raw = $row['precioventa'] ?? '';
                $preciopromo_raw = $row['preciopromo'] ?? '';

                // Procesar precio regular (precioventa)
                if ($precioventa_raw !== null && $precioventa_raw !== '') {
                    $regular_price = (float)str_replace(',', '.', trim((string)$precioventa_raw));
                    $regular_price_formatted = number_format($regular_price, 2, '.', '');

                    $current_regular = $current_prices[$pid]['_regular_price'] ?? '';
                    if ($current_regular !== $regular_price_formatted) {
                        update_post_meta($pid, '_regular_price', $regular_price_formatted);
                        $price_updates++;
                    }
                }

                // Procesar precio promocional (preciopromo)
                if ($preciopromo_raw !== null && $preciopromo_raw !== '') {
                    $promo_price = (float)str_replace(',', '.', trim((string)$preciopromo_raw));

                    if ($promo_price > 0) {
                        // Hay precio promocional válido
                        $promo_price_formatted = number_format($promo_price, 2, '.', '');
                        $current_sale = $current_prices[$pid]['_sale_price'] ?? '';

                        if ($current_sale !== $promo_price_formatted) {
                            update_post_meta($pid, '_sale_price', $promo_price_formatted);
                            $price_updates++;
                        }
                    } else {
                        // Sin precio promocional, limpiar _sale_price si existe
                        $current_sale = $current_prices[$pid]['_sale_price'] ?? '';
                        if ($current_sale !== '') {
                            update_post_meta($pid, '_sale_price', '');
                            $price_updates++;
                        }
                    }
                }

                // Actualizar _price (precio efectivo que muestra WooCommerce)
                $regular_price = $current_prices[$pid]['_regular_price'] ?? '';
                $sale_price = $current_prices[$pid]['_sale_price'] ?? '';

                // Recalcular después de las actualizaciones
                if ($price_updates > 0) {
                    $regular_price = get_post_meta($pid, '_regular_price', true);
                    $sale_price = get_post_meta($pid, '_sale_price', true);
                }

                $effective_price = (!empty($sale_price) && $sale_price > 0) ? $sale_price : $regular_price;
                if (!empty($effective_price)) {
                    update_post_meta($pid, '_price', $effective_price);
                }
            }

            if ($price_updates > 0) {
                log_sync_message("💰 Precios actualizados en $price_updates campos");
            } else {
                log_sync_message("💰 Precios ya están actualizados");
            }
        }

        // 5) calcular deltas por sucursal
        log_sync_message("🔄 Calculando cambios necesarios...");
        $deltas = []; // [slug] => array of [pid, slug, qty]
        foreach ($data as $row) {
            $sku = trim((string)($row['codigo'] ?? ''));
            if ($sku === '' || !isset($sku_to_pid[$sku])) continue;
            $pid = $sku_to_pid[$sku];

            foreach ($branches as $slug) {
                if (!array_key_exists($slug, $row)) continue; // si la API no trae ese campo, saltar
                $raw = $row[$slug];

                // Normalizar número
                if ($raw === null || $raw === '') continue;
                $num = (float)str_replace(',', '.', trim((string)$raw));
                $qty = (int)round($num); // columna INT(11)

                $cur = $current[$pid][$slug] ?? null;
                if ($cur === null || (int)$cur !== $qty) {
                    $deltas[$slug][] = [$pid, $slug, $qty];
                }
            }
        }

        // Contar total de cambios
        $total_changes = 0;
        foreach ($deltas as $slug => $rows) {
            $count = count($rows);
            if ($count > 0) {
                log_sync_message("📝 Sucursal '$slug': $count cambios pendientes");
                $total_changes += $count;
            }
        }

        if ($total_changes === 0) {
            log_sync_message("✨ No hay cambios necesarios. Stock ya está actualizado");
        }

        // 6) upsert por lotes
        $total_updates = 0;
        if (!empty($deltas)) {
            log_sync_message("💾 Aplicando cambios en base de datos...");
            $now = current_time('mysql');
            foreach ($deltas as $slug => $rows) {
                if (empty($rows)) continue;
                $chunk = 200;
                for ($i = 0; $i < count($rows); $i += $chunk) {
                    $slice = array_slice($rows, $i, $chunk);
                    $values = [];
                    $slug_sql = esc_sql($slug);
                    foreach ($slice as [$pid, $_s, $q]) {
                        $pid = (int)$pid;
                        $q   = (int)$q;
                        $values[] = "($pid,'$slug_sql',$q,'$now','$now')";
                    }
                    $sql = "
                        INSERT INTO $table (product_id, sucursal_slug, stock_quantity, created_at, updated_at)
                        VALUES " . implode(',', $values) . "
                        ON DUPLICATE KEY UPDATE
                          stock_quantity = VALUES(stock_quantity),
                          updated_at = IF(VALUES(stock_quantity) <> stock_quantity, VALUES(updated_at), updated_at)
                    ";
                    $result = $wpdb->query($sql);
                    if ($wpdb->last_error) {
                        throw new Exception("Error en upsert para sucursal {$slug}: " . $wpdb->last_error);
                    }
                    $total_updates += count($slice);
                }
            }
        }

        // Logging de éxito
        $has_updates = $total_updates > 0 ||
            (isset($min_stock_updates) && $min_stock_updates > 0) ||
            (isset($price_updates) && $price_updates > 0);

        if ($has_updates) {
            log_sync_message("✅ Sincronización completada exitosamente");
            if ($total_updates > 0) {
                log_sync_message("📊 Registros de stock procesados: $total_updates");
            }
            if (isset($min_stock_updates) && $min_stock_updates > 0) {
                log_sync_message("📏 Productos con stock mínimo actualizado: $min_stock_updates");
            }
            if (isset($price_updates) && $price_updates > 0) {
                log_sync_message("💰 Campos de precio actualizados: $price_updates");
            }
        } else {
            log_sync_message("✅ Sincronización completada - Sin cambios necesarios");
        }

        // 7) (OPCIONAL) modo autoritativo → poner en 0 lo que no llega en la API (DESACTIVADO por defecto)
        /*
        $pid_in = !empty($pids) ? implode(',', array_map('intval', $pids)) : '';
        if ($pid_in !== '') {
            $branch_in = "'" . implode("','", array_map('esc_sql', $branches)) . "'";
            $wpdb->query("
                UPDATE $table s
                LEFT JOIN (
                    SELECT l.product_id, t.codigo
                    FROM $lookup l
                    JOIN (SELECT TRIM(JSON_EXTRACT(j.value, '$.codigo')) codigo
                          FROM JSON_TABLE('".esc_sql($body)."', '$[*]' COLUMNS(value JSON PATH '$')) j
                    ) t ON t.codigo = l.sku
                ) x ON x.product_id = s.product_id
                SET s.stock_quantity = 0, s.updated_at = '".esc_sql($now)."'
                WHERE s.sucursal_slug IN ($branch_in) AND s.product_id IN ($pid_in) AND x.product_id IS NULL
            ");
        }
        */
    } catch (Throwable $e) {
        log_sync_message("❌ ERROR: " . $e->getMessage());
    } finally {
        delete_transient('sync_branch_stock_lock');

        // Guardar logs en opción de WP para pasarlos al frontend
        if ($manual || !empty($sync_logs)) {
            update_option('stock_sucursales_sync_logs', $sync_logs);
        }

        log_sync_message("🔚 Sincronización finalizada");
    }
}

// NOTA: El cron automático de WordPress ha sido deshabilitado
// Para programar la sincronización automática, usar cron del servidor:
// */5 * * * * /usr/bin/php /ruta/completa/al/plugin/scripts/sync-stock-from-server.php >/dev/null 2>&1

// Las funciones están disponibles para:
// 1. Ejecución manual desde admin (botón en interfaz)
// 2. Ejecución desde cron del servidor (sync-stock-from-server.php)
