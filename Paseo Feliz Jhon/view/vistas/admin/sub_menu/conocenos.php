<?php include_once '../../../../controller/control_acceso.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Paseo Feliz – Conócenos</title>
    <link rel="icon" href="../../../assets/images/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../../css/admin/admin.css?v=<?php echo @filemtime(__DIR__ . '/../../../css/admin/admin.css'); ?>">
    <link rel="stylesheet" href="../../../css/admin/sidebar_admin.css?v=<?php echo @filemtime(__DIR__ . '/../../../css/admin/sidebar_admin.css'); ?>">
    <link rel="stylesheet" href="../../../css/principal_css/sub_menu.css?v=<?php echo @filemtime(__DIR__ . '/../../../css/principal_css/sub_menu.css'); ?>">
</head>
<body>
<div class="app-container">

    <nav class="sidebar">
        <div class="menu-hamburguesa-container">
            <div class="profile-circle" id="btn-menu"><i class="fas fa-bars"></i></div>
            <nav class="menu-desplegable" id="menu-latente">
                <ul>
                    <a href="./conocenos.php"><li><i class="fas fa-camera"></i><span>Conócenos</span></li></a>
                    <a href="./estado_bd.php"><li><i class="fas fa-database"></i><span>Estado del sistema</span></li></a>
                    <a href="./manual.php"><li><i class="fas fa-book-open"></i><span>Manual de uso</span></li></a>
                    <li>
                        <a href="../../../../controller/logout.php" style="color:#000000;">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Cerrar Sesión</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <ul class="nav-links">
            <li><a href="../index_admin.php"><i class="fas fa-house"></i><span>Inicio</span></a></li>
            <li><a href="../usuarios_admin.php"><i class="fas fa-users"></i><span>Usuarios</span></a></li>
            <li><a href="../paseadores_admin.php"><i class="fas fa-person-walking"></i><span>Paseadores</span></a></li>
            <li><div class="nav-sep"></div></li>
            <li><a href="../paseos_admin.php"><i class="fas fa-route"></i><span>Paseos</span></a></li>
            <li><a href="../adiestramiento_admin.php"><i class="fas fa-graduation-cap"></i><span>Adiestramiento</span></a></li>
            <li><a href="../hospedaje_admin.php"><i class="fas fa-house"></i><span>Hospedaje</span></a></li>
            <li><a href="../pagos_admin.php"><i class="fas fa-credit-card"></i><span>Pagos</span></a></li>
            <li><div class="nav-sep"></div></li>
            <li><a href="../Chat_admin.php"><i class="far fa-comment-alt"></i><span>Chat</span></a></li>
            <li><a href="../mapa_admin.php"><i class="fas fa-map-location-dot"></i><span>Mapa</span></a></li>
            <li><a href="../adopcion_admin.php"><i class="fas fa-bone"></i><span>Adopción</span></a></li>
            <li><div class="nav-sep"></div></li>
            <li><a href="../usuario_admin.php"><i class="fas fa-user"></i><span>Usuario</span></a></li>
        </ul>
    </nav>

    <div class="main-content">
        <main class="page-area">
            <div class="sm-shell">
                <a href="../index_admin.php" class="sm-volver"><i class="fas fa-arrow-left"></i> Volver</a>

                <div class="sm-titulo"><i class="fas fa-paw"></i> Conócenos</div>
                <div class="sm-sub">Somos Paseo Feliz: paseos, adiestramiento, hospedaje y adopción para las mascotas de Cúcuta.</div>

                <div class="sm-card">
                    <h3><i class="fas fa-location-dot"></i> Nuestra sede</h3>
                    <div class="sm-fila"><i class="fas fa-map-pin"></i><span>Calle 7 #0e-94 Motilones, Cúcuta, Norte de Santander, Colombia</span></div>
                    <div class="sm-fila"><i class="fas fa-clock"></i><span>Atención al público: lunes a sábado, 8:00 a.m. – 5:00 p.m.</span></div>
                </div>

                <div class="sm-card">
                    <h3><i class="fas fa-address-book"></i> Contáctanos</h3>
                    <div class="sm-fila"><i class="fab fa-whatsapp"></i><span>WhatsApp: <a href="https://wa.me/+573143971465" target="_blank" rel="noopener">+57 314 397 1465</a></span></div>
                    <div class="sm-fila"><i class="fas fa-envelope"></i><span>Correo: <a href="mailto:yilvermarzal09@gmail.com">yilvermarzal09@gmail.com</a></span></div>
                    <div class="sm-fila"><i class="fab fa-facebook"></i><span><a href="https://www.facebook.com/share/1EpFyxBnPH/" target="_blank" rel="noopener">Facebook</a></span></div>
                    <div class="sm-fila"><i class="fab fa-instagram"></i><span><a href="https://www.instagram.com/paseofelizcucuta" target="_blank" rel="noopener">Instagram — @paseofelizcucuta</a></span></div>
                    <div class="sm-fila"><i class="fab fa-tiktok"></i><span><a href="https://www.tiktok.com/@paseofeliz1" target="_blank" rel="noopener">TikTok — @paseofeliz1</a></span></div>
                </div>
            </div>
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
