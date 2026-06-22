<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once __DIR__ . '/../model/conexion.php';
include_once __DIR__ . '/../model/paseos_setup.php';

setupPaseosModule($conn);

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

function jsonOut($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function crearNotificacion(mysqli $conn, int $idUsuario, ?int $idPaseo, string $tipo, string $titulo, string $mensaje): void {
    $stmt = $conn->prepare('INSERT INTO notificaciones (id_usuario, id_paseo, tipo, titulo, mensaje) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('iisss', $idUsuario, $idPaseo, $tipo, $titulo, $mensaje);
    $stmt->execute();
}

function mapPaseoRow(array $row): array {
    $estados = [
        'programado' => ['lbl' => 'Programado', 'cls' => 'b-programado'],
        'en_curso'   => ['lbl' => 'En curso', 'cls' => 'b-proceso'],
        'completado' => ['lbl' => 'Completado', 'cls' => 'b-completado'],
        'cancelado'  => ['lbl' => 'Cancelado', 'cls' => 'b-cancelado'],
    ];
    $st = $estados[$row['estado']] ?? ['lbl' => $row['estado'], 'cls' => ''];

    $durLabels = [30 => '30 min', 60 => '1 hora', 120 => '2 horas', 180 => '3 horas'];
    $dur = (int)$row['duracion_minutos'];

    return [
        'id_paseo'           => (int)$row['id_paseo'],
        'codigo'             => $row['codigo'],
        'fecha'              => $row['fecha'],
        'hora_inicio'        => substr($row['hora_inicio'], 0, 5),
        'duracion_minutos'   => $dur,
        'duracion_label'     => $durLabels[$dur] ?? ($dur . ' min'),
        'modalidad'          => $row['modalidad'],
        'zona'               => $row['zona'],
        'direccion_recogida' => $row['direccion_recogida'],
        'estado'             => $row['estado'],
        'estado_label'       => $st['lbl'],
        'estado_cls'         => $st['cls'],
        'notas'              => $row['notas'],
        'precio'             => $row['precio'] ? (float)$row['precio'] : null,
        'motivo_cancelacion' => $row['motivo_cancelacion'],
        'sin_paseador'       => empty($row['id_paseador']),
        'cliente' => [
            'id'       => (int)$row['id_cliente'],
            'nombre'   => $row['cliente_nombre'],
            'email'    => $row['cliente_email'],
            'telefono' => $row['cliente_telefono'] ?? '',
        ],
        'mascota' => [
            'id'     => (int)$row['id_mascota'],
            'nombre' => $row['nombre_mascota'],
            'avatar' => $row['avatar_mascota'] ?? '🐾',
            'notas'  => $row['biografia_canina'] ?? '',
        ],
        'paseador' => $row['id_paseador'] ? [
            'id'     => (int)$row['id_paseador'],
            'nombre' => $row['paseador_nombre'],
            'email'  => $row['paseador_email'],
        ] : null,
    ];
}

function basePaseoQuery(): string {
    return "SELECT p.*,
            uc.nombre AS cliente_nombre, uc.email AS cliente_email, ic.telefono AS cliente_telefono,
            m.nombre_mascota, m.avatar_mascota, m.biografia_canina,
            up.nombre AS paseador_nombre, up.email AS paseador_email
        FROM paseos p
        INNER JOIN usuarios uc ON uc.id = p.id_cliente
        INNER JOIN mascota_usuario m ON m.id_mascota = p.id_mascota
        LEFT JOIN info_usuario ic ON ic.id_usuario = p.id_cliente
        LEFT JOIN usuarios up ON up.id = p.id_paseador";
}

switch ($action) {

    case 'stats':
        $hoy = date('Y-m-d');
        $stats = ['hoy' => 0, 'en_curso' => 0, 'programados' => 0, 'sin_paseador' => 0, 'cancelados' => 0];
        $q = "SELECT
                SUM(fecha = '$hoy') AS hoy,
                SUM(estado = 'en_curso') AS en_curso,
                SUM(estado = 'programado') AS programados,
                SUM(estado = 'programado' AND id_paseador IS NULL) AS sin_paseador,
                SUM(estado = 'cancelado' AND fecha >= DATE_SUB('$hoy', INTERVAL 7 DAY)) AS cancelados
            FROM paseos";
        $res = $conn->query($q);
        if ($res && $row = $res->fetch_assoc()) {
            foreach ($stats as $k => $_) {
                $stats[$k] = (int)($row[$k] ?? 0);
            }
        }
        jsonOut(['success' => true, 'stats' => $stats]);

    case 'list':
        $where = ['1=1'];
        $params = [];
        $types = '';

        if (!empty($_GET['estado'])) {
            $where[] = 'p.estado = ?';
            $params[] = $_GET['estado'];
            $types .= 's';
        }
        if (!empty($_GET['fecha'])) {
            $where[] = 'p.fecha = ?';
            $params[] = $_GET['fecha'];
            $types .= 's';
        }
        if (!empty($_GET['zona'])) {
            $where[] = 'p.zona LIKE ?';
            $params[] = '%' . $_GET['zona'] . '%';
            $types .= 's';
        }
        if (!empty($_GET['sin_paseador'])) {
            $where[] = "p.id_paseador IS NULL AND p.estado = 'programado'";
        }
        if (!empty($_GET['q'])) {
            $where[] = '(p.codigo LIKE ? OR uc.nombre LIKE ? OR m.nombre_mascota LIKE ? OR up.nombre LIKE ? OR p.zona LIKE ?)';
            $q = '%' . $_GET['q'] . '%';
            array_push($params, $q, $q, $q, $q, $q);
            $types .= 'sssss';
        }

        $sql = basePaseoQuery() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY p.fecha DESC, p.hora_inicio ASC';
        $stmt = $conn->prepare($sql);
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $paseos = [];
        while ($row = $res->fetch_assoc()) {
            $paseos[] = mapPaseoRow($row);
        }
        jsonOut(['success' => true, 'paseos' => $paseos, 'total' => count($paseos)]);

    case 'detail':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) jsonOut(['success' => false, 'message' => 'ID requerido'], 400);
        $sql = basePaseoQuery() . ' WHERE p.id_paseo = ?';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) jsonOut(['success' => false, 'message' => 'Paseo no encontrado'], 404);
        jsonOut(['success' => true, 'paseo' => mapPaseoRow($row)]);

    case 'paseadores':
        $fecha = $_GET['fecha'] ?? date('Y-m-d');
        $sql = "SELECT u.id, u.nombre, u.email, i.telefono, i.direccion,
                (SELECT COUNT(*) FROM paseos px WHERE px.id_paseador = u.id AND px.fecha = ? AND px.estado IN ('programado','en_curso')) AS paseos_dia
            FROM usuarios u
            LEFT JOIN info_usuario i ON i.id_usuario = u.id
            WHERE u.rol = 'paseador'
            ORDER BY u.nombre ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $fecha);
        $stmt->execute();
        $res = $stmt->get_result();
        $paseadores = [];
        while ($row = $res->fetch_assoc()) {
            $paseadores[] = [
                'id'         => (int)$row['id'],
                'nombre'     => $row['nombre'],
                'email'      => $row['email'],
                'telefono'   => $row['telefono'] ?? '',
                'direccion'  => $row['direccion'] ?? '',
                'paseos_dia' => (int)$row['paseos_dia'],
            ];
        }
        jsonOut(['success' => true, 'paseadores' => $paseadores]);

    case 'assign':
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $idPaseo = (int)($input['id_paseo'] ?? 0);
        $idPaseador = (int)($input['id_paseador'] ?? 0);
        if (!$idPaseo || !$idPaseador) jsonOut(['success' => false, 'message' => 'Datos incompletos'], 400);

        $chk = $conn->prepare("SELECT p.*, m.nombre_mascota, up.nombre AS paseador_nombre
            FROM paseos p
            INNER JOIN mascota_usuario m ON m.id_mascota = p.id_mascota
            INNER JOIN usuarios up ON up.id = ?
            WHERE p.id_paseo = ? AND up.rol = 'paseador'");
        $chk->bind_param('ii', $idPaseador, $idPaseo);
        $chk->execute();
        $paseo = $chk->get_result()->fetch_assoc();
        if (!$paseo) jsonOut(['success' => false, 'message' => 'Paseo o paseador no válido'], 404);
        if ($paseo['estado'] === 'cancelado' || $paseo['estado'] === 'completado') {
            jsonOut(['success' => false, 'message' => 'No se puede asignar en este estado'], 400);
        }

        $upd = $conn->prepare('UPDATE paseos SET id_paseador = ? WHERE id_paseo = ?');
        $upd->bind_param('ii', $idPaseador, $idPaseo);
        $upd->execute();

        $titulo = 'Paseo asignado';
        $msgCliente = "Tu paseo {$paseo['codigo']} para {$paseo['nombre_mascota']} fue asignado a {$paseo['paseador_nombre']}.";
        $msgPaseador = "Se te asignó el paseo {$paseo['codigo']} ({$paseo['nombre_mascota']}) el {$paseo['fecha']} a las " . substr($paseo['hora_inicio'], 0, 5) . '.';

        crearNotificacion($conn, (int)$paseo['id_cliente'], $idPaseo, 'paseo_asignado', $titulo, $msgCliente);
        crearNotificacion($conn, $idPaseador, $idPaseo, 'paseo_asignado', $titulo, $msgPaseador);

        jsonOut(['success' => true, 'message' => 'Paseador asignado correctamente']);

    case 'cancel':
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $idPaseo = (int)($input['id_paseo'] ?? 0);
        $motivo = trim($input['motivo'] ?? 'Cancelado por administrador');
        if (!$idPaseo) jsonOut(['success' => false, 'message' => 'ID requerido'], 400);

        $chk = $conn->prepare('SELECT * FROM paseos WHERE id_paseo = ?');
        $chk->bind_param('i', $idPaseo);
        $chk->execute();
        $paseo = $chk->get_result()->fetch_assoc();
        if (!$paseo) jsonOut(['success' => false, 'message' => 'Paseo no encontrado'], 404);
        if ($paseo['estado'] === 'completado') jsonOut(['success' => false, 'message' => 'No se puede cancelar un paseo completado'], 400);

        $upd = $conn->prepare("UPDATE paseos SET estado = 'cancelado', motivo_cancelacion = ? WHERE id_paseo = ?");
        $upd->bind_param('si', $motivo, $idPaseo);
        $upd->execute();

        $titulo = 'Paseo cancelado';
        $msg = "El paseo {$paseo['codigo']} fue cancelado. Motivo: $motivo";
        crearNotificacion($conn, (int)$paseo['id_cliente'], $idPaseo, 'paseo_cancelado', $titulo, $msg);
        if ($paseo['id_paseador']) {
            crearNotificacion($conn, (int)$paseo['id_paseador'], $idPaseo, 'paseo_cancelado', $titulo, $msg);
        }

        jsonOut(['success' => true, 'message' => 'Paseo cancelado y notificación enviada']);

    case 'change_status':
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $idPaseo = (int)($input['id_paseo'] ?? 0);
        $estado = $input['estado'] ?? '';
        $validos = ['programado', 'en_curso', 'completado', 'cancelado'];
        if (!$idPaseo || !in_array($estado, $validos, true)) {
            jsonOut(['success' => false, 'message' => 'Datos inválidos'], 400);
        }
        $upd = $conn->prepare('UPDATE paseos SET estado = ? WHERE id_paseo = ?');
        $upd->bind_param('si', $estado, $idPaseo);
        $upd->execute();
        jsonOut(['success' => true, 'message' => 'Estado actualizado']);

    default:
        jsonOut(['success' => false, 'message' => 'Acción no válida'], 400);
}
