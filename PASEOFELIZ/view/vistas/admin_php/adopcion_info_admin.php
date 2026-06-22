<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once '../../../controller/control_acceso.php';
include_once '../../../model/conexion.php';

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

$adopcion = null;
if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM adopcion WHERE id_adopcion = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $adopcion = $result->fetch_assoc();
    $stmt->close();
}

if (!$adopcion) {
    header("Location: adopcion_admin.php");
    exit();
}

$img_src = "../../assets/recursos_pagina_principal/adopcion_imagenes/" . htmlspecialchars($adopcion['img_adop']);

// Convertir saltos de línea de requisitos en lista HTML
$req_lineas = array_filter(array_map('trim', explode("\n", $adopcion['requisitos'])));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paseo Feliz – <?php echo htmlspecialchars($adopcion['nombre']); ?></title>
    <link rel="icon" href="../../assets/images/logo.png" type="image/png">
    <link rel="stylesheet" href="../../css/admin/admin.css">
    <link rel="stylesheet" href="../../css/admin/sidebar.css">
    <link rel="stylesheet" href="../../css/admin/adopcion.css">
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
                    <a href="./sub_menu/direccion_oficial.php"><li><i class="fas fa-book-open"></i><span>Dirección oficial</span></li></a>
                    <a href="./sub_menu/centro_de_ayuda.php"><li><i class="fas fa-sliders-h"></i><span>Centro de ayuda</span></li></a>
                    <a href="./sub_menu/configuracion.php"><li><i class="fas fa-gear"></i><span>Configuración</span></li></a>
                    <li><a href="../../../controller/logout.php" style="color:#fff"><i class="fas fa-sign-out-alt"></i><span>Cerrar Sesión</span></a></li>
                </ul>
            </nav>
        </div>
        <ul class="nav-links">
            <li><a href="index_admin.php"><i class="fas fa-house"></i><span>Inicio</span></a></li>
            <li><a href="usuarios_admin.php"><i class="fas fa-users"></i><span>Usuarios</span></a></li>
            <li><a href="paseadores_admin.php"><i class="fas fa-person-walking"></i><span>Paseadores</span></a></li>
            <li><div class="nav-sep"></div></li>
            <li><a href="paseos_admin.php"><i class="fas fa-route"></i><span>Paseos</span></a></li>
            <li><a href="pagos_admin.php"><i class="fas fa-credit-card"></i><span>Pagos</span></a></li>
            <li><div class="nav-sep"></div></li>
            <li><a href="Chat_admin.php"><i class="far fa-comment-alt"></i><span>Chat</span></a></li>
            <li><a href="mapa_admin.php"><i class="fas fa-map-location-dot"></i><span>Mapa</span></a></li>
            <li class="active"><a href="adopcion_admin.php"><i class="fas fa-bone"></i><span>Adopción</span></a></li>
            <li><div class="nav-sep"></div></li>
            <li><a href="usuario_admin.php"><i class="fas fa-user"></i><span>Usuario</span></a></li>
        </ul>
    </nav>

    <!-- ══ CONTENIDO ══════════════════════════════════════════════ -->
    <main class="info-main">
        <div class="info-header">
            <a href="adopcion_admin.php"><i class="fas fa-arrow-left"></i></a>
            <h1>Detalle de Adopción</h1>
        </div>

        <div class="info-card">
            <div class="info-layout">
                <!-- Imagen -->
                <div class="info-img-col">
                    <img src="<?php echo $img_src; ?>"
                         alt="<?php echo htmlspecialchars($adopcion['nombre']); ?>"
                         onerror="this.src='../../assets/images/logo.png'">
                </div>

                <!-- Datos -->
                <div class="info-data-col">
                    <div class="info-nombre"><?php echo htmlspecialchars($adopcion['nombre']); ?></div>

                    <div class="info-stats">
                        <div class="stat-item">
                            <label>Edad</label>
                            <span><?php echo htmlspecialchars($adopcion['edad']); ?></span>
                        </div>
                        <div class="stat-item">
                            <label>Tamaño</label>
                            <span><?php echo htmlspecialchars($adopcion['tamano']); ?></span>
                        </div>
                        <div class="stat-item">
                            <label>Raza</label>
                            <span><?php echo htmlspecialchars($adopcion['raza']); ?></span>
                        </div>
                        <div class="stat-item">
                            <label>Color</label>
                            <span><?php echo htmlspecialchars($adopcion['color']); ?></span>
                        </div>
                    </div>

                    <p class="info-desc"><?php echo nl2br(htmlspecialchars($adopcion['descripcion'])); ?></p>

                    <div class="admin-actions">
                        <button class="btn-eliminar" onclick="document.getElementById('modalBorrar').classList.add('open')">
                            <i class="fas fa-trash-alt"></i> Eliminar adopción
                        </button>
                    </div>
                </div>
            </div>

            <!-- Requisitos -->
            <div class="req-section">
                <h3><i class="fas fa-clipboard-list"></i> Requisitos para adoptar a <?php echo htmlspecialchars($adopcion['nombre']); ?></h3>
                <ul class="req-list">
                    <?php foreach ($req_lineas as $linea): ?>
                        <li><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($linea); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </main>
</div>

<!-- ── MODAL CONFIRMAR BORRAR ──────────────────────────────────── -->
<div class="modal-overlay" id="modalBorrar">
    <div class="modal-box">
        <i class="fas fa-exclamation-triangle"></i>
        <h3>¿Eliminar adopción?</h3>
        <p>¿Seguro que deseas eliminar a <strong><?php echo htmlspecialchars($adopcion['nombre']); ?></strong>? Esta acción no se puede deshacer.</p>
        <div class="modal-btns">
            <button class="btn-cancel-modal" onclick="document.getElementById('modalBorrar').classList.remove('open')">Cancelar</button>
            <a href="adopcion_admin.php?borrar=<?php echo $adopcion['id_adopcion']; ?>" class="btn-confirm-del">Sí, eliminar</a>
        </div>
    </div>
</div>

<script>
    const btnMenu = document.getElementById('btn-menu');
    const menuLatente = document.getElementById('menu-latente');
    btnMenu.addEventListener('click', (e) => { e.stopPropagation(); menuLatente.classList.toggle('show'); });
    window.addEventListener('click', (e) => {
        if (!btnMenu.contains(e.target) && !menuLatente.contains(e.target)) menuLatente.classList.remove('show');
    });
</script>
</body>
</html>