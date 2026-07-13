<?php
/**
 * cancelar_pedido_paseos.php
 * (ADMIN) Cancela el servicio de paseos de una mascota: el pedido pasa a
 * 'cancelado' (valor que existía en el enum pero que nada usaba), se
 * desactiva la membresía de esa mascota, se limpia su cronograma semanal,
 * se cancelan sus paradas pendientes de hoy/futuras y se notifica al
 * cliente. Sin reembolso automático: eso se gestiona por fuera.
 *
 * Requiere la migración 2026_07_fase10_estabilizacion.sql
 * (columnas motivo_cancelacion / cancelado_por / fecha_cancelacion).
 *
 * POST JSON: { "id_pedido": 5, "motivo": "..." }
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once '../model/conexion.php';
include_once 'bot_informes.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

verificarAdmin();
$data     = leerJsonBody();
$idPedido = intval($data['id_pedido'] ?? 0);
$motivo   = trim(substr($data['motivo'] ?? '', 0, 160));

if (!$idPedido) responder(false, [], 'id_pedido requerido.');
if ($motivo === '') responder(false, [], 'Debes indicar el motivo de la cancelación.');

// ── 1. Pedido cancelable ──────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT p.id_pedido, p.id_usuario, p.id_mascota, p.estado, mu.nombre_mascota
     FROM pedidos_paseo p
     LEFT JOIN mascota_usuario mu ON mu.id_mascota = p.id_mascota
     WHERE p.id_pedido = ?"
);
$stmt->bind_param("i", $idPedido);
$stmt->execute();
$pedido = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pedido) responder(false, [], 'El pedido no existe.');
if ($pedido['estado'] === 'cancelado') responder(false, [], 'Este pedido ya está cancelado.');

$idUsuario = (int)$pedido['id_usuario'];
$idMascota = (int)$pedido['id_mascota'];
$mascota   = $pedido['nombre_mascota'] ?: 'la mascota';

$conn->begin_transaction();
try {
    // 2. Pedido -> cancelado (con motivo y actor)
    $stmt = $conn->prepare(
        "UPDATE pedidos_paseo
         SET estado = 'cancelado', motivo_cancelacion = ?,
             cancelado_por = 'admin', fecha_cancelacion = NOW()
         WHERE id_pedido = ?"
    );
    $stmt->bind_param("si", $motivo, $idPedido);
    $stmt->execute();
    $stmt->close();

    // 3. Desactivar la membresía de paseos de ESTA mascota
    $stmt = $conn->prepare(
        "UPDATE membresias SET paseos = 0
         WHERE id_usuario = ? AND id_mascota = ?"
    );
    $stmt->bind_param("ii", $idUsuario, $idMascota);
    $stmt->execute();
    $stmt->close();

    // 4. Sacar el pedido del cronograma semanal
    $stmt = $conn->prepare("DELETE FROM cronograma_paseos WHERE id_pedido = ?");
    $stmt->bind_param("i", $idPedido);
    $stmt->execute();
    $stmt->close();

    // 5. Cancelar paradas pendientes de hoy o futuras de este pedido
    //    (rutas no canceladas; lo ya entregado no se toca)
    $stmt = $conn->prepare(
        "UPDATE ruta_paradas rp
         JOIN rutas r ON r.id_ruta = rp.id_ruta
         SET rp.hora_cancelacion = NOW(), rp.motivo_cancelacion = ?, rp.id_estado = 4
         WHERE rp.id_pedido = ? AND r.fecha_paseo >= CURDATE() AND r.id_estado <> 5
           AND rp.hora_entrega IS NULL AND rp.hora_cancelacion IS NULL"
    );
    $stmt->bind_param("si", $motivo, $idPedido);
    $stmt->execute();
    $paradasCanceladas = $stmt->affected_rows;
    $stmt->close();

    // 6. Notificar al cliente (feed interno)
    crearNotificacionInterna($conn, $idUsuario, null,
        'sistema', "Tu servicio de paseos para $mascota fue cancelado. Motivo: $motivo. Si tienes dudas, contáctanos por el centro de ayuda.");

    $conn->commit();

    // Aviso por el chat del bot: best-effort y FUERA de la transacción — si
    // falla, la cancelación ya se guardó y no se debe deshacer por esto.
    try {
        enviarMensajeBot($conn, $idUsuario,
            "Tu servicio de paseos para $mascota fue cancelado.\n\nMotivo: $motivo");
    } catch (Throwable $e) {
        error_log('cancelar_pedido_paseos.php: enviarMensajeBot falló: ' . $e->getMessage());
    }

    responder(true, [
        'id_pedido'          => $idPedido,
        'paradas_canceladas' => $paradasCanceladas,
    ], "Servicio de $mascota cancelado y cliente notificado.");
} catch (Exception $e) {
    $conn->rollback();
    responder(false, [], 'Error al cancelar el pedido: ' . $e->getMessage());
}
?>
