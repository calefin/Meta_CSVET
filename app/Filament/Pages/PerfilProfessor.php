<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;

class PerfilProfessor extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static string $view = 'filament.pages.perfil-professor';

    protected static ?string $navigationLabel = 'Perfil do Professor';

    protected static ?string $navigationGroup = 'Minha Conta';

    protected static ?string $title = 'Perfil do Professor e Integrações';

    protected static ?int $navigationSort = 1;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    public function mount(): void
    {
        $user = auth()->user();
        $metadata = $user->metadata ?? [];

        // Certifique-se de descriptografar a senha se estiver salva
        $decryptedPassword = '';
        if (!empty($metadata['certificate_a1_password'])) {
            try {
                $decryptedPassword = decrypt($metadata['certificate_a1_password']);
            } catch (\Exception $e) {
                // Em caso de erro na chave de criptografia local, deixa em branco
            }
        }

        $this->form->fill([
            'name' => $user->name,
            'email' => $user->email,
            'title' => $metadata['title'] ?? '',
            'institution' => $metadata['institution'] ?? '',
            'department' => $metadata['department'] ?? '',
            'whatsapp_number' => $metadata['whatsapp_number'] ?? '',
            'storage_provider' => $metadata['storage_provider'] ?? 'google',
            'storage_folder' => $metadata['storage_folder'] ?? '',
            'certificate_a1_path' => $metadata['certificate_a1_path'] ?? '',
            'certificate_a1_password' => $decryptedPassword,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Tabs')
                    ->tabs([
                        Tabs\Tab::make('Informações Profissionais')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Nome Completo / Profissional')
                                            ->required()
                                            ->placeholder('Ex: Prof. Dr. Atilio Sersun Calefi'),
                                        TextInput::make('email')
                                            ->label('E-mail')
                                            ->email()
                                            ->required()
                                            ->placeholder('Ex: atilio@profatilio.com.br'),
                                        TextInput::make('title')
                                            ->label('Título / Titulação Acadêmica')
                                            ->placeholder('Ex: Prof. Dr., Livre-Docente')
                                            ->helperText('Usado no cabeçalho e rodapé dos relatórios de certificação.'),
                                        TextInput::make('whatsapp_number')
                                            ->label('WhatsApp Comercial')
                                            ->placeholder('Ex: 11977998476')
                                            ->helperText('Número de WhatsApp comercial usado para o widget flutuante e notificações aos alunos.'),
                                        TextInput::make('institution')
                                            ->label('Instituição de Vínculo')
                                            ->placeholder('Ex: UNIVAP'),
                                        TextInput::make('department')
                                            ->label('Departamento / Setor')
                                            ->placeholder('Ex: Medicina Veterinária / Patologia Animal'),
                                    ]),
                            ]),
                        Tabs\Tab::make('Integração de Armazenamento Nuvem')
                            ->icon('heroicon-o-cloud')
                            ->schema([
                                Section::make('Armazenamento do Professor')
                                    ->description('Configure onde as submissões dos seus alunos e relatórios de auditoria serão organizados em sua conta de nuvem.')
                                    ->schema([
                                        Select::make('storage_provider')
                                            ->label('Provedor de Armazenamento Nuvem')
                                            ->options([
                                                'google' => 'Google Drive (Recomendado)',
                                                'onedrive' => 'Microsoft OneDrive',
                                                'icloud' => 'iCloud / Local Storage',
                                            ])
                                            ->required(),
                                        TextInput::make('storage_folder')
                                            ->label('Pasta Raiz de Destino')
                                            ->placeholder('Ex: profatilio_documentos')
                                            ->helperText('Nome da pasta raiz onde serão organizados os uploads recebidos dos alunos.'),
                                    ]),
                            ]),
                        Tabs\Tab::make('Assinatura & Certificado Digital')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Section::make('Opção Gratuita: Upload de PDF Assinado (Recomendado)')
                                    ->description('Como o Gov.br restringe integrações diretas a sistemas governamentais e não há API gratuita para processamento local de A1/A3 via navegador, o fluxo padrão consiste em assinar o PDF localmente em sua máquina (via Gov.br Assinador Desktop, Adobe Reader, ITI, etc.) e depois subir o arquivo final assinado em "Assinatura via Certificado" para homologação rápida e recalque de hashes.')
                                    ->aside(),
                                Section::make('Configurar Certificado A1 para Futuras Operações Servidor')
                                    ->description('Se desejar configurar um Certificado A1 (.pfx / .p12) para que o sistema possa assinar documentos no servidor (usando OpenSSL/PHP-PKCS7), preencha os dados abaixo. Esta funcionalidade é voltada para a futura estrutura comercial do prof-aid.com.')
                                    ->schema([
                                        FileUpload::make('certificate_a1_path')
                                            ->label('Arquivo do Certificado A1 (.pfx ou .p12)')
                                            ->acceptedFileTypes(['application/x-pkcs12'])
                                            ->directory('certificates')
                                            ->visibility('private')
                                            ->maxSize(5120),
                                        TextInput::make('certificate_a1_password')
                                            ->label('Senha do Certificado')
                                            ->password()
                                            ->nullable(),
                                    ]),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function submit()
    {
        $formData = $this->form->getState();
        $user = auth()->user();

        // Atualiza informações gerais do usuário
        $user->name = $formData['name'];
        $user->email = $formData['email'];

        // Recupera metadata anterior ou cria novo
        $metadata = $user->metadata ?? [];
        $metadata['title'] = $formData['title'] ?? '';
        $metadata['institution'] = $formData['institution'] ?? '';
        $metadata['department'] = $formData['department'] ?? '';
        $metadata['whatsapp_number'] = $formData['whatsapp_number'] ?? '';
        $metadata['storage_provider'] = $formData['storage_provider'] ?? 'google';
        $metadata['storage_folder'] = $formData['storage_folder'] ?? '';
        
        if (isset($formData['certificate_a1_path'])) {
            $metadata['certificate_a1_path'] = $formData['certificate_a1_path'];
        }
        
        if (!empty($formData['certificate_a1_password'])) {
            $metadata['certificate_a1_password'] = encrypt($formData['certificate_a1_password']);
        } else {
            $metadata['certificate_a1_password'] = null;
        }

        $user->metadata = $metadata;
        $user->save();

        \Filament\Notifications\Notification::make()
            ->title('Perfil Atualizado!')
            ->body('As informações do perfil e configurações de integração foram salvas com sucesso.')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('resendConfirmation')
                ->label('Reenviar Confirmação Geral')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Reenviar Confirmação Geral')
                ->modalDescription('Esta ação enviará novamente o e-mail com link de confirmação para todos os alunos que estão com o cadastro pendente de validação de e-mail (status: awaiting_email_validation). Deseja continuar?')
                ->modalSubmitActionLabel('Sim, reenviar para todos')
                ->modalCancelActionLabel('Cancelar')
                ->action('resendConfirmationToAll'),
        ];
    }

    public function resendConfirmationToAll(): void
    {
        $pendingStudents = \App\Models\Student::with('user')
            ->where('status', 'awaiting_email_validation')
            ->get();

        $sentCount = 0;
        foreach ($pendingStudents as $student) {
            $user = $student->user;
            if (!$user || !$user->email) {
                continue;
            }

            // Se não tiver token de ativação, gera um
            if (empty($student->activation_token)) {
                $student->activation_token = bin2hex(random_bytes(16));
                $student->save();
            }

            try {
                $activationUrl = str_starts_with(config('app.url'), 'https') 
                    ? secure_url("/activate?token={$student->activation_token}") 
                    : url("/activate?token={$student->activation_token}");
                $botUsername = ltrim(config('services.telegram.bot_username') ?? '', '@');
                $messageBody = "Olá, {$user->name}!\n\n" .
                    "Este é um lembrete para a validação do seu endereço de e-mail na plataforma profatilio.com.br. Para prosseguir e validar seu e-mail, por favor clique no botão abaixo:\n\n" .
                    "📢 **Importante: Receba Alertas Rápidos no Telegram**\n" .
                    "A plataforma agora conta com integração oficial com o Telegram! Ao vincular sua conta ao bot @{$botUsername}, você receberá notificações em tempo real direto no seu celular sempre que o professor revisar ou assinar seus documentos acadêmicos.\n\n" .
                    "Você poderá realizar a vinculação assim que sua conta for ativada pelo professor, acessando seu painel de controle.";

                \Illuminate\Support\Facades\Mail::to($user->email)->queue(
                    new \App\Mail\AppNotificationMail(
                        title: 'Ativação de Conta (Lembrete)',
                        messageContent: $messageBody,
                        actionUrl: $activationUrl,
                        actionText: 'Validar E-mail',
                        subject: 'Ativação de Conta - Plataforma Prof. Atilio'
                    )
                );

                // Update metadata
                $metadata = $user->metadata ?? [];
                $metadata['last_confirmation_email_sent_at'] = now()->toIso8601String();
                $user->metadata = $metadata;
                $user->save();

                $sentCount++;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Erro ao reenviar e-mail geral para {$user->name}: " . $e->getMessage());
            }
        }

        \Filament\Notifications\Notification::make()
            ->title('E-mails de Confirmação Reenviados!')
            ->body("Foram enfileirados e-mails de confirmação para {$sentCount} alunos com cadastro pendente.")
            ->success()
            ->send();
    }
}
