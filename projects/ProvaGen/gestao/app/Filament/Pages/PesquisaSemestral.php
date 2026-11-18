<?php

namespace App\Filament\Pages;

use App\Models\StudentSemesterDeclaration;
use App\Support\Semester;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class PesquisaSemestral extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'pesquisa-semestral';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.pesquisa-semestral';

    protected static ?string $title = 'Sua Situação Neste Semestre';

    public ?array $data = [];

    public function mount(): void
    {
        $student = auth()->user()?->student;

        if (! $student?->needsSemesterSurvey()) {
            redirect(\Filament\Facades\Filament::getUrl());

            return;
        }

        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                CheckboxList::make('situations')
                    ->label('O que você está cursando com o professor neste semestre?')
                    ->options(StudentSemesterDeclaration::situationLabels())
                    ->columns(1)
                    ->live()
                    ->disabled(fn (Get $get) => (bool) $get('concluded'))
                    ->required(fn (Get $get) => ! $get('concluded'))
                    ->dehydrated(fn (Get $get) => ! $get('concluded')),
                Checkbox::make('concluded')
                    ->label('Já concluí o curso — não estou mais cursando nada com o professor')
                    ->helperText('Ao marcar esta opção, você não poderá mais abrir novos processos de TCC, estágio ou IC. Seus documentos e histórico continuam disponíveis para consulta.')
                    ->live()
                    ->afterStateUpdated(fn ($state, Set $set) => $state ? $set('situations', []) : null),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();
        $student = auth()->user()->student;
        $semester = Semester::current();
        $concluded = (bool) ($data['concluded'] ?? false);

        StudentSemesterDeclaration::updateOrCreate(
            ['student_id' => $student->id, 'semester' => $semester],
            [
                'situations' => $concluded ? [] : ($data['situations'] ?? []),
                'concluded' => $concluded,
                'declared_at' => now(),
            ]
        );

        $student->forceFill([
            'last_semester_declared' => $semester,
            'course_concluded_at' => $concluded ? ($student->course_concluded_at ?? now()) : $student->course_concluded_at,
        ])->save();

        Notification::make()
            ->title('Obrigado! Sua situação foi registrada.')
            ->success()
            ->send();

        $this->redirect(\Filament\Facades\Filament::getUrl());
    }
}
