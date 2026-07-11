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

/**
 * true si pedidos_paseo ya tiene la columna hora_paseo (migración fase 15).
 * Permite desplegar el código antes de correr la migración sin romper nada.
 * Cachea el resultado por petición.
 */
function pedidosTienenHoraExacta($conn) {
    static $tiene = null;
    if ($tiene === null) {
        $res = $conn->query("SHOW COLUMNS FROM pedidos_paseo LIKE 'hora_paseo'");
        $tiene = $res && $res->num_rows > 0;
        if ($res) $res->close();
    }
    return $tiene;
}

/**
 * Etiqueta legible del intervalo real de un paseo a partir de su hora
 * exacta y duración: ("07:00", 60) -> "7:00 a.m. – 8:00 a.m.".
 * Es lo que se guarda en franja_horaria para que todas las pantallas
 * existentes sigan mostrando el horario sin cambios.
 */
function etiquetaHorario($hora, $duracionMin = 60) {
    if (!$hora || !preg_match('/^(\d{1,2}):(\d{2})/', $hora, $m)) return null;
    $ini = ((int)$m[1]) * 60 + (int)$m[2];
    $fin = $ini + max(15, (int)$duracionMin);
    $fmt = function ($totalMin) {
        $h24 = intdiv($totalMin, 60) % 24;
        $min = $totalMin % 60;
        $h12 = $h24 % 12 ?: 12;
        return sprintf('%d:%02d %s', $h12, $min, $h24 < 12 ? 'a.m.' : 'p.m.');
    };
    return $fmt($ini) . ' – ' . $fmt($fin);
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
 * Reordena las paradas PENDIENTES de una ruta agrupándolas en SALIDAS por
 * la hora exacta contratada (pedidos_paseo.hora_paseo): los pedidos con la
 * misma hora salen juntos (paseo grupal) y las salidas se encadenan en
 * orden cronológico. Dentro de cada salida las recogidas se ordenan por
 * vecino más cercano (Haversine) y sus entregas van inmediatamente después
 * (recogida 7:00 → entrega 8:00 → recogida 8:30 → ...), ya no "todas las
 * recogidas del día primero". hora_estimada de cada recogida es la hora
 * contratada (+ caminata dentro del grupo) y la de la entrega, hora +
 * duración. Los pedidos sin hora exacta (previos a la fase 15) van al
 * final con el estimado de caminata de siempre (~80 m/min). Las paradas
 * ya cerradas NO se tocan, conservan su 'orden' original.
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

    $colHora = pedidosTienenHoraExacta($conn)
        ? "pp.hora_paseo, COALESCE(pp.duracion_min, 60) AS duracion_min"
        : "NULL AS hora_paseo, 60 AS duracion_min";
    $stmt = $conn->prepare(
        "SELECT rp.id_parada, rp.tipo, rp.lat, rp.lng, rp.id_pedido,
                COALESCE(pp.franja_horaria, '') AS franja, $colHora
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
            $hora = $f['hora_paseo'] ? substr($f['hora_paseo'], 0, 5) : horaInicioDeFranja($f['franja']);
            $porPedido[$key] = [
                'hora'     => $hora ?: '99:99', // sin hora: bucket final
                'durMin'   => (int)$f['duracion_min'],
                'recogida' => null,
                'entrega'  => null,
            ];
        }
        $porPedido[$key][$f['tipo']] = $f;
    }

    // Una SALIDA por hora exacta; se recorren en orden cronológico
    $salidas = [];
    foreach ($porPedido as $pedido) {
        $salidas[$pedido['hora']][] = $pedido;
    }
    ksort($salidas);

    // Origen: última posición GPS conocida del paseador
    $origen = null;
    $stmt = $conn->prepare("SELECT lat, lng FROM gps_paseadores WHERE id_paseador = ?");
    $stmt->bind_param("i", $ruta['id_paseador']);
    $stmt->execute();
    $gps = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($gps) $origen = ['lat' => (float)$gps['lat'], 'lng' => (float)$gps['lng']];

    $etiquetas = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $horaBase = ((int)$ruta['id_estado'] === 1)
        ? strtotime($ruta['fecha_paseo'] . ' ' . $ruta['hora_inicio'])
        : time();

    $stmtUpd = $conn->prepare("UPDATE ruta_paradas SET orden = ?, etiqueta = ?, hora_estimada = ? WHERE id_parada = ?");
    $emitir = function ($parada, $tsEstimado) use (&$orden, $etiquetas, $stmtUpd) {
        $etiqueta = $etiquetas[$orden % 26];
        $horaEstimada = date('H:i:s', $tsEstimado);
        $stmtUpd->bind_param("issi", $orden, $etiqueta, $horaEstimada, $parada['id_parada']);
        $stmtUpd->execute();
        $orden++;
    };

    $orden  = $siguienteOrden;
    $actual = $origen;   // posición al terminar la salida anterior
    $cursor = $horaBase; // para el bucket sin hora exacta
    foreach ($salidas as $hora => $grupo) {
        // Vecino más cercano entre las recogidas de ESTA salida
        $secuencia = [];
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
            $secuencia[] = $elegido;
            if ($elegido['recogida']) {
                $actual = ['lat' => (float)$elegido['recogida']['lat'], 'lng' => (float)$elegido['recogida']['lng']];
            }
        }

        $conHora  = $hora !== '99:99';
        $inicioTs = $conHora ? strtotime($ruta['fecha_paseo'] . ' ' . $hora . ':00') : $cursor;
        $durMax   = 0;
        foreach ($secuencia as $ped) $durMax = max($durMax, $ped['durMin']);

        // Recogidas: la primera exactamente a la hora contratada, las demás
        // sumando la caminata entre casas del grupo
        $acumSeg = 0;
        $prev = null;
        foreach ($secuencia as $ped) {
            if (!$ped['recogida']) continue;
            if ($prev) {
                $d = distanciaMetros($prev['lat'], $prev['lng'], (float)$ped['recogida']['lat'], (float)$ped['recogida']['lng']);
                $acumSeg += (int)round(($d / 80) * 60); // ~80 m/min caminando
            }
            $emitir($ped['recogida'], $inicioTs + $acumSeg);
            $prev = ['lat' => (float)$ped['recogida']['lat'], 'lng' => (float)$ped['recogida']['lng']];
        }

        // Entregas de la misma salida: al cumplirse la duración del paseo
        $finTs = $inicioTs + $durMax * 60;
        $acumSeg = 0;
        foreach ($secuencia as $ped) {
            if (!$ped['entrega']) continue;
            if ($prev) {
                $d = distanciaMetros($prev['lat'], $prev['lng'], (float)$ped['entrega']['lat'], (float)$ped['entrega']['lng']);
                $acumSeg += (int)round(($d / 80) * 60);
            }
            $emitir($ped['entrega'], $finTs + $acumSeg);
            $prev = ['lat' => (float)$ped['entrega']['lat'], 'lng' => (float)$ped['entrega']['lng']];
            $actual = $prev;
        }

        $cursor = max($cursor, $finTs + $acumSeg);
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