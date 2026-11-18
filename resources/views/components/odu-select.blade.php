@props(['wireModel', 'odus', 'placeholder' => '— Selecione —', 'size' => 'md'])
@php
    $selectPadding = $size === 'sm' ? 'px-2 py-1.5 text-sm' : 'px-3 py-2';
@endphp
<div {{ $attributes->only('class') }} x-data="{ q: '' }">
    <input
        type="text"
        x-model="q"
        placeholder="Buscar odù (funciona sem acento)..."
        autocomplete="off"
        class="mb-1 w-full rounded border border-orisa-earth/30 px-2 py-1 text-xs"
    >
    <select wire:model="{{ $wireModel }}" class="w-full rounded border border-orisa-earth/30 {{ $selectPadding }}">
        <option value="">{{ $placeholder }}</option>
        @foreach ($odus as $odu)
            <option value="{{ $odu->id }}" x-show="oduMatch(q, @js($odu->name))">{{ $odu->name }}</option>
        @endforeach
    </select>
</div>
