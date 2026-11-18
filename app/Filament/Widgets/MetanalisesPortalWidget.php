<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\TriagemCega;
use App\Models\SystematicReview;
use App\Models\SystematicReviewScreener;
use App\Support\ProgramJourney;
use Filament\Widgets\Widget;

/**
 * Topo do Painel do aluno: os projetos do Programa de Metanálises, o termo de
 * cada um e a próxima ação, com o botão direto. Sem isso, o aluno da seleção
 * caía num painel de TCC/estágio e só achava a "Triagem Cega" pelo menu.
 */
class MetanalisesPortalWidget extends Widget
{
    protected static string $view = 'filament.widgets.metanalises-portal-widget';

    protected static ?int $sort = -10; // Acima do guia de boas-vindas: é o que o aluno do programa precisa fazer.

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->role === 'student' && TriagemCega::myAssignments()->exists();
    }

    /** @return array<int, array{code: string, role: string, signed: bool, action: string, journey: array, button: string, url: string, chatUrl: ?string, unread: int, last: ?string, urgent: bool}> */
    public function getProjects(): array
    {
        return TriagemCega::myAssignments()->with('systematicReview')->orderBy('id')->get()
            ->map(function (SystematicReviewScreener $s) {
                $r = $s->systematicReview;
                $signed = $s->hasSignedTerms();
                $screening = $r->status === SystematicReview::STATUS_SCREENING && $s->participatesIn($r->stage) && !$s->hasFinished($r->stage);
                $button = match (true) {
                    !$signed => 'Ler e assinar o termo',
                    $screening => 'Triar agora',
                    default => 'Abrir o projeto',
                };
                // Barra colorida da esteira + "Agora" (o projeto) e "Você" (o aluno, pelo papel).
                $journey = ProgramJourney::for($r);
                $action = ProgramJourney::youText($r, $s, $journey['current']);

                // Conversa: o aluno não sabia onde ficavam as mensagens. Aqui ele vê se há novas e tem o atalho direto.
                $unread = $s->unreadMessagesCount();
                $last = $signed ? $r->messages()->reorder()->latest('id')->first() : null;

                return [
                    'code' => $r->selectionCode() ?: (string) $r->protocol_code,
                    'role' => $s->label ?: 'Triador(a)',
                    'signed' => $signed,
                    'action' => $action,
                    'journey' => $journey,
                    'button' => $button,
                    'url' => TriagemCega::getUrl(['projeto' => $s->id]),
                    'chatUrl' => $signed ? TriagemCega::getUrl(['projeto' => $s->id]) . '#conversa' : null,
                    'unread' => $unread,
                    'last' => $last ? ($last->user_id === auth()->id() ? 'Você' : $last->author_name) . ', ' . $last->created_at->format('d/m H:i') : null,
                    'urgent' => !$signed || $screening || $unread > 0,
                ];
            })->all();
    }

    /** Treino da triagem liberado: atalho à parte, para não se confundir com o projeto real. */
    public function getTraining(): ?array
    {
        $s = \App\Filament\Pages\TreinoTriagem::myAssignments()->with('systematicReview')
            ->whereHas('systematicReview', fn ($q) => $q->where('status', '!=', SystematicReview::STATUS_PROTOCOL))->latest('id')->first();
        if (!$s) {
            return null;
        }
        $r = $s->systematicReview;
        $open = $r->status === SystematicReview::STATUS_SCREENING;

        return [
            'text' => match (true) {
                $open && !$s->hasFinished($r->stage) => 'Aberto' . ($r->training_closes_at ? ' até ' . $r->training_closes_at->format('d/m') . ' às ' . $r->training_closes_at->format('H\hi') : '') . '. Decida os 25 trabalhos.',
                $open => 'Você já decidiu todos. Os resultados aparecem quando o treino fechar.',
                default => 'Encerrado: veja as suas decisões, as da dupla e o gabarito comentado.',
            },
            'button' => $open ? 'Abrir o treino' : 'Ver os resultados',
            'url' => \App\Filament\Pages\TreinoTriagem::getUrl(['projeto' => $s->id]),
            'urgent' => $open && !$s->hasFinished($r->stage),
        ];
    }
}
