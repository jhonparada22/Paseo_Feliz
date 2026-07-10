<?php
/**
 * actualizar_gps.php
 * El paseador envía su posición cada 5s vía fetch (navigator.geolocation.watchPosition).
 * - Guarda/actualiza la posición actual (gps_paseadores).
 * - Guarda un registro histórico (historial_gps).
 * - Detecta proximidad a la siguiente parada y AVISA (cliente y paseador),
 *   pero NUNCA cambia el estado de la parada: recogida y entrega solo se
 *   confirman con la acción manual del paseador (marcar_paseo_dia.php).
 *   Antes este endpoint marcaba llegada/completada por radio de 40m, lo
 *   que permitía "completar" paseos sin ejecutarlos (incluso con GPS
 *   simulado) y corrompía el conteo de paseos usados del cliente.
 *
 * POST JSON: { "lat":7.89, "lng":-72.50, "velocidad":4.2, "precision":12,
 *              "simulado": false }
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once 'config_operacion.php';
include_once '../model/conexion.php';

define('RADIO_AVISO_PROXIMIDAD_M', 200); // aviso al cliente antes de llegar
define('RADIO_LLEGADA_M', 40);           // aviso al paseador: "confirma la parada"

$idPaseador = obtenerIdPaseadorSesion($conn);
$data = leerJsonBody();

$lat        = floatval($data['lat'] ?? 0);
$lng        = floatval($data['lng'] ?? 0);
$velocidad  = floatval($data['velocidad'] ?? 0);
$precision  = floatval($data['precision'] ?? 0);
$simulado   = !empty($data['simulado']);

if (!$lat || !$lng) responder(false, [], 'Coordenadas inválidas.');

// Posiciones simuladas (fallback del navegador sin GPS): solo se aceptan
// si el entorno lo permite explícitamente (pruebas/demo). En producción
// se rechazan para que el tracking refleje únicamente ubicaciones reales.
if ($simulado && !PERMITIR_GPS_SIMULADO) {
    responder(false, ['gps_simulado_rechazado' => true],
        'El GPS simulado está deshabilitado. Activa la ubicación real de tu dispositivo.');
}

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

// 3. Avisos de proximidad (solo si hay ruta activa). Se evalúa la parada
// PENDIENTE más cercana a la posición real del paseador — no la primera
// por orden: en la calle el paseador puede adelantar o saltarse paradas,
// y con el criterio por orden los avisos se atribuían al punto equivocado.
if ($idRuta) {
    $stmtP = $conn->prepare(
        "SELECT rp.id_parada, rp.tipo, rp.lat, rp.lng, rp.id_usuario_cliente
         FROM ruta_paradas rp
         WHERE rp.id_ruta = ?
           AND rp.hora_recogida IS NULL AND rp.hora_entrega IS NULL
           AND rp.hora_cancelacion IS NULL
         ORDER BY rp.orden ASC"
    );
    $stmtP->bind_param("i", $idRuta);
    $stmtP->execute();
    $res = $stmtP->get_result();
    $parada = null;
    $dist   = null;
    while ($p = $res->fetch_assoc()) {
        $d = distanciaMetros($lat, $lng, $p['lat'], $p['lng']);
        if ($dist === null || $d < $dist) { $dist = $d; $parada = $p; }
    }
    $stmtP->close();

    if ($parada) {
        // Aviso de proximidad al CLIENTE antes de llegar (una vez por ruta+tipo)
        if ($dist <= RADIO_AVISO_PROXIMIDAD_M && $parada['id_usuario_cliente']) {
            $minutos   = max(1, round($dist / 80)); // ~80m/min caminando
            $tipoNotif = $parada['tipo'] === 'entrega' ? 'proximidad_entrega' : 'proximidad_recogida';

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

        // Aviso al PASEADOR: está en el punto -> que confirme manualmente.
        // No se toca ruta_paradas: la confirmación es una decisión humana.
        if ($dist <= RADIO_LLEGADA_M) {
            $eventos[] = [
                'tipo'        => 'cerca_de_parada',
                'id_parada'   => (int)$parada['id_parada'],
                'tipo_parada' => $parada['tipo'],
                'distancia_m' => (int)round($dist),
            ];
        }
    }
}

responder(true, ['eventos' => $eventos, 'id_ruta' => $idRuta]);
?>