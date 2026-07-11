<?php
/**
 * torre_control.php
 * (ADMIN) Panorama operativo del día: cronograma vs realidad.
 * Por primera vez el admin puede ver lo que ANTES era invisible:
 *   - paseadores que tienen paseos hoy y aún no han iniciado su jornada
 *     (no existe ruta activa) — con alerta si ya pasó su hora objetivo;
 *   - el avance de cada paseador (pendientes / en ruta / recogidos /
 *     completados / cancelados de HOY);
 *   - los paseos que quedaron sin ejecutar en los últimos días
 *     (estado no_ejecutado, candidatos a reposición).
 *
 * GET sin parámetros. Requiere la migración fase 11.
 */
header("Access-Control-Allow-Origin: *");
include_once 'helpers.php';
include_once 'helpers_paseos_programados.php';
include_once '../model/conexion.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
date_default_timezone_set('America/Bogota');

verificarAdmin();

// Mantener las instancias frescas (throttled)
materializarPaseosProgramados($conn);

$hoy = date('Y-m-d');

try {
    // ── 1. Por paseador: instancias de HOY + estado de su ruta ────────
    $res = $conn->query(
        "SELECT pp.id_paseador,
                u.nombre AS paseador,
                COUNT(*) AS total,
                SUM(pp.estado IN ('programado','asignado')) AS pendientes,
                SUM(pp.estado = 'en_ruta')    AS en_ruta,
                SUM(pp.estado = 'recogido')   AS recogidos,
                SUM(pp.estado = 'completado') AS completados,
                SUM(pp.estado = 'cancelado')  AS cancelados,
                MIN(pp.hora_objetivo)         AS primera_hora,
                MIN(pp.franja_horaria)        AS primera_franja,
                r.id_ruta, er.nombre AS estado_ruta
         FROM paseos_programados pp
         JOIN paseadores pa ON pa.id_paseador = pp.id_paseador
         JOIN usuarios u    ON u.id = pa.id_usuario
         LEFT JOIN rutas r  ON r.id_paseador = pp.id_paseador
                            AND r.fecha_paseo = '$hoy' AND r.id_estado IN (1,2,3)
         LEFT JOIN estados_ruta er ON er.id_estado = r.id_estado
         WHERE pp.fecha = '$hoy' AND pp.id_paseador IS NOT NULL
         GROUP BY pp.id_paseador, u.nombre, r.id_ruta, er.nombre
         ORDER BY u.nombre ASC"
    );

    $ahora = date('H:i:s');
    $paseadores = [];
    while ($row = $res->fetch_assoc()) {
        $inicio = $row['estado_ruta'] !== null && $row['estado_ruta'] !== 'pendiente';
        // Alerta: tiene paseos pendientes, no ha arrancado, y ya pasó su
        // hora objetivo con 30 min de margen (si no hay hora, desde las 12).
        $horaLimite = $row['primera_hora']
            ? date('H:i:s', strtotime($row['primera_hora']) + 30 * 60)
            : '12:00:00';
        $alerta = !$inicio && (int)$row['pendientes'] > 0 && $ahora > $horaLimite;

        $paseadores[] = [
            'id_paseador'    => (int)$row['id_paseador'],
            'nombre'         => $row['paseador'],
            'total'          => (int)$row['total'],
            'pendientes'     => (int)$row['pendientes'],
            'en_ruta'        => (int)$row['en_ruta'],
            'recogidos'      => (int)$row['recogidos'],
            'completados'    => (int)$row['completados'],
            'cancelados'     => (int)$row['cancelados'],
            'primera_hora'   => $row['primera_hora'] ? substr($row['primera_hora'], 0, 5) : null,
            'primera_franja' => $row['primera_franja'],
            'inicio_jornada' => $inicio,
            'estado_ruta'    => $row['estado_ruta'],
            'alerta_no_inicio' => $alerta,
        ];
    }

    // ── 2. Paseos no ejecutados (últimos 7 días) ──────────────────────
    $res = $conn->query(
        "SELECT pp.id_paseo, pp.id_pedido, pp.fecha, pp.franja_horaria,
                mu.nombre_mascota, uc.nombre AS cliente, up.nombre AS paseador
         FROM paseos_programados pp
         LEFT JOIN mascota_usuario mu ON mu.id_mascota = pp.id_mascota
         LEFT JOIN usuarios uc ON uc.id = pp.id_usuario_cliente
         LEFT JOIN paseadores pa ON pa.id_paseador = pp.id_paseador
         LEFT JOIN usuarios up ON up.id = pa.id_usuario
         WHERE pp.estado = 'no_ejecutado'
           AND pp.fecha >= DATE_SUB('$hoy', INTERVAL 7 DAY)
         ORDER BY pp.fecha DESC
         LIMIT 20"
    );
    $noEjecutados = [];
    while ($row = $res->fetch_assoc()) {
        $noEjecutados[] = [
            'id_paseo'  => (int)$row['id_paseo'],
            'id_pedido' => (int)$row['id_pedido'],
            'fecha'     => $row['fecha'],
            'franja'    => $row['franja_horaria'],
            'mascota'   => $row['nombre_mascota'] ?: '—',
            'cliente'   => $row['cliente'] ?: '—',
            'paseador'  => $row['paseador'] ?: 'Sin asignar',
        ];
    }

    // ── 3. Incidencias reportadas HOY (problemas sin cancelación) ─────
    $res = $conn->query(
        "SELECT ev.detalle, ev.creado_en, pp.id_pedido,
                mu.nombre_mascota, uc.nombre AS cliente, up.nombre AS paseador
         FROM eventos_paseo ev
         JOIN paseos_programados pp ON pp.id_paseo = ev.id_paseo
         LEFT JOIN mascota_usuario mu ON mu.id_mascota = pp.id_mascota
         LEFT JOIN usuarios uc ON uc.id = pp.id_usuario_cliente
         LEFT JOIN paseadores pa ON pa.id_paseador = pp.id_paseador
         LEFT JOIN usuarios up ON up.id = pa.id_usuario
         WHERE ev.tipo = 'incidencia' AND DATE(ev.creado_en) = '$hoy'
         ORDER BY ev.creado_en DESC
         LIMIT 15"
    );
    $incidencias = [];
    while ($row = $res->fetch_assoc()) {
        $incidencias[] = [
            'id_pedido' => (int)$row['id_pedido'],
            'hora'      => substr($row['creado_en'], 11, 5),
            'detalle'   => $row['detalle'],
            'mascota'   => $row['nombre_mascota'] ?: '—',
            'cliente'   => $row['cliente'] ?: '—',
            'paseador'  => $row['paseador'] ?: '—',
        ];
    }

    responder(true, [
        'fecha'          => $hoy,
        'paseadores_hoy' => $paseadores,
        'no_ejecutados'  => $noEjecutados,
        'incidencias'    => $incidencias,
    ]);
} catch (mysqli_sql_exception $e) {
    if (ppTablaFaltante($e)) {
        responder(false, ['migracion_pendiente' => true],
            'Falta ejecutar la migración fase 11 (paseos_programados).');
    }
    throw $e;
}
?>
