# 📌 Primeiros Passos Pós-Instalação (A Fazer Depois de Descansar)

Este arquivo serve como um guia persistente para quando você retornar. Ele detalha exatamente os comandos e configurações que faltam executar no servidor da HostGator.

---

## 👤 1. Criação do Usuário Administrador (Filament)

Como a base de dados MySQL foi recém-criada e está vazia, siga estes passos via terminal SSH dentro de `~/profatilio.com.br`:

### Passo A: Criar o Usuário base
Execute o comando abaixo e siga as instruções na tela (digite seu nome, e-mail e senha):
```bash
php artisan make:filament-user
```

### Passo B: Definir o usuário como Admin
Para que o painel identifique você com permissões de administrador total (campo `role = 'admin'`), execute:
```bash
php artisan tinker
```
Dentro do console do Tinker que se abrirá, copie e cole o seguinte código e pressione **Enter**:
```php
$user = App\Models\User::first();
$user->role = 'admin';
$user->save();
exit;
```

---

## 🔗 2. Acesso ao Painel
Após criar e configurar o usuário, acesse o painel pelo navegador:
👉 **[https://profatilio.com.br/admin](https://profatilio.com.br/admin)**

---

## ⚙️ 3. Credenciais no arquivo `.env`
Abra o arquivo `.env` no servidor (via Gerenciador de Arquivos do cPanel ou SSH) e adicione/atualize as seguintes chaves de integração:

```ini
# --- INTEGRAÇÃO COM GEMINI AI ---
GEMINI_API_KEY=INSIRA_SUA_CHAVE_AQUI
GEMINI_MODEL=gemini-2.5-flash

# --- INTEGRAÇÃO COM GOOGLE DRIVE ---
GOOGLE_DRIVE_CLIENT_ID=INSIRA_O_CLIENT_ID
GOOGLE_DRIVE_CLIENT_SECRET=INSIRA_O_CLIENT_SECRET
GOOGLE_DRIVE_REFRESH_TOKEN=INSIRA_O_REFRESH_TOKEN
GOOGLE_DRIVE_FOLDER_ID=INSIRA_O_ID_DA_PASTA_NO_DRIVE
```

---

## ⏰ 4. Agendador de Tarefas (Cron Job) no cPanel
Para que a auditoria automática de ABNT e o resumo diário de notificações funcionem em segundo plano, adicione as duas tarefas abaixo no menu **Trabalhos Cron** do cPanel da HostGator (ambas com frequência de **a cada minuto** `* * * * *`):

### Tarefa A: Agendador de Comandos (Scheduler)
Necessário para rodar o resumo diário e rotinas programadas:
- **Comando:**
  ```bash
  /usr/local/bin/php /home/atilio12/profatilio.com.br/artisan schedule:run >> /dev/null 2>&1
  ```

### Tarefa B: Fila de Processamento (Queue)
Necessário para a auditoria de ABNT e envio de e-mails em segundo plano:
- **Comando:**
  ```bash
  /usr/local/bin/php /home/atilio12/profatilio.com.br/artisan queue:work --stop-when-empty >> /dev/null 2>&1
  ```
