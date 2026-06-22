// sidebar.js
document.addEventListener("DOMContentLoaded", () => {
    const sidebarContainer = document.getElementById("sidebar-container");

    if (sidebarContainer) {
        sidebarContainer.innerHTML = `
            <nav class="sidebar">
                <div class="menu-hamburguesa-container">
                    <div class="profile-circle" id="btn-menu"><i class="fas fa-bars"></i></div>
                    <nav class="menu-desplegable" id="menu-latente">
                        <ul>
                            <li><i class="fas fa-camera"></i>Conócenos</li>
                            <li><i class="fas fa-book-open"></i>Dirección oficial</li>
                            <li><i class="fas fa-headset"></i>Centro de ayuda</li>
                            <li><i class="fas fa-gear"></i>Configuración</li>
                            <li><i class="fas fa-sign-out-alt"></i>Cerrar sesión</li>
                        </ul>
                    </nav>
                </div>
                <ul class="nav-links">
                    <li data-page="inicio"><a href="index_admin.html"><i class="fas fa-house"></i><span>Inicio</span></a></li>
                    <li data-page="usuarios"><a href="usuarios_admin.html"><i class="fas fa-users"></i><span>Usuarios</span></a></li>
                    <li data-page="paseadores"><a href="paseadores_admin.html"><i class="fas fa-person-walking"></i><span>Paseadores</span></a></li>
                    <div class="nav-sep"></div>
                    <li data-page="paseos"><a href="paseos_admin.html"><i class="fas fa-route"></i><span>Paseos</span></a></li>
                    <li data-page="pagos"><a href="pagos_admin.html"><i class="fas fa-credit-card"></i><span>Pagos</span></a></li>
                    <div class="nav-sep"></div>
                    <li data-page="chat"><a href="Chat_admin.html"><i class="far fa-comment-alt"></i><span>Chat</span></a></li>
                    <li data-page="mapa"><a href="mapa_admin.html"><i class="fas fa-map-location-dot"></i><span>Mapa</span></a></li>
                    <li data-page="adopcion"><a href="adopcion_admin.html"><i class="fas fa-bone"></i><span>Adopción</span></a></li>
                    <div class="nav-sep"></div>
                    <li data-page="config"><a href="configuracion_admin.html"><i class="fas fa-gear"></i><span>Config</span></a></li>
                    <li data-page="usuario"><a href="usuario_admin.html"><i class="fas fa-user"></i><span>Usuario</span></a></li>
                </ul>
            </nav>
        `;

        // Lógica automática para marcar el botón "activo" según la página actual
        marcarPaginaActiva();
    }
});

function marcarPaginaActiva() {
    // Detecta el nombre del archivo actual (ej: "usuarios_admin.html")
    const path = window.location.pathname;
    const page = path.split("/").pop();

    // Quitamos la clase active de todos por si acaso
    document.querySelectorAll(".nav-links li").forEach(li => li.classList.remove("active"));

    // Asignamos la clase activa dependiendo del archivo
    if (page === "index_admin.html" || page === "") {
        document.querySelector('[data-page="inicio"]')?.classList.add("active");
    } else if (page === "usuarios_admin.html") {
        document.querySelector('[data-page="usuarios"]')?.classList.add("active");
    } else if (page === "paseadores_admin.html") {
        document.querySelector('[data-page="paseadores"]')?.classList.add("active");
    } else if (page === "paseos_admin.html") {
        document.querySelector('[data-page="paseos"]')?.classList.add("active");
    } else if (page === "pagos_admin.html") {
        document.querySelector('[data-page="pagos"]')?.classList.add("active");
    } else if (page === "Chat_admin.html") {
        document.querySelector('[data-page="chat"]')?.classList.add("active");
    } else if (page === "mapa_admin.html") {
        document.querySelector('[data-page="mapa"]')?.classList.add("active");
    } else if (page === "adopcion_admin.html") {
        document.querySelector('[data-page="adopcion"]')?.classList.add("active");
    } else if (page === "configuracion_admin.html") {
        document.querySelector('[data-page="config"]')?.classList.add("active");
    } else if (page === "usuario_admin.html") {
        document.querySelector('[data-page="usuario"]')?.classList.add("active");
    }
}