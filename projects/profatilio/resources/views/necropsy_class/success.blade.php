<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presença Confirmada - Aula Prática de Necropsia</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'], outfit: ['Outfit', 'sans-serif'] },
                    colors: { brand: { teal: '#0d9488' } }
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #020617;
            background-image: radial-gradient(at 0% 0%, rgba(13, 148, 136, 0.15) 0px, transparent 50%);
        }
        .glass-card { background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(16px); border: 1px rgba(255,255,255,0.08) solid; }
    </style>
</head>
<body class="font-sans text-slate-200 min-h-screen flex items-center justify-center p-4 py-12">
    <div class="w-full max-w-md glass-card rounded-3xl p-8 shadow-2xl shadow-brand-teal/5 text-center">
        <div class="text-5xl mb-4">✅</div>
        <h1 class="font-outfit font-extrabold text-2xl text-white mb-2">Presença Confirmada!</h1>
        <p class="text-slate-300 mb-1"><strong>{{ $participant->name }}</strong></p>
        <p class="text-slate-500 text-sm mb-6">RGM {{ $participant->rgm }} — {{ $participant->institution ?? 'Instituição não informada' }}</p>

        <div class="p-4 rounded-xl bg-slate-900/60 border border-white/5 text-sm text-left">
            <p class="text-slate-400">Data: <span class="text-white">{{ $session->class_date->format('d/m/Y') }}</span></p>
            <p class="text-slate-400">Horário: <span class="text-white">{{ \Carbon\Carbon::parse($session->start_time)->format('H:i') }} às {{ \Carbon\Carbon::parse($session->end_time)->format('H:i') }}</span></p>
            <p class="text-slate-400">Local: <span class="text-white">{{ $session->location }}</span></p>
        </div>

        <p class="text-xs text-slate-500 mt-6">Bons estudos! Você já pode fechar esta página.</p>
    </div>
</body>
</html>
