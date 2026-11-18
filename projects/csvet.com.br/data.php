<?php
// Configurações e dados dos sites do ecossistema CSVET

// Configurações globais para SEO e Open Graph
$seo_config = [
    'base_url' => 'https://csvet.com.br',
    'site_name' => 'CSVET Editora',
    'title_default' => 'CSVET Editora | Periódicos Científicos, Cursos e Plataformas em Medicina Veterinária',
    'description_default' => 'CSVET Editora: publicação científica, periódicos revisados por pares, cursos e plataformas de Medicina Veterinária, Educação, Perícia Veterinária e Gestão Genética do Prof. Dr. Atilio Sersun Calefi.',
    'author' => 'Prof. Dr. Atilio Sersun Calefi',
    'og_image' => 'https://csvet.com.br/img/og-image.jpg',
    'og_image_alt' => 'CSVET Editora: periódicos científicos, cursos e plataformas em Medicina Veterinária',
    'locale' => 'pt_BR',
    'theme_color' => '#000080'
];

// Identidade da organização (JSON-LD Organization): mesma marca de csvet.app e journals.csvet.com.br
$organization = [
    'name' => 'CSVET Editora',
    'legal_name' => 'CSVET Editora, Cursos e Treinamentos',
    'logo' => 'https://csvet.com.br/img/logo-csvet.png',
    'email' => 'calefi@csvet.com.br',
    'tax_id' => '32.645.724/0001-89',
    'founder' => 'Prof. Dr. Atilio Sersun Calefi',
    'founder_url' => 'https://profatilio.com.br',
    'same_as' => [
        'https://csvet.app',
        'https://journals.csvet.com.br',
        'https://www.youtube.com/@CSVET',
        'https://www.instagram.com/prof.atilio/'
    ]
];

// Dados regulatórios (LGPD, Marco Civil da Internet). Usados no rodapé, em /privacidade e em /termos.
// CNPJ e endereço só aparecem quando preenchidos.
$legal = [
    'controller' => 'CSVET Editora, Cursos e Treinamentos',
    'responsible' => '32.645.724 Atilio Sersun Calefi', // razão social do MEI na Receita Federal
    'cnpj' => '32.645.724/0001-89',
    'address' => 'Americana/SP',
    'dpo_name' => 'Prof. Dr. Atilio Sersun Calefi',
    'dpo_email' => 'calefi@csvet.com.br',
    'forum' => 'Americana/SP',
    'updated' => '07/10/2026'
];

// Periódicos publicados pela CSVET Editora (JSON-LD Periodical e bloco de destaque)
$journals = [
    'nvas' => [
        'title' => 'Neotropical Veterinary and Animal Science',
        'abbr' => 'NVAS',
        'url' => 'https://journals.csvet.com.br/nvas',
        'languages' => ['en', 'pt-BR'],
        'description' => 'Revista científica de acesso aberto, revisada por pares e de escopo amplo em medicina veterinária e zootecnia: artigos originais, relatos de caso, revisões, imagens clínicas, métodos e artigos de dados, com interesse especial pelos Neotrópicos.'
    ]
];

// Lista completa de sites com detalhes para exibição
$sites = [
    'journals' => [
        'title' => 'CSVET Journals',
        'url' => 'https://journals.csvet.com.br',
        'badge' => 'Periódicos Científicos',
        'color' => '#00aaff', // Azul de destaque da marca CSVET
        'description' => 'Portal de periódicos da CSVET Editora (Open Journal Systems). Publica a Neotropical Veterinary and Animal Science (NVAS), revista de escopo amplo em medicina veterinária e zootecnia, com revisão por pares, acesso aberto e submissão online.',
        'icon' => 'journal'
    ],
    'csvet-app' => [
        'title' => 'CSVET App',
        'url' => 'https://csvet.app',
        'badge' => 'Portal Central',
        'color' => '#10b981', // Esmeralda / Verde Vet
        'description' => 'O portal central de aplicativos educacionais interativos, simulados, mentorias e cursos especializados em Medicina Veterinária, Imunologia, Patologia e Microbiologia.',
        'icon' => 'app'
    ],
    'profatilio' => [
        'title' => 'Prof. Atilio',
        'url' => 'https://profatilio.com.br',
        'badge' => 'Acadêmico & Carreira',
        'color' => '#3b82f6', // Azul
        'description' => 'Espaço acadêmico e profissional do Prof. Dr. Atilio Sersun Calefi. Conteúdos científicos, guias de carreira na veterinária, normas ABNT e materiais didáticos.',
        'icon' => 'academic'
    ],
    'vetrank' => [
        'title' => 'VetRank',
        'url' => 'https://vetrank.com.br',
        'badge' => 'Educação Gamificada',
        'color' => '#f59e0b', // Âmbar / Ouro
        'description' => 'Plataforma preparatória inteligente com bancos de questões de alta qualidade, simulados para o ENADE, residências e concursos públicos de Medicina Veterinária.',
        'icon' => 'ranking'
    ],
    'medvetlegal' => [
        'title' => 'MedVet Legal',
        'url' => 'https://medvetlegal.com.br',
        'badge' => 'Perícia Veterinária',
        'color' => '#ef4444', // Vermelho
        'description' => 'Consultoria e assessoria em perícia e assistência técnica veterinária forense. Suporte ético e técnico para advogados, clínicas, tutores de animais e o poder judiciário.',
        'icon' => 'legal'
    ],
    'plumoragen' => [
        'title' => 'Plumora Gen',
        'url' => 'https://plumoragen.com',
        'badge' => 'Gestão de Aves',
        'color' => '#8b5cf6', // Roxo / Violeta
        'description' => 'Plataforma avançada de gestão para criadores de aves (exóticas, domésticas e pombos-correio), com foco em controle genético, árvore genealógica e pedigree.',
        'icon' => 'genetic'
    ],
    'profaid' => [
        'title' => 'Prof-Aid',
        'url' => 'https://prof-aid.com',
        'badge' => 'Gestão de Provas',
        'color' => '#ec4899', // Rosa
        'description' => 'Plataforma para instituições de ensino e docentes que automatiza a criação de avaliações em PDF com múltiplas versões e realiza correção instantânea via câmera.',
        'icon' => 'exam'
    ]
];

// Mapeamento de chaves alternativas para redirecionamento rápido
$redirect_map = [
    'app'          => 'csvet-app',
    'csvet'        => 'csvet-app',
    'csvetapp'     => 'csvet-app',
    'profatilio'   => 'profatilio',
    'atilio'       => 'profatilio',
    'vetrank'      => 'vetrank',
    'medvetlegal'  => 'medvetlegal',
    'legal'        => 'medvetlegal',
    'plumoragen'   => 'plumoragen',
    'genetica'     => 'plumoragen',
    'plumora'      => 'plumoragen',
    'profaid'      => 'profaid',
    'aid'          => 'profaid',
    'journals'     => 'journals',
    'journal'      => 'journals',
    'revista'      => 'journals',
    'bjvr'         => 'journals',
    'nvas'         => 'journals'
];
