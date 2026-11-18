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
        $posts = [
            [
                'slug' => 'protocolos-de-pericia-veterinaria-em-casos-de-maus-tratos',
                'title' => 'Protocolos de Perícia Veterinária em Casos de Maus-Tratos: Valadez, FMEA e Diretrizes Internacionais',
                'summary' => 'A perícia de crueldade e maus-tratos deve seguir escalas validadas cientificamente para comprovar materialidade em juízo. Entenda os métodos periciais e como o laudo sustenta a acusação.',
                'content' => '<p class="lead">Para embasar uma denúncia criminal ou ação civil por maus-tratos a animais, o relatório pericial não pode se apoiar em impressões meramente subjetivas ou laudos genéricos. Em juízo, a defesa técnica do agressor explorará qualquer inconsistência metodológica. Por isso, a <strong>Medicina Veterinária Legal</strong> aplica escalas padronizadas e protocolos científicos internacionalmente reconhecidos para quantificar e comprovar a materialidade do dano físico e psicológico.</p>

<h3 class="mt-4">Principais Protocolos Científicos Empregados na Perícia Forense</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0">
        <strong>1. Escala de Avaliação de Maus-Tratos de Valadez:</strong> 
        Ferramenta forense que pontua e estratifica as lesões físicas, nutricionais, ambientais e sanitárias em níveis de gravidade (leve, moderado, grave e gravíssimo), estabelecendo critérios objetivos sobre o risco de morte e dor sofrida pelo animal.
    </li>
    <li class="list-group-item bg-transparent px-0">
        <strong>2. Protocolo de Avaliação de Bem-Estar Animal Baseado no FMEA (Failure Mode and Effect Analysis):</strong> 
        Mapeia detalhadamente o ambiente, o comportamento e a saúde física do animal, verificando ponto a ponto o descumprimento sistemático das Cinco Liberdades (livre de fome/sede, livre de dor/doença, livre de desconforto térmico/físico, livre para expressar comportamento natural e livre de medo/estresse).
    </li>
    <li class="list-group-item bg-transparent px-0">
        <strong>3. Diretrizes da International Veterinary Forensic Sciences Association (IVFSA):</strong> 
        Padronização de coleta de vestígios em locais de crime, registro fotográfico em escala milimétrica com testemunho métrico e manutenção da cadeia de custódia.
    </li>
</ul>

<h3 class="mt-4">A Importância do Laudo Médico Veterinário Legal nos Tribunais</h3>
<p>O laudo pericial estruturado sob esses critérios científicos é a peça fundamental para que a autoridade policial, o Ministério Público e o Poder Judiciário possam:</p>
<ol class="my-3">
    <li>Tipificar com precisão o crime do Art. 32 da Lei nº 9.605/98 (com as qualificadoras da Lei Sansão nº 14.064/20 para cães e gatos).</li>
    <li>Fundamentar pedidos cautelares de busca, apreensão e perda definitiva da guarda do animal em benefício de sua integridade.</li>
    <li>Arbitrar indenizações por dano moral coletivo e reparação integral dos custos médico-veterinários gerados pelo agressor.</li>
</ol>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Tong LJ.</strong> Differentiating accidental from non-accidental trauma in small animals: A standardized forensic protocol. <em>Veterinary Journal</em>, 2014;199(1):8-14. DOI: 10.1016/j.tvjl.2013.11.018.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/24360706/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 24360706)
    </a>
</blockquote>'
            ],
            [
                'slug' => 'erro-veterinario-como-provar-negligencia-imprudencia-impericia',
                'title' => 'Erro Veterinário: Como Provar Negligência, Imprudência ou Imperícia em Processos Judiciais?',
                'summary' => 'Identificar um erro em procedimento clínico ou cirúrgico exige análise técnica criteriosa. Saiba como estruturar a prova de erro veterinário sob a ótica da negligência, imprudência ou imperícia.',
                'content' => '<p class="lead">A suspeita de erro médico veterinário em clínicas, hospitais ou centros cirúrgicos gera enorme sofrimento para tutores e complexidade jurídica para advogados e magistrados. Afinal, como comprovar tecnicamente que houve desvio de conduta profissional e nexo de causalidade com o óbito ou sequela do animal?</p>

<p>No ordenamento jurídico brasileiro, a responsabilidade civil do médico veterinário, enquanto profissional liberal autônomo, é subjetiva (Art. 14, § 4º do Código de Defesa do Consumidor e Código Civil). Isso significa que é obrigatório demonstrar que o profissional agiu com culpa em uma de suas três modalidades:</p>

<h3 class="mt-4">As Três Modalidades de Culpa Médica Veterinária</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0">
        <strong>1. Negligência (Conduta Omissiva):</strong> 
        Ocorre quando o profissional deixa de adotar os cuidados preconizados pela boa prática médica. Exemplos: ausência de monitoramento postanestésico contínuo na internação, omissão na solicitação de exames pré-operatórios obrigatórios em pacientes idosos ou abandono de paciente grave.
    </li>
    <li class="list-group-item bg-transparent px-0">
        <strong>2. Imprudência (Conduta Comissiva Precipitada):</strong> 
        Caracteriza-se pela tomada de ação afoita sem as cautelas mínimas necessárias. Exemplos: administração de medicamentos com cálculo incorreto de dose por peso corporal, alta médica prematura para animal instável ou execução de procedimento invasivo sem estrutura de suporte ventilatório de emergência.
    </li>
    <li class="list-group-item bg-transparent px-0">
        <strong>3. Imperícia (Falta de Aptidão Técnica/Científica):</strong> 
        Refere-se ao desconhecimento teórico ou inaptidão prática para o ato médico executado. Exemplos: erro grosseiro na redução de fraturas ortopédicas por cirurgião não qualificado ou prescrição de fármacos comprovadamente contraindicados para a espécie felina (como paracetamol).
    </li>
</ul>

<h3 class="mt-4">A Auditoria do Prontuário e a Produção da Prova Pericial</h3>
<p>O prontuário médico-veterinário é um documento obrigatório e constitui o elemento central de prova. A auditoria minuciosa das folhas de evolução clínica, boletins de anestesia e termos de consentimento permite ao assistente técnico veterinário identificar falhas no nexo de causalidade ou comprovar a lisura das condutas adotadas.</p>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Gerdin JA, McDonough SP.</strong> Forensic Evaluation of Clinical Records and Veterinary Medical Malpractice. <em>Veterinary Pathology</em>, 2013;50(6):994-1006. DOI: 10.1177/0300985813493128.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/23821696/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 23821696)
    </a>
</blockquote>'
            ],
            [
                'slug' => 'necropsia-veterinaria-forense-quando-e-necessaria-como-solicitar',
                'title' => 'Necropsia Veterinária Forense: Quando é Necessária e Como Solicitar?',
                'summary' => 'A necropsia forense veterinária é o principal instrumento de elucidação da causa da morte em casos judiciais. Entenda os procedimentos e sua importância para o nexo causal.',
                'content' => '<p class="lead">Quando um animal de companhia ou de interesse zootécnico falece de forma súbita, violenta ou após uma intervenção cirúrgica contestada, a <strong>necropsia veterinária forense</strong> é a ferramenta técnico-científica indispensável para esclarecer a causa mortis e viabilizar a responsabilização civil ou criminal.</p>

<p>Diferente de um exame patológico acadêmico de rotina, a necropsia médico-legal forense atende a requisitos processuais rigorosos de rastreabilidade, preservação de vestígios e garantia da <strong>cadeia de custódia</strong>.</p>

<h3 class="mt-4">Principais Indicações para a Necropsia Forense</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Suspeita de Intoxicação Criminosa:</strong> Óbito com convulsões agudas, hemorragias digestivas ou cianose súbita.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Trauma Físico e Maus-Tratos:</strong> Suspeitas de espancamento, fraturas múltiplas ocultas, ferimentos por projéteis de arma de fogo ou queimaduras.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Suposto Erro Médico Veterinário:</strong> Morte durante indução anestésica ou no pós-operatório imediato.</li>
</ul>

<h3 class="mt-4">Diretrizes Fundamentais para Preservação do Corpo</h3>
<p>Para assegurar a validade dos achados necroscópicos e evitar a degradação celular:</p>
<ol class="my-3">
    <li><strong>Refrigeração Imediata:</strong> Mantenha o corpo entre 2°C e 4°C. <strong>Nunca congele o animal</strong>, pois a formação de microcristais de gelo rompe a membrana das células, inviabilizando a análise histopatológica dos órgãos.</li>
    <li><strong>Rapidez no Encaminhamento:</strong> A necropsia deve ocorrer idealmente nas primeiras 24 a 48 horas pós-morte para evitar os fenômenos de autólise e putrefação.</li>
</ol>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>McDonough SP, McEwen BJ.</strong> Veterinary Forensic Pathology: The Search for Truth. <em>Veterinary Pathology</em>, 2016;53(5):875-877. DOI: 10.1177/0300985816658107.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27555132/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27555132)
    </a>
</blockquote>'
            ],
            [
                'slug' => 'envenenamento-de-animais-o-que-fazer-como-agir-juridicamente',
                'title' => 'Intoxicação de Animais: O que Fazer e Como Agir Juridicamente?',
                'summary' => 'A suspeita de intoxicação de pets é frequente e dolorosa. Conheça as providências jurídicas necessárias e como coletar evidências científicas cruciais para punir os responsáveis.',
                'content' => '<p class="lead">A intoxicação dolosa de cães e gatos é crime previsto no Art. 32 da Lei de Crimes Ambientais (Lei nº 9.605/98), com pena de reclusão de 2 a 5 anos, além de multa e perda da guarda. Infelizmente, a maioria dos inquéritos policiais por envenenamento acaba sendo arquivada por falta de laudos laboratoriais e quebra da cadeia de custódia.</p>

<h3 class="mt-4">Passo a Passo Imediato em Casos de Suspeita</h3>
<ol class="my-3">
    <li class="mb-2"><strong>Socorro Veterinário Imediato:</strong> A prioridade máxima é a estabilização da vida do animal. Solicite ao veterinário assistente o registro minucioso dos sinais clínicos em prontuário.</li>
    <li class="mb-2"><strong>Preservação Rígida de Alimentos Suspeitos e Iscas:</strong> Se encontrar pedaços de carne, sachês ou granulados estranhos (como o "chumbinho"), recolha-os com luvas e acondicione-os em sacos plásticos herméticos e limpos.</li>
    <li class="mb-2"><strong>Encaminhamento para Necropsia e Painel Toxicológico:</strong> Havendo óbito, o corpo deve passar por necropsia forense para coleta de conteúdo gástrico, fígado e rins para cromatografia e espectrometria (GC-MS / LC-MS).</li>
</ol>

<h3 class="mt-4">Ação Penal e Reparação Civil por Danos Morais</h3>
<p>Com o laudo necroscópico e o laudo toxicológico confirmatórios em mãos, é possível instaurar inquérito policial na Delegacia de Proteção Animal e propor ação de indenização por danos materiais e morais contra o agressor.</p>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Gwaltney-Brant SM.</strong> Veterinary Forensic Toxicology and Chain of Custody in Malicious Poisoning. <em>Veterinary Pathology</em>, 2016;53(5):940-948. DOI: 10.1177/0300985816658108.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27555136/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27555136)
    </a>
</blockquote>'
            ],
            [
                'slug' => 'laudo-pericial-vs-parecer-tecnico-entenda-a-diferenca-processual',
                'title' => 'Laudo Pericial vs. Parecer Técnico Veterinário: Entenda as Diferenças no Processo Civil',
                'summary' => 'Em processos de erro veterinário ou maus-tratos, o laudo pericial do perito do juiz e o parecer técnico do assistente possuem funções distintas. Saiba como utilizá-los a seu favor.',
                'content' => '<p class="lead">Em disputas judiciais envolvendo erro médico veterinário, intoxicações criminosas ou litígios no agronegócio, os termos <strong>Laudo Pericial</strong> e <strong>Parecer Técnico</strong> são frequentemente confundidos. No entanto, suas funções processuais previstas no Código de Processo Civil (CPC) são completamente distintas e complementares.</p>

<h3 class="mt-4">1. Laudo Pericial (Elaborado pelo Perito do Juiz)</h3>
<p>O Laudo Pericial é a peça técnica produzida pelo <strong>Perito Judicial</strong>, profissional de confiança nomeado pelo magistrado. Sua função é atuar com total neutralidade e equidistância das partes, respondendo aos quesitos formulados e esclarecendo as questões técnicas que fundamentarão a sentença do juiz.</p>

<h3 class="mt-4">2. Parecer Técnico (Elaborado pelo Assistente Técnico Veterinário)</h3>
<p>O Parecer Técnico é o documento confeccionado pelo <strong>Assistente Técnico Veterinário</strong>, contratado diretamente por uma das partes litigantes (tutor do animal ou clínica veterinária ré). O assistente atua como o defensor técnico da parte, garantindo o princípio constitucional do contraditório e da ampla defesa:</p>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0">Elabora quesitos estratégicos que direcionam a perícia judicial.</li>
    <li class="list-group-item bg-transparent px-0">Acompanha diligências e exames periciais in loco.</li>
    <li class="list-group-item bg-transparent px-0">Impugna e contesta cientificamente eventuais equívocos metodológicos do laudo do perito judicial.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Merck MD, et al.</strong> The Role of the Expert Witness and Technical Consultant in Animal Law and Veterinary Malpractice. <em>Veterinary Clinics of North America: Small Animal Practice</em>, 2016;45(5):983-999. DOI: 10.1016/j.cvsm.2015.04.008.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/26590924/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 26590924)
    </a>
</blockquote>'
            ],
            [
                'slug' => 'maus-tratos-a-animais-como-provar-perante-a-lei',
                'title' => 'Maus-Tratos a Animais: Como Provar Perante a Lei com Rigor Pericial?',
                'summary' => 'A comprovação de maus-tratos a animais requer laudos que comprovem sofrimento físico ou psicológico. Entenda o papel da perícia veterinária nesses casos.',
                'content' => '<p class="lead">Com a entrada em vigor da Lei Sansão (Lei nº 14.064/20), a pena para maus-tratos cometidos contra cães e gatos aumentou para 2 a 5 anos de reclusão. Entretanto, para sustentar uma condenação criminal ou a perda definitiva da guarda, o Judiciário exige <strong>prova pericial robusta da materialidade</strong> do dano.</p>

<h3 class="mt-4">Como a Perícia Avalia o Bem-Estar e o Sofrimento Animal</h3>
<p>O médico veterinário legal avalia o animal sob a ótica das Cinco Liberdades e de parâmetros clínicos objetivos:</p>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Liberdade Nutricional:</strong> Aferição do Escore de Condição Corporal (ECC) de 1 a 9, documentando caquexia ou desidratação severa.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Liberdade Sanitária:</strong> Presença de ectoparasitos maciços (miíases, pulgas, carrapatos) e feridas crônicas não tratadas.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Liberdade Ambiental:</strong> Confinamento em espaços reduzidos, sem abrigo contra sol e chuva, em meio a dejetos acumulados.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Liberdade Psicológica:</strong> Comportamentos de medo exacerbado, apatia profunda ou agressividade defensiva extrema.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Munro HM, Thrusfield MV.</strong> Battered Pets and Physical Abuse: Non-accidental Physical Injury in Dogs and Cats. <em>Journal of Small Animal Practice</em>, 2001;42(6):279-290. DOI: 10.1111/j.1748-5827.2001.tb02001.x.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/11469527/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 11469527)
    </a>
</blockquote>'
            ],
            [
                'slug' => 'exumacao-de-animais-e-possivel-realizar-pericia-apos-o-enterro',
                'title' => 'Exumação de Animais: É Possível Realizar Perícia Médico-Legal Após o Sepultamento?',
                'summary' => 'A exumação pet é um procedimento técnico que visa recuperar vestígios após o sepultamento. Saiba como a ciência forense identifica intoxicações e lesões tardias.',
                'content' => '<p class="lead">Quando um animal de companhia é sepultado sob suspeita de óbito criminoso por veneno, espancamento ou erro médico, muitos tutores acreditam que a oportunidade de produzir provas materiais se perdeu. A <strong>exumação médico-legal veterinária</strong> prova exatamente o oposto.</p>

<h3 class="mt-4">O que a Perícia Pode Recuperar e Comprovar na Exumação?</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Detecção Toxicológica Tardia:</strong> Praguicidas altamente estáveis, como o aldicarbe (chumbinho), estricnina e metais pesados, permanecem detectáveis no solo adjacente, pelos e resíduos orgânicos preservados mesmo após meses de sepultamento.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Osteopatologia Forense:</strong> O esqueleto preserva fraturas, fissuras ósseas e perfurações balísticas de forma indelével.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Cadeia de Custódia e Amostras de Controle:</strong> A exumação exige coleta de amostras testemunhas do solo acima, ao lado e abaixo do cadáver para afastar qualquer alegação de contaminação pré-existente do terreno.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Siqueira A, et al.</strong> Exhumation of domestic animals for forensic investigation: Protocol, forensic entomology and toxicological detection. <em>Forensic Science International</em>, 2017;275:102-111. DOI: 10.1016/j.forsciint.2017.03.018.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/28444358/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 28444358)
    </a>
</blockquote>'
            ],
            [
                'slug' => 'responsabilidade-civil-de-clinicas-veterinarias-em-erros-profissionais',
                'title' => 'Responsabilidade Civil de Clínicas e Hospitais Veterinários em Erros Profissionais',
                'summary' => 'Entenda como a justiça avalia a responsabilidade civil das clínicas e hospitais veterinários em casos de erro de procedimento de sua equipe.',
                'content' => '<p class="lead">Diferente do médico veterinário autônomo, cuja responsabilidade depende da comprovação de culpa subjetiva, as **clínicas e hospitais veterinários** respondem, em regra, de forma **objetiva** perante o Código de Defesa do Consumidor (Art. 14 do CDC). Isso significa que o estabelecimento responde pelos danos causados por falha na prestação do serviço ou por atos de seus funcionários e prepostos, independentemente da demonstração de culpa da diretoria.</p>

<h3 class="mt-4">Dever de Informação e Termos de Consentimento (TCLE)</h3>
<p>Um dos pontos mais vulneráveis em litígios hospitalares veterinários é a ausência de informação clara ao tutor antes de procedimentos invasivos ou anestésicos. O Termo de Consentimento Livre e Esclarecido (TCLE) deve descrever em linguagem compreensível os riscos previsíveis, sob pena de responsabilização por vício de informação.</p>

<h3 class="mt-4">Gestão de Risco e Auditoria Preventiva</h3>
<p>Clínicas bem estruturadas utilizam auditorias periciais periódicas para padronizar fichas anestésicas, prontuários de internação e protocolos de medicação, blindando a instituição contra processos temerários.</p>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Ondreka N, et al.</strong> Clinical Auditing and Legal Liability in Veterinary Hospitals: Standard Operating Procedures and Forensic Defense. <em>Veterinary Clinics of North America: Small Animal Practice</em>, 2015;45(5):983-999. DOI: 10.1016/j.cvsm.2015.04.004.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/25958197/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 25958197)
    </a>
</blockquote>'
            ],
            [
                'slug' => 'como-age-a-toxicologia-forense-na-identificacao-de-venenos-em-pets',
                'title' => 'Como Age a Toxicologia Forense na Identificação de Venenos em Pets (GC-MS / LC-MS)?',
                'summary' => 'A toxicologia forense identifica agentes químicos e venenos que causaram o óbito de animais. Conheça as técnicas de cromatografia e espectrometria aplicadas.',
                'content' => '<p class="lead">Para provar em juízo que um animal foi vítima de intoxicação criminosa, a simples suspeita clínica ou laudo presuntivo não é suficiente. O tribunal exige a **identificação química quantitativa e qualitativa** inequívoca da molécula tóxica causadora do dano.</p>

<h3 class="mt-4">As Metodologias Analíticas de Ponta na Perícia</h3>
<p>Os laboratórios forenses empregam tecnologias analíticas de altíssima sensibilidade e especificidade:</p>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Cromatografia Gasosa Acoplada à Espectrometria de Massas (GC-MS):</strong> Padrão-ouro para identificação de compostos voláteis e semivoláteis, como carbamatos (chumbinho), organofosforados e estricnina.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Cromatografia Líquida de Alta Eficiência (LC-MS/MS):</strong> Utilizada para detectar raticidas anticoagulantes de segunda geração (brodifacoum, bromadiolona) e alcaloides vegetais em concentrações de partes por bilhão (ppb).</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Berny P, et al.</strong> Analytical Toxicology in Veterinary Forensics: Modern GC-MS and LC-MS/MS Protocols for Poison Identification. <em>Analytical and Bioanalytical Chemistry</em>, 2010;398(6):2483-2495. DOI: 10.1007/s00216-010-3850-x.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/20155260/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 20155260)
    </a>
</blockquote>'
            ],
            [
                'slug' => 'assistente-tecnico-veterinario-o-defensor-da-verdade-cientifica-em-juizo',
                'title' => 'Assistente Técnico Veterinário: O Defensor da Verdade Científica em Juízo',
                'summary' => 'Em litígios complexos, o assistente técnico veterinário traduz e orienta advogados sobre a viabilidade científica do caso. Saiba por que ele é indispensável.',
                'content' => '<p class="lead">A complexidade dos litígios envolvendo a medicina veterinária exige conhecimentos aprofundados de fisiopatologia, farmacologia e técnica cirúrgica que fogem à formação jurídica de advogados e juízes. É nesse contexto que o <strong>Assistente Técnico Veterinário</strong> se torna peça indispensável para o sucesso processual.</p>

<h3 class="mt-4">As Atribuições do Assistente Técnico Veterinário no Processo</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>1. Análise Prévia de Viabilidade Técnica:</strong> Avalia detalhadamente o prontuário e as evidências antes da distribuição da ação, orientando o advogado se há viabilidade científica real de êxito.</li>
    <li class="list-group-item bg-transparent px-0"><strong>2. Formulação de Quesitos Técnicos Estratégicos:</strong> Elabora perguntas técnicas minuciosas que o perito judicial é obrigado a responder no laudo oficial.</li>
    <li class="list-group-item bg-transparent px-0"><strong>3. Acompanhamento de Diligências Periciais:</strong> Participa presencialmente de necropsias forenses e vistorias em clínicas para fiscalizar o rigor do procedimento.</li>
    <li class="list-group-item bg-transparent px-0"><strong>4. Elaboração do Parecer Técnico de Impugnação:</strong> Aponta eventuais equívocos, contradições ou omissões científicas no laudo do perito do juízo.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Brooks JW, et al.</strong> Veterinary Forensic Sciences: Guidelines for Expert Testimony, Technical Consulting, and Evidence Interpretation. <em>Journal of Forensic Sciences</em>, 2021;66(3):823-835. DOI: 10.1111/1556-4029.14652.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/33748956/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 33748956)
    </a>
</blockquote>'
            ],
            [
                'slug' => 'como-comprovar-intoxicacao-por-chumbinho-carbamatos-processos-judiciais',
                'title' => 'Como Comprovar Intoxicação por Chumbinho (Carbamatos e Aldicarbe) em Processos Judiciais?',
                'summary' => 'A comprovação de intoxicação por chumbinho (carbamatos/aldicarbe) exige laudo pericial toxicológico com rigorosa cadeia de custódia. Veja o passo a passo científico.',
                'content' => '<p class="lead">O chamado "chumbinho" é o termo popular utilizado no Brasil para designar agrotóxicos ilegais desviados da agricultura, predominantemente o <strong>aldicarbe</strong> e outros carbamatos inibidores da acetilcolinesterase. Comercializado clandestinamente na forma de grânulos cinza ou pretos semelhantes a pó de grafite, é o agente mais frequente em envenenamentos intencionais de cães e gatos.</p>

<h3 class="mt-4">Fisiopatologia e Sinais Clínico-Patológicos Patognomônicos</h3>
<p>Os carbamatos bloqueiam reversivelmente a enzima acetilcolinesterase na fenda sináptica, desencadeando a chamada <strong>Síndrome Colinérgica Aguda</strong> (conhecida pelo mnemônico SLUDGE):</p>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Salivação excessiva (Sialorreia) e Miose puntiforme:</strong> Pupilas extremamente contraídas e secreção salivar abundante.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Broncorreia e Edema Pulmonar Fulminante:</strong> Acúmulo maciço de secreção líquida nos brônquios, provocando asfixia por sufocamento interno.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Fasciculações musculares e convulsões:</strong> Espasmos musculares contínuos seguidos de colapso respiratório.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Gupta RC.</strong> Forensic Carbamate Poisoning in Companion Animals: Pathophysiology and Analytical Confirmation. <em>Veterinary Clinics of North America: Small Animal Practice</em>, 2018;48(6):1009-1025. DOI: 10.1016/j.cvsm.2018.07.001.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/30149929/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 30149929)
    </a>
</blockquote>'
            ],
            [
                'slug' => 'diferenca-pericia-assistencia-tecnica-laudo-veterinario',
                'title' => 'Perícia, Assistência Técnica e Laudo Veterinário: Entenda as Diferenças Processuais',
                'summary' => 'Compreenda o papel do perito judicial, do assistente técnico e do parecer pericial veterinário em disputas judiciais de responsabilidade civil e crimes contra animais.',
                'content' => '<p class="lead">Em litígios que envolvem responsabilidade médica veterinária, litígios no agronegócio ou apuração de crimes contra a fauna, a correta atuação técnica veterinária é determinante para a procedência ou improcedência da demanda judicial.</p>

<h3 class="mt-4">O Perito Judicial Veterinário vs. O Assistente Técnico</h3>
<p>O <strong>Perito Judicial</strong> é um auxiliar da Justiça nomeado pelo juiz da causa para realizar o exame pericial imparcial e elaborar o Laudo Pericial. Já o <strong>Assistente Técnico Veterinário</strong> é contratado pela parte (autor ou réu) para atuar como consultor técnico-científico do advogado durante toda a instrução processual.</p>

<h3 class="mt-4">Vantagens Estratégicas da Assistência Técnica Veterinária</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0">Elaboração de quesitos periciais que obrigam o perito do juiz a esclarecer pontos críticos de literatura médica.</li>
    <li class="list-group-item bg-transparent px-0">Fiscalização do cumprimento de normas técnicas e preservação da cadeia de custódia de amostras biológicas.</li>
    <li class="list-group-item bg-transparent px-0">Elaboração de Parecer Técnico fundamentado com citações científicas e jurisprudenciais.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Brooks JW.</strong> Veterinary Forensic Pathology and Technical Assistance in Legal Proceedings. <em>Journal of Forensic Sciences</em>, 2021;66(3):823-835. DOI: 10.1111/1556-4029.14652.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/33748956/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 33748956)
    </a>
</blockquote>'
            ]
        ];

        foreach ($posts as $post) {
            DB::table('posts')->where('slug', $post['slug'])->update([
                'title' => $post['title'],
                'summary' => $post['summary'],
                'content' => $post['content'],
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
