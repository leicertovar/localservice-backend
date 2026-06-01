<?php

namespace App\Mail;

use App\Models\Usuario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProveedorRechazadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public $usuario;
    public $nota;

    public function __construct(Usuario $usuario, ?string $nota = null)
    {
        $this->usuario = $usuario;
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
