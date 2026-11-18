@php
    $url = $session->checkInUrl();
    $qrImage = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' . urlencode($url);
@endphp

<div class="flex flex-col items-center gap-4 text-center">
    <img src="{{ $qrImage }}" alt="QR Code de presença" class="rounded-lg border border-gray-200 dark:border-gray-700">

    <p class="text-sm text-gray-500 dark:text-gray-400">
        Alunos escaneiam este QR Code para confirmar presença nesta tarde. Se preferir, envie o link diretamente:
    </p>

    <input
        type="text"
        readonly
        value="{{ $url }}"
        onclick="this.select()"
        class="w-full text-xs px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-200"
    >
</div>
