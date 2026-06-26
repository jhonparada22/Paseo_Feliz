// ══════════════════════════════════════════════════════════════
// ESTADO GLOBAL
// ══════════════════════════════════════════════════════════════
let PASEADORES = [];
let filtroActual   = 'todos';
let paseadorSel    = null;
let selectedRouteClients = [];
let selectedPriority     = 'baja';
let mapInstance    = null;
let paseadorParaRuta = null;
let paseadorParaInfo = null;

const CLIENTES_DISPONIBLES = [
    { id: 101, nombre: 'María González',  mascota: 'Max',   addr: 'Av. 0 #5-35, Cúcuta Centro',    distancia: '1.2 km', urgente: false },
    { id: 102, nombre: 'Pedro Ramírez',   mascota: 'Coco',  addr: 'Calle 7 #0e-94, Motilones',     distancia: '0.8 km', urgente: true  },
    { id: 103, nombre: 'Laura Martínez',  mascota: 'Rocky', addr: 'Carrera 5 #12-20, Los Patios',  distancia: '3.1 km', urgente: false },
    { id: 104, nombre: 'Carlos López',    mascota: 'Toby',  addr: 'Urb. La Riviera, Cúcuta Norte', distancia: '2.4 km', urgente: false },
    { id: 105, nombre: 'Ana Salcedo',     mascota: 'Nala',  addr: 'Calle 10 #3-15, Atalaya',       distancia: '1.8 km', urgente: true  },
];

// Colores por índice para los avatares
const COLORS = ['#3E72A6','#16a34a','#ea580c','#7c3aed','#db2777','#0891b2','#b45309'];

// ══════════════════════════════════════════════════════════════
// SIDEBAR
// ══════════════════════════════════════════════════════════════
const btnMenu = document.getElementById('btn-menu');
const menuLatente = document.getElementById('menu-latente');
btnMenu.addEventListener('click', (e) => { e.stopPropagation(); menuLatente.classList.toggle('show'); });
window.addEventListener('click', e => {
    if (!btnMenu.contains(e.target) && !menuLatente.contains(e.target))
        menuLatente.classList.remove('show');
});

// ══════════════════════════════════════════════════════════════
// CARGA DESDE BD
// ══════════════════════════════════════════════════════════════
async function cargarPaseadores() {
    try {
        const res  = await fetch('../../../model/obtener_paseadores.php');
        const data = await res.json();
        if (!data.success) { showToast('Error al cargar paseadores', 'warning'); return; }

        // Asignar coordenadas fijas por id (lat/lng no están en BD, se usan ficticias por Cúcuta)
        const coordsBase = [
            { lat: 7.8939, lng: -72.5078 },
            { lat: 7.9121, lng: -72.5041 },
            { lat: 7.8801, lng: -72.5123 },
            { lat: 7.8650, lng: -72.4780 },
            { lat: 7.9050, lng: -72.4950 },
        ];

        PASEADORES = data.paseadores.map((p, i) => ({
            ...p,
            color: COLORS[i % COLORS.length],
            lat:   coordsBase[i % coordsBase.length].lat,
            lng:   coordsBase[i % coordsBase.length].lng,
            rutaActual: p.estado === 'en-ruta' ? 'Ruta activa' : null,
            rutasHistorial: [],
        }));

        paseadorSel = PASEADORES[0] || null;
        updateStats();
        renderLista();
        renderDetalle();
        renderHistorial();
        setTimeout(() => { initMapa(); setInterval(simularMovimiento, 5000); }, 300);
    } catch (err) {
        showToast('Error de conexión', 'warning');
        console.error(err);
    }
}

// ══════════════════════════════════════════════════════════════
// STATS
// ══════════════════════════════════════════════════════════════
function updateStats() {
    document.getElementById('statTotal').textContent   = PASEADORES.length;
    document.getElementById('statActivo').textContent  = PASEADORES.filter(p => p.estado === 'activo').length;
    document.getElementById('statEnRuta').textContent  = PASEADORES.filter(p => p.estado === 'en-ruta').length;
    document.getElementById('statPausado').textContent = PASEADORES.filter(p => p.estado === 'inactivo').length;
    const avg = PASEADORES.length
        ? (PASEADORES.reduce((s, p) => s + p.puntuacion, 0) / PASEADORES.length).toFixed(1)
        : '0.0';
    document.getElementById('statRating').textContent = avg + '★';
}

// ══════════════════════════════════════════════════════════════
// RENDER LISTA
// ══════════════════════════════════════════════════════════════
function renderLista() {
    const q     = document.getElementById('searchInput').value.toLowerCase();
    const lista = document.getElementById('paseadoresList');
    const empty = document.getElementById('emptyState');
    lista.innerHTML = '';

    const filtrados = PASEADORES.filter(p => {
        const matchQ = !q ||
            p.nombre.toLowerCase().includes(q) ||
            (p.zona_trabajo || '').toLowerCase().includes(q) ||
            p.email.toLowerCase().includes(q);
        const matchE = filtroActual === 'todos' || p.estado === filtroActual;
        return matchQ && matchE;
    });

    if (filtrados.length === 0) { empty.classList.add('visible'); return; }
    empty.classList.remove('visible');

    filtrados.forEach(p => {
        const card = document.createElement('div');
        card.className = 'paseador-card' + (paseadorSel && paseadorSel.id === p.id ? ' selected' : '');
        card.dataset.id = p.id;

        const initials  = p.nombre.split(' ').map(x => x[0]).slice(0, 2).join('');
        const zonaCorta = p.zona_trabajo ? p.zona_trabajo.split('/')[0].trim() : 'Sin zona';
        const horario   = p.hora_inicio && p.hora_fin
            ? `${p.hora_inicio.slice(0,5)}–${p.hora_fin.slice(0,5)}`
            : 'Sin horario';

        const estadoTag = {
            'activo':   '<span class="p-tag tag-activo"><i class="fas fa-circle" style="font-size:.5rem"></i> Activo</span>',
            'en-ruta':  '<span class="p-tag tag-enruta"><i class="fas fa-route" style="font-size:.6rem"></i> En ruta</span>',
            'inactivo': '<span class="p-tag tag-pausado"><i class="fas fa-pause" style="font-size:.6rem"></i> Inactivo</span>',
        }[p.estado] || '';

        const dotClass = { activo: 'dot-on', 'en-ruta': 'dot-busy', inactivo: 'dot-off' }[p.estado] || 'dot-off';

        card.innerHTML = `
      <div class="p-avatar" style="background:${p.color}">
        ${p.avatar
            ? `<img src="../../${p.avatar}" style="width:100%;height:100%;object-fit:cover;border-radius:12px;display:block;" onerror="this.style.display='none'">`
            : initials}
        <div class="p-online-dot ${dotClass}"></div>
      </div>
      <div class="p-info">
        <div class="p-name">${p.nombre}</div>
        <div class="p-email">${p.email}</div>
        <div class="p-tags">
          ${estadoTag}
          <span class="p-tag tag-rating">⭐ ${p.puntuacion.toFixed(1)}</span>
          <span class="p-tag" style="background:#f1f5f9;color:var(--muted)">
            <i class="fas fa-map-pin" style="font-size:.6rem"></i> ${zonaCorta}
          </span>
          <span class="p-tag" style="background:#f1f5f9;color:var(--muted)">
            <i class="fas fa-clock" style="font-size:.6rem"></i> ${horario}
          </span>
        </div>
      </div>
      <div class="p-stats">
        <div class="p-stat-row"><i class="fas fa-route"></i> <strong>${p.paseos_mes}</strong>&nbsp;este mes</div>
        <div class="p-stat-row"><i class="fas fa-check"></i> <strong>${p.paseos_totales}</strong>&nbsp;total</div>
        <div class="p-stat-row"><i class="fas fa-users"></i> <strong>${p.clientes.length}</strong>&nbsp;clientes</div>
      </div>
      <div class="p-actions">
        <button class="p-action-btn chat"  title="Ir al chat"       onclick="irAlChat(${p.id},event)"><i class="fas fa-comment-alt"></i></button>
        <button class="p-action-btn map"   title="Ver en mapa"      onclick="verEnMapa(${p.id},event)"><i class="fas fa-map-marker-alt"></i></button>
        <button class="p-action-btn route" title="Asignar ruta"     onclick="abrirModalRuta(${p.id},event)"><i class="fas fa-route"></i></button>
      </div>
    `;
        card.addEventListener('click', () => seleccionar(p.id));
        lista.appendChild(card);
    });
}

// ══════════════════════════════════════════════════════════════
// SELECCIONAR PASEADOR
// ══════════════════════════════════════════════════════════════
function seleccionar(id) {
    paseadorSel = PASEADORES.find(p => p.id === id);
    renderLista();
    renderDetalle();
    actualizarMapa();
}

// ══════════════════════════════════════════════════════════════
// RENDER DETALLE
// ══════════════════════════════════════════════════════════════
function renderDetalle() {
    if (!paseadorSel) return;
    const p  = paseadorSel;
    const dc = document.getElementById('detailCard');

    const initials    = p.nombre.split(' ').map(x => x[0]).slice(0, 2).join('');
    const statusClass = { activo: 'dsb-activo', 'en-ruta': 'dsb-activo', inactivo: 'dsb-pausado' }[p.estado] || 'dsb-pausado';
    const statusLabel = { activo: '● Activo', 'en-ruta': '◉ En Ruta', inactivo: '◌ Inactivo' }[p.estado] || p.estado;
    const horario     = p.hora_inicio && p.hora_fin
        ? `${p.hora_inicio.slice(0,5)} – ${p.hora_fin.slice(0,5)}`
        : 'Sin horario definido';

    dc.innerHTML = `
    <div class="dc-head">
      <div class="dc-head-top">
        <div class="dc-avatar" style="background:${p.color}">
          ${p.avatar
            ? `<img src="../../${p.avatar}" style="width:100%;height:100%;object-fit:cover;border-radius:12px;display:block;" onerror="this.style.display='none'">`
            : initials}
        </div>
        <span class="dc-status-badge ${statusClass}">${statusLabel}</span>
      </div>
      <div class="dc-name">${p.nombre}</div>
      <div class="dc-sub">${p.email}${p.telefono ? ' · ' + p.telefono : ''}</div>
    </div>
    <div class="dc-quick-actions">
      <button class="dqa-btn chat-btn" onclick="irAlChat(${p.id},event)">
        <i class="fas fa-comment-alt"></i> Chat
      </button>
      <button class="dqa-btn map-btn" onclick="verEnMapa(${p.id},event)">
        <i class="fas fa-map-marker-alt"></i> Mapa
      </button>
      <button class="dqa-btn ruta-btn" onclick="abrirModalRuta(${p.id},event)">
        <i class="fas fa-route"></i> Ruta
      </button>
    </div>
    <div class="dc-body">
      <div class="dc-info-row">
        <i class="fas fa-map-pin"></i>
        <div>
          <div class="dci-label">Zona de trabajo</div>
          <div class="dci-val">${p.zona_trabajo || 'No definida'}</div>
        </div>
      </div>
      <div class="dc-info-row">
        <i class="fas fa-clock"></i>
        <div>
          <div class="dci-label">Horario de trabajo</div>
          <div class="dci-val">${horario}</div>
        </div>
      </div>
      ${p.clientes.length ? `
      <div class="dc-info-row">
        <i class="fas fa-users"></i>
        <div>
          <div class="dci-label">Clientes asignados</div>
          <div class="dci-val">${p.clientes.join(' · ')}</div>
        </div>
      </div>` : ''}
      <div class="dc-perf">
        <div class="dcp-item"><div class="dcp-val">⭐${p.puntuacion.toFixed(1)}</div><div class="dcp-lbl">Rating</div></div>
        <div class="dcp-item"><div class="dcp-val">${p.paseos_mes}</div><div class="dcp-lbl">Este mes</div></div>
        <div class="dcp-item"><div class="dcp-val">${p.paseos_totales}</div><div class="dcp-lbl">Totales</div></div>
      </div>
      <button class="btn-secondary" style="width:100%;justify-content:center;margin-bottom:8px" onclick="abrirModalInfo(${p.id},event)">
        <i class="fas fa-pen-to-square"></i> Editar zona y horario
      </button>
      <button class="btn-primary" style="width:100%;justify-content:center" onclick="abrirModalRuta(${p.id},event)">
        <i class="fas fa-plus"></i> Asignar nueva ruta
      </button>
      <button class="btn-outline" style="width:100%;justify-content:center;margin-top:8px" onclick="showToast('Historial de ${p.nombre}','info')">
        <i class="fas fa-clock-rotate-left"></i> Ver historial completo
      </button>
    </div>
  `;
}

// ══════════════════════════════════════════════════════════════
// MODAL EDITAR INFO (zona, horario, puntuación)
// ══════════════════════════════════════════════════════════════
function abrirModalInfo(id, e) {
    if (e) e.stopPropagation();
    paseadorParaInfo = PASEADORES.find(p => p.id === id);
    if (!paseadorParaInfo) return;

    document.getElementById('infoModalTitle').textContent = `Editar: ${paseadorParaInfo.nombre}`;
    document.getElementById('infoModalSub').textContent   = 'Zona de trabajo, horario y puntuación';
    document.getElementById('infoZona').value        = paseadorParaInfo.zona_trabajo   || '';
    document.getElementById('infoHoraInicio').value  = paseadorParaInfo.hora_inicio    ? paseadorParaInfo.hora_inicio.slice(0,5) : '';
    document.getElementById('infoHoraFin').value     = paseadorParaInfo.hora_fin       ? paseadorParaInfo.hora_fin.slice(0,5)    : '';
    document.getElementById('infoPuntuacion').value  = paseadorParaInfo.puntuacion     || '';

    document.getElementById('infoModal').classList.add('open');
}

document.getElementById('closeInfoModal').addEventListener('click', () =>
    document.getElementById('infoModal').classList.remove('open'));
document.getElementById('cancelInfo').addEventListener('click', () =>
    document.getElementById('infoModal').classList.remove('open'));
document.getElementById('infoModal').addEventListener('click', e => {
    if (e.target === document.getElementById('infoModal'))
        document.getElementById('infoModal').classList.remove('open');
});

document.getElementById('confirmInfo').addEventListener('click', async () => {
    if (!paseadorParaInfo) return;

    const zona        = document.getElementById('infoZona').value.trim();
    const horaInicio  = document.getElementById('infoHoraInicio').value;
    const horaFin     = document.getElementById('infoHoraFin').value;
    const puntuacion  = parseFloat(document.getElementById('infoPuntuacion').value);

    if (horaInicio && horaFin && horaInicio >= horaFin) {
        showToast('La hora de inicio debe ser antes que la hora fin', 'warning'); return;
    }
    if (puntuacion && (puntuacion < 0 || puntuacion > 5)) {
        showToast('La puntuación debe estar entre 0 y 5', 'warning'); return;
    }

    try {
        const res  = await fetch('../../../model/guardar_info_paseador.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_paseador: paseadorParaInfo.id,
                zona_trabajo: zona,
                hora_inicio:  horaInicio || null,
                hora_fin:     horaFin    || null,
                puntuacion:   isNaN(puntuacion) ? null : puntuacion,
            })
        });
        const data = await res.json();

        if (data.success) {
            // Actualizar en memoria
            paseadorParaInfo.zona_trabajo  = zona;
            paseadorParaInfo.hora_inicio   = horaInicio;
            paseadorParaInfo.hora_fin      = horaFin;
            if (!isNaN(puntuacion)) paseadorParaInfo.puntuacion = puntuacion;

            document.getElementById('infoModal').classList.remove('open');
            showToast('Información actualizada ✓', 'success');
            renderLista();
            renderDetalle();
            updateStats();
        } else {
            showToast('Error: ' + data.message, 'warning');
        }
    } catch (err) {
        showToast('Error de conexión', 'warning');
        console.error(err);
    }
});

// ══════════════════════════════════════════════════════════════
// MAPA LEAFLET
// ══════════════════════════════════════════════════════════════
function initMapa() {
    const centro = paseadorSel ? [paseadorSel.lat, paseadorSel.lng] : [7.8939, -72.5078];
    mapInstance  = L.map('minimap', { zoomControl: true, attributionControl: false }).setView(centro, 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(mapInstance);

    PASEADORES.forEach(p => {
        const color = { activo: '#25D366', 'en-ruta': '#3E72A6', inactivo: '#f97316' }[p.estado] || '#ccc';
        const icon  = L.divIcon({
            html: `<div style="background:${color};width:30px;height:30px;border-radius:50%;border:3px solid #fff;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:.65rem;box-shadow:0 2px 8px rgba(0,0,0,.3)">${p.nombre.split(' ')[0][0]}</div>`,
            className: '', iconSize: [30, 30], iconAnchor: [15, 15]
        });
        const marker = L.marker([p.lat, p.lng], { icon }).addTo(mapInstance);
        marker.bindPopup(`<b>${p.nombre}</b><br>${p.zona_trabajo || 'Sin zona'}`);
        if (paseadorSel && p.id === paseadorSel.id) marker.openPopup();
    });
}

function actualizarMapa() {
    if (!mapInstance || !paseadorSel) return;
    mapInstance.setView([paseadorSel.lat, paseadorSel.lng], 15);
    document.getElementById('mapCoord').textContent =
        `${paseadorSel.nombre} — ${(paseadorSel.zona_trabajo || 'Sin zona').split('/')[0]}`;
}

// ══════════════════════════════════════════════════════════════
// HISTORIAL RUTAS
// ══════════════════════════════════════════════════════════════
function renderHistorial() {
    const el = document.getElementById('rutasRecientes');
    el.innerHTML = '<div style="padding:16px;color:#aaa;font-size:.85rem;text-align:center"><i class="fas fa-clock-rotate-left"></i> El historial se cargará desde la BD de paseos.</div>';
}

// ══════════════════════════════════════════════════════════════
// MODAL ASIGNAR RUTA
// ══════════════════════════════════════════════════════════════
function abrirModalRuta(id, e) {
    if (e) e.stopPropagation();
    paseadorParaRuta = PASEADORES.find(p => p.id === id);
    document.getElementById('modalTitle').textContent = `Asignar ruta a ${paseadorParaRuta.nombre}`;
    document.getElementById('modalSub').textContent   = `Zona: ${paseadorParaRuta.zona_trabajo || 'Sin zona'}`;

    const hoy = new Date().toISOString().split('T')[0];
    document.getElementById('routeDate').value  = hoy;
    document.getElementById('routeTime').value  = '08:00';
    document.getElementById('routeNotes').value = '';
    document.getElementById('routeMeet').value  = '';

    selectedRouteClients = [];
    renderClientesModal();
    document.getElementById('routeModal').classList.add('open');
}

function renderClientesModal() {
    const list = document.getElementById('clientRouteList');
    list.innerHTML = '';
    CLIENTES_DISPONIBLES.forEach(c => {
        const sel = selectedRouteClients.includes(c.id);
        const div = document.createElement('div');
        div.className = 'client-route-item' + (sel ? ' selected' : '');
        div.innerHTML = `
      <div class="cri-avatar" style="background:${COLORS[c.id % COLORS.length]}">${c.nombre[0]}</div>
      <div class="cri-info">
        <div class="cri-name">${c.nombre} ${c.urgente ? '🔴' : ''}</div>
        <div class="cri-addr"><i class="fas fa-map-pin" style="font-size:.6rem"></i>${c.addr}</div>
        <div class="cri-meta">
          <span class="cri-tag">🐕 ${c.mascota}</span>
          <span class="cri-tag">📍 ${c.distancia}</span>
          ${c.urgente ? '<span class="cri-tag" style="background:#fee2e2;color:#b91c1c;border-color:#fca5a5">⚡ Urgente</span>' : ''}
        </div>
      </div>
      <div class="cri-check"><i class="fas fa-check"></i></div>
    `;
        div.addEventListener('click', () => {
            selectedRouteClients = selectedRouteClients.includes(c.id)
                ? selectedRouteClients.filter(x => x !== c.id)
                : [...selectedRouteClients, c.id];
            renderClientesModal();
        });
        list.appendChild(div);
    });
}

document.querySelectorAll('.prio-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.prio-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        selectedPriority = btn.dataset.prio;
    });
});

document.getElementById('closeRouteModal').addEventListener('click', () =>
    document.getElementById('routeModal').classList.remove('open'));
document.getElementById('cancelRoute').addEventListener('click', () =>
    document.getElementById('routeModal').classList.remove('open'));
document.getElementById('routeModal').addEventListener('click', e => {
    if (e.target === document.getElementById('routeModal'))
        document.getElementById('routeModal').classList.remove('open');
});

document.getElementById('confirmRoute').addEventListener('click', () => {
    if (selectedRouteClients.length === 0) {
        showToast('Selecciona al menos un cliente', 'warning'); return;
    }
    const nombres = selectedRouteClients
        .map(id => CLIENTES_DISPONIBLES.find(c => c.id === id)?.nombre).join(', ');

    if (paseadorParaRuta) {
        paseadorParaRuta.estado     = 'en-ruta';
        paseadorParaRuta.rutaActual = nombres;
        // Sumar paseo (en BD se haría desde el controlador de paseos)
        paseadorParaRuta.paseos_mes++;
        paseadorParaRuta.paseos_totales++;
    }

    document.getElementById('routeModal').classList.remove('open');
    showToast(`Ruta asignada a ${paseadorParaRuta?.nombre} ✓`, 'success');
    renderLista();
    renderDetalle();
    updateStats();
});

// ══════════════════════════════════════════════════════════════
// ACCIONES RÁPIDAS
// ══════════════════════════════════════════════════════════════
function irAlChat(id, e) {
    if (e) e.stopPropagation();
    const p = PASEADORES.find(x => x.id === id);
    showToast(`Abriendo chat con ${p?.nombre}...`, 'info');
    setTimeout(() => window.location.href = `Chat_admin.php`, 1200);
}

function verEnMapa(id, e) {
    if (e) e.stopPropagation();
    const p = PASEADORES.find(x => x.id === id);
    seleccionar(id);
    showToast(`Mostrando ubicación de ${p?.nombre}`, 'info');
    document.getElementById('minimap').scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// ══════════════════════════════════════════════════════════════
// FILTROS Y BÚSQUEDA
// ══════════════════════════════════════════════════════════════
document.querySelectorAll('.sf-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.sf-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        filtroActual = btn.dataset.filter;
        renderLista();
    });
});
document.getElementById('searchInput').addEventListener('input', renderLista);

// ══════════════════════════════════════════════════════════════
// HELPERS
// ══════════════════════════════════════════════════════════════
function capitalize(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

let toastTimer;
function showToast(msg, type = 'success') {
    const t  = document.getElementById('toast');
    const ic = t.querySelector('i');
    document.getElementById('toastMsg').textContent = msg;
    t.className = `toast ${type}`;
    ic.className = {
        success: 'fas fa-check-circle',
        info:    'fas fa-info-circle',
        warning: 'fas fa-triangle-exclamation'
    }[type] || 'fas fa-check-circle';
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), 3200);
}

// Simulación GPS
function simularMovimiento() {
    PASEADORES.filter(p => p.estado === 'en-ruta').forEach(p => {
        p.lat += (Math.random() - .5) * .0008;
        p.lng += (Math.random() - .5) * .0008;
    });
    if (paseadorSel) actualizarMapa();
}

// ══════════════════════════════════════════════════════════════
// INIT
// ══════════════════════════════════════════════════════════════
cargarPaseadores();