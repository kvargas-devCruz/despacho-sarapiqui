@php /** @var \App\Models\Ambulance $unit */ $url = $unit->crewUrl(); @endphp

<div style="display:flex; flex-direction:column; align-items:center; gap:14px; text-align:center; font-size:.9rem">
    <div
        wire:ignore
        x-data
        x-init="
            const draw = () => { $refs.qr.innerHTML = ''; new QRCode($refs.qr, { text: @js($url), width: 220, height: 220, correctLevel: QRCode.CorrectLevel.M }); };
            if (window.QRCode) { draw(); } else {
                const s = document.createElement('script');
                s.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
                s.onload = draw;
                document.head.appendChild(s);
            }
        "
        style="padding:12px; background:#fff; border-radius:12px; line-height:0"
    >
        <div x-ref="qr" style="width:220px; height:220px"></div>
    </div>

    <p style="margin:0; max-width:28rem">
        Escanee el código con el celular de la tripulación de <strong>{{ $unit->code }}</strong>.
        Luego toque <strong>«Instalar la app»</strong> (Android) o <strong>Compartir → «Agregar a inicio»</strong> (iPhone)
        y active el GPS.
    </p>

    <div
        x-data="{ copied: false }"
        style="display:flex; gap:8px; width:100%; max-width:28rem"
    >
        <input type="text" readonly value="{{ $url }}" style="flex:1; min-width:0; font-size:.75rem; padding:.45rem .6rem; border-radius:.5rem; border:1px solid rgba(127,127,127,.35); background:transparent">
        <x-filament::button size="sm" color="gray" x-on:click="navigator.clipboard.writeText(@js($url)); copied = true; setTimeout(() => copied = false, 2000)">
            <span x-text="copied ? 'Copiado' : 'Copiar'"></span>
        </x-filament::button>
    </div>

    <p style="margin:0; font-size:.78rem; opacity:.7; max-width:28rem">
        El enlace funciona como llave de la unidad: no lo comparta fuera de la tripulación.
        Si se pierde un celular o cambia la tripulación, use «Nuevo enlace» y el anterior deja de servir.
        @if ($unit->crew_token_created_at)
            Enlace creado el {{ $unit->crew_token_created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}.
        @endif
    </p>
</div>
