<x-emails.layout>
    <h1 style="font-size: 20px; color:#2D6A4F; margin: 0 0 16px;">Novo Cadastro Recebido</h1>

    <p style="margin: 0 0 16px;">
        Um novo usuário se cadastrou na plataforma e está aguardando aprovação. Veja os detalhes abaixo:
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
                <td style="padding-bottom: 6px; font-weight: bold;">País:</td>
                <td style="padding-bottom: 6px;">{{ $country }}</td>
            </tr>
            <tr>
                <td style="padding-bottom: 6px; font-weight: bold;">Nascimento:</td>
                <td style="padding-bottom: 6px;">{{ $birthdate }}</td>
            </tr>
        </table>
    </div>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 0 16px;">
        <tr>
            <td style="border-radius: 6px; background-color:#2D6A4F;">
                <a href="{{ $adminUrl }}" style="display: inline-block; padding: 12px 28px; color:#FFFFFF; text-decoration:none; font-weight: bold; font-size: 15px;">
                    Ver Usuários Pendentes
                </a>
            </td>
        </tr>
    </table>
</x-emails.layout>
