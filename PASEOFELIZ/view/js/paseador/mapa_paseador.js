// ═══════════════════════════════════════════════════════════════
// RUTA BASE AL MODELO PHP
// La página vive en /view/vistas/paseador/ -> subir 3 niveles hasta /model/
// ═══════════════════════════════════════════════════════════════
const API = '../../../model/';

// ═══════════════════════════════════════════════════════════════
// ESTADO GLOBAL (igual que antes)
// ═══════════════════════════════════════════════════════════════
let map, baseLayer, satelliteLayer, routePolyline, myMarker;
let usingSatellite = false;
let paseoActivo    = false;
let paradaActual   = 0;
let myLat = 7.8939, myLng = -72.5078;
let watchId = null, timerInterval = null, simInterval = null, gpsInterval = null;
let elapsedSec = 0, distKm = 0;
let lastLat = null, lastLng = null;
let paradasMarkers = [];

// ← Antes: const PASEADOR = {...} y const RUTA = {...} hardcodeados
// Ahora se cargan del backend:
let PASEADOR = { id: null, nombre: 'Cargando...', telefono: '' };
let RUTA     = { id: null, fecha: '', hora_inicio: '', paradas: [] };

// ═══════════════════════════════════════════════════════════════
// CARGAR RUTA DEL DÍA DESDE EL BACKEND
// ═══════════════════════════════════════════════════════════════
function cargarRutaDeHoy() {
    fetch(API + 'obtener_ruta.php?modo=hoy')
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.ruta) {
                mostrarSinRuta();
                return;
            }
            const r = data.ruta;

            // Llenar PASEADOR
            PASEADOR.id     = r.id_paseador;
            PASEADOR.nombre = r.paseador;
            document.getElementById('tb-paseador-name').textContent = `${r.paseador} · Cúcuta`;

            // Llenar RUTA
            RUTA.id         = r.id_ruta;
            RUTA.hora_inicio = r.hora_inicio;
            RUTA.fecha      = new Date(r.fecha + 'T00:00:00').toLocaleDateString('es-CO', {
                weekday: 'long', day: 'numeric', month: 'long',
            });

            // Mapear paradas del backend al formato que usa el front
            const colores = ['#ef4444', '#f97316', '#8b5cf6', '#0ea5e9', '#10b981'];
            RUTA.paradas = r.paradas.map((p, i) => ({
                id:     p.id,
                label:  p.label,
                color:  colores[i % colores.length],
                addr:   p.addr,
                lat:    p.lat,
                lng:    p.lng,
                tipo:   p.tipo,
                estado: p.estado === 'completada' ? 'completado' : p.estado,
                cliente: p.cliente ? {
                    nombre:  p.cliente.nombre,
                    mascota: p.cliente.mascota,
                    raza:    '—',
                    notas:   '',
                    color:   colores[i % colores.length],
                } : null,
            }));

            // Encontrar la primera parada no completada
            paradaActual = RUTA.paradas.findIndex(p => p.estado === 'pendiente' || p.estado === 'llegada');
            if (paradaActual < 0) paradaActual = 0;

            // Actualizar resumen
            document.getElementById('fechaRuta').textContent = RUTA.fecha;
            actualizarResumen(r);

            // Dibujar mapa y panel
            renderRouteOnMap();
            renderParadas();
            updateProgress();

            if (RUTA.paradas.length) {
                map.fitBounds(RUTA.paradas.map(p => [p.lat, p.lng]), { padding: [50, 50] });
            }
        })
        .catch(() => mostrarSinRuta());
}

function mostrarSinRuta() {
    document.getElementById('paradasList').innerHTML = `
        <div style="text-align:center;padding:24px;color:var(--muted)">
            <i class="fas fa-calendar-times" style="font-size:2rem;display:block;margin-bottom:8px;color:#e2e8f0"></i>
            <p style="font-size:.85rem">No tienes rutas asignadas para hoy.</p>
        </div>`;
    document.getElementById('fechaRuta').textContent = 'Sin ruta asignada';
}

function actualizarResumen(r) {
    // Actualizar la sección de resumen con datos reales
    const grid = document.querySelector('.resumen-grid');
    if (!grid || !r) return;
    const totalMascotas = r.paradas.filter(p => p.cliente).length;
    grid.innerHTML = `
        <div class="rg-item"><div class="rg-lbl">Mascotas</div><div class="rg-val">${totalMascotas} 🐾</div></div>
        <div class="rg-item"><div class="rg-lbl">Duración est.</div><div class="rg-val">${r.duracion_estimada_min || '—'} min</div></div>
        <div class="rg-item"><div class="rg-lbl">Distancia est.</div><div class="rg-val">${r.distancia_estimada_km ? parseFloat(r.distancia_estimada_km).toFixed(1) + ' km' : '—'}</div></div>
        <div class="rg-item"><div class="rg-lbl">Inicio</div><div class="rg-val">${r.hora_inicio ? r.hora_inicio.substring(0,5) : '—'}</div></div>
    `;
}

// ═══════════════════════════════════════════════════════════════
// INIT MAPA (sin cambios visuales, agrega carga de ruta real)
// ═══════════════════════════════════════════════════════════════
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
            border:4px solid #fff;display:flex;align-items:center;justify-content:center;
            font-size:1.1rem;color:#fff;box-shadow:0 4px 14px rgba(62,114,166,.5);position:relative;">
            🚶
            <div style="position:absolute;bottom:-2px;right:-2px;
                width:14px;height:14px;border-radius:50%;background:#25D366;border:2px solid #fff;"></div>
        </div>`,
        className: 'custom-paseador-marker', iconSize: [44, 44], iconAnchor: [22, 22],
    });
    myMarker = L.marker([myLat, myLng], { icon: myIcon, zIndexOffset: 1000 }).addTo(map);
    myMarker.bindTooltip('📍 Tu ubicación', { permanent: false, direction: 'top', offset: [0, -24] });

    // Cargar ruta real desde el backend
    cargarRutaDeHoy();
}

// ═══════════════════════════════════════════════════════════════
// DIBUJAR RUTA EN MAPA (sin cambios visuales)
// ═══════════════════════════════════════════════════════════════
function renderRouteOnMap() {
    paradasMarkers.forEach(m => map.removeLayer(m));
    paradasMarkers = [];
    if (routePolyline) map.removeLayer(routePolyline);
    if (!RUTA.paradas.length) return;

    routePolyline = L.polyline(RUTA.paradas.map(p => [p.lat, p.lng]), {
        color: '#3E72A6', weight: 4, opacity: .65, dashArray: '10 6',
    }).addTo(map);

    RUTA.paradas.forEach((p, i) => {
        const isDone    = p.estado === 'completado';
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

// ═══════════════════════════════════════════════════════════════
// RENDER PARADAS (panel) — sin cambios visuales
// ═══════════════════════════════════════════════════════════════
function renderParadas() {
    const el = document.getElementById('paradasList');
    el.innerHTML = '';
    RUTA.paradas.forEach((p, i) => {
        const isDone    = p.estado === 'completado';
        const isLlegada = p.estado === 'llegada';
        const isCurrent = i === paradaActual && paseoActivo;
        const tipoLabel = { recogida: '🏠 Recogida', paseo: '🌿 Zona paseo', entrega: '🏠 Entrega' }[p.tipo] || p.tipo;

        let estadoCls = 't-pendiente', estadoLbl = '⏰ Pendiente';
        if (isDone)    { estadoCls = 't-hecho';  estadoLbl = '✅ Completado'; }
        if (isLlegada) { estadoCls = 't-actual'; estadoLbl = '📍 Llegado'; }
        if (isCurrent && !isDone) { estadoCls = 't-actual'; estadoLbl = '📍 Aquí ahora'; }

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
                            <div class="cc-sub">🐾 ${p.cliente.mascota}</div>
                        </div>
                    </div>
                </div>` : ''}
                <div style="display:flex;gap:6px;margin-top:6px;flex-wrap:wrap">
                    <button class="parada-btn" onclick="verEnMapa(${i})">
                        <i class="fas fa-eye"></i> Ver
                    </button>
                    ${isCurrent && !isDone && paseoActivo ? `
                    <button class="parada-btn btn-completar" onclick="completarParada(${i})">
                        <i class="fas fa-check"></i> Marcar completada
                    </button>` : ''}
                </div>
            </div>
        `;
        el.appendChild(div);
    });
}

function verEnMapa(idx) {
    const p = RUTA.paradas[idx];
    map.setView([p.lat, p.lng], 17);
    paradasMarkers[idx]?.openPopup();
}

// ═══════════════════════════════════════════════════════════════
// MARCAR PARADA COMPLETADA MANUALMENTE
// ═══════════════════════════════════════════════════════════════
function completarParada(idx) {
    const parada = RUTA.paradas[idx];
    if (!parada) return;

    fetch(API + 'marcar_parada.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_parada: parada.id, accion: 'completar' }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            RUTA.paradas[idx].estado = 'completado';
            paradaActual = RUTA.paradas.findIndex((p, i) => i > idx && p.estado === 'pendiente');
            if (paradaActual < 0) paradaActual = idx; // último punto
            renderParadas();
            renderRouteOnMap();
            updateProgress();
            showNotif(`✅ Punto ${parada.label} completado`, 'success');
        }
    })
    .catch(() => {
        // Fallback local si no hay conexión
        RUTA.paradas[idx].estado = 'completado';
        renderParadas(); renderRouteOnMap(); updateProgress();
    });
}

// ═══════════════════════════════════════════════════════════════
// CONTROL PASEO (sin cambios visuales)
// ═══════════════════════════════════════════════════════════════
function togglePaseo() {
    if (!paseoActivo) iniciarPaseo(); else detenerPaseo();
}

function iniciarPaseo() {
    if (!RUTA.id) { showNotif('No tienes ruta asignada para hoy', 'warning'); return; }
    paseoActivo = true;
    document.getElementById('btnPaseoIcon').className  = 'fas fa-pause';
    document.getElementById('btnPaseoLabel').textContent = 'Pausar paseo';
    document.getElementById('btnPaseo').className        = 'btn-iniciar pause';
    document.getElementById('statsPanel').style.display  = 'grid';
    showNotif('▶️ Paseo iniciado — GPS activo', 'success');

    // Notificar al servidor
    fetch(API + 'iniciar_paseo.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_ruta: RUTA.id }),
    }).catch(() => {});

    startGPS();
    startTimer();
    renderParadas();
    renderRouteOnMap();
}

function detenerPaseo() {
    paseoActivo = false;
    document.getElementById('btnPaseoIcon').className  = 'fas fa-play';
    document.getElementById('btnPaseoLabel').textContent = 'Reiniciar paseo';
    document.getElementById('btnPaseo').className        = 'btn-iniciar start';
    stopGPS();
    stopTimer();

    // Notificar al servidor (pausar)
    if (RUTA.id) {
        fetch(API + 'iniciar_paseo.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_ruta: RUTA.id, accion: 'pausar' }),
        }).catch(() => {});
    }
    renderParadas();
}

// ═══════════════════════════════════════════════════════════════
// GPS REAL — envía posición al servidor cada 5 segundos
// ═══════════════════════════════════════════════════════════════
function startGPS() {
    if ('geolocation' in navigator) {
        watchId = navigator.geolocation.watchPosition(
            pos => {
                const { latitude: lat, longitude: lng, speed, accuracy } = pos.coords;
                updatePosition(lat, lng);
                // Enviar al servidor (backend guarda en gps_paseadores + historial_gps)
                enviarGPS(lat, lng, speed ? speed * 3.6 : 0, accuracy || 0);
            },
            () => startGPSSimulation(),    // fallback si el navegador niega GPS
            { enableHighAccuracy: true, maximumAge: 3000, timeout: 8000 }
        );
    } else {
        startGPSSimulation();
    }

    // Intervalo adicional por si watchPosition tarda en dispararse
    gpsInterval = setInterval(() => {
        if (lastLat !== null) enviarGPS(lastLat, lastLng, 0, 0);
    }, 5000);
}

function startGPSSimulation() {
    document.getElementById('gpsLabel').textContent = 'GPS Simulado';
    simInterval = setInterval(() => {
        const target = RUTA.paradas[paradaActual];
        if (!target) return;
        myLat += (target.lat - myLat) * .08;
        myLng += (target.lng - myLng) * .08;
        updatePosition(myLat, myLng);
        enviarGPS(myLat, myLng, 3.5, 15);
    }, 2000);
}

function stopGPS() {
    if (watchId !== null)  { navigator.geolocation.clearWatch(watchId); watchId = null; }
    if (simInterval)       { clearInterval(simInterval); simInterval = null; }
    if (gpsInterval)       { clearInterval(gpsInterval); gpsInterval = null; }
}

// ── Enviar coordenadas al backend ──────────────────────────────
function enviarGPS(lat, lng, velocidad, precision) {
    fetch(API + 'actualizar_gps.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ lat, lng, velocidad, precision }),
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        // Procesar eventos automáticos de paradas detectados por el servidor
        if (data.eventos && data.eventos.length) {
            data.eventos.forEach(ev => {
                const idx = RUTA.paradas.findIndex(p => p.id === ev.id_parada);
                if (idx < 0) return;
                if (ev.tipo === 'llegada') {
                    RUTA.paradas[idx].estado = 'llegada';
                    showNotif(`📍 Llegaste al punto ${RUTA.paradas[idx].label}`, 'success');
                } else if (ev.tipo === 'completada') {
                    RUTA.paradas[idx].estado = 'completado';
                    paradaActual = RUTA.paradas.findIndex((p, i) => i > idx && p.estado === 'pendiente');
                    if (paradaActual < 0) paradaActual = idx;
                    showNotif(`✅ Punto ${RUTA.paradas[idx].label} completado automáticamente`, 'success');
                }
                renderParadas();
                renderRouteOnMap();
                updateProgress();
            });
        }
    })
    .catch(() => { /* sin conexión, continuar sin errores */ });
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

    // Aviso de proximidad en el front (300m → notificación local)
    const target = RUTA.paradas[paradaActual];
    if (target && target.estado === 'pendiente') {
        const dist = haversine(lat, lng, target.lat, target.lng);
        if (dist < 0.3) showNotif(`📍 Llegando a Punto ${target.label} — a ${Math.round(dist * 1000)}m`, 'warning');
    }
}

function centerOnMe() { map.setView([myLat, myLng], 17); }

function fitRoute() {
    if (!RUTA.paradas.length) return;
    const bounds = RUTA.paradas.map(p => [p.lat, p.lng]);
    bounds.push([myLat, myLng]);
    map.fitBounds(bounds, { padding: [50, 50] });
}

// ═══════════════════════════════════════════════════════════════
// TIMER (sin cambios)
// ═══════════════════════════════════════════════════════════════
function startTimer() {
    timerInterval = setInterval(() => {
        elapsedSec++;
        const m = String(Math.floor(elapsedSec / 60)).padStart(2, '0');
        const s = String(elapsedSec % 60).padStart(2, '0');
        document.getElementById('statTime').textContent = `${m}:${s}`;
    }, 1000);
}
function stopTimer() { clearInterval(timerInterval); }

// ═══════════════════════════════════════════════════════════════
// PROGRESO (sin cambios)
// ═══════════════════════════════════════════════════════════════
function updateProgress() {
    const total = RUTA.paradas.length;
    if (!total) return;
    const done = RUTA.paradas.filter(p => p.estado === 'completado').length;
    const pct  = Math.round(done / total * 100);
    document.getElementById('progressFill').style.width   = pct + '%';
    document.getElementById('progressPct').textContent    = pct + '%';
    document.getElementById('progressLabel').textContent  = `Parada ${done} de ${total}`;
    document.getElementById('statParadas').textContent    = `${done}/${total}`;

    // Si todas las paradas están completadas, finalizar paseo
    if (done === total && total > 0 && paseoActivo) {
        finalizarPaseo();
    }
}

function finalizarPaseo() {
    paseoActivo = false;
    stopGPS(); stopTimer();
    document.getElementById('btnPaseoIcon').className    = 'fas fa-flag-checkered';
    document.getElementById('btnPaseoLabel').textContent = 'Paseo finalizado';
    document.getElementById('btnPaseo').className        = 'btn-iniciar start';
    document.getElementById('btnPaseo').disabled         = true;
    showNotif('🏁 ¡Paseo completado! Buen trabajo', 'success');

    if (RUTA.id) {
        fetch(API + 'finalizar_paseo.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_ruta: RUTA.id }),
        }).catch(() => {});
    }
}

// ═══════════════════════════════════════════════════════════════
// CAPAS MAPA (sin cambios)
// ═══════════════════════════════════════════════════════════════
function toggleSatellite() {
    usingSatellite = !usingSatellite;
    if (usingSatellite) { map.removeLayer(baseLayer); satelliteLayer.addTo(map); }
    else { map.removeLayer(satelliteLayer); baseLayer.addTo(map); }
    document.getElementById('btnSatellite').classList.toggle('active', usingSatellite);
}
function resetNorth() { showNotif('Mapa orientado al norte', 'info'); }

// ═══════════════════════════════════════════════════════════════
// NOTIFICACIÓN FLOTANTE (sin cambios)
// ═══════════════════════════════════════════════════════════════
let notifTimer;
function showNotif(msg, type = 'success') {
    const el = document.getElementById('floatNotif');
    document.getElementById('notifMsg').textContent = msg;
    el.className = `float-notif ${type}`;
    document.getElementById('notifIcon').className = {
        success: 'fas fa-check-circle',
        info:    'fas fa-info-circle',
        warning: 'fas fa-triangle-exclamation',
    }[type] || 'fas fa-check-circle';
    el.classList.add('show');
    clearTimeout(notifTimer);
    notifTimer = setTimeout(() => el.classList.remove('show'), 3500);
}

// ═══════════════════════════════════════════════════════════════
// HAVERSINE (sin cambios)
// ═══════════════════════════════════════════════════════════════
function haversine(la1, lo1, la2, lo2) {
    const R = 6371, dLat = (la2 - la1) * Math.PI / 180, dLon = (lo2 - lo1) * Math.PI / 180;
    const a = Math.sin(dLat / 2) ** 2 + Math.cos(la1 * Math.PI / 180) * Math.cos(la2 * Math.PI / 180) * Math.sin(dLon / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

// ═══════════════════════════════════════════════════════════════
// MENÚ HAMBURGUESA (sin cambios)
// ═══════════════════════════════════════════════════════════════
document.getElementById('btn-menu').addEventListener('click', () =>
    document.getElementById('menu-latente').classList.toggle('show'));
window.addEventListener('click', e => {
    const btn  = document.getElementById('btn-menu');
    const menu = document.getElementById('menu-latente');
    if (!btn.contains(e.target) && !menu.contains(e.target)) menu.classList.remove('show');
});

// ═══════════════════════════════════════════════════════════════
// INIT
// ═══════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
    initMap();
    // Si viene del dashboard con flag de "iniciar paseo"
    if (localStorage.getItem('paseoIniciado') === 'true') {
        localStorage.removeItem('paseoIniciado');
        // Esperar a que la ruta cargue antes de iniciar
        setTimeout(() => { if (RUTA.id) { iniciarPaseo(); fitRoute(); } }, 1500);
    }
});