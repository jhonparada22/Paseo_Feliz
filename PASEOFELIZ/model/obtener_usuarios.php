<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once 'conexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_logged']) || $_SESSION['usuario_logged'] !== true) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$sql = "
    SELECT 
        u.id,
        u.nombre,
        u.email,
        u.fecha_registro,
        i.telefono,
        i.direccion,
        i.avatar_url,
        CASE
            WHEN a.id_admin IS NOT NULL THEN 'administrador'
            WHEN p.id_paseador IS NOT NULL THEN 'paseador'
            ELSE 'cliente'
        END AS rol,
        COUNT(DISTINCT m.id_mascota) AS total_mascotas
    FROM usuarios u
    LEFT JOIN info_usuario i ON i.id_usuario = u.id
    LEFT JOIN admin a ON a.id_usuario = u.id
    LEFT JOIN paseadores p ON p.id_usuario = u.id
    LEFT JOIN mascota_usuario m ON m.id_usuario = u.id
    GROUP BY u.id, u.nombre, u.email, u.fecha_registro,
             i.telefono, i.direccion, i.avatar_url, a.id_admin, p.id_paseador
    ORDER BY u.nombre ASC
";

$res = $conn->query($sql);

if (!$res) {
    echo json_encode(['success' => false, 'message' => 'Error en consulta: ' . $conn->error]);
    exit;
}

$usuarios = [];
while ($row = $res->fetch_assoc()) {
    $usuarios[] = [
        'id'            => (int)$row['id'],
        'nombre'        => $row['nombre'],
        'email'         => $row['email'],
        'fechaReg'      => $row['fecha_registro'] ? date('Y-m-d', strtotime($row['fecha_registro'])) : '',
        'telefono'      => $row['telefono'] ?? '',
        'direccion'     => $row['direccion'] ?? '',
        'avatar_url'    => !empty($row['avatar_url'])
            ? 'assets/' . ltrim(preg_replace('#^(\.\./)*assets/#', '', $row['avatar_url']), '/')
            : '',
        'rol'           => $row['rol'],
        'totalMascotas' => (int)$row['total_mascotas'],
    ];
}

echo json_encode(['success' => true, 'usuarios' => $usuarios]);
$conn->close();
?>