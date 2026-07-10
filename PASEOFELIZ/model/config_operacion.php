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

/**
 * Cupos operativos de asignación (validados server-side al armar el
 * cronograma y al reasignar). Ajustables según crezca la operación.
 *
 * CUPO_MAX_PEDIDOS_DIA:   máximo de mascotas que un paseador puede tener
 *                         asignadas en un mismo día.
 * CUPO_MAX_GRUPAL_FRANJA: máximo de mascotas en modalidad grupal que
 *                         comparten paseador + día + franja horaria
 *                         (el "máx. 4 perros" que se promete al vender).
 * La modalidad individual es EXCLUSIVA: una mascota individual no
 * comparte franja con ninguna otra (ni grupal ni individual).
 */
define('CUPO_MAX_PEDIDOS_DIA', 8);
define('CUPO_MAX_GRUPAL_FRANJA', 4);
?>
