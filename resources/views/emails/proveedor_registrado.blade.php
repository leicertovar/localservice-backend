<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro Exitoso - LocalService</title>
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
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
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
        .card {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 24px;
            margin: 24px 0;
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
            background-color: #4f46e5;
            color: #ffffff;
        }
        .step.active .step-number {
            background-color: #f59e0b;
            color: #ffffff;
            box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.2);
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
            background-color: rgba(245, 158, 11, 0.1);
            color: #d97706;
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
            <span class="badge">Registro Exitoso</span>
            <div class="greeting">¡Hola, {{ $usuario->nombre }}!</div>
            <p>Te damos una calurosa bienvenida a <strong>LocalService</strong>. Tu registro como proveedor de servicios se ha completado correctamente en nuestro sistema.</p>
            
            <div class="card">
                <h3 style="margin-top: 0; color: #111827; font-size: 16px;">¿Qué sigue ahora?</h3>
                <p style="margin-bottom: 0; font-size: 14px; color: #4b5563;">
                    Para garantizar la seguridad y calidad de la plataforma, un administrador está revisando los documentos de verificación que has subido. 
                    Este proceso suele tardar menos de 24 horas hábiles. Tan pronto como tu cuenta sea aprobada, recibirás una notificación de confirmación y podrás comenzar a recibir clientes.
                </p>
            </div>

            <!-- Visual steps -->
            <div class="steps">
                <div class="step completed">
                    <div class="step-number">✓</div>
                    <div class="step-label">Registro</div>
                </div>
                <div class="step active">
                    <div class="step-number">2</div>
                    <div class="step-label">Verificación</div>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-label">¡Listo!</div>
                </div>
            </div>

            <p style="text-align: center; color: #6b7280; font-size: 14px; margin-top: 30px;">
                No necesitas realizar ninguna acción adicional en este momento. ¡Agradecemos mucho tu paciencia!
            </p>
        </div>
        <div class="footer">
            <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
            <p>&copy; {{ date('Y') }} LocalService. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
