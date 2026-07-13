<?php
/**
 * avanzar_fase_hospedaje.php
 * El ADMIN mueve la fase de logística de un pedido de hospedaje (recogida
 * y entrega las hace la van de un administrador, no un paseador asignado
 * — por eso no hay selector de personal, solo botones de "siguiente paso").
 *
 * POST JSON: { "id_pedido": 12, "fase": "recogida_en_camino" }
 * Fases válidas, en orden: confirmado -> recogida_en_camino -> en_hospedaje
 *   -> entrega_en_camino -> entregado
 * Solo se permite avanzar un paso a la vez (o retroceder uno, por si el
 * admin se equivocó de botón).
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once '../model/conexion.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

verificarAdmin();
$data = leerJsonBody();

$FASES = ['confirmado', 'recogida_en_camino', 'en_hospedaje', 'entrega_en_camino', 'entregado'];

$idPedido   = intval($data['id_pedido'] ?? 0);
$faseNueva  = trim($data['fase'] ?? '');

if (!$idPedido || !in_array($faseNueva, $FASES, true)) {
    responder(false, [], 'Datos inválidos.');
}

$stmt = $conn->prepare("SELECT estado, fase_logistica FROM pedidos_hospedaje WHERE id_pedido = ?");
$stmt->bind_param("i", $idPedido);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) responder(false, [], 'El pedido no existe.');
if (!in_array($row['estado'], ['listo_para_asignar', 'pagado'])) {
    responder(false, [], 'Este pedido no está pagado/activo (estado: ' . $row['estado'] . ').');
}

$faseActual = $row['fase_logistica'] ?: 'confirmado';
$idxActual  = array_search($faseActual, $FASES, true);
$idxNueva   = array_search($faseNueva, $FASES, true);

if ($idxNueva !== $idxActual + 1 && $idxNueva !== $idxActual - 1) {
    responder(false, [], 'Solo puedes avanzar o retroceder un paso a la vez desde "' . $faseActual . '".');
}

// Al confirmar recogida (llegar a en_hospedaje) o entrega (llegar a
// entregado) se registra la hora real; si se retrocede, se limpia.
$horaRecogida = $row['hora_recogida_real'] ?? null;
$horaEntrega  = $row['hora_entrega_real'] ?? null;
$ahora = date('Y-m-d H:i:s');

if ($faseNueva === 'en_hospedaje')      $horaRecogida = $ahora;
if ($faseNueva === 'entregado')         $horaEntrega  = $ahora;
if ($idxNueva < $idxActual && $faseActual === 'en_hospedaje')  $horaRecogida = null;
if ($idxNueva < $idxActual && $faseActual === 'entregado')     $horaEntrega  = null;

$stmt = $conn->prepare(
    "UPDATE pedidos_hospedaje SET fase_logistica = ?, hora_recogida_real = ?, hora_entrega_real = ? WHERE id_pedido = ?"
);
$stmt->bind_param("sssi", $faseNueva, $horaRecogida, $horaEntrega, $idPedido);
$stmt->execute();
$stmt->close();

$ETIQUETAS = [
    'confirmado'         => 'Compra confirmada',
    'recogida_en_camino' => 'Recogida en camino',
    'en_hospedaje'       => 'Mascota en hospedaje',
    'entrega_en_camino'  => 'Entrega en camino',
    'entregado'          => 'Entregado',
];
responder(true, ['fase' => $faseNueva], 'Estado actualizado: ' . $ETIQUETAS[$faseNueva] . '.');
?>
