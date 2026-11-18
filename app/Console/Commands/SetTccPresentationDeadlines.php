<?php

namespace App\Console\Commands;

use App\Models\AcademicTitle;
use App\Models\Deadline;
use Illuminate\Console\Command;

class SetTccPresentationDeadlines extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:set-tcc-presentation-deadlines {--date=2026-09-18 : Data limite interna (Y-m-d)} {--title=Prazo Interno: TCC finalizado (Semana Acadêmica de Medicina Veterinária)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cadastra o prazo interno de finalização de TCC (15 dias antes da Semana Acadêmica) para todos os TCCs vigentes que ainda não têm esse prazo definido. Idempotente.';

    public function handle(): int
    {
        $dueDate = $this->option('date');
        $title = $this->option('title');

        $titles = AcademicTitle::where('type', 'TCC')
            ->vigente()
            ->whereDoesntHave('deadlines', function ($q) {
                $q->where('type', 'apresentacao');
            })
            ->get();

        if ($titles->isEmpty()) {
            $this->info('Nenhum TCC vigente sem prazo de apresentação definido. Nada a fazer.');
            return self::SUCCESS;
        }

        foreach ($titles as $academicTitle) {
            Deadline::create([
                'student_id' => $academicTitle->student_id,
                'academic_title_id' => $academicTitle->id,
                'type' => 'apresentacao',
                'title' => $title,
                'due_date' => $dueDate,
                'status' => 'pending',
            ]);

            $this->info("Prazo definido: {$academicTitle->student?->user?->name} — \"{$academicTitle->title}\" ({$dueDate})");
        }

        $this->info("Concluído. {$titles->count()} prazo(s) de apresentação cadastrado(s).");

        return self::SUCCESS;
    }
}
