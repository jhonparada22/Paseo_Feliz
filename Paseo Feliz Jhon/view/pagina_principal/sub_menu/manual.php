<?php include_once '../../../controller/control_acceso.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paseo Feliz - Manual de uso</title>
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

            <div class="sm-titulo"><i class="fas fa-book-open"></i> Manual de uso</div>
            <div class="sm-sub">Todo lo que puedes hacer en Paseo Feliz, paso a paso.</div>

            <details class="sm-acordeon" open>
                <summary><i class="fas fa-cart-shopping"></i> Contratar un servicio (paseos, adiestramiento u hospedaje)</summary>
                <div class="cuerpo">
                    <ol>
                        <li>Entra a <strong>Servicios</strong> y elige la pestaña del servicio que quieres.</li>
                        <li>Pulsa el botón de <strong>comprar membresía</strong> para abrir el asistente de compra.</li>
                        <li><strong>Paso 1:</strong> elige tu mascota (o regístrala ahí mismo con el botón "Registrar mascota") y configura el plan: cantidad, duración, días y hora.</li>
                        <li><strong>Paso 2:</strong> escribe tu dirección y confirma el punto exacto en el mapa (clic en el mapa, buscar la dirección o usar tu GPS).</li>
                        <li><strong>Paso 3:</strong> revisa el resumen y continúa al pago.</li>
                        <li><strong>Paso 4:</strong> paga con tarjeta, PSE o Nequi. ¡Listo! El equipo asignará a tu paseador/entrenador.</li>
                    </ol>
                </div>
            </details>

            <details class="sm-acordeon">
                <summary><i class="fas fa-paw"></i> Añadir otra mascota a un servicio activo</summary>
                <div class="cuerpo">
                    <p>Si ya tienes un servicio activo, en el panel de ese servicio aparece <strong>"Añadir a otra mascota al servicio"</strong>. Puedes elegir:</p>
                    <ul>
                        <li><strong>Unirse al servicio actual:</strong> misma configuración y personal — solo eliges la mascota y pagas.</li>
                        <li><strong>Configurar un servicio nuevo:</strong> eliges todo desde cero para esa mascota.</li>
                    </ul>
                </div>
            </details>

            <details class="sm-acordeon">
                <summary><i class="fas fa-map-location-dot"></i> Seguir el paseo en vivo (Mapa)</summary>
                <div class="cuerpo">
                    <p>El día de tu paseo, entra a <strong>Mapa</strong>: verás la ubicación del paseador en tiempo real, el tiempo estimado de llegada a tu casa, el recorrido y los eventos del paseo (recogida, entrega). Te llegarán avisos cuando el paseador esté cerca.</p>
                </div>
            </details>

            <details class="sm-acordeon">
                <summary><i class="fas fa-comment-dots"></i> Chat</summary>
                <div class="cuerpo">
                    <p>En <strong>Chat</strong> puedes escribirle a tu paseador asignado <strong>el día del servicio</strong>., El contacto "Informes" es un canal informativo del sistema: te avisa cosas importantes pero no responde mensajes y al administrador (solo si el chat esta activo).</p>
                </div>
            </details>

            <details class="sm-acordeon">
                <summary><i class="fas fa-star"></i> Calificar a tu paseador y ver su perfil</summary>
                <div class="cuerpo">
                    <p>Al terminar un paseo puedes calificarlo con estrellas y un comentario. En la tarjeta de tu paseador tienes <strong>"Ver perfil"</strong> para revisar su puntuación, cuántos paseos ha hecho y las reseñas de otros clientes.</p>
                </div>
            </details>

            <details class="sm-acordeon">
                <summary><i class="fas fa-bone"></i> Adopción</summary>
                <div class="cuerpo">
                    <ol>
                        <li>Entra a <strong>Adopción</strong> y elige la mascota que te interese.</li>
                        <li>Pulsa <strong>"Solicitar adopción"</strong> y elige fecha y hora (10:00 a.m. – 4:00 p.m.) para conocerla en nuestra sede.</li>
                        <li>El equipo revisa tu solicitud y te responde por el chat de <strong>Informes</strong>. Mientras haya una solicitud en trámite, esa mascota queda reservada.</li>
                    </ol>
                </div>
            </details>

            <details class="sm-acordeon">
                <summary><i class="fas fa-rotate-left"></i> Si tu servicio fue cancelado</summary>
                <div class="cuerpo">
                    <p>Si un servicio que ya pagaste fue cancelado, en la pestaña de ese servicio verás un aviso con el motivo y el botón <strong>"Recuperar servicio"</strong>: lo reactiva sin costo con su configuración original.</p>
                </div>
            </details>

            <details class="sm-acordeon">
                <summary><i class="fas fa-user-pen"></i> Tu perfil y tus mascotas</summary>
                <div class="cuerpo">
                    <p>En <strong>Usuario</strong> ves tu perfil. Con el botón del lápiz puedes editar tu biografía, foto, teléfono y dirección, y también <strong>registrar, editar o eliminar mascotas</strong> (nombre, raza, edad, foto, salud).</p>
                </div>
            </details>

            <details class="sm-acordeon">
                <summary><i class="fas fa-bell"></i> Notificaciones: dónde y cómo te llegan</summary>
                <div class="cuerpo">
                    <p>Los avisos te llegan por tres canales:</p>
                    <ul>
                        <li><strong>En el Mapa:</strong> el día del paseo verás avisos en vivo ("el paseador está llegando", "tu mascota fue recogida/entregada") con su línea de tiempo.</li>
                        <li><strong>En el Chat (Informes):</strong> las decisiones importantes — cancelaciones aprobadas, respuesta a tu solicitud de adopción — te llegan como mensaje del contacto <strong>Informes</strong>.</li>
                        <li><strong>En tu teléfono:</strong> la primera vez que toques la pantalla, la app te pedirá permiso para enviar notificaciones — <strong>acéptalo</strong>. Con eso, los mensajes de chat nuevos y los avisos del paseo te suenan en la barra de notificaciones aunque tengas la app minimizada.</li>
                    </ul>
                    <p><strong>Importante:</strong> las notificaciones del teléfono funcionan mientras la app esté abierta o minimizada (no cerrada del todo). El día de tu paseo, deja la app minimizada y te avisará sola. Si les dijiste "No permitir" por error, actívalas desde el candado 🔒 junto a la dirección de la página → Notificaciones → Permitir.</p>
                </div>
            </details>

            <details class="sm-acordeon">
                <summary><i class="fas fa-headset"></i> ¿Problemas?</summary>
                <div class="cuerpo">
                    <p>Usa <strong>Soporte</strong> (en este mismo menú) para reportar cualquier problema — el equipo lo recibe al instante.</p>
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
