<?php
/**
 * estado_servicio_hospedaje.php
 * Estado consolidado de la reserva de hospedaje ACTIVA más reciente del
 * CLIENTE logueado. A diferencia de Paseos/Adiestramiento, aquí no hay
 * asignación de personal — la recogida y entrega las hace la van de un
 * administrador, y el estado se mueve con `fase_logistica` (ver
 * avanzar_fase_hospedaje.php, que usa el admin).
 *
 * GET opcional ?id_mascota=X (selector de mascota).
 * Respuesta: { success, tiene_servicio, servicio:{...} }
 */
header("Access-Control-Allow-Origin: *");
include_once 'helpers.php';
include_once '../model/conexion.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
date_default_timezone_set('America/Bogota');

verificarSesion();
$idUsuario = (int)$_SESSION['usuario_id'];

$ahoraColombia = "CONVERT_TZ(NOW(), '+00:00', '-05:00')";
$idMascotaFiltro = intval($_GET['id_mascota'] ?? 0);

$sqlPedido =
    "SELECT p.id_pedido, p.id_mascota, p.fecha_entrada, p.fecha_salida, p.cantidad_noches,
            p.comportamiento, p.observaciones,
            p.direccion, p.barrio, p.referencia, p.instrucciones,
            p.lat, p.lng, p.ubicacion_validada, p.total, p.estado,
            p.fase_logistica, p.hora_recogida_real, p.hora_entrega_real, p.fecha_creacion,
            m.fecha_inicio_hospedaje,
            DATE_ADD(m.fecha_inicio_hospedaje, INTERVAL 30 DAY) AS fecha_renovacion,
            mu.nombre_mascota, mu.avatar_mascota
     FROM pedidos_hospedaje p
     JOIN membresias m       ON m.id_usuario = p.id_usuario AND m.id_mascota = p.id_mascota
     JOIN mascota_usuario mu ON mu.id_mascota = p.id_mascota
     WHERE p.id_usuario = ?
       AND p.estado IN ('pagado', 'listo_para_asignar')
       AND m.hospedaje = 1
       AND m.fecha_inicio_hospedaje IS NOT NULL
       AND DATE_ADD(m.fecha_inicio_hospedaje, INTERVAL 30 DAY) > $ahoraColombia";

if ($idMascotaFiltro > 0) {
    $sqlPedido .= " AND p.id_mascota = ?";
    $stmt = $conn->prepare($sqlPedido . " ORDER BY p.fecha_creacion DESC LIMIT 1");
    $stmt->bind_param("ii", $idUsuario, $idMascotaFiltro);
} else {
    $stmt = $conn->prepare($sqlPedido . " ORDER BY p.fecha_creacion DESC LIMIT 1");
    $stmt->bind_param("i", $idUsuario);
}
$stmt->execute();
$pedido = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pedido) {
    responder(true, ['tiene_servicio' => false], 'Sin servicio de hospedaje activo.');
}

$idPedido = (int)$pedido['id_pedido'];

// ── Historial reciente ─────────────────────────────────────────────
$FASE_TEXTO = [
    'confirmado'         => 'Compra confirmada',
    'recogida_en_camino' => 'La van salió a recoger a tu mascota',
    'en_hospedaje'       => 'Tu mascota ya está en hospedaje',
    'entrega_en_camino'  => 'La van salió a entregar a tu mascota',
    'entregado'          => 'Tu mascota fue entregada',
];
$fase = $pedido['fase_logistica'] ?: 'confirmado';

$historial = [];
$historial[] = ['fecha' => $pedido['fecha_creacion'], 'tipo' => 'compra', 'texto' => 'Compra confirmada'];
if ($pedido['hora_recogida_real']) {
    $historial[] = ['fecha' => $pedido['hora_recogida_real'], 'tipo' => 'recogida', 'texto' => 'Mascota recogida'];
}
if ($pedido['hora_entrega_real']) {
    $historial[] = ['fecha' => $pedido['hora_entrega_real'], 'tipo' => 'entrega', 'texto' => 'Mascota entregada'];
}
usort($historial, function ($a, $b) { return strcmp($b['fecha'], $a['fecha']); });

$finMembresia  = new DateTime($pedido['fecha_renovacion'], new DateTimeZone('America/Bogota'));
$ahora         = new DateTime('now', new DateTimeZone('America/Bogota'));
$diasRestantes = $ahora < $finMembresia ? $ahora->diff($finMembresia)->days : 0;

responder(true, [
    'tiene_servicio' => true,
    'servicio' => [
        'fase' => $fase,
        'fase_texto' => $FASE_TEXTO[$fase] ?? $FASE_TEXTO['confirmado'],
        'pedido' => [
            'id_pedido'       => $idPedido,
            'id_mascota'      => (int)$pedido['id_mascota'],
            'mascota'         => $pedido['nombre_mascota'],
            'avatar_mascota'  => $pedido['avatar_mascota'] ?? '',
            'fecha_entrada'   => $pedido['fecha_entrada'],
            'fecha_salida'    => $pedido['fecha_salida'],
            'cantidad_noches' => (int)$pedido['cantidad_noches'],
            'comportamiento'  => $pedido['comportamiento'] ?? '',
            'observaciones'   => $pedido['observaciones'] ?? '',
            'direccion'       => $pedido['direccion'],
            'barrio'          => $pedido['barrio'] ?? '',
            'referencia'      => $pedido['referencia'] ?? '',
            'instrucciones'   => $pedido['instrucciones'] ?? '',
            'lat'             => (float)$pedido['lat'],
            'lng'             => (float)$pedido['lng'],
            'ubicacion_validada' => (int)$pedido['ubicacion_validada'] === 1,
            'total'           => (float)$pedido['total'],
            'fecha_compra'    => $pedido['fecha_creacion'],
        ],
        'membresia' => [
            'inicio'         => $pedido['fecha_inicio_hospedaje'],
            'renovacion'     => $pedido['fecha_renovacion'],
            'dias_restantes' => $diasRestantes,
        ],
        'hora_recogida_real' => $pedido['hora_recogida_real'],
        'hora_entrega_real'  => $pedido['hora_entrega_real'],
        'historial'          => array_slice($historial, 0, 8),
    ],
]);
?>
