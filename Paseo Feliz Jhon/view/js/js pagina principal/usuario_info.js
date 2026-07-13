document.addEventListener('DOMContentLoaded', () => {
    // Lógica del Menú Hamburguesa Lateral
    const btnMenu = document.getElementById('btn-menu');
    const menuLatente = document.getElementById('menu-latente');

    if (btnMenu && menuLatente) {
        btnMenu.addEventListener('click', () => {
            menuLatente.classList.toggle('show');
        });

        window.addEventListener('click', (e) => {
            if (!btnMenu.contains(e.target) && !menuLatente.contains(e.target)) {
                menuLatente.classList.remove('show');
            }
        });
    }
});

// Lógica de intercambio de secciones (Mascotas)
function switchMascotaTab(action) {
    const tabEditar = document.getElementById('tab-editar');
    const tabAgregar = document.getElementById('tab-agregar');
    const secEditar = document.getElementById('section-editar-mascota');
    const secAgregar = document.getElementById('section-agregar-mascota');
    const inputAccion = document.getElementById('mascota_accion');

    if (inputAccion) {
        inputAccion.value = action;
    }

    if (action === 'editar') {
        if (tabEditar) tabEditar.classList.add('active');
        if (tabAgregar) tabAgregar.classList.remove('active');
        if (secEditar) secEditar.classList.remove('hidden-section');
        if (secAgregar) secAgregar.classList.add('hidden-section');
    } else {
        if (tabAgregar) tabAgregar.classList.add('active');
        if (tabEditar) tabEditar.classList.remove('active');
        if (secAgregar) secAgregar.classList.remove('hidden-section');
        if (secEditar) secEditar.classList.add('hidden-section');
    }
}

function soloNumeros(e) {
    // Capturamos el código de la tecla presionada
    var key = e.keyCode || e.which;
    var teclado = String.fromCharCode(key);
    
    // Definimos qué caracteres permitimos (solo del 0 al 9)
    var numeros = "0123456789";
    
    // Teclas especiales que debemos permitir (Retroceso / Borrar, Tabulador, Flechas)
    var especiales = ["8", "9", "37", "39", "46"];
    
    var tecla_especial = false;
    for (var i in especiales) {
        if (key == especiales[i]) {
            tecla_especial = true;
            break;
        }
    }
    
    // Si no es un número y no es una tecla especial, bloqueamos la entrada
    if (numeros.indexOf(teclado) == -1 && !tecla_especial) {
        return false;
    }
}