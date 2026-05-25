// Nuevas referencias para móvil
const btnSignUpMobile = document.getElementById("btn-sign-up-mobile");
const btnSignInMobile = document.getElementById("btn-sign-in-mobile");

// Configuración de eventos para los botones de móvil
btnSignUpMobile.addEventListener("click", () => {
  container.classList.add("toggle"); // Acción para PC
  container.classList.add("active-register"); // Acción para móvil
  limpiarTodo();
});

btnSignInMobile.addEventListener("click", () => {
  container.classList.remove("toggle"); // Acción para PC
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


// ===== FUNCIONES AUXILIARES DE LOCALSTORAGE (Mantenidas temporalmente para la simulación de recuperación de contraseña) =====
function getUsuarios() {
  const usuarios = localStorage.getItem("usuarios");
  return usuarios ? JSON.parse(usuarios) : [];
}

function isEmailRegistered(email) {
  const usuarios = getUsuarios();
  const lowerCaseEmail = email.toLowerCase();
  return usuarios.some(user => user.email === lowerCaseEmail);
}

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


// ===== CAMBIO ENTRE LOGIN / REGISTRO =====
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

// ===== MOSTRAR / OCULTAR CONTRASEÑA =====
document.querySelectorAll(".toggle-password").forEach(icon => {
  icon.addEventListener("click", () => {
    const input = icon.previousElementSibling;
    const isPassword = input.getAttribute("type") === "password";
    input.setAttribute("type", isPassword ? "text" : "password");
    icon.setAttribute("name", isPassword ? "eye-off-outline" : "eye-outline");
  });
});

// ===== VALIDACIÓN DE CONTRASEÑA =====
function isPasswordValid(password) {
    const minLength = password.length >= 5;
    const hasOneNumber = (password.match(/\d/g) || []).length >= 1; 
    const hasSpecialChar = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);

    if (!minLength) {
        return "La contraseña debe tener al menos 5 caracteres.";
    }
    if (!hasOneNumber) {
        return "La contraseña debe contener al menos un número.";
    }
    if (!hasSpecialChar) {
        return "La contraseña debe contener al menos 1 carácter especial.";
    }
    return true;
}

// ===== VALIDACIÓN DE CORREO =====
function esCorreoValido(email) {
  const regex = /^[a-zA-Z0-9._%+-]+@(gmail\.com|hotmail\.com|outlook\.com)$/i;
  return regex.test(email);
}

// ===== VALIDAR CAMPOS VACÍOS =====
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

// ===== VALIDAR CORREO EN TIEMPO REAL =====
const correos = ["register-email", "login-email"];
correos.forEach(id => {
  const input = document.getElementById(id);
  const error = document.getElementById(`${id}-error`);
  const containerInput = input.closest('.container-input');

  input.addEventListener("input", () => {
    const valor = input.value.trim();

    if (!valor) {
      error.textContent = "";
      containerInput.style.border = 'none';
      return;
    } 
    
    if (!esCorreoValido(valor)) {
      error.textContent = "Solo se permiten correos de Gmail, Hotmail o Outlook.";
      containerInput.style.border = '1px solid #d62828';
    } else {
      error.textContent = "";
      containerInput.style.border = 'none';
    }
  });
});


// ===== FUNCIONES PARA RECUPERACIÓN DE CONTRASEÑA =====
function generateVerificationCode() {
    let code = '';
    for (let i = 0; i < 6; i++) {
        code += Math.floor(Math.random() * 10);
    }
    return code;
}

function downloadCodeFile(code, email) {
    const content = `Código de Verificación para ${email}:\n${code}\n\nNota: Este archivo es para simular el envío por correo. Úsalo para ingresar el código de 6 números.`;
    const filename = `codigo_verificacion_${email.split('@')[0]}_${new Date().getTime()}.txt`;
    
    const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    
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
    resetStep3.classList.add('hidden');
    resetOverlay.classList.remove('hidden');
    
    resetCodeError.textContent = '';
    resetCodeInput.value = '';
    resetCodeInput.closest('.container-input').style.border = 'none';
    
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

// Eventos del Modal de Recuperación
forgotPasswordLink.addEventListener("click", (e) => {
    e.preventDefault();
    showResetModal();
});
btnResetCancel.addEventListener("click", hideResetModal);

btnSendCode.addEventListener("click", () => {
    const email = resetEmailInput.value.trim();
    const emailContainer = resetEmailInput.closest('.container-input');
    resetEmail = email.toLowerCase();

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
        proceedToSendCode();
    }
});

btnResendCode.addEventListener("click", () => {
    if (!btnResendCode.disabled) {
        proceedToSendCode();
    }
});

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
            resendTimerSpan.textContent = '';
        }
    }, 1000);
}

function proceedToSendCode() {
    verificationCode = generateVerificationCode();
    resetTargetEmail.textContent = resetEmail;
    resetStep1.classList.add('hidden');
    resetStep2.classList.remove('hidden');
    resetCodeInput.value = '';
    resetCodeError.textContent = '';
    resetCodeInput.closest('.container-input').style.border = 'none';
    
    downloadCodeFile(verificationCode, resetEmail);
    mostrarAlertaPersonalizada(`Se ha generado un archivo .txt con el código para ${resetEmail}. Revise sus descargas.`);
    startResendTimer();
}

btnVerifyCode.addEventListener("click", () => {
    const userInputCode = resetCodeInput.value.trim();
    const codeContainer = resetCodeInput.closest('.container-input');

    resetCodeError.textContent = '';
    codeContainer.style.border = 'none';
    
    if (userInputCode.length !== 6 || !/^\d{6}$/.test(userInputCode)) {
         resetCodeError.textContent = "El código debe ser de 6 números.";
         codeContainer.style.border = '1px solid #d62828';
    } else if (userInputCode === verificationCode) {
        resetCodeError.textContent = "Código verificado con éxito.";
        resetCodeError.style.color = 'green';
        clearInterval(resendInterval);
        
        setTimeout(() => {
            resetStep2.classList.add('hidden');
            resetStep3.classList.remove('hidden'); 
            resetCodeError.textContent = '';
            newPasswordInput.focus();
        }, 800);
    } else {
        resetCodeError.textContent = "El código ingresado es incorrecto.";
        codeContainer.style.border = '1px solid #d62828';
        resetCodeInput.value = '';
    }
});

btnChangePassword.addEventListener("click", () => {
    const newPassword = newPasswordInput.value.trim();
    const passwordContainer = newPasswordInput.closest('.container-input');
    
    newPasswordError.textContent = "";
    passwordContainer.style.border = 'none';

    if (!newPassword) {
        newPasswordError.textContent = "Debe llenar este campo.";
        passwordContainer.style.border = '1px solid #d62828';
        return;
    } 

    const passwordCheckResult = isPasswordValid(newPassword);
    if (passwordCheckResult !== true) {
        newPasswordError.textContent = passwordCheckResult;
        passwordContainer.style.border = '1px solid #d62828';
        return;
    }
    
    const success = updatePassword(resetEmail, newPassword);
    if (success) {
        mostrarAlertaPersonalizada("¡Contraseña cambiada exitosamente!");
        hideResetModal();
        limpiarTodo();
    } else {
        newPasswordError.textContent = "Error al cambiar la contraseña. Intente de nuevo.";
        passwordContainer.style.border = '1px solid #d62828';
    }
});


// ===== BOTÓN REGISTRARSE (CONECTADO A PHP) =====
document.getElementById("btn-registrarse-form").addEventListener("click", () => {
    let todoValido = true; 

    if (!validarCampo("register-nombre", "register-nombre-error", "Debe llenar este campo")) {
        todoValido = false;
    }
    
    const emailInput = document.getElementById("register-email");
    const email = emailInput.value.trim();
    const emailLower = email.toLowerCase();
    const emailError = document.getElementById("register-email-error");
    const emailContainer = emailInput.closest('.container-input');

    if (!validarCampo("register-email", "register-email-error", "Debe llenar este campo")) {
        todoValido = false;
    } else {
        if (!esCorreoValido(email)) {
            emailError.textContent = "Solo se permiten correos de Gmail, Hotmail o Outlook.";
            emailContainer.style.border = '1px solid #d62828';
            todoValido = false;
        } else {
            emailError.textContent = "";
            emailContainer.style.border = 'none';
        }
    }

    const passwordInput = document.getElementById("register-password");
    const password = passwordInput.value.trim();
    const passwordError = document.getElementById("register-password-error");
    const passwordContainer = passwordInput.closest('.container-input');

    if (!validarCampo("register-password", "register-password-error", "Debe llenar este campo")) {
        todoValido = false;
    } else {
        const passwordCheckResult = isPasswordValid(password);
        if (passwordCheckResult !== true) {
            passwordError.textContent = passwordCheckResult;
            passwordContainer.style.border = '1px solid #d62828';
            todoValido = false;
        } else {
            passwordError.textContent = "";
            passwordContainer.style.border = 'none';
        }
    }
    
    if (!validarCampo("register-sexo", "register-sexo-error", "Debe seleccionar un sexo")) {
        todoValido = false;
    }

    // --- Envío final por Fetch ---
    if (todoValido) {
      const userData = {
        nombre: document.getElementById("register-nombre").value.trim(),
        email: emailLower,
        sexo: document.getElementById("register-sexo").value,
        password: password
      };

      fetch('../../api/registrar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(userData)
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          limpiarTodo();
          mostrarAlertaPersonalizada("¡Registro exitoso! Ahora puede iniciar sesión.");
          setTimeout(() => {
            container.classList.remove("toggle");
            container.classList.remove("active-register");
          }, 2000);
        } else {
          mostrarAlertaPersonalizada(data.message);
        }
      })
      .catch(err => {
        console.error("Error en el registro:", err);
        mostrarAlertaPersonalizada("Error al conectar con el servidor.");
      });
    }
});


// ===== BOTÓN LOGIN (CORREGIDO Y LIMPIO) =====
document.getElementById("btn-login").addEventListener("click", () => {
  let nombreValid = validarCampo("login-nombre", "login-nombre-error", "Debe llenar este campo");
  let emailValid = validarCampo("login-email", "login-email-error", "Debe llenar este campo");
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
  
  const email = emailInput.value.trim().toLowerCase(); 
  const password = passwordInput.value.trim();
  
  if (email && !esCorreoValido(email)) {
    emailErrorSpan.textContent = "Solo se permiten correos de Gmail, Hotmail o Outlook.";
    emailContainer.style.border = '1px solid #d62828';
    emailValid = false;
  } 

  if (!nombreValid || !emailValid || !passwordValid) return;

  // Limpiar estados de error visuales previos
  nombreErrorSpan.textContent = "";
  nombreContainer.style.border = 'none';
  emailErrorSpan.textContent = "";
  emailContainer.style.border = 'none';
  passwordErrorSpan.textContent = "";
  passwordContainer.style.border = 'none';

  const loginData = {
    email: email,
    password: password
  };

  // Petición fetch única al servidor remoto
  fetch('../../api/login.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(loginData)
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      sessionStorage.setItem("usuario_logeado", JSON.stringify(data.usuario));
      mostrarAlertaPersonalizada(`Bienvenido A Paseo Feliz, ${data.usuario.nombre}! 👋`);
      limpiarTodo();

      setTimeout(() => {
        window.location.href = "../pagina_principal/inicio.html";
      }, 2000);
    } else {
      // El backend PHP responderá dinámicamente si el correo no existe o la clave no coincide
      mostrarAlertaPersonalizada(data.message);
    }
  })
  .catch(err => {
    console.error("Error en el login:", err);
    mostrarAlertaPersonalizada("Error al conectar con el servidor.");
  });
});


// ===== FUNCIONES DE LIMPIEZA =====
function limpiarTodo() {
  document.querySelectorAll("input").forEach(input => input.value = "");
  document.querySelectorAll("select").forEach(select => select.value = ""); 
  document.querySelectorAll(".error-msg").forEach(msg => msg.textContent = "");
  document.querySelectorAll(".container-input").forEach(container => container.style.border = 'none');
  document.querySelectorAll(".password-field input").forEach(input => input.setAttribute("type", "password"));
  document.querySelectorAll(".toggle-password").forEach(icon => icon.setAttribute("name", "eye-outline"));
}

function mostrarAlertaPersonalizada(mensaje) {
    const notificationOverlay = document.getElementById("notification-overlay");
    const notificationMessage = document.getElementById("notification-message");

    if (notificationOverlay && notificationMessage) {
        notificationMessage.textContent = mensaje;
        notificationOverlay.classList.remove("hidden");
        setTimeout(() => {
            notificationOverlay.classList.add("hidden");
        }, 2000); 
    }
}

function toggleMobileForms() {
    const loginForm = document.getElementById('form-login');
    const registerForm = document.getElementById('form-register');
    loginForm.classList.toggle('hidden-mobile');
    registerForm.classList.toggle('hidden-mobile');
}