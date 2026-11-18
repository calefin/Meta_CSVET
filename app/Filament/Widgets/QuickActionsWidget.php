<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\ManualDoAluno;
use App\Filament\Resources\AcademicProjectResource;
use App\Filament\Resources\DocumentSubmissionResource;
use App\Filament\Resources\SupportTicketResource;
use Filament\Widgets\Widget;

class QuickActionsWidget extends Widget
{
    protected static string $view = 'filament.widgets.quick-actions-widget';

    protected static ?int $sort = -3; // Bem no topo do Dashboard do Aluno

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->role === 'student';
    }

    public function getActions(): array
    {
        return [
            [
                'label' => 'Enviar Novo Documento',
                'description' => 'Submeter TCC, relatório, protocolo ou outro documento',
                'icon' => 'heroicon-o-document-plus',
                'url' => DocumentSubmissionResource::getUrl('create'),
                'color' => 'primary',
            ],
            [
                'label' => 'Enviar Protocolo CEUA/CEP',
                'description' => 'Submeter protocolo para avaliação antes do comitê',
                'icon' => 'heroicon-o-clipboard-document-check',
                'url' => DocumentSubmissionResource::getUrl('create') . '?document_type=' . urlencode('Protocolo CEUA/CEP'),
                'color' => 'info',
            ],
            [
                'label' => 'Falar com o Professor',
                'description' => 'Abrir um chamado na Central de Dúvidas',
                'icon' => 'heroicon-o-chat-bubble-left-right',
                'url' => SupportTicketResource::getUrl('create'),
                'color' => 'info',
            ],
            [
                'label' => 'Meus Projetos e Prazos',
                'description' => 'Ver andamento do seu TCC/IC',
                'icon' => 'heroicon-o-folder-open',
                'url' => AcademicProjectResource::getUrl('index'),
                'color' => 'warning',
            ],
            [
                'label' => 'Manual do Aluno',
                'description' => 'Normas ABNT e orientações gerais',
                'icon' => 'heroicon-o-book-open',
                'url' => ManualDoAluno::getUrl(),
                'color' => 'gray',
            ],
        ];
    }
}
