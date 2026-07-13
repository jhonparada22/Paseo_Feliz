// ══════════════════════════════════════════════════════════════
// HOSPEDAJE_ADMIN.JS — Gestión de reservas de hospedaje
// A diferencia de Paseos/Adiestramiento, aquí NO se asigna personal:
// la recogida y entrega las hace la van de un administrador. Este panel
// solo deja marcar en qué fase de esa logística va cada reserva.
// ══════════════════════════════════════════════════════════════
const API = '../../../model/';

let PEDIDOS = [];
let filtroActual = 'todos';
let selectedId = null;

const cop = n => '$' + Math.round(n).toLocaleString('es-CO');

const FASES = ['confirmado', 'recogida_en_camino', 'en_hospedaje', 'entrega_en_camino', 'entregado'];
const FASE_LBL = {
    confirmado:          'Compra confirmada',
    recogida_en_camino:  'Recogida en camino',
    en_hospedaje:        'Mascota en hospedaje',
    entrega_en_camino:   'Entrega en camino',
    entregado:           'Entregado',
};
const FASE_SIGUIENTE_LBL = {
    confirmado:          'Marcar recogida en camino',
    recogida_en_camino:  'Confirmar recogida (ya está en hospedaje)',
    en_hospedaje:        'Marcar entrega en camino',
    entrega_en_camino:   'Confirmar entrega',
};

// ── Toast ──────────────────────────────────────────────────────
let toastTimer;
function showToast(msg, type = 'success') {
    const toast = document.getElementById('toast');
    toast.className = `toast ${type} show`;
    toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'info' ? 'info-circle' : 'exclamation-circle'}"></i> ${msg}`;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('show'), 3400);
}

function fmtFecha(str) {
    if (!str) return '—';
    const d = new Date(String(str).replace(' ', 'T'));
    if (isNaN(d)) return str;
    return d.toLocaleDateString('es-CO', { day: 'numeric', month: 'short', year: 'numeric' }) + ', ' +
           d.toLocaleTimeString('es-CO', { hour: 'numeric', minute: '2-digit', hour12: true });
}

function estadoPedido(p) {
    if (p.estado === 'cancelado') return { lbl: 'Cancelado', cls: 'b-cancelado', dot: '#ef4444' };
    if (p.fase_logistica === 'entregado') return { lbl: 'Entregado', cls: 'b-completado', dot: '#25D366' };
    if (p.fase_logistica === 'en_hospedaje' || p.fase_logistica === 'entrega_en_camino')
        return { lbl: FASE_LBL[p.fase_logistica], cls: 'b-completado', dot: '#3E72A6' };
    return { lbl: FASE_LBL[p.fase_logistica] || 'Compra confirmada', cls: 'b-programado', dot: '#f97316' };
}

// ── Cargar datos ───────────────────────────────────────────────
async function cargarPedidos() {
    try {
        const r = await fetch(API + 'obtener_pedidos_hospedaje.php?estado=todos');
        const data = await r.json();
        if (!data.success) { showToast(data.message || 'Error al cargar reservas', 'error'); return; }
        PEDIDOS = data.pedidos;
        renderStats();
        renderCards();
        if (selectedId) renderDetalle(selectedId);
    } catch (e) {
        showToast('Error de conexión al cargar las reservas', 'error');
    }
}

// ── Stats ──────────────────────────────────────────────────────
function renderStats() {
    document.getElementById('st-total').textContent       = PEDIDOS.length;
    document.getElementById('st-pendientes').textContent  = PEDIDOS.filter(p => p.estado !== 'cancelado' && (p.fase_logistica === 'confirmado' || p.fase_logistica === 'recogida_en_camino')).length;
    document.getElementById('st-hospedadas').textContent  = PEDIDOS.filter(p => p.estado !== 'cancelado' && (p.fase_logistica === 'en_hospedaje' || p.fase_logistica === 'entrega_en_camino')).length;
    document.getElementById('st-entregadas').textContent  = PEDIDOS.filter(p => p.fase_logistica === 'entregado').length;
    document.getElementById('st-cancel').textContent      = PEDIDOS.filter(p => p.estado === 'cancelado').length;
}

// ── Tarjetas ───────────────────────────────────────────────────
function pedidosFiltrados() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    return PEDIDOS.filter(p => {
        const texto = `${p.mascota} ${p.cliente} ${p.barrio}`.toLowerCase();
        const matchQ = !q || texto.includes(q);
        let matchF = true;
        if (filtroActual === 'pendientes')  matchF = p.estado !== 'cancelado' && (p.fase_logistica === 'confirmado' || p.fase_logistica === 'recogida_en_camino');
        if (filtroActual === 'hospedadas')  matchF = p.estado !== 'cancelado' && (p.fase_logistica === 'en_hospedaje' || p.fase_logistica === 'entrega_en_camino');
        if (filtroActual === 'entregadas')  matchF = p.fase_logistica === 'entregado';
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
        div.className = 'paseo-card' + (selectedId === p.id_pedido ? ' selected' : '');
        div.innerHTML = `
            <div class="pc-header">
                <span class="pc-emoji">🏠</span>
                <span class="pc-dot" style="background:${st.dot}"></span>
            </div>
            <div class="pc-body">
                <div class="pc-name">${p.mascota}</div>
                <div class="pc-sub">Pedido #${p.id_pedido} · ${p.cantidad_noches} noche(s)</div>
                <div class="pc-tags">
                    <span class="pc-tag ${st.cls}">${st.lbl}</span>
                </div>
                <div class="pc-meta"><i class="fas fa-calendar"></i> ${fmtFecha(p.fecha_entrada)} → ${fmtFecha(p.fecha_salida)}</div>
                <div class="pc-meta"><i class="fas fa-location-dot"></i> ${p.barrio || p.direccion}</div>
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

    const fase = p.fase_logistica || 'confirmado';
    const idx = FASES.indexOf(fase);
    const puedeCancelarFase = p.estado !== 'cancelado';

    const timeline = FASES.map((f, i) => {
        const hecho = i < idx, activo = i === idx;
        return `<div class="dp-row" style="${hecho ? 'color:#15803d' : activo ? 'color:#3E72A6;font-weight:700' : 'color:var(--muted)'}">
                    <i class="fas fa-${hecho ? 'circle-check' : activo ? 'circle-dot' : 'circle'}"></i> ${FASE_LBL[f]}
                </div>`;
    }).join('');

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
            <div class="dp-label">Estadía</div>
            <div class="dp-row"><i class="fas fa-calendar-day"></i> Entrada: ${fmtFecha(p.fecha_entrada)}</div>
            <div class="dp-row"><i class="fas fa-calendar-day"></i> Salida: ${fmtFecha(p.fecha_salida)}</div>
            <div class="dp-row"><i class="fas fa-moon"></i> ${p.cantidad_noches} noche(s) · <strong>${cop(p.total)}</strong></div>
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
            <div class="dp-label">Estado de la logística (van)</div>
            ${timeline}
            ${p.hora_recogida_real ? `<div class="dp-row" style="color:var(--muted);font-size:.74rem">Recogida real: ${fmtFecha(p.hora_recogida_real)}</div>` : ''}
            ${p.hora_entrega_real ? `<div class="dp-row" style="color:var(--muted);font-size:.74rem">Entrega real: ${fmtFecha(p.hora_entrega_real)}</div>` : ''}
            ${puedeCancelarFase && idx < FASES.length - 1 ? `
            <button class="btn-primary" style="width:100%;margin-top:10px" onclick="avanzarFase(${p.id_pedido}, '${FASES[idx + 1]}')">
                <i class="fas fa-arrow-right"></i> ${FASE_SIGUIENTE_LBL[fase]}
            </button>` : ''}
            ${puedeCancelarFase && idx > 0 ? `
            <button class="btn-primary" style="width:100%;margin-top:8px;background:#f1f5f9;color:#475569;border:1px solid #e2e8f0" onclick="avanzarFase(${p.id_pedido}, '${FASES[idx - 1]}')">
                <i class="fas fa-arrow-left"></i> Retroceder (me equivoqué)
            </button>` : ''}
            ${p.estado !== 'cancelado' && fase !== 'entregado' ? `
            <button class="btn-primary" style="width:100%;margin-top:8px;background:#fee2e2;color:#b91c1c;border:1px solid #fecaca" onclick="cancelarPedido(${p.id_pedido})">
                <i class="fas fa-ban"></i> Cancelar servicio
            </button>` : ''}
        </div>`;
}

async function avanzarFase(idPedido, fase) {
    try {
        const r = await fetch(API + 'avanzar_fase_hospedaje.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_pedido: idPedido, fase }),
        });
        const data = await r.json();
        if (!data.success) { showToast(data.message || 'No se pudo actualizar', 'error'); return; }
        showToast('✅ ' + data.message, 'success');
        cargarPedidos();
    } catch (e) {
        showToast('Error de conexión al actualizar', 'error');
    }
}

// ── Cancelar servicio (pedido + membresía) ──────────────────────
function cancelarPedido(idPedido) {
    const p = PEDIDOS.find(x => x.id_pedido === idPedido);
    if (!p) return;
    document.getElementById('cancelServicioPedidoId').value = idPedido;
    document.getElementById('cancelServicioSub').textContent =
        `${p.mascota} (${p.cliente}) — se desactivará su membresía y se avisará al cliente.`;
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
        const r = await fetch(API + 'cancelar_pedido_hospedaje.php', {
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

document.getElementById('closeCancelServicioModal').addEventListener('click', cerrarModalCancelServicio);
document.getElementById('volverCancelServicio').addEventListener('click', cerrarModalCancelServicio);
document.getElementById('confirmCancelServicio').addEventListener('click', confirmarCancelarServicio);
document.getElementById('cancelServicioModal').addEventListener('click', e => {
    if (e.target === document.getElementById('cancelServicioModal')) cerrarModalCancelServicio();
});

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

// ── Sidebar hamburguesa ────────────────────────────────────────
const btnMenu = document.getElementById('btn-menu');
const menuLatente = document.getElementById('menu-latente');
btnMenu.addEventListener('click', e => { e.stopPropagation(); menuLatente.classList.toggle('show'); });
window.addEventListener('click', e => {
    if (!btnMenu.contains(e.target) && !menuLatente.contains(e.target)) menuLatente.classList.remove('show');
});

// ── INIT ───────────────────────────────────────────────────────
cargarPedidos();
