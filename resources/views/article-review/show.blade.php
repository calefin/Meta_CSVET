@php
    use App\Models\ArticleReviewer;
    use App\Models\ArticleReview;
    use App\Services\ArticleReviewService;

    $profileOld = fn (string $field, $fallback = null) => old($field, $fallback);
    $roles = old('roles', $reviewer->contribution_roles ?? []);
    $checklistOld = old('checklist', $reviewer->isProfileComplete() ? array_keys(ArticleReviewer::CHECKLIST) : []);
    $openProfile = $canEditProfile && (!$reviewer->isProfileComplete() || $errors->hasAny(array_merge(['ficha', 'orcid', 'roles', 'checklist', 'accept_participation', 'aware_content', 'name', 'citation_name', 'email', 'phone', 'institution', 'city', 'state', 'country'])));
    [$myOrder, $total] = $order;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Revisão de {{ $review->workNoun() }} — Prof. Atilio</title>
    {{-- Sem scripts de terceiros nesta página: a URL carrega o token pessoal do participante. --}}
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; padding: 2rem 1rem;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #f1f5f9; color: #0f172a; line-height: 1.5;
        }
        .wrap { max-width: 760px; margin: 0 auto; }
        .card {
            background: #fff; border: 1px solid #e2e8f0; border-radius: 1rem;
            padding: 1.5rem; box-shadow: 0 10px 30px -15px rgba(15,23,42,.15); margin-bottom: 1rem;
        }
        .eyebrow { font-size: .75rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #0d9488; }
        h1 { font-size: 1.25rem; margin: .25rem 0 .5rem; line-height: 1.3; }
        h2.title { font-size: 1.05rem; margin: 0 0 .75rem; }
        .muted { color: #64748b; font-size: .875rem; }
        .meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: .75rem; margin-top: 1rem; }
        .meta div { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: .75rem; padding: .625rem .75rem; }
        .meta span { display: block; font-size: .7rem; text-transform: uppercase; letter-spacing: .04em; color: #64748b; }
        .meta strong { font-size: .95rem; }
        .overdue { color: #b91c1c; }
        .instructions { white-space: pre-line; background: #f0fdfa; border: 1px solid #99f6e4; border-radius: .75rem; padding: .75rem 1rem; font-size: .9rem; margin-top: 1rem; }
        .turn { border-radius: 1rem; padding: 1rem 1.25rem; margin-bottom: 1rem; font-size: 1rem; border: 2px solid; }
        .turn-me { background: #ecfdf5; border-color: #10b981; color: #065f46; }
        .turn-other { background: #fffbeb; border-color: #f59e0b; color: #78350f; }
        .turn-done { background: #eff6ff; border-color: #60a5fa; color: #1e3a8a; }
        .turn strong { font-size: 1.1rem; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
            padding: .75rem 1.25rem; border-radius: .75rem; font-weight: 600; font-size: .95rem;
            border: 0; cursor: pointer; text-decoration: none;
        }
        .btn-primary { background: #0d9488; color: #fff; }
        .btn-primary:hover { background: #0f766e; }
        .btn-outline { background: #fff; color: #0f766e; border: 1px solid #0d9488; }
        .btn-outline:hover { background: #f0fdfa; }
        .btn[disabled] { opacity: .6; cursor: not-allowed; }
        .step { display: flex; gap: .75rem; margin-top: 1.25rem; }
        .step-num {
            flex: 0 0 auto; width: 1.75rem; height: 1.75rem; border-radius: 999px; background: #ccfbf1; color: #0f766e;
            font-weight: 700; display: flex; align-items: center; justify-content: center; font-size: .85rem;
        }
        .step-body { flex: 1; min-width: 0; }
        .step-body h3 { font-size: 1rem; margin: .1rem 0 .5rem; }
        label.f { font-size: .85rem; font-weight: 600; display: block; margin: .9rem 0 .3rem; }
        input[type=file], input[type=text], input[type=email], input[type=tel], input[type=password], textarea, select {
            width: 100%; font: inherit; font-size: .9rem; padding: .6rem .75rem;
            border: 1px solid #cbd5e1; border-radius: .625rem; background: #fff; color: inherit;
        }
        textarea { min-height: 110px; resize: vertical; }
        input:focus, textarea:focus, select:focus { outline: 2px solid #99f6e4; border-color: #0d9488; }
        .grid2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0 1rem; }
        .hint { font-size: .78rem; color: #64748b; margin-top: .25rem; }
        .check { display: flex; gap: .625rem; align-items: flex-start; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: .75rem; padding: .65rem .75rem; font-size: .85rem; margin-top: .5rem; cursor: pointer; }
        .check input { margin-top: .2rem; width: 1.05rem; height: 1.05rem; flex: 0 0 auto; }
        .chips { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: .5rem; margin-top: .5rem; }
        .chip { position: relative; display: block; border: 1px solid #cbd5e1; border-radius: .75rem; padding: .6rem .75rem .6rem 2.5rem; cursor: pointer; background: #fff; font-size: .83rem; transition: all .15s; }
        .chip input { position: absolute; left: .8rem; top: .75rem; width: 1.05rem; height: 1.05rem; margin: 0; accent-color: #0d9488; }
        .chip b { display: block; font-size: .87rem; }
        .chip small { color: #64748b; display: block; line-height: 1.35; }
        .chip.on { border-color: #0d9488; background: #f0fdfa; box-shadow: 0 0 0 1px #0d9488 inset; }
        .preview { margin-top: .75rem; background: #f8fafc; border: 1px dashed #94a3b8; border-radius: .75rem; padding: .65rem .8rem; font-size: .85rem; }
        .alert { border-radius: .75rem; padding: .75rem 1rem; font-size: .9rem; margin-bottom: 1rem; }
        .alert-ok { background: #ecfdf5; border: 1px solid #6ee7b7; color: #065f46; }
        .alert-err { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }
        .alert-info { background: #eff6ff; border: 1px solid #93c5fd; color: #1e3a8a; }
        .alert-warn { background: #fffbeb; border: 1px solid #fcd34d; color: #78350f; }
        .disclaimer { font-size: .8rem; color: #475569; background: #f8fafc; border-left: 3px solid #94a3b8; padding: .6rem .8rem; border-radius: .5rem; margin-top: .9rem; }
        table.queue { width: 100%; border-collapse: collapse; font-size: .85rem; }
        table.queue th { text-align: left; font-size: .7rem; text-transform: uppercase; letter-spacing: .04em; color: #64748b; padding: .4rem .5rem; border-bottom: 1px solid #e2e8f0; }
        table.queue td { padding: .55rem .5rem; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        table.queue tr.now td { background: #fef3c7; font-weight: 600; }
        table.queue tr.me td:first-child { box-shadow: 3px 0 0 #0d9488 inset; }
        table.queue tr.gone td { color: #94a3b8; text-decoration: line-through; }
        .tag { display: inline-block; font-size: .7rem; font-weight: 700; padding: .1rem .45rem; border-radius: 999px; background: #e2e8f0; color: #334155; white-space: nowrap; }
        .tag-now { background: #f59e0b; color: #fff; }
        .tag-done { background: #10b981; color: #fff; }
        .tag-ok { background: #ccfbf1; color: #0f766e; }
        .tag-pend { background: #fee2e2; color: #991b1b; }
        .scroll { overflow-x: auto; }
        ul.timeline { list-style: none; padding: 0; margin: 0; font-size: .85rem; }
        ul.timeline li { padding: .4rem 0 .4rem 1rem; border-left: 2px solid #e2e8f0; position: relative; }
        ul.timeline li::before { content: ""; position: absolute; left: -5px; top: .75rem; width: 8px; height: 8px; border-radius: 999px; background: #0d9488; }
        ul.timeline time { color: #64748b; font-size: .78rem; margin-right: .35rem; }
        details.guide summary { cursor: pointer; font-weight: 600; color: #0f766e; }
        details.guide ol { padding-left: 1.2rem; font-size: .87rem; margin: .5rem 0; }
        details.guide li { margin-bottom: .3rem; }
        .profile-summary dl { display: grid; grid-template-columns: 9rem 1fr; gap: .25rem .75rem; font-size: .85rem; margin: .5rem 0 0; }
        .profile-summary dt { color: #64748b; }
        .profile-summary dd { margin: 0; word-break: break-word; }
        .receipt dt { color: #64748b; font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; margin-top: .625rem; }
        .receipt dd { margin: 0; word-break: break-all; }
        .hp { position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden; }
        .foot { text-align: center; font-size: .75rem; color: #94a3b8; margin-top: 1.5rem; }
        .files a { color: #0f766e; }
        @media (max-width: 520px) { .profile-summary dl { grid-template-columns: 1fr; } .card { padding: 1.1rem; } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="eyebrow">{{ $review->workTypeLabel() }} · processo de revisão</div>
        <h1>{{ $review->title }}</h1>
        <p class="muted" style="margin:0">Olá, <strong>{{ $reviewer->name }}</strong>. Você participa deste processo a convite do Prof. Atilio.</p>

        <div class="meta">
            @if ($review->journal)
                <div><span>Revista-alvo</span><strong>{{ $review->journal }}</strong></div>
            @endif
            <div><span>Processo</span><strong>{{ $review->statusLabel() }}</strong></div>
            <div><span>Sua situação</span><strong>{{ $reviewer->statusLabel() }}</strong></div>
            @if ($myOrder)
                <div><span>Sua ordem de revisão</span><strong>{{ $myOrder }}º de {{ $total }}</strong></div>
            @endif
            @if ($reviewer->due_at && $isOpen)
                <div>
                    <span>Seu prazo</span>
                    <strong @class(['overdue' => $reviewer->isOverdue()])>
                        {{ $reviewer->due_at->format('d/m/Y') }}{{ $reviewer->isOverdue() ? ' (vencido)' : '' }}
                    </strong>
                </div>
            @endif
        </div>

        @if (!$gate)
            <div class="instructions">{{ $review->instructions ?: \App\Models\ArticleReview::DEFAULT_INSTRUCTIONS }}</div>
        @endif

        <div class="disclaimer"><strong>Aviso:</strong> {{ ArticleReviewService::disclaimer() }}</div>
    </div>

    {{-- Na vez de quem está --}}
    @if ($review->status === ArticleReview::STATUS_IN_REVIEW)
        @if ($isOpen)
            <div class="turn turn-me"><strong>É a sua vez!</strong><br>O arquivo está com você desde {{ $reviewer->sent_at?->format('d/m/Y') }}. Siga os passos abaixo.</div>
        @elseif ($active)
            <div class="turn turn-other">Agora está com: <strong>{{ $active->name }}</strong> <span style="font-size:.85rem">(desde {{ $active->sent_at?->format('d/m/Y') }})</span></div>
        @endif
    @elseif ($review->status === ArticleReview::STATUS_REVIEWS_DONE)
        <div class="turn turn-done">Todas as revisões foram concluídas. <strong>O trabalho está com o Prof. Atilio</strong> para consolidação e envio à revista.</div>
    @elseif ($review->status === ArticleReview::STATUS_SUBMITTED)
        <div class="turn turn-done"><strong>Processo encerrado.</strong> Enviado{{ $review->journal ? ' à revista ' . $review->journal : '' }} em {{ $review->submitted_at?->format('d/m/Y') }}. Obrigado pela participação!</div>
    @elseif ($review->status === ArticleReview::STATUS_DRAFT)
        <div class="turn turn-other">O processo ainda não começou. Já preencha e confirme a sua ficha abaixo.</div>
    @endif

    @if (session('success'))
        <div class="alert alert-ok">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-err">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @if ($gate)
        {{-- Processo exige senha --}}
        <div class="card" id="conta">
            <h2 class="title">Acesso protegido por senha</h2>
            @if ($existingUser)
                <p class="muted">Entre com a senha da sua conta ({{ $existingUser->email }}) para continuar.</p>
                <form method="POST" action="{{ $urls['access'] }}">
                    @csrf
                    <input type="hidden" name="mode" value="login">
                    <div class="hp" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
                    <label class="f" for="pw">Senha</label>
                    <input type="password" id="pw" name="password" required autocomplete="current-password">
                    <div style="margin-top:1rem;display:flex;gap:.75rem;flex-wrap:wrap;align-items:center">
                        <button class="btn btn-primary" type="submit">Entrar</button>
                        <a href="{{ \Filament\Facades\Filament::getPanel('revisor')->getRequestPasswordResetUrl() }}" class="muted">Esqueci minha senha</a>
                    </div>
                </form>
            @else
                <p class="muted">Este processo exige uma senha pessoal. Crie a sua agora (só na primeira vez).</p>
                @include('article-review.partials.create-account')
            @endif
        </div>
    @else

    {{-- Ficha do participante --}}
    <div class="card" id="ficha">
        <div style="display:flex;justify-content:space-between;gap:.5rem;align-items:center;flex-wrap:wrap">
            <h2 class="title" style="margin:0">Sua ficha de participante</h2>
            @if ($reviewer->isProfileComplete())
                <span class="tag tag-ok">Confirmada em {{ $reviewer->profile_confirmed_at->format('d/m/Y') }}</span>
            @else
                <span class="tag tag-pend">Pendente</span>
            @endif
        </div>

        @if ($reviewer->isProfileComplete())
            <div class="profile-summary">
                <dl>
                    <dt>Nome completo</dt><dd>{{ $reviewer->name }}</dd>
                    <dt>Nome p/ citação</dt><dd>{{ $reviewer->citation_name }}</dd>
                    <dt>E-mail</dt><dd>{{ $reviewer->email }}</dd>
                    <dt>Telefone</dt><dd>{{ $reviewer->phone }}</dd>
                    <dt>Filiação</dt><dd>{{ $reviewer->affiliationLine() }}</dd>
                    <dt>ORCID</dt><dd>{{ $reviewer->orcid ?: 'Não possui' }}</dd>
                    <dt>Participação</dt><dd>{{ $reviewer->rolesLabel() }}</dd>
                </dl>
            </div>
        @else
            <p class="muted" style="margin:.5rem 0 0">Todos os participantes precisam confirmar nome completo, contato, filiação, ORCID, o tipo de participação e o aceite. @if ($isOpen)<strong>O envio do arquivo só é liberado depois da ficha confirmada.</strong>@endif</p>
        @endif

        @if ($canEditProfile)
            <details @if ($openProfile) open @endif style="margin-top:1rem">
                <summary class="btn btn-outline" style="list-style:none">{{ $reviewer->isProfileComplete() ? 'Corrigir minha ficha' : 'Preencher minha ficha' }}</summary>

                <form method="POST" action="{{ $urls['profile'] }}" id="profile-form">
                    @csrf
                    <div class="hp" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>

                    <div class="grid2">
                        <div>
                            <label class="f" for="name">Nome completo</label>
                            <input type="text" id="name" name="name" required maxlength="255" value="{{ $profileOld('name', $reviewer->name) }}" autocomplete="name">
                        </div>
                        <div>
                            <label class="f" for="citation_name">Nome para citação</label>
                            <input type="text" id="citation_name" name="citation_name" required maxlength="120" placeholder="Ex.: SILVA, M. A." value="{{ $profileOld('citation_name', $reviewer->citation_name) }}">
                        </div>
                        <div>
                            <label class="f" for="email">E-mail</label>
                            <input type="email" id="email" name="email" required maxlength="255" value="{{ $profileOld('email', $reviewer->email) }}" autocomplete="email">
                        </div>
                        <div>
                            <label class="f" for="phone">Telefone / WhatsApp</label>
                            <input type="tel" id="phone" name="phone" required maxlength="40" placeholder="(11) 98765-4321" value="{{ $profileOld('phone', $reviewer->phone) }}" autocomplete="tel">
                        </div>
                    </div>

                    <h3 style="font-size:.95rem;margin:1.25rem 0 0">Filiação completa</h3>
                    <div class="grid2">
                        <div>
                            <label class="f" for="institution">Instituição</label>
                            <input type="text" id="institution" name="institution" required maxlength="255" placeholder="Ex.: Universidade de São Paulo (USP)" value="{{ $profileOld('institution', $reviewer->institution) }}">
                        </div>
                        <div>
                            <label class="f" for="department">Departamento / Programa / Setor</label>
                            <input type="text" id="department" name="department" maxlength="255" placeholder="Ex.: Departamento de Clínica Médica" value="{{ $profileOld('department', $reviewer->department) }}">
                        </div>
                        <div>
                            <label class="f" for="city">Cidade</label>
                            <input type="text" id="city" name="city" required maxlength="120" value="{{ $profileOld('city', $reviewer->city) }}">
                        </div>
                        <div>
                            <label class="f" for="state">Estado</label>
                            <input type="text" id="state" name="state" required maxlength="120" placeholder="Ex.: SP" value="{{ $profileOld('state', $reviewer->state) }}">
                        </div>
                        <div>
                            <label class="f" for="country">País</label>
                            <input type="text" id="country" name="country" required maxlength="120" value="{{ $profileOld('country', $reviewer->country ?: 'Brasil') }}">
                        </div>
                        <div>
                            <label class="f" for="orcid">ORCID</label>
                            <input type="text" id="orcid" name="orcid" maxlength="60" placeholder="0000-0000-0000-0000" value="{{ $profileOld('orcid', $reviewer->orcid) }}">
                            <label style="font-size:.8rem;display:flex;gap:.4rem;align-items:center;margin-top:.35rem;cursor:pointer">
                                <input type="checkbox" name="no_orcid" value="1" id="no_orcid" @checked($profileOld('no_orcid', $reviewer->no_orcid))> Não possuo ORCID
                            </label>
                            <div class="hint">Crie gratuitamente em orcid.org — muitas revistas exigem.</div>
                        </div>
                    </div>

                    <h3 style="font-size:.95rem;margin:1.25rem 0 0">Tipo de participação (CRediT)</h3>
                    <p class="hint" style="margin:.2rem 0 0">Clique em tudo o que você fez neste trabalho. A declaração abaixo vai se montando sozinha — é o texto que vai para a revista.</p>
                    <div class="chips" id="credit">
                        @foreach (ArticleReviewer::CREDIT_ROLES as $key => $label)
                            <label @class(['chip', 'on' => in_array($key, $roles, true)])>
                                <input type="checkbox" name="roles[]" value="{{ $key }}" data-label="{{ $label }}" @checked(in_array($key, $roles, true))>
                                <b>{{ $label }}</b>
                                <small>{{ ArticleReviewer::CREDIT_DESCRIPTIONS[$key] }}</small>
                            </label>
                        @endforeach
                    </div>
                    <label class="f" for="contribution_other">Outra participação (opcional)</label>
                    <input type="text" id="contribution_other" name="contribution_other" maxlength="255" placeholder="Ex.: atendimento clínico dos casos, necropsia..." value="{{ $profileOld('contribution_other', $reviewer->contribution_other) }}">
                    <div class="preview" id="credit-preview" aria-live="polite"></div>

                    <label class="f" for="profile_notes">Observações (conflito de interesses, financiamento, outra filiação...)</label>
                    <textarea id="profile_notes" name="profile_notes" maxlength="3000" style="min-height:70px">{{ $profileOld('profile_notes', $reviewer->profile_notes) }}</textarea>

                    <h3 style="font-size:.95rem;margin:1.25rem 0 0">Lista de verificação</h3>
                    @foreach (ArticleReviewer::CHECKLIST as $key => $text)
                        <label class="check">
                            <input type="checkbox" name="checklist[]" value="{{ $key }}" required @checked(in_array($key, $checklistOld, true))>
                            <span>{{ $text }}</span>
                        </label>
                    @endforeach

                    <h3 style="font-size:.95rem;margin:1.25rem 0 0">Aceite</h3>
                    <label class="check">
                        <input type="checkbox" name="accept_participation" value="1" required @checked(old('accept_participation', $reviewer->isProfileComplete()))>
                        <span>Aceito participar deste trabalho ("{{ $review->title }}") e do seu processo de revisão.</span>
                    </label>
                    <label class="check">
                        <input type="checkbox" name="aware_content" value="1" required @checked(old('aware_content', $reviewer->isProfileComplete()))>
                        <span>Estou ciente do conteúdo do trabalho e de que a composição e a ordem de revisão podem ser alteradas pelo Prof. Atilio durante o processo.</span>
                    </label>

                    <div style="margin-top:1.25rem">
                        <button type="submit" class="btn btn-primary">Confirmar ficha</button>
                    </div>
                </form>
            </details>
        @endif
    </div>

    @if ($isOpen)
        <div class="card">
            <h2 class="title">Sua revisão</h2>

            <div class="step">
                <div class="step-num">1</div>
                <div class="step-body">
                    <h3>Baixe a versão atual</h3>
                    <p class="muted" style="margin-top:0">Versão {{ $current['version'] }} — <code>{{ $current['filename'] }}</code></p>
                    <a class="btn btn-outline" href="{{ $urls['download'] }}" rel="nofollow">Baixar arquivo (.{{ pathinfo($current['filename'], PATHINFO_EXTENSION) }})</a>
                    @if ($files->isNotEmpty())
                        <div class="files" style="margin-top:.9rem;font-size:.85rem">
                            <strong>Documentos complementares:</strong>
                            <ul style="margin:.3rem 0 0;padding-left:1.1rem">
                                @foreach ($files as $f)
                                    <li><a href="{{ $urls['file']($f->id) }}" rel="nofollow">{{ $f->filename }}</a>
                                        <span class="muted">({{ $f->size_kb }} KB{{ $f->description ? ' · ' . $f->description : '' }}{{ $f->uploaded_by ? ' · ' . $f->uploaded_by : '' }})</span></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>

            <div class="step">
                <div class="step-num">2</div>
                <div class="step-body">
                    <h3>Revise com "Controlar Alterações" ligado</h3>
                    <details class="guide" open>
                        <summary>Como editar o arquivo (leia antes de começar)</summary>
                        <ol>
                            <li><strong>Word:</strong> abra o arquivo baixado → guia <em>Revisão</em> → clique em <em>Controlar Alterações</em> (fica destacado). Tudo o que você apagar ou escrever aparece colorido com o seu nome.</li>
                            <li>Para sugerir algo sem alterar o texto, selecione o trecho → <em>Revisão → Novo Comentário</em>.</li>
                            <li><strong>LibreOffice:</strong> <em>Editar → Controlar alterações → Gravar</em> (Ctrl+Shift+C). Comentários em <em>Inserir → Comentário</em>.</li>
                            <li><strong>Não aceite nem rejeite</strong> as alterações dos revisores anteriores — isso é feito pelo Prof. Atilio no final.</li>
                            <li>Evite Google Docs: ao converter, ele costuma perder marcas de revisão e formatação.</li>
                            <li>Salve como <strong>.docx</strong> (Documento do Word). Não precisa renomear: o sistema nomeia a nova versão com as suas iniciais.</li>
                            <li>Todas as versões ficam guardadas. Se algo der errado, avise o Prof. Atilio — dá para voltar a qualquer versão anterior.</li>
                        </ol>
                    </details>
                </div>
            </div>

            @if (!$reviewer->isProfileComplete())
                <div class="alert alert-warn" style="margin-top:1.25rem">Para enviar o arquivo revisado, primeiro <a href="#ficha">confirme a sua ficha de participante</a>.</div>
            @else
                <form method="POST" action="{{ $urls['store'] }}" enctype="multipart/form-data" id="review-form">
                    @csrf
                    <div class="hp" aria-hidden="true">
                        <label for="website">Não preencha</label>
                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="step">
                        <div class="step-num">3</div>
                        <div class="step-body">
                            <h3>Devolva o arquivo revisado</h3>
                            <label class="f" for="document" style="margin-top:0">Arquivo revisado (.docx ou .odt, até 30MB)</label>
                            <input type="file" id="document" name="document" required
                                accept=".docx,.odt,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.oasis.opendocument.text">

                            @if (session('needs_confirm'))
                                <div class="alert alert-warn" style="margin-top:.75rem">
                                    <strong>Atenção — o sistema comparou o arquivo com a versão que você recebeu:</strong>
                                    <ul style="margin:.3rem 0 .5rem;padding-left:1.1rem">
                                        @foreach (session('needs_confirm') as $warning)
                                            <li>{{ $warning }}</li>
                                        @endforeach
                                    </ul>
                                    <label style="display:flex;gap:.5rem;align-items:flex-start;cursor:pointer">
                                        <input type="checkbox" name="confirm_file" value="1" style="margin-top:.2rem">
                                        <span>Conferi e este é o arquivo correto — quero enviar mesmo assim.</span>
                                    </label>
                                </div>
                            @endif

                            <label class="f" for="extras">Documentos complementares (opcional, até 5 — PDF, Word, Excel, imagens...)</label>
                            <input type="file" id="extras" name="extras[]" multiple
                                accept=".pdf,.docx,.xlsx,.pptx,.odt,.ods,.odp,.csv,.txt,.png,.jpg,.jpeg,.tif,.tiff">

                            <label class="f" for="comments">Comentários gerais (opcional)</label>
                            <textarea id="comments" name="comments" maxlength="20000" placeholder="O que você alterou, pontos que merecem atenção...">{{ old('comments') }}</textarea>

                            <label class="f" for="suggestions">Mensagem de sugestão (opcional)</label>
                            <textarea id="suggestions" name="suggestions" maxlength="20000" placeholder="Sugestões ao autor: referências, análises, revista, próximos passos...">{{ old('suggestions') }}</textarea>
                        </div>
                    </div>

                    <div class="step">
                        <div class="step-num">4</div>
                        <div class="step-body">
                            <h3>Confirme sua participação</h3>
                            <label class="check">
                                <input type="checkbox" name="accept_participation" value="1" required @checked(old('accept_participation'))>
                                <span>{{ $statement }}</span>
                            </label>

                            <div style="margin-top:1.25rem">
                                <button type="submit" class="btn btn-primary" id="submit-btn">Enviar revisão e passar adiante</button>
                            </div>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    @elseif ($reviewer->status === ArticleReviewer::STATUS_DONE)
        <div class="card receipt">
            <h2 class="title" style="margin-bottom:.25rem">Comprovante de participação</h2>
            <p class="muted" style="margin:0">Sua revisão foi registrada. Obrigado!</p>
            <dl>
                <dt>Entregue em</dt>
                <dd>{{ $reviewer->returned_at?->format('d/m/Y \à\s H:i') }}</dd>
                <dt>Arquivo registrado</dt>
                <dd>{{ $reviewer->returned_filename }}</dd>
                <dt>Código de verificação (SHA-256)</dt>
                <dd><code>{{ $reviewer->forensic['sha256'] ?? '—' }}</code></dd>
                <dt>Participação declarada em</dt>
                <dd>{{ $reviewer->participation_accepted_at?->format('d/m/Y \à\s H:i') }}</dd>
            </dl>
        </div>
    @elseif ($reviewer->status === ArticleReviewer::STATUS_WAITING && $review->status === ArticleReview::STATUS_IN_REVIEW)
        <div class="alert alert-info">Ainda não é a sua vez. Você receberá um e-mail quando o arquivo estiver liberado para você — o download só abre na sua vez.</div>
    @elseif (!$reviewer->isActive())
        <div class="alert alert-info">Sua participação nesta rodada foi encerrada pelo autor. Em caso de dúvida, responda ao e-mail recebido.</div>
    @endif

    {{-- Fila e histórico (visíveis para todos) --}}
    <div class="card">
        <h2 class="title">Ordem de revisão e andamento</h2>
        <div class="scroll">
            <table class="queue">
                <thead><tr><th>#</th><th>Participante</th><th>Situação</th><th>Recebeu</th><th>Devolveu</th><th>Ficha</th></tr></thead>
                <tbody>
                @php $n = 0; @endphp
                @foreach ($queue as $q)
                    @php $gone = !$q->isActive(); if (!$gone) { $n++; } @endphp
                    <tr @class(['now' => $q->status === ArticleReviewer::STATUS_IN_REVIEW, 'me' => $q->id === $reviewer->id, 'gone' => $gone])>
                        <td>{{ $gone ? '—' : $n }}</td>
                        <td>{{ $q->name }}{{ $q->id === $reviewer->id ? ' (você)' : '' }}</td>
                        <td>
                            @if ($q->status === ArticleReviewer::STATUS_IN_REVIEW)
                                <span class="tag tag-now">NA VEZ</span>
                            @elseif ($q->status === ArticleReviewer::STATUS_DONE)
                                <span class="tag tag-done">Entregue</span>
                            @else
                                <span class="tag">{{ $q->statusLabel() }}</span>
                            @endif
                        </td>
                        <td>{{ $q->sent_at?->format('d/m/Y') ?? '—' }}</td>
                        <td>{{ $q->returned_at?->format('d/m/Y') ?? '—' }}</td>
                        <td>{!! $gone ? '—' : ($q->isProfileComplete() ? '<span class="tag tag-ok">OK</span>' : '<span class="tag tag-pend">Pendente</span>') !!}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        @if ($events->isNotEmpty())
            <h3 style="font-size:.95rem;margin:1.25rem 0 .5rem">Histórico</h3>
            <ul class="timeline">
                @foreach ($events->take(60) as $e)
                    <li><time>{{ \Illuminate\Support\Carbon::parse($e['at'])->format('d/m/Y H:i') }}</time>{{ $e['event'] }}</li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Avisos pelo Telegram --}}
    @if (!$review->isClosed() && $reviewer->isActive())
        @php $tgLink = $reviewer->telegramChatId() ? null : $reviewer->telegramLinkUrl(); @endphp
        @if ($reviewer->telegramChatId() || $tgLink)
            <div class="card" id="telegram">
                <h2 class="title">Avisos no Telegram</h2>
                @if ($reviewer->telegramChatId())
                    <p class="muted" style="margin:0">✅ Seu Telegram está vinculado: você recebe por lá quando chegar a sua vez, com quem está o arquivo e o andamento do processo.</p>
                @else
                    <p class="muted">Receba no celular, na hora, quando chegar a sua vez e com quem está o arquivo. Toque no botão, abra o Telegram e toque em <strong>Iniciar</strong>.</p>
                    <a class="btn btn-outline" href="{{ $tgLink }}" target="_blank" rel="noopener">📲 Receber avisos no Telegram</a>
                @endif
            </div>
        @endif
    @endif

    {{-- Conta (opcional) --}}
    <div class="card" id="conta">
        <h2 class="title">Acesso com senha (opcional)</h2>
        @if ($loggedAsOwner)
            <p class="muted" style="margin:0">Você está conectado(a) à sua conta. Todos os seus processos ficam na <a href="{{ \Filament\Facades\Filament::getPanel('revisor')->getUrl() }}">Área do Revisor</a>.</p>
        @elseif ($existingUser && $urls['access'])
            <p class="muted">Você já tem conta ({{ $existingUser->email }}). Entre para ver todos os seus processos na Área do Revisor.</p>
            <form method="POST" action="{{ $urls['access'] }}">
                @csrf
                <input type="hidden" name="mode" value="login">
                <div class="hp" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
                <label class="f" for="pw2">Senha</label>
                <input type="password" id="pw2" name="password" required autocomplete="current-password">
                <div style="margin-top:1rem;display:flex;gap:.75rem;flex-wrap:wrap;align-items:center">
                    <button class="btn btn-outline" type="submit">Entrar</button>
                    <a href="{{ \Filament\Facades\Filament::getPanel('revisor')->getRequestPasswordResetUrl() }}" class="muted">Esqueci minha senha</a>
                </div>
            </form>
        @elseif ($urls['access'])
            <p class="muted">Crie uma senha para acessar este e outros processos sem depender do link, pela <strong>Área do Revisor</strong>. Se esquecer, dá para recuperar por e-mail. A conta é só de revisor — não dá acesso ao portal de orientandos.</p>
            @include('article-review.partials.create-account')
        @endif
    </div>
    @endif

    <p class="foot">Link pessoal e intransferível · profatilio.com.br</p>
</div>

<script>
    (function () {
        var form = document.getElementById('review-form');
        if (form) {
            form.addEventListener('submit', function () {
                var btn = document.getElementById('submit-btn');
                btn.setAttribute('disabled', 'disabled');
                btn.textContent = 'Enviando...';
            });
        }

        // CRediT: clicar marca/desmarca e monta a declaração ao vivo.
        var box = document.getElementById('credit');
        var preview = document.getElementById('credit-preview');
        var other = document.getElementById('contribution_other');
        var nameInput = document.getElementById('name');
        function render() {
            if (!box || !preview) return;
            var picked = [];
            box.querySelectorAll('input[type=checkbox]').forEach(function (cb) {
                cb.closest('.chip').classList.toggle('on', cb.checked);
                if (cb.checked) picked.push(cb.getAttribute('data-label'));
            });
            if (other && other.value.trim()) picked.push(other.value.trim());
            var who = (nameInput && nameInput.value.trim()) || 'Você';
            preview.textContent = picked.length
                ? 'Declaração: ' + who + ': ' + picked.join(', ') + '.'
                : 'Declaração: clique acima nas funções que você exerceu.';
        }
        if (box) {
            box.addEventListener('change', render);
            if (other) other.addEventListener('input', render);
            if (nameInput) nameInput.addEventListener('input', render);
            render();
        }

        var noOrcid = document.getElementById('no_orcid');
        var orcid = document.getElementById('orcid');
        function toggleOrcid() { if (noOrcid && orcid) { orcid.disabled = noOrcid.checked; } }
        if (noOrcid) { noOrcid.addEventListener('change', toggleOrcid); toggleOrcid(); }
    })();
</script>
</body>
</html>
