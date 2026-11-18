<?php

namespace App\Support;

use App\Models\AdviseeAccount;
use App\Models\Owner;
use Illuminate\Support\Facades\Auth;

/**
 * Rotas fora de um painel Filament (routes/web.php, ex.: download de
 * documento por link) não passam pelo middleware SetDefaultAuthGuard dos
 * painéis, então auth()->user() sozinho só olha pro guard padrão — nunca
 * acharia um professor logado (guard 'admin') nessas rotas. Usar isto no
 * lugar de auth()->user() sempre que a rota puder ser acessada tanto pelo
 * professor quanto pelo aluno.
 */
class CurrentActor
{
    public static function get(): Owner|AdviseeAccount|null
    {
        return Auth::guard('admin')->user() ?? Auth::guard('portal')->user();
    }

    public static function isOwner(): bool
    {
        return self::get() instanceof Owner;
    }
}
