<?php
// Cargar WordPress (ajusta la ruta según la ubicación del script)
require_once __DIR__ . '/../../../../wp-load.php';

// Evitar acceso directo desde el navegador
if (php_sapi_name() !== 'cli') {
    die("❌ Este script solo debe ejecutarse desde la línea de comandos.");
}

// Obtener la ruta base del plugin
$plugin_root = dirname(__DIR__); // Sube un nivel desde /scripts/

// Incluir las funciones necesarias del plugin
require_once $plugin_root . '/scripts/sync-stock.php';

// Configurar tiempo ilimitado para evitar interrupciones
set_time_limit(0);

// Configurar memoria para procesos grandes
ini_set('memory_limit', '512M');

// Ejecutar sincronización
error_log("🚀 [SERVER CRON] Iniciando sincronización de inventario desde servidor...");

try {
    // Ejecutar la función de sincronización (modo automático, no manual)
    sync_branch_stock_run(false);
    error_log("✅ [SERVER CRON] Sincronización completada exitosamente.");
} catch (Exception $e) {
    error_log("❌ [SERVER CRON] Error durante la sincronización: " . $e->getMessage());
    exit(1); // Código de error para cron
} catch (Throwable $e) {
    error_log("❌ [SERVER CRON] Error crítico durante la sincronización: " . $e->getMessage());
    exit(1); // Código de error para cron
}

error_log("🔚 [SERVER CRON] Script de sincronización finalizado.");
exit(0); // Código de éxito para cron
