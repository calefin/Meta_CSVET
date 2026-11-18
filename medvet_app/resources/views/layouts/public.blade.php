<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="@yield('meta_description', 'Medicina Veterinária Legal, Perícia Forense e Assistência Técnica Judicial Especializada. Pareceres técnicos, necropsias, toxicologia forense e erro médico veterinário com a Dra. Adriana de Siqueira e Dr. Atilio Calefi.')">
    <meta name="keywords" content="Medicina Veterinária Legal, Medicina Legal Veterinária, Veterinária Legal, Perícia Veterinária, Perito Veterinário Forense, Veterinary Legal Medicine, Assistência Técnica Judicial, Necropsia Forense, Toxicologia Veterinária, Erro Médico Veterinário, Adriana de Siqueira, Atilio Calefi, Laudo Pericial Veterinário, CSVET">
    <meta name="author" content="MedVetLegal">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="@yield('og_title', 'MedVet Legal | Medicina Veterinária Legal e Perícia Forense Especializada')">
    <meta property="og:description" content="@yield('og_description', 'Medicina Veterinária Legal, Perícia Forense e Assistência Técnica Judicial Especializada. Pareceres técnicos, necropsias, toxicologia forense e erro médico veterinário com a Dra. Adriana de Siqueira e Dr. Atilio Calefi.')">
    <meta property="og:image" content="@yield('og_image', asset('assets/hero-bg.jpg'))">
    <meta property="og:site_name" content="MedVet Legal">
    <meta property="og:locale" content="pt_BR">

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="{{ url()->current() }}">
    <meta name="twitter:title" content="@yield('og_title', 'MedVet Legal | Medicina Veterinária Legal e Perícia Forense Especializada')">
    <meta name="twitter:description" content="@yield('og_description', 'Medicina Veterinária Legal, Perícia Forense e Assistência Técnica Judicial Especializada. Pareceres técnicos, necropsias, toxicologia forense e erro médico veterinário com a Dra. Adriana de Siqueira e Dr. Atilio Calefi.')">
    <meta name="twitter:image" content="@yield('og_image', asset('assets/hero-bg.jpg'))">
    
    <title>@yield('title', 'MedVet Legal | Medicina Veterinária Legal e Perícia Forense Especializada')</title>
    
    <link rel="canonical" href="{{ url()->current() }}">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('assets/favicon-16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <meta name="theme-color" content="#0e2a47">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome 6 for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    
    <!-- Custom Style -->
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    
    @stack('styles')
    @stack('seo_schemas')
</head>
<body>

    <!-- Header Navigation -->
    <nav class="navbar navbar-expand-lg navbar-custom sticky-top">
        <div class="container">
            <a class="navbar-brand" href="{{ url('/') }}">
                <img src="{{ asset('assets/favicon.png') }}" alt="Logo MedVetLegal" height="40" class="d-inline-block align-middle me-2" style="border-radius: 4px; background-color: rgba(255,255,255,0.9); padding: 2px;">
                <span class="brand-title" style="color: var(--white) !important;">MEDVET <span style="color: var(--accent-color) !important;">LEGAL</span></span>
            </a>
            <button class="navbar-toggler navbar-dark" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav align-items-center">
                    <li class="nav-item">
                        <a class="nav-link {{ Request::is('/') ? 'active' : '' }}" href="{{ url('/') }}#home">Início</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ url('/') }}#especialistas">Corpo Técnico</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ url('/') }}#servicos">Atuação</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ url('/blog') }}">Blog</a>
                    </li>
                    @auth
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('admin.dashboard') }}">Painel Admin</a>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('login') }}">Login</a>
                        </li>
                    @endauth
                    <li class="nav-item ms-lg-3">
                        <a class="nav-link btn-nav-contact" href="{{ url('/') }}#contato">Fale Conosco</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content Yield -->
    <main>
        @yield('content')
    </main>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row gy-5">
                <div class="col-lg-4 col-md-6">
                    <h5 class="brand-title">
                        <img src="{{ asset('assets/favicon.png') }}" alt="Logo MedVetLegal" height="35" class="d-inline-block align-middle me-2" style="border-radius: 4px; background-color: rgba(255,255,255,0.9); padding: 2px;">
                        MEDVET LEGAL
                    </h5>
                    <p class="mt-3">
                        Excelência técnica e ética na Medicina Veterinária Legal. Prestamos suporte altamente especializado para advogados, tutores de animais, clínicas veterinárias, indústrias e órgãos do judiciário em todo o território nacional.
                    </p>
                    <div class="footer-social-links">
                        <a href="https://www.youtube.com/@csvet" target="_blank" class="footer-social-btn"><i class="fab fa-youtube"></i></a>
                        <a href="https://www.instagram.com/atiliocalefi" target="_blank" class="footer-social-btn"><i class="fab fa-instagram"></i></a>
                        <a href="mailto:contato@medvetlegal.com.br" class="footer-social-btn"><i class="fas fa-envelope"></i></a>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <h5>Links Rápidos</h5>
                    <ul class="footer-links-list mt-3">
                        <li><a href="{{ url('/') }}#home"><i class="fas fa-chevron-right me-2"></i>Início</a></li>
                        <li><a href="{{ url('/') }}#especialistas"><i class="fas fa-chevron-right me-2"></i>Corpo Técnico</a></li>
                        <li><a href="{{ url('/') }}#servicos"><i class="fas fa-chevron-right me-2"></i>Áreas de Atuação</a></li>
                        <li><a href="{{ url('/blog') }}"><i class="fas fa-chevron-right me-2"></i>Artigos Técnicos (Blog)</a></li>
                        <li><a href="{{ route('terms') }}"><i class="fas fa-chevron-right me-2"></i>Termos de Uso & LGPD</a></li>
                        <li><a href="{{ route('login') }}"><i class="fas fa-chevron-right me-2"></i>Área Restrita (Login)</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 col-md-12">
                    <h5>Contato & Localização</h5>
                    <ul class="footer-links-list mt-3">
                        <li>
                            <span class="d-block text-white"><i class="fas fa-envelope text-warning me-2"></i> E-mail Geral:</span>
                            <a href="mailto:contato@medvetlegal.com.br" class="d-block py-1">contato@medvetlegal.com.br</a>
                        </li>
                        <li>
                            <span class="d-block text-white"><i class="fas fa-envelope text-warning me-2"></i> Dra. Adriana de Siqueira:</span>
                            <a href="mailto:patoforense.adriana@gmail.com" class="d-block py-1">patoforense.adriana@gmail.com</a>
                        </li>
                        <li>
                            <span class="d-block text-white"><i class="fas fa-envelope text-warning me-2"></i> Dr. Atilio S. Calefi:</span>
                            <a href="mailto:calefi@csvet.com.br" class="d-block py-1">calefi@csvet.com.br</a>
                        </li>
                        <li>
                            <span class="d-block text-white"><i class="fas fa-map-marker-alt text-warning me-2"></i> Atendimento:</span>
                            <span class="d-block small mt-1">São Paulo - SP, Brasil (Atuação Nacional)</span>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="copyright-bar">
                <p class="mb-0">&copy; {{ date('Y') }} MedVetLegal. Todos os direitos reservados. CSVET Editora, Cursos e Treinamentos.</p>
            </div>
        </div>
    </footer>

    <!-- WhatsApp floating button -->
    <a href="https://wa.me/5511993373349?text=Ol%C3%A1%2C%20vim%20pelo%20site%20da%20MedVetLegal%20e%20gostaria%20de%20solicitar%20uma%20an%C3%A1lise%20t%C3%A9cnica%20do%20meu%20caso.%20Fico%20no%20aguardo%20do%20retorno.%20Obrigado(a)!"
       target="_blank" rel="noopener" class="whatsapp-float" aria-label="Falar com a MedVetLegal no WhatsApp">
        <i class="fab fa-whatsapp"></i>
    </a>

    <!-- Bootstrap 5 Bundle JS (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    @stack('scripts')
</body>
</html>
