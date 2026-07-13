<?php
/**
 * obtener_mi_pedido_hospedaje.php
 * Devuelve el pedido de hospedaje ACTIVO más reciente del cliente en
 * sesión (fuente de verdad para el modo exprés "añadir otra mascota al
 * servicio" — Hospedaje no tiene dashboard propio como Paseos, así que
 * el banner de inicio_cliente.js usa este endpoint liviano en su lugar).
 *
 * GET sin parámetros (todo sale de la sesión).
 * Respuesta: { success, tiene_servicio, pedido:{...} }
 */
header("Access-Control-Allow-Origin: *");
include_once 'helpers.php';
include_once '../model/conexion.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

verificarSesion();
$idUsuario = (int)$_SESSION['usuario_id'];

$ahoraColombia = "CONVERT_TZ(NOW(), '+00:00', '-05:00')";
$stmt = $conn->prepare(
    "SELECT p.id_pedido, p.id_mascota, p.fecha_entrada, p.fecha_salida,
            p.comportamiento, p.direccion, p.barrio, p.referencia, p.instrucciones,
            p.lat, p.lng, mu.nombre_mascota
     FROM pedidos_hospedaje p
     JOIN membresias m       ON m.id_usuario = p.id_usuario AND m.id_mascota = p.id_mascota
     JOIN mascota_usuario mu ON mu.id_mascota = p.id_mascota
     WHERE p.id_usuario = ?
       AND p.estado IN ('pagado', 'listo_para_asignar')
       AND m.hospedaje = 1
       AND m.fecha_inicio_hospedaje IS NOT NULL
       AND DATE_ADD(m.fecha_inicio_hospedaje, INTERVAL 30 DAY) > $ahoraColombia
     ORDER BY p.fecha_creacion DESC
     LIMIT 1"
);
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$pedido = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pedido) {
    responder(true, ['tiene_servicio' => false], 'Sin servicio de hospedaje activo.');
}

responder(true, [
    'tiene_servicio' => true,
    'pedido' => [
        'id_pedido'      => (int)$pedido['id_pedido'],
        'id_mascota'     => (int)$pedido['id_mascota'],
        'mascota'        => $pedido['nombre_mascota'],
        'fecha_entrada'  => $pedido['fecha_entrada'],
        'fecha_salida'   => $pedido['fecha_salida'],
        'comportamiento' => $pedido['comportamiento'] ?? '',
        'direccion'      => $pedido['direccion'],
        'barrio'         => $pedido['barrio'] ?? '',
        'referencia'     => $pedido['referencia'] ?? '',
        'instrucciones'  => $pedido['instrucciones'] ?? '',
        'lat'            => (float)$pedido['lat'],
        'lng'            => (float)$pedido['lng'],
    ],
]);
?>
