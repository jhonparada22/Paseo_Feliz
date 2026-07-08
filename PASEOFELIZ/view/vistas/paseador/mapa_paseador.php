<?php include_once '../../../controller/control_acceso.php'; ?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Paseo Feliz – Mapa Paseador</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="../../css/paseador/mapa_paseador.css?v=<?php echo @filemtime(__DIR__ . '/../../css/paseador/mapa_paseador.css'); ?>">
    <!-- Sidebar unificado paseador (verde) — requiere id contenedor_general en el shell -->
    <link rel="stylesheet" href="../../css/paseador/sidebar_paseador.css?v=<?php echo @filemtime(__DIR__ . '/../../css/paseador/sidebar_paseador.css'); ?>">
</head>

<body>

    <div id="contenedor_general" class="app-shell">

        <!-- ══ SIDEBAR ══ -->
        <nav class="sidebar">
            <div class="menu-hamburguesa-container">
                <div class="profile-circle" id="btn-menu">
                    <i class="fas fa-bars"></i>
                </div>
                <nav class="menu-desplegable" id="menu-latente">
                    <ul>
                        <a href="../../pagina_principal/sub_menu/conocenos.php">
                            <li><i class="fas fa-camera"></i><span>Conócenos</span></li>
                        </a>
                        <a href="../../pagina_principal/sub_menu/direccion_oficial.php">
                            <li><i class="fas fa-book-open"></i><span>Dirección oficial</span></li>
                        </a>
                        <a href="../../pagina_principal/sub_menu/centro_de_ayuda.php">
                            <li><i class="fas fa-sliders-h"></i><span>Centro de ayuda</span></li>
                        </a>
                        <a href="../../../controller/logout.php">
                            <li><i class="fas fa-sign-out-alt"></i><span>Cerrar sesión</span></li>
                        </a>
                    </ul>
                </nav>
            </div>

            <ul class="nav-links">
                <li><a href="index_paseador.php"><i class="fas fa-house"></i><span>Inicio</span></a></li>
                <li><a href="paseos_paseador.php"><i class="fas fa-route"></i><span>Mis Paseos</span></a></li>
                <li>
                    <div class="nav-sep"></div>
                </li>
                <li><a href="Chat_paseador.php"><i class="far fa-comment-alt"></i><span>Chat</span></a></li>
                <li class="active"><a href="mapa_paseador.php"><i
                            class="fas fa-map-marker-alt"></i><span>Mapa</span></a></li>
                <li>
                    <div class="nav-sep"></div>
                </li>
                <li><a href="soporte_paseador.php"><i class="fas fa-headset"></i><span>Soporte</span></a></li>
                <li><a href="usuario_paseador.php"><i class="fas fa-user"></i><span>Mi Perfil</span></a></li>
            </ul>

        </nav>

        <!-- ══ PANEL DE INFO ══ -->
        <div class="info-panel">

            <!-- Cabecera -->
            <div class="panel-header">
                <div class="ph-row">
                    <div class="ph-logo">🐾</div>
                    <div class="ph-title">
                        <div class="pt-name">Vista Paseador</div>
                        <div class="pt-sub" id="tb-paseador-name">Cargando... · Cúcuta</div>
                    </div>
                    <div class="ph-actions">
                        <button class="ph-btn" title="Chat" onclick="window.location.href='Chat_paseador.php'">
                            <i class="fas fa-comment-alt"></i>
                        </button>
                        <button class="ph-btn" title="Alertas" onclick="showNotif('Sin alertas nuevas','info')">
                            <i class="fas fa-bell"></i>
                        </button>
                        <button class="ph-btn" title="Salir" onclick="window.location.href='../../../controller/logout.php'">
                            <i class="fas fa-sign-out-alt"></i>
                        </button>
                        <button class="ph-btn" title="Plegar / expandir panel" id="btnPlegarHeader" onclick="togglePanelHeader()">
                            <i class="fas fa-chevron-up" id="iconPlegar"></i>
                        </button>
                    </div>
                </div>

                <!-- GPS Chip -->
                <div class="gps-chip">
                    <div class="gps-dot on" id="gpsDot"></div>
                    <span class="gps-lbl" id="gpsLabel">GPS Activo</span>
                    <span class="gps-coords" id="gpsCoords">7.89390, -72.50780</span>
                </div>

                <!-- Botón iniciar paseo -->
                <button class="btn-iniciar start" id="btnPaseo" onclick="togglePaseo()">
                    <i class="fas fa-play" id="btnPaseoIcon"></i>
                    <span id="btnPaseoLabel">Iniciar paseo</span>
                </button>
            </div>

            <!-- Stats en tiempo real -->
            <div class="stats-row-panel" id="statsPanel" style="display:none">
                <div class="stat-pill-panel">
                    <div class="spp-icon"><i class="fas fa-road"></i></div>
                    <div class="spp-val" id="statDist">0.0 km</div>
                    <div class="spp-lbl">Recorrido</div>
                </div>
                <div class="stat-pill-panel">
                    <div class="spp-icon"><i class="fas fa-clock"></i></div>
                    <div class="spp-val" id="statTime">00:00</div>
                    <div class="spp-lbl">Tiempo</div>
                </div>
                <div class="stat-pill-panel">
                    <div class="spp-icon"><i class="fas fa-flag-checkered"></i></div>
                    <div class="spp-val" id="statParadas">0/5</div>
                    <div class="spp-lbl">Paradas</div>
                </div>
            </div>

            <!-- Barra progreso -->
            <div class="progress-block">
                <div class="pb-info">
                    <span id="progressLabel">Parada 0 de 5</span>
                    <span id="progressPct">0%</span>
                </div>
                <div class="pb-track">
                    <div class="pb-fill" id="progressFill" style="width:0%"></div>
                </div>
            </div>

            <!-- SCROLL: paseos de hoy + paradas + resumen -->
            <div class="panel-scroll">

                <!-- ══ PASEOS DE HOY: Individual / Grupal ══ -->
                <div class="sec-lbl" style="margin-top:2px">
                    Paseos de hoy — <span id="fechaPaseosHoy">...</span>
                </div>

                <div class="seg-card" id="segIndividual">
                    <button class="seg-head" onclick="toggleSegmento('Individual')">
                        <span class="seg-titulo">🐕 Individual <span class="seg-count" id="countIndividual">0</span></span>
                        <i class="fas fa-chevron-down seg-chevron" id="chevIndividual"></i>
                    </button>
                    <div class="seg-body" id="bodyIndividual">
                        <div class="seg-vacio">Sin paseos individuales hoy.</div>
                    </div>
                </div>

                <div class="seg-card" id="segGrupal">
                    <button class="seg-head" onclick="toggleSegmento('Grupal')">
                        <span class="seg-titulo">🐾 Grupal <span class="seg-count" id="countGrupal">0</span></span>
                        <i class="fas fa-chevron-down seg-chevron" id="chevGrupal"></i>
                    </button>
                    <div class="seg-body" id="bodyGrupal">
                        <div class="seg-vacio">Sin paseos grupales hoy.</div>
                    </div>
                    <div class="seg-foot" id="footGrupal" style="display:none">
                        <button class="btn-grupal" id="btnIniciarGrupal" onclick="iniciarPaseoGrupal()" disabled>
                            <i class="fas fa-play"></i> Iniciar paseo grupal
                        </button>
                    </div>
                </div>

                <div style="height:1px;background:var(--border);margin:14px 0"></div>

                <div class="sec-lbl">
                    Ruta de hoy — <span id="fechaRuta"></span>
                </div>

                <!-- Lista de paradas -->
                <div id="paradasList"></div>

                <div style="height:1px;background:var(--border);margin:14px 0"></div>

                <!-- Resumen del paseo -->
                <div class="sec-lbl">Resumen del paseo</div>
                <div class="resumen-grid">
                    <div class="rg-item">
                        <div class="rg-lbl">Mascotas</div>
                        <div class="rg-val">3 🐾</div>
                    </div>
                    <div class="rg-item">
                        <div class="rg-lbl">Duración est.</div>
                        <div class="rg-val">2h 30min</div>
                    </div>
                    <div class="rg-item">
                        <div class="rg-lbl">Distancia est.</div>
                        <div class="rg-val">5.2 km</div>
                    </div>
                    <div class="rg-item">
                        <div class="rg-lbl">Inicio</div>
                        <div class="rg-val">08:00 AM</div>
                    </div>
                </div>

                <!-- Admin chat -->
                <div class="admin-card">
                    <div class="admin-av">👤</div>
                    <div class="admin-info">
                        <div class="ai-name">Administrador</div>
                        <div class="ai-sub">Disponible ahora</div>
                    </div>
                    <button class="admin-chat" onclick="window.location.href='Chat_paseador.php'">
                        <i class="fas fa-comment-alt"></i> Chat
                    </button>
                </div>

            </div><!-- /panel-scroll -->
        </div><!-- /info-panel -->

        <!-- ══ ÁREA DEL MAPA ══ -->
        <div class="map-area">
            <div id="map"></div>

            <!-- FABs -->
            <div class="fab-group">
                <button class="fab-btn" id="btnSatellite" title="Satélite / Mapa" onclick="toggleSatellite()">
                    <i class="fas fa-layer-group"></i>
                </button>
                <button class="fab-btn" title="Brújula" onclick="resetNorth()">
                    <i class="fas fa-compass"></i>
                </button>
                <button class="fab-btn" title="Zoom a ruta" onclick="fitRoute()">
                    <i class="fas fa-expand"></i>
                </button>
            </div>
            <button class="fab-locate" title="Mi ubicación" onclick="centerOnMe()">
                <i class="fas fa-location-crosshairs"></i>
            </button>
        </div>

    </div><!-- /app-shell -->

    <!-- NOTIFICACIÓN FLOTANTE -->
    <div class="float-notif" id="floatNotif">
        <i class="fas fa-check-circle" id="notifIcon"></i>
        <span id="notifMsg">Mensaje</span>
    </div>

    <!-- ══ MODAL: CANCELAR PASEO (requiere motivo) ══ -->
    <div class="modal-cancelar-overlay" id="modalCancelar">
        <div class="modal-cancelar">
            <div class="mcx-head">
                <i class="fas fa-triangle-exclamation"></i>
                Cancelar paseo de <span id="mcxMascota">—</span>
            </div>
            <div class="mcx-sub">
                Solo cancela por circunstancias que impidan el paseo.
                El cliente recibirá una notificación con el motivo.
            </div>

            <div class="mcx-motivos" id="mcxMotivos">
                <label class="mcx-motivo"><input type="radio" name="motivoCancel" value="Está lloviendo"><span>🌧️ Está lloviendo</span></label>
                <label class="mcx-motivo"><input type="radio" name="motivoCancel" value="No me entregaron al perro"><span>🚪 No me entregaron al perro</span></label>
                <label class="mcx-motivo"><input type="radio" name="motivoCancel" value="El perro es agresivo"><span>🐕 El perro es agresivo</span></label>
                <label class="mcx-motivo"><input type="radio" name="motivoCancel" value="El perro está enfermo"><span>🤒 El perro está enfermo</span></label>
                <label class="mcx-motivo"><input type="radio" name="motivoCancel" value="__otro__"><span>✏️ Otro motivo</span></label>
            </div>

            <input type="text" class="mcx-otro" id="mcxOtroTexto" maxlength="100"
                   placeholder="Escribe el motivo..." style="display:none">

            <div class="mcx-actions">
                <button class="mcx-btn volver" onclick="cerrarModalCancelar()">
                    <i class="fas fa-arrow-left"></i> Volver
                </button>
                <button class="mcx-btn chat" id="mcxBtnChat" onclick="abrirChatClienteModal()">
                    <i class="fas fa-comment-alt"></i> Chat cliente
                </button>
                <button class="mcx-btn confirmar" id="mcxBtnConfirmar" onclick="confirmarCancelacion()" disabled>
                    <i class="fas fa-ban"></i> Cancelar paseo
                </button>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="../../js/paseador/mapa_paseador.js?v=<?php echo @filemtime(__DIR__ . '/../../js/paseador/mapa_paseador.js'); ?>"></script>
    <script src="../../js/paseador/avisos_paseos.js?v=<?php echo @filemtime(__DIR__ . '/../../js/paseador/avisos_paseos.js'); ?>"></script>
</body>

</html>
