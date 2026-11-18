<?php

/**
 * Coloca os autores de capítulo convidados (scripts/invite.php) dentro de uma coletânea.
 * Para cada linha da planilha cujo convite já foi aceito (a pessoa tem conta e o papel
 * "Autor de capítulo"):
 *   1. inclui a pessoa como participante do livro (vê as discussões e entrega o capítulo por elas);
 *   2. inclui a pessoa como autora na lista de contribuidores da obra;
 *   3. cria o capítulo (título da coluna "capitulo"), se ainda não existir, e o vincula à pessoa.
 * Quem ainda não aceitou aparece como pendente: rode o script de novo depois.
 *
 * Uso (no servidor, na raiz do OMP):
 *   php scripts/assign-chapter-authors.php ~/convites/livro-123.csv --submission=123            # simula
 *   php scripts/assign-chapter-authors.php ~/convites/livro-123.csv --submission=123 --apply    # grava
 *   php scripts/assign-chapter-authors.php ~/convites/livro-123.csv --submission=123 --apply --notify
 *        --notify envia a cada autor recém-incluído um e-mail com o livro, o capítulo e o prazo
 *
 * O número da submissão aparece no painel (Submissões) e na URL do fluxo de trabalho.
 * Mesma planilha de scripts/invite.php: colunas nome, sobrenome, email, funcao, capitulo,
 * prazo, livro, organizador (só as linhas com funcao "Autor de capítulo" são usadas).
 * Idempotente: pode rodar várias vezes; nada é duplicado.
 */

$root = dirname(__DIR__);
chdir($root);

$opts = ['apply' => false, 'notify' => false, 'submission' => null, 'press' => 'editora'];
$csvPath = null;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--apply' || $arg === '--notify') {
        $opts[substr($arg, 2)] = true;
    } elseif (preg_match('/^--(submission|press)=(.+)$/', $arg, $m)) {
        $opts[$m[1]] = $m[2];
    } elseif ($arg[0] !== '-') {
        $csvPath = $arg;
    } else {
        fwrite(STDERR, "Opção desconhecida: {$arg}\n");
        exit(1);
    }
}
if (!$csvPath || !is_file($csvPath) || !ctype_digit((string) $opts['submission'])) {
    fwrite(STDERR, "Uso: php scripts/assign-chapter-authors.php planilha.csv --submission=ID [--apply] [--notify]\n");
    exit(1);
}

require $root . '/tools/bootstrap.php';

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PKP\db\DAORegistry;

function out(string $msg): void
{
    echo $msg, "\n";
}
function fail(string $msg): never
{
    fwrite(STDERR, "ERRO: {$msg}\n");
    exit(1);
}
function normalize(string $s): string
{
    $s = mb_strtolower(trim($s));
    $s = strtr($s, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c']);
    return preg_replace('/[^a-z0-9]+/', '_', $s);
}

// Na linha de comando não há roteador nem editora "atual": textos localizados e URLs dependem deles
$router = new \APP\core\PageRouter();
$router->setApplication(Application::get());
Application::get()->getRequest()->setRouter($router);

$context = Application::getContextDAO()->getByPath($opts['press']) ?: fail("editora '{$opts['press']}' não encontrada");
$contextId = $context->getId();
$submission = Repo::submission()->get((int) $opts['submission']);
if (!$submission || $submission->getData('contextId') != $contextId) {
    fail("submissão {$opts['submission']} não encontrada na editora {$opts['press']}");
}
$publication = $submission->getCurrentPublication();
$locale = $publication->getData('locale') ?: $context->getPrimaryLocale();
$bookTitle = strip_tags($publication->getLocalizedFullTitle($locale));

$chapterAuthorGroupId = DB::table('user_groups as g')
    ->join('user_group_settings as s', 's.user_group_id', '=', 'g.user_group_id')
    ->where('g.context_id', $contextId)->where('g.role_id', 65536)
    ->where('s.setting_name', 'name')->where('s.locale', 'en')->where('s.setting_value', 'Chapter Author')
    ->value('g.user_group_id') ?: fail('papel "Chapter Author" não encontrado na editora');

$canSeeSubmission = DB::table('user_group_stage')->where('user_group_id', $chapterAuthorGroupId)->where('stage_id', 1)->exists();
if (!$canSeeSubmission) {
    out('  ! O papel Autor de capítulo ainda não participa da etapa de submissão. Rode antes: php scripts/configure-press.php');
}

out("==> Livro #{$submission->getId()}: {$bookTitle}" . ($opts['apply'] ? '' : '  [SIMULAÇÃO: use --apply para gravar]'));
if ($submission->getData('workType') != $submission::WORK_TYPE_EDITED_VOLUME) {
    out('  ! A submissão não está marcada como obra organizada (coletânea). Ajuste em Publicação › Metadados se for o caso.');
}

// ---------------------------------------------------------------- planilha
$content = preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($csvPath));
$lines = preg_split('/\r\n|\r|\n/', trim($content));
$delimiter = substr_count($lines[0], ';') >= substr_count($lines[0], ',') ? ';' : ',';
$header = array_map('normalize', str_getcsv(array_shift($lines), $delimiter));
foreach (['nome', 'sobrenome', 'email', 'capitulo'] as $required) {
    in_array($required, $header, true) || fail("a planilha não tem a coluna '{$required}'");
}

$chapterDao = DAORegistry::getDAO('ChapterDAO');
$chapters = $chapterDao->getByPublicationId($publication->getId())->toArray();
$authors = Repo::author()->getCollector()->filterByPublicationIds([$publication->getId()])->getMany()->all();
$counts = ['incluído' => 0, 'já incluído' => 0, 'pendente' => 0, 'erro' => 0];

foreach ($lines as $n => $line) {
    if (trim($line, $delimiter . " \t") === '') {
        continue;
    }
    $row = array_combine($header, array_pad(array_map('trim', str_getcsv($line, $delimiter)), count($header), ''));
    if (isset($row['funcao']) && $row['funcao'] !== '' && normalize($row['funcao']) !== 'autor_de_capitulo') {
        continue;
    }
    $label = sprintf('linha %d: %s %s <%s>', $n + 2, $row['nome'], $row['sobrenome'], $row['email']);
    $chapterTitle = $row['capitulo'];

    $user = Repo::user()->getByEmail($row['email'], true);
    $hasRole = $user && DB::table('user_user_groups')->where('user_id', $user->getId())->where('user_group_id', $chapterAuthorGroupId)->exists();
    if (!$user || !$hasRole) {
        out("  … {$label}: ainda não aceitou o convite" . ($user ? ' (tem conta, mas sem o papel Autor de capítulo)' : ''));
        $counts['pendente']++;
        continue;
    }

    $changes = [];

    // 1. Participante do livro
    $assigned = DB::table('stage_assignments')->where('submission_id', $submission->getId())
        ->where('user_id', $user->getId())->where('user_group_id', $chapterAuthorGroupId)->exists();
    if (!$assigned) {
        $changes[] = 'participante';
        $opts['apply'] && Repo::stageAssignment()->build($submission->getId(), $chapterAuthorGroupId, $user->getId(), false, false);
    }

    // 2. Contribuidor (autor) da obra, casado pelo e-mail
    $author = null;
    foreach ($authors as $a) {
        if (strcasecmp((string) $a->getData('email'), $row['email']) === 0) {
            $author = $a;
            break;
        }
    }
    if (!$author) {
        $changes[] = 'contribuidor';
        if ($opts['apply']) {
            $author = Repo::author()->newDataObject([
                'publicationId' => $publication->getId(),
                'givenName' => $user->getData('givenName') ?: [$locale => $row['nome']],
                'familyName' => $user->getData('familyName') ?: [$locale => $row['sobrenome']],
                'email' => $row['email'],
                'country' => $user->getData('country') ?: (($row['pais'] ?? '') ?: 'BR'),
                'orcid' => $user->getData('orcid') ?: null,
                'userGroupId' => $chapterAuthorGroupId,
                'includeInBrowse' => true,
                'seq' => count($authors),
            ]);
            $author->setId(Repo::author()->add($author));
            $authors[] = $author;
        }
    }

    // 3. Capítulo, casado pelo título
    $chapter = null;
    foreach ($chapters as $c) {
        if (mb_strtolower(trim(strip_tags($c->getLocalizedTitle()))) === mb_strtolower(trim($chapterTitle))) {
            $chapter = $c;
            break;
        }
    }
    if (!$chapter) {
        $changes[] = 'capítulo';
        if ($opts['apply']) {
            $chapter = $chapterDao->newDataObject();
            $chapter->setData('publicationId', $publication->getId());
            $chapter->setTitle($chapterTitle, $locale);
            $chapter->setSequence(count($chapters));
            $chapterDao->insertChapter($chapter);
            $chapters[] = $chapter;
        }
    }
    if ($chapter && $author && $author->getId()) {
        $linked = DB::table('submission_chapter_authors')->where('chapter_id', $chapter->getId())->where('author_id', $author->getId())->exists();
        if (!$linked) {
            $changes[] = 'autoria do capítulo';
            $position = DB::table('submission_chapter_authors')->where('chapter_id', $chapter->getId())->count();
            $opts['apply'] && Repo::author()->addToChapter($author->getId(), $chapter->getId(), $position === 0, $position);
        }
    } elseif (!$opts['apply']) {
        $changes[] = 'autoria do capítulo';
    }

    if (!$changes) {
        out("  - {$label}: já está no livro (capítulo \"{$chapterTitle}\")");
        $counts['já incluído']++;
        continue;
    }
    out(($opts['apply'] ? '  ✓ ' : '  ○ ') . "{$label}: " . implode(', ', $changes) . " — \"{$chapterTitle}\"");
    $counts['incluído']++;

    if ($opts['apply'] && $opts['notify'] && in_array('participante', $changes, true)) {
        $workflowUrl = rtrim(\PKP\config\Config::getVar('general', 'base_url'), '/') . "/{$opts['press']}/dashboard/mySubmissions";
        $body = "<p>Prezado(a) {$row['nome']},</p>"
            . '<p>Você foi incluído(a) no livro <strong>' . htmlspecialchars($bookTitle) . '</strong>, da Editora CSVET, como autor(a) do capítulo <strong>' . htmlspecialchars($chapterTitle) . '</strong>.</p>'
            . (($row['prazo'] ?? '') !== '' ? '<p><strong>Prazo para a entrega do texto completo: ' . htmlspecialchars($row['prazo']) . '.</strong></p>' : '')
            . "<p>Para entregar o capítulo, entre na plataforma, abra o livro em <a href=\"{$workflowUrl}\">Minhas submissões</a> e use a área de <strong>Discussões</strong> para enviar o arquivo (modelo de capítulo) e o Termo de Autor de Capítulo assinado. O(a) organizador(a) e a editora recebem a mensagem.</p>"
            . '<p>Modelos e instruções: https://books.csvet.com.br/editora/about/submissions</p><p>Atenciosamente,<br>Editora CSVET</p>';
        try {
            Mail::html($body, function ($message) use ($row, $context, $bookTitle) {
                $message->to($row['email'], trim($row['nome'] . ' ' . $row['sobrenome']))
                    ->from($context->getData('contactEmail'), $context->getLocalizedName())
                    ->subject('Seu capítulo no livro "' . $bookTitle . '" (Editora CSVET)');
            });
            out('      e-mail de aviso enviado');
        } catch (\Throwable $e) {
            out('      ! e-mail não enviado: ' . $e->getMessage());
        }
    }
}

out(sprintf('==> %d incluído(s), %d já incluído(s), %d pendente(s).', $counts['incluído'], $counts['já incluído'], $counts['pendente']));
if (!$opts['apply'] && $counts['incluído']) {
    out('    Nada foi gravado. Rode de novo com --apply.');
}
