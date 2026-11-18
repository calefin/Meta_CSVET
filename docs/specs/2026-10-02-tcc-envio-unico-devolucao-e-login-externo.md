# TCC Envio Único bloqueado, devolução assinada que não aparecia ao aluno e login de aluno externo

**Data:** 2026-10-02

## 1. "TCC Completo (Envio Único)" exigia Objetivos aprovados
**Sintoma:** aluna com TCC pronto, escrito fora do sistema, não conseguia abrir o processo como
"TCC Completo — Envio Único" (erro dizendo que precisava ter enviado as partes antes). Como "TCC"
(versão final) também exige as etapas de redação aprovadas, ela ficava sem caminho nenhum.

**Causa:** a validação de `document_type` em `DocumentSubmissionResource` colocava o Envio Único
na mesma lista das 5 etapas de redação, que exigem `Objetivos de TCC` concluído.

**Correção:** Envio Único saiu dessa lista — não tem pré-requisito. A mensagem de bloqueio do tipo
"TCC" agora sugere o Envio Único para quem já tem o trabalho completo. A aprovação do Envio Único
continua replicando a aprovação nas 5 etapas (libera o "TCC" final, se necessário).

## 2. Devolução assinada não chegava ao aluno (arquivo antigo continuava aparecendo)
**Sintoma:** professor anexava o PDF assinado no "Publicar Despacho", mas o aluno continuava vendo
o arquivo devolvido anteriormente e não conseguia baixar o novo.

**Causa:** o campo "Devolução ao Aluno" vem pré-preenchido com o arquivo devolvido antes. Anexando
um novo sem remover o antigo, o estado chegava como `[antigo, novo]` e o mutator
`setGoogleDriveReturnedFileIdAttribute()` gravava o **primeiro** — o antigo. O novo era enviado
ao Drive mas ficava órfão.

**Correção:** o mutator agora prioriza o caminho diferente do valor atual (o último anexado).
Array vazio continua preservando o valor existente. Teste: `tests/Unit/ReturnedFileSetterTest.php`.

**Remediação:** processos já afetados precisam de novo "Publicar Despacho" com o PDF assinado
(remover o arquivo antigo do campo e anexar o novo). O arquivo novo órfão será pego pela
"Limpeza do Drive".

## 3. Aluno externo "não conseguia entrar"
**Causas (duas):**
- `RedirectIfSemesterSurveyPending` prendia todo aluno na Pesquisa Semestral, cujas opções são só
  TCC/Estágio/IC. Para o aluno externo (mentoria/tutoria) nada se aplica e a única saída
  ("Já concluí o curso") bloqueia novos processos. `Student::needsSemesterSurvey()` agora retorna
  `false` para `category = 'external'`.
- `StudentLogin` lançava as mensagens de status (e-mail não confirmado, aguardando aprovação,
  status inválido, sem perfil) na chave `email`, mas o Filament só exibe erros em `data.email` —
  o aluno clicava "Entrar" e nada aparecia. Chaves corrigidas para `data.email` (afeta todos os
  alunos, não só externos).

Teste: `tests/Feature/ExternalStudentLoginTest.php`.

## Importação no prof-aid.com
- Remover o Envio Único da lista de tipos que exigem Objetivos aprovados.
- Copiar o novo `setGoogleDriveReturnedFileIdAttribute()`.
- Excluir externos da pesquisa semestral; trocar `'email'` por `'data.email'` no login custom.
