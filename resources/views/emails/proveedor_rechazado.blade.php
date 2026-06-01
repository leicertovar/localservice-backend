<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualización de tu solicitud - LocalService</title>
    <style>
        body { font-family: 'Inter', 'Segoe UI', Geneva, Verdana, sans-serif; background-color: #f3f4f6; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); }
        .header { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); padding: 40px 20px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 28px; font-weight: 700; }
        .header p { margin: 10px 0 0; font-size: 16px; opacity: 0.9; }
        .content { padding: 40px 30px; color: #1f2937; line-height: 1.6; }
        .greeting { font-size: 20px; font-weight: 600; color: #111827; margin-bottom: 20px; }
        .alert-box { background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; padding: 24px; margin: 24px 0; }
        .note-box { background-color: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 16px; margin: 16px 0; }
        .note-box p { margin: 0; font-size: 14px; color: #92400e; }
        .footer { background-color: #f9fafb; padding: 24px; text-align: center; border-top: 1px solid #e5e7eb; font-size: 12px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>LocalService</h1>
            <p>Resultado de la verificación de tu solicitud</p>
        </div>
        <div class="content">
            <div class="greeting">Hola, {{ $usuario->nombre }}.</div>
            <p>Lamentamos informarte que luego de revisar tu documentación, no ha sido posible aprobar tu cuenta de proveedor en <strong>LocalService</strong> en este momento.</p>

            <div class="alert-box">
                <strong style="color: #b91c1c;">Estado de tu solicitud: No aprobada</strong>
                <p style="margin: 8px 0 0; font-size: 14px; color: #991b1b;">
                    Nuestro equipo de verificación encontró inconvenientes con los documentos enviados.
                </p>
            </div>

            @if($nota)
            <div class="note-box">
                <strong style="display: block; margin-bottom: 6px; color: #78350f;">Nota del administrador:</strong>
                <p>{{ $nota }}</p>
            </div>
            @endif

            <p style="font-size: 14px; color: #4b5563;">
                Si crees que hubo un error o deseas corregir la información y volver a intentarlo, puedes registrarte nuevamente en nuestra plataforma con los documentos correctos.
            </p>

            <p style="font-size: 14px; color: #6b7280; margin-top: 24px; border-top: 1px dashed #e5e7eb; padding-top: 16px;">
                Gracias por tu interés en LocalService.
            </p>
        </div>
        <div class="footer">
            <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
            <p>&copy; {{ date('Y') }} LocalService. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
