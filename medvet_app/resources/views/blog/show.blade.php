@extends('layouts.public')

@section('title', $post->title . ' | Medicina Veterinária Legal - MedVet Legal')
@section('meta_description', $post->summary)

@section('og_title', $post->title . ' | MedVet Legal')
@section('og_description', $post->summary)
@section('og_type', 'article')
@section('og_image', $post->image ? (str_starts_with($post->image, 'http') ? $post->image : asset($post->image)) : asset('assets/hero-bg.jpg'))

@section('content')

    <!-- Article Header Background -->
    <div class="article-header-bg" style="height: 320px; background: linear-gradient(rgba(7, 23, 41, 0.8), rgba(14, 42, 71, 0.85)), url('{{ $post->image ? (str_starts_with($post->image, 'http') ? $post->image : asset($post->image)) : asset('assets/hero-bg.jpg') }}'); background-size: cover; background-position: center;"></div>

    <!-- Main Content Container -->
    <div class="container mb-5" style="margin-top: -100px; position: relative; z-index: 10;">
        <div class="row">
            
            <!-- Article Body (Left Column) -->
            <div class="col-lg-8 mb-4">
                <article class="p-5 bg-white rounded shadow-sm" style="box-shadow: 0 15px 35px rgba(0,0,0,0.05) !important;">
                    
                    <!-- Category Badge -->
                    <span class="badge mb-3" style="background-color: var(--accent-color); color: var(--primary-color); font-weight: bold; padding: 6px 12px;">
                        {{ $post->category }}
                    </span>
                    
                    <!-- Article Title -->
                    <h1 class="mb-4 font-playfair text-primary display-5" style="font-weight: 700; line-height: 1.25;">
                        {{ $post->title }}
                    </h1>
                    
                    <!-- Metadata Info -->
                    <div class="d-flex align-items-center text-muted small pb-3 mb-4 border-bottom">
                        <div class="me-3">
                            <i class="far fa-calendar-alt me-2"></i>
                            <span>{{ $post->published_at->format('d/m/Y') }}</span>
                        </div>
                        <div>
                            <i class="far fa-user me-2"></i>
                            <span>Publicado por <strong>{{ $post->author->name ?? 'Dr. Atilio S. Calefi' }}</strong></span>
                        </div>
                    </div>
                    
                    <!-- Rich-text HTML Content -->
                    <div class="article-rich-text mt-4">
                        {!! $post->content !!}
                    </div>

                    <!-- Compartilhar Artigo -->
                    <div class="mt-5 pt-3 border-top">
                        <span class="small text-muted font-weight-bold d-block mb-2">COMPARTILHE ESTE ARTIGO:</span>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="https://api.whatsapp.com/send?text={{ urlencode($post->title . ' - ' . route('blog.show', $post->slug)) }}" target="_blank" class="btn btn-sm btn-success px-3">
                                <i class="fab fa-whatsapp me-1"></i> WhatsApp
                            </a>
                            <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode(route('blog.show', $post->slug)) }}" target="_blank" class="btn btn-sm btn-primary px-3" style="background-color: #0077b5; border-color: #0077b5;">
                                <i class="fab fa-linkedin me-1"></i> LinkedIn
                            </a>
                            <a href="mailto:?subject={{ urlencode($post->title) }}&body={{ urlencode('Confira este artigo da MedVetLegal: ' . route('blog.show', $post->slug)) }}" class="btn btn-sm btn-secondary px-3">
                                <i class="fas fa-envelope me-1"></i> E-mail
                            </a>
                        </div>
                    </div>
                    
                    <!-- Author Information Box -->
                    <div class="author-box p-4 mt-5 rounded bg-light border-start border-4" style="border-left-color: var(--accent-color) !important;">
                        <div class="d-flex flex-column flex-sm-row align-items-center align-items-sm-start gap-4">
                            @if(Str::contains(strtolower($post->title), 'adriana') || $post->category == 'Necropsia/Exumação')
                                <img src="{{ asset('assets/adriana.jpg') }}" alt="Dra. Adriana de Siqueira" class="rounded-circle border" style="width: 100px; height: 100px; object-fit: cover; border-width: 3px !important; border-color: var(--primary-color) !important; flex-shrink: 0;">
                                <div>
                                    <h5 class="font-playfair text-primary font-weight-bold mb-1">Sobre a Autora</h5>
                                    <p class="small text-muted mb-2"><strong>Profa. Dra. Adriana de Siqueira</strong></p>
                                    <p class="small text-muted mb-0">Doutora em Patologia Forense pela USP. Autora da tese de referência nacional para detecção de carbamatos ("chumbinho") em exumação. Perita judicial e pesquisadora premiada internacionalmente pela IVFSA.</p>
                                    <a href="http://lattes.cnpq.br/8627498416487640" target="_blank" class="d-inline-block small fw-bold text-decoration-none mt-2" style="color: var(--accent-color);"><i class="fas fa-file-text me-1"></i>Currículo Lattes</a>
                                </div>
                            @else
                                <img src="{{ asset('assets/atilio.jpg') }}" alt="Prof. Dr. Atilio Sersun Calefi" class="rounded-circle border" style="width: 100px; height: 100px; object-fit: cover; border-width: 3px !important; border-color: var(--primary-color) !important; flex-shrink: 0;">
                                <div>
                                    <h5 class="font-playfair text-primary font-weight-bold mb-1">Sobre o Autor</h5>
                                    <p class="small text-muted mb-2"><strong>Prof. Dr. Atilio Sersun Calefi</strong></p>
                                    <p class="small text-muted mb-0">Pós-Doutor em Farmacologia Veterinária pela USP. Médico Veterinário e Biólogo. Consultor de toxicologia, farmacologia e patologia forense. Coordenador da CSVET Editora e preceptor.</p>
                                    <a href="http://lattes.cnpq.br/4438211760493798" target="_blank" class="d-inline-block small fw-bold text-decoration-none mt-2" style="color: var(--accent-color);"><i class="fas fa-file-text me-1"></i>Currículo Lattes</a>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Call to Action -->
                    <div class="card bg-primary text-white p-4 mt-4 border-0 text-center" style="border-radius: 8px;">
                        <h4 class="font-playfair text-white mb-2">Precisa de suporte técnico judicial?</h4>
                        <p class="small opacity-75 mb-3">Oferecemos consultoria, auditoria de prontuários, formulação de quesitos e necropsias forenses.</p>
                        <div>
                            <a href="{{ url('/') }}#contato" class="btn btn-gold px-4">
                                <i class="fas fa-file-signature me-2"></i>Falar com a Equipe
                            </a>
                        </div>
                    </div>

                </article>
            </div>

            <!-- Sidebar (Right Column) -->
            <div class="col-lg-4 ps-lg-4 mb-4">
                
                <!-- Recent posts -->
                <div class="card p-4 border-0 mb-4" style="box-shadow: var(--card-shadow); border-radius: 8px;">
                    <h5 class="font-playfair text-primary font-weight-bold pb-2 mb-3 border-bottom" style="border-bottom-color: #eee !important;">Artigos Recentes</h5>
                    
                    @if($recentPosts->isEmpty())
                        <p class="small text-muted mb-0">Nenhum outro artigo publicado.</p>
                    @else
                        <ul class="list-unstyled mb-0">
                            @foreach($recentPosts as $recent)
                                <li class="mb-3 pb-3 border-bottom" style="border-bottom-color: #f7fafc !important;">
                                    <span class="badge mb-1" style="background-color: var(--bg-light); color: var(--primary-color); font-size: 0.7rem; font-weight: bold;">
                                        {{ $recent->category }}
                                    </span>
                                    <a href="{{ route('blog.show', $recent->slug) }}" class="d-block font-playfair text-decoration-none text-primary fw-bold" style="font-size: 0.95rem; line-height: 1.3;">
                                        {{ $recent->title }}
                                    </a>
                                    <small class="text-muted d-block mt-1">{{ $recent->published_at->format('d/m/Y') }}</small>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <!-- Back to Blog Button -->
                <div class="d-grid">
                    <a href="{{ route('blog.index') }}" class="btn btn-nav-contact py-3 text-center fw-bold">
                        <i class="fas fa-arrow-left me-2"></i>Voltar ao Blog
                    </a>
                </div>
            </div>

        </div>
    </div>

@push('seo_schemas')
<script type="application/ld+json">
{
  "@@context": "https://schema.org",
  "@@type": "BlogPosting",
  "headline": "{{ $post->title }}",
  "description": "{{ $post->summary }}",
  "image": "{{ $post->image ? (str_starts_with($post->image, 'http') ? $post->image : asset($post->image)) : asset('assets/hero-bg.jpg') }}",
  "datePublished": "{{ $post->published_at ? $post->published_at->toIso8601String() : $post->created_at->toIso8601String() }}",
  "dateModified": "{{ $post->updated_at->toIso8601String() }}",
  "author": {
    "@@type": "Person",
    "name": "{{ $post->author->name ?? 'Dra. Adriana de Siqueira' }}"
  },
  "publisher": {
    "@@type": "Organization",
    "name": "MedVetLegal",
    "logo": {
      "@@type": "ImageObject",
      "url": "{{ asset('assets/favicon.png') }}"
    }
  },
  "mainEntityOfPage": {
    "@@type": "WebPage",
    "@@id": "{{ route('blog.show', $post->slug) }}"
  }
}
</script>
@endpush

@endsection
