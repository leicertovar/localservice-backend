<?php

namespace App\Mail;

use App\Models\Usuario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RestablecerPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    // Propiedades accesibles desde la vista Blade
    public $usuario;
    public $token;
    public $email;

    /**
     * Crear una nueva instancia de mensaje.
     */
    public function __construct(Usuario $usuario, string $token, string $email)
    {
        $this->usuario = $usuario;
        $this->token = $token;
        $this->email = $email;
    }

    /**
     * Obtener el sobre del mensaje (asunto del correo).
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Restablecer Contraseña de tu Cuenta | LocalService',
        );
    }

    /**
     * Obtener la definición de contenido y enlazar con la plantilla Blade de restablecimiento.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.restablecer_password',
        );
    }
}
