<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Hoja de traslado {{ $dispatch->code }}</title>
    <style>
        @page { size: letter; margin: 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "Segoe UI", Roboto, Arial, sans-serif; color: #0f172a; font-size: 12.5px; background: #f1f5f9; }
        .page { max-width: 820px; margin: 18px auto; background: #fff; padding: 28px 32px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,.08); }
        header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0d9488; padding-bottom: 12px; margin-bottom: 14px; }
        header img { height: 38px; }
        h1 { font-size: 20px; margin: 6px 0 0; }
        .code { text-align: right; }
        .code b { font-size: 16px; display: block; }
        .tag { display: inline-block; padding: 2px 8px; border-radius: 6px; font-weight: 700; font-size: 11px; color: #fff; }
        .high { background: #dc2626; } .medium { background: #d97706; } .low { background: #16a34a; }
        h2 { font-size: 12px; text-transform: uppercase; letter-spacing: .6px; color: #0f766e; margin: 16px 0 6px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 20px; }
        .row { display: flex; gap: 6px; padding: 3px 0; border-bottom: 1px dotted #e2e8f0; }
        .row span:first-child { color: #64748b; min-width: 120px; }
        .alerts { color: #b91c1c; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 5px 6px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        th { color: #64748b; font-weight: 600; font-size: 11px; }
        .sign { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 8px; }
        .sign .box { border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px; min-height: 120px; }
        .sign img { max-width: 100%; max-height: 90px; display: block; margin: 0 auto; }
        .muted { color: #64748b; }
        .toolbar { max-width: 820px; margin: 12px auto 0; display: flex; justify-content: flex-end; gap: 8px; }
        .toolbar button { background: #0d9488; color: #fff; border: 0; border-radius: 8px; padding: 9px 14px; font-weight: 600; cursor: pointer; font-family: inherit; }
        footer { margin-top: 18px; font-size: 10.5px; color: #94a3b8; text-align: center; }
        @media print { body { background: #fff; } .page { box-shadow: none; margin: 0; max-width: none; border-radius: 0; padding: 0; } .toolbar { display: none; } }
    </style>
</head>
<body>
    <div class="toolbar"><button type="button" onclick="window.print()">Imprimir o guardar en PDF</button></div>
    <div class="page">
        <header>
            <div>
                <img src="{{ asset('images/logo.svg') }}" alt="Despacho Sarapiquí">
                <h1>Hoja de traslado</h1>
            </div>
            <div class="code">
                <b>{{ $dispatch->code }}</b>
                <span class="tag {{ $dispatch->priority->value }}">{{ mb_strtoupper($dispatch->priority->getLabel()) }}</span>
                <div class="muted">{{ $dispatch->type->getLabel() }} · {{ $dispatch->status->getLabel() }}</div>
                <div class="muted">{{ $dispatch->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</div>
            </div>
        </header>

        <h2>Paciente</h2>
        <div class="grid">
            <div class="row"><span>Nombre</span><b>{{ $dispatch->patient_name ?: $dispatch->patient?->full_name ?: 'Sin identificar' }}</b></div>
            <div class="row"><span>Edad</span>{{ $dispatch->patient_age ? $dispatch->patient_age . ' años' : '—' }}</div>
            <div class="row"><span>Motivo</span>{{ $complaint ?? '—' }}</div>
            <div class="row"><span>Teléfono de contacto</span>{{ $dispatch->caller_phone ?: '—' }}</div>
        </div>
        @if ($alerts)
            <p class="alerts">Alertas del guion: {{ implode(' · ', $alerts) }}</p>
        @endif
        @if ($dispatch->description)
            <p><span class="muted">Descripción:</span> {{ $dispatch->description }}</p>
        @endif
        @if ($dispatch->patient?->care_notes)
            <p><span class="muted">Cuidados del paciente frecuente:</span> {{ $dispatch->patient->care_notes }}</p>
        @endif

        <h2>Traslado</h2>
        <div class="grid">
            <div class="row"><span>Recogida</span>{{ $dispatch->pickup_address }}</div>
            <div class="row"><span>Destino</span>{{ $dispatch->hospital?->name ?? '—' }}</div>
            <div class="row"><span>Unidad</span>{{ $dispatch->ambulance ? $dispatch->ambulance->code . ' · ' . $dispatch->ambulance->type?->getLabel() . ' · ' . $dispatch->ambulance->plate : '—' }}</div>
            <div class="row"><span>Aviso al hospital</span>{{ $dispatch->hospital_notified_at ? $dispatch->hospital_notified_at->timezone(config('app.timezone'))->format('H:i') : 'No enviado' }}</div>
        </div>

        <h2>Tiempos</h2>
        <table>
            <thead><tr><th style="width:90px">Hora</th><th>Evento</th><th style="width:160px">Registró</th></tr></thead>
            <tbody>
                @foreach ($dispatch->events as $event)
                    <tr>
                        <td>{{ $event->created_at->timezone(config('app.timezone'))->format('H:i') }}</td>
                        <td>{{ $event->note ?: $event->status?->getLabel() }}</td>
                        <td class="muted">{{ $event->user?->name ?? 'Tripulación / sistema' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h2>Documentos adjuntos</h2>
        @forelse ($dispatch->documents as $document)
            <div class="row"><span>{{ $document->typeLabel() }}</span>{{ $document->original_name }} · {{ $document->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }} · {{ $document->uploaded_via === 'crew' ? 'desde la ambulancia' : ($document->uploader?->name ?? 'despacho') }}</div>
        @empty
            <p class="muted">Sin documentos adjuntos.</p>
        @endforelse

        <h2>Entrega del paciente</h2>
        <div class="sign">
            <div class="box">
                <div class="muted">Recibido por</div>
                @if ($dispatch->received_at)
                    <b>{{ $dispatch->received_by_name }}</b> · {{ $dispatch->received_by_role }}<br>
                    <span class="muted">{{ $dispatch->received_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }} · {{ $dispatch->hospital?->name }}</span>
                @else
                    <span class="muted">Pendiente</span>
                @endif
            </div>
            <div class="box">
                <div class="muted">Firma</div>
                @if ($signature)
                    <img src="{{ $signature }}" alt="Firma de recibido">
                @endif
            </div>
        </div>

        <footer>Generado por Despacho Sarapiquí · Emergencias Zurquí · {{ now()->timezone(config('app.timezone'))->format('d/m/Y H:i') }} · Documento con datos de salud: uso interno.</footer>
    </div>
</body>
</html>
