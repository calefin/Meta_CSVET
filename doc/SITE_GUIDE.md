# Guia de Utilização do Prof-Aid (Manual do Usuário e Admin)

Bem-vindo à documentação de uso do **Prof-Aid**. Este guia apresenta as principais funcionalidades do sistema e orienta usuários (professores) e administradores a tirarem o melhor proveito da plataforma.

---

## Índice
1. [Visão Geral](#1-visão-geral)
2. [Gestão de Alunos e Turmas](#2-gestão-de-alunos-e-turmas)
3. [Banco de Questões e Importação](#3-banco-de-questões-e-importação)
4. [Criação e Geração de Provas](#4-criação-e-geração-de-provas)
5. [Gabarito Adaptativo (ZipGrade/Gradepen)](#5-gabarito-adaptativo-zipgradegradepen)
6. [Registro de Atividades](#6-registro-de-atividades)
7. [Sistema de Suporte e Tickets](#7-sistema-de-suporte-e-tickets)

---

## 1. Visão Geral
O **Prof-Aid** é um ecossistema projetado para simplificar a vida do docente no preparo e correção de avaliações. Com ele, você pode:
- Gerenciar turmas e alunos.
- Organizar um banco de questões com filtros avançados.
- Gerar provas formatadas em PDF prontas para impressão, com suporte a múltiplas versões (questões e alternativas embaralhadas).
- Imprimir ou baixar gabaritos de correção (folha de respostas) ajustáveis dinamicamente ao número de questões e alternativas de cada prova, compatíveis com leitura automatizada.

---

## 2. Gestão de Alunos e Turmas
Para gerenciar seus alunos e turmas:
1. Acesse o menu **Alunos** na barra lateral.
2. Você pode cadastrar alunos manualmente ou importar em massa.
3. Para importar em massa, faça o download do modelo disponível na página de alunos (`alunos-modelo.xlsx` ou `alunos-modelo.csv`), preencha com o nome do aluno, número de matrícula (ou e-mail) e o nome da turma, e faça o upload do arquivo.
4. O sistema irá criar as turmas e vincular os alunos automaticamente.

---

## 3. Banco de Questões e Importação
O Prof-Aid possui um poderoso banco de questões indexável.
- **Cadastro Manual:** Insira o enunciado, o tipo de questão (Múltipla Escolha ou Discursiva), alternativas (se houver), alternativa correta, nível de dificuldade, tags e disciplina.
- **Importação Automatizada:** Na aba **Importar Questões**, envie arquivos nos formatos **XLSX**, **DOCX**, **PDF** ou **JSON**. 
  - *Dica:* Use os arquivos de modelo (`questoes-modelo.docx` ou `questoes-modelo.xlsx`) para estruturar seu documento de modo a facilitar a importação direta das questões sem falhas de formatação.

---

## 4. Criação e Geração de Provas
Ao criar uma prova:
1. Vá até o menu **Provas** e clique em **Criar Prova**.
2. Preencha os metadados: Curso, Turma, Disciplina, Professor, Data, Período, Valor e Instruções (há um template de instruções padrão personalizável).
3. Selecione as questões que deseja incluir a partir do seu banco de questões.
4. **Múltiplas Versões:** Escolha quantas versões da prova você deseja gerar (ex: Versão A e Versão B). O sistema irá embaralhar a ordem das questões e das alternativas para evitar fraudes/cola, enquanto mantém um registro preciso das chaves de correção de cada versão.
5. Clique em **Salvar Prova** e visualize o PDF gerado em tamanho A4, diagramado com o cabeçalho institucional personalizado e as questões estruturadas.

---

## 5. Gabarito Adaptativo (ZipGrade/Gradepen)
Diferente de gabaritos estáticos que desperdiçam papel, o Prof-Aid gera **folhas de respostas adaptativas**:
- O sistema analisa a quantidade de questões e alternativas (ex: 10 questões de 4 alternativas) e constrói um grid de marcação compacto na folha de respostas.
- O design é otimizado para leitura óptica (OMR) utilizando métodos similares ao ZipGrade e Gradepen.
- Na visualização da prova, você pode imprimir a folha de respostas diretamente em PDF ou exportá-la como imagem de alta resolução (PNG) para impressão separada.

---

## 6. Registro de Atividades
Se você esquecer o que fez ou onde parou, o sistema monitora de forma transparente suas interações.
- Na tela do painel principal (**Início**), há uma linha do tempo (Timeline) que lista suas atividades recentes (ex: *"Prova de Cálculo Criada"*, *"Turma de Engenharia Importada"*).
- Isso ajuda a rastrear modificações e manter uma organização eficiente das tarefas cotidianas.

---

## 7. Sistema de Suporte e Tickets
Se encontrar qualquer bug, falha ou tiver sugestões de melhorias:
1. Acesse o **FAQ** (Central de Ajuda).
2. Se a sua dúvida não estiver respondida na listagem, use o formulário **"Ainda precisa de ajuda? Abra um chamado de suporte"**.
3. Escreva o título, a descrição detalhada do problema e a categoria (Erro técnico, Sugestão de melhoria, Dúvida de uso, etc.).
4. **Gerenciamento (Painel do Administrador):** 
   - Usuários com cargo de administrador podem visualizar a aba **Tickets de Suporte** dentro do painel **Admin**.
   - Lá, é possível alterar o status do ticket (Pendente, Em Andamento, Resolvido), responder textualmente ao usuário e manter um histórico completo de resolução do problema.
