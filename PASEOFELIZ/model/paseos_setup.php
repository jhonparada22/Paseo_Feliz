<?php
/**
 * Instala tablas del módulo de paseos. Retorna array de pasos ejecutados.
 */
function setupPaseosModule(mysqli $conn): array {
    $steps = [];

    $columnExists = function (string $table, string $column) use ($conn): bool {
        $table = $conn->real_escape_string($table);
        $column = $conn->real_escape_string($column);
        $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $res && $res->num_rows > 0;
    };

    $tableExists = function (string $table) use ($conn): bool {
        $table = $conn->real_escape_string($table);
        $res = $conn->query("SHOW TABLES LIKE '$table'");
        return $res && $res->num_rows > 0;
    };

    if (!$columnExists('usuarios', 'rol')) {
        $conn->query("ALTER TABLE usuarios ADD COLUMN rol ENUM('cliente','paseador','administrador') NOT NULL DEFAULT 'cliente' AFTER password");
        $steps[] = 'Columna rol agregada a usuarios';
    }

    if (!$tableExists('paseos')) {
        $conn->query("CREATE TABLE paseos (
            id_paseo INT(11) NOT NULL AUTO_INCREMENT,
            codigo VARCHAR(20) NOT NULL,
            id_cliente INT(11) NOT NULL,
            id_mascota INT(11) NOT NULL,
            id_paseador INT(11) DEFAULT NULL,
            fecha DATE NOT NULL,
            hora_inicio TIME NOT NULL,
            duracion_minutos INT(11) NOT NULL DEFAULT 60,
            modalidad ENUM('individual','grupal') NOT NULL DEFAULT 'individual',
            zona VARCHAR(100) DEFAULT NULL,
            direccion_recogida VARCHAR(255) DEFAULT NULL,
            lat_recogida DECIMAL(10,8) DEFAULT NULL,
            lng_recogida DECIMAL(11,8) DEFAULT NULL,
            estado ENUM('programado','en_curso','completado','cancelado') NOT NULL DEFAULT 'programado',
            notas TEXT DEFAULT NULL,
            precio DECIMAL(10,2) DEFAULT NULL,
            motivo_cancelacion TEXT DEFAULT NULL,
            fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_paseo),
            UNIQUE KEY uk_codigo (codigo),
            KEY idx_cliente (id_cliente),
            KEY idx_paseador (id_paseador),
            KEY idx_fecha_estado (fecha, estado),
            CONSTRAINT fk_paseo_cliente FOREIGN KEY (id_cliente) REFERENCES usuarios(id) ON DELETE CASCADE,
            CONSTRAINT fk_paseo_mascota FOREIGN KEY (id_mascota) REFERENCES mascota_usuario(id_mascota) ON DELETE CASCADE,
            CONSTRAINT fk_paseo_paseador FOREIGN KEY (id_paseador) REFERENCES usuarios(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $steps[] = 'Tabla paseos creada';
    }

    if (!$tableExists('notificaciones')) {
        $conn->query("CREATE TABLE notificaciones (
            id_notificacion INT(11) NOT NULL AUTO_INCREMENT,
            id_usuario INT(11) NOT NULL,
            id_paseo INT(11) DEFAULT NULL,
            tipo ENUM('paseo_solicitado','paseo_asignado','paseo_cancelado','paseador_llegando_recogida','paseador_llegando_entrega','general') NOT NULL DEFAULT 'general',
            titulo VARCHAR(150) NOT NULL,
            mensaje TEXT NOT NULL,
            leida TINYINT(1) NOT NULL DEFAULT 0,
            fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_notificacion),
            KEY idx_usuario (id_usuario),
            KEY idx_paseo (id_paseo),
            CONSTRAINT fk_notif_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
            CONSTRAINT fk_notif_paseo FOREIGN KEY (id_paseo) REFERENCES paseos(id_paseo) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $steps[] = 'Tabla notificaciones creada';
    }

    if (!$tableExists('ubicaciones_paseador')) {
        $conn->query("CREATE TABLE ubicaciones_paseador (
            id_ubicacion INT(11) NOT NULL AUTO_INCREMENT,
            id_paseador INT(11) NOT NULL,
            id_paseo INT(11) DEFAULT NULL,
            lat DECIMAL(10,8) NOT NULL,
            lng DECIMAL(11,8) NOT NULL,
            fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_ubicacion),
            KEY idx_paseador (id_paseador),
            KEY idx_paseo (id_paseo),
            CONSTRAINT fk_ubic_paseador FOREIGN KEY (id_paseador) REFERENCES usuarios(id) ON DELETE CASCADE,
            CONSTRAINT fk_ubic_paseo FOREIGN KEY (id_paseo) REFERENCES paseos(id_paseo) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $steps[] = 'Tabla ubicaciones_paseador creada';
    }

    $countRes = $conn->query('SELECT COUNT(*) AS total FROM paseos');
    $totalPaseos = $countRes ? (int)$countRes->fetch_assoc()['total'] : 0;

    if ($totalPaseos === 0) {
        seedDemoPaseos($conn);
        $steps[] = 'Datos de prueba insertados';
    }

    return $steps;
}

function seedDemoPaseos(mysqli $conn): void {
    $demoUsers = [
        ['nombre' => 'María González', 'email' => 'maria.demo@paseofeliz.com', 'rol' => 'cliente'],
        ['nombre' => 'Pedro Ramírez', 'email' => 'pedro.demo@paseofeliz.com', 'rol' => 'cliente'],
        ['nombre' => 'Laura Martínez', 'email' => 'laura.demo@paseofeliz.com', 'rol' => 'cliente'],
        ['nombre' => 'Carlos Rodríguez', 'email' => 'carlos.paseador@paseofeliz.com', 'rol' => 'paseador'],
        ['nombre' => 'Ana Fernández', 'email' => 'ana.paseador@paseofeliz.com', 'rol' => 'paseador'],
        ['nombre' => 'Admin Demo', 'email' => 'admin.demo@gmail.com', 'rol' => 'administrador'],
    ];

    $userIds = [];
    $passHash = password_hash('demo1234', PASSWORD_DEFAULT);

    foreach ($demoUsers as $u) {
        $stmt = $conn->prepare('SELECT id FROM usuarios WHERE email = ?');
        $stmt->bind_param('s', $u['email']);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $userIds[$u['email']] = (int)$res->fetch_assoc()['id'];
            $upd = $conn->prepare('UPDATE usuarios SET rol = ? WHERE email = ?');
            $upd->bind_param('ss', $u['rol'], $u['email']);
            $upd->execute();
        } else {
            $ins = $conn->prepare('INSERT INTO usuarios (nombre, email, sexo, password, rol) VALUES (?, ?, ?, ?, ?)');
            $sexo = 'Otro';
            $ins->bind_param('sssss', $u['nombre'], $u['email'], $sexo, $passHash, $u['rol']);
            $ins->execute();
            $userIds[$u['email']] = (int)$conn->insert_id;
        }
    }

    $pets = [
        ['maria.demo@paseofeliz.com', 'Max', '🐕'],
        ['maria.demo@paseofeliz.com', 'Luna', '🐶'],
        ['pedro.demo@paseofeliz.com', 'Rocky', '🐾'],
        ['laura.demo@paseofeliz.com', 'Duki', '🐩'],
    ];
    $petIds = [];

    foreach ($pets as [$email, $nombre, $emoji]) {
        $uid = $userIds[$email];
        $stmt = $conn->prepare('SELECT id_mascota FROM mascota_usuario WHERE id_usuario = ? AND nombre_mascota = ?');
        $stmt->bind_param('is', $uid, $nombre);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $petIds[] = (int)$res->fetch_assoc()['id_mascota'];
        } else {
            $bio = 'Mascota de demostración';
            $ins = $conn->prepare('INSERT INTO mascota_usuario (id_usuario, nombre_mascota, avatar_mascota, biografia_canina) VALUES (?, ?, ?, ?)');
            $ins->bind_param('isss', $uid, $nombre, $emoji, $bio);
            $ins->execute();
            $petIds[] = (int)$conn->insert_id;
        }
    }

    $hoy = date('Y-m-d');
    $paseador1 = $userIds['carlos.paseador@paseofeliz.com'];
    $paseador2 = $userIds['ana.paseador@paseofeliz.com'];

    $demoPaseos = [
        ['#PA-2001', $userIds['maria.demo@paseofeliz.com'], $petIds[0], $paseador1, $hoy, '07:30:00', 60, 'individual', 'Prados del Este', 'Calle 10 #5-20, Cúcuta', 'programado', 'Usar arnés de pecho.', 15000],
        ['#PA-2002', $userIds['pedro.demo@paseofeliz.com'], $petIds[2], $paseador1, $hoy, '09:00:00', 30, 'grupal', 'Barrio La Ceiba', 'Av. 0 #12-45, Cúcuta', 'en_curso', 'Llevar hidratación.', 8000],
        ['#PA-2003', $userIds['laura.demo@paseofeliz.com'], $petIds[3], null, $hoy, '11:00:00', 120, 'individual', 'Centro', 'Calle 15 #8-30, Cúcuta', 'programado', 'Sin asignar paseador.', 28000],
        ['#PA-2004', $userIds['maria.demo@paseofeliz.com'], $petIds[1], $paseador2, $hoy, '14:00:00', 60, 'individual', 'Villa del Rosario', 'Carrera 5 #3-12', 'programado', null, 15000],
        ['#PA-2005', $userIds['pedro.demo@paseofeliz.com'], $petIds[2], null, $hoy, '16:30:00', 60, 'grupal', 'Los Patios', 'Transversal 2 #1-8', 'programado', 'Pendiente de asignación.', 12000],
        ['#PA-2006', $userIds['laura.demo@paseofeliz.com'], $petIds[3], $paseador2, date('Y-m-d', strtotime('-1 day')), '10:00:00', 60, 'individual', 'Cúcuta Norte', 'Av. Gran Colombia', 'completado', null, 15000],
        ['#PA-2007', $userIds['maria.demo@paseofeliz.com'], $petIds[0], $paseador1, date('Y-m-d', strtotime('-2 day')), '08:00:00', 30, 'grupal', 'El Zulia', 'Calle 4 #2-1', 'cancelado', 'Cliente canceló por lluvia.', 8000],
    ];

    foreach ($demoPaseos as $p) {
        [$codigo, $cliente, $mascota, $paseador, $fecha, $hora, $dur, $mod, $zona, $dir, $estado, $notas, $precio] = $p;
        if ($paseador === null) {
            $ins = $conn->prepare('INSERT INTO paseos (codigo, id_cliente, id_mascota, fecha, hora_inicio, duracion_minutos, modalidad, zona, direccion_recogida, estado, notas, precio) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $ins->bind_param('siisssissssd', $codigo, $cliente, $mascota, $fecha, $hora, $dur, $mod, $zona, $dir, $estado, $notas, $precio);
        } else {
            $ins = $conn->prepare('INSERT INTO paseos (codigo, id_cliente, id_mascota, id_paseador, fecha, hora_inicio, duracion_minutos, modalidad, zona, direccion_recogida, estado, notas, precio) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $ins->bind_param('siiisssissssd', $codigo, $cliente, $mascota, $paseador, $fecha, $hora, $dur, $mod, $zona, $dir, $estado, $notas, $precio);
        }
        $ins->execute();
    }
}

// Ejecución directa del script
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'paseos_setup.php') {
    include_once __DIR__ . '/conexion.php';
    header('Content-Type: application/json; charset=UTF-8');
    $steps = setupPaseosModule($conn);
    echo json_encode(['success' => true, 'message' => 'Módulo de paseos listo', 'steps' => $steps], JSON_UNESCAPED_UNICODE);
}
