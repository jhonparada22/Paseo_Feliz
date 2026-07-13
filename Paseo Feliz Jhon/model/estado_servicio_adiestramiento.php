<?php
/**
 * estado_servicio_adiestramiento.php
 * Estado consolidado del servicio de adiestramiento del CLIENTE logueado.
 * Mismo patrón que estado_servicio_paseos.php, pero sin ruta/GPS en vivo
 * (el entrenador da la sesión, no hay seguimiento de ubicación) y sin
 * conteo de sesiones usadas (no hay sistema de asistencia todavía).
 *
 * Vistas del dashboard:
 *   - pendiente_asignacion : pagó pero aún no tiene entrenador asignado
 *   - entrenador_asignado  : tiene entrenador en el cronograma; se
 *                            calcula su próxima sesión
 *
 * GET opcional ?id_mascota=X (selector de mascota, igual que paseos).
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

// ── 1. Pedido pagado más reciente con membresía de adiestramiento vigente ─
$sqlPedido =
    "SELECT p.id_pedido, p.id_mascota, p.duracion_min, p.dias_preferidos,
            p.franja_horaria, p.fecha_inicio, p.comportamiento, p.observaciones,
            p.direccion, p.barrio, p.referencia, p.instrucciones,
            p.lat, p.lng, p.ubicacion_validada, p.total, p.estado, p.fecha_creacion,
            p.cantidad_sesiones,
            m.fecha_inicio_adiestramiento,
            DATE_ADD(m.fecha_inicio_adiestramiento, INTERVAL 30 DAY) AS fecha_renovacion,
            mu.nombre_mascota, mu.avatar_mascota
     FROM pedidos_adiestramiento p
     JOIN membresias m       ON m.id_usuario = p.id_usuario AND m.id_mascota = p.id_mascota
     JOIN mascota_usuario mu ON mu.id_mascota = p.id_mascota
     WHERE p.id_usuario = ?
       AND p.estado IN ('pagado', 'listo_para_asignar')
       AND m.adiestramiento = 1
       AND m.fecha_inicio_adiestramiento IS NOT NULL
       AND DATE_ADD(m.fecha_inicio_adiestramiento, INTERVAL 30 DAY) > $ahoraColombia";

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
    responder(true, ['tiene_servicio' => false], 'Sin servicio de adiestramiento activo.');
}

$idPedido = (int)$pedido['id_pedido'];

// ── 2. Asignación en el cronograma semanal (entrenador = paseador) ────
$stmt = $conn->prepare(
    "SELECT c.id_paseador, c.dia_semana, c.fecha_creacion
     FROM cronograma_adiestramiento c
     WHERE c.id_pedido = ?
     ORDER BY c.dia_semana ASC"
);
$stmt->bind_param("i", $idPedido);
$stmt->execute();
$res = $stmt->get_result();
$crono = [];
while ($row = $res->fetch_assoc()) $crono[] = $row;
$stmt->close();

$asignacion    = null;
$proximaSesion = null;
$diasNombres   = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
                   5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

if ($crono) {
    $hoyDia = (int)date('N');

    $mejorDelta = 8;
    $filaProxima = $crono[0];
    foreach ($crono as $fila) {
        $delta = ((int)$fila['dia_semana'] - $hoyDia + 7) % 7;
        if ($delta < $mejorDelta) { $mejorDelta = $delta; $filaProxima = $fila; }
    }

    $idPaseadorAsig = (int)$filaProxima['id_paseador'];
    $stmt = $conn->prepare(
        "SELECT pa.id_paseador, pa.id_usuario, pa.puntuacion, pa.zona_trabajo, u.nombre, iu.avatar_url, iu.telefono,
                (SELECT MIN(c2.fecha_creacion) FROM cronograma_adiestramiento c2
                 WHERE c2.id_pedido = ? AND c2.id_paseador = pa.id_paseador) AS fecha_asignacion
         FROM paseadores pa
         JOIN usuarios u ON u.id = pa.id_usuario
         LEFT JOIN info_usuario iu ON iu.id_usuario = pa.id_usuario
         WHERE pa.id_paseador = ?"
    );
    $stmt->bind_param("ii", $idPedido, $idPaseadorAsig);
    $stmt->execute();
    $pas = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $diasAsignados = [];
    foreach ($crono as $fila) {
        if ((int)$fila['id_paseador'] === $idPaseadorAsig) $diasAsignados[] = (int)$fila['dia_semana'];
    }

    if ($pas) {
        $asignacion = [
            'id_paseador'      => (int)$pas['id_paseador'],
            'id_usuario'       => (int)$pas['id_usuario'],
            'nombre'           => $pas['nombre'],
            'avatar'           => $pas['avatar_url'] ?? '',
            'telefono'         => $pas['telefono'] ?? '',
            'puntuacion'       => (float)$pas['puntuacion'],
            'zona_trabajo'     => $pas['zona_trabajo'] ?? '',
            'dias'             => $diasAsignados,
            'fecha_asignacion' => $pas['fecha_asignacion'],
        ];
    }

    $fechaProxima = date('Y-m-d', strtotime("+$mejorDelta days"));
    $proximaSesion = [
        'fecha'      => $fechaProxima,
        'dia_semana' => (int)$filaProxima['dia_semana'],
        'dia_nombre' => $diasNombres[(int)$filaProxima['dia_semana']],
        'es_hoy'     => $mejorDelta === 0,
        'franja'     => $pedido['franja_horaria'] ?? '',
    ];
}

// ── 3. Historial reciente (eventos reales, más nuevo primero) ─────────
$historial = [];
$historial[] = ['fecha' => $pedido['fecha_creacion'], 'tipo' => 'compra', 'texto' => 'Compra confirmada'];
if ($asignacion && $asignacion['fecha_asignacion']) {
    $historial[] = ['fecha' => $asignacion['fecha_asignacion'], 'tipo' => 'asignacion',
                    'texto' => 'Entrenador asignado: ' . $asignacion['nombre']];
}
if ($proximaSesion) {
    $historial[] = ['fecha' => $proximaSesion['fecha'] . ' 00:00:00', 'tipo' => 'programado',
                    'texto' => 'Próxima sesión: ' . $proximaSesion['dia_nombre']
                             . ($proximaSesion['franja'] ? ', ' . $proximaSesion['franja'] : ''),
                    'futuro' => true];
}
usort($historial, function ($a, $b) { return strcmp($b['fecha'], $a['fecha']); });

// ── 4. Estado global del servicio ──────────────────────────────────────
$estadoServicio = $asignacion ? 'entrenador_asignado' : 'pendiente_asignacion';

$finMembresia  = new DateTime($pedido['fecha_renovacion'], new DateTimeZone('America/Bogota'));
$ahora         = new DateTime('now', new DateTimeZone('America/Bogota'));
$diasRestantes = $ahora < $finMembresia ? $ahora->diff($finMembresia)->days : 0;

responder(true, [
    'tiene_servicio' => true,
    'servicio' => [
        'estado' => $estadoServicio,
        'pedido' => [
            'id_pedido'         => $idPedido,
            'id_mascota'        => (int)$pedido['id_mascota'],
            'mascota'           => $pedido['nombre_mascota'],
            'avatar_mascota'    => $pedido['avatar_mascota'] ?? '',
            'cantidad_sesiones' => (int)$pedido['cantidad_sesiones'],
            'duracion_min'      => (int)$pedido['duracion_min'],
            'dias_preferidos'   => $pedido['dias_preferidos'] ?? '',
            'franja_horaria'    => $pedido['franja_horaria'] ?? '',
            'fecha_inicio'      => $pedido['fecha_inicio'],
            'comportamiento'    => $pedido['comportamiento'] ?? '',
            'observaciones'     => $pedido['observaciones'] ?? '',
            'direccion'         => $pedido['direccion'],
            'barrio'            => $pedido['barrio'] ?? '',
            'referencia'        => $pedido['referencia'] ?? '',
            'instrucciones'     => $pedido['instrucciones'] ?? '',
            'lat'               => (float)$pedido['lat'],
            'lng'               => (float)$pedido['lng'],
            'ubicacion_validada'=> (int)$pedido['ubicacion_validada'] === 1,
            'total'             => (float)$pedido['total'],
            'fecha_compra'      => $pedido['fecha_creacion'],
        ],
        'plan' => [
            'sesiones_mes' => (int)$pedido['cantidad_sesiones'],
        ],
        'membresia' => [
            'inicio'         => $pedido['fecha_inicio_adiestramiento'],
            'renovacion'     => $pedido['fecha_renovacion'],
            'dias_restantes' => $diasRestantes,
        ],
        'asignacion'     => $asignacion,
        'proxima_sesion' => $proximaSesion,
        'historial'      => array_slice($historial, 0, 8),
    ],
]);
?>
