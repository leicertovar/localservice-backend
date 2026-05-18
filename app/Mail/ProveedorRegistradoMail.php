<?php

namespace App\Mail;

use App\Models\Usuario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProveedorRegistradoMail extends Mailable
{
    use Queueable, SerializesModels;

    // Propiedad accesible desde la vista Blade
    public $usuario;

    /**
     * Crear una nueva instancia de mensaje.
     */
    public function __construct(Usuario $usuario)
    {
        $this->usuario = $usuario;
    }

    /**
     * Obtener el sobre del mensaje (asunto del correo).
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '¡Registro Exitoso! Documentos en Proceso de Verificación | LocalService',
        );
    }

    /**
     * Obtener la definición de contenido y enlazar con la plantilla Blade en español.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.proveedor_registrado',
        );
    }
}
