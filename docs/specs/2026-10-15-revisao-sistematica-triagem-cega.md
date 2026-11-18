# Revisão sistemática / metanálise — triagem cega em dupla, kappa, PRISMA e termo

Data: 2026-10-15

## O que é

Esteira das revisões sistemáticas (POP das 20 metanálises) dentro do sistema, com o professor
como gerente:

1. **Projeto** (admin → Pesquisa & Publicações → Revisões Sistemáticas): tipo, PICO/PECO/CoCoPop/PIRD/PCC,
   protocolo PRISMA-P (em inglês, campos do OSF), registro (OSF/PROSPERO…, DOI, data, embargo ou
   justificativa), instruções aos triadores, motivos de exclusão, termos destacados (verde/vermelho)
   e tamanho do piloto de calibração.
2. **Importação**: JSON do script `scripts/revisao_sistematica/buscar.py` (recomendado) ou `.ris`,
   `.nbib`, `.csv/.txt` (Scopus/WoS). Desduplicação por DOI → PMID → título+ano (também contra o que
   já foi importado), juntando as bases de origem. Contagens por base, duplicatas e registro
   PRISMA-S de cada busca ficam em `search_counts`.
3. **Triadores**: orientando já cadastrado ou externo (nome + e-mail). Externo recebe `InviteToken`
   (categoria `external`, `auto_approve`, e-mail travado no formulário) e é obrigado a criar o login de
   aluno; ao validar o e-mail a conta é liberada direto (o professor já escolheu o aluno). O `User::created`
   vincula a conta ao triador pelo e-mail.
4. **Liberar triagem**: bloqueado sem critérios de inclusão/exclusão, registro (ou justificativa),
   registros importados e ao menos um triador.
5. **Portal → Meus Trabalhos → Triagem Cega** (`/portal/triagem-cega`): primeiro o termo
   (assinatura digitando o nome; guarda texto, SHA-256, IP, navegador, versão); depois um trabalho
   por vez com Incluir / Excluir (motivo obrigatório) / Talvez, nota opcional, atalhos I/E/1–9/T/←/→,
   progresso e "Concluir minha triagem". Cega: o aluno só vê os próprios votos; tudo revalidado no
   servidor (`SystematicReviewService::decide`).
6. **Piloto**: com `pilot_size` > 0 os triadores veem só os N primeiros; ao terminarem, o professor
   recebe o kappa e clica em "Liberar triagem completa".
7. **Conclusão da etapa** (todos concluíram): consenso incluir/excluir vira decisão final; divergência
   ou "talvez" vira conflito; professor é avisado (e-mail + Telegram) com κ e nº de conflitos;
   desempata na aba Registros (individual ou em lote) e clica em "Encerrar etapa" — pode seguir para
   **texto completo** com os mesmos triadores (motivo especial "Texto completo não obtido" = caixa
   "not retrieved" do PRISMA).
8. **Confiabilidade**: κ de Cohen por par (3 categorias) com IC 95%, concordância bruta e κ binário
   (incluir+talvez × excluir); interpretação de Landis & Koch.
9. **Saídas**: fluxograma PRISMA 2020 na página, texto de Métodos (inglês) com os números reais,
   protocolo `.md` para o OSF, planilha com todos os votos (`;`, UTF-8 BOM, proteção contra fórmula),
   incluídos em `.ris` (Zotero) e `.csv` (colunas da planilha de extração), relatório PDF (PRISMA,
   PRISMA-S, kappa, métodos, termos) e cada termo assinado em PDF (o aluno baixa o próprio).
10. **Esteira (POP)**: checklist das fases 0–6, marcado sozinho quando o sistema sabe.
11. **Artigo**: na criação do processo de revisão de artigo, "Modelo de desenvolvimento" →
    "Desenvolvimento completo" + projeto; vindo de "Criar artigo" já preenche título e os triadores
    como participantes. `article_reviews.systematic_review_id` mostra o vínculo na página do processo.

## Kit de importação

`scripts/revisao_sistematica/` (baixável em "Baixar kit de importação"): `buscar.py` (Python 3.9+, só
biblioteca padrão — PubMed E-utilities, Europe PMC, leitura de RIS/MEDLINE/CSV/JSON, DOI pelo Crossref
com título ≥ 92% e ano ±1, desduplicação, validação "título + DOI/PMID/URL", rejeitados em CSV),
`modelo_importacao.json`/`.csv` (formato `profatilio-sr/1`) e `LEIAME.md`.

## Arquivos

- Migração `2026_10_15_000001_create_systematic_reviews_tables.php` (4 tabelas + `article_reviews.systematic_review_id` + `invite_tokens.auto_approve`)
- Models `SystematicReview`, `SystematicReviewRecord`, `SystematicReviewScreener` (texto do termo, `TERMS_VERSION`), `SystematicReviewDecision`
- `App\Services\ReferenceImporter`, `App\Services\SystematicReviewService`
- `SystematicReviewResource` (+ páginas, `RecordsRelationManager`, `ScreenersRelationManager`)
- `App\Filament\Pages\TriagemCega` + `resources/views/filament/pages/triagem-cega.blade.php` (registrada no `StudentPanelProvider`)
- `SystematicReviewExportController` + `resources/views/systematic-review/{report,terms}.blade.php`, rotas `systematic-review.*`
- Alterados: `ArticleReview(Resource)`, `CreateArticleReview`, `InviteToken`, `User`, `invite.blade.php`, `routes/web.php` (convite/ativação)
- Testes: `tests/Feature/SystematicReviewFlowTest.php`

## Deploy

```
php artisan migrate --force
php artisan optimize:clear
```

Mudar o texto do termo exige subir `SystematicReviewScreener::TERMS_VERSION` (quem assinou a versão
antiga precisa assinar de novo).

## Correção de deploy (2026-10-15)

No MySQL de produção, a migration `2026_10_15_000001_create_systematic_reviews_tables`
falhou com `SQLSTATE 1059 Identifier name ... is too long`: o nome automático da FK
`systematic_review_decisions_systematic_review_screener_id_foreign` tem 65 caracteres
(limite de 64). As FKs de `systematic_review_decisions` agora têm nomes explícitos
(`sr_decisions_record_fk`, `sr_decisions_screener_fk`). Como o MySQL não reverte DDL, a
falha deixou tabelas parciais e vazias. Por isso, o `up()` descarta as quatro tabelas
`systematic_review*` antes de criá-las. **Ao importar no prof-aid.com:** usar os nomes
curtos desde o início (o bloco de limpeza só é necessário aqui).
