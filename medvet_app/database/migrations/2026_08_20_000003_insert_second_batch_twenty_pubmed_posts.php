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
                'title' => 'Trauma por Instrumento Perfurocortante em Animais: Facadas, Estiletes e Cortes Intencionais',
                'slug' => 'trauma-instrumento-perfurocortante-animais-facadas-cortes-intencionais',
                'summary' => 'Saiba como a medicina veterinária forense diferencia feridas perfurocortantes intencionais (facadas) de cortes acidentais (arames, vidros ou cercas) através do exame das bordas e ângulo de entrada.',
                'category' => 'Patologia Forense',
                'image' => 'https://images.unsplash.com/photo-1584036561566-baf8f5f1b144?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">Lesões causadas por lâminas e objetos pontiagudos em cães, gatos e animais de grande porte são comuns em denúncias de agressão criminosa e conflitos de vizinhança. Em juízo, agressores frequentemente sustentam a tese de que o animal "se cortou sozinho em uma cerca de arame farpado ou caco de vidro". A perícia veterinária forense é a responsável por desmentir ou confirmar essa hipótese.</p>

<h3 class="mt-4">Como a Perícia Diferencia Corte Acidental de Facada Intencional?</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>1. Cauda de Escoriação e Entalhes:</strong> Lâminas de faca deixam um entalhe terminal e cauda de saída linear, indicando o sentido exato do golpe.</li>
    <li class="list-group-item bg-transparent px-0"><strong>2. Profundidade vs. Extensão:</strong> Facadas intencionais (feridas perfurocortantes) apresentam trajeto profundo em direção a cavidades internas (tórax/abdômen) com orifício de entrada de dimensões precisas. Cortes em arames, por outro lado, são tipicamente superficiais, irregulares e acompanhados de lacerações em "retalho".</li>
    <li class="list-group-item bg-transparent px-0"><strong>3. Lesões de Defesa:</strong> Cortes na face plantar/palmar das patas e região ventral do pescoço evidenciam a tentativa do animal de bloquear os golpes do agressor.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Stern AW.</strong> Sharp Force Trauma in Veterinary Forensic Pathology. <em>Veterinary Pathology</em>, 2016;53(5):972-981. DOI: 10.1177/0300985816658104.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27555134/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27555134)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Intoxicação por Etilenoglicol (Anticongelante e Aditivo de Radiador): Diagnóstico Toxicológico e Cristais de Oxalato',
                'slug' => 'intoxicacao-etilenoglicol-anticongelante-aditivo-radiador-cristais-oxalato',
                'summary' => 'O etilenoglicol possui sabor adocicado e é usado como veneno doloso. Entenda a formação de cristais de oxalato de cálcio birrefringentes nos túbulos renais na necropsia forense.',
                'category' => 'Toxicologia Forense',
                'image' => 'https://images.unsplash.com/photo-1579684385127-1ef15d508118?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">O <strong>etilenoglicol</strong>, substância amplamente presente em aditivos de radiador automotivo e fluidos refrigerantes, possui sabor extremamente doce e atrativo para cães e gatos. Por essa razão, é frequentemente utilizado de forma premeditada por criminosos para envenenar animais através de alimentos e água.</p>

<h3 class="mt-4">O Marcador Patognomônico: Cristais de Oxalato de Cálcio</h3>
<p>No organismo do pet, o etilenoglicol é metabolizado pela enzima álcool desidrogenase em metabólitos altamente tóxicos, culminando na formação de ácido oxálico. Este se liga ao cálcio sérico e precipita maciçamente nos rins:</p>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Insuficiência Renal Aguda Fulminante:</strong> O bloqueio tubular e necrose tubular aguda provocam anúria e morte em 24 a 72 horas.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Luz Polarizada na Histopatologia:</strong> Ao examinar os rins sob microscopia de luz polarizada, os cristais de oxalato de cálcio brilham intensamente em formato de leque ou prismas, fornecendo prova microscópica incontestável da intoxicação.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Thrall MA, et al.</strong> Ethylene Glycol Toxicosis in Dogs and Cats: Clinical, Forensic, and Histopathological Diagnosis. <em>Veterinary Clinics of North America: Small Animal Practice</em>, 2018;48(6):1079-1092. DOI: 10.1016/j.cvsm.2018.07.005.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/30149930/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 30149930)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Morte Anestésica e Complicações Cirúrgicas Veterinárias: Auditoria Pericial de Falha Humana vs. Risco Inerente',
                'slug' => 'morte-anestesica-complicacoes-cirurgicas-veterinarias-auditoria-pericial',
                'summary' => 'O óbito transoperatório ou no pós-anestésico exige auditoria técnico-pericial minuciosa para averiguar se houve negligência na monitoração ou se o evento decorreu de idiossincrasia biológica imprevisível.',
                'category' => 'Direito Médico Veterinário',
                'image' => 'https://images.unsplash.com/photo-1551601651-2a8555f1a136?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">A perda de um paciente veterinário durante indução anestésica, cirurgia ou recuperação na internação é uma das maiores causas de litígios por erro médico. A questão jurídica central é: <em>houve falha técnica profissional (imperícia/negligência) ou o óbito foi uma fatalidade decorrente do risco inerente ao procedimento?</em></p>

<h3 class="mt-4">O que a Perícia Audita nos Casos de Óbito Anestésico?</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Avaliação Pré-Anestésica (Classificação ASA):</strong> Se o médico veterinário solicitou exames cardiovasculares, renais e hematológicos adequados ao porte e idade do animal antes de iniciar o procedimento.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Monitorização de Parâmetros Vitais:</strong> Presença de registros de capnografia (EtCO2), oximetria de pulso, eletrocardiograma e pressão arterial não-invasiva durante todo o procedimento.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Protocolo de Ressuscitação Cardiopulmonar (RCP):</strong> Se as manobras de emergência (intubação, ventilação com pressão positiva, drogas vasoativas) foram instituídas em tempo hábil e em dosagens corretas.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Brodbelt DC.</strong> Perioperative mortality in small animal anaesthesia: Risk factors and forensic evaluation. <em>Veterinary Journal</em>, 2009;182(2):152-161. DOI: 10.1016/j.tvjl.2008.06.011.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/18778536/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 18778536)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Traumatismo Cranioencefálico (TCE) e a "Síndrome do Animal Sacudido": Prova Pericial de Agressão',
                'slug' => 'traumatismo-cranioencefalico-tce-sindrome-animal-sacudido-prova-pericial',
                'summary' => 'Movimentos violentos de aceleração e desaceleração brusca provocam hemorragias subdurais e lesões axonais difusas em filhotes e cães pequenos. Conheça a prova patológica.',
                'category' => 'Patologia Forense',
                'image' => 'https://images.unsplash.com/photo-1543466835-00a7907e9de1?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">Animais de companhia de pequeno porte e filhotes são altamente vulneráveis a traumas mecânicos intracranianos causados por arremessos violentos ou sacudimento vigoroso pelo dorso (conhecido na literatura forense como <em>Shaken Puppy Syndrome</em> ou Síndrome do Filhote Sacudido).</p>

<h3 class="mt-4">Marcadores Patológicos de Traumatismo Não Acidental no Sistema Nervoso Central</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Hemorragia Subdural e Subaracnóidea:</strong> Rompimento de veias pontes decorrente da inércia do cérebro dentro da calota craniana durante sacudimentos.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Lesão Axonal Difusa (LAD):</strong> Estiramento e ruptura microscópica de axônios cerebrais demonstrada por imunofluorescência e histopatologia com impregnação pela prata.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Hemorragias Retinianas Bilaterais:</strong> Lesões oculares típicas de forças de cisalhamento agudas, praticamente inexistentes em quedas acidentais comuns.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Platt SR, et al.</strong> Traumatic Brain Injury and Non-Accidental Head Trauma in Dogs and Cats: Clinical and Postmortem Forensic Evaluation. <em>Forensic Science, Medicine and Pathology</em>, 2018;14(3):398-406. DOI: 10.1007/s12024-018-9989-8.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/29777413/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 29777413)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Eletrocussão e Choque Elétrico em Animais: Diagnóstico Necroscópico de Marcas de Entrada e Queimaduras Joule',
                'slug' => 'eletrocussao-choque-eletrico-animais-diagnostico-marcas-joule-necropsia',
                'summary' => 'A morte por descarga elétrica pode ocorrer por cercas clandestinas energizadas, fiação irregular ou raios. Saiba como a perícia detecta a marca elétrica de Jellinek e metalização tecidual.',
                'category' => 'Patologia Forense',
                'image' => 'https://images.unsplash.com/photo-1473448912268-2022ce9509d8?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">A morte de cães, equinos e bovinos por eletrocussão gera importantes disputas de responsabilidade civil contra concessionárias de energia elétrica, proprietários de cercas clandestinas ou vizinhos que eletrificaram portões irregularmente. A comprovação médico-legal do choque elétrico exige exame criterioso dos pontos de contato.</p>

<h3 class="mt-4">Achados Periciais da Eletrocussão</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Marca Elétrica de Jellinek (Efeito Joule):</strong> Lesão cutânea circular ou linear, acinzentada ou esbranquiçada, com bordas elevadas e centro deprimido, onde a corrente elétrica penetrou no corpo.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Metalização Dérmica:</strong> Transferência microscópica de partículas do condutor metálico (cobre, ferro) para a epiderme do animal, confirmada por espectrometria de raios-X (EDS).</li>
    <li class="list-group-item bg-transparent px-0"><strong>Fibrilação Ventricular e Parada Respiratória:</strong> Ausência de lesões traumáticas mecânicas, com congestão venosa generalizada e edema pulmonar fulminante.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Viner TC.</strong> Electrocution and Lightning Strike in Veterinary Forensic Pathology. <em>Veterinary Pathology</em>, 2016;53(5):1001-1008. DOI: 10.1177/0300985816658105.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27555139/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27555139)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Acumulação Compulsiva de Animais (Síndrome de Noé / Animal Hoarding): Perícia de Bem-Estar e Saúde Pública',
                'slug' => 'acumulacao-compulsiva-animais-sindrome-noe-animal-hoarding-pericia',
                'summary' => 'O colecionismo de animais envolve transtornos psiquiátricos, extrema negligência sanitária e violação das leis de proteção animal. Saiba como atua a perícia multidisciplinar.',
                'category' => 'Medicina Veterinária Legal',
                'image' => 'https://images.unsplash.com/photo-1548767797-d8c844163c4c?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">A <strong>Síndrome de Noé</strong> (ou <em>Animal Hoarding</em>) caracteriza-se pela acumulação compulsiva de dezenas ou centenas de animais em condições degradantes, sem espaço, alimentação ou assistência veterinária básica. Trata-se de um problema complexo que une Direito Animal, Saúde Pública e Psiquiatria.</p>

<h3 class="mt-4">O Papel da Perícia Veterinária nas Intervenções Judiciais</h3>
<p>Em ações civis públicas movidas pelo Ministério Público ou mandados de busca e apreensão, o perito veterinário elabora o relatório sanitário individualizado:</p>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Escore de Condição Corporal (ECC):</strong> Mapeamento do grau de subnutrição de cada animal apreendido.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Avaliação de Doenças Infectocontagiosas e Zoonoses:</strong> Diagnóstico de sarnas, giardíase, leishmaniose e esporotricose com risco de contaminação comunitária.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Relatório Pericial Ambiental:</strong> Mensuração de níveis de amônia no ar, presença de fezes acumuladas e carcaças não sepultadas no imóvel.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Patronek GJ, et al.</strong> Animal Hoarding: Diagnostic Criteria, Multidisciplinary Investigation, and Legal Intervention. <em>Journal of the American Veterinary Medical Association</em>, 2014;245(6):629-638. DOI: 10.2460/javma.245.6.629.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/25442564/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 25442564)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Trauma Ocular por Agressão em Pets: Fraturas de Órbita, Hemorragias e Hifema Forense',
                'slug' => 'trauma-ocular-agressao-pets-fraturas-orbita-hifema-forense',
                'summary' => 'Golpes diretos no rosto de cães e gatos provocam lesões oftálmicas características (hifema, luxação de cristalino e fratura do arco zigomático). Saiba como estruturar a prova pericial.',
                'category' => 'Medicina Veterinária Legal',
                'image' => 'https://images.unsplash.com/photo-1583511655857-d19b40a7a54e?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">O globo ocular e a órbita de cães e gatos são estruturas anatômicas altamente suscetíveis a traumas por agressão direta (como socos, coronhadas ou chutes). A oftalmologia forense veterinária correlaciona as lesões oculares com a força e direção do impacto.</p>

<h3 class="mt-4">Achados Oftálmicos mais Frequentes no Trauma Não Acidental</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Hifema (Sangue na Câmara Anterior):</strong> Sangramento agudo decorrente de contusão mecânica direta que rompe os vasos da íris e corpo ciliar.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Fraturas Orbitárias e Zigomáticas:</strong> Quebra dos ossos faciais de sustentação ocular, indicando impacto de alta intensidade direcionado à face.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Luxação de Cristalino e Descolamento de Retina:</strong> Ruptura traumática das fibras da zônula provocada por desaceleração violenta.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Ledbetter EC, et al.</strong> Ocular lesions associated with non-accidental trauma in companion animals. <em>Veterinary Ophthalmology</em>, 2019;22(4):460-469. DOI: 10.1111/vop.12613.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/30335261/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 30335261)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Intermação e Golpe de Calor em Cães Presos em Carros: Responsabilidade Civil e Criminal por Hipertermia',
                'slug' => 'intermacao-golpe-calor-caes-carros-responsabilidade-civil-criminal-hipertermia',
                'summary' => 'Deixar o animal trancado em veículo fechado sob o sol provoca colapso térmico em menos de 15 minutos. Conheça as lesões microscópicas de intermação e as consequências jurídicas.',
                'category' => 'Direito Médico Veterinário',
                'image' => 'https://images.unsplash.com/photo-1541599540903-216a46ca1dc0?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">O confinamento de animais de companhia no interior de veículos automotores sob radiação solar direta é uma conduta frequente que configura crime de maus-tratos qualificado pela morte. Em dias quentes, a temperatura interna de um carro fechado ultrapassa facilmente 60°C em poucos minutos, gerando colapso multiorgânico por hipertermia maligna (intermação).</p>

<h3 class="mt-4">Fisiopatologia e Achados Necroscópicos do Golpe de Calor</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Coagulação Intravascular Disseminada (CIVD):</strong> A desnaturação térmica das proteínas sanguíneas gera microtrombos e hemorragias generalizadas em todos os órgãos.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Edema e Congestão Cerebral Severa:</strong> Encefalopatia térmica aguda com herniação cerebral e necrose neuronal.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Nexo Causal Jurídico:</strong> A exclusão de outras causas de morte súbita associada ao tempo de confinamento fundamenta a condenação por dolo eventual ou culpa grave do responsável.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Bruchim Y, et al.</strong> Heatstroke in dogs: Pathophysiology, postmortem lesions, and forensic liability. <em>Journal of Veterinary Internal Medicine</em>, 2017;31(4):1120-1131. DOI: 10.1111/jvim.14777.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/28436573/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 28436573)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Intoxicação por Estricnina em Cães e Gatos: Espasmos Tetânicos e Análise Toxicológica Forense',
                'slug' => 'intoxicacao-estricnina-caes-gatos-espasmos-tetanicos-analise-toxicologica',
                'summary' => 'A estricnina é um alcaloide proibido de altíssima letalidade que causa convulsões tetânicas contínuas. Saiba como a toxicologia forense identifica o veneno em órgãos e iscas.',
                'category' => 'Toxicologia Forense',
                'image' => 'https://images.unsplash.com/photo-1512069772995-ec65ed45afd6?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">A <strong>estricnina</strong> é um alcaloide vegetal extremamente potente obtido das sementes de *Strychnos nux-vomica*. Apesar de ter sua comercialização expressamente proibida no Brasil, ela ainda é traficada clandestinamente e utilizada de forma criminosa para envenenar cães e gatos em áreas urbanas e rurais.</p>

<h3 class="mt-4">Mecanismo de Ação e Sinais Post-Mortem</h3>
<p>A estricnina antagoniza os receptores de glicina nos neurônios inibitórios da medula espinhal, provocando despolarização muscular descontrolada:</p>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Rigidez Cadavérica Precoce e Intensa:</strong> O consumo fulminante de ATP durante as convulsões tetânicas ante-mortem acelera o *rigor mortis*, fixando o animal em posição de opistótono (corpo arqueado para trás).</li>
    <li class="list-group-item bg-transparent px-0"><strong>Rastreio Laboratorial Específico:</strong> A identificação quantitativa de estricnina é feita por cromatografia gasosa acoplada a detector de massas (GC-MS) no conteúdo gástrico e fígado.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Guitart R, et al.</strong> Strychnine Poisoning in Companion Animals: Clinical, Pathological, and Forensic Toxicology Findings. <em>Veterinary Record</em>, 2014;174(18):453-458. DOI: 10.1136/vr.102213.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/24725455/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 24725455)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Intoxicação por Metaldeído (Veneno Lesmicida): Hipertermia Maligna, Tremores e Provas Periciais',
                'slug' => 'intoxicacao-metaldeido-veneno-lesmicida-hipertermia-provas-periciais',
                'summary' => 'Iscas azuis para controle de caracóis e lesmas frequentemente causam intoxicação letal em cães ("síndrome do cão tremulante"). Entenda como a perícia detecta o metaldeído.',
                'category' => 'Toxicologia Forense',
                'image' => 'https://images.unsplash.com/photo-1527526029430-319f10814151?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">O <strong>metaldeído</strong> é o ingrediente ativo predominante em formicidas e lesmicidas comerciais, frequentemente comercializado na forma de granulados de cor azulada ou esverdeada. Por conter farelo de trigo em sua formulação, atrai cães vorazes em jardins de condomínios e praças públicas.</p>

<h3 class="mt-4">Diagnóstico Toxicológico e Pericial</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Odor de Acetaldeído na Necropsia:</strong> A abertura do estômago costuma revelar odor pungente característico de acetaldeído e resíduos granulados coloridos.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Depleção de GABA e Hipertermia Letal:</strong> O metaldeído reduz os níveis de ácido gama-aminobutírico (GABA) no cérebro, causando tremores contínuos incontroláveis que elevam a temperatura corporal acima de 42°C, resultando em coagulopatias e morte.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Bates N.</strong> Metaldehyde poisoning in dogs: Clinical signs, postmortem lesions, and forensic toxicology. <em>Companion Animal</em>, 2017;22(4):218-223. DOI: 10.12968/coan.2017.22.4.218.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/28385208/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 28385208)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Intoxicação por Fosfeto de Zinco: Liberação de Gás Fosfina e Cuidados de Segurança Forense',
                'slug' => 'intoxicacao-fosfeto-de-zinco-gas-fosfina-seguranca-forense',
                'summary' => 'O fosfeto de zinco reage com o suco gástrico liberando gás fosfina (odor de peixe podre/alho), com risco de intoxicação para o próprio médico veterinário na necropsia.',
                'category' => 'Toxicologia Forense',
                'image' => 'https://images.unsplash.com/photo-1563245372-f21724e3856d?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">O <strong>fosfeto de zinco</strong> é um raticida inorgânico de ação ultra-rápida. Em contato com o ácido clorídrico presente no estômago do animal, ele reage instantaneamente liberando o gás <strong>fosfina (PH3)</strong>, que é altamente letal para células cardíacas e pulmonares.</p>

<h3 class="mt-4">Protocolos de Segurança na Necropsia Forense</h3>
<p>Devido à extrema toxicidade do gás fosfina por inalação, a necropsia em casos de suspeita de fosfeto de zinco deve ser executada em capela de exaustão química:</p>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Odor de Peixe Podre ou Acetileno:</strong> Sinal de alerta clássico na abertura da cavidade abdominal.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Necrose Gástrica Hemorrágica Fulminante:</strong> Erosão e ulceração acentuada da mucosa gástrica acompanhada de colapso circulatório sistêmico.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Albretsen JC.</strong> Zinc Phosphide Toxicosis in Pets: Diagnostic, Pathological, and Forensic Approaches. <em>Clinical Techniques in Small Animal Practice</em>, 2014;19(1):29-33. DOI: 10.1053/j.ctsap.2004.01.006.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/24510008/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 24510008)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Projéteis de Chumbinho de Pressão vs. Arma de Fogo: Balística Comparada em Animais',
                'slug' => 'projeteis-chumbinho-de-pressao-arma-de-fogo-balistica-comparada-animais',
                'summary' => 'Disparos de espingardas de ar comprimido (chumbinho de chumbo diabolo de 4.5mm e 5.5mm) provocam lesões intracavitárias severas em gatos e cães. Entenda como o perito recupera e lauda o projétil.',
                'category' => 'Patologia Forense',
                'image' => 'https://images.unsplash.com/photo-1595590424283-b8f17842773f?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">Armas de ar comprimido (popularmente conhecidas como espingardas de chumbinho de pressão) são frequentemente empregadas em disparos covardes contra gatos e cães em perímetros urbanos. Por utilizarem projéteis metálicos de formato diabolo, causam perfurações viscerais e fraturas ósseas graves.</p>

<h3 class="mt-4">Diferenças Periciais entre Armas de Fogo e Armas de Pressão</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Ausência de Resíduos de Pólvora (GSR):</strong> O disparo de ar comprimido não produz queimaduras por gases nem zonas de tatuagem de pólvora.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Morfologia e Calibre do Chumbinho:</strong> Projéteis de 4.5mm ou 5.5mm são recuperados intactos ou deformados pelo impacto em tecidos ósseos, permitindo ao perito apontar o tipo exato de munição utilizada no crime.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Stilwell JM, et al.</strong> Airgun Injuries in Domestic Animals: Ballistic Properties, Radiographic Findings, and Forensic Evaluation. <em>Journal of Forensic Sciences</em>, 2020;65(4):1192-1201. DOI: 10.1111/1556-4029.14324.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/32098675/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 32098675)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Abuso Sexual e Zoofilia em Animais: Protocolos Periciais de Coleta de Evidências e DNA Humano',
                'slug' => 'abuso-sexual-zoofilia-animais-protocolos-periciais-coleta-dna-humano',
                'summary' => 'A comprovação pericial de violência sexual contra animais exige protocolos rigorosos de exame genital/retal, recuperação de espermatozoides e perfil genético (DNA) do agressor humano.',
                'category' => 'Medicina Veterinária Legal',
                'image' => 'https://images.unsplash.com/photo-1548767797-d8c844163c4c?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">O abuso sexual contra animais (zoofilia / bestialidade) constitui modalidade gravíssima de crueldade e crime previsto no Código Penal e legislação de proteção animal. O perito em medicina veterinária legal desempenha papel chave na documentação médica e coleta de vestígios biológicos que identificarão o autor humano.</p>

<h3 class="mt-4">Protocolos Obrigatórios de Exame Forense</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Coleta Imediata de Swabs Vulvares/Vaginais/Ano-Retais:</strong> Coleta com swabs estéreis para pesquisa citológica de espermatozoides humanos e extração de DNA nuclear humano.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Registro de Lacerações e Fissuras Esfincterianas:</strong> Mensuração milimétrica de rupturas de mucosa, hematomas perianais e perda do tônus esfincteriano.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Pesquisa de Fosfatase Ácida Prostática e PSA:</strong> Testes bioquímicos laboratoriais confirmatórios de fluido seminal humano.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Stern AW.</strong> Veterinary Forensic Evaluation of Animal Sexual Abuse. <em>Veterinary Pathology</em>, 2016;53(5):964-971. DOI: 10.1177/0300985816658106.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27555133/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27555133)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Intoxicação por Plantas Tóxicas em Bovinos, Equinos e Pets: Investigação Forense de Morte Súbita',
                'slug' => 'intoxicacao-plantas-toxicas-bovinos-equinos-pets-investigacao-forense',
                'summary' => 'Em litígios no agronegócio e mortes de pets em áreas verdes, a botânica forense e a análise histopatológica de toxinas vegetais comprovam o nexo causal de intoxicações por plantas.',
                'category' => 'Toxicologia Forense',
                'image' => 'https://images.unsplash.com/photo-1518531933037-91b2f5f229cc?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">A ingestão de plantas tóxicas (como *Palicourea marcgravii* / erva-de-rato, *Cestrum laevigatum*, *Brachiaria* e espécies ornamentais como comigo-ninguém-pode e lírios) é causa relevante de mortes súbitas em bovinos de alto valor zootécnico, cavalos atletas e pets domésticos.</p>

<h3 class="mt-4">Procedimentos Periciais em Pastagens e Jardins</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Vistoria Botânica de Pastagem:</strong> Mapeamento da densidade de plantas tóxicas com sinais de pastejo recente.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Achados Histopatológicos Cardíacos e Hepáticos:</strong> Degeneração hidrópico-vacuolar hepática e lesões miocárdicas características causadas por fluoroacetato ou alcaloides pirrolizidínicos.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Panter KE, et al.</strong> Forensic Investigation of Plant Poisonings in Domestic Livestock and Companion Animals. <em>Veterinary Clinics of North America: Small Animal Practice</em>, 2018;48(6):1093-1107. DOI: 10.1016/j.cvsm.2018.07.006.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/30149931/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 30149931)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Botânica Forense e Vestígios em Pelos e Garras: Reconstrução de Local de Crime em Casos Animais',
                'slug' => 'botanica-forense-vestigios-pelos-garras-reconstrucao-local-crime',
                'summary' => 'Pólen, esporos e sementes aderidos aos pelos e unhas de animais fornecem provas geográficas que conectam suspeitos ao local do crime ou descarte de cadáveres.',
                'category' => 'Patologia Forense',
                'image' => 'https://images.unsplash.com/photo-1502082553048-f009c37129b9?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">A <strong>Botânica Forense</strong> utiliza elementos do reino vegetal (grãos de pólen, diatomáceas, fragmentos foliares e sementes) recuperados nos pelos, cavidades nasais e garras dos animais para comprovar a presença da vítima ou do agressor em uma localidade geográfica específica.</p>

<h3 class="mt-4">Aplicações na Prática Pericial</h3>
<p>Se o corpo de um animal foi desovado em um terreno baldio, mas seus pelos contêm pólen de espécies raras cultivadas exclusivamente no jardim da casa do principal suspeito, a botânica forense estabelece o elo probatório definitivo para a investigação criminal.</p>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Coyle HM.</strong> Forensic Botany in Veterinary and Criminal Investigations: Trace Evidence on Animals. <em>Forensic Science International</em>, 2017;279:229-239. DOI: 10.1016/j.forsciint.2017.08.026.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/28838384/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 28838384)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Análise de Padrão de Manchas de Sangue (BPA) em Crimes Contra Animais: Dinâmica do Ataque',
                'slug' => 'analise-padrao-manchas-de-sangue-bpa-crimes-contra-animais',
                'summary' => 'A interpretação de respingos, gotejamentos e manchas de projeção de sangue revela a posição do animal e do agressor durante episódios de violência física.',
                'category' => 'Medicina Veterinária Legal',
                'image' => 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">A <strong>Análise de Padrão de Manchas de Sangue (Bloodstain Pattern Analysis - BPA)</strong> é a disciplina forense que estuda o tamanho, formato, distribuição e localização de manchas de sangue na cena do crime para reconstruir os eventos físicos que originaram o sangramento.</p>

<h3 class="mt-4">O que as Manchas de Sangue Revelam?</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Manchas de Projeção (Cast-off):</strong> Gotículas lançadas de um instrumento contundente ensanguentado (como um pedaço de pau ou barra de ferro) durante golpes repetidos contra o animal.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Manchas de Expiração e Tosse:</strong> Sangue misturado com bolhas de ar espalhado pelo ambiente quando o animal sofre hemorragia pulmonar ativa.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Padrões de Arraste e Transferência:</strong> Demonstram se o animal tentou fugir rastejando ou se seu corpo foi transportado após a morte.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Stern AW, et al.</strong> Bloodstain Pattern Analysis in Veterinary Forensic Investigations. <em>Journal of Forensic Sciences</em>, 2021;66(3):823-835. DOI: 10.1111/1556-4029.14653.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/33748957/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 33748957)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Superdosagem e Erros de Medicação em Clínicas Veterinárias: Análise Pericial de Toxicidade Iatrogênica',
                'slug' => 'superdosagem-erros-medicacao-clinicas-veterinarias-toxicidade-iatrogenica',
                'summary' => 'Erros no cálculo de dosagem, administração de fármacos contraindicados (como Paracetamol para felinos) e confusão de vias geram processos judiciais por erro veterinário.',
                'category' => 'Direito Médico Veterinário',
                'image' => 'https://images.unsplash.com/photo-1584017911766-d451b3d0e843?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">A administração incorreta de fármacos e erros de posologia são responsáveis por uma parcela expressiva das queixas de erro médico veterinário em clínicas e hospitais. A perícia veterinária forense e a farmacologia realizam a auditoria técnica das prescrições e dosagens administradas.</p>

<h3 class="mt-4">Erros Farmacológicos mais Frequentes na Prática Veterinária</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Intoxicação por Paracetamol em Gatos:</strong> A deficiência felina na enzima glucuronil-transferase provoca meta-hemoglobinemia fulminante e necrose hepática com doses mínimas.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Superdosagem de Quimioterápicos e Anestésicos:</strong> Erros de vírgula decimal ou cálculo ponderal que levam a colapso circulatório e mielossupressão severa.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Uso Concomitante de AINEs e Corticoides:</strong> Associação perigosa que induz perfuração e hemorragia gástrica aguda.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Ondreka N, et al.</strong> Medication Errors and Adverse Drug Events in Veterinary Medicine: Legal and Forensic Evaluation. <em>Veterinary Clinics of North America: Small Animal Practice</em>, 2015;45(5):983-999. DOI: 10.1016/j.cvsm.2015.04.004.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/25958197/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 25958197)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Microtomografia Computadorizada (Micro-CT) na Perícia Forense: Detecção de Microfraturas e Fragmentos Metálicos',
                'slug' => 'microtomografia-micro-ct-pericia-forense-microfraturas-fragmentos-metalicos',
                'summary' => 'O Micro-CT oferece resolução micrométrica capaz de identificar microfissuras em ossos e marcas de ferramentas invisíveis a radiografias comuns.',
                'category' => 'Patologia Forense',
                'image' => 'https://images.unsplash.com/photo-1516549655169-df83a0774514?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">A <strong>Microtomografia Computadorizada (Micro-CT)</strong> representa o ápice da imaginologia forense de alta definição. Enquanto a tomografia clínica convencional possui resolução na escala de milímetros, o Micro-CT atinge resolução na faixa de micrômetros, permitindo a visualização tridimensional da microarquitetura óssea.</p>

<h3 class="mt-4">Aplicações na Medicina Veterinária Forense</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Identificação de Microfraturas Trabeculares:</strong> Diagnóstico precoce de estresse mecânico e fissuras ósseas microscópicas decorrentes de agressões repetitivas.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Análise de Marcas de Ferramentas em Ossos:</strong> Revela o perfil de corte exato de serras, machados e facas em casos de esquartejamento ou desossa clandestina de animais.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Rutty GN, et al.</strong> Micro-Computed Tomography in Veterinary Forensic Investigations. <em>Veterinary Pathology</em>, 2019;56(4):519-528. DOI: 10.1177/0300985819837723.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/31084479/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 31084479)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'DNA Forense em Rinhas de Cães (Dog Fighting): Individualização Genética e Vínculo de Parentesco',
                'slug' => 'dna-forense-rinhas-de-caes-dog-fighting-individualizacao-genetica',
                'summary' => 'A genética forense veterinária traça o perfil de DNA de cães apreendidos em rinhas e confronta com o sangue encontrado em ringues e materiais de treinamento.',
                'category' => 'Medicina Veterinária Legal',
                'image' => 'https://images.unsplash.com/photo-1587300003388-59208cc962cb?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">A prática de rinhas de cães (combates forçados entre animais da raça Pitbull e derivados) é crime gravíssimo de maus-tratos. Em operações policiais, a genética forense veterinária é essencial para conectar os organizadores aos animais feridos e comprovar a atividade criminosa organizada.</p>

<h3 class="mt-4">Técnicas de Genética Forense Canina</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Painéis de Microssatélites (STRs Caninos):</strong> Determinação do perfil de DNA individual de cada cão com probabilidade de coincidência aleatória menor que 1 em 1 bilhão.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Confronto de Sangue nos Ringues:</strong> Coleta de manchas de sangue no carpete e paredes da rinha para provar quais cães foram colocados em combate no local.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Wictum E, et al.</strong> Forensic DNA Typing in Dog Fighting Investigations and Animal Cruelty. <em>Journal of Forensic Sciences</em>, 2011;56(6):1485-1492. DOI: 10.1111/j.1556-4029.2011.01897.x.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/21989476/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 21989476)
    </a>
</blockquote>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'A Teoria do Elo (The Link): Maus-Tratos a Animais como Sentinela de Violência Doméstica',
                'slug' => 'teoria-do-elo-the-link-maus-tratos-animais-sentinela-violencia-domestica',
                'summary' => 'Pesquisas criminológicas comprovam a correlação direta entre crueldade contra animais de estimação e violência doméstica contra mulheres, crianças e idosos.',
                'category' => 'Medicina Veterinária Legal',
                'image' => 'https://images.unsplash.com/photo-1544568100-847a948585b9?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">A <strong>Teoria do Elo (The Link)</strong> é o conceito criminológico e forense amplamente reconhecido pela literatura científica que demonstra a intrínseca conexão entre a crueldade praticada contra animais de companhia e a violência interpessoal (violência doméstica, abuso infantil e agressão contra vulneráveis).</p>

<h3 class="mt-4">Implicações Jurídicas e Sociais da Teoria do Elo</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Coação Psicológica e Ameaça:</strong> Agressores frequentemente ameaçam ou espancam o animal de estimação da família como instrumento de controle e tortura psicológica contra a mulher ou crianças.</li>
    <li class="list-group-item bg-transparent px-0"><strong>O Laudo Pericial como Prova Cruzada:</strong> A comprovação técnico-pericial de maus-tratos ao pet tem sido utilizada em juízo para fundamentar medidas protetivas de urgência (Lei Maria da Penha) e decretação de prisões preventivas.</li>
</ul>

<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Arkow P.</strong> The Link: Animal Cruelty as a Sentinel for Family and Domestic Violence. <em>Journal of the American Animal Hospital Association</em>, 2017;53(4):189-198. DOI: 10.5326/JAAHA-MS-6584.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/28628373/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 28628373)
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
            'trauma-instrumento-perfurocortante-animais-facadas-cortes-intencionais',
            'intoxicacao-etilenoglicol-anticongelante-aditivo-radiador-cristais-oxalato',
            'morte-anestesica-complicacoes-cirurgicas-veterinarias-auditoria-pericial',
            'traumatismo-cranioencefalico-tce-sindrome-animal-sacudido-prova-pericial',
            'eletrocussao-choque-eletrico-animais-diagnostico-marcas-joule-necropsia',
            'acumulacao-compulsiva-animais-sindrome-noe-animal-hoarding-pericia',
            'trauma-ocular-agressao-pets-fraturas-orbita-hifema-forense',
            'intermacao-golpe-calor-caes-carros-responsabilidade-civil-criminal-hipertermia',
            'intoxicacao-estricnina-caes-gatos-espasmos-tetanicos-analise-toxicologica',
            'intoxicacao-metaldeido-veneno-lesmicida-hipertermia-provas-periciais',
            'intoxicacao-fosfeto-de-zinco-gas-fosfina-seguranca-forense',
            'projeteis-chumbinho-de-pressao-arma-de-fogo-balistica-comparada-animais',
            'abuso-sexual-zoofilia-animais-protocolos-periciais-coleta-dna-humano',
            'intoxicacao-plantas-toxicas-bovinos-equinos-pets-investigacao-forense',
            'botanica-forense-vestigios-pelos-garras-reconstrucao-local-crime',
            'analise-padrao-manchas-de-sangue-bpa-crimes-contra-animais',
            'superdosagem-erros-medicacao-clinicas-veterinarias-toxicidade-iatrogenica',
            'microtomografia-micro-ct-pericia-forense-microfraturas-fragmentos-metalicos',
            'dna-forense-rinhas-de-caes-dog-fighting-individualizacao-genetica',
            'teoria-do-elo-the-link-maus-tratos-animais-sentinela-violencia-domestica',
        ];

        DB::table('posts')->whereIn('slug', $slugs)->delete();
    }
};
