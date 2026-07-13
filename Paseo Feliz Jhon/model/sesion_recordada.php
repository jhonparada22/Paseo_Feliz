<?php
/**
 * sesion_recordada.php
 * "Recordarme": cookie de larga duración + token en sesiones_recordadas
 * para que el usuario no tenga que volver a iniciar sesión mientras no
 * cierre sesión explícitamente (ni en el navegador normal ni en la app
 * instalada). El valor crudo del token SOLO vive en la cookie del
 * navegador; en la BD se guarda su hash — si alguien lee la tabla no
 * puede iniciar sesión con eso.
 *
 * Se usa desde: controller/login.php (crear), controller/control_acceso.php
 * y controller/verificar_sesion_recordada.php (restaurar), controller/logout.php
 * (borrar).
 */

define('PF_REMEMBER_COOKIE', 'pf_remember');
define('PF_REMEMBER_DIAS', 180);

/** Crea el token, lo guarda (hasheado) y pone la cookie. Llamar tras un login exitoso. */
function generarSesionRecordada($conn, $idUsuario) {
    $tokenRaw  = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $tokenRaw);
    $expira    = date('Y-m-d H:i:s', strtotime('+' . PF_REMEMBER_DIAS . ' days'));

    $stmt = $conn->prepare(
        "INSERT INTO sesiones_recordadas (id_usuario, token_hash, expira_en) VALUES (?, ?, ?)"
    );
    $stmt->bind_param("iss", $idUsuario, $tokenHash, $expira);
    $stmt->execute();
    $stmt->close();

    setcookie(PF_REMEMBER_COOKIE, $idUsuario . ':' . $tokenRaw, [
        'expires'  => time() + PF_REMEMBER_DIAS * 86400,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
}

/**
 * Si ya hay sesión de PHP activa, no hace nada y devuelve true. Si no la
 * hay pero la cookie "recordarme" es válida, repuebla $_SESSION con los
 * mismos datos que pone login.php (incluye admin/paseador frescos de BD)
 * y desliza el vencimiento otros PF_REMEMBER_DIAS. Devuelve true si quedó
 * con sesión (activa o recién restaurada), false si sigue sin sesión.
 */
function intentarRestaurarSesion($conn) {
    if (isset($_SESSION['usuario_logged']) && $_SESSION['usuario_logged'] === true) return true;

    if (empty($_COOKIE[PF_REMEMBER_COOKIE]) || strpos($_COOKIE[PF_REMEMBER_COOKIE], ':') === false) {
        return false;
    }

    list($idUsuarioStr, $tokenRaw) = explode(':', $_COOKIE[PF_REMEMBER_COOKIE], 2);
    $idUsuario = intval($idUsuarioStr);
    if (!$idUsuario || !$tokenRaw) return false;
    $tokenHash = hash('sha256', $tokenRaw);

    $stmt = $conn->prepare(
        "SELECT sr.id_token, u.id, u.nombre
         FROM sesiones_recordadas sr
         JOIN usuarios u ON u.id = sr.id_usuario
         WHERE sr.id_usuario = ? AND sr.token_hash = ? AND sr.expira_en > NOW() LIMIT 1"
    );
    $stmt->bind_param("is", $idUsuario, $tokenHash);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        // Token inválido/vencido/revocado: limpiar la cookie para no reintentar en cada visita
        setcookie(PF_REMEMBER_COOKIE, '', ['expires' => time() - 3600, 'path' => '/']);
        return false;
    }

    $_SESSION['usuario_logged'] = true;
    $_SESSION['usuario_id']     = (int)$row['id'];
    $_SESSION['usuario_nombre'] = $row['nombre'];

    $stmtAdmin = $conn->prepare("SELECT 1 FROM admin WHERE id_usuario = ? LIMIT 1");
    $stmtAdmin->bind_param("i", $idUsuario);
    $stmtAdmin->execute();
    if ($stmtAdmin->get_result()->num_rows > 0) $_SESSION['usuario_admin'] = true;
    $stmtAdmin->close();

    $stmtPaseador = $conn->prepare("SELECT 1 FROM paseadores WHERE id_usuario = ? LIMIT 1");
    $stmtPaseador->bind_param("i", $idUsuario);
    $stmtPaseador->execute();
    if ($stmtPaseador->get_result()->num_rows > 0) $_SESSION['es_paseador'] = true;
    $stmtPaseador->close();

    // Deslizar el vencimiento: mientras el usuario siga volviendo, nunca vence
    $nuevaExpira = date('Y-m-d H:i:s', strtotime('+' . PF_REMEMBER_DIAS . ' days'));
    $stmtUpd = $conn->prepare("UPDATE sesiones_recordadas SET expira_en = ? WHERE id_token = ?");
    $stmtUpd->bind_param("si", $nuevaExpira, $row['id_token']);
    $stmtUpd->execute();
    $stmtUpd->close();
    setcookie(PF_REMEMBER_COOKIE, $idUsuario . ':' . $tokenRaw, [
        'expires'  => time() + PF_REMEMBER_DIAS * 86400,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);

    return true;
}

/** Borra la cookie y su token en BD. Llamar al cerrar sesión explícitamente. */
function borrarSesionRecordada($conn) {
    if (!empty($_COOKIE[PF_REMEMBER_COOKIE]) && strpos($_COOKIE[PF_REMEMBER_COOKIE], ':') !== false) {
        list(, $tokenRaw) = explode(':', $_COOKIE[PF_REMEMBER_COOKIE], 2);
        $tokenHash = hash('sha256', $tokenRaw);
        $stmt = $conn->prepare("DELETE FROM sesiones_recordadas WHERE token_hash = ?");
        $stmt->bind_param("s", $tokenHash);
        $stmt->execute();
        $stmt->close();
    }
    setcookie(PF_REMEMBER_COOKIE, '', ['expires' => time() - 3600, 'path' => '/']);
}
?>
