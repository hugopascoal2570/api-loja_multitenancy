<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperação de Senha</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #ec4899;
            margin: 0;
            font-size: 24px;
        }
        .content {
            margin-bottom: 30px;
        }
        .content p {
            margin-bottom: 15px;
        }
        .button-wrapper {
            text-align: center;
            margin: 30px 0;
        }
        .button {
            display: inline-block;
            background-color: #ec4899;
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 36px;
            border-radius: 6px;
            font-weight: bold;
            font-size: 16px;
        }
        .link-fallback {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 12px 16px;
            margin: 16px 0;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            word-break: break-all;
            color: #555;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            font-size: 12px;
            color: #666;
            text-align: center;
        }
        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ config('app.name') }}</h1>
        </div>

        <div class="content">
            <p>Olá, <strong>{{ $name }}</strong>!</p>

            <p>Recebemos uma solicitação para redefinir a senha da sua conta (<strong>{{ $email }}</strong>).</p>

            <p>Clique no botão abaixo para criar uma nova senha:</p>

            <div class="button-wrapper">
                <a href="{{ $resetUrl }}" class="button">Redefinir minha senha</a>
            </div>

            <p style="font-size: 13px; color: #666;">Se o botão não funcionar, copie e cole o link abaixo no seu navegador:</p>
            <div class="link-fallback">{{ $resetUrl }}</div>

            <div class="warning">
                <strong>Importante:</strong>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li>Este link expira em <strong>60 minutos</strong></li>
                    <li>Não compartilhe este link com ninguém</li>
                </ul>
            </div>

            <p>Se você não solicitou a redefinição de senha, ignore este email. Sua senha permanecerá inalterada.</p>
        </div>

        <div class="footer">
            <p>Este é um email automático, por favor não responda.</p>
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. Todos os direitos reservados.</p>
        </div>
    </div>
</body>
</html>
