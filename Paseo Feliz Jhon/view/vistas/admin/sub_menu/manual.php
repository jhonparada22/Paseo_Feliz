<?php include_once '../../../../controller/control_acceso.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Paseo Feliz – Manual del administrador</title>
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

                <div class="sm-titulo"><i class="fas fa-book-open"></i> Manual del administrador</div>
                <div class="sm-sub">Cómo administrar Paseo Feliz, panel por panel.</div>

                <details class="sm-acordeon" open>
                    <summary><i class="fas fa-house"></i> Inicio: el Centro de Actividad</summary>
                    <div class="cuerpo">
                        <p>El dashboard muestra las estadísticas generales y el <strong>Centro de Actividad</strong>: todo lo que pasa en el sistema en vivo, con pestañas por servicio (Paseos, Adiestramiento, Hospedaje, Adopción).</p>
                        <ul>
                            <li><strong>Necesitan atención:</strong> pedidos pagados sin asignar, solicitudes de cancelación de paseadores y solicitudes de adopción — con botones para resolverlas ahí mismo.</li>
                            <li><strong>Visto:</strong> oculta una tarjeta ya revisada (solo para ti, no borra nada).</li>
                        </ul>
                    </div>
                </details>

                <details class="sm-acordeon">
                    <summary><i class="fas fa-route"></i> Asignar servicios (Paseos / Adiestramiento)</summary>
                    <div class="cuerpo">
                        <ol>
                            <li>Entra a <strong>Paseos</strong> (o Adiestramiento) y elige un pedido "Listo para asignar".</li>
                            <li>Pulsa <strong>"Asignar al cronograma"</strong>, elige el paseador/entrenador y los días de la semana.</li>
                            <li>El servicio queda en el cronograma semanal de esa persona y el cliente lo ve reflejado en su panel.</li>
                        </ol>
                        <p><strong>Cancelar servicio:</strong> el botón rojo cancela con motivo obligatorio (desactiva membresía y saca del cronograma; se avisa al cliente). Si te equivocas, el botón verde <strong>"Reactivar y asignar"</strong> lo revive. El cliente también puede recuperarlo gratis desde su panel.</p>
                    </div>
                </details>

                <details class="sm-acordeon">
                    <summary><i class="fas fa-van-shuttle"></i> Hospedaje: la logística de la van</summary>
                    <div class="cuerpo">
                        <p>Hospedaje no usa cronograma: la van (manejada por un administrador) recoge y devuelve a la mascota. En <strong>Hospedaje</strong> avanzas las fases de cada reserva: Confirmado → Recogida en camino → En hospedaje → Entrega en camino → Entregado. Hay botón para retroceder si te equivocas.</p>
                    </div>
                </details>

                <details class="sm-acordeon">
                    <summary><i class="fas fa-map-location-dot"></i> Mapa en tiempo real</summary>
                    <div class="cuerpo">
                        <p>En <strong>Mapa</strong> ves la posición GPS de los paseadores en vivo, los clientes con pedidos activos (cuadritos morados) y las rutas del día. Desde ahí también puedes <strong>trazar y asignar rutas manuales</strong>: seleccionas paseador, marcas puntos en el mapa (o usas la ubicación de un cliente) y confirmas.</p>
                    </div>
                </details>

                <details class="sm-acordeon">
                    <summary><i class="fas fa-ban"></i> Solicitudes de cancelación de paseadores</summary>
                    <div class="cuerpo">
                        <p>Cuando un paseador no puede hacer un paseo, envía una solicitud con motivo. Te aparece en <strong>"Necesitan atención"</strong> del Inicio: al <strong>aprobar</strong>, el paseo se cancela de verdad y se avisa al cliente; al <strong>rechazar</strong> (con nota opcional), el paseo continúa y se avisa al paseador. Los avisos llegan por el chat de <strong>Informes</strong>.</p>
                    </div>
                </details>

                <details class="sm-acordeon">
                    <summary><i class="fas fa-bone"></i> Adopciones</summary>
                    <div class="cuerpo">
                        <ol>
                            <li>En <strong>Adopción</strong> publicas mascotas (foto, datos, requisitos).</li>
                            <li>Cuando un cliente solicita una cita, te llega aviso por Telegram y aparece en "Necesitan atención".</li>
                            <li><strong>Aprobar</strong> confirma la cita (se le avisa por Informes con fecha, hora y dirección de la sede). <strong>Rechazar</strong> exige motivo, que también se le envía.</li>
                            <li>Mientras haya una solicitud pendiente, esa mascota queda reservada para otros.</li>
                        </ol>
                    </div>
                </details>

                <details class="sm-acordeon">
                    <summary><i class="fas fa-users"></i> Usuarios y perfiles</summary>
                    <div class="cuerpo">
                        <p>En <strong>Usuarios</strong> administras las cuentas: cambiar rol (cliente/paseador/administrador), ver mascotas, eliminar. Con <strong>"Ver perfil"</strong> (menú ⋮) inspeccionas el perfil completo de cualquiera — solo tú ves teléfono y dirección. En los perfiles de paseadores también ves su puntuación y reseñas.</p>
                    </div>
                </details>

                <details class="sm-acordeon">
                    <summary><i class="fas fa-comment-dots"></i> Chat e Informes</summary>
                    <div class="cuerpo">
                        <p>Como admin puedes chatear con cualquiera y activar/desactivar conversaciones. <strong>Informes</strong> es la cuenta-bot del sistema: envía avisos automáticos (rechazos, cancelaciones, adopciones) y nadie puede responderle — no le escribas, no lee.</p>
                    </div>
                </details>

                <details class="sm-acordeon">
                    <summary><i class="fas fa-bell"></i> Notificaciones: dónde llega cada cosa</summary>
                    <div class="cuerpo">
                        <ul>
                            <li><strong>Centro de Actividad (Inicio):</strong> ahí se concentra todo lo que requiere tu decisión — pagos por validar, solicitudes de cancelación, adopciones pendientes.</li>
                            <li><strong>Telegram:</strong> te llegan al grupo correspondiente los registros nuevos, los pagos, las solicitudes de adopción y los reportes de soporte.</li>
                            <li><strong>Chat:</strong> con la página de Chat abierta (aunque esté minimizada), los mensajes nuevos de clientes y paseadores te suenan como notificación del sistema. La primera vez la página pide permiso de notificaciones — acéptalo.</li>
                        </ul>
                        <p>Las notificaciones del sistema funcionan mientras la pestaña esté abierta o minimizada, no cerrada. Los clientes y paseadores reciben las suyas igual: avisos del paseo en su mapa, decisiones por el chat de Informes, y notificaciones en el teléfono con la app minimizada.</p>
                    </div>
                </details>

                <details class="sm-acordeon">
                    <summary><i class="fas fa-database"></i> Estado del sistema</summary>
                    <div class="cuerpo">
                        <p>En este mismo menú, <strong>"Estado del sistema"</strong> verifica la base de datos: revisa que todas las tablas existan y respondan, y si algo falla te dice exactamente qué tabla y qué error. Úsalo cuando algo de la app se comporte raro después de un cambio.</p>
                    </div>
                </details>
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
