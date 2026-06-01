<?php

namespace App\Mail;

use App\Models\SolicitudServicio;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RecordatorioCitaMail extends Mailable
{
    use Queueable, SerializesModels;

    public $solicitud;

    public function __construct(SolicitudServicio $solicitud)
    {
        $this->solicitud = $solicitud;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recordatorio: Servicio programado para mañana - LocalService',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.recordatorio_cita',
        );
    }
}
