<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Código de recuperación - LocalService</title>
    <style>
        body {
            font-family: 'Inter', 'Segoe UI', Geneva, Verdana, sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
        }
        .header {
            background: linear-gradient(135deg, #1b6ef3 0%, #1a2b5f 100%);
            padding: 40px 20px;
            text-align: center;
            color: #ffffff;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .header p {
            margin: 10px 0 0 0;
            font-size: 16px;
            opacity: 0.9;
        }
        .content {
            padding: 40px 30px;
            color: #1f2937;
            line-height: 1.6;
        }
        .greeting {
            font-size: 20px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 20px;
        }
        .code {
            margin: 32px 0;
            text-align: center;
        }
        .code span {
            display: inline-block;
            background-color: #e8f0fe;
            color: #1b6ef3;
            font-size: 36px;
            font-weight: 700;
            letter-spacing: 12px;
            padding: 16px 20px 16px 32px;
            border-radius: 12px;
            font-family: 'Courier New', monospace;
        }
        .card {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            margin: 24px 0;
            font-size: 14px;
            color: #4b5563;
        }
        .footer {
            background-color: #f9fafb;
            padding: 24px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>LocalService</h1>
            <p>Recuperación de contraseña</p>
        </div>
        <div class="content">
            <div class="greeting">¡Hola, {{ $usuario->nombre }}!</div>
            <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta en <strong>LocalService</strong>.</p>
            <p>Escribe este código en la app para continuar:</p>

            <div class="code">
                <span>{{ $codigo }}</span>
            </div>

            <div class="card">
                <h3 style="margin-top: 0; color: #111827; font-size: 14px;">Aviso de seguridad importante:</h3>
                <p style="margin-bottom: 0; font-size: 13px;">
                    El código vence en <strong>{{ $minutosValidez }} minutos</strong> y solo puede usarse una vez.
                    Nunca lo compartas con nadie: el equipo de LocalService jamás te lo pedirá.
                    Si tú no solicitaste este cambio, ignora este mensaje; tu contraseña actual sigue siendo segura.
                </p>
            </div>
        </div>
        <div class="footer">
            <p>Este es un correo automático de seguridad, por favor no respondas a este mensaje.</p>
            <p>&copy; {{ date('Y') }} LocalService. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
