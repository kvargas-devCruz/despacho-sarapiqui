@php
    $latPath = $getLatitudeStatePath();
    $lngPath = $getLongitudeStatePath();
    $addressPath = $getAddressStatePath();
    [$centerLat, $centerLng] = $getDefaultCenter();
    $disabled = $isDisabled();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @assets
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <style>
            .lp-map { height: 320px; border-radius: .75rem; border: 1px solid rgb(229 231 235); z-index: 0; }
            .dark .lp-map { border-color: rgb(39 39 42); }
            .dark .lp-map .leaflet-tile-pane { filter: invert(1) hue-rotate(180deg) brightness(.9) contrast(.9) saturate(.6); }
            .lp-bar { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; margin-bottom: .5rem; font-size: .85rem; }
            .lp-msg { color: rgb(107 114 128); }
            .lp-msg.ok { color: rgb(22 101 52); }
            .lp-msg.warn { color: rgb(180 83 9); }
            .dark .lp-msg { color: rgb(161 161 170); }
            .dark .lp-msg.ok { color: rgb(134 239 172); }
            .dark .lp-msg.warn { color: rgb(252 211 77); }
        </style>
    @endassets

    <div
        wire:ignore
        x-data="{
            lat: $wire.$entangle(@js($latPath), true),
            lng: $wire.$entangle(@js($lngPath), true),
            disabled: @js($disabled),
            map: null,
            marker: null,
            message: '',
            tone: '',
            searching: false,
            init() {
                const start = this.hasPoint() ? [Number(this.lat), Number(this.lng)] : [{{ $centerLat }}, {{ $centerLng }}];
                this.map = L.map(this.$refs.map, { scrollWheelZoom: false }).setView(start, this.hasPoint() ? 16 : 11);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19, attribution: '&copy; OpenStreetMap',
                }).addTo(this.map);

                if (this.hasPoint()) this.place(Number(this.lat), Number(this.lng), false);
                if (! this.disabled) this.map.on('click', (e) => this.place(e.latlng.lat, e.latlng.lng, true));

                // Si los campos cambian (paciente frecuente o se escriben a mano), se mueve el pin
                this.$watch('lat', () => this.sync());
                this.$watch('lng', () => this.sync());

                // El mapa se dibuja mal si nace dentro de una sección oculta
                setTimeout(() => this.map.invalidateSize(), 300);
            },
            hasPoint() {
                return this.lat !== null && this.lat !== '' && this.lng !== null && this.lng !== '' && ! isNaN(this.lat) && ! isNaN(this.lng);
            },
            sync() {
                if (! this.hasPoint()) return;
                const lat = Number(this.lat), lng = Number(this.lng);
                if (this.marker && this.marker.getLatLng().equals([lat, lng])) return;
                this.place(lat, lng, false);
                this.map.setView([lat, lng], Math.max(this.map.getZoom(), 15));
            },
            place(lat, lng, write) {
                if (! this.marker) {
                    this.marker = L.marker([lat, lng], { draggable: ! this.disabled }).addTo(this.map);
                    this.marker.on('dragend', () => {
                        const p = this.marker.getLatLng();
                        this.write(p.lat, p.lng);
                    });
                } else {
                    this.marker.setLatLng([lat, lng]);
                }
                if (write) this.write(lat, lng);
            },
            write(lat, lng) {
                this.lat = Math.round(lat * 1e7) / 1e7;
                this.lng = Math.round(lng * 1e7) / 1e7;
                this.message = 'Punto marcado. Confírmelo con quien llama.';
                this.tone = 'ok';
            },
            async search() {
                const address = ($wire.get(@js($addressPath)) || '').trim();
                if (! address) { this.message = 'Escriba primero la dirección.'; this.tone = 'warn'; return; }
                this.searching = true;
                this.message = 'Buscando…'; this.tone = '';
                try {
                    const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=cr&accept-language=es&q='
                        + encodeURIComponent(address + ', Costa Rica');
                    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    const found = res.ok ? await res.json() : [];
                    if (found.length) {
                        const lat = Number(found[0].lat), lng = Number(found[0].lon);
                        this.place(lat, lng, true);
                        this.map.setView([lat, lng], 16);
                        this.message = 'Punto aproximado: ajústelo arrastrando el pin y confírmelo con quien llama.';
                        this.tone = 'warn';
                    } else {
                        this.message = 'No se encontró la dirección. Marque el punto tocando el mapa.';
                        this.tone = 'warn';
                    }
                } catch (e) {
                    this.message = 'Sin conexión con el buscador. Marque el punto tocando el mapa.';
                    this.tone = 'warn';
                } finally {
                    this.searching = false;
                }
            },
        }"
    >
        <div class="lp-bar" x-show="! disabled">
            <x-filament::button size="sm" color="gray" icon="heroicon-m-magnifying-glass" x-on:click="search()" x-bind:disabled="searching">
                Buscar la dirección en el mapa
            </x-filament::button>
            <span class="lp-msg" x-bind:class="tone" x-text="message || 'Toque el mapa o arrastre el pin hasta el punto exacto.'"></span>
        </div>
        <div x-ref="map" class="lp-map"></div>
    </div>
</x-dynamic-component>
