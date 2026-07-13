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
// Con la membresía de un servicio activa el cliente ya no ve la vista de
// compra: se muestra el dashboard post-compra conectado a la BD
// (dashboard_paseos.js / dashboard_hospedaje.js / dashboard_adiestramiento.js).
const DASHBOARD_FNS = {
    paseos:         { mostrar: 'mostrarDashboardPaseos',         ocultar: 'ocultarDashboardPaseos' },
    hospedaje:      { mostrar: 'mostrarDashboardHospedaje',      ocultar: 'ocultarDashboardHospedaje' },
    adiestramiento: { mostrar: 'mostrarDashboardAdiestramiento', ocultar: 'ocultarDashboardAdiestramiento' },
};

function renderService(key) {
    renderBannerOtraMascota(key);
    renderBannerRecuperarServicio(key);

    // Ocultar todos los dashboards antes de decidir cuál mostrar (cambio de tab)
    Object.values(DASHBOARD_FNS).forEach(f => {
        if (typeof window[f.ocultar] === 'function') window[f.ocultar]();
    });

    const fns = DASHBOARD_FNS[key];
    const usarDashboard = fns && membresias[key] && typeof window[fns.mostrar] === 'function';

    if (usarDashboard) {
        mostrarVistaCompra(false);
        window[fns.mostrar]().then(ok => {
            // Caso borde: membresía activa sin pedido en la BD → vista de compra normal
            if (!ok && currentSvc === key) {
                mostrarVistaCompra(true);
                renderShowcase(key);
            }
        });
        return;
    }

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

// Función del wizard correspondiente a un servicio (o null si ese wizard
// todavía no existe/cargó). Compartida entre actualizarBotonesBooking() y
// renderBannerOtraMascota(), para no duplicar el mapeo en dos lugares.
function obtenerAbridorWizard(svcKey) {
    const mapa = {
        paseos:         typeof abrirWizardPaseos === 'function' ? abrirWizardPaseos : null,
        adiestramiento: typeof abrirWizardAdiestramiento === 'function' ? abrirWizardAdiestramiento : null,
        hospedaje:      typeof abrirWizardHospedaje === 'function' ? abrirWizardHospedaje : null,
    };
    return mapa[svcKey] || null;
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
    const abrirWizardServicio = obtenerAbridorWizard(svcKey);
    const tieneWizard = !!abrirWizardServicio;

    // — Sin ninguna mascota registrada: no se puede comprar NINGUNA membresía —
    // (la membresía ahora se activa por mascota, así que sin mascota no hay
    // a quién asignársela).
    if (!membresias.tieneMascotas) {
        avail.innerHTML = `<span class="avail-dot avail-off"></span> No tienes mascotas registradas`;
        btnP.innerHTML  = `<i class="ph ph-paw-print"></i> Registrar una mascota`;
        btnP.onclick    = () => { window.location.href = 'usuario.php'; };
        btnS.style.display = 'none';

        requestAnimationFrame(() => {
            btnP.classList.add('listo');
            btnS.classList.add('listo');
            avail.classList.add('listo');
        });
        renderBannerOtraMascota(svcKey);
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

    renderBannerOtraMascota(svcKey);
}

// — Detalle de membresía por mascota + botón "Comprar para otra mascota" —
// Banner "Comprar [servicio] para otra mascota" — vive FUERA de la vitrina
// y del dashboard (#otra-mascota-banner, justo debajo de las pestañas), así
// que se ve siempre, sin importar si el dashboard post-compra reemplaza la
// vitrina normal (que era donde antes vivía este aviso, y por eso
// desaparecía en cuanto el dashboard tomaba el control).
// Endpoint que devuelve el pedido activo del cliente para un servicio —
// es la "base" del modo exprés. Hospedaje y Adiestramiento no tienen
// dashboard propio como Paseos (que ya resuelve todo esto en su propia
// tarjeta "Añadir a otra mascota al servicio"), así que este banner
// genérico usa estos endpoints livianos en su lugar.
const endpointPedidoActivo = {
    hospedaje:      '../../model/obtener_mi_pedido_hospedaje.php',
    adiestramiento: '../../model/obtener_mi_pedido_adiestramiento.php',
};

function renderBannerOtraMascota(svcKey) {
    const cont = document.getElementById('otra-mascota-banner');
    if (!cont) return;

    // Los 3 dashboards (dashboard_paseos.js / _hospedaje.js / _adiestramiento.js)
    // ya muestran su propio botón "Añadir a otra mascota al servicio" dentro
    // de su tarjeta (debajo de "Dirección"), así que aquí lo dejamos vacío
    // para no duplicarlo. Este banner genérico solo se usa si por algún
    // motivo el script del dashboard de ese servicio no cargó.
    const fnsDash = DASHBOARD_FNS[svcKey];
    const dashboardActivo = fnsDash && membresias[svcKey] && typeof window[fnsDash.mostrar] === 'function';
    if (dashboardActivo) { cont.innerHTML = ''; return; }

    inyectarEstiloBannerOtraMascota();

    const mascotas = membresias.mascotas || [];

    // Solo tiene sentido este botón si YA hay al menos 1 mascota con la
    // membresía activa (si nadie la tiene aún, el botón normal de "Comprar
    // membresía" de la vitrina ya cubre la primera compra) y si el
    // servicio tiene un endpoint de pedido activo (paseos no llega aquí).
    const activas     = mascotas.filter(m => m[svcKey]);
    const disponibles = mascotas.filter(m => !m[svcKey]);

    if (!mascotas.length || activas.length === 0 || disponibles.length === 0 || !endpointPedidoActivo[svcKey]) {
        cont.innerHTML = '';
        return;
    }

    cont.innerHTML = `
        <button type="button" class="omb-btn-add" id="omb-btn-add">
            <i class="ph ph-plus"></i> Añadir a otra mascota al servicio
        </button>`;

    document.getElementById('omb-btn-add').addEventListener('click', () => abrirModalOtraMascota(svcKey));
}

// ── Banner "Recuperar servicio cancelado" ─────────────────────────────
// Si el admin canceló un servicio que YA estaba pagado, el cliente puede
// recuperarlo sin pagar de nuevo: el pedido vuelve a listo_para_asignar
// con su configuración original. El banner vive como hijo propio dentro
// de #otra-mascota-banner (que renderBannerOtraMascota limpia primero,
// por eso este se agrega después y de forma asíncrona).
const NOMBRE_SERVICIO = { paseos: 'paseos', adiestramiento: 'adiestramiento', hospedaje: 'hospedaje' };

async function renderBannerRecuperarServicio(svcKey) {
    const cont = document.getElementById('otra-mascota-banner');
    if (!cont) return;

    // Quitar el banner anterior (de otra pestaña) si quedó
    const viejo = document.getElementById('rec-servicio-banner');
    if (viejo) viejo.remove();

    let pedidos = [];
    try {
        const r = await fetch('../../model/recuperar_pedido_cliente.php?tipo=' + svcKey).then(res => res.json());
        if (r.success) pedidos = r.pedidos || [];
    } catch (e) { return; }

    // Si mientras cargaba el usuario cambió de pestaña, no pintar nada
    if (currentSvc !== svcKey || !pedidos.length) return;

    inyectarEstiloBannerRecuperar();

    const div = document.createElement('div');
    div.id = 'rec-servicio-banner';
    div.innerHTML = pedidos.map(p => `
        <div class="rec-banner">
            <i class="ph ph-arrow-counter-clockwise"></i>
            <div class="rec-txt">
                <strong>Tu servicio de ${NOMBRE_SERVICIO[svcKey]} para ${escHtml(p.mascota)} fue cancelado.</strong>
                ${p.motivo ? '<br><small>Motivo: ' + escHtml(p.motivo) + '</small>' : ''}
                <br><small>Como ya lo pagaste, puedes recuperarlo sin costo con su configuración original.</small>
            </div>
            <button class="rec-btn" data-recuperar="${p.id_pedido}">
                <i class="ph ph-arrow-counter-clockwise"></i> Recuperar servicio
            </button>
        </div>`).join('');
    cont.appendChild(div);

    div.querySelectorAll('[data-recuperar]').forEach(btn => {
        btn.addEventListener('click', async () => {
            btn.disabled = true;
            btn.innerHTML = '<i class="ph ph-spinner"></i> Recuperando...';
            try {
                const r = await fetch('../../model/recuperar_pedido_cliente.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ tipo: svcKey, id_pedido: parseInt(btn.dataset.recuperar, 10) }),
                }).then(res => res.json());
                if (!r.success) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="ph ph-arrow-counter-clockwise"></i> Recuperar servicio';
                    alert(r.message || 'No se pudo recuperar el servicio.');
                    return;
                }
                // Refrescar membresías y volver a la pestaña del servicio recuperado
                await cargarMembresias(); // esto re-renderiza en 'paseos'
                if (svcKey !== 'paseos') {
                    const tab = document.querySelector(`.tab[data-svc="${svcKey}"]`);
                    if (tab) tab.click();
                }
            } catch (e) {
                btn.disabled = false;
                btn.innerHTML = '<i class="ph ph-arrow-counter-clockwise"></i> Recuperar servicio';
                alert('Error de conexión. Intenta de nuevo.');
            }
        });
    });
}

function inyectarEstiloBannerRecuperar() {
    if (document.getElementById('rec-banner-css')) return;
    const st = document.createElement('style');
    st.id = 'rec-banner-css';
    st.textContent = `
        .rec-banner{display:flex;align-items:center;gap:14px;background:#fff7ed;border:1px solid #fed7aa;
            border-radius:14px;padding:14px 18px;margin:0 0 14px;}
        .rec-banner>i{font-size:1.5rem;color:#ea580c;flex-shrink:0;}
        .rec-txt{flex:1;font-size:.85rem;color:#431407;line-height:1.45;}
        .rec-txt small{color:#9a3412;}
        .rec-btn{display:inline-flex;align-items:center;gap:7px;background:#ea580c;color:#fff;border:none;
            padding:10px 16px;border-radius:10px;font-size:.83rem;font-weight:700;cursor:pointer;
            transition:background .2s;white-space:nowrap;flex-shrink:0;}
        .rec-btn:hover{background:#c2410c;}
        .rec-btn:disabled{opacity:.6;cursor:not-allowed;}
        @media (max-width:640px){.rec-banner{flex-direction:column;align-items:stretch;text-align:center;}
            .rec-btn{justify-content:center;}}`;
    document.head.appendChild(st);
}

// Mini-modal: cómo añadir la nueva mascota (exprés o servicio nuevo).
// Mismo patrón que dashboard_paseos.js#abrirModalAgregarMascota, pero
// leyendo el pedido base desde endpointPedidoActivo (aquí no hay un "S"
// con el pedido ya cargado en memoria).
async function abrirModalOtraMascota(svcKey) {
    const abrir = obtenerAbridorWizard(svcKey);
    if (!abrir) return;

    const ocupadas = (membresias.mascotas || []).filter(m => m[svcKey]).map(m => m.id_mascota);

    let pedidoBase = null;
    try {
        const r = await fetch(endpointPedidoActivo[svcKey]).then(res => res.json());
        if (r.success && r.tiene_servicio) pedidoBase = r.pedido;
    } catch (e) { /* sigue con pedidoBase = null */ }

    if (!pedidoBase) {
        // No se pudo determinar el servicio activo: directo al flujo normal
        abrir({ ocupadas });
        return;
    }

    const nombreMascota = pedidoBase.mascota || 'tu mascota';
    abrirCajaOtraMascota(
        '<button class="omb-modal-x" data-cerrar><i class="ph ph-x"></i></button>' +
        '<h3 class="omb-modal-h3"><i class="ph ph-paw-print"></i> Añadir otra mascota</h3>' +
        '<p class="omb-modal-txt">¿Cómo quieres añadirla?</p>' +
        '<button id="omb-add-express" class="omb-btn-opcion">' +
            '<i class="ph ph-users-three"></i>' +
            '<span><strong>Unirse al servicio actual</strong><br>' +
            '<small>Misma configuración que ' + escHtml(nombreMascota) + '. Solo eliges la mascota y pagas.</small></span>' +
        '</button>' +
        '<button id="omb-add-normal" class="omb-btn-opcion">' +
            '<i class="ph ph-gear"></i>' +
            '<span><strong>Configurar un servicio nuevo</strong><br>' +
            '<small>Eliges todo desde cero.</small></span>' +
        '</button>'
    );

    document.getElementById('omb-add-express').addEventListener('click', () => {
        cerrarCajaOtraMascota();
        abrir({ modo: 'agregar_mascota', base: pedidoBase, ocupadas });
    });
    document.getElementById('omb-add-normal').addEventListener('click', () => {
        cerrarCajaOtraMascota();
        abrir({ ocupadas });
    });
}

function abrirCajaOtraMascota(html) {
    cerrarCajaOtraMascota();
    const m = document.createElement('div');
    m.id = 'omb-modal';
    m.innerHTML = '<div class="omb-modal-overlay"><div class="omb-modal-box">' + html + '</div></div>';
    document.body.appendChild(m);
    m.querySelector('.omb-modal-overlay').addEventListener('click', e => {
        if (e.target === e.currentTarget) cerrarCajaOtraMascota();
    });
    const cerrar = m.querySelector('[data-cerrar]');
    if (cerrar) cerrar.addEventListener('click', cerrarCajaOtraMascota);
}
function cerrarCajaOtraMascota() {
    const m = document.getElementById('omb-modal');
    if (m) m.remove();
}

function inyectarEstiloBannerOtraMascota() {
    if (document.getElementById('omb-estilos')) return;
    const style = document.createElement('style');
    style.id = 'omb-estilos';
    style.textContent = `
        .omb-btn-add {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 10px 18px; margin: 0 0 18px;
            border: 1.5px dashed #3E72A6; border-radius: 999px;
            background: #eff6ff; color: #3E72A6; font-weight: 700; font-size: .84rem;
            cursor: pointer; transition: background .15s;
        }
        .omb-btn-add:hover { background: #dbeafe; }
        @media (max-width: 640px) { .omb-btn-add { width: 100%; justify-content: center; } }
        .omb-modal-overlay {
            position: fixed; inset: 0; background: rgba(15,23,42,.5);
            display: flex; align-items: center; justify-content: center;
            z-index: 9999; padding: 16px;
        }
        .omb-modal-box {
            background: #fff; border-radius: 14px; width: 100%; max-width: 440px;
            padding: 22px 22px 18px; position: relative;
            box-shadow: 0 24px 60px rgba(2,6,23,.35);
        }
        .omb-modal-x {
            position: absolute; top: 14px; right: 14px; width: 30px; height: 30px;
            border: none; border-radius: 50%; background: #f1f5f9; color: #475569;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
        }
        .omb-modal-x:hover { background: #e5eaf1; }
        .omb-modal-h3 {
            font-size: .98rem; font-weight: 800; display: flex; align-items: center; gap: 7px;
            margin: 0 0 4px; color: #0f172a;
        }
        .omb-modal-txt { font-size: .84rem; color: #64748b; margin: 4px 0 14px; }
        .omb-btn-opcion {
            width: 100%; display: flex; align-items: flex-start; text-align: left; gap: 12px;
            padding: 13px 14px; border: 1.5px solid #e2e8f0; border-radius: 12px;
            background: #fff; cursor: pointer; margin-bottom: 10px; font-family: inherit;
            color: #0f172a; transition: border-color .15s, background .15s;
        }
        .omb-btn-opcion:hover { border-color: #3E72A6; background: #f6f9ff; }
        .omb-btn-opcion i { font-size: 1.3rem; color: #3E72A6; margin-top: 2px; }
        .omb-btn-opcion small { color: #64748b; line-height: 1.4; }
    `;
    document.head.appendChild(style);
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