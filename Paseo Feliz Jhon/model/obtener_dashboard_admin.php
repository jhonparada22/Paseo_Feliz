<?php
/**
 * obtener_dashboard_admin.php
 * Agregados para el dashboard de inicio del admin (index_admin.php).
 * Solo cubre lo que ningún otro endpoint ya calcula: contadores del día,
 * tendencia de la gráfica de línea, distribución del donut, mapa de
 * días con paseos para el calendario, y los 4 "reportes recientes".
 *
 * La tabla "Paseos Recientes" y la tarjeta "Paseadores Disponibles" se
 * alimentan en el front desde obtener_pedidos_paseos.php y
 * obtener_paseadores.php (ya existen, no se duplican aquí).
 *
 * GET sin parámetros.
 */
header("Access-Control-Allow-Origin: *");
include_once 'helpers.php';
include_once '../model/conexion.php';

verificarAdmin();
date_default_timezone_set('America/Bogota');

$hoy   = date('Y-m-d');
$ayer  = date('Y-m-d', strtotime('-1 day'));

// ── 1. Paseos activos hoy (rutas pendiente/en_curso/pausada) ──────────
function contarRutasActivas($conn, $fecha) {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS c FROM rutas WHERE fecha_paseo = ? AND id_estado IN (1,2,3)"
    );
    $stmt->bind_param("s", $fecha);
    $stmt->execute();
    $c = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
    return $c;
}
$paseosHoy  = contarRutasActivas($conn, $hoy);
$paseosAyer = contarRutasActivas($conn, $ayer);

// ── 2. Usuarios cliente (sin admin ni paseador) ───────────────────────
$sqlUsuarios = "
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN u.fecha_registro >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS nuevos_semana
    FROM usuarios u
    LEFT JOIN admin a       ON a.id_usuario = u.id
    LEFT JOIN paseadores p  ON p.id_usuario = u.id
    WHERE a.id_usuario IS NULL AND p.id_usuario IS NULL
";
$rowU = $conn->query($sqlUsuarios)->fetch_assoc();
$usuariosTotal        = (int)$rowU['total'];
$usuariosNuevosSemana = (int)$rowU['nuevos_semana'];

// ── 3. Ingresos: total, esta semana, semana anterior ──────────────────
$ingresosTotales = (float)$conn->query("SELECT COALESCE(SUM(monto),0) AS t FROM pagos")->fetch_assoc()['t'];

$stmt = $conn->prepare(
    "SELECT COALESCE(SUM(monto),0) AS t FROM pagos WHERE fecha_pago >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
);
$stmt->execute();
$ingresosSemana = (float)$stmt->get_result()->fetch_assoc()['t'];
$stmt->close();

$stmt = $conn->prepare(
    "SELECT COALESCE(SUM(monto),0) AS t FROM pagos
     WHERE fecha_pago >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND fecha_pago < DATE_SUB(NOW(), INTERVAL 7 DAY)"
);
$stmt->execute();
$ingresosSemanaAnterior = (float)$stmt->get_result()->fetch_assoc()['t'];
$stmt->close();

// ── 4. Gráfica de línea: rutas finalizadas por día (3 rangos precalculados) ──
function rutasFinalizadasPorDia($conn, $desde) {
    $stmt = $conn->prepare(
        "SELECT fecha_paseo, COUNT(*) AS c FROM rutas
         WHERE id_estado = 4 AND fecha_paseo >= ?
         GROUP BY fecha_paseo ORDER BY fecha_paseo ASC"
    );
    $stmt->bind_param("s", $desde);
    $stmt->execute();
    $res = $stmt->get_result();
    $mapa = [];
    while ($row = $res->fetch_assoc()) $mapa[$row['fecha_paseo']] = (int)$row['c'];
    $stmt->close();
    return $mapa;
}

function serieDesde($mapa, $inicio, $fin) {
    $labels = [];
    $data   = [];
    $cursor = strtotime($inicio);
    $finTs  = strtotime($fin);
    while ($cursor <= $finTs) {
        $f = date('Y-m-d', $cursor);
        $labels[] = date('d/m', $cursor);
        $data[]   = $mapa[$f] ?? 0;
        $cursor   = strtotime('+1 day', $cursor);
    }
    return ['labels' => $labels, 'data' => $data];
}

$desde30 = date('Y-m-d', strtotime('-29 days'));
$mapaFinalizadas = rutasFinalizadasPorDia($conn, $desde30);

$serie7  = serieDesde($mapaFinalizadas, date('Y-m-d', strtotime('-6 days')), $hoy);
$serie30 = serieDesde($mapaFinalizadas, $desde30, $hoy);
$primerDiaMes = date('Y-m-01');
$mapaMes = ($desde30 <= $primerDiaMes) ? $mapaFinalizadas : rutasFinalizadasPorDia($conn, $primerDiaMes);
$serieMes = serieDesde($mapaMes, $primerDiaMes, $hoy);

// ── 5. Donut: distribución de rutas por estado (todo el histórico) ────
$donut = ['completados' => 0, 'en_proceso' => 0, 'programados' => 0, 'cancelados' => 0];
$res = $conn->query("SELECT id_estado, COUNT(*) AS c FROM rutas GROUP BY id_estado");
while ($row = $res->fetch_assoc()) {
    switch ((int)$row['id_estado']) {
        case 1: $donut['programados'] += (int)$row['c']; break;
        case 2: case 3: $donut['en_proceso'] += (int)$row['c']; break;
        case 4: $donut['completados'] += (int)$row['c']; break;
        case 5: $donut['cancelados'] += (int)$row['c']; break;
    }
}

// ── 6. Calendario: conteo de rutas por fecha (sin acotar rango) ───────
$calendario = [];
$res = $conn->query("SELECT fecha_paseo, COUNT(*) AS c FROM rutas GROUP BY fecha_paseo");
while ($row = $res->fetch_assoc()) $calendario[$row['fecha_paseo']] = (int)$row['c'];

// ── 7. Reportes recientes (uno por categoría, con fecha real) ─────────
$reportes = [];

$row = $conn->query(
    "SELECT fecha_fin_real FROM rutas WHERE id_estado = 4 AND fecha_fin_real IS NOT NULL
     ORDER BY fecha_fin_real DESC LIMIT 1"
)->fetch_assoc();
$reportes[] = [
    'tipo' => 'paseo', 'titulo' => 'Paseos completados',
    'fecha' => $row['fecha_fin_real'] ?? null, 'href' => 'paseos_admin.php',
];

$row = $conn->query("SELECT fecha_pago FROM pagos ORDER BY fecha_pago DESC LIMIT 1")->fetch_assoc();
$reportes[] = [
    'tipo' => 'ingreso', 'titulo' => 'Ingresos semanales',
    'fecha' => $row['fecha_pago'] ?? null, 'href' => 'pagos_admin.php',
];

$row = $conn->query(
    "SELECT u.fecha_registro FROM usuarios u
     LEFT JOIN admin a ON a.id_usuario = u.id
     LEFT JOIN paseadores p ON p.id_usuario = u.id
     WHERE a.id_usuario IS NULL AND p.id_usuario IS NULL
     ORDER BY u.fecha_registro DESC LIMIT 1"
)->fetch_assoc();
$reportes[] = [
    'tipo' => 'usuario', 'titulo' => 'Usuarios nuevos',
    'fecha' => $row['fecha_registro'] ?? null, 'href' => 'usuarios_admin.php',
];

$row = $conn->query(
    "SELECT fecha_inicio_real FROM rutas WHERE fecha_inicio_real IS NOT NULL
     ORDER BY fecha_inicio_real DESC LIMIT 1"
)->fetch_assoc();
$reportes[] = [
    'tipo' => 'paseador', 'titulo' => 'Paseadores activos',
    'fecha' => $row['fecha_inicio_real'] ?? null, 'href' => 'paseadores_admin.php',
];

// ── 8. Pedidos listos para asignar SIN cronograma (para el widget) ────
$pedidosSinAsignar = (int)$conn->query(
    "SELECT COUNT(*) AS c FROM pedidos_paseo p
     LEFT JOIN cronograma_paseos c ON c.id_pedido = p.id_pedido
     WHERE p.estado = 'listo_para_asignar' AND c.id_cronograma IS NULL"
)->fetch_assoc()['c'];

responder(true, [
    'stats' => [
        'paseos_hoy'              => $paseosHoy,
        'paseos_ayer'             => $paseosAyer,
        'usuarios_total'          => $usuariosTotal,
        'usuarios_nuevos_semana'  => $usuariosNuevosSemana,
        'ingresos_totales'        => $ingresosTotales,
        'ingresos_semana'         => $ingresosSemana,
        'ingresos_semana_anterior'=> $ingresosSemanaAnterior,
        'pedidos_sin_asignar'     => $pedidosSinAsignar,
    ],
    'chart_linea' => [
        'dias7'  => $serie7,
        'dias30' => $serie30,
        'mes'    => $serieMes,
    ],
    'chart_donut'  => $donut,
    'calendario'   => $calendario,
    'reportes_recientes' => $reportes,
]);
?>
