# Triagem: brechas e vieses corrigidos + pipeline de submissão por tipo de artigo

Data: 2026-10-15 (complementa `2026-10-15-revisao-sistematica-triagem-cega.md`)

## Auditoria (testes de ataque locais)

| Tentativa | Antes | Agora |
|---|---|---|
| Aluno votar como outro triador / em projeto alheio / motivo inventado (adulterando o Livewire) | bloqueado | bloqueado |
| Aluno abrir telas/downloads do professor | 403 | 403 |
| Professor decidir antes dos alunos terminarem (fura a dupla cega) | permitido | bloqueado (`professorCanResolve`) |
| Cadastro por convite genérico com o e-mail do triador "tomava" a vaga | vinculava na criação da conta | só vincula ao validar o e-mail (`linkVerifiedUser` em `/activate`) |
| Piloto = primeiros importados (uma base, um período) | sim | amostra aleatória fixa (`random_key`) |

## Controles de viés

- **Ordem por triador**: embaralhamento fixo diferente para cada aluno (`orderFor`), evitando que fadiga e efeito de ordem caiam nos mesmos trabalhos e inflem a concordância.
- **Consenso auditável**: `ta_consensus`/`ft_consensus` guardam a decisão dos alunos; sobrepor exige justificativa e vai para o relatório ("Auditoria"). Ações em lote não sobrepõem consenso.
- **Piloto com κ < 0,60** só é liberado com a justificativa registrada (ex.: reunião de calibração).
- **Ritmo**: mediana de segundos entre decisões (pausas > 10 min ignoradas); < 5 s/trabalho é sinalizado no painel e no relatório.
- **Esteira por tipo de revisão** (`SystematicReview::pipeline()`): escopo sem RoB/metanálise (PRISMA-ScR), RS sem metanálise (SWiM), acurácia (QUADAS-2, bivariado, PRISMA-DTA), prevalência (JBI, proporções). Novas fases: literatura cinzenta, extração e RoB em dupla, GRADE, busca atualizada (≤ 12 meses), depósito dos dados com DOI.
- **Alertas das revistas** (`journalWarnings`): < 2 bases, busca com mais de 11 meses, sem registro, triador único.
- Texto de Métodos usa a diretriz do tipo.

## Pipeline de submissão por tipo de artigo (processo de revisão de artigo)

- `article_reviews.article_type` + `submission_checklist`. Catálogo em `App\Support\ArticleTypes` (16 tipos, a partir das normas da NVAS e da rede EQUATOR): experimental (ARRIVE 2.0), observacional (STROBE-Vet), ensaio clínico (CONSORT/REFLECT), acurácia (STARD), comunicação curta, relato e série de casos (CARE), imagem clínica, RS/MA (PRISMA 2020), escopo (PRISMA-ScR), narrativa/mini-revisão (SANRA), nota técnica, artigo de dados (FAIR), perspectiva, carta.
- Página do processo: diretriz com link de busca na EQUATOR, limites × medidas estimadas da versão atual (`ManuscriptMetrics`: palavras do texto e do resumo, referências, figuras + tabelas) e o checklist.
- Checklist: itens comuns (carta, página de título, anonimização, autor graduado garantidor, ORCID, CRediT, conflitos, financiamento, Data Availability, declaração de IA, idioma/figuras, referências com DOI, similaridade) + itens do tipo (CEUA, 3Rs, SISBIO, SisGen, MAPA, CEP/LGPD, consentimento do tutor, randomização, padrão de referência, CARE…). Cada item: Pendente / Feito / Não se aplica.
- Com revisão sistemática vinculada, o sistema comprova sozinho (🤖) registro, bases + cinzenta, data da busca, fluxograma, dupla e RoB/dados quando marcados na esteira.
- **"Registrar envio à revista" é bloqueado com item pendente** (só para processos com tipo de artigo definido; os antigos continuam iguais).

## Deploy

```
php artisan migrate --force
php artisan optimize:clear
```

Testes: `tests/Feature/SystematicReviewFlowTest.php` (17), `tests/Feature/ArticlePipelineTest.php` (4).
