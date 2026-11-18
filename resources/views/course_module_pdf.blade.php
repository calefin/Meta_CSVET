<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

@page {
    margin: 30mm 18mm 22mm 18mm;
}

body {
    font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif;
    color: #1a1a2e;
    font-size: 10.5pt;
    line-height: 1.5;
}

.header {
    position: fixed;
    top: -22mm;
    left: 0;
    right: 0;
    height: 18mm;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 2px solid #1a237e;
    padding-bottom: 3mm;
}
.header img { height: 10mm; }
.header-text { text-align: right; }
.header-text .course { font-size: 8pt; color: #888; text-transform: uppercase; letter-spacing: 1px; }
.header-text .module { font-size: 10pt; font-weight: bold; color: #1a237e; }

.footer {
    position: fixed;
    bottom: -18mm;
    left: 0;
    right: 0;
    height: 14mm;
    border-top: 1px solid #ccc;
    padding-top: 2mm;
    font-size: 6.5pt;
    color: #888;
    text-align: center;
    line-height: 1.4;
}
.footer strong { color: #555; }

.watermark {
    position: fixed;
    top: 45%;
    left: 10%;
    width: 80%;
    transform: rotate(-35deg);
    font-size: 13pt;
    color: rgba(26, 35, 126, 0.06);
    font-weight: bold;
    text-align: center;
    letter-spacing: 2px;
    z-index: -1;
}

.title { font-size: 16pt; font-weight: bold; color: #1a237e; margin-bottom: 1mm; }
.subtitle { font-size: 9pt; color: #888; margin-bottom: 6mm; }

.content { font-size: 10.5pt; }
.content p { margin: 0 0 6px 0; }
.content h2 { font-size: 13pt; font-weight: bold; color: #1a237e; margin: 10px 0 4px; }
.content h3 { font-size: 11pt; font-weight: bold; color: #333; margin: 8px 0 3px; }
.content ul, .content ol { margin: 3px 0 6px 18px; }
.content li { margin-bottom: 2px; }
.content strong { font-weight: bold; }
.content em { font-style: italic; }
.content pre {
    background: #f4f4f8;
    border: 1px solid #ddd;
    border-left: 3px solid #1a237e;
    padding: 6px 8px;
    font-family: 'DejaVu Sans Mono', monospace;
    font-size: 9pt;
    white-space: pre-wrap;
    margin: 6px 0;
}
.content code {
    font-family: 'DejaVu Sans Mono', monospace;
    background: #f4f4f8;
    padding: 1px 3px;
}

.video-note {
    margin-top: 8mm;
    padding: 4mm;
    background: #f0f4ff;
    border-left: 3px solid #1a237e;
    font-size: 9pt;
    color: #444;
}

.empty-note {
    font-style: italic;
    color: #888;
}
</style>
</head>
<body>

<div class="watermark">
    {{ $student->user->name ?? 'USO PESSOAL E INTRANSFERÍVEL' }}
</div>

<div class="header">
    <img src="{{ public_path('images/logo.png') }}" alt="Prof. Atilio">
    <div class="header-text">
        <div class="course">{{ $module->course->name }}</div>
        <div class="module">{{ $module->title }}</div>
    </div>
</div>

<div class="footer">
    Material de estudo pessoal e intransferível de <strong>{{ $student->user->name ?? '—' }}</strong>
    @if($student?->cpf) &nbsp;|&nbsp; CPF: {{ $student->cpf }} @endif
    @if($student?->registration) &nbsp;|&nbsp; Matrícula: {{ $student->registration }} @endif
    &nbsp;|&nbsp; Gerado em {{ now()->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}<br>
    Proibida a reprodução ou redistribuição deste material sem autorização do autor — Prof. Atilio Sersun Calefi
    @if($module->course->isbn) &nbsp;|&nbsp; ISBN {{ $module->course->isbn }} @endif
</div>

<div class="title">{{ $module->title }}</div>
<div class="subtitle">{{ $module->course->name }}</div>

<div class="content">
    @if($module->content)
        {!! $module->content !!}
    @else
        <p class="empty-note">Conteúdo em formato de texto não disponível para esta etapa.</p>
    @endif
</div>

@if($module->video_url)
    <div class="video-note">
        📺 Esta etapa também tem um vídeo complementar, disponível apenas na plataforma: {{ $module->video_url }}
    </div>
@endif

</body>
</html>
