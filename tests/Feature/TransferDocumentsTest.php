<?php

use App\Enums\AmbulanceStatus;
use App\Enums\AmbulanceType;
use App\Enums\DispatchPriority;
use App\Enums\DispatchType;
use App\Enums\UserRole;
use App\Mail\PatientIncoming;
use App\Models\Ambulance;
use App\Models\Dispatch;
use App\Models\DispatchDocument;
use App\Models\Hospital;
use App\Models\User;
use App\Services\Transfers\TransferDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
| Documentos del traslado (orden médica, firma de recibido, hoja impresa) y
| aviso automático al hospital.
*/

beforeEach(function () {
    config(['app.demo' => false]);
    Storage::fake('local');
    Http::fake(['*' => Http::response([], 503)]);   // sin servidor de rutas: línea recta
    Cache::flush();
});

function trasladoEnCurso(?string $hospitalEmail = 'emergencias@hospital.cr'): array
{
    $unit = Ambulance::create([
        'code' => 'SAR-03', 'plate' => 'AMB-1103', 'type' => AmbulanceType::Advanced, 'status' => AmbulanceStatus::Available,
        'latitude' => 10.4470, 'longitude' => -84.0780,
    ]);
    $unit->crewUrl();
    $hospital = Hospital::create(['name' => 'Hospital de Guápiles', 'email' => $hospitalEmail, 'phone' => '2713-9000', 'latitude' => 10.2150, 'longitude' => -83.7880]);
    $dispatch = Dispatch::create([
        'type' => DispatchType::Emergency, 'priority' => DispatchPriority::High, 'pickup_address' => 'Chilamate',
        'pickup_latitude' => 10.4470, 'pickup_longitude' => -84.0780, 'patient_name' => 'Lucía Méndez', 'patient_age' => 67,
        'chief_complaint' => 'breathing', 'triage' => ['breathing' => 0],
        'ambulance_id' => $unit->id, 'hospital_id' => $hospital->id,
    ]);

    return [$unit->fresh(), $dispatch, $hospital];
}

function avanzarHasta(Ambulance $unit, Dispatch $dispatch, array $steps): void
{
    foreach ($steps as $step) {
        test()->postJson("/unidad/{$unit->crew_token}/paso", ['dispatch' => $dispatch->id, 'step' => $step])->assertOk();
    }
}

it('al salir hacia el hospital se le avisa por correo con la hora estimada', function () {
    Mail::fake();
    [$unit, $dispatch] = trasladoEnCurso();

    avanzarHasta($unit, $dispatch, ['accept', 'depart', 'arrive', 'transport']);

    Mail::assertSent(PatientIncoming::class, function (PatientIncoming $mail) {
        return $mail->hasTo('emergencias@hospital.cr')
            && $mail->minutes > 20 && $mail->minutes < 90
            && $mail->trackingUrl !== null
            && str_contains($mail->envelope()->subject, 'SAR-03');
    });

    expect($dispatch->fresh()->hospital_notified_at)->not->toBeNull()
        ->and($dispatch->events()->pluck('note')->implode(' '))->toContain('Aviso enviado a Hospital de Guápiles');
});

it('el correo al hospital lleva motivo y alertas del guion', function () {
    [$unit, $dispatch] = trasladoEnCurso();

    $html = (new PatientIncoming($dispatch->fresh(), $unit, 35, 'https://despacho.test/seguimiento/x'))->render();

    expect($html)->toContain('Lucía Méndez')->toContain('Dificultad para respirar')->toContain('No respira con normalidad')->toContain('35 min');
});

it('si el hospital no tiene correo queda anotado para avisar por teléfono', function () {
    Mail::fake();
    [$unit, $dispatch] = trasladoEnCurso(hospitalEmail: null);

    avanzarHasta($unit, $dispatch, ['accept', 'depart', 'arrive', 'transport']);

    Mail::assertNothingSent();
    expect($dispatch->events()->pluck('note')->implode(' '))->toContain('avisar por teléfono (2713-9000)');
});

it('la tripulación sube la foto de la orden médica desde el celular', function () {
    [$unit, $dispatch] = trasladoEnCurso();

    $this->post("/unidad/{$unit->crew_token}/documento", [
        'dispatch' => $dispatch->id, 'type' => 'medical_order', 'file' => UploadedFile::fake()->create('orden.jpg', 300, 'image/jpeg'),
    ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('dispatch.documents.0', 'Orden médica');

    $document = DispatchDocument::sole();
    expect($document->uploaded_via)->toBe('crew');
    Storage::disk('local')->assertExists($document->path);

    $this->post("/unidad/{$unit->crew_token}/documento", [
        'dispatch' => $dispatch->id, 'type' => 'medical_order', 'file' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'),
    ], ['Accept' => 'application/json'])->assertUnprocessable();
});

it('la firma de recibido solo se pide en el hospital y no acepta firmas vacías', function () {
    Mail::fake();
    [$unit, $dispatch] = trasladoEnCurso();
    $url = "/unidad/{$unit->crew_token}/recibido";
    $firma = 'data:image/png;base64,' . base64_encode("\x89PNG\r\n\x1a\n" . random_bytes(3000));

    // Antes de llegar al hospital
    $this->postJson($url, ['dispatch' => $dispatch->id, 'name' => 'Dra. Solís', 'role' => 'Médico', 'signature' => $firma])->assertUnprocessable();

    avanzarHasta($unit, $dispatch, ['accept', 'depart', 'arrive', 'transport', 'hospital']);

    $vacia = 'data:image/png;base64,' . base64_encode("\x89PNG\r\n\x1a\n" . str_repeat('a', 200));
    $this->postJson($url, ['dispatch' => $dispatch->id, 'name' => 'Dra. Solís', 'role' => 'Médico', 'signature' => $vacia])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'La firma está vacía. Pida que firme con el dedo.');

    $this->postJson($url, ['dispatch' => $dispatch->id, 'name' => 'Dra. Carmen Solís', 'role' => 'Médico', 'signature' => $firma])->assertOk();

    $dispatch->refresh();
    expect($dispatch->received_by_name)->toBe('Dra. Carmen Solís')
        ->and($dispatch->received_at)->not->toBeNull();
    Storage::disk('local')->assertExists($dispatch->received_signature_path);
});

it('la hoja de traslado reúne paciente, tiempos, documentos y firma', function () {
    Mail::fake();
    [$unit, $dispatch] = trasladoEnCurso();
    avanzarHasta($unit, $dispatch, ['accept', 'depart', 'arrive', 'transport', 'hospital']);
    app(TransferDocuments::class)->receive($dispatch->fresh(), 'Dra. Carmen Solís', 'Médico', 'data:image/png;base64,' . base64_encode("\x89PNG\r\n\x1a\n" . random_bytes(3000)));
    app(TransferDocuments::class)->store($dispatch, UploadedFile::fake()->create('orden.pdf', 120, 'application/pdf'), 'medical_order', 'crew');

    $this->actingAs(User::factory()->create(['role' => UserRole::Dispatcher]));

    $this->get("/servicios/{$dispatch->id}/hoja-de-traslado")
        ->assertOk()
        ->assertSee('Hoja de traslado')
        ->assertSee('Lucía Méndez')
        ->assertSee('No respira con normalidad')
        ->assertSee('Dra. Carmen Solís')
        ->assertSee('Orden médica')
        ->assertSee('data:image/png;base64', false)
        ->assertSee('Aviso enviado a Hospital de Guápiles');
});

it('consulta no ve documentos ni la hoja; sin sesión pide iniciar', function () {
    [, $dispatch] = trasladoEnCurso();
    $document = app(TransferDocuments::class)->store($dispatch, UploadedFile::fake()->create('orden.pdf', 120, 'application/pdf'), 'medical_order');

    $this->get("/documentos-traslado/{$document->id}")->assertRedirect();

    $this->actingAs(User::factory()->create(['role' => UserRole::Viewer]));
    $this->get("/documentos-traslado/{$document->id}")->assertForbidden();
    $this->get("/servicios/{$dispatch->id}/hoja-de-traslado")->assertForbidden();

    $this->actingAs(User::factory()->create(['role' => UserRole::Dispatcher]));
    $this->get("/documentos-traslado/{$document->id}")->assertOk();
});
