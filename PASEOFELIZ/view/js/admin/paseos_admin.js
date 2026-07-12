// ══════════════════════════════════════════════════════════════
// PASEOS_ADMIN.JS — Gestión de pedidos de mensualidad comprados
// Muestra los pedidos reales (pedidos_paseo) y permite asignarlos
// al cronograma semanal de un paseador (cronograma_paseos).
// ══════════════════════════════════════════════════════════════
const API = '../../../model/';

let PEDIDOS = [];
let PASEADORES = [];
let filtroActual = 'todos';
// Deep-link desde el Centro de Actividad: paseos_admin.php?pedido=19 abre
// ese pedido ya seleccionado (cargarPedidos llama renderDetalle si hay
// selectedId). Ver activity_center.js (acción "ver_paseo").
let selectedId = parseInt(new URLSearchParams(location.search).get('pedido'), 10) || null;
let deepLinkPending = !!selectedId;   // desplaza el detalle a la vista una vez
const seleccion = new Set();          // ids marcados para eliminar (multi-select)

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
    if (p.estado === 'en_validacion')
                                   return { lbl: 'Dirección por validar', cls: 'b-proceso', dot: '#8b5cf6' };
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
    document.getElementById('st-total').textContent   = PEDIDOS.length;
    document.getElementById('st-validar').textContent = PEDIDOS.filter(p => p.estado === 'en_validacion').length;
    document.getElementById('st-listos').textContent  = PEDIDOS.filter(p => !p.asignacion && (p.estado === 'listo_para_asignar' || p.estado === 'pagado')).length;
    document.getElementById('st-crono').textContent   = PEDIDOS.filter(p => p.asignacion).length;
    document.getElementById('st-cancel').textContent  = PEDIDOS.filter(p => p.estado === 'cancelado').length;
}

// ── Torre de control: operación de HOY (cronograma vs realidad) ──
async function cargarTorre() {
    try {
        const r = await fetch(API + 'torre_control.php');
        const data = await r.json();
        if (!data.success) return; // migración pendiente: la torre no se muestra
        renderTorre(data);
    } catch (e) { /* sin conexión: se reintenta en el próximo ciclo */ }
}

function renderTorre(data) {
    const cont = document.getElementById('torreHoy');
    if (!cont) return;
    const ps  = data.paseadores_hoy || [];
    const ne  = data.no_ejecutados || [];
    const inc = data.incidencias || [];
    if (!ps.length && !ne.length && !inc.length) { cont.style.display = 'none'; return; }

    const filas = ps.map(p => {
        const estado = p.alerta_no_inicio
            ? '<span style="background:#fee2e2;color:#b91c1c;font-weight:700;padding:2px 8px;border-radius:999px;font-size:.7rem">⚠ No ha iniciado</span>'
            : (p.inicio_jornada
                ? '<span style="background:#dcfce7;color:#15803d;font-weight:700;padding:2px 8px;border-radius:999px;font-size:.7rem">● En jornada</span>'
                : '<span style="background:#fef3c7;color:#b45309;font-weight:700;padding:2px 8px;border-radius:999px;font-size:.7rem">○ Sin iniciar</span>');
        return `
        <div style="display:flex;align-items:center;gap:10px;padding:7px 0;border-top:1px solid #f1f5f9;font-size:.78rem">
            <strong style="min-width:130px">${p.nombre}</strong>
            ${estado}
            <span style="color:#64748b">${p.primera_hora ? 'desde las ' + p.primera_hora : (p.primera_franja || '')}</span>
            <span style="margin-left:auto;display:flex;gap:8px;color:#475569">
                <span title="Pendientes">⏰ ${p.pendientes}</span>
                <span title="Recogidos">🐕 ${p.recogidos}</span>
                <span title="Completados">✅ ${p.completados}</span>
                ${p.cancelados ? `<span title="Cancelados" style="color:#b91c1c">✖ ${p.cancelados}</span>` : ''}
            </span>
        </div>`;
    }).join('');

    const incHtml = inc.length ? `
        <div style="margin-top:10px;padding-top:8px;border-top:1px dashed #e2e8f0">
            <div style="font-size:.72rem;font-weight:800;color:#b91c1c;margin-bottom:4px">
                <i class="fas fa-bullhorn"></i> Incidencias reportadas HOY
            </div>
            ${inc.map(x => `
            <div style="display:flex;gap:8px;align-items:center;font-size:.74rem;padding:3px 0;cursor:pointer" onclick="seleccionar(${x.id_pedido})">
                <span style="color:#94a3b8;min-width:38px">${x.hora}</span>
                <strong>${x.mascota}</strong>
                <span style="color:#64748b">(${x.cliente})</span>
                <span style="background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;border-radius:999px;padding:1px 8px;font-size:.68rem">${x.detalle || 'incidencia'}</span>
                <span style="margin-left:auto;color:#94a3b8">${x.paseador}</span>
            </div>`).join('')}
        </div>` : '';

    const neHtml = ne.length ? `
        <div style="margin-top:10px;padding-top:8px;border-top:1px dashed #e2e8f0">
            <div style="font-size:.72rem;font-weight:800;color:#b45309;margin-bottom:4px">
                <i class="fas fa-calendar-xmark"></i> Paseos NO ejecutados (últimos 7 días) — candidatos a reposición
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:6px">
                ${ne.map(x => `<span style="background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;border-radius:999px;padding:3px 10px;font-size:.7rem;cursor:pointer" onclick="seleccionar(${x.id_pedido})">
                    ${x.fecha.slice(5)} · ${x.mascota} (${x.paseador})</span>`).join('')}
            </div>
        </div>` : '';

    cont.style.display = 'block';
    cont.innerHTML = `
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px;margin-bottom:14px;box-shadow:0 1px 3px rgba(0,0,0,.04)">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                <span style="font-weight:800;font-size:.85rem">🚦 Operación de hoy</span>
                <span style="font-size:.7rem;color:#94a3b8">se actualiza cada minuto</span>
            </div>
            ${filas || '<div style="font-size:.78rem;color:#94a3b8;padding:6px 0">Ningún paseador tiene paseos programados hoy.</div>'}
            ${incHtml}
            ${neHtml}
        </div>`;
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
        const texto = `${p.mascota} ${p.cliente} ${p.barrio} ${p.asignacion ? p.asignacion.paseador : ''}`.toLowerCase();
        const matchQ = !q || texto.includes(q);
        let matchF = true;
        if (filtroActual === 'validar')    matchF = p.estado === 'en_validacion';
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
        div.className = 'paseo-card' + (selectedId === p.id_pedido ? ' selected' : '') + (!p.asignacion && st.cls === 'b-programado' ? ' sin-paseador' : '') + (seleccion.has(p.id_pedido) ? ' sel-check' : '');
        div.innerHTML = `
            <div class="pc-header">
                <label class="pc-check" title="Seleccionar para eliminar">
                    <input type="checkbox" data-sel="${p.id_pedido}" ${seleccion.has(p.id_pedido) ? 'checked' : ''}>
                </label>
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
        // El checkbox no debe abrir el detalle: solo marca/desmarca
        const chk = div.querySelector('.pc-check');
        chk.addEventListener('click', e => e.stopPropagation());
        chk.querySelector('input').addEventListener('change', e => toggleSeleccion(p.id_pedido, e.target.checked));
        grid.appendChild(div);
    });
    renderBulkBar();
}

// ── Selección múltiple + eliminación ───────────────────────────
function toggleSeleccion(id, checked) {
    if (checked) seleccion.add(id); else seleccion.delete(id);
    const card = document.querySelector(`.paseo-card [data-sel="${id}"]`)?.closest('.paseo-card');
    if (card) card.classList.toggle('sel-check', checked);
    renderBulkBar();
}

function limpiarSeleccion() {
    seleccion.clear();
    document.querySelectorAll('.pc-check input:checked').forEach(i => (i.checked = false));
    document.querySelectorAll('.paseo-card.sel-check').forEach(c => c.classList.remove('sel-check'));
    renderBulkBar();
}

function renderBulkBar() {
    const bar = document.getElementById('bulkBar');
    if (!bar) return;
    const n = seleccion.size;
    document.getElementById('bulkCount').textContent =
        n + (n === 1 ? ' pedido seleccionado' : ' pedidos seleccionados');
    bar.classList.toggle('show', n > 0);
}

function abrirModalEliminar() {
    if (!seleccion.size) return;
    const ids = [...seleccion];
    const nombres = ids.map(id => (PEDIDOS.find(p => p.id_pedido === id)?.mascota) || ('#' + id));
    document.getElementById('delModalCount').textContent = ids.length;
    document.getElementById('delModalList').textContent = nombres.join(', ');
    document.getElementById('deleteModal').classList.add('open');
}
function cerrarModalEliminar() { document.getElementById('deleteModal').classList.remove('open'); }

async function confirmarEliminar() {
    const ids = [...seleccion];
    if (!ids.length) return;
    const btn = document.getElementById('confirmDelete');
    btn.disabled = true;
    try {
        const r = await fetch(API + 'eliminar_pedidos_paseos.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids }),
        });
        const d = await r.json();
        if (!d.success) { showToast(d.message || 'No se pudo eliminar', 'error'); return; }
        // Si el detalle abierto quedó eliminado, se limpia
        if (selectedId && ids.includes(selectedId)) {
            selectedId = null;
            document.getElementById('dpContent').style.display = 'none';
            document.getElementById('dpEmpty').style.display = '';
        }
        seleccion.clear();
        cerrarModalEliminar();
        showToast(d.message, 'success');
        await cargarPedidos();
    } catch (e) {
        showToast('Error de conexión al eliminar', 'error');
    } finally {
        btn.disabled = false;
    }
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
    if (deepLinkPending) {
        deepLinkPending = false;
        setTimeout(() => dp.scrollIntoView({ behavior: 'smooth', block: 'start' }), 120);
    }
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

        ${p.estado === 'en_validacion' ? `
        <div class="dp-section" style="background:#f5f3ff;border:1px solid #ddd6fe;border-radius:10px;padding:10px">
            <div class="dp-label" style="color:#6d28d9"><i class="fas fa-map-pin"></i> Dirección pendiente de validar</div>
            <div class="dp-row" style="font-size:.76rem;color:var(--muted)">Verifica que el pin coincida con la dirección escrita antes de liberar el pedido a la cola de asignación.</div>
            <a class="btn-outline" style="width:100%;margin-top:8px;display:flex;align-items:center;justify-content:center;gap:6px;text-decoration:none"
               href="https://www.openstreetmap.org/?mlat=${p.lat}&mlon=${p.lng}#map=18/${p.lat}/${p.lng}" target="_blank" rel="noopener">
                <i class="fas fa-map-location-dot"></i> Ver pin en el mapa
            </a>
            <button class="btn-primary" style="width:100%;margin-top:8px" onclick="aprobarDireccion(${p.id_pedido})">
                <i class="fas fa-check"></i> Aprobar dirección
            </button>
        </div>` : ''}

        <div class="dp-section">
            <div class="dp-label">Cronograma</div>
            ${p.asignacion
                ? `<div class="dp-row" style="color:#15803d"><i class="fas fa-person-walking"></i> ${p.asignacion.paseador} — ${diasTexto(p.asignacion.dias)}</div>`
                : `<div class="dp-row" style="color:var(--muted)"><i class="fas fa-circle-info"></i> Aún sin asignar a un paseador</div>`}
            ${p.estado !== 'cancelado' && p.estado !== 'en_validacion' ? `
            <button class="btn-primary" style="width:100%;margin-top:10px" onclick="abrirModalAsignar(${p.id_pedido})">
                <i class="fas fa-calendar-plus"></i> ${p.asignacion ? 'Agregar días / cambiar' : 'Asignar al cronograma'}
            </button>` : ''}
            ${p.asignacion && p.estado !== 'cancelado' ? `
            <button class="btn-primary" style="width:100%;margin-top:8px;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe" onclick="abrirModalReasignar(${p.id_pedido})">
                <i class="fas fa-people-arrows"></i> Reasignar a otro paseador
            </button>` : ''}
            ${p.estado !== 'cancelado' ? `
            <button class="btn-primary" style="width:100%;margin-top:8px;background:#fee2e2;color:#b91c1c;border:1px solid #fecaca" onclick="cancelarPedido(${p.id_pedido})">
                <i class="fas fa-ban"></i> Cancelar servicio
            </button>` : ''}
        </div>`;
}

// ── Aprobar dirección (pedido en_validacion -> listo_para_asignar) ──
async function aprobarDireccion(idPedido) {
    const p = PEDIDOS.find(x => x.id_pedido === idPedido);
    if (!p) return;
    if (!confirm(`¿Confirmas que el pin de "${p.direccion}${p.barrio ? ', ' + p.barrio : ''}" es correcto?\nEl pedido de ${p.mascota} pasará a la cola de asignación.`)) return;
    try {
        const r = await fetch(API + 'validar_direccion_pedido.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_pedido: idPedido, accion: 'aprobar' }),
        });
        const data = await r.json();
        if (!data.success) { showToast(data.message || 'No se pudo aprobar', 'error'); return; }
        showToast('✅ ' + data.message, 'success');
        cargarPedidos();
    } catch (e) {
        showToast('Error de conexión al aprobar la dirección', 'error');
    }
}

// ── Cancelar servicio (pedido + membresía + cronograma) ────────
async function cancelarPedido(idPedido) {
    const p = PEDIDOS.find(x => x.id_pedido === idPedido);
    if (!p) return;
    const motivo = prompt(
        `Vas a cancelar el servicio de paseos de ${p.mascota} (${p.cliente}).\n` +
        `Se desactivará su membresía, saldrá del cronograma y se avisará al cliente.\n\n` +
        `Motivo de la cancelación:`
    );
    if (motivo === null) return; // canceló el diálogo
    if (!motivo.trim()) { showToast('Debes indicar un motivo', 'error'); return; }

    try {
        const r = await fetch(API + 'cancelar_pedido_paseos.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_pedido: idPedido, motivo: motivo.trim() }),
        });
        const data = await r.json();
        if (!data.success) { showToast(data.message || 'No se pudo cancelar', 'error'); return; }
        showToast('✅ ' + data.message, 'success');
        cargarPedidos();
    } catch (e) {
        showToast('Error de conexión al cancelar', 'error');
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

// ── Modal reasignar a otro paseador ────────────────────────────
function abrirModalReasignar(idPedido) {
    const p = PEDIDOS.find(x => x.id_pedido === idPedido);
    if (!p || !p.asignacion) return;
    document.getElementById('reassignPedidoId').value = idPedido;
    document.getElementById('reassignModalTitle').textContent = `Reasignar a ${p.mascota} (${p.cliente})`;
    document.getElementById('reassignModalSub').textContent =
        `Paseador actual: ${p.asignacion.paseador} — ${diasTexto(p.asignacion.dias)}`;

    // Select de paseadores (excluye al actual)
    const sel = document.getElementById('reassignPaseador');
    sel.innerHTML = '<option value="">— Seleccionar paseador —</option>' +
        PASEADORES.filter(x => x.id !== p.asignacion.id_paseador)
                  .map(x => `<option value="${x.id}">${x.nombre}</option>`).join('');

    document.getElementById('reassignModal').classList.add('open');
}

async function confirmarReasignacion() {
    const idPedido  = parseInt(document.getElementById('reassignPedidoId').value);
    const idDestino = parseInt(document.getElementById('reassignPaseador').value || '0');
    const alcance   = document.querySelector('input[name="reassignAlcance"]:checked').value;

    if (!idDestino) { showToast('Selecciona el nuevo paseador', 'error'); return; }

    const btn = document.getElementById('confirmReassign');
    btn.disabled = true;
    const original = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Reasignando...';

    try {
        const r = await fetch(API + 'reasignar_paseo.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_pedido: idPedido, id_paseador_destino: idDestino, alcance }),
        });
        const data = await r.json();
        if (!data.success) { showToast(data.message || 'No se pudo reasignar', 'error'); return; }
        document.getElementById('reassignModal').classList.remove('open');
        showToast('✅ ' + data.message, 'success');
        cargarPedidos();
        cargarTorre();
    } catch (e) {
        showToast('Error de conexión al reasignar', 'error');
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
document.getElementById('btnRefresh').addEventListener('click', () => { cargarPedidos(); showToast('Actualizado', 'info'); });

document.getElementById('closeAssignModal').addEventListener('click', () => document.getElementById('assignModal').classList.remove('open'));
document.getElementById('cancelAssign').addEventListener('click', () => document.getElementById('assignModal').classList.remove('open'));
document.getElementById('assignModal').addEventListener('click', e => {
    if (e.target === document.getElementById('assignModal')) document.getElementById('assignModal').classList.remove('open');
});
document.getElementById('confirmAssign').addEventListener('click', confirmarAsignacion);

document.getElementById('closeReassignModal').addEventListener('click', () => document.getElementById('reassignModal').classList.remove('open'));
document.getElementById('cancelReassign').addEventListener('click', () => document.getElementById('reassignModal').classList.remove('open'));
document.getElementById('reassignModal').addEventListener('click', e => {
    if (e.target === document.getElementById('reassignModal')) document.getElementById('reassignModal').classList.remove('open');
});
document.getElementById('confirmReassign').addEventListener('click', confirmarReasignacion);

// ── Eliminación múltiple ───────────────────────────────────────
document.getElementById('bulkClear').addEventListener('click', limpiarSeleccion);
document.getElementById('bulkDelete').addEventListener('click', abrirModalEliminar);
document.getElementById('closeDelete').addEventListener('click', cerrarModalEliminar);
document.getElementById('cancelDelete').addEventListener('click', cerrarModalEliminar);
document.getElementById('deleteModal').addEventListener('click', e => {
    if (e.target === document.getElementById('deleteModal')) cerrarModalEliminar();
});
document.getElementById('confirmDelete').addEventListener('click', confirmarEliminar);

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
cargarTorre();
// La torre se refresca cada minuto y el listado de pedidos cada dos:
// el admin ve llegar pedidos nuevos y el avance del día sin recargar.
setInterval(cargarTorre, 60000);
setInterval(cargarPedidos, 120000);
