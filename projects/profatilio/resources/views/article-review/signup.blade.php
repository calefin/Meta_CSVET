@php use App\Services\ArticleReviewService; @endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Cadastro no processo de revisão — Prof. Atilio</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; padding: 2rem 1rem; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #f1f5f9; color: #0f172a; line-height: 1.5; }
        .wrap { max-width: 600px; margin: 0 auto; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 1rem; padding: 1.5rem; box-shadow: 0 10px 30px -15px rgba(15,23,42,.15); margin-bottom: 1rem; }
        .eyebrow { font-size: .75rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #0d9488; }
        h1 { font-size: 1.2rem; margin: .25rem 0 .5rem; line-height: 1.3; }
        .muted { color: #64748b; font-size: .875rem; }
        label.f { font-size: .85rem; font-weight: 600; display: block; margin: .9rem 0 .3rem; }
        input[type=text], input[type=email], input[type=tel] { width: 100%; font: inherit; font-size: .9rem; padding: .6rem .75rem; border: 1px solid #cbd5e1; border-radius: .625rem; }
        input:focus { outline: 2px solid #99f6e4; border-color: #0d9488; }
        .opt { display: flex; gap: .6rem; align-items: center; border: 1px solid #e2e8f0; border-radius: .75rem; padding: .6rem .75rem; margin-top: .4rem; cursor: pointer; font-size: .9rem; }
        .check { display: flex; gap: .6rem; align-items: flex-start; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: .75rem; padding: .65rem .75rem; font-size: .85rem; margin-top: 1rem; cursor: pointer; }
        .check input { margin-top: .2rem; }
        .btn { display: inline-flex; padding: .75rem 1.25rem; border-radius: .75rem; font-weight: 600; font-size: .95rem; border: 0; cursor: pointer; background: #0d9488; color: #fff; }
        .alert { border-radius: .75rem; padding: .75rem 1rem; font-size: .9rem; margin-bottom: 1rem; background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }
        .disclaimer { font-size: .8rem; color: #475569; background: #f8fafc; border-left: 3px solid #94a3b8; padding: .6rem .8rem; border-radius: .5rem; margin-top: .9rem; }
        .hp { position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden; }
        .foot { text-align: center; font-size: .75rem; color: #94a3b8; margin-top: 1.5rem; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="eyebrow">{{ $review->workTypeLabel() }} · cadastro de participante</div>
        <h1>{{ $review->title }}</h1>
        <p class="muted" style="margin:0">Você recebeu este link do Prof. Atilio para se vincular ao processo de revisão deste trabalho. Depois do cadastro, você completa a sua ficha (filiação, ORCID e tipo de participação).</p>
        <div class="disclaimer"><strong>Aviso:</strong> {{ ArticleReviewService::disclaimer() }}</div>
    </div>

    @if ($errors->any())
        <div class="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif

    <div class="card">
        <form method="POST" action="{{ route('article-review.signup.store', ['token' => $token]) }}" id="signup">
            @csrf
            <div class="hp" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>

            @if ($claimable->isNotEmpty())
                <label class="f" style="margin-top:0">Seu nome já está na lista? Selecione:</label>
                @foreach ($claimable as $c)
                    <label class="opt"><input type="radio" name="claim_id" value="{{ $c->id }}" @checked((string) old('claim_id') === (string) $c->id)> {{ $c->name }}</label>
                @endforeach
                <label class="opt"><input type="radio" name="claim_id" value="" @checked(old('claim_id') === null || old('claim_id') === '')> Não estou na lista — sou um(a) novo(a) participante</label>
            @endif

            <div id="name-box">
                <label class="f" for="name">Nome completo</label>
                <input type="text" id="name" name="name" maxlength="255" value="{{ old('name') }}" autocomplete="name">
            </div>

            <label class="f" for="email">E-mail</label>
            <input type="email" id="email" name="email" required maxlength="255" value="{{ old('email') }}" autocomplete="email">

            <label class="f" for="phone">Telefone / WhatsApp</label>
            <input type="tel" id="phone" name="phone" required maxlength="40" placeholder="(11) 98765-4321" value="{{ old('phone') }}" autocomplete="tel">

            <label class="check"><input type="checkbox" name="agree" value="1" required> <span>Quero participar deste processo e autorizo o registro dos meus dados para fins de autoria e auditoria do trabalho.</span></label>

            <div style="margin-top:1.25rem"><button class="btn" type="submit">Cadastrar e continuar</button></div>
        </form>
    </div>
    <p class="foot">profatilio.com.br</p>
</div>
<script>
    (function () {
        var box = document.getElementById('name-box');
        function sync() {
            var picked = document.querySelector('input[name=claim_id]:checked');
            box.style.display = picked && picked.value ? 'none' : '';
        }
        document.querySelectorAll('input[name=claim_id]').forEach(function (r) { r.addEventListener('change', sync); });
        sync();
    })();
</script>
</body>
</html>
