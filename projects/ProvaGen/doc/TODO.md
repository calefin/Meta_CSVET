# Prova Generator - TODO

## Fase 1: Arquitetura e Design System
- [x] Definir schema de banco de dados (usuários, perfil, questões, provas, gabaritos)
- [x] Configurar design system com tipografia, cores e componentes
- [x] Criar layout base com navegação e autenticação

## Fase 2: Autenticação e Perfil de Usuário
- [x] Implementar login/logout com Manus OAuth
- [x] Criar formulário de cadastro (email, telefone, universidade)
- [x] Implementar upload e armazenamento de logotipo institucional
- [x] Criar página de perfil do usuário com edição de dados
- [x] Testes unitários para autenticação

## Fase 3: Importação de Questões com LLM
- [x] Implementar parser para XLSX (openpyxl)
- [x] Implementar parser para DOCX (python-docx)
- [x] Implementar parser para PDF (pdfplumber)
- [x] Implementar parser para JSON
- [x] Integrar LLM para estruturação automática
- [x] Criar interface de visualização de questões importadas
- [x] Implementar edição de questões antes de salvar
- [x] Salvar questões no banco
- [x] Testes unitários para importação e LLM

## Fase 4: Banco de Questões
- [x] Criar interface de listagem de questões
- [x] Implementar busca e filtros por tipo, disciplina, etc
- [x] Implementar edição de questões
- [x] Implementar exclusão de questões
- [x] Testes unitários para banco de questões

## Fase 5: Criação de Prova
- [x] Criar formulário de dados da avaliação (curso, turma, campus, disciplina, professor, data, período, nome)
- [x] Implementar campo de instruções com texto padrão editável
- [x] Implementar seleção de questões do banco
- [x] Implementar configuração de versões da prova
- [x] Implementar embaralhamento de questões e alternativas
- [x] Criar página de listagem de provas
- [x] Testes unitários para criação de prova

## Fase 6: Geração de PDF
- [x] Implementar geração de PDF em A4 com ReportLab/WeasyPrint
- [x] Implementar cabeçalho com logo, dados da avaliação e instruções
- [x] Implementar formatação de questões múltipla escolha
- [x] Implementar formatação de questões discursivas
- [x] Implementar múltiplas versões com embaralhamento
- [x] Implementar paginação e numeração
- [x] Testes unitários para geração de PDF

## Fase 7: Processamento de Gabarito ZipGrade
- [x] Implementar upload de imagem de gabarito
- [x] Integrar visão computacional para leitura de gabarito
- [x] Implementar extração automática de respostas marcadas
- [x] Implementar geração de relatório de correção
- [x] Implementar associação de gabarito com prova
- [x] Testes unitários para processamento de gabarito

## Fase 8: Otimização e Finalização
- [x] Revisar design visual e UX
- [x] Otimizar performance
- [x] Implementar tratamento de erros robusto
- [x] Criar documentação de uso
- [x] Testes de integração completos
- [x] Deploy e entrega ao usuário


## Fase 9: Correção e Melhoria do Sistema de Importação
- [x] Analisar formato de prova modelo (DOCX com questões discursivas)
- [x] Melhorar parser DOCX para extrair questões corretamente
- [x] Integrar LLM para estruturação automática de questões
- [x] Criar interface de visualização e correção de questões
- [x] Testar importação com arquivo modelo fornecido


## Fase 10: Debug e Correção de Importação
- [x] Verificar logs do backend para erros na importação
- [x] Testar parser Python diretamente com arquivo modelo
- [x] Verificar resposta da API de importação
- [x] Corrigir problema identificado (remover processamento LLM lento)
- [x] Testar fluxo completo end-to-end
