<?php
/**
 * guardar_cronograma_adiestramiento.php
 * El ADMIN asigna un entrenador (paseador) a un pedido de adiestramiento.
 * Mismo patrón que guardar_cronograma.php pero para pedidos_adiestramiento
 * / cronograma_adiestramiento — el entrenador es un paseador (mismo rol).
 *
 * POST JSON: { "id_pedido":5, "id_paseador":3, "dias":[1,3,5] }
 * -> reemplaza la asignación completa de ese pedido (borra la anterior y
 *    guarda la nueva), a diferencia de paseos que permite ir sumando días
 *    de varios pedidos por día — aquí cada pedido tiene un solo entrenador.
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once '../model/conexion.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

verificarAdmin();
$data = leerJsonBody();

$idPedido   = intval($data['id_pedido'] ?? 0);
$idPaseador = intval($data['id_paseador'] ?? 0);
$dias       = array_values(array_unique(array_map('intval', $data['dias'] ?? [])));
$dias       = array_values(array_filter($dias, function ($d) { return $d >= 1 && $d <= 7; }));

if (!$idPedido || !$idPaseador || !$dias) {
    responder(false, [], 'Faltan datos: pedido, entrenador y al menos un día.');
}

$s = $conn->prepare("SELECT id_paseador FROM paseadores WHERE id_paseador = ?");
$s->bind_param("i", $idPaseador);
$s->execute();
if ($s->get_result()->num_rows === 0) { $s->close(); responder(false, [], 'El entrenador no existe.'); }
$s->close();

$s = $conn->prepare("SELECT estado FROM pedidos_adiestramiento WHERE id_pedido = ?");
$s->bind_param("i", $idPedido);
$s->execute();
$row = $s->get_result()->fetch_assoc();
$s->close();
if (!$row) responder(false, [], 'El pedido no existe.');
// 'cancelado' incluido a propósito: el admin debe poder reasignar un pedido
// que canceló por error o cuyas circunstancias cambiaron, sin quedar
// bloqueado para siempre (ver reactivarSiCancelado más abajo).
if (!in_array($row['estado'], ['listo_para_asignar', 'pagado', 'cancelado'])) {
    responder(false, [], 'Este pedido no está pagado/listo para asignar (estado: ' . $row['estado'] . ').');
}

// Si el pedido estaba cancelado, reactivarlo al reasignarlo: sin esto
// quedaría con entrenador pero seguiría mostrándose cancelado en el
// dashboard del cliente y con la membresía desactivada.
function reactivarSiCancelado($conn, $idPedido) {
    $s = $conn->prepare("SELECT estado, id_usuario, id_mascota FROM pedidos_adiestramiento WHERE id_pedido = ?");
    $s->bind_param("i", $idPedido);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$row || $row['estado'] !== 'cancelado') return;

    $u = $conn->prepare(
        "UPDATE pedidos_adiestramiento
         SET estado = 'listo_para_asignar', motivo_cancelacion = NULL, cancelado_por = NULL, fecha_cancelacion = NULL
         WHERE id_pedido = ?"
    );
    $u->bind_param("i", $idPedido);
    $u->execute();
    $u->close();

    $m = $conn->prepare("UPDATE membresias SET adiestramiento = 1 WHERE id_usuario = ? AND id_mascota = ?");
    $m->bind_param("ii", $row['id_usuario'], $row['id_mascota']);
    $m->execute();
    $m->close();
}

$conn->begin_transaction();
try {
    $d = $conn->prepare("DELETE FROM cronograma_adiestramiento WHERE id_pedido = ?");
    $d->bind_param("i", $idPedido);
    $d->execute();
    $d->close();

    $i = $conn->prepare("INSERT INTO cronograma_adiestramiento (id_paseador, id_pedido, dia_semana) VALUES (?, ?, ?)");
    foreach ($dias as $dia) {
        $i->bind_param("iii", $idPaseador, $idPedido, $dia);
        $i->execute();
    }
    $i->close();
    reactivarSiCancelado($conn, $idPedido);

    $conn->commit();
    $DIAS_NOMBRE = [1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábado', 7 => 'domingo'];
    $nombres = implode(', ', array_map(function ($d) use ($DIAS_NOMBRE) { return $DIAS_NOMBRE[$d]; }, $dias));
    responder(true, [], "Entrenador asignado ($nombres).");
} catch (Exception $e) {
    $conn->rollback();
    responder(false, [], 'Error al asignar: ' . $e->getMessage());
}
?>
