<?php
// =========================================================================
// CONTROLADOR DEL CHAT - PASEO FELIZ
// =========================================================================

date_default_timezone_set('America/Bogota');

// Conexión — funciona incluido desde cualquier profundidad
if (file_exists(__DIR__ . '/../model/conexion.php')) {
    include_once __DIR__ . '/../model/conexion.php';
} elseif (file_exists(__DIR__ . '/../../model/conexion.php')) {
    include_once __DIR__ . '/../../model/conexion.php';
} else {
    include_once $_SERVER['DOCUMENT_ROOT'] . '/model/conexion.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_sesion = $_SESSION['usuario_id'] ?? null;

if (!$id_sesion) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Sesión no válida']);
    exit();
}

if (!isset($conn) || !$conn) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Error de conexión a la base de datos']);
    exit();
}

$conn->query("SET time_zone = '-05:00'");
$conn->set_charset("utf8mb4");

// Carpeta de imágenes del chat (relativa al controlador, que vive en /controller/)
$dir_chat = __DIR__ . '/../view/assets/uploads/chat/';

// ── LIMPIADOR AUTOMÁTICO (imágenes > 7 días) ──────────────────────────────
if (is_dir($dir_chat)) {
    $limite = 7 * 24 * 60 * 60;
    foreach (glob($dir_chat . '*') as $archivo) {
        if (is_file($archivo) && (time() - filemtime($archivo)) > $limite) {
            @unlink($archivo);
        }
    }
}

// ── SOLO RESPONDER SI HAY UNA ACCIÓN ──────────────────────────────────────
$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
if (empty($accion)) return; // Incluido sin acción → solo carga la página

header('Content-Type: application/json; charset=utf-8');

// ── [A] SERVIR IMAGEN DEL CHAT ────────────────────────────────────────────
// Evita el 403 de acceso directo a la carpeta en ByetHost
if ($accion === 'servir_imagen') {
    $nombre = basename($_GET['archivo'] ?? '');
    if (empty($nombre)) { http_response_code(400); exit; }

    $ruta = $dir_chat . $nombre;
    if (!file_exists($ruta)) { http_response_code(404); exit; }

    $ext  = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
    $mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
             'gif' => 'image/gif', 'webp' => 'image/webp'][$ext] ?? 'application/octet-stream';

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($ruta));
    header('Cache-Control: public, max-age=604800');
    readfile($ruta);
    exit;
}

// ── [B] LISTAR CONVERSACIONES O BUSCAR USUARIOS ───────────────────────────
if ($accion === 'listar_chats') {
    $buscar = trim($_GET['buscar'] ?? '');

    if ($buscar !== '') {
        // Modo búsqueda: devuelve usuarios con o sin conversación previa
        $param = '%' . $buscar . '%';
        $sql = "SELECT u.id AS id_receptor, u.nombre, iu.avatar_url,
                    (SELECT c.id_conversacion FROM conversaciones c
                     WHERE (c.id_usuario_1 = ? AND c.id_usuario_2 = u.id)
                        OR (c.id_usuario_1 = u.id AND c.id_usuario_2 = ?)
                     LIMIT 1) AS id_conv
                FROM usuarios u
                LEFT JOIN info_usuario iu ON iu.id_usuario = u.id
                WHERE u.id != ? AND u.nombre LIKE ?
                LIMIT 15";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiis", $id_sesion, $id_sesion, $id_sesion, $param);
        $stmt->execute();
        $rows = $stmt->get_result();

        $resultado = [];
        while ($r = $rows->fetch_assoc()) {
            $resultado[] = [
                'id'          => $r['id_conv'],          // null si no existe aún
                'id_receptor' => $r['id_receptor'],
                'nombre'      => $r['nombre'],
                'avatar'      => normalizarAvatar($r['avatar_url']),
                'ultimo'      => $r['id_conv'] ? 'Chat activo' : 'Iniciar conversación',
                'no_leidos'   => 0
            ];
        }
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Modo normal: lista conversaciones existentes
    $sql = "SELECT c.id_conversacion,
                u.id AS id_receptor, u.nombre, iu.avatar_url,
                (SELECT m.mensaje    FROM mensajes m WHERE m.id_conversacion = c.id_conversacion ORDER BY m.id_mensaje DESC LIMIT 1) AS ultimo_msg,
                (SELECT m.ruta_imagen FROM mensajes m WHERE m.id_conversacion = c.id_conversacion ORDER BY m.id_mensaje DESC LIMIT 1) AS ultima_img,
                (SELECT COUNT(*) FROM mensajes m WHERE m.id_conversacion = c.id_conversacion AND m.id_emisor != ? AND m.leido = 0) AS no_leidos
            FROM conversaciones c
            INNER JOIN usuarios u ON (u.id = c.id_usuario_1 OR u.id = c.id_usuario_2) AND u.id != ?
            LEFT JOIN info_usuario iu ON iu.id_usuario = u.id
            WHERE c.id_usuario_1 = ? OR c.id_usuario_2 = ?
            ORDER BY (SELECT MAX(m2.id_mensaje) FROM mensajes m2 WHERE m2.id_conversacion = c.id_conversacion) DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $id_sesion, $id_sesion, $id_sesion, $id_sesion);
    $stmt->execute();
    $rows = $stmt->get_result();

    $chats = [];
    while ($r = $rows->fetch_assoc()) {
        if (!empty($r['ultima_img'])) {
            $previa = '📷 Imagen';
        } elseif (!empty($r['ultimo_msg'])) {
            $previa = mb_strlen($r['ultimo_msg']) > 35
                ? mb_substr($r['ultimo_msg'], 0, 35) . '...'
                : $r['ultimo_msg'];
        } else {
            $previa = 'Escribe un mensaje...';
        }

        $chats[] = [
            'id'          => $r['id_conversacion'],
            'id_receptor' => $r['id_receptor'],
            'nombre'      => $r['nombre'],
            'avatar'      => normalizarAvatar($r['avatar_url']),
            'ultimo'      => $previa,
            'no_leidos'   => (int)$r['no_leidos']
        ];
    }
    echo json_encode($chats, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── [C] CREAR O RECUPERAR CONVERSACIÓN ───────────────────────────────────
if ($accion === 'obtener_o_crear_chat') {
    $id_receptor = intval($_GET['id_receptor'] ?? 0);
    if ($id_receptor === 0 || $id_receptor === $id_sesion) {
        echo json_encode(['error' => 'Usuario inválido']);
        exit;
    }

    $stmt = $conn->prepare("SELECT id_conversacion FROM conversaciones
                             WHERE (id_usuario_1 = ? AND id_usuario_2 = ?)
                                OR (id_usuario_1 = ? AND id_usuario_2 = ?)");
    $stmt->bind_param("iiii", $id_sesion, $id_receptor, $id_receptor, $id_sesion);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($fila = $res->fetch_assoc()) {
        echo json_encode(['id_conversacion' => $fila['id_conversacion']]);
    } else {
        // Guardamos siempre el menor id primero (consistencia)
        $u1 = min($id_sesion, $id_receptor);
        $u2 = max($id_sesion, $id_receptor);
        $ins = $conn->prepare("INSERT INTO conversaciones (id_usuario_1, id_usuario_2) VALUES (?, ?)");
        $ins->bind_param("ii", $u1, $u2);
        if ($ins->execute()) {
            echo json_encode(['id_conversacion' => $ins->insert_id]);
        } else {
            echo json_encode(['error' => 'No se pudo crear la conversación: ' . $conn->error]);
        }
        $ins->close();
    }
    $stmt->close();
    exit;
}

// ── [D] CARGAR MENSAJES ───────────────────────────────────────────────────
if ($accion === 'cargar_mensajes') {
    $id_chat = intval($_GET['id_chat'] ?? 0);

    // Marcar como leídos
    $stmt_r = $conn->prepare("UPDATE mensajes SET leido = 1
                               WHERE id_conversacion = ? AND id_emisor != ? AND leido = 0");
    $stmt_r->bind_param("ii", $id_chat, $id_sesion);
    $stmt_r->execute();
    $stmt_r->close();

    $sql = "SELECT m.id_mensaje, m.id_emisor, m.mensaje, m.ruta_imagen,
                DATE_FORMAT(m.fecha_envio, '%H:%i') AS hora,
                iu.avatar_url
            FROM mensajes m
            LEFT JOIN info_usuario iu ON iu.id_usuario = m.id_emisor
            WHERE m.id_conversacion = ?
            ORDER BY m.id_mensaje ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_chat);
    $stmt->execute();
    $rows = $stmt->get_result();

    $mensajes = [];
    while ($r = $rows->fetch_assoc()) {
        // La imagen se sirve siempre a través de ?accion=servir_imagen
        // Solo guardamos el nombre del archivo en el JSON
        $img = null;
        if (!empty($r['ruta_imagen'])) {
            $img = basename($r['ruta_imagen']);
        }

        $mensajes[] = [
            'id_msg'        => $r['id_mensaje'],
            'id_emisor'     => $r['id_emisor'],
            'de'            => ($r['id_emisor'] == $id_sesion) ? 'yo' : 'ellos',
            'texto'         => $r['mensaje'],
            'imagen'        => $img,   // solo el nombre, el JS construye la URL con servir_imagen
            'hora'          => $r['hora'],
            'avatar_burbuja'=> normalizarAvatar($r['avatar_url'])
        ];
    }
    echo json_encode($mensajes, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── [E] ENVIAR MENSAJE ────────────────────────────────────────────────────
if ($accion === 'enviar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_chat    = intval($_POST['id_conversacion'] ?? 0);
    $texto      = trim($_POST['mensaje'] ?? '') ?: null;
    $ruta_img   = null;

    // Subida de imagen
    if (isset($_FILES['foto_adjunta']) && $_FILES['foto_adjunta']['error'] === UPLOAD_ERR_OK) {
        if (!is_dir($dir_chat)) @mkdir($dir_chat, 0755, true);
        $ext = strtolower(pathinfo($_FILES['foto_adjunta']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $nombre = 'chat_' . time() . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['foto_adjunta']['tmp_name'], $dir_chat . $nombre)) {
                $ruta_img = $nombre; // Solo guardamos el nombre en la BD
            }
        }
    }

    if (empty($texto) && empty($ruta_img)) {
        echo json_encode(['success' => false, 'error' => 'Mensaje vacío']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO mensajes (id_conversacion, id_emisor, mensaje, ruta_imagen)
                             VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $id_chat, $id_sesion, $texto, $ruta_img);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    exit;
}

// ── [F] BORRAR MENSAJE ────────────────────────────────────────────────────
if ($accion === 'borrar_mensaje' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_msg = intval($_POST['id_mensaje'] ?? 0);

    // Primero recuperamos la imagen para borrarla del disco
    $stmt_f = $conn->prepare("SELECT ruta_imagen FROM mensajes WHERE id_mensaje = ? AND id_emisor = ?");
    $stmt_f->bind_param("ii", $id_msg, $id_sesion);
    $stmt_f->execute();
    $res_f = $stmt_f->get_result();

    if ($fila = $res_f->fetch_assoc()) {
        if (!empty($fila['ruta_imagen'])) {
            $archivo = $dir_chat . basename($fila['ruta_imagen']);
            if (file_exists($archivo)) @unlink($archivo);
        }
        $stmt_f->close();

        $stmt_d = $conn->prepare("DELETE FROM mensajes WHERE id_mensaje = ?");
        $stmt_d->bind_param("i", $id_msg);
        $stmt_d->execute();
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
    }
    exit;
}

// ── [G] MARCAR ESCRIBIENDO ────────────────────────────────────────────────
if ($accion === 'marcar_escribiendo') {
    $id_chat = intval($_GET['id_chat'] ?? 0);
    $stmt = $conn->prepare("INSERT INTO escribiendo (id_conversacion, id_usuario) VALUES (?, ?)
                             ON DUPLICATE KEY UPDATE fecha_actualizacion = CURRENT_TIMESTAMP");
    $stmt->bind_param("ii", $id_chat, $id_sesion);
    $stmt->execute();
    echo json_encode(['success' => true]);
    exit;
}

// ── [H] CONSULTAR ESCRIBIENDO ─────────────────────────────────────────────
if ($accion === 'consultar_escribiendo') {
    $id_chat = intval($_GET['id_chat'] ?? 0);
    $conn->query("DELETE FROM escribiendo WHERE fecha_actualizacion < (NOW() - INTERVAL 10 SECOND)");

    $stmt = $conn->prepare("SELECT id_usuario FROM escribiendo
                             WHERE id_conversacion = ? AND id_usuario != ?
                             AND fecha_actualizacion > (NOW() - INTERVAL 4 SECOND)");
    $stmt->bind_param("ii", $id_chat, $id_sesion);
    $stmt->execute();
    echo json_encode(['escribiendo' => $stmt->get_result()->num_rows > 0]);
    exit;
}

// ── FUNCIÓN AUXILIAR: NORMALIZAR RUTA DE AVATAR ───────────────────────────
// Devuelve una URL absoluta desde la raíz del sitio para que funcione
// desde cualquier profundidad de carpeta (usuario o admin).
function normalizarAvatar($url) {
    if (empty($url)) return null;
    // La BD guarda rutas como: ../assets/uploads/avatar_user_8_xxx.jpg
    // Eso es relativo a /view/pagina_principal/ => apunta a /view/assets/uploads/
    // Eliminamos los ../ y anteponemos /view/ para tener URL absoluta desde la raíz
    $limpia = preg_replace('#^(\.\./)+#', '', ltrim($url, '/'));
    // Si ya empieza con "view/" no lo duplicamos
    if (strpos($limpia, 'view/') !== 0) {
        $limpia = 'view/' . $limpia;
    }
    return '/' . $limpia;
}
