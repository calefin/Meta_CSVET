<?php

/**
 * @file plugins/generic/csvetScreening/Screening.php
 *
 * @class Screening
 *
 * @brief Triagem de uma submissão: arquivos obrigatórios do tipo de artigo (rules.php),
 *  conferências automáticas do manuscrito (.docx/.odt: nomes dos autores no texto e nas
 *  propriedades, texto do modelo esquecido, palavras) e do formulário (autores, ORCID,
 *  resumo, palavras-chave, declaração). Gera os dados e o HTML (e-mail e página).
 */

namespace APP\plugins\generic\csvetScreening;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use PKP\config\Config;
use PKP\context\Context;
use PKP\db\DAORegistry;
use PKP\submission\GenreDAO;
use PKP\submissionFile\SubmissionFile;

class Screening
{
    public const OK = 'ok';
    public const MISSING = 'missing';
    public const WARN = 'warn';
    public const INFO = 'info';

    private array $genres = [];

    public function __construct(private array $rules)
    {
    }

    public function strings(string $locale): array
    {
        return $this->rules[$locale] ?? $this->rules['en'];
    }

    public function sectionAbbrev(Submission $submission, Context $context): ?string
    {
        $sectionId = $submission->getCurrentPublication()?->getData('sectionId');
        $section = $sectionId ? Repo::section()->get($sectionId, $context->getId()) : null;
        if (!$section) {
            return null;
        }
        return strtoupper((string) ($section->getData('abbrev', 'en') ?: $section->getLocalizedData('abbrev')));
    }

    /** Arquivos exigidos na seção: [chave => Genre], só os tipos que existem na revista */
    public function requiredFor(?string $abbrev, Context $context): array
    {
        $required = [];
        foreach ($this->rules['files'] as $key => $rule) {
            $applies = $rule['sections'] === '*' || ($abbrev && in_array($abbrev, $rule['sections'], true));
            // Tipo ainda não criado (configure-journal.php não rodou): não trava o envio
            if ($applies && ($genre = $this->genreByKey($rule['genre'], $context))) {
                $required[$key] = $genre;
            }
        }
        return $required;
    }

    /** Arquivos da etapa de submissão */
    public function files(Submission $submission): array
    {
        return Repo::submissionFile()->getCollector()
            ->filterBySubmissionIds([$submission->getId()])
            ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_SUBMISSION])
            ->getMany()
            ->all();
    }

    /** Arquivos obrigatórios ausentes: [chave => Genre] */
    public function missing(Submission $submission, Context $context): array
    {
        $present = array_map(fn (SubmissionFile $f) => (int) $f->getData('genreId'), $this->files($submission));
        return array_filter(
            $this->requiredFor($this->sectionAbbrev($submission, $context), $context),
            fn ($genre) => !in_array((int) $genre->getId(), $present, true)
        );
    }

    /** Relatório completo (dados para o e-mail, a página e o painel) */
    public function report(Submission $submission, Context $context, string $locale): array
    {
        $s = $this->strings($locale);
        $publication = $submission->getCurrentPublication();
        $abbrev = $this->sectionAbbrev($submission, $context);
        $section = $publication->getData('sectionId') ? Repo::section()->get($publication->getData('sectionId'), $context->getId()) : null;
        $sectionTitle = $section ? ($section->getData('title', $locale) ?: $section->getLocalizedTitle()) : '—';
        $files = $this->files($submission);
        $authors = [];
        foreach ($publication->getData('authors') ?? [] as $author) {
            $authors[] = $author;
        }

        // ------------------------------------------------ arquivos obrigatórios
        $required = [];
        $usedFileIds = [];
        foreach ($this->requiredFor($abbrev, $context) as $key => $genre) {
            $matching = array_filter($files, fn (SubmissionFile $f) => (int) $f->getData('genreId') === (int) $genre->getId());
            foreach ($matching as $f) {
                $usedFileIds[] = $f->getId();
            }
            $required[] = [
                'status' => $matching ? self::OK : self::MISSING,
                'label' => $s['files'][$key] ?? $key,
                'detail' => $matching
                    ? implode(' · ', array_map(fn ($f) => $this->fileName($f, $locale), $matching))
                    : $this->fmt($s['chosenAs'], ['genre' => $this->genreName($genre, $locale)]),
            ];
        }

        // ------------------------------------------------ demais arquivos
        $others = [];
        $checks = [];
        foreach ($files as $f) {
            if (in_array($f->getId(), $usedFileIds, true)) {
                continue;
            }
            $genre = $this->genreById((int) $f->getData('genreId'), $context);
            $others[] = $this->fileName($f, $locale) . ' — ' . ($genre ? $this->genreName($genre, $locale) : '?');
            if ($genre && $genre->getKey() === 'OTHER') {
                // Imagem como "Outro" é quase sempre figura (antes não havia o tipo Figura no envio)
                $checks[] = $this->isImage($f, $locale)
                    ? [self::INFO, $this->fmt($s['figureAsOther'], ['file' => $this->fileName($f, $locale)])]
                    : [self::WARN, $this->fmt($s['otherAsOther'], ['file' => $this->fileName($f, $locale)])];
            }
        }

        // ------------------------------------------------ manuscrito
        $names = $this->authorNames($authors);
        $manuscripts = array_filter($files, function (SubmissionFile $f) use ($context) {
            $genre = $this->genreById((int) $f->getData('genreId'), $context);
            return $genre && $genre->getKey() === 'SUBMISSION';
        });
        $reviewerFiles = array_filter($files, function (SubmissionFile $f) use ($context) {
            $genre = $this->genreById((int) $f->getData('genreId'), $context);
            return !$genre || !in_array($genre->getKey(), $this->rules['privateGenres'], true);
        });
        // Nome de autor no nome dos arquivos que vão para os revisores
        foreach ($reviewerFiles as $f) {
            if ($this->findNames($this->fileName($f, $locale), $names)) {
                $checks[] = [self::WARN, $this->fmt($s['nameInFile'], ['file' => $this->fileName($f, $locale)])];
            }
        }
        $extensions = array_map(fn ($f) => strtolower(pathinfo($this->fileName($f, $locale), PATHINFO_EXTENSION)), $manuscripts);
        if ($manuscripts && !array_diff($extensions, ['pdf'])) {
            $checks[] = [self::WARN, $this->fmt($s['pdfOnly'], ['file' => $this->fileName(reset($manuscripts), $locale)])];
        }
        foreach ($manuscripts as $f) {
            $doc = $this->readDocument($f);
            if ($doc === null) {
                if (in_array(strtolower(pathinfo($this->fileName($f, $locale), PATHINFO_EXTENSION)), ['docx', 'odt'], true)) {
                    $checks[] = [self::WARN, $s['noZip']];
                }
                continue;
            }
            [$text, $props] = $doc;
            $body = $this->mainText($text);
            if ($hits = $this->findNames($body, $names)) {
                $checks[] = [self::WARN, $this->fmt($s['nameInText'], ['names' => implode(', ', $hits)])];
            }
            foreach ($props as $value) {
                if ($value !== '' && ($this->findNames($value, $names) || !preg_match('/^(user|usuário|usuario|author|autor|microsoft office user|admin|nvas)$/iu', $value))) {
                    $checks[] = [self::WARN, $this->fmt($s['nameInProps'], ['value' => $value])];
                    break;
                }
            }
            foreach ($this->rules['templateLeftovers'] as $needle) {
                if (mb_stripos($text, $needle) !== false) {
                    $checks[] = [self::WARN, $this->fmt($s['leftovers'], ['text' => $needle])];
                    break;
                }
            }
            if ($abbrev && isset($this->rules['wordLimits'][$abbrev])) {
                $count = preg_match_all('/[\p{L}\p{N}]+/u', $body);
                $limit = $this->rules['wordLimits'][$abbrev];
                $checks[] = [$count > $limit * 1.4 ? self::WARN : self::INFO, $this->fmt($s['wordCount'], [
                    'count' => number_format($count, 0, ',', '.'),
                    'limit' => number_format($limit, 0, ',', '.'),
                ])];
            }
        }

        // ------------------------------------------------ formulário
        $primary = null;
        foreach ($authors as $author) {
            if ((int) $author->getId() === (int) $publication->getData('primaryContactId')) {
                $primary = $author;
            }
        }
        $checks[] = [self::INFO, $this->fmt($s['authors'], ['count' => count($authors), 'name' => $primary?->getFullName() ?? '—'])];

        // ------------------------------------------------ conflito com o corpo editorial
        foreach ($this->conflictChecks($submission, $context, $authors, $s) as $check) {
            $checks[] = $check;
        }
        if (count($authors) < 2) {
            $checks[] = [self::WARN, $s['oneAuthor']];
        }
        if ($primary && !$primary->getData('orcid')) {
            $checks[] = [self::WARN, $s['noOrcid']];
        }
        $abstract = array_filter((array) $publication->getData('abstract'), fn ($v) => trim(strip_tags((string) $v)) !== '');
        if (!$abstract && !($section && $section->getData('abstractsNotRequired'))) {
            $checks[] = [self::WARN, $s['noAbstract']];
        }
        $keywords = max(array_map(fn ($list) => is_array($list) ? count($list) : 0, (array) $publication->getData('keywords')) ?: [0]);
        $checks[] = [$keywords ? self::INFO : self::WARN, $this->fmt($s['keywords'], ['count' => $keywords])];
        $checks[] = $this->hasDeclaration($submission) ? [self::OK, $s['declarationOk']] : [self::WARN, $s['declarationMissing']];

        // ------------------------------------------------ conclusão
        $missing = count(array_filter($required, fn ($r) => $r['status'] === self::MISSING));
        $warnings = count(array_filter($checks, fn ($c) => $c[0] === self::WARN));
        $verdict = $missing ? 'return' : ($warnings ? 'check' : 'proceed');

        $request = Application::get()->getRequest();
        $dispatcher = Application::get()->getDispatcher();
        $submitter = $submission->getData('submitterUserId') ? Repo::user()->get($submission->getData('submitterUserId'), true) : null;

        return [
            'id' => $submission->getId(),
            'title' => trim((string) $publication->getLocalizedFullTitle()),
            'section' => $sectionTitle . ($abbrev ? " ({$abbrev})" : ''),
            'submitter' => $submitter ? $submitter->getFullName() . ' <' . $submitter->getEmail() . '>' : ($primary?->getFullName() ?? '—'),
            'dateSubmitted' => (string) $submission->getData('dateSubmitted'),
            'required' => $required,
            'others' => $others,
            'checks' => array_map(fn ($c) => ['status' => $c[0], 'text' => $c[1]], $checks),
            'missing' => $missing,
            'warnings' => $warnings,
            'verdict' => $verdict,
            'verdictText' => $s[['proceed' => 'verdictProceed', 'check' => 'verdictCheck', 'return' => 'verdictReturn'][$verdict]],
            'manual' => $s['manual'],
            'reviewFiles' => $s['reviewFiles'],
            'returnHelp' => $missing ? $s['returnHelp'] : '',
            'urls' => [
                'workflow' => $dispatcher->url($request, Application::ROUTE_PAGE, $context->getPath(), 'dashboard', 'editorial', null, ['workflowSubmissionId' => $submission->getId()]),
                'screening' => $dispatcher->url($request, Application::ROUTE_PAGE, $context->getPath(), 'screening', 'view', [$submission->getId()]),
                'guidelines' => $s['urls']['guidelines'],
                'templates' => $s['urls']['templates'],
            ],
        ];
    }

    /** Texto em minúsculas e sem acentos, para comparar filiações */
    private function fold(string $text): string
    {
        $text = mb_strtolower($text);
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        return $ascii !== false ? $ascii : $text;
    }

    /** Todas as filiações do autor, em todos os idiomas, numa string só */
    private function affiliationText($author): string
    {
        $parts = [];
        foreach ($author->getAffiliations() as $affiliation) {
            foreach ((array) $affiliation->getName() as $name) {
                $parts[] = (string) $name;
            }
            // Filiação escolhida na lista do ROR: o nome vem do registro do ROR
            foreach ((array) $affiliation->getAffiliationName(null, ['en', 'pt_BR']) as $name) {
                $parts[] = (string) $name;
            }
        }
        return $this->fold(implode(' | ', $parts));
    }

    /** Instituição do corpo editorial a que o autor pertence (nome) ou null */
    private function boardInstitution($author): ?string
    {
        $text = $this->affiliationText($author);
        foreach ($this->rules['boardInstitutions'] ?? [] as $institution) {
            foreach ($institution['match'] as $needle) {
                if ($text !== '' && str_contains($text, $this->fold($needle))) {
                    return $institution['name'];
                }
            }
        }
        return null;
    }

    /**
     * Autor do corpo editorial (gestor/editor de seção, por e-mail ou ORCID), autores da
     * instituição do corpo editorial e a proporção dessa instituição nos últimos 12 meses
     */
    private function conflictChecks(Submission $submission, Context $context, array $authors, array $s): array
    {
        $checks = [];
        $board = Repo::user()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByRoleIds([\PKP\security\Role::ROLE_ID_MANAGER, \PKP\security\Role::ROLE_ID_SUB_EDITOR])
            ->getMany();
        $orcid = fn ($o) => preg_replace('/[^0-9X]/', '', strtoupper((string) $o->getData('orcid')));
        $boardNames = [];
        foreach ($authors as $author) {
            foreach ($board as $user) {
                $sameEmail = $author->getEmail() && strtolower(trim($author->getEmail())) === strtolower(trim((string) $user->getEmail()));
                $sameOrcid = $orcid($author) !== '' && $orcid($author) === $orcid($user);
                if ($sameEmail || $sameOrcid) {
                    $boardNames[] = $author->getFullName();
                    break;
                }
            }
        }
        if ($boardNames) {
            $checks[] = [self::WARN, $this->fmt($s['boardAuthor'], ['names' => implode(', ', $boardNames)])];
        }

        $byInstitution = [];
        foreach ($authors as $author) {
            if ($institution = $this->boardInstitution($author)) {
                $byInstitution[$institution][] = $author->getFullName();
            }
        }
        foreach ($byInstitution as $institution => $names) {
            $checks[] = [self::WARN, $this->fmt($s['sameInstitution'], ['institution' => $institution, 'names' => implode(', ', $names)])];
        }

        // Proporção nos últimos 12 meses (submissões concluídas)
        $since = date('Y-m-d H:i:s', strtotime('-12 months'));
        $total = 0;
        $counts = [];
        foreach (Repo::submission()->getCollector()->filterByContextIds([$context->getId()])->getMany() as $other) {
            if ($other->getData('submissionProgress') !== '' || !$other->getData('dateSubmitted') || $other->getData('dateSubmitted') < $since) {
                continue;
            }
            $total++;
            $found = [];
            foreach ($other->getCurrentPublication()?->getData('authors') ?? [] as $author) {
                if ($institution = $this->boardInstitution($author)) {
                    $found[$institution] = true;
                }
            }
            foreach (array_keys($found) as $institution) {
                $counts[$institution] = ($counts[$institution] ?? 0) + 1;
            }
        }
        foreach ($this->rules['boardInstitutions'] ?? [] as $institution) {
            $count = $counts[$institution['name']] ?? 0;
            if ($total) {
                $percent = (int) round(100 * $count / $total);
                $checks[] = [$percent > 34 ? self::WARN : self::INFO, $this->fmt($s['institutionShare'], [
                    'count' => $count, 'total' => $total, 'percent' => $percent, 'institution' => $institution['name'],
                ])];
            }
        }
        return $checks;
    }

    /** HTML com estilos em linha (serve para e-mail e para a página) */
    public function html(array $r, string $locale, bool $withLinks = true): string
    {
        $s = $this->strings($locale);
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $colors = ['return' => ['#fdecea', '#b42318'], 'check' => ['#fff4e0', '#8a5a00'], 'proceed' => ['#e8f5ec', '#1e6b3a']];
        $icon = [self::OK => '✔', self::MISSING => '✘', self::WARN => '!', self::INFO => '·', 'todo' => '☐'];
        $iconColor = [self::OK => '#1e6b3a', self::MISSING => '#b42318', self::WARN => '#8a5a00', self::INFO => '#5a6080', 'todo' => '#5a6080'];
        [$bg, $fg] = $colors[$r['verdict']];

        $row = fn (string $status, string $html) => '<tr><td style="width:22px;vertical-align:top;padding:4px 6px 4px 0;font-weight:bold;color:' . $iconColor[$status] . ';">' . $icon[$status] . '</td><td style="padding:4px 0;vertical-align:top;">' . $html . '</td></tr>';

        $out = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#1a1a2e;">';
        $out .= '<p style="margin:0 0 4px;font-size:13px;color:#5a6080;">#' . $e($r['id']) . ' · ' . $e($r['section']) . ' · ' . $e(substr($r['dateSubmitted'], 0, 16)) . '</p>';
        $out .= '<p style="margin:0 0 12px;font-size:16px;font-weight:bold;">' . $e($r['title']) . '</p>';
        $out .= '<p style="margin:0 0 12px;font-size:13px;">' . $e($s['mailSubmitter']) . ': ' . $e($r['submitter']) . '</p>';
        $out .= '<div style="background:' . $bg . ';color:' . $fg . ';border-left:4px solid ' . $fg . ';padding:10px 12px;margin:0 0 16px;font-weight:bold;">' . $e($r['verdictText']) . '</div>';

        $out .= '<p style="margin:0 0 4px;font-weight:bold;">' . $e($this->fmt($s['required'], ['section' => $r['section']])) . '</p><table role="presentation" style="border-collapse:collapse;margin:0 0 12px;">';
        foreach ($r['required'] as $item) {
            $label = '<strong>' . $e($item['label']) . '</strong>: ' . ($item['status'] === self::OK ? $e($s['present']) . ' — ' : '<strong style="color:#b42318;">' . $e($s['missing']) . '</strong> — ') . $e($item['detail']);
            $out .= $row($item['status'], $label);
        }
        $out .= '</table>';

        if ($r['others']) {
            $out .= '<p style="margin:0 0 4px;font-weight:bold;">' . $e($s['otherFiles']) . '</p><table role="presentation" style="border-collapse:collapse;margin:0 0 12px;">';
            foreach ($r['others'] as $other) {
                $out .= $row(self::INFO, $e($other));
            }
            $out .= '</table>';
        }

        $out .= '<p style="margin:0 0 4px;font-weight:bold;">' . $e($s['title']) . '</p><table role="presentation" style="border-collapse:collapse;margin:0 0 12px;">';
        foreach ($r['checks'] as $check) {
            $out .= $row($check['status'], $e($check['text']));
        }
        $out .= '</table>';

        $out .= '<p style="margin:0 0 4px;font-weight:bold;">' . $e($s['manualTitle']) . '</p><table role="presentation" style="border-collapse:collapse;margin:0 0 8px;">';
        foreach ($r['manual'] as $item) {
            $out .= $row('todo', $e($item));
        }
        $out .= '</table><p style="margin:0 0 12px;font-size:13px;color:#5a6080;">' . $e($r['reviewFiles']) . '</p>';
        if ($r['returnHelp']) {
            $out .= '<p style="margin:0 0 12px;font-size:13px;">' . $e($r['returnHelp']) . '</p>';
        }
        if ($withLinks) {
            $btn = 'display:inline-block;padding:9px 16px;margin:4px 8px 4px 0;border-radius:4px;text-decoration:none;font-weight:bold;';
            $out .= '<p style="margin:8px 0 0;"><a href="' . $e($r['urls']['workflow']) . '" style="' . $btn . 'background:#000080;color:#ffffff;">' . $e($s['openWorkflow']) . '</a>'
                . '<a href="' . $e($r['urls']['screening']) . '" style="' . $btn . 'background:#eef0f8;color:#000080;">' . $e($s['openScreening']) . '</a></p>';
        }
        return $out . '</div>';
    }

    // ------------------------------------------------------------------ apoio

    public function fmt(string $text, array $vars): string
    {
        foreach ($vars as $k => $v) {
            $text = str_replace('{$' . $k . '}', (string) $v, $text);
        }
        return $text;
    }

    public function genreByKey(string $key, Context $context)
    {
        $id = "key:{$key}";
        if (!array_key_exists($id, $this->genres)) {
            /** @var GenreDAO $genreDao */
            $genreDao = DAORegistry::getDAO('GenreDAO');
            $genre = $genreDao->getByKey($key, $context->getId());
            $this->genres[$id] = $genre && $genre->getEnabled() ? $genre : null;
        }
        return $this->genres[$id];
    }

    public function genreById(int $genreId, Context $context)
    {
        if (!array_key_exists($genreId, $this->genres)) {
            /** @var GenreDAO $genreDao */
            $genreDao = DAORegistry::getDAO('GenreDAO');
            $this->genres[$genreId] = $genreId ? $genreDao->getById($genreId, $context->getId()) : null;
        }
        return $this->genres[$genreId];
    }

    public function genreName($genre, string $locale): string
    {
        return (string) ($genre->getName($locale) ?: $genre->getLocalizedName());
    }

    private function isImage(SubmissionFile $file, string $locale): bool
    {
        return str_starts_with((string) $file->getData('mimetype'), 'image/')
            || (bool) preg_match('/\.(tiff?|png|jpe?g|gif|bmp|svg|eps|webp)$/i', $this->fileName($file, $locale));
    }

    private function fileName(SubmissionFile $file, string $locale): string
    {
        $name = $file->getData('name');
        if (is_array($name)) {
            $name = $name[$locale] ?? (array_values(array_filter($name))[0] ?? '');
        }
        return (string) $name;
    }

    /** Nomes e sobrenomes dos autores com 4 letras ou mais (evita "da", "de", "Lee"...) */
    private function authorNames(array $authors): array
    {
        $names = [];
        foreach ($authors as $author) {
            foreach (['givenName', 'familyName'] as $field) {
                foreach ((array) $author->getData($field) as $value) {
                    foreach (preg_split('/[\s\-]+/u', (string) $value) as $part) {
                        if (mb_strlen($part) >= 4) {
                            $names[mb_strtolower($part)] = $part;
                        }
                    }
                }
            }
        }
        return $names;
    }

    private function findNames(string $text, array $names): array
    {
        $hits = [];
        foreach ($names as $lower => $name) {
            if (preg_match('/(?<![\p{L}])' . preg_quote($lower, '/') . '(?![\p{L}])/iu', $text)) {
                $hits[] = $name;
            }
        }
        return array_values(array_unique($hits));
    }

    /** Texto antes das referências (autocitação na lista de referências não conta) */
    private function mainText(string $text): string
    {
        if (preg_match_all('/^\s*(references|referências|referencias|bibliografia)\s*$/imu', $text, $m, PREG_OFFSET_CAPTURE)) {
            $last = end($m[0]);
            return substr($text, 0, $last[1]);
        }
        return $text;
    }

    /** [texto, [autor, último a salvar]] de um .docx ou .odt; null se não der para ler */
    private function readDocument(SubmissionFile $file): ?array
    {
        if (!class_exists(\ZipArchive::class)) {
            return null;
        }
        $stored = app()->get('file')->get($file->getData('fileId'));
        $path = $stored ? rtrim((string) Config::getVar('files', 'files_dir'), '/') . '/' . $stored->path : null;
        if (!$path || !is_file($path)) {
            return null;
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return null;
        }
        $isOdt = $zip->locateName('content.xml') !== false;
        $xml = $zip->getFromName($isOdt ? 'content.xml' : 'word/document.xml');
        $meta = (string) $zip->getFromName($isOdt ? 'meta.xml' : 'docProps/core.xml');
        $zip->close();
        if ($xml === false) {
            return null;
        }
        $xml = preg_replace('#</(w:p|text:p|text:h)>#', "\n", $xml);
        $xml = preg_replace('#<(w:tab|w:br|text:tab|text:line-break)\b[^>]*/>#', ' ', $xml);
        $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');

        $props = [];
        foreach (['dc:creator', 'cp:lastModifiedBy', 'meta:initial-creator'] as $tag) {
            if (preg_match('#<' . $tag . '[^>]*>([^<]*)</' . $tag . '>#u', $meta, $m)) {
                $props[] = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_XML1, 'UTF-8'));
            }
        }
        return [$text, array_values(array_unique($props))];
    }

    private function hasDeclaration(Submission $submission): bool
    {
        $entries = Repo::eventLog()->getCollector()
            ->filterByAssoc(Application::ASSOC_TYPE_SUBMISSION, [$submission->getId()])
            ->getMany();
        foreach ($entries as $entry) {
            $message = (string) $entry->getData('message');
            foreach ($this->rules['declarationLog'] as $prefix) {
                if (str_starts_with($message, $prefix)) {
                    return true;
                }
            }
        }
        return false;
    }
}
