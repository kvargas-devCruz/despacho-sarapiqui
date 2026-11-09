<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Seguimiento {{ $dispatch->code }} · Despacho Sarapiquí</title>
    <link rel="icon" href="{{ asset('images/favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        :root { --brand:#0d9488; --ink:#0f172a; --muted:#64748b; --line:#e2e8f0; --bg:#f1f5f9; }
        * { box-sizing:border-box; }
        html, body { margin:0; height:100%; }
        body { font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; color:var(--ink); background:var(--bg); display:flex; flex-direction:column; }
        header { background:#fff; border-bottom:1px solid var(--line); padding:12px 16px; display:flex; align-items:center; justify-content:space-between; gap:12px; }
        header img { height:30px; }
        header span { font-size:13px; color:var(--muted); }
        #map { flex:1 1 auto; min-height:320px; }
        .sheet { background:#fff; border-top-left-radius:20px; border-top-right-radius:20px; margin-top:-18px; position:relative; z-index:500; padding:18px 18px 22px; box-shadow:0 -6px 24px rgba(15,23,42,.12); }
        .grab { width:40px; height:4px; border-radius:4px; background:#cbd5e1; margin:0 auto 14px; }
        h1 { margin:0; font-size:21px; line-height:1.25; }
        .eta { display:flex; align-items:baseline; gap:10px; margin-top:8px; }
        .eta b { font-size:34px; line-height:1; color:var(--brand); }
        .eta span { color:var(--muted); font-size:14px; }
        .steps { display:grid; grid-template-columns:repeat(4,1fr); gap:6px; margin:18px 0 10px; }
        .step { height:6px; border-radius:6px; background:var(--line); }
        .step.on { background:var(--brand); }
        .labels { display:grid; grid-template-columns:repeat(4,1fr); gap:6px; font-size:11px; color:var(--muted); }
        .labels div.on { color:var(--ink); font-weight:600; }
        .meta { display:flex; flex-wrap:wrap; gap:8px 16px; margin-top:14px; font-size:13px; color:var(--muted); }
        .meta b { color:var(--ink); }
        .note { margin-top:14px; font-size:12px; color:var(--muted); }
        .live { display:inline-flex; align-items:center; gap:6px; }
        .live i { width:8px; height:8px; border-radius:50%; background:#16a34a; animation:blink 1.5s infinite; }
        @keyframes blink { 50% { opacity:.3; } }
        .unit { width:36px; height:36px; border-radius:50%; background:var(--c); border:3px solid #fff; box-shadow:0 2px 8px rgba(0,0,0,.4); display:flex; align-items:center; justify-content:center; color:#fff; font:700 11px/1 system-ui,sans-serif; }
        .pin { width:18px; height:18px; border-radius:50%; background:#dc2626; border:3px solid #fff; box-shadow:0 2px 6px rgba(0,0,0,.35); }
        .hosp { width:24px; height:24px; border-radius:6px; background:#fff; border:2px solid #dc2626; color:#dc2626; display:flex; align-items:center; justify-content:center; font:800 14px/1 system-ui,sans-serif; }
        @media (min-width:900px) { body { flex-direction:row; flex-wrap:wrap; } header { width:100%; } #map { height:calc(100vh - 55px); } .sheet { width:380px; margin:0; border-radius:0; height:calc(100vh - 55px); overflow:auto; box-shadow:none; border-left:1px solid var(--line); } .grab { display:none; } }
    </style>
</head>
<body>
    <header>
        <img src="{{ asset('images/logo.svg') }}" alt="Despacho Sarapiquí">
        <span>Seguimiento del servicio</span>
    </header>

    <div id="map"></div>

    <section class="sheet">
        <div class="grab"></div>
        <h1 id="headline">{{ $state['headline'] }}</h1>
        <div class="eta" id="eta" @if (! $state['eta']) style="display:none" @endif>
            <b id="eta-min">{{ $state['eta']['minutes'] ?? '' }} min</b>
            <span id="eta-text">{{ $state['eta'] ? "{$state['eta']['target']} · llegada aprox. {$state['eta']['arrival']}" : '' }}</span>
        </div>

        <div class="steps" id="steps">
            @foreach ([1, 2, 3, 4] as $i)
                <div class="step {{ $state['step'] >= $i ? 'on' : '' }}"></div>
            @endforeach
        </div>
        <div class="labels" id="labels">
            @foreach (['En camino', 'En el sitio', 'Trasladando', 'Finalizado'] as $i => $label)
                <div class="{{ $state['step'] === $i + 1 ? 'on' : '' }}">{{ $label }}</div>
            @endforeach
        </div>

        <div class="meta">
            <div>Servicio <b>{{ $dispatch->code }}</b></div>
            <div>{{ $dispatch->type->getLabel() }}</div>
            <div id="unit-code">{!! $state['unit'] ? 'Unidad <b>' . e($state['unit']['code']) . '</b>' : '' !!}</div>
            @if ($state['hospital'])
                <div>Destino <b>{{ $dispatch->hospital->short_name }}</b></div>
            @endif
        </div>

        <div class="note">
            <span class="live" id="live" @if (! $state['open']) style="display:none" @endif><i></i> Se actualiza solo cada 5 segundos.</span>
            <span id="closed" @if ($state['open']) style="display:none" @endif>Este enlace ya no muestra la ubicación de la unidad.</span>
            <br>¿Dudas? Llame a la central de despacho.
        </div>
    </section>

    <script>
        const url = @json(route('tracking.state', $dispatch->tracking_token));
        let state = @json($state);

        const map = L.map('map', { zoomControl: false, attributionControl: true }).setView([10.40, -84.03], 11);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap', maxZoom: 19,
        }).addTo(map);
        L.control.zoom({ position: 'topright' }).addTo(map);

        const icon = (html, size) => L.divIcon({ html, className: '', iconSize: [size, size], iconAnchor: [size / 2, size / 2] });
        let unitMarker = null, pickupMarker = null, hospitalMarker = null, fitted = false;

        function draw() {
            const points = [];

            if (state.pickup && state.step < 3) {
                pickupMarker ??= L.marker([state.pickup.lat, state.pickup.lng], { icon: icon('<div class="pin"></div>', 18) }).addTo(map).bindTooltip('Punto de atención');
                points.push([state.pickup.lat, state.pickup.lng]);
            } else if (pickupMarker) { map.removeLayer(pickupMarker); pickupMarker = null; }

            if (state.hospital) {
                hospitalMarker ??= L.marker([state.hospital.lat, state.hospital.lng], { icon: icon('<div class="hosp">H</div>', 24) }).addTo(map).bindTooltip(state.hospital.label);
                if (state.step >= 2) points.push([state.hospital.lat, state.hospital.lng]);
            }

            if (state.unit) {
                const html = `<div class="unit" style="--c:${state.unit.color}">${state.unit.code.replace(/^\D+-/, '')}</div>`;
                if (unitMarker) { unitMarker.setLatLng([state.unit.lat, state.unit.lng]).setIcon(icon(html, 36)); }
                else { unitMarker = L.marker([state.unit.lat, state.unit.lng], { icon: icon(html, 36), zIndexOffset: 1000 }).addTo(map); }
                points.push([state.unit.lat, state.unit.lng]);
            } else if (unitMarker) { map.removeLayer(unitMarker); unitMarker = null; }

            if (!fitted && points.length) {
                map.fitBounds(L.latLngBounds(points).pad(0.35), { maxZoom: 15 });
                fitted = true;
            }
        }

        function render() {
            document.getElementById('headline').textContent = state.headline;
            const eta = document.getElementById('eta');
            if (state.eta) {
                eta.style.display = '';
                document.getElementById('eta-min').textContent = `${state.eta.minutes} min`;
                document.getElementById('eta-text').textContent = `${state.eta.target} · llegada aprox. ${state.eta.arrival}`;
            } else { eta.style.display = 'none'; }

            document.querySelectorAll('#steps .step').forEach((el, i) => el.classList.toggle('on', state.step >= i + 1));
            document.querySelectorAll('#labels div').forEach((el, i) => el.classList.toggle('on', state.step === i + 1));
            document.getElementById('unit-code').innerHTML = state.unit ? `Unidad <b>${state.unit.code}</b>` : '';
            document.getElementById('live').style.display = state.open ? '' : 'none';
            document.getElementById('closed').style.display = state.open ? 'none' : '';
            draw();
        }

        async function poll() {
            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                if (response.ok) { state = await response.json(); render(); }
            } catch (e) { /* sin conexión: se intenta de nuevo */ }
            if (state.open) setTimeout(poll, 5000);
        }

        render();
        if (state.open) setTimeout(poll, 5000);
    </script>
</body>
</html>
