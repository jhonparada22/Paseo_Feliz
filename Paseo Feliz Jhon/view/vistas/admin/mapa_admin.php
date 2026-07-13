<?php include_once '../../../controller/control_acceso.php'; ?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Paseo Feliz – Mapa Admin</title>
    <link rel="icon" href="../../assets/images/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="../../css/admin/mapa_admin.css?v=<?php echo @filemtime(__DIR__ . '/../../css/admin/mapa_admin.css'); ?>" />
    <!-- Sidebar unificado admin — debe cargar DESPUÉS de mapa_admin.css -->
    <link rel="stylesheet" href="../../css/admin/sidebar_admin.css?v=<?php echo @filemtime(__DIR__ . '/../../css/admin/sidebar_admin.css'); ?>" />

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
                <li><a href="index_admin.php"><i class="fas fa-house"></i><span>Inicio</span></a></li>
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
                <li class="active"><a href="#"><i class="fas fa-map-location-dot"></i><span>Mapa</span></a></li>
                <li><a href="adopcion_admin.php"><i class="fas fa-bone"></i><span>Adopción</span></a></li>
                <li>
                    <div class="nav-sep"></div>
                </li>
                <li><a href="usuario_admin.php"><i class="fas fa-user"></i><span>Usuario</span></a></li>
            </ul>
        </nav>

        <!-- ══ MAIN ═════════════════════════════════════════════════ -->
        <div class="main-content">

            <!-- TOPBAR -->
            <header class="topbar">
                <i class="fas fa-map-location-dot" style="color:var(--primary);font-size:1.1rem"></i>
                <h1>Mapa en tiempo real</h1>
                <span class="sub">— Vista Administrador</span>
                <div class="topbar-right">
                    <div class="tb-profile">
                        <div class="tb-av"><?php echo strtoupper(substr($_SESSION['usuario_nombre'] ?? 'A', 0, 1)); ?></div>
                        <div>
                            <div class="tb-name"><?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Admin'); ?></div>
                            <div class="tb-role">Administrador</div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="map-area">

                <!-- ── LEFT PANEL ──────────────── -->
                <div class="left-panel">
                    <div class="panel-tabs">
                        <div class="panel-tab active" data-tab="rutas">🗺️ Asignar ruta</div>
                        <div class="panel-tab" data-tab="paseadores">📍 Paseadores</div>
                    </div>

                    <!-- TAB: RUTAS -->
                    <div class="panel-body" id="tab-rutas">

                        <!-- Seleccionar paseador -->
                        <div class="section-label" style="margin-top:4px">Paseador asignado</div>
                        <div class="form-field">
                            <select id="selectPaseador">
                                <option value="">— Seleccionar paseador —</option>
                            </select>
                        </div>

                        <!-- Puntos de la ruta -->
                        <div class="section-label">Puntos de la ruta</div>
                        <div id="routeSteps"></div>

                        <!-- Botón agregar punto -->
                        <button class="add-point-btn" id="btnAddPoint">
                            <i class="fas fa-plus-circle"></i>
                            Agregar punto (clic en el mapa o busca una dirección)
                        </button>

                        <!-- Buscar dirección -->
                        <div class="form-field">
                            <label>Buscar dirección en Cúcuta</label>
                            <input type="text" id="addrSearch" placeholder="Ej: Calle 7 #0e-94, Motilones" />
                        </div>
                        <div id="addrResults"
                            style="display:none;border:1.5px solid var(--border);border-radius:9px;overflow:hidden;margin-top:-6px;margin-bottom:10px">
                        </div>

                        <!-- Config ruta -->
                        <div class="section-label">Configuración</div>
                        <div class="form-field">
                            <label>Fecha del paseo</label>
                            <input type="date" id="routeDate" />
                        </div>
                        <div class="form-field">
                            <label>Hora de inicio</label>
                            <input type="time" id="routeTime" value="08:00" />
                        </div>

                        <!-- Acciones -->
                        <button class="btn-primary" id="btnAsignar">
                            <i class="fas fa-route"></i> Asignar ruta al paseador
                        </button>
                        <button class="btn-danger" id="btnLimpiar">
                            <i class="fas fa-trash"></i> Limpiar ruta
                        </button>

                        <!-- Rutas activas hoy -->
                        <div class="section-label" style="margin-top:16px">Rutas activas hoy</div>
                        <div id="activeRoutesList"></div>

                    </div>

                    <!-- TAB: PASEADORES -->
                    <div class="panel-body" id="tab-paseadores" style="display:none">
                        <div class="section-label" style="margin-top:4px">Estado en tiempo real</div>
                        <div id="paseadoresList"></div>
                    </div>
                </div>

                <!-- ── MAPA ────────────────────── -->
                <div class="map-wrap">
                    <div id="map"></div>

                    <!-- Controls overlay -->
                    <div class="map-controls">
                        <span class="mc-label">Modo:</span>
                        <button class="mc-btn active-mode" id="modeView" onclick="setMode('view')">
                            <i class="fas fa-eye"></i> Ver
                        </button>
                        <button class="mc-btn" id="modeRoute" onclick="setMode('route')">
                            <i class="fas fa-crosshairs"></i> Trazar ruta
                        </button>
                        <div class="mc-sep"></div>
                        <button class="mc-btn" onclick="centerCucuta()">
                            <i class="fas fa-location-crosshairs"></i> Cúcuta
                        </button>
                        <button class="mc-btn active-mode" id="btnClientes" onclick="toggleClientes()">
                            <i class="fas fa-house-user"></i> Clientes
                        </button>
                    </div>

                    <!-- LIVE badge -->
                    <div class="live-badge">
                        <div class="live-dot"></div>
                        GPS EN VIVO · actualiza 5s
                    </div>

                    <!-- Legend -->
                    <div class="map-legend">
                        <div class="leg-item">
                            <div class="leg-dot" style="background:#25D366"></div>Activo
                        </div>
                        <div class="leg-item">
                            <div class="leg-dot" style="background:#f97316"></div>En ruta
                        </div>
                        <div class="leg-item">
                            <div class="leg-dot" style="background:#ef4444"></div>Pausa
                        </div>
                        <div class="leg-item">
                            <div class="leg-dot leg-cuadro" style="background:#7c3aed"></div>Cliente
                        </div>
                        <div class="leg-item">
                            <div class="leg-dot" style="background:#3E72A6"></div>Punto A
                        </div>
                        <div class="leg-item">
                            <div class="leg-dot" style="background:#8b5cf6"></div>Punto C
                        </div>
                    </div>
                </div>

                <!-- ── RIGHT PANEL (detalle paseador) ── -->
                <div class="right-panel" id="rightPanel">
                    <div class="rp-head">
                        <div class="rp-title" id="rp-name">Selecciona un paseador</div>
                        <div class="rp-sub" id="rp-sub">para ver su información</div>
                        <button class="rp-close" id="rpClose" title="Cerrar" aria-label="Cerrar panel">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="rp-body" id="rp-body">
                        <div style="text-align:center;padding:24px;color:var(--muted)">
                            <i class="fas fa-person-walking"
                                style="font-size:2rem;color:#e2e8f0;display:block;margin-bottom:8px"></i>
                            <p style="font-size:.8rem">Haz clic en un marcador del mapa o en la lista de paseadores</p>
                        </div>
                    </div>
                </div>

            </div><!-- /map-area -->
        </div><!-- /main-content -->
    </div><!-- /app-container -->

    <!-- MODAL CONFIRMAR ASIGNACIÓN -->
    <div class="modal-overlay" id="assignModal">
        <div class="modal">
            <div class="modal-head">✅ Confirmar asignación de ruta</div>
            <div class="modal-body">
                <p>¿Deseas asignar la siguiente ruta al paseador seleccionado?</p>
                <div class="assign-summary" id="assignSummary"></div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" id="cancelAssign">Cancelar</button>
                <button class="btn-confirm" id="confirmAssign"><i class="fas fa-check"></i> Confirmar</button>
            </div>
        </div>
    </div>

    <!-- TOAST -->
    <div class="toast" id="toast"><i class="fas fa-check-circle"></i><span id="toastMsg"></span></div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="../../js/admin/mapa_admin.js?v=<?php echo @filemtime(__DIR__ . '/../../js/admin/mapa_admin.js'); ?>"></script>

</body>

</html>
