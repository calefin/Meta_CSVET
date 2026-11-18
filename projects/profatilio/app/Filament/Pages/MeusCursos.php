<?php

namespace App\Filament\Pages;

use App\Models\CertificateRequest;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseModule;
use App\Models\CourseModuleCompletion;
use App\Models\CourseModuleQuestion;
use App\Models\CourseModuleQuizAttempt;
use App\Models\Student;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;

class MeusCursos extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';
    protected static ?string $navigationLabel = 'Meus Cursos';
    protected static ?string $navigationGroup = 'Cursos & Certificados';
    protected static ?string $title = 'Meus Cursos';
    // Logo abaixo de "Minhas Submissões" (sort -10), à frente de tudo mais no
    // menu do aluno — destaque proposital para a nova funcionalidade.
    protected static ?int $navigationSort = -5;

    protected static string $view = 'filament.pages.meus-cursos';

    public static function getNavigationBadge(): ?string
    {
        return 'Novo';
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public ?int $activeCourseId = null;

    public ?array $cpfData = [];
    public ?array $quizData = [];
    public ?array $questionData = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'student';
    }

    public function mount(): void
    {
        $enrollment = CourseEnrollment::where('student_id', $this->student()?->id)
            ->where('status', 'em_andamento')
            ->latest()
            ->first();

        $this->activeCourseId = $enrollment?->course_id;

        $this->cpfForm->fill(['cpf' => $this->student()?->cpf]);
        $this->quizForm->fill();
        $this->questionForm->fill();
    }

    protected function getForms(): array
    {
        return ['cpfForm', 'quizForm', 'questionForm'];
    }

    public function cpfForm(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('cpf')
                    ->label('CPF')
                    ->required()
                    ->mask('999.999.999-99')
                    ->minLength(11)
                    ->helperText('Usado para emitir seu certificado ao concluir o curso.'),
            ])
            ->statePath('cpfData');
    }

    public function quizForm(Form $form): Form
    {
        $module = $this->currentModule();
        $schema = [];

        if ($module && $module->type === 'avaliacao') {
            foreach ($module->quiz_questions ?? [] as $i => $question) {
                $type = $question['type'] ?? 'multipla_escolha';

                if ($type === 'codigo') {
                    $schema[] = Placeholder::make("q{$i}_code")
                        ->label(($i + 1) . '. ' . ($question['question'] ?? ''))
                        ->content(new HtmlString('<pre class="text-xs bg-slate-950 text-teal-300 p-3 rounded-lg overflow-x-auto">' . e($question['code_snippet'] ?? '') . '</pre>'));
                    $schema[] = TextInput::make("q{$i}")
                        ->label('Sua resposta')
                        ->required();
                } else {
                    $schema[] = Radio::make("q{$i}")
                        ->label(($i + 1) . '. ' . ($question['question'] ?? ''))
                        ->options(collect($question['options'] ?? [])->mapWithKeys(fn ($opt, $idx) => [$idx => $opt]))
                        ->required();
                }
            }
        }

        return $form->schema($schema)->statePath('quizData');
    }

    public function questionForm(Form $form): Form
    {
        return $form
            ->schema([
                Textarea::make('question')
                    ->label('Sua dúvida sobre esta etapa')
                    ->rows(3)
                    ->required(),
            ])
            ->statePath('questionData');
    }

    public function student(): ?Student
    {
        return auth()->user()?->student;
    }

    public function catalog()
    {
        return Course::where('active', true)->whereHas('modules')->orderBy('name')->get();
    }

    public function enrollmentsByCourseId()
    {
        return CourseEnrollment::where('student_id', $this->student()?->id)->get()->keyBy('course_id');
    }

    public function activeEnrollment(): ?CourseEnrollment
    {
        if (! $this->activeCourseId) {
            return null;
        }

        return CourseEnrollment::with('course.modules')
            ->where('student_id', $this->student()?->id)
            ->where('course_id', $this->activeCourseId)
            ->first();
    }

    public function currentModule(): ?CourseModule
    {
        return $this->activeEnrollment()?->currentModule();
    }

    public function selectCourse(int $courseId): void
    {
        $this->activeCourseId = $courseId;
        $this->quizForm->fill();
        $this->questionForm->fill();
    }

    /**
     * Aluno pede acesso a um curso da vitrine — não matricula automaticamente,
     * apenas registra a solicitação para o professor avaliar e liberar (ou não)
     * manualmente em "Matrículas em Cursos".
     */
    public function requestEnrollment(int $courseId): void
    {
        $student = $this->student();
        $course = Course::find($courseId);

        if (! $student || ! $course) {
            return;
        }

        if (CourseEnrollment::where('student_id', $student->id)->where('course_id', $courseId)->exists()) {
            return;
        }

        CourseEnrollment::create([
            'course_id' => $courseId,
            'student_id' => $student->id,
            'status' => 'solicitado',
        ]);

        $this->notifyProfessorOfEnrollmentRequest($student, $course);

        Notification::make()
            ->title('Solicitação enviada!')
            ->body('O professor vai avaliar seu pedido de inscrição em "' . $course->name . '" e liberar o acesso em breve.')
            ->success()
            ->send();
    }

    protected function notifyProfessorOfEnrollmentRequest(Student $student, Course $course): void
    {
        try {
            $professorEmail = 'contato@profatilio.com.br';
            $studentName = $student->user->name ?? 'Aluno';

            \Illuminate\Support\Facades\Mail::to($professorEmail)->queue(
                new \App\Mail\AppNotificationMail(
                    title: 'Nova Solicitação de Inscrição em Curso',
                    messageContent: "Olá Prof. Atilio,\n\n{$studentName} solicitou inscrição no curso \"{$course->name}\".\n\nAcesse \"Matrículas em Cursos\" no painel para avaliar e liberar o acesso, se for o caso.",
                    actionUrl: url('/admin'),
                    actionText: 'Acessar Painel de Controle',
                    subject: "Nova Solicitação de Inscrição: {$course->name}"
                )
            );

            $professor = \App\Models\User::where('role', 'admin')->first();
            if ($professor && !empty($professor->telegram_chat_id)) {
                $tgMessage = "<b>🎓 Nova Solicitação de Inscrição</b>\n\n{$studentName} pediu acesso ao curso:\n<b>" . e($course->name) . "</b>\n\n<a href=\"" . url('/admin') . "\">👉 Avaliar no Painel</a>";
                app(\App\Services\TelegramService::class)->sendMessage($professor->telegram_chat_id, $tgMessage);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Erro ao notificar professor sobre solicitação de curso: ' . $e->getMessage());
        }
    }

    public function saveCpf(): void
    {
        $data = $this->cpfForm->getState();

        $this->student()->update(['cpf' => $data['cpf']]);

        Notification::make()
            ->title('CPF salvo!')
            ->success()
            ->send();
    }

    public function completeInstruction(): void
    {
        $enrollment = $this->activeEnrollment();
        $module = $enrollment?->currentModule();

        if (! $enrollment || ! $module || $module->type !== 'instrucao') {
            return;
        }

        CourseModuleCompletion::firstOrCreate(
            ['course_enrollment_id' => $enrollment->id, 'course_module_id' => $module->id],
            ['completed_at' => now()]
        );

        $this->checkCourseCompletion($enrollment);
        $this->quizForm->fill();
        $this->questionForm->fill();

        Notification::make()
            ->title('Etapa concluída!')
            ->success()
            ->send();
    }

    public function submitQuiz(): void
    {
        $enrollment = $this->activeEnrollment();
        $module = $enrollment?->currentModule();

        if (! $enrollment || ! $module || $module->type !== 'avaliacao') {
            return;
        }

        $data = $this->quizForm->getState();
        $questions = $module->quiz_questions ?? [];
        $correct = 0;

        foreach ($questions as $i => $question) {
            $type = $question['type'] ?? 'multipla_escolha';

            if ($type === 'codigo') {
                $given = mb_strtolower(trim((string) ($data["q{$i}"] ?? '')));
                $expected = mb_strtolower(trim((string) ($question['expected_answer'] ?? '')));

                if ($given !== '' && $given === $expected) {
                    $correct++;
                }

                continue;
            }

            if ((string) ($data["q{$i}"] ?? null) === (string) ($question['correct_index'] ?? null)) {
                $correct++;
            }
        }

        $total = max(count($questions), 1);
        $score = (int) round(($correct / $total) * 100);
        $passing = $module->passing_score ?: 70;
        $passed = $score > $passing;

        // Toda tentativa fica registrada, aprovada ou não — histórico completo de avaliação.
        CourseModuleQuizAttempt::create([
            'course_enrollment_id' => $enrollment->id,
            'course_module_id' => $module->id,
            'answers' => $data,
            'score' => $score,
            'passed' => $passed,
        ]);

        if ($passed) {
            CourseModuleCompletion::firstOrCreate(
                ['course_enrollment_id' => $enrollment->id, 'course_module_id' => $module->id],
                ['completed_at' => now(), 'quiz_answers' => $data, 'quiz_score' => $score]
            );

            $this->checkCourseCompletion($enrollment);
            $this->quizForm->fill();
            $this->questionForm->fill();

            Notification::make()
                ->title("Aprovado! Você acertou {$score}%.")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title("Você acertou {$score}%. É preciso mais que {$passing}% para aprovar.")
                ->body('Revise o conteúdo da etapa e tente novamente.')
                ->danger()
                ->send();
        }
    }

    public function askQuestion(): void
    {
        $module = $this->currentModule();

        if (! $module) {
            return;
        }

        $data = $this->questionForm->getState();

        if (blank($data['question'] ?? null)) {
            return;
        }

        CourseModuleQuestion::create([
            'course_module_id' => $module->id,
            'student_id' => $this->student()->id,
            'question' => $data['question'],
        ]);

        $this->questionForm->fill();

        Notification::make()
            ->title('Pergunta enviada! O professor vai responder por aqui mesmo.')
            ->success()
            ->send();
    }

    protected function checkCourseCompletion(CourseEnrollment $enrollment): void
    {
        $enrollment->refresh();
        $total = $enrollment->course->modules()->count();
        $completed = $enrollment->completions()->count();

        if ($completed >= $total && $enrollment->status !== 'concluido') {
            $enrollment->update(['status' => 'concluido', 'completed_at' => now()]);

            CertificateRequest::firstOrCreate(
                ['student_id' => $enrollment->student_id, 'course_id' => $enrollment->course_id],
                ['status' => 'pending', 'requested_at' => now()]
            );

            Notification::make()
                ->title('Parabéns! Você concluiu o curso 🎉')
                ->body('Seu certificado foi solicitado automaticamente e ficará disponível em "Meus Certificados" assim que for aprovado e assinado pelo professor.')
                ->success()
                ->persistent()
                ->send();
        }
    }
}
