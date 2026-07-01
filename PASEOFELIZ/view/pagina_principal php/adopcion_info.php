<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once '../../controller/control_acceso.php';
include_once '../../model/conexion.php';

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
    header("Location: adopcion.php");
    exit();
}

$img_src = "../assets/recursos_pagina_principal/adopcion_imagenes/" . htmlspecialchars($adopcion['img_adop']);
$req_lineas = array_filter(array_map('trim', explode("\n", $adopcion['requisitos'])));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paseo Feliz – <?php echo htmlspecialchars($adopcion['nombre']); ?></title>
    <link rel="icon" href="../assets/images/logo.png" type="image/png">
    <link rel="stylesheet" href="../css/principal_css/global.css">
    <link rel="stylesheet" href="../css/principal_css/sidebar_usuario.css">
    <link rel="stylesheet" href="../css/principal_css/adopcion_cliente.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="app-container">

    <nav class="sidebar">
        <div class="menu-hamburguesa-container">
            <div class="profile-circle" id="btn-menu"><i class="fas fa-bars"></i></div>
            <nav class="menu-desplegable" id="menu-latente">
                <ul>
                    <a href="./sub_menu/conocenos.php"><li><i class="fas fa-camera"></i><span>Conócenos</span></li></a>
                    <a href="./sub_menu/direccion_oficial.php"><li><i class="fas fa-book-open"></i><span>Dirección oficial</span></li></a>
                    <a href="./sub_menu/centro_de_ayuda.php"><li><i class="fas fa-sliders-h"></i><span>Centro de ayuda</span></li></a>
                    <a href="./sub_menu/configuracion.php"><li><i class="fas fa-gear"></i><span>Configuración</span></li></a>
                    <li><a href="../../controller/logout.php" style="color:#000"><i class="fas fa-sign-out-alt"></i><span>Cerrar Sesión</span></a></li>
                </ul>
            </nav>
        </div>
        <ul class="nav-links">
            <li><a href="inicio.php"><i class="fas fa-paw"></i><span>Servicios</span></a></li>
            <li><a href="Chat.php"><i class="far fa-comment-alt"></i><span>Chat</span></a></li>
            <li><a href="mapa.php"><i class="fas fa-map-marker-alt"></i><span>Mapa</span></a></li>
            <li class="active"><a href="adopcion.php"><i class="fas fa-bone"></i><span>Adopción</span></a></li>
            <li><a href="usuario.php"><i class="fas fa-user"></i><span>Usuario</span></a></li>
        </ul>
    </nav>

    <main class="info-main">
        <div class="info-back-header">
            <a href="adopcion.php"><i class="fas fa-arrow-left"></i></a>
            <h2>¡Adóptame!</h2>
        </div>

        <div class="info-card">
            <div class="info-layout">
                <!-- Imagen -->
                <div class="info-img-col">
                    <img src="../assets/recursos_pagina_principal/adopcion_imagenes/<?php echo htmlspecialchars($adopcion['img_adop']); ?>"
                         alt="<?php echo htmlspecialchars($adopcion['nombre']); ?>"
                         onerror="this.src='../assets/images/logo.png'">
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

                    <button class="btn-solicitar" onclick="mostrarEnProceso()">
                        <i class="fas fa-paw"></i> Solicitar adopción
                    </button>
                </div>
            </div>

            <!-- Requisitos dentro de la card -->
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

<!-- Toast -->
<div class="toast-proceso" id="toastProceso">
    <i class="fas fa-tools"></i> Función en proceso — pronto estará disponible
</div>

<script>
    const btnMenu = document.getElementById('btn-menu');
    const menuLatente = document.getElementById('menu-latente');
    btnMenu.addEventListener('click', (e) => { e.stopPropagation(); menuLatente.classList.toggle('show'); });
    window.addEventListener('click', (e) => {
        if (!btnMenu.contains(e.target) && !menuLatente.contains(e.target)) menuLatente.classList.remove('show');
    });

    function mostrarEnProceso() {
        const t = document.getElementById('toastProceso');
        t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), 3000);
    }
</script>
</body>
</html>