<?php
// ═══════════════════════════════════════════════════════════
//  controller/membresia_estado.php
//  Devuelve el estado de membresía POR CADA MASCOTA del usuario
//  en sesión (antes era un solo estado por usuario).
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
// Aplica a TODAS las filas de membresía del usuario (una por mascota).
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

// ── 2. Días/minutos restantes de una membresía puntual ───
function minutosRestantes($fechaFin, $pagado) {
    if (!$pagado || $fechaFin === null) return null;
    $zona  = new DateTimeZone('America/Bogota');
    $fin   = new DateTime($fechaFin, $zona);
    $ahora = new DateTime('now', $zona);
    $diff  = $ahora->diff($fin);
    return $diff->invert ? 0 : ($diff->days * 24 * 60 + $diff->h * 60 + $diff->i);
}

// ── 3. Traer TODAS las mascotas del usuario + su membresía (si tiene) ─
// LEFT JOIN: una mascota sin fila en `membresias` simplemente sale
// con todo en NULL/0 (nunca ha comprado nada para esa mascota).
$stmt = $conn->prepare("
    SELECT
        mu.id_mascota, mu.nombre_mascota, mu.avatar_mascota,
        m.paseos, m.adiestramiento, m.hospedaje,
        DATE_ADD(m.fecha_inicio_paseos,         INTERVAL 30 DAY) AS fin_paseos,
        DATE_ADD(m.fecha_inicio_adiestramiento, INTERVAL 30 DAY) AS fin_adiestramiento,
        DATE_ADD(m.fecha_inicio_hospedaje,      INTERVAL 30 DAY) AS fin_hospedaje
    FROM mascota_usuario mu
    LEFT JOIN membresias m ON m.id_mascota = mu.id_mascota AND m.id_usuario = mu.id_usuario
    WHERE mu.id_usuario = ?
    ORDER BY mu.id_mascota ASC
");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Error en consulta']);
    exit;
}

$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();

$mascotas = [];
// Acumuladores "¿alguna mascota tiene esto activo?" — se mantienen para
// no romper código que ya use membresias.paseos/adiestramiento/hospedaje
// como booleano simple.
$anyPaseos = false; $anyAdiestramiento = false; $anyHospedaje = false;
$anyRenovarPaseos = false; $anyRenovarAdiestramiento = false; $anyRenovarHospedaje = false;

while ($row = $res->fetch_assoc()) {
    $minP = minutosRestantes($row['fin_paseos'],         (bool)$row['paseos']);
    $minA = minutosRestantes($row['fin_adiestramiento'], (bool)$row['adiestramiento']);
    $minH = minutosRestantes($row['fin_hospedaje'],      (bool)$row['hospedaje']);

    $paseosActivo         = (bool)$row['paseos'];
    $adiestramientoActivo = (bool)$row['adiestramiento'];
    $hospedajeActivo      = (bool)$row['hospedaje'];

    $renovarPaseos         = $minP !== null && $minP < 1440;
    $renovarAdiestramiento = $minA !== null && $minA < 1440;
    $renovarHospedaje      = $minH !== null && $minH < 1440;

    if ($paseosActivo)         $anyPaseos = true;
    if ($adiestramientoActivo) $anyAdiestramiento = true;
    if ($hospedajeActivo)      $anyHospedaje = true;
    if ($renovarPaseos)         $anyRenovarPaseos = true;
    if ($renovarAdiestramiento) $anyRenovarAdiestramiento = true;
    if ($renovarHospedaje)      $anyRenovarHospedaje = true;

    $mascotas[] = [
        'id_mascota'         => (int)$row['id_mascota'],
        'nombre_mascota'     => $row['nombre_mascota'],
        'avatar_mascota'     => $row['avatar_mascota'],
        'paseos'             => $paseosActivo,
        'adiestramiento'     => $adiestramientoActivo,
        'hospedaje'          => $hospedajeActivo,
        'renovar'            => [
            'paseos'         => $renovarPaseos,
            'adiestramiento' => $renovarAdiestramiento,
            'hospedaje'      => $renovarHospedaje,
        ],
        'minutos_restantes'  => [
            'paseos'         => $minP,
            'adiestramiento' => $minA,
            'hospedaje'      => $minH,
        ],
    ];
}
$stmt->close();

echo json_encode([
    'success'        => true,
    'tiene_mascotas' => count($mascotas) > 0,
    'mascotas'       => $mascotas,

    // — Compatibilidad hacia atrás: "¿tiene ESTA membresía en AL MENOS
    //   una mascota?" — para código viejo que solo revisa un booleano.
    'paseos'         => $anyPaseos,
    'adiestramiento' => $anyAdiestramiento,
    'hospedaje'      => $anyHospedaje,
    'renovar'        => [
        'paseos'         => $anyRenovarPaseos,
        'adiestramiento' => $anyRenovarAdiestramiento,
        'hospedaje'      => $anyRenovarHospedaje,
    ],
]);