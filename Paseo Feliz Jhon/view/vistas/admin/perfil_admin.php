<?php
/**
 * perfil_admin.php?id=X — Perfil público visto por el ADMIN.
 * Sidebar rojo nativo del admin (activo: Usuarios, de donde llega).
 * El admin puede ver a cualquiera, incluyendo teléfono y dirección.
 * Otros roles se redirigen a su propia versión.
 * Lógica de permisos compartida en model/helpers_perfil_publico.php.
 */
include_once '../../../controller/control_acceso.php';
include_once '../../../model/conexion.php';
include_once '../../../model/helpers_perfil_publico.php';

$idViewer = (int)($_SESSION['usuario_id'] ?? 0);
$idPerfil = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

$rolViewer = ppRolDe($conn, $idViewer);
if ($rolViewer === 'paseador') { header('Location: ../paseador/perfil_paseador.php?id=' . $idPerfil); exit(); }
if ($rolViewer === 'cliente')  { header('Location: ../../pagina_principal/perfil.php?id=' . $idPerfil); exit(); }

$PP = ppCargarPerfil($conn, $idViewer, $idPerfil);
if ($PP['es_propio']) { header('Location: usuario_admin.php'); exit(); }

$PP_PREFIX = '../../';
$PP_VOLVER = 'usuarios_admin.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Paseo Feliz – Perfil</title>
    <link rel="icon" href="../../assets/images/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../css/admin/admin.css?v=<?php echo @filemtime(__DIR__ . '/../../css/admin/admin.css'); ?>">
    <link rel="stylesheet" href="../../css/admin/sidebar_admin.css?v=<?php echo @filemtime(__DIR__ . '/../../css/admin/sidebar_admin.css'); ?>">
    <link rel="stylesheet" href="../../css/principal_css/usuario.css?v=<?php echo @filemtime(__DIR__ . '/../../css/principal_css/usuario.css'); ?>">
    <link rel="stylesheet" href="../../css/principal_css/perfil_publico.css?v=<?php echo @filemtime(__DIR__ . '/../../css/principal_css/perfil_publico.css'); ?>">
</head>
<body>

<?php if (!$PP['permitido']): ?>
    <div class="pp-denegado">
        <i class="fas fa-lock"></i>
        <h2>No puedes ver este perfil</h2>
        <p>Este perfil no está disponible.<br>Te llevaremos de vuelta a Usuarios...</p>
    </div>
    <script>setTimeout(function () { window.location.href = 'usuarios_admin.php'; }, 2500);</script>
</body>
</html>
<?php exit(); endif; ?>

<div class="app-container">

    <!-- ══ SIDEBAR ══════════════════════════════════════════════ -->
    <nav class="sidebar">
        <div class="menu-hamburguesa-container">
            <div class="profile-circle" id="btn-menu"><i class="fas fa-bars"></i></div>
            <nav class="menu-desplegable" id="menu-latente">
                <ul>
                    <a href="./sub_menu/conocenos.php"><li><i class="fas fa-camera"></i><span>Conócenos</span></li></a>
                    <a href="./sub_menu/estado_bd.php"><li><i class="fas fa-database"></i><span>Estado del sistema</span></li></a>
                    <a href="./sub_menu/manual.php"><li><i class="fas fa-book-open"></i><span>Manual de uso</span></li></a>
                    
                    <li>
                        <a href="../../../controller/logout.php" style="color:#000000;">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Cerrar Sesión</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <ul class="nav-links">
            <li><a href="index_admin.php"><i class="fas fa-house"></i><span>Inicio</span></a></li>
            <li class="active"><a href="usuarios_admin.php"><i class="fas fa-users"></i><span>Usuarios</span></a></li>
            <li><a href="paseadores_admin.php"><i class="fas fa-person-walking"></i><span>Paseadores</span></a></li>
            <li><div class="nav-sep"></div></li>
            <li><a href="paseos_admin.php"><i class="fas fa-route"></i><span>Paseos</span></a></li>
            <li><a href="adiestramiento_admin.php"><i class="fas fa-graduation-cap"></i><span>Adiestramiento</span></a></li>
            <li><a href="hospedaje_admin.php"><i class="fas fa-house"></i><span>Hospedaje</span></a></li>
            <li><a href="pagos_admin.php"><i class="fas fa-credit-card"></i><span>Pagos</span></a></li>
            <li><div class="nav-sep"></div></li>
            <li><a href="Chat_admin.php"><i class="far fa-comment-alt"></i><span>Chat</span></a></li>
            <li><a href="mapa_admin.php"><i class="fas fa-map-location-dot"></i><span>Mapa</span></a></li>
            <li><a href="adopcion_admin.php"><i class="fas fa-bone"></i><span>Adopción</span></a></li>
            <li><div class="nav-sep"></div></li>
            <li><a href="usuario_admin.php"><i class="fas fa-user"></i><span>Usuario</span></a></li>
        </ul>
    </nav>

    <!-- ══ MAIN CONTENT ════════════════════════════════════════ -->
    <div class="main-content">
        <main class="page-area">
            <?php include '../../pagina_principal/_perfil_contenido.php'; ?>
        </main>
    </div>
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
