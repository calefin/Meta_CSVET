# Continuidade com IA — Memória do Projeto

Objetivo: suportar trabalho contínuo e eficiente com assistente IA, fornecendo contexto persistente e preferências.

Resumo do projeto:
- Nome do projeto: ProvaGen
- Local: repositório raiz
- Ambição: gerar, processar e avaliar provas e questões automaticamente.

Prioridades curtas (use como referência):
- Stabilizar importação de questões e formatos (QTI, JSON).
- Garantir reprodutibilidade dos PDFs gerados.
- Manter pequenas mudanças de schema documentadas.

Preferências do time / assistente:
- Linguagem principal: Português (PT-BR) para comunicação, exceto quando código.
- Estilo de código: consistente com o repositório existente; priorizar clareza sobre concisão.
- Testes: criar testes pequenos e focados para novas funcionalidades.

Informações de ambiente úteis:
- Virtualenv: `.venv` no diretório do projeto (ativado para tarefas Python).
- Frontend: Vite + React + TypeScript em `client/`.
- Backend: Node/TS + scripts Python para processamento específico em `server/`.

Prompts e instruções curtas reutilizáveis:
- "Resuma as mudanças recentes no repositório e sugira próximos passos."  
- "Liste os arquivos modificados relacionados a importação de questões."  

Como usar esta memória:
- Atualize com fatos curtos e atômicos (uma linha = um fato).
- Evite segredos e chaves (não versionar credenciais aqui).

Sugestão de próximos passos:
- Adicionar pontos de decisão arquitetural em `decisions.md` dentro do mesmo diretório.
- Atualizar esta memória sempre que houver uma mudança de prioridade ou configuração de ambiente.