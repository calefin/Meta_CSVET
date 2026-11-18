# Quadro Kanban sem estilo nenhum (classes Tailwind soltas nunca compiladas)

- **Data:** 2026-09-01
- **Origem:** o professor relatou que o Quadro Kanban "não está prático", pedindo que fosse horizontal e tivesse cores, "como nos melhores sistemas".
- **Status de importação para prof-aid.com:** pendente

## Causa raiz

`submissions-kanban.blade.php` (`Filament\Pages\Page` customizada) usava só
classes utilitárias Tailwind cruas (`grid grid-cols-1 md:grid-cols-4`,
`bg-slate-900`, `border-{{ $stageInfo['color'] }}-500` etc.) — exatamente o
padrão já documentado como quebrado em `filament-custom-pages-no-css`:
`AdminPanelProvider` não registra tema Vite para o painel, então nenhuma
classe Tailwind escrita à mão num Blade de `Page` customizada é compilada em
lugar nenhum. Na prática, a tela renderizava como HTML quase sem estilo —
sem grid, sem cor de coluna, sem cartão destacado — daí a percepção de
"impraticável", mesmo a lógica de drag-and-drop e agrupamento por status
estando correta.

Como agravante, as cores das colunas eram geradas por interpolação de
string (`bg-{{ $stageInfo['color'] }}-500`), o que nem funcionaria com
Tailwind JIT normal (classes dinâmicas não são detectadas pelo scanner) —
um segundo motivo independente para as cores nunca aparecerem, mesmo que o
build estivesse disponível.

## Comportamento (antes/depois)

**Antes:** grid CSS que colapsava para 1 coluna sem nenhuma classe
utilitária aplicada, sem cor nenhuma, sem indicação de urgência.

**Depois:** reescrito com CSS próprio (`<style>` no topo do `.blade.php`,
prefixo `.kb-`, com `.dark .kb-*` para tema escuro), no mesmo padrão já
usado em `pomodoro.blade.php`/`assinatura-avulsa.blade.php`:
- Board horizontal de verdade (`display:flex` + `overflow-x:auto`), colunas
  de largura fixa (320px) com rolagem vertical própria — visual tipo
  Trello/Linear/Jira.
- Cor sólida por coluna (borda superior + contador), definida via
  `style="--kb-accent: ...; --kb-accent-soft: ..."` (variáveis CSS, não
  interpolação de classe — funciona com CSS puro sem depender de build).
- Badge de tipo de documento colorido por família (TCC/Concepção/Objetivos/
  etapas de redação = índigo; IC = âmbar; Relatórios/Artigo/Congresso =
  azul; Estágio/Necropsia/Termos = verde).
- Badge de urgência reaproveitando o mesmo SLA visual do
  `UrgentActionsWidget` (≥3 dias parado = âmbar, ≥7 dias = vermelho),
  calculado a partir de `updated_at`, oculto na coluna "Concluídos".
- Cartões com borda esquerda colorida por tipo, hover com elevação leve,
  drag-and-drop e ações (abrir arquivo original/retornado, editar)
  preservados com a mesma lógica de eventos HTML5 nativa.

`app/Filament/Pages/SubmissionsKanban.php::getStages()` simplificado: as
chaves Tailwind não usadas (`bgClass`/`borderClass`/`textClass`) saíram,
substituídas por `color`/`colorSoft`/`colorSoftDark` (hex/rgba) consumidos
diretamente pelo CSS da view.

## Arquivos alterados

- `app/Filament/Pages/SubmissionsKanban.php` — `getStages()`: troca classes Tailwind não usadas por cores hex/rgba.
- `resources/views/filament/pages/submissions-kanban.blade.php` — reescrita completa da view com CSS próprio.

## Como importar para o prof-aid.com

1. Copiar os 2 arquivos acima.
2. Nenhuma migration, config ou pacote novo.
3. Mesmo alerta de sempre: se o fork tiver alguma página customizada própria com classes Tailwind soltas, ela tem o mesmo problema — ver `filament-custom-pages-no-css`.

## Verificação local

- `php -l` limpo no model (`SubmissionsKanban.php`).
- Blade compilado via `Illuminate\View\Compilers\BladeCompiler::compileString()` (bootstrap completo da app) e o PHP resultante passou em `php -l` sem erros.
- **Testado de verdade no navegador** (não só por leitura de código): subido `php artisan serve` local + MySQL do XAMPP, populados 10 registros de teste cobrindo as 4 colunas/vários tipos de documento, login como admin local, e verificado:
  - Board horizontal com rolagem lateral funcionando (4 colunas, a 4ª só aparece rolando).
  - Cores de coluna, badges de tipo e badges de urgência renderizando corretamente em tema claro e escuro (`document.documentElement.classList.add('dark')`).
  - Drag-and-drop funcional de ponta a ponta (disparando os eventos HTML5 `dragstart`/`dragover`/`drop` via JS, já que o simulador de mouse do agente não gera drag nativo): card moveu de coluna, contadores atualizaram, toast "Status Atualizado" apareceu, e o `wire:call('updateSubmissionStatus', ...)` do Livewire funcionou sem erros.
  - Busca por texto (`wire:model.live`) filtrando os cartões corretamente.
  - Achado e corrigido durante o teste visual: o badge de dias parado (`updated_at->diffInDays(now())`) retornava float (ex.: `5.0009643398032d`) em vez de inteiro — corrigido com cast `(int)`.
  - Dados de teste e servidor local removidos/encerrados ao final (sem alteração em produção).
