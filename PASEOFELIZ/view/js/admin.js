
    
        // ── Menú hamburguesa ──────────────────────────────────────────
        const btnMenu = document.getElementById('btn-menu');
        const menuLatente = document.getElementById('menu-latente');
        btnMenu.addEventListener('click', () => menuLatente.classList.toggle('show'));
        window.addEventListener('click', e => {
            if (!btnMenu.contains(e.target) && !menuLatente.contains(e.target))
                menuLatente.classList.remove('show');
        });

        // ── Fecha actual ──────────────────────────────────────────────
        document.getElementById('dateLabel').textContent =
            new Date().toLocaleDateString('es-ES', { day: 'numeric', month: 'long', year: 'numeric' });

        // ── Gráfico de línea ─────────────────────────────────────────
        const lCtx = document.getElementById('lineChart').getContext('2d');
        const grad = lCtx.createLinearGradient(0, 0, 0, 190);
        grad.addColorStop(0, 'rgba(62,114,166,.3)');
        grad.addColorStop(1, 'rgba(62,114,166,0)');
        new Chart(lCtx, {
            type: 'line',
            data: {
                labels: ['19 May', '20 May', '21 May', '22 May', '23 May', '24 May', '25 May'],
                datasets: [{
                    data: [22, 28, 24, 32, 30, 38, 33],
                    borderColor: '#3E72A6', backgroundColor: grad,
                    borderWidth: 2.5, tension: 0.4, fill: true,
                    pointBackgroundColor: '#3E72A6', pointBorderColor: '#fff',
                    pointBorderWidth: 2, pointRadius: 4, pointHoverRadius: 6,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 10 }, color: '#94a3b8' } },
                    y: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 10 }, color: '#94a3b8', stepSize: 10 }, beginAtZero: true, max: 50 }
                }
            }
        });

        // ── Gráfico donut ─────────────────────────────────────────────
        new Chart(document.getElementById('donutChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [58, 25, 12, 5],
                    backgroundColor: ['#22c55e', '#3E72A6', '#f97316', '#ef4444'],
                    borderWidth: 0, hoverOffset: 6,
                }]
            },
            options: {
                cutout: '72%', responsive: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: ctx => ` ${ctx.raw}%` } }
                }
            }
        });

        // ── Calendario ────────────────────────────────────────────────
        let calYear, calMonth;
        function buildCalendar(year, month) {
            const grid = document.getElementById('calGrid');
            const heading = document.getElementById('calMonth');
            const days = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
            const today = new Date();
            const eventDs = [5, 12, 18, 25];
            grid.innerHTML = '';
            heading.textContent = new Date(year, month)
                .toLocaleDateString('es-ES', { month: 'long', year: 'numeric' })
                .replace(/^\w/, c => c.toUpperCase());
            days.forEach(d => {
                const el = document.createElement('div');
                el.className = 'cal-dname'; el.textContent = d; grid.appendChild(el);
            });
            const first = new Date(year, month, 1).getDay();
            const off = first === 0 ? 6 : first - 1;
            const prev = new Date(year, month, 0).getDate();
            const dim = new Date(year, month + 1, 0).getDate();
            for (let i = off - 1; i >= 0; i--) {
                const el = document.createElement('div'); el.className = 'cal-day other';
                el.textContent = prev - i; grid.appendChild(el);
            }
            for (let d = 1; d <= dim; d++) {
                const el = document.createElement('div'); el.className = 'cal-day';
                if (d === today.getDate() && month === today.getMonth() && year === today.getFullYear())
                    el.classList.add('today');
                if (eventDs.includes(d) && !el.classList.contains('today'))
                    el.classList.add('has-event');
                el.textContent = d; grid.appendChild(el);
            }
            const rem = 42 - (off + dim);
            for (let d = 1; d <= rem; d++) {
                const el = document.createElement('div'); el.className = 'cal-day other';
                el.textContent = d; grid.appendChild(el);
            }
        }
        const now = new Date();
        calYear = now.getFullYear(); calMonth = now.getMonth();
        buildCalendar(calYear, calMonth);
        document.getElementById('calPrev').addEventListener('click', () => {
            if (--calMonth < 0) { calMonth = 11; calYear--; }
            buildCalendar(calYear, calMonth);
        });
        document.getElementById('calNext').addEventListener('click', () => {
            if (++calMonth > 11) { calMonth = 0; calYear++; }
            buildCalendar(calYear, calMonth);
        });

        // ── Tabla paseos ──────────────────────────────────────────────
        const walks = [
            { id: '#PA-1254', user: 'María González', pet: 'Max', walker: 'Carlos R.', date: '25 May, 10:00 AM', cls: 'b-proceso', lbl: 'En Proceso' },
            { id: '#PA-1253', user: 'Juan Pérez', pet: 'Luna', walker: 'Ana M.', date: '25 May, 09:30 AM', cls: 'b-completado', lbl: 'Completado' },
            { id: '#PA-1252', user: 'Laura Martínez', pet: 'Rocky', walker: 'Diego T.', date: '25 May, 08:00 AM', cls: 'b-programado', lbl: 'Programado' },
            { id: '#PA-1251', user: 'Pedro Ramírez', pet: 'Duki', walker: 'Sofía L.', date: '24 May, 06:00 PM', cls: 'b-completado', lbl: 'Completado' },
            { id: '#PA-1250', user: 'Claudia López', pet: 'Toby', walker: 'Carlos R.', date: '24 May, 04:30 PM', cls: 'b-cancelado', lbl: 'Cancelado' },
        ];
        const tbody = document.getElementById('tableBody');
        walks.forEach(w => {
            const ui = w.user.split(' ').map(p => p[0]).slice(0, 2).join('');
            const pi = w.pet[0];
            const wi = w.walker.split(' ').map(p => p[0]).slice(0, 2).join('');
            tbody.innerHTML += `
    <tr>
      <td class="id-cell">${w.id}</td>
      <td><div class="cell-flex"><div class="mini-av">${ui}</div>${w.user}</div></td>
      <td><div class="cell-flex"><div class="mini-av pet">${pi}</div>${w.pet}</div></td>
      <td><div class="cell-flex"><div class="mini-av walker">${wi}</div>${w.walker}</div></td>
      <td>${w.date}</td>
      <td><span class="badge-st ${w.cls}">${w.lbl}</span></td>
      <td><button class="dots-btn"><i class="fas fa-ellipsis-vertical"></i></button></td>
    </tr>`;
        });