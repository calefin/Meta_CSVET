<?php

namespace App\Filament\Pages;

use App\Models\DocumentSubmission;
use App\Models\Student;
use Carbon\Carbon;
use Filament\Pages\Page;

class MeuHistorico extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-clock';
    protected static string  $view            = 'filament.pages.meu-historico';
    protected static ?string $navigationLabel = 'Histórico';
    protected static ?string $title           = 'Histórico de Processos';
    protected static ?int    $navigationSort  = 5;

    public static function getNavigationGroup(): ?string
    {
        return auth()->user()?->role === 'student' ? 'Meus Trabalhos' : null;
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public function getTimelineEntries(): array
    {
        $user      = auth()->user();
        $isAdmin   = $user->role === 'admin';
        $entries   = [];

        if ($isAdmin) {
            $submissions = DocumentSubmission::with('student.user')
                ->orderByDesc('updated_at')
                ->get();
        } else {
            $student = Student::where('user_id', $user->id)->first();
            if (!$student) return [];

            $submissions = DocumentSubmission::where('student_id', $student->id)
                ->orderByDesc('updated_at')
                ->get();
        }

        foreach ($submissions as $submission) {
            $history = $submission->metadata['history'] ?? [];

            // Fallback: if history is empty, synthesize one entry from created_at
            if (empty($history)) {
                $history = [[
                    'status'    => $submission->status,
                    'old_status' => null,
                    'timestamp' => $submission->created_at
                        ?->timezone('America/Sao_Paulo')->format('d/m/Y - H:i:s') ?? '—',
                    'actor'    => 'Sistema',
                    'comments' => null,
                ]];
            }

            foreach (array_reverse($history) as $entry) {
                $ts = null;
                $rawTs = $entry['timestamp'] ?? null;

                if ($rawTs) {
                    try {
                        $ts = Carbon::createFromFormat('d/m/Y - H:i:s', $rawTs, 'America/Sao_Paulo');
                    } catch (\Exception) {
                        try {
                            $ts = Carbon::parse($rawTs)->timezone('America/Sao_Paulo');
                        } catch (\Exception) {}
                    }
                }

                $entries[] = [
                    'process_id'    => $submission->process_id,
                    'document_type' => $submission->document_type,
                    'title'         => $submission->title,
                    'student_name'  => $submission->student?->user?->name,
                    'status'        => $entry['status']   ?? '—',
                    'old_status'    => $entry['old_status'] ?? null,
                    'timestamp'     => $rawTs ?? '—',
                    'timestamp_obj' => $ts,
                    'actor'         => $entry['actor']    ?? 'Sistema',
                    'comments'      => $entry['comments'] ?? null,
                ];
            }
        }

        // Sort by timestamp desc (most recent first)
        usort($entries, function ($a, $b) {
            if (!$a['timestamp_obj'] && !$b['timestamp_obj']) return 0;
            if (!$a['timestamp_obj']) return 1;
            if (!$b['timestamp_obj']) return -1;
            return $b['timestamp_obj']->timestamp <=> $a['timestamp_obj']->timestamp;
        });

        return $entries;
    }

    public function getStatusLabel(string $status): string
    {
        return match ($status) {
            'pending'                  => 'Aguardando Auditoria de IA',
            'reviewing'                => 'Em Revisão pelo Professor',
            'approved'                 => 'Aprovado pelo Orientador',
            'returned_signed'          => 'Devolvido com Assinatura',
            'needs_corrections'        => 'Necessita Correções',
            'failed'                   => 'Falha no Processamento',
            'draft'                    => 'Rascunho',
            default                    => $status,
        };
    }

    public function getDotColor(string $status): string
    {
        if (str_starts_with($status, 'Processo Concluído')) return 'bg-green-500 ring-2 ring-green-500/20';
        if (str_contains($status, 'Ajustes') || str_contains($status, 'Correção')) return 'bg-amber-500 ring-2 ring-amber-500/20';
        if (str_contains($status, 'Aguardando') || $status === 'pending') return 'bg-blue-500 ring-2 ring-blue-500/20';
        if (in_array($status, ['approved', 'returned_signed'])) return 'bg-green-500 ring-2 ring-green-500/20';
        if (in_array($status, ['needs_corrections', 'failed'])) return 'bg-red-500 ring-2 ring-red-500/20';
        return 'bg-teal-500 ring-2 ring-teal-500/20';
    }

    public function getTypeBadgeColor(string $type): string
    {
        return match ($type) {
            'TCC'                         => 'text-blue-400 bg-blue-500/10 border-blue-500/20',
            'Iniciação Científica',
            'Termo de Aceite de IC'       => 'text-amber-400 bg-amber-500/10 border-amber-500/20',
            'Artigo Científico',
            'Relato de Caso',
            'Trabalho de Congresso'       => 'text-purple-400 bg-purple-500/10 border-purple-500/20',
            'Relatório', 'Relatório de Estágio',
            'Relatório de Necropsia'      => 'text-teal-400 bg-teal-500/10 border-teal-500/20',
            default                       => 'text-slate-400 bg-slate-500/10 border-slate-500/20',
        };
    }
}
