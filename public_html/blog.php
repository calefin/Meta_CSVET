<?php
// Arquivo: blog.php
// Integração futura com o banco de dados da HostGator
// require_once 'includes/db.php'; 

// Simulação de dados (Enquanto o DB está offline)
// Isso facilita a manutenção e a futura migração para JSON/API para o App Flutter
$artigos = [
    [
        "id" => 1,
        "categoria" => "Toxicologia Forense",
        "data" => "02 Jan 2026",
        "titulo" => "Como provar intoxicação por 'Chumbinho' (Carbamatos)?",
        "resumo" => "É possível detectar veneno após o enterro do animal? Saiba como a exumação técnica fundamenta processos judiciais.",
        "imagem" => "assets/hero-bg.jpg",
        "link" => "artigo-01.php"
    ],
    // Adicione novos artigos aqui facilmente
];

// Configurações de SEO baseadas na sua sólida carreira acadêmica
$page_title = "Blog MedVetLegal | Divulgação Científica e Medicina Veterinária";
$meta_desc = "Artigos técnicos e jurisprudência veterinária com o Prof. Dr. Atilio Sersun Calefi. Conteúdo especializado para responsáveis legais e profissionais.";[cite: 1]
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <meta name="description" content="<?php echo $meta_desc; ?>">
    <link rel="canonical" href="https://www.medvetlegal.com.br/blog.php">
    
    <!-- Bootstrap e Fontes -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    
    <link rel="icon" type="image/png" href="assets/favicon.png">[cite: 1]

    <style>
        :root { --primary: #0e2a47; --accent: #c7a006; --text-muted: #6c757d; }
        body { font-family: 'Lato', sans-serif; background-color: #f4f7f9; color: var(--primary); }
        h1, h2, h3, .navbar-brand { font-family: 'Playfair Display', serif; }
        
        .navbar { background-color: var(--primary); box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .nav-link:hover { color: var(--accent) !important; }
        
        .blog-header {
            background: linear-gradient(rgba(14, 42, 71, 0.9), rgba(14, 42, 71, 0.9)), url('assets/hero-bg.jpg');[cite: 1]
            background-size: cover;
            background-position: center;
            color: white;
            padding: 100px 0 60px;
            text-align: center;
        }

        .category-badge {
            background-color: var(--accent);
            color: var(--primary);
            font-size: 0.7rem;
            text-transform: uppercase;
            font-weight: 700;
            padding: 5px 12px;
            border-radius: 20px;
            margin-bottom: 10px;
            display: inline-block;
        }
        
        .card-blog {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            background: white;
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
        }
        .card-blog:hover { transform: translateY(-10px); box-shadow: 0 15px 30px rgba(0,0,0,0.1); }
        .card-img-top { height: 220px; object-fit: cover; }
        
        .card-title { font-size: 1.25rem; line-height: 1.4; margin-bottom: 15px; }
        .read-more { color: var(--accent); font-weight: 700; text-decoration: none; border-bottom: 2px solid transparent; transition: 0.3s; }
        .read-more:hover { border-bottom: 2px solid var(--accent); }
        
        .sidebar-box { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        
        footer { background-color: var(--primary); color: white; padding: 40px 0; }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand text-white" href="index.php">MEDVET LEGAL</a>
            <button class="navbar-toggler navbar-dark" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav align-items-center">
                    <li class="nav-item"><a class="nav-link text-white-50" href="index.php">Site Principal</a></li>
                    <li class="nav-item"><a class="nav-link text-white active" href="blog.php">Artigos</a></li>
                    <li class="nav-item"><a class="nav-link btn btn-accent btn-sm ms-lg-3 text-primary bg-warning" href="index.php#contato" style="font-weight:bold;">Consultoria</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <header class="blog-header">
        <div class="container">
            <h1 class="display-4">Divulgação Científica</h1>
            <p class="lead opacity-75">Ciência veterinária complexa traduzida de forma acessível.</p>[cite: 1]
        </div>
    </header>

    <div class="container py-5">
        <div class="row">
            <!-- Feed de Artigos -->
            <div class="col-lg-8">
                <div class="row g-4">
                    <?php foreach ($artigos as $artigo): ?>
                    <div class="col-md-6">
                        <article class="card card-blog h-100">
                            <img src="<?php echo $artigo['imagem']; ?>" class="card-img-top" alt="<?php echo $artigo['titulo']; ?>">
                            <div class="card-body d-flex flex-column">
                                <span class="category-badge"><?php echo $artigo['categoria']; ?></span>
                                <small class="text-muted mb-2"><i class="far fa-calendar-alt me-1"></i> <?php echo $artigo['data']; ?></small>
                                <h4 class="card-title text-primary"><?php echo $artigo['titulo']; ?></h4>
                                <p class="card-text text-muted small"><?php echo $artigo['resumo']; ?></p>
                                <div class="mt-auto">
                                    <a href="<?php echo $artigo['link']; ?>" class="read-more">Ler Artigo Completo <i class="fas fa-arrow-right ms-1"></i></a>
                                </div>
                            </div>
                        </article>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Sidebar Lateral -->
            <div class="col-lg-4 mt-5 mt-lg-0">
                <div class="sidebar-box mb-4 text-center">
                    <img src="assets/atilio.jpg" alt="Prof. Dr. Atilio" class="rounded-circle mb-3 border border-3 border-warning" style="width: 120px; height: 120px; object-fit: cover;">[cite: 1]
                    <h5>Prof. Dr. Atilio Sersun Calefi</h5>[cite: 1]
                    <p class="small text-muted">Médico Veterinário e Biólogo. Especialista em Patologia Experimental pela FMVZ-USP.</p>[cite: 1]
                    <div class="d-flex justify-content-center gap-3">
                        <a href="#" class="text-primary"><i class="fab fa-youtube fa-lg"></i></a>[cite: 1]
                        <a href="#" class="text-primary"><i class="fab fa-instagram fa-lg"></i></a>[cite: 1]
                        <a href="#" class="text-primary"><i class="fab fa-tiktok fa-lg"></i></a>[cite: 1]
                    </div>
                </div>

                <div class="sidebar-box shadow-sm bg-primary text-white">
                    <h5>Cursos e Certificações</h5>
                    <p class="small opacity-75">Em breve, plataforma exclusiva para alunos CSVET com emissão automática de certificados.</p>[cite: 1]
                    <button class="btn btn-warning w-100 fw-bold">Lista de Espera</button>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <div class="container text-center">
            <p class="mb-2"><strong>CSVET Editora, Cursos e Treinamentos</strong></p>[cite: 1]
            <p class="small text-white-50 mb-0">&copy; <?php echo date("Y"); ?> MedVetLegal. Desenvolvido com foco em ciência e ética.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>