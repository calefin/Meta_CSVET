<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1A1A1A; }
        h1 { color: #2D6A4F; font-size: 18px; }
        h2 { color: #7B4F2E; font-size: 14px; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #ddd; padding: 4px 6px; text-align: left; }
        th { background-color: #FAF7F0; }
    </style>
</head>
<body>
    <h1>{{ config('app.name') }} — Meus dados</h1>
    <p>Exportado em {{ now()->format('d/m/Y H:i') }} para {{ $user->name }}</p>

    <h2>Perfil</h2>
    <table>
        @foreach ($data['perfil'] as $key => $value)
            <tr><th>{{ $key }}</th><td>{{ $value }}</td></tr>
        @endforeach
    </table>

    <h2>Consultas</h2>
    <table>
        <tr><th>Modalidade</th><th>Status</th><th>Odu principal</th><th>Classificacao</th></tr>
        @foreach ($data['consultas'] as $c)
            <tr><td>{{ $c['modalidade'] }}</td><td>{{ $c['status'] }}</td><td>{{ $c['odu_principal'] }}</td><td>{{ $c['classificacao'] }}</td></tr>
        @endforeach
    </table>

    <h2>Agendamentos</h2>
    <table>
        <tr><th>Data</th><th>Modalidade</th><th>Status</th></tr>
        @foreach ($data['agendamentos'] as $a)
            <tr><td>{{ $a['data'] }}</td><td>{{ $a['modalidade'] }}</td><td>{{ $a['status'] }}</td></tr>
        @endforeach
    </table>

    <h2>Cursos</h2>
    <table>
        <tr><th>Curso</th><th>Matriculado em</th><th>Concluido em</th></tr>
        @foreach ($data['cursos'] as $c)
            <tr><td>{{ $c['curso'] }}</td><td>{{ $c['matriculado_em'] }}</td><td>{{ $c['concluido_em'] }}</td></tr>
        @endforeach
    </table>

    <h2>Consentimentos LGPD</h2>
    <table>
        <tr><th>Tipo</th><th>Aceito em</th></tr>
        @foreach ($data['consentimentos'] as $c)
            <tr><td>{{ $c['tipo'] }}</td><td>{{ $c['aceito_em'] }}</td></tr>
        @endforeach
    </table>
</body>
</html>
