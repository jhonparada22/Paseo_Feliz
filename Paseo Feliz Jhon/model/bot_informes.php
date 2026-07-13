<?php
/**
 * bot_informes.php
 * "Informes": cuenta de chat de solo-lectura que el sistema usa para avisar
 * al cliente/paseador cosas puntuales (por ejemplo, el motivo de un rechazo)
 * sin que eso vaya por el flujo normal de conversación con un admin humano.
 * Vive como fila real en `usuarios` + `admin` (para que el chat la trate
 * "sin restricciones", ver controller/chat_controller.php), pero después de
 * cada mensaje se deja la conversación con activo=0 para que el destinatario
 * no pueda escribirle de vuelta — nunca responde porque no hay nadie leyendo.
 */

define('BOT_INFORMES_EMAIL', 'bot-informes@paseofeliz.local');

/** Id (usuarios.id) del bot, cacheado por request. Devuelve null si aún no se creó (ver SQL). */
function obtenerIdBotInformes($conn) {
    static $id = null;
    if ($id === null) {
        $email = BOT_INFORMES_EMAIL;
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $id = $row ? (int)$row['id'] : 0;
    }
    return $id ?: null;
}

/**
 * Manda un mensaje del bot a un usuario (cliente o paseador). Crea la
 * conversación si no existía. Deja la conversación bloqueada para escribir
 * (activo=0) — el destinatario la ve, pero no puede responder.
 * Devuelve true si se envió.
 */
function enviarMensajeBot($conn, $idUsuarioDestino, $mensaje) {
    $idBot = obtenerIdBotInformes($conn);
    if (!$idBot || !$idUsuarioDestino || $idBot === $idUsuarioDestino) return false;

    $u1 = min($idBot, $idUsuarioDestino);
    $u2 = max($idBot, $idUsuarioDestino);

    $stmt = $conn->prepare(
        "SELECT id_conversacion FROM conversaciones WHERE id_usuario_1 = ? AND id_usuario_2 = ?"
    );
    $stmt->bind_param("ii", $u1, $u2);
    $stmt->execute();
    $conv = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($conv) {
        $idConv = (int)$conv['id_conversacion'];
    } else {
        $ins = $conn->prepare("INSERT INTO conversaciones (id_usuario_1, id_usuario_2, activo) VALUES (?, ?, 0)");
        $ins->bind_param("ii", $u1, $u2);
        $ins->execute();
        $idConv = $ins->insert_id;
        $ins->close();
    }

    $stmtMsg = $conn->prepare(
        "INSERT INTO mensajes (id_conversacion, id_emisor, mensaje) VALUES (?, ?, ?)"
    );
    $stmtMsg->bind_param("iis", $idConv, $idBot, $mensaje);
    $stmtMsg->execute();
    $stmtMsg->close();

    $stmtBloq = $conn->prepare("UPDATE conversaciones SET activo = 0 WHERE id_conversacion = ?");
    $stmtBloq->bind_param("i", $idConv);
    $stmtBloq->execute();
    $stmtBloq->close();

    return true;
}
?>
