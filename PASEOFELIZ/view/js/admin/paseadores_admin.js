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

let CLIENTES_DISPONIBLES = []; // se llena desde la BD (usuarios rol cliente + sus mascotas)

// ══════════════════════════════════════════════════════════════
// CARGAR CLIENTES REALES (usuarios con rol cliente y sus mascotas)
// ══════════════════════════════════════════════════════════════
async function cargarClientes() {
    try {
        const [resU, resM] = await Promise.all([
            fetch('../../../model/obtener_usuarios.php'),
            fetch('../../../model/obtener_mascotas.php'),
        ]);
        const dataU = await resU.json();
        const dataM = await resM.json();
        if (!dataU.success || !dataM.success) return;

        // Pedidos de mensualidad pagados: traen la ubicación exacta ya
        // validada por el cliente en el wizard de compra (sin geocodificar).
        let pedidos = [];
        try {
            const resP  = await fetch('../../../model/obtener_pedidos_paseos.php');
            const dataP = await resP.json();
            if (dataP.success) pedidos = dataP.pedidos;
        } catch (e) { /* sin pedidos, se geocodifica como siempre */ }

        CLIENTES_DISPONIBLES = dataU.usuarios
            .filter(u => u.rol === 'cliente')
            .map(u => {
                const pedido = pedidos.find(p => p.id_usuario === u.id);
                return {
                    id:       u.id,
                    nombre:   u.nombre,
                    addr:     pedido ? pedido.direccion : (u.direccion || ''),
                    telefono: u.telefono || '',
                    mascotas: dataM.mascotas.filter(m => m.id_usuario === u.id),
                    // Coordenadas exactas del pedido pagado (si existe)
                    coords:   pedido ? { lat: pedido.lat, lng: pedido.lng } : null,
                    pedido:   pedido || null,
                };
            })
            // Solo clientes con al menos una mascota registrada
            .filter(c => c.mascotas.length > 0);
    } catch (err) {
        console.error('Error cargando clientes:', err);
    }
}

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

        // Coordenadas de reserva mientras un paseador no ha enviado GPS todavía
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
            // GPS real si existe; si no, punto de referencia en Cúcuta
            lat:   p.lat !== null && p.lat !== undefined ? p.lat : coordsBase[i % coordsBase.length].lat,
            lng:   p.lng !== null && p.lng !== undefined ? p.lng : coordsBase[i % coordsBase.length].lng,
            rutaActual: p.id_ruta_activa ? `Ruta #${p.id_ruta_activa}` : null,
            rutasHistorial: [],
        }));

        paseadorSel = PASEADORES[0] || null;
        updateStats();
        renderLista();
        renderDetalle();
        renderHistorial();
        setTimeout(() => {
            initMapa();
            if (!window.__gpsRefreshTimer) {
                window.__gpsRefreshTimer = setInterval(refrescarPosiciones, 8000);
            }
        }, 300);
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
        <button class="p-action-btn" style="background:#eef2ff;color:#4338ca" title="Cronograma semanal" onclick="abrirModalCronograma(${p.id},event)"><i class="fas fa-calendar-week"></i></button>
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
      <button class="dqa-btn" style="background:#eef2ff;color:#4338ca;border:1.5px solid #c7d2fe" onclick="abrirModalCronograma(${p.id},event)">
        <i class="fas fa-calendar-week"></i> Cronograma
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
    if (mapInstance) return; // ya inicializado (cargarPaseadores puede llamarse varias veces)
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

    if (!CLIENTES_DISPONIBLES.length) {
        list.innerHTML = `
      <div style="text-align:center;padding:18px;color:var(--muted);font-size:.82rem">
        <i class="fas fa-users" style="font-size:1.4rem;display:block;margin-bottom:6px;color:#e2e8f0"></i>
        No hay clientes con mascotas registradas todavía.
      </div>`;
        return;
    }

    CLIENTES_DISPONIBLES.forEach(c => {
        const sel = selectedRouteClients.includes(c.id);
        const sinDireccion = !c.addr;
        const div = document.createElement('div');
        div.className = 'client-route-item' + (sel ? ' selected' : '');
        div.innerHTML = `
      <div class="cri-avatar" style="background:${COLORS[c.id % COLORS.length]}">${c.nombre[0]}</div>
      <div class="cri-info">
        <div class="cri-name">${c.nombre}</div>
        <div class="cri-addr"><i class="fas fa-map-pin" style="font-size:.6rem"></i>${c.addr || 'Sin dirección registrada'}</div>
        <div class="cri-meta">
          ${c.mascotas.map(m => `<span class="cri-tag">🐕 ${m.nombre}</span>`).join('')}
          ${c.pedido ? '<span class="cri-tag" style="background:#dcfce7;color:#15803d;border-color:#86efac">✓ Plan pagado · ubicación exacta</span>' : ''}
          ${sinDireccion ? '<span class="cri-tag" style="background:#fee2e2;color:#b91c1c;border-color:#fca5a5">⚠ Falta dirección</span>' : ''}
        </div>
      </div>
      <div class="cri-check"><i class="fas fa-check"></i></div>
    `;
        div.addEventListener('click', () => {
            if (sinDireccion) {
                showToast(`${c.nombre} no tiene dirección registrada. Pídele completarla en su perfil o usa el mapa.`, 'warning');
                return;
            }
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

// Convierte la dirección registrada del cliente en coordenadas (Nominatim, gratuito)
async function geocodificarDireccion(direccion) {
    const q = encodeURIComponent(direccion + ', Cúcuta, Colombia');
    const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${q}&limit=1&accept-language=es`);
    const results = await res.json();
    if (!results.length) return null;
    return { lat: parseFloat(results[0].lat), lng: parseFloat(results[0].lon) };
}

document.getElementById('confirmRoute').addEventListener('click', async () => {
    if (selectedRouteClients.length === 0) {
        showToast('Selecciona al menos un cliente', 'warning'); return;
    }
    if (!paseadorParaRuta) return;

    const btn = document.getElementById('confirmRoute');
    btn.disabled = true;
    const labelOriginal = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creando ruta...';

    try {
        const clientes = selectedRouteClients
            .map(id => CLIENTES_DISPONIBLES.find(c => c.id === id))
            .filter(Boolean);

        // 1. Obtener coordenadas de cada cliente:
        //    - si tiene pedido pagado del wizard, usar sus coords exactas
        //    - si no, geocodificar la dirección (1 por segundo, límite de Nominatim)
        const coordsPorCliente = [];
        for (const c of clientes) {
            let coords = c.coords || null;
            if (!coords) {
                coords = await geocodificarDireccion(c.addr);
                if (clientes.length > 1) await new Promise(r => setTimeout(r, 1100));
            }
            if (!coords) {
                showToast(`No se encontró la dirección de ${c.nombre} ("${c.addr}"). Usa el mapa para ubicarla manualmente.`, 'warning');
                btn.disabled = false; btn.innerHTML = labelOriginal;
                return;
            }
            coordsPorCliente.push({ cliente: c, coords });
        }

        // 2. Armar paradas: recogida en cada casa y entrega de regreso (misma dirección)
        const puntos = [];
        coordsPorCliente.forEach(({ cliente, coords }) => {
            puntos.push({
                lat: coords.lat, lng: coords.lng,
                addr: cliente.addr, tipo: 'recogida',
                id_usuario_cliente: cliente.id,
                id_mascota: cliente.mascotas[0].id_mascota,
            });
        });
        coordsPorCliente.forEach(({ cliente, coords }) => {
            puntos.push({
                lat: coords.lat, lng: coords.lng,
                addr: cliente.addr, tipo: 'entrega',
                id_usuario_cliente: cliente.id,
                id_mascota: cliente.mascotas[0].id_mascota,
            });
        });

        // 3. Crear la ruta en la BD (mismo endpoint que usa el mapa)
        const payload = {
            id_paseador: paseadorParaRuta.id,
            fecha: document.getElementById('routeDate').value || new Date().toISOString().split('T')[0],
            hora:  document.getElementById('routeTime').value || '08:00',
            puntos,
        };
        const res  = await fetch('../../../model/guardar_ruta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();

        if (!data.success) {
            showToast('Error: ' + (data.message || 'No se pudo crear la ruta'), 'warning');
            return;
        }

        document.getElementById('routeModal').classList.remove('open');
        showToast(`✅ Ruta #${data.id_ruta} asignada a ${paseadorParaRuta.nombre}`, 'success');
        cargarPaseadores(); // refresca estados y contadores desde la BD
    } catch (err) {
        console.error(err);
        showToast('Error de conexión al crear la ruta', 'warning');
    } finally {
        btn.disabled = false;
        btn.innerHTML = labelOriginal;
    }
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

// Refresco de posiciones GPS reales (sin re-render completo de la lista)
async function refrescarPosiciones() {
    try {
        const res  = await fetch('../../../model/obtener_paseadores.php');
        const data = await res.json();
        if (!data.success) return;
        data.paseadores.forEach(nuevo => {
            const p = PASEADORES.find(x => x.id === nuevo.id);
            if (!p) return;
            if (nuevo.lat !== null && nuevo.lng !== null) {
                p.lat = nuevo.lat;
                p.lng = nuevo.lng;
            }
            p.estado    = nuevo.estado;
            p.velocidad = nuevo.velocidad;
        });
        if (paseadorSel) actualizarMapa();
    } catch (err) { /* siguiente tick */ }
}

// ══════════════════════════════════════════════════════════════
// CRONOGRAMA SEMANAL (modal por paseador)
// ══════════════════════════════════════════════════════════════
const DIAS_CRONO = { 1: 'LUN', 2: 'MAR', 3: 'MIÉ', 4: 'JUE', 5: 'VIE', 6: 'SÁB', 7: 'DOM' };
let cronoPaseadorId = null;
let cronoDiaSel = Math.min(new Date().getDay() === 0 ? 7 : new Date().getDay(), 7); // día actual (1=lun..7=dom)
let CRONOGRAMA = null;

async function abrirModalCronograma(id, e) {
    if (e) e.stopPropagation();
    const p = PASEADORES.find(x => x.id === id);
    if (!p) return;
    cronoPaseadorId = id;
    document.getElementById('cronoSub').textContent = `Paseador: ${p.nombre} — asigna clientes con plan pagado por día`;
    document.getElementById('cronoPills').innerHTML =
        '<div style="padding:14px;color:#94a3b8;font-size:.8rem">Cargando cronograma...</div>';
    document.getElementById('cronoClientes').innerHTML = '';
    document.getElementById('cronoModal').classList.add('open');

    try {
        const r = await fetch(`../../../model/obtener_cronograma.php?id_paseador=${id}`);
        const data = await r.json();
        if (!data.success) { showToast(data.message || 'Error al cargar el cronograma', 'warning'); return; }
        CRONOGRAMA = data.cronograma;
        renderCronoPills();
        renderCronoDia();
    } catch (err) {
        showToast('Error de conexión al cargar el cronograma', 'warning');
    }
}

function renderCronoPills() {
    const cont = document.getElementById('cronoPills');
    cont.innerHTML = '';
    for (let d = 1; d <= 7; d++) {
        const n = (CRONOGRAMA[d] || []).length;
        const pill = document.createElement('div');
        pill.className = 'crono-pill' + (d === cronoDiaSel ? ' sel' : '');
        pill.innerHTML = `
            <div class="cp-dia">${DIAS_CRONO[d]}</div>
            <div class="cp-num">${d}</div>
            <span class="cp-cnt">${n ? n + ' Perro' + (n > 1 ? 's' : '') : (d === 7 ? 'Descanso' : 'Libre')}</span>`;
        pill.addEventListener('click', () => { cronoDiaSel = d; renderCronoPills(); renderCronoDia(); });
        cont.appendChild(pill);
    }
}

function renderCronoDia() {
    const nombreDia = { 1: 'lunes', 2: 'martes', 3: 'miércoles', 4: 'jueves', 5: 'viernes', 6: 'sábado', 7: 'domingo' }[cronoDiaSel];
    document.getElementById('cronoDiaTitulo').innerHTML =
        `<i class="fas fa-dog" style="margin-right:5px"></i>Paseos del ${nombreDia}`;

    const cont = document.getElementById('cronoClientes');
    cont.innerHTML = '';

    // Solo clientes con plan pagado (tienen pedido con coords validadas)
    const conPlan = CLIENTES_DISPONIBLES.filter(c => c.pedido);
    if (!conPlan.length) {
        cont.innerHTML = `<div style="padding:14px;color:#94a3b8;font-size:.8rem;text-align:center">
            No hay clientes con plan pagado todavía. Los clientes aparecen aquí después de comprar su mensualidad.</div>`;
        return;
    }

    const asignadosHoy = (CRONOGRAMA[cronoDiaSel] || []).map(x => x.id_pedido);

    conPlan.forEach(c => {
        const idPedido = c.pedido.id_pedido;
        const sel = asignadosHoy.includes(idPedido);
        const div = document.createElement('div');
        div.className = 'crono-cliente' + (sel ? ' sel' : '');
        div.innerHTML = `
            <input type="checkbox" ${sel ? 'checked' : ''} data-pedido="${idPedido}">
            <div class="cc-info">
                <div class="cc-nombre">${c.nombre}</div>
                <div class="cc-sub">🐕 ${c.pedido.mascota || (c.mascotas[0] && c.mascotas[0].nombre) || ''} · 📍 ${c.pedido.barrio || c.pedido.direccion} · ${c.pedido.franja_horaria || ''}</div>
            </div>
            <span class="cri-tag" style="background:#dcfce7;color:#15803d;border:1px solid #86efac;font-size:.62rem;padding:2px 8px;border-radius:10px">✓ Plan pagado</span>`;
        const chk = div.querySelector('input');
        div.addEventListener('click', ev => {
            if (ev.target !== chk) chk.checked = !chk.checked;
            div.classList.toggle('sel', chk.checked);
        });
        cont.appendChild(div);
    });
}

async function guardarCronoDia() {
    const ids = Array.from(document.querySelectorAll('#cronoClientes input:checked'))
        .map(i => parseInt(i.dataset.pedido));

    const btn = document.getElementById('confirmCrono');
    btn.disabled = true;
    const original = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

    try {
        const r = await fetch('../../../model/guardar_cronograma.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                accion: 'reemplazar_dia',
                id_paseador: cronoPaseadorId,
                dia_semana: cronoDiaSel,
                ids_pedidos: ids,
            }),
        });
        const data = await r.json();
        if (!data.success) { showToast(data.message || 'No se pudo guardar', 'warning'); return; }
        showToast('✅ ' + data.message, 'success');
        // Refrescar contadores del modal
        const r2 = await fetch(`../../../model/obtener_cronograma.php?id_paseador=${cronoPaseadorId}`);
        const d2 = await r2.json();
        if (d2.success) { CRONOGRAMA = d2.cronograma; renderCronoPills(); renderCronoDia(); }
    } catch (err) {
        showToast('Error de conexión al guardar el cronograma', 'warning');
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
}

document.getElementById('closeCronoModal').addEventListener('click', () =>
    document.getElementById('cronoModal').classList.remove('open'));
document.getElementById('cancelCrono').addEventListener('click', () =>
    document.getElementById('cronoModal').classList.remove('open'));
document.getElementById('cronoModal').addEventListener('click', e => {
    if (e.target === document.getElementById('cronoModal'))
        document.getElementById('cronoModal').classList.remove('open');
});
document.getElementById('confirmCrono').addEventListener('click', guardarCronoDia);

// ══════════════════════════════════════════════════════════════
// INIT
// ══════════════════════════════════════════════════════════════
cargarPaseadores();
cargarClientes();