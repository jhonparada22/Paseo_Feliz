// ═══════════════════════════════════════════════════════════════
// SW.JS — Service worker de Paseo Feliz (scope: /view/)
// Hace la app del paseador INSTALABLE (standalone: menos throttling
// del navegador que una pestaña normal) y deja la base lista para
// notificaciones push en el futuro.
//
// Estrategia deliberadamente conservadora: las páginas son PHP con
// sesión y los endpoints devuelven estado vivo, así que NO se cachea
// nada dinámico — todo va a la red. Solo se responde algo mínimo si
// una navegación falla sin conexión.
// ═══════════════════════════════════════════════════════════════
const SW_VERSION = 'pf-sw-v1';

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    // Solo GET; los POST (GPS, confirmaciones) nunca se interceptan
    if (event.request.method !== 'GET') return;

    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(() =>
                new Response(
                    '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">' +
                    '<meta name="viewport" content="width=device-width,initial-scale=1">' +
                    '<title>Sin conexión</title></head>' +
                    '<body style="font-family:sans-serif;text-align:center;padding:60px 20px;color:#334155">' +
                    '<div style="font-size:3rem">🐾</div>' +
                    '<h2>Sin conexión</h2>' +
                    '<p>Paseo Feliz necesita internet para el seguimiento del paseo.<br>' +
                    'Tus confirmaciones pendientes se reintentarán al volver la señal.</p>' +
                    '<button onclick="location.reload()" style="margin-top:14px;padding:10px 22px;' +
                    'border:none;border-radius:10px;background:#3E72A6;color:#fff;font-weight:700">Reintentar</button>' +
                    '</body></html>',
                    { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
                )
            )
        );
    }
    // Resto de GETs (css/js/tiles): red directa, sin cache del SW
});

// ── Notificaciones del sistema (Nivel 1, pfNotificar) ───────────────
// Al tocar la notificación: enfocar la app si ya está abierta, o abrirla.
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((lista) => {
            for (const cliente of lista) {
                if ('focus' in cliente) return cliente.focus();
            }
            return self.clients.openWindow('/');
        })
    );
});
