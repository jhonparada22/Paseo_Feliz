<?php include_once '../../../controller/control_acceso.php'; ?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Paseo Feliz – Panel Admin</title>
    <link rel="icon" href="../../assets/images/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../css/admin/admin.css?v=<?php echo @filemtime(__DIR__ . '/../../css/admin/admin.css'); ?>">
    <link rel="stylesheet" href="../../css/admin/sidebar_admin.css?v=<?php echo @filemtime(__DIR__ . '/../../css/admin/sidebar_admin.css'); ?>">
    <link rel="stylesheet" href="../../css/admin/activity_center.css?v=<?php echo @filemtime(__DIR__ . '/../../css/admin/activity_center.css'); ?>">

    <!-- PWA: instalable para el admin -->
    <link rel="manifest" href="../../apk/manifest_admin.json">
    <meta name="theme-color" content="#7f1d34">
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('../../apk/sw.js', { scope: '../../' }).catch(function () {});
        }
    </script>
</head>

<body>

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
                <li><a href="usuarios_admin.php"><i class="fas fa-users"></i><span>Usuarios</span></a></li>
                <li><a href="paseadores_admin.php"><i class="fas fa-person-walking"></i><span>Paseadores</span></a>
                </li>
                <li>
                    <div class="nav-sep"></div>
                </li>
                <li><a href="paseos_admin.php"><i class="fas fa-route"></i><span>Paseos</span></a></li>
                <li><a href="adiestramiento_admin.php"><i class="fas fa-graduation-cap"></i><span>Adiestramiento</span></a></li>
                <li><a href="hospedaje_admin.php"><i class="fas fa-house"></i><span>Hospedaje</span></a></li>
                <li><a href="pagos_admin.php"><i class="fas fa-credit-card"></i><span>Pagos</span></a></li>
                <li>
                    <div class="nav-sep"></div>
                </li>
                <li><a href="Chat_admin.php"><i class="far fa-comment-alt"></i><span>Chat</span></a></li>
                <li><a href="mapa_admin.php"><i class="fas fa-map-location-dot"></i><span>Mapa</span></a></li>
                <li><a href="adopcion_admin.php"><i class="fas fa-bone"></i><span>Adopción</span></a></li>
                <li>
                    <div class="nav-sep"></div>
                </li>
                <li><a href="usuario_admin.php"><i class="fas fa-user"></i><span>Usuario</span></a></li>
            </ul>
        </nav>

        <!-- ══ MAIN CONTENT ════════════════════════════════════════ -->
        <div class="main-content">

            <!-- PAGE AREA -->
            <main class="page-area">

                <!-- Header -->
                <div class="page-header">
                    <div>
                        <div class="greeting">¡Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Admin'); ?>! 👋</div>
                        <div class="sub">Aquí tienes un resumen general de Paseo Feliz.</div>
                    </div>
                    <div class="date-pill">
                        <i class="fas fa-calendar-alt"></i>
                        <span id="dateLabel">–</span>
                        <i class="fas fa-chevron-down" style="font-size:.62rem;color:var(--text-light)"></i>
                    </div>
                </div>

                <!-- Stat Cards -->
                <div class="stats-row">
                    <div class="stat-card">
                        <div class="stat-icon si-blue"><i class="fas fa-route"></i></div>
                        <div class="stat-info">
                            <div class="s-label">Paseos Activos</div>
                            <div class="s-value">24</div>
                            <div class="s-delta up"><i class="fas fa-arrow-up"></i> +12% desde ayer</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon si-green"><i class="fas fa-users"></i></div>
                        <div class="stat-info">
                            <div class="s-label">Usuarios Registrados</div>
                            <div class="s-value">1,248</div>
                            <div class="s-delta up"><i class="fas fa-arrow-up"></i> +18% semana pasada</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon si-orange"><i class="fas fa-person-walking"></i></div>
                        <div class="stat-info">
                            <div class="s-label">Paseadores Disponibles</div>
                            <div class="s-value">86</div>
                            <div class="s-delta up"><i class="fas fa-arrow-up"></i> +7% desde ayer</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon si-indigo"><i class="fas fa-dollar-sign"></i></div>
                        <div class="stat-info">
                            <div class="s-label">Ingresos Totales</div>
                            <div class="s-value">$12,540</div>
                            <div class="s-delta up"><i class="fas fa-arrow-up"></i> +15% semana pasada</div>
                        </div>
                    </div>
                </div>

                <!-- Centro de Actividad -->
                <div class="ac-layout">

                    <!-- Timeline (columna izquierda) -->
                    <div class="card ac-card">
                        <div class="ac-head">
                            <span class="c-title"><i class="fas fa-satellite-dish" style="color:var(--primary-blue);margin-right:6px"></i>Centro de Actividad</span>
                            <span class="ac-live"><span class="dot"></span> En vivo</span>
                        </div>
                        <div class="ac-tabs">
                            <button class="ac-tab active" data-serv="paseos"><i class="fas fa-paw"></i> Paseos <span class="ac-count">0</span></button>
                            <button class="ac-tab" data-serv="adiestramiento"><i class="fas fa-graduation-cap"></i> Adiestramiento <span class="ac-count">0</span></button>
                            <button class="ac-tab" data-serv="hospedaje"><i class="fas fa-house"></i> Hospedaje <span class="ac-count">0</span></button>
                            <button class="ac-tab" data-serv="adopcion"><i class="fas fa-paw"></i> Adopción <span class="ac-count">0</span></button>
                        </div>
                        <div class="ac-toolbar">
                            <div class="ac-search">
                                <i class="fas fa-magnifying-glass"></i>
                                <input id="ac-buscar" type="text" placeholder="Buscar cliente, mascota, paseador, #pedido...">
                            </div>
                            <select class="ac-filtro" id="ac-filtro">
                                <option value="todos">Todos</option>
                                <option value="hoy">Hoy</option>
                                <option value="24h">Últimas 24 h</option>
                                <option value="7d">Últimos 7 días</option>
                                <option value="pendientes">Pendientes</option>
                                <option value="urgentes">Urgentes</option>
                                <option value="cancelados">Cancelados</option>
                                <option value="completados">Completados</option>
                            </select>
                        </div>
                        <div class="ac-timeline" id="ac-timeline"></div>
                        <button class="ac-mas" id="ac-mas" style="display:none">Cargar más</button>
                    </div>

                    <!-- Columna derecha -->
                    <div class="ac-right">

                    <!-- Necesitan atención -->
                    <div class="card ac-atencion">
                        <div class="card-head">
                            <span class="c-title"><i class="fas fa-triangle-exclamation" style="color:#f97316;margin-right:6px"></i>Necesitan atención <span class="ac-at-badge" id="ac-at-badge" style="display:none">0</span></span>
                        </div>
                        <div class="card-body" id="ac-at-list">
                            <div class="ac-at-vacio">Cargando…</div>
                        </div>
                    </div>

                    <!-- Donut -->
                    <div class="card">
                        <div class="card-head"><span class="c-title">Paseos por Estado</span></div>
                        <div class="donut-wrap">
                            <div class="donut-pos">
                                <canvas id="donutChart" width="150" height="150"></canvas>
                                <div class="donut-center">
                                    <div class="dp">100%</div>
                                    <div class="dl">Total</div>
                                </div>
                            </div>
                            <div class="legend">
                                <div class="legend-item">
                                    <div class="l-label">
                                        <div class="l-dot" style="background:#22c55e"></div>Completados
                                    </div>
                                    <div class="l-pct">58%</div>
                                </div>
                                <div class="legend-item">
                                    <div class="l-label">
                                        <div class="l-dot" style="background:#3E72A6"></div>En Proceso
                                    </div>
                                    <div class="l-pct">25%</div>
                                </div>
                                <div class="legend-item">
                                    <div class="l-label">
                                        <div class="l-dot" style="background:#f97316"></div>Programados
                                    </div>
                                    <div class="l-pct">12%</div>
                                </div>
                                <div class="legend-item">
                                    <div class="l-label">
                                        <div class="l-dot" style="background:#ef4444"></div>Cancelados
                                    </div>
                                    <div class="l-pct">5%</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Calendario + Reportes -->
                    <div style="display:flex;flex-direction:column;gap:14px">
                        <div class="card">
                            <div class="card-head">
                                <span class="c-title"><i class="fas fa-calendar"
                                        style="color:var(--primary-blue);margin-right:6px"></i>Calendario</span>
                                <button style="color:var(--primary-blue);font-size:.72rem;font-weight:700"><i
                                        class="fas fa-chevron-right"></i></button>
                            </div>
                            <div class="card-body">
                                <div class="cal-nav">
                                    <button id="calPrev"><i class="fas fa-chevron-left"></i></button>
                                    <span class="cal-month" id="calMonth">–</span>
                                    <button id="calNext"><i class="fas fa-chevron-right"></i></button>
                                </div>
                                <div class="cal-grid" id="calGrid"></div>
                            </div>
                        </div>

                        <!-- Tendencia de paseos (reubicada) -->
                        <div class="card">
                            <div class="card-head">
                                <span class="c-title">Tendencia de paseos</span>
                                <select class="chart-select">
                                    <option>Últimos 7 días</option>
                                    <option>Últimos 30 días</option>
                                    <option>Este mes</option>
                                </select>
                            </div>
                            <div class="chart-wrap" style="height:150px">
                                <canvas id="lineChart"></canvas>
                            </div>
                        </div>
                    </div><!-- /columna calendario + tendencia -->

                    </div><!-- /ac-right -->
                </div><!-- /ac-layout -->

                <!-- Modal: resolver solicitud de cancelación -->
                <div class="ac-modal-ov" id="ac-modal">
                    <div class="ac-modal">
                        <h3 id="acm-titulo">Resolver cancelación</h3>
                        <div class="sub" id="acm-sub"></div>
                        <textarea id="acm-nota" placeholder="Nota para el paseador (opcional)"></textarea>
                        <div id="acm-error" style="display:none;color:#ef4444;font-size:.78rem;margin-top:6px"></div>
                        <div class="ac-modal-acts">
                            <button class="acm-cancel" id="acm-cancelar">Volver</button>
                            <button class="acm-ok" id="acm-confirmar">Confirmar</button>
                        </div>
                    </div>
                </div>

            </main>
        </div><!-- /main-content -->
    </div><!-- /app-container -->

    <!-- ══ SCRIPTS ════════════════════════════════════════════════ -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <script src="../../js/admin/dashboard_admin.js?v=<?php echo @filemtime(__DIR__ . '/../../js/admin/dashboard_admin.js'); ?>"></script>
    <script src="../../js/admin/activity_center.js?v=<?php echo @filemtime(__DIR__ . '/../../js/admin/activity_center.js'); ?>"></script>

</body>

</html>