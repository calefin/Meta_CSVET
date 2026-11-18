<?php

/**
 * Configura o envio de e-mails do OMP por SMTP autenticado (seção [email] do config.inc.php).
 * Sem SMTP, convites a editores e revisores tendem a cair em spam ou não chegar.
 *
 * Uso (no servidor, na raiz do OMP):
 *   php scripts/configure-email.php                         # remetente contato@csvet.com.br
 *   php scripts/configure-email.php contato@csvet.com.br --login=calefi@csvet.com.br
 *        remetente contato@ (alias) com login na conta real calefi@ (Google Workspace)
 *   php scripts/configure-email.php contato@csvet.com.br --server=mail.csvet.com.br --port=587 --auth=tls
 *
 * Google Workspace (detectado pelo MX): servidor smtp.gmail.com:587, senha de APP da conta de login.
 * Se o remetente for um alias, cadastre-o no Gmail da conta de login em
 * Configurações > Contas > Enviar e-mail como (senão o Google troca o remetente pela conta de login).
 *
 * A senha da conta de e-mail é pedida no terminal (não aparece na tela nem fica no histórico).
 * Antes de alterar, faz backup do config.inc.php em ~/backups.
 * Os dados do servidor SMTP estão no cPanel: E-mail > Contas de e-mail > Connect Devices.
 */

$root = dirname(__DIR__);
$cfg = "{$root}/config.inc.php";
if (!is_file($cfg)) {
    fwrite(STDERR, "config.inc.php não encontrado em {$root}\n");
    exit(1);
}

$account = 'contato@csvet.com.br';
$opts = ['server' => null, 'port' => '465', 'auth' => 'ssl', 'login' => null, 'name' => 'Editora CSVET'];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--(server|port|auth|login)=(.+)$/', $arg, $m)) {
        $opts[$m[1]] = $m[2];
    } elseif (filter_var($arg, FILTER_VALIDATE_EMAIL)) {
        $account = $arg;
    } else {
        fwrite(STDERR, "Argumento inválido: {$arg}\n");
        exit(1);
    }
}
if ($opts['server'] === null) {
    // E-mail do domínio no Google Workspace (MX do Google): envia pelo SMTP do Google com senha de app
    $domain = substr(strrchr($account, '@'), 1);
    $mx = implode(' ', array_column(@dns_get_record($domain, DNS_MX) ?: [], 'target'));
    if (preg_match('/google\.com|googlemail\.com/i', $mx)) {
        $opts = array_merge($opts, ['server' => 'smtp.gmail.com', 'port' => '587', 'auth' => 'tls']);
        echo "O domínio {$domain} usa Google Workspace: use uma SENHA DE APP do Google (myaccount.google.com/apppasswords).\n";
    } else {
        $opts['server'] = 'mail.' . $domain;
    }
}
if (!in_array($opts['auth'], ['ssl', 'tls'], true)) {
    fwrite(STDERR, "--auth deve ser ssl (porta 465) ou tls (porta 587)\n");
    exit(1);
}

$login = $opts['login'] ?: $account;
echo "Remetente: {$account}\nLogin SMTP: {$login}\nServidor: {$opts['server']}:{$opts['port']} ({$opts['auth']})\n";
echo ($opts['server'] === 'smtp.gmail.com' ? "Senha de app do Google da conta {$login}: " : "Senha da conta de e-mail {$login}: ");
shell_exec('stty -echo');
$password = rtrim((string) fgets(STDIN), "\r\n");
shell_exec('stty echo');
echo "\n";
if ($password === '') {
    fwrite(STDERR, "Senha vazia: nada foi alterado.\n");
    exit(1);
}

// Testa o login SMTP antes de gravar qualquer coisa
$transport = ($opts['auth'] === 'ssl' ? 'ssl://' : 'tcp://') . $opts['server'] . ':' . $opts['port'];
$fp = @stream_socket_client($transport, $errno, $errstr, 20);
if (!$fp) {
    fwrite(STDERR, "Não conectou em {$transport}: {$errstr}. Nada foi alterado.\n");
    exit(1);
}
$read = function () use ($fp) {
    $data = '';
    while (($line = fgets($fp, 515)) !== false) {
        $data .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    return $data;
};
$send = function (string $cmd) use ($fp, $read) {
    fwrite($fp, $cmd . "\r\n");
    return $read();
};
$read();
$send('EHLO ' . (gethostname() ?: 'localhost'));
if ($opts['auth'] === 'tls') {
    $send('STARTTLS');
    stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    $send('EHLO ' . (gethostname() ?: 'localhost'));
}
$send('AUTH LOGIN');
$send(base64_encode($login));
$authReply = $send(base64_encode($password));
$send('QUIT');
fclose($fp);
if (!str_starts_with($authReply, '235')) {
    fwrite(STDERR, 'O servidor recusou usuário/senha: ' . trim($authReply) . "\nNada foi alterado.\n");
    exit(1);
}
echo "Login SMTP ok.\n";

// Backup e alteração da seção [email]
$backupDir = getenv('HOME') . '/backups';
@mkdir($backupDir, 0700, true);
$backup = $backupDir . '/config.inc.php.' . date('YmdHis');
copy($cfg, $backup);
chmod($backup, 0600);

$values = [
    'default' => 'smtp',
    'smtp' => 'On',
    'smtp_server' => $opts['server'],
    'smtp_port' => $opts['port'],
    'smtp_auth' => $opts['auth'],
    'smtp_username' => $login,
    'smtp_password' => '"' . addcslashes($password, '"\\') . '"',
    'allow_envelope_sender' => 'On',
    'default_envelope_sender' => $account,
    'force_default_envelope_sender' => 'On',
    'force_dmarc_compliant_from' => 'On',
    'dmarc_compliant_from_displayname' => "'%n via %s'",
];

$lines = file($cfg);
$inEmail = false;
$done = [];
foreach ($lines as $i => $line) {
    if (preg_match('/^\s*\[(.+)\]\s*$/', $line, $m)) {
        if ($inEmail) {
            // Chaves que não existiam (nem comentadas): acrescenta no fim da seção
            $missing = array_diff_key($values, $done);
            if ($missing) {
                $extra = '';
                foreach ($missing as $k => $v) {
                    $extra .= "{$k} = {$v}\n";
                }
                $lines[$i] = $extra . "\n" . $line;
            }
        }
        $inEmail = trim($m[1]) === 'email';
        continue;
    }
    if (!$inEmail) {
        continue;
    }
    if (preg_match('/^\s*;?\s*([a-z_]+)\s*=/', $line, $m) && isset($values[$m[1]]) && !isset($done[$m[1]])) {
        $lines[$i] = "{$m[1]} = {$values[$m[1]]}\n";
        $done[$m[1]] = true;
    }
}
file_put_contents($cfg, implode('', $lines));
chmod($cfg, 0640);

echo "config.inc.php atualizado (backup em {$backup}).\n";
echo "Os e-mails saem como \"<nome do remetente> via {$opts['name']}\" <{$account}>, com resposta para o remetente.\n";
echo "Teste: faça um convite para um e-mail seu ou use \"Esqueci a senha\" no site.\n";
