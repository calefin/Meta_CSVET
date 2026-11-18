@php
    $adminUser = null;
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('users')) {
            $adminUser = \App\Models\User::where('role', 'admin')->first();
        }
    } catch (\Exception $e) {
        // Safely ignore database/migration errors in tests or early setup
    }
    
    $adminName = $adminUser?->name ?? 'Prof. Dr. Atilio Calefi';
    
    if ($adminUser && !empty($adminUser->metadata['title'])) {
        $adminDisplayName = $adminUser->metadata['title'] . ' ' . $adminUser->name;
    } else {
        $adminDisplayName = $adminName;
    }

    $rawPhone = ($adminUser && !empty($adminUser->metadata['whatsapp_number'])) 
        ? $adminUser->metadata['whatsapp_number'] 
        : config('services.whatsapp.phone');
        
    $whatsappPhone = preg_replace('/\D/', '', $rawPhone);
    if (strlen($whatsappPhone) >= 10 && strlen($whatsappPhone) <= 11 && !str_starts_with($whatsappPhone, '55')) {
        $whatsappPhone = '55' . $whatsappPhone;
    }
    
    // Extrai a inicial do primeiro nome, limpando títulos acadêmicos comuns
    $cleanNameForInitial = preg_replace('/^(Prof\.\s+Dr\.\s+|Dr\.\s+|Prof\.\s+)/i', '', $adminDisplayName);
    $avatarInitial = mb_substr($cleanNameForInitial, 0, 1, 'UTF-8') ?: 'A';
@endphp

<div class="fixed bottom-6 right-6 z-50 font-sans" x-data="{ open: false, tooltip: false }">
    <!-- Tooltip -->
    <div x-show="tooltip && !open" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-1"
         class="absolute bottom-16 right-0 mb-2 whitespace-nowrap bg-slate-900 border border-white/10 text-white text-xs py-1.5 px-3 rounded-lg shadow-xl"
         style="display: none;">
        Fale com o Prof. Atilio no WhatsApp
    </div>

    <!-- Chat Box -->
    <div x-show="open" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-90 translate-y-10"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-90 translate-y-10"
         class="absolute bottom-20 right-0 w-80 sm:w-96 bg-slate-900/90 backdrop-blur-xl border border-white/10 rounded-2xl overflow-hidden shadow-2xl text-slate-200"
         style="display: none;">
         
        <!-- Header -->
        <div class="p-4 bg-gradient-to-r from-teal-950 via-slate-900 to-indigo-950 border-b border-white/10 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="relative">
                    @if($adminUser && $adminUser->profile_photo_path)
                        <img src="{{ asset('storage/' . $adminUser->profile_photo_path) }}" class="w-10 h-10 rounded-full object-cover border border-white/20 shadow-inner" alt="{{ $adminDisplayName }}">
                    @else
                        <div class="w-10 h-10 rounded-full bg-teal-800 flex items-center justify-center text-white font-bold text-lg select-none">
                            {{ mb_strtoupper($avatarInitial) }}
                        </div>
                    @endif
                    <span class="absolute bottom-0 right-0 w-3 h-3 bg-emerald-500 border-2 border-slate-900 rounded-full"></span>
                </div>
                <div class="text-left">
                    <h4 class="text-sm font-semibold text-white">{{ $adminDisplayName }}</h4>
                    <p class="text-xs text-emerald-400 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        Online
                    </p>
                </div>
            </div>
            <button @click="open = false" class="text-slate-400 hover:text-white transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Body -->
        <div class="p-4 space-y-3 bg-slate-950/70 text-left">
            <div class="bg-slate-900/90 border border-white/5 rounded-2xl rounded-tl-none p-3.5 text-xs text-slate-300 leading-relaxed shadow-inner">
                Olá! Seja bem-vindo(a) ao meu portal acadêmico e pericial. 👋
                <br><br>
                Precisa de mentoria para TCC/IC, revisão científica nas normas ABNT ou exames de necropsia? Clique abaixo para iniciar nossa conversa.
            </div>
        </div>

        <!-- Footer -->
        <div class="p-3 bg-slate-950 border-t border-white/5 text-center">
            <a href="https://wa.me/{{ $whatsappPhone }}?text={{ urlencode('Olá ' . $adminDisplayName . ', vim do site e gostaria de tirar uma dúvida sobre seus serviços.') }}" 
               target="_blank" 
               rel="noopener"
               class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 text-white text-xs font-semibold rounded-xl transition duration-200 shadow-lg shadow-emerald-950/20">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 fill-current" viewBox="0 0 24 24">
                    <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.514 2.266 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005-.001-3.973-.502-5.724-1.458L0 24zm6.59-4.846c1.6.95 3.188 1.449 4.825 1.451 5.436 0 9.86-4.42 9.864-9.864.002-2.637-1.03-5.114-2.905-6.99C16.457 1.875 13.99 1.838 11.999 1.838c-5.437 0-9.862 4.42-9.866 9.864-.001 1.702.463 3.361 1.34 4.8l-.996 3.633 3.72-.975zm13.14-5.32c-.31-.156-1.834-.905-2.119-1.01-.284-.104-.49-.156-.697.156-.206.31-.8.984-.98 1.192-.18.207-.36.233-.67.078-.31-.156-1.309-.483-2.493-1.54-1.002-.894-1.68-2.001-1.876-2.338-.196-.337-.02-.52.135-.675.14-.14.31-.36.466-.54.156-.18.208-.31.31-.518.104-.207.052-.39-.026-.544-.078-.156-.697-1.68-.956-2.304-.252-.607-.51-.52-.697-.53-.18-.008-.387-.01-.594-.01-.207 0-.543.078-.827.39-.284.31-1.086 1.062-1.086 2.59 0 1.528 1.112 3.003 1.267 3.21.155.208 2.188 3.34 5.3 4.68 1.26.543 2.003.708 2.686.62.784-.117 2.417-.985 2.753-1.939.336-.953.336-1.77.235-1.94-.101-.17-.37-.272-.68-.429z"/>
                </svg>
                Iniciar Conversa
            </a>
        </div>
    </div>

    <!-- Floating Toggle Button -->
    <button @click="open = !open" 
            @mouseenter="tooltip = true" 
            @mouseleave="tooltip = false"
            class="flex items-center justify-center w-14 h-14 rounded-full bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 text-white shadow-2xl transition duration-300 hover:scale-110 active:scale-95 focus:outline-none relative group"
            :class="{ 'rotate-90': open }">
        <!-- Floating pulse effect -->
        <span class="absolute inset-0 rounded-full bg-emerald-500/30 animate-ping opacity-75 group-hover:opacity-100 transition-opacity"></span>
        
        <!-- Toggle Icons -->
        <svg x-show="!open" xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 fill-current relative z-10" viewBox="0 0 24 24">
            <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.514 2.266 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005-.001-3.973-.502-5.724-1.458L0 24zm6.59-4.846c1.6.95 3.188 1.449 4.825 1.451 5.436 0 9.86-4.42 9.864-9.864.002-2.637-1.03-5.114-2.905-6.99C16.457 1.875 13.99 1.838 11.999 1.838c-5.437 0-9.862 4.42-9.866 9.864-.001 1.702.463 3.361 1.34 4.8l-.996 3.633 3.72-.975zm13.14-5.32c-.31-.156-1.834-.905-2.119-1.01-.284-.104-.49-.156-.697.156-.206.31-.8.984-.98 1.192-.18.207-.36.233-.67.078-.31-.156-1.309-.483-2.493-1.54-1.002-.894-1.68-2.001-1.876-2.338-.196-.337-.02-.52.135-.675.14-.14.31-.36.466-.54.156-.18.208-.31.31-.518.104-.207.052-.39-.026-.544-.078-.156-.697-1.68-.956-2.304-.252-.607-.51-.52-.697-.53-.18-.008-.387-.01-.594-.01-.207 0-.543.078-.827.39-.284.31-1.086 1.062-1.086 2.59 0 1.528 1.112 3.003 1.267 3.21.155.208 2.188 3.34 5.3 4.68 1.26.543 2.003.708 2.686.62.784-.117 2.417-.985 2.753-1.939.336-.953.336-1.77.235-1.94-.101-.17-.37-.272-.68-.429z"/>
        </svg>
        <svg x-show="open" xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 relative z-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="display: none;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>
</div>
