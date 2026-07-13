<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/helpers_membresia.php';
require_once __DIR__ . '/conexion.php';

desactivarMembresiasVencidas($conn);

$resultado = ['pagos_recientes' => [], 'usuarios' => []];

// ── 1. PAGOS RECIENTES (ahora con la mascota a la que se le aplicó) ──
$sqlPagos = "
    SELECT p.id_pago, p.id_mascota, p.tipo_membresia, p.monto, p.fecha_pago, p.metodo_pago,
           u.id AS id_usuario, u.nombre, u.email, i.avatar_url,
           mu.nombre_mascota
    FROM pagos p
    INNER JOIN usuarios u ON u.id = p.id_usuario
    LEFT  JOIN info_usuario i ON i.id_usuario = u.id
    LEFT  JOIN mascota_usuario mu ON mu.id_mascota = p.id_mascota
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
            'id_mascota'     => $row['id_mascota'] !== null ? (int)$row['id_mascota'] : null,
            'nombre_mascota' => $row['nombre_mascota'], // null = pago histórico sin mascota asignada
        ];
    }
}

// ── 2. USUARIOS CLIENTES + sus mascotas + membresía de cada mascota ──
// (excluye paseadores y admins, igual que antes)
$sqlUsuarios = "
    SELECT u.id, u.nombre, u.email, i.avatar_url
    FROM usuarios u
    LEFT JOIN info_usuario i ON i.id_usuario = u.id
    LEFT JOIN paseadores pa ON pa.id_usuario = u.id
    LEFT JOIN admin ad       ON ad.id_usuario = u.id
    WHERE pa.id_usuario IS NULL
      AND ad.id_usuario IS NULL
    ORDER BY u.nombre ASC
";
$res2 = $conn->query($sqlUsuarios);
$usuariosBase = [];
if ($res2) {
    while ($row = $res2->fetch_assoc()) {
        $usuariosBase[(int)$row['id']] = $row;
    }
}

// Todas las mascotas, agrupadas por id_usuario
$mascotasPorUsuario = [];
$resM = $conn->query("SELECT id_mascota, id_usuario, nombre_mascota, avatar_mascota FROM mascota_usuario ORDER BY id_mascota ASC");
if ($resM) {
    while ($row = $resM->fetch_assoc()) {
        $mascotasPorUsuario[(int)$row['id_usuario']][] = $row;
    }
}

// Todas las membresías: por mascota (normal) y huérfanas sin mascota (legado)
$membresiaPorMascota      = [];  // id_mascota => fila
$membresiasSinMascotaPorU = [];  // id_usuario => [filas]
$resMem = $conn->query("SELECT * FROM membresias");
if ($resMem) {
    while ($row = $resMem->fetch_assoc()) {
        if ($row['id_mascota'] !== null) {
            $membresiaPorMascota[(int)$row['id_mascota']] = $row;
        } else {
            $membresiasSinMascotaPorU[(int)$row['id_usuario']][] = $row;
        }
    }
}

foreach ($usuariosBase as $idUsuario => $u) {
    $mascotasDelUsuario = $mascotasPorUsuario[$idUsuario] ?? [];

    $listaMascotas    = [];
    $anyActiva        = false;
    $serviciosResumen = []; // { "Paseos": diasMinimos, ... } para el badge resumido

    foreach ($mascotasDelUsuario as $m) {
        $memRow = $membresiaPorMascota[(int)$m['id_mascota']] ?? null;
        $estado = $memRow ? calcularEstadoMembresia($memRow) : ['activa' => false, 'dias_restantes' => null, 'servicios' => []];

        if ($estado['activa']) $anyActiva = true;
        foreach ($estado['servicios'] as $label => $dias) {
            if (!isset($serviciosResumen[$label]) || $dias < $serviciosResumen[$label]) {
                $serviciosResumen[$label] = $dias;
            }
        }

        $listaMascotas[] = [
            'id_mascota'     => (int)$m['id_mascota'],
            'nombre_mascota' => $m['nombre_mascota'],
            'avatar_mascota' => $m['avatar_mascota'],
            'activa'         => $estado['activa'],
            'dias_restantes' => $estado['dias_restantes'],
            'servicios'      => $estado['servicios'], // { "Paseos": 28 }
        ];
    }

    // Membresías "huérfanas" (de antes de la migración a por-mascota):
    // el usuario pagó pero no tiene ninguna mascota registrada.
    $tieneMembresiaSinMascota = false;
    foreach ($membresiasSinMascotaPorU[$idUsuario] ?? [] as $mr) {
        $estadoO = calcularEstadoMembresia($mr);
        if ($estadoO['activa']) {
            $anyActiva = true;
            $tieneMembresiaSinMascota = true;
            foreach ($estadoO['servicios'] as $label => $dias) {
                if (!isset($serviciosResumen[$label]) || $dias < $serviciosResumen[$label]) {
                    $serviciosResumen[$label] = $dias;
                }
            }
        }
    }

    $resultado['usuarios'][] = [
        'id'                          => $idUsuario,
        'nombre'                      => $u['nombre'],
        'email'                       => $u['email'],
        'avatar_url'                  => $u['avatar_url'] ?? null,
        'activa'                      => $anyActiva,
        'servicios'                   => $serviciosResumen, // badge resumido (igual que antes)
        'mascotas'                    => $listaMascotas,    // NUEVO: detalle por mascota
        'tiene_mascotas'              => count($listaMascotas) > 0,
        'tiene_membresia_sin_mascota' => $tieneMembresiaSinMascota, // aviso: revisar/reasignar
    ];
}

// ── 3. STATS ─────────────────────────────────────────────────
// miembros_con_membresia: usuarios distintos con algo activo (en cualquier mascota)
// paseos/adiestramiento/hospedaje_activos: ahora cuentan SUSCRIPCIONES activas
// (una por mascota), ya no usuarios — refleja mejor el nuevo modelo por mascota.
$resultado['stats'] = [
    'miembros_con_membresia' => (int)$conn->query("SELECT COUNT(DISTINCT id_usuario) AS c FROM membresias WHERE paseos=1 OR adiestramiento=1 OR hospedaje=1")->fetch_assoc()['c'],
    'paseos_activos'         => (int)$conn->query("SELECT COUNT(*) AS c FROM membresias WHERE paseos=1")->fetch_assoc()['c'],
    'adiestramiento_activos' => (int)$conn->query("SELECT COUNT(*) AS c FROM membresias WHERE adiestramiento=1")->fetch_assoc()['c'],
    'hospedaje_activos'      => (int)$conn->query("SELECT COUNT(*) AS c FROM membresias WHERE hospedaje=1")->fetch_assoc()['c'],
    'ingresos_totales'       => (float)$conn->query("SELECT COALESCE(SUM(monto),0) AS t FROM pagos")->fetch_assoc()['t'],
];

header('Content-Type: application/json');
echo json_encode(['success' => true] + $resultado);
$conn->close();