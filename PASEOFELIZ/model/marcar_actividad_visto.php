<?php
/**
 * marcar_actividad_visto.php
 * (ADMIN) Marca un reporte del Centro de Actividad como "visto": se oculta
 * del feed sin borrarlo (queda el histórico). Requiere la columna
 * actividad_sistema.visto (migración fase 17b); si aún no existe, responde
 * de forma controlada sin romper.
 *
 * POST JSON: { "id": 129 }
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once '../model/conexion.php';

verificarAdmin();
$data = leerJsonBody();
$id   = intval($data['id'] ?? 0);
if (!$id) responder(false, [], 'id requerido.');

try {
    $stmt = $conn->prepare("UPDATE actividad_sistema SET visto = 1 WHERE id_actividad = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    responder(true, ['id' => $id], 'Reporte marcado como visto.');
} catch (mysqli_sql_exception $e) {
    // 1054 = columna desconocida (migración fase 17b pendiente)
    if ((int)$e->getCode() === 1054) {
        responder(false, [], 'Falta ejecutar la migración fase 17b (columna visto).');
    }
    throw $e;
}
?>
