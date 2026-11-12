<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#0f172a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ $unit->code }}">
    <title>Tripulación {{ $unit->code }} · Despacho Sarapiquí</title>
    <link rel="manifest" href="{{ route('crew.manifest', $token) }}">
    <link rel="icon" href="{{ asset('images/favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('images/app-icon-192.png') }}">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        :root {
            --bg:#0b1220; --card:#131c2e; --card2:#1a2540; --line:#25324d; --ink:#f1f5f9; --muted:#94a3b8;
            --brand:#14b8a6; --brand-ink:#042f2e; --high:#ef4444; --medium:#f59e0b; --low:#22c55e; --warn:#fbbf24;
        }
        * { box-sizing:border-box; -webkit-tap-highlight-color:transparent; }
        html, body { margin:0; background:var(--bg); color:var(--ink); }
        body { font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; min-height:100vh; padding-bottom:calc(110px + env(safe-area-inset-bottom)); }
        header { position:sticky; top:0; z-index:1000; display:flex; align-items:center; gap:10px; padding:calc(10px + env(safe-area-inset-top)) 16px 10px; background:rgba(11,18,32,.92); backdrop-filter:blur(8px); border-bottom:1px solid var(--line); }
        header img { height:26px; }
        .chip { margin-left:auto; display:flex; align-items:center; gap:8px; }
        .unit { font-weight:800; font-size:15px; letter-spacing:.3px; background:var(--card2); border:1px solid var(--line); padding:5px 10px; border-radius:999px; }
        .gps { display:flex; align-items:center; gap:6px; font-size:12px; color:var(--muted); background:none; border:1px solid var(--line); border-radius:999px; padding:5px 10px; font-family:inherit; }
        .gps i { width:8px; height:8px; border-radius:50%; background:#64748b; }
        .gps.on { color:#a7f3d0; border-color:#115e59; }
        .gps.on i { background:#22c55e; box-shadow:0 0 0 0 rgba(34,197,94,.7); animation:pulse 1.6s infinite; }
        .gps.off { color:#fde68a; border-color:#78350f; }
        .gps.off i { background:var(--warn); }
        @keyframes pulse { 70% { box-shadow:0 0 0 8px rgba(34,197,94,0); } 100% { box-shadow:0 0 0 0 rgba(34,197,94,0); } }
        main { padding:14px 14px 0; display:flex; flex-direction:column; gap:12px; max-width:560px; margin:0 auto; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:18px; padding:16px; }
        .banner { display:none; background:var(--high); color:#fff; border-radius:14px; padding:12px 14px; font-weight:700; text-align:center; animation:flash 1s 3; }
        @keyframes flash { 50% { opacity:.55; } }
        .idle { text-align:center; padding:30px 18px; }
        .idle .ring { width:84px; height:84px; margin:0 auto 14px; border-radius:50%; border:3px solid var(--brand); display:flex; align-items:center; justify-content:center; font-size:34px; animation:breathe 2.4s ease-in-out infinite; }
        @keyframes breathe { 50% { transform:scale(1.06); opacity:.7; } }
        .idle h1 { margin:0; font-size:22px; }
        .idle p { margin:6px 0 0; color:var(--muted); }
        .prio { display:flex; align-items:center; justify-content:space-between; gap:10px; }
        .tag { font-size:12px; font-weight:800; letter-spacing:.6px; text-transform:uppercase; padding:5px 10px; border-radius:8px; color:#fff; }
        .tag.high { background:var(--high); } .tag.medium { background:var(--medium); color:#1c1917; } .tag.low { background:var(--low); color:#052e16; }
        .code { color:var(--muted); font-size:13px; }
        .address { font-size:22px; font-weight:750; line-height:1.25; margin:12px 0 4px; }
        .sub { color:var(--muted); font-size:14px; }
        .actions { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; margin-top:14px; }
        .btn { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:4px; min-height:62px; border-radius:14px; border:1px solid var(--line); background:var(--card2); color:var(--ink); text-decoration:none; font-family:inherit; font-size:13px; font-weight:600; line-height:1.1; }
        .btn svg { width:22px; height:22px; }
        .btn.disabled { opacity:.35; pointer-events:none; }
        #map { height:220px; border-radius:18px; border:1px solid var(--line); overflow:hidden; }
        #map .leaflet-tile-pane { filter:invert(1) hue-rotate(180deg) brightness(.85) contrast(.9) saturate(.5); }
        .label { font-size:11px; text-transform:uppercase; letter-spacing:.7px; color:var(--muted); margin-bottom:6px; }
        .patient { font-size:18px; font-weight:700; }
        .alerts { display:flex; flex-wrap:wrap; gap:6px; margin-top:10px; }
        .alert { background:rgba(239,68,68,.15); color:#fecaca; border:1px solid rgba(239,68,68,.4); border-radius:999px; padding:5px 10px; font-size:13px; font-weight:600; }
        .care { margin-top:10px; background:rgba(251,191,36,.1); border:1px solid rgba(251,191,36,.35); color:#fde68a; border-radius:12px; padding:10px 12px; font-size:14px; }
        .desc { margin-top:10px; font-size:15px; line-height:1.45; }
        .steps { display:flex; gap:6px; margin-top:4px; }
        .steps div { flex:1; height:6px; border-radius:6px; background:var(--line); }
        .steps div.done { background:var(--brand); }
        .steps div.now { background:linear-gradient(90deg,var(--brand),var(--line)); }
        .step-now { margin-top:10px; font-weight:700; }
        .dock { position:fixed; left:0; right:0; bottom:0; z-index:1000; padding:12px 14px calc(12px + env(safe-area-inset-bottom)); background:linear-gradient(180deg,rgba(11,18,32,0),rgba(11,18,32,.96) 30%); }
        .go { display:block; width:100%; max-width:560px; margin:0 auto; min-height:68px; border:0; border-radius:18px; background:var(--brand); color:var(--brand-ink); font-family:inherit; font-size:19px; font-weight:800; line-height:1.2; box-shadow:0 10px 30px rgba(20,184,166,.35); }
        .go.confirm { background:#fbbf24; color:#1c1917; box-shadow:0 10px 30px rgba(251,191,36,.35); }
        .go:disabled { opacity:.5; }
        .hint { text-align:center; color:var(--muted); font-size:12px; margin-top:8px; }
        .install { display:none; width:100%; margin-top:2px; min-height:48px; border-radius:14px; border:1px dashed var(--brand); background:none; color:var(--brand); font-family:inherit; font-size:15px; font-weight:700; }
        .toast { position:fixed; left:50%; top:calc(70px + env(safe-area-inset-top)); transform:translateX(-50%); z-index:2000; background:#f8fafc; color:#0f172a; padding:10px 14px; border-radius:12px; font-size:14px; font-weight:600; box-shadow:0 10px 30px rgba(0,0,0,.4); display:none; max-width:90vw; text-align:center; }
        .marker-unit { width:34px; height:34px; border-radius:50%; background:var(--brand); border:3px solid #fff; box-shadow:0 2px 10px rgba(0,0,0,.5); display:flex; align-items:center; justify-content:center; color:#042f2e; font:800 10px/1 system-ui; }
        .marker-pin { width:20px; height:20px; border-radius:50%; background:#ef4444; border:3px solid #fff; box-shadow:0 2px 8px rgba(0,0,0,.5); }
        .marker-hosp { width:26px; height:26px; border-radius:7px; background:#fff; border:2px solid #ef4444; color:#ef4444; display:flex; align-items:center; justify-content:center; font:900 16px/1 system-ui; }
        .decide { display:grid; grid-template-columns:1fr 1.4fr; gap:10px; max-width:560px; margin:0 auto; }
        .no { min-height:68px; border-radius:18px; border:2px solid rgba(239,68,68,.6); background:rgba(239,68,68,.12); color:#fecaca; font-family:inherit; font-size:17px; font-weight:800; }
        .yes { min-height:68px; border-radius:18px; border:0; background:var(--low); color:#052e16; font-family:inherit; font-size:19px; font-weight:800; box-shadow:0 10px 30px rgba(34,197,94,.35); }
        .sheet { position:fixed; inset:0; z-index:3000; background:rgba(2,6,23,.75); display:flex; align-items:flex-end; }
        .sheet-body { width:100%; background:var(--card); border-top-left-radius:22px; border-top-right-radius:22px; padding:18px 16px calc(18px + env(safe-area-inset-bottom)); display:flex; flex-direction:column; gap:10px; }
        .sheet-body h2 { margin:0 0 4px; font-size:18px; }
        .reason { min-height:56px; border-radius:14px; border:1px solid var(--line); background:var(--card2); color:var(--ink); font-family:inherit; font-size:16px; font-weight:600; text-align:left; padding:0 16px; }
        .reason.cancel { background:none; text-align:center; color:var(--muted); }
        .ask { text-align:center; font-weight:800; font-size:15px; color:#fde68a; margin:0 0 10px; }
        .docs { display:flex; flex-wrap:wrap; gap:6px; margin-top:10px; }
        .doc { background:rgba(20,184,166,.12); border:1px solid rgba(20,184,166,.35); color:#99f6e4; border-radius:999px; padding:5px 10px; font-size:13px; font-weight:600; }
        .upload { margin-top:12px; display:flex; align-items:center; justify-content:center; gap:8px; min-height:52px; border-radius:14px; border:1px dashed var(--brand); color:var(--brand); font-weight:700; font-size:15px; }
        .upload input { display:none; }
        .notice { margin-top:10px; font-size:13px; color:#a7f3d0; }
        .field { display:flex; flex-direction:column; gap:6px; }
        .field label { font-size:12px; color:var(--muted); text-transform:uppercase; letter-spacing:.6px; }
        .field input { min-height:50px; border-radius:12px; border:1px solid var(--line); background:var(--card2); color:var(--ink); font-family:inherit; font-size:16px; padding:0 14px; }
        .roles { display:grid; grid-template-columns:repeat(2,1fr); gap:8px; }
        .role { min-height:46px; border-radius:12px; border:1px solid var(--line); background:var(--card2); color:var(--ink); font-family:inherit; font-size:15px; font-weight:600; }
        .role.on { border-color:var(--brand); background:rgba(20,184,166,.18); color:#ccfbf1; }
        .pad { position:relative; background:#f8fafc; border-radius:14px; height:190px; touch-action:none; }
        .pad canvas { width:100%; height:100%; display:block; border-radius:14px; }
        .pad .clear { position:absolute; top:8px; right:8px; border:0; background:#e2e8f0; color:#334155; border-radius:8px; padding:6px 10px; font-family:inherit; font-size:13px; font-weight:600; }
        .pad .line { position:absolute; left:16px; right:16px; bottom:38px; border-bottom:1px dashed #94a3b8; pointer-events:none; }
        [hidden] { display:none !important; }
    </style>
</head>
<body>
    <header>
        <img src="{{ asset('images/logo-dark.svg') }}" alt="Despacho Sarapiquí">
        <div class="chip">
            <button type="button" class="gps off" id="gps"><i></i><span id="gps-text">Activar GPS</span></button>
            <span class="unit">{{ $unit->code }}</span>
        </div>
    </header>

    <div class="toast" id="toast"></div>

    <div class="sheet" id="sheet" hidden>
        <div class="sheet-body">
            <h2>¿Por qué no pueden atenderlo?</h2>
            <div id="reasons" style="display:flex; flex-direction:column; gap:10px"></div>
            <button type="button" class="reason cancel" id="sheet-cancel">Volver</button>
        </div>
    </div>

    <main>
        <div class="banner" id="banner">Nuevo servicio asignado</div>

        <section class="card idle" id="idle" hidden>
            <div class="ring">🚑</div>
            <h1 id="idle-title">Disponible</h1>
            <p>Esperando un servicio del despacho…</p>
        </section>

        <section class="card" id="service" hidden>
            <div class="prio">
                <span class="tag" id="prio"></span>
                <span class="code" id="code"></span>
            </div>
            <div class="address" id="address"></div>
            <div class="sub" id="type"></div>
            <div class="actions">
                <a class="btn" id="nav" target="_blank" rel="noopener">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l18-8-8 18-2-8-8-2z"/></svg>Maps
                </a>
                <a class="btn" id="waze" target="_blank" rel="noopener">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="11" r="8"/><circle cx="9" cy="10" r="1"/><circle cx="15" cy="10" r="1"/><path d="M9 14c1.5 1.2 4.5 1.2 6 0"/></svg>Waze
                </a>
                <a class="btn" id="call">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.9v3a2 2 0 01-2.2 2 19.8 19.8 0 01-8.6-3.1 19.5 19.5 0 01-6-6A19.8 19.8 0 012.1 4.2 2 2 0 014.1 2h3a2 2 0 012 1.7c.1 1 .4 1.9.7 2.8a2 2 0 01-.5 2.1L8 9.9a16 16 0 006 6l1.3-1.3a2 2 0 012.1-.4c.9.3 1.8.6 2.8.7a2 2 0 011.7 2z"/></svg>Llamar
                </a>
            </div>
        </section>

        <div id="map" hidden></div>

        <section class="card" id="progress" hidden>
            <div class="label">Avance del servicio</div>
            <div class="steps" id="steps"></div>
            <div class="step-now" id="step-now"></div>
        </section>

        <section class="card" id="patient-card" hidden>
            <div class="label">Paciente</div>
            <div class="patient" id="patient"></div>
            <div class="sub" id="complaint"></div>
            <div class="alerts" id="alerts"></div>
            <div class="desc" id="desc"></div>
            <div class="care" id="care" hidden></div>
            <div class="docs" id="docs"></div>
            <label class="upload" id="upload">
                <input type="file" id="file" accept="image/*,application/pdf" capture="environment">
                📷 <span id="upload-text">Fotografiar la orden médica</span>
            </label>
        </section>

        <section class="card" id="receive-card" hidden>
            <div class="label">Entrega del paciente</div>
            <div class="patient" style="margin-bottom:12px">Firma de quien recibe en el hospital</div>
            <div style="display:flex; flex-direction:column; gap:12px">
                <div class="field">
                    <label for="rname">Nombre completo</label>
                    <input id="rname" autocomplete="off" placeholder="Ej: Dra. Carmen Solís">
                </div>
                <div class="field">
                    <label>Puesto</label>
                    <div class="roles" id="roles"></div>
                </div>
                <div class="field">
                    <label>Firma con el dedo</label>
                    <div class="pad"><canvas id="pad"></canvas><div class="line"></div><button type="button" class="clear" id="pad-clear">Borrar</button></div>
                </div>
            </div>
        </section>

        <section class="card" id="hospital-card" hidden>
            <div class="label">Destino</div>
            <div class="patient" id="hospital"></div>
        </section>

        <button type="button" class="install" id="install">Instalar la app en este celular</button>
        <p class="hint" id="ios-hint" hidden>Para instalarla: botón Compartir → «Agregar a inicio».</p>
    </main>

    <div class="dock">
        <div id="decide-wrap" hidden>
            <p class="ask">¿Pueden atender este servicio?</p>
            <div class="decide">
                <button type="button" class="no" id="no">No podemos</button>
                <button type="button" class="yes" id="yes">Aceptar</button>
            </div>
        </div>
        <button type="button" class="go" id="go" hidden></button>
        <button type="button" class="go" id="receive-go" hidden>Guardar firma de recibido</button>
        <p class="hint" id="dock-hint">Mantenga la app abierta para compartir la ubicación con el despacho.</p>
    </div>

    <script>
        const URLS = {
            state: @js(route('crew.state', $token)),
            location: @js(route('crew.location', $token)),
            advance: @js(route('crew.advance', $token)),
            reject: @js(route('crew.reject', $token)),
            receive: @js(route('crew.receive', $token)),
            document: @js(route('crew.document', $token)),
        };
        let state = @js($state);
        let lastDispatchId = state.dispatch ? state.dispatch.id : null;
        let confirmTimer = null;
        let busy = false;

        const $ = (id) => document.getElementById(id);
        const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

        function toast(text, ms = 3000) {
            const t = $('toast');
            t.textContent = text;
            t.style.display = 'block';
            clearTimeout(t._h);
            t._h = setTimeout(() => (t.style.display = 'none'), ms);
        }

        // ── Mapa ───────────────────────────────────────────
        let map = null, unitMarker = null, pinMarker = null, hospMarker = null, lastFit = null;
        const icon = (cls, html = '') => L.divIcon({ className: '', html: `<div class="${cls}">${html}</div>`, iconSize: null });

        function drawMap() {
            const d = state.dispatch, u = state.unit;
            const points = [];
            $('map').hidden = false;
            if (! map) {
                map = L.map('map', { zoomControl: false, attributionControl: false }).setView([10.40, -84.03], 11);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
            }
            if (u.latitude) {
                const p = [u.latitude, u.longitude];
                unitMarker ? unitMarker.setLatLng(p) : (unitMarker = L.marker(p, { icon: icon('marker-unit', esc(u.code.split('-').pop())) }).addTo(map));
                points.push(p);
            }
            const pin = d && d.latitude ? [d.latitude, d.longitude] : null;
            if (pin) { pinMarker ? pinMarker.setLatLng(pin) : (pinMarker = L.marker(pin, { icon: icon('marker-pin') }).addTo(map)); points.push(pin); }
            else if (pinMarker) { map.removeLayer(pinMarker); pinMarker = null; }
            const h = d && d.hospital && d.hospital.latitude ? [d.hospital.latitude, d.hospital.longitude] : null;
            if (h) { hospMarker ? hospMarker.setLatLng(h) : (hospMarker = L.marker(h, { icon: icon('marker-hosp', '+') }).addTo(map)); points.push(h); }
            else if (hospMarker) { map.removeLayer(hospMarker); hospMarker = null; }

            // Encuadra solo cuando cambia el servicio, para no pelear con el dedo
            const key = (d ? d.id : 'none') + ':' + (h ? 'h' : '');
            if (key !== lastFit && points.length) {
                lastFit = key;
                points.length > 1 ? map.fitBounds(points, { padding: [36, 36], maxZoom: 15 }) : map.setView(points[0], 15);
            }
            setTimeout(() => map.invalidateSize(), 50);
        }

        // ── Pantalla ───────────────────────────────────────
        function render() {
            const d = state.dispatch;
            $('idle').hidden = !! d;
            $('service').hidden = ! d;
            $('progress').hidden = ! d;
            $('patient-card').hidden = ! d;
            $('hospital-card').hidden = ! (d && d.hospital);
            $('idle-title').textContent = state.unit.status || 'Disponible';

            if (d) {
                $('prio').className = 'tag ' + d.priority_key;
                $('prio').textContent = d.priority;
                $('code').textContent = d.code + (d.assigned_at ? ' · ' + d.assigned_at : '');
                $('address').textContent = d.address;
                $('type').textContent = d.type + ' · ' + d.status;

                const dest = d.latitude ? d.latitude + ',' + d.longitude : encodeURIComponent(d.address + ', Sarapiquí, Costa Rica');
                $('nav').href = 'https://www.google.com/maps/dir/?api=1&travelmode=driving&destination=' + dest;
                $('waze').href = d.latitude ? `https://waze.com/ul?ll=${d.latitude},${d.longitude}&navigate=yes` : 'https://waze.com/ul?q=' + encodeURIComponent(d.address);
                $('call').href = d.phone ? 'tel:' + d.phone : '#';
                $('call').classList.toggle('disabled', ! d.phone);

                $('patient').textContent = (d.patient || 'Paciente sin identificar') + (d.age ? ` · ${d.age} años` : '');
                $('complaint').textContent = d.complaint || '';
                $('alerts').innerHTML = d.alerts.map((a) => `<span class="alert">${esc(a)}</span>`).join('');
                $('desc').textContent = d.description || '';
                $('care').hidden = ! d.care_notes;
                $('care').textContent = d.care_notes ? 'Cuidados: ' + d.care_notes : '';
                $('hospital').textContent = d.hospital ? d.hospital.name + (d.hospital_notified ? ' · avisado ✓' : '') : '';

                const current = state.next ? state.steps.findIndex((s) => s.key === state.next.key) : state.steps.length;
                $('steps').innerHTML = state.steps.map((s, i) => `<div class="${i < current ? 'done' : (i === current ? 'now' : '')}"></div>`).join('');
                $('step-now').textContent = current > 0 ? state.steps[current - 1].label : 'Nuevo servicio: revise los datos y acepte';
            }

            const receiving = state.next && state.next.key === 'receive';
            $('receive-card').hidden = ! receiving;
            $('receive-go').hidden = ! receiving;
            if (receiving) { setupPad(); renderRoles(); }
            if (d) {
                $('docs').innerHTML = (d.documents || []).map((x) => `<span class="doc">✓ ${esc(x)}</span>`).join('');
                $('upload-text').textContent = (d.documents || []).length ? 'Agregar otra foto o documento' : 'Fotografiar la orden médica';
            }
            const deciding = state.next && state.next.key === 'accept';
            $('decide-wrap').hidden = ! deciding;
            $('dock-hint').hidden = deciding;
            const go = $('go');
            go.hidden = ! state.next || deciding || receiving;
            $('dock-hint').hidden = deciding || receiving;
            if (state.next && ! confirmTimer) { go.textContent = state.next.label; go.classList.remove('confirm'); }

            drawMap();
        }

        // ── Avisos de servicio nuevo ───────────────────────
        function beep() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                [0, 0.35].forEach((t) => {
                    const o = ctx.createOscillator(), g = ctx.createGain();
                    o.frequency.value = 880; o.type = 'square';
                    g.gain.setValueAtTime(0.15, ctx.currentTime + t);
                    g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + t + 0.3);
                    o.connect(g).connect(ctx.destination);
                    o.start(ctx.currentTime + t); o.stop(ctx.currentTime + t + 0.3);
                });
            } catch (e) {}
        }

        function apply(next) {
            const id = next.dispatch ? next.dispatch.id : null;
            if (id && id !== lastDispatchId) {
                $('banner').style.display = 'block';
                setTimeout(() => ($('banner').style.display = 'none'), 8000);
                if (navigator.vibrate) navigator.vibrate([400, 150, 400, 150, 400]);
                beep();
            }
            lastDispatchId = id;
            state = next;
            render();
        }

        let failures = 0;
        async function poll() {
            try {
                const res = await fetch(URLS.state, { headers: { Accept: 'application/json' }, cache: 'no-store' });
                if (res.status === 404) { toast('Este enlace ya no sirve. Pida uno nuevo al despacho.', 10000); return; }
                if (res.ok) { apply(await res.json()); failures = 0; }
            } catch (e) {
                failures++;
                if (failures === 2) toast('Sin conexión: se reintenta solo.');
            }
            setTimeout(poll, failures ? Math.min(30000, 5000 * failures) : 5000);
        }

        // ── Botón grande: dos toques para confirmar ────────
        $('go').addEventListener('click', async () => {
            const go = $('go');
            if (! state.next || busy) return;
            if (! confirmTimer) {
                go.textContent = 'Toque otra vez para confirmar';
                go.classList.add('confirm');
                confirmTimer = setTimeout(() => { confirmTimer = null; render(); }, 3000);
                return;
            }
            clearTimeout(confirmTimer); confirmTimer = null;
            busy = true; go.disabled = true;
            try {
                const res = await fetch(URLS.advance, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    body: JSON.stringify({ dispatch: state.dispatch.id, step: state.next.key }),
                });
                const data = await res.json();
                if (res.status === 409) toast(data.message || 'El servicio cambió.');
                else if (res.ok) toast('Listo: el despacho ya lo ve.');
                if (data.unit) apply(data);
                if (navigator.vibrate) navigator.vibrate(80);
            } catch (e) {
                toast('No se pudo enviar. Revise la señal e intente de nuevo.');
            } finally {
                busy = false; go.disabled = false; render();
            }
        });

        // ── Aceptar o decir que no se puede ────────────────
        async function post(url, body, okText) {
            if (busy) return;
            busy = true;
            try {
                const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(body) });
                const data = await res.json();
                if (res.status === 409) toast(data.message || 'El servicio cambió.');
                else if (res.ok) toast(okText);
                else toast(data.message || 'No se pudo enviar.');
                if (data.unit) { lastDispatchId = data.dispatch ? data.dispatch.id : null; apply(data); }
                if (navigator.vibrate) navigator.vibrate(80);
            } catch (e) {
                toast('No se pudo enviar. Revise la señal e intente de nuevo.');
            } finally { busy = false; }
        }

        $('yes').addEventListener('click', () => post(URLS.advance, { dispatch: state.dispatch.id, step: 'accept' }, 'Aceptado: el despacho ya lo sabe.'));
        $('no').addEventListener('click', () => {
            $('reasons').innerHTML = (state.reasons || []).map((r) => `<button type="button" class="reason" data-key="${esc(r.key)}">${esc(r.label)}</button>`).join('');
            $('sheet').hidden = false;
        });
        $('sheet-cancel').addEventListener('click', () => ($('sheet').hidden = true));
        $('reasons').addEventListener('click', (e) => {
            const b = e.target.closest('[data-key]');
            if (! b) return;
            $('sheet').hidden = true;
            post(URLS.reject, { dispatch: state.dispatch.id, reason: b.dataset.key }, 'Listo: el despacho asignará otra unidad.');
        });

        // ── Orden médica desde la cámara ───────────────────
        $('file').addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (! file || ! state.dispatch) return;
            if (file.size > 10 * 1024 * 1024) { toast('El archivo pesa más de 10 MB.'); return; }
            const form = new FormData();
            form.append('dispatch', state.dispatch.id);
            form.append('type', (state.dispatch.documents || []).length ? 'other' : 'medical_order');
            form.append('file', file);
            $('upload-text').textContent = 'Subiendo…';
            try {
                const res = await fetch(URLS.document, { method: 'POST', headers: { Accept: 'application/json' }, body: form });
                const data = await res.json();
                if (res.ok) { toast('Documento guardado en el servicio.'); apply(data); }
                else toast(data.message || 'No se pudo subir el documento.');
            } catch (err) { toast('Sin señal: intente de nuevo.'); }
            e.target.value = '';
            render();
        });

        // ── Firma de recibido ───────────────────────────────
        let padReady = false, drawing = false, strokes = 0, role = null;
        function setupPad() {
            const c = $('pad');
            if (padReady && c.width) return;
            const ratio = window.devicePixelRatio || 1;
            const rect = c.getBoundingClientRect();
            if (! rect.width) { setTimeout(setupPad, 100); return; }
            c.width = rect.width * ratio; c.height = rect.height * ratio;
            const ctx = c.getContext('2d');
            ctx.scale(ratio, ratio); ctx.lineWidth = 2.6; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#0f172a';
            const pos = (e) => { const r = c.getBoundingClientRect(); return [e.clientX - r.left, e.clientY - r.top]; };
            c.onpointerdown = (e) => { drawing = true; strokes++; c.setPointerCapture(e.pointerId); const [x, y] = pos(e); ctx.beginPath(); ctx.moveTo(x, y); };
            c.onpointermove = (e) => { if (! drawing) return; const [x, y] = pos(e); ctx.lineTo(x, y); ctx.stroke(); };
            c.onpointerup = c.onpointercancel = () => (drawing = false);
            padReady = true;
        }
        $('pad-clear').addEventListener('click', () => { const c = $('pad'); c.getContext('2d').clearRect(0, 0, c.width, c.height); strokes = 0; });
        function renderRoles() {
            $('roles').innerHTML = (state.receiver_roles || []).map((r) => `<button type="button" class="role ${r === role ? 'on' : ''}" data-role="${esc(r)}">${esc(r)}</button>`).join('');
        }
        $('roles').addEventListener('click', (e) => { const b = e.target.closest('[data-role]'); if (! b) return; role = b.dataset.role; renderRoles(); });
        $('receive-go').addEventListener('click', async () => {
            const name = $('rname').value.trim();
            if (name.length < 3) { toast('Escriba el nombre de quien recibe.'); return; }
            if (! role) { toast('Elija el puesto de quien recibe.'); return; }
            if (strokes === 0) { toast('Falta la firma.'); return; }
            if (busy) return;
            busy = true; $('receive-go').disabled = true;
            try {
                const res = await fetch(URLS.receive, {
                    method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    body: JSON.stringify({ dispatch: state.dispatch.id, name, role, signature: $('pad').toDataURL('image/png') }),
                });
                const data = await res.json();
                if (res.ok) { toast('Firma guardada. Ya puede terminar el servicio.'); $('rname').value = ''; role = null; strokes = 0; padReady = false; apply(data); }
                else toast(data.message || 'No se pudo guardar la firma.');
            } catch (err) { toast('Sin señal: intente de nuevo.'); }
            finally { busy = false; $('receive-go').disabled = false; }
        });

        // ── GPS real ───────────────────────────────────────
        let watchId = null, lastSent = 0, lastPos = null, wakeLock = null;

        function setGps(mode, text) {
            const b = $('gps');
            b.className = 'gps ' + mode;
            $('gps-text').textContent = text;
        }

        function distance(a, b) {
            const R = 6371000, toRad = (x) => x * Math.PI / 180;
            const dLat = toRad(b[0] - a[0]), dLng = toRad(b[1] - a[1]);
            const h = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(a[0])) * Math.cos(toRad(b[0])) * Math.sin(dLng / 2) ** 2;
            return 2 * R * Math.asin(Math.sqrt(h));
        }

        async function send(pos) {
            const c = pos.coords, now = Date.now(), here = [c.latitude, c.longitude];
            setGps('on', 'GPS ±' + Math.round(c.accuracy) + ' m');
            if (now - lastSent < 5000 && lastPos && distance(lastPos, here) < 15) return;
            lastSent = now; lastPos = here;
            state.unit.latitude = c.latitude; state.unit.longitude = c.longitude;
            if (unitMarker) unitMarker.setLatLng(here);
            try {
                const res = await fetch(URLS.location, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    body: JSON.stringify({ latitude: c.latitude, longitude: c.longitude, speed: c.speed, heading: c.heading, accuracy: c.accuracy }),
                });
                if (res.status === 422) setGps('off', 'Fuera de Costa Rica');
            } catch (e) {}
        }

        async function keepAwake() {
            try { if ('wakeLock' in navigator && ! wakeLock) { wakeLock = await navigator.wakeLock.request('screen'); wakeLock.addEventListener('release', () => (wakeLock = null)); } } catch (e) {}
        }

        function startGps() {
            if (! ('geolocation' in navigator)) { setGps('off', 'Sin GPS'); return; }
            if (watchId !== null) return;
            setGps('off', 'Buscando GPS…');
            watchId = navigator.geolocation.watchPosition(send, (err) => {
                watchId = null;
                setGps('off', err.code === 1 ? 'GPS bloqueado' : 'Sin señal GPS');
                if (err.code === 1) toast('Permita la ubicación para este sitio en los ajustes del navegador.', 6000);
            }, { enableHighAccuracy: true, maximumAge: 3000, timeout: 20000 });
            keepAwake();
        }

        $('gps').addEventListener('click', startGps);
        document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible' && watchId !== null) keepAwake(); });

        // Si ya dio permiso antes, el GPS arranca solo
        if (navigator.permissions) {
            navigator.permissions.query({ name: 'geolocation' }).then((p) => { if (p.state === 'granted') startGps(); }).catch(() => {});
        }

        // ── Instalar como app ──────────────────────────────
        let installEvent = null;
        const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone;
        window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); installEvent = e; $('install').style.display = 'block'; });
        $('install').addEventListener('click', async () => {
            if (! installEvent) return;
            installEvent.prompt();
            await installEvent.userChoice;
            installEvent = null; $('install').style.display = 'none';
        });
        if (! standalone && /iphone|ipad/i.test(navigator.userAgent)) $('ios-hint').hidden = false;

        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/crew-sw.js', { scope: '/unidad/' }).catch(() => {});
        }

        render();
        setTimeout(poll, 5000);
    </script>
</body>
</html>
