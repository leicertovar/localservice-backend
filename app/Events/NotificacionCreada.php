<?php

namespace App\Events;

use App\Models\Notificacion;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Notificación nueva para un usuario, en tiempo real (contador, lista y aviso dentro de la app). */
class NotificacionCreada implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Notificacion $notificacion)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('usuarios.' . $this->notificacion->usuario_id)];
    }

    public function broadcastAs(): string
    {
        return 'notificacion.nueva';
    }

    public function broadcastWith(): array
    {
        return ['notificacion' => $this->notificacion->toArray()];
    }
}
