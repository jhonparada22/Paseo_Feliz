<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once 'conexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_logged']) || $_SESSION['usuario_logged'] !== true) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Forzar zona horaria Colombia
date_default_timezone_set('America/Bogota');

// ── Reiniciar paseos_mes si han pasado 30 días ─────────────────
$cols = $conn->query("SHOW COLUMNS FROM paseadores LIKE 'fecha_reset_mes'");
if ($cols && $cols->num_rows > 0) {
    $conn->query("
        UPDATE paseadores
        SET paseos_mes = 0, fecha_reset_mes = CURDATE()
        WHERE fecha_reset_mes IS NULL
           OR DATEDIFF(CURDATE(), fecha_reset_mes) >= 30
    ");
}

// ── Verificar qué columnas existen ────────────────────────────
$colCheck = [];
$resC = $conn->query("SHOW COLUMNS FROM paseadores");
while ($r = $resC->fetch_assoc()) $colCheck[] = $r['Field'];

$hasPuntuacion = in_array('puntuacion',     $colCheck);
$hasHorario    = in_array('hora_inicio',    $colCheck);
$hasZona       = in_array('zona_trabajo',   $colCheck);
$hasPaseosMes  = in_array('paseos_mes',     $colCheck);
$hasPaseosTot  = in_array('paseos_totales', $colCheck);

// ── Verificar si ya existen las tablas del módulo de mapas ─────
$tablaGps   = $conn->query("SHOW TABLES LIKE 'gps_paseadores'")->num_rows > 0;
$tablaRutas = $conn->query("SHOW TABLES LIKE 'rutas'")->num_rows > 0;

// ── SELECT dinámico ───────────────────────────────────────────
$extraCols = '';
if ($hasPuntuacion) $extraCols .= ', p.puntuacion';
if ($hasHorario)    $extraCols .= ', p.hora_inicio, p.hora_fin';
if ($hasZona)       $extraCols .= ', p.zona_trabajo';
if ($hasPaseosMes)  $extraCols .= ', p.paseos_mes';
if ($hasPaseosTot)  $extraCols .= ', p.paseos_totales';

// ── Columnas GPS (solo si la tabla existe) ─────────────────────
$colsGps = $tablaGps ? ', g.lat, g.lng, g.velocidad, g.fecha_actualizacion' : '';
$joinGps = $tablaGps ? 'LEFT JOIN gps_paseadores g ON g.id_paseador = p.id_paseador' : '';

// ── Columnas ruta activa de hoy (solo si la tabla existe) ──────
$hoy      = date('Y-m-d');
$colsRuta = $tablaRutas ? ', r.id_ruta, er.nombre AS estado_ruta' : '';
$joinRutas = $tablaRutas
    ? "LEFT JOIN (
            SELECT id_ruta, id_paseador, id_estado
            FROM rutas
            WHERE fecha_paseo = '{$hoy}' AND id_estado IN (1,2,3)
        ) r ON r.id_paseador = p.id_paseador
       LEFT JOIN estados_ruta er ON er.id_estado = r.id_estado"
    : '';

$sql = "
    SELECT
        p.id_paseador,
        p.id_usuario,
        p.correo
        $extraCols
        $colsGps
        $colsRuta,
        u.nombre,
        i.telefono,
        i.avatar_url
    FROM paseadores p
    LEFT JOIN usuarios u     ON u.id = p.id_usuario
    LEFT JOIN info_usuario i ON i.id_usuario = p.id_usuario
    $joinGps
    $joinRutas
    GROUP BY p.id_paseador
    ORDER BY u.nombre ASC
";

$res = $conn->query($sql);
if (!$res) {
    echo json_encode(['success' => false, 'message' => 'Error SQL: ' . $conn->error]);
    exit;
}

// Hora actual en Colombia (PHP)
$ahoraStr = date('H:i:s');

$paseadores = [];
while ($row = $res->fetch_assoc()) {

    // ── Estado: la ruta real tiene prioridad sobre el horario ──
    $estado = 'inactivo';

    // 1. Primero revisar si tiene ruta activa hoy (módulo de mapas)
    if ($tablaRutas && !empty($row['estado_ruta'])) {
        if ($row['estado_ruta'] === 'en_curso')  $estado = 'en-ruta';
        elseif ($row['estado_ruta'] === 'pausada')   $estado = 'pausado';
        elseif ($row['estado_ruta'] === 'pendiente') $estado = 'activo';
    } else {
        // 2. Si no hay ruta, calcular por horario registrado (lógica original)
        if ($hasHorario && !empty($row['hora_inicio']) && !empty($row['hora_fin'])) {
            $ini = strlen($row['hora_inicio']) === 5 ? $row['hora_inicio'] . ':00' : $row['hora_inicio'];
            $fin = strlen($row['hora_fin'])    === 5 ? $row['hora_fin']    . ':00' : $row['hora_fin'];
            if ($ahoraStr >= $ini && $ahoraStr <= $fin) {
                $estado = 'activo';
            }
        }
    }

    // ── Normalizar avatar ──────────────────────────────────────
    $avatar = '';
    if (!empty($row['avatar_url'])) {
        $avatar = 'assets/' . ltrim(preg_replace('#^(\.\./)*assets/#', '', $row['avatar_url']), '/');
    }

    $paseadores[] = [
        'id'             => (int)$row['id_paseador'],
        'id_usuario'     => (int)($row['id_usuario'] ?? 0),
        'nombre'         => $row['nombre'] ?? $row['correo'],
        'email'          => $row['correo'],
        'telefono'       => $row['telefono'] ?? '',
        'avatar'         => $avatar,
        'puntuacion'     => $hasPuntuacion ? (float)$row['puntuacion']    : 0.0,
        'hora_inicio'    => $hasHorario    ? ($row['hora_inicio'] ?? '')  : '',
        'hora_fin'       => $hasHorario    ? ($row['hora_fin']    ?? '')  : '',
        'zona_trabajo'   => $hasZona       ? ($row['zona_trabajo'] ?? '') : '',
        'paseos_mes'     => $hasPaseosMes  ? (int)$row['paseos_mes']      : 0,
        'paseos_totales' => $hasPaseosTot  ? (int)$row['paseos_totales']  : 0,
        'estado'         => $estado,
        // ── Campos nuevos para el módulo de mapas ──
        'lat'            => ($tablaGps && $row['lat'] !== null) ? (float)$row['lat'] : null,
        'lng'            => ($tablaGps && $row['lng'] !== null) ? (float)$row['lng'] : null,
        'velocidad'      => ($tablaGps && isset($row['velocidad'])) ? (float)$row['velocidad'] : 0,
        'ultima_pos'     => ($tablaGps && !empty($row['fecha_actualizacion'])) ? $row['fecha_actualizacion'] : null,
        'id_ruta_activa' => ($tablaRutas && !empty($row['id_ruta'])) ? (int)$row['id_ruta'] : null,
        // ── Campos originales conservados ──
        'clientes'       => [],
        'hora_servidor'  => $ahoraStr,
    ];
}

echo json_encode(['success' => true, 'paseadores' => $paseadores]);
$conn->close();
?>