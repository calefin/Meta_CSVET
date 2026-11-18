<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:send-daily-summary')->dailyAt('08:00');
Schedule::command('app:send-daily-confirmation-reminders')->dailyAt('07:30');
// app:send-deadline-reminders (cobrança trimestral por aluno, baseada no
// primeiro "Termo de Aceite de IC") foi desativado do agendamento: ele quebra
// quando um aluno tem duas ICs simultâneas (uma terminando, uma começando),
// pois sempre olha só a primeira. app:send-project-deadline-reminders (abaixo)
// já cobre o mesmo caso corretamente, por projeto (academic_title_id) e com
// cadência mensal configurável via Deadline::recurrence_days.
Schedule::command('app:send-project-deadline-reminders')->dailyAt('09:00');
Schedule::command('model:prune')->daily();
// Metanálises: busca em até 14 dias depois do registro no OSF (avisa só se faltar ≤ 3 dias ou vencer).
Schedule::command('app:alertar-prazo-busca')->dailyAt('07:45');
// Treino da triagem: fecha no prazo e libera os resultados (as páginas do treino também fecham ao abrir).
Schedule::command('app:treino-triagem --vencidos')->everyFifteenMinutes();
// app:lembrar-revisoes-artigo NÃO é agendado de propósito: o professor prefere
// enviar os lembretes de revisão de artigo manualmente (clicando no nome do
// revisor no painel). O comando continua disponível para uso avulso.
