<?php

/**
 * Compras por Pix (pagamento manual): o OMP só avisa a editora por e-mail; a liberação do
 * download é feita aqui, depois de conferir o comprovante no extrato.
 *
 * Uso (no servidor, na raiz do OMP):
 *   php scripts/payments.php                    # lista as compras pendentes
 *   php scripts/payments.php --approve=12       # confirma o Pix da compra 12, libera o download e avisa o comprador
 *   php scripts/payments.php --reject=12        # descarta a compra 12 (Pix não recebido)
 *   php scripts/payments.php --paid             # lista as últimas vendas confirmadas
 */

$root = dirname(__DIR__);
chdir($root);

$opts = ['approve' => null, 'reject' => null, 'paid' => false, 'press' => 'editora'];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--(approve|reject|press)=(.+)$/', $arg, $m)) {
        $opts[$m[1]] = $m[2];
    } elseif ($arg === '--paid') {
        $opts['paid'] = true;
    } else {
        fwrite(STDERR, "Opção desconhecida: {$arg}\n");
        exit(1);
    }
}

require $root . '/tools/bootstrap.php';

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PKP\db\DAORegistry;

$router = new \APP\core\PageRouter();
$router->setApplication(Application::get());
Application::get()->getRequest()->setRouter($router);

$context = Application::getContextDAO()->getByPath($opts['press']);
if (!$context) {
    fwrite(STDERR, "Editora '{$opts['press']}' não encontrada.\n");
    exit(1);
}
// Carrega os plugins genéricos: o e-mail ao comprador sai no layout da editora (csvetEditorial)
\PKP\plugins\PluginRegistry::loadCategory('generic', true, $context->getId());
\PKP\plugins\PluginRegistry::loadCategory('paymethod', true, $context->getId());

$queuedPaymentDao = DAORegistry::getDAO('QueuedPaymentDAO'); /** @var \PKP\payment\QueuedPaymentDAO $queuedPaymentDao */
$paymentManager = Application::get()->getPaymentManager($context);
$baseUrl = rtrim(\PKP\config\Config::getVar('general', 'base_url'), '/');

/** Descrição da compra: livro, arquivo, comprador e valor */
$describe = function ($payment) use ($baseUrl, $opts): array {
    $file = Repo::submissionFile()->get((int) $payment->getAssocId());
    $submission = $file ? Repo::submission()->get($file->getData('submissionId')) : null;
    $user = Repo::user()->get((int) $payment->getUserId(), true);
    return [
        'book' => $submission ? strip_tags($submission->getCurrentPublication()->getLocalizedFullTitle()) : '(livro removido)',
        'file' => $file ? $file->getLocalizedData('name') : '(arquivo removido)',
        'user' => $user,
        'who' => $user ? $user->getFullName() . ' <' . $user->getEmail() . '>' : '(usuário removido)',
        'amount' => number_format((float) $payment->getAmount(), 2, ',', '.') . ' ' . $payment->getCurrencyCode(),
        'url' => $submission ? "{$baseUrl}/{$opts['press']}/catalog/book/" . $submission->getBestId() : $baseUrl,
    ];
};

if ($opts['paid']) {
    $rows = DB::table('completed_payments')->where('context_id', $context->getId())->orderByDesc('completed_payment_id')->limit(30)->get();
    echo "==> Últimas vendas confirmadas\n";
    foreach ($rows as $row) {
        $file = Repo::submissionFile()->get((int) $row->assoc_id);
        $user = Repo::user()->get((int) $row->user_id, true);
        printf("  #%d  %s  %s %s  %s  %s\n", $row->completed_payment_id, $row->timestamp, number_format((float) $row->amount, 2, ',', '.'),
            $row->currency_code_alpha, $user ? $user->getEmail() : '?', $file ? $file->getLocalizedData('name') : '?');
    }
    exit(0);
}

if ($id = $opts['approve'] ?? $opts['reject']) {
    $payment = $queuedPaymentDao->getById((int) $id);
    if (!$payment || (int) $payment->getContextId() !== (int) $context->getId()) {
        fwrite(STDERR, "Compra pendente #{$id} não encontrada (já aprovada ou descartada?). Rode sem opções para listar.\n");
        exit(1);
    }
    $info = $describe($payment);
    if ($opts['reject']) {
        $queuedPaymentDao->deleteById((int) $id);
        echo "  ✗ Compra #{$id} descartada: {$info['book']} – {$info['who']}\n";
        exit(0);
    }
    $paymentManager->fulfillQueuedPayment(Application::get()->getRequest(), $payment, 'ManualPayment');
    echo "  ✓ Compra #{$id} confirmada: {$info['book']} ({$info['file']}), {$info['amount']} – {$info['who']}\n";
    if ($info['user']) {
        $name = htmlspecialchars($info['user']->getGivenName($context->getPrimaryLocale()) ?: $info['user']->getFullName());
        $book = htmlspecialchars($info['book']);
        $body = "<p>Olá, {$name}!</p><p>Recebemos o seu Pix. A compra de <strong>{$book}</strong> está confirmada e o download já está liberado na sua conta.</p>"
            . "<p>Para baixar, entre na plataforma e abra a página do livro: <a href=\"{$info['url']}\">{$info['url']}</a></p>"
            . '<p>O arquivo é para uso pessoal: a reprodução, o compartilhamento e a venda sem autorização violam a Lei nº 9.610/1998.</p>'
            . '<p>Boa leitura!<br>Editora CSVET</p>';
        try {
            Mail::html($body, function ($message) use ($info, $context, $book) {
                $message->to($info['user']->getEmail(), $info['user']->getFullName())
                    ->from($context->getData('contactEmail'), $context->getLocalizedName())
                    ->subject('Compra confirmada: ' . html_entity_decode($book));
            });
            echo "    e-mail de confirmação enviado ao comprador\n";
        } catch (\Throwable $e) {
            echo '    ! e-mail não enviado: ' . $e->getMessage() . "\n";
        }
    }
    exit(0);
}

$rows = DB::table('queued_payments')->orderBy('queued_payment_id')->get();
echo "==> Compras pendentes (confira o Pix no extrato antes de aprovar)\n";
$count = 0;
foreach ($rows as $row) {
    $payment = $queuedPaymentDao->getById((int) $row->queued_payment_id);
    if (!$payment || (int) $payment->getContextId() !== (int) $context->getId()) {
        continue;
    }
    $info = $describe($payment);
    printf("  #%d  %s  %s  %s  –  %s\n", $row->queued_payment_id, $row->date_created, $info['amount'], $info['book'], $info['who']);
    $count++;
}
echo $count ? "Aprovar: php scripts/payments.php --approve=ID   |   Descartar: --reject=ID\n" : "  (nenhuma)\n";
