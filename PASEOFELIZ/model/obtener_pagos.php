<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/helpers_membresia.php';
require_once __DIR__ . '/conexion.php';

desactivarMembresiasVencidas($conn);

$resultado = [
    'pagos_recientes' => [],
    'usuarios'        => [],
];

// ── 1. PAGOS RECIENTES (últimos 20) ─────────────────────────────
$sqlPagos = "
    SELECT
        p.id_pago,
        p.tipo_membresia,
        p.monto,
        p.fecha_pago,
        p.metodo_pago,
        u.id        AS id_usuario,
        u.nombre,
        u.email,
        i.avatar_url
    FROM pagos p
    INNER JOIN usuarios u ON u.id = p.id_usuario
    LEFT  JOIN info_usuario i ON i.id_usuario = u.id
    ORDER BY p.fecha_pago DESC
    LIMIT 20
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

// ── 2. USUARIOS CLIENTES con estado de membresía ────────────────
// usuarios sin rol paseador/admin: la tabla usuarios no tiene columna rol,
// paseadores están en tabla paseadores y admins en tabla admin
$sqlUsuarios = "
    SELECT
        u.id,
        u.nombre,
        u.email,
        i.avatar_url,
        m.paseos,
        m.adiestramiento,
        m.hospedaje,
        m.fecha_fin_paseos,
        m.fecha_fin_adiestramiento,
        m.fecha_fin_hospedaje
    FROM usuarios u
    LEFT JOIN info_usuario i  ON i.id_usuario = u.id
    LEFT JOIN membresias m    ON m.id_usuario = u.id
    WHERE u.id NOT IN (SELECT id_usuario FROM paseadores)
      AND u.id NOT IN (SELECT id_usuario FROM admin)
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
            'servicios'      => $estado['servicios'],
        ];
    }
}

// ── 3. STATS ────────────────────────────────────────────────────
$statPagos   = $conn->query("SELECT COUNT(*) AS c FROM pagos");
$statActivos = $conn->query("SELECT COUNT(*) AS c FROM membresias WHERE paseos=1 OR adiestramiento=1 OR hospedaje=1");
$statIngres  = $conn->query("SELECT COALESCE(SUM(monto),0) AS t FROM pagos WHERE MONTH(fecha_pago)=MONTH(NOW()) AND YEAR(fecha_pago)=YEAR(NOW())");

$resultado['stats'] = [
    'total_pagos'        => (int)$statPagos->fetch_assoc()['c'],
    'membresias_activas' => (int)$statActivos->fetch_assoc()['c'],
    'ingresos_mes'       => (float)$statIngres->fetch_assoc()['t'],
];

header('Content-Type: application/json');
echo json_encode(['success' => true] + $resultado);
$conn->close();