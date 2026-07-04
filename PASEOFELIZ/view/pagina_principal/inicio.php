<?php
include_once '../../controller/control_acceso.php';
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paseo Feliz - Servicios</title>
    <link rel="icon" href="../assets/images/logo.png" type="image/png">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/principal_css/inicio_cliente.css">
    <link rel="stylesheet" href="../css/principal_css/sidebar_usuario.css">
    <link rel="stylesheet" href="../css/responsive/responsive_principal.css?v=<?php echo @filemtime(__DIR__ . '/../css/principal_css/responsive_principal.css'); ?>">
</head>
<body class="usuario-page">

<div id="contenedor_general" class="app-container">

    <!-- SIDEBAR (igual que adopcion.html / Chat.html / usuario.html) -->
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
                            <span>Dirección oficial</span>
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
                            <span>Configuración</span>
                        </li>
                    </a>
                    <li><a href="../../controller/logout.php"><i class="fas fa-sign-out-alt"></i><span>Cerrar sesión</span></a></li>
                </ul>
            </nav>
        </div>

        <ul class="nav-links">
            <li class="active">
                <a href="#">
                    <i class="fas fa-paw"></i>
                    <span>Servicios</span>
                </a>
            </li>
            <li>
                <a href="Chat.php">
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

    <!-- MAIN -->
    <main class="main">

        <!-- TOP BAR -->
        <header class="top-bar">
            <h1 class="welcome">¡Hola de nuevo, <?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?>! <span class="paw">🐾</span></h1>
            <div class="date-badge">
                <i class="ph ph-calendar-blank"></i>
                <span id="current-date">25 de junio de 2026</span>
            </div>
        </header>

        <!-- SECTION HEADER -->
        <div class="section-header">
            <div class="divider-line"></div>
            <i class="ph ph-paw-print"></i>
            Nuestros productos y servicios
            <i class="ph ph-paw-print"></i>
            <div class="divider-line right"></div>
        </div>

        <!-- SERVICE TABS -->
        <div class="service-tabs">
            <div class="tab active" data-svc="paseos">
                <div class="tab-icon">🐶</div>
                <div>
                    <h3>Paseos</h3>
                    <p>Ejercicio, diversión y bienestar</p>
                </div>
                <div class="tab-arrow"></div>
            </div>
            <div class="tab" data-svc="adiestramiento">
                <div class="tab-icon">🎓</div>
                <div>
                    <h3>Adiestramiento canino</h3>
                    <p>Educación positiva y efectiva</p>
                </div>
                <div class="tab-arrow"></div>
            </div>
            <div class="tab" data-svc="hospedaje">
                <div class="tab-icon">🏠</div>
                <div>
                    <h3>Hospedaje canino</h3>
                    <p>Como en casa, pero mejor</p>
                </div>
                <div class="tab-arrow"></div>
            </div>
        </div>

        <!-- SHOWCASE: 3 cols -->
        <div class="showcase" id="showcase">

            <!-- SKELETON OVERLAY (cambio de tab) -->
            <div class="sk-inline-overlay">
                <!-- Carrusel sk -->
                <div class="sk-carousel">
                    <div class="sk sk-main-img"></div>
                    <div class="sk-thumbs">
                        <div class="sk sk-thumb"></div><div class="sk sk-thumb"></div>
                        <div class="sk sk-thumb"></div><div class="sk sk-thumb"></div>
                    </div>
                </div>
                <!-- Detalles sk -->
                <div class="sk-details">
                    <div class="sk sk-title"></div>
                    <div class="sk sk-rating"></div>
                    <div class="sk sk-desc"></div><div class="sk sk-desc w80"></div>
                    <div style="height:8px"></div>
                    <div class="sk-feat-row"><div class="sk sk-feat-icon"></div><div class="sk sk-feat-txt"></div></div>
                    <div class="sk-feat-row"><div class="sk sk-feat-icon"></div><div class="sk sk-feat-txt"></div></div>
                    <div class="sk-feat-row"><div class="sk sk-feat-icon"></div><div class="sk sk-feat-txt"></div></div>
                    <div class="sk-feat-row"><div class="sk sk-feat-icon"></div><div class="sk sk-feat-txt"></div></div>
                    <div class="sk-badges"><div class="sk sk-badge"></div><div class="sk sk-badge"></div><div class="sk sk-badge"></div></div>
                </div>
                <!-- Booking sk -->
                <div class="sk-booking">
                    <div class="sk sk-avail"></div>
                    <div class="sk sk-price"></div>
                    <div class="sk sk-unit"></div>
                    <div class="sk sk-benefit"></div>
                    <div class="sk sk-benefit w85"></div>
                    <div class="sk sk-benefit"></div>
                    <div class="sk sk-benefit w70"></div>
                    <div style="flex:1"></div>
                    <div class="sk sk-btn-p"></div>
                </div>
            </div>

            <!-- COL 1: CAROUSEL -->
            <div class="carousel-col">
                <div class="img-wrapper">
                    <img id="main-img" src="../assets/images/g5.png" alt="Servicio">
                    <span class="img-tag" id="img-tag"><i class="ph ph-shield-check"></i> Como en casa, pero con más amor</span>
                    <button class="arr-btn prev" id="prev-btn"><i class="ph ph-caret-left"></i></button>
                    <button class="arr-btn next" id="next-btn"><i class="ph ph-caret-right"></i></button>
                </div>
                <div class="thumbs" id="thumbs-container"></div>
                <div class="dots" id="dots-container"></div>
            </div>

            <!-- COL 2: DETAILS -->
            <div class="details-col">
                <h2 class="svc-title" id="svc-title">Paseos</h2>
                <div class="rating-row">
                    <span class="stars">★★★★★</span>
                    <span class="rating-num" id="svc-rating">4.9</span>
                    <span class="client-cnt"><i class="ph ph-users"></i> <span id="svc-clients">+1.200 clientes felices</span></span>
                </div>
                <p class="svc-desc" id="svc-desc">Tu mascota disfrutará de paseos seguros, divertidos y adaptados a su energía. Nosotros cuidamos de su bienestar mientras tú tienes tranquilidad.</p>
                <div class="features-list" id="features-list"></div>
                <div class="badges" id="badges-row"></div>
            </div>

            <!-- COL 3: BOOKING -->
            <div class="booking-col">
                <div class="avail" id="svc-avail">
                    <span class="avail-dot"></span>
                    Disponible hoy
                </div>
                <div class="price-lbl">Desde</div>
                <div class="price-val" id="svc-price">$18.000</div>
                <div class="price-unit" id="svc-unit">por paseo</div>
                <ul class="benefits" id="benefits-list"></ul>
                <button class="btn-primary" id="btn-reservar">
                    <i class="ph ph-paw-print"></i> Reservar ahora <i class="ph ph-arrow-right"></i>
                </button>
                <button class="btn-secondary" id="btn-disponibilidad">
                    <i class="ph ph-calendar-check"></i> Ver disponibilidad
                </button>
            </div>
        </div>

        <!-- BOTTOM -->
        <div class="bottom-section" style="display:grid;">
        <!-- SKELETON OVERLAY BOTTOM (cambio de tab) -->
            <div class="sk-bottom-overlay">
                <div class="sk-info-card"><div class="sk sk-ic-icon"></div><div class="sk sk-ic-title"></div><div class="sk sk-ic-desc"></div></div>
                <div class="sk-info-card"><div class="sk sk-ic-icon"></div><div class="sk sk-ic-title"></div><div class="sk sk-ic-desc"></div></div>
                <div class="sk-info-card"><div class="sk sk-ic-icon"></div><div class="sk sk-ic-title"></div><div class="sk sk-ic-desc"></div></div>
                <div class="sk-info-card"><div class="sk sk-ic-icon"></div><div class="sk sk-ic-title"></div><div class="sk sk-ic-desc"></div></div>
                <div class="sk-right-panel">
                    <div class="sk-stats">
                        <div class="sk-stat"><div class="sk sk-stat-num"></div><div class="sk sk-stat-desc"></div></div>
                        <div class="sk-stat"><div class="sk sk-stat-num"></div><div class="sk sk-stat-desc"></div></div>
                        <div class="sk-stat"><div class="sk sk-stat-num"></div><div class="sk sk-stat-desc"></div></div>
                    </div>
                    <div class="sk-testimonial">
                        <div class="sk-t-line w90"></div><div class="sk-t-line w75"></div>
                        <div class="sk-t-author"><div class="sk-t-avatar"></div><div class="sk-t-name"></div></div>
                    </div>
                </div>
            </div>

            <div class="community-wrap">
                <!-- INFO CARDS (4) -->
                <div id="info-cards" style="display:contents"></div>

                <!-- COMMUNITY + STATS + TESTIMONIAL -->
                <div class="right-panel">
                    <div class="community-label">
                        <i class="ph ph-sparkle"></i> Nuestra comunidad
                    </div>
                    <div class="stats-row" id="stats-row"></div>
                    <div class="testimonial-card">
                        <div class="test-nav">
                            <button class="test-btn" id="tprev"><i class="ph ph-caret-left"></i></button>
                            <button class="test-btn" id="tnext"><i class="ph ph-caret-right"></i></button>
                        </div>
                        <div class="test-quote">"</div>
                        <div class="test-stars">★★★★★</div>
                        <p class="test-text" id="test-text">Mi perrito la pasó increíble, lo cuidaron como si fuera de ellos. ¡Totalmente recomendado!</p>
                        <div class="test-author">
                            <img id="test-img" src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=80&q=80" alt="testimonio">
                            <div>
                                <h5 id="test-name">Valentina G.</h5>
                                <span id="test-role">Cliente satisfecha</span>
                            </div>
                        </div>
                        <div class="test-dots" id="test-dots"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FOOTER BAR -->
        <div class="footer-bar" id="footer-bar">
            <i class="ph ph-heart"></i>
            <span id="footer-text">Seguridad, cariño y diversión en un solo lugar. 🐾</span>
        </div>

    </main>
</div>

<script src="../js/js pagina principal/inicio_cliente.js"></script>
</body>
</html>