// Espera a que el DOM esté completamente cargado antes de ejecutar el script
document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const createButton = document.querySelector('.create-btn');

    // Manejador para el envío del formulario de inicio de sesión
    if (loginForm) {
        loginForm.addEventListener('submit', function(event) {
            // Previene el comportamiento por defecto de recargar la página
            event.preventDefault();

            const emailInput = loginForm.querySelector('input[type="text"]').value.trim();
            const passwordInput = loginForm.querySelector('input[type="password"]').value.trim();

            console.log('--- INTENTO DE INICIO DE SESIÓN ---');
            
            if (emailInput === '' || passwordInput === '') {
                console.error('ERROR: Ambos campos (Correo/Teléfono y Contraseña) son obligatorios.');
                alert('Por favor, completa ambos campos para iniciar sesión.');
            } else {
                // Simulación de envío de datos
                console.log('Datos listos para ser enviados al servidor (SIMULACIÓN):');
                console.log('Correo/Teléfono:', emailInput);
                console.log('Contraseña: [OCULTA]');
                
                // En una aplicación real, aquí se usaría la API Fetch para enviar los datos:
                // fetch('/login', { method: 'POST', body: JSON.stringify({ email: emailInput, password: passwordInput }) })
                // ...
                
                alert('Inicio de Sesión simulado exitoso. Revisa la consola para ver los datos capturados.');
                loginForm.reset(); // Limpia el formulario después de la simulación
            }
        });
    }

    // Manejador para el botón de "Crear cuenta nueva"
    if (createButton) {
        createButton.addEventListener('click', function() {
            console.log('Clic en "Crear cuenta nueva". Redirigiendo a la página de registro (SIMULACIÓN).');
            // En una aplicación real, aquí iría la redirección:
            // window.location.href = '/registro';
        });
    }
});