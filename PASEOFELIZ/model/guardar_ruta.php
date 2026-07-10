<?php
/**
 * guardar_ruta.php
 * (ADMIN) Agrega paradas a la ruta activa de hoy del paseador (creándola si
 * no existe) desde el panel admin — botón "Asignar ruta al paseador" en el
 * tab "Asignar ruta" del mapa. Converge con iniciar_dia_paseador.php: si el
 * paseador ya tiene una ruta activa ese día (por cronograma o por una
 * asignación manual anterior), esta acción le suma paradas en vez de crear
 * una ruta paralela.
 *
 * POST JSON esperado:
 * {
 *   "id_paseador": 2,
 *   "fecha": "2026-06-30",
 *   "hora": "08:00",
 *   "puntos": [
 *     { "lat":7.89, "lng":-72.50, "addr":"Calle 7 #0e-94", "etiqueta":"A",
 *       "tipo":"recogida", "id_usuario_cliente":5, "id_mascota":3, "id_pedido":10 },
 *     ...
 *   ]
 * }
 *
 * Respuesta: { success, id_ruta, ruta_nueva }
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once 'helpers_paseos_programados.php';
include_once '../model/conexion.php';

// Errores SQL como excepciones -> los captura el try/catch y responden JSON limpio
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

verificarAdmin();

$data = leerJsonBody();

$idPaseador = intval($data['id_paseador'] ?? 0);
$fecha      = $data['fecha'] ?? null;
$hora       = $data['hora'] ?? '08:00';
$puntos     = $data['puntos'] ?? [];

if (!$idPaseador || !$fecha || count($puntos) < 2) {
    responder(false, [], 'Faltan datos: paseador, fecha y al menos 2 puntos.');
}

$idAdmin = (int)$_SESSION['usuario_id'];

$conn->begin_transaction();
try {
    // 1. Ruta activa de hoy para este paseador: reutilizar o crear
    $r = obtenerOCrearRutaHoy($conn, $idAdmin, $idPaseador, $fecha, $hora);
    $idRuta       = $r['id_ruta'];
    $rutaEraNueva = $r['nueva'];

    // 2. Insertar paradas nuevas, continuando el orden desde el máximo actual
    $stmt = $conn->prepare("SELECT COALESCE(MAX(orden), -1) AS maxOrden FROM ruta_paradas WHERE id_ruta = ?");
    $stmt->bind_param("i", $idRuta);
    $stmt->execute();
    $orden = (int)$stmt->get_result()->fetch_assoc()['maxOrden'] + 1;
    $stmt->close();

    // Con la migración fase 11 cada parada guarda el id de su paseo
    // programado (la instancia se crea al vuelo si el punto es manual).
    $tienePP = true;
    try {
        $stmtParada = $conn->prepare(
            "INSERT INTO ruta_paradas (id_ruta, orden, etiqueta, tipo, direccion, lat, lng, id_usuario_cliente, id_mascota, id_pedido, id_paseo, id_estado)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
        );
    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() !== 1054) throw $e; // 1054 = columna id_paseo aún no migrada
        $tienePP = false;
        $stmtParada = $conn->prepare(
            "INSERT INTO ruta_paradas (id_ruta, orden, etiqueta, tipo, direccion, lat, lng, id_usuario_cliente, id_mascota, id_pedido, id_estado)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
        );
    }
    $stmtCliente = $conn->prepare(
        "INSERT IGNORE INTO ruta_clientes (id_ruta, id_usuario_cliente, id_mascota) VALUES (?, ?, ?)"
    );

    $paseosVinculados = []; // id_pedido -> id_paseo (evita repetir la transición)
    foreach ($puntos as $p) {
        $etiqueta  = $p['etiqueta'] ?? chr(65 + ($orden % 26));
        $tipo      = in_array($p['tipo'] ?? '', ['recogida', 'paseo', 'entrega']) ? $p['tipo'] : 'paseo';
        $idCliente = !empty($p['id_usuario_cliente']) ? intval($p['id_usuario_cliente']) : null;
        $idMascota = !empty($p['id_mascota']) ? intval($p['id_mascota']) : null;
        $idPedido  = !empty($p['id_pedido']) ? intval($p['id_pedido']) : null;

        // Vincular (o crear) el paseo programado del pedido para esta fecha
        $idPaseoPP = null;
        if ($tienePP && $idPedido) {
            if (!array_key_exists($idPedido, $paseosVinculados)) {
                $paseosVinculados[$idPedido] = transicionPaseoProgramado($conn, $idPedido, $fecha, 'en_ruta', [
                    'id_ruta'     => $idRuta,
                    'id_paseador' => $idPaseador,
                    'actor'       => 'admin',
                    'detalle'     => 'Agregado a una ruta manual desde el mapa del admin',
                ]);
            }
            $idPaseoPP = $paseosVinculados[$idPedido];
        }

        if ($tienePP) {
            $stmtParada->bind_param(
                "iisssddiiii",
                $idRuta, $orden, $etiqueta, $tipo, $p['addr'], $p['lat'], $p['lng'], $idCliente, $idMascota, $idPedido, $idPaseoPP
            );
        } else {
            $stmtParada->bind_param(
                "iisssddiii",
                $idRuta, $orden, $etiqueta, $tipo, $p['addr'], $p['lat'], $p['lng'], $idCliente, $idMascota, $idPedido
            );
        }
        $stmtParada->execute();
        $orden++;

        if ($idCliente && $idMascota) {
            $stmtCliente->bind_param("iii", $idRuta, $idCliente, $idMascota);
            $stmtCliente->execute();
        }
    }
    $stmtParada->close();
    $stmtCliente->close();

    // 3. Reordenar por franja+cercanía y recalcular distancia/duración estimada
    reordenarParadasPendientes($conn, $idRuta);
    recalcularDistanciaYDuracion($conn, $idRuta);

    $conn->commit();
    responder(true, ['id_ruta' => $idRuta, 'ruta_nueva' => $rutaEraNueva], $rutaEraNueva
        ? 'Ruta creada y asignada correctamente.'
        : 'Se agregaron ' . count($puntos) . ' parada(s) a la ruta activa del paseador.');
} catch (Exception $e) {
    $conn->rollback();
    responder(false, [], 'Error al guardar la ruta: ' . $e->getMessage());
}
?>
