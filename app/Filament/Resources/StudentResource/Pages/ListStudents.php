<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Filament\Resources\StudentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListStudents extends ListRecords
{
    protected static string $resource = StudentResource::class;

    public function getTabs(): array
    {
        if (auth()->user()->role !== 'admin') {
            return [];
        }

        return [
            'all' => Tab::make('Todos')
                ->icon('heroicon-m-users')
                ->label('Todos os Alunos'),
            'pending' => Tab::make('Pendentes de Aprovação')
                ->icon('heroicon-m-clock')
                ->label('Pendentes de Aprovação')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'awaiting_advisor_approval'))
                ->badge(\App\Models\Student::where('status', 'awaiting_advisor_approval')->count())
                ->badgeColor('warning'),
            'email_pending' => Tab::make('Aguardando Validação de E-mail')
                ->icon('heroicon-m-envelope')
                ->label('Aguardando E-mail')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'awaiting_email_validation'))
                ->badge(\App\Models\Student::where('status', 'awaiting_email_validation')->count())
                ->badgeColor('danger'),
            'active' => Tab::make('Ativos')
                ->icon('heroicon-m-check-circle')
                ->label('Ativos')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'active')),
            'external' => Tab::make('Externos')
                ->icon('heroicon-m-user-plus')
                ->label('Alunos Externos')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('category', 'external'))
                ->badge(\App\Models\Student::where('category', 'external')->count())
                ->badgeColor('warning'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\ActionGroup::make([
                Actions\Action::make('generateInvite')
                    ->label('Gerar Link de Convite')
                    ->icon('heroicon-o-link')
                    ->color('success')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('email')
                            ->label('E-mail do Orientando (Opcional)')
                            ->email()
                            ->placeholder('orientando@universidade.com'),
                        \Filament\Forms\Components\Toggle::make('send_email')
                            ->label('Enviar convite por e-mail automaticamente')
                            ->default(true)
                            ->visible(fn (\Filament\Forms\Get $get) => filled($get('email'))),
                    ])
                    ->action(function (array $data) {
                        $token = bin2hex(random_bytes(16));
                        \App\Models\InviteToken::create([
                            'token' => $token,
                            'email' => $data['email'] ?? null,
                            'category' => 'regular',
                        ]);

                        $inviteUrl = str_starts_with(config('app.url'), 'https') 
                            ? secure_url("/invite?token={$token}") 
                            : url("/invite?token={$token}");

                        $emailSent = false;
                        if (!empty($data['email']) && !empty($data['send_email'])) {
                            try {
                                $messageContent = "Olá!\n\n" .
                                    "Você foi convidado pelo Prof. Dr. Atilio Calefi para se registrar na plataforma de orientação acadêmica profatilio.com.br.\n\n" .
                                    "Para realizar seu pré-cadastro, por favor clique no botão abaixo:";

                                \Illuminate\Support\Facades\Mail::to($data['email'])->send(
                                    new \App\Mail\AppNotificationMail(
                                        title: 'Convite de Cadastro',
                                        messageContent: $messageContent,
                                        actionUrl: $inviteUrl,
                                        actionText: 'Realizar Pré-Cadastro',
                                        subject: 'Convite de Pré-Cadastro - Prof. Atilio'
                                    )
                                );
                                $emailSent = true;
                            } catch (\Throwable $e) {
                                \Illuminate\Support\Facades\Log::error("Erro ao enviar e-mail de convite individual: " . $e->getMessage());
                            }
                        }

                        $body = $emailSent 
                            ? "O convite foi enviado para o e-mail {$data['email']}.\n\nLink: {$inviteUrl}" 
                            : "Copie o link abaixo para enviar ao orientando:\n\n{$inviteUrl}";

                        \Filament\Notifications\Notification::make()
                            ->title($emailSent ? 'Convite Enviado por E-mail!' : 'Link de Convite Gerado!')
                            ->body($body)
                            ->success()
                            ->persistent()
                            ->send();
                    }),
                Actions\Action::make('generateExternalInvite')
                    ->label('Gerar Convite — Aluno Externo')
                    ->icon('heroicon-o-user-plus')
                    ->color('warning')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('email')
                            ->label('E-mail do Aluno (Opcional)')
                            ->email()
                            ->placeholder('aluno@exemplo.com'),
                        \Filament\Forms\Components\Toggle::make('send_email')
                            ->label('Enviar convite por e-mail automaticamente')
                            ->default(true)
                            ->visible(fn (\Filament\Forms\Get $get) => filled($get('email'))),
                    ])
                    ->action(function (array $data) {
                        $token = bin2hex(random_bytes(16));
                        \App\Models\InviteToken::create([
                            'token' => $token,
                            'email' => $data['email'] ?? null,
                            'category' => 'external',
                        ]);

                        $inviteUrl = str_starts_with(config('app.url'), 'https') 
                            ? secure_url("/invite?token={$token}") 
                            : url("/invite?token={$token}");

                        $emailSent = false;
                        if (!empty($data['email']) && !empty($data['send_email'])) {
                            try {
                                $messageContent = "Olá!\n\n" .
                                    "Você foi convidado pelo Prof. Dr. Atilio Calefi para se registrar na plataforma de orientação acadêmica (Aluno Externo / Mentoria) profatilio.com.br.\n\n" .
                                    "Para realizar seu cadastro, por favor clique no botão abaixo:";

                                \Illuminate\Support\Facades\Mail::to($data['email'])->send(
                                    new \App\Mail\AppNotificationMail(
                                        title: 'Convite de Cadastro - Aluno Externo',
                                        messageContent: $messageContent,
                                        actionUrl: $inviteUrl,
                                        actionText: 'Realizar Cadastro',
                                        subject: 'Convite de Cadastro (Externo) - Prof. Atilio'
                                    )
                                );
                                $emailSent = true;
                            } catch (\Throwable $e) {
                                \Illuminate\Support\Facades\Log::error("Erro ao enviar e-mail de convite externo individual: " . $e->getMessage());
                            }
                        }

                        $body = $emailSent 
                            ? "O convite de aluno externo foi enviado para o e-mail {$data['email']}.\n\nLink: {$inviteUrl}" 
                            : "Copie o link abaixo e envie ao aluno de mentoria/tutoria:\n\n{$inviteUrl}";

                        \Filament\Notifications\Notification::make()
                            ->title($emailSent ? 'Convite Externo Enviado!' : 'Convite para Aluno Externo Gerado!')
                            ->body($body)
                            ->warning()
                            ->persistent()
                            ->send();
                    })
                    ->visible(fn () => auth()->user()->role === 'admin'),
                
                Actions\Action::make('sendBulkInvites')
                    ->label('Convites em Massa')
                    ->icon('heroicon-o-envelope-open')
                    ->color('info')
                    ->form([
                        \Filament\Forms\Components\Textarea::make('emails')
                            ->label('E-mails dos Alunos (um por linha)')
                            ->required()
                            ->placeholder("aluno1@email.com\naluno2@email.com\naluno3@email.com")
                            ->rows(10),
                        \Filament\Forms\Components\Select::make('category')
                            ->label('Categoria dos Convites')
                            ->options([
                                'regular' => 'Regular (Orientando TCC/IC)',
                                'external' => 'Externo (Mentoria/Tutoria)',
                            ])
                            ->required()
                            ->default('regular'),
                    ])
                    ->action(function (array $data) {
                        $emailsRaw = explode("\n", $data['emails']);
                        $sentCount = 0;
                        $invalidEmails = [];

                        foreach ($emailsRaw as $line) {
                            $email = trim($line);
                            if (empty($email)) {
                                continue;
                            }

                            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                $invalidEmails[] = $email;
                                continue;
                            }

                            // Generate token and create record
                            $token = bin2hex(random_bytes(16));
                            \App\Models\InviteToken::create([
                                'token' => $token,
                                'email' => $email,
                                'category' => $data['category'],
                            ]);

                            $inviteUrl = str_starts_with(config('app.url'), 'https') 
                                ? secure_url("/invite?token={$token}") 
                                : url("/invite?token={$token}");

                            try {
                                $isExternal = $data['category'] === 'external';
                                $messageContent = $isExternal 
                                    ? "Olá!\n\nVocê foi convidado pelo Prof. Dr. Atilio Calefi para se registrar na plataforma de orientação acadêmica (Aluno Externo / Mentoria) profatilio.com.br.\n\nPara realizar seu cadastro, por favor clique no botão abaixo:"
                                    : "Olá!\n\nVocê foi convidado pelo Prof. Dr. Atilio Calefi para se registrar na plataforma de orientação acadêmica profatilio.com.br.\n\nPara realizar seu pré-cadastro, por favor clique no botão abaixo:";

                                \Illuminate\Support\Facades\Mail::to($email)->send(
                                    new \App\Mail\AppNotificationMail(
                                        title: $isExternal ? 'Convite de Cadastro - Aluno Externo' : 'Convite de Cadastro',
                                        messageContent: $messageContent,
                                        actionUrl: $inviteUrl,
                                        actionText: $isExternal ? 'Realizar Cadastro' : 'Realizar Pré-Cadastro',
                                        subject: $isExternal ? 'Convite de Cadastro (Externo) - Prof. Atilio' : 'Convite de Pré-Cadastro - Prof. Atilio'
                                    )
                                );
                                $sentCount++;
                            } catch (\Throwable $e) {
                                \Illuminate\Support\Facades\Log::error("Erro ao enviar e-mail em massa para {$email}: " . $e->getMessage());
                                $invalidEmails[] = $email . ' (Falha no envio)';
                            }
                        }

                        $body = "Foram processados e enviados {$sentCount} convites com sucesso.";
                        if (!empty($invalidEmails)) {
                            $body .= "\n\nOcorreram falhas ou e-mails inválidos em:\n" . implode("\n", $invalidEmails);
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Processamento de Convites em Massa Concluído!')
                            ->body($body)
                            ->color(empty($invalidEmails) ? 'success' : 'warning')
                            ->persistent()
                            ->send();
                    })
                    ->visible(fn () => auth()->user()->role === 'admin'),
                
                Actions\Action::make('sendBulkVerification')
                    ->label('Notificar Pendentes de E-mail')
                    ->icon('heroicon-o-envelope')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Enviar e-mail de validação em massa')
                    ->modalDescription(function () {
                        $count = \App\Models\Student::where('status', 'awaiting_email_validation')->count();
                        return "Deseja disparar os lembretes de validação de e-mail? Existem atualmente {$count} aluno(s) aguardando validação de e-mail.";
                    })
                    ->form([
                        \Filament\Forms\Components\Textarea::make('custom_message')
                            ->label('Mensagem Personalizada Opcional')
                            ->placeholder('Ex: Por favor, validem seus e-mails para prosseguirmos com os documentos.')
                            ->rows(3),
                        \Filament\Forms\Components\Toggle::make('force_resend')
                            ->label('Forçar reenvio (Ignorar trava de 24h)')
                            ->default(false)
                            ->helperText('Por padrão, o sistema pula alunos que receberam e-mail nas últimas 24 horas para evitar spam.'),
                    ])
                    ->action(function (array $data) {
                        $studentsCount = \App\Models\Student::where('status', 'awaiting_email_validation')->count();

                        if ($studentsCount === 0) {
                            \Filament\Notifications\Notification::make()
                                ->title('Nenhum aluno pendente')
                                ->body('Não há alunos aguardando validação de e-mail no momento.')
                                ->warning()
                                ->send();
                            return;
                        }

                        // Executa o Job de forma síncrona
                        \App\Jobs\SendBulkVerificationEmailsJob::dispatchSync(
                            $data['force_resend'] ?? false,
                            $data['custom_message'] ?? null,
                            auth()->id()
                        );

                        \Filament\Notifications\Notification::make()
                            ->title('Processamento Concluído!')
                            ->body("O envio em lote foi processado com sucesso para {$studentsCount} aluno(s).")
                            ->success()
                            ->send();
                    })
                    ->visible(fn () => auth()->user()->role === 'admin'),
            ])
            ->label('Ações de Convite / Notificação')
            ->icon('heroicon-m-envelope')
            ->color('primary')
            ->button()
        ];
    }
}

