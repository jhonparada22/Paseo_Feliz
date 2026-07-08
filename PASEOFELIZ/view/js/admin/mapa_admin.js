// ═══════════════════════════════════════════════════════════════
// RUTA BASE AL MODELO PHP
// La página vive en /view/vistas/admin/ -> subir 3 niveles hasta /model/
// ═══════════════════════════════════════════════════════════════
const API = '../../../model/';

// ═══════════════════════════════════════════════════════════════
// ESTADO GLOBAL (igual que antes, sin cambios visuales)
// ═══════════════════════════════════════════════════════════════
let map, routeLayer, paseadoresLayer, clientesLayer;
let modoActual = 'view';
let puntosRuta      = [];        // [{lat, lng, label, addr, color}]
let paseadorSelId   = null;
let paseadoresMarkers = {};
let routePolyline   = null;
let listaPaseadores = [];        // ← antes era const PASEADORES (hardcoded)
let listaRutasHoy   = [];        // ← antes era const RUTAS_HOY (hardcoded)
let listaClientes   = [];        // ubicaciones de clientes con pedidos activos
let clientesVisibles = true;

const LABELS     = ['A', 'B', 'C', 'D', 'E'];
const DOT_COLORS = ['#ef4444', '#f97316', '#8b5cf6', '#0ea5e9', '#10b981'];

// ═══════════════════════════════════════════════════════════════
// INIT MAPA (sin cambios respecto al original)
// ═══════════════════════════════════════════════════════════════
function initMap() {
    map = L.map('map', { zoomControl: true, attributionControl: false })
        .setView([7.8939, -72.5078], 14);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap', maxZoom: 19,
    }).addTo(map);

    routeLayer     = L.layerGroup().addTo(map);
    paseadoresLayer = L.layerGroup().addTo(map);
    clientesLayer   = L.layerGroup().addTo(map);

    map.on('click', e => {
        if (modoActual === 'route') {
            agregarPunto(e.latlng.lat, e.latlng.lng, 'Punto seleccionado en mapa');
        }
    });

    // Cargar datos reales del backend y arrancar el polling
    cargarPaseadores();
    cargarRutasHoy();
    cargarSelectPaseador();
    cargarClientesMapa();

    // Refresca posición GPS de paseadores cada 5 segundos (antes era simularGPS)
    setInterval(cargarPaseadores, 5000);
}

// ═══════════════════════════════════════════════════════════════
// CARGAR PASEADORES DESDE EL BACKEND (reemplaza PASEADORES fake)
// ═══════════════════════════════════════════════════════════════
function cargarPaseadores() {
    fetch(API + 'obtener_paseadores.php')
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;

            // Asignar color único por índice para los marcadores
            const colores = ['#3E72A6', '#16a34a', '#ea580c', '#7c3aed', '#0891b2'];
            listaPaseadores = data.paseadores.map((p, i) => ({
                ...p,
                color: colores[i % colores.length],
                zona: p.zona_trabajo || 'Cúcuta',
                rating: p.puntuacion || 0,
                paseosMes: p.paseos_mes || 0,
                rutaActual: p.id_ruta_activa ? `Ruta #${p.id_ruta_activa}` : null,
            }));

            renderPaseadoresMarkers();
            // Si hay un paseador seleccionado, actualizar su panel derecho
            if (paseadorSelId) seleccionarPaseador(paseadorSelId);
            // Actualizar lista si el tab de paseadores está visible
            const tabPaseadores = document.getElementById('tab-paseadores');
            if (tabPaseadores && tabPaseadores.style.display !== 'none') {
                renderPaseadoresList();
            }
        })
        .catch(() => { /* silencioso, el mapa sigue funcionando */ });
}

// ═══════════════════════════════════════════════════════════════
// CLIENTES EN EL MAPA (cuadritos morados)
// Ubicaciones de los pedidos de paseo activos: el admin puede ver
// dónde está cada cliente y usar su ubicación como punto al trazar
// una ruta para cualquier paseador, a cualquier hora del día.
// ═══════════════════════════════════════════════════════════════
function cargarClientesMapa() {
    fetch(API + 'obtener_clientes_mapa.php')
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            listaClientes = data.clientes || [];
            renderClientesMapa();
        })
        .catch(() => { /* silencioso */ });
}

function renderClientesMapa() {
    clientesLayer.clearLayers();
    if (!clientesVisibles) return;

    listaClientes.forEach(c => {
        const icon = L.divIcon({
            html: `<div style="
                width:14px;height:14px;border-radius:4px;
                background:#7c3aed;border:2px solid #fff;
                box-shadow:0 2px 6px rgba(0,0,0,.35);"></div>`,
            className: '', iconSize: [14, 14], iconAnchor: [7, 7],
        });

        const asignado = c.paseador_asignado
            ? `<span class="cp-tag asignado">✓ ${c.paseador_asignado}</span>`
            : `<span class="cp-tag sin-asignar">Sin paseador</span>`;

        const marker = L.marker([c.lat, c.lng], { icon, zIndexOffset: -100 })
            .addTo(clientesLayer)
            .bindPopup(`
                <div class="cp-nombre">🏠 ${c.cliente}</div>
                <div class="cp-linea">🐾 ${c.mascota || 'Mascota'}</div>
                <div class="cp-linea">📍 ${c.direccion}${c.barrio ? ', ' + c.barrio : ''}</div>
                ${c.franja ? `<div class="cp-linea">🕐 ${c.franja}</div>` : ''}
                <div class="cp-tags">
                    <span class="cp-tag ${c.modalidad}">${c.modalidad === 'individual' ? '🐕 Individual' : '🐾 Grupal'}</span>
                    ${asignado}
                </div>
                <button class="cp-btn" onclick="usarClienteEnRuta(${c.lat}, ${c.lng}, ${c.id_pedido})">
                    <i class="fas fa-route"></i> Usar como punto de la ruta
                </button>
            `, { className: 'cliente-popup', minWidth: 210 });

        marker.bindTooltip(`${c.cliente} · ${c.mascota || ''}`, {
            direction: 'top', offset: [0, -8], className: 'custom-marker-label',
        });
    });
}

// Agrega la ubicación del cliente como punto de la ruta que se está
// armando en el tab "Asignar ruta" (y lleva al admin a ese tab)
function usarClienteEnRuta(lat, lng, idPedido) {
    const c = listaClientes.find(x => x.id_pedido === idPedido);
    const addr = c ? `${c.cliente} — ${c.direccion}${c.barrio ? ', ' + c.barrio : ''}` : 'Ubicación de cliente';
    agregarPunto(lat, lng, addr, c ? { id_pedido: c.id_pedido, id_usuario_cliente: c.id_cliente, id_mascota: c.id_mascota } : null);
    map.closePopup();

    // Mostrar el tab de rutas para que se vea el punto agregado
    const tabRutas = document.querySelector('.panel-tab[data-tab="rutas"]');
    if (tabRutas && !tabRutas.classList.contains('active')) tabRutas.click();
}

function toggleClientes() {
    clientesVisibles = !clientesVisibles;
    document.getElementById('btnClientes').classList.toggle('active-mode', clientesVisibles);
    renderClientesMapa();
}

// ═══════════════════════════════════════════════════════════════
// CARGAR RUTAS DE HOY (reemplaza RUTAS_HOY fake)
// ═══════════════════════════════════════════════════════════════
function cargarRutasHoy(fecha) {
    const url = API + 'obtener_rutas.php' + (fecha ? `?fecha=${fecha}` : '');
    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            listaRutasHoy = data.rutas;
            renderRutasHoy();
        })
        .catch(() => {});
}

// ═══════════════════════════════════════════════════════════════
// LLENAR EL SELECT DE PASEADORES (antes era hardcoded en el HTML)
// ═══════════════════════════════════════════════════════════════
function cargarSelectPaseador() {
    fetch(API + 'obtener_paseadores.php')
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const sel = document.getElementById('selectPaseador');
            // Limpiar opciones anteriores excepto el placeholder
            sel.innerHTML = '<option value="">— Seleccionar paseador —</option>';
            data.paseadores.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = p.nombre + (p.estado === 'en-ruta' ? ' (en ruta)' : '');
                sel.appendChild(opt);
            });
        })
        .catch(() => {});
}

// ═══════════════════════════════════════════════════════════════
// MARCADORES PASEADORES (sin cambios visuales, solo usa listaPaseadores)
// ═══════════════════════════════════════════════════════════════
function renderPaseadoresMarkers() {
    paseadoresLayer.clearLayers();
    paseadoresMarkers = {};

    listaPaseadores.forEach(p => {
        // Solo mostrar si tiene GPS registrado
        if (p.lat === null || p.lng === null) return;

        const dotColor = { activo: '#25D366', 'en-ruta': '#f97316', pausado: '#ef4444' }[p.estado] || '#ccc';
        const initials = p.nombre.split(' ').map(x => x[0]).slice(0, 2).join('');

        const icon = L.divIcon({
            html: `<div style="
                background:${p.color};width:38px;height:38px;border-radius:12px;
                border:3px solid #fff;display:flex;align-items:center;justify-content:center;
                color:#fff;font-weight:800;font-size:.72rem;
                box-shadow:0 3px 10px rgba(0,0,0,.25);position:relative;cursor:pointer;">
                ${initials}
                <div style="position:absolute;bottom:-3px;right:-3px;
                    width:12px;height:12px;border-radius:50%;
                    background:${dotColor};border:2px solid #fff;"></div>
            </div>`,
            className: '', iconSize: [38, 38], iconAnchor: [19, 19],
        });

        const marker = L.marker([p.lat, p.lng], { icon })
            .addTo(paseadoresLayer)
            .on('click', () => seleccionarPaseador(p.id, true));

        marker.bindTooltip(`<b>${p.nombre}</b><br>${p.zona}`, {
            direction: 'top', offset: [0, -20], className: 'custom-marker-label',
        });

        paseadoresMarkers[p.id] = marker;
    });
}

// ═══════════════════════════════════════════════════════════════
// SELECCIONAR PASEADOR → panel derecho
// "desdeUsuario" en true solo cuando lo dispara un toque/clic real:
// en pantallas chicas el panel es un bottom-sheet y no debe
// reabrirse solo por el refresco automático de GPS cada 5s.
// ═══════════════════════════════════════════════════════════════
function seleccionarPaseador(id, desdeUsuario = false) {
    paseadorSelId = id;
    const p = listaPaseadores.find(x => x.id == id);
    if (!p) return;

    if (desdeUsuario && window.matchMedia('(max-width: 900px)').matches) {
        document.getElementById('rightPanel').classList.add('open');
    }

    document.getElementById('rp-name').textContent = p.nombre;

    const coords = (p.lat && p.lng)
        ? `📍 ${parseFloat(p.lat).toFixed(5)}, ${parseFloat(p.lng).toFixed(5)}`
        : '📍 Sin posición GPS aún';

    const vel = p.velocidad ? `${parseFloat(p.velocidad).toFixed(1)} km/h` : '—';
    const ultima = p.ultima_pos
        ? new Date(p.ultima_pos).toLocaleTimeString('es-CO')
        : 'Sin datos';

    document.getElementById('rp-sub').textContent = coords;
    document.getElementById('rp-body').innerHTML = `
        <div class="rp-stat-row">
            <div class="rp-stat"><div class="rps-val">${p.rating || '—'}</div><div class="rps-lbl">Rating</div></div>
            <div class="rp-stat"><div class="rps-val">${p.paseosMes || 0}</div><div class="rps-lbl">Paseos/mes</div></div>
            <div class="rp-stat">
                <div class="rps-val" style="color:${{ activo:'#25D366','en-ruta':'#f97316', pausado:'#ef4444', inactivo:'#94a3b8' }[p.estado] || '#ccc'}">
                    ${{ activo:'Activo','en-ruta':'En ruta', pausado:'Pausado', inactivo:'Inactivo' }[p.estado] || p.estado}
                </div>
                <div class="rps-lbl">Estado</div>
            </div>
        </div>
        <div class="rp-info-row"><i class="fas fa-phone" style="color:var(--primary)"></i><span>${p.telefono || 'No registrado'}</span></div>
        <div class="rp-info-row"><i class="fas fa-tachometer-alt" style="color:var(--primary)"></i><span>Velocidad: ${vel}</span></div>
        <div class="rp-info-row"><i class="fas fa-clock" style="color:var(--primary)"></i><span>Última pos: ${ultima}</span></div>
        ${p.id_ruta_activa ? `
        <div class="rp-info-row"><i class="fas fa-route" style="color:var(--primary)"></i>
            <span>Ruta #${p.id_ruta_activa} activa</span>
        </div>
        <button onclick="verDetalleRuta(${p.id_ruta_activa})"
            style="width:100%;margin-top:8px;padding:8px;background:var(--primary);
            color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:.8rem">
            <i class="fas fa-eye"></i> Ver detalle ruta
        </button>` : ''}
        ${p.lat ? `<button onclick="centrarEnPaseador(${p.lat},${p.lng})"
            style="width:100%;margin-top:6px;padding:8px;background:var(--bg2);
            color:var(--fg);border:1px solid var(--border);border-radius:8px;cursor:pointer;font-size:.8rem">
            <i class="fas fa-crosshairs"></i> Centrar en mapa
        </button>` : ''}
    `;
}

function centrarEnPaseador(lat, lng) {
    map.setView([lat, lng], 17);
}

function verDetalleRuta(idRuta) {
    fetch(API + `obtener_ruta.php?id_ruta=${idRuta}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.ruta) return;
            const ruta = data.ruta;
            // Dibujar paradas de esa ruta en el mapa
            routeLayer.clearLayers();
            const coords = ruta.paradas.map(p => [p.lat, p.lng]);
            L.polyline(coords, { color: '#3E72A6', weight: 4, dashArray: '8 5' }).addTo(routeLayer);
            ruta.paradas.forEach((p, i) => {
                const color = DOT_COLORS[i % DOT_COLORS.length];
                L.circleMarker([p.lat, p.lng], { radius: 10, color: '#fff', fillColor: color, fillOpacity: 1, weight: 3 })
                    .addTo(routeLayer)
                    .bindPopup(`<b>${p.label}. ${p.tipo}</b><br>${p.addr}${p.cliente ? '<br>🐾 ' + p.cliente.mascota : ''}`);
            });
            if (coords.length) map.fitBounds(coords, { padding: [40, 40] });
        });
}

// ═══════════════════════════════════════════════════════════════
// LISTA DE PASEADORES (tab) — sin cambios visuales
// ═══════════════════════════════════════════════════════════════
function renderPaseadoresList() {
    const el = document.getElementById('paseadoresList');
    if (!el) return;
    el.innerHTML = '';

    if (!listaPaseadores.length) {
        el.innerHTML = '<div style="color:var(--muted);font-size:.8rem;text-align:center;padding:20px">Cargando paseadores...</div>';
        return;
    }

    listaPaseadores.forEach(p => {
        const dotColor = { activo: '#25D366', 'en-ruta': '#f97316', pausado: '#ef4444', inactivo: '#94a3b8' }[p.estado] || '#ccc';
        const estadoLabel = { activo: 'Activo', 'en-ruta': 'En ruta', pausado: 'Pausado', inactivo: 'Inactivo' }[p.estado] || p.estado;
        const coords = (p.lat && p.lng)
            ? `${parseFloat(p.lat).toFixed(4)}, ${parseFloat(p.lng).toFixed(4)}`
            : 'Sin GPS';
        const initials = p.nombre.split(' ').map(x => x[0]).slice(0, 2).join('');

        const div = document.createElement('div');
        div.className = 'paseador-card';
        div.innerHTML = `
            <div class="pc-top">
                <div class="pc-av" style="background:${p.color || '#3E72A6'}">${initials}</div>
                <div class="pc-info">
                    <div class="pci-name">${p.nombre}</div>
                    <div class="pci-sub">${p.zona || 'Cúcuta'}</div>
                </div>
                <div class="pc-dot" style="background:${dotColor}" title="${estadoLabel}"></div>
            </div>
            <div class="pc-meta">
                <span>⭐ ${p.rating}</span>
                <span>📦 ${p.paseosMes} paseos</span>
                <span style="color:${dotColor};font-weight:700">${estadoLabel}</span>
            </div>
            <div style="font-size:.7rem;color:var(--muted);margin-top:4px">
                <i class="fas fa-location-dot"></i> ${coords}
                ${p.velocidad ? ` · ${parseFloat(p.velocidad).toFixed(1)} km/h` : ''}
            </div>
        `;
        div.addEventListener('click', () => seleccionarPaseador(p.id, true));
        el.appendChild(div);
    });
}

// ═══════════════════════════════════════════════════════════════
// RENDER RUTAS HOY (usa listaRutasHoy real, mismo HTML que antes)
// ═══════════════════════════════════════════════════════════════
function renderRutasHoy() {
    const el = document.getElementById('activeRoutesList');
    if (!el) return;
    el.innerHTML = '';

    if (!listaRutasHoy.length) {
        el.innerHTML = '<div style="color:var(--muted);font-size:.78rem;padding:10px 0">No hay rutas para hoy.</div>';
        return;
    }

    listaRutasHoy.forEach(r => {
        const statusCls = r.estado === 'en_curso'
            ? 'background:#dbeafe;color:#1d4ed8'
            : r.estado === 'pausada'
                ? 'background:#fef3c7;color:#92400e'
                : 'background:#f1f5f9;color:#475569';
        const estadoLabel = { en_curso: '🔵 En ruta', pendiente: '⏰ Pendiente', pausada: '⏸ Pausada', finalizada: '✅ Finalizada' }[r.estado] || r.estado;

        const div = document.createElement('div');
        div.style.cssText = 'background:var(--bg);border-radius:10px;padding:10px 12px;margin-bottom:7px;font-size:.78rem;cursor:pointer';
        div.innerHTML = `
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                <span style="font-weight:700">${r.paseador}</span>
                <span style="padding:2px 8px;border-radius:20px;font-size:.65rem;font-weight:700;${statusCls}">${estadoLabel}</span>
            </div>
            <div style="color:var(--muted);display:flex;align-items:center;gap:5px;margin-bottom:4px">
                <i class="fas fa-clock" style="font-size:.65rem"></i> ${r.hora || ''}
            </div>
            ${r.puntos.map((pt, i) => `
                <div style="display:flex;align-items:center;gap:6px;margin-top:3px">
                    <div style="width:16px;height:16px;border-radius:50%;background:${DOT_COLORS[i] || '#ccc'};
                        display:flex;align-items:center;justify-content:center;
                        color:#fff;font-size:.55rem;font-weight:800;flex-shrink:0">${LABELS[i]}</div>
                    <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${pt}</span>
                </div>
            `).join('')}
        `;
        div.addEventListener('click', () => verDetalleRuta(r.id_ruta));
        el.appendChild(div);
    });
}

// ═══════════════════════════════════════════════════════════════
// AGREGAR PUNTO DE RUTA (sin cambios)
// ═══════════════════════════════════════════════════════════════
function agregarPunto(lat, lng, addr, cliente = null) {
    if (puntosRuta.length >= 5) { showToast('Máximo 5 puntos por ruta', 'warning'); return; }
    const idx   = puntosRuta.length;
    const label = LABELS[idx];
    const color = DOT_COLORS[idx];
    puntosRuta.push({
        lat, lng, label, addr, color,
        id_pedido: cliente ? cliente.id_pedido : null,
        id_usuario_cliente: cliente ? cliente.id_usuario_cliente : null,
        id_mascota: cliente ? cliente.id_mascota : null,
    });
    renderRouteSteps();
    renderRouteOnMap();
    showToast(`Punto ${label} agregado`, 'success');
}

// ═══════════════════════════════════════════════════════════════
// RENDER PASOS PANEL IZQUIERDO (sin cambios visuales)
// ═══════════════════════════════════════════════════════════════
function renderRouteSteps() {
    const el = document.getElementById('routeSteps');
    el.innerHTML = '';
    puntosRuta.forEach((p, i) => {
        if (i > 0) {
            const sep = document.createElement('div');
            sep.className = 'step-connector'; el.appendChild(sep);
        }
        const step = document.createElement('div');
        step.className = 'route-step';
        step.innerHTML = `
            <div class="step-dot" style="background:${p.color}">${p.label}</div>
            <div class="step-info">
                <div class="step-addr">${p.addr}</div>
                <div class="step-coords">${p.lat.toFixed(5)}, ${p.lng.toFixed(5)}</div>
            </div>
            <button class="step-del" onclick="eliminarPunto(${i})" title="Eliminar">
                <i class="fas fa-times"></i>
            </button>
        `;
        el.appendChild(step);
    });
}

function eliminarPunto(idx) {
    puntosRuta.splice(idx, 1);
    // Re-etiquetar
    puntosRuta.forEach((p, i) => { p.label = LABELS[i]; p.color = DOT_COLORS[i]; });
    renderRouteSteps();
    renderRouteOnMap();
}

// ═══════════════════════════════════════════════════════════════
// DIBUJAR RUTA EN MAPA (sin cambios)
// ═══════════════════════════════════════════════════════════════
function renderRouteOnMap() {
    routeLayer.clearLayers();
    if (puntosRuta.length < 1) return;

    if (puntosRuta.length >= 2) {
        // Intentar trazar ruta real con OSRM
        const coords = puntosRuta.map(p => `${p.lng},${p.lat}`).join(';');
        fetch(`https://router.project-osrm.org/route/v1/foot/${coords}?overview=full&geometries=geojson`)
            .then(r => r.json())
            .then(data => {
                if (data.routes?.[0]) {
                    if (routePolyline) routeLayer.removeLayer(routePolyline);
                    routePolyline = L.geoJSON(data.routes[0].geometry, {
                        style: { color: '#3E72A6', weight: 4, opacity: .8 },
                    }).addTo(routeLayer);
                }
            })
            .catch(() => {
                // Fallback: línea recta
                routePolyline = L.polyline(puntosRuta.map(p => [p.lat, p.lng]), {
                    color: '#3E72A6', weight: 4, dashArray: '8 5',
                }).addTo(routeLayer);
            });
    }

    puntosRuta.forEach(p => {
        L.circleMarker([p.lat, p.lng], {
            radius: 10, color: '#fff', fillColor: p.color, fillOpacity: 1, weight: 3,
        }).addTo(routeLayer)
         .bindTooltip(`<b>${p.label}.</b> ${p.addr}`, { direction: 'top' });
    });
}

// ═══════════════════════════════════════════════════════════════
// BÚSQUEDA DE DIRECCIÓN (Nominatim — sin cambios)
// ═══════════════════════════════════════════════════════════════
let searchTimeout;
document.getElementById('addrSearch').addEventListener('input', e => {
    clearTimeout(searchTimeout);
    const q = e.target.value.trim();
    if (q.length < 3) { document.getElementById('addrResults').style.display = 'none'; return; }
    searchTimeout = setTimeout(() => buscarDireccion(q), 400);
});

function buscarDireccion(q) {
    fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q + ', Cúcuta, Colombia')}&limit=4&accept-language=es`)
        .then(r => r.json())
        .then(results => {
            const el = document.getElementById('addrResults');
            el.innerHTML = '';
            if (!results.length) {
                el.style.display = 'none'; return;
            }
            el.style.display = 'block';
            results.forEach(r => {
                const item = document.createElement('div');
                item.style.cssText = 'padding:9px 12px;cursor:pointer;font-size:.78rem;border-bottom:1px solid var(--border);background:var(--bg)';
                item.textContent = r.display_name;
                item.addEventListener('mouseenter', () => item.style.background = 'var(--bg2)');
                item.addEventListener('mouseleave', () => item.style.background = 'var(--bg)');
                item.addEventListener('click', () => {
                    agregarPunto(parseFloat(r.lat), parseFloat(r.lon), r.display_name.split(',')[0]);
                    document.getElementById('addrSearch').value = '';
                    el.style.display = 'none';
                });
                el.appendChild(item);
            });
        })
        .catch(() => {});
}

// ═══════════════════════════════════════════════════════════════
// ASIGNAR RUTA → AHORA CON FETCH REAL AL BACKEND
// ═══════════════════════════════════════════════════════════════
document.getElementById('btnAsignar').addEventListener('click', () => {
    const sel = document.getElementById('selectPaseador').value;
    if (!sel) { showToast('Selecciona un paseador primero', 'warning'); return; }
    if (puntosRuta.length < 2) { showToast('Agrega al menos 2 puntos a la ruta', 'warning'); return; }

    const paseador = listaPaseadores.find(p => p.id == sel);
    const nombrePaseador = paseador ? paseador.nombre : `Paseador #${sel}`;
    const fecha = document.getElementById('routeDate').value || new Date().toISOString().split('T')[0];
    const hora  = document.getElementById('routeTime').value || '08:00';

    document.getElementById('assignSummary').innerHTML = `
        <div><i class="fas fa-person-walking" style="color:var(--primary)"></i><span>Paseador:</span> ${nombrePaseador}</div>
        <div><i class="fas fa-calendar" style="color:var(--primary)"></i><span>Fecha:</span> ${fecha} a las ${hora}</div>
        <div><i class="fas fa-route" style="color:var(--primary)"></i><span>Paradas:</span> ${puntosRuta.length} puntos</div>
        ${puntosRuta.map(p => `<div style="padding-left:16px;font-size:.75rem;color:var(--muted)">
            <span style="color:${p.color};font-weight:800">${p.label}.</span> ${p.addr}
        </div>`).join('')}
    `;
    document.getElementById('assignModal').classList.add('open');
});

document.getElementById('cancelAssign').addEventListener('click', () =>
    document.getElementById('assignModal').classList.remove('open'));

document.getElementById('confirmAssign').addEventListener('click', () => {
    const sel   = document.getElementById('selectPaseador').value;
    const fecha = document.getElementById('routeDate').value || new Date().toISOString().split('T')[0];
    const hora  = document.getElementById('routeTime').value || '08:00';

    const payload = {
        id_paseador: parseInt(sel),
        fecha, hora,
        puntos: puntosRuta.map((p, i) => ({
            lat: p.lat, lng: p.lng, addr: p.addr,
            etiqueta: p.label,
            tipo: i === 0 ? 'recogida' : (i === puntosRuta.length - 1 ? 'entrega' : 'paseo'),
            id_pedido: p.id_pedido || null,
            id_usuario_cliente: p.id_usuario_cliente || null,
            id_mascota: p.id_mascota || null,
        })),
    };

    // Deshabilitar botón mientras guarda
    const btn = document.getElementById('confirmAssign');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

    fetch(API + 'guardar_ruta.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('assignModal').classList.remove('open');
        if (data.success) {
            const msg = data.ruta_nueva === false
                ? `✅ Se agregaron ${puntosRuta.length} parada(s) a la ruta activa del paseador`
                : `✅ Ruta #${data.id_ruta} creada y asignada correctamente`;
            showToast(msg, 'success');
            puntosRuta = [];
            renderRouteSteps();
            routeLayer.clearLayers();
            cargarRutasHoy();       // refrescar lista de rutas
            cargarPaseadores();     // refrescar marcadores
            cargarSelectPaseador(); // refrescar select
        } else {
            showToast('Error: ' + (data.message || 'No se pudo guardar'), 'warning');
        }
    })
    .catch(() => showToast('Error de conexión al guardar', 'warning'))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Confirmar';
    });
});

// ═══════════════════════════════════════════════════════════════
// LIMPIAR RUTA (sin cambios)
// ═══════════════════════════════════════════════════════════════
document.getElementById('btnLimpiar').addEventListener('click', () => {
    puntosRuta = [];
    renderRouteSteps();
    routeLayer.clearLayers();
    showToast('Ruta limpiada', 'info');
});

document.getElementById('btnAddPoint').addEventListener('click', () => setMode('route'));

// ═══════════════════════════════════════════════════════════════
// MODO MAPA (sin cambios)
// ═══════════════════════════════════════════════════════════════
function setMode(mode) {
    modoActual = mode;
    document.getElementById('modeView').classList.toggle('active-mode', mode === 'view');
    document.getElementById('modeRoute').classList.toggle('active-mode', mode === 'route');
    map.getContainer().style.cursor = mode === 'route' ? 'crosshair' : '';
    if (mode === 'route') showToast('Modo trazado: clic en el mapa para agregar puntos', 'info');
}

function centerCucuta() {
    map.setView([7.8939, -72.5078], 14);
}

function toggleTraffic() {
    showToast('Tráfico no disponible en OpenStreetMap gratuito', 'info');
}

// ═══════════════════════════════════════════════════════════════
// TABS PANEL IZQUIERDO (sin cambios)
// ═══════════════════════════════════════════════════════════════
document.querySelectorAll('.panel-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.panel-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        document.getElementById('tab-rutas').style.display     = tab.dataset.tab === 'rutas'      ? 'block' : 'none';
        document.getElementById('tab-paseadores').style.display = tab.dataset.tab === 'paseadores' ? 'block' : 'none';
        if (tab.dataset.tab === 'paseadores') renderPaseadoresList();
    });
});

// ═══════════════════════════════════════════════════════════════
// PANEL DERECHO EN MÓVIL (bottom-sheet): botón cerrar y reajuste
// del mapa cuando cambia el tamaño/orientación de la pantalla
// ═══════════════════════════════════════════════════════════════
const rpCloseBtn = document.getElementById('rpClose');
if (rpCloseBtn) {
    rpCloseBtn.addEventListener('click', () =>
        document.getElementById('rightPanel').classList.remove('open'));
}

window.addEventListener('resize', () => {
    if (map) map.invalidateSize();
});

// ═══════════════════════════════════════════════════════════════
// SIDEBAR (sin cambios)
// ═══════════════════════════════════════════════════════════════
document.getElementById('btn-menu').addEventListener('click', () =>
    document.getElementById('menu-latente').classList.toggle('show'));
window.addEventListener('click', e => {
    const btn  = document.getElementById('btn-menu');
    const menu = document.getElementById('menu-latente');
    if (!btn.contains(e.target) && !menu.contains(e.target)) menu.classList.remove('show');
});

// ═══════════════════════════════════════════════════════════════
// TOAST (sin cambios)
// ═══════════════════════════════════════════════════════════════
let toastTimer;
function showToast(msg, type = 'success') {
    const t  = document.getElementById('toast');
    const ic = t.querySelector('i');
    document.getElementById('toastMsg').textContent = msg;
    t.className = `toast ${type}`;
    ic.className = { success: 'fas fa-check-circle', info: 'fas fa-info-circle', warning: 'fas fa-triangle-exclamation' }[type] || 'fas fa-check-circle';
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), 3500);
}

// ═══════════════════════════════════════════════════════════════
// INIT
// ═══════════════════════════════════════════════════════════════
document.getElementById('routeDate').value = new Date().toISOString().split('T')[0];
initMap();