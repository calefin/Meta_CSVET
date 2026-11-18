<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;

class TrocarSenhaObrigatoria extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'trocar-senha-obrigatoria';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.trocar-senha-obrigatoria';

    protected static ?string $title = 'Defina sua Nova Senha';

    public ?array $data = [];

    public function mount(): void
    {
        if (! auth()->user()?->must_change_password) {
            redirect(\Filament\Facades\Filament::getUrl());

            return;
        }

        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('password')
                    ->label('Nova Senha')
                    ->password()
                    ->revealable()
                    ->required()
                    ->rule(PasswordRule::default())
                    ->same('passwordConfirmation'),
                TextInput::make('passwordConfirmation')
                    ->label('Confirme a Nova Senha')
                    ->password()
                    ->revealable()
                    ->required()
                    ->dehydrated(false),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        auth()->user()->forceFill([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
        ])->save();

        Notification::make()
            ->title('Senha atualizada com sucesso!')
            ->success()
            ->send();

        $this->redirect(\Filament\Facades\Filament::getUrl());
    }
}
