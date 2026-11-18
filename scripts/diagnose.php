<?php

/**
 * Diagnóstico de e-mail, fila de tarefas e dados do perfil (ROR/ORCID) do OMP.
 *
 * Uso (no servidor, na raiz do OMP):
 *   php scripts/diagnose.php                         # só lê e mostra
 *   php scripts/diagnose.php --mail=voce@gmail.com   # também envia um e-mail de teste
 *   php scripts/diagnose.php --run-jobs              # processa a fila de e-mails pendentes agora
 *   php scripts/diagnose.php --update-ror            # baixa/atualiza a base de instituições (ROR)
 *
 * Nada é alterado sem uma das opções acima. Senhas não são exibidas.
 */

$root = dirname(__DIR__);
chdir($root);

$opts = ['mail' => null, 'run-jobs' => false, 'update-ror' => false];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--mail=(.+)$/', $arg, $m)) {
        $opts['mail'] = $m[1];
    } elseif ($arg === '--run-jobs') {
        $opts['run-jobs'] = true;
    } elseif ($arg === '--update-ror') {
        $opts['update-ror'] = true;
    } else {
        fwrite(STDERR, "Opção desconhecida: {$arg}\n");
        exit(1);
    }
}

require $root . '/tools/bootstrap.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PKP\config\Config;

function section(string $title): void
{
    echo "\n== {$title}\n";
}
function line(string $label, $value): void
{
    printf("  %-34s %s\n", $label, is_bool($value) ? ($value ? 'sim' : 'não') : (string) $value);
}

// ---------------------------------------------------------------- e-mail
section('Configuração de e-mail ([email] no config.inc.php)');
foreach (['default', 'smtp', 'smtp_server', 'smtp_port', 'smtp_auth', 'smtp_username', 'default_envelope_sender',
          'force_default_envelope_sender', 'force_dmarc_compliant_from', 'sendmail_path'] as $key) {
    $value = Config::getVar('email', $key);
    line($key, $value === null || $value === '' ? '(vazio)' : var_export($value, true));
}
line('smtp_password', Config::getVar('email', 'smtp_password') ? '(definida)' : '(vazia)');
$mailer = Config::getVar('email', 'default');
if ($mailer !== 'smtp') {
    echo "  ! O envio está em '{$mailer}', não em SMTP autenticado. Na hospedagem compartilhada isso costuma\n";
    echo "    falhar ou cair em spam. Rode: php scripts/configure-email.php\n";
}

$context = DB::table('presses')->orderBy('press_id')->first();
$contactEmail = $context ? DB::table('press_settings')->where('press_id', $context->press_id)->where('setting_name', 'contactEmail')->value('setting_value') : null;
line('E-mail de contato da editora', $contactEmail ?: '(vazio)');

section('Fila de tarefas (e-mails são enviados por ela)');
line('job_runner (roda a cada acesso)', Config::getVar('queues', 'job_runner') ? 'On' : 'Off');
line('Tarefas pendentes', DB::table('jobs')->count());
$failed = DB::table('failed_jobs')->count();
line('Tarefas com falha', $failed);
foreach (DB::table('failed_jobs')->orderByDesc('id')->limit(5)->get() as $job) {
    $payload = json_decode($job->payload, true);
    $name = $payload['displayName'] ?? '?';
    $error = strtok($job->exception, "\n");
    echo "    - {$job->failed_at}  {$name}\n      " . mb_substr($error, 0, 400) . "\n";
}
foreach (DB::table('jobs')->orderBy('id')->limit(5)->get() as $job) {
    $payload = json_decode($job->payload, true);
    echo '    · pendente desde ' . date('Y-m-d H:i', $job->created_at) . ': ' . ($payload['displayName'] ?? '?') . " (tentativas: {$job->attempts})\n";
}

if ($opts['run-jobs']) {
    section('Processando a fila agora');
    passthru(PHP_BINARY . ' ' . escapeshellarg($root . '/lib/pkp/tools/jobs.php') . ' work --stop-when-empty 2>&1', $code);
    line('Resultado', $code === 0 ? 'ok' : "código {$code}");
    line('Pendentes depois', DB::table('jobs')->count());
    line('Com falha depois', DB::table('failed_jobs')->count());
}

if ($opts['mail']) {
    section("E-mail de teste para {$opts['mail']}");
    $from = Config::getVar('email', 'default_envelope_sender') ?: ($contactEmail ?: 'no-reply@' . parse_url(Config::getVar('general', 'base_url'), PHP_URL_HOST));
    $subject = 'Teste de e-mail do CSVET Books';
    $body = "Teste de envio do OMP em " . date('Y-m-d H:i:s') . ".\r\nSe chegou, o e-mail do site esta funcionando.\r\n" . Config::getVar('general', 'base_url');

    if (Config::getVar('email', 'default') === 'smtp') {
        // Envia pelo mesmo servidor SMTP do config.inc.php (o caminho real dos e-mails do OMP)
        $auth = Config::getVar('email', 'smtp_auth');
        $server = Config::getVar('email', 'smtp_server');
        $port = Config::getVar('email', 'smtp_port');
        $fp = @stream_socket_client(($auth === 'ssl' ? 'ssl://' : 'tcp://') . "{$server}:{$port}", $errno, $errstr, 30);
        if (!$fp) {
            echo "  ✗ Não conectou em {$server}:{$port}: {$errstr}\n";
        } else {
            $read = function () use ($fp) {
                $data = '';
                while (($l = fgets($fp, 515)) !== false) {
                    $data .= $l;
                    if (isset($l[3]) && $l[3] === ' ') {
                        break;
                    }
                }
                return $data;
            };
            $cmd = function (string $c) use ($fp, $read) {
                fwrite($fp, $c . "\r\n");
                return $read();
            };
            $read();
            $cmd('EHLO ' . (gethostname() ?: 'localhost'));
            if ($auth === 'tls') {
                $cmd('STARTTLS');
                stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $cmd('EHLO ' . (gethostname() ?: 'localhost'));
            }
            $cmd('AUTH LOGIN');
            $cmd(base64_encode((string) Config::getVar('email', 'smtp_username')));
            $steps = ['login' => $cmd(base64_encode((string) Config::getVar('email', 'smtp_password')))];
            $steps['remetente'] = $cmd("MAIL FROM:<{$from}>");
            $steps['destinatario'] = $cmd("RCPT TO:<{$opts['mail']}>");
            $steps['dados'] = $cmd('DATA');
            $msg = "From: CSVET Books (teste) <{$from}>\r\nTo: <{$opts['mail']}>\r\nSubject: {$subject}\r\nDate: " . date('r') .
                "\r\nMessage-ID: <" . bin2hex(random_bytes(8)) . "@" . substr(strrchr($from, '@'), 1) . ">\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$body}\r\n.";
            $steps['envio'] = $cmd($msg);
            $cmd('QUIT');
            fclose($fp);
            $ok = str_starts_with($steps['login'], '235') && str_starts_with($steps['remetente'], '250') && str_starts_with($steps['destinatario'], '250') && str_starts_with($steps['envio'], '250');
            if ($ok) {
                echo "  ✓ Aceito pelo servidor SMTP {$server} (remetente {$from}): " . trim($steps['envio']) . "\n";
                echo "    Confira a caixa de entrada E o spam.\n";
            } else {
                foreach ($steps as $step => $reply) {
                    echo "  {$step}: " . trim($reply) . "\n";
                }
                echo "  ✗ O servidor não aceitou a mensagem (veja a etapa com código diferente de 2xx/3xx).\n";
            }
        }
    } else {
        try {
            Mail::raw($body, function ($message) use ($opts, $from, $subject) {
                $message->to($opts['mail'])->from($from, 'CSVET Books (teste)')->subject($subject);
            });
            echo "  ✓ Enviado sem erro pelo OMP (remetente {$from}). Confira a caixa de entrada E o spam.\n";
        } catch (\Throwable $e) {
            echo '  ✗ Falhou: ' . get_class($e) . ': ' . $e->getMessage() . "\n";
        }
    }
}

// ---------------------------------------------------------------- perfil
section('Afiliações (base ROR usada no campo de instituição do perfil)');
$rors = DB::table('rors')->count();
line('Instituições carregadas', number_format($rors, 0, ',', '.'));
if ($rors === 0) {
    echo "  ! Base vazia: a busca de instituição no perfil não encontra nada. Rode com --update-ror.\n";
}
if ($opts['update-ror']) {
    @set_time_limit(0);
    @ini_set('memory_limit', '1024M');
    echo "  Baixando e importando a base ROR (pode levar vários minutos)...\n";
    try {
        $ok = (new \PKP\task\UpdateRorRegistryDataset())->execute();
        line('Resultado', $ok ? 'ok' : 'falhou (veja o log do OMP)');
    } catch (\Throwable $e) {
        echo '  ✗ ' . get_class($e) . ': ' . $e->getMessage() . "\n";
    }
    line('Instituições agora', number_format(DB::table('rors')->count(), 0, ',', '.'));
}

section('ORCID');
foreach (DB::table('presses')->get() as $press) {
    $get = fn ($name) => DB::table('press_settings')->where('press_id', $press->press_id)->where('setting_name', $name)->value('setting_value');
    line("Editora {$press->path}: integração ativa", $get('orcidEnabled') ? 'sim' : 'não');
    line("Editora {$press->path}: tipo de API", $get('orcidApiType') ?: '(não definido)');
    line("Editora {$press->path}: Client ID", $get('orcidClientId') ? 'definido' : '(vazio)');
}

section('PHP');
line('Versão', PHP_VERSION);
line('memory_limit', ini_get('memory_limit'));
line('max_execution_time (web)', 'veja no MultiPHP INI Editor');
line('Extensões curl / openssl / zip', implode(' / ', array_map(fn ($e) => extension_loaded($e) ? 'ok' : 'FALTA', ['curl', 'openssl', 'zip'])));
echo "\n";
