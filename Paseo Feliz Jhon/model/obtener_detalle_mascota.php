<?php
/**
 * obtener_detalle_mascota.php
 * Detalle completo de UNA mascota para el panel de admin (usuarios_admin.php):
 * datos propios + dueño + servicios asociados (plan, último/próximo paseo,
 * paseador asignado). Reutiliza los mismos cálculos de
 * model/estado_servicio_paseos.php, pero parametrizados por id_mascota en
 * vez de por la sesión del cliente.
 *
 * GET ?id_mascota=X
 */
header("Access-Control-Allow-Origin: *");
include_once 'helpers.php';
include_once '../model/conexion.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
date_default_timezone_set('America/Bogota');

verificarAdmin();

$idMascota = intval($_GET['id_mascota'] ?? 0);
if ($idMascota <= 0) {
    responder(false, [], 'id_mascota inválido.');
}

// ── 1. Datos propios de la mascota ────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT id_mascota, id_usuario, nombre_mascota, raza, edad, avatar_mascota,
            biografia_canina, enfermedades_discapacidades, fecha_registro
     FROM mascota_usuario WHERE id_mascota = ?"
);
$stmt->bind_param("i", $idMascota);
$stmt->execute();
$mascota = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$mascota) {
    responder(false, [], 'Mascota no encontrada.');
}

$idUsuarioDueno = (int)$mascota['id_usuario'];

// Avatar normalizado a ruta absoluta desde la raíz del sitio (mismo patrón
// que model/obtener_usuarios.php)
$avatar = !empty($mascota['avatar_mascota'])
    ? 'assets/' . ltrim(preg_replace('#^(\.\./)*assets/#', '', $mascota['avatar_mascota']), '/')
    : '';

// ── 2. Dueño (contacto) ────────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT u.nombre, u.email, iu.telefono
     FROM usuarios u
     LEFT JOIN info_usuario iu ON iu.id_usuario = u.id
     WHERE u.id = ?"
);
$stmt->bind_param("i", $idUsuarioDueno);
$stmt->execute();
$dueno = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── 3. Pedido más reciente de ESTA mascota (activo o no) ───────────────
$stmt = $conn->prepare(
    "SELECT p.id_pedido, p.franja_horaria,
            pl.nombre AS plan_nombre, pl.paseos_mes,
            m.paseos AS membresia_activa, m.fecha_inicio_paseos,
            m.fecha_fin_paseos AS fecha_renovacion
     FROM pedidos_paseo p
     LEFT JOIN planes_paseos pl ON pl.id_plan = p.id_plan
     LEFT JOIN membresias m     ON m.id_usuario = p.id_usuario
     WHERE p.id_mascota = ?
     ORDER BY p.fecha_creacion DESC
     LIMIT 1"
);
$stmt->bind_param("i", $idMascota);
$stmt->execute();
$pedido = $stmt->get_result()->fetch_assoc();
$stmt->close();

$plan = null;
$proximoPaseo = null;
$asignacion = null;

if ($pedido) {
    $activa = false;
    if ((int)($pedido['membresia_activa'] ?? 0) === 1 && !empty($pedido['fecha_renovacion'])) {
        $finMembresia = new DateTime($pedido['fecha_renovacion'], new DateTimeZone('America/Bogota'));
        $ahora = new DateTime('now', new DateTimeZone('America/Bogota'));
        $activa = $ahora < $finMembresia;
    }

    $plan = [
        'nombre'     => $pedido['plan_nombre'] ?? '',
        'paseos_mes' => (int)($pedido['paseos_mes'] ?? 0),
        'activa'     => $activa,
    ];

    // ── Cronograma: próximo día asignado + paseador ────────────────────
    $idPedido = (int)$pedido['id_pedido'];
    $stmt = $conn->prepare(
        "SELECT id_paseador, dia_semana FROM cronograma_paseos WHERE id_pedido = ? ORDER BY dia_semana ASC"
    );
    $stmt->bind_param("i", $idPedido);
    $stmt->execute();
    $res = $stmt->get_result();
    $crono = [];
    while ($row = $res->fetch_assoc()) $crono[] = $row;
    $stmt->close();

    if ($crono) {
        $diasNombres = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
                        5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
        $hoyDia = (int)date('N');
        $mejorDelta = 8;
        $filaProxima = $crono[0];
        foreach ($crono as $fila) {
            $delta = ((int)$fila['dia_semana'] - $hoyDia + 7) % 7;
            if ($delta < $mejorDelta) { $mejorDelta = $delta; $filaProxima = $fila; }
        }

        $proximoPaseo = [
            'fecha'      => date('Y-m-d', strtotime("+$mejorDelta days")),
            'dia_nombre' => $diasNombres[(int)$filaProxima['dia_semana']],
            'franja'     => $pedido['franja_horaria'] ?? '',
        ];

        $idPaseadorAsig = (int)$filaProxima['id_paseador'];
        $stmt = $conn->prepare(
            "SELECT pa.puntuacion, u.nombre
             FROM paseadores pa JOIN usuarios u ON u.id = pa.id_usuario
             WHERE pa.id_paseador = ?"
        );
        $stmt->bind_param("i", $idPaseadorAsig);
        $stmt->execute();
        $pas = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($pas) {
            $asignacion = [
                'nombre'     => $pas['nombre'],
                'puntuacion' => (float)$pas['puntuacion'],
            ];
        }
    }
}

// ── 4. Último paseo completado ──────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT r.fecha_fin_real, u2.nombre AS paseador_nombre
     FROM rutas r
     JOIN ruta_paradas rp ON rp.id_ruta = r.id_ruta
     JOIN paseadores pa2  ON pa2.id_paseador = r.id_paseador
     JOIN usuarios u2     ON u2.id = pa2.id_usuario
     WHERE rp.id_mascota = ? AND rp.tipo = 'entrega' AND rp.id_estado = 3 AND r.id_estado = 4
     ORDER BY r.fecha_fin_real DESC
     LIMIT 1"
);
$stmt->bind_param("i", $idMascota);
$stmt->execute();
$ultimo = $stmt->get_result()->fetch_assoc();
$stmt->close();

$ultimoPaseo = $ultimo ? [
    'fecha'           => $ultimo['fecha_fin_real'],
    'paseador_nombre' => $ultimo['paseador_nombre'],
] : null;

responder(true, [
    'mascota' => [
        'id_mascota'     => (int)$mascota['id_mascota'],
        'nombre'         => $mascota['nombre_mascota'],
        'raza'           => $mascota['raza'] ?? '',
        'edad'           => $mascota['edad'] !== null ? (int)$mascota['edad'] : null,
        'avatar'         => $avatar,
        'biografia'      => $mascota['biografia_canina'] ?? '',
        'notas'          => $mascota['enfermedades_discapacidades'] ?? '',
        'fecha_registro' => $mascota['fecha_registro'],
        'dueno' => [
            'nombre'   => $dueno['nombre'] ?? '',
            'telefono' => $dueno['telefono'] ?? '',
            'email'    => $dueno['email'] ?? '',
        ],
        'plan'          => $plan,
        'ultimo_paseo'  => $ultimoPaseo,
        'proximo_paseo' => $proximoPaseo,
        'asignacion'    => $asignacion,
    ],
]);
?>
