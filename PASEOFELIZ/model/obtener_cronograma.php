<?php
/**
 * obtener_cronograma.php
 * Cronograma semanal de un paseador (pedidos asignados por día).
 *   GET ?id_paseador=X  -> lo pide el ADMIN (modal Cronograma)
 *   GET (sin parámetro) -> lo pide el propio PASEADOR desde su dashboard
 * Devuelve: paseador {id, nombre, puntuacion} y cronograma {1..7: [pedidos]}
 */
header("Access-Control-Allow-Origin: *");
include_once 'helpers.php';
include_once '../model/conexion.php';

verificarSesion();

$idPaseador = intval($_GET['id_paseador'] ?? 0);

if ($idPaseador) {
    // Consultar el cronograma de otro paseador requiere ser admin
    verificarAdmin();
} else {
    // Sin parámetro: el paseador logueado consulta el suyo
    $idPaseador = obtenerIdPaseadorSesion($conn);
}

// Datos del paseador
$stmt = $conn->prepare(
    "SELECT p.id_paseador, p.puntuacion, u.nombre
     FROM paseadores p JOIN usuarios u ON u.id = p.id_usuario
     WHERE p.id_paseador = ?"
);
$stmt->bind_param("i", $idPaseador);
$stmt->execute();
$pas = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$pas) responder(false, [], 'Paseador no encontrado.');

// Pedidos asignados por día, en orden cronológico REAL (hora exacta;
// los pedidos previos a la fase 15 quedan al final de su día)
$exprHora = pedidosTienenHoraExacta($conn) ? 'p.hora_paseo' : 'NULL AS hora_paseo';
$ordenHora = pedidosTienenHoraExacta($conn)
    ? "ORDER BY c.dia_semana ASC, p.hora_paseo IS NULL, p.hora_paseo ASC, p.franja_horaria ASC"
    : "ORDER BY c.dia_semana ASC, p.franja_horaria ASC";
$stmt = $conn->prepare(
    "SELECT c.id_cronograma, c.dia_semana,
            p.id_pedido, p.id_usuario, p.id_mascota, p.franja_horaria, $exprHora, p.duracion_min,
            p.modalidad, p.comportamiento, p.observaciones,
            p.direccion, p.barrio, p.referencia, p.instrucciones, p.lat, p.lng, p.estado,
            u.nombre AS cliente, i.telefono,
            m.nombre_mascota, m.avatar_mascota,
            pl.nombre AS plan
     FROM cronograma_paseos c
     JOIN pedidos_paseo p   ON p.id_pedido = c.id_pedido
     JOIN usuarios u        ON u.id = p.id_usuario
     LEFT JOIN info_usuario i ON i.id_usuario = p.id_usuario
     JOIN mascota_usuario m ON m.id_mascota = p.id_mascota
     LEFT JOIN planes_paseos pl ON pl.id_plan = p.id_plan
     WHERE c.id_paseador = ?
     $ordenHora"
);
$stmt->bind_param("i", $idPaseador);
$stmt->execute();
$res = $stmt->get_result();

$cronograma = [1 => [], 2 => [], 3 => [], 4 => [], 5 => [], 6 => [], 7 => []];
while ($row = $res->fetch_assoc()) {
    $dia = (int)$row['dia_semana'];
    if ($dia < 1 || $dia > 7) continue;
    $cronograma[$dia][] = [
        'id_cronograma'  => (int)$row['id_cronograma'],
        'id_pedido'      => (int)$row['id_pedido'],
        'id_usuario'     => (int)$row['id_usuario'],
        'cliente'        => $row['cliente'],
        'telefono'       => $row['telefono'] ?? '',
        'id_mascota'     => (int)$row['id_mascota'],
        'mascota'        => $row['nombre_mascota'],
        'avatar_mascota' => $row['avatar_mascota'] ?? '',
        'plan'           => $row['plan'] ?? '',
        'franja_horaria' => $row['franja_horaria'] ?? '',
        'hora_paseo'     => $row['hora_paseo'] ? substr($row['hora_paseo'], 0, 5) : null,
        'duracion_min'   => (int)$row['duracion_min'],
        'modalidad'      => $row['modalidad'],
        'comportamiento' => $row['comportamiento'] ?? '',
        'observaciones'  => $row['observaciones'] ?? '',
        'direccion'      => $row['direccion'],
        'barrio'         => $row['barrio'] ?? '',
        'referencia'     => $row['referencia'] ?? '',
        'instrucciones'  => $row['instrucciones'] ?? '',
        'lat'            => (float)$row['lat'],
        'lng'            => (float)$row['lng'],
        'estado_pedido'  => $row['estado'],
    ];
}
$stmt->close();

responder(true, [
    'paseador' => [
        'id'         => (int)$pas['id_paseador'],
        'nombre'     => $pas['nombre'],
        'puntuacion' => (float)$pas['puntuacion'],
    ],
    'cronograma' => $cronograma,
]);
?>
