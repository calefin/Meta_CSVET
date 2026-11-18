<?php

namespace App\Filament\Resources\SystematicReviewResource\Pages;

use App\Filament\Resources\SystematicReviewResource;
use App\Services\SystematicReviewProtocolService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListSystematicReviews extends ListRecords
{
    protected static string $resource = SystematicReviewResource::class;

    protected function getHeaderActions(): array
    {
        $awaiting = count(\App\Filament\Pages\CentralMetanalises::awaitingReplyIds());

        return [
            // Atalho para ler as conversas de todos os projetos sem abrir um por um (a mais nova primeiro).
            Actions\Action::make('todasConversas')
                ->label('💬 Todas as conversas' . ($awaiting > 0 ? " ({$awaiting} aguardando)" : ''))
                ->color($awaiting > 0 ? 'warning' : 'gray')
                ->url(\App\Filament\Pages\CentralMetanalises::getUrl(['ver' => 'conversas'])),
            Actions\Action::make('importarProtocolos')
                ->label('Importar protocolos (pipeline JSON)')
                ->icon('heroicon-o-document-arrow-up')
                ->color('primary')
                ->modalDescription('Envie de uma vez os pipeline_P01.json … pipeline_P20.json (pasta 04_Pipeline_JSON_para_o_site). Cada arquivo cria ou atualiza a revisão do seu código e a liga ao projeto da seleção de alunos (P01–P10 → PET-01…10; P11–P20 → PAT-01…10). Protocolos já congelados não são alterados. Os títulos reais ficam só no banco do servidor.')
                ->form([
                    Forms\Components\FileUpload::make('files')->label('Arquivos pipeline_PXX.json')
                        ->multiple()->maxFiles(40)
                        ->disk('local')->directory('systematic_reviews/tmp')->visibility('private')
                        ->storeFileNamesIn('names')
                        ->acceptedFileTypes(['application/json', 'text/plain', 'text/json'])->maxSize(5120)->required(),
                ])
                ->action(function (array $data) {
                    $disk = Storage::disk('local');
                    $service = app(SystematicReviewProtocolService::class);
                    $created = $updated = [];
                    $errors = [];
                    foreach ((array) $data['files'] as $path) {
                        $name = $data['names'][$path] ?? basename($path);
                        try {
                            if (str_contains(mb_strtolower($name), 'manifest')) {
                                continue;
                            }
                            [$review, $isNew] = $service->import($disk->get($path));
                            $isNew ? $created[] = $review->protocol_code : $updated[] = $review->protocol_code;
                        } catch (\RuntimeException $e) {
                            $errors[] = "{$name}: {$e->getMessage()}";
                        } finally {
                            $disk->delete($path);
                        }
                    }
                    sort($created);
                    sort($updated);
                    Notification::make()
                        ->title(count($created) . ' criados · ' . count($updated) . ' atualizados' . ($errors ? ' · ' . count($errors) . ' com erro' : ''))
                        ->body(trim(($created ? 'Criados: ' . implode(', ', $created) . "\n" : '') . ($updated ? 'Atualizados: ' . implode(', ', $updated) . "\n" : '') . implode("\n", $errors)))
                        ->color($errors ? 'warning' : 'success')
                        ->persistent()
                        ->send();
                }),
            Actions\Action::make('kit')
                ->label('Baixar kit de importação (script + modelos)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(route('systematic-review.kit')),
            Actions\CreateAction::make()->label('Novo projeto'),
        ];
    }
}
