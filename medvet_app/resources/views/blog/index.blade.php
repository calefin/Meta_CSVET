@extends('layouts.public')

@section('title', 'Blog de Medicina Veterinária Legal e Perícia Forense | MedVet Legal')
@section('meta_description', 'Artigos científicos, exames periciais e análises em Medicina Veterinária Legal com a Dra. Adriana de Siqueira e o Prof. Dr. Atilio S. Calefi.')

@section('og_title', 'Blog de Medicina Veterinária Legal e Perícia Forense | MedVet Legal')
@section('og_description', 'Artigos científicos, exames periciais e análises em Medicina Veterinária Legal com a Dra. Adriana de Siqueira e o Prof. Dr. Atilio S. Calefi.')
@section('og_image', asset('assets/hero-bg.jpg'))

@section('content')

    <!-- Blog Header Banner -->
    <header class="blog-header" style="background: linear-gradient(rgba(7, 23, 41, 0.9), rgba(14, 42, 71, 0.85)), url('{{ asset('assets/hero-bg.jpg') }}'); background-size: cover; background-position: center; padding: 100px 0 60px; text-align: center; color: white;">
        <div class="container">
            <h1 class="display-4 text-white font-playfair">Medicina Veterinária Legal & Divulgação Científica</h1>
            <p class="lead opacity-75 fs-5">Ciência médico-veterinária forense, perícia judicial e toxicologia explicadas com rigor científico.</p>
        </div>
    </header>

    <!-- Main Blog Feed Container -->
    <div class="container py-5">
        <div class="row">
            
            <!-- Feed de Artigos (Esquerda) -->
            <div class="col-lg-8">
                @if($posts->isEmpty())
                    <div class="text-center py-5 bg-light rounded shadow-sm">
                        <i class="far fa-newspaper fa-4x text-muted mb-3"></i>
                        <h4 class="text-muted">Nenhum artigo publicado no momento</h4>
                        <p class="text-muted">Volte mais tarde para ler novas análises científicas.</p>
                    </div>
                @else
                    <div class="row g-4">
                        @foreach ($posts as $post)
                            <div class="col-md-6">
                                <article class="card service-card h-100 p-0 overflow-hidden" style="border: none; box-shadow: var(--card-shadow);">
                                    <!-- Post Image -->
                                    <div style="height: 220px; overflow: hidden; position: relative;">
                                        <img src="{{ $post->image ? (str_starts_with($post->image, 'http') ? $post->image : asset($post->image)) : asset('assets/hero-bg.jpg') }}" class="w-100 h-100" style="object-fit: cover; transition: var(--transition-smooth);" alt="{{ $post->title }}">
                                        <span class="position-absolute top-0 start-0 m-3 badge" style="background-color: var(--accent-color); color: var(--primary-color); font-weight: bold; padding: 6px 12px;">
                                            {{ $post->category }}
                                        </span>
                                    </div>
                                    
                                    <!-- Card Content -->
                                    <div class="card-body d-flex flex-column p-4">
                                        <div class="d-flex align-items-center text-muted small mb-2">
                                            <i class="far fa-calendar-alt me-2"></i>
                                            <span>{{ $post->published_at->format('d/m/Y') }}</span>
                                            <span class="mx-2">|</span>
                                            <i class="far fa-user me-2"></i>
                                            <span>{{ $post->author->name ?? 'MedVetLegal' }}</span>
                                        </div>
                                        
                                        <h4 class="card-title text-primary font-playfair mb-3" style="font-size: 1.25rem; line-height: 1.4;">
                                            {{ $post->title }}
                                        </h4>
                                        
                                        <p class="text-muted small mb-4">
                                            {{ Str::limit($post->summary, 120) }}
                                        </p>
                                        
                                        <div class="mt-auto pt-3 border-top">
                                            <a href="{{ route('blog.show', $post->slug) }}" class="fw-bold text-decoration-none" style="color: var(--accent-color); transition: var(--transition-smooth);">
                                                Ler Artigo Completo <i class="fas fa-arrow-right ms-1"></i>
                                            </a>
                                        </div>
                                    </div>
                                </article>
                            </div>
                        @endforeach
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-center mt-5">
                        {{ $posts->links() }}
                    </div>
                @endif
            </div>

            <!-- Sidebar (Direita) -->
            <div class="col-lg-4 mt-5 mt-lg-0 ps-lg-4">
                
                <!-- Perfil Dr. Atilio -->
                <div class="card p-4 text-center border-0 mb-4" style="box-shadow: var(--card-shadow); border-radius: 8px;">
                    <div class="position-relative d-inline-block mx-auto mb-3">
                        <img src="{{ asset('assets/atilio.jpg') }}" alt="Prof. Dr. Atilio Sersun Calefi" class="rounded-circle border" style="width: 130px; height: 130px; object-fit: cover; border-width: 4px !important; border-color: var(--accent-color) !important;">
                    </div>
                    <h5 class="font-playfair text-primary font-weight-bold mb-1">Prof. Dr. Atilio S. Calefi</h5>
                    <p class="small text-muted mb-3">Médico Veterinário e Biólogo. Pós-Doutor em Farmacologia pela USP. Especializado em patologia experimental e análises laboratoriais forenses.</p>
                    
                    <div class="d-flex justify-content-center gap-3">
                        <a href="https://www.youtube.com/@csvet" target="_blank" class="btn btn-outline-dark btn-sm rounded-circle d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;"><i class="fab fa-youtube"></i></a>
                        <a href="https://www.instagram.com/atiliocalefi" target="_blank" class="btn btn-outline-dark btn-sm rounded-circle d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>

                <!-- Notificação Cursos CSVET -->
                <div class="card border-0 text-white p-4" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-light)); box-shadow: var(--card-shadow); border-radius: 8px;">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="d-flex align-items-center justify-content-center bg-warning text-dark rounded-circle" style="width: 45px; height: 45px; flex-shrink:0;">
                            <i class="fas fa-graduation-cap fa-lg"></i>
                        </div>
                        <h5 class="font-playfair text-white mb-0" style="font-size: 1.15rem;">Cursos & Certificações</h5>
                    </div>
                    <p class="small opacity-75">
                        Em breve, lançaremos a plataforma exclusiva CSVET, contendo cursos sobre responsabilidade civil, preenchimento de termos e procedimentos periciais veterinários, com emissão automatizada de certificados.
                    </p>
                    <a href="{{ url('/') }}#contato" class="btn btn-gold btn-sm w-100 fw-bold py-2 mt-2">
                        Registrar Interesse
                    </a>
                </div>
            </div>

        </div>
    </div>

@endsection
