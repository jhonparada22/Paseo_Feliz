// ══════════════════════════════════════════════════════════════
// DASHBOARD_ADIESTRAMIENTO.JS — Dashboard post-compra de Adiestramiento
// Se alimenta de model/estado_servicio_adiestramiento.php. A diferencia
// de Paseos, no hay seguimiento en vivo (GPS/ruta del día): el entrenador
// (mismo rol que paseador) da la sesión en la dirección del cliente.
// Expone: window.mostrarDashboardAdiestramiento() -> Promise<boolean>
//         window.ocultarDashboardAdiestramiento()
// ══════════════════════════════════════════════════════════════
(function () {
    const API = '../../model/';

    // Estilos de "añadir otra mascota al servicio" (mismo patrón que
    // dashboard_paseos.js, se inyecta una sola vez).
    (function inyectarEstiloOtraMascota() {
        if (document.getElementById('dza-om-estilos')) return;
        const style = document.createElement('style');
        style.id = 'dza-om-estilos';
        style.textContent =
            '.dz-btn-add-mascota{width:100%;display:flex;align-items:center;justify-content:center;gap:7px;' +
            'padding:10px 12px;border:1.5px dashed var(--primary);border-radius:var(--radius-md);' +
            'background:#f6f9ff;color:var(--primary);font-weight:700;font-size:.84rem;cursor:pointer;' +
            'transition:background .15s;}' +
            '.dz-btn-add-mascota:hover{background:#eaf2ff;}' +
            '.dz-modal-txt{font-size:.84rem;color:var(--muted);margin:4px 0 12px;}' +
            '.dz-btn-opcion{width:100%;display:flex;align-items:flex-start;text-align:left;gap:12px;' +
            'padding:13px 14px;border:1.5px solid var(--border);border-radius:var(--radius-md);' +
            'background:var(--bg);cursor:pointer;margin-bottom:10px;font-family:inherit;color:var(--text);' +
            'transition:border-color .15s,background .15s;}' +
            '.dz-btn-opcion:hover{border-color:var(--primary);background:#f6f9ff;}' +
            '.dz-btn-opcion i{font-size:1.3rem;color:var(--primary);margin-top:2px;}' +
            '.dz-btn-opcion small{color:var(--muted);line-height:1.4;}';
        document.head.appendChild(style);
    })();

    let S = null;
    let firmaRender = '';
    let visible = false;
    let pollTimer = null;
    let idMascotaElegida = null;

    const DIAS_CORTOS = { 1: 'Lun', 2: 'Mar', 3: 'Mié', 4: 'Jue', 5: 'Vie', 6: 'Sáb', 7: 'Dom' };
    const CSV_DIAS = { lun: 'Lun', mar: 'Mar', mie: 'Mié', jue: 'Jue', vie: 'Vie', sab: 'Sáb', dom: 'Dom' };

    function esc(t) {
        return String(t == null ? '' : t)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function avatarUrl(ruta, porDefecto) {
        if (!ruta) return porDefecto;
        if (ruta.indexOf('assets/') === 0) return '../' + ruta;
        return ruta;
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
    function fmtDiasCsv(csv) {
        if (!csv) return '—';
        return csv.split(',').map(function (d) { return CSV_DIAS[d.trim()] || d; }).join(', ');
    }

    // ── API pública ───────────────────────────────────────────────
    window.mostrarDashboardAdiestramiento = function () {
        return cargarEstado().then(function (ok) {
            if (!ok) { window.ocultarDashboardAdiestramiento(); return false; }
            visible = true;
            document.getElementById('adiestramiento-dashboard').hidden = false;
            iniciarTimers();
            return true;
        }).catch(function () {
            window.ocultarDashboardAdiestramiento();
            return false;
        });
    };

    window.ocultarDashboardAdiestramiento = function () {
        visible = false;
        const cont = document.getElementById('adiestramiento-dashboard');
        if (cont) cont.hidden = true;
        detenerTimers();
    };

    async function cargarEstado() {
        const url = API + 'estado_servicio_adiestramiento.php' + (idMascotaElegida ? '?id_mascota=' + idMascotaElegida : '');
        const r = await fetch(url);
        const data = await r.json();
        if (!data.success || !data.tiene_servicio) return false;
        S = data.servicio;
        if (S && S.pedido && S.pedido.id_mascota) idMascotaElegida = S.pedido.id_mascota;
        const firma = JSON.stringify(S);
        if (firma !== firmaRender) { firmaRender = firma; render(); }
        return true;
    }

    function iniciarTimers() {
        detenerTimers();
        pollTimer = setInterval(function () {
            if (!visible) return;
            cargarEstado().then(function (ok) {
                if (!ok && typeof cargarMembresias === 'function') {
                    window.ocultarDashboardAdiestramiento();
                    cargarMembresias();
                }
            }).catch(function () {});
        }, 15000);
    }
    function detenerTimers() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }

    // ══════════════════════════════════════════════════════════════
    //  RENDER PRINCIPAL
    // ══════════════════════════════════════════════════════════════
    function render() {
        const cont = document.getElementById('adiestramiento-dashboard');
        if (!cont || !S) return;

        const asignado = S.estado === 'entrenador_asignado';
        const sub = asignado
            ? 'Tu entrenador ha sido asignado y tu próxima sesión está programada.'
            : 'Tu servicio de adiestramiento está confirmado y estamos asignando un entrenador.';

        const col1 = cardDireccion() + cardOtraMascota();
        const col2 = cardEstadoServicio() + cardResumen() + cardHistorial('Historial reciente');
        const col3 = (asignado ? cardEntrenador() : cardBuscandoEntrenador()) + cardAcciones() + cardPlan();

        cont.innerHTML =
            '<div class="dz-sub">' + sub + '</div>' +
            '<div class="dz-grid">' +
                '<div class="dz-col">' + col1 + '</div>' +
                '<div class="dz-col dz-col-centro">' + col2 + '</div>' +
                '<div class="dz-col">' + col3 + '</div>' +
            '</div>';

        conectarAcciones();
    }

    // ── Columna izquierda ─────────────────────────────────────────
    function cardDireccion() {
        const p = S.pedido;
        return '<div class="dz-card">' +
                    '<h3 class="dz-h3"><i class="ph ph-map-pin"></i> Dirección de las sesiones</h3>' +
                    '<div class="dz-dir">' + esc(p.direccion) + (p.barrio ? ', ' + esc(p.barrio) : '') + '</div>' +
                    (p.referencia ? '<div class="dz-dir-ref"><strong>Referencia:</strong> ' + esc(p.referencia) + '</div>' : '') +
                    (p.ubicacion_validada ? '<span class="dz-chip dz-chip-verde"><i class="ph ph-check-circle"></i> Ubicación validada</span>' : '') +
               '</div>';
    }

    // "Añadir a otra mascota al servicio" — mismo patrón que dashboard_paseos.js
    function cardOtraMascota() {
        if (typeof membresias === 'undefined' || !membresias.mascotas) return '';
        const disponibles = membresias.mascotas.filter(function (m) { return !m.adiestramiento; });
        if (!disponibles.length) return '';
        return '<div class="dz-card">' +
                    '<button type="button" class="dz-btn-add-mascota" data-accion="agregar-mascota">' +
                        '<i class="ph ph-plus"></i> Añadir a otra mascota al servicio' +
                    '</button>' +
               '</div>';
    }

    function abrirModalAgregarMascota() {
        if (typeof abrirWizardAdiestramiento !== 'function') return;
        const p = S.pedido;
        const ocupadas = membresias.mascotas.filter(function (m) { return m.adiestramiento; })
            .map(function (m) { return m.id_mascota; });
        abrirModalDz(
            '<button class="dz-modal-x" data-cerrar><i class="ph ph-x"></i></button>' +
            '<h3 class="dz-h3"><i class="ph ph-paw-print"></i> Añadir otra mascota</h3>' +
            '<p class="dz-modal-txt">¿Cómo quieres añadirla?</p>' +
            '<button id="dza-add-express" class="dz-btn-opcion">' +
                '<i class="ph ph-users-three"></i>' +
                '<span><strong>Unirse al servicio actual</strong><br>' +
                '<small>Mismos días, horario y entrenador que ' + esc(p.mascota) + '. Solo eliges la mascota y pagas.</small></span>' +
            '</button>' +
            '<button id="dza-add-normal" class="dz-btn-opcion">' +
                '<i class="ph ph-gear"></i>' +
                '<span><strong>Configurar un servicio nuevo</strong><br>' +
                '<small>Eliges plan, días, horario y dirección desde cero.</small></span>' +
            '</button>'
        );
        document.getElementById('dza-add-express').addEventListener('click', function () {
            cerrarModalDz();
            abrirWizardAdiestramiento({
                modo: 'agregar_mascota',
                base: Object.assign({}, p, { cantidad_sesiones: S.plan.sesiones_mes }),
                ocupadas: ocupadas,
            });
        });
        document.getElementById('dza-add-normal').addEventListener('click', function () {
            cerrarModalDz();
            abrirWizardAdiestramiento({ ocupadas: ocupadas });
        });
    }

    // ── Columna central ───────────────────────────────────────────
    function timelineHorizontal() {
        const activo = S.estado === 'entrenador_asignado' ? 2 : 1;
        const pasos = [
            { icon: 'ph-check',        txt: 'Compra<br>confirmada' },
            { icon: 'ph-chalkboard-teacher', txt: S.estado === 'pendiente_asignacion' ? 'Asignando<br>entrenador' : 'Entrenador<br>asignado' },
            { icon: 'ph-calendar',     txt: 'Próxima<br>sesión' },
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
        const asignado = S.estado === 'entrenador_asignado';
        const chip = asignado
            ? '<span class="dz-chip dz-chip-verde"><span class="dz-dot-vivo"></span> Servicio activo</span>'
            : '<span class="dz-chip dz-chip-ambar"><i class="ph ph-clock"></i> Servicio pendiente</span>';
        const titulo = asignado ? 'Entrenador asignado' : '¡Tu compra fue confirmada!';
        const sub = asignado
            ? 'Todo listo para la próxima sesión de ' + esc(S.pedido.mascota) + '.'
            : 'Estamos asignando el mejor entrenador para ' + esc(S.pedido.mascota) + '.';
        const extra = asignado ? '' :
            '<div class="dz-aviso dz-aviso-morado dz-aviso-grande">' +
                '<i class="ph ph-clock"></i>' +
                '<div><strong>Tiempo estimado de asignación</strong><br>Normalmente entre unas horas y 1 día hábil.' +
                '<br><small>Te notificaremos cuando tengamos un entrenador disponible.</small></div>' +
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
            ['ph-paw-print',      'Mascota',              esc(p.mascota)],
            ['ph-calendar-blank', 'Sesiones al mes',      S.plan.sesiones_mes + ' al mes'],
            ['ph-clock',          'Duración',             p.duracion_min + ' minutos por sesión'],
            ['ph-calendar-check', 'Días preferidos',      fmtDiasCsv(p.dias_preferidos)],
            ['ph-timer',          'Horario preferido',    esc(p.franja_horaria || '—')],
            ['ph-flag',           'Inicio de membresía',  fmtFecha(S.membresia.inicio)],
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
        const iconos = { compra: 'ph-check-circle', asignacion: 'ph-user-check', programado: 'ph-calendar-blank' };
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

    // ── Columna derecha ───────────────────────────────────────────
    function cardBuscandoEntrenador() {
        return '<div class="dz-card dz-card-centrada">' +
                    '<h3 class="dz-h3">Entrenador</h3>' +
                    '<div class="dz-buscando-icono"><i class="ph ph-graduation-cap"></i></div>' +
                    '<div class="dz-titulo-sm">Estamos buscando el mejor entrenador para ' + esc(S.pedido.mascota) + '.</div>' +
                    '<div class="dz-aviso dz-aviso-morado"><i class="ph ph-bell"></i> Te avisaremos por notificación cuando sea asignado.</div>' +
               '</div>';
    }

    function cardEntrenador() {
        const a = S.asignacion;
        if (!a) return cardBuscandoEntrenador();
        const avatar = avatarUrl(a.avatar, '../assets/default/avatar.png');
        const estrellas = '★★★★★'.slice(0, Math.max(1, Math.round(a.puntuacion))) || '★';
        return '<div class="dz-card dz-card-centrada">' +
                    '<div class="dz-card-head"><h3 class="dz-h3">Tu entrenador asignado</h3><span class="dz-chip dz-chip-verde">Disponible</span></div>' +
                    '<img class="dz-avatar" src="' + esc(avatar) + '" alt="Entrenador" onerror="this.src=\'../assets/default/avatar.png\'">' +
                    '<div class="dz-paseador-nombre">' + esc(a.nombre) + '</div>' +
                    '<div class="dz-paseador-rating"><span class="dz-estrellas">' + estrellas + '</span> ' + a.puntuacion.toFixed(1) + '</div>' +
                    '<div class="dz-paseador-sub"><i class="ph ph-seal-check"></i> Entrenador profesional</div>' +
                    '<div class="dz-botones-fila">' +
                        '<a class="dz-btn dz-btn-primario" href="Chat.php"><i class="ph ph-chat-circle-dots"></i> Chat</a>' +
                        (a.id_usuario
                            ? '<a class="dz-btn dz-btn-borde" href="perfil.php?id=' + a.id_usuario + '"><i class="ph ph-user"></i> Ver perfil</a>'
                            : (a.telefono ? '<a class="dz-btn dz-btn-borde" href="tel:' + esc(a.telefono) + '"><i class="ph ph-phone"></i> Llamar</a>' : '')) +
                    '</div>' +
               '</div>';
    }

    function cardAcciones() {
        const mascotasConServicio = (typeof membresias !== 'undefined' && membresias.mascotas)
            ? membresias.mascotas.filter(function (m) { return m.adiestramiento; })
            : [];
        const selectorMascota = mascotasConServicio.length > 1
            ? '<label class="dz-label" style="margin-bottom:4px;display:block;">Mascota</label>' +
              '<select id="dza-sel-mascota-acciones" class="dz-textarea" style="cursor:pointer;margin-bottom:14px;">' +
                  mascotasConServicio.map(function (mm) {
                      const sel = mm.id_mascota === S.pedido.id_mascota ? ' selected' : '';
                      return '<option value="' + mm.id_mascota + '"' + sel + '>' + esc(mm.nombre_mascota) + '</option>';
                  }).join('') +
              '</select>'
            : '';

        const botones =
            '<button class="dz-btn dz-btn-accion" data-accion="instrucciones"><i class="ph ph-pencil-simple"></i> Editar instrucciones</button>' +
            '<button class="dz-btn dz-btn-accion" data-accion="direccion"><i class="ph ph-map-pin"></i> Ver dirección</button>' +
            '<a class="dz-btn dz-btn-accion" href="sub_menu/centro_de_ayuda.php"><i class="ph ph-headset"></i> Contactar soporte</a>';
        return '<div class="dz-card">' +
                    '<h3 class="dz-h3">Acciones</h3>' +
                    selectorMascota +
                    '<div class="dz-acciones">' + botones + '</div>' +
               '</div>';
    }

    function cardPlan() {
        const pl = S.plan, mem = S.membresia;
        const renovarPronto = mem.dias_restantes <= 1;
        const btnRenovar = (renovarPronto && typeof abrirWizardAdiestramiento === 'function')
            ? '<button class="dz-btn dz-btn-primario" data-accion="renovar"><i class="ph ph-arrow-clockwise"></i> Renovar membresía</button>'
            : '';
        return '<div class="dz-card">' +
                    '<h3 class="dz-h3">Tu plan</h3>' +
                    '<div class="dz-plan-nombre">Tu mensualidad</div>' +
                    '<div class="dz-texto">' + pl.sesiones_mes + ' sesiones al mes</div>' +
                    '<div class="dz-fila dz-fila-sm"><i class="ph ph-calendar-blank"></i><span>Renovación: ' + fmtFecha(mem.renovacion) + '</span></div>' +
                    (renovarPronto ? '<div class="dz-aviso dz-aviso-ambar"><i class="ph ph-warning-circle"></i> Tu membresía vence pronto.</div>' : '') +
                    btnRenovar +
               '</div>';
    }

    // ══════════════════════════════════════════════════════════════
    //  ACCIONES
    // ══════════════════════════════════════════════════════════════
    function conectarAcciones() {
        document.querySelectorAll('#adiestramiento-dashboard [data-accion]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const a = btn.getAttribute('data-accion');
                if (a === 'instrucciones') abrirModalInstrucciones();
                else if (a === 'direccion') document.querySelector('#adiestramiento-dashboard .dz-dir')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                else if (a === 'renovar' && typeof abrirWizardAdiestramiento === 'function') abrirWizardAdiestramiento();
                else if (a === 'agregar-mascota') abrirModalAgregarMascota();
            });
        });

        const selMascotaAcciones = document.getElementById('dza-sel-mascota-acciones');
        if (selMascotaAcciones) {
            selMascotaAcciones.addEventListener('change', function () {
                idMascotaElegida = parseInt(this.value, 10);
                firmaRender = '';
                cargarEstado().catch(function () {});
            });
        }
    }

    // — Modal genérico del dashboard —
    function abrirModalDz(html) {
        cerrarModalDz();
        const m = document.createElement('div');
        m.id = 'dza-modal';
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
        const m = document.getElementById('dza-modal');
        if (m) m.remove();
    }

    function abrirModalInstrucciones() {
        const p = S.pedido;
        const m = abrirModalDz(
            '<button class="dz-modal-x" data-cerrar><i class="ph ph-x"></i></button>' +
            '<h3 class="dz-h3"><i class="ph ph-clipboard-text"></i> Instrucciones de la sesión de ' + esc(p.mascota) + '</h3>' +
            '<label class="dz-label">Instrucciones para el entrenador</label>' +
            '<textarea id="dza-txt-instrucciones" class="dz-textarea" maxlength="255" rows="3" placeholder="Ej: usar correa roja, tocar el timbre del portón negro...">' + esc(p.instrucciones) + '</textarea>' +
            '<label class="dz-label">Observaciones sobre tu mascota</label>' +
            '<textarea id="dza-txt-observaciones" class="dz-textarea" maxlength="1000" rows="3" placeholder="Ej: es juguetón, se distrae fácil...">' + esc(p.observaciones) + '</textarea>' +
            '<div id="dza-modal-error" class="dz-modal-error" hidden></div>' +
            '<button id="dza-btn-guardar" class="dz-btn dz-btn-primario dz-btn-full"><i class="ph ph-check"></i> Guardar cambios</button>'
        );

        m.querySelector('#dza-btn-guardar').addEventListener('click', function () {
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<i class="ph ph-circle-notch"></i> Guardando...';
            fetch(API + 'actualizar_instrucciones_pedido.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id_pedido: p.id_pedido,
                    tipo: 'adiestramiento',
                    instrucciones: document.getElementById('dza-txt-instrucciones').value.trim(),
                    observaciones: document.getElementById('dza-txt-observaciones').value.trim(),
                }),
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) throw new Error(data.message || 'No se pudo guardar.');
                S.pedido.instrucciones = data.instrucciones;
                S.pedido.observaciones = data.observaciones;
                firmaRender = '';
                cerrarModalDz();
                render();
            })
            .catch(function (e) {
                const err = document.getElementById('dza-modal-error');
                err.hidden = false;
                err.textContent = '⚠ ' + e.message;
                btn.disabled = false;
                btn.innerHTML = '<i class="ph ph-check"></i> Guardar cambios';
            });
        });
    }
})();
