# Requisitos do Sistema & Configuração HostGator

Este documento lista todos os requisitos de infraestrutura e as recomendações de configuração para implantar o sistema no ambiente de hospedagem compartilhada da HostGator (cPanel).

---

## 💻 Requisitos do Servidor

- **PHP 8.2 ou superior** (Recomendado PHP 8.2)
- **Servidor Web Apache** com o módulo `mod_rewrite` habilitado (gerenciado pelo nosso arquivo [.htaccess](file:///D:/profatilio/.htaccess))
- **Extensões PHP obrigatórias**:
  - `zip` (habilitada) - para downloads de pacotes e descompactação de arquivos `.docx`.
  - `gd` ou `imagick` - para processamento de imagens no Filament.
  - `intl` - para localização e formatação de datas e moedas no painel administrativo.
  - `pdo_mysql` - para conexão com a base de dados.
  - `mbstring`, `openssl`, `xml`, `fileinfo`, `curl` (habilitadas por padrão).

---

## 🗄️ Recomendação de Banco de Dados MySQL (HostGator cPanel)

No cPanel da HostGator, os nomes de bancos de dados e usuários recebem obrigatoriamente um prefixo que corresponde ao nome de usuário da sua conta do cPanel (ex: `atilio_`).

### Sugestão de Nomenclatura:
- **Nome do Banco de Dados (Database):** `atilio_profatilio` (ou `atilio_v2`)
- **Usuário do Banco de Dados (User):** `atilio_dbuser`
- **Senha:** Utilize o gerador de senhas do cPanel para criar uma senha forte e segura.
- **Privilégios:** Certifique-se de associar o usuário ao banco de dados e marcar a opção **"TODOS OS PRIVILÉGIOS"** (ALL PRIVILEGES).

Depois de criado, atualize essas informações no arquivo [.env](file:///D:/profatilio/.env):
```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=atilio_profatilio
DB_USERNAME=atilio_dbuser
DB_PASSWORD=SUA_SENHA_GERADA
```

---

## ⏱️ Configuração de Filas (Queue) via Cron Job

Como a hospedagem compartilhada não suporta processos persistentes em segundo plano (como `queue:work` rodando continuamente) nem Redis, configuramos o driver `database` e utilizaremos um Cron Job no cPanel para processar as tarefas de auditoria de IA.

### Como configurar no cPanel:
1. Acesse o cPanel e vá em **Trabalhos Cron** (Cron Jobs).
2. Adicione uma nova tarefa cron executada **A cada minuto** (`* * * * *`).
3. No campo de comando, insira o seguinte caminho (ajuste `/home/usuario_cpanel` para o caminho real da sua conta):
   ```bash
   /usr/local/bin/php /home/usuario_cpanel/public_html/artisan queue:work --stop-when-empty >> /dev/null 2>&1
   ```

*Nota: A flag `--stop-when-empty` instrui o Laravel a processar todas as tarefas pendentes na fila e encerrar o script, evitando processos órfãos consumindo CPU e memória no servidor compartilhado.*

---

## 🔑 Permissões de Pastas
Para que o Laravel funcione corretamente, as seguintes pastas precisam ter permissão de escrita pelo servidor web (geralmente `755` ou `775` no cPanel):
- `storage/` (e todas as subpastas)
- `bootstrap/cache/`
