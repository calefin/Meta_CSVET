<?php

/**
 * Ajuda alguém que não consegue entrar no site: mostra a situação da conta e,
 * se pedido, gera um link de redefinição de senha para mandar por WhatsApp/e-mail
 * pessoal (sem depender do e-mail do site chegar).
 *
 * Uso (no servidor, na raiz do OJS):
 *   php scripts/user-access.php --user=pessoa@exemplo.com                 # só mostra
 *   php scripts/user-access.php --user=pessoa@exemplo.com --reset-link    # + link de redefinição (vale 3 dias)
 *   php scripts/user-access.php --user=pessoa@exemplo.com --reset-link --days=7
 *   php scripts/user-access.php --user=pessoa@exemplo.com --temp-password   # último recurso: senha provisória
 *
 * --user aceita e-mail ou nome de usuário. Se não achar, procura e-mails parecidos.
 * O link de redefinição deixa de valer quando a pessoa entra no site ou troca a senha.
 * A senha provisória obriga a pessoa a escolher uma nova no primeiro acesso.
 * Nada é alterado sem --temp-password.
 */

$root = dirname(__DIR__);
chdir($root);

$opts = ['user' => null, 'reset-link' => false, 'temp-password' => false, 'days' => 3, 'journal' => 'nvas'];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--(user|days|journal)=(.+)$/', $arg, $m)) {
        $opts[$m[1]] = $m[2];
    } elseif (in_array($arg, ['--reset-link', '--temp-password'], true)) {
        $opts[substr($arg, 2)] = true;
    } else {
        fwrite(STDERR, "Opção desconhecida: {$arg}\n");
        exit(1);
    }
}
if (!$opts['user']) {
    fwrite(STDERR, "Informe --user=EMAIL_OU_USUARIO\n");
    exit(1);
}

require $root . '/tools/bootstrap.php';

use APP\facades\Repo;
use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\security\Validation;

function line(string $label, $value): void
{
    printf("  %-30s %s\n", $label, is_bool($value) ? ($value ? 'sim' : 'não') : (string) ($value ?? '(vazio)'));
}

$needle = trim($opts['user']);
$isEmail = str_contains($needle, '@');
$user = $isEmail ? Repo::user()->getByEmail($needle, true) : Repo::user()->getByUsername($needle, true);

$journal = DB::table('journals')->where('path', $opts['journal'])->first();
$journalId = $journal->journal_id ?? null;

// Convites pendentes para esse e-mail: um revisor novo só ganha conta quando aceita o convite.
$email = $user ? $user->getEmail() : ($isEmail ? $needle : null);
if ($email) {
    $invitations = DB::table('invitations')
        ->where(fn ($q) => $q->whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->when($user, fn ($q) => $q->orWhere('user_id', $user->getId())))
        ->orderByDesc('invitation_id')->limit(5)->get();
    echo "\n== Convites para {$email}\n";
    if ($invitations->isEmpty()) {
        echo "  (nenhum)\n";
    }
    foreach ($invitations as $inv) {
        $expired = $inv->expiry_date && strtotime($inv->expiry_date) < time() ? ' (EXPIRADO)' : '';
        printf("  #%d %-22s %-10s criado %s, expira %s%s\n", $inv->invitation_id, $inv->type, $inv->status,
            $inv->created_at ?? '?', $inv->expiry_date ?? '-', $expired);
    }
}

if (!$user) {
    echo "\n== Conta: NÃO ENCONTRADA para '{$needle}'\n";
    echo "  O 'Esqueci a senha' não envia nada para e-mail sem conta (e não avisa), por isso\n";
    echo "  a pessoa pede a recuperação e nada acontece.\n";
    $local = strtolower(explode('@', $needle)[0]);
    $similar = DB::table('users')
        ->where(fn ($q) => $q->where('email', 'like', "%{$local}%")->orWhere('username', 'like', "%{$local}%"))
        ->limit(10)->get(['user_id', 'username', 'email']);
    if ($similar->isNotEmpty()) {
        echo "\n  Contas parecidas (talvez ela use outro e-mail):\n";
        foreach ($similar as $s) {
            printf("    #%d  %-25s %s\n", $s->user_id, $s->username, $s->email);
        }
    }
    echo "\n  Se há convite PENDENTE, ela precisa abrir o link do convite e criar a conta por ele;\n";
    echo "  se expirou, reenvie o convite pelo painel da submissão.\n";
    exit(1);
}

echo "\n== Conta #{$user->getId()}\n";
try {
    $name = $user->getFullName();
} catch (Throwable) {
    $name = DB::table('user_settings')->where('user_id', $user->getId())->whereIn('setting_name', ['givenName', 'familyName'])
        ->pluck('setting_value')->unique()->implode(' ');
}
line('Nome', $name);
line('Usuário', $user->getUsername());
line('E-mail', $user->getEmail());
line('Desativada', $user->getDisabled() ? 'SIM — motivo: ' . ($user->getDisabledReason() ?: '(sem motivo)') : 'não');
line('Obrigada a trocar a senha', (bool) $user->getMustChangePassword());
line('E-mail validado em', $user->getDateValidated());
line('Cadastrada em', $user->getDateRegistered());
line('Último acesso', $user->getDateLastLogin());
line('Tem senha definida', (bool) $user->getPassword());
if (Config::getVar('email', 'require_validation') && !$user->getDateValidated()) {
    echo "  ! require_validation está ligado e o e-mail não foi validado: o login é recusado.\n";
}

if ($journalId) {
    $roles = DB::table('user_user_groups as uug')
        ->join('user_groups as ug', 'ug.user_group_id', '=', 'uug.user_group_id')
        ->leftJoin('user_group_settings as ugs', fn ($j) => $j->on('ugs.user_group_id', '=', 'ug.user_group_id')
            ->where('ugs.setting_name', 'name')->where('ugs.locale', 'pt_BR'))
        ->where('uug.user_id', $user->getId())->where('ug.context_id', $journalId)
        ->pluck('ugs.setting_value')->filter()->unique()->implode(', ');
    line("Papéis em {$opts['journal']}", $roles ?: '(nenhum)');

    $reviews = DB::table('review_assignments as ra')
        ->join('submissions as s', 's.submission_id', '=', 'ra.submission_id')
        ->where('ra.reviewer_id', $user->getId())->where('s.context_id', $journalId)
        ->orderByDesc('ra.review_id')->limit(5)
        ->get(['ra.submission_id', 'ra.date_assigned', 'ra.date_confirmed', 'ra.declined', 'ra.date_completed', 'ra.date_due']);
    foreach ($reviews as $r) {
        $state = $r->date_completed ? 'concluída' : ($r->declined ? 'recusada' : ($r->date_confirmed ? 'aceita' : 'aguardando resposta'));
        printf("  Avaliação da submissão #%d: %s (atribuída %s, prazo %s)\n", $r->submission_id, $state, $r->date_assigned, $r->date_due ?? '-');
    }
}

$baseUrl = rtrim(Config::getVar('general', 'base_url'), '/');
$prefix = $baseUrl . (Config::getVar('general', 'restful_urls') ? '' : '/index.php') . '/' . $opts['journal'];

if ($opts['reset-link']) {
    $expiry = time() + max(1, (int) $opts['days']) * 86400;
    $hash = Validation::generatePasswordResetHash($user->getId(), $expiry);
    echo "\n== Link de redefinição (vale até " . date('d/m/Y H:i', $expiry) . ")\n";
    echo '  ' . $prefix . '/login/resetPassword/' . rawurlencode($user->getUsername()) . '?confirm=' . rawurlencode($hash) . "\n";
    echo "  Deixa de valer se a pessoa entrar no site ou trocar a senha antes de usá-lo.\n";
}

if ($opts['temp-password']) {
    $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $temp = '';
    for ($i = 0; $i < 12; $i++) {
        $temp .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    DB::table('users')->where('user_id', $user->getId())->update([
        'password' => Validation::encryptCredentials($user->getUsername(), $temp),
        'must_change_password' => 1,
    ]);
    echo "\n== Senha provisória definida\n";
    line('Entrar em', $prefix . '/login');
    line('Usuário (ou o e-mail)', $user->getUsername());
    line('Senha provisória', $temp);
    echo "  No primeiro acesso o site pede uma senha nova. Mande a senha por um canal separado do usuário.\n";
}

if (!$opts['reset-link'] && !$opts['temp-password']) {
    echo "\nPróximo passo: rode de novo com --reset-link para gerar o link de redefinição.\n";
}
echo "\nDicas se a senha nova 'continua errada':\n";
echo "  - Use o e-mail exato acima (ou o usuário '{$user->getUsername()}') no campo de login.\n";
echo "  - Abra a página de login de novo antes de digitar (página aberta há muito tempo perde a\n";
echo "    sessão e o OJS responde 'senha incorreta'); teste numa janela anônima.\n";
echo "  - Desative o preenchimento automático do navegador: ele costuma colar a senha antiga.\n";
