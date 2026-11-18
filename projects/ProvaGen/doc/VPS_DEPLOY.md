# Guia de Deploy e Atualização no Servidor VPS

Este guia descreve os passos necessários para implantar e atualizar a aplicação **Prof-Aid (Prova Generator)** em um servidor VPS.

A aplicação utiliza **pnpm** como gerenciador de pacotes, **Drizzle ORM** para banco de dados (MySQL ou SQLite dependendo do ambiente) e compila o frontend com **Vite** e o backend com **esbuild**.

---

## Pré-requisitos

Certifique-se de que o servidor VPS tem as seguintes ferramentas instaladas:
- **Node.js** (versão 18 ou superior, recomendável v20+)
- **pnpm** (instalável via `npm install -g pnpm`)
- **PM2** (gerenciador de processos Node.js, instalável via `npm install -g pm2`)
- **Git**

---

## Fluxo de Atualização do Servidor (Passo a Passo)

Sempre que houver atualizações na branch principal (`main` ou `master`), execute os seguintes comandos no terminal do seu VPS:

### 1. Acessar a pasta do projeto
Conecte-se ao seu VPS via SSH e navegue até a pasta onde o projeto está clonado:
```bash
cd /caminho/para/o/projeto/ProvaGen
```

### 2. Baixar as alterações do Git
Atualize o repositório local com a versão mais recente do repositório remoto:
```bash
git pull origin main
```

### 3. Instalar/Atualizar as dependências
Utilize o `pnpm` para instalar possíveis novas dependências adicionadas ao projeto:
```bash
pnpm install
```

### 4. Executar Migrações do Banco de Dados
Para atualizar as tabelas do banco de dados (por exemplo, a tabela de tickets de suporte recém-criada), rode o comando de migração do Drizzle:
```bash
pnpm db:push
```
> **Nota:** Certifique-se de que o arquivo `.env` do servidor contém as credenciais corretas do banco de dados (`DATABASE_URL`, etc.).

### 5. Compilar a aplicação para Produção
Gere a build de produção tanto do frontend (Vite) quanto do backend (esbuild):
```bash
pnpm build
```
Os arquivos gerados serão salvos na pasta `/dist`.

### 6. Reiniciar o servidor da aplicação
Se você estiver utilizando o **PM2** para manter o servidor ativo em segundo plano, reinicie o processo:
```bash
pm2 restart prof-aid
```
*(Caso não lembre o nome do processo no PM2, rode `pm2 list` para listar os processos ativos ou crie um novo processo com `pm2 start dist/index.js --name prof-aid`).*

Caso utilize **Systemd** (serviço do Linux), reinicie o serviço correspondente:
```bash
sudo systemctl restart prof-aid
```

---

## Resumo dos Comandos em Lote
Para facilitar, você pode rodar os comandos em uma única linha no VPS:
```bash
git pull origin main && pnpm install && pnpm db:push && pnpm build && pm2 restart prof-aid
```

## Monitoramento
Para verificar se o servidor iniciou corretamente e inspecionar logs de erro:
```bash
pm2 logs prof-aid
```
ou pelo systemd:
```bash
sudo journalctl -u prof-aid -f -n 100
```
