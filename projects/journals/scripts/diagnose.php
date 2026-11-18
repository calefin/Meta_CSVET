<?php

/**
 * Diagnóstico de e-mail, fila de tarefas e dados do perfil (ROR/ORCID) do OJS.
 *
 * Uso (no servidor, na raiz do OJS):
 *   php scripts/diagnose.php                         # só lê e mostra
 *   php scripts/diagnose.php --mail=voce@gmail.com   # também envia um e-mail de teste (direto no SMTP)
 *   php scripts/diagnose.php --mail-ojs=voce@gmail.com   # envia pelo OJS, como os convites, e mostra a conversa com o SMTP
 *   php scripts/diagnose.php --mail-variants=voce@gmail.com   # 8 variações; mostra qual o filtro da HostGator descarta
 *   php scripts/diagnose.php --run-jobs              # processa a fila de e-mails pendentes agora
 *   php scripts/diagnose.php --update-ror            # baixa/atualiza a base de instituições (ROR)
 *
 * Nada é alterado sem uma das opções acima. Senhas não são exibidas.
 */

$root = dirname(__DIR__);
chdir($root);

$opts = ['mail' => null, 'mail-ojs' => null, 'mail-variants' => null, 'run-jobs' => false, 'update-ror' => false];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--(mail|mail-ojs|mail-variants)=(.+)$/', $arg, $m)) {
        $opts[$m[1]] = $m[2];
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

use APP\core\Application;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use PKP\config\Config;
use PKP\plugins\Hook;
use PKP\plugins\PluginRegistry;

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

$context = DB::table('journals')->orderBy('journal_id')->first();
$contactEmail = $context ? DB::table('journal_settings')->where('journal_id', $context->journal_id)->where('setting_name', 'contactEmail')->value('setting_value') : null;
line('E-mail de contato da revista', $contactEmail ?: '(vazio)');

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
    $subject = 'Teste de e-mail do CSVET Journals';
    $body = "Teste de envio do OJS em " . date('Y-m-d H:i:s') . ".\r\nSe chegou, o e-mail do site esta funcionando.\r\n" . Config::getVar('general', 'base_url');

    if (Config::getVar('email', 'default') === 'smtp') {
        // Envia pelo mesmo servidor SMTP do config.inc.php (o caminho real dos e-mails do OJS)
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
            $msg = "From: CSVET Journals (teste) <{$from}>\r\nTo: <{$opts['mail']}>\r\nSubject: {$subject}\r\nDate: " . date('r') .
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
                $message->to($opts['mail'])->from($from, 'CSVET Journals (teste)')->subject($subject);
            });
            echo "  ✓ Enviado sem erro pelo OJS (remetente {$from}). Confira a caixa de entrada E o spam.\n";
        } catch (\Throwable $e) {
            echo '  ✗ Falhou: ' . get_class($e) . ': ' . $e->getMessage() . "\n";
        }
    }
}

if ($opts['mail-ojs']) {
    // Mesmo caminho dos convites e e-mails editoriais: Mailable do OJS, layout do csvetEmail,
    // remetente reescrito (DMARC) e cópia oculta. O --mail acima fala direto com o SMTP e
    // não prova que o OJS consegue enviar.
    section("E-mail de teste PELO OJS para {$opts['mail-ojs']}");
    $journal = Application::getContextDAO()->getById((int) $context->journal_id);
    // No terminal não há router e partes do OJS (rodapé, variáveis do e-mail) o pedem
    $request = Application::get()->getRequest();
    if (!$request->getRouter()) {
        $router = new \APP\core\PageRouter();
        $router->setApplication(Application::get());
        $request->setRouter($router);
    }
    PluginRegistry::loadCategory('generic', true, $journal->getId());
    $sent = null;
    Event::listen(MessageSent::class, function (MessageSent $e) use (&$sent) {
        $sent = $e->sent;
    });
    // O OJS só anota no error_log quando o SMTP recusa; desvia o log para mostrar aqui
    $logFile = tempnam(sys_get_temp_dir(), 'ojsmail');
    $oldLog = ini_set('error_log', $logFile);
    try {
        $mailable = new \PKP\mail\Mailable([$journal]);
        $mailable->to($opts['mail-ojs'])
            ->from($journal->getData('contactEmail'), $journal->getLocalizedName())
            ->subject('[NVAS] Teste de e-mail pelo OJS ' . date('H:i:s'))
            ->body('<p>Teste de envio pelo OJS em ' . date('Y-m-d H:i:s') . ', no mesmo caminho dos convites e das decisões.</p>'
                . '<p><a href="' . Config::getVar('general', 'base_url') . '/nvas">Neotropical Veterinary and Animal Science</a></p>');
        Mail::send($mailable);
    } catch (\Throwable $e) {
        echo '  ✗ Erro do OJS: ' . get_class($e) . ': ' . $e->getMessage() . "\n";
    }
    ini_set('error_log', $oldLog === false ? '' : $oldLog);
    $logged = trim((string) @file_get_contents($logFile));
    @unlink($logFile);
    if ($sent) {
        $message = $sent->getOriginalMessage();
        $list = fn (array $a) => implode(', ', array_map(fn ($x) => $x->toString(), $a)) ?: '-';
        echo "  ✓ Aceito pelo servidor SMTP.\n";
        line('From', $list($message->getFrom()));
        line('Reply-To', $list($message->getReplyTo()));
        line('To', $list($message->getTo()));
        line('Bcc (cópia oculta)', $list($message->getBcc()));
        line('Envelope (remetente)', $sent->getEnvelope()->getSender()->toString());
        line('Envelope (destinatários)', $list($sent->getEnvelope()->getRecipients()));
        line('Tamanho', number_format(strlen($sent->toString()) / 1024, 1, ',', '.') . ' KB');
        echo "  Conversa com o servidor (procure o id do 250 em cPanel › Rastrear entrega):\n";
        // Nada da autenticação (usuário e senha vão em base64): do primeiro 334 até o 235/535
        // só mostra as respostas do servidor que não sejam 334
        $inAuth = false;
        foreach (preg_split('/\R/', trim($sent->getDebug())) as $l) {
            if (preg_match('/ < 334 /', $l) || preg_match('/ > AUTH/i', $l)) {
                $inAuth = true;
                continue;
            }
            if ($inAuth && preg_match('/ < (235|535) /', $l)) {
                $inAuth = false;
            } elseif ($inAuth) {
                continue;
            }
            if ($l !== '') {
                echo '    ' . mb_substr($l, 0, 200) . "\n";
            }
        }
    } else {
        echo "  ✗ O OJS não conseguiu entregar a mensagem ao servidor SMTP.\n";
    }
    if ($logged !== '') {
        echo "  Registrado pelo OJS durante o envio:\n";
        foreach (array_slice(preg_split('/\R/', $logged), 0, 20) as $l) {
            echo '    ' . mb_substr($l, 0, 300) . "\n";
        }
    }
}

if ($opts['mail-variants']) {
    // A HostGator descarta em silêncio (router "fightspamHG", entregue a /dev/null) e-mails do
    // OJS que o teste em texto puro passa. Envia variações da mesma mensagem direto no SMTP
    // e consulta o rastreamento do cPanel para saber qual característica dispara o filtro.
    $to = $opts['mail-variants'];
    section("Variações de e-mail para {$to} (qual delas o filtro da HostGator descarta)");
    // O PHP da hospedagem não mostra erro fatal no terminal; aqui ele precisa aparecer
    @ini_set('display_errors', '1');
    $journal = Application::getContextDAO()->getById((int) $context->journal_id);
    $request = Application::get()->getRequest();
    if (!$request->getRouter()) {
        $router = new \APP\core\PageRouter();
        $router->setApplication(Application::get());
        $request->setRouter($router);
    }
    PluginRegistry::loadCategory('generic', true, $journal->getId());

    $noreply = Config::getVar('email', 'default_envelope_sender');
    $plain = new \Symfony\Component\Mime\Address($noreply, 'CSVET Journals');
    $via = new \Symfony\Component\Mime\Address($noreply, $journal->getLocalizedName() . ' via CSVET Journals');
    $replyTo = [new \Symfony\Component\Mime\Address($journal->getData('contactEmail'), $journal->getLocalizedName())];
    $transport = Mail::mailer()->getSymfonyTransport();
    $ids = [];
    $send = function (string $name, callable $build) use ($to, $noreply, $transport, &$ids) {
        $email = (new \Symfony\Component\Mime\Email())->to($to)->subject('[NVAS] ' . $name . ' ' . date('H:i:s'));
        $build($email);
        $email->sender($noreply);
        try {
            $sent = $transport->send($email);
            $ids[$name] = preg_match('/id=(\S+)/', (string) $sent->getDebug(), $m) ? $m[1] : '?';
        } catch (\Throwable $e) {
            $ids[$name] = 'erro: ' . mb_substr($e->getMessage(), 0, 120);
        }
        echo "  enviado: {$name} -> {$ids[$name]}\n";
        sleep(2);
    };

    // Primeiro as variações que não dependem do OJS
    $send('V1 texto puro', fn ($m) => $m->from($plain)->text('Teste V1 em ' . date('H:i:s')));
    $send('V2 HTML simples', fn ($m) => $m->from($plain)->html('<p>Teste V2 em ' . date('H:i:s') . '</p>'));
    $send('V6 texto + nome "via" no remetente', fn ($m) => $m->from($via)->text('Teste V6 em ' . date('H:i:s')));
    $send('V7 texto + responder para', fn ($m) => $m->from($plain)->replyTo(...$replyTo)->text('Teste V7 em ' . date('H:i:s')));

    // Depois o layout real: monta um e-mail do OJS e o captura antes de sair
    $captured = null;
    try {
        Hook::add('Email::send::before', function ($hookName, $args) use (&$captured) {
            $captured = clone $args['message'];
            throw new \RuntimeException('csvet-capture');
        }, Hook::SEQUENCE_LAST);
        $mailable = new \PKP\mail\Mailable([$journal]);
        $mailable->to($to)
            ->from($journal->getData('contactEmail'), $journal->getLocalizedName())
            ->subject('x')
            ->body('<p>Prezado(a),</p><p>Teste de envio da revista em ' . date('Y-m-d H:i:s') . '.</p>'
                . '<p><a href="' . Config::getVar('general', 'base_url') . '/nvas">Neotropical Veterinary and Animal Science</a></p>');
        Mail::send($mailable);
    } catch (\Throwable $e) {
        if (!$captured) {
            echo '  ✗ Não consegui montar o e-mail do OJS: ' . get_class($e) . ': ' . mb_substr($e->getMessage(), 0, 300) . "\n";
        }
    }
    if ($captured) {
        $layout = (string) $captured->getHtmlBody();
        $noLinks = preg_replace('#<a\b[^>]*>(.*?)</a>#is', '$1', $layout);
        $noImg = preg_replace('#<img\b[^>]*>#i', '', $layout);
        $send('V3 layout da revista', fn ($m) => $m->from($plain)->html($layout));
        $send('V4 layout sem links', fn ($m) => $m->from($plain)->html($noLinks));
        $send('V5 layout sem imagem', fn ($m) => $m->from($plain)->html($noImg));
        $send('V8 como o OJS envia (tudo junto)', fn ($m) => $m->from($via)->replyTo(...$replyTo)->html($layout));
    }

    // Mesmas mensagens com a conexão apresentada pelo nome do servidor (correção do csvetEmail)
    $host = \APP\plugins\generic\csvetEmail\CsvetEmailPlugin::serverHostname();
    if ($host && $transport instanceof \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport) {
        $transport->stop();
        $transport->setLocalDomain($host);
        $send("V9 texto puro, EHLO {$host}", fn ($m) => $m->from($plain)->text('Teste V9 em ' . date('H:i:s')));
        if ($captured) {
            $send('V10 como o OJS envia, EHLO do servidor', fn ($m) => $m->from($via)->replyTo(...$replyTo)->html($layout));
        }
    } else {
        echo "  ! gethostname() não deu um nome com domínio: " . gethostname() . "\n";
    }

    echo "  Aguardando o rastreamento do cPanel...\n";
    sleep(10);
    $track = json_decode((string) shell_exec('cpapi2 --output=json EmailTrack search success=1 defer=1 failure=1 inprogress=1 max_results_by_type=100 2>/dev/null'), true);
    $byId = [];
    foreach ($track['cpanelresult']['data'] ?? [] as $r) {
        if (($r['recipient'] ?? '') === $to) {
            $byId[$r['msgid']] = $r['type'] . ' (' . $r['router'] . ')';
        }
    }
    foreach ($ids as $name => $id) {
        printf("  %-44s %-28s %s\n", $name, $id, $byId[$id] ?? (str_starts_with($id, 'erro') ? '' : 'sem registro ainda'));
    }
    echo "  success = saiu para o destinatário; filtered (fightspamHG) = descartado pela HostGator.\n";
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
        line('Resultado', $ok ? 'ok' : 'falhou (veja o log do OJS)');
    } catch (\Throwable $e) {
        echo '  ✗ ' . get_class($e) . ': ' . $e->getMessage() . "\n";
    }
    line('Instituições agora', number_format(DB::table('rors')->count(), 0, ',', '.'));
}

section('ORCID');
foreach (DB::table('journals')->get() as $journal) {
    $get = fn ($name) => DB::table('journal_settings')->where('journal_id', $journal->journal_id)->where('setting_name', $name)->value('setting_value');
    line("Revista {$journal->path}: integração ativa", $get('orcidEnabled') ? 'sim' : 'não');
    line("Revista {$journal->path}: tipo de API", $get('orcidApiType') ?: '(não definido)');
    line("Revista {$journal->path}: Client ID", $get('orcidClientId') ? 'definido' : '(vazio)');
}

section('Sessão e banco (login caindo, "não autorizado", "erro inesperado")');
line('session_check_ip', Config::getVar('security', 'session_check_ip') ? 'On (desloga quando o IP muda: rode configure-production.sh)' : 'Off');
$container = (string) @file_get_contents($root . '/lib/pkp/classes/core/PKPContainer.php');
line('Patch do erro 1615 (patch-ojs.sh)', str_contains($container, 'csvet: emulated prepares') ? 'aplicado' : 'FALTA: rode bash scripts/patch-ojs.sh');
line('Sessões abertas no banco', DB::table('sessions')->count());

section('Convites recentes (papel na revista; PENDING = e-mail aceito pelo servidor SMTP)');
foreach (DB::table('invitations')->orderByDesc('invitation_id')->limit(8)->get() as $inv) {
    printf("  #%-4d %-11s %-19s %-24s %s\n", $inv->invitation_id, $inv->status, $inv->created_at ?? '', $inv->type,
        $inv->email ?: DB::table('users')->where('user_id', $inv->user_id)->value('email') ?: '(sem e-mail)');
}
echo "  INITIALIZED = criado mas não enviado (veja os erros abaixo). PENDING sem chegar = spam ou filtro do destinatário.\n";

section('Erros recentes no log do PHP (error_log da pasta do site)');
$log = $root . '/error_log';
if (!is_readable($log)) {
    echo "  (sem error_log na pasta do site)\n";
} else {
    $tail = array_slice(file($log, FILE_IGNORE_NEW_LINES) ?: [], -3000);
    // Só a linha de cada erro, sem o rastreamento (#0, #1...) que vem depois
    // Avisos (Warning/Notice/Deprecated) do OJS são ruído e escondiam as falhas de envio
    $hits = array_values(array_filter($tail, fn ($l) => preg_match('/^\[/', $l)
        && !preg_match('/PHP (Warning|Notice|Deprecated)|NotFoundHttpException/', $l)
        && preg_match('/1615|re-prepared|SQLSTATE|Transport|SMTP|csvet|Mail|Invitation|Fatal|response code|Connection could not|authenticat|rejected|relay|sandbox/i', $l)));
    line('Linhas relevantes (últimas 3000)', count($hits));
    foreach (array_slice($hits, -15) as $l) {
        echo '    ' . mb_substr($l, 0, 300) . "\n";
    }
}

section('PHP');
line('Versão', PHP_VERSION);
line('memory_limit', ini_get('memory_limit'));
line('max_execution_time (web)', 'veja no MultiPHP INI Editor');
line('Extensões curl / openssl / zip', implode(' / ', array_map(fn ($e) => extension_loaded($e) ? 'ok' : 'FALTA', ['curl', 'openssl', 'zip'])));
echo "\n";
