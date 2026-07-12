<?php
/**
 * actividad_feed.php
 * (ADMIN) Único endpoint que alimenta el Centro de Actividad del dashboard.
 * Delega toda la lógica en ActivityService; aquí solo se validan permisos
 * y se normalizan los parámetros de la query.
 *
 * GET:
 *   servicio=paseos|adiestramiento|hospedaje   (omitir = todos)
 *   filtro=todos|hoy|24h|7d|pendientes|urgentes|cancelados|completados
 *   buscar=texto
 *   desde_id=N        poll incremental (solo eventos nuevos)
 *   antes_id=N & antes_fecha=Y-m-d H:i:s   paginación (más antiguos)
 *   limit=N
 * Respuesta: { success, items, hay_mas, contadores }
 */
header("Access-Control-Allow-Origin: *");
include_once 'helpers.php';
include_once '../model/conexion.php';
include_once 'ActivityService.php';

verificarAdmin();

$feed = ActivityService::feed($conn, [
    'servicio'    => $_GET['servicio']    ?? null,
    'filtro'      => $_GET['filtro']      ?? 'todos',
    'buscar'      => $_GET['buscar']      ?? '',
    'desde_id'    => $_GET['desde_id']    ?? 0,
    'antes_id'    => $_GET['antes_id']    ?? 0,
    'antes_fecha' => $_GET['antes_fecha'] ?? '',
    'limit'       => $_GET['limit']       ?? 25,
]);

responder(true, [
    'items'      => $feed['items'],
    'hay_mas'    => $feed['hay_mas'],
    'contadores' => ActivityService::contadores($conn),
]);
?>
