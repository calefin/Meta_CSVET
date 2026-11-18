# Metanálises: guia da preparação, cartão no painel do aluno, resumo e menu do professor

**Data:** 2026-10-23
**Status:** implementado no profatilio.com.br · pendente no prof-aid.com

## Problema

Um dia depois da mensagem de próximos passos, só 3 de 18 alunos tinham assinado o termo (6/36 assinaturas). O fluxo funcionava (as 3 alunas foram até o fim), mas a conferência no navegador encontrou três barreiras para o aluno e uma para o professor:

1. **Guia errado na preparação.** Depois de assinar o termo, "Próximos passos" mostrava o texto da fase de conflitos ("Aguardar o professor encerrar a etapa… resolver as discordâncias"). Nada dizia o que fazer agora: ORCID, OSF e enviar os links pela conversa.
2. **Trilha errada.** "Onde o projeto está" marcava ✓ "Protocolo registrado no OSF" e "Buscas (você está aqui)" em projetos ainda nem congelados.
3. **Programa escondido.** O aluno da seleção entrava num Painel de Controle de TCC/estágio ("Enviar Novo Documento", "Protocolo CEUA"). O programa só aparecia como um "2" ao lado de "Triagem Cega" no menu.
4. **Menu do professor.** As quatro telas do programa ficavam misturadas em "Pesquisa & Publicações", fora de ordem: "Revisões Sistemáticas" era o último item, depois de "Revisão de Artigos/Livros".

## O que mudou

- `app/Support/StageGuides.php`:
  - novo guia `preparing` (termo dos dois projetos, ORCID, OSF, links pela conversa, leitura do protocolo quando o PDF sair);
  - `roadmap()` marca "Protocolo registrado no OSF" como etapa atual enquanto não há `registration_id`.
- `resources/views/filament/pages/triagem-cega.blade.php`:
  - na preparação, usa o guia `preparing`;
  - avisa que o PDF do protocolo aparece quando o protocolo for congelado.
- `app/Filament/Widgets/MetanalisesPortalWidget.php` + view (novo), no topo do painel do aluno (`StudentPanelProvider`):
  - cada projeto com o código, o papel, ✓/⏳ termo e a próxima ação;
  - um botão direto ("Ler e assinar o termo", "Abrir o projeto" ou "Triar agora");
  - só aparece para quem tem projeto e não mostra o título antes do termo.
- `TriagemCega::myAssignments()` agora é público (reusado pelo widget).
- `app/Filament/Widgets/MetanalisesAdminWidget.php` (novo), no Dashboard do professor: aguardando resposta, termos assinados, protocolos congelados e OSF. Cada número abre a Central.
- `CentralMetanalises::programQuery()` e `summary()`: as mesmas regras da tabela da Central. O `summary()` lê só colunas leves.
- **Menu:** novo grupo **"Programa de Metanálises"** no topo, na ordem do trabalho: Central → Revisões Sistemáticas → Inscrições na Seleção → Seleção de Alunos (sorts −40 a −37). "Revisão de Artigos/Livros" fica em "Pesquisa & Publicações".
- Testes em `tests/Feature/SystematicReviewImportedPagesTest.php`:
  - guia e trilha da preparação;
  - widget do aluno e do professor;
  - **"Congelar protocolo" pelo botão, com o PDF**: hash gravado, só quem assinou baixa.

## Como importar para o prof-aid.com

1. Copiar o guia `preparing` e a regra nova do `roadmap()`, adaptando os textos do programa (ORCID/OSF).
2. Copiar os dois widgets e registrar o do aluno no painel do portal.
3. Ajustar o grupo do menu se lá houver outros programas.
4. Sem migration.
