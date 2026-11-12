<!DOCTYPE html>
<html lang="es">
<body style="margin:0; padding:24px; background:#f1f5f9; font-family:Segoe UI, Roboto, Arial, sans-serif; color:#0f172a">
<div style="max-width:560px; margin:0 auto; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e2e8f0">
    <div style="background:{{ $dispatch->priority->value === 'high' ? '#dc2626' : ($dispatch->priority->value === 'medium' ? '#d97706' : '#16a34a') }}; color:#fff; padding:16px 20px">
        <div style="font-size:12px; letter-spacing:1px; text-transform:uppercase; opacity:.9">Paciente en camino · prioridad {{ mb_strtolower($dispatch->priority->getLabel()) }}</div>
        <div style="font-size:22px; font-weight:700; margin-top:4px">
            {{ $unit->code }}@if ($minutes) · llega en ~{{ $minutes }} min @endif
        </div>
    </div>
    <div style="padding:18px 20px; font-size:14px; line-height:1.55">
        <p style="margin:0 0 10px"><strong>Servicio:</strong> {{ $dispatch->code }} · {{ $dispatch->type->getLabel() }}</p>
        <p style="margin:0 0 10px">
            <strong>Paciente:</strong> {{ $dispatch->patient_name ?: 'Sin identificar' }}@if ($dispatch->patient_age) · {{ $dispatch->patient_age }} años @endif
        </p>
        @if ($complaint)
            <p style="margin:0 0 10px"><strong>Motivo:</strong> {{ $complaint }}</p>
        @endif
        @if ($alerts)
            <p style="margin:0 0 10px; color:#b91c1c"><strong>Alertas:</strong> {{ implode(' · ', $alerts) }}</p>
        @endif
        @if ($dispatch->description)
            <p style="margin:0 0 10px"><strong>Detalle:</strong> {{ $dispatch->description }}</p>
        @endif
        <p style="margin:0 0 16px"><strong>Unidad:</strong> {{ $unit->code }} ({{ $unit->type?->getLabel() }}) · Placa {{ $unit->plate }}</p>
        @if ($trackingUrl)
            <a href="{{ $trackingUrl }}" style="display:inline-block; background:#0d9488; color:#fff; text-decoration:none; padding:10px 16px; border-radius:8px; font-weight:600">Ver la ambulancia en el mapa</a>
        @endif
        <p style="margin:16px 0 0; font-size:12px; color:#64748b">
            Hora estimada aproximada. Aviso automático de Despacho Sarapiquí · Emergencias Zurquí.
        </p>
    </div>
</div>
</body>
</html>
