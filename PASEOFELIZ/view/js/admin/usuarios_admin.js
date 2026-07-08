// ══════════════════════════════════════════════════════════════
// ESTADO
// ══════════════════════════════════════════════════════════════
let USUARIOS      = [];
let filteredUsers = [];
let currentPage   = 1;
const PER_PAGE    = 5;

// Rutas base según estructura: view/js/admin/ → raíz
const PATHS = {
  obtenerUsuarios:       '../../../model/obtener_usuarios.php',
  obtenerMascotas:       '../../../model/obtener_mascotas.php',
  obtenerDetalleMascota: '../../../model/obtener_detalle_mascota.php',
  obtenerHistorialMascota: '../../../model/obtener_historial_mascota.php',
  gestionarMascotaAdmin: '../../../controller/gestionar_mascota_admin.php',
  cambiarRol:      '../../../controller/cambiar_rol.php',
  eliminarUsuario: '../../../controller/eliminar_usuario.php',
};

let mascotasDelModal = [];
let idMascotaActiva   = null;
let idUsuarioModal    = null;

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
// CARGAR USUARIOS
// ══════════════════════════════════════════════════════════════
async function cargarUsuarios() {
  try {
    const res  = await fetch(PATHS.obtenerUsuarios);
    const data = await res.json();
    if (!data.success) { showToast('Error al cargar usuarios: ' + data.message, 'error'); return; }
    USUARIOS      = data.usuarios;
    filteredUsers = [...USUARIOS];
    applyFilters();
  } catch (err) {
    showToast('Error de conexión al cargar usuarios', 'error');
    console.error(err);
  }
}

// ══════════════════════════════════════════════════════════════
// FILTROS
// ══════════════════════════════════════════════════════════════
const btnFilter   = document.getElementById('btnFilter');
const filterPanel = document.getElementById('filterPanel');
const btnClose    = document.getElementById('btnCloseFilter');
const btnClear    = document.getElementById('btnClearFilter');
const btnApply    = document.getElementById('btnApplyFilter');
const searchInput = document.getElementById('searchInput');

btnFilter.addEventListener('click', () => {
  filterPanel.classList.toggle('open');
  btnFilter.classList.toggle('active');
});
btnClose.addEventListener('click', () => {
  filterPanel.classList.remove('open');
  btnFilter.classList.remove('active');
});
searchInput.addEventListener('input', () => applyFilters());
btnClear.addEventListener('click', () => {
  document.getElementById('fNombre').value = '';
  document.getElementById('fFecha').value  = '';
  document.getElementById('fRol').value    = '';
  searchInput.value = '';
  applyFilters();
});
btnApply.addEventListener('click', () => {
  filterPanel.classList.remove('open');
  btnFilter.classList.remove('active');
  applyFilters();
});
document.getElementById('sortSelect').addEventListener('change', () => renderList());

function applyFilters() {
  const q    = searchInput.value.trim().toLowerCase();
  const fNom = document.getElementById('fNombre').value.trim().toLowerCase();
  const fFech= document.getElementById('fFecha').value;
  const fRol = document.getElementById('fRol').value.toLowerCase();

  filteredUsers = USUARIOS.filter(u => {
    const matchQ    = !q     || u.nombre.toLowerCase().includes(q) || u.email.toLowerCase().includes(q);
    const matchNom  = !fNom  || u.nombre.toLowerCase().includes(fNom);
    const matchFech = !fFech || u.fechaReg === fFech;
    const matchRol  = !fRol  || u.rol === fRol;
    return matchQ && matchNom && matchFech && matchRol;
  });

  currentPage = 1;
  renderList();
}

// ══════════════════════════════════════════════════════════════
// RENDER
// ══════════════════════════════════════════════════════════════
function renderList() {
  const sort   = document.getElementById('sortSelect').value;
  const sorted = [...filteredUsers].sort((a, b) => {
    if (sort === 'nombre') return a.nombre.localeCompare(b.nombre);
    if (sort === 'fecha')  return new Date(b.fechaReg) - new Date(a.fechaReg);
    if (sort === 'rol')    return a.rol.localeCompare(b.rol);
    return 0;
  });

  const start = (currentPage - 1) * PER_PAGE;
  const paged = sorted.slice(start, start + PER_PAGE);
  const total = sorted.length;
  const end   = Math.min(start + PER_PAGE, total);

  const listEl = document.getElementById('userList');
  const empty  = document.getElementById('emptyState');
  listEl.innerHTML = '';

  if (paged.length === 0) {
    empty.classList.add('visible');
  } else {
    empty.classList.remove('visible');
    paged.forEach(u => listEl.appendChild(buildUserRow(u)));
  }

  document.getElementById('pagInfo').textContent =
    total === 0 ? '0 usuarios' : `Mostrando ${start + 1}–${end} de ${total} usuarios`;
  document.getElementById('userCount').textContent = `${total} usuario${total !== 1 ? 's' : ''}`;

  renderPagination(Math.ceil(total / PER_PAGE));
  updateStats();
}

function buildUserRow(u) {
  const div    = document.createElement('div');
  div.className = 'user-row';

  const colors   = ['#3E72A6','#16a34a','#7c3aed','#ea580c','#db2777','#0891b2'];
  const ci       = u.id % colors.length;
  const initials = u.nombre.split(' ').map(p => p[0]).slice(0, 2).join('');
  const fechaFmt = u.fechaReg
    ? new Date(u.fechaReg + 'T00:00:00').toLocaleDateString('es-ES', {day:'2-digit',month:'2-digit',year:'numeric'})
    : '—';

  div.innerHTML = `
    <div class="user-avatar">
      ${u.avatar_url ? `<img src="../../${u.avatar_url}" class="av-img" alt="${u.nombre}" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">` : ''}
      <div class="av-fallback" style="background:${colors[ci]};${u.avatar_url?'display:none':''}">${initials}</div>
    </div>

    <div class="user-info">
      <div class="user-name">${u.nombre}</div>
      <div class="user-email">${u.email}</div>
      <div class="user-meta">
        ${u.telefono  ? `<div class="um-item"><i class="fas fa-phone"></i> ${u.telefono}</div>` : ''}
        ${u.direccion ? `<div class="um-item"><i class="fas fa-map-marker-alt"></i> ${u.direccion}</div>` : ''}
      </div>
    </div>

    <div class="reg-date">
      <div class="rd-label">Fecha registro</div>
      <div class="rd-val">${fechaFmt}</div>
    </div>

    <button class="btn-pets" data-id="${u.id}">
      <i class="fas fa-dog"></i> Mascotas
      <span class="pet-count">${u.totalMascotas}</span>
    </button>

    <div class="role-wrap">
      <button class="btn-role rol-${u.rol}" data-id="${u.id}">
        <span class="role-text">${capitalize(u.rol)}</span>
        <i class="fas fa-chevron-down role-arrow"></i>
      </button>
      <div class="role-dropdown" id="role-dd-${u.id}">
        <div class="role-option ${u.rol==='cliente'?'selected':''}" data-id="${u.id}" data-rol="cliente">
          <div class="role-dot dot-cliente"></div> Cliente
        </div>
        <div class="role-option ${u.rol==='paseador'?'selected':''}" data-id="${u.id}" data-rol="paseador">
          <div class="role-dot dot-paseador"></div> Paseador
        </div>
        <div class="role-option ${u.rol==='administrador'?'selected':''}" data-id="${u.id}" data-rol="administrador">
          <div class="role-dot dot-administrador"></div> Administrador
        </div>
      </div>
    </div>

    <div class="actions-menu-wrap">
      <button class="btn-more" data-id="${u.id}">
        <i class="fas fa-ellipsis-vertical"></i>
      </button>
      <div class="actions-menu" id="act-menu-${u.id}">
        <div class="action-opt" onclick="openPetsModal(${u.id})">
          <i class="fas fa-dog"></i> Ver mascotas
        </div>
        <div class="action-opt danger" onclick="confirmDelete(${u.id}, '${u.nombre.replace(/'/g,"\\'")}')">
          <i class="fas fa-trash"></i> Eliminar usuario
        </div>
      </div>
    </div>
  `;

  // Mascotas
  div.querySelector('.btn-pets').addEventListener('click', e => {
    openPetsModal(Number(e.currentTarget.dataset.id));
  });

  // Rol dropdown
  const roleBtn = div.querySelector('.btn-role');
  const roleDd  = div.querySelector('.role-dropdown');
  roleBtn.addEventListener('click', e => {
    e.stopPropagation();
    closeAllDropdowns(roleDd);
    roleDd.classList.toggle('open');
    roleBtn.classList.toggle('open');
  });
  div.querySelectorAll('.role-option').forEach(opt => {
    opt.addEventListener('click', e => {
      e.stopPropagation();
      changeRole(Number(opt.dataset.id), opt.dataset.rol);
      roleDd.classList.remove('open');
      roleBtn.classList.remove('open');
    });
  });

  // Más acciones
  const moreBtn = div.querySelector('.btn-more');
  const actMenu = div.querySelector('.actions-menu');
  moreBtn.addEventListener('click', e => {
    e.stopPropagation();
    closeAllDropdowns(actMenu);
    actMenu.classList.toggle('open');
  });

  return div;
}

// ══════════════════════════════════════════════════════════════
// CAMBIAR ROL
// ══════════════════════════════════════════════════════════════
async function changeRole(id, nuevoRol) {
  const user = USUARIOS.find(u => u.id === id);
  if (!user || user.rol === nuevoRol) return;

  const rolAnterior = user.rol;
  user.rol = nuevoRol;
  applyFilters();

  try {
    const body = new URLSearchParams({ id_usuario: id, rol: nuevoRol });
    const res  = await fetch(PATHS.cambiarRol, { method: 'POST', body });
    const data = await res.json();

    if (data.success) {
      showToast(`Rol de ${user.nombre} cambiado a ${capitalize(nuevoRol)}`, 'success');
    } else {
      user.rol = rolAnterior;
      applyFilters();
      showToast('Error: ' + data.message, 'error');
    }
  } catch (err) {
    user.rol = rolAnterior;
    applyFilters();
    showToast('Error de conexión al cambiar rol', 'error');
    console.error(err);
  }
}

// ══════════════════════════════════════════════════════════════
// ELIMINAR USUARIO
// ══════════════════════════════════════════════════════════════
async function confirmDelete(id, nombre) {
  if (!confirm(`¿Estás seguro de eliminar a ${nombre}?\nEsta acción eliminará también sus mascotas y no se puede deshacer.`)) return;

  try {
    const body = new URLSearchParams({ id_usuario: id });
    const res  = await fetch(PATHS.eliminarUsuario, { method: 'POST', body });
    const data = await res.json();

    if (data.success) {
      const idx = USUARIOS.findIndex(u => u.id === id);
      if (idx !== -1) USUARIOS.splice(idx, 1);
      showToast(`${nombre} eliminado correctamente`, 'success');
      applyFilters();
    } else {
      showToast('Error: ' + data.message, 'error');
    }
  } catch (err) {
    showToast('Error de conexión al eliminar usuario', 'error');
    console.error(err);
  }
}

// ══════════════════════════════════════════════════════════════
// MODAL MASCOTAS — lista (izquierda) + panel de detalle (derecha)
// ══════════════════════════════════════════════════════════════
async function openPetsModal(id) {
  const user = USUARIOS.find(u => u.id === id);
  if (!user) return;

  idUsuarioModal = id;
  idMascotaActiva = null;
  document.getElementById('modalUserName').textContent = `Mascotas de ${user.nombre}`;
  document.getElementById('modalPetCount').textContent = 'Cargando...';
  document.getElementById('petListPanel').innerHTML  = '<div class="no-pets"><i class="fas fa-spinner fa-spin"></i><p>Cargando mascotas...</p></div>';
  document.getElementById('petDetailPanel').innerHTML = '';
  document.getElementById('petsModal').classList.add('open');

  try {
    const res  = await fetch(`${PATHS.obtenerMascotas}?id_usuario=${id}`);
    const data = await res.json();

    if (!data.success) {
      document.getElementById('petListPanel').innerHTML = '<div class="no-pets"><p>Error al cargar mascotas.</p></div>';
      return;
    }

    mascotasDelModal = data.mascotas;
    document.getElementById('modalPetCount').textContent =
      `${mascotasDelModal.length} mascota${mascotasDelModal.length !== 1 ? 's' : ''} registrada${mascotasDelModal.length !== 1 ? 's' : ''}`;

    renderListaMascotasModal();

    if (mascotasDelModal.length > 0) {
      seleccionarMascota(mascotasDelModal[0].id_mascota);
    } else {
      document.getElementById('petDetailPanel').innerHTML =
        '<div class="no-pets"><i class="fas fa-dog"></i><p>Este usuario no tiene mascotas registradas.</p></div>';
    }
  } catch (err) {
    document.getElementById('petListPanel').innerHTML = '<div class="no-pets"><p>Error de conexión.</p></div>';
    console.error(err);
  }
}

// Lista de la izquierda: mascotas del usuario, clickeables
function renderListaMascotasModal() {
  const listEl = document.getElementById('petListPanel');
  listEl.innerHTML = '';

  mascotasDelModal.forEach(p => {
    const avatarHtml = p.avatar
      ? `<img src="../../${p.avatar}" alt="${p.nombre}" class="pet-av-img" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">`
      : '';
    const fallbackStyle = p.avatar ? 'display:none' : '';

    const card = document.createElement('div');
    card.className = 'pet-card' + (p.id_mascota === idMascotaActiva ? ' active' : '');
    card.innerHTML = `
      <div class="pet-avatar">
        ${avatarHtml}
        <div class="pet-av-fallback" style="${fallbackStyle}"><i class="fas fa-dog"></i></div>
      </div>
      <div class="pet-info">
        <div class="pet-name">${p.nombre}</div>
        ${p.raza ? `<div class="pet-breed">${p.raza}</div>` : ''}
      </div>`;
    card.addEventListener('click', () => seleccionarMascota(p.id_mascota));
    listEl.appendChild(card);
  });
}

// Fecha "dd/mm/aaaa" a partir de un datetime o de una fecha "Y-m-d" de MySQL.
// Las fechas sin hora ("2026-07-04") se interpretan como UTC por el motor de
// JS: hay que forzarlas a medianoche LOCAL o se corren un día en huso horario
// negativo (Colombia = UTC-5).
function formatearFechaMascota(fechaStr) {
  if (!fechaStr) return '—';
  const normalizado = fechaStr.includes(' ') ? fechaStr.replace(' ', 'T') : fechaStr + 'T00:00:00';
  const d = new Date(normalizado);
  if (isNaN(d.getTime())) return '—';
  return d.toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

// Trae y pinta el detalle completo de una mascota en el panel derecho
async function seleccionarMascota(idMascota) {
  idMascotaActiva = idMascota;
  renderListaMascotasModal(); // refresca cuál queda resaltada como activa

  const panel = document.getElementById('petDetailPanel');
  panel.innerHTML = '<div class="no-pets"><i class="fas fa-spinner fa-spin"></i><p>Cargando...</p></div>';

  try {
    const res = await fetch(`${PATHS.obtenerDetalleMascota}?id_mascota=${idMascota}`);
    const data = await res.json();
    if (!data.success) {
      panel.innerHTML = `<div class="no-pets"><p>${data.message || 'Error al cargar el detalle.'}</p></div>`;
      return;
    }
    renderDetalleMascota(data.mascota);
  } catch (err) {
    panel.innerHTML = '<div class="no-pets"><p>Error de conexión.</p></div>';
    console.error(err);
  }
}

// Vista de solo lectura del panel derecho
function renderDetalleMascota(m) {
  const panel = document.getElementById('petDetailPanel');
  const avatarHtml = m.avatar
    ? `<img src="../../${m.avatar}" alt="${m.nombre}" onerror="this.style.display='none'">`
    : `<i class="fas fa-dog"></i>`;

  const saludVacia = !m.notas || m.notas.trim() === '';

  let serviciosHtml;
  if (!m.plan && !m.ultimo_paseo && !m.proximo_paseo && !m.asignacion) {
    serviciosHtml = '<div class="pd-empty-servicios">Esta mascota no tiene servicios contratados.</div>';
  } else {
    serviciosHtml = `
      <div class="pd-servicios-grid">
        <div class="pd-servicio-card">
          <div class="pd-s-label"><i class="fas fa-paw"></i> Plan de paseos</div>
          <div class="pd-s-value">${m.plan ? (m.plan.activa ? 'Mensualidad activa' : 'Plan vencido') : 'Sin plan'}</div>
          ${m.plan ? `<div class="pd-s-label">${m.plan.paseos_mes} paseos por mes</div>` : ''}
        </div>
        <div class="pd-servicio-card">
          <div class="pd-s-label"><i class="far fa-calendar-check"></i> Último paseo</div>
          <div class="pd-s-value">${m.ultimo_paseo ? formatearFechaMascota(m.ultimo_paseo.fecha) : '—'}</div>
          ${m.ultimo_paseo ? `<div class="pd-s-label">con ${m.ultimo_paseo.paseador_nombre}</div>` : ''}
        </div>
        <div class="pd-servicio-card">
          <div class="pd-s-label"><i class="far fa-calendar"></i> Próximo paseo</div>
          <div class="pd-s-value">${m.proximo_paseo ? formatearFechaMascota(m.proximo_paseo.fecha) : '—'}</div>
          ${m.proximo_paseo && m.proximo_paseo.franja ? `<div class="pd-s-label">${m.proximo_paseo.franja}</div>` : ''}
        </div>
        <div class="pd-servicio-card">
          <div class="pd-s-label"><i class="fas fa-person-walking"></i> Paseador asignado</div>
          <div class="pd-s-value">${m.asignacion ? m.asignacion.nombre : '—'}</div>
          ${m.asignacion ? `<div class="pd-s-label">⭐ ${m.asignacion.puntuacion.toFixed(1)}</div>` : ''}
        </div>
      </div>`;
  }

  const edadTexto = m.edad !== null ? `${m.edad} año${m.edad === 1 ? '' : 's'}` : '—';

  panel.innerHTML = `
    <div class="pd-header-row">
      <div class="pd-avatar-big">${avatarHtml}</div>
      <div class="pd-header-info">
        <div class="pd-name">${m.nombre}</div>
        ${m.raza ? `<span class="pet-breed-pill">${m.raza}</span>` : ''}
        <div class="pd-quick-meta">
          <span><i class="fas fa-user"></i> Dueño: ${m.dueno.nombre || '—'}</span>
          <span><i class="far fa-calendar"></i> Fecha de registro: ${formatearFechaMascota(m.fecha_registro)}</span>
        </div>
      </div>
      <div class="pd-badges-col">
        <div class="pd-badge-card pd-badge-blue">
          <i class="far fa-calendar"></i>
          <div><div class="pd-bc-label">Edad</div><div class="pd-bc-value">${edadTexto}</div></div>
        </div>
        <div class="pd-badge-card ${saludVacia ? 'pd-badge-green' : 'pd-badge-orange'}">
          <i class="fas fa-heart"></i>
          <div><div class="pd-bc-label">Estado de salud</div><div class="pd-bc-value">${saludVacia ? 'Sin reportes' : 'Con reportes'}</div></div>
        </div>
        <div class="pd-badge-card pd-badge-purple">
          <i class="fas fa-shield-heart"></i>
          <div><div class="pd-bc-label">Servicios activos</div><div class="pd-bc-value">${m.plan && m.plan.activa ? 'Paseos' : 'Ninguno'}</div></div>
        </div>
      </div>
    </div>

    <div class="pd-section-title">Información general</div>
    <div class="pd-info-grid">
      <div><div class="pd-label">Nombre de la mascota</div><div class="pd-value">${m.nombre}</div></div>
      <div><div class="pd-label">Dueño</div><div class="pd-value">${m.dueno.nombre || '—'}</div></div>
      <div><div class="pd-label">Edad</div><div class="pd-value">${edadTexto}</div></div>
      <div><div class="pd-label">Teléfono del dueño</div><div class="pd-value">${m.dueno.telefono || '—'}</div></div>
      <div><div class="pd-label">Raza</div><div class="pd-value">${m.raza || '—'}</div></div>
      <div><div class="pd-label">Correo del dueño</div><div class="pd-value">${m.dueno.email || '—'}</div></div>
    </div>

    <div class="pd-section-title">Biografía canina</div>
    <div class="pd-box">${m.biografia && m.biografia.trim() !== '' ? m.biografia : 'Sin biografía registrada.'}</div>

    <div class="pd-section-title">Salud y cuidados especiales</div>
    <div class="pd-box">${saludVacia ? 'Ninguna' : m.notas}</div>
    ${saludVacia ? '<div class="pd-box-ok"><i class="fas fa-check-circle"></i> No se han reportado condiciones médicas ni cuidados especiales.</div>' : ''}

    <div class="pd-section-title">Servicios asociados</div>
    ${serviciosHtml}

    <div class="pd-actions">
      <button class="pd-btn" id="btnEditarMascota"><i class="fas fa-pen"></i> Editar mascota</button>
      <button class="pd-btn" id="btnHistorialMascota"><i class="fas fa-clock-rotate-left"></i> Ver historial de servicios</button>
      <button class="pd-btn pd-btn-danger" id="btnEliminarMascota"><i class="fas fa-trash-alt"></i> Eliminar mascota</button>
    </div>`;

  document.getElementById('btnEditarMascota').addEventListener('click', () => renderFormEditarMascota(m));
  document.getElementById('btnHistorialMascota').addEventListener('click', () => cargarHistorialMascota(m.id_mascota));
  document.getElementById('btnEliminarMascota').addEventListener('click', () => confirmarEliminarMascota(m));
}

// Cambia el panel derecho a modo edición
function renderFormEditarMascota(m) {
  const panel = document.getElementById('petDetailPanel');
  panel.innerHTML = `
    <button class="pd-back-btn" id="btnCancelarEdicion"><i class="fas fa-arrow-left"></i> Cancelar</button>
    <div class="pd-section-title">Editar mascota</div>
    <div class="pd-form-group">
      <label>Nombre</label>
      <input type="text" id="editNombre" value="${m.nombre}">
    </div>
    <div class="pd-form-row">
      <div class="pd-form-group">
        <label>Raza</label>
        <input type="text" id="editRaza" value="${m.raza || ''}">
      </div>
      <div class="pd-form-group">
        <label>Edad (años)</label>
        <input type="number" id="editEdad" min="0" max="30" value="${m.edad !== null ? m.edad : ''}">
      </div>
    </div>
    <div class="pd-form-group">
      <label>Foto (opcional)</label>
      <input type="file" id="editAvatar" accept="image/*">
    </div>
    <div class="pd-form-group">
      <label>Biografía canina</label>
      <textarea id="editBiografia" rows="3">${m.biografia || ''}</textarea>
    </div>
    <div class="pd-form-group">
      <label>Enfermedades / cuidados especiales</label>
      <textarea id="editNotas" rows="2">${m.notas || ''}</textarea>
    </div>
    <div class="pd-actions">
      <button class="pd-btn" id="btnGuardarEdicion" style="background:var(--primary-blue); color:#fff; border:none;">
        <i class="fas fa-check"></i> Guardar cambios
      </button>
    </div>`;

  document.getElementById('btnCancelarEdicion').addEventListener('click', () => renderDetalleMascota(m));
  document.getElementById('btnGuardarEdicion').addEventListener('click', () => guardarEdicionMascota(m));
}

async function guardarEdicionMascota(m) {
  const btn = document.getElementById('btnGuardarEdicion');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

  const nombreNuevo = document.getElementById('editNombre').value.trim();
  const razaNueva   = document.getElementById('editRaza').value.trim();

  const formData = new FormData();
  formData.append('accion', 'editar');
  formData.append('id_mascota', m.id_mascota);
  formData.append('nombre_mascota', nombreNuevo);
  formData.append('raza', razaNueva);
  formData.append('edad', document.getElementById('editEdad').value);
  formData.append('biografia_canina', document.getElementById('editBiografia').value.trim());
  formData.append('enfermedades_discapacidades', document.getElementById('editNotas').value.trim());
  const archivo = document.getElementById('editAvatar').files[0];
  if (archivo) formData.append('avatar_mascota', archivo);

  try {
    const res = await fetch(PATHS.gestionarMascotaAdmin, { method: 'POST', body: formData });
    const data = await res.json();

    if (!data.success) {
      showToast('Error: ' + data.message, 'error');
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-check"></i> Guardar cambios';
      return;
    }

    showToast('Mascota actualizada correctamente', 'success');

    const idx = mascotasDelModal.findIndex(p => p.id_mascota === m.id_mascota);
    if (idx !== -1) {
      if (nombreNuevo) mascotasDelModal[idx].nombre = nombreNuevo;
      if (razaNueva)   mascotasDelModal[idx].raza   = razaNueva;
    }
    renderListaMascotasModal();
    seleccionarMascota(m.id_mascota);
  } catch (err) {
    showToast('Error de conexión al guardar', 'error');
    console.error(err);
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-check"></i> Guardar cambios';
  }
}

// Cambia el panel derecho al historial de paseos completados
async function cargarHistorialMascota(idMascota) {
  const panel = document.getElementById('petDetailPanel');
  panel.innerHTML = `
    <button class="pd-back-btn" id="btnVolverDetalle"><i class="fas fa-arrow-left"></i> Volver</button>
    <div class="pd-section-title">Historial de servicios</div>
    <div id="historialLista"><div class="no-pets"><i class="fas fa-spinner fa-spin"></i><p>Cargando...</p></div></div>`;

  document.getElementById('btnVolverDetalle').addEventListener('click', () => seleccionarMascota(idMascota));

  try {
    const res = await fetch(`${PATHS.obtenerHistorialMascota}?id_mascota=${idMascota}`);
    const data = await res.json();
    const cont = document.getElementById('historialLista');

    if (!data.success) {
      cont.innerHTML = `<div class="no-pets"><p>${data.message || 'Error al cargar historial.'}</p></div>`;
      return;
    }
    if (data.historial.length === 0) {
      cont.innerHTML = '<div class="pd-empty-servicios">Esta mascota no tiene paseos completados todavía.</div>';
      return;
    }
    cont.innerHTML = data.historial.map(h => `
      <div class="pd-historial-item">
        <span>${formatearFechaMascota(h.fecha)} — ${h.paseador}</span>
        <span>${h.duracion_min !== null ? h.duracion_min + ' min' : h.duracion_estimada + ' min (estimado)'}</span>
      </div>`).join('');
  } catch (err) {
    document.getElementById('historialLista').innerHTML = '<div class="no-pets"><p>Error de conexión.</p></div>';
    console.error(err);
  }
}

// Confirma y elimina la mascota; refresca lista, panel y contador de la fila
async function confirmarEliminarMascota(m) {
  if (!confirm(`¿Seguro que quieres eliminar a ${m.nombre}? Esta acción no se puede deshacer.`)) return;

  try {
    const formData = new FormData();
    formData.append('accion', 'eliminar');
    formData.append('id_mascota', m.id_mascota);
    const res = await fetch(PATHS.gestionarMascotaAdmin, { method: 'POST', body: formData });
    const data = await res.json();

    if (!data.success) {
      showToast('Error: ' + data.message, 'error');
      return;
    }

    showToast('Mascota eliminada correctamente', 'success');

    mascotasDelModal = mascotasDelModal.filter(p => p.id_mascota !== m.id_mascota);
    document.getElementById('modalPetCount').textContent =
      `${mascotasDelModal.length} mascota${mascotasDelModal.length !== 1 ? 's' : ''} registrada${mascotasDelModal.length !== 1 ? 's' : ''}`;

    const user = USUARIOS.find(u => u.id === idUsuarioModal);
    if (user && user.totalMascotas > 0) user.totalMascotas--;
    const badge = document.querySelector(`.btn-pets[data-id="${idUsuarioModal}"] .pet-count`);
    if (badge) badge.textContent = user ? user.totalMascotas : Math.max(0, parseInt(badge.textContent, 10) - 1);

    renderListaMascotasModal();

    if (mascotasDelModal.length > 0) {
      seleccionarMascota(mascotasDelModal[0].id_mascota);
    } else {
      idMascotaActiva = null;
      document.getElementById('petDetailPanel').innerHTML =
        '<div class="no-pets"><i class="fas fa-dog"></i><p>Este usuario no tiene mascotas registradas.</p></div>';
    }
  } catch (err) {
    showToast('Error de conexión al eliminar', 'error');
    console.error(err);
  }
}

document.getElementById('closeModal').addEventListener('click', () => {
  document.getElementById('petsModal').classList.remove('open');
});
document.getElementById('petsModal').addEventListener('click', e => {
  if (e.target === document.getElementById('petsModal'))
    document.getElementById('petsModal').classList.remove('open');
});

// ══════════════════════════════════════════════════════════════
// PAGINACIÓN
// ══════════════════════════════════════════════════════════════
function renderPagination(totalPages) {
  const container = document.querySelector('.pag-btns');
  const prevBtn   = document.getElementById('pagPrev');
  const nextBtn   = document.getElementById('pagNext');
  container.querySelectorAll('[data-page]').forEach(b => b.remove());

  prevBtn.disabled = currentPage === 1;
  nextBtn.disabled = currentPage >= totalPages || totalPages === 0;

  for (let i = 1; i <= totalPages; i++) {
    const btn = document.createElement('button');
    btn.className    = 'pag-btn' + (i === currentPage ? ' active' : '');
    btn.dataset.page = i;
    btn.textContent  = i;
    btn.addEventListener('click', () => { currentPage = i; renderList(); });
    container.insertBefore(btn, nextBtn);
  }
}
document.getElementById('pagPrev').addEventListener('click', () => { if (currentPage > 1) { currentPage--; renderList(); } });
document.getElementById('pagNext').addEventListener('click', () => { currentPage++; renderList(); });

// ══════════════════════════════════════════════════════════════
// STATS
// ══════════════════════════════════════════════════════════════
function updateStats() {
  document.getElementById('statTotal').textContent    = USUARIOS.length;
  document.getElementById('statCliente').textContent  = USUARIOS.filter(u => u.rol === 'cliente').length;
  document.getElementById('statPaseador').textContent = USUARIOS.filter(u => u.rol === 'paseador').length;
  document.getElementById('statAdmin').textContent    = USUARIOS.filter(u => u.rol === 'administrador').length;
}

// ══════════════════════════════════════════════════════════════
// HELPERS
// ══════════════════════════════════════════════════════════════
function capitalize(str) { return str.charAt(0).toUpperCase() + str.slice(1); }

function closeAllDropdowns(except) {
  document.querySelectorAll('.role-dropdown.open, .actions-menu.open').forEach(d => {
    if (d !== except) {
      d.classList.remove('open');
      const btn = d.previousElementSibling;
      if (btn) btn.classList.remove('open');
    }
  });
}
document.addEventListener('click', () => closeAllDropdowns(null));

let toastTimer;
function showToast(msg, type = 'success') {
  const t = document.getElementById('toast');
  const i = t.querySelector('i');
  document.getElementById('toastMsg').textContent = msg;
  t.className = `toast ${type}`;
  i.className = type === 'success' ? 'fas fa-check-circle' : 'fas fa-info-circle';
  t.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => t.classList.remove('show'), 3000);
}

// ══════════════════════════════════════════════════════════════
// INIT
// ══════════════════════════════════════════════════════════════
cargarUsuarios();