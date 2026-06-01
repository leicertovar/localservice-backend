<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recordatorio de Cita - LocalService</title>
    <style>
        body { font-family: 'Inter', 'Segoe UI', Verdana, sans-serif; background-color: #f3f4f6; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); }
        .header { background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); padding: 40px 20px; text-align: center; color: #fff; }
        .header h1 { margin: 0; font-size: 26px; font-weight: 700; }
        .content { padding: 40px 30px; color: #1f2937; line-height: 1.7; }
        .info-box { background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 12px; padding: 20px; margin: 20px 0; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; }
        .info-label { color: #6b7280; }
        .info-value { font-weight: 600; color: #1f2937; }
        .footer { background: #f9fafb; padding: 20px; text-align: center; font-size: 12px; color: #9ca3af; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔔 Recordatorio de Servicio</h1>
            <p style="margin: 8px 0 0; opacity: 0.9;">Tienes un servicio programado para mañana</p>
        </div>
        <div class="content">
            <p style="font-size: 18px; font-weight: 600;">Hola, {{ $solicitud->proveedor->nombre }}.</p>
            <p>Te recordamos que tienes un servicio confirmado programado para <strong>mañana</strong>. Por favor asegúrate de estar listo a tiempo.</p>

            <div class="info-box">
                <div class="info-row">
                    <span class="info-label">Servicio:</span>
                    <span class="info-value">{{ $solicitud->servicio?->nombre ?? 'Servicio Personalizado' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Cliente:</span>
                    <span class="info-value">{{ $solicitud->cliente->nombre }} {{ $solicitud->cliente->apellido }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Fecha:</span>
                    <span class="info-value">{{ $solicitud->fecha }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Hora:</span>
                    <span class="info-value">{{ $solicitud->hora }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Dirección:</span>
                    <span class="info-value">{{ $solicitud->direccion }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Teléfono cliente:</span>
                    <span class="info-value">{{ $solicitud->telefono }}</span>
                </div>
                @if($solicitud->monto_cotizado)
                <div class="info-row">
                    <span class="info-label">Monto acordado:</span>
                    <span class="info-value">{{ $solicitud->monto_cotizado }}</span>
                </div>
                @endif
            </div>

            <p style="font-size: 14px; color: #6b7280;">Si necesitas comunicarte con el cliente, puedes hacerlo a través de tu panel de control en LocalService.</p>
        </div>
        <div class="footer">
            <p>Este es un recordatorio automático de LocalService.</p>
            <p>&copy; {{ date('Y') }} LocalService. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
