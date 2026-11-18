<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalActivity extends Model
{
    use \App\Models\Concerns\BelongsToOwner;

    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'notes',
        'status',
        'date',
        'started_at',
        'total_seconds',
        'completed_at',
    ];

    protected $casts = [
        'date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'total_seconds' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(PersonalActivityCategory::class, 'category_id');
    }

    /**
     * Pode pertencer ao professor (Owner) ou ao aluno (AdviseeAccount) — ver
     * a migration 2026_08_24_100002.
     */
    public function user()
    {
        return $this->morphTo();
    }

    public function isRunning(): bool
    {
        return $this->started_at !== null;
    }

    /**
     * Tempo total acumulado, incluindo a sessão em andamento (se houver).
     */
    public function currentSeconds(): int
    {
        $seconds = $this->total_seconds;

        if ($this->isRunning()) {
            // $absolute=true explícito: Carbon 3 mudou o default de diffIn*()
            // para retornar diferença com sinal (negativa se started_at é no
            // passado), o que quebraria a contagem do timer.
            $seconds += now()->diffInSeconds($this->started_at, true);
        }

        return $seconds;
    }

    public function formattedTime(): string
    {
        $seconds = $this->currentSeconds();

        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    public function startTimer(): void
    {
        if ($this->isRunning()) {
            return;
        }

        $this->update([
            'started_at' => now(),
            'status' => 'in_progress',
        ]);
    }

    /**
     * Encerra a sessão de timer em andamento, somando o tempo decorrido ao
     * total acumulado. Não marca a atividade como concluída — apenas pausa.
     */
    public function stopTimer(): void
    {
        if (!$this->isRunning()) {
            return;
        }

        $this->update([
            'total_seconds' => $this->currentSeconds(),
            'started_at' => null,
            'status' => 'pending',
        ]);
    }

    public function markDone(): void
    {
        $seconds = $this->currentSeconds();

        $this->update([
            'total_seconds' => $seconds,
            'started_at' => null,
            'status' => 'done',
            'completed_at' => now(),
        ]);
    }
}
