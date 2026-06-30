// ══════════════════════════════════════════════════════════════
// ESTADO
// ══════════════════════════════════════════════════════════════
let USUARIOS      = [];
let filteredUsers = [];
let currentPage   = 1;
const PER_PAGE    = 5;

// Rutas base según estructura: view/js/admin/ → raíz
const PATHS = {
  obtenerUsuarios: '../../../model/obtener_usuarios.php',
  obtenerMascotas: '../../../model/obtener_mascotas.php',
  cambiarRol:      '../../../controller/cambiar_rol.php',
  eliminarUsuario: '../../../controller/eliminar_usuario.php',
};

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
// MODAL MASCOTAS
// ══════════════════════════════════════════════════════════════
async function openPetsModal(id) {
  const user = USUARIOS.find(u => u.id === id);
  if (!user) return;

  document.getElementById('modalUserName').textContent = `Mascotas de ${user.nombre}`;
  document.getElementById('modalPetCount').textContent = 'Cargando...';
  document.getElementById('modalPetList').innerHTML    = '<div class="no-pets"><i class="fas fa-spinner fa-spin"></i><p>Cargando mascotas...</p></div>';
  document.getElementById('petsModal').classList.add('open');

  try {
    const res  = await fetch(`${PATHS.obtenerMascotas}?id_usuario=${id}`);
    const data = await res.json();

    if (!data.success) {
      document.getElementById('modalPetList').innerHTML = '<div class="no-pets"><p>Error al cargar mascotas.</p></div>';
      return;
    }

    const mascotas = data.mascotas;
    document.getElementById('modalPetCount').textContent =
      `${mascotas.length} mascota${mascotas.length !== 1 ? 's' : ''} registrada${mascotas.length !== 1 ? 's' : ''}`;

    const listEl = document.getElementById('modalPetList');
    listEl.innerHTML = '';

    if (mascotas.length === 0) {
      listEl.innerHTML = `<div class="no-pets"><i class="fas fa-dog"></i><p>Este usuario no tiene mascotas registradas.</p></div>`;
    } else {
      mascotas.forEach(p => {
        const avatarHtml = p.avatar_url
          ? `<img src="../../${p.avatar_url}" alt="${p.nombre_mascota}" class="pet-av-img" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">`
          : '';
        const fallbackStyle = p.avatar_url ? 'display:none' : '';
        listEl.innerHTML += `
          <div class="pet-card">
            <div class="pet-avatar">
              ${avatarHtml}
              <div class="pet-av-fallback" style="${fallbackStyle}"><i class="fas fa-dog"></i></div>
            </div>
            <div class="pet-info">
              <div class="pet-name">${p.nombre_mascota}</div>
              <div class="pet-meta">
                <span class="pet-tag">Dueño: ${user.nombre.split(' ')[0]}</span>
              </div>
            </div>
          </div>`;
      });
    }
  } catch (err) {
    document.getElementById('modalPetList').innerHTML = '<div class="no-pets"><p>Error de conexión.</p></div>';
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