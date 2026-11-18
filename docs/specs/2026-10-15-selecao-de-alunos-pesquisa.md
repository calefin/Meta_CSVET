# Seleção de alunos para os projetos de pesquisa (página pública + painel)

**Data:** 2026-10-15
**Status:** implementado no profatilio.com.br · pendente no prof-aid.com

## Objetivo

Captar alunos para os 20 projetos de revisão sistemática/metanálise com uma
página pública que deixa claros **requisitos, condições e todas as etapas** (da
inscrição à submissão do artigo), confere os requisitos automaticamente e leva o
professor da inscrição à alocação em dupla cruzada com poucos cliques.

## Para o aluno

- **`/selecao`** redireciona para a chamada aberta mais recente; **`/selecao/{slug}`** mostra:
  - topo com prazo, números-chave (projetos, horas/semana, remoto, 2 artigos);
  - "Sobre a seleção", "O que você ganha" e o esquema da **dupla cruzada**
    (líder/1º autor do projeto A + revisor cego/coautor do projeto B);
  - **passo a passo numerado** em duas fases (Seleção: inscrição → análise → entrevista →
    resultado; Projeto: login → termo → treinamento/piloto → triagem → leitura completa/extração →
    análise → redação → submissão), cada etapa com "quando" e "o que você faz";
  - **áreas dos projetos** só com código + área "blindada" (título real só depois do termo);
  - **pré-requisitos** e **condições e compromissos**; perguntas frequentes;
  - **formulário** em 5 blocos (dados, formação, interesse, disponibilidade/perfil, compromissos).
- Requisitos conferidos ao vivo (Alpine) e no servidor: curso aceito, semestre mínimo,
  horas semanais mínimas e todos os compromissos marcados. Até 3 projetos de preferência.
- Honeypot, `throttle:10,1`, prazo (`closes_at`) fecha o formulário sozinho.
- Mesmo e-mail na mesma chamada não duplica: reenvia o link de acompanhamento.
- E-mail de confirmação (síncrono) com os próximos passos e o **link pessoal de
  acompanhamento** `/selecao/acompanhar/{token}`: situação atual, entrevista marcada,
  projetos alocados, recado do professor e linha do tempo com "Você está aqui".
- Rascunho só aparece para admin logado (pré-visualização).
- Menu do site ganha "Seleção de Pesquisa" (desktop e celular) enquanto houver chamada aberta; sitemap inclui a chamada.

## Para o professor (painel admin → "Pesquisa & Publicações")

- **Seleção de Alunos** (`ResearchCallResource`):
  - "Criar chamada das metanálises": 1 clique cria a chamada em rascunho com os textos do
    guia de captação (benefícios, requisitos, compromissos, 12 etapas, FAQ, roteiro de
    entrevista, 3 eixos e 20 projetos PET-01…PET-10 / PAT-01…PAT-10).
  - Tudo editável: textos, requisitos automáticos, etapas, FAQ, eixos e projetos; cada projeto
    pode ser vinculado a uma Revisão Sistemática.
  - **Divulgar**: link, assunto + texto do e-mail e mensagem de WhatsApp prontos, com botão copiar.
  - **Matriz de duplas**: líder e revisor cego de cada projeto, vagas e nº de interessados.
- **Inscrições na Seleção** (`ResearchApplicationResource`): abas A fazer / Selecionados /
  Lista de espera / Não selecionados; badge com novas inscrições; ações:
  ver ficha, WhatsApp (wa.me com saudação), agendar entrevista (e-mail), registrar entrevista
  (roteiro em caixas + anotações), **selecionar e alocar** (líder + revisor cego; vagas ocupadas
  bloqueadas), lista de espera, não selecionar (e-mail educado), desistência, link de acompanhamento.
- Ao selecionar: se o projeto está vinculado a uma Revisão Sistemática, o aluno entra como
  triador nas duas revisões (`addScreener` com `skip_invite`) e recebe **um único** e-mail com
  os códigos, o convite para criar o login (InviteToken externo, `auto_approve`, 30 dias) se
  ainda não tem conta, e os próximos passos (termo → Triagem Cega).
- Aviso ao professor por e-mail e Telegram a cada inscrição nova.

## Arquivos

- `database/migrations/2026_10_15_000003_create_research_calls_tables.php` (`research_calls`, `research_applications`)
- `app/Models/ResearchCall.php` (conteúdo-padrão em `defaults()`, `shareText()`), `app/Models/ResearchApplication.php`
- `app/Services/ResearchRecruitmentService.php`
- `app/Http/Controllers/ResearchCallController.php` + rotas em `routes/web.php` (e sitemap)
- `resources/views/research-call/{_layout,show,done,track}.blade.php`
- `app/Filament/Resources/ResearchCallResource.php` (+ `Pages/`), `app/Filament/Resources/ResearchApplicationResource.php` (+ `Pages/`)
- `resources/views/filament/research-call/{share,matrix}.blade.php` (CSS próprio + Alpine; modal do Filament não carrega o Tailwind do app nem executa `<script>`)
- `resources/views/partials/site-nav.blade.php`, `resources/css/site.css` (`@source` das views novas) e `public/build`
- `app/Services/SystematicReviewService.php`: `addScreener()` aceita `skip_invite`
- `tests/Feature/ResearchRecruitmentTest.php`

## Decisões

- O token de acompanhamento é buscado pelo hash SHA-256 e guardado cifrado (APP_KEY) para
  reaparecer nos e-mails seguintes (entrevista, resultado).
- A etapa atual do aluno é calculada pela ordem padrão das etapas (`currentStep()`): se o
  professor reordenar as etapas, a marcação "Você está aqui" segue o índice.
- Textos da página foram reescritos sem promessas que não dependem do laboratório
  ("publicação garantida", "pontuação máxima"): a FAQ explica que a publicação depende do aceite da revista.

## Importar no prof-aid.com

1. Migration + models + service + controller + views + resources.
2. Trocar `ArticleReviewService::PROFESSOR_EMAIL` e o nome do professor nos textos de `defaults()`/e-mails pelo dono da conta (multi-tenant).
3. `@source` das views em `site.css` e rebuild dos assets.

## Ajustes de 2026-10-15 (tarde)

- **Sem IC/TCC:** a chamada passa a ser "Seleção 2026/2027 — Projetos de Pesquisa e Produção de Artigos"; o objetivo no formulário virou currículo / publicar artigos / aprender metodologia / outro; menu "Seleção de Pesquisa".
- **Títulos mais mascarados:** áreas só com disciplina + grupo de espécies (ex.: "Farmacologia clínica em lagomorfos", "Dermatopatologia equina"), sem órgão, agente, fármaco ou desfecho; descrições dos eixos genéricas. Os títulos reais nunca entram no código.
- **Desligamento por atraso (blindagem):**
  - compromisso obrigatório na inscrição: atrasos, prazos descumpridos sem justificativa ou falta de resposta podem levar ao desligamento, com redistribuição da parte e sem autoria das etapas não concluídas (texto e data do aceite ficam gravados na inscrição);
  - FAQ "O que acontece se eu atrasar?"; entrevista e e-mail da reunião citam a regra;
  - termo do portal (`SystematicReviewScreener::termsText`, versão `2026.10.2`): cláusula 9 (prazos, resposta em 2 dias úteis) e cláusula 10 (hipóteses de desligamento a–d e consequências);
  - painel: ação **Desligar do projeto** (motivo obrigatório) → status `removed`, remove o acesso às triagens vinculadas, libera a vaga e manda e-mail com o motivo e o compromisso aceito.
- **Painel de inscrições:** botões à vista **Marcar reunião**, **Aprovar** e **Reprovar**; o resto em "Mais" (WhatsApp, anotar a reunião, lista de espera, desligar, desistência, link).
- **`php artisan app:publicar-selecao [--prazo=AAAA-MM-DD] [--rascunho] [--manter-textos]`:** cria ou atualiza a chamada com os textos-padrão (mantém os vínculos com as Revisões Sistemáticas) e abre as inscrições.
- **/selecao sem chamada publicada:** página explicativa (status 404) em vez de 404 seco.
- **Correções na revisão sistemática:** a ordem embaralhada por triador usava CRC32 (linear: as ordens de dois triadores saíam correlacionadas, muitas vezes idênticas); agora usa HMAC-SHA256 com a APP_KEY. Na aprovação pela seleção, aluno com conta não recebe o aviso da triagem com o título antes de assinar o termo. Teste do piloto corrigido (supunha um registro fixo fora da amostra sorteada).

## Blindagem jurídica e aceite (2026-10-15, noite)

- **Orientador explícito:** página ("Orientador: Prof. Dr. Atilio Sersun Calefi"), compromisso, FAQ e cláusula 1 do termo.
- **Natureza da participação (mentoria):** `ResearchCall::NATURE_TEXT` — voluntária, gratuita, educacional; sem bolsa/remuneração/ressarcimento; sem jornada nem horário fixo (só prazos); não configura emprego (arts. 2º e 3º da CLT), estágio (Lei 11.788/2008) nem prestação de serviços; Lei 9.608/1998 no que couber. Painel na página, compromisso, FAQ, e-mail/WhatsApp e cláusula 2 do termo.
- **Intransferível + autoria intelectual + penalidades legais:** projetos concebidos e delimitados pelo professor; vedado repassar ou usar com outro orientador/fim; penalidades da legislação (CC arts. 186 e 927; Lei 9.610/1998 e art. 184 do CP; art. 195 da Lei 9.279/1996 quando cabível), comunicação à instituição e às revistas (COPE). Cláusulas 10 e 11 do termo.
- **Publicação e escrita obrigatórias:** artigo para periódico internacional em inglês; leitura e participação na escrita/revisão obrigatórias para autoria (cláusula 12; etapas e FAQ).
- **Desligamento:** inclui não atender ao objetivo da pesquisa apresentada; comunicado por e-mail com o motivo (cláusula 14).
- **IA:** triagem, leitura, extração e risco de viés feitos pessoalmente, sem delegar a IA (compromisso, FAQ, cláusula 6); seleção dos candidatos feita pelo professor, sem decisão automatizada (aviso LGPD, art. 20).
- **LGPD:** `ResearchCall::LGPD_TEXT` (controlador, dados, finalidades, bases legais art. 7º I e VI, sem dados sensíveis, compartilhamento, direitos art. 18, guarda art. 16, retenção de 2 anos) na página, no termo de adesão e na cláusula 15 do termo; ação "Excluir dados (pedido LGPD)" no painel.
- **Termo de adesão no envio:** caixa obrigatória; texto exato (com nome, e-mail, data/hora e IP) gravado em `adhesion_text` + `adhesion_sha256` + `adhesion_accepted_at`, além de IP e navegador; cópia integral com o hash no e-mail de confirmação; comprovante na página de acompanhamento e na ficha do painel.
- **E-mail confirmado:** e-mail digitado duas vezes; `email_verified_at` é gravado quando o aluno abre o link de acompanhamento (só chega pelo e-mail); Marcar reunião e Aprovar só aparecem com o e-mail confirmado; ação "Reenviar confirmação".
- Termo do portal na versão `2026.10.5` (16 cláusulas). Migration `2026_10_15_000004_add_adhesion_and_email_check_to_research_applications`.

## Validação, encerramento e mudança de linha (2026-10-15, fim do dia)

- **Áreas alinhadas aos protocolos atualizados** (pasta "18 metanálises", manifest de 15/10): PET-03 farmacologia anestésica em exóticos, PET-04 roedores e lagomorfos, PET-05 epidemiologia clínica em exóticos, PET-06 clínica médica e cirúrgica de furões, PAT-01 oncopatologia de pequenos mamíferos (Eixo 2), PAT-06 doenças infecciosas de pequenos mamíferos (Eixo 3). Eixo 2 deixa de ser só cães. Aplicar em produção com `php artisan app:publicar-selecao`.
- **Encerrar/Reabrir inscrições:** botão na tela de inscrições, na lista e na edição da chamada; `app:publicar-selecao --encerrar` / `--reabrir`. Atualizar os textos não reabre uma chamada encerrada. Inscrições recebidas seguem no painel normalmente.
- **Mudar de linha** (inscritos não aprovados): novo eixo + até 3 projetos dessa linha, aviso opcional por e-mail, histórico "De → Para". **Realocar projetos** (aprovados): troca líder/revisor cego com checagem de vagas, move o acesso de triador entre as revisões vinculadas e avisa o aluno.
- **Inscrições anteriores ao termo de adesão:** a página de acompanhamento mostra o termo para aceite (texto exato + IP + navegador + SHA-256, comprovante por e-mail); "Pedir confirmação e aceite" no painel; "Confirmar e-mail manualmente" (com observação no histórico). Aprovar exige e-mail confirmado **e** termo de adesão aceito; Marcar reunião exige e-mail confirmado.
