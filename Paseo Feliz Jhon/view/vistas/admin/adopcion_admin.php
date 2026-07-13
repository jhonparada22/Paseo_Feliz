<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once '../../../controller/control_acceso.php';
include_once '../../../model/conexion.php';

// ── ACCIÓN: BORRAR ───────────────────────────────────────────────
if (isset($_GET['borrar']) && is_numeric($_GET['borrar'])) {
    $id_borrar = (int)$_GET['borrar'];
    // Recuperar nombre de imagen antes de borrar para eliminar el archivo
    $stmt_img = $conn->prepare("SELECT img_adop FROM adopcion WHERE id_adopcion = ?");
    $stmt_img->bind_param("i", $id_borrar);
    $stmt_img->execute();
    $stmt_img->bind_result($img_borrar);
    $stmt_img->fetch();
    $stmt_img->close();

    $stmt_del = $conn->prepare("DELETE FROM adopcion WHERE id_adopcion = ?");
    $stmt_del->bind_param("i", $id_borrar);
    $stmt_del->execute();
    $stmt_del->close();

    // Intentar borrar el archivo de imagen del servidor
    $ruta_img = "../../assets/recursos_pagina_principal/adopcion_imagenes/" . $img_borrar;
    if (file_exists($ruta_img)) @unlink($ruta_img);

    header("Location: adopcion_admin.php?msg=borrado");
    exit();
}

// ── CARGAR ADOPCIONES ────────────────────────────────────────────
$adopciones = [];
$res = $conn->query("SELECT id_adopcion, nombre, img_adop, edad, tamano FROM adopcion ORDER BY fecha_reg DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $adopciones[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paseo Feliz – Adopción (Admin)</title>
    <link rel="icon" href="../../assets/images/logo.png" type="image/png">
    <link rel="stylesheet" href="../../css/admin/admin.css?v=<?php echo @filemtime(__DIR__ . '/../../css/admin/admin.css'); ?>">
    <link rel="stylesheet" href="../../css/admin/sidebar_admin.css?v=<?php echo @filemtime(__DIR__ . '/../../css/admin/sidebar_admin.css'); ?>">
    <link rel="stylesheet" href="../../css/admin/adopcion.css?v=<?php echo @filemtime(__DIR__ . '/../../css/admin/adopcion.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="app-container">

    <!-- ══ SIDEBAR ══════════════════════════════════════════════ -->
    <nav class="sidebar">
        <div class="menu-hamburguesa-container">
            <div class="profile-circle" id="btn-menu"><i class="fas fa-bars"></i></div>
            <nav class="menu-desplegable" id="menu-latente">
                <ul>
                    <a href="./sub_menu/conocenos.php"><li><i class="fas fa-camera"></i><span>Conócenos</span></li></a>
                    <a href="./sub_menu/estado_bd.php"><li><i class="fas fa-database"></i><span>Estado del sistema</span></li></a>
                    <a href="./sub_menu/manual.php"><li><i class="fas fa-book-open"></i><span>Manual de uso</span></li></a>
                    
                    <li><a href="../../../controller/logout.php" style="color:#000000;"><i class="fas fa-sign-out-alt"></i><span>Cerrar Sesión</span></a></li>
                </ul>
            </nav>
        </div>
        <ul class="nav-links">
            <li><a href="index_admin.php"><i class="fas fa-house"></i><span>Inicio</span></a></li>
            <li><a href="usuarios_admin.php"><i class="fas fa-users"></i><span>Usuarios</span></a></li>
            <li><a href="paseadores_admin.php"><i class="fas fa-person-walking"></i><span>Paseadores</span></a></li>
            <li><div class="nav-sep"></div></li>
            <li><a href="paseos_admin.php"><i class="fas fa-route"></i><span>Paseos</span></a></li>
            <li><a href="adiestramiento_admin.php"><i class="fas fa-graduation-cap"></i><span>Adiestramiento</span></a></li>
            <li><a href="hospedaje_admin.php"><i class="fas fa-house"></i><span>Hospedaje</span></a></li>
            <li><a href="pagos_admin.php"><i class="fas fa-credit-card"></i><span>Pagos</span></a></li>
            <li><div class="nav-sep"></div></li>
            <li><a href="Chat_admin.php"><i class="far fa-comment-alt"></i><span>Chat</span></a></li>
            <li><a href="mapa_admin.php"><i class="fas fa-map-location-dot"></i><span>Mapa</span></a></li>
            <li class="active"><a href="#"><i class="fas fa-bone"></i><span>Adopción</span></a></li>
            <li><div class="nav-sep"></div></li>
            <li><a href="usuario_admin.php"><i class="fas fa-user"></i><span>Usuario</span></a></li>
        </ul>
    </nav>

    <!-- ══ CONTENIDO ══════════════════════════════════════════════ -->
    <main class="adopt-main">

        <div class="adopt-page-header">
            <div>
                <h1>Gestión de Adopciones</h1>
                <p>Administra los animales disponibles para adopción.</p>
            </div>
            <a href="adopcion_agregar_admin.php" class="btn-agregar">
                <i class="fas fa-plus"></i> Agregar adopción
            </a>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'borrado'): ?>
            <div class="toast-msg"><i class="fas fa-check-circle"></i> Adopción eliminada correctamente.</div>
        <?php endif; ?>
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'guardado'): ?>
            <div class="toast-msg"><i class="fas fa-check-circle"></i> Nueva adopción agregada correctamente.</div>
        <?php endif; ?>

        <div class="adopt-grid">
            <?php if (empty($adopciones)): ?>
                <div class="empty-adopt">
                    <i class="fas fa-bone"></i>
                    <p>No hay adopciones registradas aún.<br>Presiona <strong>Agregar adopción</strong> para comenzar.</p>
                </div>
            <?php else: ?>
                <?php foreach ($adopciones as $a): ?>
                    <?php
                        $img_src = "../../assets/recursos_pagina_principal/adopcion_imagenes/" . htmlspecialchars($a['img_adop']);
                    ?>
                    <div class="adopt-card" onclick="location.href='adopcion_info_admin.php?id=<?php echo $a['id_adopcion']; ?>'">
                        <img class="adopt-card-img" src="<?php echo $img_src; ?>" alt="<?php echo htmlspecialchars($a['nombre']); ?>"
                             onerror="this.src='../../assets/images/logo.png'">
                        <div class="adopt-card-body">
                            <h3><?php echo htmlspecialchars($a['nombre']); ?></h3>
                            <p><?php echo htmlspecialchars($a['edad']); ?> | <?php echo htmlspecialchars($a['tamano']); ?></p>
                        </div>
                        <button class="btn-borrar-card" title="Eliminar"
                            onclick="event.stopPropagation(); confirmarBorrar(<?php echo $a['id_adopcion']; ?>, '<?php echo htmlspecialchars($a['nombre'], ENT_QUOTES); ?>')">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </main>
</div>

<!-- ── MODAL CONFIRMAR BORRAR ──────────────────────────────────── -->
<div class="modal-overlay" id="modalBorrar">
    <div class="modal-box">
        <i class="fas fa-exclamation-triangle"></i>
        <h3>¿Eliminar adopción?</h3>
        <p id="modalBorrarNombre">¿Seguro que deseas eliminar a <strong></strong>? Esta acción no se puede deshacer.</p>
        <div class="modal-btns">
            <button class="btn-cancel-modal" onclick="cerrarModal()">Cancelar</button>
            <a id="linkConfirmarBorrar" href="#" class="btn-confirm-del">Sí, eliminar</a>
        </div>
    </div>
</div>

<script>
    // Menú hamburguesa
    const btnMenu = document.getElementById('btn-menu');
    const menuLatente = document.getElementById('menu-latente');
    btnMenu.addEventListener('click', (e) => { e.stopPropagation(); menuLatente.classList.toggle('show'); });
    window.addEventListener('click', (e) => {
        if (!btnMenu.contains(e.target) && !menuLatente.contains(e.target)) menuLatente.classList.remove('show');
    });

    // Modal borrar
    function confirmarBorrar(id, nombre) {
        document.getElementById('modalBorrarNombre').innerHTML = `¿Seguro que deseas eliminar a <strong>${nombre}</strong>? Esta acción no se puede deshacer.`;
        document.getElementById('linkConfirmarBorrar').href = `adopcion_admin.php?borrar=${id}`;
        document.getElementById('modalBorrar').classList.add('open');
    }
    function cerrarModal() {
        document.getElementById('modalBorrar').classList.remove('open');
    }
</script>
</body>
</html>
