
/* ══════════════════════════════════════════════════
   DATOS (en producción vienen del backend PHP)
══════════════════════════════════════════════════ */
const PASEADOR = {
    id: 1, nombre: 'Carlos Rodríguez', telefono: '315 778 8990',
};

const RUTA = {
    id: 42,
    fecha: new Date().toLocaleDateString('es-CO', { weekday: 'long', day: 'numeric', month: 'long' }),
    hora_inicio: '08:00',
    paradas: [
        {
            id: 1, label: 'A', color: '#ef4444',
            addr: 'Calle 7 #0e-94, Motilones',
            lat: 7.8928, lng: -72.5065,
            tipo: 'recogida',
            cliente: { nombre: 'María González', mascota: 'Max', raza: 'Labrador', notas: 'Lleva correa azul. No le gusta el ruido.', color: '#3E72A6' },
            estado: 'pendiente',
        },
        {
            id: 2, label: 'B', color: '#f97316',
            addr: 'Parque Santander, Centro',
            lat: 7.8950, lng: -72.5040,
            tipo: 'paseo', cliente: null, estado: 'pendiente',
        },
        {
            id: 3, label: 'C', color: '#8b5cf6',
            addr: 'Av. Libertadores #10-15, Atalaya',
            lat: 7.9010, lng: -72.5090,
            tipo: 'recogida',
            cliente: { nombre: 'Pedro Ramírez', mascota: 'Coco', raza: 'Poodle', notas: 'Tímido con extraños. Dar tiempo.', color: '#7c3aed' },
            estado: 'pendiente',
        },
        {
            id: 4, label: 'D', color: '#0ea5e9',
            addr: 'Parque La Flora, Cúcuta Norte',
            lat: 7.9100, lng: -72.5000,
            tipo: 'paseo', cliente: null, estado: 'pendiente',
        },
        {
            id: 5, label: 'E', color: '#10b981',
            addr: 'Calle 7 #0e-94, Motilones (entrega)',
            lat: 7.8928, lng: -72.5065,
            tipo: 'entrega',
            cliente: { nombre: 'María González', mascota: 'Max', raza: 'Labrador', notas: 'Confirmar llegada al dueño.', color: '#3E72A6' },
            estado: 'pendiente',
        },
    ],
};

/* ══════════════════════════════════════════════════
   ESTADO
══════════════════════════════════════════════════ */
let map, baseLayer, satelliteLayer, routePolyline, myMarker;
let usingSatellite = false;
let paseoActivo = false;
let paradaActual = 0;
let myLat = 7.8939, myLng = -72.5078;
let watchId = null, timerInterval = null, simInterval = null;
let elapsedSec = 0, distKm = 0;
let lastLat = null, lastLng = null;
let paradasMarkers = [];

/* ══════════════════════════════════════════════════
   INIT MAPA
══════════════════════════════════════════════════ */
function initMap() {
    map = L.map('map', { zoomControl: false, attributionControl: false })
        .setView([myLat, myLng], 15);

    baseLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
    satelliteLayer = L.tileLayer(
        'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        { maxZoom: 19 }
    );

    const myIcon = L.divIcon({
        html: `<div style="
      width:44px;height:44px;border-radius:50%;
      background:linear-gradient(135deg,#3E72A6,#2c5282);
      border:4px solid #fff;
      display:flex;align-items:center;justify-content:center;
      font-size:1.1rem;color:#fff;
      box-shadow:0 4px 14px rgba(62,114,166,.5);position:relative;">
      🚶
      <div style="position:absolute;bottom:-2px;right:-2px;
        width:14px;height:14px;border-radius:50%;
        background:#25D366;border:2px solid #fff;"></div>
    </div>`,
        className: 'custom-paseador-marker', iconSize: [44, 44], iconAnchor: [22, 22],
    });
    myMarker = L.marker([myLat, myLng], { icon: myIcon, zIndexOffset: 1000 }).addTo(map);
    myMarker.bindTooltip('📍 Tu ubicación', { permanent: false, direction: 'top', offset: [0, -24] });

    renderRouteOnMap();
    map.fitBounds(RUTA.paradas.map(p => [p.lat, p.lng]), { padding: [50, 50] });
}

/* ══════════════════════════════════════════════════
   DIBUJAR RUTA EN MAPA
══════════════════════════════════════════════════ */
function renderRouteOnMap() {
    paradasMarkers.forEach(m => map.removeLayer(m));
    paradasMarkers = [];
    if (routePolyline) map.removeLayer(routePolyline);

    routePolyline = L.polyline(RUTA.paradas.map(p => [p.lat, p.lng]), {
        color: '#3E72A6', weight: 4, opacity: .65, dashArray: '10 6',
    }).addTo(map);

    RUTA.paradas.forEach((p, i) => {
        const isDone = p.estado === 'completado';
        const isCurrent = i === paradaActual && paseoActivo;
        const sz = isCurrent ? 40 : 34;
        const icon = L.divIcon({
            html: `<div style="
        width:${sz}px;height:${sz}px;border-radius:50%;
        background:${isDone ? '#94a3b8' : p.color};
        border:${isCurrent ? '4px solid #fff' : '3px solid #fff'};
        display:flex;align-items:center;justify-content:center;
        color:#fff;font-weight:800;font-size:${isCurrent ? '.88rem' : '.75rem'};
        box-shadow:${isCurrent ? '0 0 0 4px ' + p.color + '55, 0 4px 12px rgba(0,0,0,.25)' : '0 3px 8px rgba(0,0,0,.2)'};
        ${isDone ? 'opacity:.55;' : ''}
      ">${isDone ? '✓' : p.label}</div>`,
            className: '', iconSize: [sz, sz], iconAnchor: [sz / 2, sz / 2],
        });
        const tipoLabel = { recogida: '🏠 Recoger mascota', paseo: '🌿 Zona de paseo', entrega: '🏠 Entregar mascota' }[p.tipo] || p.tipo;
        const marker = L.marker([p.lat, p.lng], { icon }).addTo(map)
            .bindPopup(`
        <div style="font-family:'Segoe UI',sans-serif;min-width:180px">
          <div style="font-weight:800;font-size:.9rem;margin-bottom:4px">Punto ${p.label}</div>
          <div style="font-size:.75rem;color:#64748b;margin-bottom:6px">${tipoLabel}</div>
          <div style="font-size:.78rem;font-weight:600">${p.addr}</div>
          ${p.cliente ? `<div style="font-size:.72rem;color:#64748b;margin-top:5px">🐾 ${p.cliente.mascota} · ${p.cliente.nombre}</div>` : ''}
        </div>`, { className: 'route-popup' });
        paradasMarkers.push(marker);
    });
}

/* ══════════════════════════════════════════════════
   RENDER PARADAS (panel)
══════════════════════════════════════════════════ */
function renderParadas() {
    const el = document.getElementById('paradasList');
    el.innerHTML = '';
    RUTA.paradas.forEach((p, i) => {
        const isDone = p.estado === 'completado';
        const isCurrent = i === paradaActual && paseoActivo;
        const tipoLabel = { recogida: '🏠 Recogida', paseo: '🌿 Zona paseo', entrega: '🏠 Entrega' }[p.tipo] || p.tipo;

        let estadoCls = 't-pendiente', estadoLbl = '⏰ Pendiente';
        if (isDone) { estadoCls = 't-hecho'; estadoLbl = '✅ Completado'; }
        if (isCurrent) { estadoCls = 't-actual'; estadoLbl = '📍 Aquí ahora'; }

        const div = document.createElement('div');
        div.className = 'parada-item';
        div.innerHTML = `
      <div class="parada-left">
        <div class="parada-dot" style="background:${isDone ? '#94a3b8' : p.color};${isCurrent ? 'box-shadow:0 0 0 3px ' + p.color + '40' : ''}">
          ${isDone ? '✓' : p.label}
        </div>
        ${i < RUTA.paradas.length - 1 ? '<div class="parada-line"></div>' : ''}
      </div>
      <div class="parada-body" style="opacity:${isDone ? '.5' : '1'}">
        <div class="parada-label">${tipoLabel}</div>
        <div class="parada-addr">${p.addr}</div>
        <div class="parada-meta">
          <span class="p-tag ${estadoCls}">${estadoLbl}</span>
          ${isCurrent ? '<span class="p-tag t-actual">← Próxima</span>' : ''}
        </div>

        ${p.cliente ? `
        <div class="cliente-card">
          <div class="cc-top">
            <div class="cc-av" style="background:${p.cliente.color}">${p.cliente.nombre[0]}</div>
            <div>
              <div class="cc-name">${p.cliente.nombre}</div>
              <div class="cc-sub">🐾 ${p.cliente.mascota} · ${p.cliente.raza}</div>
            </div>
          </div>
          <div class="cc-detail">
            <div class="ccd-item"><div class="ccd-label">Mascota</div><div class="ccd-val">${p.cliente.mascota}</div></div>
            <div class="ccd-item"><div class="ccd-label">Raza</div><div class="ccd-val">${p.cliente.raza}</div></div>
          </div>
          <div style="margin-top:7px;background:#fff;border-radius:7px;padding:7px 9px;font-size:.73rem;color:var(--muted)">
            <i class="fas fa-note-sticky" style="margin-right:4px;color:var(--primary)"></i>${p.cliente.notas}
          </div>
        </div>` : ''}

        ${isCurrent && !isDone ? `
        <div class="parada-actions">
          <button class="p-action primary" onclick="marcarLlegada(${i})">
            <i class="fas fa-map-marker-alt"></i> Llegué
          </button>
          <button class="p-action green" onclick="marcarCompletado(${i})">
            <i class="fas fa-check"></i> ${p.tipo === 'paseo' ? 'Completar' : 'Entregado'}
          </button>
          <button class="p-action" onclick="verEnMapa(${i})">
            <i class="fas fa-route"></i> Ver en mapa
          </button>
        </div>` : ''}

        ${isDone ? `
        <div class="parada-actions">
          <button class="p-action" onclick="verEnMapa(${i})">
            <i class="fas fa-check-circle" style="color:var(--green)"></i> Completado
          </button>
        </div>` : ''}

        ${!isCurrent && !isDone && i > paradaActual ? `
        <div class="parada-actions">
          <button class="p-action" onclick="verEnMapa(${i})">
            <i class="fas fa-map-pin"></i> Ver en mapa
          </button>
        </div>` : ''}
      </div>`;
        el.appendChild(div);
    });
}

/* ══════════════════════════════════════════════════
   ACCIONES DE PARADA
══════════════════════════════════════════════════ */
function marcarLlegada(idx) {
    const p = RUTA.paradas[idx];
    showNotif(`📍 Llegaste a Punto ${p.label}: ${p.addr}`, 'info');
    map.setView([p.lat, p.lng], 17);
    if (p.tipo === 'recogida') showNotif(`🔔 Notificando a ${p.cliente?.nombre} — paseador en camino`, 'warning');
}

function marcarCompletado(idx) {
    RUTA.paradas[idx].estado = 'completado';
    const p = RUTA.paradas[idx];
    if (p.tipo === 'entrega') showNotif(`✅ ${p.cliente?.nombre} notificado — mascota entregada`, 'success');
    else showNotif(`✅ Punto ${p.label} completado`, 'success');

    const next = RUTA.paradas.findIndex((x, i) => i > idx && x.estado !== 'completado');
    if (next !== -1) {
        paradaActual = next;
        map.setView([RUTA.paradas[next].lat, RUTA.paradas[next].lng], 15);
    } else {
        showNotif('🎉 ¡Ruta completada! Buen trabajo', 'success');
        detenerPaseo();
    }
    updateProgress();
    renderParadas();
    renderRouteOnMap();
}

function verEnMapa(idx) {
    const p = RUTA.paradas[idx];
    map.setView([p.lat, p.lng], 17);
    paradasMarkers[idx]?.openPopup();
}

/* ══════════════════════════════════════════════════
   CONTROL PASEO
══════════════════════════════════════════════════ */
function togglePaseo() {
    if (!paseoActivo) iniciarPaseo(); else detenerPaseo();
}

function iniciarPaseo() {
    paseoActivo = true;
    document.getElementById('btnPaseoIcon').className = 'fas fa-pause';
    document.getElementById('btnPaseoLabel').textContent = 'Pausar paseo';
    document.getElementById('btnPaseo').className = 'btn-iniciar pause';
    document.getElementById('statsPanel').style.display = 'grid';
    showNotif('▶️ Paseo iniciado — GPS activo', 'success');
    startGPS(); startTimer();
    renderParadas(); renderRouteOnMap();
}

function detenerPaseo() {
    paseoActivo = false;
    document.getElementById('btnPaseoIcon').className = 'fas fa-play';
    document.getElementById('btnPaseoLabel').textContent = 'Reiniciar paseo';
    document.getElementById('btnPaseo').className = 'btn-iniciar start';
    stopGPS(); stopTimer();
}

/* ══════════════════════════════════════════════════
   GPS REAL + SIMULACIÓN
══════════════════════════════════════════════════ */
function startGPS() {
    if ('geolocation' in navigator) {
        watchId = navigator.geolocation.watchPosition(
            pos => updatePosition(pos.coords.latitude, pos.coords.longitude),
            () => startGPSSimulation(),
            { enableHighAccuracy: true, maximumAge: 3000, timeout: 8000 }
        );
    } else { startGPSSimulation(); }
}

function startGPSSimulation() {
    document.getElementById('gpsLabel').textContent = 'GPS Simulado';
    simInterval = setInterval(() => {
        const target = RUTA.paradas[paradaActual];
        if (!target) return;
        myLat += (target.lat - myLat) * .08;
        myLng += (target.lng - myLng) * .08;
        updatePosition(myLat, myLng);
    }, 2000);
}

function stopGPS() {
    if (watchId !== null) { navigator.geolocation.clearWatch(watchId); watchId = null; }
    if (simInterval) { clearInterval(simInterval); simInterval = null; }
}

function updatePosition(lat, lng) {
    myLat = lat; myLng = lng;
    myMarker.setLatLng([lat, lng]);
    if (lastLat !== null) {
        distKm += haversine(lastLat, lastLng, lat, lng);
        document.getElementById('statDist').textContent = distKm.toFixed(2) + ' km';
    }
    lastLat = lat; lastLng = lng;
    document.getElementById('gpsCoords').textContent = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;

    const target = RUTA.paradas[paradaActual];
    if (target && target.estado === 'pendiente') {
        const dist = haversine(lat, lng, target.lat, target.lng);
        if (dist < 0.2) showNotif(`📍 Llegando a Punto ${target.label} — a ${Math.round(dist * 1000)}m`, 'warning');
    }
}

function centerOnMe() { map.setView([myLat, myLng], 17); }

function fitRoute() {
    const bounds = RUTA.paradas.map(p => [p.lat, p.lng]);
    bounds.push([myLat, myLng]);
    map.fitBounds(bounds, { padding: [50, 50] });
}

/* ══════════════════════════════════════════════════
   TIMER
══════════════════════════════════════════════════ */
function startTimer() {
    timerInterval = setInterval(() => {
        elapsedSec++;
        const m = String(Math.floor(elapsedSec / 60)).padStart(2, '0');
        const s = String(elapsedSec % 60).padStart(2, '0');
        document.getElementById('statTime').textContent = `${m}:${s}`;
    }, 1000);
}
function stopTimer() { clearInterval(timerInterval); }

/* ══════════════════════════════════════════════════
   PROGRESO
══════════════════════════════════════════════════ */
function updateProgress() {
    const total = RUTA.paradas.length;
    const done = RUTA.paradas.filter(p => p.estado === 'completado').length;
    const pct = Math.round(done / total * 100);
    document.getElementById('progressFill').style.width = pct + '%';
    document.getElementById('progressPct').textContent = pct + '%';
    document.getElementById('progressLabel').textContent = `Parada ${done} de ${total}`;
    document.getElementById('statParadas').textContent = `${done}/${total}`;
}

/* ══════════════════════════════════════════════════
   CAPAS MAPA
══════════════════════════════════════════════════ */
function toggleSatellite() {
    usingSatellite = !usingSatellite;
    if (usingSatellite) { map.removeLayer(baseLayer); satelliteLayer.addTo(map); }
    else { map.removeLayer(satelliteLayer); baseLayer.addTo(map); }
    document.getElementById('btnSatellite').classList.toggle('active', usingSatellite);
}
function resetNorth() { showNotif('Mapa orientado al norte', 'info'); }

/* ══════════════════════════════════════════════════
   NOTIFICACIÓN FLOTANTE
══════════════════════════════════════════════════ */
let notifTimer;
function showNotif(msg, type = 'success') {
    const el = document.getElementById('floatNotif');
    document.getElementById('notifMsg').textContent = msg;
    el.className = `float-notif ${type}`;
    document.getElementById('notifIcon').className = {
        success: 'fas fa-check-circle',
        info: 'fas fa-info-circle',
        warning: 'fas fa-triangle-exclamation',
    }[type] || 'fas fa-check-circle';
    el.classList.add('show');
    clearTimeout(notifTimer);
    notifTimer = setTimeout(() => el.classList.remove('show'), 3500);
}

/* ══════════════════════════════════════════════════
   HAVERSINE
══════════════════════════════════════════════════ */
function haversine(la1, lo1, la2, lo2) {
    const R = 6371, dLat = (la2 - la1) * Math.PI / 180, dLon = (lo2 - lo1) * Math.PI / 180;
    const a = Math.sin(dLat / 2) ** 2 + Math.cos(la1 * Math.PI / 180) * Math.cos(la2 * Math.PI / 180) * Math.sin(dLon / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

/* ══════════════════════════════════════════════════
   MENÚ HAMBURGUESA
══════════════════════════════════════════════════ */
document.getElementById('btn-menu').addEventListener('click', () =>
    document.getElementById('menu-latente').classList.toggle('show'));
window.addEventListener('click', e => {
    const btn = document.getElementById('btn-menu');
    const menu = document.getElementById('menu-latente');
    if (!btn.contains(e.target) && !menu.contains(e.target)) menu.classList.remove('show');
});

/* ══════════════════════════════════════════════════
   INIT
══════════════════════════════════════════════════ */
document.getElementById('fechaRuta').textContent = RUTA.fecha;
document.getElementById('tb-paseador-name').textContent = `${PASEADOR.nombre} · Cúcuta`;
initMap();
renderParadas();
updateProgress();
/* ══════════════════════════════════════════════════
   ESCUCHAR INICIO DESDE EL DASHBOARD
══════════════════════════════════════════════════ */
// Al cargar el mapa, verificar si se presionó "Empezar paseos"
document.addEventListener("DOMContentLoaded", function() {
    if (localStorage.getItem('paseoIniciado') === 'true') {
        // Ejecutamos la función nativa que ya tienes para arrancar el paseo
        iniciarPaseo();
        
        // Limpiamos el estado para que no se reinicie solo en futuras cargas
        localStorage.removeItem('paseoIniciado');
        
        // Enfocamos el mapa en la ruta global automáticamente
        fitRoute();
    }
});