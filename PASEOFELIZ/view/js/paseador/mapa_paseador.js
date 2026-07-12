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
// GPS simulado: se marca en cada envío para que el servidor pueda
// rechazarlo en producción (el tracking real no debe mezclarse con
// posiciones inventadas por el navegador).
let gpsEsSimulado = false;
// Paradas por las que ya se avisó "estás cerca, confirma" (evita repetir
// la notificación con cada ping de GPS mientras sigue en el punto).
const paradasAvisadasCerca = new Set();

// ← Antes: const PASEADOR = {...} y const RUTA = {...} hardcodeados
// Ahora se cargan del backend:
let PASEADOR = { id: null, nombre: 'Cargando...', telefono: '' };
let RUTA     = { id: null, fecha: '', hora_inicio: '', paradas: [] };

// ═══════════════════════════════════════════════════════════════
// CARGAR RUTA DEL DÍA DESDE EL BACKEND
// ═══════════════════════════════════════════════════════════════
function cargarRutaDeHoy() {
    return fetch(API + 'obtener_ruta.php?modo=hoy')
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
        if (!data.success) {
            // Ej: "No se puede entregar: primero confirma la recogida"
            showNotif('⚠️ ' + (data.message || 'No se pudo completar la parada'), 'warning');
            return;
        }
        RUTA.paradas[idx].estado = 'completado';
        paradaActual = RUTA.paradas.findIndex((p, i) => i > idx && p.estado === 'pendiente');
        if (paradaActual < 0) paradaActual = idx; // último punto
        renderParadas();
        renderRouteOnMap();
        updateProgress();
        showNotif(`✅ Punto ${parada.label} completado`, 'success');
    })
    .catch(() => {
        // Sin conexión: NO se marca localmente — la confirmación real vive
        // en el servidor; marcarla solo en pantalla desincroniza al cliente.
        showNotif('⚠️ Sin conexión. Inténtalo de nuevo.', 'warning');
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
        gpsEsSimulado = false;
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
    gpsEsSimulado = true;
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
// El servidor ya NO completa paradas por proximidad: solo avisa que el
// paseador está en el punto para que CONFIRME manualmente la recogida o
// entrega (la confirmación es la que escribe los timestamps de negocio).
//
// Resiliencia móvil: si no hay señal, las posiciones se guardan en un
// buffer local y se reenvían en lote con el siguiente envío exitoso —
// el recorrido no queda con huecos en el historial.
let gpsBufferOffline = [];

function enviarGPS(lat, lng, velocidad, precision) {
    const payload = { lat, lng, velocidad, precision, simulado: gpsEsSimulado };
    if (gpsBufferOffline.length) {
        payload.buffer = gpsBufferOffline.splice(0, 200);
    }
    fetch(API + 'actualizar_gps.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            // Producción: el servidor rechaza posiciones simuladas
            if (data.gps_simulado_rechazado) {
                stopGPS();
                document.getElementById('gpsLabel').textContent = 'GPS deshabilitado';
                showNotif('⚠️ Activa la ubicación real de tu dispositivo para continuar el paseo', 'warning');
            }
            return;
        }
        if (data.eventos && data.eventos.length) {
            data.eventos.forEach(ev => {
                if (ev.tipo !== 'cerca_de_parada') return;
                if (paradasAvisadasCerca.has(ev.id_parada)) return;
                paradasAvisadasCerca.add(ev.id_parada);

                const idx = RUTA.paradas.findIndex(p => p.id === ev.id_parada);
                if (idx < 0) return;
                const p = RUTA.paradas[idx];
                const accion = p.tipo === 'entrega' ? 'la entrega' : 'la recogida';
                showNotif(`📍 Estás en el punto ${p.label} — confirma ${accion} en la lista`, 'success');

                // Resaltar la parada para que el botón de confirmar quede visible
                if (p.estado === 'pendiente') {
                    paradaActual = idx;
                    renderParadas();
                    renderRouteOnMap();
                }
            });
        }
    })
    .catch(() => {
        // Sin conexión: guardar la posición para reenviarla en lote después
        if (!gpsEsSimulado) {
            gpsBufferOffline.push({ lat, lng, velocidad, precision, ts: Date.now() });
            if (gpsBufferOffline.length > 200) gpsBufferOffline.shift(); // límite de memoria
        }
    });
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
// PASEOS DE HOY — segmentos Individual / Grupal
// El paseador ve sus paseos del día separados por modalidad:
//  - Individual: ordenados por la hora de su franja.
//  - Grupal: va marcando "Recogido" perro por perro y arranca el
//    paseo grupal cuando tiene a los seleccionados.
// Cancelar exige un motivo (modal) y notifica al cliente.
// ═══════════════════════════════════════════════════════════════
let PASEOS_HOY = [];
let seleccionGrupal = new Set();   // ids de pedido marcados para el paseo grupal
let cancelTarget = null;           // paseo sobre el que se abre el modal de cancelar

function cargarPaseosHoy() {
    return fetch(API + 'obtener_paseos_hoy_paseador.php')
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            PASEOS_HOY = data.paseos || [];
            if (data.fecha) {
                document.getElementById('fechaPaseosHoy').textContent =
                    new Date(data.fecha + 'T00:00:00').toLocaleDateString('es-CO', {
                        weekday: 'long', day: 'numeric', month: 'numeric', year: 'numeric',
                    });
            }
            // Los perros ya recogidos entran seleccionados para el grupal
            PASEOS_HOY.forEach(p => {
                if (p.modalidad === 'grupal' && (p.estado === 'recogido' || p.estado === 'en_paseo')) {
                    seleccionGrupal.add(p.id_pedido);
                }
                if (p.estado === 'cancelado' || p.estado === 'entregado') {
                    seleccionGrupal.delete(p.id_pedido);
                }
            });
            renderPaseosHoy();
        })
        .catch(() => { /* sin conexión: se reintenta en el próximo ciclo */ });
}

function chipEstadoPaseo(p) {
    switch (p.estado) {
        case 'recogido':  return '<span class="pi-chip recogido">✓ Recogido</span>';
        case 'en_paseo':  return '<span class="pi-chip enpaseo">🚶 En paseo</span>';
        case 'entregado': return '<span class="pi-chip entregado">🏠 Entregado</span>';
        case 'cancelado': return `<span class="pi-chip cancelado">✖ Cancelado${p.motivo_cancelacion ? ' — ' + p.motivo_cancelacion : ''}</span>`;
        default:          return '<span class="pi-chip pendiente">⏰ Pendiente</span>';
    }
}

function botonesPaseo(p) {
    const chat = `<button class="pi-btn chat" title="Chat con ${p.cliente}" onclick="abrirChatCliente(${p.id_cliente})"><i class="fas fa-comment-alt"></i></button>`;
    const foto = `<button class="pi-btn chat" title="Subir foto del paseo" onclick="pedirFotoPaseo(${p.id_pedido})"><i class="fas fa-camera"></i></button>`;
    if (p.estado === 'cancelado') {
        return `<button class="pi-btn deshacer" title="Deshacer cancelación" onclick="deshacerEstado(${p.id_pedido})"><i class="fas fa-rotate-left"></i></button>${chat}`;
    }
    if (p.estado === 'entregado') {
        return `${foto}${chat}`;
    }
    if (p.estado === 'recogido') {
        return `
            <button class="pi-btn entregar" onclick="marcarEntregado(${p.id_pedido})">Entregado</button>
            <button class="pi-btn deshacer" title="Deshacer recogida" onclick="deshacerEstado(${p.id_pedido})"><i class="fas fa-rotate-left"></i></button>
            <button class="pi-btn cancelar" title="Solicitar cancelación al administrador" onclick="abrirModalCancelar(${p.id_pedido})">Solicitar cancelación</button>
            ${foto}${chat}`;
    }
    return `
        <button class="pi-btn recoger" onclick="marcarRecogido(${p.id_pedido})">Recogido</button>
        <button class="pi-btn cancelar" title="Solicitar cancelación al administrador" onclick="abrirModalCancelar(${p.id_pedido})">Solicitar cancelación</button>
        <button class="pi-btn deshacer" title="Reportar problema (sin cancelar)" onclick="abrirModalIncidencia(${p.id_pedido})"><i class="fas fa-triangle-exclamation"></i></button>
        ${chat}`;
}

// ── Evidencias: subir foto del paseo (la ve el cliente en su panel) ──
let fotoTarget = null; // id_pedido al que pertenece la foto

function pedirFotoPaseo(idPedido) {
    fotoTarget = idPedido;
    document.getElementById('inputFotoPaseo').click();
}

function enviarFotoPaseo(input) {
    const file = input.files && input.files[0];
    input.value = ''; // permitir volver a elegir el mismo archivo
    if (!file || !fotoTarget) return;

    const p = PASEOS_HOY.find(x => x.id_pedido === fotoTarget);
    const tipo = p && p.estado === 'entregado' ? 'entrega' : 'paseo';

    const fd = new FormData();
    fd.append('id_pedido', fotoTarget);
    fd.append('tipo', tipo);
    fd.append('foto', file);
    showNotif('📷 Subiendo foto...', 'info');

    fetch(API + 'subir_evidencia_paseo.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (!data.success) { showNotif(data.message || 'No se pudo subir la foto', 'warning'); return; }
            showNotif('📷 ' + data.message, 'success');
        })
        .catch(() => showNotif('Sin conexión: la foto no se subió. Intenta de nuevo.', 'warning'))
        .finally(() => { fotoTarget = null; });
}

function renderPaseosHoy() {
    // Individuales siempre por horario de la franja (sin hora, al final)
    const indiv = PASEOS_HOY
        .filter(p => p.modalidad === 'individual')
        .sort((a, b) => (a.hora_inicio || '99:99').localeCompare(b.hora_inicio || '99:99'));
    const grupal = PASEOS_HOY.filter(p => p.modalidad === 'grupal');

    document.getElementById('countIndividual').textContent = indiv.length;
    document.getElementById('countGrupal').textContent     = grupal.length;

    // ── Individual: por horario ──
    const bi = document.getElementById('bodyIndividual');
    bi.innerHTML = indiv.length ? '' : '<div class="seg-vacio">Sin paseos individuales hoy.</div>';
    indiv.forEach(p => {
        const div = document.createElement('div');
        div.className = `paseo-item ${p.estado}`;
        div.innerHTML = `
            <div class="pi-hora">${p.hora_inicio || '--:--'}</div>
            <div class="pi-info">
                <div class="pi-nombre">🦴 ${p.mascota}</div>
                <div class="pi-sub">${p.cliente} · 📍 ${p.direccion}${p.barrio ? ', ' + p.barrio : ''}</div>
                ${chipEstadoPaseo(p)}
            </div>
            <div class="pi-acciones">${botonesPaseo(p)}</div>`;
        bi.appendChild(div);
    });

    // ── Grupal: selección perro por perro ──
    const bg = document.getElementById('bodyGrupal');
    bg.innerHTML = grupal.length ? '' : '<div class="seg-vacio">Sin paseos grupales hoy.</div>';
    grupal.forEach(p => {
        const bloqueado = p.estado === 'cancelado' || p.estado === 'entregado' || p.estado === 'en_paseo';
        const div = document.createElement('div');
        div.className = `paseo-item ${p.estado}`;
        div.innerHTML = `
            <label class="pi-check">
                <input type="checkbox" ${seleccionGrupal.has(p.id_pedido) ? 'checked' : ''}
                       ${bloqueado ? 'disabled' : ''}
                       onchange="toggleSeleccionGrupal(${p.id_pedido}, this.checked)">
            </label>
            <div class="pi-info">
                <div class="pi-nombre">🦴 ${p.mascota}</div>
                <div class="pi-sub">${p.cliente} · 📍 ${p.direccion}${p.barrio ? ', ' + p.barrio : ''}</div>
                ${chipEstadoPaseo(p)}
            </div>
            <div class="pi-acciones">${botonesPaseo(p)}</div>`;
        bg.appendChild(div);
    });

    document.getElementById('footGrupal').style.display = grupal.length ? 'block' : 'none';
    actualizarBtnGrupal();
}

function toggleSeleccionGrupal(idPedido, checked) {
    if (checked) seleccionGrupal.add(idPedido); else seleccionGrupal.delete(idPedido);
    actualizarBtnGrupal();
}

// "Entregar grupo" solo se habilita cuando TODOS los seleccionados ya
// están marcados como recogidos (el paseador los va recibiendo uno a uno)
function actualizarBtnGrupal() {
    const btn = document.getElementById('btnIniciarGrupal');
    if (!btn) return;
    const seleccionados = PASEOS_HOY.filter(p => p.modalidad === 'grupal' && seleccionGrupal.has(p.id_pedido));
    const listos = seleccionados.filter(p => p.estado === 'recogido');
    const pendientes = seleccionados.filter(p => p.estado === 'pendiente');
    btn.disabled = !(listos.length > 0 && pendientes.length === 0);
    btn.innerHTML = listos.length > 0
        ? `<i class="fas fa-house"></i> Entregar grupo (${listos.length})`
        : `<i class="fas fa-house"></i> Entregar grupo`;
}

function marcarRecogido(idPedido) {
    fetch(API + 'marcar_paseo_dia.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ accion: 'recogido', id_pedido: idPedido }),
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) { showNotif(data.message || 'No se pudo marcar', 'warning'); return; }
        seleccionGrupal.add(idPedido);
        showNotif('🐾 ' + (data.message || 'Recogido'), 'success');
        cargarPaseosHoy();
        cargarRutaDeHoy();  // la parada de recogida pudo cambiar de estado
    })
    .catch(() => showNotif('Sin conexión. Intenta de nuevo.', 'warning'));
}

function deshacerEstado(idPedido) {
    fetch(API + 'marcar_paseo_dia.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ accion: 'pendiente', id_pedido: idPedido }),
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) { showNotif(data.message || 'No se pudo deshacer', 'warning'); return; }
        seleccionGrupal.delete(idPedido);
        showNotif('↩️ Paseo devuelto a pendiente', 'info');
        cargarPaseosHoy();
    })
    .catch(() => showNotif('Sin conexión. Intenta de nuevo.', 'warning'));
}

function marcarEntregado(idPedido) {
    fetch(API + 'marcar_paseo_dia.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ accion: 'entregar', id_pedido: idPedido }),
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) { showNotif(data.message || 'No se pudo entregar', 'warning'); return; }
        seleccionGrupal.delete(idPedido);
        showNotif('🏠 ' + (data.message || 'Entregado'), 'success');
        cargarPaseosHoy();
        cargarRutaDeHoy(); // la parada de entrega pudo cambiar de estado
    })
    .catch(() => showNotif('Sin conexión. Intenta de nuevo.', 'warning'));
}

// "Entregar grupo": solo los perros ya recogidos del grupo seleccionado
function entregarGrupo() {
    const ids = PASEOS_HOY
        .filter(p => p.modalidad === 'grupal' && seleccionGrupal.has(p.id_pedido) && p.estado === 'recogido')
        .map(p => p.id_pedido);
    if (!ids.length) { showNotif('Selecciona perros ya recogidos para entregar', 'warning'); return; }

    fetch(API + 'marcar_paseo_dia.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ accion: 'entregar_grupal', ids_pedidos: ids }),
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) { showNotif(data.message || 'No se pudo entregar', 'warning'); return; }
        ids.forEach(id => seleccionGrupal.delete(id));
        showNotif('🏠 ' + data.message, 'success');
        cargarPaseosHoy();
        cargarRutaDeHoy();
    })
    .catch(() => showNotif('Sin conexión. Intenta de nuevo.', 'warning'));
}

// ── Modal de cancelación (motivo obligatorio) ──────────────────
function abrirModalCancelar(idPedido) {
    const p = PASEOS_HOY.find(x => x.id_pedido === idPedido);
    if (!p) return;
    cancelTarget = p;
    document.getElementById('mcxMascota').textContent = `${p.mascota} (${p.cliente})`;
    document.querySelectorAll('input[name="motivoCancel"]').forEach(r => (r.checked = false));
    document.getElementById('mcxOtroTexto').value = '';
    document.getElementById('mcxOtroTexto').style.display = 'none';
    document.getElementById('mcxBtnConfirmar').disabled = true;
    document.getElementById('modalCancelar').classList.add('open');
}

function cerrarModalCancelar() {
    document.getElementById('modalCancelar').classList.remove('open');
    cancelTarget = null;
}

function motivoSeleccionado() {
    const sel = document.querySelector('input[name="motivoCancel"]:checked');
    if (!sel) return '';
    if (sel.value === '__otro__') return document.getElementById('mcxOtroTexto').value.trim();
    return sel.value;
}

function confirmarCancelacion() {
    if (!cancelTarget) return;
    const motivo = motivoSeleccionado();
    if (!motivo) { showNotif('Selecciona o escribe el motivo', 'warning'); return; }

    fetch(API + 'marcar_paseo_dia.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ accion: 'cancelar', id_pedido: cancelTarget.id_pedido, motivo }),
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) { showNotif(data.message || 'No se pudo enviar la solicitud', 'warning'); return; }
        cerrarModalCancelar();
        // El paseo NO se cancela aquí: queda pendiente de la aprobación del
        // admin y sigue su curso. Se refresca para reflejar el estado.
        showNotif('📨 Solicitud enviada. El paseo continúa hasta que el administrador la apruebe.', 'info');
        cargarPaseosHoy();
        cargarRutaDeHoy();
    })
    .catch(() => showNotif('Sin conexión. Intenta de nuevo.', 'warning'));
}

// ── Modal de incidencia (reportar problema SIN cancelar) ────────
let incidenciaTarget = null;

function abrirModalIncidencia(idPedido) {
    const p = PASEOS_HOY.find(x => x.id_pedido === idPedido);
    if (!p) return;
    incidenciaTarget = p;
    document.getElementById('micMascota').textContent = `${p.mascota} (${p.cliente})`;
    document.querySelectorAll('input[name="motivoInc"]').forEach(r => (r.checked = false));
    document.getElementById('micNota').value = '';
    document.getElementById('modalIncidencia').classList.add('open');
}

function cerrarModalIncidencia() {
    document.getElementById('modalIncidencia').classList.remove('open');
    incidenciaTarget = null;
}

function abrirChatIncidencia() {
    if (incidenciaTarget) abrirChatCliente(incidenciaTarget.id_cliente);
}

function confirmarIncidencia() {
    if (!incidenciaTarget) return;
    const sel = document.querySelector('input[name="motivoInc"]:checked');
    if (!sel) { showNotif('Selecciona el tipo de problema', 'warning'); return; }
    const nota = document.getElementById('micNota').value.trim();

    fetch(API + 'marcar_paseo_dia.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            accion: 'incidencia',
            id_pedido: incidenciaTarget.id_pedido,
            subtipo: sel.value,
            nota,
        }),
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) { showNotif(data.message || 'No se pudo reportar', 'warning'); return; }
        cerrarModalIncidencia();
        showNotif('📣 ' + data.message, 'info');
    })
    .catch(() => showNotif('Sin conexión. Intenta de nuevo.', 'warning'));
}

function abrirChatCliente(idCliente) {
    if (!idCliente) return;
    window.location.href = 'Chat_paseador.php?chat_con=' + idCliente;
}

function abrirChatClienteModal() {
    if (cancelTarget) abrirChatCliente(cancelTarget.id_cliente);
}

// ── UI: plegar segmentos y cabecera del panel ──────────────────
function toggleSegmento(nombre) {
    const body = document.getElementById('body' + nombre);
    const chev = document.getElementById('chev' + nombre);
    const abierto = body.style.display !== 'none';
    body.style.display = abierto ? 'none' : '';
    chev.classList.toggle('girado', abierto);
    const foot = nombre === 'Grupal' ? document.getElementById('footGrupal') : null;
    if (foot) {
        const hayGrupales = PASEOS_HOY.some(p => p.modalidad === 'grupal');
        foot.style.display = (abierto || !hayGrupales) ? 'none' : 'block';
    }
}

function togglePanelHeader() {
    const panel = document.querySelector('.info-panel');
    panel.classList.toggle('plegado');
    document.getElementById('iconPlegar').className =
        panel.classList.contains('plegado') ? 'fas fa-chevron-down' : 'fas fa-chevron-up';
    setTimeout(() => { if (map) map.invalidateSize(); }, 250);
}

// Habilitar el botón "Cancelar paseo" del modal cuando hay motivo
document.addEventListener('change', e => {
    if (e.target.name === 'motivoCancel') {
        const esOtro = e.target.value === '__otro__';
        document.getElementById('mcxOtroTexto').style.display = esOtro ? 'block' : 'none';
        document.getElementById('mcxBtnConfirmar').disabled = esOtro
            ? document.getElementById('mcxOtroTexto').value.trim() === ''
            : false;
        if (esOtro) document.getElementById('mcxOtroTexto').focus();
    }
});
document.addEventListener('input', e => {
    if (e.target.id === 'mcxOtroTexto') {
        document.getElementById('mcxBtnConfirmar').disabled = e.target.value.trim() === '';
    }
});

// ═══════════════════════════════════════════════════════════════
// INIT
// ═══════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
    initMap();
    cargarPaseosHoy();
    setInterval(cargarPaseosHoy, 60000);
    // Si viene del dashboard con flag de "iniciar paseo"
    if (localStorage.getItem('paseoIniciado') === 'true') {
        localStorage.removeItem('paseoIniciado');
        // Esperar a que la ruta cargue antes de iniciar
        setTimeout(() => { if (RUTA.id) { iniciarPaseo(); fitRoute(); } }, 1500);
    }
});
// Reajustar el mapa cuando cambia el tamaño u orientación de la pantalla
// (en móvil el layout pasa a columna y Leaflet necesita recalcular su área)
window.addEventListener('resize', () => {
    if (typeof map !== 'undefined' && map) map.invalidateSize();
});

// ── Segundo plano (móvil): al cambiar de app, el navegador suspende
// watchPosition y los intervalos — el seguimiento queda congelado sin
// que el paseador lo note. Al VOLVER, se re-sincroniza todo el estado
// con el servidor y se le avisa del hueco.
let ultimaVezVisible = Date.now();
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') {
        ultimaVezVisible = Date.now();
        return;
    }
    const segundosFuera = Math.round((Date.now() - ultimaVezVisible) / 1000);
    // Re-sincronizar siempre que estuvo fuera más de 30 s
    if (segundosFuera > 30) {
        cargarPaseosHoy();
        cargarRutaDeHoy();
        if (typeof map !== 'undefined' && map) map.invalidateSize();
        if (paseoActivo) {
            showNotif(`⏸ El seguimiento se pausó ${Math.round(segundosFuera / 60) || 1} min en segundo plano. Re-sincronizado.`, 'warning');
        }
    }
});
