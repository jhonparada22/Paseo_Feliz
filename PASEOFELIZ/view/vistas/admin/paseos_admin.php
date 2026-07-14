<?php include_once '../../../controller/control_acceso.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Paseo Feliz – Gestión de Paseos</title>
  <link rel="icon" href="../../assets/images/logo.png" type="image/png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="../../css/admin/paseos_admin.css?v=<?php echo @filemtime(__DIR__ . '/../../css/admin/paseos_admin.css'); ?>" />
  <!-- Sidebar unificado admin (rojo) — después del css de la página -->
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
                        <a href="../../pagina_principal/sub_menu/conocenos.php"><li><i class="fas fa-camera"></i>Conócenos</li></a>
                        <a href="../../pagina_principal/sub_menu/direccion_oficial.php"><li><i class="fas fa-book-open"></i>Dirección oficial</li></a>
                        <a href="../../pagina_principal/sub_menu/centro_de_ayuda.php"><li><i class="fas fa-headset"></i>Centro de ayuda</li></a>
                        <a href="../../pagina_principal/sub_menu/configuracion.php"><li><i class="fas fa-gear"></i>Configuración</li></a>
                        <a href="../../../controller/logout.php"><li><i class="fas fa-sign-out-alt"></i>Cerrar sesión</li></a>
                    </ul>
                </nav>
            </div>
            <ul class="nav-links">
                <li><a href="index_admin.php"><i class="fas fa-house"></i><span>Inicio</span></a></li>
                <li><a href="usuarios_admin.php"><i class="fas fa-users"></i><span>Usuarios</span></a></li>
                <li><a href="paseadores_admin.php"><i class="fas fa-person-walking"></i><span>Paseadores</span></a>
                </li>
                    <div class="nav-sep"></div>
                </li>
                <li class="active"><a href="#"><i class="fas fa-route"></i><span>Paseos</span></a></li>
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
                <li><a href="configuracion_admin.php"><i class="fas fa-gear"></i><span>Config</span></a></li>
                <li><a href="usuario_admin.php"><i class="fas fa-user"></i><span>Usuario</span></a></li>
            </ul>
            <div class="sidebar-user">
                <div class="av">A</div><span>Admin</span>
            </div>
        </nav>

  <div class="main-content">
    <header class="topbar">
      <div style="flex:1;font-size:.85rem;font-weight:600;color:var(--muted)">
        <i class="fas fa-route" style="color:var(--primary)"></i> Panel de operaciones
      </div>
      <div class="topbar-right">
        <button class="tb-icon-btn" id="btnRefresh" title="Actualizar"><i class="fas fa-sync-alt"></i></button>
        <button class="tb-icon-btn"><i class="fas fa-bell"></i><span class="tb-badge">!</span></button>
        <div class="tb-profile">
          <div class="tb-av">A</div>
          <div><div class="tb-name"><?= htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Admin') ?></div><div class="tb-role">Administrador</div></div>
        </div>
      </div>
    </header>

    <main class="page-area">
      <div class="page-header">
        <div>
          <h1>🗺️ Gestión de Paseos</h1>
          <div class="sub">Pedidos de mensualidad comprados por los clientes: asígnalos al cronograma semanal de tus paseadores.</div>
        </div>
        <div class="header-actions">
          <button class="btn-outline" onclick="showToast('Exportación próximamente','info')"><i class="fas fa-file-export"></i> Exportar</button>
        </div>
      </div>

      <div class="mini-stats">
        <div class="mini-stat"><div class="ms-icon ms-blue"><i class="fas fa-receipt"></i></div><div><div class="ms-val" id="st-total">0</div><div class="ms-lbl">Pedidos totales</div></div></div>
        <div class="mini-stat"><div class="ms-icon ms-purple"><i class="fas fa-map-pin"></i></div><div><div class="ms-val" id="st-validar">0</div><div class="ms-lbl">Por validar</div></div></div>
        <div class="mini-stat"><div class="ms-icon ms-orange"><i class="fas fa-clock"></i></div><div><div class="ms-val" id="st-listos">0</div><div class="ms-lbl">Listos para asignar</div></div></div>
        <div class="mini-stat"><div class="ms-icon ms-green"><i class="fas fa-calendar-check"></i></div><div><div class="ms-val" id="st-crono">0</div><div class="ms-lbl">En cronograma</div></div></div>
        <div class="mini-stat"><div class="ms-icon ms-red"><i class="fas fa-ban"></i></div><div><div class="ms-val" id="st-cancel">0</div><div class="ms-lbl">Cancelados</div></div></div>
      </div>

      <!-- Torre de control: operación de HOY (cronograma vs realidad) -->
      <div id="torreHoy" style="display:none"></div>

      <div class="alertas-strip" id="alertasStrip"></div>

      <div class="filter-bar">
        <div class="si-wrap"><i class="fas fa-search"></i><input type="text" id="searchInput" placeholder="Buscar por mascota, cliente, barrio, plan o paseador..."/></div>
        <button class="sf-btn active" data-filter="todos">Todos</button>
        <button class="sf-btn" data-filter="validar">Por validar</button>
        <button class="sf-btn" data-filter="listos">Listos para asignar</button>
        <button class="sf-btn" data-filter="asignados">En cronograma</button>
        <button class="sf-btn" data-filter="cancelados">Cancelados</button>
      </div>

      <div class="content-grid">
        <div>
          <div class="cards-grid" id="cardsGrid"></div>
          <div class="empty-state" id="emptyState"><i class="fas fa-route"></i><p>No se encontraron pedidos con ese criterio.</p></div>
        </div>
        <div class="detail-panel" id="detailPanel">
          <div class="dp-empty" id="dpEmpty"><i class="fas fa-paw"></i><p>Selecciona un pedido<br>para ver el detalle y asignarlo al cronograma</p></div>
          <div id="dpContent" style="display:none"></div>
        </div>
      </div>
    </main>
  </div>
</div>

<!-- Modal asignar al cronograma -->
<div class="modal-overlay" id="assignModal">
  <div class="modal">
    <div class="modal-head">
      <div><div class="m-title" id="assignModalTitle">Asignar al cronograma</div><div class="m-sub" id="assignModalSub"></div></div>
      <button class="btn-close-modal" id="closeAssignModal"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="assignPedidoId"/>
      <div class="form-field">
        <label style="font-size:.76rem;font-weight:700;color:var(--muted)">Paseador</label>
        <select id="assignPaseador" class="filter-select" style="width:100%">
          <option value="">— Seleccionar paseador —</option>
        </select>
      </div>
      <div class="form-field" style="margin-top:12px">
        <label style="font-size:.76rem;font-weight:700;color:var(--muted)">Días de la semana para este cliente</label>
        <div id="assignDias" style="display:flex;gap:7px;flex-wrap:wrap;margin-top:6px"></div>
        <div style="font-size:.7rem;color:var(--muted);margin-top:8px">
          <i class="fas fa-info-circle"></i> Para quitar días asignados usa el botón <strong>Cronograma</strong> en la página de Paseadores.
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-cancel" id="cancelAssign">Cancelar</button>
      <button class="btn-confirm" id="confirmAssign"><i class="fas fa-check"></i> Asignar al cronograma</button>
    </div>
  </div>
</div>

<!-- Modal reasignar a otro paseador -->
<div class="modal-overlay" id="reassignModal">
  <div class="modal">
    <div class="modal-head">
      <div><div class="m-title" id="reassignModalTitle">Reasignar pedido</div><div class="m-sub" id="reassignModalSub"></div></div>
      <button class="btn-close-modal" id="closeReassignModal"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="reassignPedidoId"/>
      <div class="form-field">
        <label style="font-size:.76rem;font-weight:700;color:var(--muted)">Nuevo paseador</label>
        <select id="reassignPaseador" class="filter-select" style="width:100%">
          <option value="">— Seleccionar paseador —</option>
        </select>
      </div>
      <div class="form-field" style="margin-top:12px">
        <label style="font-size:.76rem;font-weight:700;color:var(--muted)">Alcance del cambio</label>
        <label style="display:flex;gap:8px;align-items:flex-start;margin-top:6px;font-size:.8rem;cursor:pointer">
          <input type="radio" name="reassignAlcance" value="hoy" checked>
          <span><strong>Solo el paseo de hoy</strong><br><small style="color:var(--muted)">Emergencia puntual: el cronograma semanal no cambia.</small></span>
        </label>
        <label style="display:flex;gap:8px;align-items:flex-start;margin-top:8px;font-size:.8rem;cursor:pointer">
          <input type="radio" name="reassignAlcance" value="permanente">
          <span><strong>Permanente</strong><br><small style="color:var(--muted)">Todos sus días del cronograma pasan al nuevo paseador (y el paseo de hoy, si aún no se ejecutó).</small></span>
        </label>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-cancel" id="cancelReassign">Cancelar</button>
      <button class="btn-confirm" id="confirmReassign"><i class="fas fa-people-arrows"></i> Reasignar</button>
    </div>
  </div>
</div>

<!-- Modal eliminar pedidos -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal">
    <div class="modal-head">
      <div><div class="m-title">Eliminar pedidos</div><div class="m-sub">Esta acción no se puede deshacer.</div></div>
      <button class="btn-close-modal" id="closeDelete"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <p style="font-size:.86rem;color:var(--muted);line-height:1.5">
        Vas a eliminar <strong id="delModalCount">0</strong> pedido(s) de forma permanente:
      </p>
      <p style="font-size:.84rem;font-weight:700;color:var(--text);margin-top:6px" id="delModalList"></p>
      <p style="font-size:.75rem;color:#b91c1c;margin-top:10px;line-height:1.5">
        <i class="fas fa-triangle-exclamation"></i> Se borran el pedido, su cronograma, sus paseos programados y su historial de actividad. El pago queda registrado pero desligado.
      </p>
    </div>
    <div class="modal-footer">
      <button class="btn-cancel" id="cancelDelete">Cancelar</button>
      <button class="btn-confirm" id="confirmDelete" style="background:#ef4444"><i class="fas fa-trash"></i> Sí, eliminar</button>
    </div>
  </div>
</div>

<!-- Barra flotante de selección múltiple -->
<div class="bulk-bar" id="bulkBar">
  <span id="bulkCount">0 pedidos seleccionados</span>
  <div class="bulk-actions">
    <button class="bulk-clear" id="bulkClear">Limpiar</button>
    <button class="bulk-del" id="bulkDelete"><i class="fas fa-trash"></i> Eliminar</button>
  </div>
</div>

<div class="toast" id="toast"></div>
<script src="../../js/admin/paseos_admin.js?v=<?php echo @filemtime(__DIR__ . '/../../js/admin/paseos_admin.js'); ?>"></script>
</body>
</html>
