<x-emails.layout>
    <h1 style="font-size: 20px; color:#2D6A4F; margin: 0 0 16px;">Solicitação de Consulta Recebida</h1>

    <p style="margin: 0 0 16px;">Olá, {{ $userName }}.</p>

    <p style="margin: 0 0 16px;">
        Recebemos com sucesso a sua solicitação de consulta oracular (jogo de Ifá) na modalidade <strong>{{ $modality }}</strong>.
    </p>

    <p style="margin: 0 0 16px;">
        Em instantes você receberá um segundo e-mail com o <strong>link de pagamento</strong> da consulta. <strong>O atendimento só é realizado após a confirmação do pagamento.</strong> Assim que o pagamento for confirmado, o Babalawo fará o atendimento oracular tradicional com base nos dados que você forneceu, e os áudios com a análise e orientação dos Odùs serão enviados diretamente no seu WhatsApp cadastrado: <strong>{{ $phone }}</strong>.
    </p>

    <p style="margin: 0 0 16px; font-size: 13px; color:#555;">
        Se você desejar acessar a nossa plataforma no futuro para ver seu histórico de atendimentos e pagamentos, use o link abaixo para criar uma senha informando o e-mail: <strong>{{ $email }}</strong>.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 0 16px;">
        <tr>
            <td style="border-radius: 6px; background-color:#2D6A4F;">
                <a href="{{ route('password.request') }}" style="display: inline-block; padding: 12px 28px; color:#FFFFFF; text-decoration:none; font-weight: bold; font-size: 15px;">
                    Definir Senha de Acesso
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 0; font-size: 13px; color:#888;">
        Agradecemos a sua confiança e que Ifá traga caminhos de luz (Ire) para a sua jornada.
    </p>
</x-emails.layout>
