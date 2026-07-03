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
?>