<?php
// ═══════════════════════════════════════════════════════════
//  controller/membresia_estado.php
//  Devuelve el estado de membresía del usuario en sesión.
//  También auto-expira servicios vencidos en cada consulta
//  (no depende del Event Scheduler del servidor).
// ═══════════════════════════════════════════════════════════

include_once 'control_acceso.php';   // mismo directorio: controller/
if (session_status() === PHP_SESSION_NONE) session_start();

// ── Zona horaria Colombia (UTC-5) ─────────────────────────
date_default_timezone_set('America/Bogota');

header('Content-Type: application/json');

// — Verificar sesión —
if (empty($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

include_once '../model/conexion.php';   // $conn

$id = (int) $_SESSION['usuario_id'];

// ── 1. Auto-expirar servicios vencidos ───────────────────
// Ponemos en 0 cualquier servicio cuya fecha_fin ya pasó.
// CONVERT_TZ convierte NOW() del servidor a hora Colombia (UTC-5)
$ahora_colombia = "CONVERT_TZ(NOW(), '+00:00', '-05:00')";
$conn->query("
    UPDATE membresias SET
        paseos         = IF(fecha_inicio_paseos         IS NOT NULL
                            AND DATE_ADD(fecha_inicio_paseos, INTERVAL 30 DAY) < $ahora_colombia,
                            0, paseos),
        adiestramiento = IF(fecha_inicio_adiestramiento IS NOT NULL
                            AND DATE_ADD(fecha_inicio_adiestramiento, INTERVAL 30 DAY) < $ahora_colombia,
                            0, adiestramiento),
        hospedaje      = IF(fecha_inicio_hospedaje      IS NOT NULL
                            AND DATE_ADD(fecha_inicio_hospedaje, INTERVAL 30 DAY) < $ahora_colombia,
                            0, hospedaje)
    WHERE id_usuario = $id
");

// ── 2. Leer estado actualizado ────────────────────────────
$stmt = $conn->prepare("
    SELECT
        paseos,
        adiestramiento,
        hospedaje,
        fecha_inicio_paseos,
        fecha_inicio_adiestramiento,
        fecha_inicio_hospedaje,
        DATE_ADD(fecha_inicio_paseos,         INTERVAL 30 DAY) AS fin_paseos,
        DATE_ADD(fecha_inicio_adiestramiento, INTERVAL 30 DAY) AS fin_adiestramiento,
        DATE_ADD(fecha_inicio_hospedaje,      INTERVAL 30 DAY) AS fin_hospedaje
    FROM membresias
    WHERE id_usuario = ?
");

// Si el usuario no tiene fila aún, devolvemos todo en 0
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Error en consulta']);
    exit;
}

$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    // Sin membresía registrada → todo inactivo
    echo json_encode([
        'success' => true,
        'paseos'         => false,
        'adiestramiento' => false,
        'hospedaje'      => false,
        'dias_restantes' => ['paseos' => null, 'adiestramiento' => null, 'hospedaje' => null]
    ]);
    exit;
}

$row = $res->fetch_assoc();
$now = new DateTime();

// ── 3. Calcular días restantes y avisos de renovación ────
function diasRestantes($fechaFin, $pagado) {
    if (!$pagado || $fechaFin === null) return null;
    $zona = new DateTimeZone('America/Bogota');
    $fin  = new DateTime($fechaFin, $zona);
    $ahora = new DateTime('now', $zona);
    $diff = $ahora->diff($fin);
    // diff->invert = 1 si ya venció
    return $diff->invert ? 0 : ($diff->days * 24 * 60 + $diff->h * 60 + $diff->i); // minutos restantes
}

$minPaseos  = diasRestantes($row['fin_paseos'],         (bool)$row['paseos']);
$minAdiest  = diasRestantes($row['fin_adiestramiento'], (bool)$row['adiestramiento']);
$minHosp    = diasRestantes($row['fin_hospedaje'],      (bool)$row['hospedaje']);

// "Renovar pronto" = menos de 1 día (1440 minutos)
echo json_encode([
    'success'        => true,
    'paseos'         => (bool)$row['paseos'],
    'adiestramiento' => (bool)$row['adiestramiento'],
    'hospedaje'      => (bool)$row['hospedaje'],
    'renovar'        => [
        'paseos'         => $minPaseos  !== null && $minPaseos  < 1440,
        'adiestramiento' => $minAdiest  !== null && $minAdiest  < 1440,
        'hospedaje'      => $minHosp    !== null && $minHosp    < 1440,
    ],
    'minutos_restantes' => [
        'paseos'         => $minPaseos,
        'adiestramiento' => $minAdiest,
        'hospedaje'      => $minHosp,
    ]
]);