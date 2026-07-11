<?php
/**
 * helpers_logistica.php
 * Reglas de capacidad operativa para la asignación de pedidos a paseadores.
 * Las usan guardar_cronograma.php (asignar/editar) y reasignar_paseo.php.
 *
 * Reglas (configurables en config_operacion.php):
 *  1. Un paseador no puede superar CUPO_MAX_PEDIDOS_DIA mascotas por día.
 *  2. Cada pedido ocupa el intervalo real [hora_paseo, hora_paseo + duración):
 *     - dos salidas de un mismo paseador NO pueden cruzarse en el tiempo;
 *     - pedidos grupales con la MISMA hora forman una sola salida (máximo
 *       CUPO_MAX_GRUPAL_FRANJA mascotas) y no se mezclan con individuales;
 *     - modalidad individual: el intervalo es exclusivo (una sola mascota).
 *  3. Pedidos sin hora exacta (previos a la fase 15) se validan con la
 *     regla antigua de franja en texto.
 */

include_once __DIR__ . '/config_operacion.php';
include_once __DIR__ . '/helpers.php';

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

    // Modalidad, hora exacta y duración de cada pedido del conjunto
    $colHora = pedidosTienenHoraExacta($conn) ? 'p.hora_paseo' : 'NULL AS hora_paseo';
    $placeholders = implode(',', array_fill(0, count($idsFinales), '?'));
    $stmt = $conn->prepare(
        "SELECT id_pedido, modalidad, COALESCE(franja_horaria, '') AS franja,
                COALESCE(duracion_min, 60) AS duracion_min, $colHora,
                (SELECT nombre_mascota FROM mascota_usuario mu WHERE mu.id_mascota = p.id_mascota) AS mascota
         FROM pedidos_paseo p WHERE id_pedido IN ($placeholders)"
    );
    $tipos = str_repeat('i', count($idsFinales));
    $stmt->bind_param($tipos, ...$idsFinales);
    $stmt->execute();
    $res = $stmt->get_result();

    $salidas = [];   // hora "HH:MM" => ['ini','fin','grupal'=>[],'individual'=>[]]
    $legacy  = [];   // franja texto => ['grupal'=>[],'individual'=>[]] (pedidos sin hora)
    while ($row = $res->fetch_assoc()) {
        $nombre = $row['mascota'] ?: ('pedido #' . $row['id_pedido']);
        $hora   = $row['hora_paseo'] ? substr($row['hora_paseo'], 0, 5) : horaInicioDeFranja($row['franja']);
        if ($hora) {
            list($h, $m) = explode(':', $hora);
            $ini = (int)$h * 60 + (int)$m;
            if (!isset($salidas[$hora])) {
                $salidas[$hora] = ['ini' => $ini, 'fin' => $ini, 'grupal' => [], 'individual' => []];
            }
            $salidas[$hora]['fin'] = max($salidas[$hora]['fin'], $ini + (int)$row['duracion_min']);
            $salidas[$hora][$row['modalidad']][] = $nombre;
        } else {
            $f = $row['franja'];
            if (!isset($legacy[$f])) $legacy[$f] = ['grupal' => [], 'individual' => []];
            $legacy[$f][$row['modalidad']][] = $nombre;
        }
    }
    $stmt->close();

    // ── Reglas dentro de cada salida (misma hora exacta) ─────────────
    foreach ($salidas as $hora => $s) {
        $rango = etiquetaHorario($hora, $s['fin'] - $s['ini']);
        if (count($s['individual']) > 0 && (count($s['grupal']) > 0 || count($s['individual']) > 1)) {
            return 'La modalidad individual es exclusiva: ' . implode(', ', $s['individual'])
                 . " no puede compartir el horario de las $rango con otras mascotas ese día.";
        }
        if (count($s['grupal']) > CUPO_MAX_GRUPAL_FRANJA) {
            return "Cupo grupal superado a las $rango: máximo "
                 . CUPO_MAX_GRUPAL_FRANJA . ' mascotas por salida (habría ' . count($s['grupal']) . ').';
        }
    }

    // ── Choques de horario entre salidas distintas ───────────────────
    ksort($salidas);
    $prev = null;
    foreach ($salidas as $hora => $s) {
        if ($prev && $s['ini'] < $prev['fin']) {
            $mPrev = implode(', ', array_merge($prev['grupal'], $prev['individual']));
            $mCur  = implode(', ', array_merge($s['grupal'], $s['individual']));
            return 'Choque de horario: el paseo de ' . $mCur
                 . ' (' . etiquetaHorario($hora, $s['fin'] - $s['ini']) . ')'
                 . ' se cruza con el de ' . $mPrev
                 . ' (' . etiquetaHorario(sprintf('%02d:%02d', intdiv($prev['ini'], 60), $prev['ini'] % 60), $prev['fin'] - $prev['ini']) . ').'
                 . ' Ajusta la hora o asigna otro paseador.';
        }
        if (!$prev || $s['fin'] > $prev['fin']) $prev = $s;
    }

    // ── Pedidos sin hora exacta: regla antigua por franja de texto ───
    foreach ($legacy as $franja => $g) {
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
