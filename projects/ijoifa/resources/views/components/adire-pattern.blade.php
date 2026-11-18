@props(['name' => 'default', 'class' => 'text-orisa-earth/10'])

<svg class="pointer-events-none absolute inset-0 h-full w-full {{ $class }}" aria-hidden="true">
    <defs>
        <pattern id="adire-{{ $name }}" width="48" height="48" patternUnits="userSpaceOnUse">
            <path d="M24 4 L44 24 L24 44 L4 24 Z" fill="none" stroke="currentColor" stroke-width="1.5" />
            <circle cx="24" cy="24" r="4" fill="currentColor" />
        </pattern>
    </defs>
    <rect width="100%" height="100%" fill="url(#adire-{{ $name }})" />
</svg>
