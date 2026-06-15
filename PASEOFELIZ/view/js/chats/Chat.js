/* =========================================================================
   LÓGICA DE JAVASCRIPT DEL CHAT - PASEO FELIZ
   ========================================================================= */
let convActivaId = null;
let intervalChat = null;
let msgSeleccionadoId = null;
let msgSeleccionadoTexto = "";
let msgSeleccionadoEmisor = null;

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

        fetch('Chat.php?accion=borrar_mensaje', { method: 'POST', body: params })
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
});

// Función para listar las conversaciones o buscar nuevos usuarios
function cargarConversaciones(terminoBusqueda = '') {
    let url = 'Chat.php?accion=listar_chats';
    if(terminoBusqueda) { url += '&buscar=' + encodeURIComponent(terminoBusqueda); }

    fetch(url)
        .then(res => res.json())
        .then(chats => {
            const lista = document.getElementById('conv-list');
            lista.innerHTML = '';
            
            if(chats.length === 0) {
                lista.innerHTML = '<div style="padding: 20px; text-align: center; color: #888; font-size: 0.9rem;">No se encontraron usuarios</div>';
                return;
            }

            chats.forEach(conv => {
                const item = document.createElement('div');
                item.className = 'conv-item' + (convActivaId === conv.id && conv.id ? ' active' : '');
                item.innerHTML = `
                  <div class="conv-avatar"><img src="${conv.avatar}" onerror="this.src='../assets/images/logo.png'"></div>
                  <div class="conv-info">
                    <div class="conv-name">${conv.nombre}</div>
                    <div class="conv-last">${conv.ultimo}</div>
                  </div>`;
                
                item.addEventListener('click', () => {
                    if(conv.id) {
                        abrirConversacion(conv.id, conv.nombre, conv.avatar);
                    } else {
                        fetch(`Chat.php?accion=obtener_o_crear_chat&id_receptor=${conv.id_receptor}`)
                            .then(r => r.json())
                            .then(data => {
                                if(data.id_conversacion) {
                                    document.getElementById('search-input').value = ''; 
                                    abrirConversacion(data.id_conversacion, conv.nombre, conv.avatar);
                                }
                            });
                    }
                });
                lista.appendChild(item);
            });
        }).catch(err => console.error("Error panel:", err));
}

// Configura la interfaz al seleccionar un chat activo
function abrirConversacion(id, nombre, avatar) {
    convActivaId = id;
    document.getElementById('header-avatar').innerHTML = `<img src="${avatar}" onerror="this.src='../assets/images/logo.png'">`;
    document.getElementById('header-name').textContent = nombre;
    document.getElementById('empty-state').style.display = 'none';
    document.getElementById('chat-header').style.display = 'flex';
    document.getElementById('messages-area').style.display = 'flex';
    document.getElementById('input-area').style.display = 'flex';

    cargarMensajes(id);
    clearInterval(intervalChat);
    intervalChat = setInterval(() => cargarMensajes(id), 3000);

    if (window.innerWidth <= 680) {
        document.getElementById('conv-panel').classList.add('hidden');
    }
}

// Carga asíncronamente el historial de mensajes de la conversación activa
function cargarMensajes(idChat) {
    fetch(`Chat.php?accion=cargar_mensajes&id_chat=${idChat}`)
        .then(res => res.json())
        .then(mensajes => {
            const area = document.getElementById('messages-area');
            if(area.children.length === mensajes.length) return;

            area.innerHTML = '';
            mensajes.forEach(msg => {
                const row = document.createElement('div');
                row.className = `msg-row ${msg.de === 'yo' ? 'sent' : 'received'}`;
                
                let avatarHTML = `<div class="burbuja-avatar"><img src="${msg.avatar_burbuja}" onerror="this.src='../assets/images/logo.png'"></div>`;
                
                let contenido = '';
                if (msg.imagen) {
                    let urlLimpia = msg.imagen.replace(/^\.\.\//, '');
                    if(!urlLimpia.startsWith('../')) {
                        urlLimpia = '../' + urlLimpia;
                    }
                    contenido += `<img src="${urlLimpia}" class="msg-image" onclick="window.open('${urlLimpia}')" onerror="this.src='../assets/images/logo.png'"/>`;
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

    fetch('Chat.php?accion=enviar', { method: 'POST', body: formData })
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