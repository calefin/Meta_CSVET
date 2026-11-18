# ProvaGen

Plataforma fullstack para criação, gestão e correção de avaliações educacionais. Permite que professores montem provas com banco de questões próprio, gerem PDFs com múltiplas versões embaralhadas, corrijam gabaritos por câmera e acompanhem o desempenho dos alunos por turma.

---

## Funcionalidades

### Banco de Questões
- Criação manual de questões em 4 formatos: múltipla escolha, múltipla escolha complexa, dissertativa e asserção/razão
- Importação em lote via XLSX, DOCX, PDF e JSON
- Upload de imagens por questão com controle de cota de armazenamento
- Filtros por disciplina, assunto, tipo e dificuldade
- Exportação em XLSX e JSON

### Avaliações
- Assistente de criação em 3 etapas: informações → questões → preparação para impressão
- Cabeçalho padrão (instituição, curso, disciplina, professor, turma, data) ou personalizado (imagem)
- Pontuação distribuída igualmente ou definida por questão
- Preparação para impressão:
  - Estilos: coluna dupla, coluna simples, econômico, acessibilidade
  - Impressão: frente e verso ou um lado
  - Posição do gabarito: primeira página, última página ou folha avulsa
  - Embaralhamento: questões + itens, só questões, só itens ou sem embaralhamento
  - Até 50 versões diferentes de uma mesma prova
  - Idioma da avaliação: Português, English, Español

### Gabaritos e Correção
- Gabarito no estilo de preenchimento de concurso (bubble sheet)
- Identificação por QR code e/ou código de barras
- Correção automática por câmera de celular
- Nota salva e vinculada ao aluno na nuvem

### Instituições
- Cadastro de universidades, faculdades e escolas
- Organização por departamentos e cursos

### Turmas
- Cadastro com semestre, ano e vínculo à instituição/curso
- Gradebook com notas por aluno e por prova
- Arquivamento de turmas antigas
- Geração de pacotes de gabaritos individuais por turma (PDF com código de barras único por aluno)

### Alunos
- Cadastro individual ou importação por CSV/XLSX
- Exportação em CSV/XLSX
- Vinculação a múltiplas turmas
- Histórico de provas e sessões

### Compartilhamento
- Geração de links públicos para o aluno responder a avaliação online (sem cadastro)
- Respostas salvas e vinculadas ao gradebook

### Internacionalização
- Interface disponível em Português (BR), English e Español
- Troca de idioma em tempo real via seletor na barra de navegação
- Idioma do PDF da prova configurável independentemente da interface

---

## Stack

| Camada | Tecnologia |
|--------|-----------|
| Frontend | React 19, Vite, TypeScript |
| UI | shadcn/ui (Radix UI) + Tailwind CSS |
| Roteamento | Wouter |
| API | tRPC (type-safe end-to-end) |
| ORM | Drizzle ORM |
| Banco de dados | MySQL (produção) / SQLite (desenvolvimento) |
| Servidor | Express.js + Node.js |
| Autenticação | JWT + Google OAuth 2.0 |
| Armazenamento | AWS S3 compatível / local |
| Tabelas/export | ExcelJS |
| Testes | Vitest |

---

## Pré-requisitos

- **Node.js** ≥ 20
- **pnpm** (ou npm/yarn)
- **MySQL** ≥ 8 (produção) ou SQLite (desenvolvimento local)
- Conta Google Cloud com OAuth 2.0 configurada (opcional, para login com Google)

---

## Configuração

### 1. Instalar dependências

```bash
pnpm install
```

### 2. Variáveis de ambiente

Crie um arquivo `.env` na raiz do projeto com as seguintes variáveis:

```env
# Banco de dados
# MySQL:
DATABASE_URL=mysql://usuario:senha@localhost:3306/provagen
# SQLite (desenvolvimento local):
# DATABASE_URL=sqlite:./dev.db

# Autenticação
JWT_SECRET=sua_chave_secreta_jwt

# OAuth (SDK interno)
VITE_APP_ID=seu_app_id
OAUTH_SERVER_URL=https://seu-servidor-oauth.com
OWNER_OPEN_ID=local:email@admin.com

# Google OAuth (opcional)
GOOGLE_CLIENT_ID=seu_client_id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=seu_client_secret
GOOGLE_CALLBACK_URL=http://localhost:3000/api/auth/google/callback

# Armazenamento (AWS S3 ou compatível)
AWS_ACCESS_KEY_ID=sua_access_key
AWS_SECRET_ACCESS_KEY=sua_secret_key
AWS_REGION=us-east-1
AWS_BUCKET_NAME=nome-do-bucket
```

### 3. Criar as tabelas no banco

```bash
pnpm db:push
```

> O comando executa `drizzle-kit generate` seguido de `drizzle-kit migrate`.

### 4. Iniciar o servidor de desenvolvimento

```bash
pnpm dev
```

O servidor estará disponível em [http://localhost:3000](http://localhost:3000).

---

## Scripts disponíveis

| Comando | Descrição |
|---------|-----------|
| `pnpm dev` | Inicia o servidor em modo desenvolvimento com hot-reload |
| `pnpm build` | Compila frontend (Vite) + backend (esbuild) para `dist/` |
| `pnpm start` | Executa o build de produção |
| `pnpm check` | Verifica erros de TypeScript (`tsc --noEmit`) |
| `pnpm test` | Executa os testes com Vitest |
| `pnpm db:push` | Gera e aplica as migrações do banco de dados |
| `pnpm format` | Formata o código com Prettier |

---

## Estrutura do projeto

```
ProvaGen/
├── client/                  # Frontend React
│   └── src/
│       ├── components/      # Componentes reutilizáveis
│       │   ├── questions/   # Formulários e preview de questões
│       │   └── ui/          # shadcn/ui (Radix + Tailwind)
│       ├── contexts/        # React Contexts (Theme, I18n)
│       ├── hooks/           # Custom hooks
│       ├── pages/           # Páginas da aplicação
│       └── lib/             # Utilitários (trpc client, utils)
├── server/                  # Backend Express
│   ├── _core/               # Infraestrutura (auth, storage, env)
│   └── routers/             # Procedimentos tRPC por domínio
├── drizzle/                 # Schema e migrações do banco
│   ├── schema.ts            # Definição de todas as tabelas
│   └── relations.ts         # Relações entre tabelas
├── shared/                  # Código compartilhado cliente/servidor
│   ├── locales/             # Traduções PT/EN/ES
│   └── _core/               # Tipos compartilhados
└── package.json
```

### Principais tabelas do banco

| Tabela | Descrição |
|--------|-----------|
| `users` | Usuários do sistema |
| `questions` | Banco de questões |
| `question_images` | Imagens das questões |
| `exams` | Avaliações criadas |
| `exam_questions` | Questões vinculadas a cada avaliação |
| `exam_scores` | Pontuação por questão em cada avaliação |
| `generated_exams` | PDFs gerados |
| `answer_sheets` | Gabaritos enviados para correção |
| `institutions` | Universidades, faculdades e escolas |
| `departments` | Departamentos das instituições |
| `courses` | Cursos das instituições |
| `classes` | Turmas (com semestre e ano) |
| `students` | Alunos cadastrados |
| `student_classes` | Alunos ↔ Turmas (N:N) |
| `student_grades` | Notas dos alunos por avaliação |
| `sharing_links` | Links públicos para avaliações online |
| `quiz_responses` | Respostas de alunos via link público |

---

## Rotas principais

| Rota | Descrição |
|------|-----------|
| `/` | Página inicial |
| `/questions` | Banco de questões |
| `/create-question` | Criar nova questão |
| `/exams` | Lista de avaliações |
| `/create-exam` | Criar nova avaliação |
| `/classes` | Gestão de turmas |
| `/students` | Gestão de alunos |
| `/institutions` | Gestão de instituições |
| `/sharing` | Links públicos |
| `/answer-sheets` | Correção de gabaritos |
| `/faq` | Perguntas frequentes |
| `/pricing` | Planos |
| `/profile` | Perfil do usuário |
| `/q/:token` | *(pública)* Avaliação online para o aluno |

---

## Autenticação

O sistema suporta dois métodos de login:

- **Email + senha** — cadastro e login com hash PBKDF2
- **Google OAuth 2.0** — login via conta Google (requer configuração no Google Cloud Console)

Para configurar o login com Google, consulte o arquivo [GOOGLE_AUTH_SETUP.md](./GOOGLE_AUTH_SETUP.md).

---

## Internacionalização

A troca de idioma é feita em tempo real pelo seletor no canto superior direito da interface. A preferência é salva no `localStorage`. Os arquivos de tradução estão em:

```
shared/locales/
├── pt.ts   # Português (padrão)
├── en.ts   # English
├── es.ts   # Español
└── index.ts
```

Para adicionar um novo idioma, crie o arquivo correspondente seguindo a estrutura de `pt.ts` e registre-o em `index.ts`.

---

## Testes

```bash
pnpm test
```

Os testes estão em `server/*.test.ts` e utilizam Vitest. Cobrem os principais fluxos de autenticação, questões, provas e PDF.

---

## Licença

MIT
