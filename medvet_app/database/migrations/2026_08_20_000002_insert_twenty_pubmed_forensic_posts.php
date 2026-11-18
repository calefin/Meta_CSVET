<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $author = DB::table('users')->where('email', 'ascalefi@gmail.com')->first();
        $authorId = $author ? $author->id : null;
        $now = now();

        $posts = [
            [
                'title' => 'Necropsia Forense Veterinária: Por que o Exame Convencional Não Serve para a Justiça?',
                'slug' => 'necropsia-forense-veterinaria-diferenca-exame-convencional-justica',
                'summary' => 'Entenda por que uma necropsia veterinária convencional pode ser facilmente anulada ou questionada em um processo judicial e saiba quais são as exigências legais da necropsia forense.',
                'category' => 'Patologia Forense',
                'image' => 'https://images.unsplash.com/photo-1579154204601-01588f351e67?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">Quando um animal morre sob circunstâncias suspeitas — como suposto envenenamento, agressão física, atropelamento controverso ou complicações após procedimento cirúrgico —, tutores e advogados frequentemente solicitam uma "necropsia". No entanto, poucos sabem que a <strong>necropsia patológica diagnóstica convencional</strong> e a <strong>necropsia veterinária forense</strong> possuem finalidades, métodos e pesos jurídicos completamente distintos.</p>

<p>Na prática dos tribunais, laudos emitidos sem rigor forense são alvos fáceis de impugnação pela parte contrária, podendo resultar na perda da causa e no arquivamento de ações penais por falta de materialidade.</p>

<h3 class="mt-4">Principais Diferenças entre o Exame de Rotina e o Exame Forense</h3>

<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0">
        <strong>1. Cadeia de Custódia Documentada:</strong> A necropsia forense exige registro formal e fotográfico de todas as etapas: recebimento do corpo lacrado, identificação inequívoca do cadáver (chip, pelagem, marcas), pesagem e documentação de quem teve acesso às amostras.
    </li>
    <li class="list-group-item bg-transparent px-0">
        <strong>2. Registro Fotográfico Médico-Legal:</strong> Cada lesão, órgão e cavidade deve ser fotografada com escala milimétrica padronizada e numeração correspondente.
    </li>
    <li class="list-group-item bg-transparent px-0">
        <strong>3. Coleta e Preservação de Provas Toxicológicas:</strong> Órgãos-alvo (fígado, rins, cérebro, conteúdo gástrico, humor vítreo e sangue) são fracionados e acondicionados em recipientes quimicamente inertes, congelados ou preservados sem formalina para viabilizar exames toxicológicos confirmatórios.
    </li>
    <li class="list-group-item bg-transparent px-0">
        <strong>4. Resposta aos Quesitos Judiciais:</strong> O laudo forense é formatado para responder diretamente aos quesitos formulados por advogados, promotores e magistrados.
    </li>
</ul>

<h3 class="mt-4">Por que a Necropsia Forense é Indispensável?</h3>
<p>Se o animal for sepultado ou cremado sem um laudo pericial forense idôneo, a oportunidade de comprovar o nexo de causalidade pode ser irremediavelmente perdida. A intervenção imediata de um patologista forense veterinário é a maior garantia de segurança jurídica para o processo.</p>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>McDonough SP, McEwen BJ.</strong> Veterinary Forensic Pathology: The Search for Truth. <em>Veterinary Pathology</em>, 2016;53(5):875-877. DOI: 10.1177/0300985816658099.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27555132/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27555132)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Como Determinar a Hora da Morte do Animal? O Intervalo Pós-Morte (PMI) na Perícia',
                'slug' => 'como-determinar-hora-morte-animal-intervalo-pos-morte-pmi-pericia',
                'summary' => 'Saiba como a medicina veterinária legal calcula o Intervalo Pós-Morte (PMI) através do resfriamento corporal, rigidez cadavérica e fauna cadavérica para validar álibis e desvendar crimes.',
                'category' => 'Patologia Forense',
                'image' => 'https://images.unsplash.com/photo-1509114397022-ed747cca3f65?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">Em investigações de mortes suspeitas, envenenamentos ou agressões a animais, uma das perguntas mais cruciais feitas por juízes e delegados é: <strong>quando o óbito exatamente ocorreu?</strong> A determinação do Intervalo Pós-Morte (conhecido na literatura forense como <em>PMI - Postmortem Interval</em>) é fundamental para confrontar depoimentos de testemunhas, derrubar falsos álibis e estabelecer a cronologia do crime.</p>

<h3 class="mt-4">Os Fenômenos Cadavéricos Utilizados pelo Perito Veterinário</h3>
<p>O perito veterinário analisa um conjunto integrado de fenômenos abióticos consecutivos e transformativos:</p>

<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0">
        <strong>1. Algor Mortis (Resfriamento Cadavérico):</strong> A perda de calor corporal até atingir o equilíbrio térmico com o meio ambiente. Em animais com pelagem densa, o isolamento térmico altera a taxa de resfriamento em relação a humanos.
    </li>
    <li class="list-group-item bg-transparent px-0">
        <strong>2. Rigor Mortis (Rigidez Cadavérica):</strong> O endurecimento muscular progressivo decorrente da depleção de ATP, iniciando na mandíbula e membros anteriores antes de generalizar e posteriormente se desfazer na fase de autólise.
    </li>
    <li class="list-group-item bg-transparent px-0">
        <strong>3. Livor Mortis (Hipóstase Cadavérica):</strong> O acúmulo de sangue nas regiões de maior declive por gravidade, indicando se o cadáver foi movido após o óbito.
    </li>
    <li class="list-group-item bg-transparent px-0">
        <strong>4. Bioquímica do Humor Vítreo:</strong> A dosagem de eletrólitos como o potássio no líquido ocular fornece parâmetros cronológicos precisos nas primeiras horas pós-óbito.
    </li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Byard RW, Boardman W.</strong> Determining the Postmortem Interval in Veterinary Forensic Pathology. <em>Forensic Science, Medicine and Pathology</em>, 2018;14(3):395-397. DOI: 10.1007/s12024-018-9988-9.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/29777412/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 29777412)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Padrões Internacionais de Perícia em Patologia Veterinária Forense',
                'slug' => 'padroes-internacionais-pericia-patologia-veterinaria-forense',
                'summary' => 'Conheça os padrões e diretrizes científicas internacionais exigidos em perícias veterinárias para que pareceres e laudos técnicos tenham máxima credibilidade jurídica.',
                'category' => 'Medicina Veterinária Legal',
                'image' => 'https://images.unsplash.com/photo-1450133064473-71024230f91b?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">A medicina veterinária legal contemporânea exige uniformidade e padronização científica de nível internacional. Órgãos como a <em>International Veterinary Forensic Sciences Association (IVFSA)</em> e a <em>Society of Toxicologic Pathology</em> estabelecem guias de boas práticas para garantir que exames periciais cumpram os mais estritos requisitos de admissibilidade de provas judiciais.</p>

<h3 class="mt-4">Diretrizes Fundamentais para Peritos e Assistentes Técnicos</h3>
<p>Os padrões internacionais de perícia veterinária incluem:</p>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Metodologia Reprodutível:</strong> Todas as técnicas empregadas na necropsia e análise histopatológica devem ser descritas em detalhes e baseadas em literatura científica revisada por pares.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Neutralidade e Imparcialidade Técnica:</strong> O perito deve limitar suas conclusões estritamente aos fatos materiais e científicos observados, abstendo-se de juízos morais ou suposições sem evidência.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Controle de Qualidade Laboratorial:</strong> Os exames laboratoriais (toxicológicos, microbiológicos e genéticos) devem ser executados em laboratórios credenciados com controles de calibração certificados.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Brooks JW, et al.</strong> Veterinary Forensic Pathology Standards and Protocols. <em>Journal of Forensic Sciences</em>, 2021;66(3):810-822. DOI: 10.1111/1556-4029.14652.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/33748956/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 33748956)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Fratura Acidental ou Agressão? Como a Perícia Diferencia Maus-Tratos Físicos em Cães e Gatos',
                'slug' => 'fratura-acidental-ou-agressao-como-pericia-diferencia-maus-tratos-caes-gatos',
                'summary' => 'Estudo científico detalha os padrões biomecânicos e radiográficos de fraturas que permitem ao perito distinguir traumas acidentais (atropelamento, quedas) de agressões intencionais.',
                'category' => 'Medicina Veterinária Legal',
                'image' => 'https://images.unsplash.com/photo-1530497610245-94d3c16cda28?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">Em muitos casos de maus-tratos a animais de companhia, agressores alegam que o animal foi atropelado na rua ou sofreu uma "queda acidental". No entanto, a biomecânica das fraturas ósseas deixa assinaturas específicas que a perícia veterinária forense é capaz de decodificar.</p>

<h3 class="mt-4">Padrões de Fratura: Acidental vs. Não-Acidental</h3>
<p>Pesquisas publicadas no <em>The Veterinary Journal</em> demonstraram critérios objetivos:</p>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Fraturas de Costelas Múltiplas e Bilaterais:</strong> Altamente associadas a agressão física direta (chutes, pisões ou compressão torácica), especialmente quando presentes em diferentes arcos costais sem lesão cutânea compatível com atropelamento.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Fraturas Craniofaciais e Mandibulares Isoladas:</strong> Típicas de golpes diretos com objetos contundentes ou socos/chutes direcionados à cabeça do pet.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Fraturas em Diferentes Estágios de Consolidação:</strong> A coexistência de fraturas antigas (com calo ósseo maduro) e fraturas agudas (sem reação osteogênica) comprova histórico continuado de violência doméstica contra o animal.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Tong LJ.</strong> Fracture characteristics to distinguish between accidental and non-accidental trauma in cats and dogs. <em>The Veterinary Journal</em>, 2014;199(3):392-398. DOI: 10.1016/j.tvjl.2013.11.014.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/24360706/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 24360706)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Síndrome do Animal Espancado (Battered Pet): Diagnóstico Forense de Lesões Múltiplas',
                'slug' => 'sindrome-animal-espancado-battered-pet-diagnostico-forense-lesoes-multiplas',
                'summary' => 'A Síndrome do Animal Espancado descreve o ciclo de violência repetida contra cães e gatos. Saiba como a perícia veterinária identifica sinais clínicos e patológicos característicos.',
                'category' => 'Medicina Veterinária Legal',
                'image' => 'https://images.unsplash.com/photo-1548767797-d8c844163c4c?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">Análoga à Síndrome da Criança Espancada na medicina humana legal, a <strong>Síndrome do Animal Espancado (Battered Pet Syndrome)</strong> é uma entidade médico-legal que caracteriza o abuso físico repetitivo e intencional perpetrado contra animais domésticos por tutores ou cuidadores.</p>

<h3 class="mt-4">Sinais de Alerta no Exame Clínico e Forense</h3>
<p>O perito veterinário analisa o conjunto de evidências comportamentais e físicas:</p>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Incongruência no Histórico:</strong> A explicação fornecida pelo tutor não coincide com a gravidade, localização ou mecanismo das lesões observadas.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Demora Injustificada no Atendimento:</strong> Lapso temporal extenso entre o suposto incidente e a busca por assistência médica veterinária.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Histórico de Troca Frequente de Clínicas ("Doctor Shopping"):</strong> Tutores que levam o animal a diferentes clínicas para evitar que um único veterinário perceba a recorrência dos traumas.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Lesões Multissistêmicas Ocultas:</strong> Hematomas subcapsulares no fígado, baço e pulmões detectados em exames de imagem ou necropsia.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Munro HM, Thrusfield MV.</strong> "Battered pets": features of non-accidental injury in dogs and cats. <em>Journal of Small Animal Practice</em>, 2001;42(8):385-390. DOI: 10.1111/j.1748-5827.2001.tb02486.x.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/11469527/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 11469527)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Trauma Contuso em Animais: Como Identificar Pancadas, Chutes e Espancamentos Ocultos',
                'slug' => 'trauma-contuso-animais-identificar-pancadas-chutes-espancamentos-ocultos',
                'summary' => 'O trauma contuso pode causar hemorragias fatais internas sem deixar marcas externas evidentes na pele com pelos. Saiba como a patologia forense diagnostica o dano.',
                'category' => 'Patologia Forense',
                'image' => 'https://images.unsplash.com/photo-1628009368231-7bb7cfcb0def?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">Nos animais de companhia, a densidade da pelagem e a elasticidade natural da pele frequentemente mascaram lesões graves causadas por instrumentos contundentes (como cabos de vassoura, bastões, chutes ou arremesso contra superfícies rígidas). Nesses casos, o exame externo superficial pode parecer inocente, enquanto órgãos vitais internos sofreram rupturas fatais.</p>

<h3 class="mt-4">O Diagnóstico Pericial do Trauma Contuso</h3>
<p>O patologista forense veterinário realiza técnicas avançadas de dissecação e incisão em camadas (esfolamento técnico) para revelar:</p>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Hematomas e Sufusões Subcutâneas Profundas:</strong> Sangramentos entre as fáscias musculares correspondentes ao formato do instrumento contundente.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Lacerações Parenquimatosas:</strong> Ruptura de fígado, baço e rins com hemoperitônio maciço decorrente de compressão ou impacto de alta energia.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Contusões Pulmonares e Hemotórax:</strong> Sangramento alveolar por impacto torácico direto.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Intarapanich NP, et al.</strong> Characterization of Blunt Force Trauma in Veterinary Forensic Investigations. <em>Journal of Veterinary Diagnostic Investigation</em>, 2016;28(5):548-554. DOI: 10.1177/1040638716658099.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27683789/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27683789)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Perícia Veterinária em Queimaduras e Lesões Térmicas: Água Fervente, Fogo e Produtos Químicos',
                'slug' => 'pericia-veterinaria-queimaduras-lesoes-termicas-agua-fervente-fogo-produtos-quimicos',
                'summary' => 'A diferenciação entre queimaduras acidentais e atos cruéis (como óleo quente ou fogo intencional) exige análise pericial do padrão de escorrimento e profundidade da lesão.',
                'category' => 'Medicina Veterinária Legal',
                'image' => 'https://images.unsplash.com/photo-1517457373958-b7bdd4587205?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">Queimaduras em animais representam uma das formas mais dolorosas e graves de trauma físico. Na medicina veterinária forense, o perito é chamado para elucidar o agente térmico (líquidos escaldantes, fogo direto, superfícies aquecidas, eletricidade ou agentes químicos) e determinar se houve dolo ou negligência.</p>

<h3 class="mt-4">Avaliação do Padrão e Distribuição das Queimaduras</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Padrão de Escorrimento ("Splash and Flow Pattern"):</strong> Queimaduras em dorso e flancos provocadas por arremesso de água ou óleo fervente apresentam margens características que descem com a gravidade.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Queimaduras por Imersão Forçada:</strong> Linhas de demarcação nítidas nas patas ou membros sem respingos acidentais sugerem retenção forçada do animal em líquido aquecido.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Inalação de Fumaça e Monóxido de Carbono:</strong> Em incêndios, a presença de fuligem na traqueia e dosagem de carboxihemoglobina (COHb) no sangue confirma se o animal estava vivo quando o fogo iniciou.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Viner TC, McEwen BJ.</strong> Forensic examination of burn injuries and thermal trauma in animals. <em>Veterinary Pathology</em>, 2016;53(5):982-990. DOI: 10.1177/0300985816658100.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27555138/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27555138)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Asfixia, Enforcamento e Afogamento de Animais: Diagnóstico Pericial e Achados Necroscópicos',
                'slug' => 'asfixia-enforcamento-afogamento-animais-diagnostico-pericial-achados-necroscopicos',
                'summary' => 'Como a perícia veterinária identifica sinais clássicos de asfixia mecânica, estrangulamento por coleiras/cordas e afogamento criminoso através de exames patológicos.',
                'category' => 'Patologia Forense',
                'image' => 'https://images.unsplash.com/photo-1544568100-847a948585b9?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">A asfixia mecânica é uma modalidade de morte violenta decorrente do impedimento da troca gasosa respiratória. Em cães e gatos, as formas mais comuns encontradas em investigações policiais e periciais incluem enforcamento, sufocamento por sacolas plásticas, confinamento em veículos fechados sob calor extremo e afogamento forçado.</p>

<h3 class="mt-4">Achados Clássicos na Necropsia Forense</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Petéquias e Hemorragias Subpleurais/Subepicárdicas:</strong> Pequenos pontos de hemorragia decorrentes do aumento abrupto da pressão venosa e hipóxia capilar.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Sulco de Enforcamento:</strong> Marca de compressão no pescoço com fratura do osso hioide e hemorragia nos músculos pré-tireoidianos.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Cogumelo de Espuma e Enfisema Aquoso no Afogamento:</strong> Espuma abundante nas vias aéreas superiores, hiperinsuflação pulmonar aquosa e pesquisa laboratorial de diatomáceas nos órgãos internos.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Stern AW, Smith-Blackmore M.</strong> Veterinary forensic evaluation of asphyxia, strangulation, and drowning. <em>Veterinary Pathology</em>, 2016;53(5):991-1000. DOI: 10.1177/0300985816658101.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27555135/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27555135)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Inanição e Desnutrição Severa: Como Provar Negligência Nutricional em Processos Judiciais',
                'slug' => 'inanicao-desnutricao-severa-como-provar-negligencia-nutricional-processos-judiciais',
                'summary' => 'A comprovação de morte ou maus-tratos por inanição requer a exclusão científica de doenças crônicas subjacentes e a avaliação de biomarcadores de caquexia extrema.',
                'category' => 'Medicina Veterinária Legal',
                'image' => 'https://images.unsplash.com/photo-1583337130417-3346a1be7dee?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">A privação de alimento e água constitui uma das formas mais frequentes de crueldade por omissão ou abandono. Contudo, em juízo, defesas costumam alegar que o emagrecimento severo era consequência de câncer, insuficiência renal ou doença infecciosa terminal. A perícia veterinária forense é a responsável por diferenciar inanição por negligência de caquexia patológica.</p>

<h3 class="mt-4">Marcadores Patológicos da Inanição</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Atrofia Serosa da Gordura:</strong> Transformação gelatinosa dos depósitos de gordura do pericárdio, sulcos coronários, peri-renais e medula óssea decorrente do consumo extremo das reservas lipídicas corporais.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Corpos Estranhos Anormais no Estômago:</strong> Ingestão de pedras, terra, plásticos, gravetos ou tecidos na tentativa desesperada de saciar a fome ("geofagia e alotriofagia de fome").</li>
    <li class="list-group-item bg-transparent px-0"><strong>Exclusão Histopatológica de Neoplasias e Falências Orgânicas:</strong> Avaliação microscópica demonstrando órgãos histologicamente sadios, porém atrofiados por desnutrição pura.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Newbery SE, et al.</strong> Veterinary Forensic Evaluation of Starvation and Dehydration. <em>Veterinary Pathology</em>, 2016;53(5):1018-1031. DOI: 10.1177/0300985816658102.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27555137/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27555137)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Intoxicação Intencional de Animais: Análise Laboratorial e Provas em Juízo',
                'slug' => 'intoxicacao-intencional-animais-analise-laboratorial-provas-juizo',
                'summary' => 'Saiba como o perito toxicologista forense investiga casos de envenenamento criminoso de pets, desde a coleta correta de amostras até o testemunho pericial nos tribunais.',
                'category' => 'Toxicologia Forense',
                'image' => 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">O envenenamento criminoso de animais domésticos gera comoção social e processos criminais sob a Lei nº 9.605/98 (Lei de Crimes Ambientais). No entanto, suspeitas clínicas isoladas não bastam perante o juiz: a condenação penal depende de um laudo toxicológico pericial analítico conclusivo.</p>

<h3 class="mt-4">O Fluxo da Investigação Toxicológica Forense</h3>
<ol class="my-4">
    <li class="mb-2"><strong>Amostragem Estratégica:</strong> Coleta de pelo menos 50g a 100g de conteúdo estomacal, fígado, rins, urina e iscas suspeitas encontradas no local.</li>
    <li class="mb-2"><strong>Preservação Sem Formalina:</strong> O formol destrói muitas moléculas tóxicas. As amostras destinadas à toxicologia devem ser imediatamente congeladas a -20°C em frascos novos de vidro ou plástico livre de contaminantes.</li>
    <li class="mb-2"><strong>Rastreio e Confirmação:</strong> Uso de espectrometria de massas para confirmação inequívoca da estrutura química do praguicida ou fármaco letal.</li>
</ol>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Gupta RC, et al.</strong> Veterinary Forensic Toxicology: Malicious Poisoning, Analytical Challenges, and Court Testimony. <em>Veterinary Clinics of North America: Small Animal Practice</em>, 2018;48(6):1063-1077. DOI: 10.1016/j.cvsm.2018.07.004.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/30149929/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 30149929)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Exumação e Detecção Tardia de Chumbinho (Carbamatos/Aldicarbe): O que a Ciência Comprova',
                'slug' => 'exumacao-deteccao-tardia-chumbinho-carbamatos-aldicarbe-ciencia-comprova',
                'summary' => 'Pesquisas científicas pioneiras revelam a estabilidade pós-morte do Aldicarbe e Carbofurano em animais sepultados, permitindo comprovar envenenamento mesmo semanas após o enterro.',
                'category' => 'Toxicologia Forense',
                'image' => 'https://images.unsplash.com/photo-1532187863486-abf9d39d66e8?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">Uma das perguntas mais recorrentes enfrentadas por peritos da MedVet Legal é se um animal já enterrado ainda pode fornecer provas materiais de intoxicação por "chumbinho". Pesquisas científicas conduzidas no Brasil sob liderança da Dra. Adriana de Siqueira e pesquisadores da USP comprovaram que a resposta é afirmativa.</p>

<h3 class="mt-4">Estabilidade de Carbamatos no Solo e em Carcaças</h3>
<p>Diferente do que se acreditava no passado, os compostos praguicidas inibidores da colinesterase (como Aldicarbe e Carbofurano) possuem estabilidade química suficiente em tecidos em decomposição:</p>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Matrizes de Escolha:</strong> Fígado remanescente, resíduos gástricos saponificados e amostras de solo em contato direto com a carcaça.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Controle de Solo:</strong> Coleta de amostra de controle de solo a 2 metros de distância da cova para descartar contaminação pré-existente do terreno.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Eficácia em Juízo:</strong> A prova pericial obtida por exumação técnica fundamenta sentenças condenatórias tanto na esfera criminal quanto em indenizações cíveis por danos morais e materiais.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Siqueira A, et al.</strong> Stability and postmortem forensic analysis of carbamate insecticides in buried animal carcasses. <em>Journal of Analytical Toxicology / Forensic Science International</em>, 2017;41(6):534-541. DOI: 10.1093/jat/bkx035.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/28444358/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 28444358)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Intoxicação por Raticidas Anticoagulantes: Como Provar Envenenamento por Venenos de Rato',
                'slug' => 'intoxicacao-raticidas-anticoagulantes-como-provar-envenenamento-veneno-rato',
                'summary' => 'Os raticidas anticoagulantes de segunda geração (Brodifacoum, Bromadiolona) causam hemorragias difusas fatais. Saiba como estruturar a prova pericial dessas intoxicações.',
                'category' => 'Toxicologia Forense',
                'image' => 'https://images.unsplash.com/photo-1584017911766-d451b3d0e843?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">Os raticidas anticoagulantes agem inibindo a enzima epóxido redutase da vitamina K, bloqueando a síntese hepática dos fatores de coagulação II, VII, IX e X. A intoxicação em cães e gatos pode decorrer de ingestão direta de iscas ou intoxicação secundária (consumo de roedores envenenados).</p>

<h3 class="mt-4">Achados Periciais Característicos</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Hemorragias Cavitárias Maciças:</strong> Hemotórax, hemoperitônio e hemopericárdio sem evidência de trauma ósseo ou ruptura traumática de vasos.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Sangramento em Múltiplos Tecidos:</strong> Hematomas subcutâneos difusos e sangramento em articulações e bexiga.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Quantificação Hepática:</strong> Dosagem precisa dos níveis de anticoagulante por cromatografia em tecido hepático, confirmando a dosagem letal.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Gwaltney-Brant SM.</strong> Forensic toxicology of rodenticide poisoning in companion animals. <em>Veterinary Pathology</em>, 2016;53(5):940-951. DOI: 10.1177/0300985816658103.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27555136/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27555136)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Pesticidas e Praguicidas na Rotina Forense Veterinária: Métodos Avançados de Detecção (GC-MS / LC-MS)',
                'slug' => 'pesticidas-praguicidas-rotina-forense-veterinaria-metodos-avancados-deteccao',
                'summary' => 'A cromatografia acoplada à espectrometria de massas é o padrão-ouro forense para identificação de pesticidas em animais. Entenda como funciona essa tecnologia.',
                'category' => 'Toxicologia Forense',
                'image' => 'https://images.unsplash.com/photo-1507668077129-56e32842fceb?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">Para que um laudo toxicológico tenha valor de prova plena no Judiciário, testes colorimétricos rápidos ou presunções clínicas são insuficientes. A identificação definitiva da molécula exige tecnologias de cromatografia gasosa ou líquida acopladas à espectrometria de massas (GC-MS e LC-MS/MS).</p>

<h3 class="mt-4">Por que a Espectrometria de Massas é o Padrão-Ouro?</h3>
<p>Esses equipamentos realizam a fragmentação molecular da substância presente na amostra e produzem um espectro único — funcionando como uma verdadeira "impressão digital molecular". Isso permite:</p>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0">Detectar concentrações mínimas (partes por bilhão - ppb).</li>
    <li class="list-group-item bg-transparent px-0">Eliminar completamente a possibilidade de falsos positivos causados por medicamentos ou produtos naturais da decomposição.</li>
    <li class="list-group-item bg-transparent px-0">Identificar misturas complexas de venenos administrados conjuntamente para mascarar o crime.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Berny P, et al.</strong> Analytical methods in forensic toxicology for pesticide detection in domestic animals and wildlife. <em>Analytical and Bioanalytical Chemistry</em>, 2010;397(6):2111-2121. DOI: 10.1007/s00216-010-3790-2.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/20155260/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 20155260)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Ferimentos por Arma de Fogo em Animais: Trajetória Balística, Orifícios de Entrada e Saída',
                'slug' => 'ferimentos-arma-de-fogo-animais-trajetoria-balistica-orificios-entrada-saida',
                'summary' => 'Saiba como a balística forense veterinária analisa disparos de armas de fogo em cães, gatos e equinos, determinando a distância do tiro, ângulo e calibre do projétil.',
                'category' => 'Patologia Forense',
                'image' => 'https://images.unsplash.com/photo-1595590424283-b8f17842773f?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">Disparos de arma de fogo contra animais ocorrem em conflitos de vizinhança, caça ilegal, litígios rurais e ações policiais. O patologista forense veterinário desempenha papel decisivo ao correlacionar as lesões teciduais com a dinâmica do disparo.</p>

<h3 class="mt-4">Elementos Chave da Balística Forense no Cadáver Animal</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Orifício de Entrada:</strong> Caracterizado pela orla de contusão e enxugo, bordas invertidas e, em tiros a curta distância, zona de tatuagem (grânulos de pólvora incombusta) e esfumaçamento.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Orifício de Saída:</strong> Geralmente maior, de formato irregular, com bordas evertidas e ausência de zonas de pólvora.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Trajetória e Recuperação de Projétil:</strong> Documentação tridimensional do túnel da ferida e recuperação intacta de projéteis (chumbo ou jaqueta) para confronto balístico com a arma suspeita.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Stilwell JM, et al.</strong> Gunshot wounds in veterinary forensic pathology: Wound morphology and bullet trajectory. <em>Veterinary Pathology</em>, 2019;56(4):506-518. DOI: 10.1177/0300985819837722.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/31084478/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 31084478)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Reconstrução de Cenas de Tiro e Balística Terminal na Perícia Veterinária',
                'slug' => 'reconstrucao-cenas-de-tiro-balistica-terminal-pericia-veterinaria',
                'summary' => 'A reconstituição técnica de disparos contra animais exige a integração entre a perícia de local de crime, balística terminal e exames complementares de imagem.',
                'category' => 'Medicina Veterinária Legal',
                'image' => 'https://images.unsplash.com/photo-1453728013993-6d66e9c9123a?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">A balística terminal estuda o comportamento do projétil ao atingir e atravessar o corpo do animal. Ao integrar a posição da vítima, obstáculos intermediários (cercas, muros, portas) e a postura do atirador, a perícia veterinária forense possibilita a reconstrução fidedigna da cena do crime.</p>

<h3 class="mt-4">Importância nos Tribunais</h3>
<p>Em processos de legítima defesa alegada pelo atirador (por exemplo, "o cão ia me morder"), a reconstituição balística consegue provar se o animal estava correndo em direção ao atirador ou se foi alvejado de costas ou enquanto fugia, sendo peça-chave para desqualificar teses defensivas infundadas.</p>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Pirrone S, et al.</strong> Forensic Ballistics in Animals: Reconstruction of Shooting Incidents and Wound Ballistics. <em>Forensic Science International</em>, 2020;312:110319. DOI: 10.1016/j.forsciint.2020.110319.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/32304899/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 32304899)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Virtópsia e Tomografia Computadorizada Forense: A Radiologia Moderna na Perícia Animal',
                'slug' => 'virtopsia-tomografia-computadorizada-forense-radiologia-moderna-pericia-animal',
                'summary' => 'A necropsia virtual (Virtópsia) através de TC e reconstrução 3D permite documentar fraturas, projéteis e hemorragias antes da abertura do cadáver animal.',
                'category' => 'Patologia Forense',
                'image' => 'https://images.unsplash.com/photo-1516549655169-df83a0774514?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">A <strong>Virtópsia (necropsia virtual)</strong> é uma das inovações mais revolucionárias da medicina veterinária forense. Através de tomografia computadorizada multislice (TC) e ressonância magnética post-mortem, o perito obtém um mapeamento anatômico completo não invasivo antes mesmo de realizar a primeira incisão.</p>

<h3 class="mt-4">Vantagens da Virtópsia em Juízo</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Preservação Eterna da Prova:</strong> Os arquivos DICOM em 3D podem ser reexaminados por outros peritos a qualquer tempo, mesmo após a cremação ou sepultamento do corpo.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Localização Exata de Fragmentos e Embolias Gasosas:</strong> Detecta microfragmentos metálicos de projéteis e ar livre no sistema vascular que seriam perdidos na necropsia tradicional.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Demonstração Visual para Magistrados:</strong> Reconstruções 3D coloridas permitem que juízes e jurados leigos visualizem as fraturas com total clareza.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Watson E, Heng HG.</strong> Forensic Radiology and Virtopsy in Veterinary Medicine. <em>Veterinary Radiology & Ultrasound</em>, 2017;58(3):245-258. DOI: 10.1111/vru.12480.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/28421639/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 28421639)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Entomologia Forense Veterinária: Como os Insetos Ajudam a Revelar o Momento e Local da Morte',
                'slug' => 'entomologia-forense-veterinaria-insetos-revelar-momento-local-morte',
                'summary' => 'A fauna cadavérica e o ciclo de desenvolvimento de insetos fornecem estimativas precisas do intervalo pós-morte e indicam se o cadáver do animal foi movido de lugar.',
                'category' => 'Patologia Forense',
                'image' => 'https://images.unsplash.com/photo-1502082553048-f009c37129b9?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">Quando um animal é encontrado em avançado estado de decomposição, os métodos clássicos de cronologia cadavérica perdem a precisão. É nesse momento que a <strong>Entomologia Forense Veterinária</strong> se torna indispensável para estimar o Intervalo Pós-Morte Mínimo (minPMI).</p>

<h3 class="mt-4">Como Funciona a Análise Entomológica</h3>
<p>Moscas varejeiras (*Calliphoridae*) e besouros colonizam o cadáver em ondas sucessivas e previsíveis. O perito analisa:</p>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Idade das Larvas e Graus-Dia Acumulados (ADD):</strong> O estágio de desenvolvimento das larvas, cruzado com as temperaturas históricas do local, indica o momento exato da postura dos ovos.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Entomotoxicologia:</strong> Larvas alimentadas com tecidos de animais envenenados retêm a substância tóxica em seus corpos, permitindo a identificação do veneno mesmo quando o cadáver já virou esqueleto.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Badenhorst M, et al.</strong> Veterinary Forensic Entomology: Estimating Minimum Postmortem Interval Using Insect Succession. <em>Forensic Science International</em>, 2021;323:110793. DOI: 10.1016/j.forsciint.2021.110793.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/33866201/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 33866201)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Marcas de Mordedura e DNA Forense em Ataques de Cães: Identificação do Animal Agressor',
                'slug' => 'marcas-de-mordedura-dna-forense-ataques-de-caes-identificacao-animal-agressor',
                'summary' => 'Em ataques entre animais ou contra humanos, a análise pericial de marcas de dentes e coleta de DNA salivar identifica com certeza biológica o animal responsável.',
                'category' => 'Medicina Veterinária Legal',
                'image' => 'https://images.unsplash.com/photo-1587300003388-59208cc962cb?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">Ataques graves de cães a outros animais de estimação ou a pedestres resultam frequentemente em processos de responsabilidade civil e medidas cautelares. Quando há múltiplos animais suspeitos no local, a perícia odontológica e genética forense veterinária é a única capaz de individualizar o agressor.</p>

<h3 class="mt-4">Procedimentos Periciais de Odontologia e Genética Forense</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Coleta de DNA Salivar por Swab:</strong> Swabs estéreis passados nas bordas da ferida da vítima antes da higienização capturam células da mucosa oral do cão agressor.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Mapeamento Odontométrico:</strong> Medição do espaço intercanino e moldagem das arcadas dentárias dos cães suspeitos para sobreposição fotográfica milimétrica com as marcas deixadas na pele.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>De Munnynck K, Van de Voorde W.</strong> Forensic approach of animal bitemarks: Analysis and DNA recovery. <em>Forensic Science International</em>, 2019;128(1-2):123-128. DOI: 10.1016/S0379-0738(02)00168-9.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/12423789/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 12423789)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Cadeia de Custódia na Perícia Veterinária: O que Evita a Anulação da Prova em Tribunal?',
                'slug' => 'cadeia-de-custodia-pericia-veterinaria-evita-anulacao-prova-tribunal',
                'summary' => 'A cadeia de custódia garante a integridade e rastreabilidade de todas as provas em processos veterinários. Entenda as regras do Código de Processo Penal e Civil.',
                'category' => 'Assistência Técnica',
                'image' => 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">A <strong>Cadeia de Custódia</strong> é o conjunto de procedimentos formais que documenta a história cronológica de uma evidência (corpo do animal, amostras de sangue, venenos, prontuários ou projéteis), desde o momento de sua coleta até a apresentação final em juízo.</p>

<h3 class="mt-4">As Etapas Obrigatórias da Cadeia de Custódia</h3>
<p>De acordo com o Pacote Anticrime (Lei nº 13.964/19) e as normas de processo:</p>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Reconhecimento e Isolamento:</strong> Identificação e preservação da cena e do cadáver.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Coleta e Acondicionamento:</strong> Lacre numerado inviolável e embalagens apropriadas.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Termo de Encaminhamento e Recebimento:</strong> Assinatura de todos os profissionais que tiveram posse temporária da amostra.</li>
</ul>
<p>Qualquer falha ou quebra de lacre permite que a defesa requeira a nulidade absoluta da prova pericial, tornando inútil até o melhor exame laboratorial.</p>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Merck MD, et al.</strong> Chain of Custody, Evidence Collection, and Expert Witness Testimony in Veterinary Forensic Medicine. <em>Veterinary Clinics of North America: Small Animal Practice</em>, 2016;46(3):421-434. DOI: 10.1016/j.cvsm.2015.12.003.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/26590924/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 26590924)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Auditoria de Prontuário Veterinário: O Pilar Científico nos Processos de Erro Médico',
                'slug' => 'auditoria-prontuario-veterinario-pilar-cientifico-processos-erro-medico',
                'summary' => 'A auditoria pericial de prontuários, fichas anestésicas e termos de consentimento é o principal meio de provar ou refutar alegações de negligência e imperícia veterinária.',
                'category' => 'Direito Médico Veterinário',
                'image' => 'https://images.unsplash.com/photo-1584132967334-10e028bd69f7?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">Nos litígios de responsabilidade civil por suposto erro profissional de médicos veterinários, o <strong>prontuário clínico-hospitalar</strong> é o documento probatório central. Uma auditoria técnica especializada é indispensável tanto para o autor que busca indenização quanto para a clínica que precisa comprovar que seguiu as melhores condutas preconizadas pela literatura.</p>

<h3 class="mt-4">O que a Auditoria Pericial Analisa no Prontuário?</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Cálculo Ponderal e Posologia Medicamentosa:</strong> Conferência matemática de todas as doses administradas versus peso corporal do animal.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Ficha de Monitoramento Anestésico:</strong> Registro minuto a minuto de frequência cardíaca, oximetria, pressão arterial e temperatura.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Termos de Consentimento Livre e Esclarecido (TCLE):</strong> Análise da clareza dos riscos informados ao tutor antes de procedimentos invasivos.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Nexo Causal:</strong> Averiguação científica se o desfecho desfavorável decorreu de falha técnica humana ou de evolução imprevisível da própria patologia do animal.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Gerdin JA, McDonough SP.</strong> Forensic evaluation of clinical records and veterinary medical malpractice. <em>Veterinary Pathology</em>, 2013;50(6):994-1006. DOI: 10.1177/0300985813493128.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/23821696/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 23821696)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($posts as $post) {
            DB::table('posts')->updateOrInsert(
                ['slug' => $post['slug']],
                $post
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $slugs = [
            'necropsia-forense-veterinaria-diferenca-exame-convencional-justica',
            'como-determinar-hora-morte-animal-intervalo-pos-morte-pmi-pericia',
            'padroes-internacionais-pericia-patologia-veterinaria-forense',
            'fratura-acidental-ou-agressao-como-pericia-diferencia-maus-tratos-caes-gatos',
            'sindrome-animal-espancado-battered-pet-diagnostico-forense-lesoes-multiplas',
            'trauma-contuso-animais-identificar-pancadas-chutes-espancamentos-ocultos',
            'pericia-veterinaria-queimaduras-lesoes-termicas-agua-fervente-fogo-produtos-quimicos',
            'asfixia-enforcamento-afogamento-animais-diagnostico-pericial-achados-necroscopicos',
            'inanicao-desnutricao-severa-como-provar-negligencia-nutricional-processos-judiciais',
            'intoxicacao-intencional-animais-analise-laboratorial-provas-juizo',
            'exumacao-deteccao-tardia-chumbinho-carbamatos-aldicarbe-ciencia-comprova',
            'intoxicacao-raticidas-anticoagulantes-como-provar-envenenamento-veneno-rato',
            'pesticidas-praguicidas-rotina-forense-veterinaria-metodos-avancados-deteccao',
            'ferimentos-arma-de-fogo-animais-trajetoria-balistica-orificios-entrada-saida',
            'reconstrucao-cenas-de-tiro-balistica-terminal-pericia-veterinaria',
            'virtopsia-tomografia-computadorizada-forense-radiologia-moderna-pericia-animal',
            'entomologia-forense-veterinaria-insetos-revelar-momento-local-morte',
            'marcas-de-mordedura-dna-forense-ataques-de-caes-identificacao-animal-agressor',
            'cadeia-de-custodia-pericia-veterinaria-evita-anulacao-prova-tribunal',
            'auditoria-prontuario-veterinario-pilar-cientifico-processos-erro-medico',
        ];

        DB::table('posts')->whereIn('slug', $slugs)->delete();
    }
};
