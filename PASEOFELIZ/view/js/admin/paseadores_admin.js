        // ══════════════════════════════════════════════════════════════
        // DATOS
        // ══════════════════════════════════════════════════════════════
        const PASEADORES = [
            {
                id: 1, nombre: 'Carlos Rodríguez', email: 'carlosr@gmail.com',
                telefono: '315 778 8990', zona: 'Cúcuta Centro / Los Patios',
                estado: 'activo', rating: 4.8, paseosTotales: 142, paseosMes: 18,
                rutaActual: null, lat: 7.8939, lng: -72.5078,
                clientes: ['María González', 'Pedro Ramírez'],
                color: '#3E72A6',
                rutasHistorial: [
                    { cliente: 'María González', mascota: 'Max', fecha: 'Hoy 09:00', estado: 'completada' },
                    { cliente: 'Pedro Ramírez', mascota: 'Coco', fecha: 'Ayer 08:30', estado: 'completada' },
                ]
            },
            {
                id: 2, nombre: 'Ana Fernández', email: 'ana.f@hotmail.com',
                telefono: '312 334 5566', zona: 'Cúcuta Norte / Atalaya',
                estado: 'en-ruta', rating: 4.9, paseosTotales: 98, paseosMes: 14,
                rutaActual: 'Calle 7 #0e-94, Motilones → Parque La Flora',
                lat: 7.9121, lng: -72.5041,
                clientes: ['Juan Pérez'],
                color: '#16a34a',
                rutasHistorial: [
                    { cliente: 'Juan Pérez', mascota: 'Luna', fecha: 'Hoy 07:00', estado: 'en-ruta' },
                    { cliente: 'Laura Martínez', mascota: 'Rocky', fecha: 'Ayer 16:00', estado: 'completada' },
                ]
            },
            {
                id: 3, nombre: 'Diego Torres', email: 'diegot@gmail.com',
                telefono: '318 445 6677', zona: 'Cúcuta Sur / Comuneros',
                estado: 'pausado', rating: 4.5, paseosTotales: 67, paseosMes: 8,
                rutaActual: null, lat: 7.8801, lng: -72.5123,
                clientes: ['Laura Martínez', 'Ana Fernández', 'Carlos López'],
                color: '#ea580c',
                rutasHistorial: [
                    { cliente: 'Laura Martínez', mascota: 'Rocky', fecha: 'Hace 2 días', estado: 'completada' },
                ]
            },
            {
                id: 4, nombre: 'Sofía Lozano', email: 'sofia.l@outlook.com',
                telefono: '317 456 7890', zona: 'Villa del Rosario / El Zulia',
                estado: 'activo', rating: 5.0, paseosTotales: 213, paseosMes: 24,
                rutaActual: null, lat: 7.8650, lng: -72.4780,
                clientes: ['Ana Fernández', 'Claudia López'],
                color: '#7c3aed',
                rutasHistorial: [
                    { cliente: 'Claudia López', mascota: 'Pelusa', fecha: 'Hoy 11:00', estado: 'completada' },
                    { cliente: 'Ana Fernández', mascota: 'Kira', fecha: 'Ayer 07:30', estado: 'completada' },
                ]
            },
        ];

        const CLIENTES_DISPONIBLES = [
            { id: 101, nombre: 'María González', mascota: 'Max', addr: 'Av. 0 #5-35, Cúcuta Centro', distancia: '1.2 km', urgente: false },
            { id: 102, nombre: 'Pedro Ramírez', mascota: 'Coco', addr: 'Calle 7 #0e-94, Motilones', distancia: '0.8 km', urgente: true },
            { id: 103, nombre: 'Laura Martínez', mascota: 'Rocky', addr: 'Carrera 5 #12-20, Los Patios', distancia: '3.1 km', urgente: false },
            { id: 104, nombre: 'Carlos López', mascota: 'Toby', addr: 'Urb. La Riviera, Cúcuta Norte', distancia: '2.4 km', urgente: false },
            { id: 105, nombre: 'Ana Salcedo', mascota: 'Nala', addr: 'Calle 10 #3-15, Atalaya', distancia: '1.8 km', urgente: true },
        ];

        // ══════════════════════════════════════════════════════════════
        // ESTADO
        // ══════════════════════════════════════════════════════════════
        let filtroActual = 'todos';
        let paseadorSel = PASEADORES[0];
        let selectedRouteClients = [];
        let selectedPriority = 'baja';
        let mapInstance = null;
        let mapMarker = null;

        // ══════════════════════════════════════════════════════════════
        // SIDEBAR
        // ══════════════════════════════════════════════════════════════
        const btnMenu = document.getElementById('btn-menu');
        const menuLatente = document.getElementById('menu-latente');
        btnMenu.addEventListener('click', () => menuLatente.classList.toggle('show'));
        window.addEventListener('click', e => {
            if (!btnMenu.contains(e.target) && !menuLatente.contains(e.target))
                menuLatente.classList.remove('show');
        });

        // ══════════════════════════════════════════════════════════════
        // STATS
        // ══════════════════════════════════════════════════════════════
        function updateStats() {
            document.getElementById('statTotal').textContent = PASEADORES.length;
            document.getElementById('statActivo').textContent = PASEADORES.filter(p => p.estado === 'activo').length;
            document.getElementById('statEnRuta').textContent = PASEADORES.filter(p => p.estado === 'en-ruta').length;
            document.getElementById('statPausado').textContent = PASEADORES.filter(p => p.estado === 'pausado').length;
            const avg = (PASEADORES.reduce((s, p) => s + p.rating, 0) / PASEADORES.length).toFixed(1);
            document.getElementById('statRating').textContent = avg + '★';
        }

        // ══════════════════════════════════════════════════════════════
        // RENDER LISTA
        // ══════════════════════════════════════════════════════════════
        function renderLista() {
            const q = document.getElementById('searchInput').value.toLowerCase();
            const lista = document.getElementById('paseadoresList');
            const empty = document.getElementById('emptyState');
            lista.innerHTML = '';

            const filtrados = PASEADORES.filter(p => {
                const matchQ = !q || p.nombre.toLowerCase().includes(q) || p.zona.toLowerCase().includes(q) || p.email.toLowerCase().includes(q);
                const matchE = filtroActual === 'todos' || p.estado === filtroActual;
                return matchQ && matchE;
            });

            if (filtrados.length === 0) { empty.classList.add('visible'); return; }
            empty.classList.remove('visible');

            filtrados.forEach(p => {
                const card = document.createElement('div');
                card.className = 'paseador-card' + (paseadorSel && paseadorSel.id === p.id ? ' selected' : '');
                card.dataset.id = p.id;
                const initials = p.nombre.split(' ').map(x => x[0]).slice(0, 2).join('');
                const estadoTag = {
                    'activo': '<span class="p-tag tag-activo"><i class="fas fa-circle" style="font-size:.5rem"></i> Activo</span>',
                    'en-ruta': '<span class="p-tag tag-enruta"><i class="fas fa-route" style="font-size:.6rem"></i> En ruta</span>',
                    'pausado': '<span class="p-tag tag-pausado"><i class="fas fa-pause" style="font-size:.6rem"></i> Pausado</span>',
                }[p.estado] || '';
                const dotClass = { activo: 'dot-on', 'en-ruta': 'dot-busy', pausado: 'dot-off' }[p.estado] || 'dot-off';

                card.innerHTML = `
      <div class="p-avatar" style="background:${p.color}">
        ${initials}
        <div class="p-online-dot ${dotClass}"></div>
      </div>
      <div class="p-info">
        <div class="p-name">${p.nombre}</div>
        <div class="p-email">${p.email}</div>
        <div class="p-tags">
          ${estadoTag}
          <span class="p-tag tag-rating">⭐ ${p.rating}</span>
          <span class="p-tag" style="background:#f1f5f9;color:var(--muted)"><i class="fas fa-map-pin" style="font-size:.6rem"></i> ${p.zona.split('/')[0].trim()}</span>
        </div>
      </div>
      <div class="p-stats">
        <div class="p-stat-row"><i class="fas fa-route"></i> <strong>${p.paseosMes}</strong>&nbsp;este mes</div>
        <div class="p-stat-row"><i class="fas fa-check"></i> <strong>${p.paseosTotales}</strong>&nbsp;total</div>
        <div class="p-stat-row"><i class="fas fa-users"></i> <strong>${p.clientes.length}</strong>&nbsp;clientes</div>
      </div>
      <div class="p-actions">
        <button class="p-action-btn chat" title="Ir al chat" onclick="irAlChat(${p.id},event)"><i class="fas fa-comment-alt"></i></button>
        <button class="p-action-btn map"  title="Ver en mapa" onclick="verEnMapa(${p.id},event)"><i class="fas fa-map-marker-alt"></i></button>
        <button class="p-action-btn route" title="Asignar ruta" onclick="abrirModalRuta(${p.id},event)"><i class="fas fa-route"></i></button>
      </div>
    `;
                card.addEventListener('click', () => seleccionar(p.id));
                lista.appendChild(card);
            });
        }

        // ══════════════════════════════════════════════════════════════
        // SELECCIONAR PASEADOR
        // ══════════════════════════════════════════════════════════════
        function seleccionar(id) {
            paseadorSel = PASEADORES.find(p => p.id === id);
            renderLista();
            renderDetalle();
            actualizarMapa();
        }

        // ══════════════════════════════════════════════════════════════
        // RENDER DETALLE
        // ══════════════════════════════════════════════════════════════
        function renderDetalle() {
            if (!paseadorSel) return;
            const p = paseadorSel;
            const dc = document.getElementById('detailCard');
            const initials = p.nombre.split(' ').map(x => x[0]).slice(0, 2).join('');
            const statusClass = { activo: 'dsb-activo', 'en-ruta': 'dsb-activo', pausado: 'dsb-pausado' }[p.estado] || 'dsb-pausado';
            const statusLabel = { activo: '● Activo', 'en-ruta': '◉ En Ruta', pausado: '◌ Pausado' }[p.estado] || p.estado;

            dc.innerHTML = `
    <div class="dc-head">
      <div class="dc-head-top">
        <div class="dc-avatar" style="background:${p.color}">${initials}</div>
        <span class="dc-status-badge ${statusClass}">${statusLabel}</span>
      </div>
      <div class="dc-name">${p.nombre}</div>
      <div class="dc-sub">${p.email} · ${p.telefono}</div>
    </div>
    <div class="dc-quick-actions">
      <button class="dqa-btn chat-btn" onclick="irAlChat(${p.id},event)">
        <i class="fas fa-comment-alt"></i> Chat
      </button>
      <button class="dqa-btn map-btn" onclick="verEnMapa(${p.id},event)">
        <i class="fas fa-map-marker-alt"></i> Mapa
      </button>
      <button class="dqa-btn ruta-btn" onclick="abrirModalRuta(${p.id},event)">
        <i class="fas fa-route"></i> Ruta
      </button>
    </div>
    <div class="dc-body">
      <div class="dc-info-row">
        <i class="fas fa-map-pin"></i>
        <div><div class="dci-label">Zona de trabajo</div><div class="dci-val">${p.zona}</div></div>
      </div>
      ${p.rutaActual ? `
      <div class="dc-info-row">
        <i class="fas fa-route"></i>
        <div><div class="dci-label">Ruta actual</div><div class="dci-val" style="color:var(--primary)">${p.rutaActual}</div></div>
      </div>` : ''}
      <div class="dc-info-row">
        <i class="fas fa-users"></i>
        <div><div class="dci-label">Clientes asignados</div><div class="dci-val">${p.clientes.join(' · ')}</div></div>
      </div>
      <div class="dc-perf">
        <div class="dcp-item"><div class="dcp-val">⭐${p.rating}</div><div class="dcp-lbl">Rating</div></div>
        <div class="dcp-item"><div class="dcp-val">${p.paseosMes}</div><div class="dcp-lbl">Este mes</div></div>
        <div class="dcp-item"><div class="dcp-val">${p.paseosTotales}</div><div class="dcp-lbl">Totales</div></div>
      </div>
      <button class="btn-primary" style="width:100%;justify-content:center" onclick="abrirModalRuta(${p.id},event)">
        <i class="fas fa-plus"></i> Asignar nueva ruta
      </button>
      <button class="btn-outline" style="width:100%;justify-content:center" onclick="showToast('Historial de ${p.nombre}','info')">
        <i class="fas fa-clock-rotate-left"></i> Ver historial completo
      </button>
    </div>
  `;
        }

        // ══════════════════════════════════════════════════════════════
        // MAPA LEAFLET
        // ══════════════════════════════════════════════════════════════
        function initMapa() {
            mapInstance = L.map('minimap', { zoomControl: true, attributionControl: false }).setView([7.8939, -72.5078], 14);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(mapInstance);

            // Marcadores de todos los paseadores
            PASEADORES.forEach(p => {
                const color = { activo: '#25D366', 'en-ruta': '#3E72A6', pausado: '#f97316' }[p.estado] || '#ccc';
                const icon = L.divIcon({
                    html: `<div style="background:${color};width:30px;height:30px;border-radius:50%;border:3px solid #fff;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:.65rem;box-shadow:0 2px 8px rgba(0,0,0,.3)">${p.nombre.split(' ')[0][0]}</div>`,
                    className: '', iconSize: [30, 30], iconAnchor: [15, 15]
                });
                const marker = L.marker([p.lat, p.lng], { icon }).addTo(mapInstance);
                marker.bindPopup(`<b>${p.nombre}</b><br>${p.estado === 'en-ruta' ? '📍 En ruta: ' + p.rutaActual : '📌 ' + p.zona}`);
                if (p.id === paseadorSel?.id) marker.openPopup();
            });
        }

        function actualizarMapa() {
            if (!mapInstance || !paseadorSel) return;
            mapInstance.setView([paseadorSel.lat, paseadorSel.lng], 15);
            document.getElementById('mapCoord').textContent = `${paseadorSel.nombre} — ${paseadorSel.zona.split('/')[0]}`;
        }

        // ══════════════════════════════════════════════════════════════
        // HISTORIAL RUTAS
        // ══════════════════════════════════════════════════════════════
        function renderHistorial() {
            const el = document.getElementById('rutasRecientes');
            el.innerHTML = '';
            const iconMap = { completada: 'rhi-green', pendiente: 'rhi-orange', 'en-ruta': 'rhi-blue', cancelada: 'rhi-red' };
            const faMap = { completada: 'fa-check', pendiente: 'fa-clock', 'en-ruta': 'fa-route', cancelada: 'fa-times' };

            PASEADORES.forEach(p => {
                p.rutasHistorial.forEach(r => {
                    const div = document.createElement('div');
                    div.className = 'rh-item';
                    div.innerHTML = `
        <div class="rhi-icon ${iconMap[r.estado] || 'rhi-blue'}"><i class="fas ${faMap[r.estado] || 'fa-route'}"></i></div>
        <div class="rhi-info">
          <div class="rhi-name">${p.nombre} → ${r.cliente}</div>
          <div class="rhi-sub"><i class="fas fa-dog" style="margin-right:3px"></i>${r.mascota} · ${r.fecha}</div>
        </div>
        <span class="rhi-status rs-${r.estado}">${capitalize(r.estado.replace('-', ' '))}</span>
      `;
                    el.appendChild(div);
                });
            });
        }

        // ══════════════════════════════════════════════════════════════
        // MODAL RUTA
        // ══════════════════════════════════════════════════════════════
        let paseadorParaRuta = null;

        function abrirModalRuta(id, e) {
            if (e) e.stopPropagation();
            paseadorParaRuta = PASEADORES.find(p => p.id === id);
            document.getElementById('modalTitle').textContent = `Asignar ruta a ${paseadorParaRuta.nombre}`;
            document.getElementById('modalSub').textContent = `Zona: ${paseadorParaRuta.zona}`;

            // Fecha por defecto: hoy
            const hoy = new Date().toISOString().split('T')[0];
            document.getElementById('routeDate').value = hoy;
            document.getElementById('routeTime').value = '08:00';
            document.getElementById('routeNotes').value = '';
            document.getElementById('routeMeet').value = '';

            selectedRouteClients = [];
            renderClientesModal();
            document.getElementById('routeModal').classList.add('open');
        }

        function renderClientesModal() {
            const list = document.getElementById('clientRouteList');
            list.innerHTML = '';
            CLIENTES_DISPONIBLES.forEach(c => {
                const sel = selectedRouteClients.includes(c.id);
                const div = document.createElement('div');
                div.className = 'client-route-item' + (sel ? ' selected' : '');
                div.innerHTML = `
      <div class="cri-avatar" style="background:${['#3E72A6', '#16a34a', '#ea580c', '#7c3aed', '#db2777'][c.id % 5]}">${c.nombre[0]}</div>
      <div class="cri-info">
        <div class="cri-name">${c.nombre} ${c.urgente ? '🔴' : ''}</div>
        <div class="cri-addr"><i class="fas fa-map-pin" style="font-size:.6rem"></i>${c.addr}</div>
        <div class="cri-meta">
          <span class="cri-tag">🐕 ${c.mascota}</span>
          <span class="cri-tag">📍 ${c.distancia}</span>
          ${c.urgente ? '<span class="cri-tag" style="background:#fee2e2;color:#b91c1c;border-color:#fca5a5">⚡ Urgente</span>' : ''}
        </div>
      </div>
      <div class="cri-check"><i class="fas fa-check"></i></div>
    `;
                div.addEventListener('click', () => {
                    if (selectedRouteClients.includes(c.id))
                        selectedRouteClients = selectedRouteClients.filter(x => x !== c.id);
                    else
                        selectedRouteClients.push(c.id);
                    renderClientesModal();
                });
                list.appendChild(div);
            });
        }

        // Prioridad
        document.querySelectorAll('.prio-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.prio-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                selectedPriority = btn.dataset.prio;
            });
        });

        document.getElementById('closeRouteModal').addEventListener('click', () =>
            document.getElementById('routeModal').classList.remove('open'));
        document.getElementById('cancelRoute').addEventListener('click', () =>
            document.getElementById('routeModal').classList.remove('open'));
        document.getElementById('routeModal').addEventListener('click', e => {
            if (e.target === document.getElementById('routeModal'))
                document.getElementById('routeModal').classList.remove('open');
        });

        document.getElementById('confirmRoute').addEventListener('click', () => {
            if (selectedRouteClients.length === 0) {
                showToast('Selecciona al menos un cliente', 'warning'); return;
            }
            const fecha = document.getElementById('routeDate').value;
            const hora = document.getElementById('routeTime').value;
            const nombres = selectedRouteClients.map(id => CLIENTES_DISPONIBLES.find(c => c.id === id)?.nombre).join(', ');

            // Simular asignación
            if (paseadorParaRuta) {
                paseadorParaRuta.rutaActual = `${nombres} — ${fecha} ${hora}`;
                paseadorParaRuta.estado = 'en-ruta';
                selectedRouteClients.forEach(cid => {
                    const c = CLIENTES_DISPONIBLES.find(x => x.id === cid);
                    if (c && !paseadorParaRuta.clientes.includes(c.nombre))
                        paseadorParaRuta.clientes.push(c.nombre);
                });
            }

            document.getElementById('routeModal').classList.remove('open');
            showToast(`Ruta asignada a ${paseadorParaRuta?.nombre} ✓`, 'success');
            renderLista();
            renderDetalle();
            updateStats();
        });

        // ══════════════════════════════════════════════════════════════
        // ACCIONES RÁPIDAS
        // ══════════════════════════════════════════════════════════════
        function irAlChat(id, e) {
            if (e) e.stopPropagation();
            const p = PASEADORES.find(x => x.id === id);
            showToast(`Abriendo chat con ${p?.nombre}...`, 'info');
            setTimeout(() => window.location.href = `Chat_admin.html`, 1200);
        }

        function verEnMapa(id, e) {
            if (e) e.stopPropagation();
            const p = PASEADORES.find(x => x.id === id);
            seleccionar(id);
            showToast(`Mostrando ubicación de ${p?.nombre}`, 'info');
            document.getElementById('minimap').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        // ══════════════════════════════════════════════════════════════
        // FILTROS Y BÚSQUEDA
        // ══════════════════════════════════════════════════════════════
        document.querySelectorAll('.sf-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.sf-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                filtroActual = btn.dataset.filter;
                renderLista();
            });
        });
        document.getElementById('searchInput').addEventListener('input', renderLista);

        // ══════════════════════════════════════════════════════════════
        // HELPERS
        // ══════════════════════════════════════════════════════════════
        function capitalize(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

        let toastTimer;
        function showToast(msg, type = 'success') {
            const t = document.getElementById('toast');
            const ic = t.querySelector('i');
            document.getElementById('toastMsg').textContent = msg;
            t.className = `toast ${type}`;
            ic.className = { success: 'fas fa-check-circle', info: 'fas fa-info-circle', warning: 'fas fa-triangle-exclamation' }[type] || 'fas fa-check-circle';
            t.classList.add('show');
            clearTimeout(toastTimer);
            toastTimer = setTimeout(() => t.classList.remove('show'), 3200);
        }

        // ══════════════════════════════════════════════════════════════
        // SIMULACIÓN GPS: mover marcadores cada 5s
        // ══════════════════════════════════════════════════════════════
        function simularMovimiento() {
            PASEADORES.filter(p => p.estado === 'en-ruta').forEach(p => {
                p.lat += (Math.random() - .5) * .0008;
                p.lng += (Math.random() - .5) * .0008;
            });
            if (paseadorSel) actualizarMapa();
        }

        // ══════════════════════════════════════════════════════════════
        // INIT
        // ══════════════════════════════════════════════════════════════
        updateStats();
        renderLista();
        renderDetalle();
        renderHistorial();

        setTimeout(() => {
            initMapa();
            setInterval(simularMovimiento, 5000);
        }, 300);