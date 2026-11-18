@extends('layouts.public')

@section('title', 'Página Não Encontrada (404) | MedVet Legal')
@section('meta_description', 'A página solicitada não foi encontrada ou foi movida. Acesse nossos artigos em Medicina Veterinária Legal e serviços de perícia forense.')

@section('content')
<section class="section-padding text-center" style="min-height: 70vh; display: flex; align-items: center; justify-content: center; background-color: var(--bg-light);">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card p-5 border-0 shadow-sm rounded-4">
                    <div class="mb-4">
                        <span class="display-1 fw-bold" style="color: var(--accent-color); font-family: 'Playfair Display', serif;">404</span>
                    </div>
                    <h2 class="font-playfair text-primary mb-3">Página Não Encontrada</h2>
                    <p class="text-muted fs-5 mb-4">
                        O endereço que você tentou acessar pode ter sido atualizado, renomeado ou transferido durante a modernização da nossa plataforma.
                    </p>
                    <div class="d-flex flex-wrap gap-3 justify-content-center mb-4">
                        <a href="{{ url('/') }}" class="btn btn-gold px-4 py-2 fw-bold">
                            <i class="fas fa-home me-2"></i>Ir para a Página Inicial
                        </a>
                        <a href="{{ route('blog.index') }}" class="btn btn-outline-primary px-4 py-2 fw-bold">
                            <i class="fas fa-newspaper me-2"></i>Ver Artigos Científicos (Blog)
                        </a>
                        <a href="{{ url('/') }}#contato" class="btn btn-outline-secondary px-4 py-2 fw-bold">
                            <i class="fas fa-envelope me-2"></i>Falar Conosco
                        </a>
                    </div>
                    <div class="mt-4 pt-4 border-top">
                        <h5 class="font-playfair text-primary mb-3">Artigos Recomendados:</h5>
                        <ul class="list-unstyled mb-0 d-flex flex-column gap-2 text-start">
                            <li>
                                <a href="{{ route('blog.show', 'o-que-e-medicina-veterinaria-legal-areas-atuacao-pericia') }}" class="text-decoration-none fw-bold" style="color: var(--accent-color);">
                                    <i class="fas fa-chevron-right me-2 small"></i>Medicina Veterinária Legal: O que é e áreas de atuação
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('blog.show', 'diferenca-pericia-assistencia-tecnica-laudo-veterinario') }}" class="text-decoration-none fw-bold" style="color: var(--accent-color);">
                                    <i class="fas fa-chevron-right me-2 small"></i>Perícia, Assistência Técnica e Laudo Veterinário: Entenda as Diferenças
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('blog.show', 'erro-veterinario-como-provar-negligencia-imprudencia-impericia') }}" class="text-decoration-none fw-bold" style="color: var(--accent-color);">
                                    <i class="fas fa-chevron-right me-2 small"></i>Erro Veterinário: Como provar negligência, imprudência ou imperícia?
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
