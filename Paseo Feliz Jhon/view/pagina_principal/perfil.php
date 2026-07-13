<?php
/**
 * perfil.php?id=X — Perfil público visto por un CLIENTE.
 * Sidebar azul nativo del cliente. Si quien entra es paseador o admin,
 * se le redirige a su propia versión (perfil_paseador.php / perfil_admin.php)
 * para que cada rol use su entorno CSS sin conflictos.
 * Lógica de permisos compartida en model/helpers_perfil_publico.php.
 */
include_once '../../controller/control_acceso.php';
include_once '../../model/conexion.php';
include_once '../../model/helpers_perfil_publico.php';

$idViewer = (int)($_SESSION['usuario_id'] ?? 0);
$idPerfil = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

$rolViewer = ppRolDe($conn, $idViewer);
if ($rolViewer === 'admin')    { header('Location: ../vistas/admin/perfil_admin.php?id=' . $idPerfil); exit(); }
if ($rolViewer === 'paseador') { header('Location: ../vistas/paseador/perfil_paseador.php?id=' . $idPerfil); exit(); }

$PP = ppCargarPerfil($conn, $idViewer, $idPerfil);
if ($PP['es_propio']) { header('Location: usuario.php'); exit(); }

$PP_PREFIX = '../';
$PP_VOLVER = 'inicio.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paseo Feliz - Perfil</title>
    <link rel="icon" href="../assets/images/logo.png" type="image/png">
    <link rel="stylesheet" href="../css/principal_css/global.css?v=<?php echo @filemtime(__DIR__ . '/../css/principal_css/global.css'); ?>">
    <link rel="stylesheet" href="../css/principal_css/usuario.css?v=<?php echo @filemtime(__DIR__ . '/../css/principal_css/usuario.css'); ?>">
    <link rel="stylesheet" href="../css/principal_css/sidebar_usuario.css?v=<?php echo @filemtime(__DIR__ . '/../css/principal_css/sidebar_usuario.css'); ?>">
    <link rel="stylesheet" href="../css/principal_css/perfil_publico.css?v=<?php echo @filemtime(__DIR__ . '/../css/principal_css/perfil_publico.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="usuario-page">

<?php if (!$PP['permitido']): ?>
    <div class="pp-denegado">
        <i class="fas fa-lock"></i>
        <h2>No puedes ver este perfil</h2>
        <p>Este perfil no está disponible para tu cuenta.<br>Te llevaremos de vuelta al inicio...</p>
    </div>
    <script>setTimeout(function () { window.location.href = 'inicio.php'; }, 2500);</script>
</body>
</html>
<?php exit(); endif; ?>

<div id="contenedor_general" class="app-container">

    <nav class="sidebar">
        <div class="menu-hamburguesa-container">
            <div class="profile-circle" id="btn-menu"><i class="fas fa-bars"></i></div>
            <nav class="menu-desplegable" id="menu-latente">
                <ul>
                    <li><a href="./sub_menu/conocenos.php"><i class="fas fa-camera"></i> <span>Conocenos</span></a></li>
                    <li><a href="./sub_menu/soporte.php"><i class="fas fa-headset"></i> <span>Soporte</span></a></li>
                    <li><a href="./sub_menu/manual.php"><i class="fas fa-book-open"></i> <span>Manual de uso</span></a></li>
                    
                    <li><a href="../../controller/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> <span>Cerrar Sesión</span></a></li>
                </ul>
            </nav>
        </div>
        <ul class="nav-links">
            <li class="active"><a href="inicio.php"><i class="fas fa-paw"></i> <span>Servicios</span></a></li>
            <li><a href="Chat.php"><i class="far fa-comment-alt"></i> <span>Chat</span></a></li>
            <li><a href="mapa.php"><i class="fas fa-map-marker-alt"></i> <span>Mapa</span></a></li>
            <li><a href="adopcion.php"><i class="fas fa-bone"></i> <span>Adopción</span></a></li>
            <li><a href="usuario.php"><i class="fas fa-user"></i> <span>Usuario</span></a></li>
        </ul>
    </nav>

    <main class="profile-main-content">
        <?php include '_perfil_contenido.php'; ?>
    </main>
</div>

<script>
(function () {
    const btn  = document.getElementById('btn-menu');
    const menu = document.getElementById('menu-latente');
    if (!btn || !menu) return;
    btn.addEventListener('click', function (e) { e.stopPropagation(); menu.classList.toggle('show'); });
    document.addEventListener('click', function (e) {
        if (!menu.contains(e.target) && e.target !== btn) menu.classList.remove('show');
    });
})();
</script>
</body>
</html>
