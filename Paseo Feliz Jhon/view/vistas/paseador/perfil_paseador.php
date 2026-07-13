<?php
/**
 * perfil_paseador.php?id=X — Perfil público visto por un PASEADOR.
 * Sidebar verde nativo del paseador. Solo puede ver a los clientes que
 * tiene asignados en su cronograma (paseos o adiestramiento); nunca ve
 * teléfono ni dirección. Otros roles se redirigen a su propia versión.
 * Lógica de permisos compartida en model/helpers_perfil_publico.php.
 */
include_once '../../../controller/control_acceso.php';
include_once '../../../model/conexion.php';
include_once '../../../model/helpers_perfil_publico.php';

$idViewer = (int)($_SESSION['usuario_id'] ?? 0);
$idPerfil = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

$rolViewer = ppRolDe($conn, $idViewer);
if ($rolViewer === 'admin')   { header('Location: ../admin/perfil_admin.php?id=' . $idPerfil); exit(); }
if ($rolViewer === 'cliente') { header('Location: ../../pagina_principal/perfil.php?id=' . $idPerfil); exit(); }

$PP = ppCargarPerfil($conn, $idViewer, $idPerfil);
if ($PP['es_propio']) { header('Location: usuario_paseador.php'); exit(); }

$PP_PREFIX = '../../';
$PP_VOLVER = 'index_paseador.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paseo Feliz - Perfil</title>
    <link rel="icon" href="../../assets/images/logo.png" type="image/png">
    <link rel="stylesheet" href="../../css/principal_css/global.css?v=<?php echo @filemtime(__DIR__ . '/../../css/principal_css/global.css'); ?>">
    <link rel="stylesheet" href="../../css/principal_css/usuario.css?v=<?php echo @filemtime(__DIR__ . '/../../css/principal_css/usuario.css'); ?>">
    <link rel="stylesheet" href="../../css/paseador/sidebar_paseador.css?v=<?php echo @filemtime(__DIR__ . '/../../css/paseador/sidebar_paseador.css'); ?>">
    <link rel="stylesheet" href="../../css/principal_css/perfil_publico.css?v=<?php echo @filemtime(__DIR__ . '/../../css/principal_css/perfil_publico.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="paseador-page">

<?php if (!$PP['permitido']): ?>
    <div class="pp-denegado">
        <i class="fas fa-lock"></i>
        <h2>No puedes ver este perfil</h2>
        <p>Este perfil no está disponible para tu cuenta.<br>Te llevaremos de vuelta al inicio...</p>
    </div>
    <script>setTimeout(function () { window.location.href = 'index_paseador.php'; }, 2500);</script>
</body>
</html>
<?php exit(); endif; ?>

<div id="contenedor_general" class="app-container">

    <nav class="sidebar">
        <div class="menu-hamburguesa-container">
            <div class="profile-circle" id="btn-menu"><i class="fas fa-bars"></i></div>
            <nav class="menu-desplegable" id="menu-latente">
                <ul>
                    <a href="./sub_menu/conocenos.php">
                        <li><i class="fas fa-camera"></i><span>Conócenos</span></li>
                    </a>
                    <a href="./sub_menu/soporte.php"><li><i class="fas fa-headset"></i><span>Soporte</span></li></a>
                    <a href="./sub_menu/manual.php"><li><i class="fas fa-book-open"></i><span>Manual de uso</span></li></a>
                    <li>
                        <a href="../../../controller/logout.php">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Cerrar Sesión</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <ul class="nav-links">
            <li class="active"><a href="index_paseador.php"><i class="fas fa-house"></i><span>Inicio</span></a></li>
            <li><a href="paseos_paseador.php"><i class="fas fa-route"></i><span>Mis Paseos</span></a></li>
            <li>
                <div class="nav-sep"></div>
            </li>
            <li><a href="Chat_paseador.php"><i class="far fa-comment-alt"></i><span>Chat</span></a></li>
            <li><a href="mapa_paseador.php"><i class="fas fa-map-marker-alt"></i><span>Mapa</span></a></li>
            <li>
                <div class="nav-sep"></div>
            </li>
            <li><a href="soporte_paseador.php"><i class="fas fa-headset"></i><span>Soporte</span></a></li>
            <li><a href="usuario_paseador.php"><i class="fas fa-user"></i><span>Mi Perfil</span></a></li>
        </ul>
    </nav>

    <main class="profile-main-content">
        <?php include '../../pagina_principal/_perfil_contenido.php'; ?>
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
