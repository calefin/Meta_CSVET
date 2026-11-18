<?php

namespace App\Filament\Pages;

use App\Models\DocumentSubmission;
use Filament\Pages\Page;
use Filament\Notifications\Notification;

class SubmissionsKanban extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-bar';

    protected static string $view = 'filament.pages.submissions-kanban';

    protected static ?string $navigationLabel = 'Quadro Kanban';

    protected static ?string $title = 'Fluxo de Submissões (Kanban)';

    protected static ?int $navigationSort = 2;

    public $search = '';
    public $filterType = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public function getStages(): array
    {
        return [
            'pending' => [
                'title' => 'Pendentes / Enviados',
                'color' => 'amber',
                'bgClass' => 'bg-amber-50 dark:bg-amber-950/20',
                'borderClass' => 'border-amber-200 dark:border-amber-800',
                'textClass' => 'text-amber-800 dark:text-amber-250',
                'description' => 'Aguardando ação inicial ou correção',
            ],
            'reviewing' => [
                'title' => 'Em Análise / Revisão',
                'color' => 'blue',
                'bgClass' => 'bg-blue-50 dark:bg-blue-950/20',
                'borderClass' => 'border-blue-200 dark:border-blue-800',
                'textClass' => 'text-blue-800 dark:text-blue-250',
                'description' => 'Sendo avaliados ou assinados',
            ],
            'corrections' => [
                'title' => 'Aguardando Ajustes',
                'color' => 'red',
                'bgClass' => 'bg-red-50 dark:bg-red-950/20',
                'borderClass' => 'border-red-200 dark:border-red-800',
                'textClass' => 'text-red-800 dark:text-red-250',
                'description' => 'Devolvidos com observações/correções',
            ],
            'completed' => [
                'title' => 'Concluídos',
                'color' => 'emerald',
                'bgClass' => 'bg-emerald-50 dark:bg-emerald-950/20',
                'borderClass' => 'border-emerald-200 dark:border-emerald-800',
                'textClass' => 'text-emerald-800 dark:text-emerald-250',
                'description' => 'Processo concluído com sucesso',
            ],
        ];
    }

    public function getSubmissionsProperty()
    {
        $query = DocumentSubmission::with('student.user');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title', 'like', '%' . $this->search . '%')
                  ->orWhere('process_id', 'like', '%' . $this->search . '%')
                  ->orWhereHas('student.user', function ($uq) {
                      $uq->where('name', 'like', '%' . $this->search . '%');
                  });
            });
        }

        if ($this->filterType) {
            $query->where('document_type', $this->filterType);
        }

        return $query->get()->groupBy(fn($sub) => $this->getStageForStatus($sub->status));
    }

    public function getStageForStatus(string $status): string
    {
        return match ($status) {
            'pending', 
            'Aguardando Correção do Professor', 
            'Projeto em Análise Inicial pelo Orientador', 
            'Aguardando Revisão do Professor', 
            'Aguardando Assinatura do Professor', 
            'Avaliações de Estágio Registradas e Recebidas', 
            'Relatório de Necropsia Recebido - Aguardando Análise do Professor', 
            'Termos Aceitos e Auditados - Aguardando Assinatura do Orientador',
            'Aguardando Relatório Parcial',
            'Aguardando Definição de Tema',
            'Aguardando Correção dos Objetivos',
            'Aguardando Correção da Introdução',
            'Aguardando Correção de Materiais e Métodos',
            'Aguardando Correção dos Resultados',
            'Aguardando Correção da Discussão',
            'Aguardando Correção da Conclusão' => 'pending',
            
            'reviewing', 
            'Em análise final para assinatura institucional' => 'reviewing',
            
            'needs_corrections',
            'Aguardando Ajustes do Aluno',
            'Aguardando Ajustes no Laudo pelo Aluno',
            'Aguardando Objetivos do Aluno',
            'Aguardando Tema do Aluno',
            'Aguardando Introdução do Aluno',
            'Aguardando Materiais e Métodos do Aluno',
            'Aguardando Resultados do Aluno',
            'Aguardando Discussão do Aluno',
            'Aguardando Conclusão do Aluno' => 'corrections',
            
            'approved', 
            'returned_signed', 
            'Processo Concluído - Documento Assinado Disponível', 
            'Processo Concluído - Projeto Arquivado', 
            'Processo Concluído - Versão Final Definida', 
            'Processo Concluído - Termo de Aceite Assinado', 
            'Processo Concluído - Estágio Homologado com Sucesso', 
            'Processo Concluído - Laudo de Necropsia Homologado', 
            'Processo Concluído - Orientação de IC Homologada e Ativa',
            'Processo Concluído - Tema Definido',
            'Processo Concluído - Objetivos Aprovados',
            'Processo Concluído - Introdução Aprovada',
            'Processo Concluído - Materiais e Métodos Aprovados',
            'Processo Concluído - Resultados Aprovados',
            'Processo Concluído - Discussão Aprovada',
            'Processo Concluído - Conclusão Aprovada',
            'failed' => 'completed',
            
            default => 'pending',
        };
    }

    public function getStatusForStage(string $documentType, string $stage): string
    {
        return match ($stage) {
            'pending' => match ($documentType) {
                'TCC' => 'Aguardando Correção do Professor',
                'Iniciação Científica' => 'Projeto em Análise Inicial pelo Orientador',
                'Relatório', 'Artigo Científico', 'Relato de Caso', 'Trabalho de Congresso', 'Protocolo CEUA/CEP', 'Relatório de Estágio', 'Relatório de Aula' => 'Aguardando Revisão do Professor',
                'Plano de Estágio', 'Termo de Aceite de Orientação' => 'Aguardando Assinatura do Professor',
                'Avaliações de Estágio' => 'Avaliações de Estágio Registradas e Recebidas',
                'Relatório de Necropsia' => 'Relatório de Necropsia Recebido - Aguardando Análise do Professor',
                'Termo de Aceite de IC' => 'Termos Aceitos e Auditados - Aguardando Assinatura do Orientador',
                'Concepção de TCC' => 'Aguardando Definição de Tema',
                'Objetivos de TCC' => 'Aguardando Correção dos Objetivos',
                'Introdução de TCC' => 'Aguardando Correção da Introdução',
                'Materiais e Métodos de TCC' => 'Aguardando Correção de Materiais e Métodos',
                'Resultados de TCC' => 'Aguardando Correção dos Resultados',
                'Discussão de TCC' => 'Aguardando Correção da Discussão',
                'Conclusão de TCC' => 'Aguardando Correção da Conclusão',
                'Documento Solicitado' => 'Aguardando Envio do Aluno',
                default => 'pending',
            },
            'reviewing' => match ($documentType) {
                'TCC' => 'Em análise final para assinatura institucional',
                default => 'reviewing',
            },
            'corrections' => match ($documentType) {
                'TCC' => 'Aguardando Ajustes do Aluno',
                'Relatório de Necropsia' => 'Aguardando Ajustes no Laudo pelo Aluno',
                'Concepção de TCC' => 'Aguardando Ajustes do Aluno',
                'Objetivos de TCC' => 'Aguardando Objetivos do Aluno',
                'Introdução de TCC' => 'Aguardando Introdução do Aluno',
                'Materiais e Métodos de TCC' => 'Aguardando Materiais e Métodos do Aluno',
                'Resultados de TCC' => 'Aguardando Resultados do Aluno',
                'Discussão de TCC' => 'Aguardando Discussão do Aluno',
                'Conclusão de TCC' => 'Aguardando Conclusão do Aluno',
                'Documento Solicitado' => 'Aguardando Ajustes do Aluno',
                default => 'needs_corrections',
            },
            'completed' => match ($documentType) {
                'TCC', 'Plano de Estágio', 'Protocolo CEUA/CEP', 'Relatório de Estágio', 'Relatório de Aula' => 'Processo Concluído - Documento Assinado Disponível',
                'Iniciação Científica' => 'Processo Concluído - Projeto Arquivado',
                'Relatório', 'Artigo Científico', 'Relato de Caso', 'Trabalho de Congresso' => 'Processo Concluído - Versão Final Definida',
                'Termo de Aceite de Orientação' => 'Processo Concluído - Termo de Aceite Assinado',
                'Avaliações de Estágio' => 'Processo Concluído - Estágio Homologado com Sucesso',
                'Relatório de Necropsia' => 'Processo Concluído - Laudo de Necropsia Homologado',
                'Termo de Aceite de IC' => 'Processo Concluído - Orientação de IC Homologada e Ativa',
                'Concepção de TCC' => 'Processo Concluído - Tema Definido',
                'Objetivos de TCC' => 'Processo Concluído - Objetivos Aprovados',
                'Introdução de TCC' => 'Processo Concluído - Introdução Aprovada',
                'Materiais e Métodos de TCC' => 'Processo Concluído - Materiais e Métodos Aprovados',
                'Resultados de TCC' => 'Processo Concluído - Resultados Aprovados',
                'Discussão de TCC' => 'Processo Concluído - Discussão Aprovada',
                'Conclusão de TCC' => 'Processo Concluído - Conclusão Aprovada',
                'Documento Solicitado' => 'Processo Concluído - Documento Assinado Disponível',
                default => 'approved',
            },
            default => 'pending',
        };
    }

    public function updateSubmissionStatus(int $id, string $newStage): void
    {
        $submission = DocumentSubmission::find($id);

        if (!$submission) {
            Notification::make()
                ->title('Erro ao atualizar')
                ->danger()
                ->body('Submissão não encontrada.')
                ->send();
            return;
        }

        $oldStage = $this->getStageForStatus($submission->status);
        if ($oldStage === $newStage) {
            return;
        }

        $newStatus = $this->getStatusForStage($submission->document_type, $newStage);
        $submission->status = $newStatus;
        $submission->save();

        Notification::make()
            ->title('Status Atualizado')
            ->success()
            ->body("O processo {$submission->process_id} foi movido com sucesso para " . $this->getStages()[$newStage]['title'])
            ->send();
    }
}
