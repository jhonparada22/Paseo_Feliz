const API = '../../../controller/paseos_controller.php';

let paseos = [];
let paseadores = [];
let selectedId = null;
let activeFilter = 'todos';

const cardsGrid = document.getElementById('cardsGrid');
const emptyState = document.getElementById('emptyState');
const alertasStrip = document.getElementById('alertasStrip');
const dpEmpty = document.getElementById('dpEmpty');
const dpContent = document.getElementById('dpContent');
const toast = document.getElementById('toast');

const statHoy = document.getElementById('st-hoy');
const statCurso = document.getElementById('st-curso');
const statPend = document.getElementById('st-pend');
const statSin = document.getElementById('st-sin');
const statCancel = document.getElementById('st-cancel');

const bgColors = ['#dbeafe', '#ede9fe', '#ffedd5', '#fce7f3', '#dcfce7', '#fef9c3'];

// ── Menú hamburguesa ──────────────────────────────────────────
document.getElementById('btn-menu')?.addEventListener('click', () => {
    document.getElementById('menu-latente').classList.toggle('show');
});
window.addEventListener('click', e => {
    const btn = document.getElementById('btn-menu');
    const menu = document.getElementById('menu-latente');
    if (btn && menu && !btn.contains(e.target) && !menu.contains(e.target)) {
        menu.classList.remove('show');
    }
});

function showToast(msg, type = 'success') {
    toast.className = `toast ${type} show`;
    toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'info' ? 'info-circle' : 'exclamation-circle'}"></i> ${msg}`;
    setTimeout(() => toast.classList.remove('show'), 3200);
}

async function apiGet(params = {}) {
    const qs = new URLSearchParams(params).toString();
    const res = await fetch(`${API}?${qs}`);
    return res.json();
}

async function apiPost(action, body) {
    const res = await fetch(`${API}?action=${action}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    });
    return res.json();
}

async function loadStats() {
    const data = await apiGet({ action: 'stats' });
    if (!data.success) return;
    const s = data.stats;
    statHoy.textContent = s.hoy;
    statCurso.textContent = s.en_curso;
    statPend.textContent = s.programados;
    statSin.textContent = s.sin_paseador;
    statCancel.textContent = s.cancelados;
}

async function loadPaseos() {
    const params = { action: 'list' };
    const q = document.getElementById('searchInput').value.trim();
    const zona = document.getElementById('filterZona').value;
    const fecha = document.getElementById('filterFecha').value;

    if (q) params.q = q;
    if (zona) params.zona = zona;
    if (fecha) params.fecha = fecha;

    if (activeFilter === 'sin_asignar') params.sin_paseador = '1';
    else if (activeFilter !== 'todos') params.estado = activeFilter;

    const data = await apiGet(params);
    if (!data.success) {
        showToast(data.message || 'Error al cargar paseos', 'warning');
        return;
    }
    paseos = data.paseos;
    renderCards();
    renderAlertas();
    if (selectedId && !paseos.find(p => p.id_paseo === selectedId)) {
        selectedId = null;
        showDetailEmpty();
    } else if (selectedId) {
        showDetail(paseos.find(p => p.id_paseo === selectedId));
    }
}

async function loadPaseadores(fecha) {
    const data = await apiGet({ action: 'paseadores', fecha: fecha || new Date().toISOString().slice(0, 10) });
    if (data.success) paseadores = data.paseadores;
}

function renderAlertas() {
    const sinAsignar = paseos.filter(p => p.sin_paseador && p.estado === 'programado');
    const enCurso = paseos.filter(p => p.estado === 'en_curso');
    if (sinAsignar.length === 0 && enCurso.length === 0) {
        alertasStrip.style.display = 'none';
        return;
    }
    alertasStrip.style.display = 'flex';
    let html = `<i class="fas fa-triangle-exclamation al-icon"></i><div style="flex:1">`;
    if (sinAsignar.length) {
        html += `<div class="al-title">${sinAsignar.length} paseo(s) sin paseador asignado</div>
            <div class="al-chips">${sinAsignar.map(p =>
            `<span class="al-chip" data-id="${p.id_paseo}">${p.mascota.nombre} · ${p.hora_inicio}</span>`
        ).join('')}</div>`;
    }
    if (enCurso.length) {
        html += `<div class="al-title" style="margin-top:6px;color:#1d4ed8">${enCurso.length} paseo(s) en curso ahora</div>`;
    }
    html += `</div>`;
    if (sinAsignar.length) {
        html += `<button class="btn-primary" style="padding:8px 14px;font-size:.78rem" id="btnVerSinAsignar">
            <i class="fas fa-user-plus"></i> Asignar</button>`;
    }
    alertasStrip.innerHTML = html;

    alertasStrip.querySelectorAll('.al-chip').forEach(chip => {
        chip.addEventListener('click', () => selectPaseo(+chip.dataset.id));
    });
    document.getElementById('btnVerSinAsignar')?.addEventListener('click', () => {
        setFilter('sin_asignar');
    });
}

function cardBg(i) { return bgColors[i % bgColors.length]; }

function renderCards() {
    if (!paseos.length) {
        cardsGrid.innerHTML = '';
        emptyState.classList.add('visible');
        return;
    }
    emptyState.classList.remove('visible');
    cardsGrid.innerHTML = paseos.map((p, i) => {
        const dotColor = p.estado === 'en_curso' ? '#3E72A6'
            : p.estado === 'programado' ? (p.sin_paseador ? '#f97316' : '#eab308')
                : p.estado === 'completado' ? '#22c55e' : '#ef4444';
        return `
        <div class="paseo-card ${selectedId === p.id_paseo ? 'selected' : ''} ${p.sin_paseador ? 'sin-paseador' : ''}"
             data-id="${p.id_paseo}" style="--card-bg:${cardBg(i)}">
            <div class="pc-header">
                <span class="pc-emoji">${p.mascota.avatar || '🐾'}</span>
                <span class="pc-dot" style="background:${dotColor}"></span>
                ${p.estado === 'en_curso' ? '<span class="pc-live"><span class="pulse-dot"></span></span>' : ''}
            </div>
            <div class="pc-body">
                <div class="pc-name">${p.mascota.nombre}</div>
                <div class="pc-sub">${p.codigo} · ${p.duracion_label}</div>
                <div class="pc-tags">
                    <span class="pc-tag ${p.estado_cls}">${p.estado_label}</span>
                    <span class="pc-tag modalidad">${p.modalidad === 'grupal' ? 'Grupal' : 'Individual'}</span>
                </div>
                <div class="pc-meta"><i class="fas fa-clock"></i> ${p.hora_inicio} · ${formatFecha(p.fecha)}</div>
                <div class="pc-meta"><i class="fas fa-location-dot"></i> ${p.zona || '—'}</div>
                <div class="pc-owner">
                    <i class="fas fa-user"></i>
                    ${p.paseador ? p.paseador.nombre : '<span style="color:#ea580c;font-weight:700">Sin paseador</span>'}
                </div>
            </div>
        </div>`;
    }).join('');

    cardsGrid.querySelectorAll('.paseo-card').forEach(card => {
        card.addEventListener('click', () => selectPaseo(+card.dataset.id));
    });
}

function formatFecha(f) {
    const d = new Date(f + 'T12:00:00');
    const hoy = new Date();
    if (d.toDateString() === hoy.toDateString()) return 'Hoy';
    return d.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' });
}

function formatPrecio(n) {
    if (!n) return '—';
    return '$' + n.toLocaleString('es-CO');
}

function selectPaseo(id) {
    selectedId = id;
    renderCards();
    const p = paseos.find(x => x.id_paseo === id);
    if (p) showDetail(p);
}

function showDetailEmpty() {
    dpEmpty.style.display = 'flex';
    dpContent.style.display = 'none';
}

function showDetail(p) {
    dpEmpty.style.display = 'none';
    dpContent.style.display = 'block';
    const canAssign = p.estado !== 'cancelado' && p.estado !== 'completado';
    const canCancel = p.estado !== 'completado' && p.estado !== 'cancelado';

    dpContent.innerHTML = `
        <div class="dp-head">
            <div class="dp-emoji">${p.mascota.avatar || '🐾'}</div>
            <div>
                <div class="dp-title">${p.mascota.nombre}</div>
                <div class="dp-code">${p.codigo}</div>
            </div>
            <span class="badge-st ${p.estado_cls}">${p.estado_label}</span>
        </div>
        <div class="dp-section">
            <div class="dp-label">Información del paseo</div>
            <div class="dp-row"><i class="fas fa-calendar"></i><span>${formatFecha(p.fecha)} · ${p.hora_inicio}</span></div>
            <div class="dp-row"><i class="fas fa-hourglass-half"></i><span>${p.duracion_label} · ${p.modalidad === 'grupal' ? 'Grupal' : 'Individual'}</span></div>
            <div class="dp-row"><i class="fas fa-location-dot"></i><span>${p.direccion_recogida || p.zona || '—'}</span></div>
            <div class="dp-row"><i class="fas fa-dollar-sign"></i><span>${formatPrecio(p.precio)}</span></div>
            ${p.notas ? `<div class="dp-note"><i class="fas fa-comment-medical"></i> ${p.notas}</div>` : ''}
            ${p.motivo_cancelacion ? `<div class="dp-note cancel"><i class="fas fa-ban"></i> ${p.motivo_cancelacion}</div>` : ''}
        </div>
        <div class="dp-section">
            <div class="dp-label">Cliente</div>
            <div class="dp-row"><i class="fas fa-user"></i><span>${p.cliente.nombre}</span></div>
            <div class="dp-row"><i class="fas fa-envelope"></i><span>${p.cliente.email}</span></div>
        </div>
        <div class="dp-section">
            <div class="dp-label">Paseador</div>
            ${p.paseador
            ? `<div class="dp-row"><i class="fas fa-person-walking"></i><span>${p.paseador.nombre}</span></div>
                   <div class="dp-row"><i class="fas fa-envelope"></i><span>${p.paseador.email}</span></div>`
            : `<div class="dp-sin-paseador"><i class="fas fa-user-slash"></i> Sin paseador asignado</div>`}
        </div>
        <div class="dp-actions">
            ${canAssign ? `<button class="btn-primary" id="btnAsignar"><i class="fas fa-user-plus"></i> ${p.paseador ? 'Reasignar' : 'Asignar'} paseador</button>` : ''}
            ${p.estado === 'programado' && p.paseador ? `<button class="btn-outline" id="btnIniciar"><i class="fas fa-play"></i> Marcar en curso</button>` : ''}
            ${p.estado === 'en_curso' ? `<button class="btn-outline" id="btnCompletar"><i class="fas fa-check"></i> Completar</button>` : ''}
            ${canCancel ? `<button class="btn-danger" id="btnCancelar"><i class="fas fa-ban"></i> Cancelar</button>` : ''}
        </div>`;

    document.getElementById('btnAsignar')?.addEventListener('click', () => openAssignModal(p));
    document.getElementById('btnIniciar')?.addEventListener('click', () => changeStatus(p.id_paseo, 'en_curso'));
    document.getElementById('btnCompletar')?.addEventListener('click', () => changeStatus(p.id_paseo, 'completado'));
    document.getElementById('btnCancelar')?.addEventListener('click', () => openCancelModal(p));
}

async function changeStatus(id, estado) {
    const data = await apiPost('change_status', { id_paseo: id, estado });
    if (data.success) {
        showToast(data.message);
        await refresh();
    } else showToast(data.message, 'warning');
}

function openAssignModal(p) {
    const modal = document.getElementById('assignModal');
    document.getElementById('assignModalTitle').textContent = `Asignar paseador — ${p.mascota.nombre}`;
    document.getElementById('assignModalSub').textContent = `${p.codigo} · ${formatFecha(p.fecha)} ${p.hora_inicio} · ${p.zona}`;
    document.getElementById('assignPaseoId').value = p.id_paseo;
    renderPaseadoresList(p);
    modal.classList.add('open');
}

function renderPaseadoresList(p) {
    const list = document.getElementById('paseadoresList');
    if (!paseadores.length) {
        list.innerHTML = '<div class="no-pets"><i class="fas fa-person-walking"></i><p>No hay paseadores registrados con rol paseador.</p></div>';
        return;
    }
    list.innerHTML = paseadores.map(w => `
        <label class="paseador-opt">
            <input type="radio" name="paseador" value="${w.id}" ${p.paseador?.id === w.id ? 'checked' : ''}/>
            <div class="po-info">
                <div class="po-name">${w.nombre}</div>
                <div class="po-sub">${w.email} · ${w.paseos_dia} paseo(s) hoy</div>
            </div>
            <span class="po-badge">${w.paseos_dia < 4 ? 'Disponible' : 'Ocupado'}</span>
        </label>
    `).join('');
}

async function confirmAssign() {
    const idPaseo = +document.getElementById('assignPaseoId').value;
    const selected = document.querySelector('input[name="paseador"]:checked');
    if (!selected) { showToast('Selecciona un paseador', 'warning'); return; }
    const data = await apiPost('assign', { id_paseo: idPaseo, id_paseador: +selected.value });
    if (data.success) {
        showToast(data.message);
        document.getElementById('assignModal').classList.remove('open');
        await refresh();
    } else showToast(data.message, 'warning');
}

function openCancelModal(p) {
    document.getElementById('cancelPaseoId').value = p.id_paseo;
    document.getElementById('cancelMotivo').value = '';
    document.getElementById('cancelModal').classList.add('open');
}

async function confirmCancel() {
    const idPaseo = +document.getElementById('cancelPaseoId').value;
    const motivo = document.getElementById('cancelMotivo').value.trim() || 'Cancelado por administrador';
    const data = await apiPost('cancel', { id_paseo: idPaseo, motivo });
    if (data.success) {
        showToast(data.message);
        document.getElementById('cancelModal').classList.remove('open');
        await refresh();
    } else showToast(data.message, 'warning');
}

function setFilter(f) {
    activeFilter = f;
    document.querySelectorAll('.sf-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.filter === f);
    });
    loadPaseos();
}

async function refresh() {
    await Promise.all([loadStats(), loadPaseadores(), loadPaseos()]);
}

// Eventos
document.getElementById('searchInput').addEventListener('input', debounce(loadPaseos, 300));
document.getElementById('filterZona').addEventListener('change', loadPaseos);
document.getElementById('filterFecha').addEventListener('change', loadPaseos);
document.querySelectorAll('.sf-btn').forEach(btn => {
    btn.addEventListener('click', () => setFilter(btn.dataset.filter));
});
document.getElementById('btnRefresh')?.addEventListener('click', refresh);
document.getElementById('confirmAssign')?.addEventListener('click', confirmAssign);
document.getElementById('confirmCancel')?.addEventListener('click', confirmCancel);
document.getElementById('closeAssignModal')?.addEventListener('click', () => document.getElementById('assignModal').classList.remove('open'));
document.getElementById('closeCancelModal')?.addEventListener('click', () => document.getElementById('cancelModal').classList.remove('open'));
document.getElementById('cancelAssign')?.addEventListener('click', () => document.getElementById('assignModal').classList.remove('open'));
document.getElementById('cancelCancelBtn')?.addEventListener('click', () => document.getElementById('cancelModal').classList.remove('open'));

function debounce(fn, ms) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

document.getElementById('filterFecha').value = new Date().toISOString().slice(0, 10);
refresh();
