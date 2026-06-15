<?php
// =========================================================================
// CONTROLADOR DEL CHAT - PASEO FELIZ
// =========================================================================

// Forzar la zona horaria de Colombia para corregir el desfase de horas en ByetHost
date_default_timezone_set('America/Bogota');

// Incluir la conexión a la base de datos (asumiendo que se llama desde la vista o con la ruta correcta)
// Nota: Ajustamos las rutas relativas pensando en que este archivo se incluye o procesa adecuadamente.
include_once '../../model/conexion.php';

// Asegurar que la sesión esté activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_sesion = $_SESSION['usuario_id'] ?? null;

if (!$id_sesion) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Sesión no válida']);
    exit();
}

// Asegurar que MySQL maneje la hora de la sesión con la zona horaria configurada en PHP
$conn->query("SET time_zone = '-05:00'");
$conn->set_charset("utf8mb4");

// ── LÓGICA DEL LIMPIADOR AUTOMÁTICO (IMÁGENES > 7 DÍAS) ──
$directorio_subidas = "../assets/uploads/chat/";
if (is_dir($directorio_subidas)) {
    $tiempo_limite = 7 * 24 * 60 * 60; 
    $tiempo_actual = time();
    $archivos = glob($directorio_subidas . "*");
    foreach ($archivos as $archivo) {
        if (is_file($archivo)) {
            $fecha_archivo = filemtime($archivo);
            if (($tiempo_actual - $fecha_archivo) > $tiempo_limite) {
                @unlink($archivo); 
                $ruta_buscar = "../assets/uploads/chat/" . basename($archivo);
                $sql_clean = "UPDATE mensajes SET ruta_imagen = NULL WHERE ruta_imagen = ?";
                $stmt_clean = $conn->prepare($sql_clean);
                if ($stmt_clean) {
                    $stmt_clean->bind_param("s", $ruta_buscar);
                    $stmt_clean->execute();
                    $stmt_clean->close();
                }
            }
        }
    }
}

// ── PROCESAMIENTO DE PETICIONES ASÍNCRONAS (API) ──
if (isset($_GET['accion'])) {
    header('Content-Type: application/json; charset=utf-8');
    $accion = $_GET['accion'];

    // [A] LISTAR CONVERSACIONES O BUSCAR USUARIOS NUEVOS
    if ($accion === 'listar_chats') {
        $buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

        if (!empty($buscar)) {
            $buscar_param = "%" . $buscar . "%";
            $sql = "SELECT u.id AS id_receptor, u.nombre, iu.avatar_url,
                    (SELECT c.id_conversacion FROM conversaciones c 
                     WHERE (c.id_usuario_1 = ? AND c.id_usuario_2 = u.id) 
                        OR (c.id_usuario_1 = u.id AND c.id_usuario_2 = ?)) AS id_conversacion
                    FROM usuarios u
                    LEFT JOIN info_usuario iu ON iu.id_usuario = u.id
                    WHERE u.id != ? AND u.nombre LIKE ?
                    LIMIT 15";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiis", $id_sesion, $id_sesion, $id_sesion, $buscar_param);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $resultado = [];
            while($row = $result->fetch_assoc()) {
                $avatar = '../assets/images/logo.png';
                if (!empty($row['avatar_url'])) {
                    $avatar = '../' . ltrim($row['avatar_url'], './');
                }

                $resultado[] = [
                    'id' => $row['id_conversacion'], 
                    'id_receptor' => $row['id_receptor'],
                    'nombre' => $row['nombre'],
                    'avatar' => $avatar, 
                    'ultimo' => ($row['id_conversacion']) ? 'Chat activo...' : 'Usuario nuevo... ¡Haz clic!'
                ];
            }
            echo json_encode($resultado);
        } else {
            $sql = "SELECT c.id_conversacion, u.id AS id_receptor, u.nombre, iu.avatar_url,
                    (SELECT m.mensaje FROM mensajes m WHERE m.id_conversacion = c.id_conversacion ORDER BY m.id_mensaje DESC LIMIT 1) AS ultimo_msg
                    FROM conversaciones c
                    INNER JOIN usuarios u ON (u.id = c.id_usuario_1 OR u.id = c.id_usuario_2) AND u.id != ?
                    LEFT JOIN info_usuario iu ON iu.id_usuario = u.id
                    WHERE c.id_usuario_1 = ? OR c.id_usuario_2 = ?
                    ORDER BY c.fecha_creacion DESC";
                    
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iii", $id_sesion, $id_sesion, $id_sesion);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $chats = [];
            while($row = $result->fetch_assoc()) {
                $avatar = '../assets/images/logo.png';
                if (!empty($row['avatar_url'])) {
                    $avatar = '../' . ltrim($row['avatar_url'], './');
                }

                $chats[] = [
                    'id' => $row['id_conversacion'],
                    'id_receptor' => $row['id_receptor'],
                    'nombre' => $row['nombre'],
                    'avatar' => $avatar,
                    'ultimo' => $row['ultimo_msg'] ?? 'Escribe un mensaje...'
                ];
            }
            echo json_encode($chats);
        }
        exit;
    }

    // [B] CREAR CONVERSACIÓN EN CALIENTE
    if ($accion === 'obtener_o_crear_chat') {
        $id_receptor = intval($_GET['id_receptor'] ?? 0);
        
        $sql_check = "SELECT id_conversacion FROM conversaciones WHERE (id_usuario_1 = ? AND id_usuario_2 = ?) OR (id_usuario_1 = ? AND id_usuario_2 = ?)";
        $stmt = $conn->prepare($sql_check);
        $stmt->bind_param("iiii", $id_sesion, $id_receptor, $id_receptor, $id_sesion);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($row = $res->fetch_assoc()) {
            echo json_encode(['id_conversacion' => $row['id_conversacion']]);
        } else {
            $u1 = ($id_sesion < $id_receptor) ? $id_sesion : $id_receptor;
            $u2 = ($id_sesion > $id_receptor) ? $id_sesion : $id_receptor;
            
            $sql_insert = "INSERT INTO conversaciones (id_usuario_1, id_usuario_2) VALUES (?, ?)";
            $stmt_ins = $conn->prepare($sql_insert);
            $stmt_ins->bind_param("ii", $u1, $u2);
            if ($stmt_ins->execute()) {
                echo json_encode(['id_conversacion' => $stmt_ins->insert_id]);
            } else {
                echo json_encode(['id_conversacion' => null, 'error' => $conn->error]);
            }
        }
        exit;
    }

    // [C] CARGAR HISTORIAL
    if ($accion === 'cargar_mensajes') {
        $id_chat = intval($_GET['id_chat'] ?? 0);

        $sql = "SELECT m.id_mensaje, m.id_emisor, m.mensaje, m.ruta_imagen, DATE_FORMAT(m.fecha_envio, '%H:%i') AS hora, iu.avatar_url 
                FROM mensajes m 
                LEFT JOIN info_usuario iu ON iu.id_usuario = m.id_emisor
                WHERE m.id_conversacion = ? 
                ORDER BY m.id_mensaje ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_chat);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $mensajes = [];
        while($row = $result->fetch_assoc()) {
            $avatar_burbuja = '../assets/images/logo.png';
            if (!empty($row['avatar_url'])) {
                $avatar_burbuja = '../' . ltrim($row['avatar_url'], './');
            }

            $img_path = null;
            if(!empty($row['ruta_imagen'])) {
                $img_path = '../assets/uploads/chat/' . basename($row['ruta_imagen']);
            }

            $mensajes[] = [
                'id_msg' => $row['id_mensaje'],
                'id_emisor' => $row['id_emisor'],
                'de' => ($row['id_emisor'] == $id_sesion) ? 'yo' : 'ellos',
                'texto' => $row['mensaje'],
                'imagen' => $img_path, 
                'hora' => $row['hora'],
                'avatar_burbuja' => $avatar_burbuja
            ];
        }
        echo json_encode($mensajes);
        exit;
    }

    // [D] ENVIAR MENSAJE
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'enviar') {
        $id_chat = intval($_POST['id_conversacion'] ?? 0);
        $texto = isset($_POST['mensaje']) ? trim($_POST['mensaje']) : null;
        $ruta_imagen = null;

        if (isset($_FILES['foto_adjunta']) && $_FILES['foto_adjunta']['error'] === UPLOAD_ERR_OK) {
            if (!is_dir($directorio_subidas)) {
                @mkdir($directorio_subidas, 0755, true);
            }
            $ext = strtolower(pathinfo($_FILES['foto_adjunta']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $nuevo_nombre = "chat_" . time() . "_" . uniqid() . "." . $ext;
                if (move_uploaded_file($_FILES['foto_adjunta']['tmp_name'], $directorio_subidas . $nuevo_nombre)) {
                    $ruta_imagen = "../assets/uploads/chat/" . $nuevo_nombre;
                }
            }
        }

        if (!empty($texto) || !empty($ruta_imagen)) {
            $stmt = $conn->prepare("INSERT INTO mensajes (id_conversacion, id_emisor, mensaje, ruta_imagen) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $id_chat, $id_sesion, $texto, $ruta_imagen);
            $stmt->execute();
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    }

    // [E] ACCIÓN: BORRAR MENSAJE EN BASE DE DATOS
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'borrar_mensaje') {
        $id_msg = intval($_POST['id_mensaje'] ?? 0);

        $stmt = $conn->prepare("DELETE FROM mensajes WHERE id_mensaje = ? AND id_emisor = ?");
        $stmt->bind_param("ii", $id_msg, $id_sesion);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    }
}