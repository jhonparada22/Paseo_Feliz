<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once 'conexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_logged']) || $_SESSION['usuario_logged'] !== true) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// ── Reiniciar paseos_mes si han pasado 30 días ─────────────────
// Solo si la columna existe
$cols = $conn->query("SHOW COLUMNS FROM paseadores LIKE 'fecha_reset_mes'");
if ($cols && $cols->num_rows > 0) {
    $conn->query("
        UPDATE paseadores
        SET paseos_mes = 0, fecha_reset_mes = CURDATE()
        WHERE fecha_reset_mes IS NULL
           OR DATEDIFF(CURDATE(), fecha_reset_mes) >= 30
    ");
}

// ── Verificar qué columnas existen en paseadores ───────────────
$colCheck = [];
$resC = $conn->query("SHOW COLUMNS FROM paseadores");
while ($r = $resC->fetch_assoc()) $colCheck[] = $r['Field'];

$hasPuntuacion   = in_array('puntuacion',    $colCheck);
$hasHorario      = in_array('hora_inicio',   $colCheck);
$hasZona         = in_array('zona_trabajo',  $colCheck);
$hasPaseosMes    = in_array('paseos_mes',    $colCheck);
$hasPaseosTot    = in_array('paseos_totales',$colCheck);

// ── Construir SELECT dinámico ──────────────────────────────────
$extraCols = '';
if ($hasPuntuacion) $extraCols .= ', p.puntuacion';
if ($hasHorario)    $extraCols .= ', p.hora_inicio, p.hora_fin';
if ($hasZona)       $extraCols .= ', p.zona_trabajo';
if ($hasPaseosMes)  $extraCols .= ', p.paseos_mes';
if ($hasPaseosTot)  $extraCols .= ', p.paseos_totales';

$sql = "
    SELECT
        p.id_paseador,
        p.id_usuario,
        p.correo
        $extraCols,
        u.nombre,
        i.telefono,
        i.avatar_url
    FROM paseadores p
    LEFT JOIN usuarios u       ON u.id = p.id_usuario
    LEFT JOIN info_usuario i   ON i.id_usuario = p.id_usuario
    ORDER BY u.nombre ASC
";

$res = $conn->query($sql);

if (!$res) {
    echo json_encode(['success' => false, 'message' => 'Error SQL: ' . $conn->error]);
    exit;
}

$paseadores = [];
while ($row = $res->fetch_assoc()) {
    // Calcular estado por horario si existen las columnas
    $estado = 'inactivo';
    if ($hasHorario && !empty($row['hora_inicio']) && !empty($row['hora_fin'])) {
        $ahora = date('H:i:s');
        if ($ahora >= $row['hora_inicio'] && $ahora <= $row['hora_fin']) {
            $estado = 'activo';
        }
    }

    // Normalizar avatar
    $avatar = '';
    if (!empty($row['avatar_url'])) {
        $avatar = 'assets/' . ltrim(preg_replace('#^(\.\./)* assets/#', '', $row['avatar_url']), '/');
    }

    $paseadores[] = [
        'id'             => (int)$row['id_paseador'],
        'id_usuario'     => (int)($row['id_usuario'] ?? 0),
        'nombre'         => $row['nombre'] ?? $row['correo'],
        'email'          => $row['correo'],
        'telefono'       => $row['telefono'] ?? '',
        'avatar'         => $avatar,
        'puntuacion'     => $hasPuntuacion  ? (float)$row['puntuacion']    : 0.0,
        'hora_inicio'    => $hasHorario     ? ($row['hora_inicio'] ?? '')  : '',
        'hora_fin'       => $hasHorario     ? ($row['hora_fin']    ?? '')  : '',
        'zona_trabajo'   => $hasZona        ? ($row['zona_trabajo'] ?? '') : '',
        'paseos_mes'     => $hasPaseosMes   ? (int)$row['paseos_mes']      : 0,
        'paseos_totales' => $hasPaseosTot   ? (int)$row['paseos_totales']  : 0,
        'estado'         => $estado,
        'clientes'       => [],
    ];
}

echo json_encode(['success' => true, 'paseadores' => $paseadores]);
$conn->close();
?>