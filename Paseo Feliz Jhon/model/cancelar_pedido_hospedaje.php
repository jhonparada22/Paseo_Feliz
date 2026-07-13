<?php
/**
 * cancelar_pedido_hospedaje.php
 * (ADMIN) Cancela una reserva de hospedaje: el pedido pasa a 'cancelado',
 * se desactiva la membresía de esa mascota y se notifica al cliente. No
 * hay cronograma/paseador que limpiar (hospedaje usa fase_logistica, no
 * cronograma) — se puede cancelar en cualquier fase salvo ya entregado.
 * Sin reembolso automático: eso se gestiona por fuera.
 *
 * Requiere la migración que agrega motivo_cancelacion / cancelado_por /
 * fecha_cancelacion a pedidos_hospedaje.
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
    "SELECT p.id_pedido, p.id_usuario, p.id_mascota, p.estado, p.fase_logistica, mu.nombre_mascota
     FROM pedidos_hospedaje p
     LEFT JOIN mascota_usuario mu ON mu.id_mascota = p.id_mascota
     WHERE p.id_pedido = ?"
);
$stmt->bind_param("i", $idPedido);
$stmt->execute();
$pedido = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pedido) responder(false, [], 'El pedido no existe.');
if ($pedido['estado'] === 'cancelado') responder(false, [], 'Este pedido ya está cancelado.');
if ($pedido['fase_logistica'] === 'entregado') responder(false, [], 'No se puede cancelar: la mascota ya fue entregada de vuelta.');

$idUsuario = (int)$pedido['id_usuario'];
$idMascota = (int)$pedido['id_mascota'];
$mascota   = $pedido['nombre_mascota'] ?: 'la mascota';

$conn->begin_transaction();
try {
    // 2. Pedido -> cancelado (con motivo y actor)
    $stmt = $conn->prepare(
        "UPDATE pedidos_hospedaje
         SET estado = 'cancelado', motivo_cancelacion = ?,
             cancelado_por = 'admin', fecha_cancelacion = NOW()
         WHERE id_pedido = ?"
    );
    $stmt->bind_param("si", $motivo, $idPedido);
    $stmt->execute();
    $stmt->close();

    // 3. Desactivar la membresía de hospedaje de ESTA mascota
    $stmt = $conn->prepare(
        "UPDATE membresias SET hospedaje = 0
         WHERE id_usuario = ? AND id_mascota = ?"
    );
    $stmt->bind_param("ii", $idUsuario, $idMascota);
    $stmt->execute();
    $stmt->close();

    // 4. Notificar al cliente (feed interno)
    crearNotificacionInterna($conn, $idUsuario, null,
        'sistema', "Tu reserva de hospedaje para $mascota fue cancelada. Motivo: $motivo. Si tienes dudas, contáctanos por el centro de ayuda.");

    $conn->commit();

    // Aviso por el chat del bot: best-effort y FUERA de la transacción — si
    // falla, la cancelación ya se guardó y no se debe deshacer por esto.
    try {
        enviarMensajeBot($conn, $idUsuario,
            "Tu reserva de hospedaje para $mascota fue cancelada.\n\nMotivo: $motivo");
    } catch (Throwable $e) {
        error_log('cancelar_pedido_hospedaje.php: enviarMensajeBot falló: ' . $e->getMessage());
    }

    responder(true, [
        'id_pedido' => $idPedido,
    ], "Reserva de $mascota cancelada y cliente notificado.");
} catch (Exception $e) {
    $conn->rollback();
    responder(false, [], 'Error al cancelar el pedido: ' . $e->getMessage());
}
?>
