<?php

use Illuminate\Support\Facades\Broadcast;

/*
| Canales privados de tiempo real (Laravel Reverb). Cada usuario escucha solo el suyo:
| ahí le llegan los mensajes nuevos del chat (evento "mensaje.nuevo").
*/
Broadcast::channel('usuarios.{id}', function ($usuario, $id) {
    return (string) $usuario->id === (string) $id;
}, ['guards' => ['api']]);
