# MedVetLegal — Perícia e Assistência Técnica Veterinária

Este é o repositório do site institucional e plataforma de gestão de orçamentos e blog da **MedVetLegal** (www.medvetlegal.com.br). O projeto é desenvolvido em **Laravel 11** e utiliza **AdminLTE 3** no painel administrativo e **Bootstrap 5** no frontend público.

---

## 🌟 Principais Funcionalidades

### 🖥️ Frontend Público e Autoridade
*   **Página Inicial (Landing Page):** Design responsivo com paleta em *Azul Marinho Profundo* e *Dourado Nobre*. Foco exclusivo na autoridade técnica da perita principal **Dra. Adriana de Siqueira** (Doutora pela USP, referência em necropsia de carbamatos) e do consultor científico **Dr. Atilio Sersun Calefi** (Pós-Doutor em Farmacologia pela USP).
*   **Otimização de SEO (Foco em Conversão):** 
    *   **JSON-LD Schemas:** Marcação estruturada para mecanismo de busca do tipo `MedicalBusiness` na home e `BlogPosting` nas páginas individuais do blog.
    *   **Sitemap XML Dinâmico:** Sitemap autogerido em `/sitemap.xml` que atualiza dinamicamente conforme novos posts são criados.
    *   **Robots.txt:** Configurado para direcionar indexadores e blindar a área de login/admin.
    *   **Termos de Uso & LGPD:** Página dedicada em `/termos` detalhando a política de tratamento de dados sob as diretrizes da Lei Geral de Proteção de Dados.

### 📩 Fluxo de Orçamentos e Contato
*   **Formulário de Relato de Casos:** Formulário com menu suspenso de serviços estruturados (Assistência Técnica Judicial, Análise de Viabilidade Técnica, Parecer de Prontuário, Necropsia Forense, Casos de Intoxicação e Orientação Geral).
*   **Segurança Anti-Spam:** Proteção integrada por Honeypot e Desafio Matemático.
*   **Integração com Banco de Dados:** O envio cria automaticamente um `Cliente`, abre um `Ticket` com prioridade calculada conforme a urgência do caso, e inicia a primeira `Mensagem` no histórico.
*   **Disparo de Alertas:** Envia um e-mail estruturado em HTML para a perita principal (`patoforense.adriana@gmail.com`) com cópias (CC) para `calefi@csvet.com.br` e `contato@medvetlegal.com.br`.

### 🔑 Portal do Cliente (Acompanhamento por Token)
*   **Acesso sem Senha:** O cliente recebe um link exclusivo tokenizado (ex: `/tickets/acompanhar/{token_32_digitos}`) no e-mail para acompanhar o andamento sem precisar criar uma conta de acesso.
*   **Proteção de Dados Sensíveis:** O painel público é blindado, não exibindo dados cadastrais (como e-mail, nome e telefone) para evitar vazamento em caso de compartilhamento indevido do link.
*   **Chat e Anexos:** O cliente e a perita podem trocar mensagens diretamente pela linha do tempo e anexar arquivos em PDF (de até 5MB) com segurança.
*   **Aprovação de Honorários:** O cliente pode aceitar ou recusar a proposta de orçamento proposta no painel com um clique, disparando alertas por e-mail à perita.

### 📝 Painel Administrativo de Blog (CRUD)
*   **Editor Visual:** Painel administrativo em `/admin/posts` integrado ao AdminLTE 3 com **CKEditor 5** embutido para criar e editar matérias em texto rico.
*   **Geração de Slugs:** O sistema gera URLs amigáveis com slugs automáticos e únicos baseados no título do artigo.
*   **Agendamento de Posts:** Permite definir datas de publicação futuras ou salvar artigos como rascunhos.
*   **IA-Ready:** Acompanha o modelo [article_ai_template.md](file:///home/calefi/.gemini/antigravity-cli/brain/41087bc6-7e46-4ae6-811b-5d95d67d83c3/article_ai_template.md) com prompts pré-formatados em HTML Bootstrap para geração acelerada de conteúdo por IA.

---

## 🛠️ Requisitos de Instalação

Verifique a listagem detalhada no arquivo [requirements.txt](file:///home/calefi/Documentos/Projetos%20Git/medvetlegal_site/requirements.txt).
*   PHP >= 8.2
*   Composer
*   Node.js (com NPM)
*   SQLite3 ativado no PHP (ou banco MySQL rodando)

---

## 🚀 Como Executar o Projeto Localmente

1.  **Instalar dependências do PHP (Composer):**
    ```bash
    composer install
    ```

2.  **Instalar dependências de frontend (NPM) e compilar:**
    ```bash
    npm install
    npm run build
    ```

3.  **Configurar variáveis de ambiente:**
    *   Duplique o arquivo `.env.example` e renomeie-o para `.env`.
    *   Crie um arquivo SQLite vazio em `database/database.sqlite`.
    *   No `.env`, garanta as configurações do SQLite:
        ```env
        DB_CONNECTION=sqlite
        # Remova ou comente as chaves DB_HOST, DB_PORT, DB_DATABASE, etc.
        ```

4.  **Gerar chave da aplicação:**
    ```bash
    php artisan key:generate
    ```

5.  **Criar o link simbólico do storage (Obrigatório para imagens e uploads de PDFs):**
    ```bash
    php artisan storage:link
    ```

6.  **Executar as migrações e popular o banco de dados (Seeder):**
    ```bash
    php artisan migrate --seed
    ```
    *Isso criará as tabelas e o usuário administrador padrão: `ascalefi@gmail.com` com a senha `041188As`, além do primeiro artigo científico no banco.*

7.  **Iniciar o servidor local de desenvolvimento:**
    ```bash
    php artisan serve
    ```
    Acesse a aplicação no navegador em `http://127.0.0.1:8000`.

---

## 📂 Estrutura de Banco de Dados (Entidades)

O banco de dados utiliza a seguinte arquitetura de tabelas:

*   `users`: Usuários administrativos do painel com acesso a tickets e blog.
*   `clientes`: Registro de solicitantes que enviam mensagens e demandam perícias.
*   `tickets`: Casos e propostas de orçamentos (contém status, prioridade, valor orçado e token de acesso).
*   `mensagens`: Histórico de conversação e arquivos anexos vinculados a cada ticket.
*   `posts`: Artigos científicos e notícias cadastrados no blog.
*   `cursos`: Cursos e treinamentos oferecidos pela CSVET (Fase 5).
*   `matriculas`: Relação de alunos matriculados nos cursos (Fase 5).
*   `certificados`: Códigos e logs de certificados de conclusão emitidos (Fase 5).

---

## 🛡️ Segurança e LGPD

O tratamento de dados da MedVetLegal segue a risca a LGPD:
*   Consentimento implícito na submissão de contatos.
*   Retenção estritamente baseada em fins de auditoria civil e cadeia de custódia técnico-pericial.
*   Não exibição de dados pessoais em páginas públicas tokenizadas.
*   Exclusão definitiva de registros pessoais solicitável pelo canal oficial: `patoforense.adriana@gmail.com`.
