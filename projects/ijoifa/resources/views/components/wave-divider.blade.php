@props(['class' => 'text-white', 'flip' => false])

<svg viewBox="0 0 1440 80" preserveAspectRatio="none" class="block h-12 w-full {{ $class }} {{ $flip ? 'rotate-180' : '' }}" aria-hidden="true">
    <path d="M0,40 C240,80 480,0 720,30 C960,60 1200,10 1440,40 L1440,80 L0,80 Z" fill="currentColor" />
</svg>
