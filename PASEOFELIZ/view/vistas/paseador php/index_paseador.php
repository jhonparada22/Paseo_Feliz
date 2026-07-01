<?php include_once '../../../controller/control_acceso.php'; ?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paseador - Paseo Feliz</title>
    <link rel="icon" href="../../assets/images/logo.png" type="image/png">

    <!-- Estilos globales -->
    <link rel="stylesheet" href="../../css/principal_css/global.css">
    <link rel="stylesheet" href="../../css/principal_css/paseos.css">
    <link rel="stylesheet" href="../../css/paseador/paseador_dashboard.css">
    <link rel="stylesheet" href="../../css/paseador/sidebar_paseador.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
</head>

<body class="paseador-page">

    <div id="contenedor_general" class="app-container">

        <!-- BARRA LATERAL -->
        <nav class="sidebar">
            <div class="menu-hamburguesa-container">
                <div class="profile-circle" id="btn-menu">
                    <i class="fas fa-bars"></i>
                </div>
                <nav class="menu-desplegable" id="menu-latente">
                    <ul>
                        <a href="./sub_menu/conocenos.php">
                            <li><i class="fas fa-camera"></i><span>Conócenos</span></li>
                        </a>
                        <a href="./sub_menu/direccion_oficial.php">
                            <li><i class="fas fa-book-open"></i><span>Dirección oficial</span></li>
                        </a>
                        <a href="./sub_menu/centro_de_ayuda.php">
                            <li><i class="fas fa-sliders-h"></i><span>Centro de ayuda</span></li>
                        </a>
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
                <li class="active"><a href="#"><i class="fas fa-house"></i><span>Inicio</span></a></li>
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

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-content dashboard-content">

            <div class="page-header">
                <div>
                    <div class="greeting">¡Hola de nuevo, <?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Paseador'); ?>! 🐾</div>
                    <div class="sub">Organiza tu semana y revisa los datos de los perritos asignados.</div>
                </div>
                <div class="date-pill">
                    <i class="fas fa-calendar-day"></i>
                    <span id="dateLabel"></span>
                </div>
            </div>

            <!-- Tarjetas de Rendimiento Operativo -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="fas fa-dog"></i></div>
                    <div class="stat-info">
                        <div class="label">Paseos de Hoy</div>
                        <div class="value" id="countHoy">3</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-paw"></i></div>
                    <div class="stat-info">
                        <div class="label">Total Perros Asignados</div>
                        <div class="value">8</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange"><i class="fas fa-star"></i></div>
                    <div class="stat-info">
                        <div class="label">Calificación Promedio</div>
                        <div class="value">4.9 <span class="star-small">★</span></div>
                    </div>
                </div>
            </div>

            <!-- FILA CENTRAL: CALENDARIO SEMANAL + DETALLE -->
            <div class="mid-row">

                <!-- Planificador Semanal Interactivo -->
                <div class="card grid-main-calendar">
                    <div class="card-head">
                        <span class="title"><i class="fas fa-calendar-week"></i> Cronograma de la Semana</span>
                    </div>
                    <div class="calendar-week-grid">
                        <div class="day-pill active" onclick="cambiarDia('Lunes', this)">
                            <span class="day-name">Lun</span>
                            <span class="day-num">1</span>
                            <span class="badge-count">3 Perros</span>
                        </div>
                        <div class="day-pill" onclick="cambiarDia('Martes', this)">
                            <span class="day-name">Mar</span>
                            <span class="day-num">2</span>
                            <span class="badge-count">2 Perros</span>
                        </div>
                        <div class="day-pill" onclick="cambiarDia('Miércoles', this)">
                            <span class="day-name">Mié</span>
                            <span class="day-num">3</span>
                            <span class="badge-count">4 Perros</span>
                        </div>
                        <div class="day-pill" onclick="cambiarDia('Jueves', this)">
                            <span class="day-name">Jue</span>
                            <span class="day-num">4</span>
                            <span class="badge-count">2 Perros</span>
                        </div>
                        <div class="day-pill" onclick="cambiarDia('Viernes', this)">
                            <span class="day-name">Vie</span>
                            <span class="day-num">5</span>
                            <span class="badge-count">3 Perros</span>
                        </div>
                        <div class="day-pill" onclick="cambiarDia('Sábado', this)">
                            <span class="day-name">Sáb</span>
                            <span class="day-num">6</span>
                            <span class="badge-count">2 Perros</span>
                        </div>
                        <div class="day-pill weekend" onclick="cambiarDia('Domingo', this)">
                            <span class="day-name">Dom</span>
                            <span class="day-num">7</span>
                            <span class="badge-count">Descanso</span>
                        </div>
                    </div>
                </div>

                <!-- Lista Dinámica de Perros -->
                <div class="card">
                    <div class="card-head"
                        style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                        <span class="title">
                            <i class="fas fa-list-check"></i> Perros a Pasear (<span id="selectedDayLabel">Lunes</span>)
                        </span>
                        <button id="btnEmpezarPaseos" class="btn-action-start" onclick="empezarTodosLosPaseos()"
                            style="background-color: #22c55e;">
                            <i class="fas fa-play"></i> Empezar paseos
                        </button>
                    </div>
                    <div class="agenda-list" id="agendaContainer">
                    </div>
                </div>

            </div>

            <!-- Fila Inferior: Notas de comportamiento -->
            <div class="bottom-row">
                <div class="card alert-card">
                    <div class="card-head">
                        <span class="title"><i class="fas fa-triangle-exclamation"></i> Notas Importantes de
                            Comportamiento</span>
                    </div>
                    <div class="alerts-container">
                        <div class="alert-item hazard">
                            <i class="fas fa-shield-dog"></i>
                            <div>
                                <strong>Rocky (Golden Retriever):</strong> Tiende a jalar mucho de la correa si ve
                                gatos. Llevar arnés de control.
                            </div>
                        </div>
                        <div class="alert-item medical">
                            <i class="fas fa-capsules"></i>
                            <div>
                                <strong>Luna (Pug):</strong> No cansar demasiado debido al calor de Cúcuta. Llevar
                                hidratación constante y hacer pausas en la sombra.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <script src="../../js/paseador/paseador.js"></script>

</body>

</html>