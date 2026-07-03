<?php
/**
 * iniciar_dia_paseador.php
 * El PASEADOR pulsa "Empezar paseos" en su dashboard: genera automáticamente
 * la ruta de HOY a partir de su cronograma semanal (cronograma_paseos).
 *
 * - Si ya tiene una ruta activa hoy (pendiente/en_curso/pausada) la reutiliza.
 * - Si no, crea la ruta con paradas de RECOGIDA (una por pedido, ordenadas por
 *   vecino más cercano desde su GPS) y luego las de ENTREGA (mismo orden).
 *
 * POST sin cuerpo (todo sale de la sesión y del cronograma).
 * Respuesta: { success, id_ruta, existente, paradas }
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once '../model/conexion.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$idPaseador = obtenerIdPaseadorSesion($conn);
$idUsuario  = (int)$_SESSION['usuario_id'];
$hoy        = date('Y-m-d');
$diaSemana  = (int)date('N'); // 1=lunes ... 7=domingo

// ── 1. ¿Ya hay ruta activa hoy? Reutilizarla ──────────────────────────
$stmt = $conn->prepare(
    "SELECT id_ruta FROM rutas
     WHERE id_paseador = ? AND fecha_paseo = ? AND id_estado IN (1,2,3)
     ORDER BY hora_inicio ASC LIMIT 1"
);
$stmt->bind_param("is", $idPaseador, $hoy);
$stmt->execute();
$existente = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existente) {
    responder(true, ['id_ruta' => (int)$existente['id_ruta'], 'existente' => true],
        'Ya tienes una ruta activa para hoy. Continuando con ella.');
}

// ── 2. Pedidos del cronograma para el día de hoy ──────────────────────
$stmt = $conn->prepare(
    "SELECT p.id_pedido, p.id_usuario, p.id_mascota, p.direccion, p.barrio,
            p.lat, p.lng, p.franja_horaria
     FROM cronograma_paseos c
     JOIN pedidos_paseo p ON p.id_pedido = c.id_pedido
     WHERE c.id_paseador = ? AND c.dia_semana = ?
     ORDER BY p.franja_horaria ASC, c.id_cronograma ASC"
);
$stmt->bind_param("ii", $idPaseador, $diaSemana);
$stmt->execute();
$res = $stmt->get_result();
$pedidos = [];
while ($row = $res->fetch_assoc()) $pedidos[] = $row;
$stmt->close();

if (!$pedidos) {
    responder(false, [], 'No tienes paseos programados para hoy en tu cronograma.');
}

// ── 3. Ordenar recogidas por vecino más cercano ───────────────────────
// Punto de partida: última posición GPS del paseador (si existe)
$origen = null;
$stmt = $conn->prepare("SELECT lat, lng FROM gps_paseadores WHERE id_paseador = ?");
$stmt->bind_param("i", $idPaseador);
$stmt->execute();
$gps = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($gps) $origen = ['lat' => (float)$gps['lat'], 'lng' => (float)$gps['lng']];

$pendientes = $pedidos;
$ordenados  = [];
$actual     = $origen ?: ['lat' => (float)$pendientes[0]['lat'], 'lng' => (float)$pendientes[0]['lng']];

while ($pendientes) {
    $mejorIdx = 0;
    $mejorDist = PHP_FLOAT_MAX;
    foreach ($pendientes as $idx => $p) {
        $d = distanciaMetros($actual['lat'], $actual['lng'], (float)$p['lat'], (float)$p['lng']);
        if ($d < $mejorDist) { $mejorDist = $d; $mejorIdx = $idx; }
    }
    $elegido = $pendientes[$mejorIdx];
    array_splice($pendientes, $mejorIdx, 1);
    $ordenados[] = $elegido;
    $actual = ['lat' => (float)$elegido['lat'], 'lng' => (float)$elegido['lng']];
}

// ── 4. Crear ruta + paradas + clientes en una transacción ─────────────
$horaInicio  = date('H:i:s');
$etiquetas   = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

// Distancia estimada: cadena de recogidas + regreso (entregas en mismo orden)
$distanciaKm = 0;
$prev = $origen ?: null;
foreach ($ordenados as $p) {
    if ($prev) $distanciaKm += distanciaMetros($prev['lat'], $prev['lng'], (float)$p['lat'], (float)$p['lng']) / 1000;
    $prev = ['lat' => (float)$p['lat'], 'lng' => (float)$p['lng']];
}
$distanciaKm = $distanciaKm * 2; // ida (recogidas) + vuelta (entregas)
$duracionMin = (int)round($distanciaKm * 12) + count($ordenados) * 10;

$conn->begin_transaction();
try {
    // 4.1 Ruta (el paseador figura como creador; FK id_admin_creador -> usuarios)
    $stmt = $conn->prepare(
        "INSERT INTO rutas (id_admin_creador, id_paseador, id_estado, fecha_paseo, hora_inicio, distancia_estimada_km, duracion_estimada_min)
         VALUES (?, ?, 1, ?, ?, ?, ?)"
    );
    $stmt->bind_param("iissdi", $idUsuario, $idPaseador, $hoy, $horaInicio, $distanciaKm, $duracionMin);
    $stmt->execute();
    $idRuta = $conn->insert_id;
    $stmt->close();

    // 4.2 Paradas: recogidas (orden optimizado) y entregas (mismo orden)
    $stmtParada = $conn->prepare(
        "INSERT INTO ruta_paradas (id_ruta, orden, etiqueta, tipo, direccion, lat, lng, id_usuario_cliente, id_mascota, id_estado)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
    );
    $stmtCliente = $conn->prepare(
        "INSERT IGNORE INTO ruta_clientes (id_ruta, id_usuario_cliente, id_mascota) VALUES (?, ?, ?)"
    );

    $orden = 0;
    $tiposParada = ['recogida', 'entrega'];
    foreach ($tiposParada as $tipo) {
        foreach ($ordenados as $p) {
            $etiqueta  = $etiquetas[$orden % 26];
            $lat       = (float)$p['lat'];
            $lng       = (float)$p['lng'];
            $idCliente = (int)$p['id_usuario'];
            $idMascota = (int)$p['id_mascota'];
            $direccion = $p['direccion'] . ($p['barrio'] ? ', ' . $p['barrio'] : '');

            $stmtParada->bind_param(
                "iisssddii",
                $idRuta, $orden, $etiqueta, $tipo, $direccion, $lat, $lng, $idCliente, $idMascota
            );
            $stmtParada->execute();
            $orden++;

            if ($tipo === 'recogida') {
                $stmtCliente->bind_param("iii", $idRuta, $idCliente, $idMascota);
                $stmtCliente->execute();
            }
        }
    }
    $stmtParada->close();
    $stmtCliente->close();

    $conn->commit();
    responder(true, [
        'id_ruta'   => $idRuta,
        'existente' => false,
        'paradas'   => $orden,
    ], 'Ruta del día creada con ' . count($ordenados) . ' cliente(s).');
} catch (Exception $e) {
    $conn->rollback();
    responder(false, [], 'Error al crear la ruta del día: ' . $e->getMessage());
}
?>
