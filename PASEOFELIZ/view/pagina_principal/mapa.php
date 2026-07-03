<?php include_once '../../controller/control_acceso.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
  <title>Paseo Feliz – Seguimiento de Max</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <link rel="stylesheet" href="../css/principal_css/mapa.css"/>

</head>
<body>

<!-- SPLASH -->
<div class="splash" id="splash">
  <div class="splash-logo">🐾</div>
  <div class="splash-title">Paseo Feliz</div>
  <div class="splash-sub">Conectando con la ubicación de Max...</div>
  <div class="splash-spinner"></div>
</div>

<!-- SHELL PRINCIPAL -->
<div class="app-shell">

  <!-- ══ SIDEBAR ══ -->
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
          <a href="./sub_menu/configuracion.php">
            <li><i class="fas fa-gear"></i><span>Configuración</span></li>
          </a>
          <a href="../../controller/logout.php">
            <li><i class="fas fa-right-from-bracket"></i><span>Cerrar sesión</span></li>
          </a>
        </ul>
      </nav>
    </div>

    <ul class="nav-links">
      <li>
        <a href="inicio.php">
          <i class="fas fa-paw"></i>
          <span>Servicios</span>
        </a>
      </li>
      <li>
        <a href="Chat.php">
          <i class="far fa-comment-alt"></i>
          <span>Chat</span>
        </a>
      </li>
      <li class="active">
        <a href="#">
          <i class="fas fa-map-marker-alt"></i>
          <span>Mapa</span>
        </a>
      </li>
      <li>
        <a href="adopcion.php">
          <i class="fas fa-bone"></i>
          <span>Adopción</span>
        </a>
      </li>
      <li>
        <a href="usuario.php">
          <i class="fas fa-user"></i>
          <span>Usuario</span>
        </a>
      </li>
    </ul>
  </nav>

  <!-- ══ PANEL DE INFO ══ -->
  <div class="info-panel">

    <!-- Cabecera con paseador -->
    <div class="panel-header">
      <div class="ph-top">
        <button class="ph-back" onclick="window.history.back()" title="Volver">
          <i class="fas fa-arrow-left"></i>
        </button>
        <div class="ph-pet-chip">
          <div class="ph-pet-icon">🐶</div>
          <div>
            <div class="ph-pet-name" id="phPetName">Cargando paseo...</div>
            <div class="ph-pet-live">
              <span class="live-pulse"></span>
              Hace <span id="lastUpdate">3</span>s
            </div>
          </div>
        </div>
        <button class="ph-share" onclick="compartirPaseo()" title="Compartir">
          <i class="fas fa-share-nodes"></i>
        </button>
      </div>

      <!-- Walker row -->
      <div class="walker-row">
        <div class="wr-avatar">
          <span id="wrAvatar">--</span>
          <div class="wr-dot"></div>
        </div>
        <div class="wr-info">
          <div class="wr-name" id="wrName">Cargando...</div>
          <div class="wr-sub">
            <i class="fas fa-person-walking" style="font-size:.62rem"></i>
            <span id="wrSub">Esperando datos del paseo...</span>
          </div>
        </div>
        <div class="wr-actions">
          <button class="wr-btn chat" title="Chat"
            onclick="chatConPaseador()">
            <i class="fas fa-comment-alt"></i>
          </button>
          <button class="wr-btn call" title="Llamar"
            onclick="llamarPaseador()">
            <i class="fas fa-phone"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- ETA -->
    <div class="eta-block">
      <div class="eta-icon"><i class="fas fa-route"></i></div>
      <div style="flex:1">
        <div class="eta-lbl">Llega a tu dirección</div>
        <div class="eta-val" id="etaVal">En aprox. 12 min</div>
        <div class="eta-dist" id="etaDist">📍 1.4 km de tu casa</div>
      </div>
      <div style="font-size:1.3rem">🏠</div>
    </div>

    <!-- Progreso -->
    <div class="progress-block">
      <div class="pb-labels">
        <span>Inicio del paseo</span>
        <span id="progPct">35%</span>
      </div>
      <div class="pb-track">
        <div class="pb-fill" id="progressFill" style="width:35%"></div>
      </div>
    </div>

    <!-- Tabs mascota -->
    <div class="pet-tabs">
      <div class="pt-tab active" data-tab="actividad">📍 Actividad</div>
      <div class="pt-tab" data-tab="info">🐾 Info</div>
      <div class="pt-tab" data-tab="alertas">🔔 Alertas</div>
    </div>

    <!-- Contenido scrollable -->
    <div class="panel-scroll" id="panelScroll">

      <!-- TAB: ACTIVIDAD -->
      <div id="tab-actividad">
        <div class="pet-stats" style="margin-top:4px">
          <div class="pet-stat">
            <div class="ps-emoji">⏱️</div>
            <div class="ps-val" id="statTime">00:00</div>
            <div class="ps-lbl">Tiempo</div>
          </div>
          <div class="pet-stat">
            <div class="ps-emoji">🏃</div>
            <div class="ps-val" id="statDist">0.0 km</div>
            <div class="ps-lbl">Distancia</div>
          </div>
          <div class="pet-stat">
            <div class="ps-emoji">🌡️</div>
            <div class="ps-val">26°C</div>
            <div class="ps-lbl">Clima</div>
          </div>
        </div>

        <div class="sec-lbl">Recorrido en vivo</div>
        <div class="mini-trail-box" id="miniTrailInfo">
          <div style="text-align:center">
            <div style="font-size:1.3rem;margin-bottom:4px">🗺️</div>
            <div>Sigue la ruta en el mapa principal</div>
          </div>
        </div>

        <div class="sec-lbl">Eventos del paseo</div>
        <div id="timelineList"></div>
      </div>

      <!-- TAB: INFO MASCOTA -->
      <div id="tab-info" style="display:none">
        <div style="text-align:center;margin-bottom:14px;margin-top:4px">
          <div style="font-size:2.5rem;margin-bottom:6px">🐶</div>
          <div style="font-size:1rem;font-weight:800" id="tabInfoPetName">Mascota</div>
          <div style="font-size:.76rem;color:var(--muted);margin-top:2px" id="tabInfoPetSub">—</div>
        </div>
        <div class="pet-info-grid">
          <div class="pig-item"><div class="pig-lbl">Dueño</div><div class="pig-val" id="pigDueno">—</div></div>
          <div class="pig-item"><div class="pig-lbl">Teléfono</div><div class="pig-val" id="pigTelefono">—</div></div>
          <div class="pig-item"><div class="pig-lbl">Mascota</div><div class="pig-val" id="pigMascota">—</div></div>
          <div class="pig-item"><div class="pig-lbl">Estado</div><div class="pig-val" id="pigEstado">—</div></div>
        </div>
        <div class="pet-note">
          <div class="pet-note-title"><i class="fas fa-note-sticky"></i> Notas de la mascota</div>
          <div class="pet-note-body" id="petNoteBody">
            Sin notas registradas.
          </div>
        </div>
      </div>

      <!-- TAB: ALERTAS -->
      <div id="tab-alertas" style="display:none">
        <div id="alertasList"></div>
      </div>

    </div><!-- /panel-scroll -->

    <!-- Demo controls -->
    <div class="demo-bar">
      <span>DEMO</span>
      <button class="demo-btn warn" onclick="simularLlegada()">
        <i class="fas fa-bell"></i> Simular llegada
      </button>
      <button class="demo-btn success" onclick="simularEntrega()">
        <i class="fas fa-dog"></i> Simular entrega
      </button>
    </div>

  </div><!-- /info-panel -->

  <!-- ══ ÁREA DEL MAPA ══ -->
  <div class="map-area">
    <div id="map"></div>

    <!-- FABs -->
    <div class="fab-group">
      <button class="fab-btn" id="btnSat" title="Satélite" onclick="toggleSat()">
        <i class="fas fa-layer-group"></i>
      </button>
      <button class="fab-btn" id="btnGeofence" title="Zona de alerta" onclick="toggleGeofence()">
        <i class="fas fa-bullseye"></i>
      </button>
      <button class="fab-btn" title="Ver ruta completa" onclick="fitAll()">
        <i class="fas fa-expand"></i>
      </button>
    </div>
    <button class="fab-locate" id="fabLocate" onclick="centerOnPaseador()" title="Centrar en paseador">
      <i class="fas fa-location-crosshairs"></i>
    </button>
  </div><!-- /map-area -->

</div><!-- /app-shell -->

<!-- NOTIFICACIÓN PUSH -->
<div class="push-notif" id="pushNotif">
  <div class="pn-accent"></div>
  <div class="pn-body">
    <div class="pn-icon" id="pnIcon">🔔</div>
    <div class="pn-content">
      <div class="pn-app">Paseo Feliz</div>
      <div class="pn-title" id="pnTitle">Notificación</div>
      <div class="pn-msg" id="pnMsg">Mensaje</div>
      <div class="pn-time" id="pnTime">Ahora</div>
    </div>
    <button class="pn-close" onclick="closePush()"><i class="fas fa-times"></i></button>
  </div>
  <div class="pn-actions" id="pnActions"></div>
</div>

<!-- OVERLAY LLEGADA -->
<div class="arrival-overlay" id="arrivalOverlay">
  <div class="ao-card">
    <span class="ao-emoji" id="aoEmoji">🐾</span>
    <div class="ao-title" id="aoTitle">¡Max está llegando!</div>
    <div class="ao-msg"   id="aoMsg">Carlos está a menos de 200 metros de tu casa. Prepárate para recibir a Max.</div>
    <button class="ao-btn" onclick="closeArrival()">¡Entendido!</button>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="../js/js pagina principal/mapa_cliente.js"></script>
</body>
</html>