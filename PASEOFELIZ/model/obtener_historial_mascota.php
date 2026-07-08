<?php
/**
 * obtener_historial_mascota.php
 * Historial de paseos COMPLETADOS de UNA mascota (botón "Ver historial de
 * servicios" del panel de admin). Mismo patrón de
 * model/obtener_historial_paseador.php, filtrado por id_mascota en vez de
 * por paseador.
 *
 * GET ?id_mascota=X
 * Respuesta: { success, historial: [{fecha, paseador, duracion_min, duracion_estimada}] }
 */
header("Access-Control-Allow-Origin: *");
include_once 'helpers.php';
include_once '../model/conexion.php';

verificarAdmin();

$idMascota = intval($_GET['id_mascota'] ?? 0);
if ($idMascota <= 0) {
    responder(false, [], 'id_mascota inválido.');
}

$stmt = $conn->prepare(
    "SELECT r.fecha_paseo, r.fecha_inicio_real, r.duracion_estimada_min,
            rp.hora_completado, u.nombre AS paseador
     FROM rutas r
     JOIN ruta_paradas rp ON rp.id_ruta = r.id_ruta
     JOIN paseadores pa   ON pa.id_paseador = r.id_paseador
     JOIN usuarios u      ON u.id = pa.id_usuario
     WHERE rp.id_mascota = ? AND r.id_estado = 4
       AND rp.tipo = 'entrega' AND rp.id_estado = 3
     ORDER BY r.fecha_paseo DESC, rp.hora_completado DESC
     LIMIT 60"
);
$stmt->bind_param("i", $idMascota);
$stmt->execute();
$res = $stmt->get_result();

$historial = [];
while ($row = $res->fetch_assoc()) {
    $duracionMin = null;
    if ($row['fecha_inicio_real'] && $row['hora_completado']) {
        $duracionMin = (int)round(
            (strtotime($row['hora_completado']) - strtotime($row['fecha_inicio_real'])) / 60
        );
        if ($duracionMin < 0) $duracionMin = null;
    }

    $historial[] = [
        'fecha'             => $row['fecha_paseo'],
        'paseador'          => $row['paseador'],
        'duracion_min'      => $duracionMin,
        'duracion_estimada' => (int)$row['duracion_estimada_min'],
    ];
}
$stmt->close();

responder(true, ['historial' => $historial]);
?>
