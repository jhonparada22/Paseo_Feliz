
        // ═══════════════════════════════════════════════════════════════
        // DATOS SIMULADOS
        // ═══════════════════════════════════════════════════════════════
        const PASEADORES = [
            {
                id: 1, nombre: 'Carlos Rodríguez', zona: 'Cúcuta Centro',
                estado: 'activo', lat: 7.8939, lng: -72.5078, color: '#3E72A6',
                rating: 4.8, paseosMes: 18, telefono: '315 778 8990',
                rutaActual: null
            },
            {
                id: 2, nombre: 'Ana Fernández', zona: 'Cúcuta Norte',
                estado: 'en-ruta', lat: 7.9050, lng: -72.5020, color: '#16a34a',
                rating: 4.9, paseosMes: 14, telefono: '312 334 5566',
                rutaActual: 'Calle 7 #0e-94 → Parque Santander'
            },
            {
                id: 3, nombre: 'Diego Torres', zona: 'Cúcuta Sur',
                estado: 'pausado', lat: 7.8820, lng: -72.5130, color: '#ea580c',
                rating: 4.5, paseosMes: 8, telefono: '318 445 6677',
                rutaActual: null
            },
            {
                id: 4, nombre: 'Sofía Lozano', zona: 'Villa del Rosario',
                estado: 'activo', lat: 7.8680, lng: -72.4790, color: '#7c3aed',
                rating: 5.0, paseosMes: 24, telefono: '317 456 7890',
                rutaActual: null
            },
        ];

        const RUTAS_HOY = [
            { paseador: 'Ana Fernández', puntos: ['Casa María González', 'Parque Santander', 'Casa Pedro Ramírez'], hora: '08:00', estado: 'en-ruta' },
            { paseador: 'Carlos Rodríguez', puntos: ['Casa Laura Martínez', 'Parque La Flora'], hora: '10:00', estado: 'pendiente' },
        ];

        // ═══════════════════════════════════════════════════════════════
        // ESTADO
        // ═══════════════════════════════════════════════════════════════
        let map, routeLayer, paseadoresLayer;
        let modoActual = 'view';
        let puntosRuta = [];         // [{lat, lng, label, addr}]
        let paseadorSelId = null;
        let paseadoresMarkers = {};
        let routePolyline = null;
        const LABELS = ['A', 'B', 'C', 'D', 'E'];
        const DOT_COLORS = ['#ef4444', '#f97316', '#8b5cf6', '#0ea5e9', '#10b981'];

        // ═══════════════════════════════════════════════════════════════
        // INIT MAPA
        // ═══════════════════════════════════════════════════════════════
        function initMap() {
            // Centro en Cúcuta
            map = L.map('map', { zoomControl: true, attributionControl: false })
                .setView([7.8939, -72.5078], 14);

            // Capa base OpenStreetMap (gratuita, sin API key)
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap',
                maxZoom: 19,
            }).addTo(map);

            routeLayer = L.layerGroup().addTo(map);
            paseadoresLayer = L.layerGroup().addTo(map);

            // Clic en mapa → agregar punto de ruta
            map.on('click', e => {
                if (modoActual === 'route') {
                    agregarPunto(e.latlng.lat, e.latlng.lng, 'Punto seleccionado en mapa');
                }
            });

            renderPaseadoresMarkers();
            setInterval(simularGPS, 5000);
        }

        // ═══════════════════════════════════════════════════════════════
        // MARCADORES PASEADORES
        // ═══════════════════════════════════════════════════════════════
        function renderPaseadoresMarkers() {
            paseadoresLayer.clearLayers();
            PASEADORES.forEach(p => {
                const dotColor = { activo: '#25D366', 'en-ruta': '#f97316', pausado: '#ef4444' }[p.estado] || '#ccc';
                const initials = p.nombre.split(' ').map(x => x[0]).slice(0, 2).join('');
                const icon = L.divIcon({
                    html: `<div style="
        background:${p.color};width:38px;height:38px;border-radius:12px;
        border:3px solid #fff;
        display:flex;align-items:center;justify-content:center;
        color:#fff;font-weight:800;font-size:.72rem;
        box-shadow:0 3px 10px rgba(0,0,0,.25);
        position:relative;cursor:pointer;
      ">
        ${initials}
        <div style="
          position:absolute;bottom:-3px;right:-3px;
          width:12px;height:12px;border-radius:50%;
          background:${dotColor};border:2px solid #fff;
        "></div>
      </div>`,
                    className: '', iconSize: [38, 38], iconAnchor: [19, 19],
                });

                const marker = L.marker([p.lat, p.lng], { icon })
                    .addTo(paseadoresLayer)
                    .on('click', () => seleccionarPaseador(p.id));

                // Tooltip con nombre
                marker.bindTooltip(`<b>${p.nombre}</b><br>${p.zona}`, {
                    direction: 'top', offset: [0, -20],
                    className: 'custom-marker-label',
                });

                paseadoresMarkers[p.id] = marker;
            });
        }

        // ═══════════════════════════════════════════════════════════════
        // SIMULAR GPS (mueve paseadores "en ruta")
        // ═══════════════════════════════════════════════════════════════
        function simularGPS() {
            PASEADORES.filter(p => p.estado === 'en-ruta').forEach(p => {
                p.lat += (Math.random() - .5) * .0006;
                p.lng += (Math.random() - .5) * .0006;
                if (paseadoresMarkers[p.id]) {
                    paseadoresMarkers[p.id].setLatLng([p.lat, p.lng]);
                }
                // Si es el seleccionado, actualizar panel derecho
                if (paseadorSelId === p.id) {
                    document.getElementById('rp-sub').textContent =
                        `📍 ${p.lat.toFixed(5)}, ${p.lng.toFixed(5)} · actualizado ahora`;
                }
            });
            // Re-renderizar lista
            renderPaseadoresList();
        }

        // ═══════════════════════════════════════════════════════════════
        // AGREGAR PUNTO DE RUTA
        // ═══════════════════════════════════════════════════════════════
        function agregarPunto(lat, lng, addr) {
            if (puntosRuta.length >= 5) {
                showToast('Máximo 5 puntos por ruta', 'warning'); return;
            }
            const idx = puntosRuta.length;
            const label = LABELS[idx];
            const color = DOT_COLORS[idx];

            puntosRuta.push({ lat, lng, label, addr, color });
            renderRouteSteps();
            renderRouteOnMap();
            showToast(`Punto ${label} agregado`, 'success');
        }

        // ═══════════════════════════════════════════════════════════════
        // RENDER PASOS DE RUTA (panel izquierdo)
        // ═══════════════════════════════════════════════════════════════
        function renderRouteSteps() {
            const el = document.getElementById('routeSteps');
            el.innerHTML = '';
            puntosRuta.forEach((p, i) => {
                if (i > 0) {
                    const sep = document.createElement('div');
                    sep.className = 'step-connector'; el.appendChild(sep);
                }
                const step = document.createElement('div');
                step.className = 'route-step';
                step.innerHTML = `
      <div class="step-dot dot-${p.label.toLowerCase()}" style="background:${p.color}">${p.label}</div>
      <div class="step-info">
        <div class="step-label">Punto ${p.label}</div>
        <div class="step-addr">${p.addr}</div>
        <div class="step-dist">📍 ${p.lat.toFixed(5)}, ${p.lng.toFixed(5)}</div>
      </div>
      <button class="step-remove" onclick="eliminarPunto(${i})"><i class="fas fa-times"></i></button>
    `;
                el.appendChild(step);
            });
        }

        // ═══════════════════════════════════════════════════════════════
        // RENDER RUTA EN MAPA
        // ═══════════════════════════════════════════════════════════════
        function renderRouteOnMap() {
            routeLayer.clearLayers();
            if (puntosRuta.length === 0) return;

            const coords = puntosRuta.map(p => [p.lat, p.lng]);

            // Línea de ruta
            routePolyline = L.polyline(coords, {
                color: var_primary, weight: 4, opacity: .8,
                dashArray: '8 4',
            }).addTo(routeLayer);

            // Marcadores de puntos
            puntosRuta.forEach((p, i) => {
                const icon = L.divIcon({
                    html: `<div style="
        background:${p.color};width:34px;height:34px;border-radius:50%;
        border:3px solid #fff;
        display:flex;align-items:center;justify-content:center;
        color:#fff;font-weight:800;font-size:.85rem;
        box-shadow:0 3px 10px rgba(0,0,0,.3);
      ">${p.label}</div>`,
                    className: '', iconSize: [34, 34], iconAnchor: [17, 17],
                });
                L.marker([p.lat, p.lng], { icon })
                    .addTo(routeLayer)
                    .bindPopup(`<b>Punto ${p.label}</b><br>${p.addr}`);
            });

            // Fit bounds
            if (puntosRuta.length > 1) map.fitBounds(routePolyline.getBounds(), { padding: [40, 40] });
            else map.setView([puntosRuta[0].lat, puntosRuta[0].lng], 16);
        }

        const var_primary = '#3E72A6';

        // ═══════════════════════════════════════════════════════════════
        // ELIMINAR PUNTO
        // ═══════════════════════════════════════════════════════════════
        function eliminarPunto(idx) {
            puntosRuta.splice(idx, 1);
            // Re-etiquetar
            puntosRuta.forEach((p, i) => {
                p.label = LABELS[i];
                p.color = DOT_COLORS[i];
            });
            renderRouteSteps();
            renderRouteOnMap();
        }

        // ═══════════════════════════════════════════════════════════════
        // GEOCODER SIMULADO (OpenStreetMap Nominatim)
        // ═══════════════════════════════════════════════════════════════
        let geocoderTimer;
        const FAKE_RESULTS = [
            { name: 'Calle 7 #0e-94', addr: 'Motilones, Cúcuta', lat: 7.8928, lng: -72.5065 },
            { name: 'Parque Santander', addr: 'Centro, Cúcuta', lat: 7.8950, lng: -72.5040 },
            { name: 'Av. Libertadores #10-15', addr: 'Atalaya, Cúcuta', lat: 7.9010, lng: -72.5090 },
            { name: 'Carrera 5 #12-20', addr: 'Los Patios, Norte de Santander', lat: 7.8800, lng: -72.4950 },
            { name: 'Parque La Flora', addr: 'Cúcuta Norte', lat: 7.9100, lng: -72.5000 },
            { name: 'Urb. La Riviera', addr: 'Cúcuta Norte', lat: 7.9050, lng: -72.5060 },
            { name: 'Calle 10 #3-15', addr: 'Villa del Rosario', lat: 7.8650, lng: -72.4750 },
            { name: 'Av. 0 #5-35', addr: 'Centro, Cúcuta', lat: 7.8960, lng: -72.5020 },
        ];

        document.getElementById('addrSearch').addEventListener('input', function () {
            clearTimeout(geocoderTimer);
            const q = this.value.trim().toLowerCase();
            const res = document.getElementById('addrResults');
            if (!q || q.length < 2) { res.style.display = 'none'; return; }

            geocoderTimer = setTimeout(() => {
                const matches = FAKE_RESULTS.filter(r =>
                    r.name.toLowerCase().includes(q) || r.addr.toLowerCase().includes(q)
                );
                if (matches.length === 0) { res.style.display = 'none'; return; }
                res.style.display = 'block';
                res.innerHTML = matches.slice(0, 4).map(r => `
      <div class="geo-result" onclick="selectGeoResult(${r.lat},${r.lng},'${r.name}, ${r.addr}')">
        <div class="gr-name">${r.name}</div>
        <div class="gr-addr">${r.addr}</div>
      </div>
    `).join('');
            }, 300);
        });

        function selectGeoResult(lat, lng, addr) {
            document.getElementById('addrSearch').value = '';
            document.getElementById('addrResults').style.display = 'none';
            agregarPunto(lat, lng, addr);
            map.setView([lat, lng], 16);
        }

        // ═══════════════════════════════════════════════════════════════
        // MODOS
        // ═══════════════════════════════════════════════════════════════
        function setMode(mode) {
            modoActual = mode;
            document.getElementById('modeView').classList.toggle('active-mode', mode === 'view');
            document.getElementById('modeRoute').classList.toggle('active-mode', mode === 'route');
            map.getContainer().style.cursor = mode === 'route' ? 'crosshair' : '';
            if (mode === 'route')
                showToast('Modo ruta: haz clic en el mapa para agregar puntos', 'info');
        }

        function centerCucuta() {
            map.setView([7.8939, -72.5078], 14);
        }

        let trafficLayer = null;
        function toggleTraffic() {
            if (trafficLayer) {
                map.removeLayer(trafficLayer); trafficLayer = null;
                showToast('Capa de tráfico desactivada', 'info');
            } else {
                // OpenStreetMap no tiene capa de tráfico nativa gratuita.
                // Aquí podrías integrar HERE Maps (plan gratuito) o Mapbox gratuito.
                showToast('Tráfico: conecta HERE Maps para ver tráfico en vivo (gratuito)', 'info');
            }
        }

        // ═══════════════════════════════════════════════════════════════
        // SELECCIONAR PASEADOR
        // ═══════════════════════════════════════════════════════════════
        function seleccionarPaseador(id) {
            paseadorSelId = id;
            const p = PASEADORES.find(x => x.id === id);
            if (!p) return;

            // Panel izquierdo: autoseleccionar en select
            document.getElementById('selectPaseador').value = id;

            // Panel derecho
            document.getElementById('rp-name').textContent = p.nombre;
            document.getElementById('rp-sub').textContent = `📍 ${p.lat.toFixed(5)}, ${p.lng.toFixed(5)}`;

            const statusMap = { activo: '✅ Activo', 'en-ruta': '🔵 En ruta', pausado: '⏸️ Pausado' };
            const body = document.getElementById('rp-body');
            body.innerHTML = `
    <div class="rp-stat">
      <div class="rs-label">Estado</div>
      <div class="rs-val">${statusMap[p.estado] || p.estado}</div>
    </div>
    <div class="rp-stat">
      <div class="rs-label">Zona</div>
      <div class="rs-val">${p.zona}</div>
    </div>
    <div class="rp-stat">
      <div class="rs-label">Teléfono</div>
      <div class="rs-val">${p.telefono}</div>
    </div>
    <div class="rp-stat">
      <div class="rs-label">Paseos este mes</div>
      <div class="rs-val">${p.paseosMes} paseos · ⭐ ${p.rating}</div>
    </div>
    ${p.rutaActual ? `
    <div class="rp-route-visual">
      <div class="rv-title">Ruta actual</div>
      <div style="font-size:.78rem;font-weight:600;color:var(--primary)">${p.rutaActual}</div>
    </div>` : `
    <div class="rp-route-visual">
      <div class="rv-title">Sin ruta asignada</div>
      <p style="font-size:.75rem;color:var(--muted)">Usa el panel izquierdo para asignarle una ruta.</p>
    </div>`}
    <button class="btn-primary" style="font-size:.77rem" onclick="showToast('Abriendo chat con ${p.nombre}...','info')">
      <i class="fas fa-comment-alt"></i> Enviar mensaje
    </button>
    <button style="
      display:flex;align-items:center;gap:6px;width:100%;justify-content:center;
      padding:8px 14px;border-radius:10px;border:1.5px solid var(--border);
      background:#fff;color:var(--text);font-size:.77rem;font-weight:700;
      transition:border .15s;cursor:pointer;margin-top:2px;
    " onmouseover="this.style.borderColor='var(--primary)'"
       onmouseout="this.style.borderColor='var(--border)'"
       onclick="map.setView([${p.lat},${p.lng}],17)">
      <i class="fas fa-location-crosshairs"></i> Centrar en mapa
    </button>
  `;

            // Centrar mapa
            map.setView([p.lat, p.lng], 16);
            renderPaseadoresList();
        }

        // ═══════════════════════════════════════════════════════════════
        // RENDER LISTA PASEADORES (tab)
        // ═══════════════════════════════════════════════════════════════
        function renderPaseadoresList() {
            const el = document.getElementById('paseadoresList');
            if (!el) return;
            el.innerHTML = '';
            PASEADORES.forEach(p => {
                const dotColor = { activo: 'dot-on', 'en-ruta': 'dot-ruta', pausado: 'dot-off' }[p.estado] || 'dot-off';
                const tag = { activo: 'Activo', 'en-ruta': 'En ruta', pausado: 'Pausado' }[p.estado];
                const tagCls = { activo: 'tag-activo', 'en-ruta': 'tag-ruta', pausado: 'tag-sin' }[p.estado];
                const initials = p.nombre.split(' ').map(x => x[0]).slice(0, 2).join('');
                const div = document.createElement('div');
                div.className = `paseador-item${paseadorSelId === p.id ? ' active-p' : ''}`;
                div.innerHTML = `
      <div class="p-av" style="background:${p.color}">
        ${initials}
        <div class="p-dot ${dotColor}"></div>
      </div>
      <div class="p-info">
        <div class="p-name">${p.nombre}</div>
        <div class="p-status">${p.zona} · ⭐${p.rating}</div>
      </div>
      <span class="p-tag ${tagCls}">${tag}</span>
    `;
                div.addEventListener('click', () => seleccionarPaseador(p.id));
                el.appendChild(div);
            });
        }

        // ═══════════════════════════════════════════════════════════════
        // RENDER RUTAS ACTIVAS HOY
        // ═══════════════════════════════════════════════════════════════
        function renderRutasHoy() {
            const el = document.getElementById('activeRoutesList');
            el.innerHTML = '';
            RUTAS_HOY.forEach(r => {
                const statusCls = r.estado === 'en-ruta'
                    ? 'background:#dbeafe;color:#1d4ed8'
                    : 'background:#f1f5f9;color:#475569';
                const div = document.createElement('div');
                div.style.cssText = 'background:var(--bg);border-radius:10px;padding:10px 12px;margin-bottom:7px;font-size:.78rem';
                div.innerHTML = `
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
        <span style="font-weight:700">${r.paseador}</span>
        <span style="padding:2px 8px;border-radius:20px;font-size:.65rem;font-weight:700;${statusCls}">
          ${r.estado === 'en-ruta' ? '🔵 En ruta' : '⏰ Pendiente'}
        </span>
      </div>
      <div style="color:var(--muted);display:flex;align-items:center;gap:5px;margin-bottom:4px">
        <i class="fas fa-clock" style="font-size:.65rem"></i> ${r.hora}
      </div>
      ${r.puntos.map((pt, i) => `
        <div style="display:flex;align-items:center;gap:6px;margin-top:3px">
          <div style="width:16px;height:16px;border-radius:50%;background:${DOT_COLORS[i] || '#ccc'};
            display:flex;align-items:center;justify-content:center;
            color:#fff;font-size:.55rem;font-weight:800;flex-shrink:0">${LABELS[i]}</div>
          <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${pt}</span>
        </div>
      `).join('')}
    `;
                el.appendChild(div);
            });
        }

        // ═══════════════════════════════════════════════════════════════
        // ASIGNAR RUTA
        // ═══════════════════════════════════════════════════════════════
        document.getElementById('btnAsignar').addEventListener('click', () => {
            const sel = document.getElementById('selectPaseador').value;
            if (!sel) { showToast('Selecciona un paseador primero', 'warning'); return; }
            if (puntosRuta.length < 2) { showToast('Agrega al menos 2 puntos a la ruta', 'warning'); return; }

            const paseador = PASEADORES.find(p => p.id == sel);
            const fecha = document.getElementById('routeDate').value || 'Hoy';
            const hora = document.getElementById('routeTime').value || '08:00';

            const summary = document.getElementById('assignSummary');
            summary.innerHTML = `
    <div><i class="fas fa-person-walking" style="color:var(--primary)"></i><span>Paseador:</span> ${paseador.nombre}</div>
    <div><i class="fas fa-calendar" style="color:var(--primary)"></i><span>Fecha:</span> ${fecha} a las ${hora}</div>
    <div><i class="fas fa-route" style="color:var(--primary)"></i><span>Paradas:</span> ${puntosRuta.length} puntos</div>
    ${puntosRuta.map(p => `<div style="padding-left:16px;font-size:.75rem;color:var(--muted)">
      <span style="color:${p.color};font-weight:800">${p.label}.</span> ${p.addr}
    </div>`).join('')}
  `;
            document.getElementById('assignModal').classList.add('open');
        });

        document.getElementById('cancelAssign').addEventListener('click', () =>
            document.getElementById('assignModal').classList.remove('open'));

        document.getElementById('confirmAssign').addEventListener('click', () => {
            const sel = document.getElementById('selectPaseador').value;
            const paseador = PASEADORES.find(p => p.id == sel);
            const hora = document.getElementById('routeTime').value || '08:00';

            // Simular guardado
            paseador.rutaActual = puntosRuta.map(p => p.addr).join(' → ');
            paseador.estado = 'en-ruta';
            RUTAS_HOY.push({
                paseador: paseador.nombre,
                puntos: puntosRuta.map(p => p.addr),
                hora, estado: 'pendiente',
            });

            document.getElementById('assignModal').classList.remove('open');
            showToast(`✅ Ruta asignada a ${paseador.nombre}`, 'success');
            renderPaseadoresMarkers();
            renderRutasHoy();
            renderPaseadoresList();

            // AQUÍ en producción: fetch al backend PHP
            // fetch('../model/php/guardar_ruta.php', {
            //   method:'POST',
            //   body: JSON.stringify({
            //     id_paseador: sel,
            //     puntos: puntosRuta,
            //     fecha: document.getElementById('routeDate').value,
            //     hora: hora
            //   }),
            //   headers: {'Content-Type':'application/json'}
            // });
        });

        // ═══════════════════════════════════════════════════════════════
        // LIMPIAR RUTA
        // ═══════════════════════════════════════════════════════════════
        document.getElementById('btnLimpiar').addEventListener('click', () => {
            puntosRuta = [];
            renderRouteSteps();
            routeLayer.clearLayers();
            showToast('Ruta limpiada', 'info');
        });

        document.getElementById('btnAddPoint').addEventListener('click', () => {
            setMode('route');
        });

        // ═══════════════════════════════════════════════════════════════
        // TABS PANEL IZQUIERDO
        // ═══════════════════════════════════════════════════════════════
        document.querySelectorAll('.panel-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.panel-tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                document.getElementById('tab-rutas').style.display = tab.dataset.tab === 'rutas' ? 'block' : 'none';
                document.getElementById('tab-paseadores').style.display = tab.dataset.tab === 'paseadores' ? 'block' : 'none';
                if (tab.dataset.tab === 'paseadores') renderPaseadoresList();
            });
        });

        // ═══════════════════════════════════════════════════════════════
        // SIDEBAR
        // ═══════════════════════════════════════════════════════════════
        document.getElementById('btn-menu').addEventListener('click', () =>
            document.getElementById('menu-latente').classList.toggle('show'));
        window.addEventListener('click', e => {
            const btn = document.getElementById('btn-menu');
            const menu = document.getElementById('menu-latente');
            if (!btn.contains(e.target) && !menu.contains(e.target)) menu.classList.remove('show');
        });

        // ═══════════════════════════════════════════════════════════════
        // TOAST
        // ═══════════════════════════════════════════════════════════════
        let toastTimer;
        function showToast(msg, type = 'success') {
            const t = document.getElementById('toast');
            const ic = t.querySelector('i');
            document.getElementById('toastMsg').textContent = msg;
            t.className = `toast ${type}`;
            ic.className = {
                success: 'fas fa-check-circle',
                info: 'fas fa-info-circle',
                warning: 'fas fa-triangle-exclamation',
            }[type] || 'fas fa-check-circle';
            t.classList.add('show');
            clearTimeout(toastTimer);
            toastTimer = setTimeout(() => t.classList.remove('show'), 3500);
        }

        // ═══════════════════════════════════════════════════════════════
        // INIT
        // ═══════════════════════════════════════════════════════════════
        // Fecha por defecto: hoy
        document.getElementById('routeDate').value = new Date().toISOString().split('T')[0];

        initMap();
        renderPaseadoresList();
        renderRutasHoy();