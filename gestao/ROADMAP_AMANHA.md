# 🗺️ Planejamento de Implementação — Próxima Sessão

Este documento detalha o planejamento das próximas melhorias na plataforma **profatilio.com.br**, focando em produtividade no gerenciamento de documentos e performance de carregamento.

---

## 📊 1. Quadro Kanban de Submissões no Filament
Para facilitar a visualização do fluxo de trabalho diário de correções, construímos uma visualização Kanban para o Prof. Atilio gerenciar os documentos dos alunos.

### O que fazer:
- [x] Criar uma nova aba ou recurso customizado no Filament para visualizar o quadro.
- [x] Colunas baseadas nos status das submissões (ex: "Enviado", "Em Revisão", "Aguardando Assinatura", "Concluído").
- [x] Habilitar drag-and-drop para mover submissões entre colunas e atualizar o status automaticamente via Ajax.


---

## ⏰ 2. Alertas Visuais de Prazos Trimestrais de IC
Exibição de alertas dinâmicos baseados nas datas limite de entrega dos relatórios trimestrais de Iniciação Científica.

### O que fazer:
- [x] Calcular a proximidade do prazo de entrega para cada aluno de IC.
- [x] Colorir os registros do dashboard administrativo de acordo com a proximidade do prazo:
  - **Verde**: > 30 dias restantes.
  - **Amarelo**: Entre 15 e 30 dias restantes.
  - **Vermelho**: < 15 dias restantes.
- [x] Exibir cards informativos de resumo de prazos no topo do dashboard do administrador.

---

## ⚡ 3. Otimização de Performance: Cache do Google Drive
Como as listagens de arquivos diretamente da API do Google Drive geram gargalos de rede (latência HTTP), implementaremos cache temporário no Laravel.

### O que fazer:
- [x] Configurar cache de 5 a 10 minutos para as requisições de listagem de pastas do Drive.
- [x] Implementar invalidação automática do cache sempre que uma nova submissão for criada ou atualizada.

