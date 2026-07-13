<?php
/**
 * obtener_clientes_mapa.php
 * (ADMIN) Devuelve las ubicaciones de los clientes con pedidos de paseo
 * activos para pintarlas como puntos pequeños en el mapa del administrador.
 * Con eso el admin puede asignar un paseador en cualquier momento o usar
 * la ubicación del cliente como punto al trazar una ruta.
 *
 * GET sin parámetros. Respuesta:
 * { success, clientes: [ { id_pedido, id_cliente, cliente, telefono,
 *   id_mascota, mascota, direccion, barrio, lat, lng, modalidad,
 *   franja, estado_pedido, paseador_asignado } ] }
 */
header("Access-Control-Allow-Origin: *");
include_once 'helpers.php';
include_once '../model/conexion.php';

verificarAdmin();

$sql = "SELECT pp.id_pedido, pp.id_usuario, pp.id_mascota,
               pp.direccion, pp.barrio, pp.lat, pp.lng,
               pp.modalidad, pp.franja_horaria, pp.estado,
               u.nombre AS cliente, iu.telefono,
               mu.nombre_mascota,
               (SELECT us.nombre
                  FROM cronograma_paseos cp
                  JOIN paseadores pa ON pa.id_paseador = cp.id_paseador
                  JOIN usuarios us   ON us.id = pa.id_usuario
                 WHERE cp.id_pedido = pp.id_pedido
                 LIMIT 1) AS paseador_asignado
        FROM pedidos_paseo pp
        JOIN usuarios u        ON u.id = pp.id_usuario
        LEFT JOIN info_usuario iu ON iu.id_usuario = pp.id_usuario
        LEFT JOIN mascota_usuario mu ON mu.id_mascota = pp.id_mascota
        WHERE pp.estado NOT IN ('cancelado','pendiente_pago','pago_fallido')
          AND pp.lat <> 0 AND pp.lng <> 0
        ORDER BY u.nombre ASC";

$res = $conn->query($sql);

$clientes = [];
while ($row = $res->fetch_assoc()) {
    $clientes[] = [
        'id_pedido'  => (int)$row['id_pedido'],
        'id_cliente' => (int)$row['id_usuario'],
        'cliente'    => $row['cliente'],
        'telefono'   => $row['telefono'],
        'id_mascota' => $row['id_mascota'] ? (int)$row['id_mascota'] : null,
        'mascota'    => $row['nombre_mascota'],
        'direccion'  => $row['direccion'],
        'barrio'     => $row['barrio'],
        'lat'        => (float)$row['lat'],
        'lng'        => (float)$row['lng'],
        'modalidad'  => $row['modalidad'],
        'franja'     => $row['franja_horaria'],
        'estado_pedido'      => $row['estado'],
        'paseador_asignado'  => $row['paseador_asignado'],
    ];
}

responder(true, ['clientes' => $clientes]);
?>
