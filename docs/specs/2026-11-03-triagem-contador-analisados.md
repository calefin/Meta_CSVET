# Triagem Cega: contador de analisados junto do trabalho (2026-11-03)

## Motivo
Uma aluna relatou que a triagem "não seguia a ordem": votou no trabalho 2 e a tela foi para o 17. A ordem estava certa. Cada triador tem a sua própria ordem sorteada (HMAC por triador, para o cegamento). Depois de cada voto, a página vai para o próximo trabalho **pendente** (`nextPendingId`), pulando os já votados. Quando a lista acaba, ela volta aos que ficaram sem voto.

O contador "Trabalho N de 50" mostrava a **posição** nessa ordem, por isso parecia um salto. O progresso real ("X de 50 decididos") ficava no cartão do topo, que some ao rolar até o trabalho.

## O que muda
- `TriagemCega::counter(done, total, decided, pilot)` monta o contador pelo **que o aluno já analisou**. No piloto, conta dentro da meta atual (25 + 25, via `pilotGoal`). Ele substitui o `position` em `getViewData`.
- Cartão do trabalho, no topo: bloco `.tc-prog` com "📊 Analisados: X de Y", "· meta N de 2 do piloto" (só no piloto), "faltam Z" e uma barra. Embaixo, uma frase:
  - pendente: "Este é o trabalho X+1 de Y. A ordem é sorteada só para você e o sistema pula os que você já votou."
  - já votado: "Este trabalho já tem o seu voto (marcado abaixo). Pode mudar, se quiser."
- Barra das decisões (`.tc-actions`, fixa no celular): linha `.tc-actions-prog` com "X de Y analisados · faltam Z · meta N".
- A ordem, o salto para o próximo pendente e o cegamento não mudaram. Não há migration.

## Arquivos
- `app/Filament/Pages/TriagemCega.php` (`counter()`, `getViewData`)
- `resources/views/filament/pages/triagem-cega.blade.php` (CSS próprio, sem Tailwind)
- `tests/Feature/SystematicReviewFlowTest.php`

## Deploy
`git pull` e `php artisan view:clear` (ou `optimize:clear`).

## Testes
`SystematicReviewFlowTest::test_card_counter_shows_how_many_were_analysed_not_the_shuffled_position`, mais o ajuste no teste do treino.
