<?php

namespace App\Http\Controllers;

use App\Models\Ambulance;
use App\Models\AmbulanceLocation;
use App\Models\Dispatch;
use App\Services\Crew\CrewWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * App de tripulación (web instalable en el celular). Se entra con el enlace
 * privado de la unidad, que se genera y se anula desde Unidades → Tripulación.
 *
 * La tripulación ve su servicio, comparte el GPS real y marca cada paso.
 */
class CrewController extends Controller
{
    public function __construct(private CrewWorkflow $workflow) {}

    public function show(string $token): View
    {
        $unit = $this->unit($token);

        return view('crew.show', [
            'unit' => $unit,
            'token' => $token,
            'state' => $this->payload($unit),
        ]);
    }

    /** La app lo consulta cada pocos segundos */
    public function state(string $token): JsonResponse
    {
        return response()->json($this->payload($this->unit($token)));
    }

    /** GPS real del celular */
    public function location(Request $request, string $token): JsonResponse
    {
        $unit = $this->unit($token);

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:8,11.3'],
            'longitude' => ['required', 'numeric', 'between:-86,-82.5'],
            'speed' => ['nullable', 'numeric', 'min:0', 'max:80'],       // m/s
            'heading' => ['nullable', 'numeric', 'min:0', 'max:360'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:10000'],  // metros
        ], [
            'latitude.between' => 'La ubicación está fuera de Costa Rica.',
            'longitude.between' => 'La ubicación está fuera de Costa Rica.',
        ]);

        $speedKmh = isset($data['speed']) ? round($data['speed'] * 3.6, 1) : null;

        $unit->forceFill([
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'speed' => $speedKmh ?? 0,
            'heading' => $data['heading'] ?? $unit->heading,
            'gps_accuracy' => $data['accuracy'] ?? null,
            'last_location_at' => now(),
            'is_simulated' => false,   // el simulador ya no mueve esta unidad
        ])->save();

        AmbulanceLocation::create([
            'ambulance_id' => $unit->id,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'speed' => $speedKmh,
            'heading' => $data['heading'] ?? null,
            'accuracy' => $data['accuracy'] ?? null,
            'recorded_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    /** La tripulación marca el siguiente paso del servicio */
    public function advance(Request $request, string $token): JsonResponse
    {
        $unit = $this->unit($token);

        $data = $request->validate([
            'dispatch' => ['required', 'integer'],
            'step' => ['required', 'string', 'in:' . implode(',', array_keys(CrewWorkflow::STEPS))],
        ]);

        try {
            $this->workflow->advance($unit, (int) $data['dispatch'], $data['step']);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage()] + $this->payload($unit->fresh()), 409);
        }

        return response()->json($this->payload($unit->fresh()));
    }

    /** Manifiesto para instalar la app con el enlace de esta unidad */
    public function manifest(string $token): JsonResponse
    {
        $unit = $this->unit($token);
        $start = route('crew.show', $token, false);

        return response()->json([
            'name' => "Tripulación {$unit->code} · Despacho Sarapiquí",
            'short_name' => $unit->code,
            'description' => 'Servicio asignado, GPS y estados de la unidad',
            'id' => $start,
            'start_url' => $start,
            'scope' => $start,
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#0f172a',
            'theme_color' => '#0d9488',
            'lang' => 'es',
            'icons' => [
                ['src' => asset('images/app-icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => asset('images/app-icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }

    private function unit(string $token): Ambulance
    {
        abort_if(strlen($token) < 40, 404);

        return Ambulance::where('crew_token', $token)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function payload(Ambulance $unit): array
    {
        $dispatch = Dispatch::with(['hospital', 'patient'])
            ->where('ambulance_id', $unit->id)
            ->whereIn('status', ['assigned', 'in_progress'])
            ->latest('id')
            ->first();

        $next = $this->workflow->next($unit, $dispatch);

        return [
            'unit' => [
                'code' => $unit->code,
                'type' => $unit->type?->getLabel(),
                'status' => $unit->status?->getLabel(),
                'status_key' => $unit->status?->value,
                'latitude' => $unit->latitude,
                'longitude' => $unit->longitude,
                'live_gps' => $unit->hasLiveGps(),
            ],
            'dispatch' => $dispatch ? $this->dispatch($dispatch) : null,
            'next' => $next ? ['key' => $next, 'label' => CrewWorkflow::STEPS[$next]['label']] : null,
            'steps' => $dispatch ? collect($this->workflow->stepsFor($dispatch))->map(fn (string $key) => [
                'key' => $key,
                'label' => CrewWorkflow::STEPS[$key]['done'],
            ])->all() : [],
            'server_time' => now()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function dispatch(Dispatch $dispatch): array
    {
        $alerts = collect(config('operations.intake.questions'))
            ->filter(fn (array $q, string $key) => isset($dispatch->triage[$key]) && (bool) $dispatch->triage[$key] === $q['when'])
            ->map(fn (array $q) => ucfirst($q['reason']))
            ->values()
            ->all();

        return [
            'id' => $dispatch->id,
            'code' => $dispatch->code,
            'type' => $dispatch->type->getLabel(),
            'priority' => $dispatch->priority->getLabel(),
            'priority_key' => $dispatch->priority->value,
            'status' => $dispatch->status->getLabel(),
            'address' => $dispatch->pickup_address,
            'latitude' => $dispatch->pickup_latitude,
            'longitude' => $dispatch->pickup_longitude,
            'phone' => $dispatch->caller_phone,
            'patient' => $dispatch->patient_name ?: $dispatch->patient?->full_name,
            'age' => $dispatch->patient_age,
            'complaint' => $dispatch->chief_complaint ? config("operations.intake.complaints.{$dispatch->chief_complaint}.label") : null,
            'alerts' => $alerts,
            'description' => $dispatch->description,
            'care_notes' => $dispatch->patient?->care_notes,
            'hospital' => $dispatch->hospital ? [
                'name' => $dispatch->hospital->name,
                'latitude' => $dispatch->hospital->latitude,
                'longitude' => $dispatch->hospital->longitude,
            ] : null,
            'assigned_at' => $dispatch->assigned_at?->format('H:i'),
        ];
    }
}
