<?php
/**
 * actividad_feed.php
 * (ADMIN) Centro de Actividad del dashboard — a diferencia del prototipo
 * (que usa una tabla `actividad_sistema` alimentada por un log de eventos),
 * este feed se CALCULA en cada petición leyendo directo las tablas reales
 * que ya usa el resto del sistema (pagos, pedidos_*, ruta_paradas,
 * calificaciones_paseo, cronograma_*). No requiere tablas nuevas ni que
 * ningún otro archivo "avise" a un log — siempre refleja el estado actual.
 *
 * GET:
 *   servicio=paseos|adiestramiento|hospedaje   (obligatorio, una pestaña)
 *   filtro=todos|hoy|24h|7d|pendientes|urgentes|cancelados|completados
 *   buscar=texto
 *   antes_fecha=Y-m-d H:i:s   (paginación: solo eventos más antiguos)
 *   limit=N
 * Respuesta: { success, items, hay_mas, contadores }
 */
header("Access-Control-Allow-Origin: *");
include_once 'helpers.php';
include_once '../model/conexion.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
date_default_timezone_set('America/Bogota');

verificarAdmin();

$modo = $_GET['modo'] ?? 'feed';

// ── Modo "atención": pedidos pagados sin asignar, cruzando los 3 servicios.
// No hay workflow de aprobación de cancelación en principal (el paseador
// cancela directo), así que lo único que realmente "necesita acción" del
// admin es un pedido listo para asignar que sigue sin paseador/entrenador,
// o una reserva de hospedaje confirmada que la van todavía no recogió.
if ($modo === 'atencion') {
    $items = [];

    $r = $conn->query(
        "SELECT p.id_pedido, p.fecha_creacion, u.nombre AS cliente, mu.nombre_mascota AS mascota
         FROM pedidos_paseo p
         LEFT JOIN cronograma_paseos c ON c.id_pedido = p.id_pedido
         LEFT JOIN usuarios u ON u.id = p.id_usuario
         LEFT JOIN mascota_usuario mu ON mu.id_mascota = p.id_mascota
         WHERE p.estado = 'listo_para_asignar' AND c.id_cronograma IS NULL
         GROUP BY p.id_pedido ORDER BY p.fecha_creacion ASC LIMIT 6"
    );
    while ($row = $r->fetch_assoc()) {
        $items[] = itemBase([
            'servicio' => 'paseos', 'tipo' => 'atencion', 'estado' => 'pendiente', 'prioridad' => 'alta',
            'titulo' => 'Sin paseador asignado: ' . ($row['mascota'] ?: '—'),
            'cliente' => $row['cliente'], 'mascota' => $row['mascota'],
            'id_pedido' => (int)$row['id_pedido'], 'creado_en' => $row['fecha_creacion'],
            'icono' => 'fa-person-walking', 'color' => '#f97316',
            'acciones' => ['ir_asignar_paseos'],
        ]);
    }

    $r = $conn->query(
        "SELECT p.id_pedido, p.fecha_creacion, u.nombre AS cliente, mu.nombre_mascota AS mascota
         FROM pedidos_adiestramiento p
         LEFT JOIN cronograma_adiestramiento c ON c.id_pedido = p.id_pedido
         LEFT JOIN usuarios u ON u.id = p.id_usuario
         LEFT JOIN mascota_usuario mu ON mu.id_mascota = p.id_mascota
         WHERE p.estado = 'listo_para_asignar' AND c.id_cronograma IS NULL
         GROUP BY p.id_pedido ORDER BY p.fecha_creacion ASC LIMIT 6"
    );
    while ($row = $r->fetch_assoc()) {
        $items[] = itemBase([
            'servicio' => 'adiestramiento', 'tipo' => 'atencion', 'estado' => 'pendiente', 'prioridad' => 'alta',
            'titulo' => 'Sin entrenador asignado: ' . ($row['mascota'] ?: '—'),
            'cliente' => $row['cliente'], 'mascota' => $row['mascota'],
            'id_pedido' => (int)$row['id_pedido'], 'creado_en' => $row['fecha_creacion'],
            'icono' => 'fa-graduation-cap', 'color' => '#f97316',
            'acciones' => ['ir_asignar_adiestramiento'],
        ]);
    }

    $r = $conn->query(
        "SELECT p.id_pedido, p.fecha_creacion, u.nombre AS cliente, mu.nombre_mascota AS mascota
         FROM pedidos_hospedaje p
         LEFT JOIN usuarios u ON u.id = p.id_usuario
         LEFT JOIN mascota_usuario mu ON mu.id_mascota = p.id_mascota
         WHERE p.estado = 'listo_para_asignar' AND p.fase_logistica = 'confirmado'
         ORDER BY p.fecha_creacion ASC LIMIT 6"
    );
    while ($row = $r->fetch_assoc()) {
        $items[] = itemBase([
            'servicio' => 'hospedaje', 'tipo' => 'atencion', 'estado' => 'pendiente', 'prioridad' => 'alta',
            'titulo' => 'La van aún no recoge: ' . ($row['mascota'] ?: '—'),
            'cliente' => $row['cliente'], 'mascota' => $row['mascota'],
            'id_pedido' => (int)$row['id_pedido'], 'creado_en' => $row['fecha_creacion'],
            'icono' => 'fa-van-shuttle', 'color' => '#f97316',
            'acciones' => ['ir_asignar_hospedaje'],
        ]);
    }

    $r = $conn->query(
        "SELECT sc.id_solicitud, sc.id_pedido, sc.motivo, sc.creado_en, mu.nombre_mascota AS mascota
         FROM solicitudes_cancelacion sc
         LEFT JOIN mascota_usuario mu ON mu.id_mascota = sc.id_mascota
         WHERE sc.estado = 'pendiente'
         ORDER BY sc.creado_en ASC LIMIT 6"
    );
    while ($row = $r->fetch_assoc()) {
        $items[] = itemBase([
            'servicio' => 'paseos', 'tipo' => 'cancelacion_solicitada', 'estado' => 'pendiente', 'prioridad' => 'alta',
            'titulo' => 'Solicitud de cancelación: ' . ($row['mascota'] ?: '—'),
            'descripcion' => $row['motivo'],
            'mascota' => $row['mascota'],
            'id_pedido' => (int)$row['id_pedido'], 'id_referencia' => (int)$row['id_solicitud'],
            'creado_en' => $row['creado_en'],
            'icono' => 'fa-triangle-exclamation', 'color' => '#f97316',
            'acciones' => ['aprobar', 'rechazar'],
        ]);
    }

    $r = $conn->query(
        "SELECT sa.id_solicitud, sa.fecha_cita, sa.hora_cita, sa.creado_en,
                a.nombre AS mascota, u.nombre AS cliente
         FROM solicitudes_adopcion sa
         JOIN adopcion a  ON a.id_adopcion = sa.id_adopcion
         JOIN usuarios u  ON u.id = sa.id_usuario
         WHERE sa.estado = 'pendiente'
         ORDER BY sa.creado_en ASC LIMIT 6"
    );
    while ($row = $r->fetch_assoc()) {
        $fechaFmt = date('d/m', strtotime($row['fecha_cita']));
        $horaFmt  = date('g:i a', strtotime($row['hora_cita']));
        $items[] = itemBase([
            'servicio' => 'adopcion', 'tipo' => 'adopcion_solicitada', 'estado' => 'pendiente', 'prioridad' => 'alta',
            'titulo' => 'Solicitud de adopción: ' . $row['mascota'],
            'descripcion' => $row['cliente'] . ' · cita ' . $fechaFmt . ' ' . $horaFmt,
            'cliente' => $row['cliente'], 'mascota' => $row['mascota'],
            'id_referencia' => (int)$row['id_solicitud'],
            'creado_en' => $row['creado_en'],
            'icono' => 'fa-paw', 'color' => '#f97316',
            'acciones' => ['aprobar', 'rechazar'],
        ]);
    }

    usort($items, function ($a, $b) { return strcmp((string)$a['creado_en'], (string)$b['creado_en']); });
    $items = array_slice($items, 0, 6);
    foreach ($items as &$it) {
        $it['id'] = $it['id_referencia']
            ? md5($it['servicio'] . '|' . $it['tipo'] . '|' . $it['id_referencia'])
            : md5($it['servicio'] . '|atencion|' . $it['id_pedido']);
    }
    unset($it);

    responder(true, ['items' => $items]);
    exit;
}

$servicio   = $_GET['servicio'] ?? 'paseos';
$filtro     = $_GET['filtro'] ?? 'todos';
$buscar     = trim($_GET['buscar'] ?? '');
$antesFecha = trim($_GET['antes_fecha'] ?? '');
$limit      = max(1, min(100, intval($_GET['limit'] ?? 25)));

if (!in_array($servicio, ['paseos', 'adiestramiento', 'hospedaje', 'adopcion'], true)) {
    responder(false, [], 'Servicio no válido.');
}

// ── Helpers de normalización ──────────────────────────────────────────
function itemBase($over) {
    return array_merge([
        'servicio' => null, 'tipo' => null, 'estado' => 'nuevo', 'prioridad' => 'media',
        'titulo' => '', 'descripcion' => null,
        'cliente' => null, 'mascota' => null, 'paseador' => null,
        'id_pedido' => null, 'id_referencia' => null, 'creado_en' => null,
        'icono' => 'fa-circle-info', 'color' => '#3E72A6', 'acciones' => [],
    ], $over);
}

// Filtro de texto: aplica el LIKE a nombres ya conocidos en PHP (evita
// repetir el WHERE en cada una de las ~10 consultas de origen distintas).
function coincide($item, $buscar) {
    if ($buscar === '') return true;
    $q = mb_strtolower($buscar);
    foreach (['cliente', 'mascota', 'paseador', 'titulo'] as $campo) {
        if ($item[$campo] && mb_strpos(mb_strtolower($item[$campo]), $q) !== false) return true;
    }
    if ($item['id_pedido'] && mb_strpos((string)$item['id_pedido'], $q) !== false) return true;
    return false;
}

// ── 1. Compras nuevas ──────────────────────────────────────────────────
function itemsCompras($conn, $servicio) {
    $items = [];
    if ($servicio === 'paseos') {
        $r = $conn->query(
            "SELECT p.id_pedido, p.id_usuario, p.cantidad_paseos, p.modalidad, p.direccion,
                    p.estado, p.fecha_creacion, u.nombre AS cliente, mu.nombre_mascota AS mascota
             FROM pedidos_paseo p
             LEFT JOIN usuarios u ON u.id = p.id_usuario
             LEFT JOIN mascota_usuario mu ON mu.id_mascota = p.id_mascota
             ORDER BY p.fecha_creacion DESC LIMIT 200"
        );
        while ($row = $r->fetch_assoc()) {
            $items[] = itemBase([
                'servicio' => 'paseos', 'tipo' => 'compra', 'estado' => 'nuevo', 'prioridad' => 'media',
                'titulo' => 'Nuevo servicio de paseos — ' . ($row['mascota'] ?: 'mascota'),
                'descripcion' => $row['cantidad_paseos'] . ' paseos/mes · ' . ($row['modalidad'] ?: ''),
                'cliente' => $row['cliente'], 'mascota' => $row['mascota'],
                'id_pedido' => (int)$row['id_pedido'], 'creado_en' => $row['fecha_creacion'],
                'icono' => 'fa-paw', 'color' => '#3E72A6',
                'acciones' => ['ver_paseo'],
            ]);
        }
    } elseif ($servicio === 'adiestramiento') {
        $r = $conn->query(
            "SELECT p.id_pedido, p.cantidad_sesiones, p.direccion, p.estado, p.fecha_creacion,
                    u.nombre AS cliente, mu.nombre_mascota AS mascota
             FROM pedidos_adiestramiento p
             LEFT JOIN usuarios u ON u.id = p.id_usuario
             LEFT JOIN mascota_usuario mu ON mu.id_mascota = p.id_mascota
             ORDER BY p.fecha_creacion DESC LIMIT 200"
        );
        while ($row = $r->fetch_assoc()) {
            $items[] = itemBase([
                'servicio' => 'adiestramiento', 'tipo' => 'compra', 'estado' => 'nuevo', 'prioridad' => 'media',
                'titulo' => 'Nuevo servicio de adiestramiento — ' . ($row['mascota'] ?: 'mascota'),
                'descripcion' => $row['cantidad_sesiones'] . ' sesiones/mes',
                'cliente' => $row['cliente'], 'mascota' => $row['mascota'],
                'id_pedido' => (int)$row['id_pedido'], 'creado_en' => $row['fecha_creacion'],
                'icono' => 'fa-graduation-cap', 'color' => '#7c3aed',
            ]);
        }
    } else { // hospedaje
        $r = $conn->query(
            "SELECT p.id_pedido, p.cantidad_noches, p.fecha_entrada, p.fecha_salida,
                    p.fase_logistica, p.estado, p.fecha_creacion,
                    u.nombre AS cliente, mu.nombre_mascota AS mascota
             FROM pedidos_hospedaje p
             LEFT JOIN usuarios u ON u.id = p.id_usuario
             LEFT JOIN mascota_usuario mu ON mu.id_mascota = p.id_mascota
             ORDER BY p.fecha_creacion DESC LIMIT 200"
        );
        while ($row = $r->fetch_assoc()) {
            $items[] = itemBase([
                'servicio' => 'hospedaje', 'tipo' => 'compra', 'estado' => 'nuevo', 'prioridad' => 'media',
                'titulo' => 'Nueva reserva de hospedaje — ' . ($row['mascota'] ?: 'mascota'),
                'descripcion' => $row['cantidad_noches'] . ' noche(s)',
                'cliente' => $row['cliente'], 'mascota' => $row['mascota'],
                'id_pedido' => (int)$row['id_pedido'], 'creado_en' => $row['fecha_creacion'],
                'icono' => 'fa-house', 'color' => '#0891b2',
            ]);
        }
    }
    return $items;
}

// ── 2. Pagos ────────────────────────────────────────────────────────────
function itemsPagos($conn, $servicio) {
    $items = [];
    $r = $conn->prepare(
        "SELECT pg.id_pago, pg.monto, pg.metodo, pg.metodo_pago, pg.referencia, pg.estado_pago,
                pg.fecha_pago, COALESCE(pg.id_pedido, pg.id_pedido_adiestramiento, pg.id_pedido_hospedaje) AS id_pedido,
                u.nombre AS cliente, mu.nombre_mascota AS mascota
         FROM pagos pg
         LEFT JOIN usuarios u ON u.id = pg.id_usuario
         LEFT JOIN mascota_usuario mu ON mu.id_mascota = pg.id_mascota
         WHERE pg.tipo_membresia = ?
         ORDER BY pg.fecha_pago DESC LIMIT 200"
    );
    $r->bind_param("s", $servicio);
    $r->execute();
    $res = $r->get_result();
    while ($row = $res->fetch_assoc()) {
        $rechazado = $row['estado_pago'] === 'rechazado';
        $items[] = itemBase([
            'servicio' => $servicio, 'tipo' => $rechazado ? 'pago_rechazado' : 'pago_aprobado',
            'estado' => $rechazado ? 'cancelado' : 'completado', 'prioridad' => $rechazado ? 'alta' : 'baja',
            'titulo' => ($rechazado ? 'Pago rechazado — $' : 'Pago recibido — $') . number_format($row['monto'], 0, ',', '.'),
            'descripcion' => 'Método: ' . ($row['metodo'] ?: $row['metodo_pago']) . ($row['referencia'] ? ' · Ref. ' . $row['referencia'] : ''),
            'cliente' => $row['cliente'], 'mascota' => $row['mascota'],
            'id_pedido' => $row['id_pedido'] ? (int)$row['id_pedido'] : null, 'creado_en' => $row['fecha_pago'],
            'icono' => $rechazado ? 'fa-circle-xmark' : 'fa-circle-check', 'color' => $rechazado ? '#ef4444' : '#22c55e',
            'acciones' => ['ver_comprobante'],
        ]);
    }
    $r->close();
    return $items;
}

// ── 3. Ejecución de paseos (solo paseos) ────────────────────────────────
function itemsEjecucionPaseos($conn) {
    $items = [];
    $r = $conn->query(
        "SELECT rp.id_parada, rp.tipo, rp.hora_llegada, rp.hora_completado, rp.hora_recogida,
                rp.hora_entrega, rp.hora_cancelacion, rp.motivo_cancelacion, rp.id_pedido,
                uc.nombre AS cliente, mu.nombre_mascota AS mascota, up.nombre AS paseador
         FROM ruta_paradas rp
         JOIN rutas r ON r.id_ruta = rp.id_ruta
         LEFT JOIN usuarios uc ON uc.id = rp.id_usuario_cliente
         LEFT JOIN mascota_usuario mu ON mu.id_mascota = rp.id_mascota
         LEFT JOIN paseadores pa ON pa.id_paseador = r.id_paseador
         LEFT JOIN usuarios up ON up.id = pa.id_usuario
         WHERE rp.tipo IN ('recogida', 'entrega') AND rp.id_pedido IS NOT NULL
           AND (rp.hora_recogida IS NOT NULL OR rp.hora_entrega IS NOT NULL OR rp.hora_cancelacion IS NOT NULL)
         ORDER BY COALESCE(rp.hora_cancelacion, rp.hora_entrega, rp.hora_recogida) DESC LIMIT 300"
    );
    while ($row = $r->fetch_assoc()) {
        if ($row['hora_cancelacion']) {
            $items[] = itemBase([
                'servicio' => 'paseos', 'tipo' => 'cancelado', 'estado' => 'cancelado', 'prioridad' => 'alta',
                'titulo' => 'Paseo cancelado: ' . ($row['mascota'] ?: '—'),
                'descripcion' => $row['motivo_cancelacion'],
                'cliente' => $row['cliente'], 'mascota' => $row['mascota'], 'paseador' => $row['paseador'],
                'id_pedido' => (int)$row['id_pedido'], 'creado_en' => $row['hora_cancelacion'],
                'icono' => 'fa-ban', 'color' => '#ef4444',
            ]);
        } elseif ($row['tipo'] === 'recogida' && $row['hora_recogida']) {
            $items[] = itemBase([
                'servicio' => 'paseos', 'tipo' => 'recogido', 'estado' => 'en_proceso', 'prioridad' => 'media',
                'titulo' => 'Mascota recogida: ' . ($row['mascota'] ?: '—'),
                'cliente' => $row['cliente'], 'mascota' => $row['mascota'], 'paseador' => $row['paseador'],
                'id_pedido' => (int)$row['id_pedido'], 'creado_en' => $row['hora_recogida'],
                'icono' => 'fa-hand-holding-heart', 'color' => '#3E72A6',
                'acciones' => ['ver_mapa'],
            ]);
        } elseif ($row['tipo'] === 'entrega' && $row['hora_entrega']) {
            $items[] = itemBase([
                'servicio' => 'paseos', 'tipo' => 'entregado', 'estado' => 'completado', 'prioridad' => 'baja',
                'titulo' => 'Paseo finalizado: ' . ($row['mascota'] ?: '—'),
                'cliente' => $row['cliente'], 'mascota' => $row['mascota'], 'paseador' => $row['paseador'],
                'id_pedido' => (int)$row['id_pedido'], 'creado_en' => $row['hora_entrega'],
                'icono' => 'fa-flag-checkered', 'color' => '#22c55e',
            ]);
        }
    }
    return $items;
}

// ── 4. Calificaciones (solo paseos) ─────────────────────────────────────
function itemsCalificaciones($conn) {
    $items = [];
    $r = $conn->query(
        "SELECT c.id_calificacion, c.estrellas, c.comentario, c.id_pedido, c.fecha_creacion,
                uc.nombre AS cliente, up.nombre AS paseador
         FROM calificaciones_paseo c
         LEFT JOIN usuarios uc ON uc.id = c.id_usuario_cliente
         LEFT JOIN paseadores pa ON pa.id_paseador = c.id_paseador
         LEFT JOIN usuarios up ON up.id = pa.id_usuario
         ORDER BY c.fecha_creacion DESC LIMIT 100"
    );
    while ($row = $r->fetch_assoc()) {
        $estrellas = (int)$row['estrellas'];
        $items[] = itemBase([
            'servicio' => 'paseos', 'tipo' => 'calificacion', 'estado' => 'completado', 'prioridad' => 'baja',
            'titulo' => 'Cliente calificó el paseo: ' . str_repeat('★', $estrellas) . str_repeat('☆', 5 - $estrellas),
            'descripcion' => $row['comentario'],
            'cliente' => $row['cliente'], 'paseador' => $row['paseador'],
            'id_pedido' => (int)$row['id_pedido'], 'creado_en' => $row['fecha_creacion'],
            'icono' => 'fa-star', 'color' => '#f59e0b',
        ]);
    }
    return $items;
}

// ── 5. Asignaciones (cronograma_paseos / cronograma_adiestramiento) ────
function itemsAsignaciones($conn, $servicio) {
    $items = [];
    $tabla = $servicio === 'paseos' ? 'cronograma_paseos' : 'cronograma_adiestramiento';
    $tablaPedido = $servicio === 'paseos' ? 'pedidos_paseo' : 'pedidos_adiestramiento';
    $r = $conn->query(
        "SELECT MIN(c.fecha_creacion) AS fecha, c.id_pedido, c.id_paseador,
                up.nombre AS paseador, uc.nombre AS cliente, mu.nombre_mascota AS mascota
         FROM `$tabla` c
         JOIN `$tablaPedido` p ON p.id_pedido = c.id_pedido
         LEFT JOIN paseadores pa ON pa.id_paseador = c.id_paseador
         LEFT JOIN usuarios up ON up.id = pa.id_usuario
         LEFT JOIN usuarios uc ON uc.id = p.id_usuario
         LEFT JOIN mascota_usuario mu ON mu.id_mascota = p.id_mascota
         GROUP BY c.id_pedido, c.id_paseador
         ORDER BY fecha DESC LIMIT 150"
    );
    $etiqueta = $servicio === 'paseos' ? 'Paseador asignado' : 'Entrenador asignado';
    while ($row = $r->fetch_assoc()) {
        $items[] = itemBase([
            'servicio' => $servicio, 'tipo' => 'asignacion', 'estado' => 'en_proceso', 'prioridad' => 'media',
            'titulo' => $etiqueta . ': ' . ($row['paseador'] ?: '—') . ' → ' . ($row['mascota'] ?: '—'),
            'cliente' => $row['cliente'], 'mascota' => $row['mascota'], 'paseador' => $row['paseador'],
            'id_pedido' => (int)$row['id_pedido'], 'creado_en' => $row['fecha'],
            'icono' => 'fa-user-check', 'color' => '#3E72A6',
        ]);
    }
    return $items;
}

// ── 5b. Solicitudes de cancelación pendientes (solo paseos) ────────────
function itemsSolicitudesCancelacion($conn) {
    $items = [];
    $r = $conn->query(
        "SELECT sc.id_solicitud, sc.id_pedido, sc.motivo, sc.creado_en,
                mu.nombre_mascota AS mascota, uc.nombre AS cliente, up.nombre AS paseador
         FROM solicitudes_cancelacion sc
         LEFT JOIN mascota_usuario mu ON mu.id_mascota = sc.id_mascota
         LEFT JOIN usuarios uc ON uc.id = sc.id_cliente
         LEFT JOIN paseadores pa ON pa.id_paseador = sc.id_paseador
         LEFT JOIN usuarios up ON up.id = pa.id_usuario
         WHERE sc.estado = 'pendiente'
         ORDER BY sc.creado_en DESC LIMIT 100"
    );
    while ($row = $r->fetch_assoc()) {
        $items[] = itemBase([
            'servicio' => 'paseos', 'tipo' => 'cancelacion_solicitada', 'estado' => 'pendiente', 'prioridad' => 'alta',
            'titulo' => 'Solicitud de cancelación: ' . ($row['mascota'] ?: '—'),
            'descripcion' => $row['motivo'],
            'cliente' => $row['cliente'], 'mascota' => $row['mascota'], 'paseador' => $row['paseador'],
            'id_pedido' => (int)$row['id_pedido'], 'id_referencia' => (int)$row['id_solicitud'],
            'creado_en' => $row['creado_en'],
            'icono' => 'fa-triangle-exclamation', 'color' => '#f97316',
            'acciones' => ['ver_motivo', 'aprobar', 'rechazar'],
        ]);
    }
    return $items;
}

// ── 6. Fases de logística de hospedaje ──────────────────────────────────
function itemsFasesHospedaje($conn) {
    $items = [];
    $r = $conn->query(
        "SELECT p.id_pedido, p.fase_logistica, p.hora_recogida_real, p.hora_entrega_real,
                u.nombre AS cliente, mu.nombre_mascota AS mascota
         FROM pedidos_hospedaje p
         LEFT JOIN usuarios u ON u.id = p.id_usuario
         LEFT JOIN mascota_usuario mu ON mu.id_mascota = p.id_mascota
         WHERE p.fase_logistica <> 'confirmado'
         ORDER BY COALESCE(p.hora_entrega_real, p.hora_recogida_real) DESC LIMIT 150"
    );
    while ($row = $r->fetch_assoc()) {
        if ($row['hora_entrega_real']) {
            $items[] = itemBase([
                'servicio' => 'hospedaje', 'tipo' => 'entregado', 'estado' => 'completado', 'prioridad' => 'baja',
                'titulo' => 'Mascota entregada: ' . ($row['mascota'] ?: '—'),
                'cliente' => $row['cliente'], 'mascota' => $row['mascota'],
                'id_pedido' => (int)$row['id_pedido'], 'creado_en' => $row['hora_entrega_real'],
                'icono' => 'fa-flag-checkered', 'color' => '#22c55e',
            ]);
        }
        if ($row['hora_recogida_real']) {
            $items[] = itemBase([
                'servicio' => 'hospedaje', 'tipo' => 'recogido', 'estado' => 'en_proceso', 'prioridad' => 'media',
                'titulo' => 'Mascota recogida, ya está en hospedaje: ' . ($row['mascota'] ?: '—'),
                'cliente' => $row['cliente'], 'mascota' => $row['mascota'],
                'id_pedido' => (int)$row['id_pedido'], 'creado_en' => $row['hora_recogida_real'],
                'icono' => 'fa-house', 'color' => '#0891b2',
            ]);
        }
        if (in_array($row['fase_logistica'], ['recogida_en_camino', 'entrega_en_camino'], true)) {
            $enCaminoDesc = $row['fase_logistica'] === 'recogida_en_camino' ? 'La van salió a recoger' : 'La van salió a entregar';
            $items[] = itemBase([
                'servicio' => 'hospedaje', 'tipo' => 'en_camino', 'estado' => 'en_proceso', 'prioridad' => 'media',
                'titulo' => $enCaminoDesc . ': ' . ($row['mascota'] ?: '—'),
                'cliente' => $row['cliente'], 'mascota' => $row['mascota'],
                'id_pedido' => (int)$row['id_pedido'], 'creado_en' => date('Y-m-d H:i:s'),
                'icono' => 'fa-van-shuttle', 'color' => '#f97316',
            ]);
        }
    }
    return $items;
}

// ── 7. Adopción: historial completo (pendientes + resueltas) ───────────
function itemsAdopcionHistorial($conn) {
    $items = [];
    $r = $conn->query(
        "SELECT sa.id_solicitud, sa.estado, sa.fecha_cita, sa.hora_cita, sa.motivo_rechazo,
                sa.creado_en, sa.resuelto_en, a.nombre AS mascota, u.nombre AS cliente
         FROM solicitudes_adopcion sa
         JOIN adopcion a ON a.id_adopcion = sa.id_adopcion
         JOIN usuarios u ON u.id = sa.id_usuario
         ORDER BY sa.creado_en DESC LIMIT 200"
    );
    while ($row = $r->fetch_assoc()) {
        $fechaFmt = date('d/m', strtotime($row['fecha_cita']));
        $horaFmt  = date('g:i a', strtotime($row['hora_cita']));

        if ($row['estado'] === 'pendiente') {
            $items[] = itemBase([
                'servicio' => 'adopcion', 'tipo' => 'adopcion_solicitada', 'estado' => 'pendiente', 'prioridad' => 'alta',
                'titulo' => 'Solicitud de adopción: ' . $row['mascota'],
                'descripcion' => $row['cliente'] . ' · cita ' . $fechaFmt . ' ' . $horaFmt,
                'cliente' => $row['cliente'], 'mascota' => $row['mascota'],
                'id_referencia' => (int)$row['id_solicitud'], 'creado_en' => $row['creado_en'],
                'icono' => 'fa-paw', 'color' => '#f97316',
                'acciones' => ['aprobar', 'rechazar'],
            ]);
        } elseif ($row['estado'] === 'aprobada') {
            $items[] = itemBase([
                'servicio' => 'adopcion', 'tipo' => 'adopcion_aprobada', 'estado' => 'completado', 'prioridad' => 'baja',
                'titulo' => 'Adopción aprobada: ' . $row['mascota'],
                'descripcion' => $row['cliente'] . ' · cita ' . $fechaFmt . ' ' . $horaFmt,
                'cliente' => $row['cliente'], 'mascota' => $row['mascota'],
                'id_referencia' => (int)$row['id_solicitud'],
                'creado_en' => $row['resuelto_en'] ?: $row['creado_en'],
                'icono' => 'fa-circle-check', 'color' => '#22c55e',
            ]);
        } else {
            $items[] = itemBase([
                'servicio' => 'adopcion', 'tipo' => 'adopcion_rechazada', 'estado' => 'cancelado', 'prioridad' => 'baja',
                'titulo' => 'Adopción rechazada: ' . $row['mascota'],
                'descripcion' => $row['cliente'] . ($row['motivo_rechazo'] ? ' · Motivo: ' . $row['motivo_rechazo'] : ''),
                'cliente' => $row['cliente'], 'mascota' => $row['mascota'],
                'id_referencia' => (int)$row['id_solicitud'],
                'creado_en' => $row['resuelto_en'] ?: $row['creado_en'],
                'icono' => 'fa-circle-xmark', 'color' => '#ef4444',
            ]);
        }
    }
    return $items;
}

// ── Recolectar según servicio ────────────────────────────────────────────
if ($servicio === 'adopcion') {
    $items = itemsAdopcionHistorial($conn);
} else {
    $items = array_merge(itemsCompras($conn, $servicio), itemsPagos($conn, $servicio));
    if ($servicio === 'paseos') {
        $items = array_merge($items, itemsEjecucionPaseos($conn), itemsCalificaciones($conn), itemsAsignaciones($conn, 'paseos'), itemsSolicitudesCancelacion($conn));
    } elseif ($servicio === 'adiestramiento') {
        $items = array_merge($items, itemsAsignaciones($conn, 'adiestramiento'));
    } else {
        $items = array_merge($items, itemsFasesHospedaje($conn));
    }
}

// id sintético estable (servicio+tipo+id_pedido+creado_en) — no hay tabla
// con autoincrement real que respaldar, pero alcanza para list keys, dedupe
// del poll y para la tabla actividad_vista (marcar visto).
foreach ($items as &$it) {
    $it['id'] = md5($it['servicio'] . '|' . $it['tipo'] . '|' . $it['id_pedido'] . '|' . $it['creado_en']);
}
unset($it);

// ── Ocultar lo que este admin ya marcó como "visto" ────────────────────
$idAdmin = (int)($_SESSION['usuario_id'] ?? 0);
if ($idAdmin) {
    $vistos = [];
    $rv = $conn->prepare("SELECT hash_item FROM actividad_vista WHERE id_admin = ?");
    $rv->bind_param("i", $idAdmin);
    $rv->execute();
    $resV = $rv->get_result();
    while ($row = $resV->fetch_assoc()) $vistos[$row['hash_item']] = true;
    $rv->close();
    if ($vistos) {
        $items = array_values(array_filter($items, function ($it) use ($vistos) { return !isset($vistos[$it['id']]); }));
    }
}

// ── Filtrar por búsqueda ──────────────────────────────────────────────
if ($buscar !== '') {
    $items = array_values(array_filter($items, function ($it) use ($buscar) { return coincide($it, $buscar); }));
}

// ── Filtrar por antes_fecha (paginación) ──────────────────────────────
if ($antesFecha !== '') {
    $items = array_values(array_filter($items, function ($it) use ($antesFecha) {
        return $it['creado_en'] && $it['creado_en'] < $antesFecha;
    }));
}

// ── Filtro de la pestaña de filtros ────────────────────────────────────
$ahora = new DateTime('now', new DateTimeZone('America/Bogota'));
if ($filtro === 'hoy') {
    $hoy = $ahora->format('Y-m-d');
    $items = array_values(array_filter($items, function ($it) use ($hoy) { return substr((string)$it['creado_en'], 0, 10) === $hoy; }));
} elseif ($filtro === '24h') {
    $limite = (clone $ahora)->modify('-1 day')->format('Y-m-d H:i:s');
    $items = array_values(array_filter($items, function ($it) use ($limite) { return $it['creado_en'] >= $limite; }));
} elseif ($filtro === '7d') {
    $limite = (clone $ahora)->modify('-7 days')->format('Y-m-d H:i:s');
    $items = array_values(array_filter($items, function ($it) use ($limite) { return $it['creado_en'] >= $limite; }));
} elseif ($filtro === 'pendientes') {
    $items = array_values(array_filter($items, function ($it) { return in_array($it['estado'], ['nuevo', 'pendiente'], true); }));
} elseif ($filtro === 'urgentes') {
    $items = array_values(array_filter($items, function ($it) { return $it['prioridad'] === 'alta'; }));
} elseif ($filtro === 'cancelados') {
    $items = array_values(array_filter($items, function ($it) { return $it['estado'] === 'cancelado'; }));
} elseif ($filtro === 'completados') {
    $items = array_values(array_filter($items, function ($it) { return $it['estado'] === 'completado'; }));
}

// ── Ordenar y paginar ──────────────────────────────────────────────────
usort($items, function ($a, $b) { return strcmp((string)$b['creado_en'], (string)$a['creado_en']); });

$hayMas = count($items) > $limit;
$items = array_slice($items, 0, $limit);

// ── Contadores por servicio (últimos 7 días) + necesitan atención ─────
function contadoresGlobales($conn) {
    $desde = (new DateTime('now', new DateTimeZone('America/Bogota')))->modify('-7 days')->format('Y-m-d H:i:s');
    $contar = function ($sql, $params = []) use ($conn) {
        $stmt = $conn->prepare($sql);
        if ($params) $stmt->bind_param(str_repeat('s', count($params)), ...$params);
        $stmt->execute();
        $n = (int)$stmt->get_result()->fetch_assoc()['n'];
        $stmt->close();
        return $n;
    };
    $paseos = $contar("SELECT COUNT(*) n FROM pedidos_paseo WHERE fecha_creacion >= ?", [$desde])
            + $contar("SELECT COUNT(*) n FROM pagos WHERE tipo_membresia='paseos' AND fecha_pago >= ?", [$desde]);
    $adiestramiento = $contar("SELECT COUNT(*) n FROM pedidos_adiestramiento WHERE fecha_creacion >= ?", [$desde])
            + $contar("SELECT COUNT(*) n FROM pagos WHERE tipo_membresia='adiestramiento' AND fecha_pago >= ?", [$desde]);
    $hospedaje = $contar("SELECT COUNT(*) n FROM pedidos_hospedaje WHERE fecha_creacion >= ?", [$desde])
            + $contar("SELECT COUNT(*) n FROM pagos WHERE tipo_membresia='hospedaje' AND fecha_pago >= ?", [$desde]);
    $adopcion = $contar("SELECT COUNT(*) n FROM solicitudes_adopcion WHERE creado_en >= ?", [$desde]);

    $sinAsignar =
        $contar("SELECT COUNT(*) n FROM pedidos_paseo p LEFT JOIN cronograma_paseos c ON c.id_pedido = p.id_pedido WHERE p.estado='listo_para_asignar' AND c.id_cronograma IS NULL")
      + $contar("SELECT COUNT(*) n FROM pedidos_adiestramiento p LEFT JOIN cronograma_adiestramiento c ON c.id_pedido = p.id_pedido WHERE p.estado='listo_para_asignar' AND c.id_cronograma IS NULL")
      + $contar("SELECT COUNT(*) n FROM pedidos_hospedaje WHERE estado='listo_para_asignar' AND fase_logistica='confirmado'")
      + $contar("SELECT COUNT(*) n FROM solicitudes_cancelacion WHERE estado='pendiente'")
      + $contar("SELECT COUNT(*) n FROM solicitudes_adopcion WHERE estado='pendiente'");

    return ['paseos' => $paseos, 'adiestramiento' => $adiestramiento, 'hospedaje' => $hospedaje, 'adopcion' => $adopcion, 'necesitan_atencion' => $sinAsignar];
}

responder(true, [
    'items'      => $items,
    'hay_mas'    => $hayMas,
    'contadores' => contadoresGlobales($conn),
]);
?>
