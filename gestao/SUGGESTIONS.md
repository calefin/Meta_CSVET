# Sugestões de Melhorias & Roadmap de Implementação

Este documento consolida sugestões técnicas de melhoria e um roteiro de próximas etapas para o desenvolvimento da plataforma **profatilio.com.br (V2)**, otimizando o desempenho na HostGator e melhorando a robustez geral.

---

## 🚀 1. Melhorias de Performance & Cache (HostGator)

Como o ambiente é uma hospedagem compartilhada, a otimização de recursos é fundamental:

- **Cache de Metadados do Google Drive:** A comunicação constante com a API do Google Drive para listar arquivos ou verificar a existência de pastas pode gerar latência HTTP perceptível. Sugere-se implementar cache nos retornos das listagens (`Laravel Cache`) com duração de 1 hora.
- **Otimização de Imagens para Inodes:** O HostGator possui limite rígido de *inodes* (número de arquivos). Como os uploads clínicos e acadêmicos vão diretamente para o Google Drive e são deletados localmente, garantimos espaço. Porém, para imagens do Mural públicas, sugere-se a instalação do pacote `spatie/laravel-image-optimizer` para comprimir imagens antes de salvá-las temporariamente.

---

## 🤖 2. Robustez do Auditor de Citações com IA

- **Validação de Tamanho de Arquivos:** No job **[AuditDocumentWithAI](file:///D:/profatilio/app/Jobs/AuditDocumentWithAI.php)**, o texto é limitado a 30.000 caracteres para evitar estouro de tokens da API do Gemini Flash. Recomenda-se adicionar uma validação visual no painel do aluno para não permitir o envio de arquivos PDF/DOCX excessivamente pesados que possam estourar a memória do PHP (`memory_limit` no cPanel).
- **Tratamento de Rate-Limiting da API:** A API do Gemini possui limites de requisições por minuto na versão gratuita ou de baixo custo. Sugere-se configurar o Job com a flag de tentativas (`$tries = 3`) e atraso exponencial (`$backoff`) para tentar novamente em caso de erros `429 Too Many Requests`.
- **Filtro Avançado de Caracteres Especiais:** Alguns PDFs antigos podem conter codificações de texto corrompidas. Sugere-se aplicar uma limpeza de caracteres não-UTF8 antes de enviar o texto para a LLM.

---

## 🔒 3. Segurança e Isolamento

- **Exclusão Física de Arquivos Locais:** Atualmente, o Job limpa os arquivos temporários criados em `storage/app/private`. Recomenda-se adicionar um bloco `finally` na estrutura de tratamento de erros para garantir que o arquivo seja removido mesmo em caso de falha catastrófica da auditoria.
- **Nomenclatura Jurídica Bloqueante:** Implementar testes automatizados no Laravel (`Feature/NomenclatureTest.php`) que varrem o banco de dados e arquivos de tradução para garantir que a palavra "tutor" não seja acidentalmente introduzida por outros desenvolvedores no futuro.

---

## 🗺️ 4. SEO e Sitemap Dinâmico

- **Sitemap Dinâmico:** Substituir o arquivo estático **[sitemap.xml](file:///D:/profatilio/public/sitemap.xml)** por uma rota dinâmica no Laravel (ex: `/sitemap.xml`) que leia do banco de dados e inclua os slugs dos novos artigos cadastrados no *Mural de Divulgação Científica* de forma automática.
- **Structured Data (JSON-LD):** Adicionar marcações do schema.org no HTML do portfólio para indexar o Prof. Atilio como uma entidade de prestígio do tipo `VeterinaryCare` ou `Person` acadêmica.

---

## 👥 5. Fluxos do Painel do Aluno e Orientador

- **Visualização Kanban interativa:** Implementar a esteira Kanban visual utilizando componentes Livewire nativos do Filament para que o orientador arraste os documentos de status de forma fluida.
- **Termo de Aceite Bloqueante:** Criar um formulário Custom Wizard no Filament para a submissão, onde o botão de "Enviar" permaneça desabilitado até que a caixa de confirmação da ABNT seja marcada.

---

## 🔒 6. Segurança de Integração (Telegram Spoofing)

- **Vínculo via Token Seguro**: Atualmente, o bot vincula contas usando o ID sequencial (`/start <student_id>`). Um usuário malicioso poderia varrer IDs e vincular o chat dele a outros alunos para espionar submissões (infringindo a LGPD).
- **Recomendação**: Substituir por um token randômico temporário (UUID ou hash) gerado no painel do aluno. O link do bot vira `https://t.me/Bot?start=TOKEN_SEGURO`. O webhook valida o token e o consome de forma segura.

---

## ⚡ 7. Notificações Assíncronas (E-mails e Telegram em Fila)

- **Uso de Mail::queue**: Eventos de criação e atualização enviam e-mails de forma síncrona (`Mail::send(...)`). Em hospedagens como a HostGator, a latência de conexões SMTP atrasa a resposta HTTP para o usuário final.
- **Recomendação**: Substituir por `Mail::queue(...)` ou criar Mailables com `ShouldQueue`. Da mesma forma, as mensagens do bot do Telegram devem ser enviadas por meio de um Job dedicado em segundo plano, evitando travamentos no Filament se as APIs externas estiverem lentas.

---

## 📄 8. Carimbo Visual em PDFs Homologados (Watermarking)

- **Marcação Visual no PDF**: No momento, a assinatura forense digital é armazenada apenas no banco de dados e no cabeçalho do recibo separado.
- **Recomendação**: Usar uma biblioteca como `setasign/fpdi` ou `clegginabox/pdf-watermark` para injetar uma tarja visual ou rodapé diretamente no PDF original do aluno informando: *"Documento homologado pelo Prof. Atilio em DD/MM/AAAA. Chave de Validação: [SHA-256]"* acompanhado de um QR Code.

---

## 🛠️ 9. Monitoramento de Erros via Telegram

- **Log Centralizado para o Admin**: Falhas críticas como falhas de quota da API do Gemini, erros de refresh token do Google Drive ou problemas de fila no cPanel podem demorar a ser percebidos pelo professor.
- **Recomendação**: Configurar um canal personalizado de Logs no Laravel (`config/logging.php`) que envie erros com nível de severidade `CRITICAL` ou `EMERGENCY` diretamente para o Telegram do Prof. Atilio usando o bot, permitindo correção e suporte imediatos.
