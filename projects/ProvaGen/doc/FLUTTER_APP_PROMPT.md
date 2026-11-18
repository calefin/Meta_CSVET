# Especificação de Criação do App Flutter — Prof-Aid

Forneça as instruções abaixo para a IA encarregada de desenvolver o aplicativo móvel em Flutter para o **Prof-Aid**.

---

## Prompt de Contexto e Identidade Visual (Copie e Cole)

```text
Você é um Engenheiro de Software sênior especialista em Flutter e Dart.
Você foi contratado para criar o aplicativo móvel oficial do "Prof-Aid" (um ecossistema de geração de provas e banco de questões com correção óptica de gabaritos).

O aplicativo móvel deve ser integrado ao nosso ecossistema Web atual (React + Node.js/Express) que roda em HTTPS e responde requisições JSON no padrão tRPC.

### 1. IDENTIDADE VISUAL E PALETA DE CORES (DESIGN SYSTEM)
O aplicativo deve parecer moderno, premium, limpo e profissional, seguindo as diretrizes visuais do site Prof-Aid:
- **Cores Principais**:
  - Primary (Dominante): Emerald Green (Esmeralda) -> HSL(162, 90%, 30%) / Hex #059669
  - Secondary/Accent: Indigo (Índigo) -> HSL(243, 75%, 59%) / Hex #4f46e5
  - Neutral Dark (Textos): Slate Gray -> Hex #1e293b
  - Neutral Light (Fundos): Off-White/Ice -> Hex #f8fafc
- **Tipografia**: Use a fonte 'Outfit' ou 'Inter' do Google Fonts para títulos e textos.
- **Identidade do Logotipo**: O logo do Prof-Aid consiste em uma folha de respostas A4 estilizada, cujas linhas horizontais de questões formam o perfil de uma caneta tinteiro que se funde com um visto verde esmeralda no topo direito. O ícone de aplicativo (favicon.svg no repositório) utiliza esse símbolo.

### 2. ARQUITETURA E DEPENDÊNCIAS DO FLUTTER
Estruture o projeto de forma modular e limpa (Clean Architecture ou Feature-first):
- **Gerenciamento de Estado**: Utilize Flutter BloC (Cubit) ou Provider para reatividade de estados.
- **Comunicação de Rede**: Utilize o pacote 'dio' ou 'http' para realizar chamadas REST de forma assíncrona.
- **Armazenamento Seguro**: Utilize 'flutter_secure_storage' para guardar o token de autenticação e cookies da sessão.
- **Hardware de Câmera**: Utilize o pacote 'camera' e 'image' para processar imagens e escanear gabaritos.

### 3. ESCOPO DE REQUISITOS DE TELAS DO APP
O aplicativo deve conter as seguintes telas e funcionalidades principais:

1. **Tela de Autenticação (Login/Signup)**:
   - Formulário de Login (E-mail e Senha) com feedback visual de validação.
   - Suporte para login social via Google OAuth (redirecionando para /api/auth/google e extraindo o cookie de sessão).
   - O token da sessão deve ser guardado de forma segura no dispositivo para manter o login ativo.

2. **Tela do Painel Principal (Home)**:
   - Apresentar um resumo rápido com estatísticas (Provas geradas, Alunos cadastrados, Chamados abertos).
   - Timeline/Histórico de Atividades recentes consumido do backend.

3. **Módulo de Correção por Câmera (OMR - Core do App)**:
   - Interface de câmera em tela cheia com um "overlay/retículo" semitransparente guiando o professor a alinhar os 4 marcadores nos cantos da folha de respostas.
   - Ao capturar a imagem (ou fazer detecção em tempo real), o app deve:
     1. Localizar os 4 pontos de referência da folha de respostas.
     2. Aplicar uma transformação de perspectiva (Perspective Warp) para alinhar a folha perfeitamente em A4 (usando processamento local leve).
     3. Identificar os círculos de respostas preenchidos com base na densidade de pixels pretos/cinzas de cada bolha no grid.
     4. Gerar o JSON com as respostas decodificadas e enviar via POST para a rota do servidor:
        URL: https://www.prof-aid.com/api/trpc/answerSheets.process
     5. Mostrar instantaneamente o relatório de notas do aluno após a resposta do backend.

4. **Tela de Chamados de Suporte (Helpdesk)**:
   - Listar os chamados de suporte abertos pelo próprio professor, exibindo status (Aberto, Em Andamento, Resolvido) e a resposta do administrador.
   - Formulário simples para abrir um novo chamado de suporte (Título, Descrição, Prioridade) consumindo `tickets.create`.

5. **Tela de Solicitação de Orçamento Corporativo (Orçamentos)**:
   - Formulário completo para empresas/escolas solicitarem orçamento sob medida para banco de questões ou LMS.
   - Campos: Nome, E-mail Corporativo, Telefone, Empresa, Cargo, Tipo de Serviço, Escala (Volume), Prazo de Início, Recursos Adicionais Desejados (LMS, IA, LaTeX, TRI, App Mobile, Prova Online) e Descrição das Necessidades.
   - Enviar dados via POST para a rota `quotes.create`.

### 4. EXEMPLO DE CONSUMO DE API TRPC EM DART
O tRPC trafega dados via HTTP GET/POST sob formato JSON. Toda chamada segue a estrutura `/api/trpc/<roteador>.<procedimento>`.
Sempre que fizer uma mutação (POST), envie os argumentos no corpo da requisição formatado como JSON.
Sempre que for uma rota protegida, injete no cabeçalho HTTP o Cookie da sessão salvo durante o login.

Desenvolva as telas seguindo as melhores práticas e com micro-animações suaves em transições de página usando Framer-style animations do Flutter.
```

---

## 3. Ativos e Imagens de Identidade Visual

O projeto possui os seguintes arquivos estáticos na pasta de desenvolvimento local que servem como referências oficiais de logotipo e paletas:

* **Logotipo Vetorial SVG (Ícone de Aplicativo)**: [client/public/favicon.svg](file:///home/calefi/Documentos/ProvaGen/client/public/favicon.svg)
* **Imagem de Identidade Open Graph**: [client/public/og-image.svg](file:///home/calefi/Documentos/ProvaGen/client/public/og-image.svg)
* **Design System / CSS Global**: [client/src/index.css](file:///home/calefi/Documentos/ProvaGen/client/src/index.css) (Consulte para verificar cores exatas de gradientes e classes aplicadas no frontend).
