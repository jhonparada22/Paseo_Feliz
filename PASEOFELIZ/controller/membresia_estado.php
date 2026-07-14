<?php
// ═══════════════════════════════════════════════════════════
//  controller/membresia_estado.php
//  Devuelve el estado de membresía del usuario en sesión.
//  La membresía es POR MASCOTA (una fila en `membresias` por
//  cada id_mascota), así que este endpoint:
//    - lee las mascotas reales del cliente (mascota_usuario) para
//      saber si tiene_mascotas y armar el detalle por mascota.
//    - agrega un resumen global por servicio (¿al menos una
//      mascota lo tiene activo?) para mantener compatibilidad con
//      el código que solo necesita un booleano por servicio.
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
// Ponemos en 0 cualquier servicio cuya fecha_fin ya pasó (afecta a
// todas las filas de membresía del usuario, una por mascota).
$ahora_colombia = "CONVERT_TZ(NOW(), '+00:00', '-05:00')";
$conn->query("
    UPDATE membresias SET
        paseos         = IF(fecha_fin_paseos IS NOT NULL
                            AND fecha_fin_paseos < $ahora_colombia,
                            0, paseos),
        adiestramiento = IF(fecha_inicio_adiestramiento IS NOT NULL
                            AND DATE_ADD(fecha_inicio_adiestramiento, INTERVAL 30 DAY) < $ahora_colombia,
                            0, adiestramiento),
        hospedaje      = IF(fecha_inicio_hospedaje      IS NOT NULL
                            AND DATE_ADD(fecha_inicio_hospedaje, INTERVAL 30 DAY) < $ahora_colombia,
                            0, hospedaje)
    WHERE id_usuario = $id
");

// ── 2. Mascotas reales del cliente ───────────────────────
// Fuente de verdad de "tiene_mascotas": la tabla mascota_usuario,
// no la tabla membresias (antes se devolvía tiene_mascotas=false
// siempre porque este archivo nunca consultaba esta tabla).
$stmt = $conn->prepare("SELECT id_mascota FROM mascota_usuario WHERE id_usuario = ? ORDER BY id_mascota ASC");
$stmt->bind_param('i', $id);
$stmt->execute();
$resMasc = $stmt->get_result();
$idsMascotas = [];
while ($r = $resMasc->fetch_assoc()) $idsMascotas[] = (int)$r['id_mascota'];
$stmt->close();

// ── 3. Filas de membresía del usuario (una por mascota; puede
//      existir además una fila "legado" con id_mascota NULL de
//      antes de que la membresía fuera por mascota) ──────────
$stmt = $conn->prepare("
    SELECT
        id_mascota, paseos, adiestramiento, hospedaje,
        fecha_inicio_paseos, fecha_inicio_adiestramiento, fecha_inicio_hospedaje,
        fecha_fin_paseos                                       AS fin_paseos,
        DATE_ADD(fecha_inicio_adiestramiento, INTERVAL 30 DAY) AS fin_adiestramiento,
        DATE_ADD(fecha_inicio_hospedaje,      INTERVAL 30 DAY) AS fin_hospedaje
    FROM membresias
    WHERE id_usuario = ?
");
$stmt->bind_param('i', $id);
$stmt->execute();
$resMem = $stmt->get_result();
$filasMem = [];
while ($r = $resMem->fetch_assoc()) $filasMem[] = $r;
$stmt->close();

// ── 4. Calcular días restantes y avisos de renovación ────
function diasRestantes($fechaFin, $pagado) {
    if (!$pagado || $fechaFin === null) return null;
    $zona = new DateTimeZone('America/Bogota');
    $fin  = new DateTime($fechaFin, $zona);
    $ahora = new DateTime('now', $zona);
    $diff = $ahora->diff($fin);
    // diff->invert = 1 si ya venció
    return $diff->invert ? 0 : ($diff->days * 24 * 60 + $diff->h * 60 + $diff->i); // minutos restantes
}

// Resumen global por servicio: activo si AL MENOS UNA fila (de
// cualquier mascota, o la fila legado) lo tiene activo. "Renovar
// pronto" y "minutos_restantes" toman el caso más próximo a vencer.
$paseosActivo = false; $adiestActivo = false; $hospActivo = false;
$renovarPaseos = false; $renovarAdiest = false; $renovarHosp = false;
$minPaseosMin = null; $minAdiestMin = null; $minHospMin = null;

foreach ($filasMem as $f) {
    if ($f['paseos']) {
        $paseosActivo = true;
        $min = diasRestantes($f['fin_paseos'], true);
        if ($min !== null && $min < 1440) $renovarPaseos = true;
        if ($min !== null && ($minPaseosMin === null || $min < $minPaseosMin)) $minPaseosMin = $min;
    }
    if ($f['adiestramiento']) {
        $adiestActivo = true;
        $min = diasRestantes($f['fin_adiestramiento'], true);
        if ($min !== null && $min < 1440) $renovarAdiest = true;
        if ($min !== null && ($minAdiestMin === null || $min < $minAdiestMin)) $minAdiestMin = $min;
    }
    if ($f['hospedaje']) {
        $hospActivo = true;
        $min = diasRestantes($f['fin_hospedaje'], true);
        if ($min !== null && $min < 1440) $renovarHosp = true;
        if ($min !== null && ($minHospMin === null || $min < $minHospMin)) $minHospMin = $min;
    }
}

// ── 5. Detalle por mascota (lo usan los wizards para bloquear la
//      compra de una membresía que esa mascota ya tiene activa) ──
$filaPorMascota = [];
foreach ($filasMem as $f) {
    if ($f['id_mascota'] === null) continue; // fila legado, sin mascota asociada
    $filaPorMascota[(int)$f['id_mascota']] = $f;
}
$mascotas = [];
foreach ($idsMascotas as $idM) {
    $f = $filaPorMascota[$idM] ?? null;
    $mascotas[] = [
        'id_mascota'     => $idM,
        'paseos'         => (bool)($f['paseos'] ?? false),
        'adiestramiento' => (bool)($f['adiestramiento'] ?? false),
        'hospedaje'      => (bool)($f['hospedaje'] ?? false),
    ];
}

echo json_encode([
    'success'           => true,
    'id_usuario'        => $id, // para flags por-usuario en el front (tutorial)
    'tiene_mascotas'    => count($idsMascotas) > 0,
    'mascotas'          => $mascotas,
    'paseos'            => $paseosActivo,
    'adiestramiento'    => $adiestActivo,
    'hospedaje'         => $hospActivo,
    'renovar'           => [
        'paseos'         => $renovarPaseos,
        'adiestramiento' => $renovarAdiest,
        'hospedaje'      => $renovarHosp,
    ],
    'minutos_restantes' => [
        'paseos'         => $minPaseosMin,
        'adiestramiento' => $minAdiestMin,
        'hospedaje'      => $minHospMin,
    ],
]);
