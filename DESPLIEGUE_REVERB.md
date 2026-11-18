# Chat en tiempo real (Laravel Reverb) en Railway

El chat usa **Laravel Reverb** (WebSocket, protocolo de Pusher). La API publica el evento
`mensaje.nuevo` en el canal privado `usuarios.{id}` del emisor y del receptor; la app y el web
lo reciben al instante. Si Reverb no está disponible, el envío **no falla**: los clientes siguen
consultando cada 5 s (como antes).

## 1. Crear el servicio de Reverb

En el mismo proyecto de Railway: **New → GitHub Repo →** el mismo repositorio del backend.

- **Start command:** `php artisan reverb:start --host=0.0.0.0 --port=$PORT`
- **Networking → Generate Domain** (dominio público, p. ej. `localservice-reverb.up.railway.app`).
- No necesita migraciones ni volumen.
- Reverb requiere la extensión PHP **pcntl** (señales de apagado). Está declarada en `composer.json`
  (`"ext-pcntl": "*"`) para que Railway la instale; sin ella el arranque falla con
  `Undefined constant ...\SIGINT`. En Windows no existe: en local usar
  `composer install --ignore-platform-req=ext-pcntl`.

## 2. Variables

Generar una clave y un secreto aleatorios (p. ej. `php -r "echo bin2hex(random_bytes(16));"`).

**En los dos servicios (API y Reverb), iguales:**

```
REVERB_APP_ID=localservice
REVERB_APP_KEY=<clave>
REVERB_APP_SECRET=<secreto>
BROADCAST_CONNECTION=reverb
```

**Solo en el servicio de la API** (publica a Reverb por la red privada de Railway):

```
REVERB_HOST=<nombre-del-servicio-reverb>.railway.internal
REVERB_PORT=<valor de PORT del servicio Reverb, p. ej. 8080>
REVERB_SCHEME=http
```

> Para fijar el puerto, en el servicio Reverb se puede definir `PORT=8080` y usar ese mismo
> valor en `REVERB_PORT` de la API.

## 3. Clientes (app móvil y web)

En `localservice-movil/.env.production` y en las variables del hosting del web:

```
VITE_REVERB_APP_KEY=<clave>
VITE_REVERB_HOST=<dominio público de Reverb, sin https://>
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

Después de cambiarlas, volver a compilar el web y la APK (Vite las incrusta al compilar).

## 4. Verificar

- Logs del servicio Reverb: `Starting server on 0.0.0.0:...`.
- Abrir el mismo chat en dos dispositivos: el mensaje debe aparecer en ~1 s.
- `POST /api/broadcasting/auth` responde 200 con el token JWT (403 si el canal es de otro usuario).

## Desarrollo local

```
php artisan reverb:start --port=6001 --debug
```

con `REVERB_HOST=127.0.0.1`, `REVERB_PORT=6001`, `REVERB_SCHEME=http` en el `.env` del backend y
`VITE_REVERB_HOST=localhost` (web) o `10.0.2.2` (emulador Android) en los clientes.
