<form method="POST" action="{{ $urls['access'] }}">
    @csrf
    <input type="hidden" name="mode" value="create">
    <div class="hp" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
    @if (!$reviewer->email)
        <label class="f" for="acc_email">E-mail</label>
        <input type="email" id="acc_email" name="email" required maxlength="255" value="{{ old('email') }}" autocomplete="email">
    @else
        <p class="hint" style="margin:0">Conta: <strong>{{ $reviewer->email }}</strong></p>
    @endif
    <div class="grid2">
        <div>
            <label class="f" for="acc_pw">Nova senha (mín. 8 caracteres)</label>
            <input type="password" id="acc_pw" name="password" required minlength="8" autocomplete="new-password">
        </div>
        <div>
            <label class="f" for="acc_pw2">Repita a senha</label>
            <input type="password" id="acc_pw2" name="password_confirmation" required minlength="8" autocomplete="new-password">
        </div>
    </div>
    <div style="margin-top:1rem">
        <button class="btn btn-outline" type="submit">Criar minha senha</button>
    </div>
</form>
