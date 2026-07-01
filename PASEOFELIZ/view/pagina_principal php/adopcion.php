<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once '../../controller/control_acceso.php';
include_once '../../model/conexion.php';

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
    <title>Paseo Feliz - Adopción</title>
    <link rel="icon" href="../assets/images/logo.png" type="image/png">
    <link rel="stylesheet" href="../css/principal_css/adopcion.css">
    
    <link rel="stylesheet" href="../css/principal_css/global.css">
    <link rel="stylesheet" href="../css/principal_css/sidebar_usuario.css">
    <link rel="stylesheet" href="../css/principal_css/adopcion_cliente.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div id="contenedor_general" class="app-container">

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
            <li class="active"><a href="#"><i class="fas fa-bone"></i><span>Adopción</span></a></li>
            <li><a href="usuario.php"><i class="fas fa-user"></i><span>Usuario</span></a></li>
        </ul>
    </nav>

    <main class="main-content">
        <section class="section-title adoption-header">
            <h2>¡Adóptame!</h2>
        </section>

        <div class="adopt-grid-db">
            <?php if (empty($adopciones)): ?>
                <div class="adopt-empty">
                    <i class="fas fa-bone"></i>
                    <p>No hay animales disponibles para adopción en este momento.</p>
                </div>
            <?php else: ?>
                <?php foreach ($adopciones as $a): ?>
                    <a href="adopcion_info.php?id=<?php echo $a['id_adopcion']; ?>" class="adopt-card-db">
                        <img src="../assets/recursos_pagina_principal/adopcion_imagenes/<?php echo htmlspecialchars($a['img_adop']); ?>"
                             alt="<?php echo htmlspecialchars($a['nombre']); ?>"
                             onerror="this.src='../assets/images/logo.png'">
                        <div class="adopt-info-panel">
                            <h3><?php echo htmlspecialchars($a['nombre']); ?></h3>
                            <p><?php echo htmlspecialchars($a['edad']); ?> | <?php echo htmlspecialchars($a['tamano']); ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
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