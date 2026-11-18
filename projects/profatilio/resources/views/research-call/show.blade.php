@php
    use App\Models\ResearchApplication;
    use App\Models\ResearchCall;

    $open = $call->isOpen();
    $byAxis = $call->projectsByAxis(onlyOpen: true);
    $steps = collect($call->steps ?? [])->filter(fn ($s) => filled($s['title'] ?? null))->values();
    $phases = $steps->groupBy(fn ($s) => $s['phase'] ?? 'Etapas');
    $projectCount = count($call->projects ?? []);
    $commitments = $call->texts('commitments');
    $input = 'w-full px-4 py-3 rounded-xl bg-dark-950 border border-white/10 text-white placeholder-slate-500 focus:outline-none focus:border-brand-teal text-sm transition-colors';
    $label = 'block text-sm font-semibold text-slate-200 mb-1.5';
    $hint = 'text-xs text-slate-500 mt-1';
    $err = 'text-xs text-rose-400 mt-1';
    $panel = 'rounded-3xl border border-white/10 bg-white/[0.03] p-6 sm:p-8';
    $n = 0;
@endphp
@extends('research-call._layout', [
    'pageTitle' => $call->title,
    'pageDescription' => \Illuminate\Support\Str::limit($call->headline ?? '', 155),
    'canonical' => $call->publicUrl(),
    'indexable' => $call->status === ResearchCall::STATUS_OPEN,
])

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6">

    @if($call->status === ResearchCall::STATUS_DRAFT)
        <div class="mb-8 rounded-2xl border border-amber-400/40 bg-amber-400/10 px-5 py-3 text-sm text-amber-200">
            <strong>Pré-visualização:</strong> esta chamada está em rascunho e só você (professor) consegue ver. Mude o status para "Inscrições abertas" no painel para publicar.
        </div>
    @endif

    {{-- Topo --}}
    <section class="text-center max-w-3xl mx-auto">
        <span class="text-xs font-bold text-brand-teal uppercase tracking-widest font-outfit">Seleção de alunos · Pesquisa</span>
        <h1 class="font-outfit font-extrabold text-3xl sm:text-5xl text-white mt-2 leading-tight">{{ $call->title }}</h1>
        <p class="mt-3 text-sm text-slate-400">Orientador: <strong class="text-white">Prof. Dr. Atilio Sersun Calefi</strong></p>
        @if($call->headline)
            <p class="text-slate-300 mt-5 text-base sm:text-lg font-light leading-relaxed">{{ $call->headline }}</p>
        @endif

        <div class="mt-6 mx-auto max-w-2xl rounded-2xl border border-emerald-400/30 bg-emerald-500/10 px-5 py-4 text-left text-sm text-emerald-50">
            <strong class="block text-emerald-300">Participação sem nenhum custo para o aluno</strong>
            Não há taxa de inscrição, mensalidade nem cobrança de qualquer tipo. O que conta é o seu compromisso: dedicação semanal, prazos cumpridos e sigilo. A continuidade no projeto depende disso.
        </div>

        <div class="mt-6 flex flex-wrap items-center justify-center gap-2 text-sm">
            @if($open)
                <span class="inline-flex items-center gap-2 rounded-full bg-emerald-500/15 border border-emerald-400/30 px-4 py-1.5 font-semibold text-emerald-300">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    Inscrições abertas{{ $call->closes_at ? ' até ' . $call->closes_at->format('d/m/Y') : '' }}
                </span>
            @else
                <span class="inline-flex items-center gap-2 rounded-full bg-slate-500/15 border border-slate-400/30 px-4 py-1.5 font-semibold text-slate-300">Inscrições encerradas</span>
            @endif
        </div>

        <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
            @if($open)
                <a href="#inscricao" class="inline-flex justify-center items-center gap-2 px-6 py-3.5 rounded-xl bg-gradient-to-r from-brand-teal to-brand-indigo text-white font-bold shadow-lg shadow-brand-teal/25 hover:brightness-110 transition">Quero me inscrever</a>
            @endif
            <a href="#passo-a-passo" class="inline-flex justify-center items-center gap-2 px-6 py-3.5 rounded-xl border border-white/15 text-slate-200 font-semibold hover:bg-white/5 transition">Ver o passo a passo</a>
        </div>

        <dl class="mt-10 grid grid-cols-2 sm:grid-cols-4 gap-3 text-left">
            @foreach([
                [$projectCount, 'projetos'],
                ['R$ 0', 'sem custo para o aluno'],
                [$call->min_weekly_hours . '–6 h', 'por semana, remoto'],
                ['2', 'artigos por aluno'],
            ] as [$value, $caption])
                <div class="rounded-2xl border border-white/10 bg-white/[0.03] px-4 py-3">
                    <dt class="sr-only">{{ $caption }}</dt>
                    <dd class="font-outfit font-extrabold text-2xl text-white">{{ $value }}</dd>
                    <dd class="text-xs text-slate-400">{{ $caption }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- Apresentação + como funciona a dupla --}}
    <section class="mt-16 grid lg:grid-cols-5 gap-6">
        <div class="lg:col-span-3 {{ $panel }}">
            <h2 class="font-outfit font-bold text-xl text-white">Sobre a seleção</h2>
            <div class="mt-3 space-y-3 text-slate-300 text-sm leading-relaxed">
                @foreach(preg_split('/\n\s*\n/', trim((string) $call->intro)) as $para)
                    @if(trim($para) !== '')<p>{{ $para }}</p>@endif
                @endforeach
            </div>
            @if($call->texts('benefits'))
                <h3 class="font-outfit font-bold text-white mt-6">O que você ganha</h3>
                <ul class="mt-3 space-y-2">
                    @foreach($call->texts('benefits') as $b)
                        <li class="flex gap-3 text-sm text-slate-300"><span class="text-brand-teal font-bold" aria-hidden="true">✓</span><span>{{ $b }}</span></li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div class="lg:col-span-2 {{ $panel }}">
            <h2 class="font-outfit font-bold text-xl text-white">Como funciona a dupla</h2>
            <p class="mt-3 text-sm text-slate-400">Cada aluno trabalha em dois projetos ao mesmo tempo:</p>
            <div class="mt-5 space-y-3">
                <div class="rounded-2xl border border-brand-teal/30 bg-brand-teal/10 p-4">
                    <div class="text-xs font-bold uppercase tracking-wider text-brand-teal">Projeto A · você lidera</div>
                    <div class="text-white font-semibold mt-1">1º autor</div>
                    <p class="text-xs text-slate-300 mt-1">Faz a triagem, a extração de dados e escreve o artigo com o professor.</p>
                </div>
                <div class="rounded-2xl border border-brand-indigo/30 bg-brand-indigo/10 p-4">
                    <div class="text-xs font-bold uppercase tracking-wider text-indigo-300">Projeto B · você é revisor cego</div>
                    <div class="text-white font-semibold mt-1">Coautor</div>
                    <p class="text-xs text-slate-300 mt-1">Faz a triagem em paralelo com o líder, sem ver os votos dele, e revisa o texto final.</p>
                </div>
            </div>
            <p class="mt-4 text-xs text-slate-500">"Cego" quer dizer que cada um avalia os artigos sozinho. As discordâncias são resolvidas pelo professor. É o padrão exigido pelas revistas.</p>
        </div>
    </section>

    {{-- Passo a passo --}}
    @if($steps->isNotEmpty())
    <section id="passo-a-passo" class="mt-16">
        <div class="text-center max-w-2xl mx-auto">
            <span class="text-xs font-bold text-brand-teal uppercase tracking-widest font-outfit">Etapas</span>
            <h2 class="font-outfit font-extrabold text-2xl sm:text-3xl text-white mt-1">Passo a passo, da inscrição ao artigo</h2>
            <p class="text-slate-400 mt-2 text-sm">Você recebe um aviso por e-mail a cada etapa e acompanha tudo pelo link pessoal que chega depois da inscrição.</p>
        </div>
        <div class="mt-10 grid md:grid-cols-2 gap-6">
            @foreach($phases as $phase => $items)
                <div class="{{ $panel }}">
                    <h3 class="font-outfit font-bold text-lg text-white">{{ $phase }}</h3>
                    <ol class="mt-5 space-y-5">
                        @foreach($items as $step)
                            @php $n++; @endphp
                            <li class="flex gap-4">
                                <span class="shrink-0 w-9 h-9 rounded-full bg-gradient-to-br from-brand-teal to-brand-indigo text-white font-bold text-sm flex items-center justify-center">{{ $n }}</span>
                                <div>
                                    <div class="flex flex-wrap items-baseline gap-x-2">
                                        <span class="font-semibold text-white">{{ $step['title'] }}</span>
                                        @if(filled($step['when'] ?? null))<span class="text-xs text-brand-teal font-semibold">{{ $step['when'] }}</span>@endif
                                    </div>
                                    @if(filled($step['text'] ?? null))<p class="text-sm text-slate-400 mt-1 leading-relaxed">{{ $step['text'] }}</p>@endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endforeach
        </div>
    </section>
    @endif

    {{-- Projetos --}}
    @if($projectCount)
    <section id="projetos" class="mt-16">
        <div class="text-center max-w-2xl mx-auto">
            <span class="text-xs font-bold text-brand-teal uppercase tracking-widest font-outfit">Projetos</span>
            <h2 class="font-outfit font-extrabold text-2xl sm:text-3xl text-white mt-1">Áreas dos projetos</h2>
            <p class="text-slate-400 mt-2 text-sm">Para proteger as ideias antes da publicação, cada projeto aparece só com o código e a área. O título e o protocolo completo aparecem no Portal do Aluno depois que você assina o termo de confidencialidade.</p>
        </div>
        <div class="mt-10 grid lg:grid-cols-3 gap-6">
            @foreach($byAxis as $name => $group)
                <div class="{{ $panel }}">
                    <h3 class="font-outfit font-bold text-white">{{ $name }}</h3>
                    @if(filled($group['axis']['description'] ?? null))<p class="text-xs text-slate-400 mt-2 leading-relaxed">{{ $group['axis']['description'] }}</p>@endif
                    <ul class="mt-4 space-y-2">
                        @foreach($group['projects'] as $p)
                            <li class="flex gap-3 text-sm"><span class="shrink-0 self-start font-mono text-xs font-bold text-brand-teal bg-brand-teal/10 rounded-md px-2 py-1">{{ $p['code'] }}</span><span class="text-slate-300 pt-0.5">{{ $p['area'] }}@if (!empty($p['half'])) <span class="ml-1 text-xs text-amber-300">(1 vaga)</span>@endif</span></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </section>
    @endif

    {{-- Requisitos e compromissos --}}
    <section id="requisitos" class="mt-16 grid md:grid-cols-2 gap-6">
        <div class="{{ $panel }}">
            <h2 class="font-outfit font-bold text-xl text-white">Pré-requisitos</h2>
            <p class="text-xs text-slate-400 mt-1">O formulário confere os requisitos automaticamente.</p>
            <ul class="mt-4 space-y-3">
                @foreach($call->texts('requirements') as $r)
                    <li class="flex gap-3 text-sm text-slate-300"><span class="text-emerald-400 font-bold" aria-hidden="true">✓</span><span>{{ $r }}</span></li>
                @endforeach
            </ul>
        </div>
        <div class="{{ $panel }}">
            <h2 class="font-outfit font-bold text-xl text-white">Condições e compromissos</h2>
            <p class="text-xs text-slate-400 mt-1">Na inscrição você confirma cada um destes compromissos.</p>
            <ul class="mt-4 space-y-3">
                @foreach($commitments as $c)
                    <li class="flex gap-3 text-sm text-slate-300"><span class="text-amber-300 font-bold" aria-hidden="true">!</span><span>{{ $c }}</span></li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Natureza da participação e LGPD --}}
    <section id="natureza" class="mt-6 grid md:grid-cols-2 gap-6">
        <div class="{{ $panel }}">
            <h2 class="font-outfit font-bold text-xl text-white">Natureza da participação</h2>
            <p class="text-xs text-slate-400 mt-1">Mentoria científica voluntária · sem bolsa · sem horário fixo · sem vínculo empregatício</p>
            <p class="mt-4 text-sm text-slate-300 leading-relaxed">{{ \App\Models\ResearchCall::NATURE_TEXT }}</p>
        </div>
        <div class="{{ $panel }}">
            <h2 class="font-outfit font-bold text-xl text-white">Proteção de dados (LGPD)</h2>
            <p class="text-xs text-slate-400 mt-1">Lei nº 13.709/2018 · seleção feita pelo professor, sem inteligência artificial</p>
            <p class="mt-4 text-sm text-slate-300 leading-relaxed">{{ \App\Models\ResearchCall::LGPD_TEXT }}</p>
        </div>
    </section>

    {{-- Perguntas frequentes --}}
    @if(!empty($call->faq))
    <section id="duvidas" class="mt-16 max-w-3xl mx-auto">
        <h2 class="font-outfit font-extrabold text-2xl text-white text-center">Perguntas frequentes</h2>
        <div class="mt-6 space-y-3">
            @foreach($call->faq as $item)
                @continue(blank($item['q'] ?? null))
                <details class="group rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-4">
                    <summary class="cursor-pointer list-none flex justify-between gap-4 font-semibold text-white text-sm">
                        {{ $item['q'] }}<span class="text-brand-teal transition-transform group-open:rotate-45" aria-hidden="true">+</span>
                    </summary>
                    <p class="mt-3 text-sm text-slate-400 leading-relaxed">{{ $item['a'] ?? '' }}</p>
                </details>
            @endforeach
        </div>
    </section>
    @endif

    {{-- Formulário --}}
    <section id="inscricao" class="mt-16 max-w-3xl mx-auto">
        <div class="text-center">
            <span class="text-xs font-bold text-brand-teal uppercase tracking-widest font-outfit">Inscrição</span>
            <h2 class="font-outfit font-extrabold text-2xl sm:text-3xl text-white mt-1">Formulário de inscrição</h2>
            <p class="text-slate-400 mt-2 text-sm">Leva uns 5 minutos. Depois de enviar, você recebe por e-mail a confirmação e o link para acompanhar a seleção.</p>
        </div>

        @if(!$open)
            <div class="mt-8 {{ $panel }} text-center text-slate-300">
                As inscrições desta chamada estão encerradas. Se você já se inscreveu, use o link de acompanhamento que chegou no seu e-mail.
            </div>
        @else
        <form method="POST" action="{{ route('research-call.store', $call->slug) }}" class="mt-8 space-y-6"
              x-data="{
                  course: @js(old('course', '')),
                  semester: @js(old('semester', '')),
                  hours: @js(old('weekly_hours', '')),
                  projects: @js(array_values((array) old('projects', []))),
                  courses: @js($call->eligibleCourses()),
                  minSemester: {{ (int) $call->min_semester }},
                  minHours: {{ (int) $call->min_weekly_hours }},
                  total: {{ count($commitments) + 1 }},
                  accepted: {{ count(array_filter((array) old('commitments', []))) + (old('adhesion') ? 1 : 0) }},
              }"
              x-init="@if($errors->any()) $nextTick(() => document.getElementById('form-errors')?.scrollIntoView({ behavior: 'smooth', block: 'center' })) @endif">
            @csrf
            <div class="absolute -left-[10000px] w-px h-px overflow-hidden" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>

            @if($errors->any())
                <div id="form-errors" class="rounded-2xl border border-rose-400/40 bg-rose-500/10 px-5 py-4 text-sm text-rose-200">
                    <strong class="block mb-1">Não foi possível enviar. Confira:</strong>
                    <ul class="list-disc pl-5 space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            {{-- 1. Dados --}}
            <fieldset class="{{ $panel }}">
                <legend class="sr-only">1. Seus dados</legend>
                <h3 class="font-outfit font-bold text-white text-lg mb-3" aria-hidden="true">1. Seus dados</h3>
                <div class="grid sm:grid-cols-2 gap-4 mt-2">
                    <div class="sm:col-span-2">
                        <label for="name" class="{{ $label }}">Nome completo (exatamente como nos seus documentos)</label>
                        <input id="name" name="name" required maxlength="255" autocomplete="name" value="{{ old('name') }}" class="{{ $input }}"
                               placeholder="Ex.: Maria Aparecida da Silva Souza">
                        <p class="{{ $hint }}">Sem abreviações e com letras maiúsculas e minúsculas como no RG. Este nome vai para o termo de confidencialidade (que você assina digitando o mesmo nome), para a autoria dos artigos e para os certificados. Não dá para corrigir depois.</p>
                        @error('name')<p class="{{ $err }}">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="name_confirmation" class="{{ $label }}">Confirme o nome completo</label>
                        <input id="name_confirmation" name="name_confirmation" required maxlength="255" autocomplete="off" value="{{ old('name_confirmation') }}" class="{{ $input }}"
                               onpaste="return false" placeholder="Digite de novo, sem copiar e colar">
                    </div>
                    <div>
                        <label for="email" class="{{ $label }}">E-mail</label>
                        <input id="email" type="email" name="email" required maxlength="255" autocomplete="email" value="{{ old('email') }}" class="{{ $input }}">
                        <p class="{{ $hint }}">Use um e-mail que você lê: todos os avisos chegam nele, e você vai precisar confirmá-lo.</p>
                        @error('email')<p class="{{ $err }}">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="email_confirmation" class="{{ $label }}">Confirme o e-mail</label>
                        <input id="email_confirmation" type="email" name="email_confirmation" required maxlength="255" autocomplete="off" value="{{ old('email_confirmation') }}" class="{{ $input }}"
                               onpaste="return false" placeholder="Digite de novo, sem copiar e colar">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="phone" class="{{ $label }}">WhatsApp</label>
                        <input id="phone" type="tel" name="phone" required maxlength="40" autocomplete="tel" placeholder="(11) 98765-4321" value="{{ old('phone') }}" class="{{ $input }}">
                        @error('phone')<p class="{{ $err }}">{{ $message }}</p>@enderror
                    </div>
                </div>
            </fieldset>

            {{-- 2. Formação --}}
            <fieldset class="{{ $panel }}">
                <legend class="sr-only">2. Formação</legend>
                <h3 class="font-outfit font-bold text-white text-lg mb-3" aria-hidden="true">2. Formação</h3>
                <div class="grid sm:grid-cols-2 gap-4 mt-2">
                    <div>
                        <label for="course" class="{{ $label }}">Curso</label>
                        <select id="course" name="course" required x-model="course" class="{{ $input }}">
                            <option value="">Selecione…</option>
                            @foreach(ResearchCall::COURSES as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
                        </select>
                        <p class="text-xs text-amber-300 mt-1" x-show="course && courses.length && !courses.includes(course)" x-cloak>Esta seleção é só para {{ \App\Services\ResearchRecruitmentService::humanList($call->eligibleCourses()) }}.</p>
                        @error('course')<p class="{{ $err }}">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="semester" class="{{ $label }}">Semestre atual</label>
                        <select id="semester" name="semester" required x-model="semester" class="{{ $input }}">
                            <option value="">Selecione…</option>
                            @for($s = 1; $s <= 12; $s++)<option value="{{ $s }}">{{ $s }}º semestre</option>@endfor
                        </select>
                        <p class="text-xs text-amber-300 mt-1" x-show="semester && Number(semester) < minSemester" x-cloak>É preciso estar no {{ $call->min_semester }}º semestre ou depois.</p>
                        @error('semester')<p class="{{ $err }}">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="institution" class="{{ $label }}">Instituição de ensino</label>
                        <input id="institution" name="institution" required maxlength="255" value="{{ old('institution') }}" class="{{ $input }}">
                        @error('institution')<p class="{{ $err }}">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="lattes_url" class="{{ $label }}">Currículo Lattes <span class="font-normal text-slate-500">(opcional)</span></label>
                        <input id="lattes_url" type="url" name="lattes_url" maxlength="255" placeholder="http://lattes.cnpq.br/…" value="{{ old('lattes_url') }}" class="{{ $input }}">
                        @error('lattes_url')<p class="{{ $err }}">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="orcid" class="{{ $label }}">ORCID <span class="font-normal text-slate-500">(opcional)</span></label>
                        <input id="orcid" name="orcid" maxlength="40" placeholder="0000-0000-0000-0000" value="{{ old('orcid') }}" class="{{ $input }}">
                        @error('orcid')<p class="{{ $err }}">{{ $message }}</p>@enderror
                    </div>
                </div>
            </fieldset>

            {{-- 3. Interesse --}}
            <fieldset class="{{ $panel }}">
                <legend class="sr-only">3. Interesse</legend>
                <h3 class="font-outfit font-bold text-white text-lg mb-3" aria-hidden="true">3. Interesse</h3>
                <div class="mt-2">
                    <label for="purpose" class="{{ $label }}">Para que você quer usar o projeto?</label>
                    <select id="purpose" name="purpose" required class="{{ $input }}">
                        <option value="">Selecione…</option>
                        @foreach(ResearchApplication::PURPOSES as $k => $v)<option value="{{ $k }}" @selected(old('purpose') === $k)>{{ $v }}</option>@endforeach
                    </select>
                    @error('purpose')<p class="{{ $err }}">{{ $message }}</p>@enderror
                </div>

                <div class="mt-5">
                    <span class="{{ $label }}">Eixos de interesse <span class="font-normal text-slate-500">(marque um ou mais)</span></span>
                    <div class="space-y-2">
                        @foreach($call->axisNames() as $axis)
                            <label class="flex gap-3 items-start rounded-xl border border-white/10 bg-dark-950/60 px-4 py-3 cursor-pointer hover:border-brand-teal/50 text-sm text-slate-200">
                                <input type="checkbox" name="axes[]" value="{{ $axis }}" class="mt-1 accent-teal-500" @checked(in_array($axis, (array) old('axes', []), true))>
                                <span>{{ $axis }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('axes')<p class="{{ $err }}">{{ $message }}</p>@enderror
                </div>

                @if($projectCount)
                <div class="mt-5">
                    <span class="{{ $label }}">Projetos de preferência <span class="font-normal text-slate-500">(opcional, até 3)</span></span>
                    <p class="{{ $hint }} mb-2">A alocação final considera a sua preferência, a entrevista e o equilíbrio das duplas. <span class="text-brand-teal" x-text="projects.length + '/3 escolhidos'"></span></p>
                    @foreach($byAxis as $name => $group)
                        <div class="text-xs font-bold uppercase tracking-wider text-slate-500 mt-3 mb-1.5">{{ $name }}</div>
                        <div class="grid sm:grid-cols-2 gap-2">
                            @foreach($group['projects'] as $p)
                                <label class="flex gap-2.5 items-start rounded-xl border border-white/10 bg-dark-950/60 px-3 py-2.5 text-sm text-slate-300 cursor-pointer"
                                       :class="projects.includes(@js($p['code'])) ? 'border-brand-teal/60 bg-brand-teal/10' : (projects.length >= 3 ? 'opacity-50' : 'hover:border-white/25')">
                                    <input type="checkbox" name="projects[]" value="{{ $p['code'] }}" x-model="projects" class="mt-1 accent-teal-500"
                                           @change="if (projects.length > 3) { $event.target.checked = false; projects = projects.filter(c => c !== $event.target.value) }"
                                           :disabled="projects.length >= 3 && !projects.includes(@js($p['code']))">
                                    <span><span class="font-mono text-xs font-bold text-brand-teal">{{ $p['code'] }}</span> {{ $p['area'] }}@if (!empty($p['half'])) <span class="text-xs text-amber-300">(1 vaga)</span>@endif</span>
                                </label>
                            @endforeach
                        </div>
                    @endforeach
                    @error('projects')<p class="{{ $err }}">{{ $message }}</p>@enderror
                </div>
                @endif
            </fieldset>

            {{-- 4. Perfil --}}
            <fieldset class="{{ $panel }}">
                <legend class="sr-only">4. Disponibilidade e perfil</legend>
                <h3 class="font-outfit font-bold text-white text-lg mb-3" aria-hidden="true">4. Disponibilidade e perfil</h3>
                <div class="grid sm:grid-cols-2 gap-4 mt-2">
                    <div>
                        <label for="weekly_hours" class="{{ $label }}">Horas por semana que você consegue reservar (média, em horários que você escolhe)</label>
                        <select id="weekly_hours" name="weekly_hours" required x-model="hours" class="{{ $input }}">
                            <option value="">Selecione…</option>
                            @foreach([1, 2, 3, 4, 5, 6, 8, 10] as $h)<option value="{{ $h }}">{{ $h }} {{ $h === 1 ? 'hora' : 'horas' }}{{ $h === 10 ? ' ou mais' : '' }}</option>@endforeach
                        </select>
                        <p class="text-xs text-amber-300 mt-1" x-show="hours && Number(hours) < minHours" x-cloak>O projeto exige pelo menos {{ $call->min_weekly_hours }} horas por semana.</p>
                        @error('weekly_hours')<p class="{{ $err }}">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="english_level" class="{{ $label }}">Leitura em inglês</label>
                        <select id="english_level" name="english_level" required class="{{ $input }}">
                            <option value="">Selecione…</option>
                            @foreach(ResearchApplication::ENGLISH_LEVELS as $k => $v)<option value="{{ $k }}" @selected(old('english_level') === $k)>{{ $v }}</option>@endforeach
                        </select>
                        @error('english_level')<p class="{{ $err }}">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="motivation" class="{{ $label }}">Por que você quer participar?</label>
                        <textarea id="motivation" name="motivation" required minlength="30" maxlength="2000" rows="4" class="{{ $input }}" placeholder="Conte o seu interesse pela área e o que espera do projeto.">{{ old('motivation') }}</textarea>
                        @error('motivation')<p class="{{ $err }}">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="experience" class="{{ $label }}">Experiência com pesquisa <span class="font-normal text-slate-500">(opcional)</span></label>
                        <textarea id="experience" name="experience" maxlength="2000" rows="3" class="{{ $input }}" placeholder="IC, monitoria, estágio, artigos, cursos... Não ter experiência não elimina ninguém.">{{ old('experience') }}</textarea>
                    </div>
                </div>
            </fieldset>

            {{-- 5. Compromissos --}}
            <fieldset class="{{ $panel }}">
                <legend class="sr-only">5. Compromissos</legend>
                <h3 class="font-outfit font-bold text-white text-lg mb-3" aria-hidden="true">5. Compromissos</h3>
                <p class="text-sm text-slate-400 mt-1">Para se inscrever, confirme cada item:</p>
                <div class="mt-4 space-y-2">
                    @foreach($commitments as $i => $c)
                        <label class="flex gap-3 items-start rounded-xl border border-white/10 bg-dark-950/60 px-4 py-3 cursor-pointer text-sm text-slate-200 hover:border-brand-teal/50">
                            <input type="checkbox" name="commitments[{{ $i }}]" value="1" required class="mt-1 accent-teal-500"
                                   @change="accepted += $event.target.checked ? 1 : -1" @checked(old("commitments.$i"))>
                            <span>{{ $c }}</span>
                        </label>
                    @endforeach
                </div>
                @error('commitments')<p class="{{ $err }}">{{ $message }}</p>@enderror
            </fieldset>

            {{-- 6. Termo de adesão (texto exato gravado com data, IP e SHA-256) --}}
            <fieldset class="{{ $panel }}">
                <legend class="sr-only">6. Termo de adesão</legend>
                <h3 class="font-outfit font-bold text-white text-lg mb-1" aria-hidden="true">6. Termo de adesão</h3>
                <p class="text-sm text-slate-400">Leia o termo. Ao enviar, o texto exato é gravado com o seu nome, e-mail, data e hora, endereço IP, navegador e uma impressão digital (SHA-256) que comprova o aceite. Você recebe uma cópia por e-mail.</p>
                <div class="mt-4 max-h-72 overflow-y-auto rounded-xl border border-white/10 bg-dark-950/80 p-4 text-xs leading-relaxed text-slate-300 whitespace-pre-line" tabindex="0">{{ $call->adhesionText('[seu nome completo]', '[seu e-mail]', '[data e hora do envio]') }}</div>
                <label class="mt-4 flex gap-3 items-start rounded-xl border border-brand-teal/40 bg-brand-teal/10 px-4 py-3 cursor-pointer text-sm text-white">
                    <input type="checkbox" name="adhesion" value="1" required class="mt-1 accent-teal-500"
                           @change="accepted += $event.target.checked ? 1 : -1" @checked(old('adhesion'))>
                    <span><strong>Li e aceito o termo de adesão</strong>, inclusive a natureza voluntária da participação (mentoria, sem bolsa, sem horário fixo e sem vínculo empregatício), a proibição de delegar a triagem à inteligência artificial e o tratamento dos meus dados conforme a LGPD.</span>
                </label>
                @error('adhesion')<p class="{{ $err }}">{{ $message }}</p>@enderror
            </fieldset>

            <div class="text-center">
                <button type="submit" class="inline-flex justify-center items-center gap-2 w-full sm:w-auto px-8 py-4 rounded-xl bg-gradient-to-r from-brand-teal to-brand-indigo text-white font-bold shadow-lg shadow-brand-teal/25 hover:brightness-110 transition disabled:opacity-50"
                        :disabled="accepted < total">
                    Aceitar o termo e enviar a inscrição
                </button>
                <p class="text-xs text-slate-500 mt-3" x-show="accepted < total">Confirme os compromissos e o termo de adesão acima para liberar o envio.</p>
            </div>
        </form>
        @endif
    </section>
</div>
@endsection
