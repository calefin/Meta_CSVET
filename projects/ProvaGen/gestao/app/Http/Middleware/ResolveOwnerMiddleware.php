<?php

namespace App\Http\Middleware;

use App\Support\OwnerContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve o owner_user_id ativo do request:
 *   - Logado como professor (guard 'admin', App\Models\Owner): o próprio id.
 *   - Logado como orientando (guard 'portal', App\Models\AdviseeAccount):
 *     o owner_user_id salvo na conta do orientando (o professor que o
 *     convidou/cadastrou).
 *   - Nenhum dos dois: nada é resolvido — rotas públicas (verificação de
 *     documento, checkin de aula, etc.) precisam localizar o owner por
 *     conta própria a partir do registro/token que estão acessando, não
 *     deste contexto global.
 */
class ResolveOwnerMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $ownerUserId = null;

        if ($owner = Auth::guard('admin')->user()) {
            $ownerUserId = $owner->id;
        } elseif ($advisee = Auth::guard('portal')->user()) {
            $ownerUserId = $advisee->owner_user_id;
        }

        if ($ownerUserId) {
            OwnerContext::set((int) $ownerUserId);
        }

        return $next($request);
    }
}
