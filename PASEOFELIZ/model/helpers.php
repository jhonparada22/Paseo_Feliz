<?php
/**
 * helpers.php
 * Funciones reutilizables para los endpoints del módulo de Mapas/Rutas.
 * Se incluye en cada archivo PHP del mapa (no modifica nada existente).
 */

function responder($success, $data = [], $message = '') {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array_merge(
        ['success' => $success, 'message' => $message],
        $data
    ));
    exit;
}

/** Verifica que haya una sesión activa (igual criterio que control_acceso.php) */
function verificarSesion() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['usuario_logged']) || $_SESSION['usuario_logged'] !== true) {
        responder(false, [], 'No autorizado. Inicia sesión.');
    }
}

/** Verifica que la sesión activa sea de administrador */
function verificarAdmin() {
    verificarSesion();
    if (!isset($_SESSION['usuario_admin']) || $_SESSION['usuario_admin'] !== true) {
        responder(false, [], 'Acceso restringido a administradores.');
    }
}

/** Obtiene el id_paseador asociado al usuario logueado (si existe) */
function obtenerIdPaseadorSesion($conn) {
    verificarSesion();
    $idUsuario = (int)$_SESSION['usuario_id'];
    $stmt = $conn->prepare("SELECT id_paseador FROM paseadores WHERE id_usuario = ?");
    $stmt->bind_param("i", $idUsuario);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        $stmt->close();
        responder(false, [], 'Este usuario no es un paseador.');
    }
    $row = $res->fetch_assoc();
    $stmt->close();
    return (int)$row['id_paseador'];
}

/** Distancia en metros entre dos coordenadas (fórmula de Haversine) */
function distanciaMetros($lat1, $lng1, $lat2, $lng2) {
    $R = 6371000; // radio de la Tierra en metros
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

/** Lee el cuerpo JSON de la petición (POST con fetch) */
function leerJsonBody() {
    $data = json_decode(file_get_contents("php://input"), true);
    return is_array($data) ? $data : [];
}

/** Inserta una notificación y la devuelve lista para el front */
function crearNotificacionInterna($conn, $idUsuarioDestino, $idRuta, $tipo, $mensaje) {
    $stmt = $conn->prepare(
        "INSERT INTO notificaciones (id_usuario_destino, id_ruta, tipo, mensaje) VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param("iiss", $idUsuarioDestino, $idRuta, $tipo, $mensaje);
    $stmt->execute();
    $stmt->close();
}

/**
 * Deriva el estado visible de un pedido a partir únicamente de los
 * timestamps de sus paradas (sin depender de ningún enum/tabla de estado
 * global). Reemplaza la antigua paseos_dia.estado.
 */
function estadoDerivadoPedido($horaRecogida, $horaEntrega, $horaCancelacion) {
    if ($horaCancelacion) return 'cancelado';
    if ($horaEntrega)     return 'entregado';
    if ($horaRecogida)    return 'recogido'; // cubre también el antiguo "en_paseo"
    return 'pendiente';
}

/**
 * Extrae la hora de inicio (formato HH:MM 24h) de una franja del wizard,
 * ej. "8:00 a.m. – 11:00 a.m." -> "08:00". Devuelve null si no se puede.
 */
function horaInicioDeFranja($franja) {
    if (!$franja) return null;
    if (!preg_match('/(\d{1,2}):(\d{2})\s*([ap])/iu', $franja, $m)) return null;
    $h = (int)$m[1] % 12;
    if (strtolower($m[3]) === 'p') $h += 12;
    return sprintf('%02d:%s', $h, $m[2]);
}

/** Devuelve el id_ruta activa (id_estado 1/2/3) de un paseador para una fecha, o null */
function obtenerRutaActivaHoy($conn, $idPaseador, $fecha) {
    $stmt = $conn->prepare(
        "SELECT id_ruta FROM rutas WHERE id_paseador = ? AND fecha_paseo = ? AND id_estado IN (1,2,3) LIMIT 1"
    );
    $stmt->bind_param("is", $idPaseador, $fecha);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $r ? (int)$r['id_ruta'] : null;
}

/**
 * Devuelve la ruta activa de un paseador para $fecha, o la crea si no existe.
 * Usa el UNIQUE KEY uq_ruta_activa (ver sql/migraciones/2026_07_fase1_consolidar_rutas.sql)
 * para resolver la condición de carrera entre el cronograma automático y la
 * asignación manual del admin: si ambos intentan crearla casi al mismo
 * tiempo, uno gana el INSERT y el otro reutiliza la que ganó.
 * Devuelve ['id_ruta' => int, 'nueva' => bool].
 */
function obtenerOCrearRutaHoy($conn, $idAdmin, $idPaseador, $fecha, $horaInicio) {
    $idRuta = obtenerRutaActivaHoy($conn, $idPaseador, $fecha);
    if ($idRuta) return ['id_ruta' => $idRuta, 'nueva' => false];

    try {
        $stmt = $conn->prepare(
            "INSERT INTO rutas (id_admin_creador, id_paseador, id_estado, fecha_paseo, hora_inicio, distancia_estimada_km, duracion_estimada_min)
             VALUES (?, ?, 1, ?, ?, 0, 0)"
        );
        $stmt->bind_param("iiss", $idAdmin, $idPaseador, $fecha, $horaInicio);
        $stmt->execute();
        $idRuta = $conn->insert_id;
        $stmt->close();
        return ['id_ruta' => $idRuta, 'nueva' => true];
    } catch (mysqli_sql_exception $e) {
        $idRuta = obtenerRutaActivaHoy($conn, $idPaseador, $fecha);
        if ($idRuta) return ['id_ruta' => $idRuta, 'nueva' => false];
        throw $e;
    }
}

/**
 * Reordena las paradas PENDIENTES de una ruta: agrupa primero por franja
 * horaria del pedido de origen (pedidos_paseo.franja_horaria) y, dentro de
 * cada franja, encadena por vecino más cercano (Haversine) desde la última
 * posición GPS conocida del paseador. Las paradas ya completadas/omitidas
 * o con algún timestamp de cierre (hora_recogida/hora_entrega/hora_cancelacion)
 * NO se tocan, conservan su 'orden' original. Recalcula orden, etiqueta y
 * hora_estimada (a ~80 m/min caminando) de las paradas reordenadas.
 */
function reordenarParadasPendientes($conn, $idRuta) {
    $stmt = $conn->prepare("SELECT fecha_paseo, hora_inicio, id_estado, id_paseador FROM rutas WHERE id_ruta = ?");
    $stmt->bind_param("i", $idRuta);
    $stmt->execute();
    $ruta = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$ruta) return;

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS n FROM ruta_paradas
         WHERE id_ruta = ? AND (id_estado IN (3,4) OR hora_recogida IS NOT NULL
               OR hora_entrega IS NOT NULL OR hora_cancelacion IS NOT NULL)"
    );
    $stmt->bind_param("i", $idRuta);
    $stmt->execute();
    $siguienteOrden = (int)$stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT rp.id_parada, rp.tipo, rp.lat, rp.lng, rp.id_pedido,
                COALESCE(pp.franja_horaria, '') AS franja
         FROM ruta_paradas rp
         LEFT JOIN pedidos_paseo pp ON pp.id_pedido = rp.id_pedido
         WHERE rp.id_ruta = ? AND rp.id_estado NOT IN (3,4)
           AND rp.hora_recogida IS NULL AND rp.hora_entrega IS NULL AND rp.hora_cancelacion IS NULL"
    );
    $stmt->bind_param("i", $idRuta);
    $stmt->execute();
    $res = $stmt->get_result();
    $filas = [];
    while ($row = $res->fetch_assoc()) $filas[] = $row;
    $stmt->close();
    if (!$filas) return;

    // Agrupar por pedido (cada pedido trae 1 parada de recogida + 1 de entrega)
    $porPedido = [];
    foreach ($filas as $f) {
        $key = $f['id_pedido'] !== null ? $f['id_pedido'] : ('np' . $f['id_parada']);
        if (!isset($porPedido[$key])) {
            $porPedido[$key] = ['horaFranja' => horaInicioDeFranja($f['franja']) ?: '99:99', 'recogida' => null, 'entrega' => null];
        }
        $porPedido[$key][$f['tipo']] = $f;
    }

    // Agrupar por franja horaria y ordenar los grupos por hora de inicio
    $porFranja = [];
    foreach ($porPedido as $pedido) {
        $porFranja[$pedido['horaFranja']][] = $pedido;
    }
    ksort($porFranja);

    // Origen: última posición GPS conocida del paseador
    $origen = null;
    $stmt = $conn->prepare("SELECT lat, lng FROM gps_paseadores WHERE id_paseador = ?");
    $stmt->bind_param("i", $ruta['id_paseador']);
    $stmt->execute();
    $gps = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($gps) $origen = ['lat' => (float)$gps['lat'], 'lng' => (float)$gps['lng']];

    // Vecino más cercano DENTRO de cada grupo de franja, encadenado entre grupos
    $actual = $origen;
    $secuencia = [];
    foreach ($porFranja as $grupo) {
        $restantes = $grupo;
        while ($restantes) {
            if ($actual === null) {
                $elegido = array_shift($restantes);
            } else {
                $mejorIdx = 0; $mejorDist = PHP_FLOAT_MAX;
                foreach ($restantes as $idx => $ped) {
                    if (!$ped['recogida']) continue;
                    $d = distanciaMetros($actual['lat'], $actual['lng'], (float)$ped['recogida']['lat'], (float)$ped['recogida']['lng']);
                    if ($d < $mejorDist) { $mejorDist = $d; $mejorIdx = $idx; }
                }
                $elegido = $restantes[$mejorIdx];
                array_splice($restantes, $mejorIdx, 1);
            }
            if ($elegido['recogida']) {
                $secuencia[] = $elegido;
                $actual = ['lat' => (float)$elegido['recogida']['lat'], 'lng' => (float)$elegido['recogida']['lng']];
            }
        }
    }

    // Reasignar orden/etiqueta/hora_estimada: todas las recogidas primero, luego todas las entregas
    $etiquetas = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $horaBase = ((int)$ruta['id_estado'] === 1)
        ? strtotime($ruta['fecha_paseo'] . ' ' . $ruta['hora_inicio'])
        : time();

    $stmtUpd = $conn->prepare("UPDATE ruta_paradas SET orden = ?, etiqueta = ?, hora_estimada = ? WHERE id_parada = ?");

    $orden = $siguienteOrden;
    foreach (['recogida', 'entrega'] as $tipoFase) {
        $acumSeg = 0;
        $prevPunto = $origen;
        foreach ($secuencia as $ped) {
            $p = $ped[$tipoFase];
            if (!$p) continue;
            if ($prevPunto) {
                $d = distanciaMetros($prevPunto['lat'], $prevPunto['lng'], (float)$p['lat'], (float)$p['lng']);
                $acumSeg += (int)round(($d / 80) * 60); // ~80 m/min caminando
            }
            $etiqueta = $etiquetas[$orden % 26];
            $horaEstimada = date('H:i:s', $horaBase + $acumSeg);
            $stmtUpd->bind_param("issi", $orden, $etiqueta, $horaEstimada, $p['id_parada']);
            $stmtUpd->execute();
            $orden++;
            $prevPunto = ['lat' => (float)$p['lat'], 'lng' => (float)$p['lng']];
        }
    }
    $stmtUpd->close();
}

/** Recalcula distancia_estimada_km y duracion_estimada_min de una ruta a partir de sus paradas ordenadas */
function recalcularDistanciaYDuracion($conn, $idRuta) {
    $stmt = $conn->prepare("SELECT lat, lng FROM ruta_paradas WHERE id_ruta = ? ORDER BY orden ASC");
    $stmt->bind_param("i", $idRuta);
    $stmt->execute();
    $res = $stmt->get_result();
    $puntos = [];
    while ($row = $res->fetch_assoc()) $puntos[] = $row;
    $stmt->close();

    $distanciaKm = 0;
    for ($i = 0; $i < count($puntos) - 1; $i++) {
        $distanciaKm += distanciaMetros(
            (float)$puntos[$i]['lat'], (float)$puntos[$i]['lng'],
            (float)$puntos[$i + 1]['lat'], (float)$puntos[$i + 1]['lng']
        ) / 1000;
    }
    $duracionMin = (int)round($distanciaKm * 12);

    $stmt = $conn->prepare("UPDATE rutas SET distancia_estimada_km = ?, duracion_estimada_min = ? WHERE id_ruta = ?");
    $stmt->bind_param("dii", $distanciaKm, $duracionMin, $idRuta);
    $stmt->execute();
    $stmt->close();
}
?>