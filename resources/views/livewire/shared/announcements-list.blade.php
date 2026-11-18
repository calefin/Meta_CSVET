<div>
    @if ($announcements->isNotEmpty())
        <h2 class="font-display text-lg text-ifa-green">Avisos</h2>
        <div class="mt-2 space-y-2">
            @foreach ($announcements as $announcement)
                @php $isRead = $announcement->reads->isNotEmpty(); @endphp
                <div class="rounded border border-orisa-earth/20 bg-white p-3 text-sm" wire:key="ann-{{ $announcement->id }}">
                    <div class="flex items-center justify-between">
                        <p class="font-medium">{{ $announcement->title }}</p>
                        @if (! $isRead)
                            <button wire:click="markRead({{ $announcement->id }})" class="rounded bg-ifa-green px-3 py-1 text-xs text-white hover:bg-ifa-green/90">Ciente</button>
                        @else
                            <span class="text-xs text-success">Ciente</span>
                        @endif
                    </div>
                    <p class="mt-1 text-ebony/70">{{ $announcement->body }}</p>
                </div>
            @endforeach
        </div>
    @endif
</div>
