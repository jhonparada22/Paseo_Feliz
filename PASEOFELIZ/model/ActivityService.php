<?php
/**
 * ActivityService.php
 * Núcleo del Centro de Actividad del admin (Fase 17). Es el ÚNICO punto
 * que escribe y lee el feed `actividad_sistema`. Cualquier endpoint del
 * sistema registra eventos con ActivityService::registrar(); el dashboard
 * los consume con ActivityService::feed()/::contadores() y nada más arma
 * SQL del feed por su cuenta.
 *
 * Diseño:
 *  - Escritura best-effort: registrar() NUNCA lanza. Un fallo del feed
 *    jamás debe tumbar la operación de negocio que lo originó (igual
 *    criterio que ppEvento). Tolerante a que la tabla aún no exista
 *    (migración 2026_07_fase17 no aplicada) → deploy seguro.
 *  - Lectura sin N+1: los nombres van denormalizados en la propia tabla.
 *  - Poll incremental por id_actividad (high-water mark) y paginación
 *    por cursor (creado_en, id) → coste constante con miles de filas.
 */

class ActivityService
{
    /** Estado (chip) por defecto según el tipo de evento */
    private static $ESTADO = [
        'compra'                 => 'nuevo',
        'direccion_validada'     => 'completado',
        'paseador_asignado'      => 'en_proceso',
        'en_camino'              => 'en_proceso',
        'llegada'                => 'en_proceso',
        'recogido'               => 'en_proceso',
        'en_ruta'                => 'en_proceso',
        'evidencia'              => 'en_proceso',
        'mensaje'                => 'nuevo',
        'entregado'              => 'completado',
        'calificacion'           => 'completado',
        'cancelacion_solicitada' => 'urgente',
        'cancelacion_aprobada'   => 'cancelado',
        'cancelacion_rechazada'  => 'en_proceso',
        'reprogramado'           => 'pendiente',
        'incidencia'             => 'incidencia',
        'no_ejecutado'           => 'cancelado',
        'paseador_registrado'    => 'nuevo',
        'cliente_registrado'     => 'nuevo',
        'mascota_registrada'     => 'nuevo',
        'pago_aprobado'          => 'completado',
        'pago_rechazado'         => 'cancelado',
        'membresia_por_vencer'   => 'pendiente',
        'membresia_renovada'     => 'completado',
        'ruta_creada'            => 'nuevo',
        'ruta_modificada'        => 'en_proceso',
        'ruta_eliminada'         => 'cancelado',
    ];

    /** Prioridad por defecto según el tipo de evento */
    private static $PRIORIDAD = [
        'cancelacion_solicitada' => 'alta',
        'incidencia'             => 'alta',
        'pago_rechazado'         => 'alta',
        'no_ejecutado'           => 'alta',
        'membresia_por_vencer'   => 'alta',
        'cancelacion_aprobada'   => 'alta',
        'compra'                 => 'media',
        'paseador_asignado'      => 'media',
        'entregado'              => 'media',
        'reprogramado'           => 'media',
        'membresia_renovada'     => 'media',
        // el resto cae en 'baja'
    ];

    /** Icono (Font Awesome, ya cargado en el admin) y color por tipo */
    private static $ICONO = [
        'compra'                 => ['fa-cart-shopping',   '#2563eb'],
        'direccion_validada'     => ['fa-location-check',  '#16a34a'],
        'paseador_asignado'      => ['fa-user-check',      '#2563eb'],
        'en_camino'              => ['fa-person-walking-arrow-right', '#f59e0b'],
        'llegada'                => ['fa-house-circle-check', '#0ea5e9'],
        'recogido'               => ['fa-dog',             '#0ea5e9'],
        'en_ruta'                => ['fa-route',           '#3E72A6'],
        'evidencia'              => ['fa-camera',          '#8b5cf6'],
        'mensaje'                => ['fa-comment-dots',    '#8b5cf6'],
        'entregado'              => ['fa-circle-check',    '#16a34a'],
        'calificacion'           => ['fa-star',            '#f59e0b'],
        'cancelacion_solicitada' => ['fa-triangle-exclamation', '#f97316'],
        'cancelacion_aprobada'   => ['fa-ban',             '#ef4444'],
        'cancelacion_rechazada'  => ['fa-rotate-left',     '#3E72A6'],
        'reprogramado'           => ['fa-calendar-day',    '#f59e0b'],
        'incidencia'             => ['fa-circle-exclamation', '#ef4444'],
        'no_ejecutado'           => ['fa-calendar-xmark',  '#ef4444'],
        'paseador_registrado'    => ['fa-person-walking',  '#f97316'],
        'cliente_registrado'     => ['fa-user-plus',       '#2563eb'],
        'mascota_registrada'     => ['fa-paw',             '#16a34a'],
        'pago_aprobado'          => ['fa-dollar-sign',     '#16a34a'],
        'pago_rechazado'         => ['fa-credit-card',     '#ef4444'],
        'membresia_por_vencer'   => ['fa-hourglass-half',  '#f59e0b'],
        'membresia_renovada'     => ['fa-arrows-rotate',   '#16a34a'],
        'ruta_creada'            => ['fa-map-location-dot', '#3E72A6'],
        'ruta_modificada'        => ['fa-map-pin',         '#f59e0b'],
        'ruta_eliminada'         => ['fa-trash',           '#ef4444'],
    ];

    /* ─────────────────────────────────────────────────────────────
     * ESCRITURA
     * ───────────────────────────────────────────────────────────── */

    /** true si el error es "tabla no existe" (migración pendiente) */
    private static function tablaFaltante($e)
    {
        return $e instanceof mysqli_sql_exception && (int)$e->getCode() === 1146;
    }

    /**
     * Registra un evento en el feed. NUNCA lanza (best-effort).
     * $a admite: servicio, tipo (obligatorio), estado, prioridad, titulo
     * (obligatorio), descripcion, id_cliente, cliente_nombre, id_paseador,
     * paseador_nombre, id_mascota, mascota_nombre, id_pedido, id_ruta,
     * id_referencia, direccion, resuelto.
     * Denormaliza los nombres que falten con UN lookup cada uno.
     * Devuelve el id insertado o null.
     */
    public static function registrar($conn, array $a)
    {
        if (empty($a['tipo']) || empty($a['titulo'])) return null;
        $tipo = $a['tipo'];

        $servicio  = $a['servicio']  ?? 'paseos';
        $estado    = $a['estado']    ?? (self::$ESTADO[$tipo]    ?? 'nuevo');
        $prioridad = $a['prioridad'] ?? (self::$PRIORIDAD[$tipo] ?? 'baja');
        $resuelto  = isset($a['resuelto']) ? (int)$a['resuelto'] : 1;

        $idCliente  = isset($a['id_cliente'])  ? (int)$a['id_cliente']  : null;
        $idPaseador = isset($a['id_paseador']) ? (int)$a['id_paseador'] : null;
        $idMascota  = isset($a['id_mascota'])  ? (int)$a['id_mascota']  : null;

        try {
            // Denormalizar nombres faltantes (una consulta por dato ausente)
            $cliNom = $a['cliente_nombre'] ?? null;
            if ($cliNom === null && $idCliente) $cliNom = self::nombreUsuario($conn, $idCliente);

            $pasNom = $a['paseador_nombre'] ?? null;
            if ($pasNom === null && $idPaseador) $pasNom = self::nombrePaseador($conn, $idPaseador);

            $masNom = $a['mascota_nombre'] ?? null;
            if ($masNom === null && $idMascota) $masNom = self::nombreMascota($conn, $idMascota);

            $descripcion = isset($a['descripcion']) ? mb_substr((string)$a['descripcion'], 0, 255) : null;
            $titulo      = mb_substr((string)$a['titulo'], 0, 160);
            $direccion   = isset($a['direccion']) ? mb_substr((string)$a['direccion'], 0, 160) : null;
            $idPedido    = isset($a['id_pedido'])     ? (int)$a['id_pedido']     : null;
            $idRuta      = isset($a['id_ruta'])       ? (int)$a['id_ruta']       : null;
            $idRef       = isset($a['id_referencia']) ? (int)$a['id_referencia'] : null;

            $stmt = $conn->prepare(
                "INSERT INTO actividad_sistema
                    (servicio, tipo, estado, prioridad, titulo, descripcion,
                     id_cliente, cliente_nombre, id_paseador, paseador_nombre,
                     id_mascota, mascota_nombre, id_pedido, id_ruta, id_referencia,
                     direccion, resuelto)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            $stmt->bind_param(
                "ssssssisisisiiisi",
                $servicio, $tipo, $estado, $prioridad, $titulo, $descripcion,
                $idCliente, $cliNom, $idPaseador, $pasNom,
                $idMascota, $masNom, $idPedido, $idRuta, $idRef,
                $direccion, $resuelto
            );
            $stmt->execute();
            $id = $conn->insert_id;
            $stmt->close();
            return $id;
        } catch (mysqli_sql_exception $e) {
            if (self::tablaFaltante($e)) return null; // migración pendiente
            error_log('ActivityService::registrar ' . $e->getMessage());
            return null; // best-effort: no propagar
        }
    }

    private static function nombreUsuario($conn, $id)
    {
        $s = $conn->prepare("SELECT nombre FROM usuarios WHERE id = ?");
        $s->bind_param("i", $id); $s->execute();
        $r = $s->get_result()->fetch_assoc(); $s->close();
        return $r['nombre'] ?? null;
    }
    private static function nombrePaseador($conn, $id)
    {
        $s = $conn->prepare(
            "SELECT u.nombre FROM paseadores p JOIN usuarios u ON u.id = p.id_usuario WHERE p.id_paseador = ?"
        );
        $s->bind_param("i", $id); $s->execute();
        $r = $s->get_result()->fetch_assoc(); $s->close();
        return $r['nombre'] ?? null;
    }
    private static function nombreMascota($conn, $id)
    {
        $s = $conn->prepare("SELECT nombre_mascota FROM mascota_usuario WHERE id_mascota = ?");
        $s->bind_param("i", $id); $s->execute();
        $r = $s->get_result()->fetch_assoc(); $s->close();
        return $r['nombre_mascota'] ?? null;
    }

    /* ─────────────────────────────────────────────────────────────
     * LECTURA
     * ───────────────────────────────────────────────────────────── */

    /**
     * Lee el feed. $o admite:
     *   servicio  ('paseos'|'adiestramiento'|'hospedaje'), null = todos
     *   filtro    ('todos'|'hoy'|'24h'|'7d'|'pendientes'|'urgentes'|'cancelados'|'completados')
     *   buscar    texto libre (cliente/mascota/paseador/dirección/#pedido)
     *   desde_id  poll incremental: solo id_actividad > desde_id
     *   antes_fecha + antes_id  cursor de paginación (más antiguos)
     *   limit     tamaño de página (por defecto 25, máx 50)
     * Devuelve ['items'=>[...], 'hay_mas'=>bool].
     */
    public static function feed($conn, array $o)
    {
        $limit = (int)($o['limit'] ?? 25);
        if ($limit < 1)  $limit = 25;
        if ($limit > 50) $limit = 50;

        $where = [];
        $params = [];
        $tipos  = '';

        // Tipos ocultos en el feed (se registran/backfillean pero no se
        // muestran): calificación del cliente y fotos del paseo.
        $where[] = "tipo NOT IN ('calificacion', 'evidencia')";

        if (!empty($o['servicio']) && $o['servicio'] !== 'todos') {
            $where[] = 'servicio = ?';
            $params[] = $o['servicio']; $tipos .= 's';
        }

        switch ($o['filtro'] ?? 'todos') {
            case 'hoy':        $where[] = 'creado_en >= CURDATE()'; break;
            case '24h':        $where[] = 'creado_en >= DATE_SUB(NOW(), INTERVAL 24 HOUR)'; break;
            case '7d':         $where[] = 'creado_en >= DATE_SUB(NOW(), INTERVAL 7 DAY)'; break;
            case 'pendientes': $where[] = "(resuelto = 0 OR estado = 'pendiente')"; break;
            case 'urgentes':   $where[] = "(estado = 'urgente' OR prioridad = 'alta')"; break;
            case 'cancelados': $where[] = "estado = 'cancelado'"; break;
            case 'completados':$where[] = "estado = 'completado'"; break;
        }

        if (!empty($o['buscar'])) {
            $q = trim($o['buscar']);
            $num = ltrim($q, '#');
            $like = '%' . $q . '%';
            $where[] = '(cliente_nombre LIKE ? OR mascota_nombre LIKE ? OR paseador_nombre LIKE ? OR direccion LIKE ? OR id_pedido = ?)';
            $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
            $params[] = ctype_digit($num) ? (int)$num : 0;
            $tipos .= 'ssssi';
        }

        $incremental = false;
        if (!empty($o['desde_id'])) {
            $where[] = 'id_actividad > ?';
            $params[] = (int)$o['desde_id']; $tipos .= 'i';
            $incremental = true;
        } elseif (!empty($o['antes_id'])) {
            // Cursor de paginación por (creado_en, id)
            $where[] = '(creado_en < ? OR (creado_en = ? AND id_actividad < ?))';
            $af = $o['antes_fecha'] ?? date('Y-m-d H:i:s');
            $params[] = $af; $params[] = $af; $params[] = (int)$o['antes_id'];
            $tipos .= 'ssi';
        }

        $sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $sql = "SELECT * FROM actividad_sistema $sqlWhere
                ORDER BY creado_en DESC, id_actividad DESC
                LIMIT " . ($limit + 1);

        try {
            $stmt = $conn->prepare($sql);
            if ($tipos !== '') $stmt->bind_param($tipos, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            $rows = [];
            while ($row = $res->fetch_assoc()) $rows[] = $row;
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            if (self::tablaFaltante($e)) return ['items' => [], 'hay_mas' => false];
            throw $e;
        }

        $hayMas = count($rows) > $limit;
        if ($hayMas) array_pop($rows);

        $items = array_map([self::class, 'formatear'], $rows);
        return ['items' => $items, 'hay_mas' => $hayMas && !$incremental];
    }

    /** Contadores por pestaña + eventos que requieren atención del admin */
    public static function contadores($conn)
    {
        $out = ['paseos' => 0, 'adiestramiento' => 0, 'hospedaje' => 0,
                'necesitan_atencion' => 0, 'max_id' => 0];
        try {
            $res = $conn->query(
                "SELECT servicio, COUNT(*) AS n,
                        SUM(resuelto = 0) AS pend, MAX(id_actividad) AS mx
                 FROM actividad_sistema
                 WHERE tipo NOT IN ('calificacion', 'evidencia')
                 GROUP BY servicio"
            );
            $maxId = 0; $atencion = 0;
            while ($row = $res->fetch_assoc()) {
                if (isset($out[$row['servicio']])) $out[$row['servicio']] = (int)$row['n'];
                $atencion += (int)$row['pend'];
                $maxId = max($maxId, (int)$row['mx']);
            }
            $out['necesitan_atencion'] = $atencion;
            $out['max_id'] = $maxId;
        } catch (mysqli_sql_exception $e) {
            if (!self::tablaFaltante($e)) throw $e;
        }
        return $out;
    }

    /** Da forma de presentación a una fila: icono, color, chips, acciones, hora */
    private static function formatear(array $r)
    {
        $tipo = $r['tipo'];
        list($icono, $color) = self::$ICONO[$tipo] ?? ['fa-bell', '#64748b'];

        return [
            'id'          => (int)$r['id_actividad'],
            'servicio'    => $r['servicio'],
            'tipo'        => $tipo,
            'icono'       => $icono,
            'color'       => $color,
            'estado'      => $r['estado'],
            'prioridad'   => $r['prioridad'],
            'titulo'      => $r['titulo'],
            'descripcion' => $r['descripcion'],
            'cliente'     => $r['cliente_nombre'],
            'paseador'    => $r['paseador_nombre'],
            'mascota'     => $r['mascota_nombre'],
            'direccion'   => $r['direccion'],
            'id_pedido'   => $r['id_pedido']  ? (int)$r['id_pedido']  : null,
            'id_ruta'     => $r['id_ruta']    ? (int)$r['id_ruta']    : null,
            'id_cliente'  => $r['id_cliente'] ? (int)$r['id_cliente'] : null,
            'id_referencia' => $r['id_referencia'] ? (int)$r['id_referencia'] : null,
            'resuelto'    => (int)$r['resuelto'],
            'acciones'    => self::acciones($tipo, $r),
            'creado_en'   => $r['creado_en'],
        ];
    }

    /** Acciones rápidas disponibles según el tipo de evento */
    private static function acciones($tipo, $r)
    {
        if ($tipo === 'cancelacion_solicitada' && (int)$r['resuelto'] === 0) {
            return ['ver_motivo', 'aprobar', 'rechazar'];
        }
        if (in_array($tipo, ['pago_aprobado', 'pago_rechazado'], true)) {
            return ['ver_comprobante'];
        }
        // Compra: acceso directo al pedido en el módulo de Paseos
        if ($tipo === 'compra' && $r['id_pedido']) {
            return ['ver_paseo'];
        }
        // Eventos ligados a un paseo con cliente: acciones operativas
        if ($r['id_pedido'] && in_array($tipo, [
            'paseador_asignado','recogido','en_ruta','entregado',
            'incidencia','reprogramado','en_camino','llegada',
            'direccion_validada','cancelacion_aprobada'
        ], true)) {
            return ['ver_cliente', 'ver_mapa', 'abrir_chat'];
        }
        return [];
    }
}
