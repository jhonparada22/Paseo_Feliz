<?php
/**
 * obtener_notificaciones.php
 * Devuelve las notificaciones del usuario logueado (cliente, paseador o admin).
 * GET               -> últimas 20 notificaciones
 * GET ?marcar_leidas=1 -> además marca todas como leídas
 */
header("Access-Control-Allow-Origin: *");
include_once 'helpers.php';
include_once '../model/conexion.php';

verificarSesion();

$idUsuario = (int)$_SESSION['usuario_id'];

if (!empty($_GET['marcar_leidas'])) {
    $u = $conn->prepare("UPDATE notificaciones SET leida = 1 WHERE id_usuario_destino = ? AND leida = 0");
    $u->bind_param("i", $idUsuario);
    $u->execute();
    $u->close();
}

$stmt = $conn->prepare(
    "SELECT id_notificacion, id_ruta, tipo, mensaje, leida, fecha_creacion
     FROM notificaciones
     WHERE id_usuario_destino = ?
     ORDER BY id_notificacion DESC
     LIMIT 20"
);
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$res = $stmt->get_result();

$notificaciones = [];
while ($row = $res->fetch_assoc()) {
    $notificaciones[] = [
        'id'      => (int)$row['id_notificacion'],
        'id_ruta' => $row['id_ruta'] ? (int)$row['id_ruta'] : null,
        'tipo'    => $row['tipo'],
        'mensaje' => $row['mensaje'],
        'leida'   => (bool)$row['leida'],
        'fecha'   => $row['fecha_creacion'],
    ];
}
$stmt->close();

responder(true, ['notificaciones' => $notificaciones]);
?>
