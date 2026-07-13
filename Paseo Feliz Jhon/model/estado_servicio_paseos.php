<?php
/**
 * estado_servicio_paseos.php
 * Estado consolidado del servicio de paseos del CLIENTE logueado.
 * Es la capa central que decide qué vista del dashboard post-compra ve el cliente:
 *   - pendiente_asignacion : pagó pero aún no está en el cronograma de ningún paseador
 *   - paseador_asignado    : está en un cronograma; se calcula su próximo paseo
 *   - paseo_en_curso       : hay una ruta de HOY (en curso/pausada) con una parada suya
 *
 * Todo se deriva de tablas existentes (pedidos_paseo, cronograma_paseos, rutas,
 * ruta_paradas, membresias): no se duplica estado en ninguna tabla nueva.
 *
 * GET sin parámetros (todo sale de la sesión).
 * Respuesta: { success, tiene_servicio, servicio:{...} }
 */
header("Access-Control-Allow-Origin: *");
include_once 'helpers.php';
include_once '../model/conexion.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
date_default_timezone_set('America/Bogota');

verificarSesion();
$idUsuario = (int)$_SESSION['usuario_id'];

// Hora actual del MISMO reloj que escribe NOW() en rutas/paradas.
// El front la usa para calcular el desfase con el reloj del navegador
// (los datetime de MySQL no llevan zona horaria).
$ahoraServidor = $conn->query("SELECT NOW() AS ahora")->fetch_assoc()['ahora'];

// NOW() del servidor está en UTC; la vigencia se evalúa en hora Colombia
// (mismo criterio que controller/membresia_estado.php)
$ahoraColombia = "CONVERT_TZ(NOW(), '+00:00', '-05:00')";

// Filtro opcional: si se pasa ?id_mascota=X, se consulta el servicio de
// ESA mascota puntual (lo usa el selector de mascota del modal de
// "Editar instrucciones"). Sin el parámetro, se comporta igual que
// siempre: el pedido pagado más reciente del usuario.
$idMascotaFiltro = intval($_GET['id_mascota'] ?? 0);

// ── 1. Pedido pagado más reciente con membresía de paseos vigente ─────
$exprHoraPaseo = pedidosTienenHoraExacta($conn) ? 'p.hora_paseo' : 'NULL AS hora_paseo';
$sqlPedido =
    "SELECT p.id_pedido, p.id_mascota, p.modalidad, p.duracion_min, p.dias_preferidos,
            p.franja_horaria, $exprHoraPaseo, p.fecha_inicio, p.comportamiento, p.observaciones,
            p.direccion, p.barrio, p.referencia, p.instrucciones,
            p.lat, p.lng, p.ubicacion_validada, p.total, p.estado, p.fecha_creacion,
            p.cantidad_paseos,
            m.fecha_inicio_paseos,
            DATE_ADD(m.fecha_inicio_paseos, INTERVAL 30 DAY) AS fecha_renovacion,
            mu.nombre_mascota, mu.avatar_mascota
     FROM pedidos_paseo p
     JOIN membresias m       ON m.id_usuario = p.id_usuario AND m.id_mascota = p.id_mascota
     JOIN mascota_usuario mu ON mu.id_mascota = p.id_mascota
     WHERE p.id_usuario = ?
       AND p.estado IN ('pagado', 'listo_para_asignar')
       AND m.paseos = 1
       AND m.fecha_inicio_paseos IS NOT NULL
       AND DATE_ADD(m.fecha_inicio_paseos, INTERVAL 30 DAY) > $ahoraColombia";

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
    responder(true, ['tiene_servicio' => false], 'Sin servicio de paseos activo.');
}

$idPedido  = (int)$pedido['id_pedido'];
$idMascota = (int)$pedido['id_mascota'];

// ── 2. Asignación en el cronograma semanal ────────────────────────────
// Filas (dia_semana, paseador) del pedido; normalmente un solo paseador.
$stmt = $conn->prepare(
    "SELECT c.id_paseador, c.dia_semana, c.fecha_creacion
     FROM cronograma_paseos c
     WHERE c.id_pedido = ?
     ORDER BY c.dia_semana ASC"
);
$stmt->bind_param("i", $idPedido);
$stmt->execute();
$res = $stmt->get_result();
$crono = [];
while ($row = $res->fetch_assoc()) $crono[] = $row;
$stmt->close();

$asignacion   = null;
$proximoPaseo = null;
$diasNombres  = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
                 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

if ($crono) {
    $hoyDia = (int)date('N'); // 1=lunes ... 7=domingo

    // Próximo día del cronograma (hoy cuenta; si no, el siguiente con wrap semanal)
    $mejorDelta = 8;
    $filaProxima = $crono[0];
    foreach ($crono as $fila) {
        $delta = ((int)$fila['dia_semana'] - $hoyDia + 7) % 7; // 0 = hoy
        if ($delta < $mejorDelta) { $mejorDelta = $delta; $filaProxima = $fila; }
    }

    // Datos del paseador del próximo día
    $idPaseadorAsig = (int)$filaProxima['id_paseador'];
    $stmt = $conn->prepare(
        "SELECT pa.id_paseador, pa.id_usuario, pa.puntuacion, pa.zona_trabajo, pa.hora_inicio, pa.hora_fin,
                pa.paseos_totales, u.nombre, iu.avatar_url, iu.telefono,
                (SELECT MIN(c2.fecha_creacion) FROM cronograma_paseos c2
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
        if ((int)$fila['id_paseador'] === $idPaseadorAsig) {
            $diasAsignados[] = (int)$fila['dia_semana'];
        }
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
            'hora_inicio'      => $pas['hora_inicio'],
            'hora_fin'         => $pas['hora_fin'],
            'paseos_totales'   => (int)$pas['paseos_totales'],
            'dias'             => $diasAsignados,
            'fecha_asignacion' => $pas['fecha_asignacion'],
        ];
    }

    $fechaProxima = date('Y-m-d', strtotime("+$mejorDelta days"));
    $proximoPaseo = [
        'fecha'      => $fechaProxima,
        'dia_semana' => (int)$filaProxima['dia_semana'],
        'dia_nombre' => $diasNombres[(int)$filaProxima['dia_semana']],
        'es_hoy'     => $mejorDelta === 0,
        'franja'     => $pedido['franja_horaria'] ?? '',
    ];
}

// ── 3. Ruta de HOY con parada del cliente (paseo en vivo) ─────────────
$hoy = date('Y-m-d');
$stmt = $conn->prepare(
    "SELECT DISTINCT r.id_ruta, r.id_paseador, r.id_estado, er.nombre AS estado_ruta,
            r.hora_inicio, r.fecha_inicio_real, r.fecha_fin_real, r.duracion_estimada_min
     FROM rutas r
     JOIN ruta_paradas rp ON rp.id_ruta = r.id_ruta
     JOIN estados_ruta er ON er.id_estado = r.id_estado
     WHERE rp.id_usuario_cliente = ? AND r.fecha_paseo = ? AND r.id_estado IN (1,2,3)
     ORDER BY r.hora_inicio ASC
     LIMIT 1"
);
$stmt->bind_param("is", $idUsuario, $hoy);
$stmt->execute();
$ruta = $stmt->get_result()->fetch_assoc();
$stmt->close();

$rutaHoy = null;
if ($ruta) {
    // Paradas del cliente dentro de esa ruta (recogida y entrega)
    $stmt = $conn->prepare(
        "SELECT rp.tipo, rp.id_estado, ep.nombre AS estado, rp.hora_llegada, rp.hora_completado
         FROM ruta_paradas rp
         JOIN estados_parada ep ON ep.id_estado = rp.id_estado
         WHERE rp.id_ruta = ? AND rp.id_usuario_cliente = ?
         ORDER BY rp.orden ASC"
    );
    $idRuta = (int)$ruta['id_ruta'];
    $stmt->bind_param("ii", $idRuta, $idUsuario);
    $stmt->execute();
    $res = $stmt->get_result();
    $recogida = null;
    $entrega  = null;
    while ($p = $res->fetch_assoc()) {
        $dato = [
            'estado'          => $p['estado'],
            'hora_llegada'    => $p['hora_llegada'],
            'hora_completado' => $p['hora_completado'],
        ];
        if ($p['tipo'] === 'recogida' && !$recogida) $recogida = $dato;
        if ($p['tipo'] === 'entrega') $entrega = $dato;
    }
    $stmt->close();

    // Fase del paseo derivada de las paradas del cliente
    $fase = 'en_camino'; // el paseador va hacia la recogida
    if ($entrega && $entrega['estado'] === 'completada') {
        $fase = 'finalizado';
    } elseif ($recogida && $recogida['estado'] === 'completada') {
        $fase = 'en_curso'; // mascota recogida, paseo en marcha
    }

    $rutaHoy = [
        'id_ruta'               => $idRuta,
        'id_paseador'           => (int)$ruta['id_paseador'],
        'estado'                => $ruta['estado_ruta'],
        'hora_inicio'           => $ruta['hora_inicio'],
        'fecha_inicio_real'     => $ruta['fecha_inicio_real'],
        'duracion_estimada_min' => (int)$ruta['duracion_estimada_min'],
        'fase'                  => $fase,
        'recogida'              => $recogida,
        'entrega'               => $entrega,
    ];
}

// ── 4. Paseos usados / restantes en el periodo de la membresía ────────
// Un paseo "usado" = parada de ENTREGA completada del cliente+mascota
// en una ruta desde que inició la membresía vigente.
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS usados
     FROM ruta_paradas rp
     JOIN rutas r ON r.id_ruta = rp.id_ruta
     WHERE rp.id_usuario_cliente = ? AND rp.id_mascota = ?
       AND rp.tipo = 'entrega' AND rp.id_estado = 3
       AND r.fecha_paseo >= DATE(?)"
);
$stmt->bind_param("iis", $idUsuario, $idMascota, $pedido['fecha_inicio_paseos']);
$stmt->execute();
$usados = (int)$stmt->get_result()->fetch_assoc()['usados'];
$stmt->close();

$paseosMes = (int)$pedido['cantidad_paseos'];
$restantes = max(0, $paseosMes - $usados);

// ── 5. Historial reciente (eventos reales, más nuevo primero) ─────────
$historial = [];
$historial[] = ['fecha' => $pedido['fecha_creacion'], 'tipo' => 'compra',
                'texto' => 'Compra confirmada'];
if ((int)$pedido['ubicacion_validada'] === 1) {
    $historial[] = ['fecha' => $pedido['fecha_creacion'], 'tipo' => 'direccion',
                    'texto' => 'Dirección validada'];
}
if ($asignacion && $asignacion['fecha_asignacion']) {
    $historial[] = ['fecha' => $asignacion['fecha_asignacion'], 'tipo' => 'asignacion',
                    'texto' => 'Paseador asignado: ' . $asignacion['nombre']];
}

// Últimos paseos completados (entrega completada en ruta finalizada)
$stmt = $conn->prepare(
    "SELECT r.fecha_fin_real
     FROM rutas r
     JOIN ruta_paradas rp ON rp.id_ruta = r.id_ruta
     WHERE rp.id_usuario_cliente = ? AND rp.id_mascota = ?
       AND rp.tipo = 'entrega' AND rp.id_estado = 3 AND r.id_estado = 4
     ORDER BY r.fecha_fin_real DESC
     LIMIT 3"
);
$stmt->bind_param("ii", $idUsuario, $idMascota);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $historial[] = ['fecha' => $row['fecha_fin_real'], 'tipo' => 'completado',
                    'texto' => 'Paseo completado'];
}
$stmt->close();

if ($proximoPaseo) {
    $historial[] = ['fecha' => $proximoPaseo['fecha'] . ' 00:00:00', 'tipo' => 'programado',
                    'texto' => 'Próximo paseo: ' . $proximoPaseo['dia_nombre']
                             . ($proximoPaseo['franja'] ? ', ' . $proximoPaseo['franja'] : ''),
                    'futuro' => true];
}

usort($historial, function ($a, $b) {
    return strcmp($b['fecha'], $a['fecha']);
});

// ── 6. Estado global del servicio ─────────────────────────────────────
// En curso solo cuando el paseador ya arrancó (ruta en_curso o pausada).
if ($rutaHoy && in_array($rutaHoy['estado'], ['en_curso', 'pausada'], true)) {
    $estadoServicio = 'paseo_en_curso';
} elseif ($asignacion) {
    $estadoServicio = 'paseador_asignado';
} else {
    $estadoServicio = 'pendiente_asignacion';
}

// Días restantes de la membresía
$finMembresia  = new DateTime($pedido['fecha_renovacion'], new DateTimeZone('America/Bogota'));
$ahora         = new DateTime('now', new DateTimeZone('America/Bogota'));
$diasRestantes = $ahora < $finMembresia ? $ahora->diff($finMembresia)->days : 0;

responder(true, [
    'tiene_servicio' => true,
    'ahora_servidor' => $ahoraServidor,
    'servicio' => [
        'estado' => $estadoServicio,
        'pedido' => [
            'id_pedido'       => $idPedido,
            'id_mascota'      => $idMascota,
            'mascota'         => $pedido['nombre_mascota'],
            'avatar_mascota'  => $pedido['avatar_mascota'] ?? '',
            'modalidad'       => $pedido['modalidad'],
            'duracion_min'    => (int)$pedido['duracion_min'],
            'dias_preferidos' => $pedido['dias_preferidos'] ?? '',
            'franja_horaria'  => $pedido['franja_horaria'] ?? '',
            'hora_paseo'      => $pedido['hora_paseo'] ? substr($pedido['hora_paseo'], 0, 5) : null,
            'fecha_inicio'    => $pedido['fecha_inicio'],
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
        'plan' => [
            'paseos_mes' => $paseosMes,
            'usados'     => $usados,
            'restantes'  => $restantes,
        ],
        'membresia' => [
            'inicio'         => $pedido['fecha_inicio_paseos'],
            'renovacion'     => $pedido['fecha_renovacion'],
            'dias_restantes' => $diasRestantes,
        ],
        'asignacion'    => $asignacion,
        'proximo_paseo' => $proximoPaseo,
        'ruta_hoy'      => $rutaHoy,
        'historial'     => array_slice($historial, 0, 8),
    ],
]);
?>