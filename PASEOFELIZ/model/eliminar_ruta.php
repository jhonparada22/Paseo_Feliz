<?php
/**
 * eliminar_ruta.php
 * (ADMIN) Cancela (borrado lógico) o elimina físicamente una ruta.
 *
 * Cancelar (definitivo=false):
 *   - rutas.id_estado = 5 (cancelada)
 *   - propaga la cancelación a las paradas PENDIENTES (hora_cancelacion +
 *     motivo), respetando las ya entregadas/canceladas
 *   - notifica a cada cliente afectado. Antes solo se marcaba la ruta y el
 *     paseo "desaparecía" del panel del cliente sin explicación alguna.
 *
 * Eliminar (definitivo=true):
 *   - SOLO si la ruta no tiene actividad real (recogidas/entregas
 *     confirmadas): el DELETE borra en cascada las paradas, y con ellas
 *     el historial de paseos ya ejecutados y su conteo en el plan.
 *
 * POST JSON: { "id_ruta": 1, "definitivo": false, "motivo": "..." (opcional) }
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once '../model/conexion.php';

verificarAdmin();
$data = leerJsonBody();
$idRuta     = intval($data['id_ruta'] ?? 0);
$definitivo = !empty($data['definitivo']);
$motivo     = trim(substr($data['motivo'] ?? '', 0, 120));
if ($motivo === '') $motivo = 'La ruta del día fue cancelada por el administrador.';

if (!$idRuta) responder(false, [], 'id_ruta requerido.');

// ¿La ruta tiene actividad real (mascotas ya recogidas o entregadas)?
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS n FROM ruta_paradas
     WHERE id_ruta = ? AND (hora_recogida IS NOT NULL OR hora_entrega IS NOT NULL)"
);
$stmt->bind_param("i", $idRuta);
$stmt->execute();
$conActividad = (int)$stmt->get_result()->fetch_assoc()['n'] > 0;
$stmt->close();

if ($definitivo) {
    if ($conActividad) {
        responder(false, [], 'Esta ruta tiene paseos ya ejecutados: eliminarla borraría ese historial y el conteo del plan de los clientes. Usa "Cancelar" para las paradas pendientes.');
    }
    // Elimina la ruta y, en cascada, sus paradas/clientes/historial relacionado
    $stmt = $conn->prepare("DELETE FROM rutas WHERE id_ruta = ?");
    $stmt->bind_param("i", $idRuta);
    $ok = $stmt->execute();
    $stmt->close();
    responder($ok, [], $ok ? 'Ruta eliminada definitivamente.' : 'No se pudo eliminar.');
}

// ── Cancelación lógica ────────────────────────────────────────────────
$conn->begin_transaction();
try {
    // 1. Clientes con paradas aún pendientes (para notificarles)
    $stmt = $conn->prepare(
        "SELECT DISTINCT rp.id_usuario_cliente, mu.nombre_mascota
         FROM ruta_paradas rp
         LEFT JOIN mascota_usuario mu ON mu.id_mascota = rp.id_mascota
         WHERE rp.id_ruta = ? AND rp.id_usuario_cliente IS NOT NULL
           AND rp.hora_entrega IS NULL AND rp.hora_cancelacion IS NULL"
    );
    $stmt->bind_param("i", $idRuta);
    $stmt->execute();
    $res = $stmt->get_result();
    $afectados = [];
    while ($row = $res->fetch_assoc()) $afectados[] = $row;
    $stmt->close();

    // 2. Cancelar las paradas pendientes (las entregadas/canceladas no se tocan)
    $stmt = $conn->prepare(
        "UPDATE ruta_paradas
         SET hora_cancelacion = NOW(), motivo_cancelacion = ?, id_estado = 4
         WHERE id_ruta = ? AND hora_entrega IS NULL AND hora_cancelacion IS NULL"
    );
    $stmt->bind_param("si", $motivo, $idRuta);
    $stmt->execute();
    $paradasCanceladas = $stmt->affected_rows;
    $stmt->close();

    // 3. Cancelar la ruta
    $stmt = $conn->prepare("UPDATE rutas SET id_estado = 5 WHERE id_ruta = ?");
    $stmt->bind_param("i", $idRuta);
    $stmt->execute();
    $stmt->close();

    // 4. Notificar a cada cliente afectado
    foreach ($afectados as $a) {
        $mascota = $a['nombre_mascota'] ?: 'tu mascota';
        crearNotificacionInterna($conn, (int)$a['id_usuario_cliente'], $idRuta,
            'sistema', "El paseo de hoy de $mascota fue cancelado. Motivo: $motivo");
    }

    $conn->commit();
    responder(true, [
        'paradas_canceladas'    => $paradasCanceladas,
        'clientes_notificados'  => count($afectados),
    ], 'Ruta cancelada. Se notificó a ' . count($afectados) . ' cliente(s).');
} catch (Exception $e) {
    $conn->rollback();
    responder(false, [], 'Error al cancelar la ruta: ' . $e->getMessage());
}
?>
