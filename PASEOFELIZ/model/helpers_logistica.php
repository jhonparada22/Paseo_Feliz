<?php
/**
 * helpers_logistica.php
 * Reglas de capacidad operativa para la asignación de pedidos a paseadores.
 * Las usan guardar_cronograma.php (asignar/editar) y reasignar_paseo.php.
 *
 * Reglas (configurables en config_operacion.php):
 *  1. Un paseador no puede superar CUPO_MAX_PEDIDOS_DIA mascotas por día.
 *  2. En una misma franja horaria (paseador + día):
 *     - modalidad grupal: máximo CUPO_MAX_GRUPAL_FRANJA mascotas, y no se
 *       mezcla con ninguna individual;
 *     - modalidad individual: la franja es exclusiva (una sola mascota).
 */

include_once __DIR__ . '/config_operacion.php';

/**
 * Valida el CONJUNTO FINAL de pedidos de un paseador para un día de la
 * semana. Devuelve null si es válido, o el mensaje de error si no.
 *
 * $idsFinales: ids de pedido que quedarían asignados a ese paseador ese día
 *              (los actuales que se conservan + los nuevos).
 */
function validarConjuntoDia($conn, $idPaseador, $dia, array $idsFinales) {
    $idsFinales = array_values(array_unique(array_map('intval', $idsFinales)));
    if (!$idsFinales) return null; // día vacío: siempre válido

    if (count($idsFinales) > CUPO_MAX_PEDIDOS_DIA) {
        return 'El paseador superaría el cupo de ' . CUPO_MAX_PEDIDOS_DIA
             . ' mascotas por día (tendría ' . count($idsFinales) . ').';
    }

    // Modalidad y franja de cada pedido del conjunto
    $placeholders = implode(',', array_fill(0, count($idsFinales), '?'));
    $stmt = $conn->prepare(
        "SELECT id_pedido, modalidad, COALESCE(franja_horaria, '') AS franja,
                (SELECT nombre_mascota FROM mascota_usuario mu WHERE mu.id_mascota = p.id_mascota) AS mascota
         FROM pedidos_paseo p WHERE id_pedido IN ($placeholders)"
    );
    $tipos = str_repeat('i', count($idsFinales));
    $stmt->bind_param($tipos, ...$idsFinales);
    $stmt->execute();
    $res = $stmt->get_result();

    $porFranja = []; // franja => ['grupal' => [mascotas], 'individual' => [mascotas]]
    while ($row = $res->fetch_assoc()) {
        $f = $row['franja'];
        if (!isset($porFranja[$f])) $porFranja[$f] = ['grupal' => [], 'individual' => []];
        $porFranja[$f][$row['modalidad']][] = $row['mascota'] ?: ('pedido #' . $row['id_pedido']);
    }
    $stmt->close();

    foreach ($porFranja as $franja => $g) {
        $etiqueta = $franja !== '' ? "la franja \"$franja\"" : 'la franja sin horario definido';
        if (count($g['individual']) > 0 && (count($g['grupal']) > 0 || count($g['individual']) > 1)) {
            return 'La modalidad individual es exclusiva: ' . implode(', ', $g['individual'])
                 . ' no puede compartir ' . $etiqueta . ' con otras mascotas ese día.';
        }
        if (count($g['grupal']) > CUPO_MAX_GRUPAL_FRANJA) {
            return 'Cupo grupal superado en ' . $etiqueta . ': máximo '
                 . CUPO_MAX_GRUPAL_FRANJA . ' mascotas por salida (habría ' . count($g['grupal']) . ').';
        }
    }
    return null;
}

/**
 * Ids de pedidos actualmente asignados a un paseador en un día
 * (solo pedidos activos: los cancelados no ocupan cupo).
 */
function pedidosDelDia($conn, $idPaseador, $dia) {
    $stmt = $conn->prepare(
        "SELECT c.id_pedido
         FROM cronograma_paseos c
         JOIN pedidos_paseo p ON p.id_pedido = c.id_pedido
         WHERE c.id_paseador = ? AND c.dia_semana = ?
           AND p.estado NOT IN ('cancelado','pendiente_pago','pago_fallido')"
    );
    $stmt->bind_param("ii", $idPaseador, $dia);
    $stmt->execute();
    $res = $stmt->get_result();
    $ids = [];
    while ($row = $res->fetch_assoc()) $ids[] = (int)$row['id_pedido'];
    $stmt->close();
    return $ids;
}
?>
