/* =========================================================================
   AVISOS DE PASEOS PENDIENTES — Paseo Feliz (paseador)
   Revisa cada minuto los paseos de hoy (individuales y grupales) y, cuando
   faltan 30 minutos o menos para la hora de inicio, avisa al paseador con
   una notificación del navegador + un toast en pantalla. Cada mascota se
   avisa por separado (una sola vez por día, se recuerda en localStorage).

   Se incluye en las páginas del paseador (view/vistas/paseador/*), por
   eso la ruta al modelo sube tres niveles.
   ========================================================================= */
(function () {
    const API_AVISOS = '../../../model/';
    const ANTELACION_MIN = 30;

    // ── Pedir permiso de notificaciones tras el primer toque/clic ──
    function pedirPermiso() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission().catch(() => {});
        }
    }
    document.addEventListener('click', pedirPermiso, { once: true });
    document.addEventListener('touchstart', pedirPermiso, { once: true });

    // ── Toast propio (por si la página no tiene showNotif) ─────────
    function toast(msg) {
        if (typeof window.showNotif === 'function') {
            window.showNotif(msg, 'warning');
            return;
        }
        let el = document.getElementById('avisoPaseoToast');
        if (!el) {
            el = document.createElement('div');
            el.id = 'avisoPaseoToast';
            el.style.cssText =
                'position:fixed;left:50%;bottom:76px;transform:translateX(-50%);' +
                'background:#1e293b;color:#fff;padding:12px 18px;border-radius:12px;' +
                'font:600 .82rem "Segoe UI",sans-serif;z-index:99999;max-width:92vw;' +
                'box-shadow:0 8px 24px rgba(0,0,0,.3);text-align:center;';
            document.body.appendChild(el);
        }
        el.textContent = msg;
        el.style.display = 'block';
        clearTimeout(el._t);
        el._t = setTimeout(() => { el.style.display = 'none'; }, 6000);
    }

    function notificar(titulo, cuerpo) {
        toast(cuerpo);
        if ('Notification' in window && Notification.permission === 'granted') {
            try {
                new Notification(titulo, { body: cuerpo, icon: '../../assets/images/logo.png' });
            } catch (e) { /* algunos navegadores móviles lo restringen */ }
        }
    }

    // ── Chequeo periódico ──────────────────────────────────────────
    function revisarPaseos() {
        fetch(API_AVISOS + 'obtener_paseos_hoy_paseador.php')
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.paseos) return;
                const ahora = new Date();

                data.paseos.forEach(p => {
                    if (p.estado !== 'pendiente') return;
                    if (!p.hora_inicio) return;

                    const [h, m] = p.hora_inicio.split(':').map(Number);
                    const inicio = new Date();
                    inicio.setHours(h, m, 0, 0);

                    const faltanMin = Math.round((inicio - ahora) / 60000);
                    if (faltanMin <= 0 || faltanMin > ANTELACION_MIN) return;

                    const clave = `avisoPaseo_${data.fecha}_${p.id_pedido}`;
                    if (localStorage.getItem(clave)) return;
                    localStorage.setItem(clave, '1');

                    notificar(
                        '🐕 Paseo pendiente — Paseo Feliz',
                        `El paseo de ${p.mascota} (${p.cliente}) empieza a las ${p.hora_inicio} — en ${faltanMin} min.`
                    );
                });
            })
            .catch(() => { /* sin conexión: se reintenta en el próximo ciclo */ });
    }

    // ── Notificaciones del sistema (p. ej. rechazo de una cancelación) ──
    // El paseador no tenía forma de ver las notificaciones que le llegan a
    // la tabla `notificaciones`; aquí se muestran las NUEVAS como toast +
    // notificación del navegador. Baseline en localStorage para no volcar
    // el historial la primera vez.
    function revisarNotificaciones() {
        fetch(API_AVISOS + 'obtener_notificaciones.php')
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.notificaciones || !data.notificaciones.length) return;
                const maxId = Math.max(...data.notificaciones.map(n => n.id));
                const visto = parseInt(localStorage.getItem('pf_notif_ultimo') || '0', 10);
                if (!visto) {                          // primera vez: fijar base, sin avisar
                    localStorage.setItem('pf_notif_ultimo', String(maxId));
                    return;
                }
                data.notificaciones
                    .filter(n => n.id > visto)
                    .sort((a, b) => a.id - b.id)       // de la más antigua a la más nueva
                    .slice(-3)                         // tope por si hay varias
                    .forEach(n => notificar('🔔 Paseo Feliz', n.mensaje));
                localStorage.setItem('pf_notif_ultimo', String(maxId));
            })
            .catch(() => { /* sin conexión: se reintenta en el próximo ciclo */ });
    }

    revisarPaseos();
    revisarNotificaciones();
    setInterval(revisarPaseos, 60000);
    setInterval(revisarNotificaciones, 60000);
})();
