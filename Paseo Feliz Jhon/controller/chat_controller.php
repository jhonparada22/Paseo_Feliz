<?php
// =========================================================================
// CONTROLADOR DEL CHAT - PASEO FELIZ
// =========================================================================
//
// REGLAS DE QUIÉN PUEDE HABLAR CON QUIÉN (para INICIAR una conversación
// nueva; una conversación que ya existe nunca se oculta ni se pierde):
//  - usuario  ↔ usuario  : NUNCA permitido.
//  - paseador ↔ paseador : NUNCA permitido.
//  - usuario  ↔ paseador : solo si HOY es uno de los días asignados en el
//                           cronograma de ese paseador para ese cliente
//                           (cronograma_paseos.dia_semana = hoy).
//  - usuario  ↔ admin    : el usuario necesita tener algún servicio activo
//                           (membresias.paseos/adiestramiento/hospedaje = 1).
//  - paseador ↔ admin    : sin restricción.
//  - admin    ↔ cualquiera: sin restricción, y puede activar/desactivar
//                           cualquier conversación con el botón del chat.
//
// El listado de contactos (listar_chats) se agrupa por rol —
// Administradores / Paseadores / Clientes — mostrando primero a quien
// ya tiene una conversación activa (ordenado por el mensaje más reciente,
// como WhatsApp) y luego, alfabéticamente, al resto de contactos con los
// que SÍ se podría iniciar una conversación nueva según las reglas de
// arriba. Una conversación que ya existe siempre aparece, aunque la
// ventana de elegibilidad para iniciar una nueva ya haya pasado.
//
// Una vez que una conversación EXISTE, su disponibilidad para escribir
// (para usuario/paseador) depende de la columna `conversaciones.activo`:
//   1 = puede escribir libremente
//   0 = el campo de texto se reemplaza por un aviso de chat desactivado
// El admin siempre puede escribir, sin importar el estado `activo`.
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

// ── MARCA DE ACTIVIDAD (para el estado en línea / desconectado) ──────────
// Toda petición a este controlador (listar_chats, cargar_mensajes, etc.)
// refresca la marca de tiempo; no hace falta un endpoint de "latido" aparte
// porque mientras el chat está abierto ya se está consultando cada 3-8s.
$stmt_act = $conn->prepare("UPDATE usuarios SET ultima_actividad = NOW() WHERE id = ?");
$stmt_act->bind_param("i", $id_sesion);
$stmt_act->execute();
$stmt_act->close();

// ── ROL DEL USUARIO EN SESIÓN (admin / paseador / usuario) ────────────────
$rol_sesion = 'usuario';

if (isset($_SESSION['usuario_admin']) && $_SESSION['usuario_admin'] === true) {
    $rol_sesion = 'admin';
} else {
    $stmt_rol = $conn->prepare("SELECT id_paseador FROM paseadores WHERE id_usuario = ? LIMIT 1");
    $stmt_rol->bind_param("i", $id_sesion);
    $stmt_rol->execute();
    if ($stmt_rol->get_result()->num_rows > 0) {
        $rol_sesion = 'paseador';
    }
    $stmt_rol->close();
}

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

// ── [B] LISTAR CONTACTOS, AGRUPADOS POR ROL ───────────────────────────────
// "buscar" ya no es un modo aparte: solo filtra por nombre dentro de la
// MISMA lista de contactos permitidos (mismas reglas, con o sin término).
if ($accion === 'listar_chats') {
    $buscar = trim($_GET['buscar'] ?? '');
    $conversaciones = conversacionesDeSesion($conn, $id_sesion);

    // $gruposBase[rol][id_usuario] = ['id'=>, 'nombre'=>, 'avatar_url'=>, ...]
    $gruposBase = ['admin' => [], 'paseador' => [], 'usuario' => []];

    if ($rol_sesion === 'admin') {
        // El admin ve y puede iniciar con CUALQUIER usuario registrado.
        $stmt = $conn->prepare(
            "SELECT u.id, u.nombre, iu.avatar_url,
                    (SELECT COUNT(*) FROM admin a WHERE a.id_usuario = u.id) AS es_admin,
                    (SELECT COUNT(*) FROM paseadores p WHERE p.id_usuario = u.id) AS es_paseador,
                    (u.ultima_actividad IS NOT NULL AND TIMESTAMPDIFF(SECOND, u.ultima_actividad, NOW()) <= 45) AS en_linea
             FROM usuarios u
             LEFT JOIN info_usuario iu ON iu.id_usuario = u.id
             WHERE u.id != ?"
        );
        $stmt->bind_param("i", $id_sesion);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $rolR = obtenerRol($r['es_admin'], $r['es_paseador']);
            $gruposBase[$rolR][(int)$r['id']] = $r;
        }
        $stmt->close();
    } else {
        // Paseador: siempre puede iniciarle a un admin. Cliente: solo si
        // tiene algún servicio activo (paseos/adiestramiento/hospedaje).
        $puedeUsarChat = ($rol_sesion === 'paseador') ? true : tieneServicioActivo($conn, $id_sesion);

        $admins = todosLosAdmins($conn, $id_sesion);
        foreach ($admins as $idR => $datos) {
            if ($puedeUsarChat || isset($conversaciones[$idR])) {
                $gruposBase['admin'][$idR] = $datos;
            }
        }

        if ($rol_sesion === 'paseador') {
            // Clientes: solo los asignados HOY en su cronograma.
            // (paseador ↔ paseador: NUNCA permitido, ni para iniciar ni para
            // ver contactos nuevos — solo se filtra abajo si ya existiera
            // historial de antes, que tampoco se muestra)
            foreach (clientesAsignadosHoy($conn, $id_sesion) as $idR => $datos) {
                $gruposBase['usuario'][$idR] = $datos;
            }
        } elseif ($puedeUsarChat) {
            // Cliente: su paseador asignado HOY (si tiene servicio activo).
            foreach (paseadorAsignadoHoy($conn, $id_sesion) as $idR => $datos) {
                $gruposBase['paseador'][$idR] = $datos;
            }
        }

        // Una conversación que ya existe nunca se pierde, aunque la
        // ventana de elegibilidad para iniciarla de nuevo ya haya pasado.
        foreach ($conversaciones as $idR => $conv) {
            $rolR = obtenerRol($conv['es_admin'], $conv['es_paseador']);
            if ($rol_sesion === 'usuario' && $rolR === 'usuario') continue; // nunca, ni con historial
            if ($rol_sesion === 'paseador' && $rolR === 'paseador') continue; // nunca, ni con historial
            if (!isset($gruposBase[$rolR][$idR])) {
                $gruposBase[$rolR][$idR] = $conv;
            }
        }
    }

    $titulos = ['admin' => 'Administradores', 'paseador' => 'Paseadores', 'usuario' => 'Clientes'];
    $grupos = [];
    foreach (['admin', 'paseador', 'usuario'] as $rolClave) {
        $contactos = [];
        foreach ($gruposBase[$rolClave] as $idR => $datos) {
            if ($buscar !== '' && stripos($datos['nombre'], $buscar) === false) continue;
            $contactos[] = construirContacto($idR, $datos, $conversaciones[$idR] ?? null);
        }
        if (!$contactos) continue;
        $grupos[] = ['rol' => $rolClave, 'titulo' => $titulos[$rolClave], 'contactos' => ordenarContactos($contactos)];
    }

    echo json_encode(['grupos' => $grupos], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── [C] CREAR O RECUPERAR CONVERSACIÓN ───────────────────────────────────
if ($accion === 'obtener_o_crear_chat') {
    $id_receptor = intval($_GET['id_receptor'] ?? 0);
    if ($id_receptor === 0 || $id_receptor === $id_sesion) {
        echo json_encode(['error' => 'Usuario inválido']);
        exit;
    }

    // Verificar si ya existe la conversación (siempre se permite continuarla)
    $stmt_ex = $conn->prepare("SELECT id_conversacion FROM conversaciones
                                WHERE (id_usuario_1 = ? AND id_usuario_2 = ?)
                                   OR (id_usuario_1 = ? AND id_usuario_2 = ?)");
    $stmt_ex->bind_param("iiii", $id_sesion, $id_receptor, $id_receptor, $id_sesion);
    $stmt_ex->execute();
    $ya_existe = $stmt_ex->get_result()->fetch_assoc();
    $stmt_ex->close();

    if (!$ya_existe) {
        // Solo al CREAR una conversación nueva se aplican las restricciones de rol
        $rol_receptor = obtenerRolPorId($conn, $id_receptor);

        if ($rol_sesion === 'usuario') {
            if ($rol_receptor === 'usuario') {
                echo json_encode(['error' => 'No puedes escribirle a otro usuario']);
                exit;
            }
            if (!tieneServicioActivo($conn, $id_sesion)) {
                echo json_encode(['error' => 'Necesitas tener un servicio activo (paseos, adiestramiento u hospedaje) para usar el chat.']);
                exit;
            }
            if ($rol_receptor === 'paseador' && !asignadoHoy($conn, $id_sesion, $id_receptor)) {
                echo json_encode(['error' => 'Solo puedes escribirle a tu paseador asignado el día de su servicio.']);
                exit;
            }
            // usuario → admin: permitido si tiene servicio activo (ya validado arriba)
        } elseif ($rol_sesion === 'paseador') {
            if ($rol_receptor === 'paseador') {
                echo json_encode(['error' => 'No puedes escribirle a otro paseador']);
                exit;
            }
            if ($rol_receptor === 'usuario' && !asignadoHoy($conn, $id_receptor, $id_sesion)) {
                echo json_encode(['error' => 'Solo puedes escribirle a un cliente asignado hoy en tu cronograma.']);
                exit;
            }
            // paseador → admin: sin restricción
        }
        // admin: sin restricción
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
        $ins = $conn->prepare("INSERT INTO conversaciones (id_usuario_1, id_usuario_2, activo) VALUES (?, ?, 1)");
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

    // Verificar que la sesión pertenece a esta conversación y obtener su estado
    $stmt_chk = $conn->prepare("SELECT id_usuario_1, id_usuario_2, activo FROM conversaciones WHERE id_conversacion = ?");
    $stmt_chk->bind_param("i", $id_chat);
    $stmt_chk->execute();
    $conv = $stmt_chk->get_result()->fetch_assoc();
    $stmt_chk->close();

    if (!$conv || ($conv['id_usuario_1'] != $id_sesion && $conv['id_usuario_2'] != $id_sesion)) {
        echo json_encode(['error' => 'No autorizado']);
        exit;
    }

    // Estado en línea/desconectado del otro participante, para el encabezado
    $id_otro = ($conv['id_usuario_1'] == $id_sesion) ? $conv['id_usuario_2'] : $conv['id_usuario_1'];
    $stmt_en = $conn->prepare(
        "SELECT (ultima_actividad IS NOT NULL AND TIMESTAMPDIFF(SECOND, ultima_actividad, NOW()) <= 45) AS en_linea
         FROM usuarios WHERE id = ?"
    );
    $stmt_en->bind_param("i", $id_otro);
    $stmt_en->execute();
    $en_linea_otro = (bool)($stmt_en->get_result()->fetch_assoc()['en_linea'] ?? 0);
    $stmt_en->close();

    // Un chat cliente↔paseador solo sigue "activo" mientras hoy sea uno de
    // los días asignados en el cronograma — no basta con que haya existido
    // en el pasado. paseador↔paseador nunca está permitido (aunque la
    // conversación ya exista de antes). admin nunca se ve afectado por
    // estas reglas (sin restricción).
    $activo_efectivo = (int)$conv['activo'];
    if ($activo_efectivo === 1 && $rol_sesion !== 'admin') {
        $rol_otro = obtenerRolPorId($conn, $id_otro);
        if ($rol_sesion === 'usuario' && $rol_otro === 'paseador' && !asignadoHoy($conn, $id_sesion, $id_otro)) {
            $activo_efectivo = 0;
        } elseif ($rol_sesion === 'paseador' && $rol_otro === 'usuario' && !asignadoHoy($conn, $id_otro, $id_sesion)) {
            $activo_efectivo = 0;
        } elseif ($rol_sesion === 'paseador' && $rol_otro === 'paseador') {
            $activo_efectivo = 0;
        }
    }

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

    echo json_encode([
        'mensajes' => $mensajes,
        'activo'   => $activo_efectivo,
        'es_admin' => ($rol_sesion === 'admin'),
        'en_linea' => $en_linea_otro
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── [E] ENVIAR MENSAJE ────────────────────────────────────────────────────
if ($accion === 'enviar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_chat    = intval($_POST['id_conversacion'] ?? 0);
    $texto      = trim($_POST['mensaje'] ?? '') ?: null;
    $ruta_img   = null;

    // Verificar que la sesión pertenece a la conversación y su estado activo
    $stmt_chk = $conn->prepare("SELECT id_usuario_1, id_usuario_2, activo FROM conversaciones WHERE id_conversacion = ?");
    $stmt_chk->bind_param("i", $id_chat);
    $stmt_chk->execute();
    $conv = $stmt_chk->get_result()->fetch_assoc();
    $stmt_chk->close();

    if (!$conv || ($conv['id_usuario_1'] != $id_sesion && $conv['id_usuario_2'] != $id_sesion)) {
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit;
    }

    // Si el chat está desactivado (manualmente por admin, o porque hoy ya
    // no es un día de servicio entre este cliente y este paseador), solo
    // el admin puede seguir escribiendo.
    $chat_bloqueado = ((int)$conv['activo'] === 0);
    if (!$chat_bloqueado && $rol_sesion !== 'admin') {
        $id_otro_env = ($conv['id_usuario_1'] == $id_sesion) ? $conv['id_usuario_2'] : $conv['id_usuario_1'];
        $rol_otro_env = obtenerRolPorId($conn, $id_otro_env);
        if ($rol_sesion === 'usuario' && $rol_otro_env === 'paseador' && !asignadoHoy($conn, $id_sesion, $id_otro_env)) {
            $chat_bloqueado = true;
        } elseif ($rol_sesion === 'paseador' && $rol_otro_env === 'usuario' && !asignadoHoy($conn, $id_otro_env, $id_sesion)) {
            $chat_bloqueado = true;
        } elseif ($rol_sesion === 'paseador' && $rol_otro_env === 'paseador') {
            $chat_bloqueado = true;
        }
    }
    if ($chat_bloqueado && $rol_sesion !== 'admin') {
        echo json_encode(['success' => false, 'error' => 'Este chat está desactivado mientras no esté en servicio']);
        exit;
    }

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

// ── [I] ACTIVAR / DESACTIVAR CONVERSACIÓN (solo admin) ─────────────────────
if ($accion === 'cambiar_estado_chat' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($rol_sesion !== 'admin') {
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit;
    }

    $id_chat = intval($_POST['id_conversacion'] ?? 0);

    $stmt_a = $conn->prepare("SELECT activo FROM conversaciones WHERE id_conversacion = ?");
    $stmt_a->bind_param("i", $id_chat);
    $stmt_a->execute();
    $fila = $stmt_a->get_result()->fetch_assoc();
    $stmt_a->close();

    if (!$fila) {
        echo json_encode(['success' => false, 'error' => 'Conversación no encontrada']);
        exit;
    }

    $nuevo_estado = $fila['activo'] ? 0 : 1;
    $stmt_u = $conn->prepare("UPDATE conversaciones SET activo = ? WHERE id_conversacion = ?");
    $stmt_u->bind_param("ii", $nuevo_estado, $id_chat);
    $stmt_u->execute();
    $stmt_u->close();

    echo json_encode(['success' => true, 'activo' => $nuevo_estado]);
    exit;
}

// ── FUNCIÓN AUXILIAR: DETERMINAR ROL DE UN USUARIO (a partir de conteos) ──
function obtenerRol($es_admin, $es_paseador) {
    if ((int)$es_admin > 0) return 'admin';
    if ((int)$es_paseador > 0) return 'paseador';
    return 'usuario';
}

// ── FUNCIÓN AUXILIAR: DETERMINAR ROL DE UN USUARIO A PARTIR DE SU ID ──────
function obtenerRolPorId($conn, $id_usuario) {
    $stmt = $conn->prepare("SELECT id_admin FROM admin WHERE id_usuario = ? LIMIT 1");
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        return 'admin';
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT id_paseador FROM paseadores WHERE id_usuario = ? LIMIT 1");
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        return 'paseador';
    }
    $stmt->close();

    return 'usuario';
}

// ── FUNCIÓN AUXILIAR: ¿HOY es un día asignado en el cronograma? ───────────
// $id_usuario_cliente  = id (usuarios.id) del cliente
// $id_usuario_paseador = id (usuarios.id) de la cuenta del paseador
// El chat cliente↔paseador solo se habilita el día de la semana en que
// el cronograma los relaciona (todo el día, no solo mientras el paseo
// puntual está en curso).
function asignadoHoy($conn, $id_usuario_cliente, $id_usuario_paseador) {
    $hoy = (int)date('N'); // 1=lunes ... 7=domingo
    $stmt = $conn->prepare(
        "SELECT cp.id_cronograma
         FROM cronograma_paseos cp
         JOIN pedidos_paseo pp ON pp.id_pedido = cp.id_pedido
         JOIN paseadores pa    ON pa.id_paseador = cp.id_paseador
         WHERE pp.id_usuario = ? AND pa.id_usuario = ? AND cp.dia_semana = ?
         LIMIT 1"
    );
    $stmt->bind_param("iii", $id_usuario_cliente, $id_usuario_paseador, $hoy);
    $stmt->execute();
    $existe = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $existe;
}

// ── FUNCIÓN AUXILIAR: ¿el usuario tiene algún servicio activo? ────────────
function tieneServicioActivo($conn, $id_usuario) {
    $stmt = $conn->prepare("SELECT paseos, adiestramiento, hospedaje FROM membresias WHERE id_usuario = ? LIMIT 1");
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return false;
    return (int)$row['paseos'] === 1 || (int)$row['adiestramiento'] === 1 || (int)$row['hospedaje'] === 1;
}

// ── LISTAS BASE PARA "listar_chats" (todas indexadas por id de usuario) ───

function todosLosAdmins($conn, $excluir_id) {
    $stmt = $conn->prepare(
        "SELECT u.id, u.nombre, iu.avatar_url,
                (u.ultima_actividad IS NOT NULL AND TIMESTAMPDIFF(SECOND, u.ultima_actividad, NOW()) <= 45) AS en_linea
         FROM admin a JOIN usuarios u ON u.id = a.id_usuario
         LEFT JOIN info_usuario iu ON iu.id_usuario = u.id
         WHERE u.id != ?"
    );
    $stmt->bind_param("i", $excluir_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) $out[(int)$r['id']] = $r;
    $stmt->close();
    return $out;
}

function todosLosPaseadores($conn, $excluir_id) {
    $stmt = $conn->prepare(
        "SELECT u.id, u.nombre, iu.avatar_url,
                (u.ultima_actividad IS NOT NULL AND TIMESTAMPDIFF(SECOND, u.ultima_actividad, NOW()) <= 45) AS en_linea
         FROM paseadores p JOIN usuarios u ON u.id = p.id_usuario
         LEFT JOIN info_usuario iu ON iu.id_usuario = u.id
         WHERE u.id != ?"
    );
    $stmt->bind_param("i", $excluir_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) $out[(int)$r['id']] = $r;
    $stmt->close();
    return $out;
}

// Clientes asignados HOY (por día de la semana) a este paseador
function clientesAsignadosHoy($conn, $id_usuario_paseador) {
    $hoy = (int)date('N');
    $stmt = $conn->prepare(
        "SELECT DISTINCT u.id, u.nombre, iu.avatar_url,
                (u.ultima_actividad IS NOT NULL AND TIMESTAMPDIFF(SECOND, u.ultima_actividad, NOW()) <= 45) AS en_linea
         FROM cronograma_paseos cp
         JOIN pedidos_paseo pp ON pp.id_pedido = cp.id_pedido
         JOIN usuarios u       ON u.id = pp.id_usuario
         LEFT JOIN info_usuario iu ON iu.id_usuario = u.id
         JOIN paseadores pa    ON pa.id_paseador = cp.id_paseador
         WHERE pa.id_usuario = ? AND cp.dia_semana = ?"
    );
    $stmt->bind_param("ii", $id_usuario_paseador, $hoy);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) $out[(int)$r['id']] = $r;
    $stmt->close();
    return $out;
}

// Paseador(es) asignado(s) HOY (por día de la semana) a este cliente
function paseadorAsignadoHoy($conn, $id_usuario_cliente) {
    $hoy = (int)date('N');
    $stmt = $conn->prepare(
        "SELECT DISTINCT u.id, u.nombre, iu.avatar_url,
                (u.ultima_actividad IS NOT NULL AND TIMESTAMPDIFF(SECOND, u.ultima_actividad, NOW()) <= 45) AS en_linea
         FROM cronograma_paseos cp
         JOIN pedidos_paseo pp ON pp.id_pedido = cp.id_pedido
         JOIN paseadores pa    ON pa.id_paseador = cp.id_paseador
         JOIN usuarios u       ON u.id = pa.id_usuario
         LEFT JOIN info_usuario iu ON iu.id_usuario = u.id
         WHERE pp.id_usuario = ? AND cp.dia_semana = ?"
    );
    $stmt->bind_param("ii", $id_usuario_cliente, $hoy);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) $out[(int)$r['id']] = $r;
    $stmt->close();
    return $out;
}

// Todas las conversaciones de la sesión, indexadas por id del otro usuario
function conversacionesDeSesion($conn, $id_sesion) {
    $stmt = $conn->prepare(
        "SELECT c.id_conversacion, c.activo,
                u.id AS id_receptor, u.nombre, iu.avatar_url,
                (u.ultima_actividad IS NOT NULL AND TIMESTAMPDIFF(SECOND, u.ultima_actividad, NOW()) <= 45) AS en_linea,
                (SELECT COUNT(*) FROM admin a WHERE a.id_usuario = u.id) AS es_admin,
                (SELECT COUNT(*) FROM paseadores p WHERE p.id_usuario = u.id) AS es_paseador,
                (SELECT m.mensaje FROM mensajes m WHERE m.id_conversacion = c.id_conversacion ORDER BY m.id_mensaje DESC LIMIT 1) AS ultimo_msg,
                (SELECT m.ruta_imagen FROM mensajes m WHERE m.id_conversacion = c.id_conversacion ORDER BY m.id_mensaje DESC LIMIT 1) AS ultima_img,
                (SELECT DATE_FORMAT(m.fecha_envio, '%H:%i') FROM mensajes m WHERE m.id_conversacion = c.id_conversacion ORDER BY m.id_mensaje DESC LIMIT 1) AS ultima_hora,
                (SELECT MAX(m.id_mensaje) FROM mensajes m WHERE m.id_conversacion = c.id_conversacion) AS ultimo_id_msg,
                (SELECT COUNT(*) FROM mensajes m WHERE m.id_conversacion = c.id_conversacion AND m.id_emisor != ? AND m.leido = 0) AS no_leidos
         FROM conversaciones c
         INNER JOIN usuarios u ON (u.id = c.id_usuario_1 OR u.id = c.id_usuario_2) AND u.id != ?
         LEFT JOIN info_usuario iu ON iu.id_usuario = u.id
         WHERE c.id_usuario_1 = ? OR c.id_usuario_2 = ?"
    );
    $stmt->bind_param("iiii", $id_sesion, $id_sesion, $id_sesion, $id_sesion);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) $out[(int)$r['id_receptor']] = $r;
    $stmt->close();
    return $out;
}

// Arma la tarjeta de contacto que consume el front (con o sin conversación previa)
function construirContacto($id_receptor, $datos, $conv) {
    $previa       = 'Iniciar conversación';
    $activo       = 1;
    $id_conv      = null;
    $no_leidos    = 0;
    $ultimo_orden = 0;
    $hora         = '';

    if ($conv) {
        $id_conv      = (int)$conv['id_conversacion'];
        $activo       = (int)$conv['activo'];
        $no_leidos    = (int)$conv['no_leidos'];
        $ultimo_orden = (int)($conv['ultimo_id_msg'] ?? 0);
        $hora         = $conv['ultima_hora'] ?? '';

        if (!empty($conv['ultima_img'])) {
            $previa = '📷 Imagen';
        } elseif (!empty($conv['ultimo_msg'])) {
            $previa = mb_strlen($conv['ultimo_msg']) > 35
                ? mb_substr($conv['ultimo_msg'], 0, 35) . '...'
                : $conv['ultimo_msg'];
        } else {
            $previa = 'Escribe un mensaje...';
        }
    }

    return [
        'id'          => $id_conv,
        'id_receptor' => $id_receptor,
        'nombre'      => $datos['nombre'],
        'avatar'      => normalizarAvatar($datos['avatar_url']),
        'en_linea'    => (bool)($datos['en_linea'] ?? 0),
        'activo'      => $activo,
        'ultimo'      => $previa,
        'hora'        => $hora,
        'no_leidos'   => $no_leidos,
        '_orden'      => $ultimo_orden, // interno, se descarta antes de responder
    ];
}

// Conversaciones con mensajes recientes primero (como WhatsApp);
// los contactos sin conversación aún van después, en orden alfabético.
function ordenarContactos($contactos) {
    usort($contactos, function ($a, $b) {
        if ($a['_orden'] > 0 || $b['_orden'] > 0) return $b['_orden'] - $a['_orden'];
        return strcasecmp($a['nombre'], $b['nombre']);
    });
    foreach ($contactos as &$c) unset($c['_orden']);
    unset($c);
    return $contactos;
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