<?php 
// 1. CONTROL DE ACCESO
include_once '../../controller/control_acceso.php'; 

$id_sesion = $_SESSION['usuario_id'] ?? null;

if (!$id_sesion) {
    header("Location: ../../index.php");
    exit();
}

// 2. INCLUIR LA LÓGICA DEL CONTROLADOR (Maneja la API asíncrona y limpieza automática)
include_once '../../controller/chat_controller.php';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paseo Feliz - Chat</title>
    <link rel="icon" href="../assets/images/logo.png" type="image/png">

    <link rel="stylesheet" href="../css/principal_css/global.css?v=<?php echo @filemtime(__DIR__ . '/../css/principal_css/global.css'); ?>">
    <link rel="stylesheet" href="../css/principal_css/sidebar_usuario.css?v=<?php echo @filemtime(__DIR__ . '/../css/principal_css/sidebar_usuario.css'); ?>">
    <link rel="stylesheet" href="../css/principal_css/paseos.css?v=<?php echo @filemtime(__DIR__ . '/../css/principal_css/paseos.css'); ?>">
    <link rel="stylesheet" href="../css/principal_css/Chat.css?v=<?php echo @filemtime(__DIR__ . '/../css/principal_css/Chat.css'); ?>">
    <link rel="stylesheet" href="../css/responsive/responsive_principal.css?v=<?php echo @filemtime(__DIR__ . '/../css/principal_css/responsive_principal.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <div id="contenedor_general" class="app-container">
        <nav class="sidebar">
            <div class="menu-hamburguesa-container">
                <div class="profile-circle" id="btn-menu"><i class="fas fa-bars"></i></div>
                <nav class="menu-desplegable" id="menu-latente">
                    <ul>
                        <a href="./sub_menu/conocenos.php"><li><i class="fas fa-camera"></i> <span>Conocenos</span></li></a>
                        <a href="./sub_menu/soporte.php"><li><i class="fas fa-headset"></i><span>Soporte</span></li></a>
                        <a href="./sub_menu/manual.php"><li><i class="fas fa-book-open"></i><span>Manual de uso</span></li></a>
                        
                        <li><a href="../../controller/logout.php" style="color: #000000;"><i class="fas fa-sign-out-alt"></i> <span>Cerrar Sesión</span></a></li>
                    </ul>
                </nav>
            </div>
            <ul class="nav-links">
                <li><a href="inicio.php"><i class="fas fa-paw"></i> <span>Servicios</span></a></li>
                <li class="active"><a href="#"><i class="far fa-comment-alt"></i> <span>Chat</span></a></li>
                <li><a href="mapa.php"><i class="fas fa-map-marker-alt"></i> <span>Mapa</span></a></li>
                <li><a href="adopcion.php"><i class="fas fa-bone"></i> <span>Adopción</span></a></li>
                <li><a href="usuario.php"><i class="fas fa-user"></i> <span>Usuario</span></a></li>
            </ul>
        </nav>

        <div class="chat-main">
            <div class="conversations-panel" id="conv-panel">
                <div class="conversations-header"><h2>Conversaciones</h2></div>
                <div class="search-conv">
                    <i class="fas fa-search"></i>
                    <input type="text" id="search-input" placeholder="Buscar usuario por nombre..." />
                </div>
                <div class="conv-list" id="conv-list"></div>
            </div>

            <div class="chat-window" id="chat-window">
                <div class="empty-chat" id="empty-state">
                    <i class="far fa-comment-dots"></i>
                    <p>Busca un usuario o selecciona un chat para comenzar</p>
                </div>

                <div class="chat-header" id="chat-header" style="display:none;">
                    <button class="chat-header-back" id="btn-back" style="display:none;"><i class="fas fa-arrow-left"></i></button>
                    <div class="chat-header-avatar" id="header-avatar"></div>
                    <div class="chat-header-info">
                        <div class="chat-header-name" id="header-name"></div>
                    </div>
                </div>

                <div class="messages-area" id="messages-area" style="display:none;"></div>

                <div id="emoji-picker" class="emoji-picker"></div>

                <div class="input-area" id="input-area" style="display:none;">
                    <button class="input-icon-btn" title="Emoji" id="btn-emoji"><i class="far fa-smile"></i></button>
                    <input type="file" id="file-chat-input" accept="image/*" style="display: none;" />
                    <button class="input-icon-btn" title="Adjuntar foto" id="btn-attach" onclick="document.getElementById('file-chat-input').click();">
                        <i class="fas fa-paperclip"></i>
                    </button>
                    <div class="input-wrapper">
                        <textarea id="msg-input" rows="1" placeholder="Escribe un mensaje..."></textarea>
                    </div>
                    <button class="send-btn" id="btn-send" disabled><i class="fas fa-paper-plane"></i></button>
                </div>
            </div>
        </div> 
    </div>

    <ul id="context-menu" class="custom-context-menu">
        <li id="ctx-copy"><i class="far fa-copy"></i> Copiar texto</li>
        <li id="ctx-delete" class="delete-opt"><i class="far fa-trash-alt"></i> Borrar mensaje</li>
    </ul>

    <script>
        const idSesionActual = <?php echo json_encode($id_sesion); ?>;
        const rolSesionActual = <?php echo json_encode($rol_sesion); ?>;
    </script>

    <script src="../js/notificaciones_local.js?v=<?php echo @filemtime(__DIR__ . '/../js/notificaciones_local.js'); ?>"></script>
    <script src="../js/chats/Chat.js?v=<?php echo @filemtime(__DIR__ . '/../js/chats/Chat.js'); ?>"></script>
</body>
</html>