# Notas de portabilidade — profatilio.com.br → gestao

Este `gestao/` começou como uma cópia (`git archive`) do sistema pessoal
`D:\profatilio` (profatilio.com.br), adaptada para venda multi-tenant como
módulo do prof-aid. Vez ou outra, mudanças/correções feitas em
`D:\profatilio` são portadas manualmente pra cá (ver processo no
`CHANGELOG.md` do profatilio: `git show <hash> -- <arquivo>` e aplicar o
diff aqui). **`D:\profatilio` nunca é tocado a partir daqui** — é o
ambiente pessoal do usuário, produção separada.

## Funções removidas de propósito — NÃO portar de volta

Estas funções existem e continuam válidas em `D:\profatilio` (são serviços
pessoais reais do Prof. Atilio), mas foram deliberadamente removidas do
`gestao` em 2026-08-26 por estarem fora do escopo do módulo comercial
vendido a outros professores:

- **Necropsia**: orçamento/autorização/check-in de aula prática
  (`NecropsyRequestResource`, `NecropsyClassSessionResource`,
  `NecropsyClassParticipantResource`, rotas `/solicitar-necropsia`,
  `/necropsia-aula/checkin/*`).
- **Cursos & matrícula**: `CourseResource`, `CourseEnrollmentResource`,
  `CourseModuleQuestionResource`, página `MeusCursos`, certificados de
  curso (`CertificateRequestResource`, `MeuCertificadoResource`, rotas
  `/certificado-curso/*`, `/portal/curso/*`).
- **Solicitação de Atendimento/Consultoria**: `ConsultationRequestResource`,
  rota `/solicitar-atendimento` e `/submit-consultation`.

Se um update em `D:\profatilio` tocar algum desses arquivos/rotas, **não
portar** — a menos que o usuário peça explicitamente pra trazer de volta.

## O que continua compartilhado normalmente

Tudo o mais (TCC/IC, `DocumentSubmission`, Telegram, assinatura Gov.br,
Turmas de Estágio/Assinatura Externa, portal do aluno) é código do produto
comercial de verdade — updates nessas áreas em `D:\profatilio` continuam
candidatas normais a portar pra cá, seguindo o processo de sempre.

Nota: **"Relatório de Necropsia" como tipo de documento** dentro do fluxo
de TCC/IC (`DocumentSubmission::document_type`) não é a função removida
acima — é só uma categoria de entrega acadêmica, continua existindo.
