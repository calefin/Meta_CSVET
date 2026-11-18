<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; color: #111; line-height: 1.5; }
    pre { white-space: pre-wrap; font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; margin: 0; }
    .box { border: 1px solid #999; padding: 8px 10px; margin-top: 14px; font-size: 8.5pt; }
    .mono { font-family: "DejaVu Sans Mono", monospace; font-size: 7.5pt; word-break: break-all; }
</style>
</head>
<body>
    <pre>{{ $s->terms_text }}</pre>
    <div class="box">
        <strong>ASSINATURA ELETRÔNICA</strong><br>
        Assinado por: {{ $s->terms_signature }} ({{ $s->email }})<br>
        Data/hora: {{ $s->terms_accepted_at?->format('d/m/Y H:i:s') }} ({{ config('app.timezone') }})<br>
        Versão do termo: {{ $s->terms_version }}<br>
        IP: {{ $s->terms_ip ?: '—' }}<br>
        Navegador: {{ $s->terms_user_agent ?: '—' }}<br>
        SHA-256 do texto: <span class="mono">{{ $s->terms_sha256 }}</span><br>
        Integridade: {{ hash('sha256', (string) $s->terms_text) === $s->terms_sha256 ? 'texto conferido — idêntico ao assinado' : 'ATENÇÃO: o texto não confere com a impressão digital' }}
    </div>
    <div style="font-size:7.5pt;color:#555;margin-top:8px">Documento gerado em {{ now()->format('d/m/Y H:i') }} — profatilio.com.br</div>
</body>
</html>
