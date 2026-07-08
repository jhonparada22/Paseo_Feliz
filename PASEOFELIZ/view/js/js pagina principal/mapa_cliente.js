// ═══════════════════════════════════════════════════════════════
// RUTA BASE AL MODELO PHP (mismo patrón que mapa_admin.js / mapa_paseador.js)
// ═══════════════════════════════════════════════════════════════
const API = '../../model/';

// ═══════════════════════════════════════════════════════════════
// ESTADO GLOBAL
// ═══════════════════════════════════════════════════════════════
let map, baseLayer, satLayer;
let paseadorMarker, trailLine, geofenceCircle, plannedRouteLine;
let routeMarkers = [];
let usingSat = false, geofenceActive = true;
let elapsedSec = 0, distKm = 0;
let lastLat = null, lastLng = null;
let paseadorLat = 7.8939, paseadorLng = -72.5078;
let trailCoords = [];
let timeline = [];
let alertas = [];
let progreso = 0;
let updateCounter = 0;
let arrivalNotified = false, deliveryNotified = false;
let ultimaNotifId = 0;
let primerCargaNotifs = true;

let MI_USUARIO_ID = null;
let RUTA = { id: null, id_paseador: null, paseador: '', paradas: [] };
let paradaObjetivo = null; // próxima parada PROPIA (recogida/entrega) pendiente

let gpsInterval = null, notifInterval = null, timerInterval = null;

// ═══════════════════════════════════════════════════════════════
// INIT MAPA (misma vista/capas que el resto de mapas de la app)
// ═══════════════════════════════════════════════════════════════
function initMap() {
    map = L.map('map', { zoomControl: false, attributionControl: false })
        .setView([paseadorLat, paseadorLng], 15);

    baseLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
    satLayer = L.tileLayer(
        'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        { maxZoom: 19 }
    );

    trailLine = L.polyline(trailCoords, { color: '#25D366', weight: 4, opacity: .85 }).addTo(map);

    paseadorMarker = L.marker([paseadorLat, paseadorLng], {
        icon: crearIconoPaseador(), zIndexOffset: 1000,
    }).addTo(map);

    cargarRutaCliente();
    startTimer();
}

function crearIconoPaseador() {
    return L.divIcon({
        html: `<div style="
      width:46px;height:46px;border-radius:50%;
      background:linear-gradient(135deg,#3E72A6,#2c5282);
      border:4px solid #fff;
      display:flex;flex-direction:column;align-items:center;justify-content:center;
      box-shadow:0 4px 16px rgba(62,114,166,.5);position:relative;">
      <div style="font-size:1.1rem">🚶</div>
      <div style="position:absolute;bottom:-2px;right:-2px;
        width:13px;height:13px;border-radius:50%;
        background:#25D366;border:2px solid #fff;"></div>
    </div>`,
        className: '', iconSize: [46, 46], iconAnchor: [23, 46],
    });
}

// ═══════════════════════════════════════════════════════════════
// CARGAR RUTA DEL CLIENTE
// ═══════════════════════════════════════════════════════════════
function cargarRutaCliente() {
    fetch(API + 'obtener_ruta.php?modo=cliente')
        .then(r => r.json())
        .then(data => {
            if (!data.success) { mostrarSinPaseo(); return; }
            MI_USUARIO_ID = data.yo;

            if (!data.ruta) { mostrarSinPaseo(); return; }

            const r = data.ruta;
            RUTA.id = r.id_ruta;
            RUTA.id_paseador = r.id_paseador;
            RUTA.paseador = r.paseador;
            RUTA.paradas = r.paradas;

            // Info del paseador (cabecera)
            const iniciales = (r.paseador || '--').trim().split(/\s+/).map(x => x[0]).slice(0, 2).join('').toUpperCase();
            document.getElementById('wrAvatar').textContent = iniciales || '--';
            document.getElementById('wrName').textContent = r.paseador || 'Paseador';

            const total = r.paradas.length;
            const completadas = r.paradas.filter(p => p.estado === 'completada').length;
            document.getElementById('wrSub').textContent = `${estadoRutaLabel(r.estado)} · ${completadas}/${total} paradas`;

            // Parada propia (para el nombre de cabecera y el tab de info de mascota)
            const propia = r.paradas.find(p => p.cliente && p.id_usuario_cliente === MI_USUARIO_ID);
            document.getElementById('phPetName').textContent = propia
                ? `${propia.cliente.mascota} está de paseo`
                : 'Paseo en curso';
            if (propia) actualizarTabInfo(propia, r.estado);

            renderRutaPlaneada();
            buscarParadaObjetivo();
            actualizarProgreso();

            document.getElementById('statParadas') && (document.getElementById('statParadas').textContent = `${completadas}/${total}`);

            if (r.paradas.length) {
                map.fitBounds(r.paradas.map(p => [p.lat, p.lng]), { padding: [60, 40] });
            }

            iniciarPolling();
        })
        .catch(() => mostrarSinPaseo());
}

function estadoRutaLabel(estado) {
    return { pendiente: 'Por iniciar', en_curso: 'Paseo en curso', pausada: 'Paseo pausado', finalizada: 'Paseo finalizado', cancelada: 'Paseo cancelado' }[estado] || estado;
}

function mostrarSinPaseo() {
    document.getElementById('phPetName').textContent = 'Sin paseo programado';
    document.getElementById('wrName').textContent = 'Sin paseo activo';
    document.getElementById('wrSub').textContent = 'No tienes ningún paseo programado para hoy';
    document.getElementById('etaVal').textContent = '—';
    document.getElementById('etaDist').textContent = 'Sin información disponible';
    document.getElementById('progressFill').style.width = '0%';
    document.getElementById('progPct').textContent = '0%';
    document.getElementById('timelineList').innerHTML =
        '<div style="text-align:center;color:var(--muted);font-size:.8rem;padding:16px 0">No hay actividad para mostrar todavía.</div>';
}

function actualizarTabInfo(parada, estadoRuta) {
    const c = parada.cliente;
    document.getElementById('tabInfoPetName').textContent = c.mascota || 'Mascota';
    document.getElementById('tabInfoPetSub').textContent = `Dueño: ${c.nombre || '—'}`;
    document.getElementById('pigDueno').textContent = c.nombre || '—';
    document.getElementById('pigTelefono').textContent = c.telefono || 'No registrado';
    document.getElementById('pigMascota').textContent = c.mascota || '—';
    document.getElementById('pigEstado').textContent = estadoRutaLabel(estadoRuta);
    document.getElementById('petNoteBody').textContent = c.biografia || c.notas || 'Sin notas registradas.';
}

// ═══════════════════════════════════════════════════════════════
// DIBUJAR RUTA PLANEADA (respeta la privacidad de otros clientes)
// ═══════════════════════════════════════════════════════════════
function renderRutaPlaneada() {
    if (plannedRouteLine) map.removeLayer(plannedRouteLine);
    routeMarkers.forEach(m => map.removeLayer(m));
    routeMarkers = [];

    const coords = RUTA.paradas.map(p => [p.lat, p.lng]);
    if (coords.length > 1) {
        plannedRouteLine = L.polyline(coords, { color: '#3E72A6', weight: 3, opacity: .25, dashArray: '8 6' }).addTo(map);
    }

    RUTA.paradas.forEach(p => {
        const esMia = p.id_usuario_cliente === MI_USUARIO_ID;
        let icon, popup;

        if (esMia) {
            icon = L.divIcon({
                html: `<div style="font-size:1.5rem;filter:drop-shadow(0 2px 6px rgba(0,0,0,.35))">🏠</div>`,
                className: '', iconSize: [32, 32], iconAnchor: [16, 28],
            });
            popup = `<b>${p.addr}</b><br>Tu dirección`;
        } else if (p.tipo === 'paseo') {
            icon = L.divIcon({ html: `<div style="font-size:1.3rem">🌿</div>`, className: '', iconSize: [28, 28], iconAnchor: [14, 24] });
            popup = 'Zona de paseo';
        } else {
            // Parada de otro cliente: no se revela su dirección ni su nombre
            icon = L.divIcon({
                html: `<div style="width:14px;height:14px;border-radius:50%;background:#94a3b8;border:2px solid #fff"></div>`,
                className: '', iconSize: [14, 14], iconAnchor: [7, 7],
            });
            popup = 'Otra parada de la ruta';
        }

        routeMarkers.push(L.marker([p.lat, p.lng], { icon }).addTo(map).bindPopup(popup));
    });
}

function buscarParadaObjetivo() {
    paradaObjetivo = RUTA.paradas.find(p =>
        p.id_usuario_cliente === MI_USUARIO_ID && (p.estado === 'pendiente' || p.estado === 'llegada')
    ) || null;
    actualizarGeofence();
}

function actualizarGeofence() {
    if (geofenceCircle) { map.removeLayer(geofenceCircle); geofenceCircle = null; }
    if (!paradaObjetivo) return;
    geofenceCircle = L.circle([paradaObjetivo.lat, paradaObjetivo.lng], {
        radius: 300, color: '#3E72A6', fillColor: '#3E72A6', fillOpacity: .08, weight: 2, dashArray: '6 4',
    });
    if (geofenceActive) geofenceCircle.addTo(map);
}

// ═══════════════════════════════════════════════════════════════
// POLLING: GPS del paseador (cada 5s) + notificaciones (cada 8s)
// ═══════════════════════════════════════════════════════════════
function iniciarPolling() {
    if (gpsInterval || notifInterval) return; // ya estaba corriendo
    actualizarGPSReal();
    cargarNotificaciones();
    gpsInterval = setInterval(actualizarGPSReal, 5000);
    notifInterval = setInterval(cargarNotificaciones, 8000);
}

function actualizarGPSReal() {
    if (!RUTA.id_paseador) return;
    fetch(API + `obtener_gps.php?id_paseador=${RUTA.id_paseador}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.gps) return;
            const { lat, lng, velocidad } = data.gps;

            paseadorLat = lat; paseadorLng = lng;
            paseadorMarker.setLatLng([lat, lng]);
            trailCoords.push([lat, lng]);
            if (trailCoords.length > 100) trailCoords.shift();
            trailLine.setLatLngs(trailCoords);

            if (lastLat !== null) {
                distKm += haversine(lastLat, lastLng, lat, lng);
                document.getElementById('statDist').textContent = distKm.toFixed(2) + ' km';
            }
            lastLat = lat; lastLng = lng;

            updateCounter++;
            const lastUpdateEl = document.getElementById('lastUpdate');
            if (lastUpdateEl) lastUpdateEl.textContent = Math.min(updateCounter, 5);

            if (paradaObjetivo) {
                const dist = haversine(lat, lng, paradaObjetivo.lat, paradaObjetivo.lng);
                const velKmh = velocidad > 0.5 ? velocidad : 4.5; // fallback: ritmo caminando
                const etaMin = Math.max(1, Math.round((dist / velKmh) * 60));
                document.getElementById('etaVal').textContent = `En aprox. ${etaMin} min`;
                document.getElementById('etaDist').textContent = `📍 ${dist.toFixed(2)} km de tu casa`;
            } else {
                document.getElementById('etaVal').textContent = '✅ Sin paradas pendientes';
                document.getElementById('etaDist').textContent = 'Tu mascota ya fue atendida';
            }
        })
        .catch(() => { /* sin conexión, se reintenta en el próximo tick */ });
}

function actualizarProgreso() {
    const total = RUTA.paradas.length;
    if (!total) return;
    const completadas = RUTA.paradas.filter(p => p.estado === 'completada').length;
    progreso = Math.round(completadas / total * 100);
    document.getElementById('progressFill').style.width = progreso + '%';
    document.getElementById('progPct').textContent = progreso + '%';
}

// ═══════════════════════════════════════════════════════════════
// NOTIFICACIONES REALES → timeline + alertas + push/overlay
// ═══════════════════════════════════════════════════════════════
function cargarNotificaciones() {
    fetch(API + 'obtener_notificaciones.php')
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const notifs = data.notificaciones.slice().reverse(); // orden cronológico ascendente

            if (primerCargaNotifs) {
                notifs.forEach(n => registrarNotificacion(n, false));
                primerCargaNotifs = false;
                if (notifs.length) ultimaNotifId = Math.max(...notifs.map(n => n.id));
                renderTimeline(); renderAlertas();
                return;
            }

            const nuevas = notifs.filter(n => n.id > ultimaNotifId);
            if (!nuevas.length) return;
            nuevas.forEach(n => registrarNotificacion(n, true));
            ultimaNotifId = Math.max(...notifs.map(n => n.id));

            renderTimeline(); renderAlertas();

            // Refrescar estado de la ruta: una notificación implica avance real del paseo
            cargarRutaCliente();
        })
        .catch(() => { });
}

function registrarNotificacion(n, disparar) {
    const iconos = { proximidad_recogida: '🏃', proximidad_entrega: '🏠', llegada_parada: '📍', sistema: '🔔' };
    const emoji = iconos[n.tipo] || '🔔';
    const hora = new Date(n.fecha).toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' });

    timeline.push({ emoji, title: n.mensaje, sub: '', time: hora, color: '#25D366' });
    alertas.push({ tipo: 'success', emoji, title: n.mensaje, msg: '', time: hora });

    if (!disparar) return;

    if (n.tipo === 'proximidad_recogida' && !arrivalNotified) {
        arrivalNotified = true;
        showPush('arrival', `🐾 ${RUTA.paseador || 'El paseador'} está llegando`, n.mensaje, [
            { label: 'Ver en mapa', action: () => { if (paradaObjetivo) map.setView([paradaObjetivo.lat, paradaObjetivo.lng], 17); } },
            { label: 'OK, gracias', primary: true, action: closePush },
        ]);
        document.getElementById('aoEmoji').textContent = '🏃🐶';
        document.getElementById('aoTitle').textContent = `¡${RUTA.paseador || 'El paseador'} está llegando!`;
        document.getElementById('aoMsg').textContent = n.mensaje;
        setTimeout(() => document.getElementById('arrivalOverlay').classList.add('show'), 800);
    } else if (n.tipo === 'proximidad_entrega' && !deliveryNotified) {
        deliveryNotified = true;
        showPush('delivery', '✅ Tu mascota está por regresar', n.mensaje);
        document.getElementById('aoEmoji').textContent = '🎉🐶';
        document.getElementById('aoTitle').textContent = '¡Tu mascota está por regresar!';
        document.getElementById('aoMsg').textContent = n.mensaje;
        document.getElementById('arrivalOverlay').classList.add('show');
    } else if (n.tipo === 'llegada_parada') {
        showPush('update', '📍 Actualización del paseo', n.mensaje);
    }
}

// ═══════════════════════════════════════════════════════════════
// PUSH NOTIFICATIONS (misma UI que el diseño original)
// ═══════════════════════════════════════════════════════════════
let pushTimer;
function showPush(tipo, title, msg = '', actions = []) {
    const map2 = {
        arrival: { icon: '🏠 🐾', cls: 'arrival' },
        delivery: { icon: '✅ 🐾', cls: 'delivery' },
        update: { icon: '📍', cls: 'update' },
    };
    const t = map2[tipo] || map2.update;
    document.getElementById('pnIcon').textContent = t.icon;
    document.getElementById('pnIcon').className = `pn-icon ${t.cls}`;
    document.getElementById('pnTitle').textContent = title;
    document.getElementById('pnMsg').textContent = msg || '';
    document.getElementById('pnTime').textContent = 'Ahora mismo';

    const actEl = document.getElementById('pnActions');
    actEl.innerHTML = '';
    actions.forEach(a => {
        const btn = document.createElement('button');
        btn.className = `pna-btn ${a.primary ? 'primary' : ''}`;
        btn.textContent = a.label;
        btn.onclick = () => { closePush(); if (a.action) a.action(); };
        actEl.appendChild(btn);
    });
    document.getElementById('pushNotif').classList.add('show');
    clearTimeout(pushTimer);
    pushTimer = setTimeout(closePush, 5500);
}
function closePush() { document.getElementById('pushNotif').classList.remove('show'); }

// ═══════════════════════════════════════════════════════════════
// VISTA PREVIA LOCAL (botones "DEMO" del panel — no tocan el servidor)
// ═══════════════════════════════════════════════════════════════
function simularLlegada() {
    showPush('arrival', `🐾 ${RUTA.paseador || 'El paseador'} está llegando`,
        'Vista previa: así se ve un aviso de proximidad.',
        [{ label: 'OK, gracias', primary: true, action: closePush }]);
    document.getElementById('aoEmoji').textContent = '🏃🐶';
    document.getElementById('aoTitle').textContent = `¡${RUTA.paseador || 'El paseador'} está llegando!`;
    document.getElementById('aoMsg').textContent = 'Vista previa del aviso de proximidad.';
    document.getElementById('arrivalOverlay').classList.add('show');
}
function simularEntrega() {
    showPush('delivery', '✅ Vista previa de entrega', 'Así se ve el aviso cuando tu mascota está por regresar.');
    document.getElementById('aoEmoji').textContent = '🎉🐶';
    document.getElementById('aoTitle').textContent = 'Vista previa de entrega';
    document.getElementById('aoMsg').textContent = 'Así se ve el aviso cuando tu mascota está por regresar.';
    document.getElementById('arrivalOverlay').classList.add('show');
}
function closeArrival() {
    const overlay = document.getElementById('arrivalOverlay');
    if (overlay) { overlay.style.display = 'none'; overlay.classList.remove('show'); }
}

// ═══════════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════════
function haversine(la1, lo1, la2, lo2) {
    const R = 6371, dLat = (la2 - la1) * Math.PI / 180, dLon = (lo2 - lo1) * Math.PI / 180;
    const a = Math.sin(dLat / 2) ** 2 + Math.cos(la1 * Math.PI / 180) * Math.cos(la2 * Math.PI / 180) * Math.sin(dLon / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function renderTimeline() {
    const el = document.getElementById('timelineList');
    if (!el) return;
    el.innerHTML = '';
    if (!timeline.length) {
        el.innerHTML = '<div style="text-align:center;color:var(--muted);font-size:.8rem;padding:16px 0">Sin eventos todavía.</div>';
        return;
    }
    timeline.forEach((t, i) => {
        const d = document.createElement('div');
        d.className = 'timeline-item';
        d.innerHTML = `
      <div class="tl-left">
        <div class="tl-dot" style="background:${t.color}20;color:${t.color}">${t.emoji}</div>
        ${i < timeline.length - 1 ? '<div class="tl-line"></div>' : ''}
      </div>
      <div>
        <div class="tl-title">${t.title}</div>
        <div class="tl-sub">${t.sub}</div>
        <div class="tl-time"><i class="fas fa-clock" style="font-size:.6rem"></i> ${t.time}</div>
      </div>`;
        el.appendChild(d);
    });
}

function renderAlertas() {
    const el = document.getElementById('alertasList');
    if (!el) return;
    el.innerHTML = '';
    if (!alertas.length) {
        el.innerHTML = '<div style="text-align:center;color:var(--muted);font-size:.8rem;padding:16px 0">No tienes alertas todavía.</div>';
        return;
    }
    const bg = { success: '#dcfce7', info: '#dbeafe', warning: '#ffedd5', error: '#fee2e2' };
    alertas.slice().reverse().forEach(a => {
        const d = document.createElement('div');
        d.className = 'alert-item';
        d.style.background = bg[a.tipo] || '#f1f5f9';
        d.innerHTML = `
      <div class="alert-icon">${a.emoji}</div>
      <div>
        <div class="al-title">${a.title}</div>
        <div class="al-sub">${a.msg}</div>
        <div class="al-time">${a.time}</div>
      </div>`;
        el.appendChild(d);
    });
}

// ═══════════════════════════════════════════════════════════════
// CONTROLES DEL MAPA
// ═══════════════════════════════════════════════════════════════
function toggleSat() {
    usingSat = !usingSat;
    if (usingSat) { map.removeLayer(baseLayer); satLayer.addTo(map); }
    else { map.removeLayer(satLayer); baseLayer.addTo(map); }
    document.getElementById('btnSat').classList.toggle('active', usingSat);
}
function toggleGeofence() {
    geofenceActive = !geofenceActive;
    if (geofenceCircle) {
        if (geofenceActive) geofenceCircle.addTo(map);
        else map.removeLayer(geofenceCircle);
    }
    document.getElementById('btnGeofence').classList.toggle('active', geofenceActive);
}
function fitAll() {
    const puntos = RUTA.paradas.map(p => [p.lat, p.lng]);
    puntos.push([paseadorLat, paseadorLng]);
    map.fitBounds(puntos, { padding: [60, 40] });
}
function centerOnPaseador() { map.setView([paseadorLat, paseadorLng], 17); }

// ═══════════════════════════════════════════════════════════════
// ACCIONES DE CABECERA (chat/llamada/compartir)
// ═══════════════════════════════════════════════════════════════
function chatConPaseador() { showPush('update', 'Chat', `Abriendo chat con ${RUTA.paseador || 'el paseador'}...`); }
function llamarPaseador() { showPush('update', 'Llamada', `Llamando a ${RUTA.paseador || 'el paseador'}...`); }
function compartirPaseo() { showPush('update', 'Compartir', '¡Comparte el paseo de tu mascota!'); }

// ═══════════════════════════════════════════════════════════════
// TABS
// ═══════════════════════════════════════════════════════════════
document.querySelectorAll('.pt-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.pt-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        ['actividad', 'info', 'alertas'].forEach(id => {
            const el = document.getElementById('tab-' + id);
            if (el) el.style.display = tab.dataset.tab === id ? 'block' : 'none';
        });
    });
});

// ═══════════════════════════════════════════════════════════════
// MENÚ HAMBURGUESA
// ═══════════════════════════════════════════════════════════════
const btnMenu = document.getElementById('btn-menu');
const menuLatente = document.getElementById('menu-latente');
btnMenu.addEventListener('click', () => menuLatente.classList.toggle('show'));
window.addEventListener('click', e => {
    if (!btnMenu.contains(e.target) && !menuLatente.contains(e.target)) menuLatente.classList.remove('show');
});

// ═══════════════════════════════════════════════════════════════
// TIMER (tiempo transcurrido en pantalla, informativo)
// ═══════════════════════════════════════════════════════════════
function startTimer() {
    timerInterval = setInterval(() => {
        elapsedSec++;
        const m = String(Math.floor(elapsedSec / 60)).padStart(2, '0');
        const s = String(elapsedSec % 60).padStart(2, '0');
        const el = document.getElementById('statTime');
        if (el) el.textContent = `${m}:${s}`;
    }, 1000);
}

// ═══════════════════════════════════════════════════════════════
// ARRANQUE
// ═══════════════════════════════════════════════════════════════
setTimeout(() => {
    document.getElementById('splash').classList.add('hide');
    setTimeout(() => document.getElementById('splash').style.display = 'none', 600);
}, 1200);

initMap();

// Reajustar el mapa cuando cambia el tamaño u orientación de la pantalla
// (en móvil el layout pasa a columna y Leaflet necesita recalcular su área)
window.addEventListener('resize', () => {
    if (typeof map !== 'undefined' && map) map.invalidateSize();
});
