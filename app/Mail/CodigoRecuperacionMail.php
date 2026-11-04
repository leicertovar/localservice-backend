<?php

namespace App\Mail;

use App\Models\Usuario;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// Cifrado: el código o enlace no debe quedar legible en la tabla jobs
class CodigoRecuperacionMail extends Mailable implements ShouldQueue, ShouldBeEncrypted
{
    use Queueable, SerializesModels;

    // Propiedades accesibles desde la vista Blade
    public $usuario;
    public $codigo;
    public $minutosValidez;

    /**
     * Crear una nueva instancia de mensaje.
     */
    public function __construct(Usuario $usuario, string $codigo, int $minutosValidez)
    {
        $this->usuario = $usuario;
        $this->codigo = $codigo;
        $this->minutosValidez = $minutosValidez;
    }

    /**
     * Obtener el sobre del mensaje (asunto del correo).
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu código para restablecer la contraseña | LocalService',
        );
    }

    /**
     * Obtener la definición de contenido y enlazar con la plantilla Blade del código.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.codigo_recuperacion',
        );
    }
}
