<?php
/**
 * actualizar_gps.php
 * El paseador envía su posición cada 5s vía fetch (navigator.geolocation.watchPosition).
 * - Guarda/actualiza la posición actual (gps_paseadores).
 * - Guarda un registro histórico (historial_gps).
 * - Detecta automáticamente llegada/salida de paradas (<40m) y dispara notificaciones.
 *
 * POST JSON: { "lat":7.89, "lng":-72.50, "velocidad":4.2, "precision":12 }
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once '../model/conexion.php';

define('RADIO_AVISO_PROXIMIDAD_M', 200); // aviso al cliente antes de llegar
define('RADIO_LLEGADA_M', 40);           // detección automática de llegada/salida

$idPaseador = obtenerIdPaseadorSesion($conn);
$data = leerJsonBody();

$lat        = floatval($data['lat'] ?? 0);
$lng        = floatval($data['lng'] ?? 0);
$velocidad  = floatval($data['velocidad'] ?? 0);
$precision  = floatval($data['precision'] ?? 0);

if (!$lat || !$lng) responder(false, [], 'Coordenadas inválidas.');

// Ruta activa de hoy (si la hay)
$hoy = date('Y-m-d');
$stmtR = $conn->prepare(
    "SELECT id_ruta FROM rutas WHERE id_paseador = ? AND fecha_paseo = ? AND id_estado IN (1,2) LIMIT 1"
);
$stmtR->bind_param("is", $idPaseador, $hoy);
$stmtR->execute();
$rutaRow = $stmtR->get_result()->fetch_assoc();
$stmtR->close();
$idRuta = $rutaRow ? (int)$rutaRow['id_ruta'] : null;

// 1. Upsert posición actual
$stmt = $conn->prepare(
    "INSERT INTO gps_paseadores (id_paseador, lat, lng, velocidad, precision_m)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE lat = VALUES(lat), lng = VALUES(lng),
        velocidad = VALUES(velocidad), precision_m = VALUES(precision_m)"
);
$stmt->bind_param("idddd", $idPaseador, $lat, $lng, $velocidad, $precision);
$stmt->execute();
$stmt->close();

// 2. Historial
$stmt = $conn->prepare(
    "INSERT INTO historial_gps (id_paseador, id_ruta, lat, lng, velocidad, precision_m) VALUES (?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param("iidddd", $idPaseador, $idRuta, $lat, $lng, $velocidad, $precision);
$stmt->execute();
$stmt->close();

$eventos = [];

// 3. Lógica de paradas automáticas (solo si hay ruta activa)
if ($idRuta) {
    $stmtP = $conn->prepare(
        "SELECT rp.id_parada, rp.tipo, rp.lat, rp.lng, rp.id_estado, rp.id_usuario_cliente, ep.nombre AS estado
         FROM ruta_paradas rp
         JOIN estados_parada ep ON ep.id_estado = rp.id_estado
         WHERE rp.id_ruta = ? AND rp.id_estado IN (1,2)
         ORDER BY rp.orden ASC LIMIT 1"
    );
    $stmtP->bind_param("i", $idRuta);
    $stmtP->execute();
    $parada = $stmtP->get_result()->fetch_assoc();
    $stmtP->close();

    if ($parada) {
        $dist = distanciaMetros($lat, $lng, $parada['lat'], $parada['lng']);

        if ($parada['estado'] === 'pendiente') {
            // Aviso de proximidad antes de llegar
            if ($dist <= RADIO_AVISO_PROXIMIDAD_M) {
                $minutos = max(1, round($dist / 80)); // ~80m/min caminando
                if ($parada['id_usuario_cliente']) {
                    $tipoNotif = $parada['tipo'] === 'entrega' ? 'proximidad_entrega' : 'proximidad_recogida';

                    // Evitar repetir la misma notificación mientras el paseador
                    // permanece dentro del radio de 300m de esta parada.
                    $chkN = $conn->prepare(
                        "SELECT 1 FROM notificaciones
                         WHERE id_usuario_destino = ? AND id_ruta = ? AND tipo = ?
                         LIMIT 1"
                    );
                    $chkN->bind_param("iis", $parada['id_usuario_cliente'], $idRuta, $tipoNotif);
                    $chkN->execute();
                    $yaExiste = $chkN->get_result()->num_rows > 0;
                    $chkN->close();

                    if (!$yaExiste) {
                        $msg = $parada['tipo'] === 'entrega'
                            ? 'El paseador está próximo a entregar a tu mascota.'
                            : "El paseador llegará en aproximadamente {$minutos} minutos.";
                        crearNotificacionInterna($conn, $parada['id_usuario_cliente'], $idRuta, $tipoNotif, $msg);
                    }
                }
            }
            // Llegó -> marcar "llegada"
            if ($dist <= RADIO_LLEGADA_M) {
                $u = $conn->prepare("UPDATE ruta_paradas SET id_estado = 2, hora_llegada = NOW() WHERE id_parada = ?");
                $u->bind_param("i", $parada['id_parada']);
                $u->execute(); $u->close();
                $eventos[] = ['tipo' => 'llegada', 'id_parada' => (int)$parada['id_parada']];
            }
        } elseif ($parada['estado'] === 'llegada' && $dist > RADIO_LLEGADA_M) {
            // Salió del punto -> marcar "completada"
            $u = $conn->prepare("UPDATE ruta_paradas SET id_estado = 3, hora_completado = NOW() WHERE id_parada = ?");
            $u->bind_param("i", $parada['id_parada']);
            $u->execute(); $u->close();
            $eventos[] = ['tipo' => 'completada', 'id_parada' => (int)$parada['id_parada']];
        }
    }
}

responder(true, ['eventos' => $eventos, 'id_ruta' => $idRuta]);
?>