<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Mapeia para a tabela `users` do ProvaGen — mesmo banco físico, tabela
 * pertence ao outro app (Node/Drizzle). É o professor, dono pagante da
 * conta. Nunca autentica por senha aqui: o hash em `password_hash` é
 * PBKDF2 (formato do ProvaGen), incompatível com o Hash::check() padrão do
 * Laravel (bcrypt). A entrada é sempre via SSO vindo do prof-aid — a rota de
 * callback do SSO chama Auth::guard('admin')->login($owner) diretamente
 * depois de validar o token assinado (ver Fase D do plano de integração).
 *
 * SSO implementado em App\Http\Controllers\SsoController — mas
 * AdminPanelProvider::login() ainda expõe um formulário de senha que nunca
 * autentica de verdade (Hash::check sempre falso, é seguro mas é uma
 * entrada morta) — só existe porque o Filament exige uma login page
 * registrada para o redirect de "não autenticado" funcionar.
 */
class Owner extends Authenticatable implements FilamentUser
{
    protected $table = 'users';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [];

    protected $hidden = [
        'passwordHash',
    ];

    /**
     * TODO(Fase H): trocar por uma checagem real de assinatura do módulo de
     * gestão (tabela gestaoModuleSubscriptions no ProvaGen) em vez do plano
     * genérico — "as capacidades dependem do pagamento", mas o pagamento
     * específico deste módulo ainda não tem uma tabela própria.
     */
    public function hasGestaoAccess(): bool
    {
        return in_array($this->plan, ['pro', 'enterprise'], true);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin' && $this->hasGestaoAccess();
    }

    /**
     * Continua chamado de "Student" na camada de código (classe, relações,
     * variáveis) para minimizar o volume de renomeação — só a tabela física
     * se chama "advisees", porque "students" já é do ProvaGen neste banco.
     */
    public function advisees()
    {
        return $this->hasMany(Student::class, 'owner_user_id');
    }

    public function settings()
    {
        return $this->hasOne(OwnerSettings::class, 'owner_user_id');
    }

    public function getTelegramChatIdAttribute(): ?string
    {
        return $this->settings?->telegram_chat_id;
    }

    /**
     * O código herdado do profatilio original (58 arquivos: Resources,
     * Widgets, Pages) checa `auth()->user()->role === 'admin'` em dezenas de
     * lugares pra saber "é o professor logado?". A coluna `role` real desta
     * tabela é do ProvaGen (enum 'user'/'admin' de plataforma — a maioria
     * dos professores pagantes reais é 'user', não 'admin'!) e significa
     * outra coisa completamente diferente. Em vez de tocar os 58 arquivos
     * (alto risco sem conseguir compilar localmente), este accessor
     * sobrescreve `role` só pra esta classe: todo Owner autenticado no
     * painel admin JÁ é o professor por definição (canAccessPanel() barrou
     * quem não tem acesso), então sempre responder 'admin' aqui é
     * semanticamente correto — não é gambiarra, é a definição certa de
     * "role" no contexto deste app.
     */
    public function getRoleAttribute(): string
    {
        return 'admin';
    }
}
