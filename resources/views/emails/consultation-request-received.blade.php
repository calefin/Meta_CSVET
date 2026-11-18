<x-emails.layout>
    <h1 style="font-size: 20px; color:#2D6A4F; margin: 0 0 16px;">Nova Solicitação de Consulta</h1>

    <p style="margin: 0 0 16px;">
        Uma nova solicitação de consulta foi enviada pelo formulário público. Veja os detalhes abaixo:
    </p>

    <div style="background-color: #FAF7F0; border-radius: 6px; padding: 16px; margin-bottom: 20px;">
        <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="font-size: 14px;">
            <tr>
                <td style="padding-bottom: 6px; font-weight: bold; width: 140px;">Nome:</td>
                <td style="padding-bottom: 6px;">{{ $userName }}</td>
            </tr>
            <tr>
                <td style="padding-bottom: 6px; font-weight: bold;">E-mail:</td>
                <td style="padding-bottom: 6px;">{{ $email }}</td>
            </tr>
            <tr>
                <td style="padding-bottom: 6px; font-weight: bold;">WhatsApp:</td>
                <td style="padding-bottom: 6px;">{{ $phone }}</td>
            </tr>
            <tr>
                <td style="padding-bottom: 6px; font-weight: bold;">Mãe de Solteira:</td>
                <td style="padding-bottom: 6px;">{{ $motherName }}</td>
            </tr>
            <tr>
                <td style="padding-bottom: 6px; font-weight: bold;">Nascimento:</td>
                <td style="padding-bottom: 6px;">{{ $birthdate }}</td>
            </tr>
            <tr>
                <td style="padding-bottom: 6px; font-weight: bold;">País:</td>
                <td style="padding-bottom: 6px;">{{ $country }}</td>
            </tr>
            <tr>
                <td style="padding-bottom: 6px; font-weight: bold;">Modalidade:</td>
                <td style="padding-bottom: 6px;">{{ $modality }}</td>
            </tr>
        </table>
    </div>

    <p style="margin: 0 0 8px; font-weight: bold;">Motivo da consulta / Descrição:</p>
    <p style="margin: 0 0 24px; padding: 12px; background-color: #FAF7F0; border-left: 4px solid #2D6A4F; font-style: italic;">
        {!! nl2br(e($description)) !!}
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 0 16px;">
        <tr>
            <td style="border-radius: 6px; background-color:#2D6A4F;">
                <a href="{{ $adminUrl }}" style="display: inline-block; padding: 12px 28px; color:#FFFFFF; text-decoration:none; font-weight: bold; font-size: 15px;">
                    Ver Fila de Atendimentos
                </a>
            </td>
        </tr>
    </table>
</x-emails.layout>
