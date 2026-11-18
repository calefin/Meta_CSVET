<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

@page {
    size: A4 landscape;
    margin: 0;
}

body {
    font-family: Georgia, 'Times New Roman', serif;
    background: #fff;
    color: #1a1a2e;
}

/* ── PAGE 1: Certificado ─────────────────────────── */
.page {
    width: 297mm;
    height: 210mm;
    position: relative;
    page-break-after: always;
    background: #ffffff;
    overflow: hidden;
}

.page-2 {
    width: 297mm;
    height: 210mm;
    position: relative;
    background: #ffffff;
    overflow: hidden;
}

/* Bordas duplas */
.border-outer {
    position: absolute;
    top: 8mm; left: 8mm; right: 8mm; bottom: 8mm;
    border: 3px solid #1a237e;
}
.border-inner {
    position: absolute;
    top: 12mm; left: 12mm; right: 12mm; bottom: 12mm;
    border: 1px solid #1a237e;
}

/* Cantos ornamentais */
.corner {
    position: absolute;
    width: 14mm; height: 14mm;
}
.corner-tl { top: 7mm; left: 7mm; border-top: 3px solid #1a237e; border-left: 3px solid #1a237e; }
.corner-tr { top: 7mm; right: 7mm; border-top: 3px solid #1a237e; border-right: 3px solid #1a237e; }
.corner-bl { bottom: 7mm; left: 7mm; border-bottom: 3px solid #1a237e; border-left: 3px solid #1a237e; }
.corner-br { bottom: 7mm; right: 7mm; border-bottom: 3px solid #1a237e; border-right: 3px solid #1a237e; }

/* Conteúdo da página 1 */
.content {
    position: absolute;
    top: 18mm; left: 20mm; right: 20mm; bottom: 18mm;
    display: flex;
    flex-direction: column;
    align-items: center;
}

/* Logo */
.logo {
    margin-top: 4mm;
    margin-bottom: 4mm;
    text-align: center;
}
.logo img {
    height: 16mm;
    max-width: 120mm;
}

/* Linha separadora fina */
.divider {
    width: 70mm;
    height: 1px;
    background: linear-gradient(to right, transparent, #1a237e, transparent);
    margin: 2mm 0;
}

/* Título */
.cert-title {
    font-family: Georgia, serif;
    font-size: 22pt;
    font-weight: normal;
    letter-spacing: 4px;
    color: #1a237e;
    text-transform: uppercase;
    text-align: center;
    margin-bottom: 1mm;
}
.cert-subtitle {
    font-size: 9pt;
    letter-spacing: 8px;
    color: #9c8b5a;
    text-transform: uppercase;
    text-align: center;
    margin-bottom: 4mm;
}

/* Texto do reconhecimento */
.cert-text {
    font-size: 10pt;
    color: #555;
    text-align: center;
    letter-spacing: 0.5px;
    margin-bottom: 2mm;
}

/* Nome do aluno */
.student-name {
    font-family: 'Palatino Linotype', Palatino, Georgia, serif;
    font-size: 22pt;
    font-style: italic;
    color: #1a237e;
    text-align: center;
    margin: 2mm 0;
    letter-spacing: 1px;
}

.cert-text-mid {
    font-size: 9pt;
    color: #555;
    text-align: center;
    margin-bottom: 1mm;
}

/* Nome do curso */
.course-name {
    font-size: 13pt;
    font-weight: bold;
    color: #1a1a1a;
    text-align: center;
    margin: 1mm 0;
}

.cert-meta {
    font-size: 8.5pt;
    color: #555;
    text-align: center;
    margin-bottom: 3mm;
}

/* Área de assinatura + footer */
.footer-area {
    width: 100%;
    margin-top: auto;
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
}

.signature-block {
    text-align: center;
    flex: 1;
}
.sig-line {
    width: 70mm;
    height: 1px;
    background: #1a237e;
    margin: 0 auto 2mm;
}
.sig-name {
    font-size: 9pt;
    font-weight: bold;
    color: #1a237e;
}
.sig-title {
    font-size: 7.5pt;
    color: #555;
}
.sig-cnpj {
    font-size: 7pt;
    color: #888;
    margin-top: 1mm;
}

.qr-block {
    text-align: right;
    flex-shrink: 0;
}
.qr-block img {
    width: 18mm;
    height: 18mm;
}
.qr-label {
    font-size: 6pt;
    color: #888;
    text-align: center;
    margin-top: 1mm;
}

/* Watermark */
.watermark {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) rotate(-40deg);
    font-size: 60pt;
    font-family: Georgia, serif;
    color: rgba(26, 35, 126, 0.03);
    font-weight: bold;
    letter-spacing: 10px;
    white-space: nowrap;
    pointer-events: none;
}

/* Hash line bottom */
.hash-line {
    position: absolute;
    bottom: 10.5mm;
    left: 20mm;
    right: 20mm;
    font-size: 5.5pt;
    color: #bbb;
    text-align: center;
    letter-spacing: 0.5px;
}

/* ── PAGE 2: Conteúdo Programático ────────────────── */
.p2-content {
    position: absolute;
    top: 18mm; left: 20mm; right: 20mm; bottom: 18mm;
}

.p2-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 2px solid #1a237e;
    padding-bottom: 3mm;
    margin-bottom: 5mm;
}
.p2-logo img {
    height: 10mm;
}
.p2-header-text {
    text-align: right;
}
.p2-header-text .label {
    font-size: 6.5pt;
    color: #888;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.p2-header-text .value {
    font-size: 8pt;
    color: #1a1a2e;
    font-weight: bold;
}

.p2-title {
    font-size: 14pt;
    font-weight: bold;
    color: #1a237e;
    text-transform: uppercase;
    letter-spacing: 2px;
    margin-bottom: 1mm;
}
.p2-course {
    font-size: 10pt;
    font-style: italic;
    color: #444;
    margin-bottom: 4mm;
    border-left: 3px solid #9c8b5a;
    padding-left: 3mm;
}

.p2-syllabus {
    font-size: 9pt;
    color: #333;
    line-height: 1.6;
    word-break: break-word;
}
.p2-syllabus p { margin: 0 0 4px 0; }
.p2-syllabus h2 { font-size: 10pt; font-weight: bold; color: #1a237e; margin: 6px 0 2px; }
.p2-syllabus h3 { font-size: 9.5pt; font-weight: bold; color: #333; margin: 4px 0 2px; }
.p2-syllabus ul, .p2-syllabus ol { margin: 2px 0 4px 16px; padding: 0; }
.p2-syllabus li { margin-bottom: 2px; }
.p2-syllabus strong { font-weight: bold; }
.p2-syllabus em { font-style: italic; }
.p2-syllabus u { text-decoration: underline; }

.p2-footer {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    padding-bottom: 2mm;
}
.p2-sig-line {
    width: 80mm;
    height: 1px;
    background: #1a237e;
    margin: 0 auto 2mm;
}
.p2-sig-name {
    font-size: 9pt;
    font-weight: bold;
    color: #1a237e;
    text-align: center;
}
.p2-sig-sub {
    font-size: 7.5pt;
    color: #555;
    text-align: center;
}
</style>
</head>
<body>

<!-- ═══ PÁGINA 1 — Certificado ═══ -->
<div class="page">
    <div class="watermark">CSVET</div>
    <div class="border-outer"></div>
    <div class="border-inner"></div>
    <div class="corner corner-tl"></div>
    <div class="corner corner-tr"></div>
    <div class="corner corner-bl"></div>
    <div class="corner corner-br"></div>

    <div class="content">
        <div class="logo">
            <img src="{{ public_path('images/csvet-logo.png') }}" alt="CSVET">
        </div>

        <div class="divider"></div>

        <div class="cert-title">Certificado</div>
        <div class="cert-subtitle">de Conclusão</div>

        <div class="cert-text">RECONHECEMOS QUE</div>

        <div class="student-name">{{ $request->student->user->name }}</div>

        <div class="cert-text-mid">Concluiu com Êxito</div>

        <div class="course-name">{{ $request->course->name }}</div>

        <div class="cert-meta">
            com carga horária de <strong>{{ $request->course->workload }} horas</strong>
            &nbsp;|&nbsp;
            CPF: {{ $request->student->cpf }}
        </div>

        <div class="cert-text-mid" style="margin-bottom:0">
            em <strong>CSvet Editora, Cursos e Treinamentos</strong>
            &nbsp;em&nbsp;{{ $request->course->end_date_formatted }}
        </div>

        <div class="footer-area">
            <div style="width:20mm"></div>

            <div class="signature-block">
                <div class="sig-line"></div>
                <div class="sig-name">Atilio Sersun Calefi</div>
                <div class="sig-title">MV, MSc, PhD &nbsp;|&nbsp; RG SSP 39.493.470-2</div>
                <div class="sig-cnpj">CSvet Editora, Cursos e Treinamentos &nbsp;|&nbsp; CNPJ 32.645.724/0001-89</div>
            </div>

            <div class="qr-block">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data={{ urlencode(url('/certificado-curso/validar/' . $request->cert_hash)) }}" alt="QR">
                <div class="qr-label">Validar</div>
            </div>
        </div>
    </div>

    <div class="hash-line">
        Documento autêntico — Valide em profatilio.com.br/certificado-curso/validar/{{ $request->cert_hash }}
    </div>
</div>

<!-- ═══ PÁGINA 2 — Conteúdo Programático ═══ -->
<div class="page-2">
    <div class="border-outer"></div>
    <div class="border-inner"></div>
    <div class="corner corner-tl"></div>
    <div class="corner corner-tr"></div>
    <div class="corner corner-bl"></div>
    <div class="corner corner-br"></div>

    <div class="p2-content">
        <div class="p2-header">
            <div class="p2-logo">
                <img src="{{ public_path('images/csvet-logo.png') }}" alt="CSVET">
            </div>
            <div class="p2-header-text">
                <div class="label">Aluno</div>
                <div class="value">{{ $request->student->user->name }}</div>
                <div class="label" style="margin-top:1mm">CPF</div>
                <div class="value">{{ $request->student->cpf }}</div>
            </div>
        </div>

        <div class="p2-title">Conteúdo Programático</div>
        <div class="p2-course">{{ $request->course->name }} &nbsp;—&nbsp; {{ $request->course->workload }}h &nbsp;|&nbsp; {{ $request->course->formatted_period }}</div>

        <div class="p2-syllabus">{!! $request->course->syllabus !!}</div>

        <div class="p2-footer">
            <div>
                <div class="p2-sig-line"></div>
                <div class="p2-sig-name">Atilio Sersun Calefi</div>
                <div class="p2-sig-sub">MV, MSc, PhD &nbsp;|&nbsp; CSvet Editora, Cursos e Treinamentos &nbsp;|&nbsp; CNPJ 32.645.724/0001-89</div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
