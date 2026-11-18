# 🚀 Planejamento de Transição SaaS — prof-aid.com

Este documento serve como o mapa estratégico e especificação técnica para a transformação da plataforma de orientação de um único professor (**profatilio.com.br**) em uma plataforma comercial multi-tenant escalável (**prof-aid.com**).

---

## 📋 Sumário
1. [Fase 1: Clonagem e Isolamento do Ambiente](#fase-1-clonagem-e-isolamento-do-ambiente)
2. [Fase 2: Arquitetura Multi-Tenant (Banco de Dados e Rotas)](#fase-2-arquitetura-multi-tenant-banco-de-dados-e-rotas)
3. [Fase 3: Dinamização de APIs e Armazenamento (SaaS Upgrades)](#fase-3-dinamizacao-de-apis-e-armazenamento-saas-upgrades)
4. [Fase 4: Integração Comercial e Cobranças (Stripe/Asaas)](#fase-4-integracao-comercial-e-cobrancas-stripeasaas)
5. [Fase 5: Customização Visual e Identidade Visual (White-Label)](#fase-5-customizacao-visual-e-identidade-visual-white-label)

---

## 📁 Fase 1: Clonagem e Isolamento do Ambiente

Para iniciar o desenvolvimento do **prof-aid.com** sem risco para o site em produção, o primeiro passo é criar um clone completo do repositório em uma pasta dedicada e reconfigurar o ecossistema.

### 💻 Passos para a Criação do Workspace
1. **Clonar para nova pasta local**:
   ```bash
   git clone /home/calefi/Documentos/profatilio /home/calefi/Documentos/profaid
   cd /home/calefi/Documentos/profaid
   ```
2. **Desvincular do repositório original e iniciar novo Git**:
   ```bash
   git remote remove origin
   # Configurar o novo repositório privado do GitHub do prof-aid
   # git remote add origin https://github.com/calefin/profaid.git
   ```
3. **Novo Banco de Dados de Desenvolvimento**:
   Criar um banco de dados local chamado `profaid_dev`.
4. **Isolar Variáveis de Ambiente (`.env`)**:
   ```bash
   cp .env.example .env
   # Configurar DB_DATABASE=profaid_dev
   # Configurar APP_NAME="Prof-Aid"
   # Configurar APP_URL="http://profaid.test" ou "http://127.0.0.1:8000"
   ```
5. **Instalar Dependências e Inicializar**:
   ```bash
   composer install
   npm install
   php artisan key:generate
   php artisan migrate --seed
   ```

---

## 🗄️ Fase 2: Arquitetura Multi-Tenant (Banco de Dados e Rotas)

O maior upgrade técnico é fazer a plataforma suportar múltiplos professores (tenants) compartilhando o mesmo código.

### 1. Estratégia de Multi-Tenancy: Single Database vs Multiple Databases
*   **Decisão Recomendada:** **Single Database (Banco de Dados Único com Escopo)**. 
    *   *Por quê:* Mais barato e simples de manter em hospedagens compartilhadas ou VPS iniciais. Cada tabela possui a coluna `tenant_id`.
    *   *Ferramenta:* Pacote `spatie/laravel-multitenancy` ou criação manual de Traits com Global Scopes.

### 2. Mudanças no Schema do Banco de Dados
A tabela de inquilinos (`tenants` ou `professors`) gerencia as assinaturas e domínios.
*   **Nova tabela `tenants`**:
    *   `id` (Primary Key)
    *   `name` (Nome comercial do professor/entidade)
    *   `domain` (Subdomínio, ex: `atilio.prof-aid.com` ou domínio próprio `portal.professor.com.br`)
    *   `email` (E-mail administrativo do tenant)
    *   `status` (`active`, `suspended`, `trial`)
    *   `plan_id` (Plano de assinatura do Stripe/Asaas)
    *   `expires_at` (Vencimento da licença)
    *   `settings` (JSON contendo configurações visuais, chaves de API específicas, etc.)
*   **Vincular tabelas existentes ao `tenant_id`**:
    *   `users` (Professores extras e alunos) ➔ `tenant_id`
    *   `students` (Orientandos) ➔ `tenant_id`
    *   `document_submissions` (Processos) ➔ `tenant_id`
    *   `academic_titles` (Títulos) ➔ `tenant_id`
    *   `invite_tokens` (Convites) ➔ `tenant_id`

### 3. Implementação do `TenantScope` (Segurança Absoluta)
Criar uma trait global no Laravel para garantir que nenhum aluno ou professor acesse dados de outro inquilino:
```php
namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (session()->has('tenant_id')) {
            $builder->where('tenant_id', session('tenant_id'));
        }
    }
}
```

### 🌐 4. Roteamento por Subdomínio
As rotas públicas e administrativas responderão dinamicamente ao subdomínio ativo:
```php
Route::domain('{tenant}.prof-aid.com')->group(function () {
    // Rotas de cada portal individual de orientando/professor
    Route::get('/', [LandingTenantController::class, 'show']);
    Route::get('/portal', [StudentPortalController::class, 'dashboard']);
});
```

---

## 🔌 Fase 3: Dinamização de APIs e Armazenamento (SaaS Upgrades)

No `profatilio.com.br`, as chaves de integração do Google Drive e Telegram estão travadas no arquivo `.env`. No SaaS, elas devem ser dinâmicas.

### 1. Sistema Dinâmico de Filesystem (Drives Individuais)
Em vez de ler do `.env`, o driver de armazenamento será instanciado em tempo de execução usando a coluna JSON `metadata` do professor/tenant logado:
```php
// No Service Provider do Laravel:
public function resolveTenantDisk($tenant)
{
    $config = $tenant->settings['storage'];

    if ($config['provider'] === 'google') {
        config(['filesystems.disks.tenant_google' => [
            'driver' => 'google',
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'refresh_token' => $config['refresh_token'],
            'folder_id' => $config['folder_id'],
        ]]);
    }
    // Permite trocar o disco ativo dinamicamente para cada processo do aluno
}
```

### 🤖 2. Bot do Telegram Centralizado com Deep Links
Manter um bot de Telegram para cada professor é inviável no modelo SaaS inicial.
*   **Solução:** Um único bot oficial do sistema (`@ProfAidBot`).
*   **Vínculo Inteligente:** O link gerado no painel do aluno terá um token codificado: `https://t.me/ProfAidBot?start=tenantid_studentid`.
*   **Roteamento de Mensagens:** O webhook do bot lê o ID do inquilino para saber qual aluno notificar e de qual processo se trata.

### 📧 3. Servidor de E-mail Dinâmico (SMTP ou API)
*   **Envio por Servidor Central:** E-mails acadêmicos serão enviados de `suporte@prof-aid.com`, mas com o nome e e-mail de resposta do professor configurados no cabeçalho `Reply-To`.
*   **SMTP Próprio (Opcional - Premium):** Permitir que o professor insira suas próprias credenciais SMTP de e-mail institucional no perfil.

---

## 💳 Fase 4: Integração Comercial e Cobranças (Stripe/Asaas)

Para viabilizar a monetização e controle comercial do SaaS.

### 1. Planos de Assinatura
| Recurso | Plano Básico (Gratuito / Trial) | Plano Pro (Individual) | Plano Premium (Institutos / Equipes) |
| :--- | :--- | :--- | :--- |
| **Limite de Orientandos** | Até 3 alunos ativos | Até 15 alunos ativos | Ilimitado |
| **Armazenamento** | 50MB no disco local | Conexão com Drive Próprio | Conexão com Drive Próprio + Armazenamento Central |
| **IA Audits** | 5 auditorias por mês | Ilimitado | Ilimitado |
| **Telegram Bot** | Não | Sim | Sim |
| **Assinatura Gov.br** | Apenas Manual | Homologação em Lote | Assinatura Direta via API (ICP-Brasil) |

### 2. Integração com Gateway de Pagamento
*   **Asaas** (Recomendado para Pix/Boleto no Brasil) ou **Stripe** (Excelente para cartão de crédito recorrente e internacional).
*   **Webhooks de Cobrança:** Ao receber a notificação de confirmação de pagamento (`invoice.paid`), atualize `expires_at` na tabela de inquilinos. Caso o pagamento falhe (`invoice.payment_failed`), mude o status do inquilino para `suspended`, impedindo novos envios pelos alunos, mas garantindo acesso aos dados antigos.

---

## 🎨 Fase 5: Customização Visual (White-Label)

Para que o portal de cada professor pareça único e personalizado para a sua instituição.

### 1. Cores e Logotipos do Painel
*   Adicionar no Perfil do Professor campos para:
    *   **Cor Primária** (Hexadecimal)
    *   **Logotipo do cabeçalho** (Arquivo PNG)
    *   **Favicon** (Ícone do navegador)
*   Na renderização do painel do orientando, usar as variáveis CSS injetadas dinamicamente:
    ```html
    <style>
        :root {
            --primary-color: {{ $tenant->settings['primary_color'] ?? '#0d9488' }};
        }
    </style>
    ```

### 📝 2. Configurações de Triagem Personalizadas
Cada professor poderá habilitar ou desabilitar regras e filtros:
- *"Exigir termos da LGPD no convite"*
- *"Validar normas ABNT via Inteligência Artificial"*
- *"Bloquear palavras específicas de nomenclatura jurídica (ex: tutor)"*
- *"Habilitar botão flutuante de suporte via WhatsApp"*
