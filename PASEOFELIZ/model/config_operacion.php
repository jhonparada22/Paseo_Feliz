<?php
/**
 * config_operacion.php
 * Flags de operación del módulo de paseos.
 *
 * PERMITIR_GPS_SIMULADO:
 *   true  -> el servidor acepta posiciones marcadas como simuladas
 *            (entorno de pruebas/demo, ej. paseofeliztest.byethost17.com
 *            o XAMPP local, donde el paseador se prueba desde un PC).
 *   false -> PRODUCCIÓN: las posiciones simuladas se rechazan. Sin esto,
 *            un paseador que niega el permiso de GPS podría alimentar el
 *            tracking con posiciones falsas generadas por el navegador.
 *
 * IMPORTANTE: al pasar a producción real, poner este valor en false.
 */
define('PERMITIR_GPS_SIMULADO', true);
?>
