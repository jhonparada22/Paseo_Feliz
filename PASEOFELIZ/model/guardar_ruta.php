<?php
/**
 * guardar_ruta.php
 * Crea una ruta nueva (con sus paradas) y la asigna directamente a un paseador.
 * Corresponde al botón "Asignar ruta al paseador" del panel admin.
 *
 * POST JSON esperado:
 * {
 *   "id_paseador": 2,
 *   "fecha": "2026-06-30",
 *   "hora": "08:00",
 *   "puntos": [
 *     { "lat":7.89, "lng":-72.50, "addr":"Calle 7 #0e-94", "etiqueta":"A",
 *       "tipo":"recogida", "id_usuario_cliente":5, "id_mascota":3 },
 *     ...
 *   ]
 * }
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
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
    // 1. Calcular distancia estimada sumando tramos (línea recta entre puntos)
    $distanciaKm = 0;
    for ($i = 0; $i < count($puntos) - 1; $i++) {
        $distanciaKm += distanciaMetros(
            $puntos[$i]['lat'], $puntos[$i]['lng'],
            $puntos[$i + 1]['lat'], $puntos[$i + 1]['lng']
        ) / 1000;
    }
    $duracionMin = (int)round($distanciaKm * 12); // estimación simple: ~5km/h caminando

    // 2. Crear la ruta (estado 1 = pendiente)
    $stmt = $conn->prepare(
        "INSERT INTO rutas (id_admin_creador, id_paseador, id_estado, fecha_paseo, hora_inicio, distancia_estimada_km, duracion_estimada_min)
         VALUES (?, ?, 1, ?, ?, ?, ?)"
    );
    $stmt->bind_param("iissdi", $idAdmin, $idPaseador, $fecha, $hora, $distanciaKm, $duracionMin);
    $stmt->execute();
    $idRuta = $conn->insert_id;
    $stmt->close();

    // 3. Insertar paradas en orden + recolectar clientes únicos
    $stmtParada = $conn->prepare(
        "INSERT INTO ruta_paradas (id_ruta, orden, etiqueta, tipo, direccion, lat, lng, id_usuario_cliente, id_mascota, id_estado)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
    );
    $stmtCliente = $conn->prepare(
        "INSERT IGNORE INTO ruta_clientes (id_ruta, id_usuario_cliente, id_mascota) VALUES (?, ?, ?)"
    );

    foreach ($puntos as $i => $p) {
        $etiqueta = $p['etiqueta'] ?? chr(65 + $i); // A, B, C...
        $tipo     = in_array($p['tipo'] ?? '', ['recogida', 'paseo', 'entrega']) ? $p['tipo'] : 'paseo';
        $idCliente = !empty($p['id_usuario_cliente']) ? intval($p['id_usuario_cliente']) : null;
        $idMascota = !empty($p['id_mascota']) ? intval($p['id_mascota']) : null;

        $stmtParada->bind_param(
            "iisssddii",
            $idRuta, $i, $etiqueta, $tipo, $p['addr'], $p['lat'], $p['lng'], $idCliente, $idMascota
        );
        $stmtParada->execute();

        if ($idCliente && $idMascota) {
            $stmtCliente->bind_param("iii", $idRuta, $idCliente, $idMascota);
            $stmtCliente->execute();
        }
    }
    $stmtParada->close();
    $stmtCliente->close();

    $conn->commit();
    responder(true, ['id_ruta' => $idRuta], 'Ruta creada y asignada correctamente.');
} catch (Exception $e) {
    $conn->rollback();
    responder(false, [], 'Error al guardar la ruta: ' . $e->getMessage());
}
?>