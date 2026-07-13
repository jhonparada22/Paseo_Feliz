<?php
/**
 * obtener_pedidos_hospedaje.php
 * Lista para el ADMIN los pedidos de hospedaje (pedidos_hospedaje), con
 * su fase de logística de la van (recogida/entrega) — no hay asignación
 * de personal en este servicio, la recogida/entrega la maneja la van de
 * un administrador directamente.
 * GET ?estado=listo_para_asignar (por defecto) | todos
 */
header("Access-Control-Allow-Origin: *");
include_once 'helpers.php';
include_once '../model/conexion.php';

verificarAdmin();

$estado = $_GET['estado'] ?? 'listo_para_asignar';

$sqlBase =
    "SELECT p.id_pedido, p.id_usuario, p.id_mascota, p.fecha_entrada, p.fecha_salida,
            p.cantidad_noches, p.comportamiento, p.observaciones,
            p.direccion, p.barrio, p.referencia, p.instrucciones,
            p.lat, p.lng, p.total, p.estado, p.fase_logistica,
            p.hora_recogida_real, p.hora_entrega_real, p.fecha_creacion,
            u.nombre AS cliente, i.telefono,
            m.nombre_mascota, m.avatar_mascota
     FROM pedidos_hospedaje p
     JOIN usuarios u        ON u.id = p.id_usuario
     LEFT JOIN info_usuario i ON i.id_usuario = p.id_usuario
     JOIN mascota_usuario m ON m.id_mascota = p.id_mascota";

if ($estado === 'todos') {
    $stmt = $conn->prepare("$sqlBase ORDER BY p.fecha_entrada ASC");
} else {
    $stmt = $conn->prepare("$sqlBase WHERE p.estado = ? ORDER BY p.fecha_entrada ASC");
    $stmt->bind_param("s", $estado);
}
$stmt->execute();
$res = $stmt->get_result();

$pedidos = [];
while ($row = $res->fetch_assoc()) {
    $pedidos[] = [
        'id_pedido'          => (int)$row['id_pedido'],
        'id_usuario'         => (int)$row['id_usuario'],
        'cliente'            => $row['cliente'],
        'telefono'           => $row['telefono'] ?? '',
        'id_mascota'         => (int)$row['id_mascota'],
        'mascota'            => $row['nombre_mascota'],
        'avatar_mascota'     => $row['avatar_mascota'] ?? '',
        'fecha_entrada'      => $row['fecha_entrada'],
        'fecha_salida'       => $row['fecha_salida'],
        'cantidad_noches'    => (int)$row['cantidad_noches'],
        'comportamiento'     => $row['comportamiento'] ?? '',
        'observaciones'      => $row['observaciones'] ?? '',
        'direccion'          => $row['direccion'],
        'barrio'             => $row['barrio'] ?? '',
        'referencia'         => $row['referencia'] ?? '',
        'instrucciones'      => $row['instrucciones'] ?? '',
        'lat'                => (float)$row['lat'],
        'lng'                => (float)$row['lng'],
        'total'              => (float)$row['total'],
        'estado'             => $row['estado'],
        'fase_logistica'     => $row['fase_logistica'] ?? 'confirmado',
        'hora_recogida_real' => $row['hora_recogida_real'],
        'hora_entrega_real'  => $row['hora_entrega_real'],
        'fecha_compra'       => $row['fecha_creacion'],
    ];
}
$stmt->close();

responder(true, ['pedidos' => $pedidos]);
?>
