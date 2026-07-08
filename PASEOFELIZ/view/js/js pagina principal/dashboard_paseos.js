// ══════════════════════════════════════════════════════════════
// DASHBOARD_PASEOS.JS — Dashboard post-compra del servicio de Paseos
// Se alimenta de model/estado_servicio_paseos.php y muestra una de
// 3 vistas: pendiente_asignacion | paseador_asignado | paseo_en_curso.
// inicio_cliente.js lo muestra/oculta según membresía y pestaña activa.
// Expone: window.mostrarDashboardPaseos() -> Promise<boolean>
//         window.ocultarDashboardPaseos()
// ══════════════════════════════════════════════════════════════
(function () {
    const API = '../../model/';

    let S = null;             // servicio (respuesta del endpoint)
    let pedidosActivos = [];  // [{id_pedido, id_mascota, nombre, avatar}] para el selector
    let idPedidoSel = null;   // pedido elegido en el selector (persiste entre polls)
    let firmaRender = '';     // firma del último render (evita repintar sin cambios)
    let visible = false;
    let pollTimer = null;     // estado del servicio cada 15 s
    let gpsTimer = null;      // GPS del paseador cada 8 s (solo en curso)
    let tickTimer = null;     // reloj del tiempo transcurrido (1 s)
    let mapa = null, markerPaseador = null, gpsAjustado = false;
    // Desfase entre el reloj del servidor (que escribe NOW() en la BD, sin
    // zona horaria) y el del navegador; corrige horas y tiempo transcurrido.
    let skewMs = 0;

    const DIAS_CORTOS = { 1: 'Lun', 2: 'Mar', 3: 'Mié', 4: 'Jue', 5: 'Vie', 6: 'Sáb', 7: 'Dom' };
    const CSV_DIAS = { lun: 'Lun', mar: 'Mar', mie: 'Mié', jue: 'Jue', vie: 'Vie', sab: 'Sáb', dom: 'Dom' };

    // ── Helpers ───────────────────────────────────────────────────
    function esc(t) {
        return String(t == null ? '' : t)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function avatarUrl(ruta, porDefecto) {
        if (!ruta) return porDefecto;
        if (ruta.indexOf('assets/') === 0) return '../' + ruta; // rutas guardadas sin ../
        return ruta;
    }
    function fmtDinero(n) {
        return '$' + Number(n || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });
    }
    function fmtFecha(str) {
        if (!str) return '—';
        const d = new Date(String(str).replace(' ', 'T'));
        if (isNaN(d)) return str;
        return d.toLocaleDateString('es-CO', { day: 'numeric', month: 'long', year: 'numeric' });
    }
    function fmtFechaCorta(str) {
        if (!str) return '—';
        const d = new Date(String(str).replace(' ', 'T'));
        if (isNaN(d)) return str;
        return d.toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }
    // Epoch (ms) de un datetime del servidor, corregido con el desfase de reloj
    function msServidor(str) {
        const d = new Date(String(str).replace(' ', 'T'));
        return isNaN(d) ? NaN : d.getTime() - skewMs;
    }
    function fmtHora(str) {
        if (!str) return '—';
        const ms = msServidor(str);
        if (isNaN(ms)) return str;
        return new Date(ms).toLocaleTimeString('es-CO', { hour: 'numeric', minute: '2-digit', hour12: true });
    }
    function fmtDiasCsv(csv) {
        if (!csv) return '—';
        return csv.split(',').map(function (d) { return CSV_DIAS[d.trim()] || d; }).join(', ');
    }
    function fmtDiasNums(arr) {
        if (!arr || !arr.length) return '—';
        return arr.map(function (n) { return DIAS_CORTOS[n] || n; }).join(', ');
    }
    function fmtDuracion(seg) {
        const h = Math.floor(seg / 3600), m = Math.floor((seg % 3600) / 60), s = Math.floor(seg % 60);
        function p(n) { return String(n).padStart(2, '0'); }
        return (h ? p(h) + ':' : '') + p(m) + ':' + p(s);
    }

    // ── API pública ───────────────────────────────────────────────
    window.mostrarDashboardPaseos = function () {
        return cargarEstado().then(function (ok) {
            if (!ok) { window.ocultarDashboardPaseos(); return false; }
            visible = true;
            document.getElementById('paseos-dashboard').hidden = false;
            iniciarTimers();
            return true;
        }).catch(function () {
            window.ocultarDashboardPaseos();
            return false;
        });
    };

    window.ocultarDashboardPaseos = function () {
        visible = false;
        const cont = document.getElementById('paseos-dashboard');
        if (cont) cont.hidden = true;
        detenerTimers();
    };

    // ── Carga y polling ───────────────────────────────────────────
    async function cargarEstado() {
        const url = API + 'estado_servicio_paseos.php' + (idPedidoSel ? '?id_pedido=' + idPedidoSel : '');
        const r = await fetch(url);
        const data = await r.json();
        if (!data.success || !data.tiene_servicio) return false;
        if (data.ahora_servidor) {
            const ahora = new Date(String(data.ahora_servidor).replace(' ', 'T'));
            if (!isNaN(ahora)) skewMs = ahora.getTime() - Date.now();
        }
        S = data.servicio;
        pedidosActivos = data.pedidos_activos || [];
        // Sincronizar la selección con lo que el servidor realmente devolvió
        // (si el pedido elegido venció, cae al más reciente)
        idPedidoSel = S.pedido && S.pedido.id_pedido ? S.pedido.id_pedido : null;
        const firma = JSON.stringify(S) + '|' + JSON.stringify(pedidosActivos);
        if (firma !== firmaRender) {
            firmaRender = firma;
            render();
        }
        return true;
    }

    // Cambia la mascota mostrada en el dashboard (click en el selector)
    function seleccionarPedido(idPedido) {
        if (idPedido === idPedidoSel) return;
        idPedidoSel = idPedido;
        firmaRender = ''; // fuerza repintado con la respuesta nueva
        cargarEstado().catch(function () {});
    }

    function iniciarTimers() {
        detenerTimers();
        pollTimer = setInterval(function () {
            if (!visible) return;
            cargarEstado().then(function (ok) {
                // Si la membresía expiró en caliente, volver a la vista de compra
                if (!ok && typeof cargarMembresias === 'function') {
                    window.ocultarDashboardPaseos();
                    cargarMembresias();
                }
            }).catch(function () {});
        }, 15000);
    }

    function detenerTimers() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
        detenerTimersEnCurso();
    }
    function detenerTimersEnCurso() {
        if (gpsTimer) { clearInterval(gpsTimer); gpsTimer = null; }
        if (tickTimer) { clearInterval(tickTimer); tickTimer = null; }
    }

    // ══════════════════════════════════════════════════════════════
    //  RENDER PRINCIPAL
    // ══════════════════════════════════════════════════════════════
    function render() {
        const cont = document.getElementById('paseos-dashboard');
        if (!cont || !S) return;

        detenerTimersEnCurso();
        if (mapa) { mapa.remove(); mapa = null; markerPaseador = null; gpsAjustado = false; }

        const estado = S.estado;
        let sub, col1, col2, col3;

        if (estado === 'paseo_en_curso') {
            sub  = 'El paseo de ' + esc(S.pedido.mascota) + ' está en curso. Puedes seguirlo en tiempo real.';
            col1 = cardMapa(true) + cardInstrucciones() + cardMascotas();
            col2 = cardEstadoPaseo() + cardInfoPaseo() + cardHistorial('Historial del paseo de hoy');
            col3 = cardPaseador(true) + cardAcciones() + cardPlan();
        } else if (estado === 'paseador_asignado') {
            sub  = 'Tu paseador ha sido asignado y tu próximo paseo está programado.';
            col1 = cardMapa(false) + cardProximoPaseo() + cardMascotas();
            col2 = cardEstadoServicio() + cardResumen() + cardHistorial('Historial reciente');
            col3 = cardPaseador(false) + cardAcciones() + cardPlan();
        } else {
            sub  = 'Tu servicio de paseos está confirmado y estamos asignando un paseador.';
            col1 = cardMapa(false) + cardDireccion() + cardMascotas();
            col2 = cardEstadoServicio() + cardResumen() + cardHistorial('Historial reciente');
            col3 = cardBuscandoPaseador() + cardAcciones() + cardPlan();
        }

        cont.innerHTML =
            '<div class="dz-sub">' + sub + '</div>' +
            '<div class="dz-grid">' +
                '<div class="dz-col">' + col1 + '</div>' +
                '<div class="dz-col dz-col-centro">' + col2 + '</div>' +
                '<div class="dz-col">' + col3 + '</div>' +
            '</div>';

        conectarAcciones();
        iniciarMapa();
        if (estado === 'paseo_en_curso') iniciarModoEnCurso();
    }

    // ── Columna izquierda ─────────────────────────────────────────
    function cardMapa(enVivo) {
        const badge = enVivo
            ? '<span class="dz-map-badge dz-badge-vivo"><span class="dz-dot-vivo"></span> Seguimiento en tiempo real</span>'
            : '<span class="dz-map-badge"><i class="ph ph-check-circle"></i> Ubicación de recogida confirmada</span>';
        const pie = enVivo
            ? '<a class="dz-btn dz-btn-primario dz-map-link" href="mapa.php"><i class="ph ph-map-trifold"></i> Ver seguimiento completo</a>'
            : '';
        return '<div class="dz-card dz-card-mapa">' +
                    badge +
                    '<div id="dz-map"></div>' +
                    pie +
               '</div>';
    }

    function cardDireccion() {
        const p = S.pedido;
        return '<div class="dz-card">' +
                    '<h3 class="dz-h3"><i class="ph ph-map-pin"></i> Dirección de recogida</h3>' +
                    '<div class="dz-dir">' + esc(p.direccion) + (p.barrio ? ', ' + esc(p.barrio) : '') + '</div>' +
                    (p.referencia ? '<div class="dz-dir-ref"><strong>Referencia:</strong> ' + esc(p.referencia) + '</div>' : '') +
                    (p.ubicacion_validada ? '<span class="dz-chip dz-chip-verde"><i class="ph ph-check-circle"></i> Ubicación validada</span>' : '') +
               '</div>';
    }

    function cardProximoPaseo() {
        const p = S.pedido, pp = S.proximo_paseo;
        if (!pp) return cardDireccion();
        const fecha = pp.es_hoy ? 'Hoy, ' + fmtFecha(pp.fecha) : pp.dia_nombre + ', ' + fmtFecha(pp.fecha);
        return '<div class="dz-card">' +
                    '<h3 class="dz-h3 dz-h3-verde"><i class="ph ph-calendar-check"></i> Información del próximo paseo</h3>' +
                    '<div class="dz-fila"><i class="ph ph-calendar-blank"></i><span><strong>Fecha:</strong> ' + esc(fecha) + '</span></div>' +
                    (pp.franja ? '<div class="dz-fila"><i class="ph ph-clock"></i><span><strong>Hora:</strong> ' + esc(pp.franja) + ' (' + S.pedido.duracion_min + ' min)</span></div>' : '') +
                    '<div class="dz-fila"><i class="ph ph-map-pin"></i><span><strong>Recogida:</strong> ' + esc(p.direccion) + (p.barrio ? ', ' + esc(p.barrio) : '') + '</span></div>' +
                    (p.referencia ? '<div class="dz-fila"><i class="ph ph-note"></i><span><strong>Referencia:</strong> ' + esc(p.referencia) + '</span></div>' : '') +
                    '<div class="dz-aviso"><i class="ph ph-bell"></i> Te notificaremos cuando tu paseador esté en camino a la recogida.</div>' +
               '</div>';
    }

    // Selector de mascotas en servicio + botón para añadir otra
    function cardMascotas() {
        const filas = pedidosActivos.map(function (pa) {
            const sel = pa.id_pedido === (S.pedido && S.pedido.id_pedido);
            const av = avatarUrl(pa.avatar, '');
            return '<div class="dz-mascota-fila' + (sel ? ' sel' : '') + '" data-pedido="' + pa.id_pedido + '">' +
                       (av ? '<img class="dz-mascota-av" src="' + esc(av) + '" onerror="this.outerHTML=\'<span class=dz-mascota-emoji>🐶</span>\'">'
                           : '<span class="dz-mascota-emoji">🐶</span>') +
                       '<span class="dz-mascota-nombre">' + esc(pa.nombre) + '</span>' +
                       (sel ? '<i class="ph ph-check-circle dz-mascota-check"></i>' : '') +
                   '</div>';
        }).join('');

        return '<div class="dz-card">' +
                    filas +
                    '<button class="dz-btn-add-mascota" data-accion="agregar-mascota">' +
                        '<i class="ph ph-plus"></i> Añadir a otra mascota al servicio' +
                    '</button>' +
               '</div>';
    }

    // Mini-modal: cómo añadir la nueva mascota (exprés o servicio nuevo)
    function abrirModalAgregarMascota() {
        if (typeof abrirWizardPaseos !== 'function') return;
        const p = S.pedido;
        abrirModalDz(
            '<button class="dz-modal-x" data-cerrar><i class="ph ph-x"></i></button>' +
            '<h3 class="dz-h3"><i class="ph ph-paw-print"></i> Añadir otra mascota</h3>' +
            '<p class="dz-modal-txt">¿Cómo quieres añadirla?</p>' +
            '<button id="dz-add-express" class="dz-btn-opcion">' +
                '<i class="ph ph-users-three"></i>' +
                '<span><strong>Unirse al servicio actual</strong><br>' +
                '<small>Mismos días, horario y paseador que ' + esc(p.mascota) + '. Solo eliges la mascota y pagas.</small></span>' +
            '</button>' +
            '<button id="dz-add-normal" class="dz-btn-opcion">' +
                '<i class="ph ph-gear"></i>' +
                '<span><strong>Configurar un servicio nuevo</strong><br>' +
                '<small>Eliges plan, días, horario y dirección desde cero.</small></span>' +
            '</button>'
        );
        document.getElementById('dz-add-express').addEventListener('click', function () {
            cerrarModalDz();
            abrirWizardPaseos({
                modo: 'agregar_mascota',
                base: S.pedido,
                ocupadas: pedidosActivos.map(function (pa) { return pa.id_mascota; }),
            });
        });
        document.getElementById('dz-add-normal').addEventListener('click', function () {
            cerrarModalDz();
            // También en el flujo normal: las mascotas que ya tienen servicio
            // se muestran bloqueadas en el paso 1 del wizard
            abrirWizardPaseos({
                ocupadas: pedidosActivos.map(function (pa) { return pa.id_mascota; }),
            });
        });
    }

    function cardInstrucciones() {
        const p = S.pedido;
        const items = [];
        if (p.instrucciones) items.push(p.instrucciones);
        if (p.observaciones) items.push(p.observaciones);
        const cuerpo = items.length
            ? items.map(function (t) {
                  return '<div class="dz-instr-item"><i class="ph ph-check-circle"></i><span>' + esc(t) + '</span></div>';
              }).join('')
            : '<div class="dz-vacio">Aún no has agregado instrucciones para el paseo.</div>';
        return '<div class="dz-card">' +
                    '<div class="dz-card-head">' +
                        '<h3 class="dz-h3"><i class="ph ph-clipboard-text"></i> Instrucciones e información</h3>' +
                        '<button class="dz-btn-mini" data-accion="instrucciones"><i class="ph ph-pencil-simple"></i> Editar</button>' +
                    '</div>' + cuerpo +
                    '<div class="dz-aviso dz-aviso-morado"><i class="ph ph-info"></i> Si tienes alguna novedad, contáctanos al instante por el chat.</div>' +
               '</div>';
    }

    // ── Columna central ───────────────────────────────────────────
    function timelineHorizontal() {
        const estado = S.estado;
        // índice del paso activo: 2 = asignando, 3 = próximo paseo, 4 = en curso
        const activo = estado === 'paseo_en_curso' ? 4 : (estado === 'paseador_asignado' ? 3 : 2);
        const pasos = [
            { icon: 'ph-check',        txt: 'Compra<br>confirmada' },
            { icon: 'ph-check',        txt: 'Dirección<br>validada' },
            { icon: 'ph-paw-print',    txt: estado === 'pendiente_asignacion' ? 'Asignando<br>paseador' : 'Paseador<br>asignado' },
            { icon: 'ph-calendar',     txt: 'Próximo<br>paseo' },
            { icon: 'ph-person-simple-walk', txt: 'En curso' },
            { icon: 'ph-flag-checkered', txt: 'Completado' },
        ];
        return '<div class="dz-timeline">' + pasos.map(function (p, i) {
            const cls = i < activo ? 'hecho' : (i === activo ? 'activo' : '');
            return '<div class="dz-paso ' + cls + '">' +
                        '<div class="dz-paso-circulo"><i class="ph ' + (i < activo ? 'ph-check' : p.icon) + '"></i></div>' +
                        '<div class="dz-paso-txt">' + p.txt + '</div>' +
                   '</div>';
        }).join('<div class="dz-paso-linea"></div>') + '</div>';
    }

    function cardEstadoServicio() {
        const asignado = S.estado === 'paseador_asignado';
        const chip = asignado
            ? '<span class="dz-chip dz-chip-verde"><span class="dz-dot-vivo"></span> Servicio activo</span>'
            : '<span class="dz-chip dz-chip-ambar"><i class="ph ph-clock"></i> Servicio pendiente</span>';
        const titulo = asignado ? 'Paseador asignado' : '¡Tu compra fue confirmada!';
        const sub = asignado
            ? 'Todo listo para el próximo paseo de ' + esc(S.pedido.mascota) + '.'
            : 'Estamos asignando el mejor paseador para ' + esc(S.pedido.mascota) + '.';
        const extra = asignado ? '' :
            '<div class="dz-aviso dz-aviso-morado dz-aviso-grande">' +
                '<i class="ph ph-clock"></i>' +
                '<div><strong>Tiempo estimado de asignación</strong><br>Normalmente entre unas horas y 1 día hábil.' +
                '<br><small>Te notificaremos cuando tengamos un paseador disponible.</small></div>' +
            '</div>';
        return '<div class="dz-card">' +
                    '<div class="dz-card-head"><h3 class="dz-h3">Estado del servicio</h3>' + chip + '</div>' +
                    '<div class="dz-titulo' + (asignado ? ' dz-titulo-verde' : '') + '">' + titulo + '</div>' +
                    '<div class="dz-texto">' + sub + '</div>' +
                    timelineHorizontal() + extra +
               '</div>';
    }

    function cardResumen() {
        const p = S.pedido;
        const filas = [
            ['ph-paw-print',      'Mascota',           esc(p.mascota)],
            ['ph-calendar-blank', 'Plan',              esc(S.plan.nombre)],
            ['ph-clock',          'Duración',          p.duracion_min + ' minutos por paseo'],
            ['ph-users',          'Modalidad',         p.modalidad === 'individual' ? 'Individual' : 'Grupal'],
            ['ph-calendar-check', 'Días preferidos',   fmtDiasCsv(p.dias_preferidos)],
            ['ph-timer',          'Horario preferido', esc(p.franja_horaria || '—')],
            ['ph-flag',           'Inicio de membresía', fmtFecha(S.membresia.inicio)],
        ];
        return '<div class="dz-card">' +
                    '<h3 class="dz-h3">Resumen del servicio</h3>' +
                    filas.map(function (f) {
                        return '<div class="dz-fila"><i class="ph ' + f[0] + '"></i>' +
                               '<span class="dz-fila-lbl">' + f[1] + ':</span><span class="dz-fila-val">' + f[2] + '</span></div>';
                    }).join('') +
               '</div>';
    }

    function cardHistorial(titulo) {
        const h = S.historial || [];
        const iconos = { compra: 'ph-check-circle', direccion: 'ph-map-pin', asignacion: 'ph-user-check',
                         completado: 'ph-flag-checkered', programado: 'ph-calendar-blank' };
        const cuerpo = h.length ? h.map(function (e) {
            const futuro = e.futuro ? ' dz-hist-futuro' : '';
            return '<div class="dz-hist-item' + futuro + '">' +
                        '<span class="dz-hist-fecha">' + fmtFechaCorta(e.fecha) + '</span>' +
                        '<i class="ph ' + (iconos[e.tipo] || 'ph-circle') + '"></i>' +
                        '<span>' + esc(e.texto) + '</span>' +
                   '</div>';
        }).join('') : '<div class="dz-vacio">Sin eventos todavía.</div>';
        return '<div class="dz-card"><h3 class="dz-h3">' + esc(titulo) + '</h3>' + cuerpo + '</div>';
    }

    // ── Vista "en curso": estado del paseo + info ─────────────────
    function fasesPaseo() {
        const r = S.ruta_hoy || {};
        const rec = r.recogida || {}, ent = r.entrega || {};
        const recogido = rec.estado === 'completada';
        const finalizado = ent.estado === 'completada';
        return [
            { titulo: 'Paseador en camino', hora: r.fecha_inicio_real, desc: 'Salió hacia tu ubicación.',
              hecho: true },
            { titulo: 'Mascota recogida', hora: rec.hora_completado, desc: esc(S.pedido.mascota) + (recogido ? ' ya está con el paseador.' : ' aún no ha sido recogido.'),
              hecho: recogido },
            { titulo: 'Paseo en curso', hora: null, desc: recogido && !finalizado ? 'Disfrutando del paseo.' : 'A la espera de la recogida.',
              hecho: finalizado, activo: recogido && !finalizado },
            { titulo: 'Paseo finalizado', hora: ent.hora_completado, desc: finalizado ? esc(S.pedido.mascota) + ' fue entregado en casa.' : 'A la espera de finalizar el paseo.',
              hecho: finalizado },
        ];
    }

    function cardEstadoPaseo() {
        const fases = fasesPaseo();
        return '<div class="dz-card">' +
                    '<div class="dz-card-head"><h3 class="dz-h3">Estado del paseo</h3>' +
                    '<span class="dz-chip dz-chip-verde"><span class="dz-dot-vivo"></span> En curso</span></div>' +
                    '<div class="dz-tl-vertical">' +
                    fases.map(function (f) {
                        const cls = f.hecho ? 'hecho' : (f.activo ? 'activo' : '');
                        return '<div class="dz-tlv-item ' + cls + '">' +
                                    '<div class="dz-tlv-punto"><i class="ph ' + (f.hecho ? 'ph-check' : (f.activo ? 'ph-person-simple-walk' : 'ph-circle')) + '"></i></div>' +
                                    '<div class="dz-tlv-info">' +
                                        '<div class="dz-tlv-titulo">' + f.titulo +
                                        (f.hora ? '<span class="dz-tlv-hora">' + fmtHora(f.hora) + '</span>' : (f.activo ? '<span class="dz-tlv-hora">En progreso</span>' : '')) +
                                        '</div>' +
                                        '<div class="dz-tlv-desc">' + f.desc + '</div>' +
                                    '</div>' +
                               '</div>';
                    }).join('') +
                    '</div>' +
               '</div>';
    }

    function cardInfoPaseo() {
        const r = S.ruta_hoy || {};
        const filas = [
            ['ph-play',            'Inicio del paseo',    r.fecha_inicio_real ? fmtHora(r.fecha_inicio_real) : '—'],
            ['ph-clock',           'Duración programada', S.pedido.duracion_min + ' minutos'],
            ['ph-timer',           'Tiempo transcurrido', '<span id="dz-transcurrido">—</span>'],
            ['ph-person-simple-walk', 'Paseador',         esc(S.asignacion ? S.asignacion.nombre : '—')],
            ['ph-activity',        'Estado',              r.estado === 'pausada' ? 'Paseo pausado' : 'Paseo en curso'],
            ['ph-map-pin',         'Última actualización','<span id="dz-gps-updated">—</span>'],
        ];
        return '<div class="dz-card">' +
                    '<h3 class="dz-h3">Información del paseo en curso</h3>' +
                    filas.map(function (f) {
                        return '<div class="dz-fila"><i class="ph ' + f[0] + '"></i>' +
                               '<span class="dz-fila-lbl">' + f[1] + ':</span><span class="dz-fila-val">' + f[2] + '</span></div>';
                    }).join('') +
                    '<div class="dz-stats-mini" id="dz-stats-mini">' +
                        '<div class="dz-stat"><div class="dz-stat-val" id="dz-stat-tiempo">—</div><div class="dz-stat-lbl">Transcurrido</div></div>' +
                        '<div class="dz-stat"><div class="dz-stat-val" id="dz-stat-eta">—</div><div class="dz-stat-lbl">Fin estimado</div></div>' +
                    '</div>' +
               '</div>';
    }

    // ── Columna derecha ───────────────────────────────────────────
    function cardBuscandoPaseador() {
        return '<div class="dz-card dz-card-centrada">' +
                    '<h3 class="dz-h3">Paseador</h3>' +
                    '<div class="dz-buscando-icono"><i class="ph ph-dog"></i></div>' +
                    '<div class="dz-titulo-sm">Estamos buscando al mejor paseador para ' + esc(S.pedido.mascota) + '.</div>' +
                    '<div class="dz-aviso dz-aviso-morado"><i class="ph ph-bell"></i> Te avisaremos por notificación cuando sea asignado.</div>' +
               '</div>';
    }

    function cardPaseador(enVivo) {
        const a = S.asignacion;
        if (!a) return cardBuscandoPaseador();
        const avatar = avatarUrl(a.avatar, '../assets/default/avatar.png');
        const estrellas = '★★★★★'.slice(0, Math.max(1, Math.round(a.puntuacion))) || '★';
        const chip = enVivo
            ? '<span class="dz-chip dz-chip-verde"><span class="dz-dot-vivo"></span> En línea</span>'
            : '<span class="dz-chip dz-chip-verde">Disponible</span>';
        const llamar = (enVivo && a.telefono)
            ? '<a class="dz-btn dz-btn-borde" href="tel:' + esc(a.telefono) + '"><i class="ph ph-phone"></i> Llamar</a>'
            : '<button class="dz-btn dz-btn-borde" data-accion="perfil"><i class="ph ph-user"></i> Ver perfil</button>';
        return '<div class="dz-card dz-card-centrada">' +
                    '<div class="dz-card-head"><h3 class="dz-h3">' + (enVivo ? 'Tu paseador' : 'Tu paseador asignado') + '</h3>' + chip + '</div>' +
                    '<img class="dz-avatar" src="' + esc(avatar) + '" alt="Paseador" onerror="this.src=\'../assets/default/avatar.png\'">' +
                    '<div class="dz-paseador-nombre">' + esc(a.nombre) + '</div>' +
                    '<div class="dz-paseador-rating"><span class="dz-estrellas">' + estrellas + '</span> ' + a.puntuacion.toFixed(1) + '</div>' +
                    '<div class="dz-paseador-sub"><i class="ph ph-seal-check"></i> Paseador profesional</div>' +
                    '<div class="dz-botones-fila">' +
                        '<a class="dz-btn dz-btn-primario" href="Chat.php"><i class="ph ph-chat-circle-dots"></i> Chat</a>' +
                        llamar +
                    '</div>' +
               '</div>';
    }

    function cardAcciones() {
        const enCurso = S.estado === 'paseo_en_curso';
        let botones =
            '<button class="dz-btn dz-btn-accion" data-accion="instrucciones"><i class="ph ph-pencil-simple"></i> Editar instrucciones del paseo</button>' +
            '<button class="dz-btn dz-btn-accion" data-accion="direccion"><i class="ph ph-map-pin"></i> Ver dirección de recogida</button>';
        botones += enCurso
            ? '<a class="dz-btn dz-btn-accion dz-btn-rojo" href="sub_menu/centro_de_ayuda.php"><i class="ph ph-warning"></i> Reportar inconveniente</a>'
            : '<a class="dz-btn dz-btn-accion" href="sub_menu/centro_de_ayuda.php"><i class="ph ph-headset"></i> Contactar soporte</a>';
        return '<div class="dz-card">' +
                    '<h3 class="dz-h3">' + (enCurso ? 'Acciones rápidas' : 'Acciones') + '</h3>' +
                    '<div class="dz-acciones">' + botones + '</div>' +
               '</div>';
    }

    function cardPlan() {
        const pl = S.plan, mem = S.membresia;
        const pct = pl.paseos_mes ? Math.min(100, Math.round(pl.usados / pl.paseos_mes * 100)) : 0;
        const renovarPronto = mem.dias_restantes <= 1;
        const btnRenovar = (renovarPronto && typeof abrirWizardPaseos === 'function')
            ? '<button class="dz-btn dz-btn-primario" data-accion="renovar"><i class="ph ph-arrow-clockwise"></i> Renovar membresía</button>'
            : '';
        return '<div class="dz-card">' +
                    '<h3 class="dz-h3">Tu plan</h3>' +
                    '<div class="dz-plan-nombre">Plan mensual</div>' +
                    '<div class="dz-texto">' + esc(pl.nombre) + '</div>' +
                    '<div class="dz-plan-uso"><span>Usados: <strong>' + pl.usados + '</strong></span><span>Restantes: <strong>' + pl.restantes + '</strong></span></div>' +
                    '<div class="dz-barra"><div class="dz-barra-fill" style="width:' + pct + '%"></div></div>' +
                    '<div class="dz-fila dz-fila-sm"><i class="ph ph-calendar-blank"></i><span>Renovación: ' + fmtFecha(mem.renovacion) + '</span></div>' +
                    (renovarPronto ? '<div class="dz-aviso dz-aviso-ambar"><i class="ph ph-warning-circle"></i> Tu membresía vence pronto.</div>' : '') +
                    btnRenovar +
               '</div>';
    }

    // ══════════════════════════════════════════════════════════════
    //  MAPA (Leaflet ya está cargado en inicio.php por el wizard)
    // ══════════════════════════════════════════════════════════════
    function iniciarMapa() {
        const div = document.getElementById('dz-map');
        if (!div || typeof L === 'undefined') return;
        const p = S.pedido;
        mapa = L.map('dz-map', { zoomControl: true, attributionControl: false })
                .setView([p.lat, p.lng], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(mapa);
        L.marker([p.lat, p.lng], {
            icon: L.divIcon({
                className: 'dz-pin',
                html: '<div class="dz-pin-casa"><i class="ph ph-house"></i></div>',
                iconSize: [34, 34], iconAnchor: [17, 30],
            }),
        }).addTo(mapa).bindPopup('Recogida: ' + esc(p.direccion));
        // El contenedor acaba de hacerse visible: recalcular tamaño
        setTimeout(function () { if (mapa) mapa.invalidateSize(); }, 150);
    }

    // ── Modo "en curso": GPS del paseador + reloj ─────────────────
    function iniciarModoEnCurso() {
        actualizarGps();
        gpsTimer = setInterval(actualizarGps, 8000);
        actualizarReloj();
        tickTimer = setInterval(actualizarReloj, 1000);
    }

    function actualizarGps() {
        if (!S || !S.ruta_hoy) return;
        fetch(API + 'obtener_gps.php?id_paseador=' + S.ruta_hoy.id_paseador)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success || !data.gps || !mapa) return;
                const pos = [data.gps.lat, data.gps.lng];
                if (!markerPaseador) {
                    markerPaseador = L.marker(pos, {
                        icon: L.divIcon({
                            className: 'dz-pin',
                            html: '<div class="dz-pin-paseador"><i class="ph ph-person-simple-walk"></i></div>',
                            iconSize: [34, 34], iconAnchor: [17, 17],
                        }),
                    }).addTo(mapa).bindPopup('Tu paseador');
                } else {
                    markerPaseador.setLatLng(pos);
                }
                if (!gpsAjustado) {
                    gpsAjustado = true;
                    const p = S.pedido;
                    mapa.fitBounds(L.latLngBounds([pos, [p.lat, p.lng]]).pad(0.25));
                }
                const upd = document.getElementById('dz-gps-updated');
                if (upd) upd.textContent = 'Hace un momento';
            })
            .catch(function () {});
    }

    function actualizarReloj() {
        const r = S && S.ruta_hoy;
        if (!r || !r.fecha_inicio_real) return;
        const inicioMs = msServidor(r.fecha_inicio_real);
        if (isNaN(inicioMs)) return;
        const seg = Math.max(0, (Date.now() - inicioMs) / 1000);
        const fin = new Date(inicioMs + S.pedido.duracion_min * 60000);
        const elT = document.getElementById('dz-transcurrido');
        const elS = document.getElementById('dz-stat-tiempo');
        const elE = document.getElementById('dz-stat-eta');
        if (elT) elT.textContent = fmtDuracion(seg);
        if (elS) elS.textContent = fmtDuracion(seg);
        if (elE) elE.textContent = fin.toLocaleTimeString('es-CO', { hour: 'numeric', minute: '2-digit', hour12: true });
    }

    // ══════════════════════════════════════════════════════════════
    //  ACCIONES (instrucciones, dirección, perfil, renovar)
    // ══════════════════════════════════════════════════════════════
    function conectarAcciones() {
        document.querySelectorAll('#paseos-dashboard [data-accion]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const a = btn.getAttribute('data-accion');
                if (a === 'instrucciones') abrirModalInstrucciones();
                else if (a === 'direccion') centrarMapa();
                else if (a === 'perfil') abrirModalPerfil();
                else if (a === 'renovar' && typeof abrirWizardPaseos === 'function') abrirWizardPaseos();
                else if (a === 'agregar-mascota') abrirModalAgregarMascota();
            });
        });
        // Selector de mascota en servicio (cambia el pedido mostrado)
        document.querySelectorAll('#paseos-dashboard [data-pedido]').forEach(function (fila) {
            fila.addEventListener('click', function () {
                seleccionarPedido(parseInt(fila.getAttribute('data-pedido'), 10));
            });
        });
    }

    function centrarMapa() {
        if (!mapa) return;
        const p = S.pedido;
        mapa.setView([p.lat, p.lng], 16);
        document.getElementById('dz-map').scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // — Modal genérico del dashboard —
    function abrirModalDz(html) {
        cerrarModalDz();
        const m = document.createElement('div');
        m.id = 'dz-modal';
        m.innerHTML = '<div class="dz-modal-overlay"><div class="dz-modal-box">' + html + '</div></div>';
        document.body.appendChild(m);
        m.querySelector('.dz-modal-overlay').addEventListener('click', function (e) {
            if (e.target === this) cerrarModalDz();
        });
        const cerrar = m.querySelector('[data-cerrar]');
        if (cerrar) cerrar.addEventListener('click', cerrarModalDz);
        return m;
    }
    function cerrarModalDz() {
        const m = document.getElementById('dz-modal');
        if (m) m.remove();
    }

    function abrirModalInstrucciones() {
        const p = S.pedido;
        const m = abrirModalDz(
            '<button class="dz-modal-x" data-cerrar><i class="ph ph-x"></i></button>' +
            '<h3 class="dz-h3"><i class="ph ph-clipboard-text"></i> Instrucciones del paseo</h3>' +
            '<label class="dz-label">Instrucciones para el paseador</label>' +
            '<textarea id="dz-txt-instrucciones" class="dz-textarea" maxlength="255" rows="3" placeholder="Ej: usar correa roja, tocar el timbre del portón negro...">' + esc(p.instrucciones) + '</textarea>' +
            '<label class="dz-label">Observaciones sobre tu mascota</label>' +
            '<textarea id="dz-txt-observaciones" class="dz-textarea" maxlength="1000" rows="3" placeholder="Ej: es juguetón, no darle agua fría después de correr...">' + esc(p.observaciones) + '</textarea>' +
            '<div id="dz-modal-error" class="dz-modal-error" hidden></div>' +
            '<button id="dz-btn-guardar" class="dz-btn dz-btn-primario dz-btn-full"><i class="ph ph-check"></i> Guardar cambios</button>'
        );
        m.querySelector('#dz-btn-guardar').addEventListener('click', function () {
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<i class="ph ph-circle-notch"></i> Guardando...';
            fetch(API + 'actualizar_instrucciones_pedido.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id_pedido: p.id_pedido,
                    instrucciones: document.getElementById('dz-txt-instrucciones').value.trim(),
                    observaciones: document.getElementById('dz-txt-observaciones').value.trim(),
                }),
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) throw new Error(data.message || 'No se pudo guardar.');
                S.pedido.instrucciones = data.instrucciones;
                S.pedido.observaciones = data.observaciones;
                firmaRender = ''; // forzar repintado con los nuevos textos
                cerrarModalDz();
                render();
            })
            .catch(function (e) {
                const err = document.getElementById('dz-modal-error');
                err.hidden = false;
                err.textContent = '⚠ ' + e.message;
                btn.disabled = false;
                btn.innerHTML = '<i class="ph ph-check"></i> Guardar cambios';
            });
        });
    }

    function abrirModalPerfil() {
        const a = S.asignacion;
        if (!a) return;
        const avatar = avatarUrl(a.avatar, '../assets/default/avatar.png');
        const horario = (a.hora_inicio && a.hora_fin)
            ? a.hora_inicio.slice(0, 5) + ' – ' + a.hora_fin.slice(0, 5)
            : 'Horario flexible';
        abrirModalDz(
            '<button class="dz-modal-x" data-cerrar><i class="ph ph-x"></i></button>' +
            '<div class="dz-card-centrada">' +
                '<img class="dz-avatar" src="' + esc(avatar) + '" alt="Paseador" onerror="this.src=\'../assets/default/avatar.png\'">' +
                '<div class="dz-paseador-nombre">' + esc(a.nombre) + '</div>' +
                '<div class="dz-paseador-rating"><span class="dz-estrellas">★</span> ' + a.puntuacion.toFixed(1) + ' · Paseador profesional</div>' +
            '</div>' +
            '<div class="dz-fila"><i class="ph ph-map-pin"></i><span class="dz-fila-lbl">Zona:</span><span class="dz-fila-val">' + esc(a.zona_trabajo || 'Toda la ciudad') + '</span></div>' +
            '<div class="dz-fila"><i class="ph ph-clock"></i><span class="dz-fila-lbl">Horario:</span><span class="dz-fila-val">' + esc(horario) + '</span></div>' +
            '<div class="dz-fila"><i class="ph ph-paw-print"></i><span class="dz-fila-lbl">Paseos realizados:</span><span class="dz-fila-val">' + a.paseos_totales + '</span></div>' +
            '<div class="dz-fila"><i class="ph ph-calendar-check"></i><span class="dz-fila-lbl">Tus días:</span><span class="dz-fila-val">' + fmtDiasNums(a.dias) + '</span></div>' +
            '<a class="dz-btn dz-btn-primario dz-btn-full" href="Chat.php"><i class="ph ph-chat-circle-dots"></i> Abrir chat</a>'
        );
    }
})();
