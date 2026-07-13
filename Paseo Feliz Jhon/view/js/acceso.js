// ===== SESIÓN RECORDADA: si ya inició sesión antes, saltar el login =====
fetch('../../controller/verificar_sesion_recordada.php')
  .then(res => res.json())
  .then(data => {
    if (!data.success) return;
    let destino;
    if (data.esAdmin) {
      destino = "../vistas/admin/index_admin.php";
    } else if (data.esPaseador) {
      destino = "../vistas/paseador/index_paseador.php";
    } else {
      destino = "../pagina_principal/inicio.php";
    }
    window.location.href = destino;
  })
  .catch(() => {}); // sin conexión: se queda en el login normal

// ===== REFERENCIAS DOM =====
const container        = document.querySelector(".container");
const btnSignIn        = document.getElementById("btn-sign-in");
const btnSignUp        = document.getElementById("btn-sign-up");
const formRegister     = document.getElementById("form-register");
const formLogin        = document.getElementById("form-login");
const btnSignUpMobile  = document.getElementById("btn-sign-up-mobile");
const btnSignInMobile  = document.getElementById("btn-sign-in-mobile");

// Modal de verificación de registro (reutilizamos el overlay de reset del HTML)
const resetOverlay     = document.getElementById("password-reset-overlay");
const resetStep1       = document.getElementById("reset-step-1");
const resetStep2       = document.getElementById("reset-step-2");
const resetStep3       = document.getElementById("reset-step-3");
const resetEmailInput  = document.getElementById("reset-email-input");
const resetEmailError  = document.getElementById("reset-email-error");
const resetTargetEmail = document.getElementById("reset-target-email");
const btnSendCode      = document.getElementById("btn-send-code");
const resetCodeInput   = document.getElementById("reset-code-input");
const resetCodeError   = document.getElementById("reset-code-error");
const btnVerifyCode    = document.getElementById("btn-verify-code");
const btnResendCode    = document.getElementById("btn-resend-code");
const resendTimerSpan  = document.getElementById("resend-timer");
const btnResetCancel   = document.getElementById("btn-reset-cancel");
const resetStep3El     = document.getElementById("reset-step-3");
const newPasswordInput = document.getElementById("reset-new-password");
const newPasswordError = document.getElementById("reset-new-password-error");
const btnChangePassword= document.getElementById("btn-change-password");
const forgotPasswordLink = document.querySelector("#form-login a[href='#']");

// Estado global
let verificationCode   = '';   // solo para recuperación (local, ya no se usa para verificar — el servidor decide)
let resetEmail         = '';
let resendInterval;
const RESEND_TIME      = 30;

// Modo actual del modal: 'registro' o 'recuperacion'
let modalMode = 'recuperacion';

// Datos temporales del formulario de registro (se guardan antes de abrir el modal de código)
let pendingRegisterData = null;
// Código verificado del servidor para recuperación (guardamos que el server dijo OK)
let resetCodeVerified  = false;
let resetCodeValue     = '';   // código que el usuario ingresó y el servidor aprobó


// ===== CAMBIO ENTRE LOGIN / REGISTRO (ESCRITORIO) =====
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

// ===== CAMBIO ENTRE LOGIN / REGISTRO (MÓVIL) =====
btnSignUpMobile.addEventListener("click", () => {
  container.classList.add("toggle");
  container.classList.add("active-register");
  limpiarTodo();
});
btnSignInMobile.addEventListener("click", () => {
  container.classList.remove("toggle");
  container.classList.remove("active-register");
  limpiarTodo();
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


// ===== VALIDACIONES =====
function isPasswordValid(password) {
  if (password.length < 5) return "La contraseña debe tener al menos 5 caracteres.";
  if ((password.match(/\d/g) || []).length < 1) return "La contraseña debe contener al menos un número.";
  if (!/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) return "La contraseña debe contener al menos 1 carácter especial.";
  return true;
}

function esCorreoValido(email) {
  return /^[a-zA-Z0-9._%+-]+@(gmail\.com|hotmail\.com|outlook\.com)$/i.test(email);
}

function validarCampo(idInput, idError, mensajeVacio) {
  const input = document.getElementById(idInput);
  const error = document.getElementById(idError);
  const containerInput = input.closest('.container-input');
  const valor = input.value.trim();
  if (!valor) {
    error.textContent = mensajeVacio;
    containerInput.style.border = '1px solid #d62828';
    return false;
  }
  error.textContent = "";
  containerInput.style.border = 'none';
  return true;
}

// Validación en tiempo real de correos
["register-email", "login-email"].forEach(id => {
  const input = document.getElementById(id);
  const error = document.getElementById(`${id}-error`);
  const containerInput = input.closest('.container-input');
  input.addEventListener("input", () => {
    const valor = input.value.trim();
    if (!valor) { error.textContent = ""; containerInput.style.border = 'none'; return; }
    if (!esCorreoValido(valor)) {
      error.textContent = "Solo se permiten correos de Gmail, Hotmail o Outlook.";
      containerInput.style.border = '1px solid #d62828';
    } else {
      error.textContent = ""; containerInput.style.border = 'none';
    }
  });
});


// ===================================================
// ===== REGISTRO CON VERIFICACIÓN POR CORREO =====
// ===================================================
document.getElementById("btn-registrarse-form").addEventListener("click", () => {
  let todoValido = true;

  if (!validarCampo("register-nombre", "register-nombre-error", "Debe llenar este campo")) todoValido = false;

  const emailInput     = document.getElementById("register-email");
  const email          = emailInput.value.trim();
  const emailLower     = email.toLowerCase();
  const emailError     = document.getElementById("register-email-error");
  const emailContainer = emailInput.closest('.container-input');

  if (!validarCampo("register-email", "register-email-error", "Debe llenar este campo")) {
    todoValido = false;
  } else if (!esCorreoValido(email)) {
    emailError.textContent = "Solo se permiten correos de Gmail, Hotmail o Outlook.";
    emailContainer.style.border = '1px solid #d62828';
    todoValido = false;
  } else {
    emailError.textContent = ""; emailContainer.style.border = 'none';
  }

  const passwordInput     = document.getElementById("register-password");
  const password          = passwordInput.value.trim();
  const passwordError     = document.getElementById("register-password-error");
  const passwordContainer = passwordInput.closest('.container-input');

  if (!validarCampo("register-password", "register-password-error", "Debe llenar este campo")) {
    todoValido = false;
  } else {
    const check = isPasswordValid(password);
    if (check !== true) {
      passwordError.textContent = check;
      passwordContainer.style.border = '1px solid #d62828';
      todoValido = false;
    } else {
      passwordError.textContent = ""; passwordContainer.style.border = 'none';
    }
  }

  if (!validarCampo("register-sexo", "register-sexo-error", "Debe seleccionar un sexo")) todoValido = false;

  if (!todoValido) return;

  // Guardar datos y pedir código al servidor
  pendingRegisterData = {
    nombre:   document.getElementById("register-nombre").value.trim(),
    email:    emailLower,
    sexo:     document.getElementById("register-sexo").value,
    password: password
  };

  // Enviar código de verificación al correo
  const btn = document.getElementById("btn-registrarse-form");
  btn.disabled = true;
  btn.textContent = "ENVIANDO...";

  fetch('../../model/enviar_codigo_registro.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email: emailLower })
  })
  .then(res => res.json())
  .then(data => {
    btn.disabled = false;
    btn.textContent = "REGISTRARSE";
    if (data.success) {
      // Abrir modal en modo registro
      abrirModalCodigoRegistro(emailLower);
    } else {
      mostrarAlertaPersonalizada(data.message, true);
    }
  })
  .catch(() => {
    btn.disabled = false;
    btn.textContent = "REGISTRARSE";
    mostrarAlertaPersonalizada("Error al conectar con el servidor.", true);
  });
});


// ===== MODAL DE CÓDIGO (usado para REGISTRO y para RECUPERACIÓN) =====

function abrirModalCodigoRegistro(email) {
  modalMode = 'registro';
  // Ajustar el título del paso 1 que no se usa (pasamos directo al paso 2)
  resetTargetEmail.textContent = email;
  resetEmail = email;

  resetStep1.classList.add('hidden');
  resetStep2.classList.remove('hidden');
  resetStep3.classList.add('hidden');
  resetCodeInput.value = '';
  resetCodeError.textContent = '';
  resetCodeError.style.color = '#d62828';
  if (resetCodeInput.closest('.container-input')) {
    resetCodeInput.closest('.container-input').style.border = 'none';
  }

  // Actualizar el encabezado del paso 2 dinámicamente
  const h3Step2 = resetStep2.querySelector('h3');
  if (h3Step2) h3Step2.textContent = 'Verificación de Correo (Registro)';
  const pStep2 = resetStep2.querySelector('p');
  if (pStep2) pStep2.innerHTML = 'Ingresa el código de 5 dígitos enviado a: <strong id="reset-target-email">' + email + '</strong><br><small style="color:#e67e00; margin-top:6px; display:block;">⚠️ Si no lo ves en tu bandeja, revisa la carpeta de <b>spam o correo no deseado</b>.</small>';

  startResendTimer();
  resetOverlay.classList.remove('hidden');
}

function abrirModalRecuperacion() {
  modalMode = 'recuperacion';
  resetEmail = '';
  resetEmailInput.value = '';
  resetEmailError.textContent = '';
  if (resetEmailInput.closest('.container-input')) {
    resetEmailInput.closest('.container-input').style.border = 'none';
  }
  resetCodeInput.value = '';
  resetCodeError.textContent = '';
  resetCodeError.style.color = '#d62828';
  newPasswordInput.value = '';
  newPasswordError.textContent = '';

  // Restaurar encabezado del paso 2
  const h3Step2 = resetStep2.querySelector('h3');
  if (h3Step2) h3Step2.textContent = 'Código de Verificación (Paso 2)';

  resetStep1.classList.remove('hidden');
  resetStep2.classList.add('hidden');
  resetStep3.classList.add('hidden');
  clearInterval(resendInterval);
  resendTimerSpan.textContent = '';
  btnResendCode.disabled = false;
  resetOverlay.classList.remove('hidden');
}

function cerrarModal() {
  clearInterval(resendInterval);
  resetOverlay.classList.add('hidden');
  pendingRegisterData = null;
  resetCodeVerified = false;
  resetCodeValue = '';
}

btnResetCancel.addEventListener("click", cerrarModal);

// ===== REENVIAR CÓDIGO =====
btnResendCode.addEventListener("click", () => {
  if (btnResendCode.disabled) return;
  if (modalMode === 'registro') {
    // Reenviar código de registro
    fetch('../../model/enviar_codigo_registro.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: resetEmail })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        resetCodeInput.value = '';
        resetCodeError.textContent = 'Código reenviado.';
        resetCodeError.style.color = 'green';
        setTimeout(() => { resetCodeError.textContent = ''; resetCodeError.style.color = '#d62828'; }, 2000);
        startResendTimer();
      } else {
        mostrarAlertaPersonalizada(data.message, true);
      }
    })
    .catch(() => mostrarAlertaPersonalizada("Error al reenviar.", true));
  } else {
    // Reenviar código de recuperación
    proceedToSendResetCode();
  }
});

// ===== VERIFICAR CÓDIGO =====
btnVerifyCode.addEventListener("click", () => {
  const userCode   = resetCodeInput.value.trim();
  const codeContainer = resetCodeInput.closest('.container-input');

  resetCodeError.textContent = '';
  resetCodeError.style.color = '#d62828';
  codeContainer.style.border = 'none';

  if (!/^\d{5}$/.test(userCode)) {
    resetCodeError.textContent = "El código debe ser de 5 dígitos numéricos.";
    codeContainer.style.border = '1px solid #d62828';
    return;
  }

  if (modalMode === 'registro') {
    // Verificar y registrar en un solo paso
    btnVerifyCode.disabled = true;
    btnVerifyCode.textContent = 'Verificando...';

    fetch('../../model/verificar_registro.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ...pendingRegisterData, codigo: userCode })
    })
    .then(res => res.json())
    .then(data => {
      btnVerifyCode.disabled = false;
      btnVerifyCode.textContent = 'Verificar';
      if (data.success) {
        cerrarModal();
        limpiarTodo();
        mostrarAlertaPersonalizada("¡Registro exitoso! Ahora puede iniciar sesión.");
        setTimeout(() => {
          container.classList.remove("toggle");
          container.classList.remove("active-register");
        }, 2000);
      } else {
        resetCodeError.textContent = data.message;
        codeContainer.style.border = '1px solid #d62828';
        resetCodeInput.value = '';
      }
    })
    .catch(() => {
      btnVerifyCode.disabled = false;
      btnVerifyCode.textContent = 'Verificar';
      mostrarAlertaPersonalizada("Error de conexión.", true);
    });

  } else {
    // Verificar código de recuperación en servidor
    btnVerifyCode.disabled = true;
    btnVerifyCode.textContent = 'Verificando...';

    fetch('../../controller/verificar_reset.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ accion: 'verificar', email: resetEmail, codigo: userCode })
    })
    .then(res => res.json())
    .then(data => {
      btnVerifyCode.disabled = false;
      btnVerifyCode.textContent = 'Verificar';
      if (data.success) {
        resetCodeVerified = true;
        resetCodeValue    = userCode;
        clearInterval(resendInterval);
        resetCodeError.textContent = 'Código verificado.';
        resetCodeError.style.color = 'green';
        setTimeout(() => {
          resetStep2.classList.add('hidden');
          resetStep3.classList.remove('hidden');
          resetCodeError.textContent = '';
          newPasswordInput.focus();
        }, 800);
      } else {
        resetCodeError.textContent = data.message;
        codeContainer.style.border = '1px solid #d62828';
        resetCodeInput.value = '';
      }
    })
    .catch(() => {
      btnVerifyCode.disabled = false;
      btnVerifyCode.textContent = 'Verificar';
      mostrarAlertaPersonalizada("Error de conexión.", true);
    });
  }
});


// ===================================================
// ===== RECUPERACIÓN DE CONTRASEÑA =====
// ===================================================
forgotPasswordLink.addEventListener("click", (e) => {
  e.preventDefault();
  abrirModalRecuperacion();
});

// Paso 1: Enviar código al correo
btnSendCode.addEventListener("click", () => {
  const email = resetEmailInput.value.trim();
  const emailContainer = resetEmailInput.closest('.container-input');
  resetEmailError.textContent = '';
  emailContainer.style.border = 'none';

  if (!email) {
    resetEmailError.textContent = "Debe ingresar su correo electrónico.";
    emailContainer.style.border = '1px solid #d62828';
    return;
  }
  if (!esCorreoValido(email)) {
    resetEmailError.textContent = "Solo se permiten correos de Gmail, Hotmail o Outlook.";
    emailContainer.style.border = '1px solid #d62828';
    return;
  }

  resetEmail = email.toLowerCase();
  proceedToSendResetCode();
});

function proceedToSendResetCode() {
  btnSendCode.disabled = true;
  btnSendCode.textContent = 'Enviando...';

  fetch('../../controller/recuperar_pass.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email: resetEmail })
  })
  .then(res => res.json())
  .then(data => {
    btnSendCode.disabled = false;
    btnSendCode.textContent = 'Enviar Código';
    if (data.success) {
      resetStep1.classList.add('hidden');
      resetStep2.classList.remove('hidden');
      resetCodeInput.value = '';
      resetCodeError.textContent = '';
      if (resetCodeInput.closest('.container-input')) {
        resetCodeInput.closest('.container-input').style.border = 'none';
      }
      // Aviso de spam para recuperación
      const paso2p = resetStep2.querySelector('p');
      if (paso2p) {
        paso2p.innerHTML = 'Ingresa el código enviado a: <strong>' + resetEmail + '</strong><br><small style="color:#e67e00; margin-top:6px; display:block;">⚠️ Si no lo ves, revisa la carpeta de <b>spam o correo no deseado</b>.</small>';
      }
      startResendTimer();
    } else {
      resetEmailError.textContent = data.message;
      if (resetEmailInput.closest('.container-input')) {
        resetEmailInput.closest('.container-input').style.border = '1px solid #d62828';
      }
    }
  })
  .catch(() => {
    btnSendCode.disabled = false;
    btnSendCode.textContent = 'Enviar Código';
    mostrarAlertaPersonalizada("Error de conexión.", true);
  });
}

// Paso 3: Cambiar contraseña
btnChangePassword.addEventListener("click", () => {
  const newPassword = newPasswordInput.value.trim();
  const passwordContainer = newPasswordInput.closest('.container-input');
  newPasswordError.textContent = '';
  passwordContainer.style.border = 'none';

  if (!newPassword) {
    newPasswordError.textContent = "Debe llenar este campo.";
    passwordContainer.style.border = '1px solid #d62828';
    return;
  }
  const check = isPasswordValid(newPassword);
  if (check !== true) {
    newPasswordError.textContent = check;
    passwordContainer.style.border = '1px solid #d62828';
    return;
  }

  btnChangePassword.disabled = true;
  btnChangePassword.textContent = 'Guardando...';

  fetch('../../controller/verificar_reset.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      accion: 'cambiar',
      email: resetEmail,
      codigo: resetCodeValue,
      nueva_password: newPassword
    })
  })
  .then(res => res.json())
  .then(data => {
    btnChangePassword.disabled = false;
    btnChangePassword.textContent = 'Confirmar cambio';
    if (data.success) {
      cerrarModal();
      limpiarTodo();
      mostrarAlertaPersonalizada("¡Contraseña cambiada exitosamente!");
    } else {
      newPasswordError.textContent = data.message;
      passwordContainer.style.border = '1px solid #d62828';
    }
  })
  .catch(() => {
    btnChangePassword.disabled = false;
    btnChangePassword.textContent = 'Confirmar cambio';
    mostrarAlertaPersonalizada("Error de conexión.", true);
  });
});


// ===== TIMER DE REENVÍO =====
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


// ===================================================
// ===== LOGIN =====
// ===================================================
document.getElementById("btn-login").addEventListener("click", () => {
  let emailValid    = validarCampo("login-email", "login-email-error", "Debe llenar este campo");
  let passwordValid = validarCampo("login-password", "login-password-error", "Debe llenar este campo");

  const emailInput      = document.getElementById("login-email");
  const emailErrorSpan  = document.getElementById("login-email-error");
  const emailContainer  = emailInput.closest('.container-input');
  const email           = emailInput.value.trim().toLowerCase();

  if (email && !esCorreoValido(email)) {
    emailErrorSpan.textContent = "Solo se permiten correos de Gmail, Hotmail o Outlook.";
    emailContainer.style.border = '1px solid #d62828';
    emailValid = false;
  }

  if (!emailValid || !passwordValid) return;

  emailErrorSpan.textContent = ""; emailContainer.style.border = 'none';
  document.getElementById("login-password-error").textContent = "";
  document.getElementById("login-password").closest('.container-input').style.border = 'none';

  fetch('../../controller/login.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password: document.getElementById("login-password").value.trim() })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      sessionStorage.setItem("usuario_logeado", JSON.stringify(data.usuario));
      mostrarAlertaPersonalizada(`Bienvenido A Paseo Feliz, ${data.usuario.nombre}! 👋`);
      limpiarTodo();
      let destino;
      if (data.esAdmin) {
        destino = "../vistas/admin/index_admin.php";
      } else if (data.esPaseador) {
        destino = "../vistas/paseador/index_paseador.php";
      } else {
        destino = "../pagina_principal/inicio.php";
      }
      setTimeout(() => { window.location.href = destino; }, 2000);
    } else {
      mostrarAlertaPersonalizada(data.message, true);
    }
  })
  .catch(() => mostrarAlertaPersonalizada("Error al conectar con el servidor.", true));
});


// ===== FUNCIONES DE LIMPIEZA Y ALERTA =====
function limpiarTodo() {
  document.querySelectorAll("input").forEach(input => input.value = "");
  document.querySelectorAll("select").forEach(select => select.value = "");
  document.querySelectorAll(".error-msg").forEach(msg => msg.textContent = "");
  document.querySelectorAll(".container-input").forEach(c => c.style.border = 'none');
  document.querySelectorAll(".password-field input").forEach(input => input.setAttribute("type", "password"));
  document.querySelectorAll(".toggle-password").forEach(icon => icon.setAttribute("name", "eye-outline"));
}

function mostrarAlertaPersonalizada(mensaje, esError = false) {
  const overlay  = document.getElementById("notification-overlay");
  const msg      = document.getElementById("notification-message");
  const icon     = document.getElementById("notification-icon");
  if (!overlay || !msg || !icon) return;
  msg.textContent = mensaje;
  if (esError) {
    icon.setAttribute("name", "close-circle-outline");
    icon.style.color = "#d62828";
  } else {
    icon.setAttribute("name", "checkmark-circle-outline");
    icon.style.color = "#28a745";
  }
  overlay.classList.remove("hidden");
  setTimeout(() => overlay.classList.add("hidden"), 2500);
}