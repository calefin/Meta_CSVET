# Guia de Implantação e Atualização de Banco de Dados

Este guia descreve os passos necessários para implantar o sistema **profatilio.com.br (V2)** no servidor da HostGator e atualizar o banco de dados.

---

## 🚀 Método 1: Implantação Automatizada via Web (`deploy.php`)

Se você não tiver acesso direto via SSH ou preferir uma atualização rápida através do navegador, criamos o script utilitário **[deploy.php](file:///D:/profatilio/deploy.php)**.

### Passos:
1. Envie todos os arquivos do repositório para o servidor.
2. Abra o arquivo **[deploy.php](file:///D:/profatilio/deploy.php)** e altere o token de segurança na linha 13 (`DEPLOY_TOKEN`) para uma chave secreta de sua escolha.
3. No seu navegador, acesse a URL abaixo para iniciar a instalação e atualizar o banco de dados:
   ```text
   https://profatilio.com.br/deploy.php?token=SEU_TOKEN_SECRETO
   ```
4. O script executará as seguintes ações automaticamente:
   - Limpeza de caches antigos.
   - Instalação das dependências via Composer (`composer install`).
   - **Atualização do Banco de Dados** (`php artisan migrate --force`).
   - Otimização do cache de rotas, configurações e views.

   ⚠️ O `deploy.php` **não roda seeders**. Se o deploy incluir o sistema de Cursos (sessão de 2026-08-13), rode manualmente depois — via SSH, ou temporariamente por uma rota/tinker protegida — o comando `php artisan db:seed --class="Database\Seeders\CourseCurriculumSeeder" --force` (ver Método 2, passo 3).
   - Reinicialização da fila de processamento.
5. **Importante:** Por questões de segurança, delete ou renomeie o arquivo `deploy.php` do servidor após concluir a implantação.

---

## 💻 Método 2: Implantação Manual via SSH (Recomendado)

Caso você possua acesso ao terminal SSH na sua conta cPanel da HostGator:

### 1. Preparar os Arquivos
Acesse o diretório raiz da sua aplicação no servidor:
```bash
cd /home/usuario_cpanel/public_html
```

### 2. Instalar dependências de Produção
O shell da HostGator (jailshell) **não tem o comando `composer` no PATH**. Já existe um `composer.phar` baixado
diretamente em `~/profatilio.com.br/composer.phar` (instalado em 2026-08-19 via `php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && php composer-setup.php`,
já que o download direto funcionou nesse servidor). Use sempre esse `.phar` local:
```bash
php composer.phar install --no-dev --optimize-autoloader
```
Só é necessário rodar isso quando `composer.json`/`composer.lock` mudarem (nova dependência). Se um dia o
`composer.phar` sumir do servidor, basta rebaixar com o mesmo comando de instalação acima.

### 3. Atualizar o Banco de Dados (Migrations)
Execute as migrações para subir as novas tabelas e relacionamentos (`students`, `document_submissions`, `clinical_records`, etc.):
```bash
php artisan migrate --force
```
*Nota: A flag `--force` é obrigatória em ambiente de produção para ignorar confirmações manuais no terminal.*

**Após o deploy que inclui o sistema de Cursos** (sessão de 2026-08-13), rode também o seeder que popula os cursos de R e Python (idempotente — pode rodar de novo sem duplicar):
```bash
php artisan db:seed --class="Database\Seeders\CourseCurriculumSeeder" --force
```

### 4. Otimizar a Aplicação
Limpe e gere os arquivos de cache de configuração, rotas e views do Laravel:
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 5. Reiniciar Filas (Queue)
Se houver jobs em segundo plano ativos, reinicie o worker:
```bash
php artisan queue:restart
```

### 6. Link de Storage Público (necessário a partir do Blog, sessão de 2026-08-26)
As imagens de capa dos posts do blog usam o disco `public` (não o disco padrão `google`, que é privado). Isso exige o symlink `public/storage` → `storage/app/public`, que só precisa ser criado uma vez:
```bash
php artisan storage:link
```
Se esse comando já tiver sido rodado antes, ele avisa que o link já existe — pode rodar de novo sem problema.
