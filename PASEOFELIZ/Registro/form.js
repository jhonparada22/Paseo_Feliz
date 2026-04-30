// Nuevas referencias para móvil
const btnSignUpMobile = document.getElementById("btn-sign-up-mobile");
const btnSignInMobile = document.getElementById("btn-sign-in-mobile");

// Configuración de eventos para los botones de móvil[cite: 3, 4]
btnSignUpMobile.addEventListener("click", () => {
  container.classList.add("toggle"); // Acción para PC[cite: 3]
  container.classList.add("active-register"); // Acción para móvil
  limpiarTodo();
});

btnSignInMobile.addEventListener("click", () => {
  container.classList.remove("toggle"); // Acción para PC[cite: 3]
  container.classList.remove("active-register"); // Acción para móvil
  limpiarTodo();
});

const container = document.querySelector(".container");
const btnSignIn = document.getElementById("btn-sign-in");
const btnSignUp = document.getElementById("btn-sign-up");
const formRegister = document.getElementById("form-register");
const formLogin = document.getElementById("form-login");

// NUEVOS ELEMENTOS DEL CAPTCHA
const captchaOverlay = document.getElementById("captcha-overlay");
const captchaDisplay = document.getElementById("captcha-display");
const captchaInput = document.getElementById("captcha-input");
const captchaVerifyBtn = document.getElementById("captcha-verify-btn");
const captchaRefreshBtn = document.getElementById("captcha-refresh-btn");
const captchaMessage = document.getElementById("captcha-message");
const captchaCloseBtn = document.getElementById("captcha-close-btn");

let currentCaptchaString = ''; // String correcto del CAPTCHA
let userDataToSave = {}; // Objeto para guardar temporalmente los datos del usuario

// NUEVOS ELEMENTOS PARA RECUPERACIÓN DE CONTRASEÑA
const resetOverlay = document.getElementById("password-reset-overlay");
const resetStep1 = document.getElementById("reset-step-1");
const resetStep2 = document.getElementById("reset-step-2");
const resetEmailInput = document.getElementById("reset-email-input");
const resetEmailError = document.getElementById("reset-email-error");
const resetTargetEmail = document.getElementById("reset-target-email");
const btnSendCode = document.getElementById("btn-send-code");
const resetCodeInput = document.getElementById("reset-code-input");
const resetCodeError = document.getElementById("reset-code-error");
const btnVerifyCode = document.getElementById("btn-verify-code");
const btnResendCode = document.getElementById("btn-resend-code");
const resendTimerSpan = document.getElementById("resend-timer");
const btnResetCancel = document.getElementById("btn-reset-cancel");
const forgotPasswordLink = document.querySelector("#form-login a[href='#']");

// NUEVAS CONSTANTES PARA EL PASO 3
const resetStep3 = document.getElementById("reset-step-3");
const newPasswordInput = document.getElementById("reset-new-password");
const newPasswordError = document.getElementById("reset-new-password-error");
const btnChangePassword = document.getElementById("btn-change-password");

let verificationCode = ''; // Almacena el código de verificación de 6 números
let resetEmail = ''; // Almacena el correo electrónico en el proceso
let resendInterval; // Para controlar el contador
const RESEND_TIME = 30; // 30 segundos


// ===== FUNCIONES DE GESTIÓN DE USUARIOS (MODIFICADA) =====
function getUsuarios() {
  // Recupera el arreglo de usuarios, o un arreglo vacío si no existe.
  const usuarios = localStorage.getItem("usuarios");
  return usuarios ? JSON.parse(usuarios) : [];
}

function isEmailRegistered(email) {
  const usuarios = getUsuarios();
  const lowerCaseEmail = email.toLowerCase();
  // Busca si existe algún usuario con el mismo email (ya almacenado en minúsculas)
  return usuarios.some(user => user.email === lowerCaseEmail);
}

function addUsuario(userData) {
  const usuarios = getUsuarios();
  usuarios.push(userData);
  localStorage.setItem("usuarios", JSON.stringify(usuarios));
}

/**
 * Función: Actualiza la contraseña de un usuario por su correo electrónico.
 */
function updatePassword(email, newPassword) {
    const usuarios = getUsuarios();
    const index = usuarios.findIndex(user => user.email === email);
    
    if (index !== -1) {
        usuarios[index].password = newPassword; 
        localStorage.setItem("usuarios", JSON.stringify(usuarios));
        return true;
    }
    return false;
}
// ======================================================


// ===== CAMBIO ENTRE LOGIN / REGISTRO (NO MODIFICADAS) =====
btnSignIn.addEventListener("click", () => {
  container.classList.remove("toggle");
  limpiarTodo();
  formRegister.classList.remove("hidden");
});

btnSignUp.addEventListener("click", () => {
  container.classList.add("toggle");
  limpiarTodo();
  formLogin.classList.remove("hidden");
});

// ===== MOSTRAR / OCULTAR CONTRASEÑA (NO MODIFICADAS) =====
document.querySelectorAll(".toggle-password").forEach(icon => {
  icon.addEventListener("click", () => {
    const input = icon.previousElementSibling;
    const isPassword = input.getAttribute("type") === "password";
    input.setAttribute("type", isPassword ? "text" : "password");
    icon.setAttribute("name", isPassword ? "eye-off-outline" : "eye-outline");
  });
});

// ===== VALIDACIÓN DE CONTRASEÑA (ACTUALIZADA: Un número y mensaje corregido) =====
function isPasswordValid(password) {
    const minLength = password.length >= 5;
    // La lógica para al menos un número
    const hasOneNumber = (password.match(/\d/g) || []).length >= 1; 
    // Caracteres especiales: !@#$%^&*()_+-=[]{};':"\\|,.<>/?
    const hasSpecialChar = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);

    if (!minLength) {
        return "La contraseña debe tener al menos 5 caracteres.";
    }
    if (!hasOneNumber) {
        // MENSAJE CORREGIDO
        return "La contraseña debe contener al menos un número.";
    }
    if (!hasSpecialChar) {
        return "La contraseña debe contener al menos 1 carácter especial.";
    }
    return true;
}

// ===== VALIDACIÓN DE CORREO (NO MODIFICADAS) =====
function esCorreoValido(email) {
  const regex = /^[a-zA-Z0-9._%+-]+@(gmail\.com|hotmail\.com|outlook\.com)$/i;
  return regex.test(email);
}

// ===== VALIDAR CAMPOS VACÍOS (NO MODIFICADAS) =====
function validarCampo(idInput, idError, mensajeVacio) {
  const input = document.getElementById(idInput);
  const error = document.getElementById(idError);
  const containerInput = input.closest('.container-input');
  
  let valor = input.value.trim();

  if (!valor) {
    error.textContent = mensajeVacio;
    containerInput.style.border = '1px solid #d62828';
    return false;
  } else {
    error.textContent = "";
    containerInput.style.border = 'none';
    return true;
  }
}

// ===== VALIDAR CORREO EN TIEMPO REAL (NO MODIFICADAS) =====
const correos = ["register-email", "login-email"];
correos.forEach(id => {
  const input = document.getElementById(id);
  const error = document.getElementById(`${id}-error`);
  const containerInput = input.closest('.container-input');

  input.addEventListener("input", () => {
    const valor = input.value.trim();

    // Si está vacío, se deja la validación de campo vacío al enviar el formulario
    if (!valor) {
      error.textContent = "";
      containerInput.style.border = 'none';
      return;
    } 
    
    // Validación de formato
    if (!esCorreoValido(valor)) {
      error.textContent = "Solo se permiten correos de Gmail, Hotmail o Outlook.";
      containerInput.style.border = '1px solid #d62828';
    } else {
      error.textContent = "";
      containerInput.style.border = 'none';
    }
  });
});

// ===== CAPTCHA DE TEXTO LÓGICA (MODIFICADO para volver a login) =====
function generateCaptchaString() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let result = '';
    for (let i = 0; i < 6; i++) {
        result += chars.charAt(Math.floor(Math.random() * chars.length)); 
    }
    return result;
}

function renderCaptcha() {
    currentCaptchaString = generateCaptchaString();
    captchaInput.value = '';
    captchaMessage.textContent = '';
    captchaMessage.style.color = '#d62828';
    
    captchaDisplay.innerHTML = '';
    for (let i = 0; i < currentCaptchaString.length; i++) {
        const span = document.createElement('span');
        span.textContent = currentCaptchaString[i];
        span.style.color = `hsl(${Math.random() * 360}, 60%, 40%)`; 
        span.style.transform = `rotate(${Math.random() * 10 - 5}deg)`;
        span.style.display = 'inline-block';
        captchaDisplay.appendChild(span);
    }
}

function showCaptcha(userData) {
    userDataToSave = userData;
    renderCaptcha();
    captchaOverlay.classList.remove('hidden');
    captchaInput.focus();
}

function hideCaptcha() {
    captchaOverlay.classList.add('hidden');
    captchaMessage.textContent = '';
}

// Evento para refrescar el CAPTCHA
captchaRefreshBtn.addEventListener('click', renderCaptcha);

// Evento para verificar el CAPTCHA
captchaVerifyBtn.addEventListener('click', () => {
    const userInput = captchaInput.value.trim();
    
    if (userInput.toUpperCase() === currentCaptchaString.toUpperCase()) {
        
        captchaMessage.textContent = "Verificación exitosa. Registrando...";
        captchaMessage.style.color = 'green';
        
        addUsuario(userDataToSave); 
        
        setTimeout(() => {
            hideCaptcha();
            limpiarTodo();
            // -------------------------------------------------------------
            mostrarAlertaPersonalizada("¡Registro exitoso! Ahora puede iniciar sesión con su nueva cuenta.");
            setTimeout(() => {
                window.location.href = "../Pagina_principal/index.html";
            }, 2000); 
        }, 1000);
        
    } else {
        captchaMessage.textContent = "Código incorrecto. Inténtalo de nuevo.";
        captchaMessage.style.color = '#d62828';
        renderCaptcha();
    }
});

captchaCloseBtn.addEventListener('click', hideCaptcha);


// ===== FUNCIONES PARA RECUPERACIÓN DE CONTRASEÑA (NO MODIFICADAS) =====
function generateVerificationCode() {
    let code = '';
    for (let i = 0; i < 6; i++) {
        code += Math.floor(Math.random() * 10); // Genera un número del 0 al 9
    }
    return code;
}

/**
 * Función que crea un archivo de texto con el código y fuerza su descarga.
 * @param {string} code El código de 6 dígitos.
 * @param {string} email El correo asociado.
 */
function downloadCodeFile(code, email) {
    const content = `Código de Verificación para ${email}:\n${code}\n\nNota: Este archivo es para simular el envío por correo. Úsalo para ingresar el código de 6 números.`;
    const filename = `codigo_verificacion_${email.split('@')[0]}_${new Date().getTime()}.txt`;
    
    // Crear un Blob (objeto de archivo) con el contenido
    const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
    
    // Crear un enlace temporal para la descarga
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    
    // Simular el clic para iniciar la descarga
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}


function showResetModal() {
    resetEmailInput.value = '';
    resetEmailError.textContent = '';
    resetEmailInput.closest('.container-input').style.border = 'none';

    resetStep1.classList.remove('hidden');
    resetStep2.classList.add('hidden');
    resetStep3.classList.add('hidden'); // Ocultar Paso 3
    resetOverlay.classList.remove('hidden');
    
    resetCodeError.textContent = '';
    resetCodeInput.value = '';
    resetCodeInput.closest('.container-input').style.border = 'none';
    
    // Limpiar campos de paso 3
    newPasswordInput.value = ''; 
    newPasswordError.textContent = ''; 
    if(newPasswordInput.closest('.container-input')) {
        newPasswordInput.closest('.container-input').style.border = 'none'; 
    }
    
    clearInterval(resendInterval);
    resendTimerSpan.textContent = '';
    btnResendCode.disabled = false;
}

function hideResetModal() {
    clearInterval(resendInterval);
    resetOverlay.classList.add('hidden');
}

function startResendTimer() {
    clearInterval(resendInterval);
    let timeLeft = RESEND_TIME;
    btnResendCode.disabled = true;
    
    resendTimerSpan.textContent = `(${timeLeft}s)`; 

    resendInterval = setInterval(() => {
        timeLeft--;
        resendTimerSpan.textContent = `(${timeLeft}s)`; 
        if (timeLeft <= 0) {
            clearInterval(resendInterval);
            btnResendCode.disabled = false;
            resendTimerSpan.textContent = ''; // Limpia el contador
        }
    }, 1000);
}

function proceedToSendCode() {
    // Generar código de verificación
    verificationCode = generateVerificationCode();
    
    resetTargetEmail.textContent = resetEmail;
    resetStep1.classList.add('hidden');
    resetStep2.classList.remove('hidden');
    resetCodeInput.value = ''; // Limpiar campo de código
    resetCodeError.textContent = ''; // Limpiar error
    resetCodeInput.closest('.container-input').style.border = 'none';
    
    // 1. Llamar a la nueva función para descargar el archivo de texto
    downloadCodeFile(verificationCode, resetEmail);
    
    // 2. Informar al usuario 
    mostrarAlertaPersonalizada(`Se ha generado y descargado un archivo .txt con el código de verificación para ${resetEmail}. Por favor, revísalo en tu carpeta de descargas para obtener el código.`);
    
    // 3. Iniciar el contador
    startResendTimer();
}
// ======================================================


// ===== BOTÓN REGISTRARSE (NO MODIFICADO) =====
document.getElementById("btn-registrarse-form").addEventListener("click", () => {
    // Flag general de validez. Se asume true al inicio.
    let todoValido = true; 

    // --- 1. Validar campo Nombre de Usuario ---
    if (!validarCampo("register-nombre", "register-nombre-error", "Debe llenar este campo")) {
        todoValido = false;
    }
    
    // --- 2. Validar campo Email ---
    const emailInput = document.getElementById("register-email");
    const email = emailInput.value.trim();
    const emailLower = email.toLowerCase();
    const emailError = document.getElementById("register-email-error");
    const emailContainer = emailInput.closest('.container-input');

    // Validación de campo vacío de Email
    if (!validarCampo("register-email", "register-email-error", "Debe llenar este campo")) {
        todoValido = false;
    } else {
        // Si el campo no está vacío, validar formato
        if (!esCorreoValido(email)) {
            emailError.textContent = "Solo se permiten correos de Gmail, Hotmail o Outlook.";
            emailContainer.style.border = '1px solid #d62828';
            todoValido = false;
        } else {
            // Si el formato es válido, chequear duplicado
            if (isEmailRegistered(emailLower)) {
                emailError.textContent = "Este correo ya está registrado";
                emailContainer.style.border = '1px solid #d62828';
                todoValido = false;
            } else {
                // Limpiar error de formato/duplicado si pasa la validación
                emailError.textContent = "";
                emailContainer.style.border = 'none';
            }
        }
    }


    // --- 3. Validar campo Contraseña ---
    const passwordInput = document.getElementById("register-password");
    const password = passwordInput.value.trim();
    const passwordError = document.getElementById("register-password-error");
    const passwordContainer = passwordInput.closest('.container-input');

    // Validación de campo vacío de Contraseña
    if (!validarCampo("register-password", "register-password-error", "Debe llenar este campo")) {
        todoValido = false;
    } else {
        // Si el campo no está vacío, validar seguridad
        const passwordCheckResult = isPasswordValid(password);
        if (passwordCheckResult !== true) {
            passwordError.textContent = passwordCheckResult;
            passwordContainer.style.border = '1px solid #d62828';
            todoValido = false;
        } else {
             // Limpiar error de seguridad si pasa la validación
            passwordError.textContent = "";
            passwordContainer.style.border = 'none';
        }
    }
    
    // --- 4. Validar campo Sexo ---
    if (!validarCampo("register-sexo", "register-sexo-error", "Debe seleccionar un sexo")) {
        todoValido = false;
    }

// --- 5. Verificación final y acción ---
if (todoValido) {
    // 1. Recoger datos
    const userData = {
        nombre: document.getElementById("register-nombre").value.trim(),
        email: emailLower,
        sexo: document.getElementById("register-sexo").value, 
        password: password
    };
    
    // CAMBIO: Guardar directamente en lugar de llamar a showCaptcha(userData)
    addUsuario(userData); 
    
    // Limpiar y notificar éxito
    limpiarTodo();
    mostrarAlertaPersonalizada("¡Registro exitoso! Ahora puede iniciar sesión con su nueva cuenta.");
    
    setTimeout(() => {
        window.location.href = "../Pagina_principal/index.html";
    }, 2000); 
}
});

// ===== LOGIN (NO MODIFICADO) =====
document.getElementById("btn-login").addEventListener("click", () => {
  // 1. Validar campo Nombre de Usuario
  let nombreValid = validarCampo("login-nombre", "login-nombre-error", "Debe llenar este campo");

  // 2. Validar campo Email
  let emailValid = validarCampo("login-email", "login-email-error", "Debe llenar este campo");

  // 3. Validar campo Contraseña
  let passwordValid = validarCampo("login-password", "login-password-error", "Debe llenar este campo");

  const nombreInput = document.getElementById("login-nombre");
  const nombreErrorSpan = document.getElementById("login-nombre-error");
  const nombreContainer = nombreInput.closest('.container-input');
  
  const emailInput = document.getElementById("login-email");
  const emailErrorSpan = document.getElementById("login-email-error");
  const emailContainer = emailInput.closest('.container-input');

  const passwordInput = document.getElementById("login-password");
  const passwordErrorSpan = document.getElementById("login-password-error");
  const passwordContainer = passwordInput.closest('.container-input');
  
  const nombre = nombreInput.value.trim();
  const email = emailInput.value.trim().toLowerCase(); 
  const password = passwordInput.value.trim();
  
  // Si no está vacío, validar formato de email
  if (email && !esCorreoValido(email)) {
    emailErrorSpan.textContent = "Solo se permiten correos de Gmail, Hotmail o Outlook.";
    emailContainer.style.border = '1px solid #d62828';
    emailValid = false;
  } 

  if (!nombreValid || !emailValid || !passwordValid) return;

  // Lógica de autenticación
  const usuarios = getUsuarios(); 
  const foundUser = usuarios.find(u => u.email === email); 
  
  // Limpiar mensajes de error previos antes de la nueva validación de credenciales
  nombreErrorSpan.textContent = "";
  nombreContainer.style.border = 'none';
  emailErrorSpan.textContent = "";
  emailContainer.style.border = 'none';
  passwordErrorSpan.textContent = "";
  passwordContainer.style.border = 'none';

  if (!foundUser) {
    // Error: Correo no registrado
    emailErrorSpan.textContent = "Este correo no está registrado";
    emailContainer.style.border = '1px solid #d62828';
  } else if (foundUser.password !== password) {
    // Error: Contraseña incorrecta
    passwordErrorSpan.textContent = "Contraseña incorrecta";
    passwordContainer.style.border = '1px solid #d62828';
  } else if (foundUser.nombre !== nombre) {
    // Error: Nombre de usuario incorrecto (asumiendo que debe coincidir)
    nombreErrorSpan.textContent = "El nombre de usuario no coincide con el registrado.";
    nombreContainer.style.border = '1px solid #d62828';
  }
  else {
    // Login exitoso
    mostrarAlertaPersonalizada(`Bienvenido A Paseo Feliz, ${foundUser.nombre}! 👋`);
    limpiarTodo();

setTimeout(() => {
        window.location.href = "../Pagina_principal/index.html";
    }, 2000);
  }
});

// ===== EVENTOS DEL MODAL DE RECUPERACIÓN DE CONTRASEÑA (NO MODIFICADOS) =====

// 1. Mostrar modal al hacer clic en el enlace
forgotPasswordLink.addEventListener("click", (e) => {
    e.preventDefault();
    showResetModal();
});

// 2. Cancelar/Cerrar modal
btnResetCancel.addEventListener("click", hideResetModal);

// 3. ENVIAR CÓDIGO (Paso 1)
btnSendCode.addEventListener("click", () => {
    const email = resetEmailInput.value.trim();
    const emailContainer = resetEmailInput.closest('.container-input');
    resetEmail = email.toLowerCase(); // Guarda el correo en minúsculas

    // Limpiar errores previos
    resetEmailError.textContent = '';
    emailContainer.style.border = 'none';

    if (!email) {
        resetEmailError.textContent = "Debe ingresar su correo electrónico.";
        emailContainer.style.border = '1px solid #d62828';
    } else if (!esCorreoValido(email)) {
        resetEmailError.textContent = "Solo se permiten correos de Gmail, Hotmail o Outlook.";
        emailContainer.style.border = '1px solid #d62828';
    } else if (!isEmailRegistered(resetEmail)) {
        resetEmailError.textContent = "Este correo no se encuentra registrado.";
        emailContainer.style.border = '1px solid #d62828';
    } else {
        // Correo válido y registrado
        proceedToSendCode();
    }
});


// 4. REENVIAR CÓDIGO (Vuelve a empezar el proceso de envío y el contador)
btnResendCode.addEventListener("click", () => {
    if (!btnResendCode.disabled) {
        proceedToSendCode(); // Genera nuevo código y reinicia contador
    }
});

// 5. VERIFICAR CÓDIGO (Paso 2) - Transición a Paso 3
btnVerifyCode.addEventListener("click", () => {
    const userInputCode = resetCodeInput.value.trim();
    const codeContainer = resetCodeInput.closest('.container-input');

    // Limpiar errores previos
    resetCodeError.textContent = '';
    codeContainer.style.border = 'none';
    
    // El código debe ser de 6 caracteres y solo números
    if (userInputCode.length !== 6 || !/^\d{6}$/.test(userInputCode)) {
         resetCodeError.textContent = "El código debe ser de 6 números.";
         codeContainer.style.border = '1px solid #d62828';
    } else if (userInputCode === verificationCode) {
        // Transición al Paso 3
        resetCodeError.textContent = "Código verificado con éxito. Ingrese su nueva contraseña.";
        resetCodeError.style.color = 'green';
        clearInterval(resendInterval);
        
        setTimeout(() => {
            resetStep2.classList.add('hidden');
            resetStep3.classList.remove('hidden'); 
            resetCodeError.textContent = ''; // Limpiar mensaje después de la transición
            newPasswordInput.focus(); // Enfocar el nuevo campo
        }, 800);

    } else {
        resetCodeError.textContent = "El código ingresado es incorrecto.";
        codeContainer.style.border = '1px solid #d62828';
        resetCodeInput.value = '';
    }
});


// NUEVO EVENTO: 6. CAMBIAR CONTRASEÑA (Paso 3)
btnChangePassword.addEventListener("click", () => {
    const newPassword = newPasswordInput.value.trim();
    const passwordContainer = newPasswordInput.closest('.container-input');
    
    // Limpiar errores previos
    newPasswordError.textContent = "";
    passwordContainer.style.border = 'none';

    // 1. Validar campo vacío
    if (!newPassword) {
        newPasswordError.textContent = "Debe llenar este campo.";
        passwordContainer.style.border = '1px solid #d62828';
        return;
    } 

    // 2. Validar seguridad de la contraseña (reutilizando la función isPasswordValid)
    const passwordCheckResult = isPasswordValid(newPassword);
    if (passwordCheckResult !== true) {
        newPasswordError.textContent = passwordCheckResult;
        passwordContainer.style.border = '1px solid #d62828';
        return;
    }
    
    // 3. Actualizar la contraseña en localStorage
    const success = updatePassword(resetEmail, newPassword);

    if (success) {
        mostrarAlertaPersonalizada("¡Contraseña cambiada exitosamente! Ahora puede iniciar sesión con su nueva contraseña.");
        hideResetModal();
        limpiarTodo(); // Limpiar formularios, incluyendo el login
    } else {
        newPasswordError.textContent = "Error al intentar cambiar la contraseña. Intente de nuevo.";
        passwordContainer.style.border = '1px solid #d62828';
    }
});


// ===== FUNCIONES DE LIMPIEZA (NO MODIFICADAS) =====
function limpiarTodo() {
  document.querySelectorAll("input").forEach(input => input.value = "");
  document.querySelectorAll("select").forEach(select => select.value = ""); // Limpia el select
  document.querySelectorAll(".error-msg").forEach(msg => msg.textContent = "");
  document.querySelectorAll(".container-input").forEach(container => container.style.border = 'none');
  document.querySelectorAll(".password-field input").forEach(input => input.setAttribute("type", "password"));
  document.querySelectorAll(".toggle-password").forEach(icon => icon.setAttribute("name", "eye-outline"));
}

// Función para sustituir los alert nativos por el modal del HTML
function mostrarAlertaPersonalizada(mensaje) {
    const notificationOverlay = document.getElementById("notification-overlay");
    const notificationMessage = document.getElementById("notification-message");

    if (notificationOverlay && notificationMessage) {
        // 1. Ponemos el mensaje y lo hacemos visible
        notificationMessage.textContent = mensaje;
        notificationOverlay.classList.remove("hidden");

        // 2. Se cerrará automáticamente tras 2 segundos (2000ms)
        setTimeout(() => {
            notificationOverlay.classList.add("hidden");
        }, 2000); 
    }
}

// Ejemplo de función para alternar pantallas en móvil
function toggleMobileForms() {
    const loginForm = document.getElementById('form-login');
    const registerForm = document.getElementById('form-register');
    
    loginForm.classList.toggle('hidden-mobile');
    registerForm.classList.toggle('hidden-mobile');
}

