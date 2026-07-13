<?php
/**
 * enviar_reporte_soporte.php
 * (CLIENTE / PASEADOR) Envía un reporte de problema al grupo de soporte
 * de Telegram. El mensaje lleva el nombre del usuario y abajo el reporte.
 *
 * POST JSON: { "mensaje": "..." }
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once '../model/conexion.php';
include_once 'modelotelegram.php';

verificarSesion();

$data    = leerJsonBody();
$mensaje = trim(substr($data['mensaje'] ?? '', 0, 1000));

if (mb_strlen($mensaje) < 10) {
    responder(false, [], 'Describe el problema con un poco más de detalle (mínimo 10 caracteres).');
}

$nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';

// Rol del que reporta, para dar contexto al equipo
$idUsuario = (int)$_SESSION['usuario_id'];
$rol = 'Cliente';
$s = $conn->prepare("SELECT 1 FROM paseadores WHERE id_usuario = ? LIMIT 1");
$s->bind_param("i", $idUsuario);
$s->execute();
if ($s->get_result()->num_rows > 0) $rol = 'Paseador';
$s->close();
if (!empty($_SESSION['usuario_admin'])) $rol = 'Admin';

$telegram = new ModeloTelegram();
$r = $telegram->enviarMensajeSoporte(
    "🛠 <b>Nuevo reporte de soporte</b>\n\n" .
    "👤 <b>" . htmlspecialchars($nombre) . "</b> ($rol)\n\n" .
    htmlspecialchars($mensaje)
);

if (!$r['success']) {
    responder(false, [], 'No se pudo enviar el reporte. Intenta de nuevo en unos minutos.');
}

responder(true, [], 'Reporte enviado. El equipo lo revisará pronto.');
?>
