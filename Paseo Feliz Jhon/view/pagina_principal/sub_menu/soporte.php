<?php include_once '../../../controller/control_acceso.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paseo Feliz - Soporte</title>
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

            <div class="sm-titulo"><i class="fas fa-headset"></i> Soporte</div>
            <div class="sm-sub">¿Algo no funciona o tuviste un problema con un servicio? Cuéntanos y el equipo lo revisará.</div>

            <div class="sm-card">
                <h3><i class="fas fa-flag"></i> Reportar un problema</h3>
                <textarea id="smReporte" class="sm-textarea" maxlength="1000"
                          placeholder="Describe el problema con el mayor detalle posible: qué estabas haciendo, qué esperabas que pasara y qué pasó en su lugar..."></textarea>
                <button class="sm-btn" id="smEnviar"><i class="fas fa-paper-plane"></i> Enviar reporte</button>
                <div class="sm-msg" id="smMsg"></div>
            </div>

            <div class="sm-card">
                <h3><i class="fas fa-circle-info"></i> Otras vías de contacto</h3>
                <div class="sm-fila"><i class="fab fa-whatsapp"></i><span>WhatsApp: <a href="https://wa.me/+573143971465" target="_blank" rel="noopener">+57 314 397 1465</a></span></div>
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

document.getElementById('smEnviar').addEventListener('click', async function () {
    const btn = this;
    const msg = document.getElementById('smMsg');
    const texto = document.getElementById('smReporte').value.trim();
    msg.className = 'sm-msg';

    if (texto.length < 10) {
        msg.textContent = 'Describe el problema con un poco más de detalle (mínimo 10 caracteres).';
        msg.classList.add('error');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
    try {
        const r = await fetch('../../../model/enviar_reporte_soporte.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mensaje: texto }),
        }).then(res => res.json());

        msg.textContent = r.message || (r.success ? 'Reporte enviado.' : 'No se pudo enviar.');
        msg.classList.add(r.success ? 'ok' : 'error');
        if (r.success) document.getElementById('smReporte').value = '';
    } catch (e) {
        msg.textContent = 'Error de conexión. Intenta de nuevo.';
        msg.classList.add('error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar reporte';
    }
});
</script>
</body>
</html>
