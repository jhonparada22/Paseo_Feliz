// ══════════════════════════════════════════════════════════════
// PASEADOR.JS — Dashboard del paseador conectado a la BD
// Lee su cronograma semanal real (model/obtener_cronograma.php) y
// "Empezar paseos" genera la ruta del día (model/iniciar_dia_paseador.php)
// y salta al mapa (mapa_paseador.js auto-inicia con localStorage).
// ══════════════════════════════════════════════════════════════
const API = '../../../model/';

// ── Menú Hamburguesa ──────────────────────────────────────────
const btnMenu = document.getElementById('btn-menu');
const menuLatente = document.getElementById('menu-latente');
btnMenu.addEventListener('click', () => menuLatente.classList.toggle('show'));
window.addEventListener('click', (e) => {
    if (!btnMenu.contains(e.target) && !menuLatente.contains(e.target)) {
        menuLatente.classList.remove('show');
    }
});

// ── Fecha de cabecera ─────────────────────────────────────────
document.getElementById('dateLabel').textContent = new Date().toLocaleDateString('es-ES', {
    day: 'numeric', month: 'long', year: 'numeric',
});

// ── Estado ────────────────────────────────────────────────────
const DIAS = {
    1: { corto: 'Lun', nombre: 'Lunes' },    2: { corto: 'Mar', nombre: 'Martes' },
    3: { corto: 'Mié', nombre: 'Miércoles' },4: { corto: 'Jue', nombre: 'Jueves' },
    5: { corto: 'Vie', nombre: 'Viernes' },  6: { corto: 'Sáb', nombre: 'Sábado' },
    7: { corto: 'Dom', nombre: 'Domingo' },
};
const HOY = new Date().getDay() === 0 ? 7 : new Date().getDay(); // 1=lun..7=dom

let CRONO = { 1: [], 2: [], 3: [], 4: [], 5: [], 6: [], 7: [] };
let diaSel = HOY;

// ── Cargar cronograma real ────────────────────────────────────
async function cargarCronograma() {
    try {
        const r = await fetch(API + 'obtener_cronograma.php');
        const data = await r.json();
        if (!data.success) throw new Error(data.message || 'Error');
        CRONO = data.cronograma;

        // Stats reales
        const idsUnicos = new Set();
        for (let d = 1; d <= 7; d++) (CRONO[d] || []).forEach(p => idsUnicos.add(p.id_pedido));
        document.getElementById('countHoy').textContent    = (CRONO[HOY] || []).length;
        document.getElementById('countPerros').textContent = idsUnicos.size;
        document.getElementById('countRating').innerHTML   =
            `${(data.paseador.puntuacion || 0).toFixed(1)} <span class="star-small">★</span>`;

        renderPills();
        cambiarDia(diaSel);
        renderNotas();
    } catch (e) {
        document.getElementById('agendaContainer').innerHTML = `
            <div class="no-walks"><i class="fas fa-plug-circle-xmark"></i>
            <p>No se pudo cargar tu cronograma. Verifica tu conexión e intenta de nuevo.</p></div>`;
    }
}

// ── Pills de la semana con contadores reales ──────────────────
function renderPills() {
    const grid = document.getElementById('calendarGrid');
    grid.innerHTML = '';
    for (let d = 1; d <= 7; d++) {
        const n = (CRONO[d] || []).length;
        const pill = document.createElement('div');
        pill.className = 'day-pill' + (d === diaSel ? ' active' : '') + (n === 0 && d === 7 ? ' weekend' : '');
        pill.innerHTML = `
            <span class="day-name">${DIAS[d].corto}</span>
            <span class="day-num">${d}</span>
            <span class="badge-count">${n ? n + ' Perro' + (n > 1 ? 's' : '') : (d === 7 ? 'Descanso' : 'Libre')}</span>`;
        pill.addEventListener('click', () => cambiarDia(d));
        grid.appendChild(pill);
    }
}

// ── Cambiar día seleccionado ──────────────────────────────────
function cambiarDia(d) {
    diaSel = d;
    renderPills();
    document.getElementById('selectedDayLabel').textContent = DIAS[d].nombre;

    const container = document.getElementById('agendaContainer');
    container.innerHTML = '';
    const perros = CRONO[d] || [];

    // "Empezar paseos" solo tiene sentido para HOY y si hay perros
    const btnEmpezar = document.getElementById('btnEmpezarPaseos');
    btnEmpezar.style.display = (d === HOY && perros.length) ? '' : 'none';

    if (!perros.length) {
        container.innerHTML = `
            <div class="no-walks">
                <i class="fas fa-mug-hot"></i>
                <p>No tienes perritos programados para este día. ${d === 7 ? '¡Día de descanso!' : ''}</p>
            </div>`;
        return;
    }

    perros.forEach(p => {
        const item = document.createElement('div');
        item.className = 'agenda-item';
        item.innerHTML = `
            <div class="agenda-time">${p.franja_horaria ? p.franja_horaria.split('–')[0].trim() : '—'}</div>
            <div class="agenda-info">
                <span class="pet-name">${p.mascota} <small>${p.cliente}</small></span>
                <span class="pet-zone"><i class="fas fa-map-marker-alt"></i> ${p.barrio || p.direccion}</span>
            </div>
            ${d === HOY ? `<button class="btn-action-start" onclick="empezarTodosLosPaseos()">Iniciar</button>` : ''}
        `;
        container.appendChild(item);
    });
}

// ── Notas de comportamiento reales ────────────────────────────
function renderNotas() {
    const cont = document.getElementById('alertsContainer');
    cont.innerHTML = '';

    const vistos = new Set();
    const notas = [];
    for (let d = 1; d <= 7; d++) {
        (CRONO[d] || []).forEach(p => {
            if (vistos.has(p.id_pedido)) return;
            vistos.add(p.id_pedido);
            const esRiesgo = ['reactivo', 'no_sociable'].includes(p.comportamiento);
            if (esRiesgo || p.observaciones) {
                notas.push({ mascota: p.mascota, cliente: p.cliente, riesgo: esRiesgo, comp: p.comportamiento, obs: p.observaciones });
            }
        });
    }

    if (!notas.length) {
        cont.innerHTML = `<div class="alert-item"><i class="fas fa-circle-check" style="color:#22c55e"></i>
            <div>Sin notas especiales esta semana. ¡Todos los perritos son tranquilos! 🐾</div></div>`;
        return;
    }

    const compTxt = { reactivo: 'Puede reaccionar a otros perros', no_sociable: 'Prefiere paseos individuales', timido: 'Necesita tiempo para adaptarse', sociable: '' };
    notas.forEach(n => {
        const div = document.createElement('div');
        div.className = 'alert-item ' + (n.riesgo ? 'hazard' : 'medical');
        div.innerHTML = `
            <i class="fas ${n.riesgo ? 'fa-shield-dog' : 'fa-capsules'}"></i>
            <div><strong>${n.mascota} (${n.cliente}):</strong>
                ${n.riesgo ? compTxt[n.comp] + '. ' : ''}${n.obs || ''}</div>`;
        cont.appendChild(div);
    });
}

// ── Empezar los paseos de hoy: crear la ruta y saltar al mapa ─
let iniciando = false;
async function empezarTodosLosPaseos() {
    if (iniciando) return;
    iniciando = true;
    const btn = document.getElementById('btnEmpezarPaseos');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creando ruta...';

    try {
        const r = await fetch(API + 'iniciar_dia_paseador.php', { method: 'POST' });
        const data = await r.json();
        if (!data.success) {
            alert('⚠ ' + (data.message || 'No se pudo crear la ruta del día.'));
            return;
        }
        // La ruta quedó lista: el mapa la carga (modo=hoy) y auto-inicia el GPS
        localStorage.setItem('paseoIniciado', 'true');
        window.location.href = 'mapa_paseador.php';
    } catch (e) {
        alert('⚠ Error de conexión al crear la ruta. Inténtalo de nuevo.');
    } finally {
        iniciando = false;
        btn.disabled = false;
        btn.innerHTML = original;
    }
}

// ── INIT ──────────────────────────────────────────────────────
cargarCronograma();
