<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¡Cuenta Aprobada! - LocalService</title>
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
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
        .success-box {
            background-color: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 12px;
            padding: 24px;
            margin: 24px 0;
            text-align: center;
        }
        .btn {
            display: inline-block;
            background-color: #10b981;
            color: #ffffff !important;
            padding: 14px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            margin: 15px 0;
            box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2), 0 2px 4px -1px rgba(16, 185, 129, 0.1);
            transition: all 0.2s ease;
        }
        .steps {
            display: flex;
            justify-content: space-between;
            margin: 30px 0;
            position: relative;
        }
        .step {
            flex: 1;
            text-align: center;
            z-index: 2;
        }
        .step-number {
            width: 36px;
            height: 36px;
            line-height: 36px;
            border-radius: 50%;
            background-color: #e5e7eb;
            color: #6b7280;
            font-weight: 700;
            margin: 0 auto 8px auto;
            font-size: 14px;
        }
        .step.completed .step-number {
            background-color: #10b981;
            color: #ffffff;
        }
        .step-label {
            font-size: 12px;
            font-weight: 600;
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
            background-color: rgba(16, 185, 129, 0.1);
            color: #059669;
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
            <p>Tu plataforma de servicios locales de confianza</p>
        </div>
        <div class="content">
            <span class="badge">Cuenta Aprobada</span>
            <div class="greeting">¡Felicitaciones, {{ $usuario->nombre }}!</div>
            <p>Tenemos excelentes noticias para ti. Nuestros administradores han completado la revisión de tus documentos y tu perfil de proveedor en <strong>LocalService</strong> ha sido <strong>aprobado con éxito</strong>.</p>
            
            <div class="success-box">
                <h3 style="margin-top: 0; color: #065f46; font-size: 18px;">¡Ya puedes empezar!</h3>
                <p style="font-size: 14px; color: #047857; margin-bottom: 20px;">
                    Tu cuenta está activa y lista para recibir solicitudes de clientes en tu zona. Haz clic en el botón de abajo para iniciar sesión en tu panel de control.
                </p>
                <a href="http://localservice.ds2.eleueleo.com/login" class="btn">Iniciar Sesión en LocalService</a>
            </div>

            <!-- Visual steps -->
            <div class="steps">
                <div class="step completed">
                    <div class="step-number">✓</div>
                    <div class="step-label">Registro</div>
                </div>
                <div class="step completed">
                    <div class="step-number">✓</div>
                    <div class="step-label">Verificación</div>
                </div>
                <div class="step completed">
                    <div class="step-number">✓</div>
                    <div class="step-label">¡Listo!</div>
                </div>
            </div>

            <p style="color: #4b5563; font-size: 14px;">
                Te recomendamos completar tu perfil público agregando una biografía detallada, tus habilidades, fotos de tus trabajos anteriores y tus horarios de atención. Un perfil completo y pulido atrae hasta un 80% más de clientes.
            </p>
            
            <p style="color: #6b7280; font-size: 14px; margin-top: 24px; border-top: 1px dashed #e5e7eb; padding-top: 16px;">
                ¡Te deseas el mayor de los éxitos en LocalService!
            </p>
        </div>
        <div class="footer">
            <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
            <p>&copy; {{ date('Y') }} LocalService. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
