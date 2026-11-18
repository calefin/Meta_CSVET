# Guia de Deploy e Configuração de E-mail (SMTP) no Servidor VPS

Este guia orienta sobre como configurar as credenciais do seu e-mail profissional do VetRank (`contato@vetrank.com.br`, `suporte@vetrank.com.br` e `noreply@vetrank.com.br`) no servidor VPS.

---

## 1. Passo a Passo no Servidor VPS

Como o projeto é executado com Docker Compose, o deploy baixará automaticamente a nova dependência (`nodemailer`) e reconstruirá a aplicação.

Siga os passos abaixo quando estiver pronto para atualizar o servidor:

### Passo 1: Acesse seu servidor via SSH
```bash
ssh seu_usuario@ip_do_servidor
```

### Passo 2: Edite o arquivo `.env` do servidor
Navegue até o diretório da aplicação e edite as variáveis de ambiente utilizando o editor de texto `nano` ou `vim`:
```bash
cd /opt/apps/vetrank
nano .env
```

### Passo 3: Adicione as novas variáveis de SMTP
No arquivo `.env` do servidor, configure os dados do seu provedor de e-mail (como Titan Mail, Hostinger, Locaweb, etc.). 

Adicione ou atualize os campos abaixo:
```env
# ─── Configuração de E-mail SMTP (Gratuito) ───
SMTP_HOST=mail.vetrank.com.br
SMTP_PORT=465
SMTP_SECURE=true
SMTP_USER=noreply@vetrank.com.br
SMTP_PASS=senha_da_conta_noreply

# Remetente padrão do sistema
EMAIL_FROM=VetRank <noreply@vetrank.com.br>
```

> **Atenção**: As mensagens automáticas de cadastro e expiração de conta serão enviadas por `noreply@vetrank.com.br`. As comunicações de suporte serão exibidas como de `suporte@vetrank.com.br` nos links de contato.

Salve o arquivo (no `nano`, pressione `Ctrl + O`, `Enter` e depois `Ctrl + X`).

### Passo 4: Execute o script de deploy
Rode o comando de deploy para atualizar o código e reconstruir o container Docker:
```bash
cd /opt/apps/vetrank && git pull origin main && docker compose up -d --build
```
*Nota: O build completo dentro do Docker leva cerca de 7 a 8 minutos.*

### Passo 5: Aplique as migrações do Banco de Dados (Drizzle)
Após o container estar rodando, você deve aplicar as migrações do banco de dados executando o script `db:push` de dentro do container:
```bash
docker compose exec vetrank pnpm db:push
```

---

## 2. Configurações de SMTP Comuns

### Provedor Titan Mail (Hostinger profissional)
* **SMTP Host**: `smtp.titan.email`
* **Porta**: `465` (SSL) -> `SMTP_SECURE=true` ou `587` (TLS) -> `SMTP_SECURE=false`
* **Usuário**: `noreply@vetrank.com.br`

### Provedor Hostinger (E-mail padrão)
* **SMTP Host**: `smtp.hostinger.com`
* **Porta**: `465` (SSL) -> `SMTP_SECURE=true` ou `587` (TLS) -> `SMTP_SECURE=false`
* **Usuário**: `noreply@vetrank.com.br`

---

## 3. Fluxo de E-mails Atualizado
* **Remetente padrão (Sistema)**: `noreply@vetrank.com.br` (para cadastros, ativações, alertas de expiração).
* **Fale Conosco / Suporte**: links nas páginas de erro de pagamento e política de privacidade agora direcionam para `suporte@vetrank.com.br`.
* **Notificação de novos tickets**: toda vez que um aluno abrir um ticket de suporte, um e-mail de aviso continuará sendo enviado para **`calefi@csvet.com.br`** (definido no backend).
