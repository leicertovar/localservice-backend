<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña - LocalService</title>
    <style>
        body {
            font-family: 'Outfit', 'Inter', 'Segoe UI', Geneva, Verdana, sans-serif;
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
            background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
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
        .button-container {
            text-align: center;
            margin: 35px 0;
        }
        .btn-primary {
            display: inline-block;
            background-color: #4f46e5;
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 30px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2), 0 2px 4px -1px rgba(79, 70, 229, 0.1);
            transition: background-color 0.2s;
        }
        .btn-primary:hover {
            background-color: #4338ca;
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
        .badge {
            display: inline-block;
            background-color: rgba(79, 70, 229, 0.1);
            color: #4f46e5;
            padding: 6px 12px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>LocalService</h1>
            <p>Recuperación de Acceso de Cuenta</p>
        </div>
        <div class="content">
            <span class="badge">Seguridad</span>
            <div class="greeting">¡Hola, {{ $usuario->nombre }}!</div>
            <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta asociada a este correo electrónico en <strong>LocalService</strong>.</p>
            <p>Para establecer una nueva contraseña, haz clic en el siguiente botón de restablecimiento de seguridad:</p>
            
            <div class="button-container">
                <a href="http://localhost:8080/recuperar-contrasena?token={{ $token }}&email={{ urlencode($email) }}" class="btn-primary" target="_blank">
                    Restablecer Contraseña
                </a>
            </div>

            <p>Si el botón de arriba no funciona, puedes copiar y pegar el siguiente enlace en tu navegador web:</p>
            <p style="word-break: break-all; font-size: 13px; color: #4f46e5;">
                http://localhost:8080/recuperar-contrasena?token={{ $token }}&email={{ urlencode($email) }}
            </p>

            <div class="card">
                <h3 style="margin-top: 0; color: #111827; font-size: 14px;">Aviso de seguridad importante:</h3>
                <p style="margin-bottom: 0; font-size: 13px;">
                    Este enlace de recuperación tiene una validez estricta de <strong>30 minutos</strong> por motivos de seguridad informática. 
                    Si tú no has solicitado este restablecimiento, puedes ignorar este mensaje; tu contraseña actual seguirá siendo totalmente segura.
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
