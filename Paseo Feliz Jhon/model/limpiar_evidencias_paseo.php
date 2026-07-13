<?php
/**
 * limpiar_evidencias_paseo.php
 * Mantenimiento: borra evidencias_paseo (foto + fila) con más de 7 días.
 * Ya se llama de forma oportunista en cada subida (subir_evidencia_paseo.php),
 * pero si el paseador no sube fotos por varios días eso no alcanza —
 * este script está pensado para colgarlo de un cron real del hosting
 * (cPanel: Cron Jobs → una vez al día), así la limpieza no depende de
 * que alguien use la app.
 *
 * Ejemplo de cron (cPanel, PHP CLI):
 *   0 3 * * * php /home/tu_usuario/public_html/principal/model/limpiar_evidencias_paseo.php
 * Si el hosting solo permite crons por URL (wget/curl), también sirve:
 *   0 3 * * * wget -q -O /dev/null https://tudominio.com/principal/model/limpiar_evidencias_paseo.php
 *
 * No requiere sesión (lo dispara el cron, no un usuario logueado). No
 * recibe parámetros del cliente y solo borra por fecha fija, así que no
 * hay superficie de ataque real si alguien más lo llama por accidente.
 */
include_once 'helpers.php';
include_once '../model/conexion.php';

header('Content-Type: application/json; charset=UTF-8');

$borradas = limpiarEvidenciasVencidas($conn, 7);

echo json_encode(['success' => true, 'borradas' => $borradas]);
?>
