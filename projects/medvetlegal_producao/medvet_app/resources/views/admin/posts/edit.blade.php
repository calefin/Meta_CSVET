@extends('layouts.admin')

@section('title', 'Editar Artigo')
@section('header')
    <h1>Editar Artigo <a href="{{ route('admin.posts.index') }}" class="btn btn-secondary btn-sm float-right"><i class="fas fa-arrow-left"></i> Voltar</a></h1>
@endsection

@section('content')
<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.posts.update', $post) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="row">
                <!-- Título -->
                <div class="col-md-8">
                    <div class="form-group">
                        <label for="title">Título do Artigo *</label>
                        <input type="text" name="title" id="title" class="form-control" value="{{ old('title', $post->title) }}" required placeholder="Ex: Como comprovar intoxicação por 'Chumbinho'...">
                    </div>
                </div>

                <!-- Categoria -->
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="category">Categoria *</label>
                        <select name="category" id="category" class="form-control" required>
                            <option value="">Selecione uma Categoria...</option>
                            <option value="Toxicologia Forense" {{ old('category', $post->category) == 'Toxicologia Forense' ? 'selected' : '' }}>Toxicologia Forense</option>
                            <option value="Necropsia/Exumação" {{ old('category', $post->category) == 'Necropsia/Exumação' ? 'selected' : '' }}>Necropsia/Exumação</option>
                            <option value="Assistência Técnica" {{ old('category', $post->category) == 'Assistência Técnica' ? 'selected' : '' }}>Assistência Técnica Judicial</option>
                            <option value="Maus-tratos" {{ old('category', $post->category) == 'Maus-tratos' ? 'selected' : '' }}>Maus-tratos e Bem-estar</option>
                            <option value="Responsabilidade Civil" {{ old('category', $post->category) == 'Responsabilidade Civil' ? 'selected' : '' }}>Responsabilidade Civil (Erro Veterinário)</option>
                            <option value="Consultoria e Cursos" {{ old('category', $post->category) == 'Consultoria e Cursos' ? 'selected' : '' }}>Consultoria e Cursos</option>
                            <option value="Outros" {{ old('category', $post->category) == 'Outros' ? 'selected' : '' }}>Outros</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Resumo -->
            <div class="form-group">
                <label for="summary">Resumo do Artigo *</label>
                <textarea name="summary" id="summary" class="form-control" rows="3" required placeholder="Descreva brevemente o artigo em 2 ou 3 frases.">{{ old('summary', $post->summary) }}</textarea>
                <small class="form-text text-muted">Máximo de 1000 caracteres.</small>
            </div>

            <div class="row">
                <!-- Imagem de Capa -->
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="image_file">Alterar Imagem de Capa (Opcional)</label>
                        <input type="file" name="image_file" id="image_file" class="form-control-file" accept="image/*">
                        <small class="form-text text-muted">Deixe em branco se não quiser alterar a imagem atual. Formatos: jpeg, png, jpg. Max: 2MB.</small>
                        
                        @if($post->image)
                            <div class="mt-3">
                                <p class="small mb-1 font-weight-bold">Imagem Atual:</p>
                                <img src="{{ str_starts_with($post->image, 'http') ? $post->image : asset($post->image) }}" class="rounded shadow-sm" style="max-width: 150px; height: auto;" alt="Capa Atual">
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Data de Publicação -->
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="published_at">Agendar Publicação (Deixe em branco para manter como rascunho)</label>
                        <input type="datetime-local" name="published_at" id="published_at" class="form-control" value="{{ old('published_at', $post->published_at ? $post->published_at->format('Y-m-d\TH:i') : '') }}">
                    </div>
                </div>
            </div>

            <!-- Conteúdo (CKEditor) -->
            <div class="form-group">
                <label for="content">Conteúdo Completo do Artigo *</label>
                <textarea name="content" id="content" class="form-control" rows="15" placeholder="Escreva o artigo científico aqui...">{{ old('content', $post->content) }}</textarea>
            </div>

            <div class="form-group mt-4">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Alterações</button>
                <a href="{{ route('admin.posts.index') }}" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<!-- CKEditor 5 CDN -->
<script src="https://cdn.ckeditor.com/ckeditor5/36.0.1/classic/ckeditor.js"></script>
<script>
    ClassicEditor
        .create(document.querySelector('#content'), {
            toolbar: [ 'heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote', 'undo', 'redo' ]
        })
        .catch(error => {
            console.error(error);
        });
</script>
@endpush
