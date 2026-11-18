Ìjọ Ifá Èjìogbè — Opção escolhida, aguardando pagamento

Olá, Sacerdote,

Um cliente escolheu uma das opções de orçamento enviadas e está prosseguindo para o pagamento:

- Cliente: {{ $clientName }} ({{ $clientEmail }})
- Opção escolhida: {{ $optionLabel }}
@if ($comment)
- Comentário: {{ $comment }}
@endif
- Valor: R$ {{ $total }}
- Status: Aguardando pagamento

Você pode verificar os detalhes acessando o Painel do Sacerdote no link:
{{ $adminUrl }}

--
Ìjọ Ifá Èjìogbè
