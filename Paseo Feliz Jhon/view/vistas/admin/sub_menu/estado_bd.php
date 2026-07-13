<?php include_once '../../../../controller/control_acceso.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Paseo Feliz – Estado del sistema</title>
    <link rel="icon" href="../../../assets/images/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../../css/admin/admin.css?v=<?php echo @filemtime(__DIR__ . '/../../../css/admin/admin.css'); ?>">
    <link rel="stylesheet" href="../../../css/admin/sidebar_admin.css?v=<?php echo @filemtime(__DIR__ . '/../../../css/admin/sidebar_admin.css'); ?>">
    <link rel="stylesheet" href="../../../css/principal_css/sub_menu.css?v=<?php echo @filemtime(__DIR__ . '/../../../css/principal_css/sub_menu.css'); ?>">
</head>
<body>
<div class="app-container">

    <nav class="sidebar">
        <div class="menu-hamburguesa-container">
            <div class="profile-circle" id="btn-menu"><i class="fas fa-bars"></i></div>
            <nav class="menu-desplegable" id="menu-latente">
                <ul>
                    <a href="./conocenos.php"><li><i class="fas fa-camera"></i><span>Conócenos</span></li></a>
                    <a href="./estado_bd.php"><li><i class="fas fa-database"></i><span>Estado del sistema</span></li></a>
                    <a href="./manual.php"><li><i class="fas fa-book-open"></i><span>Manual de uso</span></li></a>
                    <li>
                        <a href="../../../../controller/logout.php" style="color:#000000;">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Cerrar Sesión</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <ul class="nav-links">
            <li><a href="../index_admin.php"><i class="fas fa-house"></i><span>Inicio</span></a></li>
            <li><a href="../usuarios_admin.php"><i class="fas fa-users"></i><span>Usuarios</span></a></li>
            <li><a href="../paseadores_admin.php"><i class="fas fa-person-walking"></i><span>Paseadores</span></a></li>
            <li><div class="nav-sep"></div></li>
            <li><a href="../paseos_admin.php"><i class="fas fa-route"></i><span>Paseos</span></a></li>
            <li><a href="../adiestramiento_admin.php"><i class="fas fa-graduation-cap"></i><span>Adiestramiento</span></a></li>
            <li><a href="../hospedaje_admin.php"><i class="fas fa-house"></i><span>Hospedaje</span></a></li>
            <li><a href="../pagos_admin.php"><i class="fas fa-credit-card"></i><span>Pagos</span></a></li>
            <li><div class="nav-sep"></div></li>
            <li><a href="../Chat_admin.php"><i class="far fa-comment-alt"></i><span>Chat</span></a></li>
            <li><a href="../mapa_admin.php"><i class="fas fa-map-location-dot"></i><span>Mapa</span></a></li>
            <li><a href="../adopcion_admin.php"><i class="fas fa-bone"></i><span>Adopción</span></a></li>
            <li><div class="nav-sep"></div></li>
            <li><a href="../usuario_admin.php"><i class="fas fa-user"></i><span>Usuario</span></a></li>
        </ul>
    </nav>

    <div class="main-content">
        <main class="page-area">
            <div class="sm-shell">
                <a href="../index_admin.php" class="sm-volver"><i class="fas fa-arrow-left"></i> Volver</a>

                <div class="sm-titulo"><i class="fas fa-database"></i> Estado del sistema</div>
                <div class="sm-sub">Verificación de salud de la base de datos: conexión y todas las tablas de la aplicación.</div>

                <div id="smEstado">
                    <div class="sm-card" style="text-align:center;color:#64748b">
                        <i class="fas fa-spinner fa-spin" style="font-size:1.4rem"></i>
                        <div style="margin-top:8px;font-size:.85rem">Verificando la base de datos...</div>
                    </div>
                </div>

                <button class="sm-btn" id="smReverificar" style="display:none"><i class="fas fa-rotate"></i> Verificar de nuevo</button>
            </div>
        </main>
    </div>
</div>

<script>
(function () {
    const btn  = document.getElementById('btn-menu');
    const menu = document.getElementById('menu-latente');
    if (!btn || !menu) return;
    btn.addEventListener('click', function (e) { e.stopPropagation(); menu.classList.toggle('show'); });
    document.addEventListener('click', function (e) {
        if (!menu.contains(e.target) && e.target !== btn) menu.classList.remove('show');
    });
})();

function esc(t) {
    const d = document.createElement('div');
    d.textContent = t ?? '';
    return d.innerHTML;
}

async function verificarBD() {
    const cont = document.getElementById('smEstado');
    const btnRe = document.getElementById('smReverificar');
    btnRe.style.display = 'none';
    cont.innerHTML = '<div class="sm-card" style="text-align:center;color:#64748b">' +
        '<i class="fas fa-spinner fa-spin" style="font-size:1.4rem"></i>' +
        '<div style="margin-top:8px;font-size:.85rem">Verificando la base de datos...</div></div>';

    try {
        const r = await fetch('../../../../model/verificar_bd_admin.php').then(res => res.json());
        if (!r.success) throw new Error(r.message || 'sin respuesta');

        let html = '';
        if (r.ok) {
            html += '<div class="sm-estado-ok"><i class="fas fa-circle-check"></i><div>' +
                    '<div>La base de datos está funcionando correctamente.</div>' +
                    '<div style="font-weight:400;font-size:.8rem;margin-top:2px">' + esc(r.resumen) + '</div>' +
                    '</div></div>';
        } else {
            html += '<div class="sm-estado-mal"><i class="fas fa-triangle-exclamation"></i><div>' +
                    '<div>La base de datos está fallando.</div>' +
                    '<div style="font-weight:400;font-size:.8rem;margin-top:2px">' + esc(r.resumen) + '</div>' +
                    '</div></div>';
        }

        html += '<div class="sm-metricas">' +
                '<div class="sm-metrica"><div class="v">' + r.tablas_ok + '/' + r.tablas_total + '</div><div class="l">Tablas respondiendo</div></div>' +
                '<div class="sm-metrica"><div class="v">' + r.latencia_ms + ' ms</div><div class="l">Latencia de consulta</div></div>' +
                '<div class="sm-metrica"><div class="v">' + (r.ok ? 'OK' : 'FALLA') + '</div><div class="l">Estado general</div></div>' +
                '</div>';

        if (r.problemas && r.problemas.length) {
            html += '<div class="sm-card"><h3><i class="fas fa-bug"></i> Qué está fallando</h3>';
            r.problemas.forEach(function (p) {
                html += '<div class="sm-problema"><strong>' + esc(p.tabla) + ':</strong> ' + esc(p.error) + '</div>';
            });
            html += '<div style="font-size:.78rem;color:#64748b;margin-top:8px">' +
                    'Normalmente esto significa que falta ejecutar una migración SQL en phpMyAdmin, ' +
                    'o que la tabla fue borrada/renombrada por accidente.</div></div>';
        }

        cont.innerHTML = html;
    } catch (e) {
        cont.innerHTML = '<div class="sm-estado-mal"><i class="fas fa-plug-circle-xmark"></i><div>' +
            '<div>No se pudo conectar con la base de datos.</div>' +
            '<div style="font-weight:400;font-size:.8rem;margin-top:2px">El servidor no respondió a la verificación. ' +
            'Puede ser un problema de conexión con MySQL o del hosting.</div></div></div>';
    } finally {
        btnRe.style.display = 'inline-flex';
    }
}

document.getElementById('smReverificar').addEventListener('click', verificarBD);
verificarBD();
</script>
</body>
</html>
