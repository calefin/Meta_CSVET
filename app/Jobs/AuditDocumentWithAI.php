<?php

namespace App\Jobs;

use App\Models\DocumentSubmission;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AuditDocumentWithAI implements ShouldQueue
{
    use Queueable;

    protected $submission;

    /**
     * O número de vezes que o job pode ser tentado para lidar com rate-limiting temporário.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * O número de segundos de espera antes de tentar novamente o job (atraso exponencial).
     *
     * @return array
     */
    public function backoff(): array
    {
        return [60, 120, 240]; // Espera 1 minuto, depois 2 minutos, depois 4 minutos
    }

    /**
     * Create a new job instance.
     */
    public function __construct(DocumentSubmission $submission)
    {
        $this->submission = $submission;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $submission = $this->submission;
        $submission->update(['status' => 'auditing']);
        $tempPath = null;

        try {
            $diskName = in_array(config('filesystems.default'), ['google', 'onedrive']) ? config('filesystems.default') : 'public';
            $disk = Storage::disk($diskName);
            $fileId = $submission->google_drive_file_id;

            if (is_array($fileId)) {
                if (empty($fileId)) {
                    throw new \Exception("Nenhum arquivo do Google Drive foi associado a esta submissão.");
                }
                $fileId = $fileId[0];
            }

            if (!$fileId) {
                throw new \Exception("Nenhum arquivo do Google Drive foi associado a esta submissão.");
            }

            // Baixar o arquivo temporariamente para processar
            $fileContent = $disk->get($fileId);
            $extension = pathinfo($submission->title, PATHINFO_EXTENSION);
            if (!$extension) {
                $extension = str_contains($submission->title, '.') 
                    ? last(explode('.', $submission->title)) 
                    : 'pdf';
            }
            $extension = strtolower($extension);

            $tempPath = storage_path('app/private/temp_audit_' . $submission->id . '.' . $extension);
            
            // Garantir que a pasta private existe
            if (!is_dir(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }
            file_put_contents($tempPath, $fileContent);

            // Extrair o texto com base na extensão
            $text = '';
            if ($extension === 'pdf') {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($tempPath);
                $text = $pdf->getText();
            } elseif ($extension === 'docx') {
                $zip = new \ZipArchive();
                if ($zip->open($tempPath) === true) {
                    if (($index = $zip->locateName('word/document.xml')) !== false) {
                        $xmlContent = $zip->getFromIndex($index);
                        $xmlContent = str_replace(['</w:p>', '</w:r>', '<w:tab/>'], ["\n", ' ', "\t"], $xmlContent);
                        $text = strip_tags($xmlContent);
                    }
                    $zip->close();
                }
            } else {
                throw new \Exception("Formato de arquivo não suportado para auditoria: " . $extension);
            }

            // Garantir codificação UTF-8 válida e remover caracteres de controle corrompidos
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
            $text = preg_replace('/[^\x20-\x7E\x0A\x0D\x{00A0}-\x{024F}\x{1E00}-\x{1EFF}\x{0100}-\x{017F}]/u', ' ', $text);

            if (empty(trim($text))) {
                throw new \Exception("Não foi possível extrair nenhum texto do documento.");
            }

            // Substituído para auditoria local e gratuita (sem uso da API do Gemini para evitar despesas)
            $abnt_valid = true;
            $abnt_feedback = [];
            $content_type_valid = true;
            $content_type_error_message = '';

            // 1. Verificação de Nomenclatura (tutor)
            if (preg_match('/\btutor\b/i', $text)) {
                $abnt_valid = false;
                $abnt_feedback[] = [
                    'citation' => 'tutor',
                    'issue' => 'Uso do termo proibido "tutor" para se referir ao proprietário ou responsável por um animal.',
                    'suggestion' => 'Substituir obrigatoriamente pelo termo legal "responsável legal".'
                ];
            }

            // 2. Verificação de Citações ABNT (caixa alta entre parênteses)
            if (preg_match_all('/\(\s*([A-ZÀ-Ú\s;]{3,})\s*,\s*(\d{4})\s*\)/u', $text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $citation = $match[0];
                    $authors = $match[1];
                    $year = $match[2];

                    if ($authors === mb_strtoupper($authors) && !is_numeric($authors)) {
                        $abnt_valid = false;
                        
                        $parts = explode(';', $authors);
                        $capitalizedParts = array_map(function($p) {
                            return mb_convert_case(trim($p), MB_CASE_TITLE, 'UTF-8');
                        }, $parts);
                        $suggestionAuthors = implode('; ', $capitalizedParts);

                        $abnt_feedback[] = [
                            'citation' => $citation,
                            'issue' => 'Citação dentro de parênteses em caixa alta (desacordo com a ABNT NBR 10520:2023).',
                            'suggestion' => "Alterar para ({$suggestionAuthors}, {$year})"
                        ];
                    }
                }
            }

            // 3. Veracidade Biológica (Esteatose Hepática em Elasmobrânquios)
            $hasElasmobranch = preg_match('/(tubar[aã]o|ca[cç][aã]o|arraia|raia|elasmobr[aâ]nquio)/i', $text);
            $hasEsteatose = preg_match('/esteatose\s+hep[aá]tica/i', $text);
            if ($hasElasmobranch && $hasEsteatose) {
                $isDescribedAsPathology = preg_match('/(patolog|doen[cç]a|altera[cç][aã]o|les[aã]o|grave|tratar|tratamento)/i', $text);
                if ($isDescribedAsPathology) {
                    $abnt_valid = false;
                    $abnt_feedback[] = [
                        'citation' => 'esteatose hepática descrita como alteração patológica',
                        'issue' => 'A esteatose hepática em elasmobrânquios é descrita como patologia.',
                        'suggestion' => 'Classificar a esteatose hepática como achado metabólico esperado e fisiológico para o táxon, e não como uma alteração patológica.'
                    ];
                }
            }

            // 4. Verificação de Tipo de Conteúdo (Desvio de Escopo)
            $textLower = mb_strtolower($text);
            if ($submission->document_type === 'Relatório de Necropsia') {
                $hasNecroTerms = preg_match('/(necropsia|laudo|macroscop|microscop|[oó]bito|morte|animal)/i', $textLower);
                if (!$hasNecroTerms) {
                    $content_type_valid = false;
                    $content_type_error_message = 'Identifiquei que este conteúdo parece pertencer a outro fluxo de trabalho. Por favor, certifique-se de que selecionou a opção correta no painel do profatilio.com.br antes de prosseguir.';
                }
            } elseif ($submission->document_type === 'TCC') {
                // TCCs devem ser razoavelmente longos
                $wordCount = count(preg_split('/\s+/u', trim($text)));
                if ($wordCount < 100) {
                    $content_type_valid = false;
                    $content_type_error_message = 'Identifiquei que este conteúdo parece pertencer a outro fluxo de trabalho. Por favor, certifique-se de que selecionou a opção correta no painel do profatilio.com.br antes de prosseguir.';
                }
            }

            // 5. Geração de Resumo Executivo em 3 parágrafos
            $student = $submission->student;
            $studentName = $student && $student->user ? $student->user->name : 'Estudante';
            $institution = $student ? $student->institution : 'não especificada';
            $campus = $student ? $student->campus : 'não especificado';
            
            $wordCount = count(preg_split('/\s+/u', trim($text)));
            $complianceStatus = $abnt_valid ? 'em total conformidade com as regras acadêmicas da plataforma' : 'com inconsistências identificadas que requerem atenção';

            $p1 = "Este documento refere-se a uma submissão de tipo " . $submission->document_type . " (ID: " . $submission->process_id . "), realizada pelo orientando " . $studentName . " da instituição " . $institution . " (" . $campus . "). O processamento inicial extraiu com sucesso o conteúdo textual do arquivo para realizar a auditoria de conformidade regulatória e técnica.";

            $p2 = "A análise de estrutura e formato contabilizou um volume total de aproximadamente " . $wordCount . " palavras. Durante a auditoria local, foram avaliados o cumprimento da norma de citações bibliográficas ABNT NBR 10520:2023, a restrição forense de terminologias para proprietários de animais e a consistência técnica das descrições biológicas específicas.";

            $p3 = "Como resultado final, o arquivo foi classificado como " . $complianceStatus . ". Este relatório foi emitido pelo sistema de validação local e está pronto para visualização do orientador, garantindo um fluxo ágil de correções sem consumo de créditos externos de API.";

            $summary = $p1 . "\n\n" . $p2 . "\n\n" . $p3;

            $newStatus = 'reviewed';
            if (!$content_type_valid) {
                $newStatus = 'needs_corrections';
                $summary = $content_type_error_message . "\n\n" . $summary;
            }

            $submission->update([
                'status' => $newStatus,
                'ai_abnt_feedback_json' => $abnt_feedback,
                'ai_executive_summary' => $summary,
                'abnt_verified_at' => now(),
            ]);

        } catch (\Exception $e) {
            Log::error("Erro na auditoria da submissão ID " . $submission->id . ": " . $e->getMessage());
            $submission->update([
                'status' => 'failed',
                'ai_executive_summary' => 'Falha na auditoria automática: ' . $e->getMessage()
            ]);
        } finally {
            // Garantir a exclusão física do arquivo temporário mesmo em caso de exceção no processamento
            if ($tempPath && file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }
}
