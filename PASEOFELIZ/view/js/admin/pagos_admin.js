// ══════════════════════════════════════════════════════════════
// ESTADO
// ══════════════════════════════════════════════════════════════
let PAGOS    = [];
let USUARIOS = [];
let filteredUsuarios = [];
let USUARIOS_POR_ID = {}; // id_usuario -> objeto usuario (con .mascotas)
let currentPage  = 1;
const PER_PAGE   = 8;
let filtroActivo = 'todos';

// Precios fijos (adiestramiento/hospedaje). Paseos ahora es $18.000 × cantidad de días.
// Se sobreescriben con lo real al cargar (obtener_precios.php); estos son
// solo valores de respaldo mientras carga o si el fetch falla.
const PRECIOS_MEM = {
  adiestramiento: 22000,
  hospedaje:      28000,
};
let PRECIO_PASEO_DIA = 18000;
let DESCUENTOS_SERVICIOS = { paseos: [], adiestramiento: [], hospedaje: [] };

function calcularConDescuento(precioUnidad, cantidad, tipo) {
  const subtotal = precioUnidad * cantidad;
  let pct = 0;
  (DESCUENTOS_SERVICIOS[tipo] || []).forEach(d => {
    if (cantidad >= d.cantidad_minima && d.descuento_pct > pct) pct = d.descuento_pct;
  });
  const descuento = Math.round(subtotal * pct) / 100;
  return { subtotal, descuento_pct: pct, descuento, total: subtotal - descuento };
}

const PATHS = {
  obtenerPagos:   '../../../model/obtener_pagos.php',
  obtenerPrecios: '../../../model/obtener_precios.php',
  guardarPrecios: '../../../controller/guardar_precios.php',
  registrarPago:  '../../../controller/registrar_pago.php',
};

// Mapa: centro por defecto (Cúcuta) mientras el admin marca el punto real
const MAPA_CENTRO_DEFECTO = [7.8939, -72.5078];

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

    await cargarPrecios();

    PAGOS    = data.pagos_recientes;
    USUARIOS = data.usuarios;
    filteredUsuarios = [...USUARIOS];
    USUARIOS_POR_ID = {};
    USUARIOS.forEach(u => { USUARIOS_POR_ID[u.id] = u; });

    renderStats(data.stats);
    renderPagos();
    poblarSelectUsuarios();
    applyFilter(filtroActivo);
  } catch (err) {
    showToast('Error de conexión', 'error');
    console.error(err);
  }
}

async function cargarPrecios() {
  try {
    const r = await fetch(PATHS.obtenerPrecios);
    const d = await r.json();
    if (!d.success) return;
    if (d.precios.paseos) PRECIO_PASEO_DIA = d.precios.paseos.precio_unidad;
    if (d.precios.adiestramiento) PRECIOS_MEM.adiestramiento = d.precios.adiestramiento.precio_unidad;
    if (d.precios.hospedaje) PRECIOS_MEM.hospedaje = d.precios.hospedaje.precio_unidad;
    DESCUENTOS_SERVICIOS = d.descuentos || DESCUENTOS_SERVICIOS;
    actualizarTextosDePrecio();
  } catch (e) { /* se queda con los valores de respaldo */ }
}

// Refresca todos los textos fijos que muestran precio (el hint de "Paseos
// al mes" en el modal, y las 3 tarjetas de tipo de membresía), para que
// nunca se queden mostrando un precio viejo tras cambiarlo en "Precios".
function actualizarTextosDePrecio() {
  const hint = document.getElementById('mpPrecioUnidadHint');
  if (hint) hint.textContent = `(${formatMonto(PRECIO_PASEO_DIA)} c/u)`;

  document.querySelectorAll('#mpTipo .mem-opcion').forEach(op => {
    const val = op.querySelector('input')?.value;
    const small = op.querySelector('small');
    if (!val || !small) return;
    if (val === 'adiestramiento') small.textContent = '$' + formatMonto(PRECIOS_MEM.adiestramiento);
    if (val === 'hospedaje')      small.textContent = '$' + formatMonto(PRECIOS_MEM.hospedaje);
  });
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
      <div class="pago-email">${esc(p.email)}${p.nombre_mascota ? ' · 🐾 ' + esc(p.nombre_mascota) : ' · <em>sin mascota asignada</em>'}</div>
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

  // Antes esto mostraba un resumen SIN decir de cuál mascota — ahora se
  // arma una fila por cada mascota que sí tiene algo activo, con su
  // nombre, para que quede claro a quién pertenece cada membresía.
  const mascotasConServicio = (u.mascotas || []).filter(m => m.servicios && Object.keys(m.servicios).length > 0);

  let svHtml = '';
  if (mascotasConServicio.length > 0) {
    svHtml = '<div class="umem-servicios-wrap">';
    mascotasConServicio.forEach(m => {
      svHtml += `<div class="sv-mascota-nombre"><i class="fas fa-paw"></i> ${esc(m.nombre_mascota)}</div>`;
      Object.entries(m.servicios).forEach(([sv, dias]) => {
        const clsDias = (typeof dias === 'number' && dias <= 5) ? 'dias-proximo' : '';
        svHtml += `
          <div class="sv-item">
            <span class="sv-tag ${clsMap[sv] || ''}">${sv}</span>
            <span class="sv-dias ${clsDias}">${dias}d</span>
          </div>`;
      });
    });
    svHtml += '</div>';
  } else {
    svHtml = `
      <div class="umem-servicios-wrap">
        <div class="sv-item">
          <span class="sv-tag sv-ninguno">Sin membresía</span>
          <span class="sv-dias dias-vencido">—</span>
        </div>
      </div>`;
  }

  const statusHtml = u.activa
    ? `<span class="mem-status status-activa"><i class="fas fa-circle" style="font-size:.5rem"></i> Activa</span>`
    : `<span class="mem-status status-inactiva"><i class="fas fa-circle" style="font-size:.5rem"></i> Inactiva</span>`;

  const sinMascotas = !u.tiene_mascotas;
  const avisoMascota = sinMascotas
    ? `<div class="umem-mascota-tag" title="Debe registrar una mascota antes de poder comprar una membresía"><i class="fas fa-triangle-exclamation"></i> Sin mascotas</div>`
    : (u.tiene_membresia_sin_mascota
        ? `<div class="umem-mascota-tag" title="Tiene un pago histórico sin mascota asignada, revisar"><i class="fas fa-circle-info"></i> Revisar asignación</div>`
        : '');

  div.innerHTML = `
    <div class="umem-avatar" style="background:${color}">
      ${src
        ? `<img src="${src}" alt="${esc(u.nombre)}" onerror="this.style.display='none'">`
        : initials}
    </div>
    <div class="umem-info">
      <div class="umem-nombre">${esc(u.nombre)}</div>
      <div class="umem-email">${esc(u.email)}</div>
      ${avisoMascota}
    </div>
    <div class="umem-servicios">${svHtml}</div>
    ${statusHtml}
  `;

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
  actualizarMascotasModal(idUsuario);
  mostrarOcultarDetallePaseos(null);
  resetFormularioPaseos();
  document.getElementById('modalPago').classList.add('open');
}

// Pobla el <select> de mascotas según el usuario elegido en el modal.
// Si el usuario no tiene ninguna mascota, muestra el aviso y bloquea
// el envío (no se puede registrar un pago sin mascota).
const LABEL_TIPO_MEM = { paseos: 'Paseos', adiestramiento: 'Adiestramiento', hospedaje: 'Hospedaje' };

function actualizarMascotasModal(idUsuario) {
  const grupo   = document.getElementById('mpMascotaGroup');
  const aviso   = document.getElementById('mpAvisoSinMascota');
  const select  = document.getElementById('mpMascota');
  const btnConf = document.getElementById('btnConfirmarPago');

  const valorPrevio = select.value;
  select.innerHTML = '<option value="">Seleccionar mascota...</option>';

  const usuario  = idUsuario ? USUARIOS_POR_ID[idUsuario] : null;
  const mascotas = usuario?.mascotas ?? [];
  const tipo     = document.querySelector('input[name="tipo_mem"]:checked')?.value ?? null;
  const label    = tipo ? LABEL_TIPO_MEM[tipo] : null;

  if (!idUsuario) {
    grupo.style.display  = 'none';
    aviso.style.display  = 'none';
    btnConf.disabled     = false;
    return;
  }

  if (mascotas.length === 0) {
    grupo.style.display  = 'none';
    aviso.style.display  = 'flex';
    btnConf.disabled     = true; // no se puede comprar sin mascota
    return;
  }

  aviso.style.display = 'none';
  grupo.style.display = 'block';
  btnConf.disabled    = false;

  mascotas.forEach(m => {
    // Ya tiene ESTE servicio específico activo (no basta con "activa" en
    // general — puede tener Paseos y no Adiestramiento, por ejemplo).
    const yaTieneEsteServicio = !!(label && m.servicios && m.servicios[label] !== undefined);

    const opt = document.createElement('option');
    opt.value = m.id_mascota;
    opt.textContent = m.nombre_mascota + (yaTieneEsteServicio ? ` (ya tiene ${label} activo)` : '');
    opt.disabled = yaTieneEsteServicio;
    select.appendChild(opt);
  });

  // Restaurar selección previa si sigue siendo válida; si no, elegir la
  // primera mascota que SÍ se pueda seleccionar.
  const opcionesValidas = Array.from(select.options).filter(o => o.value && !o.disabled);
  if (valorPrevio && !select.querySelector(`option[value="${valorPrevio}"]`)?.disabled) {
    select.value = valorPrevio;
  } else if (opcionesValidas.length === 1) {
    select.value = opcionesValidas[0].value;
  }
}

document.getElementById('mpUsuario').addEventListener('change', (e) => {
  actualizarMascotasModal(e.target.value ? parseInt(e.target.value, 10) : null);
});

function actualizarPrecioModal(tipo) {
  const precioEl = document.getElementById('mpPrecioMostrado');

  if (!tipo) {
    precioEl.textContent = 'Selecciona una membresía';
    precioEl.className   = 'mp-precio-hint';
    return;
  }

  if (tipo === 'paseos') {
    const cantidad = parseInt(document.getElementById('mpCantidadPaseos').value, 10) || 0;
    if (cantidad < 1) {
      precioEl.textContent = 'Ingresa cuántos paseos al mes';
      precioEl.className   = 'mp-precio-hint';
      return;
    }
    const c = calcularConDescuento(PRECIO_PASEO_DIA, cantidad, 'paseos');
    precioEl.textContent = '$' + formatMonto(c.total) + ' COP' + (c.descuento_pct ? ` (${c.descuento_pct}% dcto)` : '');
    precioEl.className   = 'mp-precio-hint activo';
    return;
  }

  if (!PRECIOS_MEM[tipo]) {
    precioEl.textContent = 'Selecciona una membresía';
    precioEl.className   = 'mp-precio-hint';
  } else {
    precioEl.textContent = '$' + formatMonto(PRECIOS_MEM[tipo]) + ' COP';
    precioEl.className   = 'mp-precio-hint activo';
  }
}

let mapaAdmin = null, marcadorAdmin = null;
let coordsAdmin = { lat: null, lng: null };

function initMapaAdminSiNecesario() {
  if (mapaAdmin || typeof L === 'undefined') return;
  mapaAdmin = L.map('mpMapa').setView(MAPA_CENTRO_DEFECTO, 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap',
  }).addTo(mapaAdmin);
  mapaAdmin.on('click', (e) => {
    coordsAdmin.lat = e.latlng.lat;
    coordsAdmin.lng = e.latlng.lng;
    if (marcadorAdmin) marcadorAdmin.setLatLng(e.latlng);
    else marcadorAdmin = L.marker(e.latlng).addTo(mapaAdmin);
    document.getElementById('mpCoordsTexto').textContent =
      `Lat ${e.latlng.lat.toFixed(5)}, Lng ${e.latlng.lng.toFixed(5)}`;
  });
}

function resetMapaAdmin() {
  coordsAdmin = { lat: null, lng: null };
  if (marcadorAdmin && mapaAdmin) { mapaAdmin.removeLayer(marcadorAdmin); marcadorAdmin = null; }
  if (mapaAdmin) mapaAdmin.setView(MAPA_CENTRO_DEFECTO, 13);
  const txt = document.getElementById('mpCoordsTexto');
  if (txt) txt.textContent = 'Sin ubicación marcada';
}

function resetFormularioPaseos() {
  document.getElementById('mpCantidadPaseos').value = 8;
  document.querySelectorAll('#mpDias input[type=checkbox]').forEach(c => {
    c.checked = ['lun', 'mie', 'vie'].includes(c.value);
  });
  document.getElementById('mpModalidad').value    = 'grupal';
  document.getElementById('mpDuracion').value     = '60';
  document.getElementById('mpFranja').selectedIndex = 1;
  document.getElementById('mpFechaInicio').value  = new Date().toISOString().split('T')[0];
  document.getElementById('mpDireccion').value    = '';
  document.getElementById('mpBarrio').value       = '';
  document.getElementById('mpReferencia').value   = '';
  document.getElementById('mpInstrucciones').value = '';
  resetMapaAdmin();
}

function mostrarOcultarDetallePaseos(tipo) {
  const bloque = document.getElementById('mpPaseosDetalle');
  if (tipo === 'paseos') {
    bloque.style.display = 'block';
    // El mapa necesita el contenedor ya visible para medir bien su tamaño
    setTimeout(() => {
      initMapaAdminSiNecesario();
      if (mapaAdmin) mapaAdmin.invalidateSize();
    }, 60);
  } else {
    bloque.style.display = 'none';
  }
}

document.querySelectorAll('input[name="tipo_mem"]').forEach(r => {
  r.addEventListener('change', () => {
    mostrarOcultarDetallePaseos(r.value);
    actualizarPrecioModal(r.value);
    actualizarMascotasModal(document.getElementById('mpUsuario').value || null);
  });
});

document.getElementById('mpCantidadPaseos').addEventListener('input', () => actualizarPrecioModal('paseos'));

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
  const id_mascota = document.getElementById('mpMascota').value;
  const tipoRadio  = document.querySelector('input[name="tipo_mem"]:checked');
  const tipo       = tipoRadio?.value ?? '';
  const metodo     = document.getElementById('mpMetodo').value;

  const usuario  = id_usuario ? USUARIOS_POR_ID[id_usuario] : null;
  const mascotas = usuario?.mascotas ?? [];

  if (!id_usuario) { showToast('Selecciona un usuario', 'error'); return; }
  if (mascotas.length === 0) {
    showToast('Este usuario no tiene mascotas registradas. No se puede registrar el pago.', 'error');
    return;
  }
  if (!id_mascota) { showToast('Selecciona la mascota a la que se le activará la membresía', 'error'); return; }

  {
    const mascotaSel = mascotas.find(m => String(m.id_mascota) === String(id_mascota));
    const labelTipo  = LABEL_TIPO_MEM[tipo];
    if (mascotaSel && mascotaSel.servicios && mascotaSel.servicios[labelTipo] !== undefined) {
      showToast(`Esta mascota ya tiene ${labelTipo} activo. Elige otra mascota.`, 'error');
      return;
    }
  }
  if (!tipo)       { showToast('Selecciona una membresía', 'error'); return; }

  let body;

  if (tipo === 'paseos') {
    // Igual que el wizard del cliente: crea un pedido_paseo real, para que
    // luego se pueda asignar cronograma/paseador desde el panel de mapas.
    const cantidadPaseos = parseInt(document.getElementById('mpCantidadPaseos').value, 10) || 0;
    if (cantidadPaseos < 1) { showToast('Ingresa cuántos paseos al mes quiere el cliente', 'error'); return; }

    const dias = Array.from(document.querySelectorAll('#mpDias input:checked')).map(c => c.value);
    if (!dias.length) { showToast('Selecciona al menos un día preferido', 'error'); return; }

    const direccion = document.getElementById('mpDireccion').value.trim();
    if (!direccion) { showToast('La dirección de recogida es obligatoria', 'error'); return; }

    if (coordsAdmin.lat === null || coordsAdmin.lng === null) {
      showToast('Marca la ubicación de recogida en el mapa', 'error');
      return;
    }

    const fechaInicio = document.getElementById('mpFechaInicio').value;
    if (!fechaInicio) { showToast('Selecciona la fecha de inicio', 'error'); return; }

    body = new URLSearchParams({
      id_usuario, id_mascota,
      tipo_membresia:  'paseos',
      metodo_pago:     metodo,
      crear_pedido:    '1',
      cantidad_paseos: cantidadPaseos,
      modalidad:       document.getElementById('mpModalidad').value,
      duracion_min:    document.getElementById('mpDuracion').value,
      dias_preferidos: dias.join(','),
      franja_horaria:  document.getElementById('mpFranja').value,
      fecha_inicio:    fechaInicio,
      direccion,
      barrio:          document.getElementById('mpBarrio').value.trim(),
      referencia:      document.getElementById('mpReferencia').value.trim(),
      instrucciones:   document.getElementById('mpInstrucciones').value.trim(),
      lat: coordsAdmin.lat,
      lng: coordsAdmin.lng,
    });
  } else {
    const monto = PRECIOS_MEM[tipo] ?? 0;
    body = new URLSearchParams({ id_usuario, id_mascota, tipo_membresia: tipo, monto, metodo_pago: metodo });
  }

  const btn = document.getElementById('btnConfirmarPago');
  btn.disabled  = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';

  try {
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

// ══════════════════════════════════════════════════════════════
// MODAL DE PRECIOS Y DESCUENTOS
// ══════════════════════════════════════════════════════════════
function precioActualDe(tipo) {
  return tipo === 'paseos' ? PRECIO_PASEO_DIA : PRECIOS_MEM[tipo];
}

function unidadLabelDe(tipo) {
  return { paseos: 'día', adiestramiento: 'sesión', hospedaje: 'noche' }[tipo] || 'unidad';
}

function renderDescuentosLista(tipo) {
  const cont = document.getElementById('precDescuentosLista');
  const filas = DESCUENTOS_SERVICIOS[tipo] || [];
  cont.innerHTML = '';
  filas.forEach((d, i) => cont.appendChild(crearFilaDescuento(d.cantidad_minima, d.descuento_pct, i)));
  if (!filas.length) {
    cont.innerHTML = '<div style="font-size:.78rem;color:#94a3b8;">Sin descuentos configurados — se cobra siempre el precio de unidad × cantidad.</div>';
  }
}

function crearFilaDescuento(cantidadMinima = '', descuentoPct = '') {
  const fila = document.createElement('div');
  fila.className = 'prec-descuento-fila';
  fila.innerHTML = `
    <span>Desde</span>
    <input type="number" class="prec-cant-min" min="1" value="${cantidadMinima}" placeholder="cantidad">
    <span>→</span>
    <input type="number" class="prec-desc-pct" min="1" max="100" value="${descuentoPct}" placeholder="% dcto">
    <button type="button" class="btn-quitar-desc"><i class="fas fa-trash"></i></button>
  `;
  fila.querySelector('.btn-quitar-desc').addEventListener('click', () => fila.remove());
  return fila;
}

function tipoPrecioSeleccionado() {
  return document.querySelector('input[name="prec_tipo"]:checked')?.value || 'paseos';
}

function cargarFormularioPrecios(tipo) {
  document.getElementById('precPrecioUnidad').value = precioActualDe(tipo);
  document.getElementById('precUnidadLabel').value  = unidadLabelDe(tipo);
  renderDescuentosLista(tipo);
}

function abrirModalPrecios() {
  document.querySelector('input[name="prec_tipo"][value="paseos"]').checked = true;
  cargarFormularioPrecios('paseos');
  document.getElementById('modalPrecios').classList.add('open');
}

document.getElementById('btnPrecios').addEventListener('click', abrirModalPrecios);
document.getElementById('closeModalPrecios').addEventListener('click', () =>
  document.getElementById('modalPrecios').classList.remove('open')
);

document.querySelectorAll('input[name="prec_tipo"]').forEach(r => {
  r.addEventListener('change', () => cargarFormularioPrecios(r.value));
});

document.getElementById('btnAgregarDescuento').addEventListener('click', () => {
  const cont = document.getElementById('precDescuentosLista');
  if (cont.children.length === 1 && cont.children[0].tagName === 'DIV' && !cont.querySelector('input')) {
    cont.innerHTML = ''; // quitar el aviso de "sin descuentos"
  }
  cont.appendChild(crearFilaDescuento());
});

document.getElementById('btnGuardarPrecios').addEventListener('click', async () => {
  const tipo   = tipoPrecioSeleccionado();
  const precio = parseFloat(document.getElementById('precPrecioUnidad').value);

  if (!precio || precio <= 0) { showToast('Ingresa un precio válido', 'error'); return; }

  const descuentos = [];
  document.querySelectorAll('#precDescuentosLista .prec-descuento-fila').forEach(fila => {
    const cantMin = parseInt(fila.querySelector('.prec-cant-min').value, 10);
    const pct     = parseInt(fila.querySelector('.prec-desc-pct').value, 10);
    if (cantMin > 0 && pct > 0) descuentos.push({ cantidad_minima: cantMin, descuento_pct: pct });
  });

  const btn = document.getElementById('btnGuardarPrecios');
  btn.disabled  = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

  try {
    const body = new URLSearchParams({
      tipo_membresia: tipo,
      precio_unidad: precio,
      descuentos: JSON.stringify(descuentos),
    });
    const res  = await fetch(PATHS.guardarPrecios, { method: 'POST', body });
    const data = await res.json();

    if (data.success) {
      showToast('Precio de "' + tipo + '" actualizado ✓', 'success');
      await cargarPrecios();
      // Si el modal de registrar pago tiene Paseos elegido, refrescar su precio
      if (document.querySelector('input[name="tipo_mem"]:checked')?.value === tipo) {
        actualizarPrecioModal(tipo);
      }
    } else {
      showToast('Error: ' + data.message, 'error');
    }
  } catch (err) {
    showToast('Error de conexión', 'error');
    console.error(err);
  } finally {
    btn.disabled  = false;
    btn.innerHTML = '<i class="fas fa-check"></i> Guardar precio de este servicio';
  }
});

init();