<?php include_once '../../../controller/control_acceso.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paseo Feliz - Conócenos</title>
    <link rel="icon" href="../../assets/images/logo.png" type="image/png">
    <link rel="stylesheet" href="../../css/principal_css/global.css?v=<?php echo @filemtime(__DIR__ . '/../../css/principal_css/global.css'); ?>">
    <link rel="stylesheet" href="../../css/principal_css/sidebar_usuario.css?v=<?php echo @filemtime(__DIR__ . '/../../css/principal_css/sidebar_usuario.css'); ?>">
    <link rel="stylesheet" href="../../css/principal_css/sub_menu.css?v=<?php echo @filemtime(__DIR__ . '/../../css/principal_css/sub_menu.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="usuario-page">
<div id="contenedor_general" class="app-container">

    <nav class="sidebar">
        <div class="menu-hamburguesa-container">
            <div class="profile-circle" id="btn-menu"><i class="fas fa-bars"></i></div>
            <nav class="menu-desplegable" id="menu-latente">
                <ul>
                    <a href="./conocenos.php"><li><i class="fas fa-camera"></i><span>Conócenos</span></li></a>
                    <a href="./soporte.php"><li><i class="fas fa-headset"></i><span>Soporte</span></li></a>
                    <a href="./manual.php"><li><i class="fas fa-book-open"></i><span>Manual de uso</span></li></a>
                    <li><a href="../../../controller/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i><span>Cerrar Sesión</span></a></li>
                </ul>
            </nav>
        </div>
        <ul class="nav-links">
            <li><a href="../inicio.php"><i class="fas fa-paw"></i><span>Servicios</span></a></li>
            <li><a href="../Chat.php"><i class="far fa-comment-alt"></i><span>Chat</span></a></li>
            <li><a href="../mapa.php"><i class="fas fa-map-marker-alt"></i><span>Mapa</span></a></li>
            <li><a href="../adopcion.php"><i class="fas fa-bone"></i><span>Adopción</span></a></li>
            <li><a href="../usuario.php"><i class="fas fa-user"></i><span>Usuario</span></a></li>
        </ul>
    </nav>

    <main class="main-content" style="background:#f1f5f9;overflow-y:auto;">
        <div class="sm-shell">
            <a href="../inicio.php" class="sm-volver"><i class="fas fa-arrow-left"></i> Volver</a>

            <div class="sm-titulo"><i class="fas fa-paw"></i> Conócenos</div>
            <div class="sm-sub">Somos Paseo Feliz: paseos, adiestramiento, hospedaje y adopción para tu mejor amigo en Cúcuta.</div>

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
