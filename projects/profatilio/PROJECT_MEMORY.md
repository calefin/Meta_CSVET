# Memória Técnica do Projeto — profatilio.com.br

Este arquivo registra a arquitetura, regras de negócio e o histórico de desenvolvimento da plataforma **profatilio.com.br (V2)** para garantir agilidade e consistência em melhorias futuras.

---

## 🏗️ 1. Arquitetura e Tecnologias Core

- **Framework**: Laravel 12.x com PHP 8.2 (configurado no ambiente HostGator/cPanel).
- **Interface e Painel de Controle**: Filament Admin (v3.x) servindo como painel administrativo (Prof. Atilio) e painel do estudante.
- **Banco de Dados**: MySQL (no HostGator, utiliza o prefixo de conta do cPanel, ex: `atilio_profatilio`).
- **Segurança & Criptografia**: Hash SHA-256 para auditorias forenses, e Termo de Consentimento LGPD mandatório.
- **Auditoria de IA**: Job do Laravel (`AuditDocumentWithAI`) integrado à API do Gemini (`gemini-2.5-flash` ou `gemini-2.5-pro` dependendo do env) que analisa citações ABNT NBR 10520:2023, nomenclatura civil/forense e veracidade biológica.
- **Processamento de Filas (Queue)**: Banco de dados (`database` driver) processado via Cron Job a cada minuto com a flag `--stop-when-empty` para evitar estouro de memória no cPanel.
- **Geração de PDF**: Integrado com `barryvdh/laravel-dompdf` para geração automática e arquivamento de recibos.
- **Armazenamento em Nuvem**: Suporta armazenamento local (`public`), Google Drive (via `yaza/laravel-google-drive-storage`) ou OneDrive Pessoal/Family (via driver customizado `OneDriveAdapter` desenvolvido sob medida). O disco ativo é definido por `FILESYSTEM_DISK` no `.env`.

---

## 📁 2. Estrutura de Pastas e Arquivos Principais

- **Rotas**:
  - [routes/web.php](file:///D:/profatilio/routes/web.php): Contém a rota de convites, rotas de ativação, rotas públicas de visualização/download de recibos criptografados, e a rota de download seguro de arquivos acadêmicos (`/documento/download/{id}/{type}`) para preservação da LGPD independente do storage.
- **Recursos do Filament (Painéis)**:
  - [StudentResource.php](file:///D:/profatilio/app/Filament/Resources/StudentResource.php): Gestão de alunos e títulos acadêmicos vinculados.
  - [DocumentSubmissionResource.php](file:///D:/profatilio/app/Filament/Resources/DocumentSubmissionResource.php): Entrada e ciclo de vida de todos os documentos (Fluxos 1 a 8).
  - [AcademicProjectResource.php](file:///D:/profatilio/app/Filament/Resources/AcademicProjectResource.php): Aba de arquivo histórico que lista projetos, vigências e possui aba para documentos por tipo.
  - [ActiveSubmissionsWidget.php](file:///D:/profatilio/app/Filament/Widgets/ActiveSubmissionsWidget.php): Widget de dashboard que lista processos ativos e suas etapas.
- **Modelos do Eloquent**:
  - [User.php](file:///D:/profatilio/app/Models/User.php): Usuários e controle de papéis (`admin` vs `student`).
  - [Student.php](file:///D:/profatilio/app/Models/Student.php): Detalhes acadêmicos, termos LGPD e status de ativação.
  - [AcademicTitle.php](file:///D:/profatilio/app/Models/AcademicTitle.php): Armazena os temas/títulos vinculados. Contém travas de imutabilidade.
  - [DocumentSubmission.php](file:///D:/profatilio/app/Models/DocumentSubmission.php): Core dos fluxos. Controla a geração de IDs únicos (`PROJ-IC-...`, `TCC-...`), auditorias forenses, logHeader e disparo de e-mails.
- **Jobs e Lógica de IA**:
  - [AuditDocumentWithAI.php](file:///D:/profatilio/app/Jobs/AuditDocumentWithAI.php): Baixa arquivos do Google Drive, extrai o texto (PDF/DOCX), chama a API do Gemini para validar a ABNT, nomenclatura de "responsável legal" (bloqueando "tutor"), esteatose favorável em elasmobrânquios, e verifica desvios de conteúdo por escopo do fluxo.
- **Controladores e Views**:
  - [DocumentAuditController.php](file:///D:/profatilio/app/Http/Controllers/DocumentAuditController.php): Controla a rota de recibos (renderização e download de PDF físico via query `?download=1`).
  - [document_receipt.blade.php](file:///D:/profatilio/resources/views/document_receipt.blade.php): Template HTML/CSS moderno do recibo impresso e baixado em PDF.

---

## 🔒 3. Diretrizes de Governança e LGPD

1. **Máscara de Dados (Minimização)**:
   - Para o estudante, RGM, e-mail completo e telefone são mascarados como `***` ou simplificados.
   - O nome do estudante é reduzido ao **Primeiro e Último Nome** (ex: "Atilio Calefi").
   - O Prof. Atilio (admin) tem acesso visual irrestrito e analítico no painel.
2. **Opt-in de Termos**:
   - Caixa obrigatória `terms_accepted` no formulário de convite e perfil do aluno com o texto padrão de conformidade LGPD.
3. **Imutabilidade**:
   - Títulos associados a processos em andamento ou concluídos não podem ser editados ou deletados pelo estudante.

---

## ⚡ 4. Lógica de Negócio dos Fluxos de Trabalho (1 a 8)

1. **TCC** (`TCC-YYYY-XXXX`): Ciclo de vaivém de correções até a versão final assinada pelo professor.
2. **Projeto de IC** (`PROJ-IC-YYYY-XXXX`): Revisões estruturais até a marcação de "Versão Final" pelo orientador.
3. **Relatórios, Artigos, Relatos e Trabalhos** (`REL-IC-`, `ART-`, `CASO-`, `CONG-`): Revisões periódicas. A entrega bem-sucedida de `REL-IC` suspende e remove os alertas regressivos do trimestre vigente do Fluxo 8. Para submissões do tipo `Artigo Científico`, é obrigatório informar a revista de destino (`metadata.target_journal`) e o link das normas de formatação (`metadata.journal_formatting_url`).
4. **Plano de Estágio** (`PLAN-EST-YYYY-XXXX`): Envio de PDF pelo aluno e retorno assinado pelo professor.
5. **Termo de Aceite de Orientação** (`TERMO-ACEITE-YYYY-XXXX`): Envio e devolução assinada.
6. **Avaliações de Estágio** (`AVAL-EST-YYYY-XXXX`): Upload múltiplo e compactado de PDFs de comprovação, limitado a 10MB no total. Exige carga horária, locais e nota.
7. **Relatório de Necropsia** (`NECRO-YYYY-XXXX`): Captura metadados do animal, exame e data. Correções de descrição macroscópica.
8. **Termo de Aceite de IC** (`ACEITE-IC-YYYY-XXXX`): Captura IP, Timestamp de milissegundos e User-Agent para gerar uma assinatura Hash SHA-256 única. Cria e arquiva o recibo em PDF dinâmico no storage do aluno. Envia e-mails de alertas regressivos (30, 15 e 7 dias antes do prazo trimestral) que são suspensos na entrega do Relatório (Fluxo 3).

---

## 📝 5. Histórico Recente e Próximos Passos

### ✅ Funcionalidades Recém-Concluídas:
1. **Integração Completa com Telegram**:
   - Bot com webhooks ativos suportando comandos `/start admin_{id}` e `/start {student_id}` para vinculação e desvinculação automática de contas.
   - Status visual de conexão com o Telegram no Dashboard do Admin.
   - Envio de notificações instantâneas sobre revisões, aprovações e convites.
2. **Assinatura Digital em Lote via Gov.br**:
   - Assinatura eletrônica integrada (ITI) individual ou em grupo.
   - Nova aba dedicada "Aguardando Assinatura" no dashboard administrativo.
   - Retorno automático de PDFs assinados direto para o Google Drive e mudança de status.
3. **Novo Tipo de Documento: Protocolo CEUA/CEP**:
   - Suporte nativo com prefixo de protocolo customizado, geração e validação automática.
4. **Envio de E-mails em Lote & Informativo Geral**:
   - Disparo individual, bulk e broadcast de informativos a todos os alunos via painel (ex: anúncio de integração com o Telegram).
5. **Conformidade LGPD**:
   - Banner de cookies persistente e política de privacidade interativa lincada à Lei Federal nº 13.709/2018.
6. **Normalização de Nomes de Usuários**:
   - Mutator no model `User` que formata automaticamente nomes em Title Case (ex: `ADRIANA DE SIQUEIRA` -> `Adriana de Siqueira`), mantendo preposições brasileiras em minúsculas.
   - Migration de dados históricos criada e testada com sucesso.
7. **Quadro Kanban de Submissões no Filament**:
   - Visualização Kanban interativa por colunas (*Pendentes*, *Em Análise*, *Aguardando Ajustes*, *Concluídos*) para gerenciamento de documentos e submissões dos alunos.
   - Atualizações automáticas de status no banco de dados e disparo automático de notificações por e-mail/Telegram via drag-and-drop nativo.
8. **Alertas Visuais de Prazos Trimestrais de IC**:
   - Cálculo automático de trimestre e dias restantes com código de cores e badges no dashboard.
   - Widget de resumo no topo do painel administrativo com estatísticas consolidadas.
9. **Otimização de Cache da API do Google Drive**:
   - Caching transparente de existências de arquivos, metadados e listagens por 10 minutos para contornar latência de API.
   - Ganchos do Eloquent para invalidar automaticamente os caches nas submissões.
10. **Structured Data (JSON-LD) para SEO**:
    - Injeção de dados estruturados Schema.org (JSON-LD) em [welcome.blade.php](file:///D:/profatilio/resources/views/welcome.blade.php) para catalogar o Prof. Atilio e a CSVET, otimizando visualização e indexação orgânica no Google.
11. **Sitemap Dinâmico via Rota**:
    - Removido sitemap estático e substituído por uma rota Laravel que gera a árvore XML dinâmica, incluindo páginas públicas e recibos criptografados.
12. **Exportação Consolidada de Orientações em PDF**:
    - Rota segura `/admin/relatorio-consolidado` e visual [admin_consolidated_report.blade.php](file:///D:/profatilio/resources/views/admin_consolidated_report.blade.php) em paisagem que resume os prazos de todos os alunos de IC e TCC.
    - Botão de exportador adicionado diretamente no painel Filament.
13. **Suporte Reverso no Bot do Telegram**:
    - Novo comando `/suporte <mensagem>` no webhook que armazena chamados no banco e repassa em tempo real para o Telegram do Prof. Atilio.
14. **Geração Segura de Process ID (Sem Duplicações)**:
    - Substituição da lógica antiga baseada em `count() + 1` por uma verificação do maior número de sequência existente (`max(sequence) + 1`) via método `generateUniqueProcessId` no modelo `DocumentSubmission`. Isso impede erros de violação de chave única (`UniqueConstraintViolationException`) quando categorias são editadas ou submissões são excluídas.
15. **Acesso Direto à Edição de Processos para Admin**:
    - Ajustado o `recordUrl` da tabela no painel do administrador para que o clique direto em qualquer linha leve à tela de edição em vez da tela de visualização (onde os campos ficam bloqueados).
16. **Correção do Lifecycle de Rascunho vs Publicação**:
    - Introdução da flag `isPublishing` na página `EditDocumentSubmission` para impedir que o gancho `beforeSave` force a submissão a ser salva sempre como rascunho (`is_draft_save = true`) quando o botão de publicação do despacho for pressionado.
17. **Envio Assíncrono de E-mails (Otimização)**:
    - Alterado o disparo de e-mails em `DocumentSubmission` e `StudentResource` de síncrono (`Mail::send`) para assíncrono em fila (`Mail::queue`). Isso evita que requisições Web travem esperando a resposta do servidor de e-mail (SMTP), tornando a publicação de despachos instantânea.
18. **Políticas de Edição para Alunos e Acesso Direto**:
    - Criado o método `canStudentEdit()` no modelo para definir quando um aluno pode reenviar arquivos e observações (quando o status for `Aguardando Ajustes do Aluno`, `needs_corrections`, etc.).
    - Ajustado o `recordUrl` em `DocumentSubmissionResource`, `SubmissionsRelationManager` e `ActiveSubmissionsWidget` para direcionar o aluno diretamente para a página de edição se a submissão puder ser corrigida por ele, e para a página de visualização caso contrário.
19. **Prevenção contra Processos Duplicados**:
    - Adicionada regra de validação na criação de novas submissões para bloquear a abertura de processos em duplicidade para um mesmo título/projeto em andamento (comparando `academic_title_id` ou o texto do título de forma case-insensitive e excluindo processos concluídos). O bloqueio aplica-se apenas a projetos em correção (TCC, Relatório de Estágio, Iniciação Científica e Relatório) e está desativado para documentos de assinatura (Termos de Aceite, Planos de Estágio, CEUA/CEP) e avaliações.
20. **Comentários Inlines na Criação**:
    - Movido o campo de observações do aluno (`student_notes`) para a primeira seção do formulário de criação/edição. Agora ele é totalmente visível durante o preenchimento, evitando seções colapsadas que eram omitidas no envio.
21. **Agrupamento Dinâmico de Status**:
    - O campo de status no formulário agora é renderizado dinamicamente com base no tipo de documento selecionado (campo reativo via `live()`), exibindo apenas as opções válidas para o fluxo do processo em questão e evitando atribuições incorretas de status.
22. **Lifecycle da Triagem e Reclassificação de Categoria**:
    - Reordenado o hook `updating` no modelo `DocumentSubmission` para processar a mudança de `document_type` e a regeneração de `process_id` antes do renaming de arquivos.
    - Aprimorado o fluxo de renomeação para detectar alterações no `document_type`, renomeando fisicamente os arquivos salvos em disco/Google Drive/OneDrive para corresponder ao novo ID e categoria do processo.
23. **Correções no Fluxo de Cancelamento**:
    - Adicionado o status `'Cancelado'` ao método `isConcluded()` no modelo. Isso finaliza a submissão de forma que não seja mais considerada ativa, desbloqueando o aluno para abrir novos processos para o mesmo tipo de documento.
    - Corrigido o envio de e-mails para que processos cancelados recebam uma mensagem clara de cancelamento e instruções para a abertura de um novo processo, em vez da mensagem padrão de boas-vindas da triagem habilitada.
24. **Correção de Processos Concluídos na Fila de Pendências**:
    - Ajustado o widget `ActiveSubmissionsWidget` para excluir explicitamente os status `'Assinatura Realizada e Auditada'` (assinaturas avulsas concluídas) e `'Cancelado'` da fila de pendências em andamento.
    - Resolvido o bug em que processos salvos anteriormente como rascunho com o arquivo assinado não eram marcados como concluídos após a publicação do rascunho. Isso ocorria porque o atributo `google_drive_returned_file_id` não era mais considerado "dirty" no momento da publicação. A regra agora auto-conclui a submissão se houver um arquivo assinado e a publicação for efetuada, respeitando qualquer alteração manual de status.
25. **Fila Assíncrona para E-mails Públicos**:
    - Convertidos todos os disparos síncronos de e-mail (`Mail::send`) remanescentes em `routes/web.php` para e-mails em fila (`Mail::queue`), eliminando os atrasos de conexão SMTP no carregamento do pré-cadastro de estudantes e na ativação de contas via e-mail.
26. **Fix da Exibição e Registro de Comentários do Aluno (Resubmissão)**:
    - Corrigido o hook `created` e `creating` no modelo `DocumentSubmission` para inicializar a linha do tempo (timeline) na criação gravando o comentário inicial do aluno (`student_notes`), garantindo que ele não seja perdido.
    - Ajustados os botões da página de edição (`EditDocumentSubmission`): estudantes agora veem "Enviar Correções / Comentários" (ação `enviarCorrecoes`) em vez do botão do professor "Publicar Despacho". Ao clicar nele, a flag `is_draft_save` é definida como `false` para indicar que a correção foi enviada.
    - Implementada a transição automática de status no hook `updating` do modelo `DocumentSubmission` quando o aluno envia correções (de um status de ajuste como `Aguardando Ajustes do Aluno` para o respectivo status de revisão pendente do professor com base no tipo de documento).
    - Aprimorada a gravação de histórico de status: quando a ação é realizada pelo estudante, grava o seu comentário (`student_notes`) na timeline em vez de usar o sumário do orientador (`ai_executive_summary`).
    - Adicionada notificação por e-mail e Telegram específica para o Prof. Atilio avisando do reenvio de correções com o comentário do aluno.

27. **Simplificação da Triagem e Correção de Validação de Assinatura**:
    - Ocultados os campos de resposta (`google_drive_returned_file_id` e a seção de parecer do professor) da tela de edição enquanto o processo estiver no status inicial `'Aguardando Triagem'`. Isso separa a triagem (ver e habilitar/cancelar) da fase de resposta.
    - Corrigido o hook de atualização no modelo `DocumentSubmission` para que a verificação de assinatura digital ICP-Brasil e o renomeamento de arquivos só ocorram se o arquivo retornado tiver sido efetivamente modificado (`google_drive_returned_file_id !== getOriginal('google_drive_returned_file_id')`).
    - **Restrição de Assinatura ICP-Brasil**: A auditoria criptográfica da assinatura digital local só é realizada quando o status do processo transiciona para um estado concluído/assinado (status que começam com `Processo Concluído -` ou que sejam `approved`, `returned_signed` ou `Assinatura Realizada e Auditada`). Isso permite salvar feedbacks, pareceres parciais e enviar para o status `Aguardando Ajustes do Aluno` com rascunhos sem forçar a validação de assinatura de forma prematura.
28. **Botão de Retorno nos Formulários de Necropsia**:
    - Adicionado link "Voltar ao site principal" no rodapé dos formulários públicos de orçamento (`quote_form.blade.php`) e autorização/assinatura (`auth_form.blade.php`) de exames necroscópicos para unificar e facilitar a navegação do usuário.
29. **Correção de Download e Fallback de Storage (Resolução do Erro 404)**:
    - Corrigido o bug na rota `/documento/download-path` em que a busca no banco de dados falhava ao tentar correspondência exata do caminho de arquivos salvos em formato de array JSON (`google_drive_file_id`), pois estes contêm barras e caracteres unicode escapados (`\/` e `\u00e3`). Aprimorada a consulta `LIKE` para testar variações com/sem escape de barras e com codificação JSON nativa.
    - Implementada uma lógica sequencial de busca nos discos (`google`/`onedrive` -> `local` -> `public`) tanto na ação de download por ID/Tipo quanto no download por caminho. Isso impede erros 404 em caso de migrações de storage ou downloads locais em ambiente de desenvolvimento.
    - Criada a suite de testes automatizados `DocumentDownloadFallbackTest` que simula e valida ambas as correções.

33. **Otimização de SEO e Indexação de Atendimento**:
    - Inclusão da rota pública `/solicitar-atendimento` na lista de URLs indexadas no sitemap dinâmico gerado em [web.php](file:///home/calefi/Documentos/profatilio/routes/web.php#L531).
    - Adição de meta tags SEO completas (descrição, keywords, robots, Open Graph e Twitter Cards) e de dados estruturados Schema.org (`ContactPage`/`ProfessionalService` em formato JSON-LD) no template [solicitar_atendimento.blade.php](file:///home/calefi/Documentos/profatilio/resources/views/solicitar_atendimento.blade.php#L5-L78).
    - Criação de um artefato estratégico detalhado de marketing digital e otimizações de conversão em [seo_recommendations.md](file:///home/calefi/.gemini/antigravity-cli/brain/58ab96f3-b267-46ad-af8c-245249aae96d/seo_recommendations.md).
34. **Botão de WhatsApp Flutuante com Widget Premium**:
    - Criação de uma view parcial reutilizável [whatsapp_widget.blade.php](file:///home/calefi/Documentos/profatilio/resources/views/partials/whatsapp_widget.blade.php) contendo um botão flutuante animado (com efeito de pulso) e um popup de chat integrado utilizando Alpine.js e Tailwind CSS.
    - O telefone do WhatsApp é recuperado dinamicamente via `config('services.whatsapp.phone')`, mapeado ao arquivo de configuração [services.php](file:///home/calefi/Documentos/profatilio/config/services.php) e documentado em [.env.example](file:///home/calefi/Documentos/profatilio/.env.example).
    - Inclusão da view parcial em todas as páginas públicas chave do sistema: na home ([welcome.blade.php](file:///home/calefi/Documentos/profatilio/resources/views/welcome.blade.php)), na página de normas ([normas-abnt.blade.php](file:///home/calefi/Documentos/profatilio/resources/views/normas-abnt.blade.php)), no perfil do orientador ([orientador_profile.blade.php](file:///home/calefi/Documentos/profatilio/resources/views/orientador_profile.blade.php)), no validador ([document_verifier.blade.php](file:///home/calefi/Documentos/profatilio/resources/views/document_verifier.blade.php)), no atendimento geral ([solicitar_atendimento.blade.php](file:///home/calefi/Documentos/profatilio/resources/views/solicitar_atendimento.blade.php)), e nos fluxos de necropsia ([quote_form.blade.php](file:///home/calefi/Documentos/profatilio/resources/views/necropsy/quote_form.blade.php), [auth_form.blade.php](file:///home/calefi/Documentos/profatilio/resources/views/necropsy/auth_form.blade.php) e [success.blade.php](file:///home/calefi/Documentos/profatilio/resources/views/necropsy/success.blade.php)).
    - Criação do guia passo a passo [SETUP_NOVO_PC.md](file:///home/calefi/Documentos/profatilio/SETUP_NOVO_PC.md) na raiz do projeto para instruir a replicação das configurações locais em outra máquina e detalhar o planejamento das próximas melhorias.
35. **Melhorias de Produtividade para o Professor (Otimização do Fluxo de Trabalho)**:
    - **Modelos de Despacho Rápido (Template Macros)**: Integração de um campo reativo `feedback_macro` em [DocumentSubmissionResource.php](file:///home/calefi/Documentos/profatilio/app/Filament/Resources/DocumentSubmissionResource.php#L672) e no modal de despacho (linha 970+) que auto-preenche o parecer científico do orientador com mensagens polidas pré-definidas (Aprovado, Ajustes ABNT, Incompleto, Assinatura Pendente ou Cancelado).
    - **Visualizador de PDF Embutido (Side-by-Side)**: Ajustado o método `downloadFile` em [DocumentAuditController.php](file:///home/calefi/Documentos/profatilio/app/Http/Controllers/DocumentAuditController.php#L306) para dar suporte à visualização online (`?preview=1`) com os cabeçalhos MIME corretos. Acoplado um `iframe` de preview dinâmico no Filament [DocumentSubmissionResource.php](file:///home/calefi/Documentos/profatilio/app/Filament/Resources/DocumentSubmissionResource.php#L293) permitindo a leitura de arquivos PDF originais lado a lado com a escrita de pareceres, sem necessidade de baixar o arquivo localmente.
    - **Central de Ações Urgentes**: Criação de um novo widget de tabela [UrgentActionsWidget.php](file:///home/calefi/Documentos/profatilio/app/Filament/Widgets/UrgentActionsWidget.php) que lista unicamente os processos aguardando parecer ou triagem do orientador, ordenados por tempo de espera (mais antigos primeiro), registrado no topo do painel administrativo em [AdminPanelProvider.php](file:///home/calefi/Documentos/profatilio/app/Providers/Filament/AdminPanelProvider.php#L52).
    - **Cobrança Automática de Relatórios Trimestrais de IC**: Desenvolvimento do comando Artisan [SendDeadlineReminders.php](file:///home/calefi/Documentos/profatilio/app/Console/Commands/SendDeadlineReminders.php) que varre prazos de alunos de IC e alerta-os automaticamente via e-mail e Telegram se estiverem a menos de 15 dias do vencimento trimestral sem relatório entregue. O comando foi agendado em [console.php](file:///home/calefi/Documentos/profatilio/routes/console.php#L12) para execução semanal aos domingos.

36. **Reorganização de Ações na Lista de Projetos**:
    - Renomeação da ação principal `despachar` para `'Publicar Despacho'` para torná-la mais clara e contextualizada.
    - Agrupamento de ações secundárias ("Visualizar", "Ver Histórico (Linha do Tempo)", "Baixar Recibo Forense", "Ver Certificado Acadêmico", "Validar Assinatura Digital", "Excluir") dentro de um dropdown `ActionGroup` chamado `'Mais Opções'`, evitando o estouro horizontal e despoluindo visualmente a listagem para o orientador.

37. **Perfil do Professor e Configurações de Integração (Preparação para SaaS)**:
    - **Migração de Banco**: Criação da migration `2026_06_18_000000_add_metadata_to_users_table.php` para adicionar a coluna JSON `metadata` na tabela `users` (castado e fillable no modelo [User.php](file:///home/calefi/Documentos/profatilio/app/Models/User.php#L31)).
    - **Página de Perfil**: Implementada a página customizada [PerfilProfessor.php](file:///home/calefi/Documentos/profatilio/app/Filament/Pages/PerfilProfessor.php) no painel Filament, dividida em abas estratégicas:
      - *Informações Profissionais*: Nome, E-mail, Título Acadêmico (Ex: Prof. Dr.), WhatsApp Comercial, Instituição e Departamento.
      - *Integração de Armazenamento Nuvem*: Configuração do provedor de nuvem (Google Drive, OneDrive, iCloud) e nome da pasta raiz.
      - *Assinatura & Certificado Digital*: Explicações sobre a limitação do Gov.br e instrução para upload de PDFs assinados via certificado (fluxo gratuito funcional). Contém campos para upload do Certificado A1 (`.pfx`/`.p12`) e senha criptografada para futuras implementações em lote.
    - **WhatsApp Dinâmico**: Alterado o widget flutuante [whatsapp_widget.blade.php](file:///home/calefi/Documentos/profatilio/resources/views/partials/whatsapp_widget.blade.php#L1-L26) para ler dinamicamente o WhatsApp comercial e o título/nome configurados no perfil do professor (com fallback automático e tratamento seguro de banco de dados não migrado em testes).
    - **Suíte de Testes**: Criada a classe [ProfessorProfileTest.php](file:///home/calefi/Documentos/profatilio/tests/Feature/ProfessorProfileTest.php) para validar a autenticação da página de perfil e persistência correta dos campos.

38. **Assinatura Offline de PDFs com Validação e Homologação Local (e-CPF A3/A1)**:
    - **Instalação do FPDI/FPDF**: Instalados os pacotes `setasign/fpdi` e `fpdf/fpdf` para permitir a manipulação de arquivos PDF existentes em nível de bytes no servidor PHP, sem custos ou APIs de terceiros.
    - **Descarte da Integração PJeOffice Browser**: Devido a restrições de segurança nativas do PJeOffice Pro (bloqueio CORS de conexões websocket/API locais originadas fora de domínios governamentais *.jus.br, *.gov.br, etc.), o fluxo de assinatura direta via navegador foi descartado para garantir a viabilidade em domínios privados como `profatilio.com.br` ou `prof-aid.com`.
    - **Fluxo de Homologação Offline**: Atualizada a página [AssinaturaAvulsa.php](file:///home/calefi/Documentos/profatilio/app/Filament/Pages/AssinaturaAvulsa.php) e o template [assinatura-avulsa.blade.php](file:///home/calefi/Documentos/profatilio/resources/views/filament/pages/assinatura-avulsa.blade.php) para o fluxo offline:
      - O professor assina os PDFs localmente em sua máquina (via Adobe Reader, Assinador Gov.br Desktop ou PJeOffice Desktop local).
      - O lote de até 10 PDFs já assinados é enviado pelo formulário Filament.
      - O sistema realiza auditoria criptográfica em nível de bytes no PDF (verificando assinaturas digitais ICP-Brasil), extrai o checksum hash SHA-256 e armazena permanentemente os metadados forenses no banco de dados.
    - **Recibo de Validação Permanente**: Para cada PDF assinado homologado, o sistema gera uma certidão/termo de homologação PDF com um QR Code dinâmico apontando para a página pública de verificação do portal.
    - **Validação Dinâmica e Portabilidade (SaaS)**: Atualizada a página de validação pública [document_validate.blade.php](file:///home/calefi/Documentos/profatilio/resources/views/document_validate.blade.php) para resolver dinamicamente o domínio (`request()->getHost()`) e URLs base, assegurando que o QR Code e o validador funcionem automaticamente em qualquer ambiente (como `prof-aid.com` e `profatilio.com.br`) sem necessidade de refatoração.
    - **Segurança e Testes**: Criada a classe [AssinaturaAvulsaTest.php](file:///home/calefi/Documentos/profatilio/tests/Feature/AssinaturaAvulsaTest.php) para validar a submissão, verificação criptográfica do arquivo mockado, registro correto no banco e geração do termo de homologação.
39. **Correção de Erro de Tipo (foreach) em Atualizações de Upload via Livewire**:
    - **Problema**: O Filament desidrata estados de uploads simples (`multiple(false)`) como strings contendo o caminho do arquivo para salvar no banco. Em requisições de atualização subsequentes via Livewire (`POST /livewire/update`), o Livewire recuperava essa string e a injetava no estado do componente. Ao chamar `getUploadedFiles()`, o Filament iterava sobre essa string via `foreach()`, estourando um `ErrorException: foreach() argument must be of type array|object, string given`.
    - **Solução**: Forçamos os componentes de `FileUpload` em [DocumentSubmissionResource.php](file:///D:/profatilio/app/Filament/Resources/DocumentSubmissionResource.php) a sempre usarem arrays para formatação (`formatStateUsing`) e desidratação (`dehydrateStateUsing`), garantindo que o Livewire mantenha o estado como array em memória. No modelo [DocumentSubmission.php](file:///D:/profatilio/app/Models/DocumentSubmission.php), ajustamos os setters de atributos para interceptar o array e salvar como string no banco para fluxos simples (e como JSON array apenas em múltiplos arquivos, como "Avaliações de Estágio"). Isso evita quebras e garante compatibilidade com as listagens e o validador.
40. **Controle de Alunos Inativos e Data de Ativação**:
    - Adicionado suporte a status `'inactive'` para alunos após a conclusão de seus cursos, permitindo suspendê-los de notificações burocráticas/acadêmicas e relatórios, mas mantendo acesso ao portal para gerar certificados e receber e-mails de marketing.
    - Gravação automática do timestamp de ativação (`activated_at`) no model [Student.php](file:///home/calefi/Documentos/profatilio/app/Models/Student.php) assim que o status da conta passa para `'active'`.
    - Ajustado o painel administrativo em [StudentResource.php](file:///home/calefi/Documentos/profatilio/app/Filament/Resources/StudentResource.php) para exibir "Ativo Desde" na listagem/formulário de edição, e adicionado ações rápidas na tabela para inativar e reativar alunos.
    - Atualizados os comandos de notificação e monitoramento ([SendDeadlineReminders.php](file:///home/calefi/Documentos/profatilio/app/Console/Commands/SendDeadlineReminders.php), [ICDeadlinesOverviewWidget.php](file:///home/calefi/Documentos/profatilio/app/Filament/Widgets/ICDeadlinesOverviewWidget.php) e rota de relatório consolidado em [web.php](file:///home/calefi/Documentos/profatilio/routes/web.php)) para ignorar automaticamente alunos inativos.
    - Implementada a possibilidade de incluir alunos inativos nas ações de disparo de e-mails em lote em [EnviarEmailAlunos.php](file:///home/calefi/Documentos/profatilio/app/Filament/Pages/EnviarEmailAlunos.php) para fins de marketing.
    - Resolvido um conflito de compilação em PHP 8.4 no construtor de [BulkEmailAlunos.php](file:///home/calefi/Documentos/profatilio/app/Mail/BulkEmailAlunos.php) relacionado à re-declaração de propriedades como `readonly`.
41. **Ferramentas de Gerenciamento de Senha no Painel Administrativo**:
    - Adicionadas ações dedicadas no [StudentResource.php](file:///D:/profatilio/app/Filament/Resources/StudentResource.php) para resolver problemas de entrega de e-mail de redefinição de senha:
      - **Gerar Link de Senha**: Gera um token oficial e exibe o link direto (`/portal/password-reset/reset?token=...`) para que o professor possa copiar e enviar manualmente à aluna (via WhatsApp ou Telegram).
      - **Alterar Senha**: Permite definir uma nova senha diretamente pelo painel administrativo para redefinições emergenciais.
      - **Enviar E-mail Redefinição**: Força o disparo manual do e-mail de redefinição de senha.

42. **Correção de Acesso Bloqueado para Alunos Inativos (Portal e PWA)**:
    - **Problema**: `User::canAccessPanel()` só liberava o painel `portal` para `status === 'active'`, contradizendo o item 40 (que documentava acesso mantido para inativos). Qualquer aluno marcado como "Inativo" via ação rápida do painel ficava travado fora do portal, recebendo a mensagem genérica "credenciais erradas" do Filament (que não distingue senha errada de painel não autorizado) — e no PWA instalado (que usa `start_url: /portal`), o mesmo bloqueio aparecia como uma tela crua de "não autorizado", já que o service worker ([sw.js](file:///home/calefi/Documentos/profatilio/public/sw.js)) só cai para o cache offline em falha de rede, não em respostas HTTP de erro.
    - **Solução**: Ajustado [User.php](file:///home/calefi/Documentos/profatilio/app/Models/User.php#L72) para permitir acesso ao portal também quando `status === 'inactive'`, mantendo o bloqueio apenas para `awaiting_email_validation` e `awaiting_advisor_approval`.
43. **Agrupamento Visual de Processos Relacionados na Listagem do Aluno**:
    - **Problema**: Etapas da mesma jornada acadêmica (Concepção de TCC → Objetivos de TCC → TCC; ou Termo de Aceite de IC → Iniciação Científica → Relatórios) apareciam como linhas soltas e desconectadas em "Minhas Submissões", exigindo que o aluno procurasse manualmente qual linha era a mais recente.
    - **Solução**: [DocumentSubmissionResource.php](file:///home/calefi/Documentos/profatilio/app/Filament/Resources/DocumentSubmissionResource.php) agora agrupa, apenas na visão do aluno, os registros que compartilham o mesmo `academic_title_id` (chave já usada pelo sistema para encadear as etapas automaticamente) sob um único cabeçalho de "Processo" — usando um `group_key` calculado via `COALESCE(academic_title_id, id + 1000000000)` para que processos sem título vinculado nunca colidam nem se misturem entre si. Dentro de cada grupo, ordenação por `created_at desc` garante que a etapa mais recente apareça primeiro. A visão do professor (abas de triagem) permanece inalterada.
44. **Sistema de Cursos (Trilha, Quiz, Certificado Automático)**:
    - Novo módulo de treinamento interno com matrícula atribuída pelo professor: `CourseModule` (etapas de instrução/avaliação, com vídeo do YouTube opcional e exercícios práticos autoverificáveis), `CourseEnrollment` (progresso sequencial bloqueado), `CourseModuleCompletion`, `CourseModuleQuizAttempt` (histórico de toda tentativa de quiz, aprovação exige nota estritamente maior que o corte) e `CourseModuleQuestion` (dúvidas por etapa, respondidas pelo professor, visíveis a todos os matriculados no curso).
    - Ao concluir todas as etapas, cria automaticamente uma `CertificateRequest` reaproveitando o fluxo já existente de aprovação/assinatura de certificado ([CertificateRequestResource.php](file:///home/calefi/Documentos/profatilio/app/Filament/Resources/CertificateRequestResource.php)). Cada etapa pode ser baixada em PDF com nome/CPF/matrícula do aluno impressos no rodapé.
    - Catálogo de cursos visível a todo aluno logado ([MeusCursos.php](file:///home/calefi/Documentos/profatilio/app/Filament/Pages/MeusCursos.php)), travado para quem não tem matrícula; vitrine pública informativa na landing page (sem link de acesso).
    - Populados via [CourseCurriculumSeeder.php](file:///home/calefi/Documentos/profatilio/database/seeders/CourseCurriculumSeeder.php) três cursos completos — Programação Básica em R (22 etapas) e em Python (22 etapas) para Ciências de Dados, e Organização e Estruturação de Dados para Pesquisa e Análise Estatística (18 etapas, com ilustrações SVG próprias em [public/images/courses/](file:///home/calefi/Documentos/profatilio/public/images/courses/)).
45. **Pesquisa Semestral de Situação do Aluno**:
    - Modal obrigatório no login do aluno (mesmo padrão de gate do `must_change_password`) perguntando uma vez por semestre a situação atual (TCC/Estágio/IC ou conclusão do curso). Histórico salvo em `student_semester_declarations`. Aluno que declara conclusão perde acesso para abrir novos processos, mas mantém leitura/download do que já tem.

46. **Módulo de Consulta Segura de Devolutivas de Trabalhos & Avaliações (Zero-Knowledge / LGPD)**:
    - **Privacidade Radical (LGPD Art. 12)**: Nenhum nome civil ou RGM é armazenado no banco do site. O sistema lê pacotes JSON criptografados armazenados em `storage/app/exam_mirrors/`.
    - **Criptografia por Chave Derivada Forte**:
      - *Para Avaliações (Provas)*: O espelho é descriptografado em memória combinando `Package_ID` + `RGM` + respostas assinaladas nas questões `Q1, Q2 e Q3` do cartão óptico (`HMAC-SHA256` + `AES-256-CBC`).
      - *Para Trabalhos Acadêmicos*: O relatório pedagógico é descriptografado combinando `Package_ID` + `RGM` + `Primeiro Nome` do aluno conforme a lista de presença/chamada escolar.
    - **Definição de Ano Letivo & Semestre**: Cada pacote possui metadados de `ano` (ex.: `2026`) e `semestre` (ex.: `2026.2`). O topo da interface possui seletor reativo de Ano/Semestre com filtragem imediata das atividades daquele período.
    - **Bloqueio de Semestres Anteriores Encerrados**: O docente gerencia no painel Filament o status de encerramento de cada semestre. Ao encerrar o semestre, qualquer consulta a espelhos ou devolutivas do período anterior é automaticamente bloqueada tanto no formulário quanto na API/backend (`isSemesterClosed`).
    - **Devolutiva Completa de Trabalhos Práticos**: Exibição da nota final da etapa (A2), nota do trabalho escrito e nota da avaliação online Blackboard (P-BB); detalhamento das notas e barras de progresso nos critérios (Proposta de Valor, Canvas de 9 blocos e 5W2H); e painéis formativos com "🌟 Destaques Positivos & Pontos Fortes" e "💡 Oportunidades de Melhoria".
    - **Gestão no Painel do Orientador (Filament)**: Página administrativa `GerenciarEspelhosProva` em `/admin/gerenciar-espelhos-prova` integrada ao grupo principal `Gestão Acadêmica & Devolutivas`. Dispõe de **CRUD completo**:
      - *Create*: Inicialização de novas disciplinas pré-configuradas (ex.: `Fisiologia Animal II` no campus PTA com turnos Matutino/Noturno) ou upload de pacotes JSON.
      - *Read*: Inspeção detalhada de dados publicados, total de provas/alunos e links diretos de visualização.
      - *Update*: Edição de metadados (disciplina, campus PTA/SM/GRU/AF/VL, avaliação, ano, semestre, escala e adição/remoção de turmas).
      - *Delete*: Exclusão segura de pacotes publicados.
      - *Semestres*: Controle de semestre vigente e encerramento em 1 clique.
      - *Importação Direta de Planilhas (CSV/TSV/Excel) sem Scripts*: O professor pode importar notas de Trabalhos ou Provas sem depender de scripts Python locais. Aceita upload de arquivo `.csv`/`.tsv` ou colar linhas de texto copiadas direto do Excel/ZipGrade. O PHP calcula os hashes e criptografa os cofres individuais (Zero-Knowledge) na hora.
      - *Inclusão de Aluno Avulso*: Formulário interativo para inserir ou retificar a nota de um aluno específico diretamente no cofre anônimo pelo painel.
      - *Manual de Operação Pós-Correção*: Instruções detalhadas para preparo de planilhas e importação registradas em [INSTRUCOES_DEVOLUTIVAS.md](file:///home/calefi/Documentos/profatilio/INSTRUCOES_DEVOLUTIVAS.md).
    - **Disclaimer Obrigatório & LGPD Radical**: Inclusão de avisos em destaque na consulta e nos resultados informando que a nota oficial válida é exclusivamente a da **Área do Aluno** da faculdade (site com finalidade meramente educacional). Remoção de qualquer código de oferta técnica em conformidade com o Art. 12 da LGPD.
    - **Testes Automatizados**: `ExamMirrorTest` com cobertura completa de carregamento por ano, descriptografia de provas e de trabalhos, rejeição de tentativas inválidas, bloqueio estrito de semestres encerrados, conferência de disclaimer, CRUD do admin, importador de CSV sem scripts e cadastro de aluno avulso. Suíte global com 161 testes passando (2016 asserções).
46. **Confiabilidade da Triagem em Metanálises e Calibração de Kappa (Conformidade OSF: κ Binário e 3 Categorias)**:
    - **Problema**: O gate da calibração piloto de revisões sistemáticas exigia estritamente Kappa de Cohen $\ge 0.60$ em matriz 3x3 (*Include* vs *Exclude* vs *Maybe*). Em bases com baixa prevalência de elegíveis, divergências sutis de conservadorismo (ex.: um revisor marca *Include* e o outro marca *Maybe*) penalizavam artificialmente o índice mesmo com concordância observada $\ge 90\%$.
    - **Solução OSF (Item 11b)**: No protocolo registrado, tanto *Include* quanto *Maybe* avançam compulsoriamente para texto completo, tornando a decisão preliminar de triagem operacionalmente binária. Implementados em `SystematicReviewService.php` os métodos `pilotKappaBinary()` e `pilotEffectiveKappa()`. O gate de liberação (`releasePilot()`) agora avalia o Kappa efetivo, permitindo a liberação com embasamento no protocolo OSF e destacando ambos os índices no painel administrativo Filament (`SystematicReviewResource.php` e `ViewSystematicReview.php`).

### 🔮 Próximos Passos (Roadmap Técnico):
1. **Rodar as Migrações e o Seeder de Cursos em Produção** (pendente — sessão de 2026-08-13 ainda não foi deployada):
   - Executar `php artisan migrate --force` na hospedagem HostGator para criar/atualizar as tabelas (pesquisa semestral + sistema de cursos completo) e normalizar os nomes de usuários históricos.
   - Executar `php artisan db:seed --class="Database\Seeders\CourseCurriculumSeeder" --force` para popular os cursos de R e Python (idempotente, pode rodar de novo com segurança). O banco local de desenvolvimento é ignorado pelo Git (`database/.gitignore`), então o conteúdo dos cursos só existe em produção depois desse comando rodar lá.
2. **Configuração de Produção na HostGator**:
   - Configurar o agendador de Cron Job para a fila de tarefas e processamento de jobs pendentes.
3. **Migração e Portabilidade para prof-aid.com**:
   - Manter a plataforma `profatilio.com.br` 100% focada em estabilidade, polimento de UX e produção.
   - Quando estiver madura e estável, o código será duplicado/portado para um novo repositório Git dedicado para integração com a marca `prof-aid.com`, onde serão adicionados os módulos multi-tenant de cobrança e conexão de drives por professor.



