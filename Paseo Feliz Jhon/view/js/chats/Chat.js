/* =========================================================================
   LÓGICA DE JAVASCRIPT DEL CHAT - PASEO FELIZ
   ========================================================================= */
// Avatar por defecto (SVG embebido) para usuarios sin foto de perfil.
// Al ir embebido no depende de rutas relativas, así que se ve igual sin
// importar la profundidad de carpeta de la página (Chat.php / Chat_admin.php / Chat_paseador.php).
const AVATAR_DEFAULT = 'data:image/svg+xml;utf8,' + encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">' +
    '<circle cx="50" cy="50" r="50" fill="#E1E4E8"/>' +
    '<circle cx="50" cy="40" r="18" fill="#B0B7C0"/>' +
    '<path d="M50 62c-20 0-32 11-32 25v3h64v-3c0-14-12-25-32-25z" fill="#B0B7C0"/>' +
    '</svg>'
);

function avatarSrc(url) {
    return url ? url : AVATAR_DEFAULT;
}

let convActivaId = null;
let intervalChat = null;
let msgSeleccionadoId = null;
let msgSeleccionadoTexto = "";
let msgSeleccionadoEmisor = null;

// Notificaciones del sistema: conteo de no-leídos por conversación para
// detectar mensajes NUEVOS entre un sondeo y el siguiente (la primera
// carga solo establece la línea base, no notifica lo ya acumulado).
let pfNoLeidosPrevios = null;

// Lista de emojis disponibles para el contenedor flotante
const listaEmojis = [
    '😀','😁','😂','🤣','😃','😄','😅','😆','😉','😊','😋','😎','😍','😘','😗','😙','😚','🙂','🤗','🤩','🤔','🤨','😐','😑','😶','🙄','😏','😣','😥','😮','🤐','😯','😪','😫','😴','😌','😛','😜','😝','🤤','😒','😓','😔','😕','🙃','🤑','😲','☹️','🙁','😖','😞','😟','😤','😢','😭','😦','😧','😨','😩','🤯','😬','😰','😱','😳','🤪','😵','😡','😠','🤬','😷','🤒','🤕','🤢','🤮','🤧','😇','🤠','🤡','🥳','🥴','🥺','🤥','🤫','🤭','🧐','🤓','😈','👿','👹','👺','💀','👻','👽','🤖','💩','😺','😸','😹','😻','😼','😽','🙀','😿','😾','👋','🤚','🖐️','✋','🖖','👌','✌️','🤞','🤟','🤘','🤙','👈','👉','👆','🖕','👇','☝️','👍','👎','✊','👊','🤛','🤜','👏','🙌','👐','🤲','🤝','🙏','✍️','💅','🤳','💪','🦾','🐾','🐶','🐱','🐭','🐹','🐰','🦊','🐻','🐼','🐨','🐯','🦁','🐮','🐷','🐽','🐸','🐵','🙈','🙉','🙊','🐒','🐔','🐧','🐦','🐤','🐣','🐥','🦆','🦅','🦉','🦇','🐺','🐗','🐴','🦄','🐝','🐛','🦋','🐌','🐞','🐜','🦟','🦗','🕷️','🕸️','🦂','🐢','🐍','🦎','🦖','🦕','🐙','🦑','🦐','🦞','🦀','🐡','🐠','🐟','🐬','🐳','🐋','🦈','🐊','🐅','🐆','🦓','🦍','🦧','🦣','🐘','🦛','🦏','🐪','🐫','🦒','🦘','🦬','🐃','🐂','🐄','🐎','🐖','🐏','🐑','🐐','🦌','🐕','🐩','🦮','🐕‍🦺','🐈','🐈‍⬛','🐓','🦃','🦤','🦚','🦜','🦢','🦩','🕊️','🐇','🦝','🦨','🦡','🦫','🦦','🦥','🦽','🦾','❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❤️‍🔥','❤️‍🩹','❣️','💕','💞','💓','💗','💖','💘','💝','💟'
];

// Inicializar el cargador de emojis al cargar el documento
document.addEventListener('DOMContentLoaded', () => {
    const picker = document.getElementById('emoji-picker');
    if (picker) {
        listaEmojis.forEach(emoji => {
            const span = document.createElement('span');
            span.className = 'emoji-item';
            span.textContent = emoji;
            span.addEventListener('click', (e) => {
                e.stopPropagation(); 
                const tx = document.getElementById('msg-input');
                tx.value += emoji;
                document.getElementById('btn-send').disabled = false;
                tx.focus();
            });
            picker.appendChild(span);
        });
    }

    // Evento para abrir/cerrar el contenedor de emojis
    document.getElementById('btn-emoji').addEventListener('click', (e) => {
        e.stopPropagation();
        picker.style.display = (picker.style.display === 'grid') ? 'none' : 'grid';
    });

    // Eventos del buscador y área de texto
    document.getElementById('search-input').addEventListener('input', function() {
        cargarConversaciones(this.value);
    });

    document.getElementById('file-chat-input').addEventListener('change', function() {
    if (this.files && this.files[0]) {
        // Se envía automáticamente en cuanto el usuario selecciona la foto
        enviarMensaje(this.files[0]);
    }
});

    document.getElementById('msg-input').addEventListener('input', function () {
        document.getElementById('btn-send').disabled = this.value.trim() === '';
    });

    document.getElementById('msg-input').addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { 
            e.preventDefault(); 
            enviarMensaje(); 
        }
    });

    document.getElementById('btn-send').addEventListener('click', () => enviarMensaje());
    
    // Botón de regresar en vista móvil
    document.getElementById('btn-back').addEventListener('click', () => {
        document.getElementById('conv-panel').classList.remove('hidden');
        document.getElementById('chat-header').style.display = 'none';
        document.getElementById('messages-area').style.display = 'none';
        document.getElementById('input-area').style.display = 'none';
        document.getElementById('empty-state').style.display = 'flex';
        clearInterval(intervalChat);
        convActivaId = null;
    });
    
    // Menú hamburguesa lateral
    document.getElementById('btn-menu').addEventListener('click', () => {
        document.getElementById('menu-latente').classList.toggle('show');
    });

    // Ocultar menús flotantes al hacer clic en cualquier parte de la pantalla
    document.addEventListener('click', ocultarElementosFlotantes);

    // Acciones del menú contextual (Clic derecho)
    document.getElementById('ctx-copy').addEventListener('click', () => {
        if(msgSeleccionadoTexto) navigator.clipboard.writeText(msgSeleccionadoTexto);
        ocultarElementosFlotantes();
    });

    document.getElementById('ctx-delete').addEventListener('click', () => {
        if(!msgSeleccionadoId) return;
        const params = new FormData();
        params.append('id_mensaje', msgSeleccionadoId);

        fetch('?accion=borrar_mensaje', { method: 'POST', body: params })
            .then(res => res.json())
            .then(() => {
                const area = document.getElementById('messages-area');
                area.innerHTML = ''; 
                cargarMensajes(convActivaId);
            });
        ocultarElementosFlotantes();
    });

    // Carga inicial de los chats del panel izquierdo
    cargarConversaciones();
    setInterval(cargarConversaciones, 8000);

    // Deep-link: ?chat_con=<id_usuario> abre directamente el chat con esa
    // persona (lo usan los botones "Chat cliente" del mapa del paseador).
    const chatCon = new URLSearchParams(window.location.search).get('chat_con');
    if (chatCon) {
        // Limpiar la URL para no reabrir el chat al recargar la página
        history.replaceState(null, '', window.location.pathname);
        abrirChatConUsuario(parseInt(chatCon, 10));
    }
});

// Abre (o crea) la conversación con un usuario puntual y la muestra
function abrirChatConUsuario(idReceptor) {
    if (!idReceptor) return;
    fetch(`?accion=obtener_o_crear_chat&id_receptor=${idReceptor}`)
        .then(r => r.json())
        .then(data => {
            if (!data.id_conversacion) {
                if (data.error) alert(data.error);
                return;
            }
            // Buscar nombre/avatar/estado del contacto en la lista de chats
            fetch('?accion=listar_chats')
                .then(r => r.json())
                .then(lista => {
                    let conv = null;
                    (lista.grupos || []).forEach(g => (g.contactos || []).forEach(c => {
                        if (c.id === data.id_conversacion || c.id_receptor === idReceptor) conv = c;
                    }));
                    abrirConversacion(
                        data.id_conversacion,
                        conv ? conv.nombre : 'Conversación',
                        conv ? conv.avatar : null,
                        conv ? !!conv.en_linea : false
                    );
                })
                .catch(() => abrirConversacion(data.id_conversacion, 'Conversación', null, false));
        })
        .catch(() => {});
}

// Función para listar los contactos permitidos, agrupados por rol
// (Administradores / Paseadores / Clientes, con línea divisoria entre
// cada grupo). "buscar" solo filtra por nombre dentro de esos mismos
// grupos, ya no es un modo aparte.
function cargarConversaciones(terminoBusqueda = '') {
    let url = '?accion=listar_chats';
    if(terminoBusqueda) { url += '&buscar=' + encodeURIComponent(terminoBusqueda); }

    fetch(url)
        .then(res => res.json())
        .then(data => {
            const grupos = data.grupos || [];
            notificarMensajesNuevos(grupos, terminoBusqueda);
            const lista = document.getElementById('conv-list');
            lista.innerHTML = '';

            const totalContactos = grupos.reduce((acc, g) => acc + g.contactos.length, 0);
            if (totalContactos === 0) {
                lista.innerHTML = '<div style="padding: 20px; text-align: center; color: #888; font-size: 0.9rem;">No se encontraron usuarios</div>';
                return;
            }

            grupos.forEach(grupo => {
                const divisor = document.createElement('div');
                divisor.className = 'conv-divider';
                divisor.innerHTML = `<span>${grupo.titulo}</span>`;
                lista.appendChild(divisor);

                grupo.contactos.forEach(conv => {
                    const item = document.createElement('div');
                    item.className = 'conv-item' + (convActivaId === conv.id && conv.id ? ' active' : '');
                    item.innerHTML = `
                      <div class="conv-avatar">
                        <img src="${avatarSrc(conv.avatar)}" onerror="this.onerror=null;this.src='${AVATAR_DEFAULT}'">
                        <span class="estado-dot ${conv.en_linea ? 'en-linea' : ''}"></span>
                      </div>
                      <div class="conv-info">
                        <div class="conv-name">${conv.nombre}</div>
                        <div class="conv-last">${conv.ultimo}</div>
                      </div>
                      <div class="conv-meta">
                        ${conv.hora ? `<div class="conv-time">${conv.hora}</div>` : ''}
                        ${conv.no_leidos > 0 ? `<div class="conv-badge">${conv.no_leidos > 99 ? '99+' : conv.no_leidos}</div>` : ''}
                      </div>`;

                    item.addEventListener('click', () => {
                        // Feedback visual inmediato: resaltar este item y quitarle
                        // el badge de no leídos sin esperar al próximo refresco
                        // automático de la lista (que corre cada 8s).
                        document.querySelectorAll('.conv-item.active').forEach(el => el.classList.remove('active'));
                        item.classList.add('active');
                        const badge = item.querySelector('.conv-badge');
                        if (badge) badge.remove();

                        if(conv.id) {
                            abrirConversacion(conv.id, conv.nombre, conv.avatar, conv.en_linea);
                        } else {
                            fetch(`?accion=obtener_o_crear_chat&id_receptor=${conv.id_receptor}`)
                                .then(r => r.json())
                                .then(data => {
                                    if(data.id_conversacion) {
                                        document.getElementById('search-input').value = '';
                                        abrirConversacion(data.id_conversacion, conv.nombre, conv.avatar, conv.en_linea);
                                    } else if (data.error) {
                                        alert(data.error);
                                    }
                                });
                        }
                    });
                    lista.appendChild(item);
                });
            });
        }).catch(err => console.error("Error panel:", err));
}

// Notificación del sistema (teléfono/escritorio) cuando sube el conteo de
// no-leídos de una conversación entre un sondeo y el siguiente. Solo suena
// con la página en segundo plano (pfNotificar ya filtra eso) y nunca para
// la conversación que se tiene abierta. Con búsqueda activa no se evalúa,
// porque la lista viene filtrada y los conteos estarían incompletos.
function notificarMensajesNuevos(grupos, terminoBusqueda) {
    if (typeof pfNotificar !== 'function' || terminoBusqueda) return;

    const actuales = {};
    grupos.forEach(g => (g.contactos || []).forEach(c => {
        const clave = c.id ? 'c' + c.id : 'r' + c.id_receptor;
        actuales[clave] = { noLeidos: c.no_leidos || 0, nombre: c.nombre, ultimo: c.ultimo, id: c.id };
    }));

    if (pfNoLeidosPrevios !== null) {
        Object.keys(actuales).forEach(clave => {
            const a = actuales[clave];
            const previos = pfNoLeidosPrevios[clave] ? pfNoLeidosPrevios[clave].noLeidos : 0;
            if (a.noLeidos > previos && a.id !== convActivaId) {
                pfNotificar(`💬 Mensaje de ${a.nombre}`, a.ultimo || 'Tienes un mensaje nuevo', 'chat-' + clave);
            }
        });
    }
    pfNoLeidosPrevios = actuales;
}

// Configura la interfaz al seleccionar un chat activo
function abrirConversacion(id, nombre, avatar, enLinea = false) {
    convActivaId = id;
    document.getElementById('header-avatar').innerHTML = `
        <img src="${avatarSrc(avatar)}" onerror="this.onerror=null;this.src='${AVATAR_DEFAULT}'">
        <span class="estado-dot ${enLinea ? 'en-linea' : ''}"></span>`;
    document.getElementById('header-name').textContent = nombre;
    document.getElementById('empty-state').style.display = 'none';
    document.getElementById('chat-header').style.display = 'flex';
    document.getElementById('messages-area').style.display = 'flex';
    document.getElementById('input-area').style.display = 'flex';

    mostrarCargandoChat();

    // Al abrir, el backend marca los mensajes como leídos; refrescamos la
    // lista de contactos justo después para que el badge quede sincronizado
    // con el servidor (no solo el ajuste visual instantáneo del click).
    cargarMensajes(id, true);
    clearInterval(intervalChat);
    intervalChat = setInterval(() => cargarMensajes(id), 3000);

    if (window.innerWidth <= 680) {
        document.getElementById('conv-panel').classList.add('hidden');
    }
}

// Carga asíncronamente el historial de mensajes de la conversación activa.
// "refrescarLista" se pasa en true solo al abrir la conversación (no en cada
// sondeo de 3s): el backend ya marcó los mensajes como leídos en esta misma
// petición, así que aprovechamos para sincronizar el badge de la lista.
function cargarMensajes(idChat, refrescarLista = false) {
    fetch(`?accion=cargar_mensajes&id_chat=${idChat}`)
        .then(res => res.json())
        .then(data => {
            if (data.error) return;
            actualizarEstadoChat(idChat, data.activo);
            const dot = document.querySelector('#header-avatar .estado-dot');
            if (dot) dot.classList.toggle('en-linea', !!data.en_linea);
            if (refrescarLista) cargarConversaciones(document.getElementById('search-input').value);
            const mensajes = data.mensajes || [];
            const area = document.getElementById('messages-area');
            const estabaCargando = area.classList.contains('cargando');
            if (!estabaCargando && area.children.length === mensajes.length) return;

            area.classList.remove('cargando');
            area.innerHTML = '';
            mensajes.forEach(msg => {
                const row = document.createElement('div');
                row.className = `msg-row ${msg.de === 'yo' ? 'sent' : 'received'}`;
                
                let avatarHTML = `<div class="burbuja-avatar"><img src="${avatarSrc(msg.avatar_burbuja)}" onerror="this.onerror=null;this.src='${AVATAR_DEFAULT}'"></div>`;
                
                let contenido = '';
                if (msg.imagen) {
                    // El backend solo manda el nombre del archivo; la imagen
                    // se sirve siempre a través de ?accion=servir_imagen
                    // (evita el 403 de acceso directo a la carpeta en byethost).
                    const urlImg = `?accion=servir_imagen&archivo=${encodeURIComponent(msg.imagen)}`;
                    contenido += `<img src="${urlImg}" class="msg-image" onclick="window.open('${urlImg}')" onerror="this.onerror=null;this.src='${AVATAR_DEFAULT}'"/>`;
                }
                
                if (msg.texto) {
                    contenido += `<span class="txt-copy-target">${msg.texto}</span>`;
                }
                
                if(msg.de === 'yo') {
                    row.innerHTML = `<div><div class="msg-bubble" data-id="${msg.id_msg}" data-emisor="${msg.id_emisor}">${contenido}</div><div class="msg-time">${msg.hora}</div></div> ${avatarHTML}`;
                } else {
                    row.innerHTML = `${avatarHTML} <div><div class="msg-bubble" data-id="${msg.id_msg}" data-emisor="${msg.id_emisor}">${contenido}</div><div class="msg-time">${msg.hora}</div></div>`;
                }
                
                // Evento para el menú contextual del clic derecho
                const burbuja = row.querySelector('.msg-bubble');
                burbuja.addEventListener('contextmenu', function(e) {
                    e.preventDefault();
                    
                    msgSeleccionadoId = this.getAttribute('data-id');
                    msgSeleccionadoEmisor = parseInt(this.getAttribute('data-emisor'));
                    const textSpan = this.querySelector('.txt-copy-target');
                    msgSeleccionadoTexto = textSpan ? textSpan.textContent : "";

                    const deleteBtn = document.getElementById('ctx-delete');
                    deleteBtn.style.display = (msgSeleccionadoEmisor === idSesionActual) ? "flex" : "none";

                    const menu = document.getElementById('context-menu');
                    menu.style.display = 'block';
                    menu.style.left = `${e.pageX}px`;
                    menu.style.top = `${e.pageY}px`;
                });

                area.appendChild(row);
            });
            area.scrollTop = area.scrollHeight;
        });
}

// Envía el mensaje de texto o archivo al backend
function enviarMensaje(archivo = null) {
    if (!convActivaId) return;
    const input = document.getElementById('msg-input');
    const texto = input.value.trim();
    if (!texto && !archivo) return;

    const formData = new FormData();
    formData.append('id_conversacion', convActivaId);
    if (texto) formData.append('mensaje', texto);
    if (archivo) formData.append('foto_adjunta', archivo);

    fetch('?accion=enviar', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(() => {
        input.value = '';
        document.getElementById('file-chat-input').value = '';
        document.getElementById('btn-send').disabled = true;
        const area = document.getElementById('messages-area');
        area.innerHTML = '';
        cargarMensajes(convActivaId);
        cargarConversaciones();
    });
}

function ocultarElementosFlotantes() {
    const menu = document.getElementById('context-menu');
    const picker = document.getElementById('emoji-picker');
    if(menu) menu.style.display = 'none';
    if(picker) picker.style.display = 'none';
}

// ==== ESTADO ACTIVO / INACTIVO DEL CHAT (restaurado del original) ====
// Controla si se muestra el campo de texto o el aviso de "chat desactivado",
// y (solo para admin) mantiene actualizado el boton de alternar estado.
function actualizarEstadoChat(idChat, activo) {
    const inputArea = document.getElementById('input-area');
    const esAdmin = (typeof rolSesionActual !== 'undefined' && rolSesionActual === 'admin');

    let banner = document.getElementById('chat-desactivado-banner');

    if (activo === 1 || esAdmin) {
        inputArea.style.display = 'flex';
        if (banner) banner.style.display = 'none';
    } else {
        inputArea.style.display = 'none';
        if (!banner) {
            banner = document.createElement('div');
            banner.id = 'chat-desactivado-banner';
            banner.className = 'chat-desactivado-banner';
            inputArea.parentNode.insertBefore(banner, inputArea.nextSibling);
        }
        banner.innerHTML = '<i class="fas fa-lock"></i> Este chat esta desactivado mientras no este en servicio.';
        banner.style.display = 'flex';
    }

    if (esAdmin) {
        actualizarBotonToggle(idChat, activo);
    }
}

// ==== BOTON ADMIN: ACTIVAR / DESACTIVAR CONVERSACION ====
function actualizarBotonToggle(idChat, activo) {
    const header = document.getElementById('chat-header');
    if (!header) return;

    let btn = document.getElementById('btn-toggle-chat');
    if (!btn) {
        btn = document.createElement('button');
        btn.id = 'btn-toggle-chat';
        btn.className = 'chat-toggle-btn';
        header.appendChild(btn);
        btn.addEventListener('click', () => {
            const idActual = btn.getAttribute('data-id-chat');
            if (!idActual) return;
            const formData = new FormData();
            formData.append('id_conversacion', idActual);
            fetch(`?accion=cambiar_estado_chat`, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        actualizarEstadoChat(idActual, data.activo);
                        cargarConversaciones();
                    } else if (data.error) {
                        alert(data.error);
                    }
                })
                .catch(err => console.error('Error cambiando estado del chat:', err));
        });
    }

    btn.setAttribute('data-id-chat', idChat);
    if (activo === 1) {
        btn.innerHTML = '<i class="fas fa-toggle-on"></i> Activo';
        btn.classList.remove('chat-toggle-inactivo');
        btn.classList.add('chat-toggle-activo');
        btn.title = 'Clic para desactivar este chat';
    } else {
        btn.innerHTML = '<i class="fas fa-toggle-off"></i> Inactivo';
        btn.classList.remove('chat-toggle-activo');
        btn.classList.add('chat-toggle-inactivo');
        btn.title = 'Clic para activar este chat';
    }
}

// ==== SPINNER DE CARGA AL CAMBIAR DE CONVERSACIÓN ====
// Se muestra al instante en abrirConversacion(), antes de que responda el
// servidor, para que el cambio entre chats no se vea "congelado" con el
// contenido del chat anterior ni salte de golpe al vacío.
function mostrarCargandoChat() {
    const area = document.getElementById('messages-area');
    area.classList.add('cargando');
    area.innerHTML = '<div class="chat-loading-spinner"></div>';
}