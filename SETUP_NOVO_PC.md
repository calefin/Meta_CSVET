# Guia de Configuração em Novo Computador (Setup) & Próximas Implementações

Este guia contém os passos para configurar este projeto em qualquer outra máquina de desenvolvimento e detalha as novas implementações sugeridas para o crescimento da plataforma.

---

## 💻 1. Como Configurar o Projeto em outro Computador

Siga estes passos sequenciais ao baixar ou clonar o projeto em uma nova máquina:

### Passo 1: Instalar Requisitos do Sistema
Garanta que a máquina possua:
* **PHP 8.2 ou superior** (com as extensões comuns: `openssl`, `pdo`, `mbstring`, `tokenizer`, `xml`, `curl`, `zip`).
* **Composer** (gerenciador de dependências PHP).
* **MySQL** ou **SQLite** (banco de dados).
* **Node.js** e **NPM** (para gerenciar pacotes front-end, se aplicável).

### Passo 2: Copiar e Configurar o Ambiente
Na raiz do projeto, copie o arquivo de exemplo de variáveis de ambiente:
```bash
cp .env.example .env
```
Abra o arquivo `.env` gerado e configure:
1. As credenciais de banco de dados (`DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).
2. O seu número de WhatsApp comercial na chave correspondente:
   ```env
   WHATSAPP_PHONE=5511977998476
   ```
3. Chaves adicionais caso utilize APIs externas (como `GEMINI_API_KEY`, `TELEGRAM_BOT_TOKEN`, etc.).

### Passo 3: Instalar Dependências do PHP
Instale todas as bibliotecas necessárias declaradas no `composer.json`:
```bash
composer install
```

### Passo 4: Gerar Chave da Aplicação
Gere a chave criptográfica única do Laravel para a sua sessão local:
```bash
php artisan key:generate
```

### Passo 5: Executar as Migrações e Alimentar o Banco (Migrations & Seeders)
Crie a estrutura de tabelas e crie os dados iniciais padrão (como o usuário administrador):
```bash
php artisan migrate --seed
```

### Passo 6: Rodar Testes de Validação
Verifique se a aplicação está 100% íntegra rodando a suite de testes automáticos:
```bash
vendor/bin/phpunit
```
*(Todos os 9 testes de integração devem passar com sucesso).*

### Passo 7: Iniciar o Servidor Local
Inicie o servidor de desenvolvimento interno do Laravel:
```bash
php artisan serve
```
Acesse no navegador através de: `http://127.0.0.1:8000`.

---

## 🔮 2. Novas Implementações Sugeridas (Roadmap de Crescimento)

Com o botão do WhatsApp e a otimização básica de SEO concluídos, aqui estão as próximas implementações recomendadas para atração de clientes e otimização da plataforma:

### 1. Seção de Perguntas Frequentes (FAQ) Interativa
* **O que é**: Uma sanfona (accordion) de perguntas e respostas frequentes inserida na página principal ([welcome.blade.php](file:///home/calefi/Documentos/profatilio/resources/views/welcome.blade.php)) ou na página de orçamento de necropsia ([quote_form.blade.php](file:///home/calefi/Documentos/profatilio/resources/views/necropsy/quote_form.blade.php)).
* **Benefício de SEO**: Injeção da marcação estruturada `FAQPage` (JSON-LD). O Google exibe essas perguntas diretamente nos resultados de busca do usuário, ocupando mais espaço visual na tela de pesquisa e aumentando o tráfego orgânico.
* **Exemplos de perguntas**: *"Como funciona a mentoria de TCC?"*, *"Quais os prazos para entrega de laudos de necropsia?"*, *"Como é feita a assinatura eletrônica pelo Gov.br?"*.

### 2. Prova Social e Depoimentos (AggregateRating Schema)
* **O que é**: Uma seção moderna de carrossel de depoimentos avaliados com estrelas (1 a 5) de ex-alunos de mentoria e clínicas veterinárias parceiras que contrataram laudos.
* **Benefício de SEO**: Injeção do schema `AggregateRating` do Schema.org. Isso permite que o Google mostre as estrelas douradas de avaliação (ex: "★ 4.9 - 42 avaliações") diretamente no resultado de busca do seu site, aumentando drasticamente a taxa de cliques (CTR).

### 3. Integração de Mapa Local para a CSVET (SEO Local)
* **O que é**: Um mapa estilizado em modo escuro integrado na seção de contato e rodapé, junto à formatação do endereço físico da CSVET.
* **Benefício de SEO**: Conecta as coordenadas geográficas ao schema `LocalBusiness` ou `VeterinaryCare`. Facilita o ranqueamento no Google Maps regional para termos como *"necropsia veterinária SP"*, *"laudo veterinário perto de mim"* ou *"patologista veterinário"*.

### 4. Automatização de Agendamentos / Pré-triagem
* **O que é**: Adicionar um pequeno calendário interativo (ou link de integração ao Calendly/Google Calendar) na página de orçamento para que o cliente interessado já reserve uma data para triagem de sua mentoria ou entrega do animal para necropsia.
* **Benefício**: Reduz a fricção de conversão comercial, capturando o cliente no momento de maior interesse.
