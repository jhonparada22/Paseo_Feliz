<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once '../../../controller/control_acceso.php';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Paseo Feliz – Paseadores</title>
    <link rel="icon" href="../../assets/images/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="../../css/admin/admin.css">
    <link rel="stylesheet" href="../../css/admin/sidebar_admin.css">
    <link rel="stylesheet" href="../../css/admin/paseadores_admin.css" />
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
               <a href="../../../controller/logout.php" style="color: #000000;">
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
                <li class="active"><a href="#"><i class="fas fa-person-walking"></i><span>Paseadores</span></a>
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
<main class="page-area">

                <!-- Header -->
                <div class="page-header">
                    <div>
                        <h1>Gestión de Paseadores</h1>
                        <div class="sub">Monitorea, asigna rutas y gestiona el equipo de paseadores en tiempo real.
                        </div>
                    </div>
</div>

                <!-- Mini stats -->
                <div class="mini-stats">
                    <div class="mini-stat">
                        <div class="ms-icon ms-blue"><i class="fas fa-person-walking"></i></div>
                        <div>
                            <div class="ms-val" id="statTotal">0</div>
                            <div class="ms-lbl">Total paseadores</div>
                        </div>
                    </div>
                    <div class="mini-stat">
                        <div class="ms-icon ms-green"><i class="fas fa-circle-check"></i></div>
                        <div>
                            <div class="ms-val" id="statActivo">0</div>
                            <div class="ms-lbl">Activos ahora</div>
                        </div>
                    </div>
                    <div class="mini-stat">
                        <div class="ms-icon ms-orange"><i class="fas fa-route"></i></div>
                        <div>
                            <div class="ms-val" id="statEnRuta">0</div>
                            <div class="ms-lbl">En ruta</div>
                        </div>
                    </div>
                    <div class="mini-stat">
                        <div class="ms-icon ms-red"><i class="fas fa-circle-pause"></i></div>
                        <div>
                            <div class="ms-val" id="statPausado">0</div>
                            <div class="ms-lbl">Inactivos</div>
                        </div>
                    </div>
                    <div class="mini-stat">
                        <div class="ms-icon ms-purple"><i class="fas fa-star"></i></div>
                        <div>
                            <div class="ms-val" id="statRating">0</div>
                            <div class="ms-lbl">Rating promedio</div>
                        </div>
                    </div>
                </div>

                <!-- Búsqueda -->
                <div class="search-bar-wrap">
                    <div class="search-input-wrap">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Buscar paseador por nombre, zona o email..." />
                    </div>
                    <div class="status-filter-btns">
                        <button class="sf-btn active" data-filter="todos">Todos</button>
                        <button class="sf-btn green-f" data-filter="activo">Activos</button>
                        <button class="sf-btn orange-f" data-filter="en-ruta">En ruta</button>
                        <button class="sf-btn red-f" data-filter="inactivo">Inactivos</button>
                    </div>
                </div>

                <!-- Grid principal -->
                <div class="main-grid">

                    <!-- Lista de paseadores -->
                    <div>
                        <div class="paseadores-list" id="paseadoresList"></div>
                        <div class="empty-state" id="emptyState">
                            <i class="fas fa-person-walking"></i>
                            <p>No se encontraron paseadores.</p>
                        </div>

                        <!-- Historial rutas recientes -->
                        <div class="routes-history" style="margin-top:18px">
                            <div class="rh-head">
                                <span class="rht"><i class="fas fa-clock-rotate-left"
                                        style="color:var(--primary);margin-right:7px"></i>Rutas Recientes</span>
                                <button class="btn-primary" style="font-size:.72rem;padding:5px 11px"
                                    onclick="showToast('Ver historial completo','info')">Ver todas</button>
                            </div>
                            <div id="rutasRecientes"></div>
                        </div>
                    </div>

                    <!-- Panel derecho -->
                    <div class="right-panel">

                        <!-- Detalle paseador -->
                        <div class="detail-card" id="detailCard">
                            <div style="padding:32px;text-align:center;color:var(--muted)">
                                <i class="fas fa-person-walking"
                                    style="font-size:2.5rem;color:#ddd;display:block;margin-bottom:10px"></i>
                                <p style="font-size:.85rem">Selecciona un paseador para ver su detalle</p>
                            </div>
                        </div>

                        <!-- Mini mapa -->
                        <div class="map-card">
                            <div class="map-head">
                                <div class="mh-title"><i class="fas fa-map-location-dot"></i> Ubicación en tiempo real
                                </div>
                                <div class="mh-live">
                                    <div class="live-dot"></div> EN VIVO
                                </div>
                            </div>
                            <div id="minimap"></div>
                            <div class="map-footer">
                                <span class="mf-coord" id="mapCoord">Cúcuta, Norte de Santander</span>
                                <a href="mapa_admin.php" class="mf-btn">
                                    <i class="fas fa-expand"></i> Ver mapa completo
                                </a>
                            </div>
                        </div>

                    </div>
                </div>
            </main>
        </div>
    </div>


    <!-- ══ MODAL EDITAR INFO PASEADOR ══════════════════════════ -->
    <div class="modal-overlay" id="infoModal">
        <div class="modal">
            <div class="modal-head">
                <div>
                    <div class="m-title" id="infoModalTitle">Editar información</div>
                    <div class="m-sub" id="infoModalSub">Zona de trabajo y horario</div>
                </div>
                <button class="btn-close-modal" id="closeInfoModal"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="schedule-grid">
                    <div class="form-field" style="grid-column:span 2">
                        <label>Zona de trabajo</label>
                        <input type="text" id="infoZona" placeholder="Ej: Cúcuta Centro / Los Patios" />
                    </div>
                    <div class="form-field">
                        <label>Hora inicio</label>
                        <input type="time" id="infoHoraInicio" />
                    </div>
                    <div class="form-field">
                        <label>Hora fin</label>
                        <input type="time" id="infoHoraFin" />
                    </div>
                    <div class="form-field" style="grid-column:span 2">
                        <label>Puntuación</label>
                        <div id="infoPuntuacionSoloLectura" style="font-size:.8rem;color:var(--muted);background:#f8fafc;border-radius:8px;padding:10px 12px">
                            Se calcula automáticamente con las calificaciones de 1-5 estrellas que dejan los clientes al finalizar cada paseo. Ya no se edita manualmente.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" id="cancelInfo">Cancelar</button>
                <button class="btn-confirm" id="confirmInfo">
                    <i class="fas fa-save"></i> Guardar cambios
                </button>
            </div>
        </div>
    </div>

    <!-- ══ MODAL ASIGNAR RUTA ════════════════════════════════════ -->
    <div class="modal-overlay" id="routeModal">
        <div class="modal">
            <div class="modal-head">
                <div>
                    <div class="m-title" id="modalTitle">Asignar Ruta</div>
                    <div class="m-sub" id="modalSub">Selecciona los clientes y configura el paseo</div>
                </div>
                <button class="btn-close-modal" id="closeRouteModal"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">

                <!-- Clientes disponibles -->
                <div>
                    <div class="route-section-title"><i class="fas fa-users" style="margin-right:5px"></i>Clientes para
                        asignar</div>
                    <div id="clientRouteList" style="display:flex;flex-direction:column;gap:8px"></div>
                </div>

                <!-- Configuración -->
                <div>
                    <div class="route-section-title"><i class="fas fa-sliders-h"
                            style="margin-right:5px"></i>Configuración del paseo</div>
                    <div class="schedule-grid">
                        <div class="form-field">
                            <label>Fecha</label>
                            <input type="date" id="routeDate" />
                        </div>
                        <div class="form-field">
                            <label>Hora inicio</label>
                            <input type="time" id="routeTime" />
                        </div>
                        <div class="form-field">
                            <label>Duración estimada</label>
                            <select id="routeDuration">
                                <option value="30">30 minutos</option>
                                <option value="60" selected>1 hora</option>
                                <option value="90">1.5 horas</option>
                                <option value="120">2 horas</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label>Punto de encuentro</label>
                            <input type="text" id="routeMeet" placeholder="Ej: Parque Santander" />
                        </div>
                    </div>
                    <div class="form-field" style="margin-top:10px">
                        <label>Prioridad</label>
                        <div class="priority-btns">
                            <div class="prio-btn baja active" data-prio="baja">🟢 Baja</div>
                            <div class="prio-btn media" data-prio="media">🟡 Media</div>
                            <div class="prio-btn alta" data-prio="alta">🔴 Alta</div>
                        </div>
                    </div>
                    <div class="form-field" style="margin-top:10px">
                        <label>Notas para el paseador</label>
                        <textarea id="routeNotes"
                            placeholder="Instrucciones especiales, alergias de mascotas, etc..."></textarea>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button class="btn-cancel" id="cancelRoute">Cancelar</button>
                <button class="btn-confirm" id="confirmRoute">
                    <i class="fas fa-route"></i> Asignar ruta
                </button>
            </div>
        </div>
    </div>

    <!-- ══ MODAL CRONOGRAMA SEMANAL ══════════════════════════════ -->
    <style>
        /* pills de días del cronograma (diseño de referencia) */
        .crono-pill {
            flex: 1; min-width: 74px; text-align: center; cursor: pointer;
            border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 10px 6px;
            background: #fff; transition: all .15s;
        }
        .crono-pill:hover { border-color: #b8cde2; }
        .crono-pill.sel { background: #1e3a5f; border-color: #1e3a5f; color: #fff; }
        .crono-pill .cp-dia { font-size: .68rem; font-weight: 800; letter-spacing: .5px; }
        .crono-pill .cp-num { font-size: 1.15rem; font-weight: 800; margin: 2px 0; }
        .crono-pill .cp-cnt {
            font-size: .6rem; font-weight: 700; border-radius: 10px; padding: 1px 8px;
            background: #eaf1f8; color: #2c5282; display: inline-block;
        }
        .crono-pill.sel .cp-cnt { background: rgba(255,255,255,.2); color: #fff; }
        .crono-cliente {
            display: flex; align-items: center; gap: 10px; padding: 10px 12px;
            border: 1.5px solid #e2e8f0; border-radius: 10px; cursor: pointer; background: #fff;
        }
        .crono-cliente.sel { border-color: var(--primary, #3E72A6); background: #f4f8fc; }
        .crono-cliente input { width: 16px; height: 16px; accent-color: var(--primary, #3E72A6); }
        .crono-cliente .cc-info { flex: 1; }
        .crono-cliente .cc-nombre { font-weight: 700; font-size: .82rem; }
        .crono-cliente .cc-sub { font-size: .7rem; color: #64748b; }
    </style>
    <div class="modal-overlay" id="cronoModal">
        <div class="modal" style="max-width:640px">
            <div class="modal-head">
                <div>
                    <div class="m-title" id="cronoTitle"><i class="fas fa-calendar-week"></i> Cronograma de la Semana</div>
                    <div class="m-sub" id="cronoSub"></div>
                </div>
                <button class="btn-close-modal" id="closeCronoModal"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div id="cronoPills" style="display:flex;gap:8px;flex-wrap:wrap"></div>
                <div class="route-section-title" style="margin-top:16px" id="cronoDiaTitulo"></div>
                <div style="font-size:.7rem;color:var(--muted);margin-bottom:8px">
                    Marca los clientes (con plan pagado) que este paseador atenderá ese día y pulsa "Guardar día".
                </div>
                <div id="cronoClientes" style="display:flex;flex-direction:column;gap:8px"></div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" id="cancelCrono">Cerrar</button>
                <button class="btn-confirm" id="confirmCrono"><i class="fas fa-check"></i> Guardar día</button>
            </div>
        </div>
    </div>

    <!-- TOAST -->
    <div class="toast" id="toast"><i class="fas fa-check-circle"></i><span id="toastMsg"></span></div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="../../js/admin/paseadores_admin.js?v=3"></script>
    
</body>

</html>