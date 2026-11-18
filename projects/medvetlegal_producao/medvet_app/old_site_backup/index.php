<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Assistência Técnica e Perícia Veterinária Forense. Especializados em Patologia, Toxicologia (Carbamatos/Aldicarbe), Necropsia e Análise de Processos.">
    <meta name="keywords" content="Perícia Veterinária, Medicina Legal, Necropsia Forense, Toxicologia Veterinária, Erro Médico Veterinário, Adriana de Siqueira, Atilio Calefi, Laudo Pericial">
    <meta name="author" content="MedVetLegal">
    
    <title>MedVetLegal | Perícia e Assistência Técnica Veterinária</title>
    <link rel="canonical" href="https://www.medvetlegal.com.br/">

    <link rel="icon" type="image/png" href="assets/favicon.png">
    <link rel="apple-touch-icon" href="assets/favicon.png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #0e2a47; /* Azul Marinho Profundo */
            --accent-color: #c7a006;  /* Dourado Nobre */
            --text-dark: #333;
        }
        
        body { font-family: 'Lato', sans-serif; color: var(--text-dark); }
        h1, h2, h3, h4, .navbar-brand { font-family: 'Playfair Display', serif; }
        
        /* Navbar */
        .navbar { background-color: var(--primary-color); padding: 1rem 0; }
        .navbar-brand { font-size: 1.5rem; letter-spacing: 1px; color: white !important; }
        .nav-link { color: rgba(255,255,255,0.8) !important; margin-left: 15px; transition: 0.3s; }
        .nav-link:hover { color: var(--accent-color) !important; }
        
        /* Hero Section */
        .hero-section {
            /* Adicione uma imagem chamada 'hero-bg.jpg' na pasta assets */
            background: linear-gradient(rgba(14, 42, 71, 0.92), rgba(14, 42, 71, 0.85)), url('assets/hero-bg.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 120px 0;
            position: relative;
        }
        .hero-title { font-size: 3.5rem; margin-bottom: 20px; }
        .btn-gold {
            background-color: var(--accent-color);
            color: #000;
            font-weight: 700;
            padding: 15px 40px;
            border-radius: 2px;
            border: none;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: 0.3s;
        }
        .btn-gold:hover { background-color: #e0b408; transform: translateY(-2px); }

        /* Seção Sobre */
        .section-title {
            color: var(--primary-color);
            position: relative;
            display: inline-block;
            margin-bottom: 50px;
        }
        .section-title::after {
            content: '';
            display: block;
            width: 60px;
            height: 3px;
            background: var(--accent-color);
            margin: 15px auto 0;
        }
        
        .profile-card {
            border: none;
            background: #fff;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            transition: transform 0.3s;
        }
        .profile-card:hover { transform: translateY(-5px); }
        .profile-img {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--accent-color);
            margin-top: -90px;
            background-color: #eee;
        }
        .profile-header {
            background-color: #f8f9fa;
            height: 120px;
            border-radius: 5px 5px 0 0;
        }

        /* Serviços */
        .service-icon {
            font-size: 2.5rem;
            color: var(--accent-color);
            margin-bottom: 20px;
        }
        .service-item { padding: 30px; border: 1px solid #eee; height: 100%; transition: 0.3s; }
        .service-item:hover { border-color: var(--accent-color); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }

        /* Contato e Footer */
        footer { background-color: var(--primary-color); color: white; padding: 40px 0; }
        .footer-link { color: #ccc; text-decoration: none; }
        .footer-link:hover { color: var(--accent-color); }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand" href="#">MEDVET LEGAL</a>
            <button class="navbar-toggler navbar-dark" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="#home">Início</a></li>
                    <li class="nav-item"><a class="nav-link" href="#especialistas">Corpo técnico</a></li>
                    <li class="nav-item"><a class="nav-link" href="#servicos">Atuação</a></li>
                    <li class="nav-item"><a class="nav-link" href="blog.php">Blog</a></li>
                    <li class="nav-item"><a class="nav-link btn btn-outline-light ms-3 px-3" href="#contato">Fale Conosco</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <header id="home" class="hero-section text-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <h1 class="hero-title">Rigor Científico na Medicina Veterinária Forense</h1>
                    <p class="lead mb-5 fs-4">Assistência técnica judicial, perícia e consultoria especializada em Patologia e Toxicologia.</p>
                    <a href="#contato" class="btn btn-gold">Solicitar Análise de Caso</a>
                </div>
            </div>
        </div>
    </header>

    <section id="especialistas" class="py-5 bg-light">
        <div class="container pt-5">
            <div class="text-center mb-5">
                <h2 class="section-title">Corpo Técnico Especializado</h2>
                <p class="text-muted w-75 mx-auto">Unimos a Medicina Veterinária e a Biologia para fornecer pareceres técnicos e laudos periciais.</p>
            </div>

            <div class="row g-4 justify-content-center pt-5">
                
                <div class="col-lg-6 mb-4">
                    <div class="card profile-card h-100">
                        <div class="profile-header"></div>
                        <div class="card-body text-center px-4 pb-5">
                            <img src="assets/adriana.jpg" alt="Dra. Adriana de Siqueira" class="profile-img shadow">
                            <h3 class="mt-4 mb-1">Profa. Dra. Adriana de Siqueira</h3>
                            <p class="text-primary fw-bold mb-3">Patologista Forense & Perita Judicial</p>
                            
                            <div class="text-start mt-4">
                                <p class="small mb-2"><i class="fas fa-check text-warning me-2"></i><strong>Doutora e Mestre em Patologia (USP):</strong> Tese de referência em toxicologia forense (carbamatos).</p>
                                <p class="small mb-2"><i class="fas fa-check text-warning me-2"></i><strong>Multidisciplinar:</strong> Graduada em Medicina Veterinária (UFPR) e Ciências Econômicas (USP).</p>
                                <p class="small mb-2"><i class="fas fa-check text-warning me-2"></i><strong>Premiada:</strong> <em>IVFSA Student Award</em> (International Veterinary Forensic Sciences Association).</p>
                            </div>
                            
                            <p class="text-muted mt-3 small text-start border-top pt-3">
                                Atua na elucidação de <em>causa mortis</em> e casos de crueldade animal.
                            </p>
                            
                            <a href="http://lattes.cnpq.br/8627498416487640" target="_blank" class="btn btn-sm btn-outline-dark mt-2">Ver Lattes</a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 mb-4">
                    <div class="card profile-card h-100">
                        <div class="profile-header"></div>
                        <div class="card-body text-center px-4 pb-5">
                            <img src="assets/atilio.jpg" alt="Dr. Atilio Sersun Calefi" class="profile-img shadow">
                            <h3 class="mt-4 mb-1">Prof. Dr. Atilio Sersun Calefi</h3>
                            <p class="text-primary fw-bold mb-3">Consultor em Farmacologia & Toxicologia</p>
                            
                            <div class="text-start mt-4">
                                <p class="small mb-2"><i class="fas fa-check text-warning me-2"></i><strong>Pós-Doutor em Farmacologia (USP):</strong> Foco em neuroimunomodulação e toxicologia.</p>
                                <p class="small mb-2"><i class="fas fa-check text-warning me-2"></i><strong>Dupla Formação:</strong> Graduado em Medicina Veterinária (UEL) e Ciências Biológicas (UNICSUL).</p>
                                <p class="small mb-2"><i class="fas fa-check text-warning me-2"></i><strong>Experiência Empresarial:</strong> Preceptor em Patologia Animal</p>
                            </div>
                            
                            <p class="text-muted mt-3 small text-start border-top pt-3">
                                Especializado em análises laboratoriais complexas, patologia experimental e consultoria para a indústria farmacêutica.
                            </p>
                            
                            <a href="http://lattes.cnpq.br/4438211760493798" class="btn btn-sm btn-outline-dark mt-2">Ver Lattes</a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <section id="servicos" class="py-5">
        <div class="container py-4">
            <div class="text-center mb-5">
                <h2 class="section-title">Áreas de Atuação</h2>
                <p class="text-muted">Atendimento a médicos veterinários, responsáveis pelos animais, seguradoras, escritórios de advocacia e produtores rurais.</p>
            </div>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="service-item text-center">
                        <i class="fas fa-skull-crossbones service-icon"></i>
                        <h4>Toxicologia Forense</h4>
                        <p class="small text-muted">Especializados em casos de intoxicação por praguicidas (ex: "chumbinho"/aldicarbe), erros de medicação e reações adversas. Análise crítica de exames toxicológicos.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="service-item text-center">
                        <i class="fas fa-search-plus service-icon"></i>
                        <h4>Necropsia e Exumação</h4>
                        <p class="small text-muted">Realização e acompanhamento de necropsias com rigor de cadeia de custódia. Pareceres técnicos sobre laudos oficiais e exumações para coleta de provas.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="service-item text-center">
                        <i class="fas fa-gavel service-icon"></i>
                        <h4>Assistência Técnica Judicial</h4>
                        <p class="small text-muted">Formulação de quesitos estratégicos, contestação de laudos periciais e acompanhamento presencial de diligências em processos cíveis e criminais.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="service-item text-center">
                        <i class="fas fa-file-medical-alt service-icon"></i>
                        <h4>Falha na Prestação de Serviços Médico-Veterinários</h4>
                        <p class="small text-muted">
                            Análise técnica e pericial em processos de responsabilidade civil. Auditoria de prontuários para verificação de nexo causal, conduta (negligência, imprudência, imperícia) e dever de informação.
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="service-item text-center">
                        <i class="fas fa-paw service-icon"></i>
                        <h4>Maus-tratos e Bem-estar</h4>
                        <p class="small text-muted">Caracterização técnica de situações de maus-tratos, negligência e acumuladores de animais (Síndrome de Noé), com base em patologia veterinária.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="service-item text-center">
                        <i class="fas fa-chalkboard-teacher service-icon"></i>
                        <h4>Consultoria e Cursos</h4>
                        <p class="small text-muted">Palestras e treinamentos *in company* sobre responsabilidade civil veterinária, legislação e procedimentos forenses para clínicas e hospitais.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="contato" class="py-5" style="background-color: #f1f3f5;">
        <div class="container py-4">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card shadow border-0">
                        <div class="card-body p-5">
                            <h2 class="text-center mb-4 font-playfair">Fale com o corpo técnico</h2>
                            <p class="text-center text-muted mb-5">Preencha o formulário para uma análise preliminar. Garantimos total sigilo.</p>
                            
                            <form action="enviar_email.php" method="POST">
    
                                <div style="display: none; visibility: hidden;">
                                    <label>Se você não é um robô, deixe este campo em branco:</label>
                                    <input type="text" name="url_spam_check" tabindex="-1" autocomplete="off">
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold small">Nome Completo</label>
                                        <input type="text" class="form-control" name="nome" required placeholder="Seu nome">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold small">Telefone / WhatsApp</label>
                                        <input type="tel" class="form-control" name="telefone" required placeholder="(XX) 99999-9999">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold small">E-mail Profissional</label>
                                    <input type="email" class="form-control" name="email" required placeholder="seu@email.com">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold small">Assunto de Interesse</label>
                                    <select class="form-select" name="assunto">
                                        <option value="Assistência Técnica">Assistência Técnica Judicial</option>
                                        <option value="Necropsia/Exumação">Solicitar Necropsia ou Exumação</option>
                                        <option value="Toxicologia">Discussão sobre casos de intoxicação</option>
                                        <option value="Consultoria">Consultoria / Auditoria de Prontuário</option>
                                        <option value="Outros">Outros Assuntos</option>
                                    </select>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label fw-bold small">Breve Relato do Caso</label>
                                    <textarea class="form-control" name="mensagem" rows="5" required placeholder="Descreva sucintamente o ocorrido..."></textarea>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-bold small" style="color: var(--primary-color);">Verificação de Segurança</label>
                                    <input type="tel" class="form-control" name="desafio_humano" required placeholder="Quanto é 3 + 4? (Digite apenas o número)">
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-gold btn-lg">ENVIAR SOLICITAÇÃO</button>
                                </div>
                            </form>
                            
                            <div class="text-center mt-4">
                                <p class="small text-muted mb-0">Ou envie um e-mail direto para:</p>
                                <a href="mailto:patoforense.adriana@gmail.com" class="fw-bold text-dark text-decoration-none">patoforense.adriana@gmail.com</a>
                                <span class="mx-2">|</span>
                                <a href="mailto:calefi@csvet.com.br" class="fw-bold text-dark text-decoration-none">calefi@csvet.com.br</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="row gy-4">
                <div class="col-lg-4 col-md-6">
                    <h5 class="mb-3 text-white font-playfair">MEDVET LEGAL</h5>
                    <p class="small text-white-50">Excelência técnica e ética na Medicina Veterinária Legal. Atuamos em todo o território nacional com suporte a médicos veterinários, responsáveis pelos animais, seguradoras, escritórios de advocacia e produtores rurais.</p>
                </div>
                <div class="col-lg-4 col-md-6">
                    <h5 class="mb-3 text-white font-playfair">Links Rápidos</h5>
                    <ul class="list-unstyled">
                        <li><a href="#home" class="footer-link small">Início</a></li>
                        <li><a href="#especialistas" class="footer-link small">Corpo Técnico</a></li>
                        <li><a href="#servicos" class="footer-link small">Nossos Serviços</a></li>
                        <li><a href="/blog" class="footer-link small">Artigos e Notícias</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 col-md-12">
                    <h5 class="mb-3 text-white font-playfair">Contato</h5>
                    <p class="small text-white-50 mb-1"><i class="fas fa-envelope me-2"></i> calefi@csvet.com.br</p>
                    <p class="small text-white-50 mb-1"><i class="fas fa-envelope me-2"></i> patoforense.adriana@gmail.com</p>
                    <p class="small text-white-50"><i class="fas fa-map-marker-alt me-2"></i> São Paulo - SP, Brasil</p>
                </div>
            </div>
            <hr class="border-secondary mt-4">
            <div class="text-center small text-white-50">
                &copy; <?php echo date("Y"); ?> MedVetLegal. Todos os direitos reservados.
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>