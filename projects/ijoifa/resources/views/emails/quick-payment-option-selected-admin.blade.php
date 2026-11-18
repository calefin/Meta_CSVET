<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Opção escolhida — aguardando pagamento</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #1A1A1A; margin: 0; padding: 20px; background-color: #FAF7F0;">
    <div style="max-width: 600px; margin: 0 auto; background: #FFFFFF; border: 1px solid #7B4F2E; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
        <!-- Header -->
        <div style="background-color: #2D6A4F; color: #FFFFFF; padding: 20px; text-align: center;">
            <h2 style="margin: 0; font-size: 20px; letter-spacing: 1px;">Ìjọ Ifá Èjìogbè</h2>
            <p style="margin: 5px 0 0 0; font-size: 14px; opacity: 0.9;">Opção escolhida — aguardando pagamento</p>
        </div>

        <!-- Body -->
        <div style="padding: 24px;">
            <h3 style="color: #2D6A4F; margin-top: 0; font-size: 18px; border-bottom: 1px solid #7B4F2E/10; padding-bottom: 8px;">Olá, Sacerdote</h3>
            <p style="font-size: 14px; margin-bottom: 20px;">
                Um cliente escolheu uma das opções de orçamento enviadas e está prosseguindo para o pagamento:
            </p>

            <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 14px;">
                <tr>
                    <td style="padding: 8px 0; font-weight: bold; width: 150px; color: #555;">Cliente:</td>
                    <td style="padding: 8px 0;">{{ $clientName }} ({{ $clientEmail }})</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: bold; color: #555;">Opção escolhida:</td>
                    <td style="padding: 8px 0; font-weight: bold; color: #2D6A4F;">{{ $optionLabel }}</td>
                </tr>
                @if ($comment)
                    <tr>
                        <td style="padding: 8px 0; font-weight: bold; color: #555;">Comentário:</td>
                        <td style="padding: 8px 0;">{{ $comment }}</td>
                    </tr>
                @endif
                <tr>
                    <td style="padding: 8px 0; font-weight: bold; color: #555;">Valor:</td>
                    <td style="padding: 8px 0; font-weight: bold; color: #2D6A4F; font-size: 16px;">R$ {{ $total }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: bold; color: #555;">Status:</td>
                    <td style="padding: 8px 0;">Aguardando pagamento</td>
                </tr>
            </table>

            <div style="text-align: center; margin-top: 30px;">
                <a href="{{ $adminUrl }}" style="display: inline-block; background-color: #2D6A4F; color: #FFFFFF; text-decoration: none; padding: 12px 24px; font-weight: bold; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    Acessar Painel do Sacerdote
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div style="background-color: #FAF7F0; color: #555; padding: 15px; text-align: center; font-size: 12px; border-top: 1px solid #7B4F2E/10;">
            <p style="margin: 0;">&copy; {{ date('Y') }} {{ config('app.name') }}. Todos os direitos reservados.</p>
        </div>
    </div>
</body>
</html>
