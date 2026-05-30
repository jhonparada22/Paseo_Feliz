<?php include_once '../../controller/control_acceso.php'; ?>
<!DOCTYPE html>
<html lan<?php include_once '../../api/control_acceso.php'; ?>g="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paseo Feliz - Servicios</title>
    <link rel="icon" href="../assets/images/logo.png" type="image/png">

    <link rel="stylesheet" href="../css/principal_css/global.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>

    <div id="contenedor_general" class="app-container">

        <nav class="sidebar">
            <div class="menu-hamburguesa-container">
                <div class="profile-circle" id="btn-menu">
                    <i class="fas fa-bars"></i>
                </div>
                <nav class="menu-desplegable" id="menu-latente">
                    <ul>
                        <a href="./sub_menu/conocenos.php">
                            <li>
                                <i class="fas fa-camera"></i>
                                <span>Conocenos</span>
                            </li>
                        </a>

                        <a href="./sub_menu/direccion_oficial.php">
                            <li>
                                <i class="fas fa-book-open"></i>
                                <span>Direccion oficial</span>
                            </li>
                        </a>

                        <a href="./sub_menu/centro_de_ayuda.php">
                            <li>
                                <i class="fas fa-sliders-h"></i>
                                <span>Centro de ayuda</span>
                            </li>
                        </a>

                        <a href="./sub_menu/configuracion.php">
                            <li>
                                <i class="fas fa-gear"></i>
                                <span>Configuracion</span>
                            </li>
                        </a>

                        <li>
                             <a href="../../controller/logout.php" style="color: #000000;">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Cerrar Sesión</span>
                            </a>
                        </li>

                    </ul>
                </nav>
            </div>

            <ul class="nav-links">
                <li>
                    <a href="inicio.php">
                        <i class="fas fa-paw"></i>
                        <span>Servicios</span>
                    </a>
                </li>
                <li>
                <li class="active"> <a href="#">
                        <i class="far fa-comment-alt"></i>
                        <span>Chat</span>
                    </a>
                </li>
                <li>
                    <a href="mapa.php">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Mapa</span>
                    </a>
                </li>
                <li>
                    <a href="adopcion.php">
                        <i class="fas fa-bone"></i>
                        <span>Adopción</span>
                    </a>
                </li>
                <li>
                    <a href="usuario.php">
                        <i class="fas fa-user"></i>
                        <span>Usuario</span>
                    </a>
                </li>
            </ul>
        </nav>

    </div>

</body>
<script>
    const btnMenu = document.getElementById('btn-menu');
    const menuLatente = document.getElementById('menu-latente');

    btnMenu.addEventListener('click', () => {
        // Alterna la clase 'show' para mostrar/ocultar
        menuLatente.classList.toggle('show');
    });

    // Opcional: Cerrar el menú si haces clic fuera de él
    window.addEventListener('click', (e) => {
        if (!btnMenu.contains(e.target) && !menuLatente.contains(e.target)) {
            menuLatente.classList.remove('show');
        }
    });
</script>

</html>