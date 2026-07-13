<?php
/**
 * verificar_bd_admin.php
 * (ADMIN) Chequeo de salud de la base de datos: conexión + que cada tabla
 * del sistema exista y responda a una consulta simple. Devuelve el detalle
 * de qué está fallando (tablas faltantes o con error), si algo falla.
 *
 * GET sin parámetros. Respuesta:
 * { success, ok: bool, resumen, tablas_ok: n, problemas: [ {tabla, error} ] }
 */
header("Access-Control-Allow-Origin: *");
include_once 'helpers.php';
include_once '../model/conexion.php';

verificarAdmin();

// Todas las tablas que la aplicación usa hoy
$TABLAS = [
    'usuarios', 'admin', 'info_usuario', 'mascota_usuario', 'membresias',
    'paseadores', 'gps_paseadores', 'historial_gps',
    'pedidos_paseo', 'pedidos_adiestramiento', 'pedidos_hospedaje',
    'cronograma_paseos', 'cronograma_adiestramiento',
    'rutas', 'ruta_paradas', 'ruta_clientes', 'estados_ruta', 'estados_parada',
    'pagos', 'precios_servicios', 'descuentos_servicios', 'planes_paseos',
    'calificaciones_paseo', 'notificaciones',
    'conversaciones', 'mensajes', 'escribiendo',
    'adopcion', 'solicitudes_adopcion', 'solicitudes_cancelacion',
    'evidencias_paseo', 'actividad_vista', 'sesiones_recordadas',
    'codigos_verificacion',
];

$problemas = [];
$ok = 0;

foreach ($TABLAS as $t) {
    try {
        $res = @$conn->query("SELECT 1 FROM `$t` LIMIT 1");
        if ($res === false) {
            $problemas[] = ['tabla' => $t, 'error' => $conn->error ?: 'La tabla no existe o no responde.'];
        } else {
            $ok++;
            if ($res instanceof mysqli_result) $res->close();
        }
    } catch (Throwable $e) {
        $problemas[] = ['tabla' => $t, 'error' => $e->getMessage()];
    }
}

// Latencia de una consulta trivial, como referencia
$inicio = microtime(true);
$conn->query("SELECT 1");
$latenciaMs = round((microtime(true) - $inicio) * 1000, 1);

$todoBien = count($problemas) === 0;
responder(true, [
    'ok'          => $todoBien,
    'resumen'     => $todoBien
        ? "La base de datos está funcionando correctamente. $ok tablas verificadas."
        : count($problemas) . ' problema(s) encontrado(s) de ' . count($TABLAS) . ' tablas revisadas.',
    'tablas_ok'   => $ok,
    'tablas_total'=> count($TABLAS),
    'latencia_ms' => $latenciaMs,
    'problemas'   => $problemas,
]);
?>
