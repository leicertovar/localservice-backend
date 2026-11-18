<?php

namespace App\Events;

use App\Models\Mensaje;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Mensaje nuevo del chat, en tiempo real (Laravel Reverb).
 * Se envía al canal privado del receptor y al del emisor (sus otros dispositivos).
 * ShouldBroadcastNow: se publica en la misma petición, sin cola de trabajos.
 */
class MensajeEnviado implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Mensaje $mensaje)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('usuarios.' . $this->mensaje->receptor_id),
            new PrivateChannel('usuarios.' . $this->mensaje->emisor_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'mensaje.nuevo';
    }

    public function broadcastWith(): array
    {
        return ['mensaje' => $this->mensaje->loadMissing('emisor:id,nombre,apellido')->toArray()];
    }
}
