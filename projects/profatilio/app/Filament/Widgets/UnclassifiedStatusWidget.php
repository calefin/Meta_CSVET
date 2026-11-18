<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DocumentSubmissionResource;
use App\Filament\Resources\DocumentSubmissionResource\Pages\ListDocumentSubmissions;
use App\Models\DocumentSubmission;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Rede de segurança do painel: mostra qualquer processo cujo status não se encaixe em
 * nenhuma aba conhecida de ListDocumentSubmissions (Para Habilitar / Meu Despacho /
 * Aguardando o Aluno / Concluídos). Foi assim que o status 'reviewed' da auditoria de IA
 * ficou invisível para o professor — este widget existe para que isso nunca mais passe
 * despercebido. Só aparece quando há algo de fato fora do mapa.
 */
class UnclassifiedStatusWidget extends BaseWidget
{
    protected static ?string $heading = '🚨 Status Não Reconhecido — Verifique o Fluxo';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = -1; // Acima de tudo: isso é sinal de bug, não de rotina

    public static function canView(): bool
    {
        if (auth()->user()?->role !== 'admin') {
            return false;
        }

        return ListDocumentSubmissions::unclassifiedQuery()->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ListDocumentSubmissions::unclassifiedQuery()->oldest('updated_at'))
            ->columns([
                Tables\Columns\TextColumn::make('process_id')
                    ->label('ID do Processo')
                    ->copyable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('student.user.name')
                    ->label('Aluno'),
                Tables\Columns\TextColumn::make('document_type')
                    ->badge()
                    ->label('Tipo'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color('danger')
                    ->label('Status Bruto (sem tradução)'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Desde')
                    ->since()
                    ->dateTimeTooltip()
                    ->color('danger'),
            ])
            ->actions([
                Tables\Actions\Action::make('evaluate')
                    ->label('Abrir')
                    ->icon('heroicon-o-pencil-square')
                    ->color('danger')
                    ->button()
                    ->url(fn ($record) => DocumentSubmissionResource::getUrl('edit', ['record' => $record])),
            ])
            ->recordUrl(fn ($record) => DocumentSubmissionResource::getUrl('edit', ['record' => $record]))
            ->paginated(false);
    }
}
