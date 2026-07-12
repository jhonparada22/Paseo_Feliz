// ══════════════════════════════════════════════════════════════
// ACTIVITY_CENTER.JS — Centro de Actividad del dashboard admin (Fase 17)
// Consume model/actividad_feed.php (único endpoint) y model/
// resolver_cancelacion.php. Timeline con pestañas por servicio, filtros,
// buscador, poll incremental cada 30s (solo inserta lo nuevo) y paginación.
// ══════════════════════════════════════════════════════════════
(function () {
    'use strict';

    const API = '../../../model/';
    const POLL_MS = 30000;

    const S = {
        servicio: 'paseos',
        filtro: 'todos',
        buscar: '',
        items: [],
        desdeId: 0,        // mayor id mostrado en la pestaña actual (poll)
        antesId: 0,        // id más antiguo mostrado (paginación)
        antesFecha: '',
        cargando: false,
        hayMas: false,
    };
    let pollTimer = null;
    let buscarTimer = null;

    // ── Utilidades ────────────────────────────────────────────────
    function esc(t) {
        return String(t == null ? '' : t)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    function horaRelativa(str) {
        if (!str) return '';
        const d = new Date(str.replace(' ', 'T'));
        if (isNaN(d)) return str;
        const seg = Math.floor((Date.now() - d.getTime()) / 1000);
        if (seg < 60) return 'ahora';
        if (seg < 3600) return 'hace ' + Math.floor(seg / 60) + ' min';
        if (seg < 86400) return 'hace ' + Math.floor(seg / 3600) + ' h';
        return d.toLocaleDateString('es-CO', { day: '2-digit', month: 'short' });
    }
    const $ = s => document.querySelector(s);

    // ── Render de una fila ────────────────────────────────────────
    function itemHTML(it, esNuevo) {
        const chips = `
            <span class="ac-chip st-${it.estado}">${it.estado.replace('_', ' ')}</span>
            <span class="ac-chip pr-${it.prioridad}">${it.prioridad}</span>`;

        const meta = [];
        if (it.cliente)  meta.push(`<span><b>Cliente:</b> ${esc(it.cliente)}</span>`);
        if (it.mascota)  meta.push(`<span><b>Mascota:</b> ${esc(it.mascota)}</span>`);
        if (it.paseador) meta.push(`<span><b>Paseador:</b> ${esc(it.paseador)}</span>`);
        if (it.id_pedido) meta.push(`<span><b>Pedido:</b> #${it.id_pedido}</span>`);

        return `
        <div class="ac-item${esNuevo ? ' ac-nuevo' : ''}" data-id="${it.id}">
            <div class="ac-ic" style="background:${it.color}"><i class="fas ${it.icono}"></i></div>
            <div class="ac-body">
                <div class="ac-title">${esc(it.titulo)}</div>
                ${it.descripcion ? `<div class="ac-desc">${esc(it.descripcion)}</div>` : ''}
                <div class="ac-meta">${meta.join('')}</div>
                ${accionesHTML(it)}
            </div>
            <div class="ac-right-col">
                <span class="ac-hora">${horaRelativa(it.creado_en)}</span>
                <div class="ac-chips">${chips}</div>
                ${(it.tipo === 'cancelacion_solicitada' && it.resuelto === 0)
                    ? ''
                    : `<button class="ac-visto" data-visto="${it.id}" title="Ocultar este reporte"><i class="fas fa-check"></i> Visto</button>`}
            </div>
        </div>`;
    }

    function accionesHTML(it) {
        if (!it.acciones || !it.acciones.length) return '';
        const b = [];
        it.acciones.forEach(a => {
            switch (a) {
                case 'ver_motivo':  b.push(btn(it.id, 'ver_motivo', 'fa-eye', 'Ver motivo')); break;
                case 'aprobar':     b.push(btn(it.id, 'aprobar', 'fa-check', 'Aprobar', 'ok')); break;
                case 'rechazar':    b.push(btn(it.id, 'rechazar', 'fa-xmark', 'Rechazar', 'no')); break;
                case 'ver_paseo':   b.push(link('paseos_admin.php?pedido=' + (it.id_pedido || ''), 'fa-eye', 'Ver paseo')); break;
                case 'ver_cliente': b.push(link('usuarios_admin.php', 'fa-user', 'Ver cliente')); break;
                case 'ver_mapa':    b.push(link('mapa_admin.php', 'fa-map-location-dot', 'Ver mapa')); break;
                case 'abrir_chat':  b.push(link('Chat_admin.php', 'fa-comment-alt', 'Chat')); break;
                case 'ver_comprobante': b.push(link('pagos_admin.php', 'fa-receipt', 'Comprobante')); break;
            }
        });
        return b.length ? `<div class="ac-actions">${b.join('')}</div>` : '';
    }
    const btn = (id, acc, ic, txt, cls) =>
        `<button class="ac-btn ${cls || ''}" data-acc="${acc}" data-id="${id}"><i class="fas ${ic}"></i> ${txt}</button>`;
    const link = (href, ic, txt) =>
        `<a class="ac-btn" href="${href}"><i class="fas ${ic}"></i> ${txt}</a>`;

    // ── Pintado del timeline ──────────────────────────────────────
    function pintar() {
        const tl = $('#ac-timeline');
        if (!S.items.length) {
            tl.innerHTML = `<div class="ac-vacio"><i class="fas fa-inbox" style="font-size:1.6rem;opacity:.4"></i><br>Sin actividad para este filtro.</div>`;
            $('#ac-mas').style.display = 'none';
            return;
        }
        tl.innerHTML = S.items.map(it => itemHTML(it, false)).join('');
        $('#ac-mas').style.display = S.hayMas ? 'block' : 'none';
    }

    // ── Cargas ────────────────────────────────────────────────────
    function qs(extra) {
        const p = new URLSearchParams(Object.assign({
            servicio: S.servicio, filtro: S.filtro, buscar: S.buscar,
        }, extra));
        return API + 'actividad_feed.php?' + p.toString();
    }

    function cargar(reset) {
        if (S.cargando) return;
        S.cargando = true;
        fetch(qs({}))
            .then(r => r.json())
            .then(d => {
                if (!d.success) return;
                S.items = d.items;
                S.hayMas = d.hay_mas;
                S.desdeId = d.items.length ? Math.max(...d.items.map(i => i.id)) : 0;
                if (d.items.length) {
                    const ult = d.items[d.items.length - 1];
                    S.antesId = ult.id; S.antesFecha = ult.creado_en;
                }
                pintar();
                actualizarContadores(d.contadores);
            })
            .catch(() => {})
            .finally(() => { S.cargando = false; });
    }

    function cargarMas() {
        if (S.cargando || !S.hayMas) return;
        S.cargando = true;
        fetch(qs({ antes_id: S.antesId, antes_fecha: S.antesFecha }))
            .then(r => r.json())
            .then(d => {
                if (!d.success) return;
                S.items = S.items.concat(d.items);
                S.hayMas = d.hay_mas;
                if (d.items.length) {
                    const ult = d.items[d.items.length - 1];
                    S.antesId = ult.id; S.antesFecha = ult.creado_en;
                }
                pintar();
            })
            .catch(() => {})
            .finally(() => { S.cargando = false; });
    }

    function poll() {
        fetch(qs({ desde_id: S.desdeId }))
            .then(r => r.json())
            .then(d => {
                if (!d.success) return;
                actualizarContadores(d.contadores);
                cargarAtencion();
                if (!d.items.length) return;
                // Nuevos: se anteponen con animación
                S.items = d.items.concat(S.items);
                S.desdeId = Math.max(S.desdeId, ...d.items.map(i => i.id));
                const tl = $('#ac-timeline');
                const nuevoHTML = d.items.map(it => itemHTML(it, true)).join('');
                tl.insertAdjacentHTML('afterbegin', nuevoHTML);
                mostrarToast(d.items.length);
            })
            .catch(() => {});
    }

    // ── Contadores + panel de atención ────────────────────────────
    function actualizarContadores(c) {
        if (!c) return;
        ['paseos', 'adiestramiento', 'hospedaje'].forEach(s => {
            const el = document.querySelector(`.ac-tab[data-serv="${s}"] .ac-count`);
            if (el) el.textContent = c[s] ?? 0;
        });
        const badge = $('#ac-at-badge');
        if (badge) {
            badge.textContent = c.necesitan_atencion ?? 0;
            badge.style.display = (c.necesitan_atencion > 0) ? '' : 'none';
        }
    }

    function cargarAtencion() {
        fetch(API + 'actividad_feed.php?filtro=pendientes&limit=6')
            .then(r => r.json())
            .then(d => {
                if (!d.success) return;
                const cont = $('#ac-at-list');
                if (!cont) return;
                if (!d.items.length) {
                    cont.innerHTML = `<div class="ac-at-vacio">Todo al día. Nada pendiente. 🎉</div>`;
                    return;
                }
                cont.innerHTML = d.items.map(it => `
                    <div class="ac-at-item" data-id="${it.id}" data-ref="${it.id_referencia || 0}" data-tipo="${it.tipo}">
                        <i class="fas ${it.icono}"></i>
                        <div style="flex:1;min-width:0">
                            <div class="t">${esc(it.titulo)}</div>
                            <div class="s">${esc(it.mascota || '')} · ${horaRelativa(it.creado_en)}</div>
                        </div>
                        <i class="fas fa-chevron-right" style="color:#cbd5e1"></i>
                    </div>`).join('');
            })
            .catch(() => {});
    }

    // ── Toast ─────────────────────────────────────────────────────
    let toastTimer = null;
    function mostrarToast(n) {
        let t = $('#ac-toast');
        if (!t) {
            t = document.createElement('div');
            t.id = 'ac-toast'; t.className = 'ac-toast';
            document.body.appendChild(t);
        }
        t.innerHTML = `<i class="fas fa-bell"></i> ${n} ${n === 1 ? 'evento nuevo' : 'eventos nuevos'}`;
        t.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => t.classList.remove('show'), 3200);
    }

    // ── Resolución de cancelaciones ───────────────────────────────
    let resolverCtx = null;
    function abrirResolver(idSolicitud, accion) {
        resolverCtx = { idSolicitud, accion };
        $('#acm-titulo').textContent = accion === 'aprobar' ? 'Aprobar cancelación' : 'Rechazar solicitud';
        $('#acm-sub').textContent = accion === 'aprobar'
            ? 'El paseo se cancelará de verdad y se notificará al cliente. Esta acción no se puede deshacer.'
            : 'El paseo continúa con normalidad y se avisará al paseador.';
        $('#acm-nota').value = '';
        const ok = $('#acm-confirmar');
        ok.className = accion === 'aprobar' ? 'acm-ok' : 'acm-no';
        ok.textContent = accion === 'aprobar' ? 'Sí, aprobar' : 'Sí, rechazar';
        $('#ac-modal').classList.add('open');
    }
    function cerrarResolver() { $('#ac-modal').classList.remove('open'); resolverCtx = null; }
    function confirmarResolver() {
        if (!resolverCtx) return;
        const nota = $('#acm-nota').value.trim();
        const btnC = $('#acm-confirmar');
        btnC.disabled = true;
        fetch(API + 'resolver_cancelacion.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_solicitud: resolverCtx.idSolicitud, accion: resolverCtx.accion, nota }),
        })
            .then(r => r.json())
            .then(d => {
                btnC.disabled = false;
                cerrarResolver();
                mostrarToast(1);
                cargar(true);        // refresca el timeline
                cargarAtencion();
            })
            .catch(() => { btnC.disabled = false; });
    }

    function verMotivo(id) {
        const it = S.items.find(x => x.id === id);
        if (it) alert('Motivo de la solicitud:\n\n' + (it.descripcion || '—'));
    }

    // Marcar un reporte como visto → se oculta del feed (no se borra)
    function marcarVisto(id) {
        fetch(API + 'marcar_actividad_visto.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id }),
        })
            .then(r => r.json())
            .then(d => {
                if (!d.success) return;
                S.items = S.items.filter(x => x.id !== id);
                const el = document.querySelector(`.ac-item[data-id="${id}"]`);
                if (el) {
                    el.style.transition = 'opacity .2s, transform .2s';
                    el.style.opacity = '0';
                    el.style.transform = 'translateX(24px)';
                    setTimeout(() => { el.remove(); if (!S.items.length) pintar(); }, 200);
                }
                cargarAtencion();
            })
            .catch(() => {});
    }

    // ── Eventos de UI ─────────────────────────────────────────────
    function initEventos() {
        // Pestañas
        document.querySelectorAll('.ac-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.ac-tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                S.servicio = tab.dataset.serv;
                cargar(true);
            });
        });
        // Filtro
        $('#ac-filtro').addEventListener('change', e => { S.filtro = e.target.value; cargar(true); });
        // Buscador (debounce)
        $('#ac-buscar').addEventListener('input', e => {
            clearTimeout(buscarTimer);
            buscarTimer = setTimeout(() => { S.buscar = e.target.value.trim(); cargar(true); }, 350);
        });
        // Cargar más
        $('#ac-mas').addEventListener('click', cargarMas);

        // Delegación de acciones en el timeline
        $('#ac-timeline').addEventListener('click', e => {
            const v = e.target.closest('.ac-visto');
            if (v) { marcarVisto(parseInt(v.dataset.visto, 10)); return; }
            const b = e.target.closest('.ac-btn[data-acc]');
            if (!b) return;
            const id = parseInt(b.dataset.id, 10);
            const it = S.items.find(x => x.id === id);
            const acc = b.dataset.acc;
            if (acc === 'ver_motivo') verMotivo(id);
            else if (acc === 'aprobar' || acc === 'rechazar') {
                if (it && it.id_referencia) abrirResolver(it.id_referencia, acc);
            }
        });

        // Panel de atención → abrir resolver si es cancelación
        const at = $('#ac-at-list');
        if (at) at.addEventListener('click', e => {
            const row = e.target.closest('.ac-at-item');
            if (!row) return;
            if (row.dataset.tipo === 'cancelacion_solicitada' && +row.dataset.ref) {
                abrirResolver(+row.dataset.ref, 'aprobar');
            }
        });

        // Modal
        $('#acm-cancelar').addEventListener('click', cerrarResolver);
        $('#acm-confirmar').addEventListener('click', confirmarResolver);
        $('#ac-modal').addEventListener('click', e => { if (e.target.id === 'ac-modal') cerrarResolver(); });

        // Pausar el poll cuando la pestaña no está visible (ahorra carga)
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) { clearInterval(pollTimer); pollTimer = null; }
            else if (!pollTimer) { poll(); pollTimer = setInterval(poll, POLL_MS); }
        });
    }

    // ── Init ──────────────────────────────────────────────────────
    function init() {
        if (!$('#ac-timeline')) return; // el markup no está en esta página
        initEventos();
        cargar(true);
        cargarAtencion();
        pollTimer = setInterval(poll, POLL_MS);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
