// ══════════════════════════════════════════════════════════════
// PASEOS_ADMIN.JS — Gestión de pedidos de mensualidad comprados
// Muestra los pedidos reales (pedidos_paseo) y permite asignarlos
// al cronograma semanal de un paseador (cronograma_paseos).
// ══════════════════════════════════════════════════════════════
const API = '../../../model/';

let PEDIDOS = [];
let PASEADORES = [];
let filtroActual = 'todos';
let selectedId = null;

const DIAS_CORTO = { 1: 'Lun', 2: 'Mar', 3: 'Mié', 4: 'Jue', 5: 'Vie', 6: 'Sáb', 7: 'Dom' };
const cop = n => '$' + Math.round(n).toLocaleString('es-CO');

// ── Toast ──────────────────────────────────────────────────────
let toastTimer;
function showToast(msg, type = 'success') {
    const toast = document.getElementById('toast');
    toast.className = `toast ${type} show`;
    toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'info' ? 'info-circle' : 'exclamation-circle'}"></i> ${msg}`;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('show'), 3400);
}

// ── Helpers de presentación ────────────────────────────────────
function diasTexto(dias) {
    return (dias || []).map(d => DIAS_CORTO[d]).filter(Boolean).join(', ');
}

function estadoPedido(p) {
    if (p.estado === 'cancelado')  return { lbl: 'Cancelado',          cls: 'b-cancelado',  dot: '#ef4444' };
    if (p.asignacion)              return { lbl: 'En cronograma',      cls: 'b-completado', dot: '#25D366' };
    if (p.estado === 'listo_para_asignar' || p.estado === 'pagado')
                                   return { lbl: 'Listo para asignar', cls: 'b-programado', dot: '#f97316' };
    return                                { lbl: 'Sin pagar',          cls: 'b-proceso',    dot: '#94a3b8' };
}

function diasPreferidosTxt(csv) {
    const mapa = { lun: 'Lun', mar: 'Mar', mie: 'Mié', jue: 'Jue', vie: 'Vie', sab: 'Sáb', dom: 'Dom' };
    return (csv || '').split(',').map(k => mapa[k.trim()] || '').filter(Boolean).join(', ') || '—';
}

// ── Cargar datos ───────────────────────────────────────────────
async function cargarPedidos() {
    try {
        const r = await fetch(API + 'obtener_pedidos_paseos.php?estado=todos');
        const data = await r.json();
        if (!data.success) { showToast(data.message || 'Error al cargar pedidos', 'error'); return; }
        PEDIDOS = data.pedidos;
        renderStats();
        renderAlertas();
        renderCards();
        if (selectedId) renderDetalle(selectedId);
    } catch (e) {
        showToast('Error de conexión al cargar los pedidos', 'error');
    }
}

async function cargarPaseadores() {
    try {
        const r = await fetch(API + 'obtener_paseadores.php');
        const data = await r.json();
        if (data.success) PASEADORES = data.paseadores;
    } catch (e) { /* el select quedará vacío */ }
}

// ── Stats ──────────────────────────────────────────────────────
function renderStats() {
    const hoy = new Date().toISOString().split('T')[0];
    document.getElementById('st-total').textContent  = PEDIDOS.length;
    document.getElementById('st-listos').textContent = PEDIDOS.filter(p => !p.asignacion && (p.estado === 'listo_para_asignar' || p.estado === 'pagado')).length;
    document.getElementById('st-crono').textContent  = PEDIDOS.filter(p => p.asignacion).length;
    document.getElementById('st-hoy').textContent    = PEDIDOS.filter(p => p.fecha_inicio === hoy && p.estado !== 'cancelado').length;
    document.getElementById('st-cancel').textContent = PEDIDOS.filter(p => p.estado === 'cancelado').length;
}

// ── Alertas (pedidos pagados sin cronograma) ───────────────────
function renderAlertas() {
    const strip = document.getElementById('alertasStrip');
    const sinAsignar = PEDIDOS.filter(p => !p.asignacion && (p.estado === 'listo_para_asignar' || p.estado === 'pagado'));
    if (!sinAsignar.length) { strip.style.display = 'none'; return; }
    strip.style.display = 'flex';
    strip.innerHTML = `
        <i class="fas fa-triangle-exclamation al-icon"></i>
        <div style="flex:1">
            <div class="al-title">${sinAsignar.length} pedido(s) pagado(s) sin cronograma asignado</div>
            <div class="al-chips">${sinAsignar.map(p =>
                `<span class="al-chip" data-id="${p.id_pedido}">${p.mascota} · ${p.cliente}</span>`).join('')}</div>
        </div>`;
    strip.querySelectorAll('.al-chip').forEach(ch =>
        ch.addEventListener('click', () => { seleccionar(parseInt(ch.dataset.id)); }));
}

// ── Tarjetas ───────────────────────────────────────────────────
function pedidosFiltrados() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    return PEDIDOS.filter(p => {
        const texto = `${p.mascota} ${p.cliente} ${p.barrio} ${p.paseos_mes} paseos ${p.asignacion ? p.asignacion.paseador : ''}`.toLowerCase();
        const matchQ = !q || texto.includes(q);
        let matchF = true;
        if (filtroActual === 'listos')     matchF = !p.asignacion && (p.estado === 'listo_para_asignar' || p.estado === 'pagado');
        if (filtroActual === 'asignados')  matchF = !!p.asignacion;
        if (filtroActual === 'cancelados') matchF = p.estado === 'cancelado';
        return matchQ && matchF;
    });
}

function renderCards() {
    const grid  = document.getElementById('cardsGrid');
    const empty = document.getElementById('emptyState');
    const lista = pedidosFiltrados();
    grid.innerHTML = '';

    if (!lista.length) { empty.classList.add('visible'); return; }
    empty.classList.remove('visible');

    lista.forEach(p => {
        const st = estadoPedido(p);
        const div = document.createElement('div');
        div.className = 'paseo-card' + (selectedId === p.id_pedido ? ' selected' : '') + (!p.asignacion && st.cls === 'b-programado' ? ' sin-paseador' : '');
        div.innerHTML = `
            <div class="pc-header">
                <span class="pc-emoji">🐾</span>
                <span class="pc-dot" style="background:${st.dot}"></span>
            </div>
            <div class="pc-body">
                <div class="pc-name">${p.mascota}</div>
                <div class="pc-sub">Pedido #${p.id_pedido} · ${p.paseos_mes} paseos/mes</div>
                <div class="pc-tags">
                    <span class="pc-tag ${st.cls}">${st.lbl}</span>
                    <span class="pc-tag modalidad">${p.modalidad === 'grupal' ? 'Grupal' : 'Individual'}</span>
                </div>
                <div class="pc-meta"><i class="fas fa-calendar"></i> ${diasPreferidosTxt(p.dias_preferidos)} · ${p.franja_horaria || '—'}</div>
                <div class="pc-meta"><i class="fas fa-location-dot"></i> ${p.barrio || p.direccion}</div>
                ${p.asignacion ? `<div class="pc-meta" style="color:#15803d"><i class="fas fa-person-walking"></i> ${p.asignacion.paseador} (${diasTexto(p.asignacion.dias)})</div>` : ''}
                <div class="pc-owner"><i class="fas fa-user"></i> ${p.cliente} · <strong>${cop(p.total)}</strong></div>
            </div>`;
        div.addEventListener('click', () => seleccionar(p.id_pedido));
        grid.appendChild(div);
    });
}

// ── Detalle ────────────────────────────────────────────────────
function seleccionar(id) {
    selectedId = id;
    renderCards();
    renderDetalle(id);
}

function renderDetalle(id) {
    const p = PEDIDOS.find(x => x.id_pedido === id);
    if (!p) return;
    const st = estadoPedido(p);
    document.getElementById('dpEmpty').style.display = 'none';
    const dp = document.getElementById('dpContent');
    dp.style.display = 'block';
    dp.innerHTML = `
        <div class="dp-head">
            <span class="dp-emoji">🐕</span>
            <div>
                <div class="dp-title">${p.mascota}</div>
                <div class="dp-code">Pedido #${p.id_pedido} · ${new Date(p.fecha_compra).toLocaleDateString('es-CO')}</div>
            </div>
            <span class="badge-st ${st.cls}" style="margin-left:auto">${st.lbl}</span>
        </div>

        <div class="dp-section">
            <div class="dp-label">Cliente</div>
            <div class="dp-row"><i class="fas fa-user"></i> ${p.cliente}</div>
            ${p.telefono ? `<div class="dp-row"><i class="fas fa-phone"></i> ${p.telefono}</div>` : ''}
        </div>

        <div class="dp-section">
            <div class="dp-label">Plan contratado</div>
            <div class="dp-row"><i class="fas fa-receipt"></i> ${p.paseos_mes} paseos al mes · <strong>${cop(p.total)}</strong></div>
            <div class="dp-row"><i class="fas fa-clock"></i> ${p.duracion_min} min · ${p.modalidad === 'grupal' ? 'Grupal' : 'Individual'}</div>
            <div class="dp-row"><i class="fas fa-calendar"></i> ${diasPreferidosTxt(p.dias_preferidos)} · ${p.franja_horaria || '—'}</div>
            <div class="dp-row"><i class="fas fa-play"></i> Inicia: ${p.fecha_inicio}</div>
        </div>

        <div class="dp-section">
            <div class="dp-label">Recogida y entrega</div>
            <div class="dp-row"><i class="fas fa-location-dot"></i> ${p.direccion}${p.barrio ? ', ' + p.barrio : ''}</div>
            ${p.referencia ? `<div class="dp-row"><i class="fas fa-map-pin"></i> ${p.referencia}</div>` : ''}
            ${p.instrucciones ? `<div class="dp-note">📋 ${p.instrucciones}</div>` : ''}
        </div>

        ${p.comportamiento || p.observaciones ? `
        <div class="dp-section">
            <div class="dp-label">Comportamiento</div>
            ${p.comportamiento ? `<div class="dp-row"><i class="fas fa-paw"></i> ${p.comportamiento}</div>` : ''}
            ${p.observaciones ? `<div class="dp-note">⚠ ${p.observaciones}</div>` : ''}
        </div>` : ''}

        <div class="dp-section">
            <div class="dp-label">Cronograma</div>
            ${p.asignacion
                ? `<div class="dp-row" style="color:#15803d"><i class="fas fa-person-walking"></i> ${p.asignacion.paseador} — ${diasTexto(p.asignacion.dias)}</div>`
                : `<div class="dp-row" style="color:var(--muted)"><i class="fas fa-circle-info"></i> Aún sin asignar a un paseador</div>`}
            ${p.estado === 'cancelado' ? `
            <button class="btn-primary" style="width:100%;margin-top:10px;background:#dcfce7;color:#15803d;border:1px solid #86efac" onclick="abrirModalAsignar(${p.id_pedido})">
                <i class="fas fa-rotate-left"></i> Reactivar y asignar al cronograma
            </button>` : `
            <button class="btn-primary" style="width:100%;margin-top:10px" onclick="abrirModalAsignar(${p.id_pedido})">
                <i class="fas fa-calendar-plus"></i> ${p.asignacion ? 'Agregar días / cambiar' : 'Asignar al cronograma'}
            </button>
            <button class="btn-primary" style="width:100%;margin-top:8px;background:#fee2e2;color:#b91c1c;border:1px solid #fecaca" onclick="cancelarPedido(${p.id_pedido})">
                <i class="fas fa-ban"></i> Cancelar servicio
            </button>`}
        </div>`;
}

// ── Cancelar servicio (pedido + membresía + cronograma) ────────
function cancelarPedido(idPedido) {
    const p = PEDIDOS.find(x => x.id_pedido === idPedido);
    if (!p) return;
    document.getElementById('cancelServicioPedidoId').value = idPedido;
    document.getElementById('cancelServicioSub').textContent =
        `${p.mascota} (${p.cliente}) — se desactivará su membresía, saldrá del cronograma y se avisará al cliente.`;
    document.getElementById('cancelServicioMotivo').value = '';
    document.getElementById('cancelServicioError').style.display = 'none';
    document.getElementById('cancelServicioModal').classList.add('open');
}

function cerrarModalCancelServicio() {
    document.getElementById('cancelServicioModal').classList.remove('open');
}

async function confirmarCancelarServicio() {
    const idPedido = parseInt(document.getElementById('cancelServicioPedidoId').value, 10);
    const motivo   = document.getElementById('cancelServicioMotivo').value.trim();
    const errorEl  = document.getElementById('cancelServicioError');

    if (!motivo) {
        errorEl.textContent = 'Debes indicar el motivo de la cancelación.';
        errorEl.style.display = 'block';
        return;
    }

    const btn = document.getElementById('confirmCancelServicio');
    btn.disabled = true;
    try {
        const r = await fetch(API + 'cancelar_pedido_paseos.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_pedido: idPedido, motivo }),
        });
        const data = await r.json();
        if (!data.success) {
            errorEl.textContent = data.message || 'No se pudo cancelar.';
            errorEl.style.display = 'block';
            return;
        }
        cerrarModalCancelServicio();
        showToast('✅ ' + data.message, 'success');
        cargarPedidos();
    } catch (e) {
        errorEl.textContent = 'Error de conexión al cancelar.';
        errorEl.style.display = 'block';
    } finally {
        btn.disabled = false;
    }
}

// ── Modal asignar al cronograma ────────────────────────────────
function abrirModalAsignar(idPedido) {
    const p = PEDIDOS.find(x => x.id_pedido === idPedido);
    if (!p) return;
    document.getElementById('assignPedidoId').value = idPedido;
    document.getElementById('assignModalTitle').textContent = `Asignar a ${p.mascota} (${p.cliente})`;
    document.getElementById('assignModalSub').textContent = `Días preferidos del cliente: ${diasPreferidosTxt(p.dias_preferidos)} · ${p.franja_horaria || ''}`;

    // Select de paseadores
    const sel = document.getElementById('assignPaseador');
    sel.innerHTML = '<option value="">— Seleccionar paseador —</option>' +
        PASEADORES.map(x => `<option value="${x.id}" ${p.asignacion && p.asignacion.id_paseador === x.id ? 'selected' : ''}>${x.nombre}</option>`).join('');

    // Chips de días (pre-marcados con los preferidos del cliente si no hay asignación)
    const mapa = { lun: 1, mar: 2, mie: 3, jue: 4, vie: 5, sab: 6, dom: 7 };
    const preferidos = (p.dias_preferidos || '').split(',').map(k => mapa[k.trim()]).filter(Boolean);
    const actuales = p.asignacion ? p.asignacion.dias : preferidos;
    const cont = document.getElementById('assignDias');
    cont.innerHTML = Object.entries(DIAS_CORTO).map(([n, txt]) => `
        <span class="sf-btn dia-chip ${actuales.includes(parseInt(n)) ? 'active' : ''}" data-dia="${n}"
              style="cursor:pointer">${txt}</span>`).join('');
    cont.querySelectorAll('.dia-chip').forEach(ch =>
        ch.addEventListener('click', () => ch.classList.toggle('active')));

    document.getElementById('assignModal').classList.add('open');
}

async function confirmarAsignacion() {
    const idPedido   = parseInt(document.getElementById('assignPedidoId').value);
    const idPaseador = parseInt(document.getElementById('assignPaseador').value || '0');
    const dias = Array.from(document.querySelectorAll('#assignDias .dia-chip.active')).map(c => parseInt(c.dataset.dia));

    if (!idPaseador) { showToast('Selecciona un paseador', 'error'); return; }
    if (!dias.length) { showToast('Elige al menos un día de la semana', 'error'); return; }

    const btn = document.getElementById('confirmAssign');
    btn.disabled = true;
    const original = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Asignando...';

    try {
        const r = await fetch(API + 'guardar_cronograma.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'asignar_pedido', id_pedido: idPedido, id_paseador: idPaseador, dias }),
        });
        const data = await r.json();
        if (!data.success) { showToast(data.message || 'No se pudo asignar', 'error'); return; }
        document.getElementById('assignModal').classList.remove('open');
        showToast('✅ ' + data.message, 'success');
        cargarPedidos();
    } catch (e) {
        showToast('Error de conexión al asignar', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
}

// ── Filtros / listeners ────────────────────────────────────────
document.querySelectorAll('.filter-bar .sf-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.filter-bar .sf-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        filtroActual = btn.dataset.filter;
        renderCards();
    });
});
document.getElementById('searchInput').addEventListener('input', renderCards);

document.getElementById('closeAssignModal').addEventListener('click', () => document.getElementById('assignModal').classList.remove('open'));
document.getElementById('cancelAssign').addEventListener('click', () => document.getElementById('assignModal').classList.remove('open'));
document.getElementById('assignModal').addEventListener('click', e => {
    if (e.target === document.getElementById('assignModal')) document.getElementById('assignModal').classList.remove('open');
});

document.getElementById('closeCancelServicioModal').addEventListener('click', cerrarModalCancelServicio);
document.getElementById('volverCancelServicio').addEventListener('click', cerrarModalCancelServicio);
document.getElementById('confirmCancelServicio').addEventListener('click', confirmarCancelarServicio);
document.getElementById('cancelServicioModal').addEventListener('click', e => {
    if (e.target === document.getElementById('cancelServicioModal')) cerrarModalCancelServicio();
});
document.getElementById('confirmAssign').addEventListener('click', confirmarAsignacion);

// ── Sidebar hamburguesa ────────────────────────────────────────
const btnMenu = document.getElementById('btn-menu');
const menuLatente = document.getElementById('menu-latente');
btnMenu.addEventListener('click', e => { e.stopPropagation(); menuLatente.classList.toggle('show'); });
window.addEventListener('click', e => {
    if (!btnMenu.contains(e.target) && !menuLatente.contains(e.target)) menuLatente.classList.remove('show');
});

// ── INIT ───────────────────────────────────────────────────────
cargarPedidos();
cargarPaseadores();