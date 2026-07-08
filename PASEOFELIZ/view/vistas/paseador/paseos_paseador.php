<?php include_once '../../../controller/control_acceso.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Paseos - Paseo Feliz</title>
    <link rel="icon" href="../../assets/images/logo.png" type="image/png">

    <link rel="stylesheet" href="../../css/principal_css/global.css">
    <link rel="stylesheet" href="../../css/principal_css/paseos.css">
    <link rel="stylesheet" href="../../css/paseador/paseos_paseador.css">
    <link rel="stylesheet" href="../../css/paseador/sidebar_paseador.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="paseador-page">

    <div id="contenedor_general" class="app-container">

        <nav class="sidebar">
            <div class="menu-hamburguesa-container">
                <div class="profile-circle" id="btn-menu"><i class="fas fa-bars"></i></div>
                <nav class="menu-desplegable" id="menu-latente">
                    <ul>
                        <a href="../../pagina_principal/sub_menu/conocenos.php"><li><i class="fas fa-camera"></i><span>Conócenos</span></li></a>
                        <a href="../../pagina_principal/sub_menu/direccion_oficial.php"><li><i class="fas fa-book-open"></i><span>Dirección oficial</span></li></a>
                        <a href="../../pagina_principal/sub_menu/centro_de_ayuda.php"><li><i class="fas fa-sliders-h"></i><span>Centro de ayuda</span></li></a>
                        <a href="../../../controller/logout.php"><li><i class="fas fa-sign-out-alt"></i><span>Cerrar sesión</span></li></a>
                    </ul>
                </nav>
            </div>

            <ul class="nav-links">
                <li><a href="index_paseador.php"><i class="fas fa-house"></i><span>Inicio</span></a></li>
                <li class="active"><a href="#"><i class="fas fa-route"></i><span>Mis Paseos</span></a></li>
                <li><div class="nav-sep"></div></li>
                <li><a href="Chat_paseador.php"><i class="far fa-comment-alt"></i><span>Chat</span></a></li>
                <li><a href="mapa_paseador.php"><i class="fas fa-map-marker-alt"></i><span>Mapa</span></a></li>
                <li><div class="nav-sep"></div></li>
                <li><a href="soporte_paseador.php"><i class="fas fa-headset"></i><span>Soporte</span></a></li>
                <li><a href="usuario_paseador.php"><i class="fas fa-user"></i><span>Mi Perfil</span></a></li>
            </ul>
        </nav>

        <main class="main-content paseos-container">

            <div class="page-header">
                <div>
                    <h1 class="page-title">Gestión de Paseos</h1>
                    <p class="page-sub">Controla tus servicios en curso y revisa tu histórico de cumplimiento.</p>
                </div>
            </div>

            <div class="tabs-wrapper">
                <button class="tab-btn active" onclick="switchTab(event, 'tab-hoy')">
                    <i class="fas fa-calendar-day"></i> Paseos de Hoy
                </button>
                <button class="tab-btn" onclick="switchTab(event, 'tab-historial')">
                    <i class="fas fa-history"></i> Historial Completo
                </button>
            </div>

            <!-- Aviso cuando aún no se ha creado la ruta de hoy (ver Inicio > Empezar paseos) -->
            <div id="sinRutaBanner" class="sin-ruta-banner" style="display:none">
                <i class="fas fa-circle-info"></i>
                <span>Aún no has iniciado tus paseos de hoy. Ve a <a href="index_paseador.php">Inicio</a> y presiona "Empezar paseos" para trazar la ruta del día.</span>
            </div>

            <div id="tab-hoy" class="tab-content active">
                <div class="walks-grid" id="walksGridHoy">
                    <div class="no-walks-msg"><i class="fas fa-spinner fa-spin"></i> Cargando tus paseos de hoy...</div>
                </div>
            </div>

            <div id="tab-historial" class="tab-content">
                <div class="history-table-card">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Mascota</th>
                                    <th>Dueño</th>
                                    <th>Dirección</th>
                                    <th>Duración</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody id="historialBody">
                                <tr><td colspan="6" class="no-walks-msg"><i class="fas fa-spinner fa-spin"></i> Cargando historial...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <script src="../../js/paseador/paseos_paseador.js?v=1"></script>
    <script src="../../js/paseador/avisos_paseos.js?v=<?php echo @filemtime(__DIR__ . '/../../js/paseador/avisos_paseos.js'); ?>"></script>
</body>
</html>
