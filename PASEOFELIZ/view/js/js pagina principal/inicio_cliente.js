// ── SERVICE DATA ──
const services = {
    paseos: {
        tag: '⭐ Más solicitado',
        images: [
            'https://images.unsplash.com/photo-1601758124510-52d02ddb7cbd?auto=format&fit=crop&w=700&q=80',
            'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?auto=format&fit=crop&w=700&q=80',
            'https://images.unsplash.com/photo-1587300003388-59208cc962cb?auto=format&fit=crop&w=700&q=80',
            'https://images.unsplash.com/photo-1554692918-08fa0fdc9db3?auto=format&fit=crop&w=700&q=80',
        ],
        title: 'Paseos',
        rating: '4.9',
        clients: '+1.200 clientes felices',
        desc: 'Tu mascota disfrutará de paseos seguros, divertidos y adaptados a su energía. Nosotros cuidamos de su bienestar mientras tú tienes tranquilidad.',
        features: [
            { icon: 'ph-paw-print', text: '<strong>Número de mascotas:</strong> <span>Máximo 6 mascotas.</span>' },
            { icon: 'ph-users', text: '<strong>Modalidad de paseo:</strong> <span>Grupal o Individual.</span>' },
            { icon: 'ph-calendar-blank', text: '<strong>Días en la semana:</strong> <span>Puedes elegir los días que prefieras.</span>' },
            { icon: 'ph-clock', text: '<strong>Duración del paseo:</strong> <span>Desde 30 min, 1 h, 2 h. Máx. 3 h.</span>' },
        ],
        badges: ['Paseadores certificados', 'Mascotas aseguradas', 'Trato amoroso', 'Seguimiento en tiempo real'],
        price: '$18.000',
        unit: 'por paseo',
        benefits: ['Fotos del paseo', 'Seguimiento GPS', 'Hidratación incluida', 'Ejercicio y diversión'],
        stats: [
            { icon: 'ph-paw-print', num: '3.200+', desc: 'Mascotas felices' },
            { icon: 'ph-person-simple-walk', num: '8.400+', desc: 'Paseos realizados' },
            { icon: 'ph-star', num: '4.9/5', desc: 'Calificación' },
        ],
        infoCards: [
            { icon: 'ph-camera', title: 'Fotos del paseo', desc: 'Recibe imágenes de tu peludo.' },
            { icon: 'ph-map-pin', title: 'Ruta GPS', desc: 'Seguimiento del recorrido.' },
            { icon: 'ph-chat-dots', title: 'Resumen del paseo', desc: 'Informe con detalles y recomendaciones.' },
            { icon: 'ph-drop', title: 'Hidratación incluida', desc: 'Agua fresca durante el paseo.' },
        ],
        testimonials: [
            { text: 'Mi perro volvió feliz y cansado cada vez. ¡Excelente servicio!', name: 'Laura M.', role: 'Cliente habitual', img: 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&w=80&q=80' },
            { text: 'Los paseadores son increíbles. Mi golden los adora.', name: 'Sofía R.', role: 'Cliente satisfecha', img: 'https://images.unsplash.com/photo-1508214751196-bcfd4ca60f91?auto=format&fit=crop&w=80&q=80' },
        ],
        footer: 'Porque tu mascota merece lo mejor, cada paseo es una experiencia llena de amor y cuidado. 🐾',
    },
    adiestramiento: {
        tag: '🎓 Transformamos su comportamiento',
        images: [
            'https://images.unsplash.com/photo-1522276498395-f4f68f7f8454?auto=format&fit=crop&w=700&q=80',
            'https://images.unsplash.com/photo-1576201836106-db1758fd1c97?auto=format&fit=crop&w=700&q=80',
            'https://images.unsplash.com/photo-1568572933382-74d440642117?auto=format&fit=crop&w=700&q=80',
            'https://images.unsplash.com/photo-1530281700549-e82e7bf110d6?auto=format&fit=crop&w=700&q=80',
        ],
        title: 'Adiestramiento canino',
        rating: '4.9',
        clients: '+850 perros educados',
        desc: 'Métodos positivos, basados en refuerzo y empatía. Mejora la conducta de tu perro y fortalece la relación que tienen.',
        features: [
            { icon: 'ph-users', text: '<strong>Modalidades:</strong> <span>Individual / A domicilio / Grupal.</span>' },
            { icon: 'ph-target', text: '<strong>Áreas de trabajo:</strong> <span>Obediencia, conducta, socialización, ansiedad, reactividad y más.</span>' },
            { icon: 'ph-clock', text: '<strong>Duración:</strong> <span>Sesiones de 45 a 60 minutos.</span>' },
            { icon: 'ph-clipboard-text', text: '<strong>Plan personalizado:</strong> <span>Adaptado a las necesidades de tu perro.</span>' },
        ],
        badges: ['Entrenadores certificados', 'Métodos positivos', 'Bienestar emocional', 'Resultados efectivos'],
        price: '$22.000',
        unit: 'por sesión',
        benefits: ['Evaluación inicial incluida', 'Plan de entrenamiento personalizado', 'Seguimiento y recomendaciones', 'Apoyo entre sesiones', 'Informe de progreso'],
        stats: [
            { icon: 'ph-paw-print', num: '850+', desc: 'Perros entrenados' },
            { icon: 'ph-users', num: '620+', desc: 'Familias satisfechas' },
            { icon: 'ph-star', num: '4.9/5', desc: 'Calificación' },
        ],
        infoCards: [
            { icon: 'ph-lightbulb', title: 'Mejora la conducta', desc: 'Reduce ladridos, saltos y conductas indeseadas.' },
            { icon: 'ph-heart', title: 'Vínculo más fuerte', desc: 'Comunicación y confianza.' },
            { icon: 'ph-target', title: 'Obediencia básica', desc: 'Sentarse, quedarse, venir, caminar.' },
            { icon: 'ph-smiley', title: 'Menos estrés', desc: 'Perros más tranquilos y equilibrados.' },
        ],
        testimonials: [
            { text: 'El cambio de mi perro ha sido increíble. Más tranquilo, obediente y feliz.', name: 'Carolina R.', role: 'Cliente satisfecha', img: 'https://images.unsplash.com/photo-1508214751196-bcfd4ca60f91?auto=format&fit=crop&w=80&q=80' },
            { text: 'Increíble metodología. Mi perro aprendió en tiempo récord.', name: 'Andrés P.', role: 'Cliente habitual', img: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=80&q=80' },
        ],
        footer: 'Cada perro es único, por eso diseñamos un plan a la medida para lograr resultados reales. 🐾',
    },
    hospedaje: {
        tag: '🏡 Como en casa, pero con más amor',
        images: [
            'https://images.unsplash.com/photo-1543466835-00a7907e9de1?auto=format&fit=crop&w=700&q=80',
            'https://images.unsplash.com/photo-1583511655857-d19b40a7a54e?auto=format&fit=crop&w=700&q=80',
            'https://images.unsplash.com/photo-1518717758536-85ae29035b6d?auto=format&fit=crop&w=700&q=80',
            'https://images.unsplash.com/photo-1537151625747-768eb6cf92b2?auto=format&fit=crop&w=700&q=80',
        ],
        title: 'Hospedaje canino',
        rating: '4.9',
        clients: '+950 perros hospedados',
        desc: 'Tu perro se sentirá como en casa. Un ambiente seguro, cómodo y lleno de cariño mientras tú estás fuera.',
        features: [
            { icon: 'ph-house', text: '<strong>Ambiente familiar:</strong> <span>Atención personalizada 24/7.</span>' },
            { icon: 'ph-shield-check', text: '<strong>Espacios seguros:</strong> <span>Casa adaptada y áreas de juego.</span>' },
            { icon: 'ph-person-simple-walk', text: '<strong>Paseos diarios:</strong> <span>Ejercicio y diversión asegurados.</span>' },
            { icon: 'ph-fork-knife', text: '<strong>Alimentación incluida:</strong> <span>Seguimos sus hábitos y horarios.</span>' },
            { icon: 'ph-camera', text: '<strong>Reportes diarios:</strong> <span>Fotos y videos para tu tranquilidad.</span>' },
        ],
        badges: ['Supervisión 24/7', 'Atención personalizada', 'Fotos y videos diarios', 'Veterinaria de confianza'],
        price: '$28.000',
        unit: 'por noche',
        benefits: ['Alojamiento en ambiente familiar', 'Paseos y juegos diarios', 'Alimentación incluida', 'Medicamentos (si aplica)', 'Actualizaciones diarias'],
        stats: [
            { icon: 'ph-paw-print', num: '950+', desc: 'Perros hospedados' },
            { icon: 'ph-star', num: '4.9/5', desc: 'Calificación' },
            { icon: 'ph-smiley', num: '98%', desc: 'Clientes satisfechos' },
        ],
        infoCards: [
            { icon: 'ph-house', title: 'Ambiente hogareño', desc: 'Tu perro vive dentro de casa.' },
            { icon: 'ph-heart', title: 'Mucho amor', desc: 'Cuidado y compañía todo el día.' },
            { icon: 'ph-tree', title: 'Zonas de juego', desc: 'Patio seguro para correr y explorar.' },
            { icon: 'ph-moon', title: 'Rutinas respetadas', desc: 'Mantenemos sus hábitos y horarios.' },
        ],
        testimonials: [
            { text: 'Mi perrito la pasó increíble, lo cuidaron como si fuera de ellos. ¡Totalmente recomendado!', name: 'Valentina G.', role: 'Cliente satisfecha', img: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=80&q=80' },
            { text: 'Regresé tranquila sabiendo que mi perra estaba en las mejores manos.', name: 'Mariana L.', role: 'Cliente habitual', img: 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&w=80&q=80' },
        ],
        footer: 'Seguridad, cariño y diversión en un solo lugar. 🐾',
    }
};

// ── STATE ──
let currentSvc = 'paseos';
let currentImg = 0;
let currentTest = 0;
let lastImg = -1;
let lastSvc = '';

function randomImgIndex(images, svcKey) {
    if (svcKey !== lastSvc) { lastImg = -1; lastSvc = svcKey; }
    if (images.length <= 1) return 0;
    let idx;
    do { idx = Math.floor(Math.random() * images.length); } while (idx === lastImg);
    lastImg = idx;
    return idx;
}

// ── DOM REFS ──
const mainImg = document.getElementById('main-img');
const imgTag = document.getElementById('img-tag');
const thumbsEl = document.getElementById('thumbs-container');
const dotsEl = document.getElementById('dots-container');
const svcTitle = document.getElementById('svc-title');
const svcRating = document.getElementById('svc-rating');
const svcClients = document.getElementById('svc-clients');
const svcDesc = document.getElementById('svc-desc');
const featuresList = document.getElementById('features-list');
const badgesRow = document.getElementById('badges-row');
const svcPrice = document.getElementById('svc-price');
const svcUnit = document.getElementById('svc-unit');
const benefitsList = document.getElementById('benefits-list');
const infoCardsEl = document.getElementById('info-cards');
const statsRow = document.getElementById('stats-row');
const testText = document.getElementById('test-text');
const testImg = document.getElementById('test-img');
const testName = document.getElementById('test-name');
const testRole = document.getElementById('test-role');
const testDots = document.getElementById('test-dots');
const footerText = document.getElementById('footer-text');
const showcase = document.getElementById('showcase');
const bottomSection = document.querySelector('.bottom-section');

// ══════════════════════════════════════════════════
//  SKELETON: CAMBIO DE TAB
// ══════════════════════════════════════════════════
function showTabSkeleton() {
    showcase.classList.add('sk-loading');
    bottomSection.classList.add('sk-loading');
}
function hideTabSkeleton() {
    showcase.classList.remove('sk-loading');
    bottomSection.classList.remove('sk-loading');
}

// ── Regla de navegación post-compra (FASE 4) ──
// Con la membresía de Paseos activa el cliente ya no ve la vista de compra:
// se muestra el dashboard post-compra conectado a la BD (dashboard_paseos.js).
function renderService(key) {
    const usarDashboard = key === 'paseos' && membresias.paseos &&
                          typeof mostrarDashboardPaseos === 'function';

    if (usarDashboard) {
        mostrarVistaCompra(false);
        mostrarDashboardPaseos().then(ok => {
            // Caso borde: membresía activa sin pedido en la BD → vista de compra normal
            if (!ok && currentSvc === 'paseos') {
                mostrarVistaCompra(true);
                renderShowcase(key);
            }
        });
        return;
    }

    if (typeof ocultarDashboardPaseos === 'function') ocultarDashboardPaseos();
    mostrarVistaCompra(true);
    renderShowcase(key);
}

// Muestra u oculta la vista de compra (showcase + tarjetas + footer)
function mostrarVistaCompra(visible) {
    showcase.style.display = visible ? '' : 'none';           // CSS: display grid
    bottomSection.style.display = visible ? 'grid' : 'none';  // inline original: grid
    const footer = document.getElementById('footer-bar');
    if (footer) footer.style.display = visible ? '' : 'none';
}

function renderShowcase(key) {
    const s = services[key];
    currentImg = randomImgIndex(s.images, key);
    currentTest = 0;

    // 1. Mostrar skeleton de tab
    showTabSkeleton();

    // 2. Tras 420 ms renderizar contenido y quitar skeleton
    setTimeout(() => { try {

        // Tag & image
        imgTag.innerHTML = s.tag;
        mainImg.src = s.images[currentImg];

        // Thumbnails
        thumbsEl.innerHTML = '';
        dotsEl.innerHTML = '';
        s.images.forEach((src, i) => {
            const t = document.createElement('img');
            t.src = src; t.className = 'thumb' + (i===currentImg?' active':'');
            t.addEventListener('click', () => setImage(i));
            thumbsEl.appendChild(t);

            const d = document.createElement('div');
            d.className = 'dot' + (i===currentImg?' active':'');
            d.addEventListener('click', () => setImage(i));
            dotsEl.appendChild(d);
        });

        // Details
        svcTitle.textContent = s.title;
        svcRating.textContent = s.rating;
        svcClients.textContent = s.clients;
        svcDesc.textContent = s.desc;

        featuresList.innerHTML = s.features.map(f =>
            `<div class="feat"><i class="ph ${f.icon}"></i><div>${f.text}</div></div>`
        ).join('');

        badgesRow.innerHTML = s.badges.map(b =>
            `<span class="badge"><i class="ph ph-check-circle"></i>${b}</span>`
        ).join('');

        // Booking
        svcPrice.textContent = s.price;
        svcUnit.textContent = s.unit;
        benefitsList.innerHTML = s.benefits.map(b =>
            `<li><i class="ph ph-check-circle"></i>${b}</li>`
        ).join('');

        // Info cards
        infoCardsEl.innerHTML = s.infoCards.map(c =>
            `<div class="info-card">
                <i class="ph ${c.icon} ic"></i>
                <h4>${c.title}</h4>
                <p>${c.desc}</p>
            </div>`
        ).join('');

        // Stats
        statsRow.innerHTML = s.stats.map(st =>
            `<div class="stat-box">
                <i class="ph ${st.icon}"></i>
                <span class="stat-num">${st.num}</span>
                <span class="stat-desc">${st.desc}</span>
            </div>`
        ).join('');

        // Testimonial
        renderTestimonial(s, 0);

        // Footer
        footerText.textContent = s.footer;

        // 3. Botones según membresía
        actualizarBotonesBooking(key);

        // 4. Quitar skeleton → aparece contenido
        hideTabSkeleton();

    } catch(err) {
        console.error('renderService error:', err);
        hideTabSkeleton(); // siempre quitar skeleton aunque falle algo
    } }, 420);
}

function setImage(idx) {
    const s = services[currentSvc];
    currentImg = idx;
    mainImg.src = s.images[idx];
    document.querySelectorAll('.thumb').forEach((t,i) => t.classList.toggle('active', i===idx));
    document.querySelectorAll('.dot').forEach((d,i) => d.classList.toggle('active', i===idx));
}

function renderTestimonial(s, idx) {
    const t = s.testimonials[idx];
    testText.textContent = t.text;
    testName.textContent = '— ' + t.name;
    testRole.textContent = t.role;
    testImg.src = t.img;

    testDots.innerHTML = s.testimonials.map((_,i) =>
        `<div class="test-dot${i===idx?' active':''}"></div>`
    ).join('');
    testDots.querySelectorAll('.test-dot').forEach((d,i) => {
        d.addEventListener('click', () => { currentTest=i; renderTestimonial(s,i); });
    });
}

// ── EVENT LISTENERS ──
document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelector('.tab.active').classList.remove('active');
        tab.classList.add('active');
        currentSvc = tab.dataset.svc;
        renderService(currentSvc);
    });
});

document.getElementById('prev-btn').addEventListener('click', () => {
    const imgs = services[currentSvc].images;
    setImage((currentImg - 1 + imgs.length) % imgs.length);
});
document.getElementById('next-btn').addEventListener('click', () => {
    const imgs = services[currentSvc].images;
    setImage((currentImg + 1) % imgs.length);
});

document.getElementById('tprev').addEventListener('click', () => {
    const s = services[currentSvc];
    currentTest = (currentTest - 1 + s.testimonials.length) % s.testimonials.length;
    renderTestimonial(s, currentTest);
});
document.getElementById('tnext').addEventListener('click', () => {
    const s = services[currentSvc];
    currentTest = (currentTest + 1) % s.testimonials.length;
    renderTestimonial(s, currentTest);
});

// ══════════════════════════════════════════════════
//  MEMBRESÍAS
// ══════════════════════════════════════════════════

// Estado global de membresías (se llena al cargar)
// Ahora la membresía es POR MASCOTA: `mascotas` trae el detalle de cada
// una; paseos/adiestramiento/hospedaje siguen existiendo como resumen
// ("¿al menos una mascota la tiene activa?") por compatibilidad.
let membresias = {
    paseos:         false,
    adiestramiento: false,
    hospedaje:      false,
    renovar:        { paseos: false, adiestramiento: false, hospedaje: false },
    mascotas:       [],
    tieneMascotas:  true, // optimista hasta que cargue; evita parpadeo de aviso
};

function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str ?? '';
    return d.innerHTML;
}

// Nombres legibles para el modal "En proceso"
const svcNombre = {
    paseos:         'Paseos',
    adiestramiento: 'Adiestramiento canino',
    hospedaje:      'Hospedaje canino',
};

// — Estilos del bloque "membresía por mascota" (inyectados por JS: no
//   se tocó ningún .css para no romper otros estilos ya existentes) —
(function inyectarEstilosMascotas() {
    const style = document.createElement('style');
    style.textContent = `
        .membresia-mascotas { margin-top: 14px; width: 100%; }
        .mm-titulo { font-size: .78rem; font-weight: 700; color: #475569; margin-bottom: 8px; display:flex; flex-wrap:wrap; gap:6px; align-items:center; }
        .mm-titulo-nota { font-weight: 500; color: #16a34a; }
        .mm-aviso {
            display:flex; align-items:center; gap:8px;
            background:#fff7ed; border:1px solid #fdba74; color:#9a3412;
            border-radius:10px; padding:10px 12px; font-size:.8rem; line-height:1.35;
        }
        .mm-aviso i { color:#ea580c; flex-shrink:0; font-size:1.1rem; }
        .mm-row {
            display:flex; align-items:center; gap:8px;
            padding:7px 0; border-top:1px solid #eef2f7;
        }
        .mm-row:first-of-type { border-top:none; }
        .mm-avatar { width:28px; height:28px; border-radius:50%; object-fit:cover; flex-shrink:0; background:#f1f5f9; }
        .mm-nombre { flex:1; font-size:.82rem; color:#334155; font-weight:600; }
        .mm-badge {
            display:inline-flex; align-items:center; gap:4px;
            font-size:.72rem; font-weight:700; padding:3px 9px; border-radius:999px;
        }
        .mm-activa { background:#dcfce7; color:#16a34a; }
        .mm-porvencer { background:#fef3c7; color:#b45309; }
        .mm-btn-comprar {
            display:inline-flex; align-items:center; gap:5px;
            background:#eff6ff; color:#3E72A6; border:1px solid #bfdbfe;
            border-radius:999px; padding:5px 11px; font-size:.74rem; font-weight:700;
            cursor:pointer; transition:background .15s;
        }
        .mm-btn-comprar:hover { background:#dbeafe; }
    `;
    document.head.appendChild(style);
})();

// — Inyectar modal reutilizable al DOM —
(function crearModal() {
    const m = document.createElement('div');
    m.id = 'modal-membresia';
    m.innerHTML = `
        <div class="modal-overlay" id="modal-overlay">
            <div class="modal-box">
                <button class="modal-close" id="modal-close">
                    <i class="ph ph-x"></i>
                </button>
                <div class="modal-icon"><i class="ph ph-clock" id="modal-icono"></i></div>
                <h3 id="modal-titulo">En proceso</h3>
                <p  id="modal-cuerpo">Estamos trabajando para habilitarte esta funcionalidad muy pronto. ¡Gracias por tu paciencia! 🐾</p>
                <button class="modal-btn-ok" id="modal-btn-ok">Entendido</button>
            </div>
        </div>`;
    document.body.appendChild(m);

    const cerrar = () => m.classList.remove('visible');
    document.getElementById('modal-close').addEventListener('click', cerrar);
    document.getElementById('modal-btn-ok').addEventListener('click', cerrar);
    document.getElementById('modal-overlay').addEventListener('click', e => {
        if (e.target === document.getElementById('modal-overlay')) cerrar();
    });
})();

function abrirModal(titulo, cuerpo, icono = 'ph-clock') {
    document.getElementById('modal-titulo').textContent  = titulo;
    document.getElementById('modal-cuerpo').textContent  = cuerpo;
    document.getElementById('modal-icono').className     = `ph ${icono}`;
    document.getElementById('modal-membresia').classList.add('visible');
}

// — Actualizar botones de booking según membresía —
function actualizarBotonesBooking(svcKey) {
    // Buscar TODOS los elementos con esa clase y tomar el que esté dentro del showcase
    // (el skeleton usa clases iguales pero está en posición absolute, no interfiere con los clics)
    const showcase = document.getElementById('showcase');
    if (!showcase) return;

    // Buscar dentro del showcase ignorando el overlay del skeleton
    const bookingCol = showcase.querySelector('.booking-col');
    if (!bookingCol) return;

    const btnP  = bookingCol.querySelector('button.btn-primary');
    const btnS  = bookingCol.querySelector('button.btn-secondary');
    const avail = bookingCol.querySelector('.avail');

    console.log('🔍 actualizarBotonesBooking:', svcKey, {btnP, btnS, avail, membresias});

    if (!btnP || !btnS || !avail) {
        console.warn('❌ No se encontraron los botones del booking-col');
        return;
    }

    // Ocultar mientras se actualiza (evita flash)
    btnP.classList.remove('listo');
    btnS.classList.remove('listo');
    avail.classList.remove('listo');
    const nombre = svcNombre[svcKey];

    // Los 3 servicios ya tienen checkout real, con el mismo patrón de 4
    // pasos (mascota → ubicación/mapa → resumen → pago) que Paseos.
    const abridores = {
        paseos:         typeof abrirWizardPaseos === 'function' ? abrirWizardPaseos : null,
        adiestramiento: typeof abrirWizardAdiestramiento === 'function' ? abrirWizardAdiestramiento : null,
        hospedaje:      typeof abrirWizardHospedaje === 'function' ? abrirWizardHospedaje : null,
    };
    const abrirWizardServicio = abridores[svcKey];
    const tieneWizard = !!abrirWizardServicio;

    // — Sin ninguna mascota registrada: no se puede comprar NINGUNA membresía —
    // (la membresía ahora se activa por mascota, así que sin mascota no hay
    // a quién asignársela).
    if (!membresias.tieneMascotas) {
        avail.innerHTML = `<span class="avail-dot avail-off"></span> No tienes mascotas registradas`;
        btnP.innerHTML  = `<i class="ph ph-paw-print"></i> Registrar una mascota`;
        // Paseos permite registrar la mascota DENTRO del wizard (se abre con
        // el formulario listo) y seguir directo al pago, sin pasar por
        // Usuario → tu perfil. Los otros servicios aún no tienen registro
        // inline, así que conservan la ruta al perfil.
        btnP.onclick = (svcKey === 'paseos' && tieneWizard)
            ? () => abrirWizardServicio()
            : () => { window.location.href = 'usuario.php'; };
        btnS.style.display = 'none';

        requestAnimationFrame(() => {
            btnP.classList.add('listo');
            btnS.classList.add('listo');
            avail.classList.add('listo');
        });
        renderMascotasMembresia(svcKey);
        return;
    }

    if (membresias.renovar[svcKey]) {
        // Membresía activa pero vence en menos de 1 día
        avail.innerHTML = `<span class="avail-dot avail-warn"></span> ¡Renueva tu membresía hoy!`;
        btnP.innerHTML  = `<i class="ph ph-arrow-clockwise"></i> Renovar membresía`;
        btnP.onclick    = tieneWizard
            ? () => abrirWizardServicio()
            : () => abrirModal(
                '🔄 Renovación en proceso',
                `Tu membresía de ${nombre} vence muy pronto. El sistema de pago estará disponible próximamente.`,
                'ph-arrow-clockwise'
            );
        btnS.style.display = 'flex';
        btnS.innerHTML  = `<i class="ph ph-calendar-check"></i> Ver disponibilidad`;
        btnS.onclick    = () => abrirModal('📅 En proceso', 'La consulta de disponibilidad estará lista muy pronto.', 'ph-calendar-check');

    } else if (membresias[svcKey]) {
        // Membresía activa y vigente
        avail.innerHTML = `<span class="avail-dot"></span> Servicio activo`;
        btnP.innerHTML  = `<i class="ph ph-paw-print"></i> Reservar ahora <i class="ph ph-arrow-right"></i>`;
        btnP.onclick    = () => abrirModal(
            '📅 Reservas en proceso',
            'El sistema de reservas estará disponible muy pronto. ¡Tu membresía ya está activa! 🐾',
            'ph-calendar-check'
        );
        btnS.style.display = 'flex';
        btnS.innerHTML  = `<i class="ph ph-calendar-check"></i> Ver disponibilidad`;
        btnS.onclick    = () => abrirModal('📅 En proceso', 'La consulta de disponibilidad estará lista muy pronto.', 'ph-calendar-check');

    } else {
        // Sin membresía
        avail.innerHTML = `<span class="avail-dot avail-off"></span> No tienes membresía`;
        btnP.innerHTML  = `<i class="ph ph-shopping-cart"></i> Comprar membresía de ${nombre}`;
        btnP.onclick    = tieneWizard
            ? () => abrirWizardServicio()
            : () => abrirModal(
                '🛒 Pago en proceso',
                `El módulo de compra para "${nombre}" estará disponible muy pronto. ¡Estamos trabajando en ello! 🐾`,
                'ph-shopping-cart'
            );
        btnS.style.display = 'none'; // "Ver disponibilidad" oculto sin membresía
    }

    // Mostrar con transición suave
    requestAnimationFrame(() => {
        btnP.classList.add('listo');
        btnS.classList.add('listo');
        avail.classList.add('listo');
    });

    renderMascotasMembresia(svcKey);
}

// — Detalle de membresía por mascota + botón "Comprar para otra mascota" —
// Se muestra debajo de los botones de reserva. Si el usuario tiene varias
// mascotas, cada una tiene su propio estado y su propio botón de compra
// (así puede comprar la misma membresía para más de una mascota).
function renderMascotasMembresia(svcKey) {
    // Deshabilitado para los 3 servicios: Paseos ya tiene su propio wizard
    // (Paso 1: "Selecciona la mascota"), y Adiestramiento/Hospedaje van a
    // tener el suyo propio próximamente — este listado genérico quedaría
    // redundante en los tres casos.
    const cont = document.getElementById('membresia-mascotas');
    if (cont) cont.innerHTML = '';
}

// — Tutorial de bienvenida para usuarios nuevos (sin mascotas) —
// Se muestra UNA sola vez por navegador (localStorage). El CTA abre el
// wizard de Paseos, que al no haber mascotas muestra de una vez el
// formulario de registro: el usuario registra a su peludito y sigue
// directo al pago, todo en el mismo flujo.
function mostrarTutorialBienvenida() {
    if (membresias.tieneMascotas) return;
    if (localStorage.getItem('pf_tutorial_visto')) return;
    if (document.getElementById('pf-tuto-overlay')) return;

    if (!document.getElementById('pf-tuto-css')) {
        const css = document.createElement('style');
        css.id = 'pf-tuto-css';
        css.textContent = `
        #pf-tuto-overlay{position:fixed;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(3px);z-index:9998;display:flex;align-items:center;justify-content:center;padding:16px;animation:pfTutoIn .25s ease}
        @keyframes pfTutoIn{from{opacity:0}to{opacity:1}}
        .pf-tuto-card{background:#fff;border-radius:20px;max-width:430px;width:100%;padding:28px 26px 22px;box-shadow:0 24px 60px rgba(2,6,23,.35);text-align:center;animation:pfTutoUp .3s ease}
        @keyframes pfTutoUp{from{transform:translateY(14px);opacity:0}to{transform:translateY(0);opacity:1}}
        .pf-tuto-emoji{font-size:2.4rem;line-height:1}
        .pf-tuto-card h2{margin:8px 0 2px;font-size:1.25rem;color:#0f172a}
        .pf-tuto-sub{margin:0 0 16px;font-size:.85rem;color:#64748b}
        .pf-tuto-paso{display:flex;gap:12px;align-items:flex-start;text-align:left;padding:9px 10px;border-radius:12px;background:#f8fafc;margin-bottom:8px}
        .pf-tuto-paso .n{flex:none;width:26px;height:26px;border-radius:50%;background:#2563eb;color:#fff;font-weight:800;font-size:.8rem;display:flex;align-items:center;justify-content:center;margin-top:2px}
        .pf-tuto-paso strong{font-size:.84rem;color:#0f172a}
        .pf-tuto-paso small{font-size:.74rem;color:#64748b}
        #pf-tuto-cta{width:100%;margin-top:10px;padding:13px;border:0;border-radius:12px;background:#22c55e;color:#fff;font-weight:800;font-size:.92rem;cursor:pointer}
        #pf-tuto-cta:hover{background:#16a34a}
        #pf-tuto-despues{width:100%;margin-top:8px;padding:9px;border:0;background:none;color:#94a3b8;font-size:.78rem;cursor:pointer;text-decoration:underline}
        `;
        document.head.appendChild(css);
    }

    const ov = document.createElement('div');
    ov.id = 'pf-tuto-overlay';
    ov.innerHTML = `
      <div class="pf-tuto-card">
        <div class="pf-tuto-emoji">🐾</div>
        <h2>¡Bienvenido a Paseo Feliz!</h2>
        <p class="pf-tuto-sub">Tu peludito sale a pasear en 3 pasos:</p>
        <div class="pf-tuto-paso"><span class="n">1</span><div><strong>Registra a tu mascota</strong><br><small>Nombre y foto — toma menos de un minuto y lo haces aquí mismo.</small></div></div>
        <div class="pf-tuto-paso"><span class="n">2</span><div><strong>Elige su plan de paseos</strong><br><small>Cuántos paseos al mes, qué días y a qué hora exacta quieres que salga.</small></div></div>
        <div class="pf-tuto-paso"><span class="n">3</span><div><strong>Síguelo en vivo</strong><br><small>GPS en tiempo real, fotos del paseo y aviso de recogida y entrega.</small></div></div>
        <button id="pf-tuto-cta">🐶 Registrar mi mascota ahora</button>
        <button id="pf-tuto-despues">Explorar primero</button>
      </div>`;
    document.body.appendChild(ov);

    const cerrar = () => { localStorage.setItem('pf_tutorial_visto', '1'); ov.remove(); };
    ov.querySelector('#pf-tuto-despues').addEventListener('click', cerrar);
    ov.querySelector('#pf-tuto-cta').addEventListener('click', () => {
        cerrar();
        if (typeof abrirWizardPaseos === 'function') abrirWizardPaseos();
        else window.location.href = 'usuario.php';
    });
}

// — Llamar al endpoint PHP y guardar estado —
async function cargarMembresias() {
    // inicio.php está en view/pagina_principal/ → controller está en ../../controller/
    try {
        const r = await fetch('../../controller/membresia_estado.php');
        if (r.ok) {
            const texto = await r.text();
            if (texto.trim().startsWith('{')) {
                const d = JSON.parse(texto);
                if (d.success) {
                    membresias.paseos         = d.paseos;
                    membresias.adiestramiento = d.adiestramiento;
                    membresias.hospedaje      = d.hospedaje;
                    membresias.renovar        = d.renovar;
                    membresias.mascotas       = d.mascotas ?? [];
                    membresias.tieneMascotas  = !!d.tiene_mascotas;
                    console.log('✅ Membresías cargadas:', membresias);
                }
            }
        }
    } catch (e) {
        console.warn('Membresías no disponibles:', e);
    }

    // Traer los precios reales configurados por el admin (antes estaban
    // fijos en este archivo: "$18.000" nunca cambiaba aunque el admin
    // actualizara el precio desde el panel).
    try {
        const rp = await fetch('../../model/obtener_precios.php');
        if (rp.ok) {
            const dp = await rp.json();
            if (dp.success) {
                if (dp.precios.paseos)         services.paseos.price         = '$' + Math.round(dp.precios.paseos.precio_unidad).toLocaleString('es-CO');
                if (dp.precios.adiestramiento) services.adiestramiento.price = '$' + Math.round(dp.precios.adiestramiento.precio_unidad).toLocaleString('es-CO');
                if (dp.precios.hospedaje)      services.hospedaje.price      = '$' + Math.round(dp.precios.hospedaje.precio_unidad).toLocaleString('es-CO');
            }
        }
    } catch (e) {
        console.warn('Precios no disponibles, se usan los de respaldo:', e);
    }

    // SIEMPRE renderizar, con o sin datos de membresía
    renderService('paseos');

    // Usuario nuevo sin mascotas: tutorial de bienvenida (una sola vez)
    mostrarTutorialBienvenida();
}

// ── INIT ──
// Dynamic date
const now = new Date();
const opts = { weekday: undefined, year: 'numeric', month: 'long', day: 'numeric' };
document.getElementById('current-date').textContent = now.toLocaleDateString('es-CO', opts);

// Cargar membresías → luego renderizar
cargarMembresias();

// ── MENÚ HAMBURGUESA ──
(function() {
    const btn  = document.getElementById('btn-menu');
    const menu = document.getElementById('menu-latente');
    if (!btn || !menu) return;
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        menu.classList.toggle('show');
    });
    document.addEventListener('click', function(e) {
        if (!menu.contains(e.target) && e.target !== btn)
            menu.classList.remove('show');
    });
})();