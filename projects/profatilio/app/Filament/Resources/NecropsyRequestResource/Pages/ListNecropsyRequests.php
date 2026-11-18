<?php

namespace App\Filament\Resources\NecropsyRequestResource\Pages;

use App\Filament\Resources\NecropsyRequestResource;
use App\Models\NecropsyRequest;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Str;

class ListNecropsyRequests extends ListRecords
{
    protected static string $resource = NecropsyRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Link fixo: para mandar a quem combinou a necropsia direto com o professor.
            Actions\Action::make('directLink')
                ->label('Link direto (combinado)')
                ->icon('heroicon-o-link')
                ->color('gray')
                ->action(function (): void {
                    Notification::make()
                        ->title('Link direto da necropsia')
                        ->body("Copie a mensagem abaixo e envie pelo WhatsApp:\n\n" . NecropsyRequest::directLinkMessage())
                        ->success()
                        ->persistent()
                        ->send();
                }),

            // Termo já com o valor combinado: cria o pedido e devolve o link pessoal do termo.
            Actions\Action::make('generateAgreedTerm')
                ->label('Termo com valor combinado')
                ->icon('heroicon-o-document-plus')
                ->modalHeading('Gerar termo de necropsia já combinado')
                ->modalDescription('Cria o pedido com o valor que você combinou e gera o link do termo para a pessoa assinar.')
                ->modalSubmitActionLabel('Gerar link')
                ->form([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('requester_name')->label('Nome do responsável')->required(),
                        Forms\Components\TextInput::make('requester_phone')->label('WhatsApp (com DDD)')->tel()->required(),
                        Forms\Components\TextInput::make('species')->label('Espécie')->required()->datalist(['Cão', 'Gato', 'Ave', 'Réptil', 'Roedor']),
                        Forms\Components\TextInput::make('weight')->label('Peso (kg)')->numeric()->minValue(0.01)->required()->live(onBlur: true),
                        Forms\Components\TextInput::make('final_price')
                            ->columnSpanFull()
                            ->label('Valor combinado (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->helperText(function (Forms\Get $get): string {
                                $weight = (float) $get('weight');
                                if ($weight <= 0) {
                                    return 'Deixe em branco para usar a tabela.';
                                }
                                $p = NecropsyRequest::calculatePricing($weight, false);

                                return 'Em branco = tabela: R$ ' . number_format($p['final_price'], 2, ',', '.');
                            }),
                    ]),
                ])
                ->action(function (array $data): void {
                    $pricing = NecropsyRequest::calculatePricing((float) $data['weight'], false);

                    if (filled($data['final_price'] ?? null)) {
                        $agreed = round((float) $data['final_price'], 2);
                        $pricing = [
                            'price' => max($pricing['price'], $agreed),
                            'discount' => max(0, $pricing['price'] - $agreed),
                            'final_price' => $agreed,
                        ];
                    }

                    $record = NecropsyRequest::create(array_merge($pricing, [
                        'token' => Str::random(32),
                        'requester_name' => $data['requester_name'],
                        'requester_phone' => $data['requester_phone'],
                        'species' => $data['species'],
                        'weight' => $data['weight'],
                        'status' => 'quote_generated',
                        'contacted_at' => now(),
                    ]));

                    $message = $record->authorizationMessage();
                    $whatsapp = $record->whatsappUrl($message);

                    Notification::make()
                        ->title('Link do termo gerado')
                        ->body("Copie a mensagem abaixo e envie para {$record->requester_name}:\n\n{$message}")
                        ->success()
                        ->persistent()
                        ->actions($whatsapp ? [
                            NotificationAction::make('whatsapp')->label('Abrir no WhatsApp')->url($whatsapp, shouldOpenInNewTab: true)->button(),
                        ] : [])
                        ->send();
                }),

            Actions\CreateAction::make(),
        ];
    }
}
