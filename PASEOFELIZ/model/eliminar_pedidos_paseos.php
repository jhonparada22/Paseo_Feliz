<?php
/**
 * eliminar_pedidos_paseos.php
 * (ADMIN) Elimina DEFINITIVAMENTE uno o varios pedidos de paseos. Pensado
 * para limpiar datos de prueba: quita el pedido y todo lo que cuelga de él.
 *
 * A diferencia de cancelar_pedido_paseos.php (que solo marca 'cancelado'),
 * aquí el pedido desaparece de la base:
 *   - ruta_paradas, solicitudes_cancelacion y actividad_sistema del pedido
 *     se borran explícitamente (no tienen FK en cascada).
 *   - cronograma_paseos, paseos_programados (→ eventos_paseo) y
 *     calificaciones_paseo caen por ON DELETE CASCADE.
 *   - pagos.id_pedido queda en NULL (ON DELETE SET NULL): el pago histórico
 *     se conserva, solo se desliga.
 *   - la membresía de paseos de esa mascota se libera (paseos=0) para poder
 *     volver a comprarle el servicio en las pruebas.
 *
 * POST JSON: { "ids": [22, 21, 20] }  (o { "id_pedido": 22 })
 * Respuesta: { success, eliminados, ids }
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once '../model/conexion.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

verificarAdmin();
$data = leerJsonBody();

$ids = $data['ids'] ?? null;
if (!is_array($ids)) $ids = isset($data['id_pedido']) ? [$data['id_pedido']] : [];
$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

if (!$ids)            responder(false, [], 'No se recibió ningún pedido para eliminar.');
if (count($ids) > 100) responder(false, [], 'Demasiados pedidos a la vez (máx. 100).');

/** Borra de una tabla tolerando que aún no exista (migración pendiente). */
function borrarTolerante($conn, $sql, $id) {
    try {
        $s = $conn->prepare($sql);
        $s->bind_param("i", $id);
        $s->execute();
        $s->close();
    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() !== 1146) throw $e; // 1146 = tabla no existe
    }
}

$conn->begin_transaction();
try {
    $eliminados = [];
    foreach ($ids as $id) {
        // Datos del pedido (para liberar la membresía de su mascota)
        $s = $conn->prepare("SELECT id_usuario, id_mascota FROM pedidos_paseo WHERE id_pedido = ?");
        $s->bind_param("i", $id);
        $s->execute();
        $pedido = $s->get_result()->fetch_assoc();
        $s->close();
        if (!$pedido) continue; // ya no existe

        $idUsuario = (int)$pedido['id_usuario'];
        $idMascota = (int)$pedido['id_mascota'];

        // Hijos sin FK en cascada
        borrarTolerante($conn, "DELETE FROM ruta_paradas WHERE id_pedido = ?", $id);
        borrarTolerante($conn, "DELETE FROM solicitudes_cancelacion WHERE id_pedido = ?", $id);
        borrarTolerante($conn, "DELETE FROM actividad_sistema WHERE id_pedido = ?", $id);

        // Liberar la membresía de paseos de esa mascota (deja intactos
        // adiestramiento/hospedaje de la misma fila)
        $s = $conn->prepare(
            "UPDATE membresias
             SET paseos = 0, fecha_inicio_paseos = NULL, fecha_fin_paseos = NULL, id_pago_paseos = NULL
             WHERE id_usuario = ? AND id_mascota = ?"
        );
        $s->bind_param("ii", $idUsuario, $idMascota);
        $s->execute();
        $s->close();

        // Pedido (cascada: cronograma_paseos, paseos_programados→eventos_paseo,
        // calificaciones_paseo; pagos.id_pedido → NULL)
        $s = $conn->prepare("DELETE FROM pedidos_paseo WHERE id_pedido = ?");
        $s->bind_param("i", $id);
        $s->execute();
        if ($s->affected_rows > 0) $eliminados[] = $id;
        $s->close();
    }

    $conn->commit();
    responder(true, [
        'eliminados' => count($eliminados),
        'ids'        => $eliminados,
    ], count($eliminados) === 1
        ? 'Pedido eliminado.'
        : count($eliminados) . ' pedidos eliminados.');
} catch (Exception $e) {
    $conn->rollback();
    responder(false, [], 'Error al eliminar: ' . $e->getMessage());
}
?>
