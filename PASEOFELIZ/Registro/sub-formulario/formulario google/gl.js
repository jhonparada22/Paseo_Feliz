document.addEventListener('DOMContentLoaded', () => {
    const identifierForm = document.getElementById('identifierForm');
    const passwordForm = document.getElementById('passwordForm');
    const stepIdentifier = document.getElementById('step-identifier');
    const stepPassword = document.getElementById('step-password');
    const identifierInput = document.getElementById('identifier');
    const userEmailDisplay = document.getElementById('user-email-display');
    const passwordInput = document.getElementById('password');
    const showPasswordCheckbox = document.getElementById('show-password');

    // Estado para almacenar el identificador (correo/teléfono)
    let currentIdentifier = '';

    // Función para manejar el envío del IDENTIFICADOR (Paso 1)
    if (identifierForm) {
        identifierForm.addEventListener('submit', function(event) {
            event.preventDefault();

            currentIdentifier = identifierInput.value.trim();

            if (currentIdentifier === '') {
                console.error('ERROR: El campo de identificación es obligatorio.');
                alert('Ingresa tu correo electrónico o teléfono para continuar.');
            } else {
                // Actualiza el display del correo en la pantalla de contraseña
                userEmailDisplay.textContent = currentIdentifier;
                
                // Transición de pantallas
                stepIdentifier.classList.add('hidden');
                stepPassword.classList.remove('hidden');

                // Opcional: Enfocar automáticamente el campo de contraseña
                passwordInput.focus();

                console.log('TRANSICIÓN: Identificador aceptado. Pasando a solicitar contraseña.');
            }
        });
    }

    // Función para manejar el envío de la CONTRASEÑA (Paso 2)
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(event) {
            event.preventDefault();

            const passwordValue = passwordInput.value.trim();
            
            if (passwordValue === '') {
                console.error('ERROR: El campo de contraseña es obligatorio.');
                alert('Ingresa tu contraseña.');
            } else {
                console.log('--- INICIO DE SESIÓN COMPLETO (SIMULADO) ---');
                console.log(`Usuario: ${currentIdentifier}`);
                console.log('Contraseña: [CAPTURA SIMULADA]');
                
                // Simulación de inicio de sesión exitoso
                alert(`¡Inicio de sesión simulado exitoso para ${currentIdentifier}!`);
                
                // Limpiar campos y volver al inicio (o redirigir)
                passwordForm.reset();
                identifierForm.reset();
                goBackToIdentifier(); // Vuelve a la pantalla de identificador
            }
        });
    }

    // Función para volver a la pantalla de Identificador
    window.goBackToIdentifier = function() {
        stepPassword.classList.add('hidden');
        stepIdentifier.classList.remove('hidden');
        identifierInput.focus(); // Vuelve a enfocar el input de identificador
        identifierInput.value = currentIdentifier; // Mantiene el identificador
        console.log('VOLVER: Regresando a la pantalla de Identificador.');
    };

    // Manejar el checkbox "Mostrar contraseña"
    if (showPasswordCheckbox) {
        showPasswordCheckbox.addEventListener('change', function() {
            if (this.checked) {
                passwordInput.type = 'text';
            } else {
                passwordInput.type = 'password';
            }
        });
    }

    // Función global para crear cuenta
    window.handleCreateAccount = function() {
        console.log('Clic en "Crear una cuenta". Redirigiendo a registro (SIMULACIÓN).');
        alert('Simulación: Serás redirigido a la página de creación de cuenta de Google.');
        // window.location.href = 'https://accounts.google.com/signup'; // Redirección real
    };

    // Asegurarse de que el Paso 1 sea visible al cargar
    stepIdentifier.classList.remove('hidden');
    stepPassword.classList.add('hidden');
});