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

// La mascota queda "en espera" mientras tenga cualquier solicitud pendiente
$stmt = $conn->prepare("SELECT 1 FROM solicitudes_adopcion WHERE id_adopcion = ? AND estado = 'pendiente' LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$enTramite = $stmt->get_result()->num_rows > 0;
$stmt->close();

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
    <link rel="stylesheet" href="../css/principal_css/global.css?v=<?php echo @filemtime(__DIR__ . '/../css/principal_css/global.css'); ?>">
    <link rel="stylesheet" href="../css/principal_css/sidebar_usuario.css?v=<?php echo @filemtime(__DIR__ . '/../css/principal_css/sidebar_usuario.css'); ?>">
    <link rel="stylesheet" href="../css/principal_css/adopcion_cliente.css?v=<?php echo @filemtime(__DIR__ . '/../css/principal_css/adopcion_cliente.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="usuario-page">
<div id="contenedor_general" class="app-container">

    <nav class="sidebar">
        <div class="menu-hamburguesa-container">
            <div class="profile-circle" id="btn-menu"><i class="fas fa-bars"></i></div>
            <nav class="menu-desplegable" id="menu-latente">
                <ul>
                    <a href="./sub_menu/conocenos.php"><li><i class="fas fa-camera"></i><span>Conócenos</span></li></a>
                    <a href="./sub_menu/soporte.php"><li><i class="fas fa-headset"></i><span>Soporte</span></li></a>
                    <a href="./sub_menu/manual.php"><li><i class="fas fa-book-open"></i><span>Manual de uso</span></li></a>
                    
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

                    <?php if ($enTramite): ?>
                        <button class="btn-solicitar" disabled style="opacity:.55;cursor:not-allowed">
                            <i class="fas fa-hourglass-half"></i> Solicitud en trámite
                        </button>
                    <?php else: ?>
                        <button class="btn-solicitar" onclick="abrirModalAdopcion()">
                            <i class="fas fa-paw"></i> Solicitar adopción
                        </button>
                    <?php endif; ?>
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

<!-- Modal: solicitar cita de adopción -->
<div class="modal-overlay-adop" id="modalSolicitarAdopcion">
    <div class="modal-adop">
        <button class="modal-adop-close" onclick="cerrarModalAdopcion()"><i class="fas fa-times"></i></button>
        <h3><i class="fas fa-paw"></i>Solicitar cita para conocer a <?php echo htmlspecialchars($adopcion['nombre']); ?></h3>

        <div class="mad-direccion">
            <i class="fas fa-location-dot"></i>
            <div>
                <strong>Punto de encuentro — Paseo Feliz</strong>
                <p>Calle 7 #0e-94 Motilones, Cúcuta, Norte de Santander, Colombia</p>
            </div>
        </div>

        <label for="madFecha">Fecha de la visita</label>
        <input type="date" id="madFecha">

        <label for="madHora">Hora disponible</label>
        <select id="madHora">
            <option value="10:00">10:00 a.m.</option>
            <option value="11:00">11:00 a.m.</option>
            <option value="12:00">12:00 p.m.</option>
            <option value="13:00">1:00 p.m.</option>
            <option value="14:00">2:00 p.m.</option>
            <option value="15:00">3:00 p.m.</option>
            <option value="16:00">4:00 p.m.</option>
        </select>

        <div class="mad-error" id="madError"></div>

        <button class="mad-btn-enviar" id="madBtnEnviar" onclick="enviarSolicitudAdopcion()">
            <i class="fas fa-paper-plane"></i> Enviar solicitud
        </button>
    </div>
</div>

<script>
    const btnMenu = document.getElementById('btn-menu');
    const menuLatente = document.getElementById('menu-latente');
    btnMenu.addEventListener('click', (e) => { e.stopPropagation(); menuLatente.classList.toggle('show'); });
    window.addEventListener('click', (e) => {
        if (!btnMenu.contains(e.target) && !menuLatente.contains(e.target)) menuLatente.classList.remove('show');
    });

    const ID_ADOPCION = <?php echo (int)$adopcion['id_adopcion']; ?>;

    function abrirModalAdopcion() {
        const hoy = new Date().toISOString().split('T')[0];
        const fechaInput = document.getElementById('madFecha');
        fechaInput.min = hoy;
        fechaInput.value = hoy;
        document.getElementById('madError').classList.remove('show');
        document.getElementById('modalSolicitarAdopcion').classList.add('show');
    }

    function cerrarModalAdopcion() {
        document.getElementById('modalSolicitarAdopcion').classList.remove('show');
    }

    function enviarSolicitudAdopcion() {
        const fecha = document.getElementById('madFecha').value;
        const hora  = document.getElementById('madHora').value;
        const errorEl = document.getElementById('madError');

        if (!fecha) {
            errorEl.textContent = 'Selecciona una fecha para la visita.';
            errorEl.classList.add('show');
            return;
        }
        errorEl.classList.remove('show');

        const btn = document.getElementById('madBtnEnviar');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';

        fetch('../../model/solicitar_adopcion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_adopcion: ID_ADOPCION, fecha_cita: fecha, hora_cita: hora }),
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                window.location.href = 'adopcion.php?solicitud=enviada';
            } else {
                errorEl.textContent = data.message || 'No se pudo enviar la solicitud.';
                errorEl.classList.add('show');
            }
        })
        .catch(() => {
            errorEl.textContent = 'Error de conexión. Intenta de nuevo.';
            errorEl.classList.add('show');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar solicitud';
        });
    }
</script>
</body>
</html>