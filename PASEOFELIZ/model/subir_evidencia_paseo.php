<?php
/**
 * subir_evidencia_paseo.php
 * (PASEADOR) Sube una foto del paseo de HOY de una de sus mascotas
 * asignadas — la evidencia que el landing promete al cliente ("Fotos del
 * paseo"). El cliente la ve en su dashboard en el siguiente poll.
 *
 * POST multipart/form-data:
 *   id_pedido (int), foto (file), tipo ('recogida'|'paseo'|'entrega',
 *   opcional, default 'paseo'), nota (opcional, máx 255)
 *
 * Guarda el archivo en view/assets/uploads/evidencias/ (mismo patrón que
 * los avatares) y registra la fila en evidencias_paseo + evento en el log.
 * Requiere migración fase 13.
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once 'helpers_paseos_programados.php';
include_once 'ActivityService.php';
include_once '../model/conexion.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
date_default_timezone_set('America/Bogota');

$idPaseador = obtenerIdPaseadorSesion($conn);
$hoy        = date('Y-m-d');

$idPedido = intval($_POST['id_pedido'] ?? 0);
$tipo     = in_array($_POST['tipo'] ?? '', ['recogida', 'paseo', 'entrega']) ? $_POST['tipo'] : 'paseo';
$nota     = substr(trim($_POST['nota'] ?? ''), 0, 255);

if (!$idPedido) responder(false, [], 'id_pedido requerido.');
if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
    responder(false, [], 'No se recibió la foto (o superó el tamaño máximo del servidor).');
}
if ($_FILES['foto']['size'] > 6 * 1024 * 1024) {
    responder(false, [], 'La foto supera el máximo de 6 MB.');
}

// La foto debe corresponder a un paseo de HOY de ESTE paseador
$stmt = $conn->prepare(
    "SELECT pp.id_paseo, pp.id_usuario_cliente, mu.nombre_mascota
     FROM paseos_programados pp
     LEFT JOIN mascota_usuario mu ON mu.id_mascota = pp.id_mascota
     WHERE pp.id_pedido = ? AND pp.fecha = ? AND pp.id_paseador = ?"
);
$stmt->bind_param("isi", $idPedido, $hoy, $idPaseador);
$stmt->execute();
$pp = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pp) responder(false, [], 'Este paseo no está asignado a ti para hoy.');

$idPaseo   = (int)$pp['id_paseo'];
$idCliente = (int)$pp['id_usuario_cliente'];
$mascota   = $pp['nombre_mascota'] ?: 'tu mascota';

// Validar que sea una imagen real (no solo por extensión)
$info = @getimagesize($_FILES['foto']['tmp_name']);
$mimesOk = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
if (!$info || !isset($mimesOk[$info['mime']])) {
    responder(false, [], 'El archivo debe ser una imagen (JPG, PNG, WEBP o GIF).');
}
$ext = $mimesOk[$info['mime']];

// Guardar en view/assets/uploads/evidencias/ (patrón de los avatares)
$dir = __DIR__ . '/../view/assets/uploads/evidencias/';
if (!is_dir($dir)) mkdir($dir, 0777, true);

$nombre = 'evid_' . $idPaseo . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
if (!move_uploaded_file($_FILES['foto']['tmp_name'], $dir . $nombre)) {
    responder(false, [], 'No se pudo guardar la foto en el servidor.');
}
// URL relativa a las páginas de view/pagina_principal/ (igual que avatares)
$url = '../assets/uploads/evidencias/' . $nombre;

try {
    $stmt = $conn->prepare(
        "INSERT INTO evidencias_paseo (id_paseo, id_pedido, tipo, url, nota) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("iisss", $idPaseo, $idPedido, $tipo, $url, $nota);
    $stmt->execute();
    $idEvidencia = $conn->insert_id;
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    @unlink($dir . $nombre);
    if (ppTablaFaltante($e)) {
        responder(false, [], 'Falta ejecutar la migración fase 13 (evidencias_paseo).');
    }
    throw $e;
}

ppEvento($conn, $idPaseo, 'evidencia', 'paseador', "Foto de $tipo subida");

ActivityService::registrar($conn, [
    'servicio' => 'paseos', 'tipo' => 'evidencia',
    'titulo' => "Foto del paseo de $mascota",
    'descripcion' => 'El paseador envió una foto (' . $tipo . ').',
    'id_cliente' => $idCliente ?: null, 'id_paseador' => $idPaseador,
    'id_pedido' => $idPedido, 'id_referencia' => $idEvidencia,
]);

if ($idCliente) {
    crearNotificacionInterna($conn, $idCliente, null,
        'sistema', "📷 Tu paseador subió una foto del paseo de $mascota. Puedes verla en tu panel.");
}

responder(true, [
    'id_evidencia' => $idEvidencia,
    'url'          => $url,
    'tipo'         => $tipo,
], 'Foto subida. El cliente podrá verla en su panel.');
?>
