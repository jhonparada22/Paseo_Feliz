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
 * Crea la tabla paseos_dia si aún no existe (ver sql/modulo_paseos_dia.sql).
 * Se llama al inicio de los endpoints que la usan, así el módulo funciona
 * sin tener que ejecutar SQL a mano en phpMyAdmin.
 */
function asegurarTablaPaseosDia($conn) {
    $conn->query(
        "CREATE TABLE IF NOT EXISTS `paseos_dia` (
          `id_paseo_dia` int(11) NOT NULL AUTO_INCREMENT,
          `fecha` date NOT NULL,
          `id_pedido` int(11) NOT NULL,
          `id_paseador` int(11) NOT NULL,
          `estado` enum('pendiente','recogido','en_paseo','entregado','cancelado') NOT NULL DEFAULT 'pendiente',
          `motivo_cancelacion` varchar(120) DEFAULT NULL,
          `hora_recogida` datetime DEFAULT NULL,
          `hora_cancelacion` datetime DEFAULT NULL,
          `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id_paseo_dia`),
          UNIQUE KEY `uq_dia_pedido` (`fecha`,`id_pedido`),
          KEY `idx_pd_paseador_fecha` (`id_paseador`,`fecha`),
          CONSTRAINT `fk_pd_pedido` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos_paseo` (`id_pedido`) ON DELETE CASCADE ON UPDATE CASCADE,
          CONSTRAINT `fk_pd_paseador` FOREIGN KEY (`id_paseador`) REFERENCES `paseadores` (`id_paseador`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
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
?>