// ═══════════════════════════════════════════════════════════════
// WIZARD_HOSPEDAJE.JS — Modal "Contratar Hospedaje Canino" (4 pasos)
// Paso 1: Mascota y servicio · Paso 2: Dirección y ubicación
// Paso 3: Resumen del pedido · Paso 4: Pago
//
// Se abre con window.abrirWizardHospedaje() desde inicio_cliente.js.
// Backend: model/obtener_planes.php, obtener_mi_perfil.php,
//          obtener_mascotas.php y procesar_compra_hospedaje.php.
// ═══════════════════════════════════════════════════════════════
(function () {
    'use strict';

    const API_WZ = '../../model/';
    const BANCOS_PSE = ['Bancolombia', 'Banco de Bogotá', 'Davivienda', 'BBVA Colombia', 'Banco de Occidente', 'Banco Popular', 'Banco AV Villas', 'Scotiabank Colpatria'];
    // El precio por noche y los descuentos se cargan desde obtener_precios.php
    // (configurables por el admin) — el total final SIEMPRE lo valida
    // procesar_compra_hospedaje.php en el servidor.
    let PRECIO_NOCHE_HOSP = 28000; // valor de respaldo mientras carga
    let DESCUENTOS_HOSP = [];      // [{cantidad_minima, descuento_pct}, ...]
    const MAX_NOCHES_HOSPEDAJE = 60;
    const COMPORTAMIENTOS = [
        { k: 'sociable',    icono: 'ph-smiley',         t: 'Sociable',    d: 'Le va bien con otros perros' },
        { k: 'timido',      icono: 'ph-smiley-meh',     t: 'Tímido',      d: 'Necesita tiempo para adaptarse' },
        { k: 'reactivo',    icono: 'ph-warning-circle', t: 'Reactivo',    d: 'Puede reaccionar a otros perros' },
        { k: 'no_sociable', icono: 'ph-smiley-x-eyes',  t: 'No sociable', d: 'Prefiere estar solo' },
    ];

    // ── Estado ──────────────────────────────────────────────
    let datos = { mascotas: [], perfil: null };
    let W = null;              // estado del pedido en curso
    let pasoActual = 1;
    let mapaWz = null, marcadorWz = null;
    let procesando = false;

    function estadoInicial() {
        const entrada = hoyISO();
        return {
            id_mascota: null,
            fecha_entrada: entrada,
            fecha_salida: sumarDias(entrada, 3),
            comportamiento: 'sociable',
            observaciones: '',
            ubicacion: { direccion: '', barrio: '', referencia: '', instrucciones: '', lat: null, lng: null, validada: false },
            pago: { metodo: 'tarjeta', numero: '', titular: '', venc: '', cvv: '', cuotas: 1, tipo_persona: 'natural', documento: '', banco: '', email_confirmacion: '' },
            facturacion: { usar_perfil: true, pais: 'Colombia', ciudad: 'Cúcuta', departamento: 'Norte de Santander', direccion: '', complemento: '', codigo_postal: '' },
            conf: { datos: false, terminos: false, autorizo: false },
        };
    }

    // ── Helpers ─────────────────────────────────────────────
    const $  = (sel) => document.querySelector(sel);
    const $$ = (sel) => Array.from(document.querySelectorAll(sel));
    const cop = (n) => '$' + Math.round(n).toLocaleString('es-CO');
    function hoyISO() { return new Date().toISOString().split('T')[0]; }
    function sumarDias(fechaISO, dias) {
        const d = new Date(fechaISO + 'T00:00:00');
        d.setDate(d.getDate() + dias);
        return d.toISOString().split('T')[0];
    }
    function calcularNoches(entradaISO, salidaISO) {
        const a = new Date(entradaISO + 'T00:00:00');
        const b = new Date(salidaISO + 'T00:00:00');
        return Math.round((b - a) / 86400000);
    }

    function normalizarAvatar(a) {
        if (!a) return '';
        a = a.replace(/^(\.\.\/)+/, '');
        return a.startsWith('assets/') ? '../' + a : a;
    }

    function calcularPrecio() {
        const noches = Math.max(0, calcularNoches(W.fecha_entrada, W.fecha_salida));
        const subtotal = PRECIO_NOCHE_HOSP * noches;
        let descuentoPct = 0;
        DESCUENTOS_HOSP.forEach(d => {
            if (noches >= d.cantidad_minima && d.descuento_pct > descuentoPct) descuentoPct = d.descuento_pct;
        });
        const descuento = Math.round(subtotal * descuentoPct) / 100;
        return { precio_noche: PRECIO_NOCHE_HOSP, noches, cantidad: noches, subtotal, descuento_pct: descuentoPct, descuento, total: subtotal - descuento };
    }
    function mascotaSel() { return datos.mascotas.find(m => m.id_mascota === W.id_mascota) || null; }

    function luhnValido(num) {
        const d = num.replace(/\D/g, '');
        if (d.length < 13 || d.length > 19) return false;
        let suma = 0, alt = false;
        for (let i = d.length - 1; i >= 0; i--) {
            let n = parseInt(d[i], 10);
            if (alt) { n *= 2; if (n > 9) n -= 9; }
            suma += n; alt = !alt;
        }
        return suma % 10 === 0;
    }

    function vencValido(v) {
        const m = v.match(/^(\d{2})\s*\/\s*(\d{2})$/);
        if (!m) return false;
        const mes = parseInt(m[1], 10), anio = 2000 + parseInt(m[2], 10);
        if (mes < 1 || mes > 12) return false;
        const ahora = new Date();
        return anio > ahora.getFullYear() || (anio === ahora.getFullYear() && mes >= ahora.getMonth() + 1);
    }

    // ── Apertura del wizard ─────────────────────────────────
    window.abrirWizardHospedaje = async function () {
        inyectarModal();
        W = estadoInicial();
        pasoActual = 1;
        $('#wizard-hospedaje').classList.add('visible');
        $('#wizard-hospedaje').style.display = 'flex';
        $('#wzh-cuerpo').innerHTML = '<div style="text-align:center;padding:60px;color:#64748b">Cargando tus datos... 🐾</div>';

        try {
            const rPerfil = await fetch(API_WZ + 'obtener_mi_perfil.php').then(r => r.json());
            if (!rPerfil.success) throw new Error(rPerfil.message || 'Error');
            datos.perfil = rPerfil.perfil;

            const rMasc = await fetch(API_WZ + 'obtener_mascotas.php?id_usuario=' + datos.perfil.id).then(r => r.json());
            datos.mascotas = rMasc.success ? rMasc.mascotas : [];

            // Marcar qué mascotas YA tienen esta membresía activa, para no
            // dejar comprarla de nuevo para la misma mascota (sí se puede
            // comprar para otra mascota distinta).
            const rMem = await fetch('../../controller/membresia_estado.php').then(r => r.json()).catch(() => null);
            const membresiaPorMascota = {};
            if (rMem && rMem.success) (rMem.mascotas || []).forEach(m => { membresiaPorMascota[m.id_mascota] = m; });
            datos.mascotas.forEach(m => { m.yaTieneEsta = !!(membresiaPorMascota[m.id_mascota]?.hospedaje); });

            const rPrecios = await fetch(API_WZ + 'obtener_precios.php').then(r => r.json()).catch(() => null);
            if (rPrecios && rPrecios.success && rPrecios.precios.hospedaje) {
                PRECIO_NOCHE_HOSP = rPrecios.precios.hospedaje.precio_unidad;
                DESCUENTOS_HOSP = rPrecios.descuentos.hospedaje || [];
            }

            // Prellenar con el perfil
            W.ubicacion.direccion = datos.perfil.direccion || '';
            const primeraDisponible = datos.mascotas.find(m => !m.yaTieneEsta);
            if (primeraDisponible) W.id_mascota = primeraDisponible.id_mascota;
            else if (datos.mascotas.length) W.id_mascota = datos.mascotas[0].id_mascota;
            W.pago.titular = datos.perfil.nombre || '';
            W.pago.email_confirmacion = datos.perfil.email || '';

            renderPaso(1);
        } catch (e) {
            $('#wzh-cuerpo').innerHTML =
                '<div style="text-align:center;padding:60px;color:#b91c1c">No se pudieron cargar tus datos. Verifica tu conexión e inténtalo de nuevo.</div>';
        }
    };

    // ── Estructura base del modal ───────────────────────────
    function inyectarModal() {
        if (document.getElementById('wizard-hospedaje')) return;
        const div = document.createElement('div');
        div.id = 'wizard-hospedaje';
        div.innerHTML = `
        <div class="wz-overlay" id="wzh-overlay">
          <div class="wz-modal">
            <div class="wz-header">
              <div class="wz-header-icon"><i class="ph ph-paw-print"></i></div>
              <div>
                <h2>Contratar Hospedaje Canino</h2>
                <div class="wz-sub" id="wzh-subtitulo"></div>
              </div>
              <button class="wz-close" id="wzh-cerrar" title="Cerrar"><i class="ph ph-x"></i></button>
            </div>
            <div class="wz-progress" id="wzh-progreso"></div>
            <div class="wz-body" id="wzh-cuerpo"></div>
            <div class="wz-footer" id="wzh-footer">
              <div class="wz-seguro"><i class="ph ph-shield-check"></i> Tu información está protegida</div>
              <div class="wz-botones" id="wzh-botones"></div>
            </div>
          </div>
        </div>`;
        document.body.appendChild(div);
        $('#wzh-cerrar').addEventListener('click', cerrarWizard);
    }

    function cerrarWizard() {
        $('#wizard-hospedaje').classList.remove('visible');
        $('#wizard-hospedaje').style.display = 'none';
        if (mapaWz) { mapaWz.remove(); mapaWz = null; marcadorWz = null; }
        // Si la compra fue exitosa, refrescar los botones de membresía del inicio
        if (W && W.__exito && typeof cargarMembresias === 'function') cargarMembresias();
    }

    // ── Navegación y render ─────────────────────────────────
    const TITULOS = { 1: 'Mascota y detalles del servicio', 2: 'Dirección y ubicación', 3: 'Resumen del pedido', 4: 'Pago' };
    const LBL_PASOS = ['Mascota y servicio', 'Dirección y ubicación', 'Resumen del pedido', 'Pago'];

    function renderPaso(n) {
        pasoActual = n;
        $('#wzh-subtitulo').innerHTML = `<strong>Paso ${n} de 4:</strong> ${TITULOS[n]}`;

        // Barra de progreso
        let html = '';
        for (let i = 1; i <= 4; i++) {
            const cls = i < n ? 'hecho' : (i === n ? 'activo' : '');
            html += `<div class="wz-step ${cls}">
                       <div class="wz-step-dot">${i < n ? '✓' : i}</div>
                       <div class="wz-step-lbl">${LBL_PASOS[i - 1]}</div>
                     </div>`;
            if (i < 4) html += `<div class="wz-step-linea ${i < n ? 'hecha' : ''}"></div>`;
        }
        $('#wzh-progreso').innerHTML = html;

        if (n === 1) renderPaso1();
        if (n === 2) renderPaso2();
        if (n === 3) renderPaso3();
        if (n === 4) renderPaso4();
        $('#wzh-cuerpo').scrollTop = 0;
    }

    function botonesFooter(atras, siguienteTxt, onSiguiente, siguienteId = 'wzh-btn-sig') {
        let html = '';
        if (atras) html += `<button class="wz-btn wz-btn-sec" id="wzh-btn-atras"><i class="ph ph-arrow-left"></i> Anterior</button>`;
        else       html += `<button class="wz-btn wz-btn-sec" id="wzh-btn-atras">Cancelar</button>`;
        html += `<button class="wz-btn wz-btn-prim" id="${siguienteId}">${siguienteTxt}</button>`;
        $('#wzh-botones').innerHTML = html;
        $('#wzh-btn-atras').addEventListener('click', () => {
            if (atras) renderPaso(pasoActual - 1);
            else cerrarWizard();
        });
        $('#' + siguienteId).addEventListener('click', onSiguiente);
    }

    // Panel lateral (resumen) reutilizado en pasos 1-4
    function lateralHTML(extendido) {
        const m = mascotaSel(), pr = calcularPrecio();
        const av = m ? normalizarAvatar(m.avatar) : '';
        let html = `
        <div class="wz-lateral">
          <h4>Resumen del pedido</h4>
          <div class="wz-lat-mascota">
            ${av ? `<img src="${av}" onerror="this.outerHTML='<div class=emoji>🐶</div>'">` : '<div class="emoji">🐶</div>'}
            <div>
              <div style="font-weight:800;font-size:.9rem">${m ? m.nombre : 'Sin mascota'}</div>
              <span class="wz-tag">Mascota seleccionada</span>
            </div>
          </div>
          <div class="wz-lat-sep"></div>
          <div style="font-weight:800;font-size:.8rem;margin-bottom:5px">Estadía</div>
          <div class="wz-lat-fila"><span class="d"><i class="ph ph-calendar-blank"></i>Entrada:</span><span class="v">${W.fecha_entrada}</span></div>
          <div class="wz-lat-fila"><span class="d"><i class="ph ph-calendar-check"></i>Salida:</span><span class="v">${W.fecha_salida}</span></div>
          <div class="wz-lat-fila"><span class="d"><i class="ph ph-moon"></i>Noches:</span><span class="v">${pr.noches}</span></div>`;

        if (W.ubicacion.validada) {
            html += `
          <div class="wz-lat-sep"></div>
          <div style="font-weight:800;font-size:.8rem;margin-bottom:5px">Ubicación</div>
          <div style="font-size:.76rem;color:#334155">📍 ${W.ubicacion.direccion}</div>
          <span class="wz-tag" style="margin-top:5px;display:inline-block">Ubicación confirmada ✓</span>`;
        }

        if (pr.noches > 0) {
            html += `
          <div class="wz-lat-sep"></div>
          <div style="font-weight:800;font-size:.8rem;margin-bottom:5px">Precio</div>
          <div class="wz-lat-fila"><span class="d">Precio por noche</span><span class="v">${cop(pr.precio_noche)}</span></div>
          <div class="wz-lat-fila"><span class="d">Cantidad de noches</span><span class="v">${pr.noches}</span></div>
          <div class="wz-lat-fila"><span class="d">Subtotal</span><span class="v">${cop(pr.subtotal)}</span></div>
          ${pr.descuento > 0 ? `<div class="wz-lat-fila"><span class="d wz-lat-desc">Descuento (${pr.descuento_pct}%)</span><span class="v wz-lat-desc">-${cop(pr.descuento)}</span></div>` : ''}
          <div class="wz-lat-total"><span>Total estadía</span><span class="monto">${cop(pr.total)}</span></div>`;
        }

        html += `
          <div class="wz-lat-aviso"><i class="ph ph-calendar-blank"></i> El cobro cubre toda la estadía, desde la entrada hasta la salida.</div>`;

        if (extendido) {
            html += `
          <div class="wz-lat-beneficios">
            <div><i class="ph ph-check-circle"></i> Cuidadores certificados</div>
            <div><i class="ph ph-check-circle"></i> Seguimiento en tiempo real</div>
            <div><i class="ph ph-check-circle"></i> Trato amoroso y responsable</div>
          </div>`;
        }
        html += `</div>`;
        return html;
    }

    function refrescarLateral(extendido) {
        const lat = $('#wzh-lateral-slot');
        if (lat) lat.innerHTML = lateralHTML(extendido);
    }

    // ═══════════════════════════════════════════════════════
    // PASO 1 — MASCOTA Y SERVICIO
    // ═══════════════════════════════════════════════════════
    function renderPaso1() {
        const sinMascotas = !datos.mascotas.length;

        const cardsMascotas = datos.mascotas.map(m => {
            const av = normalizarAvatar(m.avatar);
            const bloqueada = m.yaTieneEsta;
            return `<div class="wz-card ${W.id_mascota === m.id_mascota ? 'sel' : ''} ${bloqueada ? 'wz-card-bloqueada' : ''}" data-mascota="${m.id_mascota}" ${bloqueada ? 'data-bloqueada="1"' : ''} ${bloqueada ? 'style="opacity:.5;cursor:not-allowed;"' : ''}>
                      ${av ? `<img src="${av}" onerror="this.outerHTML='<div class=wz-card-emoji>🐶</div>'">` : '<div class="wz-card-emoji">🐶</div>'}
                      <div class="wz-card-nombre">${m.nombre}</div>
                      <div class="wz-card-sub">${bloqueada ? 'Ya tiene esta membresía activa' : ((m.biografia || '').slice(0, 26) || 'Tu mascota')}</div>
                    </div>`;
        }).join('');

        const cardsComp = COMPORTAMIENTOS.map(c =>
            `<div class="wz-card wz-card-ancha ${W.comportamiento === c.k ? 'sel' : ''}" data-comp="${c.k}">
               <i class="ph ${c.icono}"></i>
               <div><div style="font-weight:700;font-size:.8rem">${c.t}</div><div style="font-size:.68rem;color:#64748b">${c.d}</div></div>
             </div>`
        ).join('');

        $('#wzh-cuerpo').innerHTML = `
        <div class="wz-grid">
          <div>
            <div class="wz-bloque">
              <div class="wz-titulo-seccion"><i class="ph ph-paw-print"></i> 1. Selecciona la mascota</div>
              ${sinMascotas
                ? `<div class="wz-banner-info"><i class="ph ph-info"></i> No tienes mascotas registradas. Ve a <strong>&nbsp;Usuario → tu perfil&nbsp;</strong> y registra tu mascota antes de contratar el plan.</div>`
                : `<div class="wz-cards">${cardsMascotas}</div>`}
            </div>

            <div class="wz-bloque">
              <div class="wz-titulo-seccion"><i class="ph ph-list-checks"></i> 2. Fechas de la estadía</div>
              <div class="wz-form-fila">
                <div class="wz-campo">
                  <label>Fecha de entrada</label>
                  <input type="date" id="wzh-fecha-entrada" value="${W.fecha_entrada}" min="${hoyISO()}">
                </div>
                <div class="wz-campo">
                  <label>Fecha de salida</label>
                  <input type="date" id="wzh-fecha-salida" value="${W.fecha_salida}" min="${sumarDias(W.fecha_entrada, 1)}">
                </div>
                <div class="wz-campo">
                  <label>Noches <span class="op" style="font-weight:400;color:#94a3b8">(${cop(PRECIO_NOCHE_HOSP)} c/u)</span></label>
                  <div class="wz-noches-box" id="wzh-noches-box" style="padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:10px;font-weight:700;color:#334155;background:#f8fafc;">${Math.max(0, calcularNoches(W.fecha_entrada, W.fecha_salida))} noche(s)</div>
                </div>
              </div>
            </div>

            <div class="wz-bloque">
              <div class="wz-titulo-seccion"><i class="ph ph-heart"></i> 3. Cuidados y comportamiento</div>
              <div class="wz-cards">${cardsComp}</div>
            </div>

            <div class="wz-bloque">
              <div class="wz-titulo-seccion"><i class="ph ph-note-pencil"></i> Observaciones adicionales <span class="op" style="font-weight:400;color:#94a3b8">(opcional)</span></div>
              <div class="wz-campo">
                <textarea id="wzh-observaciones" rows="3" maxlength="300"
                  placeholder="Ej: Tiene alergias, toma medicamentos, alimentación especial, rutinas...">${W.observaciones}</textarea>
                <div class="wz-contador"><span id="wzh-obs-count">${W.observaciones.length}</span> / 300</div>
              </div>
            </div>
          </div>
          <div id="wzh-lateral-slot">${lateralHTML(true)}</div>
        </div>`;

        // Listeners
        $$('#wzh-cuerpo [data-mascota]').forEach(c => c.addEventListener('click', () => {
            if (c.dataset.bloqueada) { alertaPaso('Esta mascota ya tiene la membresía activa. Elige otra mascota.'); return; }
            W.id_mascota = parseInt(c.dataset.mascota);
            $$('#wzh-cuerpo [data-mascota]').forEach(x => x.classList.toggle('sel', x === c));
            refrescarLateral(true);
        }));
        $$('#wzh-cuerpo [data-comp]').forEach(c => c.addEventListener('click', () => {
            W.comportamiento = c.dataset.comp;
            $$('#wzh-cuerpo [data-comp]').forEach(x => x.classList.toggle('sel', x === c));
        }));
        $('#wzh-fecha-entrada').addEventListener('change', e => {
            W.fecha_entrada = e.target.value;
            // La salida mínima siempre es 1 noche después de la nueva entrada
            const minSalida = sumarDias(W.fecha_entrada, 1);
            $('#wzh-fecha-salida').min = minSalida;
            if (W.fecha_salida <= W.fecha_entrada) {
                W.fecha_salida = minSalida;
                $('#wzh-fecha-salida').value = minSalida;
            }
            $('#wzh-noches-box').textContent = Math.max(0, calcularNoches(W.fecha_entrada, W.fecha_salida)) + ' noche(s)';
            refrescarLateral(true);
        });
        $('#wzh-fecha-salida').addEventListener('change', e => {
            W.fecha_salida = e.target.value;
            $('#wzh-noches-box').textContent = Math.max(0, calcularNoches(W.fecha_entrada, W.fecha_salida)) + ' noche(s)';
            refrescarLateral(true);
        });
        $('#wzh-observaciones').addEventListener('input', e => {
            W.observaciones = e.target.value;
            $('#wzh-obs-count').textContent = e.target.value.length;
        });

        botonesFooter(false, 'Continuar con ubicación <i class="ph ph-arrow-right"></i>', () => {
            if (!W.id_mascota) return alertaPaso('Selecciona una mascota (o registra una en tu perfil).');
            {
                const mSel = datos.mascotas.find(m => m.id_mascota === W.id_mascota);
                if (mSel && mSel.yaTieneEsta) return alertaPaso('Esta mascota ya tiene la membresía activa. Elige otra mascota.');
            }
            if (!W.fecha_entrada || W.fecha_entrada < hoyISO()) return alertaPaso('La fecha de entrada debe ser hoy o una fecha futura.');
            if (!W.fecha_salida || W.fecha_salida <= W.fecha_entrada) return alertaPaso('La fecha de salida debe ser posterior a la de entrada.');
            if (calcularNoches(W.fecha_entrada, W.fecha_salida) > MAX_NOCHES_HOSPEDAJE) return alertaPaso(`La estadía no puede superar ${MAX_NOCHES_HOSPEDAJE} noches.`);
            renderPaso(2);
        });
    }

    function alertaPaso(msg) {
        // Aviso simple sin perder los datos ya diligenciados
        let al = $('#wzh-alerta-flotante');
        if (!al) {
            al = document.createElement('div');
            al.id = 'wzh-alerta-flotante';
            al.className = 'wz-alerta-error visible';
            al.style.cssText = 'position:absolute;bottom:74px;left:24px;right:24px;z-index:10';
            $('.wz-modal').appendChild(al);
        }
        al.textContent = '⚠ ' + msg;
        al.classList.add('visible');
        clearTimeout(al.__t);
        al.__t = setTimeout(() => al.classList.remove('visible'), 3500);
    }

    // ═══════════════════════════════════════════════════════
    // PASO 2 — DIRECCIÓN Y UBICACIÓN (Leaflet + Nominatim)
    // ═══════════════════════════════════════════════════════
    function renderPaso2() {
        const u = W.ubicacion;
        $('#wzh-cuerpo').innerHTML = `
        <div class="wz-grid" style="grid-template-columns: 340px 1fr 300px">
          <div>
            <div class="wz-titulo-seccion"><i class="ph ph-map-pin"></i> 1. Dirección de recogida y entrega</div>
            <div class="wz-campo">
              <label>Dirección <span style="color:#dc2626">*</span></label>
              <input type="text" id="wzh-dir" value="${u.direccion.replace(/"/g, '&quot;')}" placeholder="Ej: Calle 10 #5-20, Barrio Blanco">
            </div>
            <div class="wz-form-fila">
              <div class="wz-campo">
                <label>Barrio / Sector</label>
                <input type="text" id="wzh-barrio" value="${u.barrio.replace(/"/g, '&quot;')}" placeholder="Ej: Barrio Blanco">
              </div>
            </div>
            <div class="wz-campo">
              <label>Punto de referencia <span class="op">(opcional)</span></label>
              <input type="text" id="wzh-ref" value="${u.referencia.replace(/"/g, '&quot;')}" placeholder="Ej: Casa blanca con portón negro">
            </div>
            <div class="wz-campo">
              <label>Instrucciones para el cuidador <span class="op">(opcional)</span></label>
              <textarea id="wzh-instr" rows="2" maxlength="200" placeholder="Ej: Tocar timbre. La mascota sale con pechera azul.">${u.instrucciones}</textarea>
            </div>

            <div class="wz-titulo-seccion" style="margin-top:14px"><i class="ph ph-crosshair"></i> 2. Confirmar ubicación en el mapa</div>
            <div class="wz-metodos-ubi">
              <div class="wz-metodo-ubi" id="wzh-ubi-gps">
                <i class="ph ph-crosshair-simple"></i>
                <div><div class="t">Usar mi ubicación actual</div><div class="d">Detectar automáticamente</div></div>
              </div>
              <div class="wz-metodo-ubi" id="wzh-ubi-buscar">
                <i class="ph ph-magnifying-glass"></i>
                <div><div class="t">Buscar dirección</div><div class="d">Geocodificar la dirección escrita</div></div>
              </div>
              <div class="wz-metodo-ubi sel">
                <i class="ph ph-hand-pointing"></i>
                <div><div class="t">Ajustar marcador manualmente</div><div class="d">Arrastra el pin o haz clic en el mapa</div></div>
              </div>
            </div>
            <div class="wz-resultados-dir" id="wzh-resultados"></div>
          </div>

          <div>
            <div id="wzh-mapa" class="wz-mapa"></div>
            <div id="wzh-estado-ubi">
              ${u.validada
                ? `<div class="wz-banner-ok"><i class="ph ph-check-circle"></i> Ubicación confirmada — ${Number(u.lat).toFixed(6)}, ${Number(u.lng).toFixed(6)}</div>`
                : `<div class="wz-banner-info"><i class="ph ph-info"></i> Marca el punto exacto donde se recogerá a tu mascota (clic en el mapa, buscar la dirección o usar tu GPS).</div>`}
            </div>
          </div>
          <div id="wzh-lateral-slot">${lateralHTML(false)}</div>
        </div>`;

        // Inputs → estado
        $('#wzh-dir').addEventListener('input', e => { u.direccion = e.target.value; });
        $('#wzh-barrio').addEventListener('input', e => { u.barrio = e.target.value; });
        $('#wzh-ref').addEventListener('input', e => { u.referencia = e.target.value; });
        $('#wzh-instr').addEventListener('input', e => { u.instrucciones = e.target.value; });

        // Mapa Leaflet
        setTimeout(() => iniciarMapaWizard(), 60);

        // GPS del navegador
        $('#wzh-ubi-gps').addEventListener('click', () => {
            if (!('geolocation' in navigator)) return alertaPaso('Tu navegador no soporta geolocalización.');
            navigator.geolocation.getCurrentPosition(
                pos => fijarUbicacion(pos.coords.latitude, pos.coords.longitude, true),
                ()  => alertaPaso('No se pudo obtener tu ubicación. Marca el punto en el mapa.'),
                { enableHighAccuracy: true, timeout: 8000 }
            );
        });

        // Buscar dirección con Nominatim (mismo patrón que el mapa del admin)
        $('#wzh-ubi-buscar').addEventListener('click', () => {
            const q = u.direccion.trim();
            if (q.length < 4) return alertaPaso('Escribe primero la dirección en el campo de arriba.');
            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q + ', Cúcuta, Colombia')}&limit=4&accept-language=es`)
                .then(r => r.json())
                .then(res => {
                    const cont = $('#wzh-resultados');
                    cont.innerHTML = '';
                    if (!res.length) { cont.style.display = 'none'; return alertaPaso('No se encontró esa dirección. Ajusta el marcador manualmente.'); }
                    cont.style.display = 'block';
                    res.forEach(r0 => {
                        const item = document.createElement('div');
                        item.textContent = r0.display_name;
                        item.addEventListener('click', () => {
                            fijarUbicacion(parseFloat(r0.lat), parseFloat(r0.lon), true);
                            cont.style.display = 'none';
                        });
                        cont.appendChild(item);
                    });
                })
                .catch(() => alertaPaso('Error consultando la dirección. Ajusta el marcador manualmente.'));
        });

        botonesFooter(true, 'Confirmar ubicación y continuar <i class="ph ph-arrow-right"></i>', () => {
            if (u.direccion.trim().length < 5) return alertaPaso('Escribe la dirección de recogida.');
            if (!u.validada) return alertaPaso('Confirma la ubicación en el mapa antes de continuar.');
            renderPaso(3);
        });
    }

    function iniciarMapaWizard() {
        if (typeof L === 'undefined') {
            $('#wzh-mapa').innerHTML = '<div style="padding:30px;text-align:center;color:#64748b">No se pudo cargar el mapa. Recarga la página.</div>';
            return;
        }
        if (mapaWz) { mapaWz.remove(); mapaWz = null; }
        const u = W.ubicacion;
        const centro = u.lat ? [u.lat, u.lng] : [7.8939, -72.5078]; // Cúcuta
        mapaWz = L.map('wzh-mapa').setView(centro, u.lat ? 16 : 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap contributors' }).addTo(mapaWz);

        marcadorWz = L.marker(centro, { draggable: true }).addTo(mapaWz);
        marcadorWz.on('dragend', () => {
            const p = marcadorWz.getLatLng();
            fijarUbicacion(p.lat, p.lng, false);
        });
        mapaWz.on('click', e => fijarUbicacion(e.latlng.lat, e.latlng.lng, false));
    }

    function fijarUbicacion(lat, lng, centrar) {
        const u = W.ubicacion;
        u.lat = lat; u.lng = lng; u.validada = true;
        if (marcadorWz) marcadorWz.setLatLng([lat, lng]);
        if (centrar && mapaWz) mapaWz.setView([lat, lng], 16);
        $('#wzh-estado-ubi').innerHTML =
            `<div class="wz-banner-ok"><i class="ph ph-check-circle"></i> Ubicación confirmada — ${lat.toFixed(6)}, ${lng.toFixed(6)}</div>`;
        refrescarLateral(false);
    }

    // ═══════════════════════════════════════════════════════
    // PASO 3 — RESUMEN DEL PEDIDO
    // ═══════════════════════════════════════════════════════
    function renderPaso3() {
        const m = mascotaSel(), pr = calcularPrecio(), u = W.ubicacion;
        const av = m ? normalizarAvatar(m.avatar) : '';
        const comp = COMPORTAMIENTOS.find(c => c.k === W.comportamiento);

        $('#wzh-cuerpo').innerHTML = `
        <div class="wz-grid">
          <div>
            <div style="margin-bottom:14px">
              <div style="font-weight:800;font-size:1rem">Revisa y confirma tu pedido</div>
              <div style="font-size:.78rem;color:#64748b">Verifica que todos los datos estén correctos antes de continuar con el pago.</div>
            </div>

            <div class="wz-resumen-card">
              <div class="wz-rc-head"><span class="t"><i class="ph ph-paw-print"></i> Mascota</span>
                <button class="wz-btn-editar" data-ir="1"><i class="ph ph-pencil-simple"></i> Editar</button></div>
              <div style="display:flex;gap:12px;align-items:center">
                ${av ? `<img src="${av}" style="width:64px;height:64px;border-radius:10px;object-fit:cover" onerror="this.style.display='none'">` : '<div style="font-size:2rem">🐶</div>'}
                <div>
                  <div style="font-weight:700">${m ? m.nombre : '—'}</div>
                  <div class="wz-tags">
                    <span class="wz-tag">${comp ? comp.t : ''}</span>
                  </div>
                  ${W.observaciones ? `<div style="font-size:.74rem;color:#64748b;margin-top:5px"><strong>Observaciones:</strong> ${W.observaciones}</div>` : ''}
                </div>
              </div>
            </div>

            <div class="wz-resumen-card">
              <div class="wz-rc-head"><span class="t"><i class="ph ph-calendar-check"></i> Estadía</span>
                <button class="wz-btn-editar" data-ir="1"><i class="ph ph-pencil-simple"></i> Editar</button></div>
              <div class="wz-datos-grid">
                <span class="d">Entrada:</span><span class="v">${W.fecha_entrada}</span>
                <span class="d">Salida:</span><span class="v">${W.fecha_salida}</span>
                <span class="d">Noches:</span><span class="v">${pr.noches} (${cop(pr.total)} en total)</span>
              </div>
            </div>

            <div class="wz-resumen-card">
              <div class="wz-rc-head"><span class="t"><i class="ph ph-map-pin"></i> Dirección de recogida y entrega</span>
                <button class="wz-btn-editar" data-ir="2"><i class="ph ph-pencil-simple"></i> Editar</button></div>
              <div style="font-size:.82rem;font-weight:600">${u.direccion}</div>
              ${u.barrio ? `<div style="font-size:.76rem;color:#64748b">${u.barrio}, Cúcuta, Norte de Santander</div>` : ''}
              ${u.referencia ? `<div style="font-size:.76rem;color:#64748b;margin-top:4px"><strong>Referencia:</strong> ${u.referencia}</div>` : ''}
              ${u.instrucciones ? `<div style="font-size:.76rem;color:#64748b"><strong>Instrucciones:</strong> ${u.instrucciones}</div>` : ''}
              <span class="wz-tag" style="margin-top:8px;display:inline-block">Ubicación confirmada ✓ (${Number(u.lat).toFixed(5)}, ${Number(u.lng).toFixed(5)})</span>
            </div>

            <div class="wz-banner-info">
              <i class="ph ph-info"></i>
              <div>
                <strong>Información importante:</strong> la reserva queda confirmada desde el momento del pago ·
                la mascota debe llegar sana y con sus vacunas al día ·
                puedes cancelar la reserva hasta con 48 horas de anticipación.
              </div>
            </div>
          </div>
          <div id="wzh-lateral-slot">${lateralHTML(false)}</div>
        </div>`;

        $$('#wzh-cuerpo [data-ir]').forEach(b => b.addEventListener('click', () => renderPaso(parseInt(b.dataset.ir))));
        botonesFooter(true, 'Continuar al pago <i class="ph ph-arrow-right"></i>', () => renderPaso(4));
    }

    // ═══════════════════════════════════════════════════════
    // PASO 4 — PAGO
    // ═══════════════════════════════════════════════════════
    function renderPaso4() {
        const pr = calcularPrecio();
        const g = W.pago, f = W.facturacion;

        $('#wzh-cuerpo').innerHTML = `
        <div class="wz-grid">
          <div>
            <div class="wz-alerta-error" id="wzh-error-pago"></div>

            <div class="wz-bloque">
              <div class="wz-titulo-seccion"><i class="ph ph-credit-card"></i> 1. Método de pago</div>
              <div class="wz-metodos-pago">
                <div class="wz-card wz-card-ancha ${g.metodo === 'tarjeta' ? 'sel' : ''}" data-metodo="tarjeta">
                  <i class="ph ph-credit-card"></i>
                  <div><div style="font-weight:700;font-size:.82rem">Tarjeta débito / crédito</div>
                  <div style="font-size:.66rem;color:#64748b">Visa · Mastercard · Amex</div></div>
                </div>
                <div class="wz-card wz-card-ancha ${g.metodo === 'pse' ? 'sel' : ''}" data-metodo="pse">
                  <i class="ph ph-bank"></i>
                  <div><div style="font-weight:700;font-size:.82rem">PSE</div>
                  <div style="font-size:.66rem;color:#64748b">Débito desde tu banco</div></div>
                </div>
                <div class="wz-card wz-card-ancha deshabilitada" data-metodo="nequi">
                  <i class="ph ph-device-mobile"></i>
                  <div><div style="font-weight:700;font-size:.82rem">Nequi / Daviplata</div>
                  <span class="wz-chip-proximamente">Próximamente</span></div>
                </div>
              </div>
            </div>

            <div class="wz-bloque" id="wzh-form-pago"></div>

            <div class="wz-bloque">
              <div class="wz-titulo-seccion"><i class="ph ph-receipt"></i> 3. Dirección de facturación</div>
              <label class="wz-check-linea">
                <input type="checkbox" id="wzh-fact-perfil" ${f.usar_perfil ? 'checked' : ''}>
                <span>Usar los mismos datos de mi perfil para facturación</span>
              </label>
              <div class="wz-banner-info" style="margin-top:2px"><i class="ph ph-info"></i> La dirección de facturación no modifica la dirección de recogida de tu mascota.</div>
              <div id="wzh-fact-campos" style="display:${f.usar_perfil ? 'none' : 'block'};margin-top:12px">
                <div class="wz-form-fila">
                  <div class="wz-campo"><label>País</label><input id="wzh-f-pais" value="${f.pais}"></div>
                  <div class="wz-campo"><label>Departamento / Región</label><input id="wzh-f-depto" value="${f.departamento}"></div>
                  <div class="wz-campo"><label>Ciudad</label><input id="wzh-f-ciudad" value="${f.ciudad}"></div>
                </div>
                <div class="wz-form-fila">
                  <div class="wz-campo" style="flex:2"><label>Dirección</label><input id="wzh-f-dir" value="${f.direccion}"></div>
                  <div class="wz-campo"><label>Complemento <span class="op">(opcional)</span></label><input id="wzh-f-comp" value="${f.complemento}"></div>
                  <div class="wz-campo"><label>Código postal <span class="op">(opcional)</span></label><input id="wzh-f-cp" value="${f.codigo_postal}"></div>
                </div>
              </div>
            </div>

            <div class="wz-bloque">
              <div class="wz-titulo-seccion"><i class="ph ph-check-square"></i> 4. Confirmaciones</div>
              <label class="wz-check-linea"><input type="checkbox" id="wzh-c-datos" ${W.conf.datos ? 'checked' : ''}>
                <span>Confirmo que los datos del servicio y la dirección de recogida son correctos.</span></label>
              <label class="wz-check-linea"><input type="checkbox" id="wzh-c-terminos" ${W.conf.terminos ? 'checked' : ''}>
                <span>Acepto los <strong>Términos y Condiciones</strong> del servicio de paseos.</span></label>
              <label class="wz-check-linea"><input type="checkbox" id="wzh-c-autorizo" ${W.conf.autorizo ? 'checked' : ''}>
                <span>Autorizo el procesamiento del pago para confirmar mi reserva.</span></label>
            </div>
          </div>

          <div id="wzh-lateral-slot">${lateralHTML(true).replace('wz-lat-aviso', 'wz-lat-aviso ok').replace(
              'La reserva queda confirmada para las fechas que elegiste.',
              'Al completar el pago, tu pedido quedará registrado y pasará a validación para asignación de ruta.')}</div>
        </div>`;

        renderFormPago();

        // Método de pago
        $$('#wzh-cuerpo [data-metodo]').forEach(c => c.addEventListener('click', () => {
            const met = c.dataset.metodo;
            if (met === 'nequi') return alertaPaso('Nequi / Daviplata estará disponible próximamente.');
            W.pago.metodo = met;
            $$('#wzh-cuerpo [data-metodo]').forEach(x => x.classList.toggle('sel', x.dataset.metodo === met));
            renderFormPago();
            validarBotonPagar();
        }));

        // Facturación
        $('#wzh-fact-perfil').addEventListener('change', e => {
            f.usar_perfil = e.target.checked;
            $('#wzh-fact-campos').style.display = f.usar_perfil ? 'none' : 'block';
            validarBotonPagar();
        });
        ['pais', 'depto', 'ciudad', 'dir', 'comp', 'cp'].forEach(k => {
            const el = $('#wzh-f-' + k);
            if (!el) return;
            el.addEventListener('input', () => {
                f.pais = $('#wzh-f-pais').value; f.departamento = $('#wzh-f-depto').value;
                f.ciudad = $('#wzh-f-ciudad').value; f.direccion = $('#wzh-f-dir').value;
                f.complemento = $('#wzh-f-comp').value; f.codigo_postal = $('#wzh-f-cp').value;
                validarBotonPagar();
            });
        });

        // Confirmaciones
        [['datos', 'wzh-c-datos'], ['terminos', 'wzh-c-terminos'], ['autorizo', 'wzh-c-autorizo']].forEach(([k, id]) => {
            $('#' + id).addEventListener('change', e => { W.conf[k] = e.target.checked; validarBotonPagar(); });
        });

        // Footer con el total en el botón
        botonesFooter(true, `<i class="ph ph-lock-simple"></i> Pagar reserva &nbsp; ${pr.cantidad ? cop(pr.total) : ''}`, pagar, 'wzh-btn-pagar');
        $('#wzh-btn-atras').innerHTML = '<i class="ph ph-arrow-left"></i> Volver al resumen';
        validarBotonPagar();
    }

    // Formulario dinámico según método (bloque 2)
    function renderFormPago() {
        const g = W.pago;
        const cont = $('#wzh-form-pago');
        if (g.metodo === 'tarjeta') {
            cont.innerHTML = `
            <div class="wz-titulo-seccion"><i class="ph ph-credit-card"></i> 2. Datos de la tarjeta</div>
            <div class="wz-form-fila">
              <div class="wz-campo" style="flex:2">
                <label>Número de tarjeta</label>
                <input id="wzh-t-numero" inputmode="numeric" autocomplete="off" placeholder="4242 4242 4242 4242" value="${g.numero}" maxlength="19">
                <div class="wz-error" id="wzh-e-numero">Número de tarjeta inválido.</div>
              </div>
              <div class="wz-campo" style="flex:2">
                <label>Nombre del titular</label>
                <input id="wzh-t-titular" value="${g.titular.replace(/"/g, '&quot;')}" placeholder="Como aparece en la tarjeta">
                <div class="wz-error" id="wzh-e-titular">Escribe el nombre del titular.</div>
              </div>
            </div>
            <div class="wz-form-fila">
              <div class="wz-campo">
                <label>Fecha de vencimiento</label>
                <input id="wzh-t-venc" placeholder="MM / AA" value="${g.venc}" maxlength="7" inputmode="numeric" autocomplete="off">
                <div class="wz-error" id="wzh-e-venc">Fecha inválida o vencida.</div>
              </div>
              <div class="wz-campo">
                <label>CVV</label>
                <input id="wzh-t-cvv" type="password" placeholder="123" value="" maxlength="4" inputmode="numeric" autocomplete="off">
                <div class="wz-error" id="wzh-e-cvv">3 o 4 dígitos.</div>
              </div>
              <div class="wz-campo">
                <label>Número de cuotas <span class="op">(opcional)</span></label>
                <select id="wzh-t-cuotas">${[1, 3, 6, 12].map(c => `<option value="${c}" ${g.cuotas === c ? 'selected' : ''}>${c} cuota${c > 1 ? 's' : ''}</option>`).join('')}</select>
              </div>
            </div>
            <div class="wz-nota-verde"><i class="ph ph-lock-simple"></i> Tus datos se procesan de forma segura. Paseo Feliz no almacena la información completa de tu tarjeta.</div>`;

            // Formateo y validación en vivo
            $('#wzh-t-numero').addEventListener('input', e => {
                let v = e.target.value.replace(/\D/g, '').slice(0, 16);
                e.target.value = v.replace(/(\d{4})(?=\d)/g, '$1 ');
                g.numero = e.target.value;
                marcarValidez('wzh-t-numero', 'wzh-e-numero', v.length === 0 || luhnValido(v));
                validarBotonPagar();
            });
            $('#wzh-t-titular').addEventListener('input', e => { g.titular = e.target.value; validarBotonPagar(); });
            $('#wzh-t-venc').addEventListener('input', e => {
                let v = e.target.value.replace(/\D/g, '').slice(0, 4);
                e.target.value = v.length > 2 ? v.slice(0, 2) + ' / ' + v.slice(2) : v;
                g.venc = e.target.value;
                marcarValidez('wzh-t-venc', 'wzh-e-venc', v.length < 4 || vencValido(e.target.value));
                validarBotonPagar();
            });
            $('#wzh-t-cvv').addEventListener('input', e => {
                e.target.value = e.target.value.replace(/\D/g, '').slice(0, 4);
                g.cvv = e.target.value;
                marcarValidez('wzh-t-cvv', 'wzh-e-cvv', g.cvv.length === 0 || g.cvv.length >= 3);
                validarBotonPagar();
            });
            $('#wzh-t-cuotas').addEventListener('change', e => { g.cuotas = parseInt(e.target.value); });
        } else {
            cont.innerHTML = `
            <div class="wz-titulo-seccion"><i class="ph ph-bank"></i> 2. Datos de PSE</div>
            <div class="wz-form-fila">
              <div class="wz-campo">
                <label>Tipo de persona</label>
                <select id="wzh-p-tipo">
                  <option value="natural" ${g.tipo_persona === 'natural' ? 'selected' : ''}>Persona natural</option>
                  <option value="juridica" ${g.tipo_persona === 'juridica' ? 'selected' : ''}>Persona jurídica</option>
                </select>
              </div>
              <div class="wz-campo" style="flex:2">
                <label>Nombre del titular</label>
                <input id="wzh-p-titular" value="${g.titular.replace(/"/g, '&quot;')}">
              </div>
            </div>
            <div class="wz-form-fila">
              <div class="wz-campo">
                <label>Documento</label>
                <input id="wzh-p-doc" value="${g.documento}" inputmode="numeric" placeholder="C.C. o NIT" maxlength="15">
              </div>
              <div class="wz-campo">
                <label>Banco</label>
                <select id="wzh-p-banco">
                  <option value="">— Selecciona tu banco —</option>
                  ${BANCOS_PSE.map(b => `<option ${g.banco === b ? 'selected' : ''}>${b}</option>`).join('')}
                </select>
              </div>
              <div class="wz-campo" style="flex:2">
                <label>Correo de confirmación</label>
                <input id="wzh-p-email" type="email" value="${g.email_confirmacion}">
              </div>
            </div>
            <div class="wz-nota-verde"><i class="ph ph-lock-simple"></i> Serás dirigido a tu banco para completar el débito de forma segura.</div>`;

            $('#wzh-p-tipo').addEventListener('change', e => { g.tipo_persona = e.target.value; });
            $('#wzh-p-titular').addEventListener('input', e => { g.titular = e.target.value; validarBotonPagar(); });
            $('#wzh-p-doc').addEventListener('input', e => { e.target.value = e.target.value.replace(/\D/g, ''); g.documento = e.target.value; validarBotonPagar(); });
            $('#wzh-p-banco').addEventListener('change', e => { g.banco = e.target.value; validarBotonPagar(); });
            $('#wzh-p-email').addEventListener('input', e => { g.email_confirmacion = e.target.value; validarBotonPagar(); });
        }
    }

    function marcarValidez(idInput, idError, ok) {
        const inp = $('#' + idInput), err = $('#' + idError);
        if (!inp) return;
        inp.classList.toggle('invalido', !ok);
        if (err) err.classList.toggle('visible', !ok);
    }

    function pagoValido() {
        const g = W.pago, f = W.facturacion;
        if (!W.conf.datos || !W.conf.terminos || !W.conf.autorizo) return false;
        if (!f.usar_perfil && (!f.pais.trim() || !f.ciudad.trim() || !f.direccion.trim())) return false;
        if (g.metodo === 'tarjeta') {
            return luhnValido(g.numero) && g.titular.trim().length >= 3 && vencValido(g.venc) && g.cvv.length >= 3;
        }
        if (g.metodo === 'pse') {
            return g.titular.trim().length >= 3 && g.documento.length >= 5 && g.banco !== '' &&
                   /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(g.email_confirmacion);
        }
        return false;
    }

    function validarBotonPagar() {
        const btn = $('#wzh-btn-pagar');
        if (btn) btn.disabled = !pagoValido() || procesando;
    }

    // ── Enviar la compra al backend ─────────────────────────
    async function pagar() {
        if (procesando || !pagoValido()) return;
        procesando = true;
        const btn = $('#wzh-btn-pagar');
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="wz-spinner"></span> Procesando pago...';
        $('#wzh-error-pago').classList.remove('visible');

        const g = W.pago;
        const payload = {
            id_mascota: W.id_mascota,
            fecha_entrada: W.fecha_entrada,
            fecha_salida: W.fecha_salida,
            comportamiento: W.comportamiento,
            observaciones: W.observaciones,
            ubicacion: W.ubicacion,
            // SEGURIDAD: nunca se envía el número completo de la tarjeta ni el CVV
            pago: {
                metodo: g.metodo,
                titular: g.titular.trim(),
                ultimos4: g.metodo === 'tarjeta' ? g.numero.replace(/\D/g, '').slice(-4) : null,
                cuotas: g.cuotas,
                banco: g.metodo === 'pse' ? g.banco : null,
                tipo_persona: g.metodo === 'pse' ? g.tipo_persona : null,
                documento: g.metodo === 'pse' ? g.documento : null,
                email_confirmacion: g.metodo === 'pse' ? g.email_confirmacion : null,
            },
            facturacion: W.facturacion,
            confirmaciones: W.conf,
        };

        try {
            const r = await fetch(API_WZ + 'procesar_compra_hospedaje.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await r.json();

            if (!data.success) {
                const err = $('#wzh-error-pago');
                err.textContent = '⚠ ' + (data.message || 'No se pudo procesar el pago.');
                err.classList.add('visible');
                $('#wzh-cuerpo').scrollTop = 0;
                return;
            }
            renderExito(data);
        } catch (e) {
            const err = $('#wzh-error-pago');
            err.textContent = '⚠ Error de conexión al procesar el pago. Tus datos siguen aquí: inténtalo de nuevo.';
            err.classList.add('visible');
            $('#wzh-cuerpo').scrollTop = 0;
        } finally {
            procesando = false;
            if (btn.isConnected) { btn.innerHTML = original; validarBotonPagar(); }
        }
    }

    // ── Pantalla de éxito ───────────────────────────────────
    function renderExito(data) {
        W.__exito = true;
        const m = mascotaSel();
        $('#wzh-progreso').innerHTML = '';
        $('#wzh-subtitulo').innerHTML = '<strong>¡Compra completada!</strong>';
        $('#wzh-cuerpo').innerHTML = `
        <div class="wz-exito">
          <div class="icono"><i class="ph ph-check-circle"></i></div>
          <h3>¡Tu reserva de hospedaje está confirmada! 🎉</h3>
          <p>${m ? m.nombre : 'Tu mascota'} se quedará <strong>${data.noches ?? ''} noche(s)</strong>,
             del ${W.fecha_entrada} al ${W.fecha_salida}. Tu pedido quedó registrado y pasará a asignación de cuidador.</p>
          <div class="ref">
            Pedido <strong>#${data.id_pedido}</strong> · Referencia de pago <strong>${data.referencia}</strong><br>
            Total pagado: <strong>${cop(data.total)}</strong>
          </div>
          <p style="font-size:.76rem">Recibirás notificaciones cuando el cuidador esté asignado.</p>
        </div>`;
        $('#wzh-botones').innerHTML = '<button class="wz-btn wz-btn-prim" id="wzh-btn-fin"><i class="ph ph-paw-print"></i> ¡Entendido!</button>';
        $('#wzh-btn-fin').addEventListener('click', cerrarWizard);
    }
})();