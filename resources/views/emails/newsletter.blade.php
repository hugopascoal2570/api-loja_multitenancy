<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $newsletterTitle }}</title>
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
            padding: 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background-color: #ec4899;
            text-align: center;
            padding: 25px 30px;
        }
        .header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 22px;
        }
        .image-container {
            width: 100%;
            text-align: center;
        }
        .image-container img {
            width: 100%;
            max-height: 350px;
            object-fit: cover;
            display: block;
        }
        .content {
            padding: 30px;
        }
        .content h2 {
            color: #ec4899;
            margin-top: 0;
            font-size: 20px;
        }
        .content p {
            margin-bottom: 15px;
            color: #555;
        }
        .footer {
            padding: 20px 30px;
            border-top: 1px solid #e0e0e0;
            font-size: 12px;
            color: #999;
            text-align: center;
            background-color: #fafafa;
        }
        .footer a {
            color: #ec4899;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ config('app.name') }}</h1>
        </div>

        @if($imageUrl)
        <div class="image-container">
            <img src="{{ $imageUrl }}" alt="{{ $newsletterTitle }}">
        </div>
        @endif

        <div class="content">
            {!! $newsletterContent !!}
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. Todos os direitos reservados.</p>
            <p>
                Não deseja mais receber nossos emails?
                <a href="{{ $unsubscribeUrl }}">Clique aqui para se descadastrar</a>
            </p>
        </div>
    </div>
</body>
</html>
