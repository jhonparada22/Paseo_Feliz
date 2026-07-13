// ══════════════════════════════════════════════════════════════
// DASHBOARD_HOSPEDAJE.JS — Dashboard post-compra de Hospedaje
// Se alimenta de model/estado_servicio_hospedaje.php. A diferencia de
// Paseos/Adiestramiento, aquí NO hay asignación de personal — la
// recogida y entrega las hace la van de un administrador, así que el
// estado se muestra como una línea de tiempo de fases (fase_logistica).
// Expone: window.mostrarDashboardHospedaje() -> Promise<boolean>
//         window.ocultarDashboardHospedaje()
// ══════════════════════════════════════════════════════════════
(function () {
    const API = '../../model/';

    (function inyectarEstiloOtraMascota() {
        if (document.getElementById('dzh-om-estilos')) return;
        const style = document.createElement('style');
        style.id = 'dzh-om-estilos';
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

    const FASES = ['confirmado', 'recogida_en_camino', 'en_hospedaje', 'entrega_en_camino', 'entregado'];
    const FASE_LBL = {
        confirmado:          'Compra confirmada',
        recogida_en_camino:  'Recogida en camino',
        en_hospedaje:        'Mascota en hospedaje',
        entrega_en_camino:   'Entrega en camino',
        entregado:           'Entregado',
    };

    function esc(t) {
        return String(t == null ? '' : t)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function fmtFecha(str) {
        if (!str) return '—';
        const d = new Date(String(str).replace(' ', 'T'));
        if (isNaN(d)) return str;
        return d.toLocaleDateString('es-CO', { day: 'numeric', month: 'long', year: 'numeric' }) + ', ' +
               d.toLocaleTimeString('es-CO', { hour: 'numeric', minute: '2-digit', hour12: true });
    }
    function fmtFechaCorta(str) {
        if (!str) return '—';
        const d = new Date(String(str).replace(' ', 'T'));
        if (isNaN(d)) return str;
        return d.toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    // ── API pública ───────────────────────────────────────────────
    window.mostrarDashboardHospedaje = function () {
        return cargarEstado().then(function (ok) {
            if (!ok) { window.ocultarDashboardHospedaje(); return false; }
            visible = true;
            document.getElementById('hospedaje-dashboard').hidden = false;
            iniciarTimers();
            return true;
        }).catch(function () {
            window.ocultarDashboardHospedaje();
            return false;
        });
    };

    window.ocultarDashboardHospedaje = function () {
        visible = false;
        const cont = document.getElementById('hospedaje-dashboard');
        if (cont) cont.hidden = true;
        detenerTimers();
    };

    async function cargarEstado() {
        const url = API + 'estado_servicio_hospedaje.php' + (idMascotaElegida ? '?id_mascota=' + idMascotaElegida : '');
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
                    window.ocultarDashboardHospedaje();
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
        const cont = document.getElementById('hospedaje-dashboard');
        if (!cont || !S) return;

        const sub = S.fase_texto || 'Tu reserva de hospedaje está confirmada.';
        const col1 = cardDireccion() + cardOtraMascota();
        const col2 = cardEstadoServicio() + cardResumen() + cardHistorial('Historial reciente');
        const col3 = cardEstadia() + cardAcciones();

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
                    '<h3 class="dz-h3"><i class="ph ph-map-pin"></i> Dirección de recogida y entrega</h3>' +
                    '<div class="dz-dir">' + esc(p.direccion) + (p.barrio ? ', ' + esc(p.barrio) : '') + '</div>' +
                    (p.referencia ? '<div class="dz-dir-ref"><strong>Referencia:</strong> ' + esc(p.referencia) + '</div>' : '') +
                    (p.ubicacion_validada ? '<span class="dz-chip dz-chip-verde"><i class="ph ph-check-circle"></i> Ubicación validada</span>' : '') +
               '</div>';
    }

    function cardOtraMascota() {
        if (typeof membresias === 'undefined' || !membresias.mascotas) return '';
        const disponibles = membresias.mascotas.filter(function (m) { return !m.hospedaje; });
        if (!disponibles.length) return '';
        return '<div class="dz-card">' +
                    '<button type="button" class="dz-btn-add-mascota" data-accion="agregar-mascota">' +
                        '<i class="ph ph-plus"></i> Añadir a otra mascota al servicio' +
                    '</button>' +
               '</div>';
    }

    function abrirModalAgregarMascota() {
        if (typeof abrirWizardHospedaje !== 'function') return;
        const p = S.pedido;
        const ocupadas = membresias.mascotas.filter(function (m) { return m.hospedaje; })
            .map(function (m) { return m.id_mascota; });
        abrirModalDz(
            '<button class="dz-modal-x" data-cerrar><i class="ph ph-x"></i></button>' +
            '<h3 class="dz-h3"><i class="ph ph-paw-print"></i> Añadir otra mascota</h3>' +
            '<p class="dz-modal-txt">¿Cómo quieres añadirla?</p>' +
            '<button id="dzh-add-express" class="dz-btn-opcion">' +
                '<i class="ph ph-users-three"></i>' +
                '<span><strong>Unirse al servicio actual</strong><br>' +
                '<small>Mismas fechas y dirección que ' + esc(p.mascota) + '. Solo eliges la mascota y pagas.</small></span>' +
            '</button>' +
            '<button id="dzh-add-normal" class="dz-btn-opcion">' +
                '<i class="ph ph-gear"></i>' +
                '<span><strong>Configurar un servicio nuevo</strong><br>' +
                '<small>Eliges fechas y dirección desde cero.</small></span>' +
            '</button>'
        );
        document.getElementById('dzh-add-express').addEventListener('click', function () {
            cerrarModalDz();
            abrirWizardHospedaje({ modo: 'agregar_mascota', base: p, ocupadas: ocupadas });
        });
        document.getElementById('dzh-add-normal').addEventListener('click', function () {
            cerrarModalDz();
            abrirWizardHospedaje({ ocupadas: ocupadas });
        });
    }

    // ── Columna central ───────────────────────────────────────────
    function timelineHorizontal() {
        const activo = FASES.indexOf(S.fase);
        const pasos = [
            { icon: 'ph-check',        txt: 'Compra<br>confirmada' },
            { icon: 'ph-van',          txt: 'Recogida<br>en camino' },
            { icon: 'ph-house',        txt: 'En<br>hospedaje' },
            { icon: 'ph-van',          txt: 'Entrega<br>en camino' },
            { icon: 'ph-flag-checkered', txt: 'Entregado' },
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
        const enHospedaje = S.fase === 'en_hospedaje' || S.fase === 'entrega_en_camino';
        const entregado = S.fase === 'entregado';
        const chip = entregado
            ? '<span class="dz-chip dz-chip-verde"><i class="ph ph-check-circle"></i> Completado</span>'
            : enHospedaje
                ? '<span class="dz-chip dz-chip-verde"><span class="dz-dot-vivo"></span> En hospedaje</span>'
                : '<span class="dz-chip dz-chip-ambar"><i class="ph ph-clock"></i> Servicio pendiente</span>';
        return '<div class="dz-card">' +
                    '<div class="dz-card-head"><h3 class="dz-h3">Estado del servicio</h3>' + chip + '</div>' +
                    '<div class="dz-titulo' + (enHospedaje || entregado ? ' dz-titulo-verde' : '') + '">' + S.fase_texto + '</div>' +
                    timelineHorizontal() +
               '</div>';
    }

    function cardResumen() {
        const p = S.pedido;
        const filas = [
            ['ph-paw-print',      'Mascota',        esc(p.mascota)],
            ['ph-calendar-blank', 'Entrada',        fmtFecha(p.fecha_entrada)],
            ['ph-calendar-check', 'Salida',         fmtFecha(p.fecha_salida)],
            ['ph-moon',           'Noches',         p.cantidad_noches + ' noche(s)'],
        ];
        return '<div class="dz-card">' +
                    '<h3 class="dz-h3">Resumen de la reserva</h3>' +
                    filas.map(function (f) {
                        return '<div class="dz-fila"><i class="ph ' + f[0] + '"></i>' +
                               '<span class="dz-fila-lbl">' + f[1] + ':</span><span class="dz-fila-val">' + f[2] + '</span></div>';
                    }).join('') +
               '</div>';
    }

    function cardHistorial(titulo) {
        const h = S.historial || [];
        const iconos = { compra: 'ph-check-circle', recogida: 'ph-van', entrega: 'ph-flag-checkered' };
        const cuerpo = h.length ? h.map(function (e) {
            return '<div class="dz-hist-item">' +
                        '<span class="dz-hist-fecha">' + fmtFechaCorta(e.fecha) + '</span>' +
                        '<i class="ph ' + (iconos[e.tipo] || 'ph-circle') + '"></i>' +
                        '<span>' + esc(e.texto) + '</span>' +
                   '</div>';
        }).join('') : '<div class="dz-vacio">Sin eventos todavía.</div>';
        return '<div class="dz-card"><h3 class="dz-h3">' + esc(titulo) + '</h3>' + cuerpo + '</div>';
    }

    // ── Columna derecha ───────────────────────────────────────────
    function cardEstadia() {
        const p = S.pedido;
        return '<div class="dz-card dz-card-centrada">' +
                    '<h3 class="dz-h3">Tu mascota</h3>' +
                    '<div class="dz-buscando-icono"><i class="ph ph-house-line"></i></div>' +
                    '<div class="dz-titulo-sm">' + esc(S.fase_texto) + '</div>' +
                    '<div class="dz-aviso dz-aviso-morado"><i class="ph ph-bell"></i> Te avisaremos por notificación en cada paso (recogida, llegada y entrega).</div>' +
               '</div>';
    }

    function cardAcciones() {
        const mascotasConServicio = (typeof membresias !== 'undefined' && membresias.mascotas)
            ? membresias.mascotas.filter(function (m) { return m.hospedaje; })
            : [];
        const selectorMascota = mascotasConServicio.length > 1
            ? '<label class="dz-label" style="margin-bottom:4px;display:block;">Mascota</label>' +
              '<select id="dzh-sel-mascota-acciones" class="dz-textarea" style="cursor:pointer;margin-bottom:14px;">' +
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

    // ══════════════════════════════════════════════════════════════
    //  ACCIONES
    // ══════════════════════════════════════════════════════════════
    function conectarAcciones() {
        document.querySelectorAll('#hospedaje-dashboard [data-accion]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const a = btn.getAttribute('data-accion');
                if (a === 'instrucciones') abrirModalInstrucciones();
                else if (a === 'direccion') document.querySelector('#hospedaje-dashboard .dz-dir')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                else if (a === 'agregar-mascota') abrirModalAgregarMascota();
            });
        });

        const selMascotaAcciones = document.getElementById('dzh-sel-mascota-acciones');
        if (selMascotaAcciones) {
            selMascotaAcciones.addEventListener('change', function () {
                idMascotaElegida = parseInt(this.value, 10);
                firmaRender = '';
                cargarEstado().catch(function () {});
            });
        }
    }

    function abrirModalDz(html) {
        cerrarModalDz();
        const m = document.createElement('div');
        m.id = 'dzh-modal';
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
        const m = document.getElementById('dzh-modal');
        if (m) m.remove();
    }

    function abrirModalInstrucciones() {
        const p = S.pedido;
        const m = abrirModalDz(
            '<button class="dz-modal-x" data-cerrar><i class="ph ph-x"></i></button>' +
            '<h3 class="dz-h3"><i class="ph ph-clipboard-text"></i> Instrucciones de la estadía de ' + esc(p.mascota) + '</h3>' +
            '<label class="dz-label">Instrucciones para el equipo de recogida/entrega</label>' +
            '<textarea id="dzh-txt-instrucciones" class="dz-textarea" maxlength="255" rows="3" placeholder="Ej: tocar el timbre, la mascota sale con su correa roja...">' + esc(p.instrucciones) + '</textarea>' +
            '<label class="dz-label">Observaciones sobre tu mascota</label>' +
            '<textarea id="dzh-txt-observaciones" class="dz-textarea" maxlength="1000" rows="3" placeholder="Ej: alergias, medicamentos, rutina de alimentación...">' + esc(p.observaciones) + '</textarea>' +
            '<div id="dzh-modal-error" class="dz-modal-error" hidden></div>' +
            '<button id="dzh-btn-guardar" class="dz-btn dz-btn-primario dz-btn-full"><i class="ph ph-check"></i> Guardar cambios</button>'
        );

        m.querySelector('#dzh-btn-guardar').addEventListener('click', function () {
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<i class="ph ph-circle-notch"></i> Guardando...';
            fetch(API + 'actualizar_instrucciones_pedido.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id_pedido: p.id_pedido,
                    tipo: 'hospedaje',
                    instrucciones: document.getElementById('dzh-txt-instrucciones').value.trim(),
                    observaciones: document.getElementById('dzh-txt-observaciones').value.trim(),
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
                const err = document.getElementById('dzh-modal-error');
                err.hidden = false;
                err.textContent = '⚠ ' + e.message;
                btn.disabled = false;
                btn.innerHTML = '<i class="ph ph-check"></i> Guardar cambios';
            });
        });
    }
})();
