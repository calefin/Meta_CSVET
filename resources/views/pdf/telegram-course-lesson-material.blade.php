<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>{{ $lesson->title }} — Material do Curso</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #1e1e1e; font-size: 13px; margin: 0; padding: 30px 36px; }
        .header { text-align: center; border-bottom: 2px solid #2D6A4F; padding-bottom: 15px; margin-bottom: 20px; }
        .title { font-size: 20px; font-weight: bold; color: #2D6A4F; margin: 0; }
        .subtitle { font-size: 12px; color: #666; margin-top: 5px; }
        .lesson-title { font-size: 16px; font-weight: bold; color: #1e1e1e; margin: 0 0 4px; }
        .lesson-type { display: inline-block; font-size: 10px; font-weight: bold; text-transform: uppercase; color: #2D6A4F; background: #e8f4ec; border-radius: 4px; padding: 2px 8px; margin-bottom: 14px; }
        .disclaimer { background: #fdf3e7; border: 1px solid #e8c99a; border-radius: 6px; padding: 12px 14px; font-size: 11px; color: #7a5210; margin-bottom: 22px; line-height: 1.5; }
        .disclaimer strong { color: #5c3d0c; }
        .content { font-size: 13px; line-height: 1.6; }
        .content p { margin: 0 0 10px; }
        .content h2 { font-size: 15px; color: #2D6A4F; margin: 18px 0 8px; }
        .content h3 { font-size: 13px; color: #2D6A4F; margin: 14px 0 6px; }
        .content ul, .content ol { margin: 0 0 10px 18px; padding: 0; }
        .content blockquote { border-left: 3px solid #2D6A4F; margin: 10px 0; padding: 4px 12px; color: #444; background: #fdfbf7; }
        .footer { margin-top: 40px; text-align: center; font-size: 10px; color: #888; border-top: 1px solid #ddd; padding-top: 12px; }

        .watermark { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; }
        .watermark span {
            position: absolute;
            font-size: 11px;
            font-weight: bold;
            color: #2D6A4F;
            opacity: 0.10;
            transform: rotate(-35deg);
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="watermark">
        @php
            $stamp = ($user->name ?? $user->email) . '  •  ' . $user->email . '  •  Reprodução proibida';
            $positions = [
                ['top' => '8%', 'left' => '-10%'], ['top' => '8%', 'left' => '45%'],
                ['top' => '28%', 'left' => '10%'], ['top' => '28%', 'left' => '65%'],
                ['top' => '48%', 'left' => '-10%'], ['top' => '48%', 'left' => '45%'],
                ['top' => '68%', 'left' => '10%'], ['top' => '68%', 'left' => '65%'],
                ['top' => '88%', 'left' => '-10%'], ['top' => '88%', 'left' => '45%'],
            ];
        @endphp
        @foreach ($positions as $pos)
            <span style="top: {{ $pos['top'] }}; left: {{ $pos['left'] }};">{{ $stamp }}</span>
        @endforeach
    </div>

    <div class="header">
        <div class="title">Ìjọ Ifá Èjìogbè</div>
        <div class="subtitle">Material de Apoio — Curso de Formação Contínua no Telegram</div>
    </div>

    <div class="lesson-type">{{ $lesson->type === 'presential_meeting' ? 'Encontro Presencial' : 'Aula Semanal Telegram' }}</div>
    <h1 class="lesson-title">{{ $lesson->title }}</h1>

    <div class="disclaimer">
        <strong>Material de uso exclusivo e pessoal.</strong> Documento gerado para
        <strong>{{ $user->name ?? $user->email }}</strong> ({{ $user->email }}) em
        {{ $generatedAt->format('d/m/Y \à\s H:i') }}. É <strong>proibida a reprodução total ou parcial</strong>
        deste material, por qualquer meio, sem autorização expressa de Ìjọ Ifá Èjìogbè. O compartilhamento
        indevido deste conteúdo é rastreável a este download.
    </div>

    <div class="content">
        {!! $lesson->content !!}
    </div>

    @if ($lesson->checklist_materials)
        <div class="disclaimer" style="background: #eef6f0; border-color: #bfe0cb; color: #1e4d2f;">
            <strong>Insumos / Materiais Práticos:</strong><br>
            {!! nl2br(e($lesson->checklist_materials)) !!}
        </div>
    @endif

    <div class="footer">
        <p>&copy; {{ date('Y') }} Ìjọ Ifá Èjìogbè — Todos os direitos reservados.</p>
    </div>
</body>
</html>
