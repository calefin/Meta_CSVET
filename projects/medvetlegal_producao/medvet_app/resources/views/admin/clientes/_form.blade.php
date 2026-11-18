@php $cliente = $cliente ?? null; @endphp

<div class="form-group">
    <label>Nome *</label>
    <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome', $cliente->nome ?? '') }}" required>
    @error('nome')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="form-group">
    <label>Email *</label>
    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $cliente->email ?? '') }}" required>
    @error('email')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Telefone</label>
            <input type="text" name="telefone" class="form-control @error('telefone') is-invalid @enderror" value="{{ old('telefone', $cliente->telefone ?? '') }}">
            @error('telefone')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>CPF/CNPJ</label>
            <input type="text" name="cpf_cnpj" class="form-control @error('cpf_cnpj') is-invalid @enderror" value="{{ old('cpf_cnpj', $cliente->cpf_cnpj ?? '') }}">
            @error('cpf_cnpj')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>
