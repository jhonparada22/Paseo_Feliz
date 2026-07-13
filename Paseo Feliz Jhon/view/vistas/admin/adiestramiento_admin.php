<?php include_once '../../../controller/control_acceso.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Paseo Feliz – Gestión de Adiestramiento</title>
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
                        <a href="./sub_menu/conocenos.php"><li><i class="fas fa-camera"></i>Conócenos</li></a>
                        <a href="./sub_menu/estado_bd.php"><li><i class="fas fa-database"></i><span>Estado del sistema</span></li></a>
                        <a href="./sub_menu/manual.php"><li><i class="fas fa-book-open"></i><span>Manual de uso</span></li></a>
                        
                        <a href="../../../controller/logout.php"><li><i class="fas fa-sign-out-alt"></i>Cerrar sesión</li></a>
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
                <li class="active"><a href="#"><i class="fas fa-graduation-cap"></i><span>Adiestramiento</span></a></li>
                <li><a href="hospedaje_admin.php"><i class="fas fa-house"></i><span>Hospedaje</span></a></li>
                <li><a href="pagos_admin.php"><i class="fas fa-credit-card"></i><span>Pagos</span></a></li>
                <li>
                    <div class="nav-sep"></div>
                </li>
                <li><a href="Chat_admin.php"><i class="far fa-comment-alt"></i><span>Chat</span></a></li>
                <li><a href="adopcion_admin.php"><i class="fas fa-bone"></i><span>Adopción</span></a></li>
                <li>
                    <div class="nav-sep"></div>
                </li>
                <li><a href="usuario_admin.php"><i class="fas fa-user"></i><span>Usuario</span></a></li>
            </ul>
        </nav>

  <div class="main-content">
    <main class="page-area">
      <div class="page-header">
        <div>
          <h1>🎓 Gestión de Adiestramiento</h1>
          <div class="sub">Pedidos de adiestramiento comprados por los clientes: asígnalos a un entrenador (paseador) y sus días de sesión.</div>
        </div>
        <div class="header-actions">
          <button class="btn-outline" onclick="showToast('Exportación próximamente','info')"><i class="fas fa-file-export"></i> Exportar</button>
        </div>
      </div>

      <div class="mini-stats">
        <div class="mini-stat"><div class="ms-icon ms-blue"><i class="fas fa-receipt"></i></div><div><div class="ms-val" id="st-total">0</div><div class="ms-lbl">Pedidos totales</div></div></div>
        <div class="mini-stat"><div class="ms-icon ms-orange"><i class="fas fa-clock"></i></div><div><div class="ms-val" id="st-listos">0</div><div class="ms-lbl">Listos para asignar</div></div></div>
        <div class="mini-stat"><div class="ms-icon ms-green"><i class="fas fa-calendar-check"></i></div><div><div class="ms-val" id="st-crono">0</div><div class="ms-lbl">Con entrenador</div></div></div>
        <div class="mini-stat"><div class="ms-icon ms-purple"><i class="fas fa-play"></i></div><div><div class="ms-val" id="st-hoy">0</div><div class="ms-lbl">Inician hoy</div></div></div>
        <div class="mini-stat"><div class="ms-icon ms-red"><i class="fas fa-ban"></i></div><div><div class="ms-val" id="st-cancel">0</div><div class="ms-lbl">Cancelados</div></div></div>
      </div>

      <div class="alertas-strip" id="alertasStrip"></div>

      <div class="filter-bar">
        <div class="si-wrap"><i class="fas fa-search"></i><input type="text" id="searchInput" placeholder="Buscar por mascota, cliente, barrio o entrenador..."/></div>
        <button class="sf-btn active" data-filter="todos">Todos</button>
        <button class="sf-btn" data-filter="listos">Listos para asignar</button>
        <button class="sf-btn" data-filter="asignados">Con entrenador</button>
        <button class="sf-btn" data-filter="cancelados">Cancelados</button>
      </div>

      <div class="content-grid">
        <div>
          <div class="cards-grid" id="cardsGrid"></div>
          <div class="empty-state" id="emptyState"><i class="fas fa-graduation-cap"></i><p>No se encontraron pedidos con ese criterio.</p></div>
        </div>
        <div class="detail-panel" id="detailPanel">
          <div class="dp-empty" id="dpEmpty"><i class="fas fa-paw"></i><p>Selecciona un pedido<br>para ver el detalle y asignar entrenador</p></div>
          <div id="dpContent" style="display:none"></div>
        </div>
      </div>
    </main>
  </div>
</div>

<!-- Modal asignar entrenador -->
<div class="modal-overlay" id="assignModal">
  <div class="modal">
    <div class="modal-head">
      <div><div class="m-title" id="assignModalTitle">Asignar entrenador</div><div class="m-sub" id="assignModalSub"></div></div>
      <button class="btn-close-modal" id="closeAssignModal"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="assignPedidoId"/>
      <div class="form-field">
        <label style="font-size:.76rem;font-weight:700;color:var(--muted)">Entrenador (paseador)</label>
        <select id="assignPaseador" class="filter-select" style="width:100%">
          <option value="">— Seleccionar entrenador —</option>
        </select>
      </div>
      <div class="form-field" style="margin-top:12px">
        <label style="font-size:.76rem;font-weight:700;color:var(--muted)">Días de la semana para las sesiones</label>
        <div id="assignDias" style="display:flex;gap:7px;flex-wrap:wrap;margin-top:6px"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-cancel" id="cancelAssign">Cancelar</button>
      <button class="btn-confirm" id="confirmAssign"><i class="fas fa-check"></i> Asignar entrenador</button>
    </div>
  </div>
</div>

<!-- Modal cancelar servicio -->
<div class="modal-overlay" id="cancelServicioModal">
  <div class="modal">
    <div class="modal-head">
      <div><div class="m-title">Cancelar servicio</div><div class="m-sub" id="cancelServicioSub"></div></div>
      <button class="btn-close-modal" id="closeCancelServicioModal"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="cancelServicioPedidoId"/>
      <div class="form-field">
        <label style="font-size:.76rem;font-weight:700;color:var(--muted)">Motivo de la cancelación (obligatorio)</label>
        <textarea id="cancelServicioMotivo" rows="3" style="width:100%;margin-top:6px;padding:9px 11px;border:1.5px solid var(--border);border-radius:9px;font-size:.85rem;font-family:inherit;resize:vertical" placeholder="Ej: el cliente lo solicitó, incumplimiento de requisitos..."></textarea>
      </div>
      <div id="cancelServicioError" style="display:none;color:#ef4444;font-size:.78rem;margin-top:8px"></div>
    </div>
    <div class="modal-footer">
      <button class="btn-cancel" id="volverCancelServicio">Volver</button>
      <button class="btn-confirm" id="confirmCancelServicio" style="background:#ef4444"><i class="fas fa-ban"></i> Cancelar servicio</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>
<script src="../../js/admin/adiestramiento_admin.js?v=<?php echo @filemtime(__DIR__ . '/../../js/admin/adiestramiento_admin.js'); ?>"></script>
</body>
</html>
