<?php

namespace App\Http\Controllers;

use App\Models\SystematicReview;
use App\Models\SystematicReviewDecision;
use App\Models\SystematicReviewRecord;
use App\Models\SystematicReviewScreener;
use App\Services\SystematicReviewService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * Downloads da revisão sistemática: kit de importação, protocolo, planilha
 * da triagem, incluídos (RIS/CSV), relatório PDF e termos assinados.
 */
class SystematicReviewExportController extends Controller
{
    protected function admin()
    {
        if (!auth()->check()) {
            return redirect()->guest(\Filament\Facades\Filament::getPanel('admin')->getLoginUrl());
        }
        abort_unless(auth()->user()->role === 'admin', 403);

        return null;
    }

    public function kit()
    {
        if ($r = $this->admin()) {
            return $r;
        }
        $dir = base_path('scripts/revisao_sistematica');
        $tmp = tempnam(sys_get_temp_dir(), 'srkit');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach (['buscar.py', 'LEIAME.md', 'modelo_importacao.json', 'modelo_importacao.csv'] as $file) {
            $zip->addFile("{$dir}/{$file}", "kit_revisao_sistematica/{$file}");
        }
        $zip->close();

        return response()->download($tmp, 'kit_revisao_sistematica.zip')->deleteFileAfterSend();
    }

    public function export(SystematicReview $systematicReview, string $type, SystematicReviewService $service)
    {
        if ($r = $this->admin()) {
            return $r;
        }
        $stem = $systematicReview->baseFileStem();
        $px = $systematicReview->filePrefix();
        $protocol = app(\App\Services\SystematicReviewProtocolService::class);
        $extraction = app(\App\Services\SystematicReviewExtractionService::class);
        $today = now()->format('Y-m-d');
        $decisions = function (string $stage, string $tag) use ($systematicReview, $protocol, $px, $today) {
            // Etapa ainda aberta: o arquivo sai marcado como PARCIAL (não subir no OSF).
            $closed = $systematicReview->isStageClosed($stage);
            $date = $closed ? \Illuminate\Support\Carbon::parse(($systematicReview->stage_closed_at ?? [])[$stage])->format('Y-m-d') : $today;

            return $this->csvRaw("{$px}_TRIAGEM_{$tag}_decisoes_{$date}" . ($closed ? '' : '_PARCIAL') . '.csv',
                \App\Services\SystematicReviewProtocolService::csvString($protocol->decisionRows($systematicReview, $stage)));
        };

        return match ($type) {
            'pacote-osf' => response()->download($protocol->osfPackage($systematicReview), "{$px}_pacote_OSF_{$today}.zip")->deleteFileAfterSend(),
            'osf-ficha' => response($protocol->osfFormText($systematicReview), 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"OSF_Form_{$px}.txt\"",
            ]),
            'osf-json' => response($protocol->osfResponsesJson($systematicReview), 200, [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$px}_OSF_registration_responses.json\"",
            ]),
            'pipeline' => response($protocol->pipelineJson($systematicReview), 200, [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"pipeline_{$px}.json\"",
            ]),
            'extracao-travada', 'rob-travado' => $this->lockedFile($systematicReview, $type, $px),
            'extracao-duplicata' => $this->csvRaw("{$px}_EXTRACAO_duplicata_{$today}.csv", \App\Services\SystematicReviewProtocolService::csvString($extraction->duplicateRows($systematicReview))),
            'contatos-autores' => $this->csvRaw("{$px}_CONTATOS_autores.csv", \App\Services\SystematicReviewProtocolService::csvString($extraction->contactRows($systematicReview))),
            'grade' => $this->csvRaw("{$px}_GRADE_SoF.csv", \App\Services\SystematicReviewProtocolService::csvString($extraction->gradeRows($systematicReview))),
            'atas' => $this->csvRaw("{$px}_ATAS.csv", \App\Services\SystematicReviewProtocolService::csvString($extraction->meetingRows($systematicReview))),
            // Protocolo v1.1: o que a IA propôs × o que o Revisor 1 conferiu, e o prompt fixo.
            'ia-extracao' => $this->csvRaw("{$px}_IA_extracao.csv", \App\Services\SystematicReviewProtocolService::csvString($extraction->aiRows($systematicReview))),
            'ia-prompt' => response($systematicReview->ai_extraction['prompt'] ?? $extraction->aiPrompt($systematicReview), 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$px}_IA_prompt_extracao.txt\"",
            ]),
            'busca-log' =>$this->csvRaw("{$px}_BUSCA_log.csv", \App\Services\SystematicReviewProtocolService::csvString($protocol->searchLogRows($systematicReview))),
            'triagem-ta' => $decisions(SystematicReview::STAGE_TA, 'TA'),
            'triagem-ft' => $decisions(SystematicReview::STAGE_FT, 'FT'),
            'prisma-csv' => $this->csvRaw("{$px}_PRISMA_contagens.csv", \App\Services\SystematicReviewProtocolService::csvString($protocol->prismaRows($systematicReview))),
            'desvios' => $this->csvRaw("{$px}_DESVIOS.csv", \App\Services\SystematicReviewProtocolService::csvString($protocol->deviationRows($systematicReview))),
            'extracao-modelo' => $this->csvRaw("{$px}_EXTRACAO_modelo.csv", \App\Services\SystematicReviewProtocolService::csvString($protocol->extractionTemplateRows($systematicReview))),
            'dicionario' => $this->csvRaw("{$px}_dicionario_extracao.csv", \App\Services\SystematicReviewProtocolService::csvString($protocol->dictionaryRows($systematicReview))),
            'protocolo' => response($service->protocolMarkdown($systematicReview), 200, [
                'Content-Type' => 'text/markdown; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"PROTOCOLO_PRISMA-P_{$stem}.md\"",
            ]),
            'triagem' => $this->csv("Triagem_{$stem}.csv", $this->screeningRows($systematicReview)),
            'incluidos-csv' => $this->csv("Incluidos_{$stem}.csv", $this->includedRows($systematicReview)),
            'incluidos-ris' => response($this->includedRis($systematicReview), 200, [
                'Content-Type' => 'application/x-research-info-systems; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"Incluidos_{$stem}.ris\"",
            ]),
            'relatorio' => Pdf::loadHTML(view('systematic-review.report', [
                'review' => $systematicReview,
                'prisma' => $service->prisma($systematicReview),
                'reliability' => collect(SystematicReview::STAGE_LABELS)->map(fn ($label, $stage) => $service->reliability($systematicReview, $stage)),
                'methods' => $service->methodsText($systematicReview),
                'screeners' => $systematicReview->screeners()->get(),
                'searches' => $systematicReview->search_counts['searches'] ?? [],
                'warnings' => $systematicReview->journalWarnings(),
                'audit' => collect($systematicReview->history ?? [])->filter(fn ($e) => str_starts_with($e['event'], 'Consenso dos triadores sobreposto')
                    || str_starts_with($e['event'], 'Triagem completa liberada') || str_starts_with($e['event'], 'Protocolo alterado'))->values(),
                'paces' => $systematicReview->screeners()->get()->mapWithKeys(fn ($s) => [$s->id => collect(SystematicReview::STAGE_LABELS)
                    ->map(fn ($l, $stage) => $service->pace($s, $stage))]),
            ])->render())->setPaper('a4')->download("Relatorio_Triagem_{$stem}_" . now()->format('Y-m-d') . '.pdf'),
            default => abort(404),
        };
    }

    public function terms(SystematicReviewScreener $screener)
    {
        if ($r = $this->admin()) {
            return $r;
        }

        return $this->termsPdf($screener);
    }

    /** O próprio aluno (ou o professor) baixa o termo que assinou. */
    public function myTerms(SystematicReviewScreener $screener)
    {
        abort_unless(auth()->check() && (auth()->id() === $screener->user_id || auth()->user()->role === 'admin'), 403);

        return $this->termsPdf($screener);
    }

    /** PDF do protocolo congelado: só para o próprio triador, ativo e com o termo assinado. */
    public function myProtocol(SystematicReviewScreener $screener)
    {
        abort_unless(auth()->check(), 403);
        $isAdmin = auth()->user()->role === 'admin';
        abort_unless($isAdmin || (auth()->id() === $screener->user_id && !$screener->removed_at && $screener->hasSignedTerms()), 403);
        $review = $screener->systematicReview;
        abort_unless($review->protocol_pdf_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($review->protocol_pdf_path), 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->download($review->protocol_pdf_path,
            'Protocolo_' . $review->filePrefix() . '_v' . ($review->protocol_version ?: '1.0') . '.pdf');
    }

    protected function termsPdf(SystematicReviewScreener $screener)
    {
        abort_unless($screener->terms_accepted_at, 404);

        return Pdf::loadHTML(view('systematic-review.terms', ['s' => $screener])->render())
            ->setPaper('a4')
            ->download('Termo_' . \Illuminate\Support\Str::slug($screener->name, '_') . '_' . $screener->systematicReview->baseFileStem() . '.pdf');
    }

    /** Arquivo de uma versão travada (a mais recente, ou ?v=N). O conteúdo é o que foi gravado na trava: o SHA-256 confere. */
    protected function lockedFile(SystematicReview $review, string $type, string $px)
    {
        $version = (int) request('v');
        $lock = $version ? $review->datasetLocks()->where('version', $version)->first() : $review->latestLock();
        abort_unless($lock, 404, 'A base ainda não foi travada.');
        [$name, $contents] = $type === 'extracao-travada'
            ? ["{$px}_EXTRACAO_travada_v{$lock->version}.csv", $lock->extraction_csv]
            : ["{$px}_RoB_v{$lock->version}.csv", $lock->rob_csv];

        return $this->csvRaw($name, $contents);
    }

    /** Script e saídas de uma análise registrada. */
    public function analysisFile(\App\Models\SrAnalysisRun $run, string $file)
    {
        if ($r = $this->admin()) {
            return $r;
        }
        $path = $file === 'script' ? $run->script_path : ($run->outputs[(int) $file]['path'] ?? null);
        abort_unless($path && \Illuminate\Support\Facades\Storage::disk('local')->exists($path), 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->download($path, basename($path));
    }

    protected function csvRaw(string $filename, string $contents)
    {
        return response($contents, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    protected function csv(string $filename, iterable $rows)
    {
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($v) => self::safeCell($v), $row), ';', '"', '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Evita injeção de fórmula ao abrir no Excel. */
    protected static function safeCell($value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }

    protected function screeningRows(SystematicReview $review): \Generator
    {
        $screeners = $review->screeners()->get();
        $header = ['#', 'title', 'authors', 'year', 'journal', 'doi', 'pmid', 'sources'];
        foreach (SystematicReview::STAGE_LABELS as $stage => $label) {
            foreach ($screeners as $s) {
                $header[] = "{$stage}: {$s->name}";
                $header[] = "{$stage}: {$s->name} (motivo)";
            }
        }
        array_push($header, 'final_title_abstract', 'reason_title_abstract', 'by_professor_ta', 'final_full_text', 'reason_full_text', 'by_professor_ft', 'resolution_note');
        yield $header;

        foreach ($review->records()->with('decisions')->lazy(200) as $r) {
            $votes = $r->decisions->keyBy(fn (SystematicReviewDecision $d) => $d->stage . '|' . $d->systematic_review_screener_id);
            $row = [$r->seq, $r->title, $r->authors, $r->year, $r->journal, $r->doi, $r->pmid, $r->sources];
            foreach (array_keys(SystematicReview::STAGE_LABELS) as $stage) {
                foreach ($screeners as $s) {
                    $v = $votes->get($stage . '|' . $s->id);
                    $row[] = $v?->decision;
                    $row[] = $v?->reason;
                }
            }
            array_push($row, $r->ta_decision, $r->ta_reason, $r->ta_resolved_by_professor ? 'sim' : '', $r->ft_decision, $r->ft_reason, $r->ft_resolved_by_professor ? 'sim' : '', $r->resolution_note);
            yield $row;
        }
    }

    protected function includedQuery(SystematicReview $review)
    {
        $column = $review->isStageClosed(SystematicReview::STAGE_FT) || $review->stage === SystematicReview::STAGE_FT ? 'ft_decision' : 'ta_decision';

        return $review->records()->where($column, 'include');
    }

    protected function includedRows(SystematicReview $review): \Generator
    {
        // Colunas iniciais da planilha de extração do POP (Fase 3).
        yield ['study_id', 'title', 'authors', 'year', 'journal', 'volume', 'issue', 'pages', 'doi', 'pmid', 'country', 'design', 'species',
            'n_exp', 'n_ctrl', 'mean_exp', 'sd_exp', 'mean_ctrl', 'sd_ctrl', 'events_exp', 'events_ctrl', 'dose_route', 'timepoint', 'rob_overall', 'notes'];
        foreach ($this->includedQuery($review)->lazy(200) as $r) {
            $first = \Illuminate\Support\Str::of((string) $r->authors)->before(',')->before(';')->trim()->toString();
            yield [($first ?: 'Study') . ' ' . $r->year, $r->title, $r->authors, $r->year, $r->journal,
                $r->extra['volume'] ?? '', $r->extra['issue'] ?? '', $r->extra['pages'] ?? '', $r->doi, $r->pmid];
        }
    }

    protected function includedRis(SystematicReview $review): string
    {
        $out = '';
        foreach ($this->includedQuery($review)->lazy(200) as $r) {
            /** @var SystematicReviewRecord $r */
            $out .= "TY  - JOUR\r\nTI  - " . preg_replace('/\s+/', ' ', (string) $r->title) . "\r\n";
            foreach (array_filter(array_map('trim', explode(';', (string) $r->authors))) as $a) {
                $out .= "AU  - {$a}\r\n";
            }
            foreach (['PY' => $r->year, 'T2' => $r->journal, 'VL' => $r->extra['volume'] ?? null, 'IS' => $r->extra['issue'] ?? null,
                'SP' => $r->extra['pages'] ?? null, 'SN' => $r->extra['issn'] ?? null, 'DO' => $r->doi, 'AN' => $r->pmid,
                'UR' => $r->link(), 'AB' => $r->abstract, 'KW' => $r->keywords] as $tag => $value) {
                if (filled($value)) {
                    $out .= "{$tag}  - " . preg_replace('/\s+/', ' ', (string) $value) . "\r\n";
                }
            }
            $out .= "ER  - \r\n\r\n";
        }

        return $out;
    }
}
