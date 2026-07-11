<?php
/**
 * helpers_paseos_programados.php
 * Capa operativa del módulo de paseos: la entidad paseos_programados
 * ("el paseo de ESTA mascota, ESTE día") que conecta la compra con la
 * ejecución. Requiere la migración 2026_07_fase11_paseos_programados.sql.
 *
 * - materializarPaseosProgramados(): genera las instancias de los próximos
 *   días a partir del cronograma semanal, respetando la vigencia de la
 *   membresía y el tope de paseos del plan; y marca como no_ejecutado lo
 *   que quedó pendiente de días pasados. Sin cron: se invoca de forma
 *   perezosa desde los endpoints (con throttle en control_procesos).
 * - transicionPaseoProgramado(): cambia el estado de la instancia de un
 *   pedido para una fecha e inserta el evento en el log (eventos_paseo).
 *
 * Todas las funciones son tolerantes a que las tablas aún no existan
 * (error 1146): devuelven sin hacer nada, para que el deploy de código
 * y la migración SQL no tengan que ser simultáneos.
 */

include_once __DIR__ . '/helpers.php';

define('PP_HORIZONTE_DIAS', 7);      // días hacia adelante que se generan
define('PP_THROTTLE_MIN', 30);       // minutos entre corridas perezosas

/** true si el error es "tabla no existe" (migración aún no aplicada) */
function ppTablaFaltante($e) {
    return $e instanceof mysqli_sql_exception && (int)$e->getCode() === 1146;
}

/**
 * Inserta un evento en el log del paseo. Nunca lanza: el log no debe
 * tumbar la operación que lo origina.
 */
function ppEvento($conn, $idPaseo, $tipo, $actor = 'sistema', $detalle = null) {
    try {
        $stmt = $conn->prepare(
            "INSERT INTO eventos_paseo (id_paseo, tipo, actor, detalle) VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("isss", $idPaseo, $tipo, $actor, $detalle);
        $stmt->execute();
        $stmt->close();
    } catch (mysqli_sql_exception $e) { /* log best-effort */ }
}

/**
 * Cambia el estado de la instancia de un (pedido, fecha) y registra el
 * evento. Si la instancia no existe (caso borde: ruta manual de un día
 * fuera del cronograma), la crea al vuelo con origen='manual' para no
 * perder el registro operativo.
 *
 * $extra: ['id_ruta'=>int, 'id_paseador'=>int, 'motivo'=>str,
 *          'cancelado_por'=>str, 'actor'=>str, 'detalle'=>str]
 * Devuelve el id_paseo afectado o null.
 */
function transicionPaseoProgramado($conn, $idPedido, $fecha, $nuevoEstado, $extra = []) {
    $actor   = $extra['actor'] ?? 'sistema';
    $detalle = $extra['detalle'] ?? null;

    try {
        // 1. Buscar la instancia
        $stmt = $conn->prepare(
            "SELECT id_paseo, estado FROM paseos_programados WHERE id_pedido = ? AND fecha = ?"
        );
        $stmt->bind_param("is", $idPedido, $fecha);
        $stmt->execute();
        $pp = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // 2. Crearla al vuelo si no existe (origen manual)
        if (!$pp) {
            $exprHora = pedidosTienenHoraExacta($conn) ? 'p.hora_paseo' : 'NULL';
            $stmt = $conn->prepare(
                "INSERT IGNORE INTO paseos_programados
                    (id_pedido, id_mascota, id_usuario_cliente, fecha, franja_horaria,
                     hora_objetivo, duracion_min, modalidad, id_paseador, id_ruta, estado, origen)
                 SELECT p.id_pedido, p.id_mascota, p.id_usuario, ?, p.franja_horaria,
                        $exprHora, p.duracion_min, p.modalidad, ?, ?, 'programado', 'manual'
                 FROM pedidos_paseo p WHERE p.id_pedido = ?"
            );
            $idPaseadorN = isset($extra['id_paseador']) ? (int)$extra['id_paseador'] : null;
            $idRutaN     = isset($extra['id_ruta']) ? (int)$extra['id_ruta'] : null;
            $stmt->bind_param("siii", $fecha, $idPaseadorN, $idRutaN, $idPedido);
            $stmt->execute();
            $idPaseo = $conn->insert_id;
            $stmt->close();
            if (!$idPaseo) return null; // el pedido no existe
            ppEvento($conn, $idPaseo, 'programado', 'sistema', 'Instancia creada al vuelo (fuera de cronograma)');
        } else {
            $idPaseo = (int)$pp['id_paseo'];
            // Los estados finales no se pisan (un completado no vuelve atrás
            // salvo el "deshacer" explícito del paseador, que llega como
            // nuevoEstado='en_ruta' con actor='paseador')
            $finales = ['completado', 'cancelado', 'no_ejecutado', 'reprogramado'];
            $esDeshacer = ($nuevoEstado === 'en_ruta' && $actor === 'paseador');
            if (in_array($pp['estado'], $finales, true) && !$esDeshacer) {
                return $idPaseo;
            }
        }

        // 3. Aplicar la transición
        $sets   = "estado = ?";
        $tipos  = "s";
        $vals   = [$nuevoEstado];
        if (isset($extra['id_ruta']))     { $sets .= ", id_ruta = ?";     $tipos .= "i"; $vals[] = (int)$extra['id_ruta']; }
        if (isset($extra['id_paseador'])) { $sets .= ", id_paseador = ?"; $tipos .= "i"; $vals[] = (int)$extra['id_paseador']; }
        if ($nuevoEstado === 'cancelado') {
            $sets .= ", motivo_cancelacion = ?, cancelado_por = ?";
            $tipos .= "ss";
            $vals[] = substr($extra['motivo'] ?? '', 0, 160);
            $vals[] = in_array($extra['cancelado_por'] ?? '', ['cliente','paseador','admin','sistema'])
                        ? $extra['cancelado_por'] : 'sistema';
        }
        $tipos .= "i";
        $vals[] = $idPaseo;

        $stmt = $conn->prepare("UPDATE paseos_programados SET $sets WHERE id_paseo = ?");
        $stmt->bind_param($tipos, ...$vals);
        $stmt->execute();
        $stmt->close();

        // 4. Evento (el tipo del log usa 'entregado' para completado)
        $tipoEv = $nuevoEstado === 'completado' ? 'entregado' : $nuevoEstado;
        if (isset($extra['evento_tipo'])) $tipoEv = $extra['evento_tipo'];
        ppEvento($conn, $idPaseo, $tipoEv, $actor, $detalle ?? ($extra['motivo'] ?? null));

        return $idPaseo;
    } catch (mysqli_sql_exception $e) {
        if (ppTablaFaltante($e)) return null; // migración pendiente: no-op
        throw $e;
    }
}

/**
 * Sincroniza las instancias FUTURAS aún no ejecutadas con el cronograma
 * vigente (llamar tras editarlo): elimina las que quedaron sin respaldo
 * (el pedido ya no está asignado ese día de la semana) y corrige el
 * paseador de las que cambiaron de manos. Lo ya ejecutado no se toca.
 */
function sincronizarInstanciasConCronograma($conn) {
    try {
        // Instancias especulativas sin respaldo en el cronograma -> fuera
        $conn->query(
            "DELETE pp FROM paseos_programados pp
             LEFT JOIN cronograma_paseos c
                    ON c.id_pedido = pp.id_pedido AND c.dia_semana = WEEKDAY(pp.fecha) + 1
             WHERE pp.fecha >= CURDATE()
               AND pp.estado IN ('programado','asignado')
               AND pp.origen = 'cronograma'
               AND c.id_cronograma IS NULL"
        );
        // Cambios de paseador en el cronograma -> actualizar la instancia
        $conn->query(
            "UPDATE paseos_programados pp
             JOIN cronograma_paseos c
                   ON c.id_pedido = pp.id_pedido AND c.dia_semana = WEEKDAY(pp.fecha) + 1
             SET pp.id_paseador = c.id_paseador
             WHERE pp.fecha >= CURDATE()
               AND pp.estado IN ('programado','asignado')
               AND (pp.id_paseador IS NULL OR pp.id_paseador <> c.id_paseador)"
        );
    } catch (mysqli_sql_exception $e) {
        if (ppTablaFaltante($e)) return;
        throw $e;
    }
}

/**
 * Genera las instancias de los próximos PP_HORIZONTE_DIAS días desde el
 * cronograma semanal y liquida lo vencido. Idempotente (UNIQUE pedido+fecha).
 *
 * $force=true salta el throttle (usar tras cambiar el cronograma).
 */
function materializarPaseosProgramados($conn, $force = false) {
    try {
        // ── Throttle (sin cron): máx. 1 corrida cada PP_THROTTLE_MIN ──
        if (!$force) {
            $res = $conn->query(
                "SELECT ultima_ejecucion FROM control_procesos WHERE proceso = 'materializar_paseos'"
            );
            $row = $res ? $res->fetch_assoc() : null;
            if ($row && $row['ultima_ejecucion'] !== null
                && strtotime($row['ultima_ejecucion']) > time() - PP_THROTTLE_MIN * 60) {
                return;
            }
        }
        $conn->query(
            "INSERT INTO control_procesos (proceso, ultima_ejecucion) VALUES ('materializar_paseos', NOW())
             ON DUPLICATE KEY UPDATE ultima_ejecucion = NOW()"
        );

        // ── 1. Liquidar días pasados: lo no ejecutado queda registrado ──
        $res = $conn->query(
            "SELECT id_paseo FROM paseos_programados
             WHERE fecha < CURDATE() AND estado IN ('programado','asignado','en_ruta','recogido')"
        );
        $vencidos = [];
        while ($row = $res->fetch_assoc()) $vencidos[] = (int)$row['id_paseo'];
        if ($vencidos) {
            $ids = implode(',', $vencidos);
            $conn->query("UPDATE paseos_programados SET estado = 'no_ejecutado' WHERE id_paseo IN ($ids)");
            foreach ($vencidos as $idP) {
                ppEvento($conn, $idP, 'no_ejecutado', 'sistema', 'El día terminó sin que el paseo se ejecutara');
            }
        }

        // ── 2. Pedidos activos con cronograma y membresía vigente ──────
        $ahoraColombia = "CONVERT_TZ(NOW(), '+00:00', '-05:00')";
        $exprHora = pedidosTienenHoraExacta($conn) ? 'p.hora_paseo' : 'NULL AS hora_paseo';
        $res = $conn->query(
            "SELECT p.id_pedido, p.id_mascota, p.id_usuario, p.cantidad_paseos, p.duracion_min,
                    p.modalidad, p.franja_horaria, $exprHora, p.fecha_inicio,
                    m.fecha_fin_paseos,
                    DATE_SUB(m.fecha_fin_paseos, INTERVAL 30 DAY) AS inicio_periodo,
                    c.id_paseador, c.dia_semana
             FROM pedidos_paseo p
             JOIN membresias m ON m.id_usuario = p.id_usuario AND m.id_mascota = p.id_mascota
             JOIN cronograma_paseos c ON c.id_pedido = p.id_pedido
             WHERE p.estado IN ('pagado', 'listo_para_asignar')
               AND m.paseos = 1
               AND m.fecha_fin_paseos IS NOT NULL
               AND m.fecha_fin_paseos > $ahoraColombia
             ORDER BY p.id_pedido, c.dia_semana"
        );

        // Agrupar el cronograma por pedido
        $pedidos = [];
        while ($row = $res->fetch_assoc()) {
            $id = (int)$row['id_pedido'];
            if (!isset($pedidos[$id])) {
                $row['dias'] = [];
                $pedidos[$id] = $row;
            }
            $pedidos[$id]['dias'][(int)$row['dia_semana']] = (int)$row['id_paseador'];
        }

        if (!$pedidos) return;

        $stmtIns = $conn->prepare(
            "INSERT IGNORE INTO paseos_programados
                (id_pedido, id_mascota, id_usuario_cliente, fecha, franja_horaria, hora_objetivo,
                 duracion_min, modalidad, id_paseador, estado, origen)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'asignado', 'cronograma')"
        );
        $stmtCupo = $conn->prepare(
            "SELECT COUNT(*) AS n FROM paseos_programados
             WHERE id_pedido = ? AND fecha >= DATE(?)
               AND estado NOT IN ('cancelado','no_ejecutado','reprogramado')"
        );

        $hoy = new DateTime('today');
        foreach ($pedidos as $p) {
            $idPedido = (int)$p['id_pedido'];
            // Hora exacta contratada (fase 15); los pedidos viejos caen al
            // inicio de su franja de texto. Puede ser null.
            $horaObj  = $p['hora_paseo'] ? substr($p['hora_paseo'], 0, 8)
                                         : horaInicioDeFranja($p['franja_horaria']);

            // Cupo del periodo: completados + programados no cancelados.
            // No se generan más instancias que las que el plan pagó.
            $stmtCupo->bind_param("is", $idPedido, $p['inicio_periodo']);
            $stmtCupo->execute();
            $enPlan = (int)$stmtCupo->get_result()->fetch_assoc()['n'];
            $cupoRestante = max(0, (int)$p['cantidad_paseos'] - $enPlan);
            if ($cupoRestante <= 0) continue;

            $finMembresia = new DateTime(date('Y-m-d', strtotime($p['fecha_fin_paseos'])));
            $inicioPedido = new DateTime($p['fecha_inicio']);

            for ($i = 0; $i < PP_HORIZONTE_DIAS && $cupoRestante > 0; $i++) {
                $dia = (clone $hoy)->modify("+$i days");
                $dow = (int)$dia->format('N'); // 1=lunes ... 7=domingo
                if (!isset($p['dias'][$dow])) continue;          // no toca ese día
                if ($dia > $finMembresia) break;                 // fuera de vigencia
                if ($dia < $inicioPedido) continue;              // aún no arranca

                $fecha      = $dia->format('Y-m-d');
                $idPaseador = $p['dias'][$dow];
                $idMascota  = (int)$p['id_mascota'];
                $idCliente  = (int)$p['id_usuario'];
                $durMin     = (int)$p['duracion_min'];

                $stmtIns->bind_param(
                    "iiisssisi",
                    $idPedido, $idMascota, $idCliente, $fecha, $p['franja_horaria'],
                    $horaObj, $durMin, $p['modalidad'], $idPaseador
                );
                $stmtIns->execute();
                if ($stmtIns->affected_rows > 0) {
                    $cupoRestante--;
                    ppEvento($conn, $conn->insert_id, 'asignado', 'sistema',
                        'Generado desde el cronograma semanal');
                }
            }
        }
        $stmtIns->close();
        $stmtCupo->close();
    } catch (mysqli_sql_exception $e) {
        if (ppTablaFaltante($e)) return; // migración pendiente: no-op
        throw $e;
    }
}
?>
