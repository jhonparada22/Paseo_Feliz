<?php include_once '../../../controller/control_acceso.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Paseo Feliz – Pagos</title>
  <link rel="icon" href="../../assets/images/logo.png" type="image/png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../../css/admin/admin.css">
  <link rel="stylesheet" href="../../css/admin/pagos_admin.css?v=<?php echo @filemtime(__DIR__ . '/../../css/admin/pagos_admin.css'); ?>">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <link rel="stylesheet" href="../../css/admin/sidebar_admin.css?v=<?php echo @filemtime(__DIR__ . '/../../css/admin/sidebar_admin.css'); ?>">
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
            <a href="../../../controller/logout.php" style="color:#000000;">
              <i class="fas fa-sign-out-alt"></i><span>Cerrar Sesión</span>
            </a>
          </li>
        </ul>
      </nav>
    </div>
    <ul class="nav-links">
      <li><a href="index_admin.php"><i class="fas fa-house"></i><span>Inicio</span></a></li>
      <li><a href="usuarios_admin.php"><i class="fas fa-users"></i><span>Usuarios</span></a></li>
      <li><a href="paseadores_admin.php"><i class="fas fa-person-walking"></i><span>Paseadores</span></a></li>
      <li><div class="nav-sep"></div></li>
      <li><a href="paseos_admin.php"><i class="fas fa-route"></i><span>Paseos</span></a></li>
      <li class="active"><a href="#"><i class="fas fa-credit-card"></i><span>Pagos</span></a></li>
      <li><div class="nav-sep"></div></li>
      <li><a href="Chat_admin.php"><i class="far fa-comment-alt"></i><span>Chat</span></a></li>
      <li><a href="mapa_admin.php"><i class="fas fa-map-location-dot"></i><span>Mapa</span></a></li>
      <li><a href="adopcion_admin.php"><i class="fas fa-bone"></i><span>Adopción</span></a></li>
      <li><div class="nav-sep"></div></li>
      <li><a href="usuario_admin.php"><i class="fas fa-user"></i><span>Usuario</span></a></li>
    </ul>
  </nav>

  <!-- ══ MAIN ═════════════════════════════════════════════════ -->
  <div class="main-content">
    <main class="page-area">

      <div class="page-header">
        <div>
          <h1>Gestión de Pagos</h1>
          <div class="sub">Registra pagos y controla las membresías activas.</div>
        </div>
        <div style="display:flex; gap:10px;">
          <button class="btn-nuevo-pago" id="btnPrecios" style="background:#475569;">
            <i class="fas fa-tags"></i> Precios
          </button>
          <button class="btn-nuevo-pago" id="btnNuevoPago">
            <i class="fas fa-plus"></i> Registrar pago
          </button>
        </div>
      </div>

      <div class="mini-stats mini-stats-5">
        <div class="mini-stat">
          <div class="ms-icon blue"><i class="fas fa-id-card"></i></div>
          <div class="ms-info">
            <div class="ms-lbl">Miembros con membresía</div>
            <div class="ms-val" id="statMiembros">0</div>
          </div>
        </div>
        <div class="mini-stat">
          <div class="ms-icon green"><i class="fas fa-dog"></i></div>
          <div class="ms-info">
            <div class="ms-lbl">Paseos activas</div>
            <div class="ms-val" id="statPaseos">0</div>
          </div>
        </div>
        <div class="mini-stat">
          <div class="ms-icon orange"><i class="fas fa-graduation-cap"></i></div>
          <div class="ms-info">
            <div class="ms-lbl">Adiestramiento activas</div>
            <div class="ms-val" id="statAdiestramiento">0</div>
          </div>
        </div>
        <div class="mini-stat">
          <div class="ms-icon purple"><i class="fas fa-house"></i></div>
          <div class="ms-info">
            <div class="ms-lbl">Hospedaje activas</div>
            <div class="ms-val" id="statHospedaje">0</div>
          </div>
        </div>
        <div class="mini-stat">
          <div class="ms-icon teal"><i class="fas fa-sack-dollar"></i></div>
          <div class="ms-info">
            <div class="ms-lbl">Ingresos totales</div>
            <div class="ms-val" id="statIngresosTotales">$0</div>
          </div>
        </div>
      </div>

      <!-- PAGOS RECIENTES -->
      <div class="section-card">
        <div class="sc-head">
          <div class="sc-title"><i class="fas fa-clock-rotate-left"></i> Pagos recientes</div>
          <span class="sc-badge" id="badgePagos">0</span>
        </div>
        <div id="listaPagos" class="pagos-list"></div>
        <div class="empty-state" id="emptyPagos">
          <i class="fas fa-receipt"></i>
          <p>Aún no hay pagos registrados.</p>
        </div>
      </div>

      <!-- USUARIOS Y MEMBRESÍAS -->
      <div class="section-card">
        <div class="sc-head">
          <div class="sc-title"><i class="fas fa-users"></i> Estado de membresías por usuario</div>
          <div class="sc-search">
            <i class="fas fa-search"></i>
            <input type="text" id="searchUsuarios" placeholder="Buscar usuario...">
          </div>
        </div>
        <div class="mem-filter-bar">
          <button class="mf-btn active" data-filter="todos">Todos</button>
          <button class="mf-btn" data-filter="activa">Con membresía activa</button>
          <button class="mf-btn" data-filter="inactiva">Sin membresía</button>
        </div>
        <div id="listaUsuariosMem" class="usuarios-mem-list"></div>
        <div class="empty-state" id="emptyUsuarios">
          <i class="fas fa-users-slash"></i>
          <p>No se encontraron usuarios con esos criterios.</p>
        </div>
        <div class="pagination-row">
          <span class="pag-info" id="pagInfo">Cargando...</span>
          <div class="pag-btns">
            <button class="pag-btn" id="pagPrev" disabled><i class="fas fa-chevron-left"></i></button>
            <button class="pag-btn" id="pagNext"><i class="fas fa-chevron-right"></i></button>
          </div>
        </div>
      </div>

    </main>
  </div>
</div>

<!-- ══ MODAL PRECIOS Y DESCUENTOS ════════════════════════════ -->
<div class="modal-overlay" id="modalPrecios">
  <div class="modal modal-pago">
    <div class="modal-head">
      <div>
        <div class="m-title">Precios y descuentos</div>
        <div class="m-sub">Precio por unidad y descuentos por cantidad de cada servicio</div>
      </div>
      <button class="btn-close-modal" id="closeModalPrecios"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">

      <div class="form-group">
        <label>Servicio</label>
        <div class="mem-opciones" id="precTipoTabs">
          <label class="mem-opcion">
            <input type="radio" name="prec_tipo" value="paseos" checked>
            <span><i class="fas fa-dog"></i> Paseos</span>
          </label>
          <label class="mem-opcion">
            <input type="radio" name="prec_tipo" value="adiestramiento">
            <span><i class="fas fa-graduation-cap"></i> Adiestramiento</span>
          </label>
          <label class="mem-opcion">
            <input type="radio" name="prec_tipo" value="hospedaje">
            <span><i class="fas fa-house"></i> Hospedaje</span>
          </label>
        </div>
      </div>

      <div class="form-row-2">
        <div class="form-group">
          <label>Precio por unidad (COP)</label>
          <input type="number" id="precPrecioUnidad" class="mp-input-simple" min="0" step="500">
        </div>
        <div class="form-group">
          <label>Unidad</label>
          <input type="text" id="precUnidadLabel" class="mp-input-simple" placeholder="día, sesión, noche...">
        </div>
      </div>

      <div class="form-group">
        <label>Descuentos por cantidad <span class="mp-req-hint">(ej: 8+ = 5%, 12+ = 10%)</span></label>
        <div id="precDescuentosLista"></div>
        <button type="button" class="mem-opcion" id="btnAgregarDescuento" style="margin-top:8px; padding:8px 14px; cursor:pointer; background:none;">
          <i class="fas fa-plus"></i> Agregar tramo de descuento
        </button>
      </div>

      <button class="btn-confirmar-pago" id="btnGuardarPrecios">
        <i class="fas fa-check"></i> Guardar precio de este servicio
      </button>

    </div>
  </div>
</div>

<!-- ══ MODAL REGISTRAR PAGO ══════════════════════════════════ -->

<div class="modal-overlay" id="modalPago">
  <div class="modal modal-pago">
    <div class="modal-head">
      <div>
        <div class="m-title">Registrar nuevo pago</div>
        <div class="m-sub">Activa la membresía por 30 días</div>
      </div>
      <button class="btn-close-modal" id="closeModalPago"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">

      <div class="form-group">
        <label>Usuario</label>
        <div class="select-wrap">
          <i class="fas fa-user"></i>
          <select id="mpUsuario">
            <option value="">Seleccionar usuario...</option>
          </select>
          <i class="fas fa-chevron-down select-arrow"></i>
        </div>
      </div>

      <!-- Aviso: el usuario elegido no tiene ninguna mascota registrada -->
      <div class="mp-aviso-sin-mascota" id="mpAvisoSinMascota" style="display:none;">
        <i class="fas fa-triangle-exclamation"></i>
        Este usuario no tiene ninguna mascota registrada. No se puede registrar un pago
        (la membresía se activa por mascota) hasta que registre al menos una.
      </div>

      <div class="form-group" id="mpMascotaGroup" style="display:none;">
        <label>Mascota</label>
        <div class="select-wrap">
          <i class="fas fa-paw"></i>
          <select id="mpMascota">
            <option value="">Seleccionar mascota...</option>
          </select>
          <i class="fas fa-chevron-down select-arrow"></i>
        </div>
      </div>

      <div class="form-group">
        <label>Membresía</label>
        <div class="mem-opciones" id="mpTipo">
          <label class="mem-opcion">
            <input type="radio" name="tipo_mem" value="paseos">
            <span><i class="fas fa-dog"></i> Paseos<br><small>Según plan</small></span>
          </label>
          <label class="mem-opcion">
            <input type="radio" name="tipo_mem" value="adiestramiento">
            <span><i class="fas fa-graduation-cap"></i> Adiestramiento<br><small>$22.000</small></span>
          </label>
          <label class="mem-opcion">
            <input type="radio" name="tipo_mem" value="hospedaje">
            <span><i class="fas fa-house"></i> Hospedaje<br><small>$28.000</small></span>
          </label>
        </div>
      </div>

      <!-- ═══ Detalles del pedido de Paseos (solo si tipo_mem = paseos) ═══
           Crea un pedido_paseo real, igual que el wizard del cliente, para
           que después se pueda asignar cronograma/paseador normalmente. -->
      <div id="mpPaseosDetalle" style="display:none;">

        <div class="form-group">
          <label>Paseos al mes <span class="mp-req-hint" id="mpPrecioUnidadHint">($18.000 c/u)</span></label>
          <div class="select-wrap">
            <i class="fas fa-calendar-days"></i>
            <input type="number" id="mpCantidadPaseos" class="mp-input-simple" style="padding-left:36px;" min="1" max="31" value="8">
          </div>
        </div>

        <div class="form-row-2">
          <div class="form-group">
            <label>Modalidad</label>
            <div class="select-wrap">
              <select id="mpModalidad">
                <option value="grupal">Grupal</option>
                <option value="individual">Individual</option>
              </select>
              <i class="fas fa-chevron-down select-arrow"></i>
            </div>
          </div>
          <div class="form-group">
            <label>Duración del paseo</label>
            <div class="select-wrap">
              <select id="mpDuracion">
                <option value="30">30 minutos</option>
                <option value="60" selected>60 minutos</option>
                <option value="120">2 horas</option>
                <option value="180">3 horas</option>
              </select>
              <i class="fas fa-chevron-down select-arrow"></i>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label>Días preferidos</label>
          <div class="dias-semana-grid" id="mpDias">
            <label><input type="checkbox" value="lun" checked><span>Lun</span></label>
            <label><input type="checkbox" value="mar"><span>Mar</span></label>
            <label><input type="checkbox" value="mie" checked><span>Mié</span></label>
            <label><input type="checkbox" value="jue"><span>Jue</span></label>
            <label><input type="checkbox" value="vie" checked><span>Vie</span></label>
            <label><input type="checkbox" value="sab"><span>Sáb</span></label>
            <label><input type="checkbox" value="dom"><span>Dom</span></label>
          </div>
        </div>

        <div class="form-row-2">
          <div class="form-group">
            <label>Franja horaria</label>
            <div class="select-wrap">
              <select id="mpFranja">
                <option>6:00 a.m. – 8:00 a.m.</option>
                <option selected>8:00 a.m. – 11:00 a.m.</option>
                <option>11:00 a.m. – 2:00 p.m.</option>
                <option>2:00 p.m. – 5:00 p.m.</option>
              </select>
              <i class="fas fa-chevron-down select-arrow"></i>
            </div>
          </div>
          <div class="form-group">
            <label>Fecha de inicio</label>
            <input type="date" id="mpFechaInicio" class="mp-input-simple">
          </div>
        </div>

        <div class="form-group">
          <label>Dirección de recogida</label>
          <input type="text" id="mpDireccion" class="mp-input-simple" placeholder="Ej: Cra 5 # 10-20, Cúcuta">
        </div>

        <div class="form-row-2">
          <div class="form-group">
            <label>Barrio (opcional)</label>
            <input type="text" id="mpBarrio" class="mp-input-simple">
          </div>
          <div class="form-group">
            <label>Referencia (opcional)</label>
            <input type="text" id="mpReferencia" class="mp-input-simple" placeholder="Casa esquinera, portón azul...">
          </div>
        </div>

        <div class="form-group">
          <label>Instrucciones para el paseador (opcional)</label>
          <input type="text" id="mpInstrucciones" class="mp-input-simple">
        </div>

        <div class="form-group">
          <label>Ubicación en el mapa <span class="mp-req-hint">(clic para marcar el punto de recogida)</span></label>
          <div id="mpMapa" class="mp-mapa-box"></div>
          <div class="mp-coords" id="mpCoordsTexto">Sin ubicación marcada</div>
        </div>

      </div>

      <!-- Precio mostrado automáticamente, sin input editable -->
      <div class="form-group">
        <label>Total a cobrar</label>
        <div class="mp-precio-box">
          <i class="fas fa-dollar-sign"></i>
          <span id="mpPrecioMostrado" class="mp-precio-hint">Selecciona una membresía</span>
        </div>
      </div>

      <div class="form-group">
        <label>Método de pago</label>
        <div class="select-wrap">
          <i class="fas fa-credit-card"></i>
          <select id="mpMetodo">
            <option value="manual">Manual / Efectivo</option>
            <option value="transferencia">Transferencia</option>
            <option value="nequi">Nequi</option>
            <option value="daviplata">Daviplata</option>
          </select>
          <i class="fas fa-chevron-down select-arrow"></i>
        </div>
      </div>

      <button class="btn-confirmar-pago" id="btnConfirmarPago">
        <i class="fas fa-check"></i> Confirmar pago
      </button>

    </div>
  </div>
</div>

<!-- ══ TOAST ═════════════════════════════════════════════════ -->
<div class="toast" id="toast">
  <i class="fas fa-check-circle"></i>
  <span id="toastMsg">Cambio guardado</span>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="../../js/admin/pagos_admin.js"></script>
</body>
</html>