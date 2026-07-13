<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Anti-caché del HTML: el navegador siempre revalida la página con el
// servidor (los .js/.css se refrescan solos por su ?v=filemtime). Evita
// que PC/teléfono muestren versiones viejas de las páginas tras un cambio.
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

$url_actual = $_SERVER['REQUEST_URI'];

// Login con ruta ABSOLUTA desde la raíz del sitio: funciona desde cualquier
// profundidad de carpeta (antes las rutas relativas fallaban en carpetas
// nuevas como vistas/paseador/sub_menu/ o vistas/admin/sub_menu/).
$login_url = '/view/registro/acceso.html';

// ── 0. Sin sesión de PHP pero con cookie "recordarme" válida → restaurarla
// silenciosamente (no pedir login de nuevo hasta que el usuario cierre
// sesión explícito, ni en el navegador normal ni en la app instalada).
if (!isset($_SESSION['usuario_logged']) || $_SESSION['usuario_logged'] !== true) {
    require_once __DIR__ . '/../model/conexion.php';
    require_once __DIR__ . '/../model/sesion_recordada.php';
    intentarRestaurarSesion($conn);
}

// ── 1. Sin sesión activa → login ──────────────────────────────────────────────
if (!isset($_SESSION['usuario_logged']) || $_SESSION['usuario_logged'] !== true) {
    header("Location: " . $login_url);
    exit();
}

// ── 2. Ruta de admin → debe tener flag de admin en sesión ────────────────────
if (strpos($url_actual, '/vistas/admin/') !== false) {
    if (!isset($_SESSION['usuario_admin']) || $_SESSION['usuario_admin'] !== true) {
        header("Location: " . $login_url);
        exit();
    }
}

// ── 3. Ruta de paseador → verificar rol en BD (admin siempre puede entrar) ───
$es_admin = isset($_SESSION['usuario_admin']) && $_SESSION['usuario_admin'] === true;

if (!$es_admin && strpos($url_actual, '/vistas/paseador/') !== false) {

    if (!isset($_SESSION['usuario_id'])) {
        header("Location: " . $login_url);
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
        header("Location: " . $login_url);
        exit();
    }
}
?>