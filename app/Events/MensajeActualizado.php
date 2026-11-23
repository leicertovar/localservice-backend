<?php

namespace App\Events;

use App\Models\Mensaje;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Un mensaje del chat se editó o se eliminó, en tiempo real.
 * "Para todos" va a los dos usuarios; "para mí", solo a los dispositivos de quien lo eliminó.
 */
class MensajeActualizado implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @param string[] $usuarios IDs de los usuarios que deben enterarse */
    public function __construct(public Mensaje $mensaje, public array $usuarios)
    {
    }

    public function broadcastOn(): array
    {
        return array_map(fn ($id) => new PrivateChannel('usuarios.' . $id), array_unique($this->usuarios));
    }

    public function broadcastAs(): string
    {
        return 'mensaje.actualizado';
    }

    public function broadcastWith(): array
    {
        return ['mensaje' => $this->mensaje->loadMissing('emisor:id,nombre,apellido')->toArray()];
    }
}
