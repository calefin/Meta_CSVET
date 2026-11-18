# Etapas de redação do TCC caindo em "Não Classificado" (processos somem para o professor)

- **Data:** 2026-08-31
- **Origem:** o professor relatou que, ao habilitar processos (ex: termos de aceite, entre outros), eles somem — apareciam normalmente antes da última atualização (rede de segurança "Não Classificado" de 28/08).
- **Status de importação para prof-aid.com:** pendente

## Causa raiz

Os 5 status por trás do pipeline paralelo de etapas de redação do TCC (Introdução,
Materiais e Métodos, Resultados, Discussão, Conclusão — implementado em
`2026-08-24-tcc-etapas-paralelas`) nunca foram adicionados às whitelists
`ListDocumentSubmissions::STUDENT_STATUSES`/`PROFESSOR_STATUSES` nem à query do
`UrgentActionsWidget`:

- Lado aluno: `Aguardando Introdução do Aluno`, `Aguardando Materiais e Métodos do
  Aluno`, `Aguardando Resultados do Aluno`, `Aguardando Discussão do Aluno`,
  `Aguardando Conclusão do Aluno`.
- Lado professor: `Aguardando Correção da Introdução`, `Aguardando Correção de
  Materiais e Métodos`, `Aguardando Correção dos Resultados`, `Aguardando
  Correção da Discussão`, `Aguardando Correção da Conclusão`.

Isso já era uma lacuna antes de 28/08, mas silenciosa: sem a aba "Não
Classificado", esses processos só ficavam fora das abas filtradas (apareciam
em "Todos os Envios"). A partir da rede de segurança adicionada em
`2026-08-28-status-orfao-invisivel-lista-trabalhos`, `getDefaultActiveTab()`
passou a **priorizar a aba "Não Classificado" sobre qualquer outra** sempre
que existe pelo menos um processo fora do mapa em **qualquer lugar do banco**
— não necessariamente o processo que acabou de ser habilitado. Resultado: ao
habilitar um processo (ex: Termo de Aceite), o processo em si fica
corretamente classificado em "Aguardando Meu Despacho", mas a página abre por
padrão em "Não Classificado" (por causa de alguma etapa de TCC solta em outro
aluno) — dando a impressão de que o processo recém-habilitado sumiu.

## Comportamento (antes/depois)

**Antes:** processo de etapa de TCC (Introdução/M&M/Resultados/Discussão/
Conclusão) em correção fica fora de "Aguardando o Aluno" e "Aguardando Meu
Despacho"; a partir de 28/08 passa a cair em "Não Classificado" e sequestra a
aba padrão ao abrir o painel, escondendo outros processos recém-habilitados
que estão corretamente classificados em outras abas.

**Depois:** os 10 status entram em `STUDENT_STATUSES`/`PROFESSOR_STATUSES`
(e na query do `UrgentActionsWidget`) — voltam para as abas certas
("Aguardando o Aluno" / "Aguardando Meu Despacho") e não acionam mais a aba
"Não Classificado", então `getDefaultActiveTab()` volta a abrir normalmente
em "Para Habilitar" / "Meu Despacho" quando há trabalho pendente. Cor de
badge (vermelho, "aguardando alguém agir") adicionada em
`ActiveSubmissionsWidget` para os mesmos 10 status (antes caíam no cinza
genérico do `default`).

## Arquivos alterados

- `app/Filament/Resources/DocumentSubmissionResource/Pages/ListDocumentSubmissions.php` — adiciona os 5 status de aluno a `STUDENT_STATUSES` e os 5 de professor a `PROFESSOR_STATUSES`.
- `app/Filament/Widgets/UrgentActionsWidget.php` — adiciona os 5 status de professor à query `whereIn('status', [...])`.
- `app/Filament/Widgets/ActiveSubmissionsWidget.php` — adiciona os 10 status à cor `danger` da coluna "Etapa Atual" (antes caíam no `default` cinza).

`app/Filament/Resources/DocumentSubmissionResource.php` já tinha os 10 status
corretamente mapeados na tabela principal (cor de badge + filtro dropdown) —
não precisou de alteração.

## Como importar para o prof-aid.com

1. Copiar os 3 arquivos acima.
2. Nenhuma migration, config ou pacote novo.
3. Só se aplica a forks que também tenham o pipeline de etapas de TCC em
   paralelo (`2026-08-24-tcc-etapas-paralelas`). Se o fork não tiver esse
   pipeline, este fix não se aplica.
4. Mesmo alerta do fix de 28/08: qualquer status próprio que o fork tenha
   adicionado e não exista aqui pode estar caindo em "Não Classificado" —
   conferir `ListDocumentSubmissions::unclassifiedQuery()->count()` via
   tinker antes de assumir que está tudo coberto.

## Verificação local

- `php -l` limpo nos 3 arquivos (`C:\xampp\php\php.exe -l`, PHP do XAMPP não está no PATH — ver [[local_dev_windows]]).
- Banco local (MySQL do XAMPP) sem dados de teste para reproduzir visualmente
  o antes/depois com uma linha real presa; fix validado por leitura de código
  (comparação direta entre os `match()` de status no `DocumentSubmission`
  model — que geram os 10 status — e as constantes de whitelist), já que o
  padrão do bug é idêntico ao documentado em
  `2026-08-28-status-orfao-invisivel-lista-trabalhos.md`.
