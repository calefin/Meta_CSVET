<div>
    <h1 class="font-display text-2xl text-ifa-green">Correcao de quizzes</h1>

    <div class="mt-4 space-y-3">
        @foreach ($submissions as $submission)
            <div class="rounded-lg border border-orisa-earth/20 bg-white p-4" wire:key="sub-{{ $submission->id }}">
                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                    <span lang="yo">{{ $submission->user->name }} — {{ $submission->lesson->title }} ({{ $submission->lesson->module->course->title }})</span>
                    <span class="text-xs {{ $submission->reviewed_at ? 'text-success' : 'text-orisa-earth' }}">
                        {{ $submission->reviewed_at ? 'Corrigido' : 'Pendente' }}
                    </span>
                </div>
                <p class="mt-2 text-sm text-ebony/80">{{ $submission->answer }}</p>

                @if ($submission->feedback)
                    <p class="mt-2 rounded bg-sacred-sand p-2 text-sm"><strong>Feedback:</strong> {{ $submission->feedback }}</p>
                @endif

                <button wire:click="openReview({{ $submission->id }})" class="mt-2 text-xs text-orun-blue hover:underline">
                    {{ $submission->feedback ? 'Editar feedback' : 'Dar feedback' }}
                </button>

                @if ($reviewingId === $submission->id)
                    <form wire:submit="saveFeedback" class="mt-2 flex gap-2">
                        <input type="text" wire:model="feedback" class="flex-1 rounded border border-orisa-earth/30 px-3 py-2 text-sm">
                        <button type="submit" class="rounded bg-ifa-green px-4 py-2 text-sm text-white hover:bg-ifa-green/90">Salvar</button>
                    </form>
                    @error('feedback') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-4">{{ $submissions->links() }}</div>
</div>
