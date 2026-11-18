<x-emails.layout>
    <h1 style="font-size: 20px; color:#2D6A4F; margin: 0 0 16px;">Confirme seu e-mail</h1>

    <p style="margin: 0 0 16px;">Olá, {{ $userName }}.</p>

    <p style="margin: 0 0 16px;">
        Obrigado por se cadastrar em {{ config('app.name') }}. Confirme seu e-mail clicando no botão abaixo.
        Este link expira em 24 horas.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 0 16px;">
        <tr>
            <td style="border-radius: 6px; background-color:#2D6A4F;">
                <a href="{{ $verifyUrl }}" style="display: inline-block; padding: 12px 28px; color:#FFFFFF; text-decoration:none; font-weight: bold; font-size: 15px;">
                    Confirmar e-mail
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 0; font-size: 12px; color:#888;">
        Se o botão não funcionar, copie e cole este link no navegador:<br>
        <span style="word-break: break-all;">{{ $verifyUrl }}</span>
    </p>
</x-emails.layout>
