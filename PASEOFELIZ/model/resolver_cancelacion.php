<?php
/**
 * resolver_cancelacion.php
 * (ADMIN) Resuelve una solicitud de cancelación creada por el paseador
 * (marcar_paseo_dia.php → 'cancelar').
 *
 *   aprobar  → cancela el paseo DE VERDAD (misma lógica que antes vivía en
 *              marcar_paseo_dia): cancela las paradas no entregadas,
 *              transiciona el paseo programado a 'cancelado' (cancelado_por
 *              admin) y notifica al cliente.
 *   rechazar → el paseo continúa; se notifica al paseador.
 *
 * POST JSON: { id_solicitud, accion: 'aprobar'|'rechazar', nota? }
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once 'helpers_paseos_programados.php';
include_once 'ActivityService.php';
include_once '../model/conexion.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

verificarAdmin();
$idAdmin = (int)$_SESSION['usuario_id'];

$data        = leerJsonBody();
$idSolicitud = intval($data['id_solicitud'] ?? 0);
$accion      = $data['accion'] ?? '';
$nota        = trim(substr($data['nota'] ?? '', 0, 160));

if (!$idSolicitud || !in_array($accion, ['aprobar', 'rechazar'], true)) {
    responder(false, [], 'Datos inválidos: se requiere id_solicitud y acción (aprobar|rechazar).');
}

// La solicitud debe existir y seguir pendiente (evita doble resolución)
$stmt = $conn->prepare(
    "SELECT sc.*, DATE(sc.creado_en) AS fecha_paseo, mu.nombre_mascota,
            pa.id_usuario AS id_usuario_paseador
     FROM solicitudes_cancelacion sc
     LEFT JOIN mascota_usuario mu ON mu.id_mascota = sc.id_mascota
     LEFT JOIN paseadores pa      ON pa.id_paseador = sc.id_paseador
     WHERE sc.id_solicitud = ?"
);
$stmt->bind_param("i", $idSolicitud);
$stmt->execute();
$sol = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sol) responder(false, [], 'La solicitud no existe.');
if ($sol['estado'] !== 'pendiente') {
    responder(false, [], 'Esta solicitud ya fue ' . $sol['estado'] . '.');
}

$idPedido   = (int)$sol['id_pedido'];
$idRuta     = (int)$sol['id_ruta'];
$idCliente  = (int)$sol['id_cliente'];
$idPaseador = (int)$sol['id_paseador'];
$idMascota  = (int)$sol['id_mascota'];
$fechaPaseo = $sol['fecha_paseo'];
$motivo     = $sol['motivo'];
$mascota    = $sol['nombre_mascota'] ?: 'la mascota';

if ($accion === 'aprobar') {
    // 1. Cancelar las paradas que aún no se entregaron ni cancelaron
    $stmt = $conn->prepare(
        "UPDATE ruta_paradas
         SET hora_cancelacion = NOW(), motivo_cancelacion = ?, id_estado = 4
         WHERE id_ruta = ? AND id_pedido = ? AND tipo IN ('recogida','entrega')
           AND hora_entrega IS NULL AND hora_cancelacion IS NULL"
    );
    $stmt->bind_param("sii", $motivo, $idRuta, $idPedido);
    $stmt->execute();
    $afectadas = $stmt->affected_rows;
    $stmt->close();

    if (!$afectadas) {
        // El paseo ya avanzó (entregado) entre la solicitud y ahora
        marcarSolicitud($conn, $idSolicitud, 'rechazada', $idAdmin,
            'El paseo ya había sido entregado; no se pudo cancelar.');
        marcarActividadResuelta($conn, $idSolicitud);
        responder(false, [], 'El paseo ya fue entregado; no se puede cancelar.');
    }

    // 2. Reflejar en el paseo programado + log de eventos
    transicionPaseoProgramado($conn, $idPedido, $fechaPaseo, 'cancelado', [
        'actor' => 'admin', 'motivo' => $motivo, 'cancelado_por' => 'admin',
    ]);

    // 3. Cerrar la solicitud y notificar al cliente
    marcarSolicitud($conn, $idSolicitud, 'aprobada', $idAdmin, $nota);
    marcarActividadResuelta($conn, $idSolicitud);
    crearNotificacionInterna($conn, $idCliente, $idRuta, 'sistema',
        "El paseo de hoy de $mascota fue cancelado. Motivo: $motivo. El paseador puede darte más detalles por el chat.");

    ActivityService::registrar($conn, [
        'servicio'    => 'paseos', 'tipo' => 'cancelacion_aprobada',
        'titulo'      => "Cancelación aprobada — $mascota",
        'descripcion' => "Motivo: $motivo",
        'id_cliente'  => $idCliente, 'id_paseador' => $idPaseador, 'id_mascota' => $idMascota,
        'id_pedido'   => $idPedido, 'id_ruta' => $idRuta, 'resuelto' => 1,
    ]);

    responder(true, ['estado' => 'aprobada'], 'Cancelación aprobada. El cliente fue notificado.');
}

// ── rechazar ──────────────────────────────────────────────────────────
marcarSolicitud($conn, $idSolicitud, 'rechazada', $idAdmin, $nota);
marcarActividadResuelta($conn, $idSolicitud);

if (!empty($sol['id_usuario_paseador'])) {
    crearNotificacionInterna($conn, (int)$sol['id_usuario_paseador'], $idRuta, 'sistema',
        "El administrador rechazó tu solicitud de cancelación del paseo de $mascota. Continúa con el paseo con normalidad."
        . ($nota !== '' ? " Nota: $nota" : ''));
}

ActivityService::registrar($conn, [
    'servicio'    => 'paseos', 'tipo' => 'cancelacion_rechazada',
    'titulo'      => "Cancelación rechazada — $mascota",
    'descripcion' => $nota !== '' ? "Nota: $nota" : 'El paseo continúa.',
    'id_cliente'  => $idCliente, 'id_paseador' => $idPaseador, 'id_mascota' => $idMascota,
    'id_pedido'   => $idPedido, 'id_ruta' => $idRuta, 'resuelto' => 1,
]);

responder(true, ['estado' => 'rechazada'], 'Solicitud rechazada. El paseo continúa y el paseador fue avisado.');


// ── helpers locales ───────────────────────────────────────────────────
function marcarSolicitud($conn, $idSolicitud, $estado, $idAdmin, $nota)
{
    $stmt = $conn->prepare(
        "UPDATE solicitudes_cancelacion
         SET estado = ?, resuelto_por = ?, nota_admin = ?, resuelto_en = NOW()
         WHERE id_solicitud = ? AND estado = 'pendiente'"
    );
    $stmt->bind_param("sisi", $estado, $idAdmin, $nota, $idSolicitud);
    $stmt->execute();
    $stmt->close();
}

function marcarActividadResuelta($conn, $idSolicitud)
{
    try {
        $stmt = $conn->prepare(
            "UPDATE actividad_sistema SET resuelto = 1
             WHERE tipo = 'cancelacion_solicitada' AND id_referencia = ?"
        );
        $stmt->bind_param("i", $idSolicitud);
        $stmt->execute();
        $stmt->close();
    } catch (mysqli_sql_exception $e) { /* best-effort */ }
}
?>
