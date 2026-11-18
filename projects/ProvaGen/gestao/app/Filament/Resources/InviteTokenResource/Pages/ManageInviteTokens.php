<?php

namespace App\Filament\Resources\InviteTokenResource\Pages;

use App\Filament\Resources\InviteTokenResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageInviteTokens extends ManageRecords
{
    protected static string $resource = InviteTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    $data['send_email_after'] = $data['send_email'] ?? false;
                    unset($data['send_email']);
                    return $data;
                })
                ->after(function (\App\Models\InviteToken $record, array $data) {
                    if (!empty($data['send_email_after']) && !empty($record->email)) {
                        try {
                            $inviteUrl = str_starts_with(config('app.url'), 'https') 
                                ? secure_url("/invite?token={$record->token}") 
                                : url("/invite?token={$record->token}");
                            
                            $isExternal = $record->category === 'external';
                            $messageContent = $isExternal 
                                ? "Olá!\n\nVocê foi convidado pelo Prof. Dr. Atilio Calefi para se registrar na plataforma de orientação acadêmica (Aluno Externo / Mentoria) profatilio.com.br.\n\nPara realizar seu cadastro, por favor clique no botão abaixo:"
                                : "Olá!\n\nVocê foi convidado pelo Prof. Dr. Atilio Calefi para se registrar na plataforma de orientação acadêmica profatilio.com.br.\n\nPara realizar seu pré-cadastro, por favor clique no botão abaixo:";

                            \Illuminate\Support\Facades\Mail::to($record->email)->queue(
                                new \App\Mail\AppNotificationMail(
                                    title: $isExternal ? 'Convite de Cadastro - Aluno Externo' : 'Convite de Cadastro',
                                    messageContent: $messageContent,
                                    actionUrl: $inviteUrl,
                                    actionText: $isExternal ? 'Realizar Cadastro' : 'Realizar Pré-Cadastro',
                                    subject: $isExternal ? 'Convite de Cadastro (Externo) - Prof. Atilio' : 'Convite de Pré-Cadastro - Prof. Atilio'
                                )
                            );
                            
                            \Filament\Notifications\Notification::make()
                                ->title('E-mail enviado!')
                                ->body("O e-mail de convite foi enviado para {$record->email} com sucesso.")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::error("Erro ao enviar convite via InviteTokenResource: " . $e->getMessage());
                            \Filament\Notifications\Notification::make()
                                ->title('Erro ao enviar e-mail')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }
                }),
        ];
    }
}
