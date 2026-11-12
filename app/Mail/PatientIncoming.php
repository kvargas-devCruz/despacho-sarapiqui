<?php

namespace App\Mail;

use App\Models\Ambulance;
use App\Models\Dispatch;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Aviso al hospital: paciente en camino */
class PatientIncoming extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Dispatch $dispatch,
        public Ambulance $unit,
        public ?int $minutes,
        public ?string $trackingUrl,
    ) {}

    public function envelope(): Envelope
    {
        $priority = mb_strtoupper($this->dispatch->priority->getLabel());

        return new Envelope(subject: "[{$priority}] Paciente en camino · {$this->unit->code}" . ($this->minutes ? " · llega en ~{$this->minutes} min" : ''));
    }

    public function content(): Content
    {
        $alerts = collect(config('operations.intake.questions'))
            ->filter(fn (array $q, string $key) => isset($this->dispatch->triage[$key]) && (bool) $this->dispatch->triage[$key] === $q['when'])
            ->map(fn (array $q) => ucfirst($q['reason']))
            ->values()
            ->all();

        return new Content(view: 'mail.patient-incoming', with: [
            'alerts' => $alerts,
            'complaint' => $this->dispatch->chief_complaint ? config("operations.intake.complaints.{$this->dispatch->chief_complaint}.label") : null,
        ]);
    }
}
