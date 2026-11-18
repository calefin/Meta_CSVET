@extends('layouts.admin')

@section('title', 'Blog - Artigos')
@section('header')
    <h1>Artigos do Blog <a href="{{ route('admin.posts.create') }}" class="btn btn-primary btn-sm float-right"><i class="fas fa-plus"></i> Novo Artigo</a></h1>
@endsection

@section('content')
<div class="card">
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Capa</th>
                    <th>Título</th>
                    <th>Categoria</th>
                    <th>Autor</th>
                    <th>Publicado Em</th>
                    <th>Criado Em</th>
                    <th class="text-right">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($posts as $post)
                <tr>
                    <td>{{ $post->id }}</td>
                    <td>
                        @if($post->image)
                            <img src="{{ str_starts_with($post->image, 'http') ? $post->image : asset($post->image) }}" class="rounded shadow-sm" style="width: 50px; height: 35px; object-fit: cover;" alt="Capa">
                        @else
                            <span class="badge badge-secondary">Sem capa</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('blog.show', $post->slug) }}" target="_blank" class="font-weight-bold">
                            {{ Str::limit($post->title, 45) }}
                        </a>
                    </td>
                    <td>
                        <span class="badge badge-info">{{ $post->category }}</span>
                    </td>
                    <td>{{ $post->author->name ?? 'Admin' }}</td>
                    <td>
                        @if($post->published_at)
                            <span class="badge badge-success">{{ $post->published_at->format('d/m/Y H:i') }}</span>
                        @else
                            <span class="badge badge-warning">Rascunho</span>
                        @endif
                    </td>
                    <td>{{ $post->created_at->format('d/m/Y H:i') }}</td>
                    <td class="text-right">
                        <div class="btn-group">
                            <a href="{{ route('admin.posts.edit', $post) }}" class="btn btn-sm btn-warning" title="Editar"><i class="fas fa-edit"></i></a>
                            <form action="{{ route('admin.posts.destroy', $post) }}" method="POST" onsubmit="return confirm('Deseja realmente excluir este artigo?');" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger" title="Excluir"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-4">Nenhum artigo cadastrado. Clique em <strong>Novo Artigo</strong> para criar o primeiro.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($posts->hasPages())
    <div class="card-footer">
        {{ $posts->links() }}
    </div>
    @endif
</div>
@endsection
