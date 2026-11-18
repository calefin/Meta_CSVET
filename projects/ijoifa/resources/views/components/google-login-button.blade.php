@props(['label'])

<a
    href="{{ route('auth.google.redirect') }}"
    class="flex w-full items-center justify-center gap-2 rounded border border-orisa-earth/30 px-4 py-2 text-sm font-medium text-ebony hover:bg-sacred-sand"
>
    <svg viewBox="0 0 48 48" class="h-5 w-5" aria-hidden="true">
        <path fill="#FFC107" d="M43.61 20.08H42V20H24v8h11.3c-1.65 4.66-6.08 8-11.3 8-6.63 0-12-5.37-12-12s5.37-12 12-12c3.06 0 5.85 1.15 7.96 3.04l5.66-5.66C34.46 6.06 29.51 4 24 4 12.95 4 4 12.95 4 24s8.95 20 20 20 20-8.95 20-20c0-1.34-.14-2.65-.39-3.92z"/>
        <path fill="#FF3D00" d="M6.31 14.69l6.57 4.82C14.66 16.06 18.99 13 24 13c3.06 0 5.85 1.15 7.96 3.04l5.66-5.66C34.46 6.06 29.51 4 24 4 16.32 4 9.66 8.34 6.31 14.69z"/>
        <path fill="#4CAF50" d="M24 44c5.43 0 10.32-1.97 14.06-5.34l-6.49-5.49C29.36 34.83 26.81 36 24 36c-5.2 0-9.62-3.32-11.28-7.96l-6.52 5.03C9.5 39.55 16.18 44 24 44z"/>
        <path fill="#1976D2" d="M43.61 20.08H42V20H24v8h11.3a12.04 12.04 0 0 1-4.13 6.17l6.49 5.49C40.97 36.95 44 31.06 44 24c0-1.34-.14-2.65-.39-3.92z"/>
    </svg>
    {{ $label }}
</a>
