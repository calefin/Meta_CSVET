<x-emails.layout>
    <h1 style="font-size: 20px; color:#2D6A4F; margin: 0 0 16px;">Resumo da sua Consulta — {{ config('app.name') }}</h1>

    <p style="margin: 0 0 16px;">Olá, {{ $userName }}.</p>

    <p style="margin: 0 0 16px;">
        Sua consulta oracular de Ifá foi realizada e validada pelo Babalawo em <strong>{{ $consultedAt }}</strong>.
    </p>

    <div style="background-color: #F4F6F0; border-left: 4px solid #2D6A4F; padding: 16px; margin: 0 0 24px; border-radius: 4px;">
        <h2 style="font-size: 16px; color:#2D6A4F; margin: 0 0 12px; font-family: sans-serif;">Detalhes Oraculares</h2>
        <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; font-size: 14px; color: #333333; line-height: 1.6;">
            <tr>
                <td style="width: 120px; font-weight: bold; padding-bottom: 8px;">Odù Principal:</td>
                <td style="padding-bottom: 8px;" lang="yo"><strong>{{ $oduMain }}</strong></td>
            </tr>
            <tr>
                <td style="font-weight: bold; padding-bottom: 8px;">Classificação:</td>
                <td style="padding-bottom: 8px;">{{ $classification }}</td>
            </tr>
        </table>
    </div>

    <div style="margin-bottom: 24px;">
        <h3 style="font-size: 15px; color:#2D6A4F; margin: 0 0 8px;">Orientações Gerais</h3>
        <p style="margin: 0; font-size: 14px; line-height: 1.6; color: #444444; white-space: pre-line;">{{ $generalGuidance }}</p>
    </div>

    <p style="margin: 0 0 16px;">
        Você pode acessar seu histórico completo de consultas e recomendações a qualquer momento fazendo login no nosso portal.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td style="border-radius: 6px; background-color:#2D6A4F;">
                <a href="{{ route('login') }}" style="display: inline-block; padding: 12px 28px; color:#FFFFFF; text-decoration:none; font-weight: bold; font-size: 15px;">
                    Acessar o Painel
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 0; font-size: 12px; color:#888;">
        Ìbọrú Ìbọyẹ l’Ábọṣíṣẹ. Que as bênçãos de Ifá tragam caminhos abertos e sabedoria em sua jornada.
    </p>
</x-emails.layout>
