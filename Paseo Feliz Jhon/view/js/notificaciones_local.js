/* =========================================================================
   NOTIFICACIONES DEL SISTEMA (Nivel 1) - PASEO FELIZ
   Muestra notificaciones nativas del navegador/teléfono (barra de
   notificaciones, sonido/vibración) cuando la página está minimizada o en
   segundo plano. NO es Web Push: solo funciona mientras la app/pestaña
   siga abierta (aunque sea de fondo) — suficiente para el caso real:
   el cliente espera su paseo con la app minimizada.

   Uso desde cualquier página que incluya este archivo:
     pfNotificar('💬 Mensaje de Ana', 'Hola, ya voy en camino', 'chat-5');
   - Solo dispara si el usuario dio permiso y la pestaña NO está visible
     (si está mirando la página, la UI interna ya se lo muestra).
   - El "tag" agrupa: una notificación nueva con el mismo tag reemplaza a
     la anterior en vez de apilar diez seguidas.
   ========================================================================= */

(function () {
    'use strict';

    if (!('Notification' in window)) {
        window.pfNotificar = function () {};
        return;
    }

    var ICONO = '/view/apk/icon-192.png'; // absoluto: sirve a cualquier profundidad

    // ── Permiso ─────────────────────────────────────────────────────────
    // Los navegadores móviles exigen que la petición de permiso salga de un
    // gesto del usuario; se pide en su primer toque/clic en la página.
    function pedirPermiso() {
        if (Notification.permission === 'default') {
            try { Notification.requestPermission(); } catch (e) { /* Safari viejo */ }
        }
    }
    if (Notification.permission === 'default') {
        document.addEventListener('click', pedirPermiso, { once: true });
        document.addEventListener('touchstart', pedirPermiso, { once: true });
    }

    // ── Mostrar notificación ────────────────────────────────────────────
    // En Android Chrome `new Notification()` está prohibido: hay que pasar
    // por el service worker (el de la PWA ya cubre todo /view/). Se intenta
    // por ahí primero y se cae al constructor clásico en escritorio.
    window.pfNotificar = function (titulo, cuerpo, tag) {
        if (Notification.permission !== 'granted') return;
        if (document.visibilityState === 'visible') return; // la UI interna ya lo muestra

        var opciones = {
            body: cuerpo || '',
            icon: ICONO,
            badge: ICONO,
            tag: tag || 'paseofeliz',
        };

        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistration().then(function (reg) {
                if (reg && reg.showNotification) {
                    reg.showNotification(titulo, opciones);
                } else {
                    try { new Notification(titulo, opciones); } catch (e) { /* Android sin SW */ }
                }
            }).catch(function () {
                try { new Notification(titulo, opciones); } catch (e) { /* sin soporte */ }
            });
        } else {
            try { new Notification(titulo, opciones); } catch (e) { /* sin soporte */ }
        }
    };
})();
