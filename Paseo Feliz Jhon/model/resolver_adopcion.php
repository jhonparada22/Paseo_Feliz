<?php
/**
 * resolver_adopcion.php
 * (ADMIN) Aprueba o rechaza una solicitud de cita de adopción
 * (creada en solicitar_adopcion.php). Avisa al cliente por el chat del
 * bot "Informes" — al rechazar, el motivo es obligatorio.
 *
 * POST JSON: { id_solicitud, accion: 'aprobar'|'rechazar', motivo? }
 * (motivo es obligatorio si accion === 'rechazar')
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once '../model/conexion.php';
include_once 'bot_informes.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

verificarAdmin();
$idAdmin = (int)$_SESSION['usuario_id'];

$data        = leerJsonBody();
$idSolicitud = intval($data['id_solicitud'] ?? 0);
$accion      = $data['accion'] ?? '';
$motivo      = trim(substr($data['motivo'] ?? '', 0, 255));

if (!$idSolicitud || !in_array($accion, ['aprobar', 'rechazar'], true)) {
    responder(false, [], 'Datos inválidos: se requiere id_solicitud y acción (aprobar|rechazar).');
}
if ($accion === 'rechazar' && $motivo === '') {
    responder(false, [], 'Debes indicar el motivo del rechazo.');
}

$stmt = $conn->prepare(
    "SELECT sa.*, a.nombre AS mascota
     FROM solicitudes_adopcion sa
     JOIN adopcion a ON a.id_adopcion = sa.id_adopcion
     WHERE sa.id_solicitud = ?"
);
$stmt->bind_param("i", $idSolicitud);
$stmt->execute();
$sol = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sol) responder(false, [], 'La solicitud no existe.');
if ($sol['estado'] !== 'pendiente') {
    responder(false, [], 'Esta solicitud ya fue ' . $sol['estado'] . '.');
}

$idUsuario = (int)$sol['id_usuario'];
$mascota   = $sol['mascota'];
$fechaFmt  = date('d/m/Y', strtotime($sol['fecha_cita']));
$horaFmt   = date('g:i a', strtotime($sol['hora_cita']));

$nuevoEstado = $accion === 'aprobar' ? 'aprobada' : 'rechazada';
$stmt = $conn->prepare(
    "UPDATE solicitudes_adopcion
     SET estado = ?, motivo_rechazo = ?, resuelto_por = ?, resuelto_en = NOW()
     WHERE id_solicitud = ? AND estado = 'pendiente'"
);
$motivoGuardar = $accion === 'rechazar' ? $motivo : null;
$stmt->bind_param("ssii", $nuevoEstado, $motivoGuardar, $idAdmin, $idSolicitud);
$stmt->execute();
$stmt->close();

// El aviso por el chat del bot es "best-effort": si falla (por lo que sea),
// la aprobación/rechazo YA se guardó arriba y no debe perderse la respuesta
// limpia al admin por culpa de un error en el mensaje de chat.
$avisoEnviado = true;
try {
    if ($accion === 'aprobar') {
        enviarMensajeBot($conn, $idUsuario,
            "🎉 ¡Tu solicitud para conocer a $mascota fue aprobada!\n\n" .
            "Te esperamos el $fechaFmt a las $horaFmt en Calle 7 #0e-94 Motilones, Cúcuta.\n" .
            "Si necesitas cambiar la hora, contáctanos por este medio."
        );
    } else {
        enviarMensajeBot($conn, $idUsuario,
            "Tu solicitud para conocer a $mascota (cita del $fechaFmt a las $horaFmt) fue rechazada.\n\n" .
            "Motivo: $motivo"
        );
    }
} catch (Throwable $e) {
    $avisoEnviado = false;
    error_log('resolver_adopcion.php: enviarMensajeBot falló: ' . $e->getMessage());
}

if ($accion === 'aprobar') {
    responder(true, ['estado' => 'aprobada', 'aviso_enviado' => $avisoEnviado],
        $avisoEnviado ? 'Solicitud aprobada. El cliente fue notificado.' : 'Solicitud aprobada (no se pudo avisar por chat).');
} else {
    responder(true, ['estado' => 'rechazada', 'aviso_enviado' => $avisoEnviado],
        $avisoEnviado ? 'Solicitud rechazada. El cliente fue notificado.' : 'Solicitud rechazada (no se pudo avisar por chat).');
}
?>
