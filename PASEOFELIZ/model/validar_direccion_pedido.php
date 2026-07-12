<?php
/**
 * validar_direccion_pedido.php
 * (ADMIN) Valida la dirección de un pedido de paseos que quedó en
 * 'en_validacion' tras el pago. El pin lo puso el cliente en el wizard
 * (buscador Nominatim o clic en el mapa) y puede estar mal ubicado: este
 * paso humano evita que el paseador llegue a la dirección equivocada.
 *
 * POST JSON:
 *   { "id_pedido": 5, "accion": "aprobar" }
 *   { "id_pedido": 5, "accion": "corregir", "lat": 7.89, "lng": -72.50 }
 *     -> corrige el pin y aprueba en el mismo paso
 *
 * Al aprobar: estado -> 'listo_para_asignar' (entra a la cola del admin)
 * y se notifica al cliente.
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once '../model/conexion.php';
include_once 'ActivityService.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

verificarAdmin();
$data     = leerJsonBody();
$idPedido = intval($data['id_pedido'] ?? 0);
$accion   = $data['accion'] ?? 'aprobar';

if (!$idPedido) responder(false, [], 'id_pedido requerido.');
if (!in_array($accion, ['aprobar', 'corregir'])) responder(false, [], 'Acción no válida.');

// El pedido debe estar pendiente de validación
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
if ($pedido['estado'] !== 'en_validacion') {
    responder(false, [], 'Este pedido no está pendiente de validación (estado: ' . $pedido['estado'] . ').');
}

if ($accion === 'corregir') {
    $lat = floatval($data['lat'] ?? 0);
    $lng = floatval($data['lng'] ?? 0);
    if (!$lat || !$lng) responder(false, [], 'Coordenadas inválidas para la corrección.');
    $stmt = $conn->prepare(
        "UPDATE pedidos_paseo
         SET lat = ?, lng = ?, ubicacion_validada = 1, estado = 'listo_para_asignar'
         WHERE id_pedido = ?"
    );
    $stmt->bind_param("ddi", $lat, $lng, $idPedido);
} else {
    $stmt = $conn->prepare(
        "UPDATE pedidos_paseo
         SET ubicacion_validada = 1, estado = 'listo_para_asignar'
         WHERE id_pedido = ?"
    );
    $stmt->bind_param("i", $idPedido);
}
$stmt->execute();
$stmt->close();

$mascota = $pedido['nombre_mascota'] ?: 'tu mascota';
crearNotificacionInterna($conn, (int)$pedido['id_usuario'], null,
    'sistema', "La dirección de recogida de $mascota fue verificada. Estamos asignando tu paseador.");

ActivityService::registrar($conn, [
    'servicio' => 'paseos', 'tipo' => 'direccion_validada',
    'titulo' => "Dirección validada — $mascota",
    'descripcion' => $accion === 'corregir' ? 'El admin corrigió el pin y aprobó la dirección.' : 'La dirección del cliente fue validada correctamente.',
    'id_cliente' => (int)$pedido['id_usuario'], 'id_mascota' => (int)$pedido['id_mascota'],
    'id_pedido' => $idPedido, 'id_referencia' => $idPedido,
]);

responder(true, ['id_pedido' => $idPedido, 'estado' => 'listo_para_asignar'],
    $accion === 'corregir'
        ? 'Pin corregido y dirección aprobada. El pedido entró a la cola de asignación.'
        : 'Dirección aprobada. El pedido entró a la cola de asignación.');
?>
