<?php
/**
 * resolver_cancelacion.php
 * (ADMIN) Resuelve una solicitud de cancelación creada por el paseador
 * (marcar_paseo_dia.php → 'cancelar').
 *
 *   aprobar  → cancela el paseo DE VERDAD (misma lógica que antes vivía
 *              directo en marcar_paseo_dia): cancela las paradas de
 *              recogida/entrega que aún no se cerraron, y notifica al cliente.
 *   rechazar → el paseo continúa; se notifica al paseador.
 *
 * POST JSON: { id_solicitud, accion: 'aprobar'|'rechazar', nota? }
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once '../model/conexion.php';
include_once 'bot_informes.php';

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
    "SELECT sc.*, mu.nombre_mascota, pa.id_usuario AS id_usuario_paseador
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

$idPedido  = (int)$sol['id_pedido'];
$idRuta    = (int)$sol['id_ruta'];
$idCliente = (int)$sol['id_cliente'];
$motivo    = $sol['motivo'];
$mascota   = $sol['nombre_mascota'] ?: 'la mascota';

function marcarSolicitud($conn, $idSolicitud, $estado, $idAdmin, $nota) {
    $stmt = $conn->prepare(
        "UPDATE solicitudes_cancelacion
         SET estado = ?, resuelto_por = ?, nota_admin = ?, resuelto_en = NOW()
         WHERE id_solicitud = ? AND estado = 'pendiente'"
    );
    $stmt->bind_param("sisi", $estado, $idAdmin, $nota, $idSolicitud);
    $stmt->execute();
    $stmt->close();
}

if ($accion === 'aprobar') {
    // Cancelar las paradas que aún no se entregaron ni cancelaron
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
        marcarSolicitud($conn, $idSolicitud, 'rechazada', $idAdmin, 'El paseo ya había sido entregado; no se pudo cancelar.');
        responder(false, [], 'El paseo ya fue entregado; no se puede cancelar.');
    }

    marcarSolicitud($conn, $idSolicitud, 'aprobada', $idAdmin, $nota);

    // Si esta cancelación era lo único que quedaba, cerrar la ruta:
    // sin esto quedaba "en curso" para siempre (el front nunca llega al 100%)
    finalizarRutaSiCompleta($conn, $idRuta);

    crearNotificacionInterna($conn, $idCliente, $idRuta, 'sistema',
        "El paseo de hoy de $mascota fue cancelado. Motivo: $motivo. El paseador puede darte más detalles por el chat.");
    try {
        enviarMensajeBot($conn, $idCliente,
            "El paseo de hoy de $mascota fue cancelado.\n\nMotivo: $motivo");
    } catch (Throwable $e) {
        error_log('resolver_cancelacion.php: enviarMensajeBot (cliente) falló: ' . $e->getMessage());
    }

    responder(true, ['estado' => 'aprobada'], 'Cancelación aprobada. El cliente fue notificado.');
}

// ── rechazar ──────────────────────────────────────────────────────────
marcarSolicitud($conn, $idSolicitud, 'rechazada', $idAdmin, $nota);

if (!empty($sol['id_usuario_paseador'])) {
    $msgPaseador = "El administrador rechazó tu solicitud de cancelación del paseo de $mascota. Continúa con el paseo con normalidad."
        . ($nota !== '' ? " Nota: $nota" : '');
    crearNotificacionInterna($conn, (int)$sol['id_usuario_paseador'], $idRuta, 'sistema', $msgPaseador);
    try {
        enviarMensajeBot($conn, (int)$sol['id_usuario_paseador'], $msgPaseador);
    } catch (Throwable $e) {
        error_log('resolver_cancelacion.php: enviarMensajeBot (paseador) falló: ' . $e->getMessage());
    }
}

responder(true, ['estado' => 'rechazada'], 'Solicitud rechazada. El paseo continúa y el paseador fue avisado.');
?>
