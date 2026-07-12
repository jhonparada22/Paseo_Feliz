<?php
/**
 * calificar_paseo.php
 * (CLIENTE) Califica con estrellas (1-5) un paseo entregado de una de sus
 * mascotas dentro de los últimos 7 días (antes solo se podía el MISMO día:
 * si el cliente no calificaba antes de medianoche, perdía la posibilidad
 * para siempre). Recalcula paseadores.puntuacion como el promedio de todas
 * las calificaciones reales que ha recibido ese paseador.
 *
 * POST JSON: { "id_pedido": 5, "estrellas": 5,
 *              "comentario": "..." (opcional),
 *              "id_ruta": 12 (opcional: calificar un paseo específico;
 *                             sin él, el entregado más reciente) }
 * Respuesta: { success, puntuacion_paseador }
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once '../model/conexion.php';
include_once 'ActivityService.php';

define('DIAS_VENTANA_CALIFICACION', 7);

verificarSesion();
$idUsuario = (int)$_SESSION['usuario_id'];

$data       = leerJsonBody();
$idPedido   = intval($data['id_pedido'] ?? 0);
$estrellas  = intval($data['estrellas'] ?? 0);
$comentario = trim(substr($data['comentario'] ?? '', 0, 255));
$idRutaSel  = intval($data['id_ruta'] ?? 0);

if (!$idPedido || $estrellas < 1 || $estrellas > 5) {
    responder(false, [], 'Datos inválidos: se requiere id_pedido y estrellas (1-5).');
}

// Debe ser un paseo entregado de este cliente dentro de la ventana (evita
// calificar pedidos ajenos o paseos que todavía no terminaron).
$desde = date('Y-m-d', strtotime('-' . DIAS_VENTANA_CALIFICACION . ' days'));
$sql = "SELECT rp.id_ruta, r.id_paseador
        FROM ruta_paradas rp
        JOIN rutas r ON r.id_ruta = rp.id_ruta
        WHERE rp.id_pedido = ? AND rp.tipo = 'entrega' AND rp.hora_entrega IS NOT NULL
          AND rp.id_usuario_cliente = ? AND r.fecha_paseo >= ?";
if ($idRutaSel > 0) $sql .= " AND r.id_ruta = " . $idRutaSel;
$sql .= " ORDER BY rp.hora_entrega DESC LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iis", $idPedido, $idUsuario, $desde);
$stmt->execute();
$parada = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$parada) responder(false, [], 'No se encontró un paseo entregado en los últimos ' . DIAS_VENTANA_CALIFICACION . ' días para calificar.');

$idRuta     = (int)$parada['id_ruta'];
$idPaseador = (int)$parada['id_paseador'];

$stmt = $conn->prepare(
    "INSERT INTO calificaciones_paseo (id_pedido, id_ruta, id_paseador, id_usuario_cliente, estrellas, comentario)
     VALUES (?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE estrellas = VALUES(estrellas), comentario = VALUES(comentario)"
);
$stmt->bind_param("iiiiis", $idPedido, $idRuta, $idPaseador, $idUsuario, $estrellas, $comentario);
$stmt->execute();
$stmt->close();

// Recalcular el promedio del paseador con todas sus calificaciones reales
$stmt = $conn->prepare("SELECT AVG(estrellas) AS prom FROM calificaciones_paseo WHERE id_paseador = ?");
$stmt->bind_param("i", $idPaseador);
$stmt->execute();
$prom = (float)$stmt->get_result()->fetch_assoc()['prom'];
$stmt->close();

$stmt = $conn->prepare("UPDATE paseadores SET puntuacion = ? WHERE id_paseador = ?");
$stmt->bind_param("di", $prom, $idPaseador);
$stmt->execute();
$stmt->close();

responder(true, ['puntuacion_paseador' => round($prom, 1)], '¡Gracias por tu calificación!');
?>
