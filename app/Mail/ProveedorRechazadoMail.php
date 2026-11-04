<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProveedorRechazadoMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $nombre;
    public $nota;

    // Recibe el nombre y no el modelo: el usuario se elimina antes de que el worker envíe el correo
    public function __construct(string $nombre, ?string $nota = null)
    {
        $this->nombre = $nombre;
        $this->nota = $nota;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Actualización sobre tu solicitud en LocalService',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.proveedor_rechazado',
        );
    }
}
