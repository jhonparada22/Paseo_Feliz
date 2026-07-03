// ══════════════════════════════════════════════════════════════
// pagos.js  –  Vista CLIENTE
// Ubicación: view/js/js pagina principal/pagos.js
// inicio.php está en: view/pagina_principal/
// ══════════════════════════════════════════════════════════════

const PATHS_PAGOS = {
  registrarPago: '../../controller/registrar_pago.php',
};

// Precios exactos según lo que muestra inicio.php — NO modificables
const PRECIOS = {
  paseos:         18000,
  adiestramiento: 22000,
  hospedaje:      28000,
};

const LABELS = {
  paseos:         'Paseos',
  adiestramiento: 'Adiestramiento canino',
  hospedaje:      'Hospedaje canino',
};

const ICONOS = {
  paseos:         '🐶',
  adiestramiento: '🎓',
  hospedaje:      '🏠',
};

// ── Inyectar modal en el DOM ──────────────────────────────────
(function inyectarModal() {
  const overlay = document.createElement('div');
  overlay.id        = 'pagoOverlay';
  overlay.className = 'pago-overlay';
  overlay.innerHTML = `
    <div class="pago-modal" id="pagoModal">
      <button class="pago-close" id="pagoClose"><i class="ph ph-x"></i></button>

      <div class="pm-header">
        <div class="pm-icon" id="pmIcon">🐶</div>
        <div>
          <h3 class="pm-title" id="pmTitle">Membresía Paseos</h3>
          <p class="pm-sub">Vigencia: 30 días desde hoy</p>
        </div>
      </div>

      <div class="pm-precio">
        <span class="pm-desde">Total a pagar</span>
        <span class="pm-monto" id="pmMonto">$18.000</span>
        <span class="pm-cop">COP</span>
      </div>

      <div class="pm-metodo">
        <label>Método de pago</label>
        <div class="pm-metodos-grid">
          <label class="pm-met-op">
            <input type="radio" name="pm_metodo" value="nequi" checked>
            <span>Nequi</span>
          </label>
          <label class="pm-met-op">
            <input type="radio" name="pm_metodo" value="daviplata">
            <span>Daviplata</span>
          </label>
          <label class="pm-met-op">
            <input type="radio" name="pm_metodo" value="transferencia">
            <span>Transferencia</span>
          </label>
          <label class="pm-met-op">
            <input type="radio" name="pm_metodo" value="efectivo">
            <span>Efectivo</span>
          </label>
        </div>
      </div>

      <p class="pm-aviso">
        <i class="ph ph-info"></i>
        Se cobrará exactamente <strong id="pmMontoAviso">$18.000</strong> COP.
        El precio no puede ser modificado.
      </p>

      <button class="pm-btn-pagar" id="pmBtnPagar">
        <i class="ph ph-lock-key"></i> Pagar ahora
      </button>
      <div class="pm-msg" id="pmMsg"></div>
    </div>
  `;

  const style = document.createElement('style');
  style.textContent = `
    .pago-overlay {
      display:none; position:fixed; inset:0;
      background:rgba(0,0,0,.5); z-index:9000;
      align-items:center; justify-content:center; padding:16px;
    }
    .pago-overlay.open { display:flex; }
    .pago-modal {
      background:#fff; border-radius:20px; padding:28px 24px;
      width:100%; max-width:420px; position:relative;
      box-shadow:0 24px 64px rgba(0,0,0,.2);
      animation:pmUp .25s ease;
    }
    @keyframes pmUp { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:none} }
    .pago-close {
      position:absolute; top:14px; right:14px;
      width:30px; height:30px; border-radius:8px;
      border:none; background:#f1f5f9; color:#64748b;
      display:flex; align-items:center; justify-content:center;
      cursor:pointer; font-size:1rem;
    }
    .pm-header { display:flex; align-items:center; gap:14px; margin-bottom:18px; }
    .pm-icon   { font-size:2.2rem; line-height:1; }
    .pm-title  { font-size:1.05rem; font-weight:700; color:#1e293b; margin:0 0 2px; }
    .pm-sub    { font-size:.78rem; color:#94a3b8; margin:0; }
    .pm-precio {
      display:flex; align-items:baseline; gap:6px;
      background:#eff6ff; border-radius:12px; padding:14px 18px; margin-bottom:18px;
    }
    .pm-desde { font-size:.78rem; color:#64748b; flex:1; }
    .pm-monto { font-size:1.8rem; font-weight:800; color:#3E72A6; }
    .pm-cop   { font-size:.8rem; color:#94a3b8; }
    .pm-metodo label:first-of-type {
      display:block; font-size:.8rem; font-weight:600; color:#475569; margin-bottom:8px;
    }
    .pm-metodos-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:16px; }
    .pm-met-op { cursor:pointer; }
    .pm-met-op input { display:none; }
    .pm-met-op span {
      display:block; text-align:center; padding:9px 8px;
      border-radius:10px; border:1.5px solid #e2e8f0;
      font-size:.82rem; font-weight:600; color:#64748b; transition:all .2s;
    }
    .pm-met-op input:checked + span { background:#eff6ff; border-color:#3E72A6; color:#3E72A6; }
    .pm-met-op:hover span { border-color:#3E72A6; }
    .pm-aviso {
      display:flex; align-items:flex-start; gap:7px;
      font-size:.78rem; color:#64748b;
      background:#f8fafc; border-radius:8px; padding:10px 12px; margin-bottom:18px;
    }
    .pm-aviso i { flex-shrink:0; margin-top:1px; }
    .pm-btn-pagar {
      width:100%; padding:13px; border-radius:12px; border:none;
      background:#3E72A6; color:#fff;
      font-size:.95rem; font-weight:700;
      display:flex; align-items:center; justify-content:center; gap:8px;
      cursor:pointer; transition:background .2s, transform .15s;
    }
    .pm-btn-pagar:hover    { background:#2f5a85; transform:translateY(-1px); }
    .pm-btn-pagar:disabled { opacity:.6; cursor:not-allowed; transform:none; }
    .pm-msg { text-align:center; font-size:.82rem; margin-top:10px; min-height:18px; }
    .pm-msg.ok  { color:#16a34a; }
    .pm-msg.err { color:#ef4444; }
  `;
  document.head.appendChild(style);
  document.body.appendChild(overlay);
})();

let servicioActual = 'paseos';

function abrirPago(tipoServicio) {
  servicioActual = tipoServicio;
  const precio   = PRECIOS[tipoServicio];
  const montoFmt = '$' + precio.toLocaleString('es-CO');

  document.getElementById('pmIcon').textContent       = ICONOS[tipoServicio] ?? '🐾';
  document.getElementById('pmTitle').textContent      = 'Membresía ' + LABELS[tipoServicio];
  document.getElementById('pmMonto').textContent      = montoFmt;
  document.getElementById('pmMontoAviso').textContent = montoFmt;
  document.getElementById('pmMsg').textContent        = '';
  document.getElementById('pmMsg').className          = 'pm-msg';

  document.querySelectorAll('input[name="pm_metodo"]').forEach(r => r.checked = r.value === 'nequi');
  document.getElementById('pagoOverlay').classList.add('open');
}

function cerrarPago() {
  document.getElementById('pagoOverlay').classList.remove('open');
}

document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('pagoClose').addEventListener('click', cerrarPago);
  document.getElementById('pagoOverlay').addEventListener('click', e => {
    if (e.target.id === 'pagoOverlay') cerrarPago();
  });

  const btnReservar = document.getElementById('btn-reservar');
  if (btnReservar) {
    btnReservar.addEventListener('click', () => {
      const tabActivo = document.querySelector('.tab.active');
      const svc       = tabActivo?.dataset?.svc ?? 'paseos';
      abrirPago(svc);
    });
  }

  document.getElementById('pmBtnPagar').addEventListener('click', procesarPago);
});

async function procesarPago() {
  const metodo = document.querySelector('input[name="pm_metodo"]:checked')?.value ?? 'nequi';
  const monto  = PRECIOS[servicioActual]; // precio fijo, no viene de ningún input
  const msgEl  = document.getElementById('pmMsg');
  const btn    = document.getElementById('pmBtnPagar');

  btn.disabled  = true;
  btn.innerHTML = '<i class="ph ph-spinner"></i> Procesando...';
  msgEl.textContent = '';

  try {
    const body = new URLSearchParams({
      tipo_membresia: servicioActual,
      monto,
      metodo_pago: metodo,
    });

    const res  = await fetch(PATHS_PAGOS.registrarPago, { method: 'POST', body });
    const data = await res.json();

    if (data.success) {
      msgEl.textContent = '¡Membresía activada! Vigente por 30 días.';
      msgEl.className   = 'pm-msg ok';
      setTimeout(cerrarPago, 2200);
    } else {
      msgEl.textContent = data.message ?? 'Ocurrió un error al procesar el pago.';
      msgEl.className   = 'pm-msg err';
    }
  } catch (err) {
    msgEl.textContent = 'Error de conexión. Intenta de nuevo.';
    msgEl.className   = 'pm-msg err';
    console.error(err);
  } finally {
    btn.disabled  = false;
    btn.innerHTML = '<i class="ph ph-lock-key"></i> Pagar ahora';
  }
}