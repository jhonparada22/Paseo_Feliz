<?php
/**
 * solicitar_adopcion.php
 * (CLIENTE) Solicita una cita para conocer a una mascota en adopción.
 * No aprueba nada — queda 'pendiente' hasta que el admin la resuelva
 * (ver resolver_adopcion.php). Avisa al equipo por Telegram.
 *
 * POST JSON: { id_adopcion, fecha_cita: "2026-07-15", hora_cita: "10:00" }
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once '../model/conexion.php';
include_once 'modelotelegram.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

verificarSesion();
$idUsuario = (int)$_SESSION['usuario_id'];

$data       = leerJsonBody();
$idAdopcion = intval($data['id_adopcion'] ?? 0);
$fechaCita  = trim($data['fecha_cita'] ?? '');
$horaCita   = trim($data['hora_cita'] ?? '');

$HORAS_VALIDAS = ['10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00'];

if (!$idAdopcion) responder(false, [], 'Falta indicar la mascota.');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaCita) || $fechaCita < date('Y-m-d')) {
    responder(false, [], 'Selecciona una fecha válida (no puede ser en el pasado).');
}
if (!in_array($horaCita, $HORAS_VALIDAS, true)) {
    responder(false, [], 'Selecciona una hora disponible (10:00 a.m. – 4:00 p.m.).');
}

$stmt = $conn->prepare("SELECT nombre FROM adopcion WHERE id_adopcion = ?");
$stmt->bind_param("i", $idAdopcion);
$stmt->execute();
$adopcion = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$adopcion) responder(false, [], 'Esa mascota ya no está disponible para adopción.');

// La mascota queda "en espera" mientras haya CUALQUIER solicitud pendiente
// para ella (sin importar quién la pidió) — nadie más puede solicitar cita
// hasta que el admin la resuelva (aprobar o rechazar).
$stmt = $conn->prepare(
    "SELECT 1 FROM solicitudes_adopcion WHERE id_adopcion = ? AND estado = 'pendiente' LIMIT 1"
);
$stmt->bind_param("i", $idAdopcion);
$stmt->execute();
$yaPendiente = $stmt->get_result()->num_rows > 0;
$stmt->close();
if ($yaPendiente) responder(false, [], 'Esta mascota ya tiene una solicitud de adopción en trámite. Intenta de nuevo más tarde.');

$stmt = $conn->prepare(
    "INSERT INTO solicitudes_adopcion (id_adopcion, id_usuario, fecha_cita, hora_cita) VALUES (?, ?, ?, ?)"
);
$stmt->bind_param("iiss", $idAdopcion, $idUsuario, $fechaCita, $horaCita);
$stmt->execute();
$stmt->close();

$nombreUsuario = $_SESSION['usuario_nombre'] ?? 'Usuario';
$fechaFmt = date('d/m/Y', strtotime($fechaCita));
$horaFmt  = date('g:i a', strtotime($horaCita));

try {
    $telegram = new ModeloTelegram();
    $telegram->enviarMensajeAdopcion(
        "🐾 <b>Nueva solicitud de adopción</b>\n\n" .
        "👤 <b>Usuario:</b> " . htmlspecialchars($nombreUsuario) . "\n" .
        "🐶 <b>Mascota:</b> " . htmlspecialchars($adopcion['nombre']) . "\n" .
        "📅 <b>Cita solicitada:</b> $fechaFmt a las $horaFmt"
    );
} catch (Exception $e) {
    // best-effort: si Telegram falla, la solicitud igual queda guardada
}

responder(true, [], 'Solicitud enviada correctamente.');
?>
