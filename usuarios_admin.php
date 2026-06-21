<?php include_once '../../controller/control_acceso.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Paseo Feliz – Usuarios</title>
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
          <a href="./sub_menu/conocenos.php"><li><i class="fas fa-camera"></i><span>Conócenos</span></li></a>
          <a href="./sub_menu/direccion_oficial.php"><li><i class="fas fa-book-open"></i><span>Dirección oficial</span></li></a>
          <a href="./sub_menu/centro_de_ayuda.php"><li><i class="fas fa-sliders-h"></i><span>Centro de ayuda</span></li></a>
          <a href="./sub_menu/configuracion.php"><li><i class="fas fa-gear"></i><span>Configuración</span></li></a>
          <li>
               <a href="../../controller/logout.php" style="color: #000000;">
                  <i class="fas fa-sign-out-alt"></i>
                  <span>Cerrar Sesión</span>
              </a>
          </li>
        </ul>
      </nav>
            </div>
            <ul class="nav-links">
                <li><a href="index_admin.php"><i class="fas fa-house"></i><span>Inicio</span></a></li>
                <li class="active"><a href="#"><i class="fas fa-users"></i><span>Usuarios</span></a></li>
                <li><a href="paseadores_admin.php"><i class="fas fa-person-walking"></i><span>Paseadores</span></a>
                </li>
                <li><a href="mascotas_admin.php"><i class="fas fa-dog"></i><span>Mascotas</span></a></li>
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
          <h1>Gestión de Usuarios</h1>
          <div class="sub">Administra los usuarios registrados en la plataforma.</div>
        </div>
        <button class="btn-add" onclick="showToast('Función próximamente disponible','info')">
          <i class="fas fa-user-plus"></i> Agregar usuario
        </button>
      </div>

      <!-- Mini stats -->
      <div class="mini-stats">
        <div class="mini-stat">
          <div class="ms-icon blue"><i class="fas fa-users"></i></div>
          <div class="ms-info"><div class="ms-val" id="statTotal">0</div><div class="ms-lbl">Total usuarios</div></div>
        </div>
        <div class="mini-stat">
          <div class="ms-icon green"><i class="fas fa-user"></i></div>
          <div class="ms-info"><div class="ms-val" id="statCliente">0</div><div class="ms-lbl">Clientes</div></div>
        </div>
        <div class="mini-stat">
          <div class="ms-icon orange"><i class="fas fa-person-walking"></i></div>
          <div class="ms-info"><div class="ms-val" id="statPaseador">0</div><div class="ms-lbl">Paseadores</div></div>
        </div>
        <div class="mini-stat">
          <div class="ms-icon purple"><i class="fa-solid fa-shield-halved"></i></div>
          <div class="ms-info"><div class="ms-val" id="statAdmin">0</div><div class="ms-lbl">Administradores</div></div>
        </div>
      </div>

      <!-- Search + Filter -->
      <div class="search-filter-bar">
        <div class="search-row">
          <div class="search-input-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Buscar por nombre, correo o cédula..." />
          </div>
          <button class="btn-filter" id="btnFilter" title="Filtrar">
            <i class="fas fa-filter"></i>
          </button>
        </div>
        <div class="filter-panel" id="filterPanel">
          <div class="filter-panel-head">
            <div class="fp-title"><i class="fas fa-sliders-h"></i> Filtrar por</div>
            <button class="btn-close-filter" id="btnCloseFilter"><i class="fas fa-chevron-up"></i></button>
          </div>
          <div class="filter-fields">
            <div class="filter-field">
              <label>Nombre</label>
              <div class="f-input-wrap">
                <i class="fas fa-user"></i>
                <input type="text" id="fNombre" placeholder="Buscar por nombre..." />
              </div>
            </div>
            <div class="filter-field">
              <label>Fecha de registro</label>
              <div class="f-input-wrap">
                <i class="fas fa-calendar"></i>
                <input type="date" id="fFecha" style="padding-left:34px" />
              </div>
            </div>
            <div class="filter-field">
              <label>Rol</label>
              <div class="f-input-wrap">
                <i class="fas fa-tag"></i>
                <select id="fRol">
                  <option value="">Todos los roles</option>
                  <option value="cliente">Cliente</option>
                  <option value="paseador">Paseador</option>
                  <option value="administrador">Administrador</option>
                </select>
                <i class="fas fa-chevron-down select-arrow"></i>
              </div>
            </div>
          </div>
          <div class="filter-actions">
            <button class="btn-clear-filter" id="btnClearFilter">Limpiar filtros</button>
            <button class="btn-apply-filter" id="btnApplyFilter">Aplicar filtros</button>
          </div>
        </div>
      </div>

      <!-- Lista de usuarios -->
      <div class="user-list-wrap">
        <div class="list-head">
          <div style="display:flex;align-items:center;gap:10px">
            <span class="lh-title">Usuarios registrados</span>
            <span class="lh-count" id="userCount">0 usuarios</span>
          </div>
          <div class="list-head-right">
            <select class="sort-select" id="sortSelect">
              <option value="nombre">Ordenar por nombre</option>
              <option value="fecha">Ordenar por fecha</option>
              <option value="rol">Ordenar por rol</option>
            </select>
          </div>
        </div>

        <div id="userList"></div>
        <div class="empty-state" id="emptyState">
          <i class="fas fa-users-slash"></i>
          <p>No se encontraron usuarios con esos criterios.</p>
        </div>

        <div class="pagination-row">
          <span class="pag-info" id="pagInfo">Mostrando 1–5 de 8 usuarios</span>
          <div class="pag-btns">
            <button class="pag-btn" id="pagPrev" disabled><i class="fas fa-chevron-left"></i></button>
            <button class="pag-btn active" data-page="1">1</button>
            <button class="pag-btn" data-page="2">2</button>
            <button class="pag-btn" id="pagNext"><i class="fas fa-chevron-right"></i></button>
          </div>
        </div>
      </div>

    </main>
  </div>
</div>

<!-- ── MODAL MASCOTAS ───────────────────────────────────────── -->
<div class="modal-overlay" id="petsModal">
  <div class="modal">
    <div class="modal-head">
      <div>
        <div class="m-title" id="modalUserName">Mascotas de Usuario</div>
        <div class="m-sub" id="modalPetCount">0 mascotas registradas</div>
      </div>
      <button class="btn-close-modal" id="closeModal"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body" id="modalPetList"></div>
  </div>
</div>

<!-- ── TOAST ────────────────────────────────────────────────── -->
<div class="toast" id="toast">
  <i class="fas fa-check-circle"></i>
  <span id="toastMsg">Cambio guardado</span>
</div>

<script src="../../js/admin/usuarios_admin.js">

</script>
</body>
</html>