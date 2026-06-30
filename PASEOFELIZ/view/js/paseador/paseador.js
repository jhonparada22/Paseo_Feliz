// ── Menú Hamburguesa ──────────────────────────────────────────────────────────
const btnMenu = document.getElementById('btn-menu');
const menuLatente = document.getElementById('menu-latente');

btnMenu.addEventListener('click', () => menuLatente.classList.toggle('show'));
window.addEventListener('click', (e) => {
    if (!btnMenu.contains(e.target) && !menuLatente.contains(e.target)) {
        menuLatente.classList.remove('show');
    }
});

// ── Fecha de cabecera ─────────────────────────────────────────────────────────
document.getElementById('dateLabel').textContent = new Date().toLocaleDateString('es-ES', {
    day: 'numeric',
    month: 'long',
    year: 'numeric'
});

// ── Base de datos simulada de paseos por día ──────────────────────────────────
const basePaseos = {
    "Lunes": [
        { id: 201, pet: "Rocky", breed: "Golden", time: "07:30 AM", zone: "Prados del Este", status: "Pendiente" },
        { id: 202, pet: "Luna", breed: "Pug", time: "09:00 AM", zone: "La Ceiba", status: "Pendiente" },
        { id: 203, pet: "Zeus", breed: "Pastor Alemán", time: "04:00 PM", zone: "Quinta Vélez", status: "Pendiente" }
    ],
    "Martes": [
        { id: 204, pet: "Coco", breed: "Poodle", time: "08:00 AM", zone: "Centro", status: "Pendiente" },
        { id: 205, pet: "Toby", breed: "Criollo", time: "10:30 AM", zone: "San Eduardo", status: "Pendiente" }
    ],
    "Miércoles": [
        { id: 206, pet: "Rocky", breed: "Golden", time: "07:30 AM", zone: "Prados del Este", status: "Pendiente" },
        { id: 207, pet: "Milo", breed: "Beagle", time: "09:00 AM", zone: "Guaimaral", status: "Pendiente" },
        { id: 208, pet: "Bella", breed: "Siberiano", time: "02:00 PM", zone: "Niza", status: "Pendiente" },
        { id: 209, pet: "Luna", breed: "Pug", time: "04:30 PM", zone: "La Ceiba", status: "Pendiente" }
    ],
    "Jueves": [
        { id: 210, pet: "Coco", breed: "Poodle", time: "08:00 AM", zone: "Centro", status: "Pendiente" },
        { id: 211, pet: "Toby", breed: "Criollo", time: "10:30 AM", zone: "San Eduardo", status: "Pendiente" }
    ],
    "Viernes": [
        { id: 212, pet: "Rocky", breed: "Golden", time: "07:30 AM", zone: "Prados del Este", status: "Pendiente" },
        { id: 213, pet: "Luna", breed: "Pug", time: "09:00 AM", zone: "La Ceiba", status: "Pendiente" },
        { id: 214, pet: "Zeus", breed: "Pastor Alemán", time: "04:00 PM", zone: "Quinta Vélez", status: "Pendiente" }
    ],
    "Sábado": [
        { id: 215, pet: "Rocky", breed: "Golden", time: "07:30 AM", zone: "Prados del Este", status: "Pendiente" },
        { id: 216, pet: "Luna", breed: "Pug", time: "09:00 AM", zone: "La Ceiba", status: "Pendiente" }
    ],
    "Domingo": [] // Descanso
};

// ── Cambiar día en el calendario semanal ──────────────────────────────────────
function cambiarDia(dia, elemento) {
    document.querySelectorAll('.day-pill').forEach(pill => pill.classList.remove('active'));
    elemento.classList.add('active');

    document.getElementById('selectedDayLabel').textContent = dia;

    const container = document.getElementById('agendaContainer');
    container.innerHTML = '';

    const perros = basePaseos[dia] || [];

    if (perros.length === 0) {
        container.innerHTML = `
            <div class="no-walks">
                <i class="fas fa-mug-hot"></i>
                <p>No tienes perritos programados para este día. ¡Día de descanso!</p>
            </div>`;
        return;
    }

    perros.forEach(p => {
        container.innerHTML += `
            <div class="agenda-item">
                <div class="agenda-time">${p.time}</div>
                <div class="agenda-info">
                    <span class="pet-name">${p.pet} <small>${p.breed}</small></span>
                    <span class="pet-zone"><i class="fas fa-map-marker-alt"></i> ${p.zone}</span>
                </div>
                <button class="btn-action-start" onclick="iniciarRuta(${p.id})">Iniciar</button>
            </div>
        `;
    });
}

// ── Iniciar ruta individual ───────────────────────────────────────────────────
function iniciarRuta(id) {
    alert(`Abriendo mapa de navegación de Paseo Feliz para el servicio de paseo #${id}`);
}

// ── Iniciar todos los paseos del día ─────────────────────────────────────────
function empezarTodosLosPaseos() {
    localStorage.setItem('paseoIniciado', 'true');
    window.location.href = 'mapa_paseador.php';
}

// ── Cargar Lunes por defecto ──────────────────────────────────────────────────
cambiarDia('Lunes', document.querySelector('.day-pill.active'));
