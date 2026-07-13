<?php
/**
 * calificar_paseo.php
 * (CLIENTE) Califica con estrellas (1-5) el paseo de HOY ya entregado de
 * una de sus mascotas. Recalcula paseadores.puntuacion como el promedio
 * de todas las calificaciones reales que ha recibido ese paseador.
 *
 * POST JSON: { "id_pedido": 5, "estrellas": 5, "comentario": "..." (opcional) }
 * Respuesta: { success, puntuacion_paseador }
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once '../model/conexion.php';

verificarSesion();
$idUsuario = (int)$_SESSION['usuario_id'];

$data       = leerJsonBody();
$idPedido   = intval($data['id_pedido'] ?? 0);
$estrellas  = intval($data['estrellas'] ?? 0);
$comentario = trim(substr($data['comentario'] ?? '', 0, 255));

if (!$idPedido || $estrellas < 1 || $estrellas > 5) {
    responder(false, [], 'Datos inválidos: se requiere id_pedido y estrellas (1-5).');
}

// Debe ser un paseo de HOY, entregado, y de este cliente (evita calificar
// pedidos ajenos o paseos que todavía no terminaron).
$hoy = date('Y-m-d');
$stmt = $conn->prepare(
    "SELECT rp.id_ruta, r.id_paseador
     FROM ruta_paradas rp
     JOIN rutas r ON r.id_ruta = rp.id_ruta
     WHERE rp.id_pedido = ? AND rp.tipo = 'entrega' AND rp.hora_entrega IS NOT NULL
       AND rp.id_usuario_cliente = ? AND r.fecha_paseo = ?
     ORDER BY rp.hora_entrega DESC LIMIT 1"
);
$stmt->bind_param("iis", $idPedido, $idUsuario, $hoy);
$stmt->execute();
$parada = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$parada) responder(false, [], 'No se encontró un paseo entregado hoy para calificar.');

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
