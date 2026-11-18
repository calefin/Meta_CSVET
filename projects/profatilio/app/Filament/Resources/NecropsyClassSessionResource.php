<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NecropsyClassSessionResource\Pages;
use App\Filament\Resources\NecropsyClassSessionResource\RelationManagers;
use App\Models\NecropsyClassParticipant;
use App\Models\NecropsyClassSession;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NecropsyClassSessionResource extends Resource
{
    protected static ?string $model = NecropsyClassSession::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Frequência de Necropsia';

    protected static ?string $modelLabel = 'Aula Prática de Necropsia';

    protected static ?string $pluralModelLabel = 'Aulas Práticas de Necropsia';

    protected static ?string $navigationGroup = 'Orientação Acadêmica';

    protected static ?int $navigationSort = 42;

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Tarde de Necropsia')
                    ->description('Cada tarde ocupa por padrão das 13:00 às 17:00. Ajuste o horário se a atividade ocorrer em outro turno.')
                    ->schema([
                        Forms\Components\DatePicker::make('class_date')
                            ->label('Data')
                            ->native(false)
                            ->required()
                            ->default(now()->next('Thursday')),
                        Forms\Components\TimePicker::make('start_time')
                            ->label('Início')
                            ->seconds(false)
                            ->default('13:00')
                            ->required(),
                        Forms\Components\TimePicker::make('end_time')
                            ->label('Término')
                            ->seconds(false)
                            ->default('17:00')
                            ->required(),
                        Forms\Components\TextInput::make('location')
                            ->label('Local')
                            ->default('Complexo Veterinário - UNICSUL')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])->columns(3),

                Forms\Components\Section::make('Diário da Atividade')
                    ->description('Animais necropsiados e achados/observações do dia — usado depois no relatório de frequência.')
                    ->schema([
                        Forms\Components\Textarea::make('animals')
                            ->label('Animais Necropsiados')
                            ->rows(3)
                            ->placeholder("Ex: Canino SRD, macho, 8 anos\nFelino SRD, fêmea, 3 anos")
                            ->columnSpanFull(),
                        Forms\Components\RichEditor::make('diary')
                            ->label('Achados / Observações do Dia')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Cancelamento / Dispensa')
                    ->description('Use em feriados ou ocasiões especiais em que a atividade não ocorre.')
                    ->schema([
                        Forms\Components\Toggle::make('is_cancelled')
                            ->label('Atividade cancelada/dispensada')
                            ->live()
                            ->default(fn ($record) => (bool) $record?->cancelled_at)
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('cancellation_reason')
                            ->label('Motivo')
                            ->maxLength(255)
                            ->visible(fn (Forms\Get $get) => $get('is_cancelled')),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('class_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Horário')
                    ->state(fn (NecropsyClassSession $record) => \Carbon\Carbon::parse($record->start_time)->format('H:i') . ' - ' . \Carbon\Carbon::parse($record->end_time)->format('H:i')),
                Tables\Columns\TextColumn::make('location')
                    ->label('Local')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('attendances_count')
                    ->label('Presentes')
                    ->counts('attendances')
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('animals')
                    ->label('Animais')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('cancelled_at')
                    ->label('Cancelada')
                    ->boolean()
                    ->trueIcon('heroicon-o-x-circle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success'),
            ])
            ->defaultSort('class_date', 'desc')
            ->actions([
                Tables\Actions\Action::make('qrcode')
                    ->label('QR Code')
                    ->icon('heroicon-o-qr-code')
                    ->color('info')
                    ->modalHeading(fn (NecropsyClassSession $record) => 'QR Code de Presença — ' . $record->class_date->format('d/m/Y'))
                    ->modalContent(fn (NecropsyClassSession $record) => view('filament.necropsy-class-qrcode', ['session' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar'),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('proximaAula')
                    ->label('Ver QR Code da Próxima Aula')
                    ->icon('heroicon-o-qr-code')
                    ->color('info')
                    ->modalHeading(fn () => 'QR Code — Próxima Aula: ' . (static::nextSession()?->class_date?->format('d/m/Y') ?? ''))
                    ->modalContent(fn () => view('filament.necropsy-class-qrcode', ['session' => static::nextSession()]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->visible(fn () => static::nextSession() !== null),
                Tables\Actions\Action::make('exportarDetalhado')
                    ->label('Exportar Frequência (Detalhado)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->form([
                        Forms\Components\DatePicker::make('start_date')->label('De')->native(false)->required(),
                        Forms\Components\DatePicker::make('end_date')->label('Até')->native(false)->required()->default(now()),
                    ])
                    ->action(fn (array $data) => static::exportDetailed($data['start_date'], $data['end_date'])),
                Tables\Actions\Action::make('exportarResumo')
                    ->label('Exportar Carga Horária (Resumo)')
                    ->icon('heroicon-o-clock')
                    ->color('primary')
                    ->form([
                        Forms\Components\DatePicker::make('start_date')->label('De')->native(false)->required(),
                        Forms\Components\DatePicker::make('end_date')->label('Até')->native(false)->required()->default(now()),
                    ])
                    ->action(fn (array $data) => static::exportSummary($data['start_date'], $data['end_date'])),
            ]);
    }

    /**
     * CSV linha-a-linha: uma linha por presença, com data, horário, carga
     * horária daquela tarde e um trecho do diário/animais do dia — o que
     * permite compor o trecho de "informação do dia" pedido no certificado.
     */
    protected static function exportDetailed(string $startDate, string $endDate)
    {
        $sessions = NecropsyClassSession::whereBetween('class_date', [$startDate, $endDate])
            ->whereNull('cancelled_at')
            ->with('attendances.participant')
            ->orderBy('class_date')
            ->get();

        return response()->streamDownload(function () use ($sessions) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Nome', 'RGM', 'Instituição', 'Data', 'Horário', 'Carga Horária (h)', 'Animais / Achados do Dia']);

            foreach ($sessions as $session) {
                $excerpt = trim(strip_tags(($session->animals ? $session->animals . ' — ' : '') . ($session->diary ?? '')));
                $excerpt = mb_substr($excerpt, 0, 300);

                foreach ($session->attendances as $attendance) {
                    $participant = $attendance->participant;
                    fputcsv($handle, [
                        $participant?->name,
                        $participant?->rgm,
                        $participant?->institution,
                        $session->class_date->format('d/m/Y'),
                        \Carbon\Carbon::parse($session->start_time)->format('H:i') . '-' . \Carbon\Carbon::parse($session->end_time)->format('H:i'),
                        number_format($session->durationHours(), 2, ',', '.'),
                        $excerpt,
                    ]);
                }
            }

            fclose($handle);
        }, 'frequencia_necropsia_detalhado_' . now()->format('Ymd_His') . '.csv');
    }

    /**
     * CSV resumido: uma linha por participante, com total de presenças e
     * carga horária somada no período — pronto para embasar o certificado.
     */
    protected static function exportSummary(string $startDate, string $endDate)
    {
        $sessions = NecropsyClassSession::whereBetween('class_date', [$startDate, $endDate])
            ->whereNull('cancelled_at')
            ->with('attendances.participant')
            ->get();

        $summary = [];
        foreach ($sessions as $session) {
            $hours = $session->durationHours();
            foreach ($session->attendances as $attendance) {
                $participant = $attendance->participant;
                if (!$participant) {
                    continue;
                }
                if (!isset($summary[$participant->id])) {
                    $summary[$participant->id] = [
                        'name' => $participant->name,
                        'rgm' => $participant->rgm,
                        'institution' => $participant->institution,
                        'sessions' => 0,
                        'hours' => 0,
                    ];
                }
                $summary[$participant->id]['sessions']++;
                $summary[$participant->id]['hours'] += $hours;
            }
        }

        return response()->streamDownload(function () use ($summary) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Nome', 'RGM', 'Instituição', 'Tardes Presentes', 'Carga Horária Total (h)']);

            foreach ($summary as $row) {
                fputcsv($handle, [
                    $row['name'],
                    $row['rgm'],
                    $row['institution'],
                    $row['sessions'],
                    number_format($row['hours'], 2, ',', '.'),
                ]);
            }

            fclose($handle);
        }, 'frequencia_necropsia_resumo_' . now()->format('Ymd_His') . '.csv');
    }

    protected static function nextSession(): ?NecropsyClassSession
    {
        return NecropsyClassSession::whereNull('cancelled_at')
            ->whereDate('class_date', '>=', now()->toDateString())
            ->orderBy('class_date')
            ->first();
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AttendancesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNecropsyClassSessions::route('/'),
            'create' => Pages\CreateNecropsyClassSession::route('/create'),
            'view' => Pages\ViewNecropsyClassSession::route('/{record}'),
            'edit' => Pages\EditNecropsyClassSession::route('/{record}/edit'),
        ];
    }
}
