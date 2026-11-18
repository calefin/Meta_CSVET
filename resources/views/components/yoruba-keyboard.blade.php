@props(['target'])

<div
    x-data="{
        insert(char) {
            const el = document.getElementById('{{ $target }}');
            if (! el) return;
            const start = el.selectionStart ?? el.value.length;
            const end = el.selectionEnd ?? el.value.length;
            el.value = el.value.slice(0, start) + char + el.value.slice(end);
            el.selectionStart = el.selectionEnd = start + char.length;
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.focus();
        }
    }"
    class="flex flex-wrap gap-1 rounded border border-orisa-earth/20 bg-sacred-sand p-2 font-mono text-sm"
>
    @foreach (['ẹ','Ẹ','ọ','Ọ','ṣ','Ṣ','n̄','N̄','à','á','â','ã','è','é','ê','ì','í','î','ò','ó','ô','ù','ú','û'] as $char)
        <button
            type="button"
            x-on:click="insert('{{ $char }}')"
            class="min-w-[2rem] rounded border border-orisa-earth/30 bg-white px-2 py-1 hover:bg-ifa-green hover:text-white"
        >{{ $char }}</button>
    @endforeach
</div>
