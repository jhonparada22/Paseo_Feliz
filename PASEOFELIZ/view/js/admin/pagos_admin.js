// ══════════════════════════════════════════════════════════════
// ESTADO
// ══════════════════════════════════════════════════════════════
let PAGOS    = [];
let USUARIOS = [];
let filteredUsuarios = [];
let currentPage  = 1;
const PER_PAGE   = 8;
let filtroActivo = 'todos';

// Precios fijos
const PRECIOS_MEM = {
  paseos:         18000,
  adiestramiento: 22000,
  hospedaje:      28000,
};

const PATHS = {
  obtenerPagos:  '../../../model/obtener_pagos.php',
  registrarPago: '../../../controller/registrar_pago.php',
};

const COLORS = ['#3E72A6','#16a34a','#7c3aed','#ea580c','#db2777','#0891b2'];

// ── Helper: construye la ruta correcta al avatar ──────────────
// La BD guarda "../assets/uploads/nombre.jpg", solo necesitamos
// el nombre del archivo y lo servimos desde view/assets/uploads/
function avatarSrc(url) {
  if (!url) return null;
  const filename = url.split('/').pop();
  return `../../assets/uploads/${filename}`;
}

// ══════════════════════════════════════════════════════════════
// SIDEBAR
// ══════════════════════════════════════════════════════════════
const btnMenu     = document.getElementById('btn-menu');
const menuLatente = document.getElementById('menu-latente');
btnMenu.addEventListener('click', () => menuLatente.classList.toggle('show'));
window.addEventListener('click', e => {
  if (!btnMenu.contains(e.target) && !menuLatente.contains(e.target))
    menuLatente.classList.remove('show');
});

// ══════════════════════════════════════════════════════════════
// INIT
// ══════════════════════════════════════════════════════════════
async function init() {
  try {
    const res  = await fetch(PATHS.obtenerPagos);
    const data = await res.json();
    if (!data.success) { showToast('Error al cargar datos: ' + data.message, 'error'); return; }

    PAGOS    = data.pagos_recientes;
    USUARIOS = data.usuarios;
    filteredUsuarios = [...USUARIOS];

    renderStats(data.stats);
    renderPagos();
    poblarSelectUsuarios();
    applyFilter(filtroActivo);
  } catch (err) {
    showToast('Error de conexión', 'error');
    console.error(err);
  }
}

// ══════════════════════════════════════════════════════════════
// STATS
// ══════════════════════════════════════════════════════════════
function renderStats(stats) {
  document.getElementById('statMiembros').textContent         = stats.miembros_con_membresia ?? 0;
  document.getElementById('statPaseos').textContent           = stats.paseos_activos ?? 0;
  document.getElementById('statAdiestramiento').textContent   = stats.adiestramiento_activos ?? 0;
  document.getElementById('statHospedaje').textContent        = stats.hospedaje_activos ?? 0;
  document.getElementById('statIngresosTotales').textContent  = '$' + formatMonto(stats.ingresos_totales ?? 0);
}

// ══════════════════════════════════════════════════════════════
// PAGOS RECIENTES
// ══════════════════════════════════════════════════════════════
function renderPagos() {
  const lista = document.getElementById('listaPagos');
  const empty = document.getElementById('emptyPagos');
  const badge = document.getElementById('badgePagos');
  lista.innerHTML   = '';
  badge.textContent = PAGOS.length;

  if (PAGOS.length === 0) { empty.classList.add('visible'); return; }
  empty.classList.remove('visible');
  PAGOS.forEach(p => lista.appendChild(buildPagoRow(p)));
}

function buildPagoRow(p) {
  const div     = document.createElement('div');
  div.className = 'pago-row';

  const initials = p.nombre.split(' ').map(w => w[0]).slice(0,2).join('').toUpperCase();
  const color    = COLORS[p.id_usuario % COLORS.length];
  const src      = avatarSrc(p.avatar_url);
  const labelMapPago = { paseos:'🐶 Paseos', adiestramiento:'🎓 Adiestramiento', hospedaje:'🏠 Hospedaje' };
  const clsMapPago   = { paseos:'tipo-paseos', adiestramiento:'tipo-adiestramiento', hospedaje:'tipo-hospedaje' };

  div.innerHTML = `
    <div class="pago-avatar" style="background:${color}">
      ${src
        ? `<img src="${src}" alt="${esc(p.nombre)}" onerror="this.style.display='none'">`
        : initials}
    </div>
    <div class="pago-info">
      <div class="pago-nombre">${esc(p.nombre)}</div>
      <div class="pago-email">${esc(p.email)}</div>
    </div>
    <span class="pago-tipo ${clsMapPago[p.tipo_membresia]}">${labelMapPago[p.tipo_membresia]}</span>
    <div class="pago-monto">$${formatMonto(p.monto)}</div>
    <div class="pago-fecha">${formatFecha(p.fecha_pago)}</div>
  `;
  return div;
}

// ══════════════════════════════════════════════════════════════
// LISTA USUARIOS + MEMBRESÍAS
// ══════════════════════════════════════════════════════════════
function applyFilter(tipo) {
  filtroActivo = tipo;
  const q = document.getElementById('searchUsuarios').value.trim().toLowerCase();

  filteredUsuarios = USUARIOS.filter(u => {
    const matchQ = !q || u.nombre.toLowerCase().includes(q) || u.email.toLowerCase().includes(q);
    const matchF = tipo === 'todos'  ? true
                 : tipo === 'activa' ? u.activa
                 : !u.activa;
    return matchQ && matchF;
  });

  currentPage = 1;
  renderUsuarios();
}

function renderUsuarios() {
  const lista = document.getElementById('listaUsuariosMem');
  const empty = document.getElementById('emptyUsuarios');
  lista.innerHTML = '';

  const start = (currentPage - 1) * PER_PAGE;
  const paged = filteredUsuarios.slice(start, start + PER_PAGE);
  const total = filteredUsuarios.length;

  if (paged.length === 0) {
    empty.classList.add('visible');
  } else {
    empty.classList.remove('visible');
    paged.forEach(u => lista.appendChild(buildUsuarioRow(u)));
  }

  document.getElementById('pagInfo').textContent =
    total === 0 ? '0 usuarios'
    : `Mostrando ${start + 1}–${Math.min(start + PER_PAGE, total)} de ${total}`;

  renderPaginacion(Math.ceil(total / PER_PAGE));
}

function buildUsuarioRow(u) {
  const div     = document.createElement('div');
  div.className = 'umem-row';

  const initials = u.nombre.split(' ').map(w => w[0]).slice(0,2).join('').toUpperCase();
  const color    = COLORS[u.id % COLORS.length];
  const src      = avatarSrc(u.avatar_url);

  const clsMap = { 'Paseos':'sv-paseos', 'Adiestramiento':'sv-adiestramiento', 'Hospedaje':'sv-hospedaje' };

  // Soporta ambos formatos del backend:
  // - Objeto nuevo: { "Paseos": 28, "Adiestramiento": 15 }
  // - Array viejo:  ["Paseos", "Adiestramiento"]
  let entries = [];
  if (u.servicios && typeof u.servicios === 'object' && !Array.isArray(u.servicios)) {
    entries = Object.entries(u.servicios); // formato nuevo
  } else if (Array.isArray(u.servicios) && u.servicios.length > 0) {
    // formato viejo: array sin días → usamos dias_restantes para todos
    entries = u.servicios.map(sv => [sv, u.dias_restantes ?? '?']);
  }

  let svHtml   = '';
  let diasHtml = '';

  if (entries.length > 0) {
    // Badges con días debajo de cada uno
    svHtml = '<div class="umem-servicios-wrap">';
    entries.forEach(([sv, dias]) => {
      const clsDias = (typeof dias === 'number' && dias <= 5) ? 'dias-proximo' : '';
      svHtml += `
        <div class="sv-item">
          <span class="sv-tag ${clsMap[sv] || ''}">${sv}</span>
          <span class="sv-dias ${clsDias}">${dias}d</span>
        </div>`;
    });
    svHtml += '</div>';
    diasHtml = ''; // ya no se necesita columna separada
  } else {
    svHtml = `
      <div class="umem-servicios-wrap">
        <div class="sv-item">
          <span class="sv-tag sv-ninguno">Sin membresía</span>
          <span class="sv-dias dias-vencido">—</span>
        </div>
      </div>`;
    diasHtml = '';
  }

  const statusHtml = u.activa
    ? `<span class="mem-status status-activa"><i class="fas fa-circle" style="font-size:.5rem"></i> Activa</span>`
    : `<span class="mem-status status-inactiva"><i class="fas fa-circle" style="font-size:.5rem"></i> Inactiva</span>`;

  div.innerHTML = `
    <div class="umem-avatar" style="background:${color}">
      ${src
        ? `<img src="${src}" alt="${esc(u.nombre)}" onerror="this.style.display='none'">`
        : initials}
    </div>
    <div class="umem-info">
      <div class="umem-nombre">${esc(u.nombre)}</div>
      <div class="umem-email">${esc(u.email)}</div>
    </div>
    <div class="umem-servicios">${svHtml}</div>
    ${statusHtml}
    <button class="btn-asignar" data-id="${u.id}">
      <i class="fas fa-plus"></i> Asignar
    </button>
  `;

  div.querySelector('.btn-asignar').addEventListener('click', () => abrirModal(u.id));
  return div;
}

// ══════════════════════════════════════════════════════════════
// PAGINACIÓN
// ══════════════════════════════════════════════════════════════
function renderPaginacion(totalPages) {
  const container = document.querySelector('.pag-btns');
  const prevBtn   = document.getElementById('pagPrev');
  const nextBtn   = document.getElementById('pagNext');

  container.querySelectorAll('[data-page]').forEach(b => b.remove());
  prevBtn.disabled = currentPage === 1;
  nextBtn.disabled = currentPage >= totalPages || totalPages === 0;

  for (let i = 1; i <= totalPages; i++) {
    const btn        = document.createElement('button');
    btn.className    = 'pag-btn' + (i === currentPage ? ' active' : '');
    btn.dataset.page = i;
    btn.textContent  = i;
    btn.addEventListener('click', () => { currentPage = i; renderUsuarios(); });
    container.insertBefore(btn, nextBtn);
  }
}

document.getElementById('pagPrev').addEventListener('click', () => {
  if (currentPage > 1) { currentPage--; renderUsuarios(); }
});
document.getElementById('pagNext').addEventListener('click', () => {
  currentPage++; renderUsuarios();
});

// ══════════════════════════════════════════════════════════════
// FILTROS
// ══════════════════════════════════════════════════════════════
document.querySelectorAll('.mf-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.mf-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    applyFilter(btn.dataset.filter);
  });
});

document.getElementById('searchUsuarios').addEventListener('input', () => applyFilter(filtroActivo));

// ══════════════════════════════════════════════════════════════
// MODAL
// ══════════════════════════════════════════════════════════════
function poblarSelectUsuarios() {
  const sel = document.getElementById('mpUsuario');
  sel.innerHTML = '<option value="">Seleccionar usuario...</option>';
  USUARIOS.forEach(u => {
    const opt       = document.createElement('option');
    opt.value       = u.id;
    opt.textContent = `${u.nombre} (${u.email})`;
    sel.appendChild(opt);
  });
}

function abrirModal(idUsuario = null) {
  document.getElementById('mpUsuario').value = idUsuario ?? '';
  document.querySelectorAll('input[name="tipo_mem"]').forEach(r => r.checked = false);
  actualizarPrecioModal(null);
  document.getElementById('mpMetodo').value = 'manual';
  document.getElementById('modalPago').classList.add('open');
}

function actualizarPrecioModal(tipo) {
  const precioEl = document.getElementById('mpPrecioMostrado');
  if (!tipo || !PRECIOS_MEM[tipo]) {
    precioEl.textContent = 'Selecciona una membresía';
    precioEl.className   = 'mp-precio-hint';
  } else {
    precioEl.textContent = '$' + formatMonto(PRECIOS_MEM[tipo]) + ' COP';
    precioEl.className   = 'mp-precio-hint activo';
  }
}

document.querySelectorAll('input[name="tipo_mem"]').forEach(r => {
  r.addEventListener('change', () => actualizarPrecioModal(r.value));
});

document.getElementById('btnNuevoPago').addEventListener('click',  () => abrirModal());
document.getElementById('closeModalPago').addEventListener('click', cerrarModal);
document.getElementById('modalPago').addEventListener('click', e => {
  if (e.target === document.getElementById('modalPago')) cerrarModal();
});

function cerrarModal() {
  document.getElementById('modalPago').classList.remove('open');
}

document.getElementById('btnConfirmarPago').addEventListener('click', async () => {
  const id_usuario = document.getElementById('mpUsuario').value;
  const tipoRadio  = document.querySelector('input[name="tipo_mem"]:checked');
  const tipo       = tipoRadio?.value ?? '';
  const metodo     = document.getElementById('mpMetodo').value;
  const monto      = PRECIOS_MEM[tipo] ?? 0;

  if (!id_usuario) { showToast('Selecciona un usuario', 'error'); return; }
  if (!tipo)       { showToast('Selecciona una membresía', 'error'); return; }

  const btn = document.getElementById('btnConfirmarPago');
  btn.disabled  = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';

  try {
    const body = new URLSearchParams({ id_usuario, tipo_membresia: tipo, monto, metodo_pago: metodo });
    const res  = await fetch(PATHS.registrarPago, { method: 'POST', body });
    const data = await res.json();

    if (data.success) {
      showToast('Pago registrado y membresía activada ✓', 'success');
      cerrarModal();
      await init();
    } else {
      showToast('Error: ' + data.message, 'error');
    }
  } catch (err) {
    showToast('Error de conexión', 'error');
    console.error(err);
  } finally {
    btn.disabled  = false;
    btn.innerHTML = '<i class="fas fa-check"></i> Confirmar pago';
  }
});

// ══════════════════════════════════════════════════════════════
// HELPERS
// ══════════════════════════════════════════════════════════════
function formatMonto(n) {
  return Number(n).toLocaleString('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}
function formatFecha(str) {
  if (!str) return '—';
  return new Date(str).toLocaleDateString('es-CO', {
    day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit'
  });
}
function esc(str) {
  const d = document.createElement('div');
  d.textContent = str;
  return d.innerHTML;
}
let toastTimer;
function showToast(msg, type = 'success') {
  const t = document.getElementById('toast');
  document.getElementById('toastMsg').textContent = msg;
  t.className = `toast ${type}`;
  t.querySelector('i').className = type === 'success' ? 'fas fa-check-circle' : 'fas fa-circle-xmark';
  t.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => t.classList.remove('show'), 3500);
}

init();