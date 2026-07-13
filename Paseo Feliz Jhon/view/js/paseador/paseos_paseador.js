// ══════════════════════════════════════════════════════════════
// PASEOS_PASEADOR.JS — "Mis Paseos" conectado a la BD
// Tab "Hoy": obtener_cronograma.php (datos del pedido: mascota, dueño,
//   horario, zona, notas) + obtener_estado_paseos_hoy.php (estado real
//   de la ruta de hoy: pendiente/en_curso/completado por cliente).
// Tab "Historial": obtener_historial_paseador.php (rutas finalizadas).
// Acción "Finalizar Paseo": marcar_parada.php sobre la parada de
// entrega del cliente; si era la última pendiente, además cierra la
// ruta con finalizar_paseo.php.
// ══════════════════════════════════════════════════════════════
const API = '../../../model/';

// ── Menú hamburguesa ──────────────────────────────────────────
const btnMenu = document.getElementById('btn-menu');
const menuLatente = document.getElementById('menu-latente');
btnMenu.addEventListener('click', () => menuLatente.classList.toggle('show'));
window.addEventListener('click', (e) => {
    if (!btnMenu.contains(e.target) && !menuLatente.contains(e.target)) {
        menuLatente.classList.remove('show');
    }
});

// ── Pestañas ──────────────────────────────────────────────────
function switchTab(event, tabId) {
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    event.currentTarget.classList.add('active');

    if (tabId === 'tab-historial' && !historialCargado) cargarHistorial();
}

const HOY = new Date().getDay() === 0 ? 7 : new Date().getDay(); // 1=lun..7=dom
let historialCargado = false;

// ── Tab "Paseos de Hoy" ───────────────────────────────────────
async function cargarPaseosHoy() {
    const grid = document.getElementById('walksGridHoy');
    try {
        const [rCrono, rEstado] = await Promise.all([
            fetch(API + 'obtener_cronograma.php').then(r => r.json()),
            fetch(API + 'obtener_estado_paseos_hoy.php').then(r => r.json()),
        ]);

        if (!rCrono.success) throw new Error(rCrono.message || 'Error al cargar el cronograma');

        const pedidosHoy = rCrono.cronograma[HOY] || [];
        const ruta = rEstado.success ? rEstado.ruta : null;
        const estados = rEstado.success ? rEstado.clientes : [];

        document.getElementById('sinRutaBanner').style.display = ruta ? 'none' : 'flex';

        if (!pedidosHoy.length) {
            grid.innerHTML = `
                <div class="no-walks-msg">
                    <i class="fas fa-mug-hot"></i><br>
                    No tienes perritos programados para hoy. ¡Disfruta tu día!
                </div>`;
            return;
        }

        // Cruzar cronograma (datos del pedido) con el estado real de la ruta
        const mapaEstados = {};
        estados.forEach(e => { mapaEstados[e.id_usuario + '_' + e.id_mascota] = e; });

        grid.innerHTML = '';
        pedidosHoy.forEach(p => {
            const info = mapaEstados[p.id_usuario + '_' + p.id_mascota] || null;
            const estado = info ? info.estado : 'pendiente';
            grid.appendChild(crearTarjetaPaseo(p, estado, info, !!ruta));
        });
    } catch (e) {
        grid.innerHTML = `
            <div class="no-walks-msg">
                <i class="fas fa-plug-circle-xmark"></i><br>
                No se pudo cargar tu agenda de hoy. Verifica tu conexión e intenta de nuevo.
            </div>`;
    }
}

function crearTarjetaPaseo(p, estado, info, tieneRuta) {
    const div = document.createElement('div');

    let badge, acciones;
    if (estado === 'completado') {
        div.className = 'walk-card done';
        badge = '<div class="card-status-badge badge-status-done"><i class="fas fa-check"></i> Completado</div>';
        acciones = '';
    } else if (estado === 'en_curso') {
        div.className = 'walk-card live';
        badge = '<div class="card-status-badge badge-live"><span class="pulse-dot"></span> En Curso</div>';
        acciones = `
            <a href="mapa_paseador.php" class="btn-card primary"><i class="fas fa-location-arrow"></i> Ver Mapa / GPS</a>
            <button type="button" class="btn-card success" data-accion="finalizar" data-parada="${info.id_parada_entrega}">Finalizar Paseo</button>`;
    } else {
        div.className = 'walk-card';
        badge = '<div class="card-status-badge badge-pending">Pendiente</div>';
        acciones = tieneRuta
            ? `<a href="Chat_paseador.php" class="btn-card secondary"><i class="far fa-comment-alt"></i> Chat Dueño</a>
               <a href="mapa_paseador.php" class="btn-card primary"><i class="fas fa-location-arrow"></i> Ver Mapa / GPS</a>`
            : `<a href="Chat_paseador.php" class="btn-card secondary"><i class="far fa-comment-alt"></i> Chat Dueño</a>
               <a href="index_paseador.php" class="btn-card primary"><i class="fas fa-play"></i> Ir a Inicio</a>`;
    }

    const notas = [];
    if (['reactivo', 'no_sociable'].includes(p.comportamiento)) {
        const texto = p.comportamiento === 'reactivo' ? 'Puede reaccionar a otros perros.' : 'Prefiere paseos individuales.';
        notas.push(texto);
    }
    if (p.observaciones) notas.push(p.observaciones);

    div.innerHTML = `
        ${badge}
        <div class="walk-card-header">
            <div class="dog-avatar">🐾</div>
            <div class="dog-details">
                <h3>${esc(p.mascota)}</h3>
                <p>${esc(p.plan || '')}</p>
            </div>
        </div>
        <div class="walk-card-body">
            <div class="info-row"><i class="fas fa-user"></i> <span><strong>Dueño:</strong> ${esc(p.cliente)}</span></div>
            <div class="info-row"><i class="fas fa-clock"></i> <span><strong>Horario:</strong> ${esc(p.franja_horaria || '—')}</span></div>
            <div class="info-row"><i class="fas fa-location-dot"></i> <span><strong>Zona:</strong> ${esc(p.barrio || p.direccion)}</span></div>
            ${notas.length ? `<div class="info-row alert-notes"><i class="fas fa-comment-medical"></i> <span><strong>Nota:</strong> ${esc(notas.join(' '))}</span></div>` : ''}
        </div>
        <div class="walk-card-actions">${acciones}</div>
    `;

    const btnFinalizar = div.querySelector('[data-accion="finalizar"]');
    if (btnFinalizar) {
        btnFinalizar.addEventListener('click', () => finalizarParadaCliente(btnFinalizar));
    }

    return div;
}

// ── Acción: Finalizar Paseo (marca la entrega de ese cliente) ─
async function finalizarParadaCliente(btn) {
    if (!confirm('¿Confirmas que ya entregaste la mascota a su dueño?')) return;

    const idParada = parseInt(btn.getAttribute('data-parada'), 10);
    if (!idParada) return;

    btn.disabled = true;
    const original = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

    try {
        const r = await fetch(API + 'marcar_parada.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_parada: idParada, accion: 'completar' }),
        });
        const data = await r.json();
        if (!data.success) {
            alert('⚠ ' + (data.message || 'No se pudo finalizar el paseo.'));
            btn.disabled = false;
            btn.innerHTML = original;
            return;
        }

        // Si ya no quedan clientes pendientes/en curso, cerrar la ruta del día
        const rEstado = await fetch(API + 'obtener_estado_paseos_hoy.php').then(x => x.json());
        if (rEstado.success && rEstado.ruta) {
            const quedan = rEstado.clientes.some(c => c.estado !== 'completado');
            if (!quedan) {
                await fetch(API + 'finalizar_paseo.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id_ruta: rEstado.ruta.id_ruta }),
                }).catch(() => {});
            }
        }

        cargarPaseosHoy();
    } catch (e) {
        alert('⚠ Error de conexión. Inténtalo de nuevo.');
        btn.disabled = false;
        btn.innerHTML = original;
    }
}

// ── Tab "Historial Completo" ──────────────────────────────────
async function cargarHistorial() {
    const tbody = document.getElementById('historialBody');
    try {
        const r = await fetch(API + 'obtener_historial_paseador.php');
        const data = await r.json();
        if (!data.success) throw new Error(data.message || 'Error');
        historialCargado = true;

        if (!data.historial.length) {
            tbody.innerHTML = `<tr><td colspan="6" class="no-walks-msg">Aún no tienes paseos completados.</td></tr>`;
            return;
        }

        tbody.innerHTML = data.historial.map(h => {
            const duracion = h.duracion_min !== null
                ? h.duracion_min + ' min'
                : h.duracion_estimada + ' min (estimado)';
            return `
                <tr>
                    <td>${fmtFecha(h.fecha)}</td>
                    <td><strong>${esc(h.mascota)}</strong></td>
                    <td>${esc(h.dueno)}</td>
                    <td>${esc(h.direccion)}</td>
                    <td>${esc(duracion)}</td>
                    <td><span class="badge-status-done">Completado</span></td>
                </tr>`;
        }).join('');
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="6" class="no-walks-msg">No se pudo cargar el historial. Intenta de nuevo.</td></tr>`;
    }
}

// ── Helpers ───────────────────────────────────────────────────
function esc(t) {
    return String(t == null ? '' : t)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}
function fmtFecha(str) {
    const d = new Date(str + 'T00:00:00');
    if (isNaN(d)) return str;
    return d.toLocaleDateString('es-CO', { day: '2-digit', month: 'short', year: 'numeric' });
}

// ── INIT ──────────────────────────────────────────────────────
cargarPaseosHoy();
