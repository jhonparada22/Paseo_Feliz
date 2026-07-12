// ══════════════════════════════════════════════════════════════
// DASHBOARD_ADMIN.JS — Inicio del admin conectado a la BD
// Combina obtener_dashboard_admin.php (nuevo, agregados) con
// obtener_pedidos_paseos.php y obtener_paseadores.php (ya existentes,
// reutilizados tal cual) para no duplicar consultas.
// ══════════════════════════════════════════════════════════════
// La tabla "Paseos Recientes" y la lista "Reportes Recientes" fueron
// reemplazadas por el Centro de Actividad (activity_center.js). Este archivo
// solo alimenta las stat cards, el donut, el calendario y la mini gráfica.
const PATHS = {
    dashboard:  '../../../model/obtener_dashboard_admin.php',
    paseadores: '../../../model/obtener_paseadores.php',
};

// ── Menú hamburguesa ──────────────────────────────────────────
const btnMenu     = document.getElementById('btn-menu');
const menuLatente = document.getElementById('menu-latente');
btnMenu.addEventListener('click', () => menuLatente.classList.toggle('show'));
window.addEventListener('click', e => {
    if (!btnMenu.contains(e.target) && !menuLatente.contains(e.target))
        menuLatente.classList.remove('show');
});

// ── Fecha de cabecera ─────────────────────────────────────────
document.getElementById('dateLabel').textContent = new Date().toLocaleDateString('es-CO', {
    day: 'numeric', month: 'long', year: 'numeric',
});

// ── Estado global ─────────────────────────────────────────────
let DASH = null;
let lineChart = null, donutChart = null;
let calMes = new Date(); // mes que se está viendo en el calendario

// ══════════════════════════════════════════════════════════════
// CARGA INICIAL
// ══════════════════════════════════════════════════════════════
async function cargarDashboard() {
    try {
        const [rDash, rPaseadores] = await Promise.all([
            fetch(PATHS.dashboard).then(r => r.json()),
            fetch(PATHS.paseadores).then(r => r.json()),
        ]);

        if (!rDash.success) throw new Error(rDash.message || 'Error al cargar el dashboard');
        DASH = rDash;

        renderStatCards(rDash.stats, rPaseadores.success ? rPaseadores.paseadores : []);
        renderLineChart(rDash.chart_linea);
        renderDonutChart(rDash.chart_donut);
        renderCalendario(rDash.calendario);
        renderWidgetEstado(rDash.stats.pedidos_sin_asignar);
    } catch (e) {
        console.error('Error cargando dashboard admin:', e);
    }
}

// ══════════════════════════════════════════════════════════════
// TARJETAS DE ESTADÍSTICAS
// ══════════════════════════════════════════════════════════════
function renderStatCards(stats, paseadores) {
    const cards = document.querySelectorAll('.stat-card');

    // 1. Paseos activos hoy
    setDelta(cards[0], stats.paseos_hoy, deltaAbsoluto(stats.paseos_hoy, stats.paseos_ayer, 'desde ayer'));

    // 2. Usuarios registrados
    const deltaUsuarios = stats.usuarios_nuevos_semana > 0
        ? { texto: `+${stats.usuarios_nuevos_semana} esta semana`, clase: 'up' }
        : { texto: 'Sin nuevos esta semana', clase: '' };
    setDelta(cards[1], stats.usuarios_total.toLocaleString('es-CO'), deltaUsuarios);

    // 3. Paseadores disponibles (estado === 'activo': dentro de horario y sin ruta en curso)
    const disponibles = paseadores.filter(p => p.estado === 'activo').length;
    setDelta(cards[2], disponibles, { texto: `de ${paseadores.length} registrados`, clase: '' });

    // 4. Ingresos totales (delta % semana vs semana anterior)
    const montoFmt = '$' + Math.round(stats.ingresos_totales).toLocaleString('es-CO');
    let deltaIngresos;
    if (stats.ingresos_semana_anterior > 0) {
        const pct = Math.round((stats.ingresos_semana - stats.ingresos_semana_anterior) / stats.ingresos_semana_anterior * 100);
        deltaIngresos = { texto: `${pct >= 0 ? '+' : ''}${pct}% esta semana`, clase: pct >= 0 ? 'up' : 'down' };
    } else if (stats.ingresos_semana > 0) {
        deltaIngresos = { texto: 'Primeros ingresos esta semana', clase: 'up' };
    } else {
        deltaIngresos = { texto: 'Sin ingresos esta semana', clase: '' };
    }
    setDelta(cards[3], montoFmt, deltaIngresos);
}

function deltaAbsoluto(hoy, ayer, sufijo) {
    const dif = hoy - ayer;
    if (dif > 0) return { texto: `+${dif} ${sufijo}`, clase: 'up' };
    if (dif < 0) return { texto: `${dif} ${sufijo}`, clase: 'down' };
    return { texto: `Igual que ${sufijo.replace('desde ', '')}`, clase: '' };
}

function setDelta(card, valor, delta) {
    if (!card) return;
    card.querySelector('.s-value').textContent = valor;
    const el = card.querySelector('.s-delta');
    el.className = 's-delta' + (delta.clase ? ' ' + delta.clase : '');
    const icono = delta.clase === 'up' ? '<i class="fas fa-arrow-up"></i> '
                : delta.clase === 'down' ? '<i class="fas fa-arrow-down"></i> ' : '';
    el.innerHTML = icono + delta.texto;
}

// ══════════════════════════════════════════════════════════════
// GRÁFICA DE LÍNEA (con selector de rango funcional)
// ══════════════════════════════════════════════════════════════
function renderLineChart(chartLinea) {
    const ctx = document.getElementById('lineChart');
    const serie = chartLinea.dias7;

    lineChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: serie.labels,
            datasets: [{
                label: 'Paseos completados',
                data: serie.data,
                borderColor: '#3E72A6',
                backgroundColor: 'rgba(62,114,166,.12)',
                tension: .35,
                fill: true,
                pointRadius: 3,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
        },
    });

    document.querySelector('.chart-select').addEventListener('change', function () {
        const clave = this.selectedIndex === 0 ? 'dias7' : this.selectedIndex === 1 ? 'dias30' : 'mes';
        const s = chartLinea[clave];
        lineChart.data.labels = s.labels;
        lineChart.data.datasets[0].data = s.data;
        lineChart.update();
    });
}

// ══════════════════════════════════════════════════════════════
// DONUT (Paseos por Estado)
// ══════════════════════════════════════════════════════════════
function renderDonutChart(donut) {
    const total = donut.completados + donut.en_proceso + donut.programados + donut.cancelados;
    const pct = n => total ? Math.round(n / total * 100) : 0;

    const ctx = document.getElementById('donutChart');
    donutChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Completados', 'En Proceso', 'Programados', 'Cancelados'],
            datasets: [{
                data: [donut.completados, donut.en_proceso, donut.programados, donut.cancelados],
                backgroundColor: ['#22c55e', '#3E72A6', '#f97316', '#ef4444'],
                borderWidth: 0,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            plugins: { legend: { display: false } },
        },
    });

    document.querySelector('.donut-center .dp').textContent = total ? '100%' : '0%';
    const legendItems = document.querySelectorAll('.legend-item .l-pct');
    const valores = [pct(donut.completados), pct(donut.en_proceso), pct(donut.programados), pct(donut.cancelados)];
    legendItems.forEach((el, i) => { el.textContent = valores[i] + '%'; });
}

// ══════════════════════════════════════════════════════════════
// CALENDARIO (navegación real sobre el mapa ya cargado)
// ══════════════════════════════════════════════════════════════
const DIAS_SEMANA = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

function renderCalendario(mapaEventos) {
    pintarMes(mapaEventos);
    document.getElementById('calPrev').addEventListener('click', () => {
        calMes.setMonth(calMes.getMonth() - 1);
        pintarMes(mapaEventos);
    });
    document.getElementById('calNext').addEventListener('click', () => {
        calMes.setMonth(calMes.getMonth() + 1);
        pintarMes(mapaEventos);
    });
}

function pintarMes(mapaEventos) {
    const anio = calMes.getFullYear();
    const mes  = calMes.getMonth(); // 0-indexado
    document.getElementById('calMonth').textContent =
        calMes.toLocaleDateString('es-CO', { month: 'long', year: 'numeric' });

    const grid = document.getElementById('calGrid');
    grid.innerHTML = '';
    DIAS_SEMANA.forEach(d => {
        const el = document.createElement('div');
        el.className = 'cal-dname';
        el.textContent = d;
        grid.appendChild(el);
    });

    const primerDia = new Date(anio, mes, 1);
    // Lunes=0 ... Domingo=6 (getDay() da 0=domingo)
    const offset = (primerDia.getDay() + 6) % 7;
    const diasEnMes = new Date(anio, mes + 1, 0).getDate();
    const diasMesAnterior = new Date(anio, mes, 0).getDate();

    const hoyStr = new Date().toISOString().slice(0, 10);

    // Días del mes anterior (relleno)
    for (let i = offset; i > 0; i--) {
        agregarCelda(grid, diasMesAnterior - i + 1, 'other');
    }
    // Días del mes actual
    for (let d = 1; d <= diasEnMes; d++) {
        const fecha = `${anio}-${String(mes + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        let clases = '';
        if (fecha === hoyStr) clases += ' today';
        if (mapaEventos[fecha]) clases += ' has-event';
        agregarCelda(grid, d, clases.trim(), mapaEventos[fecha] || 0);
    }
    // Relleno final para completar semanas de 7
    const totalCeldas = offset + diasEnMes;
    const faltan = (7 - (totalCeldas % 7)) % 7;
    for (let d = 1; d <= faltan; d++) {
        agregarCelda(grid, d, 'other');
    }
}

function agregarCelda(grid, numero, clases, nEventos) {
    const el = document.createElement('div');
    el.className = 'cal-day' + (clases ? ' ' + clases : '');
    el.textContent = numero;
    if (nEventos) el.title = `${nEventos} paseo(s) programado(s)`;
    grid.appendChild(el);
}

// ══════════════════════════════════════════════════════════════
// REPORTES RECIENTES (fecha real + link a la sección correspondiente)
// ══════════════════════════════════════════════════════════════
function renderReportes(reportes) {
    const idsFecha = { paseo: 'r-date-paseo', ingreso: 'r-date-ingreso', usuario: 'r-date-usuario', paseador: 'r-date-paseador' };
    reportes.forEach(r => {
        const spanId = idsFecha[r.tipo];
        const span = spanId && document.getElementById(spanId);
        if (span) span.textContent = r.fecha ? fmtFechaCorta(r.fecha) : 'Sin datos aún';

        const link = document.querySelector(`.report-item[data-tipo="${r.tipo}"]`);
        if (link && r.href) link.setAttribute('href', r.href);
    });
}

function fmtFechaCorta(str) {
    const d = new Date(str.replace(' ', 'T'));
    if (isNaN(d)) return str;
    return d.toLocaleDateString('es-CO', { day: '2-digit', month: 'short', year: 'numeric' });
}

// ══════════════════════════════════════════════════════════════
// TABLA "PASEOS RECIENTES" (mismo criterio de badges que paseos_admin.js)
// ══════════════════════════════════════════════════════════════
function estadoInfo(p) {
    if (p.estado === 'cancelado')  return { lbl: 'Cancelado',          cls: 'b-cancelado'  };
    if (p.asignacion)              return { lbl: 'En cronograma',      cls: 'b-completado' };
    if (p.estado === 'listo_para_asignar' || p.estado === 'pagado')
                                    return { lbl: 'Listo para asignar', cls: 'b-programado' };
    return                                { lbl: 'Sin pagar',          cls: 'b-proceso'    };
}

function renderTablaPaseos(pedidos) {
    const tbody = document.getElementById('tableBody');
    const filas = pedidos.slice(0, 8);

    if (!filas.length) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--text-light);padding:20px">Aún no hay paseos registrados.</td></tr>';
        return;
    }

    tbody.innerHTML = filas.map(p => {
        const st = estadoInfo(p);
        const fecha = new Date(p.fecha_compra.replace(' ', 'T')).toLocaleDateString('es-CO', { day: '2-digit', month: 'short' });
        return `
            <tr>
                <td class="id-cell">#${p.id_pedido}</td>
                <td>${esc(p.cliente)}</td>
                <td>${esc(p.mascota)}</td>
                <td>${p.asignacion ? esc(p.asignacion.paseador) : '—'}</td>
                <td>${fecha}</td>
                <td><span class="badge-st ${st.cls}">${st.lbl}</span></td>
                <td><a href="paseos_admin.php" title="Ver en Paseos"><i class="fas fa-eye"></i></a></td>
            </tr>`;
    }).join('');
}

function esc(t) {
    return String(t == null ? '' : t)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

// ══════════════════════════════════════════════════════════════
// WIDGET DE ESTADO (alerta real si hay pedidos sin asignar)
// ══════════════════════════════════════════════════════════════
function renderWidgetEstado(pedidosSinAsignar) {
    const widget = document.querySelector('.status-widget');
    if (!widget) return;

    if (pedidosSinAsignar > 0) {
        widget.style.background = 'linear-gradient(135deg, #f97316, #c2410c)';
        widget.querySelector('.sw-title').textContent = '⚠️ Necesitan atención';
        widget.querySelector('.sw-sub').textContent =
            `Tienes ${pedidosSinAsignar} pedido${pedidosSinAsignar > 1 ? 's' : ''} pagado${pedidosSinAsignar > 1 ? 's' : ''} sin asignar a un paseador.`;
        const btn = widget.querySelector('.sw-btn');
        btn.textContent = 'Ir a asignar ahora';
        btn.onclick = () => window.location.href = 'paseos_admin.php';
    }
    // Si no hay pendientes, se conserva el mensaje positivo original del HTML.
}

// ── INIT ──────────────────────────────────────────────────────
cargarDashboard();
