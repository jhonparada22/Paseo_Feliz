<?php
/**
 * iniciar_dia_paseador.php
 * El PASEADOR pulsa "Empezar paseos" en su dashboard: obtiene su ruta activa
 * de hoy (creándola si no existe) y le agrega los pedidos del cronograma
 * semanal (cronograma_paseos) que todavía no tengan parada en esa ruta.
 *
 * Si el admin ya había creado una ruta manual/urgente para este paseador
 * hoy (guardar_ruta.php), esta acción la reutiliza y le suma los pedidos
 * del cronograma en vez de crear una ruta paralela — así solo puede existir
 * una ruta activa por (paseador, fecha), reforzado por el UNIQUE KEY
 * uq_ruta_activa (ver sql/migraciones/2026_07_fase1_consolidar_rutas.sql).
 *
 * POST sin cuerpo (todo sale de la sesión y del cronograma).
 * Respuesta: { success, id_ruta, existente, agregados }
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
$horaInicio = date('H:i:s');

// ── 1. Ruta activa de hoy: reutilizar (cronograma o manual del admin) o crear ──
$r = obtenerOCrearRutaHoy($conn, $idUsuario, $idPaseador, $hoy, $horaInicio);
$idRuta       = $r['id_ruta'];
$rutaEraNueva = $r['nueva'];

// ── 2. Pedidos del cronograma de hoy que AÚN NO están en esta ruta ────────
$stmt = $conn->prepare(
    "SELECT p.id_pedido, p.id_usuario, p.id_mascota, p.direccion, p.barrio,
            p.lat, p.lng, p.franja_horaria
     FROM cronograma_paseos c
     JOIN pedidos_paseo p ON p.id_pedido = c.id_pedido
     WHERE c.id_paseador = ? AND c.dia_semana = ?
       AND p.estado NOT IN ('cancelado','pendiente_pago','pago_fallido')
       AND NOT EXISTS (
         SELECT 1 FROM ruta_paradas rp WHERE rp.id_ruta = ? AND rp.id_pedido = p.id_pedido
       )
     ORDER BY p.franja_horaria ASC, c.id_cronograma ASC"
);
$stmt->bind_param("iii", $idPaseador, $diaSemana, $idRuta);
$stmt->execute();
$res = $stmt->get_result();
$pedidos = [];
while ($row = $res->fetch_assoc()) $pedidos[] = $row;
$stmt->close();

if (!$pedidos) {
    if (!$rutaEraNueva) {
        responder(true, ['id_ruta' => $idRuta, 'existente' => true, 'agregados' => 0],
            'Ya tienes una ruta activa para hoy. Continuando con ella.');
    }
    responder(false, [], 'No tienes paseos programados para hoy en tu cronograma.');
}

// ── 3. Insertar las paradas nuevas (recogida + entrega) ───────────────────
$etiquetas = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

$stmt = $conn->prepare("SELECT COALESCE(MAX(orden), -1) AS maxOrden FROM ruta_paradas WHERE id_ruta = ?");
$stmt->bind_param("i", $idRuta);
$stmt->execute();
$orden = (int)$stmt->get_result()->fetch_assoc()['maxOrden'] + 1;
$stmt->close();

$conn->begin_transaction();
try {
    $stmtParada = $conn->prepare(
        "INSERT INTO ruta_paradas (id_ruta, orden, etiqueta, tipo, direccion, lat, lng, id_usuario_cliente, id_mascota, id_pedido, id_estado)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
    );
    $stmtCliente = $conn->prepare(
        "INSERT IGNORE INTO ruta_clientes (id_ruta, id_usuario_cliente, id_mascota) VALUES (?, ?, ?)"
    );

    foreach (['recogida', 'entrega'] as $tipo) {
        foreach ($pedidos as $p) {
            $etiqueta  = $etiquetas[$orden % 26];
            $lat       = (float)$p['lat'];
            $lng       = (float)$p['lng'];
            $idCliente = (int)$p['id_usuario'];
            $idMascota = (int)$p['id_mascota'];
            $idPedido  = (int)$p['id_pedido'];
            $direccion = $p['direccion'] . ($p['barrio'] ? ', ' . $p['barrio'] : '');

            $stmtParada->bind_param(
                "iisssddiii",
                $idRuta, $orden, $etiqueta, $tipo, $direccion, $lat, $lng, $idCliente, $idMascota, $idPedido
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

    reordenarParadasPendientes($conn, $idRuta);
    recalcularDistanciaYDuracion($conn, $idRuta);

    $conn->commit();
    responder(true, [
        'id_ruta'   => $idRuta,
        'existente' => !$rutaEraNueva,
        'agregados' => count($pedidos),
    ], $rutaEraNueva
        ? 'Ruta del día creada con ' . count($pedidos) . ' cliente(s).'
        : 'Se agregaron ' . count($pedidos) . ' cliente(s) nuevo(s) a tu ruta activa de hoy.');
} catch (Exception $e) {
    $conn->rollback();
    responder(false, [], 'Error al crear la ruta del día: ' . $e->getMessage());
}
?>
