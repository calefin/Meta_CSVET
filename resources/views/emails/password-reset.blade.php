<x-emails.layout>
    <h1 style="font-size: 20px; color:#2D6A4F; margin: 0 0 16px;">Redefinir senha</h1>

    <p style="margin: 0 0 16px;">Olá, {{ $userName }}.</p>

    <p style="margin: 0 0 16px;">
        Recebemos uma solicitação para redefinir a senha da sua conta em {{ config('app.name') }}.
        Clique no botão abaixo para escolher uma nova senha. Este link expira em 60 minutos.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 0 16px;">
        <tr>
            <td style="border-radius: 6px; background-color:#2D6A4F;">
                <a href="{{ $resetUrl }}" style="display: inline-block; padding: 12px 28px; color:#FFFFFF; text-decoration:none; font-weight: bold; font-size: 15px;">
                    Redefinir senha
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 0 0 16px; font-size: 13px; color:#555;">
        Se você não solicitou a redefinição de senha, pode ignorar este e-mail com segurança.
    </p>

    <p style="margin: 0; font-size: 12px; color:#888;">
        Se o botão não funcionar, copie e cole este link no navegador:<br>
        <span style="word-break: break-all;">{{ $resetUrl }}</span>
    </p>
</x-emails.layout>
