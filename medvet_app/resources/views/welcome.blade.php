@extends('layouts.public')

@section('title', 'MedVet Legal | Medicina Veterinária Legal, Perícia e Assistência Técnica Forense')
@section('meta_description', 'Especialistas em Medicina Veterinária Legal, perícia forense e assistência técnica judicial. Pareceres técnicos, necropsias, toxicologia e auditoria com a Dra. Adriana de Siqueira e Dr. Atilio S. Calefi.')

@section('og_title', 'MedVet Legal | Medicina Veterinária Legal e Perícia Forense Especializada')
@section('og_description', 'Especialistas em Medicina Veterinária Legal, perícia forense e assistência técnica judicial. Pareceres técnicos, necropsias, toxicologia e auditoria com a Dra. Adriana de Siqueira e Dr. Atilio S. Calefi.')
@section('og_image', asset('assets/hero-bg.jpg'))

@section('content')

    <!-- Hero Section -->
    <header id="home" class="hero-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10 animated fadeInUp">
                    <h1 class="hero-title">Medicina Veterinária Legal e Perícia Forense Especializada</h1>
                    <p class="hero-subtitle">
                        Pareceres técnicos, perícia judicial e assistência técnica em Medicina Veterinária Legal, Patologia, Toxicologia e auditoria de processos perante a Justiça, sob a coordenação científica da perita Dra. Adriana de Siqueira e consultoria do Dr. Atilio S. Calefi.
                    </p>
                    <a href="#contato" class="btn btn-gold">
                        <i class="fas fa-file-invoice-dollar me-2"></i>Solicitar Análise de Caso
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Trust / Social Proof Bar -->
    <div class="trust-bar">
        <div class="container">
            <div class="row gy-4 trust-bar-inner">
                <div class="col-6 col-md-3 trust-item">
                    <i class="fas fa-graduation-cap"></i>
                    <span>Doutorado pela USP</span>
                </div>
                <div class="col-6 col-md-3 trust-item">
                    <i class="fas fa-award"></i>
                    <span>Prêmio Internacional IVFSA</span>
                </div>
                <div class="col-6 col-md-3 trust-item">
                    <i class="fas fa-map-marked-alt"></i>
                    <span>Atendimento em Todo o Brasil</span>
                </div>
                <div class="col-6 col-md-3 trust-item">
                    <i class="fas fa-user-shield"></i>
                    <span>Sigilo Profissional Absoluto</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Specialists Section -->
    <section id="especialistas" class="section-padding bg-light">
        <div class="container">
            <div class="text-center">
                <h2 class="section-title">Corpo Técnico em Medicina Veterinária Legal</h2>
                <p class="section-desc">
                    Unimos a Medicina Veterinária Legal e a ciência forense ao Direito para fornecer laudos periciais e pareceres técnicos de máxima robustez probatória.
                </p>
            </div>

            <div class="row g-5 justify-content-center mt-3">
                <!-- Dra. Adriana Profile -->
                <div class="col-lg-5 col-md-6">
                    <div class="card profile-card h-100">
                        <div class="profile-header"></div>
                        <div class="card-body text-center px-4 pb-5">
                            <img src="{{ asset('assets/adriana.jpg') }}" alt="Dra. Adriana de Siqueira - Perita em Medicina Veterinária Legal" class="profile-img">
                            <h3>Profa. Dra. Adriana de Siqueira</h3>
                            <div class="profile-role">Perita em Medicina Veterinária Legal & Coordenadora Técnica</div>
                            
                            <div class="profile-features">
                                <div class="profile-feature-item">
                                    <i class="fas fa-graduation-cap"></i>
                                    <span><strong>Doutora e Mestre em Patologia (USP):</strong> Tese de doutorado referência nacional sobre detecção de Carbamatos (chumbinho) em animais exumados.</span>
                                </div>
                                <div class="profile-feature-item">
                                    <i class="fas fa-award"></i>
                                    <span><strong>Premiada Internacionalmente:</strong> Vencedora do <em>IVFSA Student Award</em> da International Veterinary Forensic Sciences Association.</span>
                                </div>
                                <div class="profile-feature-item">
                                    <i class="fas fa-balance-scale"></i>
                                    <span><strong>Multidisciplinaridade:</strong> Graduada em Medicina Veterinária (UFPR) e Ciências Econômicas (USP).</span>
                                </div>
                                <div class="profile-feature-item">
                                    <i class="fas fa-check-double"></i>
                                    <span><strong>Atuação Especializada:</strong> Elucidação de <em>causa mortis</em>, exumação técnica e averiguação de crueldade e maus-tratos.</span>
                                </div>
                            </div>
                            
                            <div class="profile-card-footer">
                                <a href="http://lattes.cnpq.br/8627498416487640" target="_blank" class="btn btn-lattes">
                                    <i class="fas fa-file-text me-2"></i>Ver Currículo Lattes
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dr. Atilio Profile -->
                <div class="col-lg-5 col-md-6">
                    <div class="card profile-card h-100">
                        <div class="profile-header"></div>
                        <div class="card-body text-center px-4 pb-5">
                            <img src="{{ asset('assets/atilio.jpg') }}" alt="Prof. Dr. Atilio Sersun Calefi - Especialista em Medicina Veterinária Legal e Farmacologia" class="profile-img">
                            <h3>Prof. Dr. Atilio Sersun Calefi</h3>
                            <div class="profile-role">Consultor Científico em Medicina Veterinária Legal & Farmacologia</div>
                            
                            <div class="profile-features">
                                <div class="profile-feature-item">
                                    <i class="fas fa-graduation-cap"></i>
                                    <span><strong>Pós-Doutor em Farmacologia (USP):</strong> Pesquisa avançada em toxicologia veterinária e neuroimunomodulação.</span>
                                </div>
                                <div class="profile-feature-item">
                                    <i class="fas fa-microscope"></i>
                                    <span><strong>Dupla Formação Científica:</strong> Graduado em Medicina Veterinária (UEL) e Ciências Biológicas (UNICSUL).</span>
                                </div>
                                <div class="profile-feature-item">
                                    <i class="fas fa-book-reader"></i>
                                    <span><strong>Atuação Educacional:</strong> Diretor da CSVET Editora, Cursos e Treinamentos, preceptor de patologia e palestrante nacional.</span>
                                </div>
                                <div class="profile-feature-item">
                                    <i class="fas fa-vials"></i>
                                    <span><strong>Atuação Especializada:</strong> Análise crítica de exames toxicológicos complexos e consultoria farmacêutica especializada.</span>
                                </div>
                            </div>
                            
                            <div class="profile-card-footer">
                                <a href="http://lattes.cnpq.br/4438211760493798" target="_blank" class="btn btn-lattes">
                                    <i class="fas fa-file-text me-2"></i>Ver Currículo Lattes
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="servicos" class="section-padding">
        <div class="container">
            <div class="text-center">
                <h2 class="section-title">Áreas de Atuação em Medicina Veterinária Legal</h2>
                <p class="section-desc">
                    Prestamos suporte técnico em Medicina Veterinária Legal para médicos veterinários, responsáveis por animais, seguradoras, produtores rurais e escritórios de advocacia.
                </p>
            </div>

            <div class="row g-4 mt-2">
                <!-- Servico 1 -->
                <div class="col-lg-4 col-md-6">
                    <div class="service-card text-center">
                        <div class="service-icon-wrapper">
                            <i class="fas fa-skull-crossbones"></i>
                        </div>
                        <h4>Toxicologia Forense</h4>
                        <p>
                            Especialistas em laudos para suspeitas de intoxicação (como carbamatos / "chumbinho"), erro de dosagem medicamentosa e reações adversas graves. Análise crítica laboratorial.
                        </p>
                    </div>
                </div>

                <!-- Servico 2 -->
                <div class="col-lg-4 col-md-6">
                    <div class="service-card text-center">
                        <div class="service-icon-wrapper">
                            <i class="fas fa-search"></i>
                        </div>
                        <h4>Necropsia e Exumação</h4>
                        <p>
                            Acompanhamento e execução de necropsias com rígido controle de cadeia de custódia. Elaboração de pareceres sobre laudos oficiais e realização de exumações.
                        </p>
                    </div>
                </div>

                <!-- Servico 3 -->
                <div class="col-lg-4 col-md-6">
                    <div class="service-card text-center">
                        <div class="service-icon-wrapper">
                            <i class="fas fa-gavel"></i>
                        </div>
                        <h4>Assistência Técnica Judicial</h4>
                        <p>
                            Formulação de quesitos periciais, acompanhamento presencial de diligências, assistência na redação de petições técnicas e contestação de laudos periciais desfavoráveis.
                        </p>
                    </div>
                </div>

                <!-- Servico 4 -->
                <div class="col-lg-4 col-md-6">
                    <div class="service-card text-center">
                        <div class="service-icon-wrapper">
                            <i class="fas fa-file-medical-alt"></i>
                        </div>
                        <h4>Auditoria de Erro Veterinário</h4>
                        <p>
                            Análise crítica detalhada de prontuários, termos de consentimento, receitas e prontuários clínicos para avaliação de nexo causal e conformidade ética e técnica.
                        </p>
                    </div>
                </div>

                <!-- Servico 5 -->
                <div class="col-lg-4 col-md-6">
                    <div class="service-card text-center">
                        <div class="service-icon-wrapper">
                            <i class="fas fa-paw"></i>
                        </div>
                        <h4>Maus-tratos e Bem-estar</h4>
                        <p>
                            Emissão de laudos periciais caracterizando a ocorrência de maus-tratos físicos, psicológicos, negligência de cuidados ou colecionismo de animais (Síndrome de Noé).
                        </p>
                    </div>
                </div>

                <!-- Servico 6 -->
                <div class="col-lg-4 col-md-6">
                    <div class="service-card text-center">
                        <div class="service-icon-wrapper">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <h4>Cursos e Treinamentos</h4>
                        <p>
                            Palestras e capacitações corporativas sobre responsabilidade civil do médico veterinário, gestão de risco legal para hospitais e procedimentos periciais forenses.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact & Quote Request Section -->
    <section id="contato" class="section-padding contact-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-10">
                    <div class="card contact-card">
                        <div class="row g-0">
                            <!-- Info Panel -->
                            <div class="col-lg-5 contact-info-panel">
                                <h3>Fale com a Perita Principal</h3>
                                <p class="mb-5 opacity-75">
                                    Preencha o formulário para submeter seu caso diretamente para a avaliação de viabilidade técnica da Dra. Adriana de Siqueira. Asseguramos sigilo absoluto.
                                </p>
                                
                                <div class="contact-info-item">
                                    <div class="contact-info-icon">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <div class="contact-info-text">
                                        <h5>E-mail Direto da Perita</h5>
                                        <a href="mailto:patoforense.adriana@gmail.com">patoforense.adriana@gmail.com</a>
                                    </div>
                                </div>
                                
                                <div class="contact-info-item">
                                    <div class="contact-info-icon">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </div>
                                    <div class="contact-info-text">
                                        <h5>Abrangência</h5>
                                        <p>Base em São Paulo / SP - Atendimento presencial e remoto em todo o Brasil.</p>
                                    </div>
                                </div>

                                <div class="contact-info-item">
                                    <div class="contact-info-icon">
                                        <i class="fas fa-lock"></i>
                                    </div>
                                    <div class="contact-info-text">
                                        <h5>Sigilo</h5>
                                        <p>Todas as comunicações e documentos enviados são protegidos por estrito segredo profissional.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Form Panel -->
                            <div class="col-lg-7 contact-form-panel">
                                <h3>Solicitação à Dra. Adriana</h3>
                                <p class="text-muted mb-4">Sua descrição do caso e dúvidas serão analisadas diretamente pela perita responsável.</p>
                                
                                @if(session('success'))
                                    <div id="mensagem-sucesso" class="alert alert-success p-4 rounded-3 shadow-sm mb-4 border-0" style="background-color: #d4edda; border-left: 5px solid #28a745 !important;">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-check-circle text-success fa-2x me-3"></i>
                                            <div>
                                                <h5 class="alert-heading mb-1 text-success font-weight-bold" style="font-weight: 700;">Solicitação Enviada com Sucesso!</h5>
                                                <p class="mb-0 text-dark" style="font-size: 0.95rem;">{{ session('success') }}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <script>
                                        document.addEventListener("DOMContentLoaded", function() {
                                            var el = document.getElementById("mensagem-sucesso");
                                            if (el) {
                                                el.scrollIntoView({ behavior: "smooth", block: "center" });
                                            }
                                        });
                                    </script>
                                @endif

                                @if($errors->any())
                                    <div id="mensagem-erro" class="alert alert-danger p-4 rounded-3 shadow-sm mb-4 border-0" style="background-color: #f8d7da; border-left: 5px solid #dc3545 !important;">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-exclamation-triangle text-danger fa-2x me-3"></i>
                                            <div>
                                                <h5 class="alert-heading mb-1 text-danger font-weight-bold" style="font-weight: 700;">Atenção ao preencher o formulário:</h5>
                                                <ul class="mb-0 ps-3 text-dark">
                                                    @foreach($errors->all() as $error)
                                                        <li>{{ $error }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <script>
                                        document.addEventListener("DOMContentLoaded", function() {
                                            var el = document.getElementById("mensagem-erro");
                                            if (el) {
                                                el.scrollIntoView({ behavior: "smooth", block: "center" });
                                            }
                                        });
                                    </script>
                                @endif

                                <form action="{{ route('contact.submit') }}" method="POST">
                                    @csrf
                                    
                                    <!-- Honeypot anti-spam -->
                                    <div class="d-none">
                                        <label>Se você não é um robô, deixe em branco:</label>
                                        <input type="text" name="url_spam_check" tabindex="-1" autocomplete="off">
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="nome" class="form-label">Nome Completo *</label>
                                            <input type="text" class="form-control" id="nome" name="nome" value="{{ old('nome') }}" required placeholder="Seu nome">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="telefone" class="form-label">Telefone / WhatsApp *</label>
                                            <input type="tel" class="form-control" id="telefone" name="telefone" value="{{ old('telefone') }}" required maxlength="16" placeholder="(XX) 99999-9999">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">E-mail Profissional *</label>
                                        <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" required placeholder="exemplo@dominio.com">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="assunto" class="form-label">Assunto / Tipo de Serviço *</label>
                                        <select class="form-select" id="assunto" name="assunto">
                                            <option value="Assistência Técnica Judicial" {{ old('assunto') == 'Assistência Técnica Judicial' ? 'selected' : '' }}>Assistência Técnica Judicial</option>
                                            <option value="Análise de Viabilidade Técnica" {{ old('assunto') == 'Análise de Viabilidade Técnica' ? 'selected' : '' }}>Análise de Viabilidade Técnica (Estudo de Caso)</option>
                                            <option value="Parecer Técnico em Prontuário" {{ old('assunto') == 'Parecer Técnico em Prontuário' ? 'selected' : '' }}>Parecer Técnico / Auditoria de Prontuário Clínico</option>
                                            <option value="Necropsia Forense ou Exumação" {{ old('assunto') == 'Necropsia Forense ou Exumação' ? 'selected' : '' }}>Necropsia Forense ou Exumação</option>
                                            <option value="Discussão de Casos de Intoxicação" {{ old('assunto') == 'Discussão de Casos de Intoxicação' ? 'selected' : '' }}>Discussão de Casos de Intoxicação (Toxicologia Forense)</option>
                                            <option value="Não sei o que preciso / Orientação" {{ old('assunto') == 'Não sei o que preciso / Orientação' ? 'selected' : '' }}>Não sei o que preciso / Quero orientação preliminar</option>
                                        </select>
                                        <span class="form-text text-muted small mt-1 d-block">
                                            <i class="fas fa-question-circle me-1"></i> Não tem certeza sobre qual serviço escolher? 
                                            <a href="{{ route('blog.show', 'diferenca-pericia-assistencia-tecnica-laudo-veterinario') }}" target="_blank" class="fw-bold" style="color: var(--accent-color);">
                                                Leia nosso guia sobre Perícia, Assistência e Laudo.
                                            </a>
                                        </span>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="mensagem" class="form-label">Breve relato do caso *</label>
                                        <textarea class="form-control" id="mensagem" name="mensagem" rows="4" required placeholder="Descreva o ocorrido de forma resumida...">{{ old('mensagem') }}</textarea>
                                        <span class="form-text text-muted small mt-1 d-block">
                                            <i class="fas fa-lock me-1"></i> <strong>Aviso de Privacidade:</strong> Não envie senhas, CPFs ou dados pessoais sensíveis neste relato. Se você possui documentos (exames, fotos ou processos em PDF), poderá anexá-los com segurança no seu portal de acompanhamento criptografado imediatamente após o envio deste formulário.
                                        </span>
                                    </div>

                                    <!-- Math security challenge -->
                                    <div class="mb-4">
                                        <label for="desafio_humano" class="form-label">Verificação de Segurança: Quanto é {{ $challengeA }} + {{ $challengeB }}? *</label>
                                        <input type="text" class="form-control" id="desafio_humano" name="desafio_humano" required placeholder="Digite apenas o número">
                                        <span class="form-text text-muted small">Proteção básica contra robôs de spam.</span>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-gold py-3">
                                            <i class="fas fa-paper-plane me-2"></i>Enviar Solicitação
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

@push('seo_schemas')
<script type="application/ld+json">
{
  "@@context": "https://schema.org",
  "@@type": ["MedicalBusiness", "LegalService"],
  "name": "MedVet Legal - Medicina Veterinária Legal e Perícia Forense",
  "alternateName": ["MedVetLegal", "Medicina Veterinária Legal", "Perícia Veterinária Forense"],
  "url": "{{ url('/') }}",
  "logo": "{{ asset('assets/favicon.png') }}",
  "image": "{{ asset('assets/hero-bg.jpg') }}",
  "description": "Medicina Veterinária Legal, Assistência Técnica Judicial e Perícia Forense Especializada sob a coordenação técnica da Dra. Adriana de Siqueira e consultoria do Dr. Atilio S. Calefi.",
  "telephone": "+55-11-99337-3349",
  "email": "contato@medvetlegal.com.br",
  "priceRange": "$$",
  "medicalSpecialty": "https://schema.org/Veterinary",
  "address": {
    "@@type": "PostalAddress",
    "addressLocality": "São Paulo",
    "addressRegion": "SP",
    "addressCountry": "BR"
  },
  "geo": {
    "@@type": "GeoCoordinates",
    "latitude": "-23.550520",
    "longitude": "-46.633308"
  },
  "areaServed": {
    "@@type": "Country",
    "name": "Brasil"
  },
  "sameAs": [
    "https://www.youtube.com/@csvet",
    "https://www.instagram.com/atiliocalefi",
    "http://lattes.cnpq.br/8627498416487640",
    "http://lattes.cnpq.br/4438211760493798"
  ],
  "founder": [
    {
      "@@type": "Person",
      "name": "Dra. Adriana de Siqueira",
      "jobTitle": "Perita em Medicina Veterinária Legal e Patologista Forense",
      "alumniOf": "Universidade de São Paulo",
      "sameAs": "http://lattes.cnpq.br/8627498416487640"
    },
    {
      "@@type": "Person",
      "name": "Dr. Atilio Sersun Calefi",
      "jobTitle": "Farmacologista Veterinário e Consultor Científico",
      "alumniOf": "Universidade de São Paulo",
      "sameAs": "http://lattes.cnpq.br/4438211760493798"
    }
  ],
  "knowsAbout": [
    "Medicina Veterinária Legal",
    "Medicina Legal Veterinária",
    "Veterinária Legal",
    "Veterinary Legal Medicine",
    "Perícia Veterinária Forense",
    "Assistência Técnica Judicial Veterinária",
    "Patologia Forense Veterinária",
    "Toxicologia Veterinária Forense",
    "Toxicologia de Carbamatos e Chumbinho",
    "Necropsia Forense de Animais",
    "Exumação Técnica Animal",
    "Erro Médico Veterinário",
    "Maus-tratos a Animais"
  ]
}
</script>
@endpush

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var telefoneInput = document.getElementById('telefone');
    if (!telefoneInput) {
      return;
    }
    telefoneInput.addEventListener('input', function (e) {
      var digits = e.target.value.replace(/\D/g, '').slice(0, 11);
      var formatted = digits;
      if (digits.length > 10) {
        formatted = digits.replace(/^(\d{2})(\d{5})(\d{0,4}).*/, '($1) $2-$3');
      } else if (digits.length > 6) {
        formatted = digits.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3');
      } else if (digits.length > 2) {
        formatted = digits.replace(/^(\d{2})(\d{0,5})/, '($1) $2');
      } else if (digits.length > 0) {
        formatted = digits.replace(/^(\d{0,2})/, '($1');
      }
      e.target.value = formatted;
    });
  });
</script>
@endpush

@endsection