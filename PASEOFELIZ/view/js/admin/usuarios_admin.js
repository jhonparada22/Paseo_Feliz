// ══════════════════════════════════════════════════════════════
// DATOS (simulando BD — reemplazar con fetch al PHP)
// ══════════════════════════════════════════════════════════════
const USUARIOS = [
  {
    cc: 1001, nombre: 'Pedro Ramírez', email: 'pedro@gmail.com',
    rol: 'cliente', fechaReg: '2026-01-08', online: true,
    telefono: '314 123 4567', direccion: 'Cúcuta, Norte de Santander',
    mascotas: [
      { nombre: 'Max', raza: 'Golden Retriever', edad: '3 años', emoji: '🐕' },
      { nombre: 'Coco', raza: 'Poodle',          edad: '1 año',  emoji: '🐩' },
    ]
  },
  {
    cc: 1002, nombre: 'Claudia López', email: 'claudia@hotmail.com',
    rol: 'paseador', fechaReg: '2026-05-03', online: true,
    telefono: '300 987 6543', direccion: 'Cúcuta Centro',
    mascotas: [
      { nombre: 'Luna', raza: 'Labrador', edad: '2 años', emoji: '🐶' },
    ]
  },
  {
    cc: 1003, nombre: 'Humberto Sánchez', email: 'humberto@gmail.com',
    rol: 'administrador', fechaReg: '2026-03-08', online: false,
    telefono: '317 456 7890', direccion: 'Cúcuta Norte',
    mascotas: []
  },
  {
    cc: 1004, nombre: 'María González', email: 'maria@outlook.com',
    rol: 'cliente', fechaReg: '2026-02-14', online: true,
    telefono: '320 111 2233', direccion: 'Los Patios, Norte de Santander',
    mascotas: [
      { nombre: 'Rocky', raza: 'Bulldog', edad: '4 años', emoji: '🐾' },
    ]
  },
  {
    cc: 1005, nombre: 'Juan Pérez', email: 'juanp@gmail.com',
    rol: 'paseador', fechaReg: '2025-11-20', online: false,
    telefono: '311 222 3344', direccion: 'Villa del Rosario',
    mascotas: [
      { nombre: 'Nala', raza: 'Beagle', edad: '2 años', emoji: '🐕' },
      { nombre: 'Bruno', raza: 'Dálmata', edad: '5 años', emoji: '🐾' },
      { nombre: 'Tomy', raza: 'Chihuahua', edad: '1 año', emoji: '🐩' },
    ]
  },
  {
    cc: 1006, nombre: 'Laura Martínez', email: 'laura@gmail.com',
    rol: 'cliente', fechaReg: '2026-04-22', online: true,
    telefono: '318 445 6677', direccion: 'Cúcuta Sur',
    mascotas: []
  },
  {
    cc: 1007, nombre: 'Carlos Rodríguez', email: 'carlosr@hotmail.com',
    rol: 'paseador', fechaReg: '2025-12-05', online: false,
    telefono: '315 778 8990', direccion: 'El Zulia, Norte de Santander',
    mascotas: [
      { nombre: 'Kira', raza: 'Husky', edad: '3 años', emoji: '🐕' },
    ]
  },
  {
    cc: 1008, nombre: 'Ana Fernández', email: 'anaf@outlook.com',
    rol: 'cliente', fechaReg: '2026-03-30', online: true,
    telefono: '312 334 5566', direccion: 'Cúcuta Este',
    mascotas: [
      { nombre: 'Pelusa', raza: 'Maltés', edad: '6 meses', emoji: '🐩' },
    ]
  },
];

// ══════════════════════════════════════════════════════════════
// ESTADO
// ══════════════════════════════════════════════════════════════
let filteredUsers = [...USUARIOS];
let currentPage   = 1;
const PER_PAGE    = 5;

// ══════════════════════════════════════════════════════════════
// SIDEBAR MENÚ
// ══════════════════════════════════════════════════════════════
const btnMenu = document.getElementById('btn-menu');
const menuLatente = document.getElementById('menu-latente');
btnMenu.addEventListener('click', () => menuLatente.classList.toggle('show'));
window.addEventListener('click', e => {
  if (!btnMenu.contains(e.target) && !menuLatente.contains(e.target))
    menuLatente.classList.remove('show');
});

// ══════════════════════════════════════════════════════════════
// FILTROS
// ══════════════════════════════════════════════════════════════
const btnFilter    = document.getElementById('btnFilter');
const filterPanel  = document.getElementById('filterPanel');
const btnClose     = document.getElementById('btnCloseFilter');
const btnClear     = document.getElementById('btnClearFilter');
const btnApply     = document.getElementById('btnApplyFilter');
const searchInput  = document.getElementById('searchInput');

btnFilter.addEventListener('click', () => {
  filterPanel.classList.toggle('open');
  btnFilter.classList.toggle('active');
});
btnClose.addEventListener('click', () => {
  filterPanel.classList.remove('open');
  btnFilter.classList.remove('active');
});

// Búsqueda en tiempo real
searchInput.addEventListener('input', () => {
  applyFilters();
});

// Limpiar filtros
btnClear.addEventListener('click', () => {
  document.getElementById('fNombre').value = '';
  document.getElementById('fFecha').value  = '';
  document.getElementById('fRol').value    = '';
  searchInput.value = '';
  applyFilters();
});

// Aplicar filtros
btnApply.addEventListener('click', () => {
  filterPanel.classList.remove('open');
  btnFilter.classList.remove('active');
  applyFilters();
});

// Ordenar
document.getElementById('sortSelect').addEventListener('change', () => renderList());

function applyFilters() {
  const q     = searchInput.value.trim().toLowerCase();
  const fNom  = document.getElementById('fNombre').value.trim().toLowerCase();
  const fFech = document.getElementById('fFecha').value;
  const fRol  = document.getElementById('fRol').value.toLowerCase();

  filteredUsers = USUARIOS.filter(u => {
    const matchQ    = !q    || u.nombre.toLowerCase().includes(q) || u.email.toLowerCase().includes(q) || String(u.cc).includes(q);
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
  const sort = document.getElementById('sortSelect').value;
  const sorted = [...filteredUsers].sort((a, b) => {
    if (sort === 'nombre') return a.nombre.localeCompare(b.nombre);
    if (sort === 'fecha')  return new Date(b.fechaReg) - new Date(a.fechaReg);
    if (sort === 'rol')    return a.rol.localeCompare(b.rol);
    return 0;
  });

  const start  = (currentPage - 1) * PER_PAGE;
  const paged  = sorted.slice(start, start + PER_PAGE);
  const total  = sorted.length;
  const end    = Math.min(start + PER_PAGE, total);

  const listEl = document.getElementById('userList');
  const empty  = document.getElementById('emptyState');
  listEl.innerHTML = '';

  if (paged.length === 0) {
    empty.classList.add('visible');
  } else {
    empty.classList.remove('visible');
    paged.forEach(u => listEl.appendChild(buildUserRow(u)));
  }

  // Paginación
  document.getElementById('pagInfo').textContent =
    total === 0 ? '0 usuarios' : `Mostrando ${start+1}–${end} de ${total} usuarios`;
  document.getElementById('userCount').textContent = `${total} usuario${total !== 1 ? 's' : ''}`;

  const totalPages = Math.ceil(total / PER_PAGE);
  renderPagination(totalPages);
  updateStats();
}

function buildUserRow(u) {
  const div = document.createElement('div');
  div.className = 'user-row';

  // Colores de avatar por nombre
  const colors = ['#3E72A6','#16a34a','#7c3aed','#ea580c','#db2777','#0891b2'];
  const ci = u.cc % colors.length;
  const initials = u.nombre.split(' ').map(p=>p[0]).slice(0,2).join('');

  const fechaFmt = new Date(u.fechaReg + 'T00:00:00')
    .toLocaleDateString('es-ES', {day:'2-digit', month:'2-digit', year:'numeric'});

  div.innerHTML = `
    <!-- Avatar -->
    <div class="user-avatar">
      <div class="av-fallback" style="background:${colors[ci]}">${initials}</div>
      <div class="avatar-online ${u.online ? 'on' : 'off'}"></div>
    </div>

    <!-- Info -->
    <div class="user-info">
      <div class="user-name">${u.nombre}</div>
      <div class="user-email">${u.email}</div>
      <div class="user-meta">
        <div class="um-item"><i class="fas fa-id-card"></i> CC: ${u.cc}</div>
        <div class="um-item"><i class="fas fa-phone"></i> ${u.telefono}</div>
        <div class="um-item"><i class="fas fa-map-marker-alt"></i> ${u.direccion}</div>
      </div>
    </div>

    <!-- Fecha registro -->
    <div class="reg-date">
      <div class="rd-label">Fecha registro</div>
      <div class="rd-val">${fechaFmt}</div>
    </div>

    <!-- Mascotas -->
    <button class="btn-pets" data-cc="${u.cc}">
      <i class="fas fa-dog"></i>
      Mascotas
      <span class="pet-count">${u.mascotas.length}</span>
    </button>

    <!-- Rol dropdown -->
    <div class="role-wrap">
      <button class="btn-role rol-${u.rol}" data-cc="${u.cc}">
        <span class="role-text">${capitalize(u.rol)}</span>
        <i class="fas fa-chevron-down role-arrow"></i>
      </button>
      <div class="role-dropdown" id="role-dd-${u.cc}">
        <div class="role-option ${u.rol==='cliente'?'selected':''}" data-cc="${u.cc}" data-rol="cliente">
          <div class="role-dot dot-cliente"></div> Cliente
        </div>
        <div class="role-option ${u.rol==='paseador'?'selected':''}" data-cc="${u.cc}" data-rol="paseador">
          <div class="role-dot dot-paseador"></div> Paseador
        </div>
        <div class="role-option ${u.rol==='administrador'?'selected':''}" data-cc="${u.cc}" data-rol="administrador">
          <div class="role-dot dot-administrador"></div> Administrador
        </div>
      </div>
    </div>

    <!-- Más acciones -->
    <div class="actions-menu-wrap">
      <button class="btn-more" data-cc="${u.cc}">
        <i class="fas fa-ellipsis-vertical"></i>
      </button>
      <div class="actions-menu" id="act-menu-${u.cc}">
        <div class="action-opt" onclick="showToast('Ver perfil de ${u.nombre}','info')">
          <i class="fas fa-eye"></i> Ver perfil
        </div>
        <div class="action-opt" onclick="showToast('Editando a ${u.nombre}','info')">
          <i class="fas fa-pen"></i> Editar
        </div>
        <div class="action-opt" onclick="openPetsModal(${u.cc})">
          <i class="fas fa-dog"></i> Ver mascotas
        </div>
        <div class="action-opt" onclick="showToast('Correo enviado a ${u.email}','info')">
          <i class="fas fa-envelope"></i> Enviar mensaje
        </div>
        <div class="action-opt danger" onclick="confirmDelete(${u.cc})">
          <i class="fas fa-trash"></i> Eliminar usuario
        </div>
      </div>
    </div>
  `;

  // ── Evento botón mascotas
  div.querySelector('.btn-pets').addEventListener('click', e => {
    openPetsModal(Number(e.currentTarget.dataset.cc));
  });

  // ── Evento role dropdown
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
      const cc  = Number(opt.dataset.cc);
      const rol = opt.dataset.rol;
      changeRole(cc, rol);
      roleDd.classList.remove('open');
      roleBtn.classList.remove('open');
    });
  });

  // ── Evento más acciones
  const moreBtn  = div.querySelector('.btn-more');
  const actMenu  = div.querySelector('.actions-menu');
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
function changeRole(cc, nuevoRol) {
  const user = USUARIOS.find(u => u.cc === cc);
  if (!user || user.rol === nuevoRol) return;
  const rolAnterior = user.rol;
  user.rol = nuevoRol;

  // TODO: Aquí va el fetch al PHP
  // fetch('../../../model/php pagina principal/cambiar_rol.php', { method:'POST', body: new URLSearchParams({cc, rol: nuevoRol}) })

  showToast(`Rol de ${user.nombre} cambiado a ${capitalize(nuevoRol)}`, 'success');
  applyFilters();
}

// ══════════════════════════════════════════════════════════════
// MODAL MASCOTAS
// ══════════════════════════════════════════════════════════════
function openPetsModal(cc) {
  const user = USUARIOS.find(u => u.cc === cc);
  if (!user) return;

  document.getElementById('modalUserName').textContent = `Mascotas de ${user.nombre}`;
  document.getElementById('modalPetCount').textContent =
    `${user.mascotas.length} mascota${user.mascotas.length !== 1 ? 's' : ''} registrada${user.mascotas.length !== 1 ? 's' : ''}`;

  const listEl = document.getElementById('modalPetList');
  listEl.innerHTML = '';

  if (user.mascotas.length === 0) {
    listEl.innerHTML = `<div class="no-pets"><i class="fas fa-dog"></i><p>Este usuario no tiene mascotas registradas.</p></div>`;
  } else {
    user.mascotas.forEach(p => {
      listEl.innerHTML += `
        <div class="pet-card">
          <div class="pet-avatar">${p.emoji}</div>
          <div class="pet-info">
            <div class="pet-name">${p.nombre}</div>
            <div class="pet-breed">${p.raza}</div>
            <div class="pet-meta">
              <span class="pet-tag"><i class="fas fa-birthday-cake"></i> ${p.edad}</span>
              <span class="pet-tag">Dueño: ${user.nombre.split(' ')[0]}</span>
            </div>
          </div>
        </div>`;
    });
  }
  document.getElementById('petsModal').classList.add('open');
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
  // Limpiar botones de página (dejar solo prev y next)
  container.querySelectorAll('[data-page]').forEach(b => b.remove());

  prevBtn.disabled = currentPage === 1;
  nextBtn.disabled = currentPage >= totalPages || totalPages === 0;

  for (let i = 1; i <= totalPages; i++) {
    const btn = document.createElement('button');
    btn.className = 'pag-btn' + (i === currentPage ? ' active' : '');
    btn.dataset.page = i;
    btn.textContent  = i;
    btn.addEventListener('click', () => {
      currentPage = i; renderList();
    });
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
  document.getElementById('statCliente').textContent  = USUARIOS.filter(u=>u.rol==='cliente').length;
  document.getElementById('statPaseador').textContent = USUARIOS.filter(u=>u.rol==='paseador').length;
  document.getElementById('statAdmin').textContent    = USUARIOS.filter(u=>u.rol==='administrador').length;
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

function confirmDelete(cc) {
  const user = USUARIOS.find(u => u.cc === cc);
  if (!user) return;
  if (confirm(`¿Estás seguro de eliminar a ${user.nombre}? Esta acción no se puede deshacer.`)) {
    const idx = USUARIOS.indexOf(user);
    USUARIOS.splice(idx, 1);
    showToast(`${user.nombre} eliminado correctamente`, 'success');
    applyFilters();
  }
}

// ══════════════════════════════════════════════════════════════
// INIT
// ══════════════════════════════════════════════════════════════
applyFilters();