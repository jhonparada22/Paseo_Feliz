document.addEventListener('DOMContentLoaded', () => {
    const identifierForm = document.getElementById('identifierForm');
    const passwordForm = document.getElementById('passwordForm');
    const stepIdentifier = document.getElementById('step-identifier');
    const stepPassword = document.getElementById('step-password');
    const identifierInput = document.getElementById('identifier');
    const userIdentifierDisplay = document.getElementById('user-identifier-display');
    const welcomeMessage = document.getElementById('welcome-message');

    let currentIdentifier = '';

    // Manejador para el envío del IDENTIFICADOR (Paso 1)
    if (identifierForm) {
        identifierForm.addEventListener('submit', function(event) {
            event.preventDefault();

            currentIdentifier = identifierInput.value.trim();

            if (currentIdentifier === '') {
                alert('Ingresa tu correo o número de teléfono.');
            } else {
                // 1. Mostrar el identificador en la pantalla de contraseña
                userIdentifierDisplay.textContent = currentIdentifier;
                
                // 2. Transición de pantallas
                stepIdentifier.classList.add('hidden');
                stepPassword.classList.remove('hidden');

                // 3. Opcional: Actualizar el título si queremos que cambie
                welcomeMessage.textContent = 'Ingresa tu contraseña'; 

                console.log(`TRANSICIÓN: Identificador (${currentIdentifier}) aceptado. Pasando a contraseña.`);
                document.getElementById('password').focus();
            }
        });
    }

    // Manejador para el envío de la CONTRASEÑA (Paso 2)
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(event) {
            event.preventDefault();

            const passwordValue = document.getElementById('password').value.trim();
            
            if (passwordValue === '') {
                alert('Ingresa tu contraseña.');
            } else {
                console.log('--- INICIO DE SESIÓN COMPLETO (SIMULADO) ---');
                console.log(`Usuario: ${currentIdentifier}`);
                
                alert(`Inicio de sesión simulado exitoso para ${currentIdentifier} en Apple.`);
                
                // Limpiar y volver al inicio
                passwordForm.reset();
                identifierForm.reset();
                goBackToIdentifier(); 
            }
        });
    }

    // Función para volver a la pantalla de Identificador
    window.goBackToIdentifier = function() {
        stepPassword.classList.add('hidden');
        stepIdentifier.classList.remove('hidden');
        identifierInput.value = currentIdentifier; // Mantiene el último valor
        identifierInput.focus();
        welcomeMessage.textContent = 'Iniciar sesión con la cuenta de Apple'; // Restaura el título
        console.log('VOLVER: Regresando a la pantalla de Identificador.');
    };

    // Función global para crear cuenta
    window.handleCreateAccount = function() {
        console.log('Clic en "Crear cuenta de Apple" (SIMULACIÓN).');
        alert('Simulación: Serás redirigido a la página de creación de cuenta de Apple.');
    };

    // Asegurarse de que el Paso 1 sea visible al cargar
    stepIdentifier.classList.remove('hidden');
    stepPassword.classList.add('hidden');
});