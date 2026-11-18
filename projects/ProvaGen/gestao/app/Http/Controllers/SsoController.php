<?php

namespace App\Http\Controllers;

use App\Models\Owner;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Entrada única do professor no módulo de gestão — não existe login por
 * senha aqui (Owner mapeia direto pra `users` do ProvaGen, hash é PBKDF2,
 * incompatível com bcrypt). O prof-aid (Node) assina um token de curta
 * duração em server/_core/gestaoSso.ts e manda pra cá via GET /api/gestao/sso.
 */
class SsoController extends Controller
{
    public function callback(Request $request)
    {
        $token = $request->query('token');

        if (! $token) {
            abort(400, 'Token ausente.');
        }

        $secret = config('services.gestao_sso.secret');
        if (! $secret) {
            Log::error('[SSO] GESTAO_SSO_SECRET não configurado.');
            abort(500, 'SSO não configurado.');
        }

        try {
            $payload = JWT::decode($token, new Key($secret, 'HS256'));
        } catch (ExpiredException $e) {
            abort(419, 'Link de acesso expirado — volte ao prof-aid e tente de novo.');
        } catch (\Exception $e) {
            Log::warning('[SSO] Token inválido: ' . $e->getMessage());
            abort(403, 'Token de acesso inválido.');
        }

        $ownerId = (int) ($payload->sub ?? 0);
        $owner = Owner::find($ownerId);

        if (! $owner) {
            abort(403, 'Conta não encontrada.');
        }

        if (! $owner->hasGestaoAccess()) {
            abort(403, 'Sua assinatura não inclui o módulo de gestão de alunos.');
        }

        Auth::guard('admin')->login($owner);
        $request->session()->regenerate();

        return redirect('/admin');
    }
}
