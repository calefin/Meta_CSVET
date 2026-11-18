<?php

/**
 * Marca d'água dos e-books vendidos (plugins/generic/csvetEditorial/Watermark.php).
 *
 * Uso (no servidor, na raiz do OMP):
 *   php scripts/watermark.php --test=livro.pdf      # carimba com dados fictícios: confira o arquivo
 *   php scripts/watermark.php --test=livro.epub     #   gerado antes de pôr a obra à venda
 *   php scripts/watermark.php --verify=A1B2-C3D4-E5 # de quem é o exemplar com esse código
 *
 * --test grava <arquivo>-marcado.pdf/.epub ao lado do original. Se o PDF falhar com
 * "compression technique which is not supported", exporte o PDF-matriz em PDF/A-1b
 * (Word: Salvar como PDF › Opções › Compatível com ISO 19005-1 (PDF/A); LaTeX: pdfx com
 * \pdfobjcompresslevel=0 e \pdfminorversion=4) e teste de novo.
 */

$root = dirname(__DIR__);
chdir($root);

$opts = ['test' => null, 'verify' => null];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--(test|verify)=(.+)$/', $arg, $m)) {
        $opts[$m[1]] = $m[2];
    } else {
        fwrite(STDERR, "Opção desconhecida: {$arg}\n");
        exit(1);
    }
}
if (!$opts['test'] && !$opts['verify']) {
    fwrite(STDERR, "Use --test=arquivo.pdf|.epub ou --verify=CÓDIGO\n");
    exit(1);
}

require $root . '/tools/bootstrap.php';

use APP\facades\Repo;
use APP\payment\omp\OMPPaymentManager;
use APP\plugins\generic\csvetEditorial\Watermark;
use Illuminate\Support\Facades\DB;

if ($opts['test']) {
    $source = realpath($opts['test']);
    if (!$source || !is_file($source)) {
        fwrite(STDERR, "Arquivo não encontrado: {$opts['test']}\n");
        exit(1);
    }
    $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
    $target = preg_replace('/\.(pdf|epub)$/i', "-marcado.{$extension}", $source);
    $license = ['name' => 'Leitora de Teste', 'email' => 'teste@exemplo.com.br', 'order' => 0, 'date' => date('d/m/Y'), 'code' => 'TEST-0000-00'];
    try {
        $extension === 'pdf' ? Watermark::stampPdf($source, $target, $license) : Watermark::stampEpub($source, $target, $license);
    } catch (\Throwable $e) {
        fwrite(STDERR, "✗ Não foi possível marcar: {$e->getMessage()}\n");
        exit(1);
    }
    echo "✓ Arquivo marcado: {$target}\n  Abra e confira o rodapé (PDF) ou a página de créditos (EPUB), e se os links continuam funcionando.\n";
    exit(0);
}

$wanted = strtoupper(trim($opts['verify']));
$purchases = DB::table('completed_payments')
    ->where('payment_type', OMPPaymentManager::PAYMENT_TYPE_PURCHASE_FILE)
    ->orderBy('completed_payment_id')
    ->get();
foreach ($purchases as $payment) {
    if (Watermark::code((int) $payment->user_id, (int) $payment->assoc_id, (int) $payment->completed_payment_id) !== $wanted) {
        continue;
    }
    $user = Repo::user()->get((int) $payment->user_id, true);
    $file = Repo::submissionFile()->get((int) $payment->assoc_id);
    $submission = $file ? Repo::submission()->get($file->getData('submissionId')) : null;
    echo "✓ Código {$wanted}\n";
    echo '  comprador: ' . ($user ? "{$user->getFullName()} <{$user->getEmail()}> (usuário {$user->getUsername()})" : "usuário {$payment->user_id} (removido)") . "\n";
    echo "  pedido nº {$payment->completed_payment_id} em {$payment->timestamp}, {$payment->amount} {$payment->currency_code_alpha}\n";
    echo '  livro: ' . ($submission ? $submission->getCurrentPublication()->getLocalizedFullTitle() : '(não encontrado)') . ' — arquivo ' . ($file ? $file->getLocalizedData('name') : $payment->assoc_id) . "\n";
    exit(0);
}
echo "✗ Nenhuma venda com o código {$wanted}. Confira a digitação (formato XXXX-XXXX-XX).\n";
exit(1);
