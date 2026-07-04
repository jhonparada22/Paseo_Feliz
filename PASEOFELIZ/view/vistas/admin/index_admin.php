<?php include_once '../../../controller/control_acceso.php'; ?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Paseo Feliz – Panel Admin</title>
    <link rel="icon" href="../../assets/images/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../css/admin/admin.css">
    <link rel="stylesheet" href="../../css/admin/sidebar_admin.css">
    
</head>

<body>

    <div class="app-container">

        <!-- ══ SIDEBAR ══════════════════════════════════════════════ -->
<nav class="sidebar">
            <div class="menu-hamburguesa-container">
                <div class="profile-circle" id="btn-menu"><i class="fas fa-bars"></i></div>
      <nav class="menu-desplegable" id="menu-latente">
        <ul>
          <a href="../../pagina_principal/sub_menu/conocenos.php"><li><i class="fas fa-camera"></i><span>Conócenos</span></li></a>
          <a href="../../pagina_principal/sub_menu/direccion_oficial.php"><li><i class="fas fa-book-open"></i><span>Dirección oficial</span></li></a>
          <a href="../../pagina_principal/sub_menu/centro_de_ayuda.php"><li><i class="fas fa-sliders-h"></i><span>Centro de ayuda</span></li></a>
          <a href="../../pagina_principal/sub_menu/configuracion.php"><li><i class="fas fa-gear"></i><span>Configuración</span></li></a>
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
                        <div class="stat-icon si-blue"><i class="fas fa-person-walking-luggage"></i></div>
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

                <!-- Mid Row -->
                <div class="mid-row">

                    <!-- Línea -->
                    <div class="card">
                        <div class="card-head">
                            <span class="c-title">Resumen de Paseos</span>
                            <select class="chart-select">
                                <option>Últimos 7 días</option>
                                <option>Últimos 30 días</option>
                                <option>Este mes</option>
                            </select>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="lineChart"></canvas>
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

                        <div class="card">
                            <div class="card-head">
                                <span class="c-title">Reportes Recientes</span>
                            </div>
                            <div class="card-body" style="padding-top:4px;padding-bottom:4px">
                                <a href="paseos_admin.php" class="report-item" data-tipo="paseo" style="text-decoration:none;color:inherit">
                                    <div class="r-icon ri-green"><i class="fas fa-route"></i></div>
                                    <div class="r-info">
                                        <div class="r-name">Paseos completados</div>
                                        <div class="r-date" id="r-date-paseo">–</div>
                                    </div>
                                    <i class="fas fa-chevron-right"
                                        style="font-size:.68rem;color:var(--text-light)"></i>
                                </a>
                                <a href="pagos_admin.php" class="report-item" data-tipo="ingreso" style="text-decoration:none;color:inherit">
                                    <div class="r-icon ri-blue"><i class="fas fa-dollar-sign"></i></div>
                                    <div class="r-info">
                                        <div class="r-name">Ingresos semanales</div>
                                        <div class="r-date" id="r-date-ingreso">–</div>
                                    </div>
                                    <i class="fas fa-chevron-right"
                                        style="font-size:.68rem;color:var(--text-light)"></i>
                                </a>
                                <a href="usuarios_admin.php" class="report-item" data-tipo="usuario" style="text-decoration:none;color:inherit">
                                    <div class="r-icon ri-red"><i class="fas fa-user-plus"></i></div>
                                    <div class="r-info">
                                        <div class="r-name">Usuarios nuevos</div>
                                        <div class="r-date" id="r-date-usuario">–</div>
                                    </div>
                                    <i class="fas fa-chevron-right"
                                        style="font-size:.68rem;color:var(--text-light)"></i>
                                </a>
                                <a href="paseadores_admin.php" class="report-item" data-tipo="paseador" style="text-decoration:none;color:inherit">
                                    <div class="r-icon ri-orange"><i class="fas fa-person-walking"></i></div>
                                    <div class="r-info">
                                        <div class="r-name">Paseadores activos</div>
                                        <div class="r-date" id="r-date-paseador">–</div>
                                    </div>
                                    <i class="fas fa-chevron-right"
                                        style="font-size:.68rem;color:var(--text-light)"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom Row -->
                <div class="bottom-row">

                    <!-- Tabla paseos recientes -->
                    <div class="card">
                        <div class="card-head">
                            <span class="c-title">Paseos Recientes</span>
                            <a href="paseos_admin.php" class="ver-todos">Ver todos</a>
                        </div>
                        <div class="table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Usuario</th>
                                        <th>Mascota</th>
                                        <th>Paseador</th>
                                        <th>Fecha</th>
                                        <th>Estado</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody id="tableBody"></tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Widget estado -->
                    <div class="status-widget">
                        <div class="sw-title">¡Todo en orden! 🐾</div>
                        <div class="sw-sub">Tu plataforma está funcionando correctamente.</div>
                        <button class="sw-btn">Ver estadísticas completas</button>
                        <img class="dog-img" src="https://images.dog.ceo/breeds/retriever-golden/n02099601_3004.jpg"
                            alt="perro feliz" onerror="this.style.display='none'" />
                    </div>

                </div>

            </main>
        </div><!-- /main-content -->
    </div><!-- /app-container -->

    <!-- ══ SCRIPTS ════════════════════════════════════════════════ -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <script src="../../js/admin/dashboard_admin.js?v=1"></script>

</body>

</html>