// ═══════════════════════════════════════════════════════════════
// WIZARD_ADIESTRAMIENTO.JS — Modal "Contratar Adiestramiento Canino" (4 pasos)
// Paso 1: Mascota y servicio · Paso 2: Dirección y ubicación
// Paso 3: Resumen del pedido · Paso 4: Pago
//
// Se abre con window.abrirWizardAdiestramiento() desde inicio_cliente.js.
// Backend: model/obtener_mi_perfil.php, obtener_mascotas.php,
//          obtener_precios.php y procesar_compra_adiestramiento.php.
// ═══════════════════════════════════════════════════════════════
(function () {
    'use strict';

    const API_WZ = '../../model/';
    const DIAS   = [
        { k: 'lun', n: 'Lun' }, { k: 'mar', n: 'Mar' }, { k: 'mie', n: 'Mié' },
        { k: 'jue', n: 'Jue' }, { k: 'vie', n: 'Vie' }, { k: 'sab', n: 'Sáb' }, { k: 'dom', n: 'Dom' },
    ];
    const FRANJAS = ['6:00 a.m. – 8:00 a.m.', '8:00 a.m. – 11:00 a.m.', '11:00 a.m. – 2:00 p.m.', '2:00 p.m. – 5:00 p.m.'];
    const BANCOS_PSE = ['Bancolombia', 'Banco de Bogotá', 'Davivienda', 'BBVA Colombia', 'Banco de Occidente', 'Banco Popular', 'Banco AV Villas', 'Scotiabank Colpatria'];
    // Ya no se cobra por "pack" (4/8/12): el cliente elige cuántos paseos
    // al mes quiere y se cobra $18.000 por cada uno. El precio real
    // SIEMPRE se valida en el servidor (procesar_compra_adiestramiento.php); esta
    // constante es solo para mostrar el total mientras se edita el pedido.
    // El precio por día y los descuentos se cargan desde obtener_precios.php
    // (configurables por el admin) — el total final SIEMPRE lo valida
    // procesar_compra_adiestramiento.php en el servidor, esto es solo para mostrar
    // el precio mientras el cliente edita el pedido.
    let PRECIO_SESION_ADI = 18000; // valor de respaldo mientras carga
    let DESCUENTOS_ADI = [];   // [{cantidad_minima, descuento_pct}, ...]
    const MIN_SESIONES_MES = 1;
    const MAX_SESIONES_MES = 31;
    const COMPORTAMIENTOS = [
        { k: 'sociable',    icono: 'ph-smiley',         t: 'Sociable',    d: 'Le va bien con otros perros' },
        { k: 'timido',      icono: 'ph-smiley-meh',     t: 'Tímido',      d: 'Necesita tiempo para adaptarse' },
        { k: 'reactivo',    icono: 'ph-warning-circle', t: 'Reactivo',    d: 'Puede reaccionar a otros perros' },
        { k: 'no_sociable', icono: 'ph-smiley-x-eyes',  t: 'No sociable', d: 'Prefiere sesiones individuales' },
    ];

    // ── Estado ──────────────────────────────────────────────
    let datos = { mascotas: [], perfil: null };
    let W = null;              // estado del pedido en curso
    let pasoActual = 1;
    let mapaWz = null, marcadorWz = null;
    let procesando = false;

    function estadoInicial() {
        return {
            id_mascota: null,
            cantidad_sesiones: 8,
            modalidad: 'grupal',
            duracion_min: 60,
            dias: ['lun', 'mie', 'vie'],
            franja: FRANJAS[1],
            fecha_inicio: hoyISO(),
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

    function normalizarAvatar(a) {
        if (!a) return '';
        a = a.replace(/^(\.\.\/)+/, '');
        return a.startsWith('assets/') ? '../' + a : a;
    }

    function calcularPrecio() {
        const cantidad = Math.max(0, parseInt(W.cantidad_sesiones, 10) || 0);
        const subtotal = PRECIO_SESION_ADI * cantidad;
        // Mejor descuento aplicable: el de mayor cantidad_minima que la
        // cantidad sí alcance a cumplir (mismo criterio que el servidor)
        let descuentoPct = 0;
        DESCUENTOS_ADI.forEach(d => {
            if (cantidad >= d.cantidad_minima && d.descuento_pct > descuentoPct) descuentoPct = d.descuento_pct;
        });
        const descuento = Math.round(subtotal * descuentoPct) / 100;
        return { precio_paseo: PRECIO_SESION_ADI, cantidad, subtotal, descuento_pct: descuentoPct, descuento, total: subtotal - descuento };
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
    window.abrirWizardAdiestramiento = async function () {
        inyectarModal();
        W = estadoInicial();
        pasoActual = 1;
        $('#wizard-adiestramiento').classList.add('visible');
        $('#wizard-adiestramiento').style.display = 'flex';
        $('#wza-cuerpo').innerHTML = '<div style="text-align:center;padding:60px;color:#64748b">Cargando tus datos... 🐾</div>';

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
            datos.mascotas.forEach(m => { m.yaTieneEsta = !!(membresiaPorMascota[m.id_mascota]?.adiestramiento); });

            const rPrecios = await fetch(API_WZ + 'obtener_precios.php').then(r => r.json()).catch(() => null);
            if (rPrecios && rPrecios.success && rPrecios.precios.adiestramiento) {
                PRECIO_SESION_ADI = rPrecios.precios.adiestramiento.precio_unidad;
                DESCUENTOS_ADI = rPrecios.descuentos.adiestramiento || [];
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
            $('#wza-cuerpo').innerHTML =
                '<div style="text-align:center;padding:60px;color:#b91c1c">No se pudieron cargar tus datos. Verifica tu conexión e inténtalo de nuevo.</div>';
        }
    };

    // ── Estructura base del modal ───────────────────────────
    function inyectarModal() {
        if (document.getElementById('wizard-adiestramiento')) return;
        const div = document.createElement('div');
        div.id = 'wizard-adiestramiento';
        div.innerHTML = `
        <div class="wz-overlay" id="wza-overlay">
          <div class="wz-modal">
            <div class="wz-header">
              <div class="wz-header-icon"><i class="ph ph-paw-print"></i></div>
              <div>
                <h2>Contratar Adiestramiento Canino</h2>
                <div class="wz-sub" id="wza-subtitulo"></div>
              </div>
              <button class="wz-close" id="wza-cerrar" title="Cerrar"><i class="ph ph-x"></i></button>
            </div>
            <div class="wz-progress" id="wza-progreso"></div>
            <div class="wz-body" id="wza-cuerpo"></div>
            <div class="wz-footer" id="wza-footer">
              <div class="wz-seguro"><i class="ph ph-shield-check"></i> Tu información está protegida</div>
              <div class="wz-botones" id="wza-botones"></div>
            </div>
          </div>
        </div>`;
        document.body.appendChild(div);
        $('#wza-cerrar').addEventListener('click', cerrarWizard);
    }

    function cerrarWizard() {
        $('#wizard-adiestramiento').classList.remove('visible');
        $('#wizard-adiestramiento').style.display = 'none';
        if (mapaWz) { mapaWz.remove(); mapaWz = null; marcadorWz = null; }
        // Si la compra fue exitosa, refrescar los botones de membresía del inicio
        if (W && W.__exito && typeof cargarMembresias === 'function') cargarMembresias();
    }

    // ── Navegación y render ─────────────────────────────────
    const TITULOS = { 1: 'Mascota y detalles del servicio', 2: 'Dirección y ubicación', 3: 'Resumen del pedido', 4: 'Pago' };
    const LBL_PASOS = ['Mascota y servicio', 'Dirección y ubicación', 'Resumen del pedido', 'Pago'];

    function renderPaso(n) {
        pasoActual = n;
        $('#wza-subtitulo').innerHTML = `<strong>Paso ${n} de 4:</strong> ${TITULOS[n]}`;

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
        $('#wza-progreso').innerHTML = html;

        if (n === 1) renderPaso1();
        if (n === 2) renderPaso2();
        if (n === 3) renderPaso3();
        if (n === 4) renderPaso4();
        $('#wza-cuerpo').scrollTop = 0;
    }

    function botonesFooter(atras, siguienteTxt, onSiguiente, siguienteId = 'wza-btn-sig') {
        let html = '';
        if (atras) html += `<button class="wz-btn wz-btn-sec" id="wza-btn-atras"><i class="ph ph-arrow-left"></i> Anterior</button>`;
        else       html += `<button class="wz-btn wz-btn-sec" id="wza-btn-atras">Cancelar</button>`;
        html += `<button class="wz-btn wz-btn-prim" id="${siguienteId}">${siguienteTxt}</button>`;
        $('#wza-botones').innerHTML = html;
        $('#wza-btn-atras').addEventListener('click', () => {
            if (atras) renderPaso(pasoActual - 1);
            else cerrarWizard();
        });
        $('#' + siguienteId).addEventListener('click', onSiguiente);
    }

    // Panel lateral (resumen) reutilizado en pasos 1-4
    function lateralHTML(extendido) {
        const m = mascotaSel(), pr = calcularPrecio();
        const av = m ? normalizarAvatar(m.avatar) : '';
        const diasTxt = W.dias.map(k => DIAS.find(d => d.k === k)?.n).filter(Boolean).join(', ') || '—';
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
          <div style="font-weight:800;font-size:.8rem;margin-bottom:5px">Servicio</div>
          <div class="wz-lat-fila"><span class="d"><i class="ph ph-calendar-blank"></i>Sesiones al mes:</span><span class="v">${pr.cantidad}</span></div>
          <div class="wz-lat-fila"><span class="d"><i class="ph ph-clock"></i>Duración:</span><span class="v">${W.duracion_min} minutos</span></div>
          <div class="wz-lat-fila"><span class="d"><i class="ph ph-users"></i>Modalidad:</span><span class="v">${W.modalidad === 'grupal' ? 'Grupal' : 'Individual'}</span></div>
          <div class="wz-lat-fila"><span class="d"><i class="ph ph-calendar-check"></i>Días:</span><span class="v">${diasTxt}</span></div>
          <div class="wz-lat-fila"><span class="d"><i class="ph ph-clock-clockwise"></i>Horario:</span><span class="v">${W.franja}</span></div>
          <div class="wz-lat-fila"><span class="d"><i class="ph ph-play"></i>Inicio:</span><span class="v">${W.fecha_inicio}</span></div>`;

        if (W.ubicacion.validada) {
            html += `
          <div class="wz-lat-sep"></div>
          <div style="font-weight:800;font-size:.8rem;margin-bottom:5px">Ubicación</div>
          <div style="font-size:.76rem;color:#334155">📍 ${W.ubicacion.direccion}</div>
          <span class="wz-tag" style="margin-top:5px;display:inline-block">Ubicación confirmada ✓</span>`;
        }

        if (pr.cantidad > 0) {
            html += `
          <div class="wz-lat-sep"></div>
          <div style="font-weight:800;font-size:.8rem;margin-bottom:5px">Precio</div>
          <div class="wz-lat-fila"><span class="d">Precio por sesión</span><span class="v">${cop(pr.precio_paseo)}</span></div>
          <div class="wz-lat-fila"><span class="d">Cantidad de sesiones al mes</span><span class="v">${pr.cantidad}</span></div>
          <div class="wz-lat-fila"><span class="d">Subtotal</span><span class="v">${cop(pr.subtotal)}</span></div>
          ${pr.descuento > 0 ? `<div class="wz-lat-fila"><span class="d wz-lat-desc">Descuento (${pr.descuento_pct}%)</span><span class="v wz-lat-desc">-${cop(pr.descuento)}</span></div>` : ''}
          <div class="wz-lat-total"><span>Total mensual</span><span class="monto">${cop(pr.total)}</span></div>`;
        }

        html += `
          <div class="wz-lat-aviso"><i class="ph ph-calendar-blank"></i> La mensualidad comienza el día que elijas como fecha de inicio.</div>`;

        if (extendido) {
            html += `
          <div class="wz-lat-beneficios">
            <div><i class="ph ph-check-circle"></i> Entrenadores certificados</div>
            <div><i class="ph ph-check-circle"></i> Seguimiento en tiempo real</div>
            <div><i class="ph ph-check-circle"></i> Trato amoroso y responsable</div>
          </div>`;
        }
        html += `</div>`;
        return html;
    }

    function refrescarLateral(extendido) {
        const lat = $('#wza-lateral-slot');
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

        const chipsDias = DIAS.map(d =>
            `<span class="wz-dia ${W.dias.includes(d.k) ? 'sel' : ''}" data-dia="${d.k}">${d.n}</span>`
        ).join('');

        const cardsComp = COMPORTAMIENTOS.map(c =>
            `<div class="wz-card wz-card-ancha ${W.comportamiento === c.k ? 'sel' : ''}" data-comp="${c.k}">
               <i class="ph ${c.icono}"></i>
               <div><div style="font-weight:700;font-size:.8rem">${c.t}</div><div style="font-size:.68rem;color:#64748b">${c.d}</div></div>
             </div>`
        ).join('');

        $('#wza-cuerpo').innerHTML = `
        <div class="wz-grid">
          <div>
            <div class="wz-bloque">
              <div class="wz-titulo-seccion"><i class="ph ph-paw-print"></i> 1. Selecciona la mascota</div>
              ${sinMascotas
                ? `<div class="wz-banner-info"><i class="ph ph-info"></i> No tienes mascotas registradas. Ve a <strong>&nbsp;Usuario → tu perfil&nbsp;</strong> y registra tu mascota antes de contratar el plan.</div>`
                : `<div class="wz-cards">${cardsMascotas}</div>`}
            </div>

            <div class="wz-bloque">
              <div class="wz-titulo-seccion"><i class="ph ph-list-checks"></i> 2. Detalles de la sesión</div>
              <div class="wz-form-fila">
                <div class="wz-campo">
                  <label>Sesiones al mes <span class="op" style="font-weight:400;color:#94a3b8">(${cop(PRECIO_SESION_ADI)} c/u)</span></label>
                  <input type="number" id="wza-cantidad" min="${MIN_SESIONES_MES}" max="${MAX_SESIONES_MES}" value="${W.cantidad_sesiones}">
                </div>
                <div class="wz-campo">
                  <label>Duración de la sesión</label>
                  <select id="wza-duracion">
                    <option value="30" ${W.duracion_min === 30 ? 'selected' : ''}>30 minutos</option>
                    <option value="45" ${W.duracion_min === 45 ? 'selected' : ''}>45 minutos</option>
                    <option value="60" ${W.duracion_min === 60 ? 'selected' : ''}>60 minutos</option>
                  </select>
                </div>
                <div class="wz-campo">
                  <label>Modalidad</label>
                  <select id="wza-modalidad">
                    <option value="grupal" ${W.modalidad === 'grupal' ? 'selected' : ''}>Grupal (máx. 4 perros)</option>
                    <option value="individual" ${W.modalidad === 'individual' ? 'selected' : ''}>Individual</option>
                  </select>
                </div>
              </div>
              <div class="wz-form-fila">
                <div class="wz-campo">
                  <label>Días preferidos</label>
                  <div class="wz-dias">${chipsDias}</div>
                </div>
                <div class="wz-campo">
                  <label>Franja horaria preferida</label>
                  <select id="wza-franja">${FRANJAS.map(f => `<option ${W.franja === f ? 'selected' : ''}>${f}</option>`).join('')}</select>
                </div>
                <div class="wz-campo">
                  <label>Inicio de la membresía</label>
                  <input type="date" id="wza-fecha-inicio" value="${W.fecha_inicio}" min="${hoyISO()}">
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
                <textarea id="wza-observaciones" rows="3" maxlength="300"
                  placeholder="Ej: Tiene alergias, toma medicamentos, miedos, instrucciones especiales...">${W.observaciones}</textarea>
                <div class="wz-contador"><span id="wza-obs-count">${W.observaciones.length}</span> / 300</div>
              </div>
            </div>
          </div>
          <div id="wza-lateral-slot">${lateralHTML(true)}</div>
        </div>`;

        // Listeners
        $$('#wza-cuerpo [data-mascota]').forEach(c => c.addEventListener('click', () => {
            if (c.dataset.bloqueada) { alertaPaso('Esta mascota ya tiene la membresía activa. Elige otra mascota.'); return; }
            W.id_mascota = parseInt(c.dataset.mascota);
            $$('#wza-cuerpo [data-mascota]').forEach(x => x.classList.toggle('sel', x === c));
            refrescarLateral(true);
        }));
        $$('#wza-cuerpo [data-comp]').forEach(c => c.addEventListener('click', () => {
            W.comportamiento = c.dataset.comp;
            $$('#wza-cuerpo [data-comp]').forEach(x => x.classList.toggle('sel', x === c));
        }));
        $$('#wza-cuerpo [data-dia]').forEach(ch => ch.addEventListener('click', () => {
            const k = ch.dataset.dia;
            W.dias = W.dias.includes(k) ? W.dias.filter(x => x !== k) : [...W.dias, k];
            ch.classList.toggle('sel');
            refrescarLateral(true);
        }));
        $('#wza-cantidad').addEventListener('input', e => {
            let v = parseInt(e.target.value, 10);
            if (isNaN(v)) v = 0;
            if (v > MAX_SESIONES_MES) v = MAX_SESIONES_MES;
            W.cantidad_sesiones = v;
            refrescarLateral(true);
        });
        $('#wza-duracion').addEventListener('change', e => { W.duracion_min = parseInt(e.target.value); refrescarLateral(true); });
        $('#wza-modalidad').addEventListener('change', e => { W.modalidad = e.target.value; refrescarLateral(true); });
        $('#wza-franja').addEventListener('change', e => { W.franja = e.target.value; refrescarLateral(true); });
        $('#wza-fecha-inicio').addEventListener('change', e => { W.fecha_inicio = e.target.value; refrescarLateral(true); });
        $('#wza-observaciones').addEventListener('input', e => {
            W.observaciones = e.target.value;
            $('#wza-obs-count').textContent = e.target.value.length;
        });

        botonesFooter(false, 'Continuar con ubicación <i class="ph ph-arrow-right"></i>', () => {
            if (!W.id_mascota) return alertaPaso('Selecciona una mascota (o registra una en tu perfil).');
            {
                const mSel = datos.mascotas.find(m => m.id_mascota === W.id_mascota);
                if (mSel && mSel.yaTieneEsta) return alertaPaso('Esta mascota ya tiene la membresía activa. Elige otra mascota.');
            }
            if (!W.cantidad_sesiones || W.cantidad_sesiones < MIN_SESIONES_MES) return alertaPaso(`Ingresa cuántos sesiones al mes quieres (mínimo ${MIN_SESIONES_MES}).`);
            if (!W.dias.length) return alertaPaso('Elige al menos un día preferido.');
            if (!W.fecha_inicio || W.fecha_inicio < hoyISO()) return alertaPaso('La fecha de inicio debe ser hoy o una fecha futura.');
            renderPaso(2);
        });
    }

    function alertaPaso(msg) {
        // Aviso simple sin perder los datos ya diligenciados
        let al = $('#wza-alerta-flotante');
        if (!al) {
            al = document.createElement('div');
            al.id = 'wza-alerta-flotante';
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
        $('#wza-cuerpo').innerHTML = `
        <div class="wz-grid" style="grid-template-columns: 340px 1fr 300px">
          <div>
            <div class="wz-titulo-seccion"><i class="ph ph-map-pin"></i> 1. Dirección de recogida y entrega</div>
            <div class="wz-campo">
              <label>Dirección <span style="color:#dc2626">*</span></label>
              <input type="text" id="wza-dir" value="${u.direccion.replace(/"/g, '&quot;')}" placeholder="Ej: Calle 10 #5-20, Barrio Blanco">
            </div>
            <div class="wz-form-fila">
              <div class="wz-campo">
                <label>Barrio / Sector</label>
                <input type="text" id="wza-barrio" value="${u.barrio.replace(/"/g, '&quot;')}" placeholder="Ej: Barrio Blanco">
              </div>
            </div>
            <div class="wz-campo">
              <label>Punto de referencia <span class="op">(opcional)</span></label>
              <input type="text" id="wza-ref" value="${u.referencia.replace(/"/g, '&quot;')}" placeholder="Ej: Casa blanca con portón negro">
            </div>
            <div class="wz-campo">
              <label>Instrucciones para el entrenador <span class="op">(opcional)</span></label>
              <textarea id="wza-instr" rows="2" maxlength="200" placeholder="Ej: Tocar timbre. La mascota sale con pechera azul.">${u.instrucciones}</textarea>
            </div>

            <div class="wz-titulo-seccion" style="margin-top:14px"><i class="ph ph-crosshair"></i> 2. Confirmar ubicación en el mapa</div>
            <div class="wz-metodos-ubi">
              <div class="wz-metodo-ubi" id="wza-ubi-gps">
                <i class="ph ph-crosshair-simple"></i>
                <div><div class="t">Usar mi ubicación actual</div><div class="d">Detectar automáticamente</div></div>
              </div>
              <div class="wz-metodo-ubi" id="wza-ubi-buscar">
                <i class="ph ph-magnifying-glass"></i>
                <div><div class="t">Buscar dirección</div><div class="d">Geocodificar la dirección escrita</div></div>
              </div>
              <div class="wz-metodo-ubi sel">
                <i class="ph ph-hand-pointing"></i>
                <div><div class="t">Ajustar marcador manualmente</div><div class="d">Arrastra el pin o haz clic en el mapa</div></div>
              </div>
            </div>
            <div class="wz-resultados-dir" id="wza-resultados"></div>
          </div>

          <div>
            <div id="wza-mapa" class="wz-mapa"></div>
            <div id="wza-estado-ubi">
              ${u.validada
                ? `<div class="wz-banner-ok"><i class="ph ph-check-circle"></i> Ubicación confirmada — ${Number(u.lat).toFixed(6)}, ${Number(u.lng).toFixed(6)}</div>`
                : `<div class="wz-banner-info"><i class="ph ph-info"></i> Marca el punto exacto donde se recogerá a tu mascota (clic en el mapa, buscar la dirección o usar tu GPS).</div>`}
            </div>
          </div>
          <div id="wza-lateral-slot">${lateralHTML(false)}</div>
        </div>`;

        // Inputs → estado
        $('#wza-dir').addEventListener('input', e => { u.direccion = e.target.value; });
        $('#wza-barrio').addEventListener('input', e => { u.barrio = e.target.value; });
        $('#wza-ref').addEventListener('input', e => { u.referencia = e.target.value; });
        $('#wza-instr').addEventListener('input', e => { u.instrucciones = e.target.value; });

        // Mapa Leaflet
        setTimeout(() => iniciarMapaWizard(), 60);

        // GPS del navegador
        $('#wza-ubi-gps').addEventListener('click', () => {
            if (!('geolocation' in navigator)) return alertaPaso('Tu navegador no soporta geolocalización.');
            navigator.geolocation.getCurrentPosition(
                pos => fijarUbicacion(pos.coords.latitude, pos.coords.longitude, true),
                ()  => alertaPaso('No se pudo obtener tu ubicación. Marca el punto en el mapa.'),
                { enableHighAccuracy: true, timeout: 8000 }
            );
        });

        // Buscar dirección con Nominatim (mismo patrón que el mapa del admin)
        $('#wza-ubi-buscar').addEventListener('click', () => {
            const q = u.direccion.trim();
            if (q.length < 4) return alertaPaso('Escribe primero la dirección en el campo de arriba.');
            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q + ', Cúcuta, Colombia')}&limit=4&accept-language=es`)
                .then(r => r.json())
                .then(res => {
                    const cont = $('#wza-resultados');
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
            $('#wza-mapa').innerHTML = '<div style="padding:30px;text-align:center;color:#64748b">No se pudo cargar el mapa. Recarga la página.</div>';
            return;
        }
        if (mapaWz) { mapaWz.remove(); mapaWz = null; }
        const u = W.ubicacion;
        const centro = u.lat ? [u.lat, u.lng] : [7.8939, -72.5078]; // Cúcuta
        mapaWz = L.map('wza-mapa').setView(centro, u.lat ? 16 : 13);
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
        $('#wza-estado-ubi').innerHTML =
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
        const diasTxt = W.dias.map(k => DIAS.find(d => d.k === k)?.n).filter(Boolean).join(', ');

        $('#wza-cuerpo').innerHTML = `
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
                    <span class="wz-tag">${W.modalidad === 'grupal' ? 'Sesión en grupo' : 'Sesión individual'}</span>
                  </div>
                  ${W.observaciones ? `<div style="font-size:.74rem;color:#64748b;margin-top:5px"><strong>Observaciones:</strong> ${W.observaciones}</div>` : ''}
                </div>
              </div>
            </div>

            <div class="wz-resumen-card">
              <div class="wz-rc-head"><span class="t"><i class="ph ph-calendar-check"></i> Servicio contratado</span>
                <button class="wz-btn-editar" data-ir="1"><i class="ph ph-pencil-simple"></i> Editar</button></div>
              <div class="wz-datos-grid">
                <span class="d">Sesiones al mes:</span><span class="v">${pr.cantidad} (${cop(pr.total)}/mes)</span>
                <span class="d">Duración por sesión:</span><span class="v">${W.duracion_min} minutos</span>
                <span class="d">Modalidad:</span><span class="v">${W.modalidad === 'grupal' ? 'Grupal (máx. 4 perros)' : 'Individual'}</span>
                <span class="d">Días preferidos:</span><span class="v">${diasTxt}</span>
                <span class="d">Horario preferido:</span><span class="v">${W.franja}</span>
                <span class="d">Inicio de la membresía:</span><span class="v">${W.fecha_inicio}</span>
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
                <strong>Información importante:</strong> la mensualidad inicia en la fecha seleccionada ·
                las sesiones se programarán según disponibilidad del entrenador y tu horario preferido ·
                puedes pausar tu membresía hasta con 7 días de anticipación.
              </div>
            </div>
          </div>
          <div id="wza-lateral-slot">${lateralHTML(false)}</div>
        </div>`;

        $$('#wza-cuerpo [data-ir]').forEach(b => b.addEventListener('click', () => renderPaso(parseInt(b.dataset.ir))));
        botonesFooter(true, 'Continuar al pago <i class="ph ph-arrow-right"></i>', () => renderPaso(4));
    }

    // ═══════════════════════════════════════════════════════
    // PASO 4 — PAGO
    // ═══════════════════════════════════════════════════════
    function renderPaso4() {
        const pr = calcularPrecio();
        const g = W.pago, f = W.facturacion;

        $('#wza-cuerpo').innerHTML = `
        <div class="wz-grid">
          <div>
            <div class="wz-alerta-error" id="wza-error-pago"></div>

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

            <div class="wz-bloque" id="wza-form-pago"></div>

            <div class="wz-bloque">
              <div class="wz-titulo-seccion"><i class="ph ph-receipt"></i> 3. Dirección de facturación</div>
              <label class="wz-check-linea">
                <input type="checkbox" id="wza-fact-perfil" ${f.usar_perfil ? 'checked' : ''}>
                <span>Usar los mismos datos de mi perfil para facturación</span>
              </label>
              <div class="wz-banner-info" style="margin-top:2px"><i class="ph ph-info"></i> La dirección de facturación no modifica la dirección de recogida de tu mascota.</div>
              <div id="wza-fact-campos" style="display:${f.usar_perfil ? 'none' : 'block'};margin-top:12px">
                <div class="wz-form-fila">
                  <div class="wz-campo"><label>País</label><input id="wza-f-pais" value="${f.pais}"></div>
                  <div class="wz-campo"><label>Departamento / Región</label><input id="wza-f-depto" value="${f.departamento}"></div>
                  <div class="wz-campo"><label>Ciudad</label><input id="wza-f-ciudad" value="${f.ciudad}"></div>
                </div>
                <div class="wz-form-fila">
                  <div class="wz-campo" style="flex:2"><label>Dirección</label><input id="wza-f-dir" value="${f.direccion}"></div>
                  <div class="wz-campo"><label>Complemento <span class="op">(opcional)</span></label><input id="wza-f-comp" value="${f.complemento}"></div>
                  <div class="wz-campo"><label>Código postal <span class="op">(opcional)</span></label><input id="wza-f-cp" value="${f.codigo_postal}"></div>
                </div>
              </div>
            </div>

            <div class="wz-bloque">
              <div class="wz-titulo-seccion"><i class="ph ph-check-square"></i> 4. Confirmaciones</div>
              <label class="wz-check-linea"><input type="checkbox" id="wza-c-datos" ${W.conf.datos ? 'checked' : ''}>
                <span>Confirmo que los datos del servicio y la dirección de recogida son correctos.</span></label>
              <label class="wz-check-linea"><input type="checkbox" id="wza-c-terminos" ${W.conf.terminos ? 'checked' : ''}>
                <span>Acepto los <strong>Términos y Condiciones</strong> del servicio de adiestramiento.</span></label>
              <label class="wz-check-linea"><input type="checkbox" id="wza-c-autorizo" ${W.conf.autorizo ? 'checked' : ''}>
                <span>Autorizo el procesamiento del pago para activar mi membresía.</span></label>
            </div>
          </div>

          <div id="wza-lateral-slot">${lateralHTML(true).replace('wz-lat-aviso', 'wz-lat-aviso ok').replace(
              'La mensualidad comienza el día que elijas como fecha de inicio.',
              'Al completar el pago, tu pedido quedará registrado y pasará a asignación de entrenador.')}</div>
        </div>`;

        renderFormPago();

        // Método de pago
        $$('#wza-cuerpo [data-metodo]').forEach(c => c.addEventListener('click', () => {
            const met = c.dataset.metodo;
            if (met === 'nequi') return alertaPaso('Nequi / Daviplata estará disponible próximamente.');
            W.pago.metodo = met;
            $$('#wza-cuerpo [data-metodo]').forEach(x => x.classList.toggle('sel', x.dataset.metodo === met));
            renderFormPago();
            validarBotonPagar();
        }));

        // Facturación
        $('#wza-fact-perfil').addEventListener('change', e => {
            f.usar_perfil = e.target.checked;
            $('#wza-fact-campos').style.display = f.usar_perfil ? 'none' : 'block';
            validarBotonPagar();
        });
        ['pais', 'depto', 'ciudad', 'dir', 'comp', 'cp'].forEach(k => {
            const el = $('#wza-f-' + k);
            if (!el) return;
            el.addEventListener('input', () => {
                f.pais = $('#wza-f-pais').value; f.departamento = $('#wza-f-depto').value;
                f.ciudad = $('#wza-f-ciudad').value; f.direccion = $('#wza-f-dir').value;
                f.complemento = $('#wza-f-comp').value; f.codigo_postal = $('#wza-f-cp').value;
                validarBotonPagar();
            });
        });

        // Confirmaciones
        [['datos', 'wza-c-datos'], ['terminos', 'wza-c-terminos'], ['autorizo', 'wza-c-autorizo']].forEach(([k, id]) => {
            $('#' + id).addEventListener('change', e => { W.conf[k] = e.target.checked; validarBotonPagar(); });
        });

        // Footer con el total en el botón
        botonesFooter(true, `<i class="ph ph-lock-simple"></i> Pagar membresía &nbsp; ${pr.cantidad ? cop(pr.total) : ''}`, pagar, 'wza-btn-pagar');
        $('#wza-btn-atras').innerHTML = '<i class="ph ph-arrow-left"></i> Volver al resumen';
        validarBotonPagar();
    }

    // Formulario dinámico según método (bloque 2)
    function renderFormPago() {
        const g = W.pago;
        const cont = $('#wza-form-pago');
        if (g.metodo === 'tarjeta') {
            cont.innerHTML = `
            <div class="wz-titulo-seccion"><i class="ph ph-credit-card"></i> 2. Datos de la tarjeta</div>
            <div class="wz-form-fila">
              <div class="wz-campo" style="flex:2">
                <label>Número de tarjeta</label>
                <input id="wza-t-numero" inputmode="numeric" autocomplete="off" placeholder="4242 4242 4242 4242" value="${g.numero}" maxlength="19">
                <div class="wz-error" id="wza-e-numero">Número de tarjeta inválido.</div>
              </div>
              <div class="wz-campo" style="flex:2">
                <label>Nombre del titular</label>
                <input id="wza-t-titular" value="${g.titular.replace(/"/g, '&quot;')}" placeholder="Como aparece en la tarjeta">
                <div class="wz-error" id="wza-e-titular">Escribe el nombre del titular.</div>
              </div>
            </div>
            <div class="wz-form-fila">
              <div class="wz-campo">
                <label>Fecha de vencimiento</label>
                <input id="wza-t-venc" placeholder="MM / AA" value="${g.venc}" maxlength="7" inputmode="numeric" autocomplete="off">
                <div class="wz-error" id="wza-e-venc">Fecha inválida o vencida.</div>
              </div>
              <div class="wz-campo">
                <label>CVV</label>
                <input id="wza-t-cvv" type="password" placeholder="123" value="" maxlength="4" inputmode="numeric" autocomplete="off">
                <div class="wz-error" id="wza-e-cvv">3 o 4 dígitos.</div>
              </div>
              <div class="wz-campo">
                <label>Número de cuotas <span class="op">(opcional)</span></label>
                <select id="wza-t-cuotas">${[1, 3, 6, 12].map(c => `<option value="${c}" ${g.cuotas === c ? 'selected' : ''}>${c} cuota${c > 1 ? 's' : ''}</option>`).join('')}</select>
              </div>
            </div>
            <div class="wz-nota-verde"><i class="ph ph-lock-simple"></i> Tus datos se procesan de forma segura. Paseo Feliz no almacena la información completa de tu tarjeta.</div>`;

            // Formateo y validación en vivo
            $('#wza-t-numero').addEventListener('input', e => {
                let v = e.target.value.replace(/\D/g, '').slice(0, 16);
                e.target.value = v.replace(/(\d{4})(?=\d)/g, '$1 ');
                g.numero = e.target.value;
                marcarValidez('wza-t-numero', 'wza-e-numero', v.length === 0 || luhnValido(v));
                validarBotonPagar();
            });
            $('#wza-t-titular').addEventListener('input', e => { g.titular = e.target.value; validarBotonPagar(); });
            $('#wza-t-venc').addEventListener('input', e => {
                let v = e.target.value.replace(/\D/g, '').slice(0, 4);
                e.target.value = v.length > 2 ? v.slice(0, 2) + ' / ' + v.slice(2) : v;
                g.venc = e.target.value;
                marcarValidez('wza-t-venc', 'wza-e-venc', v.length < 4 || vencValido(e.target.value));
                validarBotonPagar();
            });
            $('#wza-t-cvv').addEventListener('input', e => {
                e.target.value = e.target.value.replace(/\D/g, '').slice(0, 4);
                g.cvv = e.target.value;
                marcarValidez('wza-t-cvv', 'wza-e-cvv', g.cvv.length === 0 || g.cvv.length >= 3);
                validarBotonPagar();
            });
            $('#wza-t-cuotas').addEventListener('change', e => { g.cuotas = parseInt(e.target.value); });
        } else {
            cont.innerHTML = `
            <div class="wz-titulo-seccion"><i class="ph ph-bank"></i> 2. Datos de PSE</div>
            <div class="wz-form-fila">
              <div class="wz-campo">
                <label>Tipo de persona</label>
                <select id="wza-p-tipo">
                  <option value="natural" ${g.tipo_persona === 'natural' ? 'selected' : ''}>Persona natural</option>
                  <option value="juridica" ${g.tipo_persona === 'juridica' ? 'selected' : ''}>Persona jurídica</option>
                </select>
              </div>
              <div class="wz-campo" style="flex:2">
                <label>Nombre del titular</label>
                <input id="wza-p-titular" value="${g.titular.replace(/"/g, '&quot;')}">
              </div>
            </div>
            <div class="wz-form-fila">
              <div class="wz-campo">
                <label>Documento</label>
                <input id="wza-p-doc" value="${g.documento}" inputmode="numeric" placeholder="C.C. o NIT" maxlength="15">
              </div>
              <div class="wz-campo">
                <label>Banco</label>
                <select id="wza-p-banco">
                  <option value="">— Selecciona tu banco —</option>
                  ${BANCOS_PSE.map(b => `<option ${g.banco === b ? 'selected' : ''}>${b}</option>`).join('')}
                </select>
              </div>
              <div class="wz-campo" style="flex:2">
                <label>Correo de confirmación</label>
                <input id="wza-p-email" type="email" value="${g.email_confirmacion}">
              </div>
            </div>
            <div class="wz-nota-verde"><i class="ph ph-lock-simple"></i> Serás dirigido a tu banco para completar el débito de forma segura.</div>`;

            $('#wza-p-tipo').addEventListener('change', e => { g.tipo_persona = e.target.value; });
            $('#wza-p-titular').addEventListener('input', e => { g.titular = e.target.value; validarBotonPagar(); });
            $('#wza-p-doc').addEventListener('input', e => { e.target.value = e.target.value.replace(/\D/g, ''); g.documento = e.target.value; validarBotonPagar(); });
            $('#wza-p-banco').addEventListener('change', e => { g.banco = e.target.value; validarBotonPagar(); });
            $('#wza-p-email').addEventListener('input', e => { g.email_confirmacion = e.target.value; validarBotonPagar(); });
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
        const btn = $('#wza-btn-pagar');
        if (btn) btn.disabled = !pagoValido() || procesando;
    }

    // ── Enviar la compra al backend ─────────────────────────
    async function pagar() {
        if (procesando || !pagoValido()) return;
        procesando = true;
        const btn = $('#wza-btn-pagar');
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="wz-spinner"></span> Procesando pago...';
        $('#wza-error-pago').classList.remove('visible');

        const g = W.pago;
        const payload = {
            id_mascota: W.id_mascota,
            cantidad_sesiones: W.cantidad_sesiones,
            modalidad: W.modalidad,
            duracion_min: W.duracion_min,
            dias_preferidos: W.dias.join(','),
            franja_horaria: W.franja,
            fecha_inicio: W.fecha_inicio,
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
            const r = await fetch(API_WZ + 'procesar_compra_adiestramiento.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await r.json();

            if (!data.success) {
                const err = $('#wza-error-pago');
                err.textContent = '⚠ ' + (data.message || 'No se pudo procesar el pago.');
                err.classList.add('visible');
                $('#wza-cuerpo').scrollTop = 0;
                return;
            }
            renderExito(data);
        } catch (e) {
            const err = $('#wza-error-pago');
            err.textContent = '⚠ Error de conexión al procesar el pago. Tus datos siguen aquí: inténtalo de nuevo.';
            err.classList.add('visible');
            $('#wza-cuerpo').scrollTop = 0;
        } finally {
            procesando = false;
            if (btn.isConnected) { btn.innerHTML = original; validarBotonPagar(); }
        }
    }

    // ── Pantalla de éxito ───────────────────────────────────
    function renderExito(data) {
        W.__exito = true;
        const m = mascotaSel();
        $('#wza-progreso').innerHTML = '';
        $('#wza-subtitulo').innerHTML = '<strong>¡Compra completada!</strong>';
        $('#wza-cuerpo').innerHTML = `
        <div class="wz-exito">
          <div class="icono"><i class="ph ph-check-circle"></i></div>
          <h3>¡Tu membresía de adiestramiento está activa! 🎉</h3>
          <p>${m ? m.nombre : 'Tu mascota'} ya tiene <strong>${W.cantidad_sesiones} sesiones al mes</strong>.
             Tu pedido quedó registrado y pasará a asignación de entrenador.</p>
          <div class="ref">
            Pedido <strong>#${data.id_pedido}</strong> · Referencia de pago <strong>${data.referencia}</strong><br>
            Total pagado: <strong>${cop(data.total)}</strong>
          </div>
          <p style="font-size:.76rem">Recibirás notificaciones cuando el entrenador esté asignado y en cada sesión.</p>
        </div>`;
        $('#wza-botones').innerHTML = '<button class="wz-btn wz-btn-prim" id="wza-btn-fin"><i class="ph ph-paw-print"></i> ¡Entendido!</button>';
        $('#wza-btn-fin').addEventListener('click', cerrarWizard);
    }
})();