<x-emails.layout>
    <h1 style="font-size: 20px; color:#2D6A4F; margin: 0 0 16px;">Seu orçamento está pronto</h1>

    <p style="margin: 0 0 16px;">Olá, {{ $userName }}.</p>

    <p style="margin: 0 0 16px;">
        O orçamento para o trabalho <strong>{{ $workName }}</strong> foi preparado
        @if($hasMultipleOptions)
            com opções para você escolher, no valor de até <strong>R$ {{ $amount }}</strong>.
        @else
            no valor de <strong>R$ {{ $amount }}</strong>.
        @endif
    </p>

    <p style="margin: 0 0 16px;">
        Acesse o portal para ver os detalhes{{ $hasMultipleOptions ? ', escolher a opção desejada' : '' }} e aprovar o orçamento.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td style="border-radius: 6px; background-color:#2D6A4F;">
                <a href="{{ $portalUrl }}" style="display: inline-block; padding: 12px 28px; color:#FFFFFF; text-decoration:none; font-weight: bold; font-size: 15px;">
                    Ver Orçamento
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 0; font-size: 12px; color:#888;">
        Se o botão não funcionar, copie e cole este link no navegador:<br>
        <span style="word-break: break-all;">{{ $portalUrl }}</span>
    </p>
</x-emails.layout>
