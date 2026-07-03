<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$url_actual = $_SERVER['REQUEST_URI'];

// Páginas que viven dos niveles bajo view/ (view/vistas/admin/*, view/vistas/paseador/*,
// view/pagina_principal/sub_menu/*) necesitan subir dos niveles para llegar a registro/.
$dosNiveles = strpos($url_actual, '/sub_menu/') !== false
    || strpos($url_actual, '/vistas/admin/') !== false
    || strpos($url_actual, '/vistas/paseador/') !== false;
$prefijoLogin = $dosNiveles ? '../../registro/acceso.html' : '../registro/acceso.html';

// ── 1. Sin sesión activa → login ──────────────────────────────────────────────
if (!isset($_SESSION['usuario_logged']) || $_SESSION['usuario_logged'] !== true) {
    header("Location: $prefijoLogin");
    exit();
}

// ── 2. Ruta de admin → debe tener flag de admin en sesión ────────────────────
if (strpos($url_actual, '/vistas/admin/') !== false) {
    if (!isset($_SESSION['usuario_admin']) || $_SESSION['usuario_admin'] !== true) {
        header("Location: $prefijoLogin");
        exit();
    }
}

// ── 3. Ruta de paseador → verificar rol en BD (admin siempre puede entrar) ───
$es_admin = isset($_SESSION['usuario_admin']) && $_SESSION['usuario_admin'] === true;

if (!$es_admin && strpos($url_actual, '/vistas/paseador/') !== false) {

    if (!isset($_SESSION['usuario_id'])) {
        header("Location: $prefijoLogin");
        exit();
    }

    // Forzar consulta fresca cada vez
    unset($_SESSION['es_paseador']);

    require_once __DIR__ . '/../model/conexion.php';

    $id_usuario = intval($_SESSION['usuario_id']);

    $stmt = $conn->prepare(
        "SELECT id_paseador FROM paseadores WHERE id_usuario = ? LIMIT 1"
    );
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    $_SESSION['es_paseador'] = ($resultado->num_rows > 0);

    $stmt->close();

    if ($_SESSION['es_paseador'] !== true) {
        header("Location: $prefijoLogin");
        exit();
    }
}
?>