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

function renderService(key) {
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
let membresias = {
    paseos:         false,
    adiestramiento: false,
    hospedaje:      false,
    renovar:        { paseos: false, adiestramiento: false, hospedaje: false },
};

// Nombres legibles para el modal "En proceso"
const svcNombre = {
    paseos:         'Paseos',
    adiestramiento: 'Adiestramiento canino',
    hospedaje:      'Hospedaje canino',
};

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

    // Paseos ya tiene wizard de compra real; los demás servicios siguen "en proceso"
    const tieneWizard = svcKey === 'paseos' && typeof abrirWizardPaseos === 'function';

    if (membresias.renovar[svcKey]) {
        // Membresía activa pero vence en menos de 1 día
        avail.innerHTML = `<span class="avail-dot avail-warn"></span> ¡Renueva tu membresía hoy!`;
        btnP.innerHTML  = `<i class="ph ph-arrow-clockwise"></i> Renovar membresía`;
        btnP.onclick    = tieneWizard
            ? () => abrirWizardPaseos()
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
            ? () => abrirWizardPaseos()
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
                    console.log('✅ Membresías cargadas:', membresias);
                }
            }
        }
    } catch (e) {
        console.warn('Membresías no disponibles:', e);
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