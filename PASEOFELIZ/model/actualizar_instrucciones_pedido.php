<?php
/**
 * actualizar_instrucciones_pedido.php
 * El CLIENTE edita las instrucciones y observaciones de su pedido de paseos
 * (acción "Editar instrucciones del paseo" del dashboard post-compra).
 *
 * POST JSON: { id_pedido, instrucciones, observaciones }
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once '../model/conexion.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

verificarSesion();
$idUsuario = (int)$_SESSION['usuario_id'];

$body = leerJsonBody();
$idPedido      = (int)($body['id_pedido'] ?? 0);
$instrucciones = trim((string)($body['instrucciones'] ?? ''));
$observaciones = trim((string)($body['observaciones'] ?? ''));

if (!$idPedido) responder(false, [], 'id_pedido requerido.');

// La columna instrucciones es VARCHAR(255); observaciones es TEXT
if (mb_strlen($instrucciones) > 255) $instrucciones = mb_substr($instrucciones, 0, 255);
if (mb_strlen($observaciones) > 1000) $observaciones = mb_substr($observaciones, 0, 1000);

// Solo el dueño del pedido, y solo mientras el servicio está activo
$stmt = $conn->prepare(
    "UPDATE pedidos_paseo
     SET instrucciones = ?, observaciones = ?
     WHERE id_pedido = ? AND id_usuario = ?
       AND estado IN ('pagado', 'listo_para_asignar')"
);
$stmt->bind_param("ssii", $instrucciones, $observaciones, $idPedido, $idUsuario);
$stmt->execute();
$filas = $stmt->affected_rows;
$stmt->close();

// affected_rows = 0 también ocurre si se guardó el mismo texto; verificar pertenencia
if ($filas === 0) {
    $stmt = $conn->prepare(
        "SELECT 1 FROM pedidos_paseo WHERE id_pedido = ? AND id_usuario = ?"
    );
    $stmt->bind_param("ii", $idPedido, $idUsuario);
    $stmt->execute();
    $existe = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if (!$existe) responder(false, [], 'Pedido no encontrado o no te pertenece.');
}

responder(true, [
    'instrucciones' => $instrucciones,
    'observaciones' => $observaciones,
], 'Instrucciones actualizadas.');
?>
