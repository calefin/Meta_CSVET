<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Certificado de Conclusão — {{ $submission->process_id }}</title>
    <style>
        @page { margin: 0; size: A4 landscape; }
        body {
            font-family: 'Helvetica Neue', 'Helvetica', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #fff;
            color: #1e293b;
        }
        .page {
            width: 297mm;
            height: 210mm;
            position: relative;
            overflow: hidden;
            padding: 18mm 20mm;
            box-sizing: border-box;
        }
        /* Borda dupla decorativa */
        .border-outer {
            position: absolute;
            inset: 8mm;
            border: 2px solid #0d9488;
            border-radius: 6px;
            pointer-events: none;
        }
        .border-inner {
            position: absolute;
            inset: 11mm;
            border: 1px solid rgba(13,148,136,0.3);
            border-radius: 4px;
            pointer-events: none;
        }
        /* Marca d'água */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 72px;
            font-weight: 900;
            color: rgba(13,148,136,0.04);
            letter-spacing: -2px;
            white-space: nowrap;
            pointer-events: none;
        }
        .content {
            position: relative;
            z-index: 1;
            text-align: center;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .logo-area {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0;
        }
        .logo-text {
            font-size: 10px;
            color: #64748b;
            text-align: left;
        }
        .logo-text strong { color: #0d9488; }
        .process-id {
            font-size: 9px;
            color: #94a3b8;
            text-align: right;
        }
        .cert-label {
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 0.25em;
            color: #0d9488;
            text-transform: uppercase;
        }
        .cert-title {
            font-size: 30px;
            font-weight: 900;
            color: #0f172a;
            letter-spacing: -0.5px;
            margin: 4px 0 2px;
        }
        .cert-subtitle {
            font-size: 11px;
            color: #64748b;
        }
        .student-name {
            font-size: 26px;
            font-weight: 700;
            color: #0d9488;
            margin: 10px 0 4px;
        }
        .cert-body {
            font-size: 11px;
            color: #334155;
            line-height: 1.7;
            max-width: 580px;
            margin: 0 auto;
        }
        .work-title {
            font-size: 13px;
            font-weight: bold;
            color: #1e293b;
            font-style: italic;
            margin: 6px 0;
        }
        .signatures {
            display: flex;
            justify-content: space-around;
            margin-top: 0;
        }
        .sig-block {
            text-align: center;
        }
        .sig-line {
            display: inline-block;
            border-top: 1px solid #334155;
            width: 160px;
            padding-top: 4px;
            font-size: 9.5px;
            color: #334155;
        }
        .qr-block {
            position: absolute;
            bottom: 14mm;
            right: 20mm;
            text-align: center;
        }
        .qr-block img {
            width: 52px;
            height: 52px;
        }
        .qr-block p {
            font-size: 7px;
            color: #94a3b8;
            margin: 2px 0 0;
        }
        .footer-text {
            font-size: 8px;
            color: #94a3b8;
            text-align: center;
            margin-top: 2px;
        }
    </style>
</head>
<body>
<div class="page">
    <div class="border-outer"></div>
    <div class="border-inner"></div>
    <div class="watermark">PROFATILIO</div>

    <div class="content">
        <!-- Cabeçalho -->
        <div class="logo-area">
            <div class="logo-text">
                <strong>profatilio.com.br</strong><br>
                Plataforma de Orientação Acadêmica
            </div>
            <div class="process-id">
                Processo: {{ $submission->process_id ?? '—' }}<br>
                Emitido: {{ now()->format('d/m/Y') }}
            </div>
        </div>

        <!-- Corpo -->
        <div>
            <p class="cert-label">Certificado de Conclusão</p>
            <p class="cert-title">CERTIFICAMOS</p>
            <p class="cert-subtitle">que o(a) orientando(a)</p>

            <p class="student-name">{{ $student->user->name ?? '—' }}</p>

            <p class="cert-body">
                @if($student->institution)
                    {{ $student->institution }}{{ $student->campus ? ' — ' . $student->campus : '' }},
                    nível {{ $student->academic_level ?? 'Graduação' }},
                @endif
                concluiu com sucesso o trabalho acadêmico da categoria
                <strong>{{ $submission->document_type }}</strong>,
                sob orientação do <strong>Prof. Dr. Atilio Sersun Calefi</strong>,
                na plataforma profatilio.com.br, em conformidade com as normas ABNT e padrões
                de qualidade acadêmica verificados por auditoria de IA.
            </p>

            @if($submission->ai_executive_summary)
            <p class="work-title">"{{ Str::limit(strip_tags($submission->ai_executive_summary), 120) }}"</p>
            @elseif($submission->research_topic_snapshot ?? $student->research_topic)
            <p class="work-title">"{{ Str::limit($student->research_topic ?? '', 120) }}"</p>
            @endif

            <p class="cert-body" style="margin-top:6px; font-size:10px; color:#64748b;">
                Concluído em {{ $submission->updated_at?->format('d') }} de
                {{ $submission->updated_at?->locale('pt_BR')->isoFormat('MMMM') }} de
                {{ $submission->updated_at?->format('Y') }}
            </p>
        </div>

        <!-- Assinaturas -->
        <div>
            <div class="signatures">
                <div class="sig-block">
                    <div class="sig-line">
                        Prof. Dr. Atilio Sersun Calefi<br>
                        Orientador
                    </div>
                </div>
                <div class="sig-block">
                    <div class="sig-line">
                        {{ $student->user->name ?? '—' }}<br>
                        Orientando(a)
                    </div>
                </div>
            </div>

            <p class="footer-text" style="margin-top:10px;">
                Valide este certificado em: profatilio.com.br/documento/certificado/validar/{{ $certHash }}
            </p>
        </div>
    </div>

    <!-- QR Code de validação -->
    <div class="qr-block">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=52x52&data={{ urlencode(url('/documento/certificado/validar/' . $certHash)) }}" alt="QR">
        <p>Autenticidade</p>
    </div>
</div>
</body>
</html>
