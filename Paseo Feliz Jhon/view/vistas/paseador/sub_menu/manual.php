<?php include_once '../../../../controller/control_acceso.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paseo Feliz - Manual del paseador</title>
    <link rel="icon" href="../../../assets/images/logo.png" type="image/png">
    <link rel="stylesheet" href="../../../css/principal_css/global.css?v=<?php echo @filemtime(__DIR__ . '/../../../css/principal_css/global.css'); ?>">
    <link rel="stylesheet" href="../../../css/paseador/sidebar_paseador.css?v=<?php echo @filemtime(__DIR__ . '/../../../css/paseador/sidebar_paseador.css'); ?>">
    <link rel="stylesheet" href="../../../css/principal_css/sub_menu.css?v=<?php echo @filemtime(__DIR__ . '/../../../css/principal_css/sub_menu.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="paseador-page">
<div id="contenedor_general" class="app-container">

    <nav class="sidebar">
        <div class="menu-hamburguesa-container">
            <div class="profile-circle" id="btn-menu"><i class="fas fa-bars"></i></div>
            <nav class="menu-desplegable" id="menu-latente">
                <ul>
                    <a href="./conocenos.php"><li><i class="fas fa-camera"></i><span>Conócenos</span></li></a>
                    <a href="./soporte.php"><li><i class="fas fa-headset"></i><span>Soporte</span></li></a>
                    <a href="./manual.php"><li><i class="fas fa-book-open"></i><span>Manual de uso</span></li></a>
                    <li><a href="../../../../controller/logout.php"><i class="fas fa-sign-out-alt"></i><span>Cerrar Sesión</span></a></li>
                </ul>
            </nav>
        </div>
        <ul class="nav-links">
            <li><a href="../index_paseador.php"><i class="fas fa-house"></i><span>Inicio</span></a></li>
            <li><a href="../paseos_paseador.php"><i class="fas fa-route"></i><span>Mis Paseos</span></a></li>
            <li><div class="nav-sep"></div></li>
            <li><a href="../Chat_paseador.php"><i class="far fa-comment-alt"></i><span>Chat</span></a></li>
            <li><a href="../mapa_paseador.php"><i class="fas fa-map-marker-alt"></i><span>Mapa</span></a></li>
            <li><div class="nav-sep"></div></li>
            <li><a href="../soporte_paseador.php"><i class="fas fa-headset"></i><span>Soporte</span></a></li>
            <li><a href="../usuario_paseador.php"><i class="fas fa-user"></i><span>Mi Perfil</span></a></li>
        </ul>
    </nav>

    <main class="main-content" style="background:#f1f5f9;overflow-y:auto;">
        <div class="sm-shell">
            <a href="../index_paseador.php" class="sm-volver"><i class="fas fa-arrow-left"></i> Volver</a>

            <div class="sm-titulo"><i class="fas fa-book-open"></i> Manual del paseador</div>
            <div class="sm-sub">Cómo trabajar con la app de Paseo Feliz, paso a paso.</div>

            <details class="sm-acordeon" open>
                <summary><i class="fas fa-house"></i> Tu día de trabajo (Inicio)</summary>
                <div class="cuerpo">
                    <p>En <strong>Inicio</strong> ves tu cronograma semanal: qué clientes tienes asignados cada día, con sus mascotas y horarios. El administrador es quien asigna clientes a tu cronograma — si un día no tienes nada, no hay servicios asignados para ti ese día.</p>
                </div>
            </details>

            <details class="sm-acordeon">
                <summary><i class="fas fa-map-location-dot"></i> El Mapa: tu herramienta principal</summary>
                <div class="cuerpo">
                    <ol>
                        <li>Al iniciar tu jornada, entra al <strong>Mapa</strong>: verás tu ruta del día con las paradas de recogida y entrega en orden.</li>
                        <li>Activa el GPS cuando te lo pida — así el cliente puede seguir el paseo en vivo y el sistema te avisa cuando estés cerca de una parada.</li>
                        <li>Mantén la app abierta durante el paseo. Si cambias de app y vuelves, el seguimiento se re-sincroniza solo.</li>
                        <li><strong>Consejo:</strong> instala la app en tu celular (opción "Instalar" del navegador) — el GPS funciona mejor así.</li>
                    </ol>
                </div>
            </details>

            <details class="sm-acordeon">
                <summary><i class="fas fa-dog"></i> Confirmar recogidas y entregas</summary>
                <div class="cuerpo">
                    <p>En la lista de "Paseos de hoy" del Mapa, cada mascota tiene sus botones:</p>
                    <ul>
                        <li><strong>Recogido:</strong> márcalo cuando tengas a la mascota. El cliente recibe el aviso.</li>
                        <li><strong>Entregado:</strong> márcalo al devolverla. En paseos grupales puedes entregar a todo el grupo junto.</li>
                        <li><strong>Deshacer (↺):</strong> si te equivocaste al marcar.</li>
                        <li><strong>Cámara (📷):</strong> sube una foto del paseo — el cliente la ve en su panel.</li>
                    </ul>
                    <p><strong>Importante:</strong> las confirmaciones son manuales y tuyas. El sistema nunca marca nada solo.</p>
                </div>
            </details>

            <details class="sm-acordeon">
                <summary><i class="fas fa-ban"></i> Solicitar la cancelación de un paseo</summary>
                <div class="cuerpo">
                    <p>Si algo impide el paseo (lluvia fuerte, no te entregan a la mascota, etc.), usa <strong>Cancelar</strong> en esa mascota y elige el motivo. <strong>El paseo NO se cancela de inmediato:</strong> el administrador revisa tu solicitud y la aprueba o rechaza. Mientras tanto, el paseo sigue en pie. La respuesta te llega por el chat de <strong>Informes</strong>.</p>
                </div>
            </details>

            <details class="sm-acordeon">
                <summary><i class="fas fa-comment-dots"></i> Chat</summary>
                <div class="cuerpo">
                    <p>Puedes chatear con tus clientes <strong>el día que los tengas asignados</strong>., El contacto "Informes" es informativo: te avisa decisiones del sistema pero no responde y con el administrador (solo si el chat esta activo).</p>
                </div>
            </details>

            <details class="sm-acordeon">
                <summary><i class="fas fa-user"></i> Tu perfil y tus clientes</summary>
                <div class="cuerpo">
                    <p>En <strong>Mi Perfil</strong> editas tu foto, biografía y datos. Los clientes ven tu perfil público con tu <strong>puntuación</strong> y reseñas — cuida tu servicio, que las estrellas las ponen ellos. Con el botón 👤 junto a cada mascota del mapa puedes ver el perfil del cliente asignado (sin sus datos privados).</p>
                </div>
            </details>

            <details class="sm-acordeon">
                <summary><i class="fas fa-bell"></i> Notificaciones: dónde y cómo te llegan</summary>
                <div class="cuerpo">
                    <ul>
                        <li><strong>En el Mapa:</strong> avisos en vivo mientras trabajas — al llegar a una parada te dice "estás en el punto, confirma la recogida/entrega".</li>
                        <li><strong>En el Chat (Informes):</strong> las respuestas del administrador a tus solicitudes de cancelación llegan como mensaje del contacto <strong>Informes</strong>.</li>
                        <li><strong>En tu teléfono:</strong> la primera vez que toques la pantalla, la app pedirá permiso para notificaciones — <strong>acéptalo</strong>. Así los mensajes de chat y los avisos de parada te suenan en la barra de notificaciones aunque lleves el teléfono en el bolsillo con la app minimizada.</li>
                    </ul>
                    <p><strong>Importante:</strong> funcionan con la app abierta o minimizada, no cerrada. Durante tu jornada mantén la app abierta (mejor si la instalaste). Si negaste el permiso por error: candado 🔒 junto a la dirección → Notificaciones → Permitir.</p>
                </div>
            </details>

            <details class="sm-acordeon">
                <summary><i class="fas fa-headset"></i> ¿Problemas con la app?</summary>
                <div class="cuerpo">
                    <p>Usa <strong>Soporte</strong> (en este mismo menú) para reportar cualquier falla — el equipo lo recibe al instante.</p>
                </div>
            </details>
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
