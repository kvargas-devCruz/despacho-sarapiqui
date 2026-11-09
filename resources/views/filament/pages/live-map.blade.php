<x-filament-panels::page>
    @assets
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <style>
            .lm-wrap { --lm-card: #ffffff; --lm-border: #e5e7eb; --lm-text: #111827; --lm-muted: #6b7280; --lm-hover: #f3f4f6; }
            .dark .lm-wrap { --lm-card: #18181b; --lm-border: #27272a; --lm-text: #f4f4f5; --lm-muted: #a1a1aa; --lm-hover: #27272a; }

            .lm-stats { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1rem; }
            .lm-stat { display: flex; align-items: center; gap: .5rem; padding: .5rem .85rem; border-radius: .75rem;
                       background: var(--lm-card); border: 1px solid var(--lm-border); color: var(--lm-text); font-size: .85rem; }
            .lm-stat b { font-size: 1.1rem; }
            .lm-dot { width: .7rem; height: .7rem; border-radius: 9999px; flex-shrink: 0; }
            .lm-live { margin-left: auto; color: var(--lm-muted); }
            .lm-live .lm-dot { background: #16a34a; animation: lm-blink 1.5s infinite; }

            .lm-grid { display: grid; grid-template-columns: 1fr 300px; gap: 1rem; }
            @media (max-width: 1024px) { .lm-grid { grid-template-columns: 1fr; } }
            .lm-map { height: 72vh; min-height: 520px; border-radius: 1rem; overflow: hidden; border: 1px solid var(--lm-border); z-index: 0; }
            .dark .lm-map .leaflet-tile-pane { filter: invert(1) hue-rotate(180deg) brightness(.9) contrast(.9) saturate(.6); }
            .lm-side { background: var(--lm-card); border: 1px solid var(--lm-border); border-radius: 1rem; overflow: hidden;
                       max-height: 72vh; display: flex; flex-direction: column; }
            .lm-side-title { padding: .85rem 1rem; font-weight: 600; color: var(--lm-text); border-bottom: 1px solid var(--lm-border); }
            .lm-list { overflow-y: auto; }
            .lm-item { display: flex; gap: .75rem; align-items: flex-start; padding: .75rem 1rem; cursor: pointer;
                       border-bottom: 1px solid var(--lm-border); color: var(--lm-text); }
            .lm-item:hover { background: var(--lm-hover); }
            .lm-item-code { font-weight: 700; }
            .lm-item-sub { font-size: .8rem; color: var(--lm-muted); }
            .lm-item .lm-dot { margin-top: .35rem; }

            .lm-unit { position: relative; width: 34px; height: 34px; border-radius: 9999px; background: var(--c);
                       border: 3px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,.45); display: flex; align-items: center;
                       justify-content: center; color: #fff; font: 700 12px/1 system-ui, sans-serif; }
            .lm-unit.lm-pulse::after { content: ''; position: absolute; inset: -8px; border-radius: 9999px;
                       border: 3px solid var(--c); animation: lm-ring 1.6s ease-out infinite; }
            .lm-hospital { width: 26px; height: 26px; border-radius: 6px; background: #fff; color: #dc2626; border: 2px solid #dc2626;
                       display: flex; align-items: center; justify-content: center; font: 800 15px/1 system-ui, sans-serif;
                       box-shadow: 0 2px 6px rgba(0,0,0,.35); }
            .lm-alert { width: 22px; height: 22px; border-radius: 9999px; background: #dc2626; border: 3px solid #fff; color: #fff;
                       display: flex; align-items: center; justify-content: center; font: 800 12px/1 system-ui, sans-serif;
                       animation: lm-alert 1s ease-in-out infinite alternate; }

            .lm-popup { font: 13px/1.45 system-ui, sans-serif; min-width: 200px; }
            .lm-popup h4 { margin: 0 0 .25rem; font-size: 15px; }
            .lm-popup .lm-badge { display: inline-block; padding: 1px 8px; border-radius: 9999px; color: #fff; font-size: 11px; font-weight: 600; }
            .lm-popup hr { border: 0; border-top: 1px solid #e5e7eb; margin: .5rem 0; }
            .lm-popup .lm-muted { color: #6b7280; }

            @keyframes lm-ring { from { transform: scale(.8); opacity: .9; } to { transform: scale(1.6); opacity: 0; } }
            @keyframes lm-blink { 50% { opacity: .3; } }
            @keyframes lm-alert { from { transform: scale(.9); box-shadow: 0 0 0 0 rgba(220,38,38,.7); }
                                  to { transform: scale(1.1); box-shadow: 0 0 0 10px rgba(220,38,38,0); } }
        </style>
    @endassets

    <div id="lm-offline" style="display:none; margin-bottom:8px; padding:8px 12px; border-radius:8px; background:#fef3c7; color:#92400e; font-size:14px">
        Sin conexión con el servidor: el mapa muestra la última posición conocida. Reintentando cada 3 segundos…
    </div>
    <div class="lm-wrap" wire:ignore>
        <div class="lm-stats" id="lm-stats"></div>

        <div class="lm-grid">
            <div id="lm-map" class="lm-map"></div>

            <aside class="lm-side">
                <div class="lm-side-title">Unidades</div>
                <div class="lm-list" id="lm-list"></div>
            </aside>
        </div>
    </div>
</x-filament-panels::page>

@script
<script>
    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const isDark = document.documentElement.classList.contains('dark');

    const map = L.map('lm-map', { zoomControl: true }).setView([10.40, -84.03], 11);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
    }).addTo(map);

    const unitMarkers = {};
    const hospitalLayer = L.layerGroup().addTo(map);
    const pendingLayer = L.layerGroup().addTo(map);
    let firstLoad = true;

    const unitIcon = (u) => L.divIcon({
        className: '',
        iconSize: [34, 34],
        iconAnchor: [17, 17],
        html: `<div class="lm-unit ${['en_route', 'on_scene'].includes(u.status) ? 'lm-pulse' : ''}" style="--c:${u.color}">${esc(u.code.split('-').pop())}</div>`,
    });

    const unitPopup = (u) => `
        <div class="lm-popup">
            <h4>${esc(u.code)}</h4>
            <span class="lm-badge" style="background:${u.color}">${esc(u.statusLabel)}</span>
            <div class="lm-muted" style="margin-top:.35rem">${esc(u.type)}</div>
            ${u.dispatch ? `
                <hr>
                <div><b>${esc(u.dispatch.type)}</b> · ${esc(u.dispatch.code)}</div>
                ${u.dispatch.patient ? `<div>Paciente: ${esc(u.dispatch.patient)}</div>` : ''}
                ${u.dispatch.destination ? `<div>Destino: ${esc(u.dispatch.destination)}</div>` : ''}
            ` : ''}
            <hr>
            <div class="lm-muted">
                ${u.speed !== null ? `${u.speed} km/h · ` : ''}Señal ${esc(u.updated ?? 'sin datos')}
            </div>
        </div>`;

    // Desliza el marcador suavemente hasta la nueva posición
    const animateTo = (marker, lat, lng, ms = 1500) => {
        const from = marker.getLatLng();
        if (Math.abs(from.lat - lat) < 1e-7 && Math.abs(from.lng - lng) < 1e-7) return;
        const start = performance.now();
        const step = (now) => {
            const k = Math.min(1, (now - start) / ms);
            marker.setLatLng([from.lat + (lat - from.lat) * k, from.lng + (lng - from.lng) * k]);
            if (k < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    };

    const render = (data) => {
        // Contadores por estado
        const counts = Object.fromEntries(data.statuses.map((s) => [s.value, 0]));
        data.units.forEach((u) => counts[u.status]++);
        document.getElementById('lm-stats').innerHTML =
            data.statuses.map((s) => `
                <div class="lm-stat"><span class="lm-dot" style="background:${s.color}"></span>${esc(s.label)} <b>${counts[s.value]}</b></div>
            `).join('') +
            `<div class="lm-stat"><span class="lm-dot" style="background:#dc2626"></span>Emergencias pendientes <b>${data.pending.length}</b></div>` +
            `<div class="lm-stat lm-live"><span class="lm-dot"></span>En vivo · ${new Date().toLocaleTimeString('es-CR')}</div>`;

        // Unidades
        const seen = new Set();
        data.units.forEach((u) => {
            seen.add(u.id);
            let m = unitMarkers[u.id];
            if (!m) {
                m = unitMarkers[u.id] = L.marker([u.lat, u.lng], { icon: unitIcon(u), zIndexOffset: 1000 }).addTo(map);
                m.bindPopup(unitPopup(u));
            } else {
                animateTo(m, u.lat, u.lng);
                m.setIcon(unitIcon(u));
                m.setPopupContent(unitPopup(u));
            }
            m._status = u.status;
        });
        Object.keys(unitMarkers).forEach((id) => {
            if (!seen.has(Number(id))) { map.removeLayer(unitMarkers[id]); delete unitMarkers[id]; }
        });

        // Lista lateral
        document.getElementById('lm-list').innerHTML = data.units.map((u) => `
            <div class="lm-item" data-id="${u.id}">
                <span class="lm-dot" style="background:${u.color}"></span>
                <div>
                    <div class="lm-item-code">${esc(u.code)}</div>
                    <div class="lm-item-sub">${esc(u.statusLabel)}${u.dispatch ? ' · ' + esc(u.dispatch.code) : ''}</div>
                </div>
            </div>
        `).join('');

        // Centros de salud
        hospitalLayer.clearLayers();
        data.hospitals.forEach((h) => {
            L.marker([h.lat, h.lng], {
                icon: L.divIcon({ className: '', iconSize: [26, 26], iconAnchor: [13, 13], html: '<div class="lm-hospital">H</div>' }),
            }).bindPopup(`<div class="lm-popup"><h4>${esc(h.name)}</h4>${h.phone ? `<div class="lm-muted">Tel. ${esc(h.phone)}</div>` : ''}</div>`)
              .addTo(hospitalLayer);
        });

        // Emergencias pendientes
        pendingLayer.clearLayers();
        data.pending.forEach((p) => {
            L.marker([p.lat, p.lng], {
                icon: L.divIcon({ className: '', iconSize: [22, 22], iconAnchor: [11, 11], html: '<div class="lm-alert">!</div>' }),
                zIndexOffset: 2000,
            }).bindPopup(`
                <div class="lm-popup">
                    <h4>${esc(p.type)} pendiente</h4>
                    <span class="lm-badge" style="background:#dc2626">Prioridad ${esc(p.priority)}</span>
                    <div style="margin-top:.35rem">${esc(p.address)}</div>
                    ${p.description ? `<div class="lm-muted">${esc(p.description)}</div>` : ''}
                    <hr><div class="lm-muted">${esc(p.code)} · recibido ${esc(p.since)}</div>
                </div>`).addTo(pendingLayer);
        });

        // Encuadrar el mapa en las unidades la primera vez
        if (firstLoad && data.units.length) {
            map.fitBounds(L.latLngBounds(data.units.map((u) => [u.lat, u.lng])).pad(0.25));
            firstLoad = false;
        }
    };

    // Clic en la lista: volar hacia la unidad
    document.getElementById('lm-list').addEventListener('click', (e) => {
        const item = e.target.closest('.lm-item');
        if (!item) return;
        const m = unitMarkers[item.dataset.id];
        if (m) { map.flyTo(m.getLatLng(), 15, { duration: 0.8 }); setTimeout(() => m.openPopup(), 850); }
    });

    render(@js($this->getMapData()));

    // Si se cae la conexión, el mapa conserva lo último y muestra el aviso
    const timer = setInterval(async () => {
        if (!document.getElementById('lm-map')) return clearInterval(timer);
        const offline = document.getElementById('lm-offline');
        try {
            render(await $wire.getMapData());
            offline.style.display = 'none';
        } catch (e) {
            offline.style.display = '';
        }
    }, 3000);
</script>
@endscript