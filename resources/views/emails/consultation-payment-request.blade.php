<x-emails.layout>
    <h1 style="font-size: 20px; color:#2D6A4F; margin: 0 0 16px;">Link de Pagamento da Consulta</h1>

    <p style="margin: 0 0 16px;">Olá, {{ $userName }}.</p>

    <p style="margin: 0 0 16px;">
        A sua solicitação de consulta oracular foi revisada e o valor correspondente foi definido em <strong>R$ {{ $amount }}</strong>.
    </p>

    <p style="margin: 0 0 16px;">
        Clique no botão abaixo para efetuar o pagamento com segurança através do Mercado Pago (PIX, cartão ou boleto).
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td style="border-radius: 6px; background-color:#2D6A4F;">
                <a href="{{ $paymentUrl }}" style="display: inline-block; padding: 12px 28px; color:#FFFFFF; text-decoration:none; font-weight: bold; font-size: 15px;">
                    Pagar Consulta
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 0 0 16px; font-size: 13px; color:#555;">
        Após a confirmação do pagamento pelo sistema, o Babalawo fará o jogo e enviará os áudios explicativos da consulta diretamente para o seu WhatsApp.
    </p>

    <p style="margin: 0; font-size: 12px; color:#888;">
        Se o botão não funcionar, copie e cole este link no navegador:<br>
        <span style="word-break: break-all;">{{ $paymentUrl }}</span>
    </p>
</x-emails.layout>
