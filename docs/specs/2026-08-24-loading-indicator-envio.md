# Indicador de carregamento ao enviar formulário (anti-clique-duplo)

- **Data:** 2026-08-24
- **Origem:** feedback recorrente de uma aluna dizendo que "o arquivo não carrega no envio" — na prática, o upload do arquivo em si já tinha indicador, mas o clique no botão Enviar/Salvar (que dispara processamento no backend: mover o arquivo pro Google Drive, disparar a auditoria de IA, enviar e-mail) ficava sem nenhum feedback visual, levando a aluna a clicar várias vezes por achar que não funcionou.
- **Status de importação para prof-aid.com:** pendente

## Comportamento

1. Ao clicar em qualquer botão de submit dentro de um formulário Filament (Criar, Salvar, ações em modal como "Publicar Despacho"), um overlay de tela cheia aparece imediatamente: fundo escurecido + spinner + texto "Enviando, aguarde... Isso pode levar alguns segundos. Não feche esta página nem clique novamente."
2. O overlay bloqueia cliques na página (evita duplo envio, complementando a proteção que o próprio Filament/Alpine já faz no nível do `<form>`).
3. O overlay desaparece automaticamente quando a requisição termina (sucesso ou erro) — nesse momento a notificação nativa do Filament (toast de sucesso/erro) já fica visível.
4. Continua existindo, sem alteração, o indicador específico de progresso de upload de arquivo (barra fina no topo + toast "Enviando arquivo... X%"), que cobre a etapa de selecionar/anexar o arquivo antes do submit.

## Arquivos alterados

- `resources/views/partials/upload-progress-indicator.blade.php` — partial compartilhada. Adicionado:
  - HTML/CSS do overlay (`#app-submit-overlay`).
  - Script que escuta `submit` (capture phase) em qualquer `<form class="fi-form">` para mostrar o overlay, e usa `Livewire.hook('request', ({succeed, fail}) => ...)` para escondê-lo quando a requisição correspondente terminar.

Essa partial já era registrada via `FilamentView::registerRenderHook(PanelsRenderHook::BODY_END, ...)` tanto em `AdminPanelProvider.php` quanto em `StudentPanelProvider.php` — nenhuma mudança foi necessária nesses providers, a correção se propaga automaticamente para os dois painéis.

## Como importar para o prof-aid.com

1. Copiar o arquivo `resources/views/partials/upload-progress-indicator.blade.php` (versão completa, incluindo o bloco novo do overlay) para o mesmo caminho no projeto prof-aid.com.
2. Confirmar que os PanelProviders do prof-aid.com (equivalentes a `AdminPanelProvider`/`StudentPanelProvider`) já registram essa partial no hook `PanelsRenderHook::BODY_END`. Se o prof-aid.com não usa essa partial ainda, registrar do zero:
   ```php
   FilamentView::registerRenderHook(
       PanelsRenderHook::BODY_END,
       fn (): string => view('partials.upload-progress-indicator')->render(),
   );
   ```
3. Não depende de nenhuma migration, config ou pacote novo — é só Blade/CSS/JS puro, compatível com Livewire 3 + Filament 3 (usa a API pública `Livewire.hook('request', ...)`).

## Possíveis melhorias futuras (não implementadas)

- Mostrar progresso real (%) durante o processamento do backend, se algum dia isso virar viável via polling/eventos (hoje é indeterminado porque o processamento — Drive, IA — não emite progresso incremental).
- Restringir o overlay a formulários específicos (hoje é global a qualquer `.fi-form`), caso algum formulário rápido/instantâneo comece a incomodar com o overlay piscando.
