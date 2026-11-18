<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; color: #1A1A1A; text-align: center; padding-top: 80px; }
        .border-box { border: 4px solid #2D6A4F; padding: 60px 40px; margin: 20px; }
        h1 { color: #2D6A4F; font-size: 28px; margin-bottom: 0; }
        .student-name { font-size: 24px; margin: 24px 0; color: #7B4F2E; font-weight: bold; }
        .course-name { font-size: 18px; margin: 12px 0; }
        .date { margin-top: 40px; font-size: 12px; color: #555; }
        .signature { margin-top: 60px; font-size: 12px; }
        .signature-line { border-top: 1px solid #1A1A1A; width: 240px; margin: 0 auto 6px; }
    </style>
</head>
<body>
    <div class="border-box">
        <h1>{{ config('app.name') }}</h1>
        <p>Certificado de Conclusao</p>

        <p class="student-name">{{ $user->name }}</p>

        <p>concluiu com sucesso o curso</p>
        <p class="course-name"><strong>{{ $course->title }}</strong></p>

        <p class="date">Concluido em {{ $completedAt->format('d/m/Y') }}</p>

        <div class="signature">
            <div class="signature-line"></div>
            <p>Babalawo — {{ config('app.name') }}</p>
        </div>
    </div>
</body>
</html>
