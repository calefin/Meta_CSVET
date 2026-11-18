<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Acesso') | MedVetLegal</title>

    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('assets/favicon-16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <meta name="theme-color" content="#0e2a47">

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Lato:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary-color: #0e2a47;
            --primary-dark: #071729;
            --accent-color: #c7a006;
            --accent-hover: #e0b408;
        }

        body {
            font-family: 'Lato', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            padding: 24px;
        }

        .auth-wrapper {
            width: 100%;
            max-width: 440px;
        }

        .auth-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
            overflow: hidden;
        }

        .auth-card-header {
            background-color: var(--primary-color);
            text-align: center;
            padding: 36px 30px 26px;
        }

        .auth-card-header img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.92);
            padding: 6px;
            margin-bottom: 12px;
        }

        .auth-card-header h1 {
            font-family: 'Playfair Display', serif;
            color: #fff;
            font-size: 1.35rem;
            letter-spacing: 1.5px;
            margin: 0;
        }

        .auth-card-header p {
            color: rgba(255, 255, 255, 0.65);
            font-size: 0.78rem;
            margin: 6px 0 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .auth-card-body {
            padding: 32px 35px 30px;
        }

        .auth-card-body h2 {
            font-family: 'Playfair Display', serif;
            color: var(--primary-color);
            font-size: 1.25rem;
            margin-bottom: 20px;
            text-align: center;
        }

        .auth-intro {
            color: #718096;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }

        .form-label {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            color: var(--primary-color);
        }

        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 14px;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(14, 42, 71, 0.1);
        }

        .btn-auth {
            background-color: var(--accent-color);
            color: var(--primary-color);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 12px;
            border-radius: 6px;
            border: none;
            width: 100%;
            transition: all 0.3s;
        }

        .btn-auth:hover {
            background-color: var(--accent-hover);
            color: var(--primary-dark);
        }

        .auth-links {
            text-align: center;
            margin-top: 18px;
            font-size: 0.88rem;
        }

        .auth-links a {
            color: var(--primary-color);
            font-weight: 700;
            text-decoration: none;
        }

        .auth-links a:hover {
            color: var(--accent-color);
        }

        .auth-back {
            display: block;
            text-align: center;
            margin-top: 22px;
            color: rgba(255, 255, 255, 0.65);
            font-size: 0.85rem;
            text-decoration: none;
        }

        .auth-back:hover {
            color: #fff;
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-card-header">
                <img src="{{ asset('assets/favicon.png') }}" alt="MedVetLegal">
                <h1>MEDVET <span style="color: var(--accent-color);">LEGAL</span></h1>
                <p>Painel Administrativo</p>
            </div>
            <div class="auth-card-body">
                @yield('content')
            </div>
        </div>
        <a href="{{ url('/') }}" class="auth-back"><i class="fas fa-arrow-left me-1"></i> Voltar ao site</a>
    </div>
</body>
</html>
