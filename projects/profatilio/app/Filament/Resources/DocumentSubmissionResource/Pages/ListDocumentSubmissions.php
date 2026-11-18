<?php

namespace App\Filament\Resources\DocumentSubmissionResource\Pages;

use App\Filament\Resources\DocumentSubmissionResource;
use App\Models\DocumentSubmission;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListDocumentSubmissions extends ListRecords
{
    protected static string $resource = DocumentSubmissionResource::class;

    /**
     * Status em que a bola está com o professor: precisa revisar, corrigir, assinar ou
     * homologar algo que o aluno já entregou. Cobre todos os fluxos (TCC, IC, Necropsia,
     * Estágio, Relatórios, Termos de Aceite etc).
     */
    public const PROFESSOR_STATUSES = [
        'Aguardando Definição de Tema',
        'Aguardando Correção dos Objetivos',
        'Aguardando Correção do Professor',
        'Em análise final para assinatura institucional',
        'Projeto em Análise Inicial pelo Orientador',
        'Aguardando Revisão do Professor',
        'Aguardando Assinatura do Professor',
        'Avaliações de Estágio Registradas e Recebidas',
        'Relatório de Necropsia Recebido - Aguardando Análise do Professor',
        'Termos Aceitos e Auditados - Aguardando Assinatura do Orientador',
        'reviewing',
        'reviewed', // Auditoria de IA concluída sem apontar desvio de escopo — aguardando parecer do professor
        'auditing', // Documento em processamento pelo job de auditoria de IA (evita sumir da lista se a fila travar)
        // Etapas de redação do TCC (pipeline: Introdução -> M&M -> Resultados -> Discussão -> Conclusão)
        'Aguardando Correção da Introdução',
        'Aguardando Correção de Materiais e Métodos',
        'Aguardando Correção dos Resultados',
        'Aguardando Correção da Discussão',
        'Aguardando Correção da Conclusão',
        'Aguardando Correção do TCC Completo',
    ];

    /**
     * Status em que a bola está com o aluno: precisa enviar, ajustar ou corrigir algo antes
     * do professor poder agir de novo.
     */
    public const STUDENT_STATUSES = [
        'Aguardando Ajustes do Aluno',
        'Documento Assinado - Aguardando Correções do Aluno',
        'Aguardando Ajustes no Laudo pelo Aluno',
        'Aguardando Objetivos do Aluno',
        'Aguardando Tema do Aluno',
        'Aguardando Relatório Parcial',
        'needs_corrections',
        'pending',
        // Etapas de redação do TCC (pipeline: Introdução -> M&M -> Resultados -> Discussão -> Conclusão)
        'Aguardando Introdução do Aluno',
        'Aguardando Materiais e Métodos do Aluno',
        'Aguardando Resultados do Aluno',
        'Aguardando Discussão do Aluno',
        'Aguardando Conclusão do Aluno',
    ];

    public const CONCLUDED_STATUSES = [
        'approved',
        'returned_signed',
        'failed',
        'Assinatura Realizada e Auditada',
        'Cancelado',
    ];

    /**
     * Status de entrada, tratado no hook do model e nunca deve persistir no banco.
     */
    public const TRIAGE_STATUS = 'Aguardando Triagem';

    /**
     * Rede de segurança: qualquer processo cujo status não se encaixe em nenhuma aba conhecida
     * cai aqui. Sem isso, um status novo/esquecido (ex: o bug do 'reviewed' da auditoria de IA)
     * simplesmente some de todas as listas filtradas sem avisar ninguém.
     */
    public static function applyUnclassifiedScope(Builder $query): Builder
    {
        return $query
            ->where('status', '!=', self::TRIAGE_STATUS)
            ->whereNotIn('status', self::PROFESSOR_STATUSES)
            ->whereNotIn('status', self::STUDENT_STATUSES)
            ->whereNotIn('status', self::CONCLUDED_STATUSES)
            ->where('status', 'not like', 'Processo Concluído%');
    }

    public static function unclassifiedQuery(): Builder
    {
        return self::applyUnclassifiedScope(DocumentSubmission::query());
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cobrarAguardandoAluno')
                ->label('Cobrar Todos: Aguardando o Aluno')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn () => auth()->user()?->role === 'admin')
                ->requiresConfirmation()
                ->modalHeading('Cobrar todos os processos aguardando o aluno')
                ->modalDescription(fn () => 'Envia um e-mail (e Telegram, se vinculado) para cada aluno com processo pendente na aba "Aguardando o Aluno", avisando que você está aguardando o retorno dele. Total de processos pendentes agora: ' . DocumentSubmission::whereIn('status', self::STUDENT_STATUSES)->count() . '.')
                ->modalSubmitActionLabel('Enviar Cobranças')
                ->action('cobrarTodosAguardandoAluno'),
            Actions\CreateAction::make()
                ->label('Enviar Novo Documento')
                ->icon('heroicon-o-document-plus')
                ->color('primary'),
        ];
    }

    /**
     * Botão rápido do orientador: envia, de uma vez, um aviso (e-mail + Telegram)
     * para cada aluno que tem pelo menos um processo na aba "Aguardando o Aluno",
     * listando os processos pendentes e avisando que o orientador aguarda o retorno.
     */
    public function cobrarTodosAguardandoAluno(): void
    {
        $submissions = DocumentSubmission::whereIn('status', self::STUDENT_STATUSES)
            ->with('student.user')
            ->get()
            ->filter(fn (DocumentSubmission $s) => $s->student && $s->student->user);

        $byStudent = $submissions->groupBy('student_id');
        $sentCount = 0;

        foreach ($byStudent as $studentSubmissions) {
            $student = $studentSubmissions->first()->student;
            $name = $student->user->name;
            $email = $student->user->email;

            $itemsHtml = '<ul>';
            foreach ($studentSubmissions as $sub) {
                $itemsHtml .= "<li><strong>{$sub->process_id}</strong> — {$sub->title} ({$sub->document_type})</li>";
            }
            $itemsHtml .= '</ul>';

            if ($email) {
                $htmlBody = "Olá, <strong>{$name}</strong>!<br><br>"
                    . "Estou aguardando o seu retorno para dar continuidade no(s) processo(s) abaixo:<br><br>"
                    . $itemsHtml
                    . "<br>Por favor, acesse o portal e envie o que for necessário assim que possível.<br><br>"
                    . "Atenciosamente,<br><strong>Prof. Dr. Atilio Sersun Calefi</strong>";

                \Illuminate\Support\Facades\Mail::to($email)->queue(new \App\Mail\BulkEmailAlunos(
                    subject: 'Aguardando seu retorno para continuidade do seu processo',
                    htmlBody: $htmlBody,
                    recipientName: $name,
                ));
            }

            $telegramChatId = $student->telegram_chat_id ?? $student->user->telegram_chat_id;
            if (!empty($telegramChatId)) {
                $tgLines = $studentSubmissions
                    ->map(fn (DocumentSubmission $sub) => "• <b>{$sub->process_id}</b> — " . e($sub->title))
                    ->implode("\n");

                $tgMessage = "⏳ <b>Aguardando seu retorno</b>\n\n"
                    . "Olá, {$name}!\n"
                    . "Estou aguardando seu retorno para continuidade do(s) processo(s):\n\n"
                    . "{$tgLines}\n\n"
                    . "<a href=\"" . url('/portal') . "\">👉 Acessar Portal</a>";

                try {
                    app(\App\Services\TelegramService::class)->sendMessage((string) $telegramChatId, $tgMessage);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Erro ao enviar Telegram de cobrança em massa: " . $e->getMessage());
                }
            }

            $sentCount++;
        }

        \Filament\Notifications\Notification::make()
            ->title($sentCount > 0 ? "Cobrança enviada para {$sentCount} aluno(s)." : 'Nenhum aluno com processo pendente encontrado.')
            ->success()
            ->send();
    }

    /**
     * Abre direto na aba com trabalho pendente para o professor, em vez de sempre cair
     * em "Para Habilitar" mesmo quando ela está vazia e o que precisa de resposta está
     * em "Aguardando Meu Despacho".
     */
    public function getDefaultActiveTab(): string|int|null
    {
        if (auth()->user()->role !== 'admin') {
            return null;
        }

        // Prioridade máxima: um status não reconhecido é sinal de bug no fluxo, não de rotina.
        if (self::unclassifiedQuery()->exists()) {
            return 'unclassified';
        }

        if (DocumentSubmission::where('status', self::TRIAGE_STATUS)->exists()) {
            return 'to_enable';
        }

        if (DocumentSubmission::whereIn('status', self::PROFESSOR_STATUSES)->exists()) {
            return 'my_dispatch';
        }

        return 'to_enable';
    }

    public function getTabs(): array
    {
        if (auth()->user()->role !== 'admin') {
            return [];
        }

        $tabs = [
            'to_enable' => Tab::make('Para Habilitar')
                ->icon('heroicon-o-inbox-arrow-down')
                ->badge(DocumentSubmission::where('status', self::TRIAGE_STATUS)->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', self::TRIAGE_STATUS)),
            'my_dispatch' => Tab::make('Aguardando Meu Despacho')
                ->icon('heroicon-o-pencil-square')
                ->badge(DocumentSubmission::whereIn('status', self::PROFESSOR_STATUSES)->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', self::PROFESSOR_STATUSES)),
            'awaiting_student' => Tab::make('Aguardando o Aluno')
                ->icon('heroicon-o-clock')
                ->badge(DocumentSubmission::whereIn('status', self::STUDENT_STATUSES)->count())
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', self::STUDENT_STATUSES)),
            'concluded' => Tab::make('Processos Concluídos')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where(fn (Builder $q) => $q
                        ->where('status', 'like', 'Processo Concluído%')
                        ->orWhereIn('status', self::CONCLUDED_STATUSES)
                    )
                ),
            'all' => Tab::make('Todos os Envios')
                ->icon('heroicon-o-list-bullet'),
        ];

        $unclassifiedCount = self::unclassifiedQuery()->count();
        if ($unclassifiedCount > 0) {
            // Só aparece quando há algo realmente fora do mapa — não polui a navegação no dia a dia.
            $tabs = ['unclassified' => Tab::make('⚠️ Não Classificado')
                ->icon('heroicon-o-exclamation-triangle')
                ->badge($unclassifiedCount)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => self::applyUnclassifiedScope($query))] + $tabs;
        }

        return $tabs;
    }
}
