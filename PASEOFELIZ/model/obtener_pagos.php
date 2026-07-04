<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/helpers_membresia.php';
require_once __DIR__ . '/conexion.php';

desactivarMembresiasVencidas($conn);

$resultado = ['pagos_recientes' => [], 'usuarios' => []];

// ── 1. PAGOS RECIENTES ───────────────────────────────────────
$sqlPagos = "
    SELECT p.id_pago, p.tipo_membresia, p.monto, p.fecha_pago, p.metodo_pago,
           u.id AS id_usuario, u.nombre, u.email, i.avatar_url
    FROM pagos p
    INNER JOIN usuarios u ON u.id = p.id_usuario
    LEFT  JOIN info_usuario i ON i.id_usuario = u.id
    WHERE p.tipo_membresia IS NOT NULL AND p.tipo_membresia != ''
    ORDER BY p.fecha_pago DESC LIMIT 20
";
$res = $conn->query($sqlPagos);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $resultado['pagos_recientes'][] = [
            'id_pago'        => (int)$row['id_pago'],
            'tipo_membresia' => $row['tipo_membresia'],
            'monto'          => (float)$row['monto'],
            'fecha_pago'     => $row['fecha_pago'],
            'metodo_pago'    => $row['metodo_pago'],
            'id_usuario'     => (int)$row['id_usuario'],
            'nombre'         => $row['nombre'],
            'email'          => $row['email'],
            'avatar_url'     => $row['avatar_url'] ?? null,
        ];
    }
}

// ── 2. USUARIOS CLIENTES ─────────────────────────────────────
$sqlUsuarios = "
    SELECT u.id, u.nombre, u.email, i.avatar_url,
           m.paseos, m.adiestramiento, m.hospedaje,
           m.fecha_fin_paseos, m.fecha_fin_adiestramiento, m.fecha_fin_hospedaje
    FROM usuarios u
    LEFT JOIN info_usuario i ON i.id_usuario = u.id
    LEFT JOIN membresias m   ON m.id_usuario = u.id
    LEFT JOIN paseadores pa ON pa.id_usuario = u.id
    LEFT JOIN admin ad       ON ad.id_usuario = u.id
    WHERE pa.id_usuario IS NULL
      AND ad.id_usuario IS NULL
    ORDER BY u.nombre ASC
";
$res2 = $conn->query($sqlUsuarios);
if ($res2) {
    while ($row = $res2->fetch_assoc()) {
        $estado = calcularEstadoMembresia($row);
        $resultado['usuarios'][] = [
            'id'             => (int)$row['id'],
            'nombre'         => $row['nombre'],
            'email'          => $row['email'],
            'avatar_url'     => $row['avatar_url'] ?? null,
            'activa'         => $estado['activa'],
            'dias_restantes' => $estado['dias_restantes'],
            'servicios'      => $estado['servicios'], // { "Paseos": 28, "Adiestramiento": 15 }
        ];
    }
}

// ── 3. STATS ─────────────────────────────────────────────────
$resultado['stats'] = [
    'miembros_con_membresia' => (int)$conn->query("SELECT COUNT(*) AS c FROM membresias WHERE paseos=1 OR adiestramiento=1 OR hospedaje=1")->fetch_assoc()['c'],
    'paseos_activos'         => (int)$conn->query("SELECT COUNT(*) AS c FROM membresias WHERE paseos=1")->fetch_assoc()['c'],
    'adiestramiento_activos' => (int)$conn->query("SELECT COUNT(*) AS c FROM membresias WHERE adiestramiento=1")->fetch_assoc()['c'],
    'hospedaje_activos'      => (int)$conn->query("SELECT COUNT(*) AS c FROM membresias WHERE hospedaje=1")->fetch_assoc()['c'],
    'ingresos_totales'       => (float)$conn->query("SELECT COALESCE(SUM(monto),0) AS t FROM pagos")->fetch_assoc()['t'],
];

header('Content-Type: application/json');
echo json_encode(['success' => true] + $resultado);
$conn->close();