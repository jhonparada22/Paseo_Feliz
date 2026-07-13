<?php
/**
 * helpers_perfil_publico.php
 * Lógica compartida de los perfiles públicos ("inspeccionar cuenta").
 * La usan las 3 páginas — una por rol, cada una con su sidebar nativo:
 *   view/pagina_principal/perfil.php            (cliente)
 *   view/vistas/paseador/perfil_paseador.php    (paseador)
 *   view/vistas/admin/perfil_admin.php          (admin)
 *
 * Reglas de permiso (viewer → objetivo):
 *   admin    → cualquiera, con teléfono y dirección (ver_ocultos).
 *   paseador → solo clientes asignados en su cronograma (paseos o
 *              adiestramiento, cualquier día), sin ocultos.
 *   cliente  → solo su paseador/entrenador asignado, sin ocultos.
 */

function ppRolDe($conn, $idUsuario) {
    $s = $conn->prepare("SELECT 1 FROM admin WHERE id_usuario = ? LIMIT 1");
    $s->bind_param("i", $idUsuario); $s->execute();
    if ($s->get_result()->num_rows > 0) { $s->close(); return 'admin'; }
    $s->close();
    $s = $conn->prepare("SELECT id_paseador FROM paseadores WHERE id_usuario = ? LIMIT 1");
    $s->bind_param("i", $idUsuario); $s->execute();
    $r = $s->get_result()->fetch_assoc(); $s->close();
    return $r ? 'paseador' : 'cliente';
}

/** ¿Este paseador (id usuario) atiende a este cliente (id usuario)?
 *  Cualquier día, por cronograma de paseos O de adiestramiento. */
function ppAtiendeA($conn, $idUsuarioPaseador, $idUsuarioCliente) {
    $sqls = [
        "SELECT 1 FROM cronograma_paseos c
         JOIN pedidos_paseo p ON p.id_pedido = c.id_pedido
         JOIN paseadores pa   ON pa.id_paseador = c.id_paseador
         WHERE pa.id_usuario = ? AND p.id_usuario = ? LIMIT 1",
        "SELECT 1 FROM cronograma_adiestramiento c
         JOIN pedidos_adiestramiento p ON p.id_pedido = c.id_pedido
         JOIN paseadores pa            ON pa.id_paseador = c.id_paseador
         WHERE pa.id_usuario = ? AND p.id_usuario = ? LIMIT 1",
    ];
    foreach ($sqls as $sql) {
        $s = $conn->prepare($sql);
        $s->bind_param("ii", $idUsuarioPaseador, $idUsuarioCliente);
        $s->execute();
        $hay = $s->get_result()->num_rows > 0;
        $s->close();
        if ($hay) return true;
    }
    return false;
}

/**
 * Resuelve permiso + carga todos los datos del perfil de $idPerfil visto
 * por $idViewer. Devuelve un array con:
 *   rol_viewer, rol_perfil, es_propio, permitido, ver_ocultos,
 *   objetivo {id, nombre}, perfil {biografia...direccion}, mascotas[],
 *   stats|null (solo paseadores), resenas[]
 */
function ppCargarPerfil($conn, $idViewer, $idPerfil) {
    $out = [
        'rol_viewer' => ppRolDe($conn, $idViewer),
        'rol_perfil' => null, 'es_propio' => false,
        'permitido' => false, 'ver_ocultos' => false,
        'objetivo' => null,
        'perfil' => ['biografia' => 'Sin biografía registrada.', 'cumpleanos' => 'No especificado',
                     'profesion' => 'No especificada', 'telefono' => 'No registrado',
                     'direccion' => 'No registrada', 'avatar_url' => '', 'banner_url' => ''],
        'mascotas' => [], 'stats' => null, 'resenas' => [],
    ];

    if ($idPerfil > 0) {
        $s = $conn->prepare("SELECT id, nombre FROM usuarios WHERE id = ?");
        $s->bind_param("i", $idPerfil); $s->execute();
        $out['objetivo'] = $s->get_result()->fetch_assoc();
        $s->close();
    }
    if (!$out['objetivo']) return $out;

    $out['rol_perfil'] = ppRolDe($conn, $idPerfil);
    $out['es_propio']  = ($idPerfil === $idViewer);

    if ($out['es_propio']) {
        return $out; // la página redirige al perfil propio
    } elseif ($out['rol_viewer'] === 'admin') {
        $out['permitido'] = true;
        $out['ver_ocultos'] = true;
    } elseif ($out['rol_viewer'] === 'paseador' && $out['rol_perfil'] === 'cliente') {
        $out['permitido'] = ppAtiendeA($conn, $idViewer, $idPerfil);
    } elseif ($out['rol_viewer'] === 'cliente' && $out['rol_perfil'] === 'paseador') {
        $out['permitido'] = ppAtiendeA($conn, $idPerfil, $idViewer);
    }
    if (!$out['permitido']) return $out;

    $s = $conn->prepare("SELECT * FROM info_usuario WHERE id_usuario = ?");
    $s->bind_param("i", $idPerfil); $s->execute();
    if ($row = $s->get_result()->fetch_assoc()) {
        foreach (['biografia', 'cumpleanos', 'profesion', 'telefono', 'direccion', 'avatar_url', 'banner_url'] as $c) {
            if (!empty($row[$c])) $out['perfil'][$c] = $row[$c];
        }
    }
    $s->close();

    $s = $conn->prepare("SELECT * FROM mascota_usuario WHERE id_usuario = ?");
    $s->bind_param("i", $idPerfil); $s->execute();
    $res = $s->get_result();
    while ($m = $res->fetch_assoc()) $out['mascotas'][] = $m;
    $s->close();

    if ($out['rol_perfil'] === 'paseador') {
        $s = $conn->prepare(
            "SELECT pa.id_paseador, pa.puntuacion, pa.zona_trabajo, pa.paseos_totales
             FROM paseadores pa WHERE pa.id_usuario = ?"
        );
        $s->bind_param("i", $idPerfil); $s->execute();
        $pas = $s->get_result()->fetch_assoc();
        $s->close();

        if ($pas) {
            $idPas = (int)$pas['id_paseador'];
            $s = $conn->prepare(
                "SELECT COUNT(*) AS n, COALESCE(AVG(estrellas),0) AS prom
                 FROM calificaciones_paseo WHERE id_paseador = ?"
            );
            $s->bind_param("i", $idPas); $s->execute();
            $cal = $s->get_result()->fetch_assoc();
            $s->close();

            $out['stats'] = [
                'puntuacion'     => (float)($cal['n'] > 0 ? $cal['prom'] : $pas['puntuacion']),
                'total_resenas'  => (int)$cal['n'],
                'zona_trabajo'   => $pas['zona_trabajo'] ?: 'Cúcuta',
                'paseos_totales' => (int)$pas['paseos_totales'],
            ];

            $s = $conn->prepare(
                "SELECT c.estrellas, c.comentario, c.fecha_creacion, u.nombre AS cliente
                 FROM calificaciones_paseo c
                 JOIN usuarios u ON u.id = c.id_usuario_cliente
                 WHERE c.id_paseador = ?
                 ORDER BY c.fecha_creacion DESC LIMIT 5"
            );
            $s->bind_param("i", $idPas); $s->execute();
            $res = $s->get_result();
            while ($r = $res->fetch_assoc()) $out['resenas'][] = $r;
            $s->close();
        }
    }

    return $out;
}

/** Normaliza rutas de imagen ("../assets/...") al prefijo de la página que la muestra. */
function ppAsset($url, $prefijo) {
    if (empty($url)) return '';
    if (preg_match('#^https?://#i', $url)) return $url;
    $limpia = preg_replace('#^(\.\./)+#', '', ltrim($url, '/'));
    if (strpos($limpia, 'view/') === 0) $limpia = substr($limpia, 5);
    return $prefijo . $limpia;
}
?>
