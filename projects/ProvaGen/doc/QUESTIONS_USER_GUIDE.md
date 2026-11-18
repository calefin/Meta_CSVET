# Guia de Uso: Sistema de Questões ProvaGen

## 🚀 Início Rápido

### Instalação

```bash
# Instale as dependências
pnpm install

# Execute a migração do banco de dados
pnpm db:push

# Inicie o servidor de desenvolvimento
pnpm dev
```

### Acesso

1. Abra seu navegador em `http://localhost:5173`
2. Faça login na aplicação
3. Navegue para "Banco de Questões" → "Nova Questão"

---

## 📋 Criando uma Questão

### Passo 1: Selecionar Tipo de Questão

Escolha um dos 4 tipos disponíveis:

#### 1️⃣ **Múltipla Escolha**
- Padrão com 1 resposta correta
- 4 ou 5 alternativas (A-D ou A-E)
- Ideal para testes objetivos

**Exemplo:**
```
Pergunta: Qual é a capital da França?

A) Londres
B) Berlim
C) Paris ✓ (correta)
D) Madri
E) Roma
```

#### 2️⃣ **Múltipla Escolha Complexa**
- Múltiplas respostas corretas
- Ideal para questões com múltiplos conceitos

**Exemplo:**
```
Pergunta: Quais dos seguintes são mamíferos?

A) Cachorro ✓
B) Cobra
C) Gato ✓
D) Sapo
E) Baleia ✓
```

#### 3️⃣ **Questão Dissertativa**
- Resposta aberta
- Com sugestão de resposta para o professor
- Critérios de avaliação opcionais

**Exemplo:**
```
Pergunta: Explique o ciclo da água

Resposta Sugerida:
O ciclo da água envolve evaporação, condensação, 
precipitação e escoamento...

Critérios de Avaliação:
• Mencionou evaporação
• Mencionou condensação
• Explicou cada etapa
```

#### 4️⃣ **Asserção e Razão** (I a V ou I a IV)
- Padrão brasileiro
- Avalia se a asserção é verdadeira E se a razão a justifica
- Presets pré-moldados

**Exemplo:**
```
Item I
Asserção: A fotossíntese produz oxigênio
Razão: Plantas precisam de oxigênio para respirar

Padrão: Verdadeira, mas Razão Não Explica
```

---

## 📝 Preenchendo o Formulário

### Campos Obrigatórios

1. **Enunciado/Pergunta**
   - Seja claro e direto
   - Pode incluir contexto adicional
   - Suporta quebras de linha

2. **Disciplina**
   - Selecione entre as existentes
   - Ou crie uma nova digitando o nome
   
   *Exemplos:*
   - Matemática
   - Português
   - Biologia
   - História

3. **Assunto**
   - Tópico específico dentro da disciplina
   - Ajuda na organização e busca
   
   *Exemplos (Matemática):*
   - Frações
   - Geometria
   - Equações

### Campos Opcionais

- **Dificuldade**: Fácil, Médio, Difícil
- **Tags**: Etiquetas adicionais de categorização

---

## 🎨 Abas do Formulário

### Aba 1: Formulário
- Preencha todos os campos
- Validação em tempo real
- Mensagens de erro claras

### Aba 2: Pré-visualização
- Visualize como a questão ficará
- Veja formatação e imagens
- Confira antes de salvar

### Aba 3: Imagens
- Adicione até 5 imagens
- Serão comprimidas automaticamente
- Sistema validará quota de armazenamento

---

## 📷 Gerenciar Imagens

### Upload de Imagens

```
┌─────────────────────────────┐
│ Clique ou arraste aqui      │
│ PNG, JPG, WebP até 10MB     │
└─────────────────────────────┘
```

**Formatos Suportados:**
- PNG (recomendado para gráficos)
- JPG (recomendado para fotos)
- WebP (moderno, comprime bem)
- GIF (suportado)

### Limite de Armazenamento

**Plano Gratuito:**
- 100MB total
- Até 5 imagens por questão
- Máx 10MB por imagem

**Após Upload:**
- Imagens comprimidas no cliente
- Armazenadas localmente no servidor
- Servidas via API `/api/images/:chave`

### Monitorar Quota

```
Armazenamento: 42.5 MB / 100 MB
████████████░░░░░░░░░░░░░░░░░░░ 42.5%

Espaço disponível: 57.5 MB
```

---

## 🔄 Fluxo Completo de Criação

### 1. Nova Questão
```
Clique em: Banco de Questões > Nova Questão
```

### 2. Preench Dados Básicos
```
Enunciado: [Sua pergunta]
Disciplina: [Selecione/Digite]
Assunto: [Selecione/Digite]
Dificuldade: [Selecione]
Tipo: [Selecione o tipo]
```

### 3. Preencher Específico do Tipo

**Se Múltipla Escolha:**
```
Alt. A: [texto]
Alt. B: [texto]
Alt. C: [texto]
Alt. D: [texto]
Resposta Correta: [Selecione]
Explicação: [Opcional]
```

**Se Dissertativa:**
```
Resposta Sugerida: [texto longo]
Critérios: [Um por linha]
```

**Se Asserção e Razão:**
```
Estrutura: [I-IV ou I-V]

Item I:
  Asserção: [texto]
  Verdadeira: [V/F]
  Razão: [texto]
  Razão Correta: [S/N]

[Repetir para cada item]
```

### 4. Pré-visualizar
```
Clique em: Pré-visualização
Confira formatação e conteúdo
```

### 5. Adicionar Imagens (Opcional)
```
Clique em: Imagens
Arraste ou clique para adicionar
```

### 6. Salvar
```
Clique em: Salvar Questão
Sistema valida e salva
```

---

## 🔍 Editando Questões

### Acessar Edição

1. Vá para **Banco de Questões**
2. Encontre a questão na lista
3. Clique no ícone **✏️ Editar**

### Modificar

- Edite qualquer campo
- Adicione ou remova imagens
- Confirme pré-visualização
- Clique **Salvar Alterações**

### Deletar

1. Clique no ícone **🗑️ Lixeira**
2. Confirme no diálogo
3. Questão e imagens são removidas
4. Quota é liberada

---

## 📊 Listando e Filtrando

### Banco de Questões

**Visualizar Todas:**
- Acesse: **Banco de Questões**
- Mostra paginação (10 por página)

**Buscar:**
```
Digite na caixa de busca:
- Parte da pergunta
- Palavra-chave
- Disciplina
```

**Filtrar:**

| Filtro | Opções |
|--------|--------|
| Tipo | Múltipla Escolha, Dissertativa, Asserção e Razão |
| Disciplina | Matemática, Português, Biologia, etc. |
| Dificuldade | Fácil, Médio, Difícil |

**Exemplo Filtrado:**
```
Disciplina: Matemática
Tipo: Múltipla Escolha
Dificuldade: Difícil
→ Mostra apenas questões que atendem todos
```

---

## 📈 Gerenciar Armazenamento

### Visualizar Uso

```
Armazenamento: 42.5 MB / 100 MB
Status: OK (42.5% utilizado)
```

### Quando Aproximar do Limite

- Sistema avisa em **80%**
- Botão para **Atualizar Plano**

### Planos Disponíveis

| Plano | Quota | Preço | Renovação |
|-------|-------|-------|-----------|
| Gratuito | 100MB | Grátis | - |
| Básico | 500MB | R$ 9,90 | Mensal |
| Pro | 2GB | R$ 29,90 | Mensal |
| Enterprise | 10GB | R$ 99,90 | Mensal |

---

## ⚠️ Limitações e Dicas

### Tamanho de Arquivo
- Máx **10MB** por imagem (individual)
- Serão comprimidas no cliente
- Redimensionadas para máx 1920x1440px

### Quota
- Contabilizado o tamanho pós-compressão
- Deletar questão libera espaço
- Imagens órfãs são limpas periodicamente

### Performance
- Paginação: 10 questões por página
- Busca: instantânea no cliente
- Imagens: cache de 1 ano

### Boas Práticas
1. ✅ Use enunciados claros e objetivos
2. ✅ Adicione disciplina e assunto sempre
3. ✅ Comprima imagens antes de enviar (faço automaticamente)
4. ✅ Use imagens para ilustrar, não apenas decorar
5. ✅ Revise antes de salvar
6. ✅ Organize com tags e disciplinas

---

## 🆘 Troubleshooting

### Erro: "Imagem muito grande"
**Solução:** Reduza o tamanho no editor de imagens

### Erro: "Quota insuficiente"
**Solução:** Delete questões antigas ou upgrade de plano

### Erro: "Enunciado obrigatório"
**Solução:** Preencha o campo de pergunta/enunciado

### Imagem não aparece na pré-visualização
**Solução:** Verifique o formato (PNG/JPG/WebP)

### Questão não salva
**Solução:** Confira validação (aba Pré-visualização)

---

## 🎓 Casos de Uso

### Professor de Biologia
```
Disciplina: Biologia
Assunto: Genética
Tipo: Múltipla Escolha
Dificuldade: Médio

Adiciona imagem: Punnett Square
Cria 5 questões sobre herança genética
```

### Professor de Português
```
Disciplina: Português
Assunto: Literatura Brasileira
Tipo: Dissertativa
Dificuldade: Difícil

Define critérios de avaliação:
• Análise de personagens
• Contexto histórico
• Uso de exemplos
```

### Professor de Tecnologia
```
Disciplina: Programação
Assunto: Estruturas de Dados
Tipo: Asserção e Razão
Dificuldade: Médio

Cria questões I-IV com padrões de complexidade
```

---

## 📚 Próximas Funcionalidades

- [ ] Importar questões de arquivo (XLSX/CSV)
- [ ] Exportar em lote
- [ ] Duplicar questão
- [ ] Versão da questão
- [ ] Compartilhar com colegas
- [ ] Relatório de uso

---

**Última atualização:** Maio 2026
**Versão:** 1.0.0
