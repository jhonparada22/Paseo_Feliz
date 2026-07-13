<?php
/**
 * obtener_historial_paseador.php
 * Historial real de paseos COMPLETADOS del paseador logueado (pestaña
 * "Historial Completo" de Mis Paseos). Una fila por cada entrega
 * completada dentro de una ruta ya finalizada.
 *
 * GET sin parámetros (todo sale de la sesión).
 * Respuesta: { success, historial: [{fecha, mascota, dueno, direccion,
 *              duracion_min, duracion_estimada}] }
 */
header("Access-Control-Allow-Origin: *");
include_once 'helpers.php';
include_once '../model/conexion.php';

$idPaseador = obtenerIdPaseadorSesion($conn);

$stmt = $conn->prepare(
    "SELECT r.fecha_paseo, r.fecha_inicio_real, r.duracion_estimada_min,
            rp.direccion, rp.hora_completado,
            u.nombre AS cliente, mu.nombre_mascota
     FROM rutas r
     JOIN ruta_paradas rp ON rp.id_ruta = r.id_ruta
     LEFT JOIN usuarios u        ON u.id = rp.id_usuario_cliente
     LEFT JOIN mascota_usuario mu ON mu.id_mascota = rp.id_mascota
     WHERE r.id_paseador = ? AND r.id_estado = 4
       AND rp.tipo = 'entrega' AND rp.id_estado = 3
       AND rp.id_usuario_cliente IS NOT NULL
     ORDER BY r.fecha_paseo DESC, rp.hora_completado DESC
     LIMIT 60"
);
$stmt->bind_param("i", $idPaseador);
$stmt->execute();
$res = $stmt->get_result();

$historial = [];
while ($row = $res->fetch_assoc()) {
    $duracionMin = null;
    if ($row['fecha_inicio_real'] && $row['hora_completado']) {
        $duracionMin = (int)round(
            (strtotime($row['hora_completado']) - strtotime($row['fecha_inicio_real'])) / 60
        );
        if ($duracionMin < 0) $duracionMin = null; // dato inconsistente, se descarta
    }

    $historial[] = [
        'fecha'              => $row['fecha_paseo'],
        'mascota'            => $row['nombre_mascota'] ?? '—',
        'dueno'               => $row['cliente'] ?? '—',
        'direccion'          => $row['direccion'],
        'duracion_min'       => $duracionMin,
        'duracion_estimada'  => (int)$row['duracion_estimada_min'],
    ];
}
$stmt->close();

responder(true, ['historial' => $historial]);
?>
