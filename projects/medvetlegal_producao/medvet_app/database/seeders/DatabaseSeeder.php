<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Post;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Avoid duplicate seeding errors if run multiple times (Admin User 1)
        $user = User::where('email', 'ascalefi@gmail.com')->first();
        if (!$user) {
            $user = User::create([
                'name' => 'Atilio Sersun Calefi',
                'email' => 'ascalefi@gmail.com',
                'password' => bcrypt('041188As'),
                'is_admin' => true,
            ]);
        } else {
            $user->update(['is_admin' => true]);
        }

        // Avoid duplicate seeding errors if run multiple times (Admin User 2)
        $user2 = User::where('email', 'contato@medvetlegal.com.br')->first();
        if (!$user2) {
            User::create([
                'name' => 'MedVetLegal Admin',
                'email' => 'contato@medvetlegal.com.br',
                'password' => bcrypt('041188As'),
                'is_admin' => true,
            ]);
        } else {
            $user2->update(['is_admin' => true]);
        }

        // Avoid duplicate seeding errors if run multiple times (Admin User 3)
        $user3 = User::where('email', 'patoforense.adriana@gmail.com')->first();
        if (!$user3) {
            User::create([
                'name' => 'Dra. Adriana Siqueira',
                'email' => 'patoforense.adriana@gmail.com',
                'password' => bcrypt('041188As'),
                'is_admin' => true,
            ]);
        } else {
            $user3->update(['is_admin' => true]);
        }

        // Seed initial article
        Post::updateOrCreate(
            ['slug' => 'como-comprovar-intoxicacao-por-chumbinho-carbamatos-processos-judiciais'],
            [
                'title' => 'Como Comprovar Intoxicação por Chumbinho (Carbamatos e Aldicarbe) em Processos Judiciais?',
                'summary' => 'A comprovação de intoxicação por chumbinho (carbamatos/aldicarbe) exige laudo pericial toxicológico com rigorosa cadeia de custódia. Veja o passo a passo científico.',
                'category' => 'Toxicologia Forense',
                'image' => 'https://images.unsplash.com/photo-1532187863486-abf9d39d66e8?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">O chamado "chumbinho" é o termo popular utilizado no Brasil para designar agrotóxicos ilegais desviados da agricultura, predominantemente o <strong>aldicarbe</strong> e outros carbamatos inibidores da acetilcolinesterase. Comercializado clandestinamente na forma de grânulos cinza ou pretos semelhantes a pó de grafite, é o agente mais frequente em envenenamentos intencionais de cães e gatos.</p>

<h3 class="mt-4">Fisiopatologia e Sinais Clínico-Patológicos Patognomônicos</h3>
<p>Os carbamatos bloqueiam reversivelmente a enzima acetilcolinesterase na fenda sináptica, desencadeando a chamada <strong>Síndrome Colinérgica Aguda</strong> (conhecida pelo mnemônico SLUDGE):</p>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Salivação excessiva (Sialorreia) e Miose puntiforme:</strong> Pupilas extremamente contraídas e secreção salivar abundante.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Broncorreia e Edema Pulmonar Fulminante:</strong> Acúmulo maciço de secreção líquida nos brônquios, provocando asfixia por sufocamento interno.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Fasciculações musculares e convulsões:</strong> Espasmos musculares contínuos seguidos de colapso respiratório.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>de Siqueira A et al.</strong> Poisoning of cats and dogs by the carbamate pesticides aldicarb and carbofuran. <em>Res Vet Sci</em>, 2015;102():142-9. DOI: 10.1016/j.rvsc.2015.08.006.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/26412534/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 26412534)
    </a>
</blockquote>',
                'published_at' => now(),
            ]
        );

        // Seed second article (Orientation on expert terms)
        Post::updateOrCreate(
            ['slug' => 'diferenca-pericia-assistencia-tecnica-laudo-veterinario'],
            [
                'title' => 'Perícia, Assistência Técnica e Laudo Veterinário: Entenda as Diferenças Processuais',
                'summary' => 'Compreenda o papel do perito judicial, do assistente técnico e do parecer pericial veterinário em disputas judiciais de responsabilidade civil e crimes contra animais.',
                'category' => 'Assistência Técnica',
                'image' => 'https://images.unsplash.com/photo-1453728013993-6d66e9c9123a?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">Em litígios que envolvem responsabilidade médica veterinária, litígios no agronegócio ou apuração de crimes contra a fauna, a correta atuação técnica veterinária é determinante para a procedência ou improcedência da demanda judicial.</p>

<h3 class="mt-4">O Perito Judicial Veterinário vs. O Assistente Técnico</h3>
<p>O <strong>Perito Judicial</strong> é um auxiliar da Justiça nomeado pelo juiz da causa para realizar o exame pericial imparcial e elaborar o Laudo Pericial. Já o <strong>Assistente Técnico Veterinário</strong> é contratado pela parte (autor ou réu) para atuar como consultor técnico-científico do advogado durante toda a instrução processual.</p>

<h3 class="mt-4">Vantagens Estratégicas da Assistência Técnica Veterinária</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0">Elaboração de quesitos periciais que obrigam o perito do juiz a esclarecer pontos críticos de literatura médica.</li>
    <li class="list-group-item bg-transparent px-0">Fiscalização do cumprimento de normas técnicas e preservação da cadeia de custódia de amostras biológicas.</li>
    <li class="list-group-item bg-transparent px-0">Elaboração de Parecer Técnico fundamentado com citações científicas e jurisprudenciais.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Frederickson R</strong> Demystifying the Courtroom: Everything the Veterinary Pathologist Needs to Know About Testifying in an Animal Cruelty Case. <em>Vet Pathol</em>, 2016;53(5):888-93. DOI: 10.1177/0300985816647439.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27185534/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27185534)
    </a>
</blockquote>',
                'published_at' => now(),
            ]
        );

        // Seed SEO-optimized articles
        $seoPosts = [
            [
                'title' => 'Erro Veterinário: Como Provar Negligência, Imprudência ou Imperícia em Processos Judiciais?',
                'slug' => 'erro-veterinario-como-provar-negligencia-imprudencia-impericia',
                'summary' => 'Identificar um erro em procedimento clínico ou cirúrgico exige análise técnica criteriosa. Saiba como estruturar a prova de erro veterinário sob a ótica da negligência, imprudência ou imperícia.',
                'category' => 'Direito Médico Veterinário',
                'image' => 'https://images.unsplash.com/photo-1584132967334-10e028bd69f7?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
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
<p>O prontuário médico-veterinário é um documento obrigatório e constitui o elemento central de prova. A auditoria minuciosa das folhas de evolução clínica, boletins de anestesia e termos de consentimento permite ao assistente técnico veterinário identificar falhas no nexo de causalidade ou comprovar a lisura das condutas adotadas.</p>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Souza CNA et al.</strong> Quantitative and qualitative analysis of lawsuits against veterinarians and correlation of potential risk factors with court decisions. <em>Forensic Sci Int</em>, 2020;310():110233. DOI: 10.1016/j.forsciint.2020.110233.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/32193129/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 32193129)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Necropsia Veterinária Forense: Quando é Necessária e Como Solicitar?',
                'slug' => 'necropsia-veterinaria-forense-quando-e-necessaria-como-solicitar',
                'summary' => 'A necropsia forense veterinária é o principal instrumento de elucidação da causa da morte em casos judiciais. Entenda os procedimentos e sua importância para o nexo causal.',
                'category' => 'Patologia Forense',
                'image' => 'https://images.unsplash.com/photo-1518152006812-edab29b069ac?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
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
</ol>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Brownlie HW et al.</strong> The Veterinary Forensic Necropsy: A Review of Procedures and Protocols. <em>Vet Pathol</em>, 2016;53(5):919-28. DOI: 10.1177/0300985816655851.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27371536/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27371536)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Intoxicação de Animais: O que Fazer e Como Agir Juridicamente?',
                'slug' => 'envenenamento-de-animais-o-que-fazer-como-agir-juridicamente',
                'summary' => 'A suspeita de intoxicação de pets é frequente e dolorosa. Conheça as providências jurídicas necessárias e como coletar evidências científicas cruciais para punir os responsáveis.',
                'category' => 'Toxicologia Forense',
                'image' => 'https://images.unsplash.com/photo-1601758228041-f3b2795255f1?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">A intoxicação dolosa de cães e gatos é crime previsto no Art. 32 da Lei de Crimes Ambientais (Lei nº 9.605/98), com pena de reclusão de 2 a 5 anos, além de multa e perda da guarda. Infelizmente, a maioria dos inquéritos policiais por envenenamento acaba sendo arquivada por falta de laudos laboratoriais e quebra da cadeia de custódia.</p>

<h3 class="mt-4">Passo a Passo Imediato em Casos de Suspeita</h3>
<ol class="my-3">
    <li class="mb-2"><strong>Socorro Veterinário Imediato:</strong> A prioridade máxima é a estabilização da vida do animal. Solicite ao veterinário assistente o registro minucioso dos sinais clínicos em prontuário.</li>
    <li class="mb-2"><strong>Preservação Rígida de Alimentos Suspeitos e Iscas:</strong> Se encontrar pedaços de carne, sachês ou granulados estranhos (como o "chumbinho"), recolha-os com luvas e acondicione-os em sacos plásticos herméticos e limpos.</li>
    <li class="mb-2"><strong>Encaminhamento para Necropsia e Painel Toxicológico:</strong> Havendo óbito, o corpo deve passar por necropsia forense para coleta de conteúdo gástrico, fígado e rins para cromatografia e espectrometria (GC-MS / LC-MS).</li>
</ol>

<h3 class="mt-4">Ação Penal e Reparação Civil por Danos Morais</h3>
<p>Com o laudo necroscópico e o laudo toxicológico confirmatórios em mãos, é possível instaurar inquérito policial na Delegacia de Proteção Animal e propor ação de indenização por danos materiais e morais contra o agressor.</p>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Gwaltney-Brant SM</strong> Veterinary Forensic Toxicology. <em>Vet Pathol</em>, 2016;53(5):1067-77. DOI: 10.1177/0300985816641994.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27090769/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27090769)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Laudo Pericial vs. Parecer Técnico Veterinário: Entenda as Diferenças no Processo Civil',
                'slug' => 'laudo-pericial-vs-parecer-tecnico-entenda-a-diferenca-processual',
                'summary' => 'Em processos de erro veterinário ou maus-tratos, o laudo pericial do perito do juiz e o parecer técnico do assistente possuem funções distintas. Saiba como utilizá-los a seu favor.',
                'category' => 'Assistência Técnica',
                'image' => 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">Em disputas judiciais envolvendo erro médico veterinário, intoxicações criminosas ou litígios no agronegócio, os termos <strong>Laudo Pericial</strong> e <strong>Parecer Técnico</strong> são frequentemente confundidos. No entanto, suas funções processuais previstas no Código de Processo Civil (CPC) são completamente distintas e complementares.</p>

<h3 class="mt-4">1. Laudo Pericial (Elaborado pelo Perito do Juiz)</h3>
<p>O Laudo Pericial é a peça técnica produzida pelo <strong>Perito Judicial</strong>, profissional de confiança nomeado pelo magistrado. Sua função é atuar com total neutralidade e equidistância das partes, respondendo aos quesitos formulados e esclarecendo as questões técnicas que fundamentarão a sentença do juiz.</p>

<h3 class="mt-4">2. Parecer Técnico (Elaborado pelo Assistente Técnico Veterinário)</h3>
<p>O Parecer Técnico é o documento confeccionado pelo <strong>Assistente Técnico Veterinário</strong>, contratado diretamente por uma das partes litigantes (tutor do animal ou clínica veterinária ré). O assistente atua como o defensor técnico da parte, garantindo o princípio constitucional do contraditório e da ampla defesa:</p>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0">Elabora quesitos estratégicos que direcionam a perícia judicial.</li>
    <li class="list-group-item bg-transparent px-0">Acompanha diligências e exames periciais in loco.</li>
    <li class="list-group-item bg-transparent px-0">Impugna e contesta cientificamente eventuais equívocos metodológicos do laudo do perito judicial.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>McDonough SP et al.</strong> Veterinary Forensic Pathology: The Search for Truth. <em>Vet Pathol</em>, 2016;53(5):875-7. DOI: 10.1177/0300985816647450.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27515387/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27515387)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Maus-Tratos a Animais: Como Provar Perante a Lei com Rigor Pericial?',
                'slug' => 'maus-tratos-a-animais-como-provar-perante-a-lei',
                'summary' => 'A comprovação de maus-tratos a animais requer laudos que comprovem sofrimento físico ou psicológico. Entenda o papel da perícia veterinária nesses casos.',
                'category' => 'Medicina Veterinária Legal',
                'image' => 'https://images.unsplash.com/photo-1544568100-847a948585b9?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">Com a entrada em vigor da Lei Sansão (Lei nº 14.064/20), a pena para maus-tratos cometidos contra cães e gatos aumentou para 2 a 5 anos de reclusão. Entretanto, para sustentar uma condenação criminal ou a perda definitiva da guarda, o Judiciário exige <strong>prova pericial robusta da materialidade</strong> do dano.</p>

<h3 class="mt-4">Como a Perícia Avalia o Bem-Estar e o Sofrimento Animal</h3>
<p>O médico veterinário legal avalia o animal sob a ótica das Cinco Liberdades e de parâmetros clínicos objetivos:</p>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Liberdade Nutricional:</strong> Aferição do Escore de Condição Corporal (ECC) de 1 a 9, documentando caquexia ou desidratação severa.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Liberdade Sanitária:</strong> Presença de ectoparasitos maciços (miíases, pulgas, carrapatos) e feridas crônicas não tratadas.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Liberdade Ambiental:</strong> Confinamento em espaços reduzidos, sem abrigo contra sol e chuva, em meio a dejetos acumulados.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Liberdade Psicológica:</strong> Comportamentos de medo exacerbado, apatia profunda ou agressividade defensiva extrema.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>de Siqueira A et al.</strong> Non-accidental injuries found in necropsies of domestic cats: a review of 191 cases. <em>J Feline Med Surg</em>, 2012;14(10):723-8. DOI: 10.1177/1098612X12451374.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/22694958/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 22694958)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Exumação de Animais: É Possível Realizar Perícia Médico-Legal Após o Sepultamento?',
                'slug' => 'exumacao-de-animais-e-possivel-realizar-pericia-apos-o-enterro',
                'summary' => 'A exumação pet é um procedimento técnico que visa recuperar vestígios após o sepultamento. Saiba como a ciência forense identifica intoxicações e lesões tardias.',
                'category' => 'Patologia Forense',
                'image' => 'https://images.unsplash.com/photo-1448375240586-882707db888b?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">Quando um animal de companhia é sepultado sob suspeita de óbito criminoso por veneno, espancamento ou erro médico, muitos tutores acreditam que a oportunidade de produzir provas materiais se perdeu. A <strong>exumação médico-legal veterinária</strong> prova exatamente o oposto.</p>

<h3 class="mt-4">O que a Perícia Pode Recuperar e Comprovar na Exumação?</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Detecção Toxicológica Tardia:</strong> Praguicidas altamente estáveis, como o aldicarbe (chumbinho), estricnina e metais pesados, permanecem detectáveis no solo adjacente, pelos e resíduos orgânicos preservados mesmo após meses de sepultamento.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Osteopatologia Forense:</strong> O esqueleto preserva fraturas, fissuras ósseas e perfurações balísticas de forma indelével.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Cadeia de Custódia e Amostras de Controle:</strong> A exumação exige coleta de amostras testemunhas do solo acima, ao lado e abaixo do cadáver para afastar qualquer alegação de contaminação pré-existente do terreno.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>de Siqueira A et al.</strong> Exhumation of Wistar rats experimentally exposed to the carbamate pesticides aldicarb and carbofuran: A pathological and toxicological study. <em>Exp Toxicol Pathol</em>, 2016;68(6):307-14. DOI: 10.1016/j.etp.2016.03.002.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27020045/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27020045)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Responsabilidade Civil de Clínicas e Hospitais Veterinários em Erros Profissionais',
                'slug' => 'responsabilidade-civil-de-clinicas-veterinarias-em-erros-profissionais',
                'summary' => 'Entenda como a justiça avalia a responsabilidade civil das clínicas e hospitais veterinários em casos de erro de procedimento de sua equipe.',
                'category' => 'Direito Médico Veterinário',
                'image' => 'https://images.unsplash.com/photo-1581888227599-779811939961?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">Diferente do médico veterinário autônomo, cuja responsabilidade depende da comprovação de culpa subjetiva, as <strong>clínicas e hospitais veterinários</strong> respondem, em regra, de forma <strong>objetiva</strong> perante o Código de Defesa do Consumidor (Art. 14 do CDC). Isso significa que o estabelecimento responde pelos danos causados por falha na prestação do serviço ou por atos de seus funcionários e prepostos, independentemente da demonstração de culpa da diretoria.</p>

<h3 class="mt-4">Dever de Informação e Termos de Consentimento (TCLE)</h3>
<p>Um dos pontos mais vulneráveis em litígios hospitalares veterinários é a ausência de informação clara ao tutor antes de procedimentos invasivos ou anestésicos. O Termo de Consentimento Livre e Esclarecido (TCLE) deve descrever em linguagem compreensível os riscos previsíveis, sob pena de responsabilização por vício de informação.</p>

<h3 class="mt-4">Gestão de Risco e Auditoria Preventiva</h3>
<p>Clínicas bem estruturadas utilizam auditorias periciais periódicas para padronizar fichas anestésicas, prontuários de internação e protocolos de medicação, blindando a instituição contra processos temerários.</p>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Ruiz RT et al.</strong> Characterization of cases and analysis of First-instance Judgments in Ethical Proceedings at the Regional Council of Veterinary Medicine of the State of São Paulo. <em>Braz J Vet Med</em>, 2025;47():e002725. DOI: 10.29374/2527-2179.bjvm002725.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/41064061/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 41064061)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Como Age a Toxicologia Forense na Identificação de Venenos em Pets (GC-MS / LC-MS)?',
                'slug' => 'como-age-a-toxicologia-forense-na-identificacao-de-venenos-em-pets',
                'summary' => 'A toxicologia forense identifica agentes químicos e venenos que causaram o óbito de animais. Conheça as técnicas de cromatografia e espectrometria aplicadas.',
                'category' => 'Toxicologia Forense',
                'image' => 'https://images.unsplash.com/photo-1576086213369-97a306d36557?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">Para provar em juízo que um animal foi vítima de intoxicação criminosa, a simples suspeita clínica ou laudo presuntivo não é suficiente. O tribunal exige a <strong>identificação química quantitativa e qualitativa</strong> inequívoca da molécula tóxica causadora do dano.</p>

<h3 class="mt-4">As Metodologias Analíticas de Ponta na Perícia</h3>
<p>Os laboratórios forenses empregam tecnologias analíticas de altíssima sensibilidade e especificidade:</p>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Cromatografia Gasosa Acoplada à Espectrometria de Massas (GC-MS):</strong> Padrão-ouro para identificação de compostos voláteis e semivoláteis, como carbamatos (chumbinho), organofosforados e estricnina.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Cromatografia Líquida de Alta Eficiência (LC-MS/MS):</strong> Utilizada para detectar raticidas anticoagulantes de segunda geração (brodifacoum, bromadiolona) e alcaloides vegetais em concentrações de partes por bilhão (ppb).</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Fukushima AR et al.</strong> Development, Optimization, and Validation of Forensic Analytical Method for Quantification of Anticholinesterase Pesticides in Biological Matrices from Suspected Cases of Animal Poisoning. <em>Toxics</em>, 2022;10(5):. DOI: 10.3390/toxics10050269.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/35622682/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 35622682)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Assistente Técnico Veterinário: O Defensor da Verdade Científica em Juízo',
                'slug' => 'assistente-tecnico-veterinario-o-defensor-da-verdade-cientifica-em-juizo',
                'summary' => 'Em litígios complexos, o assistente técnico veterinário traduz e orienta advogados sobre a viabilidade científica do caso. Saiba por que ele é indispensável.',
                'category' => 'Assistência Técnica',
                'image' => 'https://images.unsplash.com/photo-1521791136064-7986c2920216?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">A complexidade dos litígios envolvendo a medicina veterinária exige conhecimentos aprofundados de fisiopatologia, farmacologia e técnica cirúrgica que fogem à formação jurídica de advogados e juízes. É nesse contexto que o <strong>Assistente Técnico Veterinário</strong> se torna peça indispensável para o sucesso processual.</p>

<h3 class="mt-4">As Atribuições do Assistente Técnico Veterinário no Processo</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>1. Análise Prévia de Viabilidade Técnica:</strong> Avalia detalhadamente o prontuário e as evidências antes da distribuição da ação, orientando o advogado se há viabilidade científica real de êxito.</li>
    <li class="list-group-item bg-transparent px-0"><strong>2. Formulação de Quesitos Técnicos Estratégicos:</strong> Elabora perguntas técnicas minuciosas que o perito judicial é obrigado a responder no laudo oficial.</li>
    <li class="list-group-item bg-transparent px-0"><strong>3. Acompanhamento de Diligências Periciais:</strong> Participa presencialmente de necropsias forenses e vistorias em clínicas para fiscalizar o rigor do procedimento.</li>
    <li class="list-group-item bg-transparent px-0"><strong>4. Elaboração do Parecer Técnico de Impugnação:</strong> Aponta eventuais equívocos, contradições ou omissões científicas no laudo do perito do juízo.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Newbery SG et al.</strong> A Perspective on Veterinary Forensic Pathology and Medicine in the United Kingdom. <em>Vet Pathol</em>, 2016;53(5):894-7. DOI: 10.1177/0300985816654527.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27371542/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27371542)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Protocolos de Perícia Veterinária em Casos de Maus-Tratos: Valadez, FMEA e Diretrizes Internacionais',
                'slug' => 'protocolos-de-pericia-veterinaria-em-casos-de-maus-tratos',
                'summary' => 'A perícia de crueldade e maus-tratos deve seguir escalas validadas cientificamente para comprovar materialidade em juízo. Entenda os métodos periciais e como o laudo sustenta a acusação.',
                'category' => 'Medicina Veterinária Legal',
                'image' => 'https://images.unsplash.com/photo-1516733725897-1aa73b87c8e8?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
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
</ol>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Touroo R et al.</strong> Identification, Collection, and Preservation of Veterinary Forensic Evidence: On Scene and During the Postmortem Examination. <em>Vet Pathol</em>, 2016;53(5):880-7. DOI: 10.1177/0300985816641175.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27030370/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27030370)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Medicina Veterinária Legal: O que é, áreas de atuação e a importância da perícia',
                'slug' => 'o-que-e-medicina-veterinaria-legal-areas-atuacao-pericia',
                'summary' => 'A Medicina Veterinária Legal é a especialidade que une a ciência médico-veterinária ao Direito. Conheça as principais áreas de atuação, como necropsia forense, toxicologia, auditoria de erro veterinário e assistência técnica judicial.',
                'category' => 'Medicina Veterinária Legal',
                'image' => 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">A <strong>Medicina Veterinária Legal</strong> (também conhecida internacionalmente como <em>Veterinary Forensic Medicine</em>) é o ramo da medicina veterinária que aplica conhecimentos técnico-científicos para auxiliar a Justiça e solucionar questões jurídicas que envolvam animais.</p>
<p>Com a evolução legislativa brasileira (como a Lei Sansão - Lei nº 14.064/20), o aumento de litígios por suspeita de erro médico veterinário e a necessidade de comprovação científica em casos de intoxicações e disputas de guarda ou compra de animais, a atuação do perito em medicina veterinária legal tornou-se indispensável.</p>
<h3 class="mt-4">Principais Áreas de Atuação na Medicina Veterinária Legal</h3>
<p>O campo da perícia e medicina veterinária forense é multidisciplinar e abrange diversas ramificações:</p>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0">
        <strong>1. Patologia Forense e Necropsia:</strong> Exame cadavérico detalhado com cadeia de custódia para determinação inequívoca da <em>causa mortis</em> em mortes súbitas, violentas ou sob custódia clínica.
    </li>
    <li class="list-group-item bg-transparent px-0">
        <strong>2. Toxicologia Forense Veterinária:</strong> Identificação laboratorial e analítica de substâncias tóxicas, praguicidas (como carbamatos/"chumbinho\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Averaimo D et al.</strong> Retrospective classification of veterinary forensic cases from the archives of the Italian Istituti Zooprofilattici Sperimentali (2013-2023). <em>Front Vet Sci</em>, 2026;13():1856464. DOI: 10.3389/fvets.2026.1856464.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/42440463/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 42440463)
    </a>
</blockquote>", estricnina e raticidas) e reações medicamentosas adversas em animais vivos ou exumados.
    </li>
    <li class="list-group-item bg-transparent px-0">
        <strong>3. Auditoria de Responsabilidade Civil e Erro Veterinário:</strong> Avaliação crítica de prontuários clínicos, termos de consentimento, condutas terapêuticas e protocolos anestésico-cirúrgicos para apuração de nexo causal e eventuais falhas (negligência, imprudência ou imperícia).
    </li>
    <li class="list-group-item bg-transparent px-0">
        <strong>4. Averiguação de Maus-tratos e Crueldade:</strong> Aplicação de protocolos científicos validados para diagnosticar e mensurar sofrimento físico, nutricional, ambiental ou psicológico em cães, gatos e animais de grande porte.
    </li>
    <li class="list-group-item bg-transparent px-0">
        <strong>5. Assistência Técnica Judicial:</strong> Suporte especializado a advogados e partes do processo (autores ou réus), formulando quesitos estratégicos e emitindo pareceres técnicos fundamentados para assegurar o amplo direito de defesa e o contraditório científico.
    </li>
</ul>
<h3 class="mt-4">Perito do Juízo vs. Assistente Técnico Veterinário</h3>
<p>Na prática dos tribunais:</p>
<ul>
    <li>O <strong>Perito Judicial</strong> é o profissional nomeado pelo juiz para agir com neutralidade e elaborar o <strong>Laudo Pericial</strong> oficial do processo.</li>
    <li>O <strong>Assistente Técnico</strong> é contratado pela parte (tutor, clínica ou escritório de advocacia) para fiscalizar os procedimentos, participar dos exames periciais e produzir o <strong>Parecer Técnico</strong> fundamentado.</li>
</ul>
<h3 class="mt-4">Conclusão</h3>
<p>A precisão de um parecer em Medicina Veterinária Legal pode ser o diferencial definitivo para a decisão de um magistrado. Contar com peritos experientes, com formação acadêmica de excelência e publicações científicas na área, garante que a verdade dos fatos seja apresentada com rigor e segurança jurídica.</p>',
                'published_at' => now(),
            ],
            [
                'title' => 'Necropsia Forense Veterinária: Por que o Exame Convencional Não Serve para a Justiça?',
                'slug' => 'necropsia-forense-veterinaria-diferenca-exame-convencional-justica',
                'summary' => 'Entenda por que uma necropsia veterinária convencional pode ser facilmente anulada ou questionada em um processo judicial e saiba quais são as exigências legais da necropsia forense.',
                'category' => 'Patologia Forense',
                'image' => 'https://images.unsplash.com/photo-1579154204601-01588f351e67?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
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
<p>Se o animal for sepultado ou cremado sem um laudo pericial forense idôneo, a oportunidade de comprovar o nexo de causalidade pode ser irremediavelmente perdida. A intervenção imediata de um patologista forense veterinário é a maior garantia de segurança jurídica para o processo.</p>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Pollanen MS</strong> The Rise of Forensic Pathology in Human Medicine: Lessons for Veterinary Forensic Pathology. <em>Vet Pathol</em>, 2016;53(5):878-9. DOI: 10.1177/0300985816653171.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27281015/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27281015)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Como Determinar a Hora da Morte do Animal? O Intervalo Pós-Morte (PMI) na Perícia',
                'slug' => 'como-determinar-hora-morte-animal-intervalo-pos-morte-pmi-pericia',
                'summary' => 'Saiba como a medicina veterinária legal calcula o Intervalo Pós-Morte (PMI) através do resfriamento corporal, rigidez cadavérica e fauna cadavérica para validar álibis e desvendar crimes.',
                'category' => 'Patologia Forense',
                'image' => 'https://images.unsplash.com/photo-1509114397022-ed747cca3f65?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
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
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Brooks JW</strong> Postmortem Changes in Animal Carcasses and Estimation of the Postmortem Interval. <em>Vet Pathol</em>, 2016;53(5):929-40. DOI: 10.1177/0300985816629720.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/26945004/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 26945004)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Padrões Internacionais de Perícia em Patologia Veterinária Forense',
                'slug' => 'padroes-internacionais-pericia-patologia-veterinaria-forense',
                'summary' => 'Conheça os padrões e diretrizes científicas internacionais exigidos em perícias veterinárias para que pareceres e laudos técnicos tenham máxima credibilidade jurídica.',
                'category' => 'Medicina Veterinária Legal',
                'image' => 'https://images.unsplash.com/photo-1450133064473-71024230f91b?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">A medicina veterinária legal contemporânea exige uniformidade e padronização científica de nível internacional. Órgãos como a <em>International Veterinary Forensic Sciences Association (IVFSA)</em> e a <em>Society of Toxicologic Pathology</em> estabelecem guias de boas práticas para garantir que exames periciais cumpram os mais estritos requisitos de admissibilidade de provas judiciais.</p>

<h3 class="mt-4">Diretrizes Fundamentais para Peritos e Assistentes Técnicos</h3>
<p>Os padrões internacionais de perícia veterinária incluem:</p>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Metodologia Reprodutível:</strong> Todas as técnicas empregadas na necropsia e análise histopatológica devem ser descritas em detalhes e baseadas em literatura científica revisada por pares.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Neutralidade e Imparcialidade Técnica:</strong> O perito deve limitar suas conclusões estritamente aos fatos materiais e científicos observados, abstendo-se de juízos morais ou suposições sem evidência.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Controle de Qualidade Laboratorial:</strong> Os exames laboratoriais (toxicológicos, microbiológicos e genéticos) devem ser executados em laboratórios credenciados com controles de calibração certificados.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>McEwen BJ et al.</strong> A Survey of Attitudes of Board-Certified Veterinary Pathologists to Forensic Veterinary Pathology. <em>Vet Pathol</em>, 2016;53(5):1099-102. DOI: 10.1177/0300985815625756.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/26926083/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 26926083)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Fratura Acidental ou Agressão? Como a Perícia Diferencia Maus-Tratos Físicos em Cães e Gatos',
                'slug' => 'fratura-acidental-ou-agressao-como-pericia-diferencia-maus-tratos-caes-gatos',
                'summary' => 'Estudo científico detalha os padrões biomecânicos e radiográficos de fraturas que permitem ao perito distinguir traumas acidentais (atropelamento, quedas) de agressões intencionais.',
                'category' => 'Medicina Veterinária Legal',
                'image' => 'https://images.unsplash.com/photo-1530497610245-94d3c16cda28?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">Em muitos casos de maus-tratos a animais de companhia, agressores alegam que o animal foi atropelado na rua ou sofreu uma "queda acidental". No entanto, a biomecânica das fraturas ósseas deixa assinaturas específicas que a perícia veterinária forense é capaz de decodificar.</p>

<h3 class="mt-4">Padrões de Fratura: Acidental vs. Não-Acidental</h3>
<p>Pesquisas publicadas no <em>The Veterinary Journal</em> demonstraram critérios objetivos:</p>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Fraturas de Costelas Múltiplas e Bilaterais:</strong> Altamente associadas a agressão física direta (chutes, pisões ou compressão torácica), especialmente quando presentes em diferentes arcos costais sem lesão cutânea compatível com atropelamento.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Fraturas Craniofaciais e Mandibulares Isoladas:</strong> Típicas de golpes diretos com objetos contundentes ou socos/chutes direcionados à cabeça do pet.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Fraturas em Diferentes Estágios de Consolidação:</strong> A coexistência de fraturas antigas (com calo ósseo maduro) e fraturas agudas (sem reação osteogênica) comprova histórico continuado de violência doméstica contra o animal.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Intarapanich NP et al.</strong> Characterization and Comparison of Injuries Caused by Accidental and Non-accidental Blunt Force Trauma in Dogs and Cats. <em>J Forensic Sci</em>, 2016;61(4):993-9. DOI: 10.1111/1556-4029.13074.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27364279/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27364279)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Síndrome do Animal Espancado (Battered Pet): Diagnóstico Forense de Lesões Múltiplas',
                'slug' => 'sindrome-animal-espancado-battered-pet-diagnostico-forense-lesoes-multiplas',
                'summary' => 'A Síndrome do Animal Espancado descreve o ciclo de violência repetida contra cães e gatos. Saiba como a perícia veterinária identifica sinais clínicos e patológicos característicos.',
                'category' => 'Medicina Veterinária Legal',
                'image' => 'https://images.unsplash.com/photo-1548767797-d8c844163c4c?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">Análoga à Síndrome da Criança Espancada na medicina humana legal, a <strong>Síndrome do Animal Espancado (Battered Pet Syndrome)</strong> é uma entidade médico-legal que caracteriza o abuso físico repetitivo e intencional perpetrado contra animais domésticos por tutores ou cuidadores.</p>

<h3 class="mt-4">Sinais de Alerta no Exame Clínico e Forense</h3>
<p>O perito veterinário analisa o conjunto de evidências comportamentais e físicas:</p>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Incongruência no Histórico:</strong> A explicação fornecida pelo tutor não coincide com a gravidade, localização ou mecanismo das lesões observadas.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Demora Injustificada no Atendimento:</strong> Lapso temporal extenso entre o suposto incidente e a busca por assistência médica veterinária.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Histórico de Troca Frequente de Clínicas ("Doctor Shopping"):</strong> Tutores que levam o animal a diferentes clínicas para evitar que um único veterinário perceba a recorrência dos traumas.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Lesões Multissistêmicas Ocultas:</strong> Hematomas subcapsulares no fígado, baço e pulmões detectados em exames de imagem ou necropsia.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Salvagni FA et al.</strong> Animal serial killing: The first criminal conviction for animal cruelty in Brazil. <em>Forensic Sci Int</em>, 2016;267():e1-e5. DOI: 10.1016/j.forsciint.2016.08.033.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27616733/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27616733)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Trauma Contuso em Animais: Como Identificar Pancadas, Chutes e Espancamentos Ocultos',
                'slug' => 'trauma-contuso-animais-identificar-pancadas-chutes-espancamentos-ocultos',
                'summary' => 'O trauma contuso pode causar hemorragias fatais internas sem deixar marcas externas evidentes na pele com pelos. Saiba como a patologia forense diagnostica o dano.',
                'category' => 'Patologia Forense',
                'image' => 'https://images.unsplash.com/photo-1628009368231-7bb7cfcb0def?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">Nos animais de companhia, a densidade da pelagem e a elasticidade natural da pele frequentemente mascaram lesões graves causadas por instrumentos contundentes (como cabos de vassoura, bastões, chutes ou arremesso contra superfícies rígidas). Nesses casos, o exame externo superficial pode parecer inocente, enquanto órgãos vitais internos sofreram rupturas fatais.</p>

<h3 class="mt-4">O Diagnóstico Pericial do Trauma Contuso</h3>
<p>O patologista forense veterinário realiza técnicas avançadas de dissecação e incisão em camadas (esfolamento técnico) para revelar:</p>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Hematomas e Sufusões Subcutâneas Profundas:</strong> Sangramentos entre as fáscias musculares correspondentes ao formato do instrumento contundente.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Lacerações Parenquimatosas:</strong> Ruptura de fígado, baço e rins com hemoperitônio maciço decorrente de compressão ou impacto de alta energia.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Contusões Pulmonares e Hemotórax:</strong> Sangramento alveolar por impacto torácico direto.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Ressel L et al.</strong> Blunt Force Trauma in Veterinary Forensic Pathology. <em>Vet Pathol</em>, 2016;53(5):941-61. DOI: 10.1177/0300985816653988.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27381403/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27381403)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Perícia Veterinária em Queimaduras e Lesões Térmicas: Água Fervente, Fogo e Produtos Químicos',
                'slug' => 'pericia-veterinaria-queimaduras-lesoes-termicas-agua-fervente-fogo-produtos-quimicos',
                'summary' => 'A diferenciação entre queimaduras acidentais e atos cruéis (como óleo quente ou fogo intencional) exige análise pericial do padrão de escorrimento e profundidade da lesão.',
                'category' => 'Medicina Veterinária Legal',
                'image' => 'https://images.unsplash.com/photo-1517457373958-b7bdd4587205?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">Queimaduras em animais representam uma das formas mais dolorosas e graves de trauma físico. Na medicina veterinária forense, o perito é chamado para elucidar o agente térmico (líquidos escaldantes, fogo direto, superfícies aquecidas, eletricidade ou agentes químicos) e determinar se houve dolo ou negligência.</p>

<h3 class="mt-4">Avaliação do Padrão e Distribuição das Queimaduras</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Padrão de Escorrimento ("Splash and Flow Pattern"):</strong> Queimaduras em dorso e flancos provocadas por arremesso de água ou óleo fervente apresentam margens características que descem com a gravidade.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Queimaduras por Imersão Forçada:</strong> Linhas de demarcação nítidas nas patas ou membros sem respingos acidentais sugerem retenção forçada do animal em líquido aquecido.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Inalação de Fumaça e Monóxido de Carbono:</strong> Em incêndios, a presença de fuligem na traqueia e dosagem de carboxihemoglobina (COHb) no sangue confirma se o animal estava vivo quando o fogo iniciou.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Wohlsein P et al.</strong> Thermal Injuries in Veterinary Forensic Pathology. <em>Vet Pathol</em>, 2016;53(5):1001-17. DOI: 10.1177/0300985816643368.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27106739/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27106739)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Asfixia, Enforcamento e Afogamento de Animais: Diagnóstico Pericial e Achados Necroscópicos',
                'slug' => 'asfixia-enforcamento-afogamento-animais-diagnostico-pericial-achados-necroscopicos',
                'summary' => 'Como a perícia veterinária identifica sinais clássicos de asfixia mecânica, estrangulamento por coleiras/cordas e afogamento criminoso através de exames patológicos.',
                'category' => 'Patologia Forense',
                'image' => 'https://images.unsplash.com/photo-1544568100-847a948585b9?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">A asfixia mecânica é uma modalidade de morte violenta decorrente do impedimento da troca gasosa respiratória. Em cães e gatos, as formas mais comuns encontradas em investigações policiais e periciais incluem enforcamento, sufocamento por sacolas plásticas, confinamento em veículos fechados sob calor extremo e afogamento forçado.</p>

<h3 class="mt-4">Achados Clássicos na Necropsia Forense</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Petéquias e Hemorragias Subpleurais/Subepicárdicas:</strong> Pequenos pontos de hemorragia decorrentes do aumento abrupto da pressão venosa e hipóxia capilar.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Sulco de Enforcamento:</strong> Marca de compressão no pescoço com fratura do osso hioide e hemorragia nos músculos pré-tireoidianos.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Cogumelo de Espuma e Enfisema Aquoso no Afogamento:</strong> Espuma abundante nas vias aéreas superiores, hiperinsuflação pulmonar aquosa e pesquisa laboratorial de diatomáceas nos órgãos internos.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>McEwen BJ</strong> Nondrowning Asphyxia in Veterinary Forensic Pathology: Suffocation, Strangulation, and Mechanical Asphyxia. <em>Vet Pathol</em>, 2016;53(5):1037-48. DOI: 10.1177/0300985816643370.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27084399/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27084399)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Inanição e Desnutrição Severa: Como Provar Negligência Nutricional em Processos Judiciais',
                'slug' => 'inanicao-desnutricao-severa-como-provar-negligencia-nutricional-processos-judiciais',
                'summary' => 'A comprovação de morte ou maus-tratos por inanição requer a exclusão científica de doenças crônicas subjacentes e a avaliação de biomarcadores de caquexia extrema.',
                'category' => 'Medicina Veterinária Legal',
                'image' => 'https://images.unsplash.com/photo-1583337130417-3346a1be7dee?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">A privação de alimento e água constitui uma das formas mais frequentes de crueldade por omissão ou abandono. Contudo, em juízo, defesas costumam alegar que o emagrecimento severo era consequência de câncer, insuficiência renal ou doença infecciosa terminal. A perícia veterinária forense é a responsável por diferenciar inanição por negligência de caquexia patológica.</p>

<h3 class="mt-4">Marcadores Patológicos da Inanição</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Atrofia Serosa da Gordura:</strong> Transformação gelatinosa dos depósitos de gordura do pericárdio, sulcos coronários, peri-renais e medula óssea decorrente do consumo extremo das reservas lipídicas corporais.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Corpos Estranhos Anormais no Estômago:</strong> Ingestão de pedras, terra, plásticos, gravetos ou tecidos na tentativa desesperada de saciar a fome ("geofagia e alotriofagia de fome").</li>
    <li class="list-group-item bg-transparent px-0"><strong>Exclusão Histopatológica de Neoplasias e Falências Orgânicas:</strong> Avaliação microscópica demonstrando órgãos histologicamente sadios, porém atrofiados por desnutrição pura.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Gerdin JA et al.</strong> Circumstances, Descriptive Characteristics, and Pathologic Findings in Dogs Suspected of Starving. <em>Vet Pathol</em>, 2016;53(5):1087-94. DOI: 10.1177/0300985815575049.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/25791037/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 25791037)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Intoxicação Intencional de Animais: Análise Laboratorial e Provas em Juízo',
                'slug' => 'intoxicacao-intencional-animais-analise-laboratorial-provas-juizo',
                'summary' => 'Saiba como o perito toxicologista forense investiga casos de envenenamento criminoso de pets, desde a coleta correta de amostras até o testemunho pericial nos tribunais.',
                'category' => 'Toxicologia Forense',
                'image' => 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">O envenenamento criminoso de animais domésticos gera comoção social e processos criminais sob a Lei nº 9.605/98 (Lei de Crimes Ambientais). No entanto, suspeitas clínicas isoladas não bastam perante o juiz: a condenação penal depende de um laudo toxicológico pericial analítico conclusivo.</p>

<h3 class="mt-4">O Fluxo da Investigação Toxicológica Forense</h3>
<ol class="my-4">
    <li class="mb-2"><strong>Amostragem Estratégica:</strong> Coleta de pelo menos 50g a 100g de conteúdo estomacal, fígado, rins, urina e iscas suspeitas encontradas no local.</li>
    <li class="mb-2"><strong>Preservação Sem Formalina:</strong> O formol destrói muitas moléculas tóxicas. As amostras destinadas à toxicologia devem ser imediatamente congeladas a -20°C em frascos novos de vidro ou plástico livre de contaminantes.</li>
    <li class="mb-2"><strong>Rastreio e Confirmação:</strong> Uso de espectrometria de massas para confirmação inequívoca da estrutura química do praguicida ou fármaco letal.</li>
</ol>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Buchweitz JP et al.</strong> Malicious poisoning of a canine with the neuromuscular blocking agent rocuronium: a case report with postmortem quantitation. <em>J Anal Toxicol</em>, 2026;50(6):. DOI: 10.1093/jat/bkag005.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/41514164/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 41514164)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Exumação e Detecção Tardia de Chumbinho (Carbamatos/Aldicarbe): O que a Ciência Comprova',
                'slug' => 'exumacao-deteccao-tardia-chumbinho-carbamatos-aldicarbe-ciencia-comprova',
                'summary' => 'Pesquisas científicas pioneiras revelam a estabilidade pós-morte do Aldicarbe e Carbofurano em animais sepultados, permitindo comprovar envenenamento mesmo semanas após o enterro.',
                'category' => 'Toxicologia Forense',
                'image' => 'https://images.unsplash.com/photo-1532187863486-abf9d39d66e8?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">Uma das perguntas mais recorrentes enfrentadas por peritos da MedVet Legal é se um animal já enterrado ainda pode fornecer provas materiais de intoxicação por "chumbinho". Pesquisas científicas conduzidas no Brasil sob liderança da Dra. Adriana de Siqueira e pesquisadores da USP comprovaram que a resposta é afirmativa.</p>

<h3 class="mt-4">Estabilidade de Carbamatos no Solo e em Carcaças</h3>
<p>Diferente do que se acreditava no passado, os compostos praguicidas inibidores da colinesterase (como Aldicarbe e Carbofurano) possuem estabilidade química suficiente em tecidos em decomposição:</p>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Matrizes de Escolha:</strong> Fígado remanescente, resíduos gástricos saponificados e amostras de solo em contato direto com a carcaça.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Controle de Solo:</strong> Coleta de amostra de controle de solo a 2 metros de distância da cova para descartar contaminação pré-existente do terreno.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Eficácia em Juízo:</strong> A prova pericial obtida por exumação técnica fundamenta sentenças condenatórias tanto na esfera criminal quanto em indenizações cíveis por danos morais e materiais.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>da Silva MP et al.</strong> Presence of Nationally Banned Carbamates and Organophosphate Pesticides in Wild Carnivores, Southeastern Brazil. <em>Environ Toxicol</em>, 2026;. DOI: 10.1002/tox.70131.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/42219579/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 42219579)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Intoxicação por Raticidas Anticoagulantes: Como Provar Envenenamento por Venenos de Rato',
                'slug' => 'intoxicacao-raticidas-anticoagulantes-como-provar-envenenamento-veneno-rato',
                'summary' => 'Os raticidas anticoagulantes de segunda geração (Brodifacoum, Bromadiolona) causam hemorragias difusas fatais. Saiba como estruturar a prova pericial dessas intoxicações.',
                'category' => 'Toxicologia Forense',
                'image' => 'https://images.unsplash.com/photo-1584017911766-d451b3d0e843?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">Os raticidas anticoagulantes agem inibindo a enzima epóxido redutase da vitamina K, bloqueando a síntese hepática dos fatores de coagulação II, VII, IX e X. A intoxicação em cães e gatos pode decorrer de ingestão direta de iscas ou intoxicação secundária (consumo de roedores envenenados).</p>

<h3 class="mt-4">Achados Periciais Característicos</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Hemorragias Cavitárias Maciças:</strong> Hemotórax, hemoperitônio e hemopericárdio sem evidência de trauma ósseo ou ruptura traumática de vasos.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Sangramento em Múltiplos Tecidos:</strong> Hematomas subcutâneos difusos e sangramento em articulações e bexiga.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Quantificação Hepática:</strong> Dosagem precisa dos níveis de anticoagulante por cromatografia em tecido hepático, confirmando a dosagem letal.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Dartencet C et al.</strong> Intravenous Administration of Vitamin K1 in Dogs and Cats Presented for Anticoagulant Rodenticide Poisoning: A Retrospective Study of 58 Cases. <em>J Vet Emerg Crit Care (San Antonio)</em>, 2026;36(3):365-371. DOI: 10.1111/vec.70120.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/42189143/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 42189143)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Pesticidas e Praguicidas na Rotina Forense Veterinária: Métodos Avançados de Detecção (GC-MS / LC-MS)',
                'slug' => 'pesticidas-praguicidas-rotina-forense-veterinaria-metodos-avancados-deteccao',
                'summary' => 'A cromatografia acoplada à espectrometria de massas é o padrão-ouro forense para identificação de pesticidas em animais. Entenda como funciona essa tecnologia.',
                'category' => 'Toxicologia Forense',
                'image' => 'https://images.unsplash.com/photo-1507668077129-56e32842fceb?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">Para que um laudo toxicológico tenha valor de prova plena no Judiciário, testes colorimétricos rápidos ou presunções clínicas são insuficientes. A identificação definitiva da molécula exige tecnologias de cromatografia gasosa ou líquida acopladas à espectrometria de massas (GC-MS e LC-MS/MS).</p>

<h3 class="mt-4">Por que a Espectrometria de Massas é o Padrão-Ouro?</h3>
<p>Esses equipamentos realizam a fragmentação molecular da substância presente na amostra e produzem um espectro único — funcionando como uma verdadeira "impressão digital molecular". Isso permite:</p>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0">Detectar concentrações mínimas (partes por bilhão - ppb).</li>
    <li class="list-group-item bg-transparent px-0">Eliminar completamente a possibilidade de falsos positivos causados por medicamentos ou produtos naturais da decomposição.</li>
    <li class="list-group-item bg-transparent px-0">Identificar misturas complexas de venenos administrados conjuntamente para mascarar o crime.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Kakazu K et al.</strong> Use of nitrogen as an alternative to helium in gas chromatography/mass spectrometry for forensic toxicological analysis. <em>Forensic Toxicol</em>, 2026;44(2):281-295. DOI: 10.1007/s11419-025-00744-1.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/41739340/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 41739340)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Ferimentos por Arma de Fogo em Animais: Trajetória Balística, Orifícios de Entrada e Saída',
                'slug' => 'ferimentos-arma-de-fogo-animais-trajetoria-balistica-orificios-entrada-saida',
                'summary' => 'Saiba como a balística forense veterinária analisa disparos de armas de fogo em cães, gatos e equinos, determinando a distância do tiro, ângulo e calibre do projétil.',
                'category' => 'Patologia Forense',
                'image' => 'https://images.unsplash.com/photo-1595590424283-b8f17842773f?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">Disparos de arma de fogo contra animais ocorrem em conflitos de vizinhança, caça ilegal, litígios rurais e ações policiais. O patologista forense veterinário desempenha papel decisivo ao correlacionar as lesões teciduais com a dinâmica do disparo.</p>

<h3 class="mt-4">Elementos Chave da Balística Forense no Cadáver Animal</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Orifício de Entrada:</strong> Caracterizado pela orla de contusão e enxugo, bordas invertidas e, em tiros a curta distância, zona de tatuagem (grânulos de pólvora incombusta) e esfumaçamento.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Orifício de Saída:</strong> Geralmente maior, de formato irregular, com bordas evertidas e ausência de zonas de pólvora.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Trajetória e Recuperação de Projétil:</strong> Documentação tridimensional do túnel da ferida e recuperação intacta de projéteis (chumbo ou jaqueta) para confronto balístico com a arma suspeita.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Bradley-Siemens N et al.</strong> Veterinary Forensics: Firearms and Investigation of Projectile Injury. <em>Vet Pathol</em>, 2016;53(5):988-1000. DOI: 10.1177/0300985816653170.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27312366/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27312366)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Reconstrução de Cenas de Tiro e Balística Terminal na Perícia Veterinária',
                'slug' => 'reconstrucao-cenas-de-tiro-balistica-terminal-pericia-veterinaria',
                'summary' => 'A reconstituição técnica de disparos contra animais exige a integração entre a perícia de local de crime, balística terminal e exames complementares de imagem.',
                'category' => 'Medicina Veterinária Legal',
                'image' => 'https://images.unsplash.com/photo-1453728013993-6d66e9c9123a?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">A balística terminal estuda o comportamento do projétil ao atingir e atravessar o corpo do animal. Ao integrar a posição da vítima, obstáculos intermediários (cercas, muros, portas) e a postura do atirador, a perícia veterinária forense possibilita a reconstrução fidedigna da cena do crime.</p>

<h3 class="mt-4">Importância nos Tribunais</h3>
<p>Em processos de legítima defesa alegada pelo atirador (por exemplo, "o cão ia me morder"), a reconstituição balística consegue provar se o animal estava correndo em direção ao atirador ou se foi alvejado de costas ou enquanto fugia, sendo peça-chave para desqualificar teses defensivas infundadas.</p>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Yağlı K et al.</strong> Forensic evaluation of a fatal shotgun injury in a Cane Corso dog: Necropsy and radiographic findings. <em>Open Vet J</em>, 2026;16(1):71-80. DOI: 10.5455/OVJ.2026.v16.i1.69.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/42375290/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 42375290)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Virtópsia e Tomografia Computadorizada Forense: A Radiologia Moderna na Perícia Animal',
                'slug' => 'virtopsia-tomografia-computadorizada-forense-radiologia-moderna-pericia-animal',
                'summary' => 'A necropsia virtual (Virtópsia) através de TC e reconstrução 3D permite documentar fraturas, projéteis e hemorragias antes da abertura do cadáver animal.',
                'category' => 'Patologia Forense',
                'image' => 'https://images.unsplash.com/photo-1516549655169-df83a0774514?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">A <strong>Virtópsia (necropsia virtual)</strong> é uma das inovações mais revolucionárias da medicina veterinária forense. Através de tomografia computadorizada multislice (TC) e ressonância magnética post-mortem, o perito obtém um mapeamento anatômico completo não invasivo antes mesmo de realizar a primeira incisão.</p>

<h3 class="mt-4">Vantagens da Virtópsia em Juízo</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Preservação Eterna da Prova:</strong> Os arquivos DICOM em 3D podem ser reexaminados por outros peritos a qualquer tempo, mesmo após a cremação ou sepultamento do corpo.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Localização Exata de Fragmentos e Embolias Gasosas:</strong> Detecta microfragmentos metálicos de projéteis e ar livre no sistema vascular que seriam perdidos na necropsia tradicional.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Demonstração Visual para Magistrados:</strong> Reconstruções 3D coloridas permitem que juízes e jurados leigos visualizem as fraturas com total clareza.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>McEwen BJ et al.</strong> Veterinary Forensic Pathology: Drowning and Bodies Recovered From Water. <em>Vet Pathol</em>, 2016;53(5):1049-56. DOI: 10.1177/0300985815625757.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/26926081/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 26926081)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Entomologia Forense Veterinária: Como os Insetos Ajudam a Revelar o Momento e Local da Morte',
                'slug' => 'entomologia-forense-veterinaria-insetos-revelar-momento-local-morte',
                'summary' => 'A fauna cadavérica e o ciclo de desenvolvimento de insetos fornecem estimativas precisas do intervalo pós-morte e indicam se o cadáver do animal foi movido de lugar.',
                'category' => 'Patologia Forense',
                'image' => 'https://images.unsplash.com/photo-1502082553048-f009c37129b9?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">Quando um animal é encontrado em avançado estado de decomposição, os métodos clássicos de cronologia cadavérica perdem a precisão. É nesse momento que a <strong>Entomologia Forense Veterinária</strong> se torna indispensável para estimar o Intervalo Pós-Morte Mínimo (minPMI).</p>

<h3 class="mt-4">Como Funciona a Análise Entomológica</h3>
<p>Moscas varejeiras (*Calliphoridae*) e besouros colonizam o cadáver em ondas sucessivas e previsíveis. O perito analisa:</p>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Idade das Larvas e Graus-Dia Acumulados (ADD):</strong> O estágio de desenvolvimento das larvas, cruzado com as temperaturas históricas do local, indica o momento exato da postura dos ovos.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Entomotoxicologia:</strong> Larvas alimentadas com tecidos de animais envenenados retêm a substância tóxica em seus corpos, permitindo a identificação do veneno mesmo quando o cadáver já virou esqueleto.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Brundage A et al.</strong> Forensic Entomology in Animal Cruelty Cases. <em>Vet Pathol</em>, 2016;53(5):898-909. DOI: 10.1177/0300985816651683.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27480760/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27480760)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Marcas de Mordedura e DNA Forense em Ataques de Cães: Identificação do Animal Agressor',
                'slug' => 'marcas-de-mordedura-dna-forense-ataques-de-caes-identificacao-animal-agressor',
                'summary' => 'Em ataques entre animais ou contra humanos, a análise pericial de marcas de dentes e coleta de DNA salivar identifica com certeza biológica o animal responsável.',
                'category' => 'Medicina Veterinária Legal',
                'image' => 'https://images.unsplash.com/photo-1587300003388-59208cc962cb?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">Ataques graves de cães a outros animais de estimação ou a pedestres resultam frequentemente em processos de responsabilidade civil e medidas cautelares. Quando há múltiplos animais suspeitos no local, a perícia odontológica e genética forense veterinária é a única capaz de individualizar o agressor.</p>

<h3 class="mt-4">Procedimentos Periciais de Odontologia e Genética Forense</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Coleta de DNA Salivar por Swab:</strong> Swabs estéreis passados nas bordas da ferida da vítima antes da higienização capturam células da mucosa oral do cão agressor.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Mapeamento Odontométrico:</strong> Medição do espaço intercanino e moldagem das arcadas dentárias dos cães suspeitos para sobreposição fotográfica milimétrica com as marcas deixadas na pele.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Giovannini E et al.</strong> Morphometric analysis of dog bitemarks. An experimental study. <em>Forensic Sci Int</em>, 2025;368():112392. DOI: 10.1016/j.forsciint.2025.112392.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/39923367/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 39923367)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Cadeia de Custódia na Perícia Veterinária: O que Evita a Anulação da Prova em Tribunal?',
                'slug' => 'cadeia-de-custodia-pericia-veterinaria-evita-anulacao-prova-tribunal',
                'summary' => 'A cadeia de custódia garante a integridade e rastreabilidade de todas as provas em processos veterinários. Entenda as regras do Código de Processo Penal e Civil.',
                'category' => 'Assistência Técnica',
                'image' => 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">A <strong>Cadeia de Custódia</strong> é o conjunto de procedimentos formais que documenta a história cronológica de uma evidência (corpo do animal, amostras de sangue, venenos, prontuários ou projéteis), desde o momento de sua coleta até a apresentação final em juízo.</p>

<h3 class="mt-4">As Etapas Obrigatórias da Cadeia de Custódia</h3>
<p>De acordo com o Pacote Anticrime (Lei nº 13.964/19) e as normas de processo:</p>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Reconhecimento e Isolamento:</strong> Identificação e preservação da cena e do cadáver.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Coleta e Acondicionamento:</strong> Lacre numerado inviolável e embalagens apropriadas.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Termo de Encaminhamento e Recebimento:</strong> Assinatura de todos os profissionais que tiveram posse temporária da amostra.</li>
</ul>
<p>Qualquer falha ou quebra de lacre permite que a defesa requeira a nulidade absoluta da prova pericial, tornando inútil até o melhor exame laboratorial.</p>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Viner TC et al.</strong> Integrating the Forensic Sciences in Wildlife Case Investigations: A Case Report of Pentobarbital and Phenytoin Toxicosis in a Bald Eagle (Haliaeetus leucocephalus). <em>Vet Pathol</em>, 2016;53(5):1103-6. DOI: 10.1177/0300985816641176.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27030371/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27030371)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Auditoria de Prontuário Veterinário: O Pilar Científico nos Processos de Erro Médico',
                'slug' => 'auditoria-prontuario-veterinario-pilar-cientifico-processos-erro-medico',
                'summary' => 'A auditoria pericial de prontuários, fichas anestésicas e termos de consentimento é o principal meio de provar ou refutar alegações de negligência e imperícia veterinária.',
                'category' => 'Direito Médico Veterinário',
                'image' => 'https://images.unsplash.com/photo-1584132967334-10e028bd69f7?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">Nos litígios de responsabilidade civil por suposto erro profissional de médicos veterinários, o <strong>prontuário clínico-hospitalar</strong> é o documento probatório central. Uma auditoria técnica especializada é indispensável tanto para o autor que busca indenização quanto para a clínica que precisa comprovar que seguiu as melhores condutas preconizadas pela literatura.</p>

<h3 class="mt-4">O que a Auditoria Pericial Analisa no Prontuário?</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Cálculo Ponderal e Posologia Medicamentosa:</strong> Conferência matemática de todas as doses administradas versus peso corporal do animal.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Ficha de Monitoramento Anestésico:</strong> Registro minuto a minuto de frequência cardíaca, oximetria, pressão arterial e temperatura.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Termos de Consentimento Livre e Esclarecido (TCLE):</strong> Análise da clareza dos riscos informados ao tutor antes de procedimentos invasivos.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Nexo Causal:</strong> Averiguação científica se o desfecho desfavorável decorreu de falha técnica humana ou de evolução imprevisível da própria patologia do animal.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Nakamura SI</strong> A concise review of veterinary forensic medicine in Japan: current status and perspectives. <em>J Toxicol Pathol</em>, 2025;38(2):131-137. DOI: 10.1293/tox.2024-0047.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/40190625/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 40190625)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Trauma por Instrumento Perfurocortante em Animais: Facadas, Estiletes e Cortes Intencionais',
                'slug' => 'trauma-instrumento-perfurocortante-animais-facadas-cortes-intencionais',
                'summary' => 'Saiba como a medicina veterinária forense diferencia feridas perfurocortantes intencionais (facadas) de cortes acidentais (arames, vidros ou cercas) através do exame das bordas e ângulo de entrada.',
                'category' => 'Patologia Forense',
                'image' => 'https://images.unsplash.com/photo-1584036561566-baf8f5f1b144?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">Lesões causadas por lâminas e objetos pontiagudos em cães, gatos e animais de grande porte são comuns em denúncias de agressão criminosa e conflitos de vizinhança. Em juízo, agressores frequentemente sustentam a tese de que o animal "se cortou sozinho em uma cerca de arame farpado ou caco de vidro". A perícia veterinária forense é a responsável por desmentir ou confirmar essa hipótese.</p>

<h3 class="mt-4">Como a Perícia Diferencia Corte Acidental de Facada Intencional?</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>1. Cauda de Escoriação e Entalhes:</strong> Lâminas de faca deixam um entalhe terminal e cauda de saída linear, indicando o sentido exato do golpe.</li>
    <li class="list-group-item bg-transparent px-0"><strong>2. Profundidade vs. Extensão:</strong> Facadas intencionais (feridas perfurocortantes) apresentam trajeto profundo em direção a cavidades internas (tórax/abdômen) com orifício de entrada de dimensões precisas. Cortes em arames, por outro lado, são tipicamente superficiais, irregulares e acompanhados de lacerações em "retalho".</li>
    <li class="list-group-item bg-transparent px-0"><strong>3. Lesões de Defesa:</strong> Cortes na face plantar/palmar das patas e região ventral do pescoço evidenciam a tentativa do animal de bloquear os golpes do agressor.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>de Siqueira A et al.</strong> Forensic Veterinary Pathology: Sharp Injuries in Animals. <em>Vet Pathol</em>, 2016;53(5):979-87. DOI: 10.1177/0300985816655850.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27418586/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27418586)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Intoxicação por Etilenoglicol (Anticongelante e Aditivo de Radiador): Diagnóstico Toxicológico e Cristais de Oxalato',
                'slug' => 'intoxicacao-etilenoglicol-anticongelante-aditivo-radiador-cristais-oxalato',
                'summary' => 'O etilenoglicol possui sabor adocicado e é usado como veneno doloso. Entenda a formação de cristais de oxalato de cálcio birrefringentes nos túbulos renais na necropsia forense.',
                'category' => 'Toxicologia Forense',
                'image' => 'https://images.unsplash.com/photo-1579684385127-1ef15d508118?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">O <strong>etilenoglicol</strong>, substância amplamente presente em aditivos de radiador automotivo e fluidos refrigerantes, possui sabor extremamente doce e atrativo para cães e gatos. Por essa razão, é frequentemente utilizado de forma premeditada por criminosos para envenenar animais através de alimentos e água.</p>

<h3 class="mt-4">O Marcador Patognomônico: Cristais de Oxalato de Cálcio</h3>
<p>No organismo do pet, o etilenoglicol é metabolizado pela enzima álcool desidrogenase em metabólitos altamente tóxicos, culminando na formação de ácido oxálico. Este se liga ao cálcio sérico e precipita maciçamente nos rins:</p>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Insuficiência Renal Aguda Fulminante:</strong> O bloqueio tubular e necrose tubular aguda provocam anúria e morte em 24 a 72 horas.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Luz Polarizada na Histopatologia:</strong> Ao examinar os rins sob microscopia de luz polarizada, os cristais de oxalato de cálcio brilham intensamente em formato de leque ou prismas, fornecendo prova microscópica incontestável da intoxicação.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Kim AY et al.</strong> Ethylene glycol poisoning in a free-roaming cat associated with suspected animal cruelty: a forensic veterinary case report in South Korea. <em>Front Vet Sci</em>, 2026;13():1846573. DOI: 10.3389/fvets.2026.1846573.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/42221951/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 42221951)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Morte Anestésica e Complicações Cirúrgicas Veterinárias: Auditoria Pericial de Falha Humana vs. Risco Inerente',
                'slug' => 'morte-anestesica-complicacoes-cirurgicas-veterinarias-auditoria-pericial',
                'summary' => 'O óbito transoperatório ou no pós-anestésico exige auditoria técnico-pericial minuciosa para averiguar se houve negligência na monitoração ou se o evento decorreu de idiossincrasia biológica imprevisível.',
                'category' => 'Direito Médico Veterinário',
                'image' => 'https://images.unsplash.com/photo-1551601651-2a8555f1a136?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">A perda de um paciente veterinário durante indução anestésica, cirurgia ou recuperação na internação é uma das maiores causas de litígios por erro médico. A questão jurídica central é: <em>houve falha técnica profissional (imperícia/negligência) ou o óbito foi uma fatalidade decorrente do risco inerente ao procedimento?</em></p>

<h3 class="mt-4">O que a Perícia Audita nos Casos de Óbito Anestésico?</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Avaliação Pré-Anestésica (Classificação ASA):</strong> Se o médico veterinário solicitou exames cardiovasculares, renais e hematológicos adequados ao porte e idade do animal antes de iniciar o procedimento.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Monitorização de Parâmetros Vitais:</strong> Presença de registros de capnografia (EtCO2), oximetria de pulso, eletrocardiograma e pressão arterial não-invasiva durante todo o procedimento.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Protocolo de Ressuscitação Cardiopulmonar (RCP):</strong> Se as manobras de emergência (intubação, ventilação com pressão positiva, drogas vasoativas) foram instituídas em tempo hábil e em dosagens corretas.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>DeLay J</strong> Perianesthetic Mortality in Domestic Animals: A Retrospective Study of Postmortem Lesions and Review of Autopsy Procedures. <em>Vet Pathol</em>, 2016;53(5):1078-86. DOI: 10.1177/0300985816655853.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27371539/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27371539)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Traumatismo Cranioencefálico (TCE) e a "Síndrome do Animal Sacudido": Prova Pericial de Agressão',
                'slug' => 'traumatismo-cranioencefalico-tce-sindrome-animal-sacudido-prova-pericial',
                'summary' => 'Movimentos violentos de aceleração e desaceleração brusca provocam hemorragias subdurais e lesões axonais difusas em filhotes e cães pequenos. Conheça a prova patológica.',
                'category' => 'Patologia Forense',
                'image' => 'https://images.unsplash.com/photo-1543466835-00a7907e9de1?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">Animais de companhia de pequeno porte e filhotes são altamente vulneráveis a traumas mecânicos intracranianos causados por arremessos violentos ou sacudimento vigoroso pelo dorso (conhecido na literatura forense como <em>Shaken Puppy Syndrome</em> ou Síndrome do Filhote Sacudido).</p>

<h3 class="mt-4">Marcadores Patológicos de Traumatismo Não Acidental no Sistema Nervoso Central</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Hemorragia Subdural e Subaracnóidea:</strong> Rompimento de veias pontes decorrente da inércia do cérebro dentro da calota craniana durante sacudimentos.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Lesão Axonal Difusa (LAD):</strong> Estiramento e ruptura microscópica de axônios cerebrais demonstrada por imunofluorescência e histopatologia com impregnação pela prata.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Hemorragias Retinianas Bilaterais:</strong> Lesões oculares típicas de forças de cisalhamento agudas, praticamente inexistentes em quedas acidentais comuns.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Finnie JW</strong> Forensic Pathology of Traumatic Brain Injury. <em>Vet Pathol</em>, 2016;53(5):962-78. DOI: 10.1177/0300985815612155.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/26578643/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 26578643)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Eletrocussão e Choque Elétrico em Animais: Diagnóstico Necroscópico de Marcas de Entrada e Queimaduras Joule',
                'slug' => 'eletrocussao-choque-eletrico-animais-diagnostico-marcas-joule-necropsia',
                'summary' => 'A morte por descarga elétrica pode ocorrer por cercas clandestinas energizadas, fiação irregular ou raios. Saiba como a perícia detecta a marca elétrica de Jellinek e metalização tecidual.',
                'category' => 'Patologia Forense',
                'image' => 'https://images.unsplash.com/photo-1473448912268-2022ce9509d8?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">A morte de cães, equinos e bovinos por eletrocussão gera importantes disputas de responsabilidade civil contra concessionárias de energia elétrica, proprietários de cercas clandestinas ou vizinhos que eletrificaram portões irregularmente. A comprovação médico-legal do choque elétrico exige exame criterioso dos pontos de contato.</p>

<h3 class="mt-4">Achados Periciais da Eletrocussão</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Marca Elétrica de Jellinek (Efeito Joule):</strong> Lesão cutânea circular ou linear, acinzentada ou esbranquiçada, com bordas elevadas e centro deprimido, onde a corrente elétrica penetrou no corpo.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Metalização Dérmica:</strong> Transferência microscópica de partículas do condutor metálico (cobre, ferro) para a epiderme do animal, confirmada por espectrometria de raios-X (EDS).</li>
    <li class="list-group-item bg-transparent px-0"><strong>Fibrilação Ventricular e Parada Respiratória:</strong> Ausência de lesões traumáticas mecânicas, com congestão venosa generalizada e edema pulmonar fulminante.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Kagan RA</strong> Electrocution of Raptors on Power Lines: A Review of Necropsy Methods and Findings. <em>Vet Pathol</em>, 2016;53(5):1030-6. DOI: 10.1177/0300985816646431.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27154543/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27154543)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Acumulação Compulsiva de Animais (Síndrome de Noé / Animal Hoarding): Perícia de Bem-Estar e Saúde Pública',
                'slug' => 'acumulacao-compulsiva-animais-sindrome-noe-animal-hoarding-pericia',
                'summary' => 'O colecionismo de animais envolve transtornos psiquiátricos, extrema negligência sanitária e violação das leis de proteção animal. Saiba como atua a perícia multidisciplinar.',
                'category' => 'Medicina Veterinária Legal',
                'image' => 'https://images.unsplash.com/photo-1548767797-d8c844163c4c?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">A <strong>Síndrome de Noé</strong> (ou <em>Animal Hoarding</em>) caracteriza-se pela acumulação compulsiva de dezenas ou centenas de animais em condições degradantes, sem espaço, alimentação ou assistência veterinária básica. Trata-se de um problema complexo que une Direito Animal, Saúde Pública e Psiquiatria.</p>

<h3 class="mt-4">O Papel da Perícia Veterinária nas Intervenções Judiciais</h3>
<p>Em ações civis públicas movidas pelo Ministério Público ou mandados de busca e apreensão, o perito veterinário elabora o relatório sanitário individualizado:</p>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Escore de Condição Corporal (ECC):</strong> Mapeamento do grau de subnutrição de cada animal apreendido.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Avaliação de Doenças Infectocontagiosas e Zoonoses:</strong> Diagnóstico de sarnas, giardíase, leishmaniose e esporotricose com risco de contaminação comunitária.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Relatório Pericial Ambiental:</strong> Mensuração de níveis de amônia no ar, presença de fezes acumuladas e carcaças não sepultadas no imóvel.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Kmetiuk LB et al.</strong> "Dying alone and being eaten": dog scavenging on the remains of an elderly animal hoarder-a case report. <em>Front Vet Sci</em>, 2023;10():1161935. DOI: 10.3389/fvets.2023.1161935.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/37706076/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 37706076)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Trauma Ocular por Agressão em Pets: Fraturas de Órbita, Hemorragias e Hifema Forense',
                'slug' => 'trauma-ocular-agressao-pets-fraturas-orbita-hifema-forense',
                'summary' => 'Golpes diretos no rosto de cães e gatos provocam lesões oftálmicas características (hifema, luxação de cristalino e fratura do arco zigomático). Saiba como estruturar a prova pericial.',
                'category' => 'Medicina Veterinária Legal',
                'image' => 'https://images.unsplash.com/photo-1583511655857-d19b40a7a54e?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">O globo ocular e a órbita de cães e gatos são estruturas anatômicas altamente suscetíveis a traumas por agressão direta (como socos, coronhadas ou chutes). A oftalmologia forense veterinária correlaciona as lesões oculares com a força e direção do impacto.</p>

<h3 class="mt-4">Achados Oftálmicos mais Frequentes no Trauma Não Acidental</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Hifema (Sangue na Câmara Anterior):</strong> Sangramento agudo decorrente de contusão mecânica direta que rompe os vasos da íris e corpo ciliar.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Fraturas Orbitárias e Zigomáticas:</strong> Quebra dos ossos faciais de sustentação ocular, indicando impacto de alta intensidade direcionado à face.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Luxação de Cristalino e Descolamento de Retina:</strong> Ruptura traumática das fibras da zônula provocada por desaceleração violenta.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Piraino M et al.</strong> From evidence to proof: case series application of 3d reconstructions in forensic analysis of dog bite injuries. <em>Leg Med (Tokyo)</em>, 2025;78():102718. DOI: 10.1016/j.legalmed.2025.102718.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/41004983/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 41004983)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Intermação e Golpe de Calor em Cães Presos em Carros: Responsabilidade Civil e Criminal por Hipertermia',
                'slug' => 'intermacao-golpe-calor-caes-carros-responsabilidade-civil-criminal-hipertermia',
                'summary' => 'Deixar o animal trancado em veículo fechado sob o sol provoca colapso térmico em menos de 15 minutos. Conheça as lesões microscópicas de intermação e as consequências jurídicas.',
                'category' => 'Direito Médico Veterinário',
                'image' => 'https://images.unsplash.com/photo-1541599540903-216a46ca1dc0?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">O confinamento de animais de companhia no interior de veículos automotores sob radiação solar direta é uma conduta frequente que configura crime de maus-tratos qualificado pela morte. Em dias quentes, a temperatura interna de um carro fechado ultrapassa facilmente 60°C em poucos minutos, gerando colapso multiorgânico por hipertermia maligna (intermação).</p>

<h3 class="mt-4">Fisiopatologia e Achados Necroscópicos do Golpe de Calor</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Coagulação Intravascular Disseminada (CIVD):</strong> A desnaturação térmica das proteínas sanguíneas gera microtrombos e hemorragias generalizadas em todos os órgãos.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Edema e Congestão Cerebral Severa:</strong> Encefalopatia térmica aguda com herniação cerebral e necrose neuronal.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Nexo Causal Jurídico:</strong> A exclusão de outras causas de morte súbita associada ao tempo de confinamento fundamenta a condenação por dolo eventual ou culpa grave do responsável.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Beard S et al.</strong> Evaluation and validation of a revised VetCompass clinical grading tool for heat-related illness in dogs. <em>Sci Rep</em>, 2026;16(1):4661. DOI: 10.1038/s41598-025-34768-9.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/41513723/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 41513723)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Intoxicação por Estricnina em Cães e Gatos: Espasmos Tetânicos e Análise Toxicológica Forense',
                'slug' => 'intoxicacao-estricnina-caes-gatos-espasmos-tetanicos-analise-toxicologica',
                'summary' => 'A estricnina é um alcaloide proibido de altíssima letalidade que causa convulsões tetânicas contínuas. Saiba como a toxicologia forense identifica o veneno em órgãos e iscas.',
                'category' => 'Toxicologia Forense',
                'image' => 'https://images.unsplash.com/photo-1512069772995-ec65ed45afd6?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">A <strong>estricnina</strong> é um alcaloide vegetal extremamente potente obtido das sementes de *Strychnos nux-vomica*. Apesar de ter sua comercialização expressamente proibida no Brasil, ela ainda é traficada clandestinamente e utilizada de forma criminosa para envenenar cães e gatos em áreas urbanas e rurais.</p>

<h3 class="mt-4">Mecanismo de Ação e Sinais Post-Mortem</h3>
<p>A estricnina antagoniza os receptores de glicina nos neurônios inibitórios da medula espinhal, provocando despolarização muscular descontrolada:</p>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Rigidez Cadavérica Precoce e Intensa:</strong> O consumo fulminante de ATP durante as convulsões tetânicas ante-mortem acelera o *rigor mortis*, fixando o animal em posição de opistótono (corpo arqueado para trás).</li>
    <li class="list-group-item bg-transparent px-0"><strong>Rastreio Laboratorial Específico:</strong> A identificação quantitativa de estricnina é feita por cromatografia gasosa acoplada a detector de massas (GC-MS) no conteúdo gástrico e fígado.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Cowan VE</strong> Strychnine poisoning in nontarget species in western Canada: A retrospective case series (2014 to 2023). <em>Can Vet J</em>, 2025;66(2):168-176.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/39898164/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 39898164)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Intoxicação por Metaldeído (Veneno Lesmicida): Hipertermia Maligna, Tremores e Provas Periciais',
                'slug' => 'intoxicacao-metaldeido-veneno-lesmicida-hipertermia-provas-periciais',
                'summary' => 'Iscas azuis para controle de caracóis e lesmas frequentemente causam intoxicação letal em cães ("síndrome do cão tremulante"). Entenda como a perícia detecta o metaldeído.',
                'category' => 'Toxicologia Forense',
                'image' => 'https://images.unsplash.com/photo-1527526029430-319f10814151?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">O <strong>metaldeído</strong> é o ingrediente ativo predominante em formicidas e lesmicidas comerciais, frequentemente comercializado na forma de granulados de cor azulada ou esverdeada. Por conter farelo de trigo em sua formulação, atrai cães vorazes em jardins de condomínios e praças públicas.</p>

<h3 class="mt-4">Diagnóstico Toxicológico e Pericial</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Odor de Acetaldeído na Necropsia:</strong> A abertura do estômago costuma revelar odor pungente característico de acetaldeído e resíduos granulados coloridos.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Depleção de GABA e Hipertermia Letal:</strong> O metaldeído reduz os níveis de ácido gama-aminobutírico (GABA) no cérebro, causando tremores contínuos incontroláveis que elevam a temperatura corporal acima de 42°C, resultando em coagulopatias e morte.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Fidalgo ÍJ et al.</strong> Grayanotoxin poisoning in animals following the ingestion of Ericaceae plants. <em>Vet J</em>, 2026;315():106564. DOI: 10.1016/j.tvjl.2026.106564.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/41544885/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 41544885)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Intoxicação por Fosfeto de Zinco: Liberação de Gás Fosfina e Cuidados de Segurança Forense',
                'slug' => 'intoxicacao-fosfeto-de-zinco-gas-fosfina-seguranca-forense',
                'summary' => 'O fosfeto de zinco reage com o suco gástrico liberando gás fosfina (odor de peixe podre/alho), com risco de intoxicação para o próprio médico veterinário na necropsia.',
                'category' => 'Toxicologia Forense',
                'image' => 'https://images.unsplash.com/photo-1563245372-f21724e3856d?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">O <strong>fosfeto de zinco</strong> é um raticida inorgânico de ação ultra-rápida. Em contato com o ácido clorídrico presente no estômago do animal, ele reage instantaneamente liberando o gás <strong>fosfina (PH3)</strong>, que é altamente letal para células cardíacas e pulmonares.</p>

<h3 class="mt-4">Protocolos de Segurança na Necropsia Forense</h3>
<p>Devido à extrema toxicidade do gás fosfina por inalação, a necropsia em casos de suspeita de fosfeto de zinco deve ser executada em capela de exaustão química:</p>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Odor de Peixe Podre ou Acetileno:</strong> Sinal de alerta clássico na abertura da cavidade abdominal.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Necrose Gástrica Hemorrágica Fulminante:</strong> Erosão e ulceração acentuada da mucosa gástrica acompanhada de colapso circulatório sistêmico.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Spadotto L et al.</strong> Fatal Intoxication of European Yew (Taxus baccata L.) in Two Donkeys in North-Eastern Italy: A Case Report. <em>Toxics</em>, 2026;14(4):. DOI: 10.3390/toxics14040294.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/42043122/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 42043122)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Projéteis de Chumbinho de Pressão vs. Arma de Fogo: Balística Comparada em Animais',
                'slug' => 'projeteis-chumbinho-de-pressao-arma-de-fogo-balistica-comparada-animais',
                'summary' => 'Disparos de espingardas de ar comprimido (chumbinho de chumbo diabolo de 4.5mm e 5.5mm) provocam lesões intracavitárias severas em gatos e cães. Entenda como o perito recupera e lauda o projétil.',
                'category' => 'Patologia Forense',
                'image' => 'https://images.unsplash.com/photo-1595590424283-b8f17842773f?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">Armas de ar comprimido (popularmente conhecidas como espingardas de chumbinho de pressão) são frequentemente empregadas em disparos covardes contra gatos e cães em perímetros urbanos. Por utilizarem projéteis metálicos de formato diabolo, causam perfurações viscerais e fraturas ósseas graves.</p>

<h3 class="mt-4">Diferenças Periciais entre Armas de Fogo e Armas de Pressão</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Ausência de Resíduos de Pólvora (GSR):</strong> O disparo de ar comprimido não produz queimaduras por gases nem zonas de tatuagem de pólvora.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Morfologia e Calibre do Chumbinho:</strong> Projéteis de 4.5mm ou 5.5mm são recuperados intactos ou deformados pelo impacto em tecidos ósseos, permitindo ao perito apontar o tipo exato de munição utilizada no crime.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Wenzlow N et al.</strong> Feasibility of using tissue autolysis to estimate the postmortem interval in horses. <em>J Vet Diagn Invest</em>, 2021;33(5):825-833. DOI: 10.1177/10406387211021865.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/34109897/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 34109897)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Abuso Sexual e Zoofilia em Animais: Protocolos Periciais de Coleta de Evidências e DNA Humano',
                'slug' => 'abuso-sexual-zoofilia-animais-protocolos-periciais-coleta-dna-humano',
                'summary' => 'A comprovação pericial de violência sexual contra animais exige protocolos rigorosos de exame genital/retal, recuperação de espermatozoides e perfil genético (DNA) do agressor humano.',
                'category' => 'Medicina Veterinária Legal',
                'image' => 'https://images.unsplash.com/photo-1548767797-d8c844163c4c?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">O abuso sexual contra animais (zoofilia / bestialidade) constitui modalidade gravíssima de crueldade e crime previsto no Código Penal e legislação de proteção animal. O perito em medicina veterinária legal desempenha papel chave na documentação médica e coleta de vestígios biológicos que identificarão o autor humano.</p>

<h3 class="mt-4">Protocolos Obrigatórios de Exame Forense</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Coleta Imediata de Swabs Vulvares/Vaginais/Ano-Retais:</strong> Coleta com swabs estéreis para pesquisa citológica de espermatozoides humanos e extração de DNA nuclear humano.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Registro de Lacerações e Fissuras Esfincterianas:</strong> Mensuração milimétrica de rupturas de mucosa, hematomas perianais e perda do tônus esfincteriano.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Pesquisa de Fosfatase Ácida Prostática e PSA:</strong> Testes bioquímicos laboratoriais confirmatórios de fluido seminal humano.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Stern AW et al.</strong> Veterinary Forensic Pathology of Animal Sexual Abuse. <em>Vet Pathol</em>, 2016;53(5):1057-66. DOI: 10.1177/0300985816643574.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27169881/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27169881)
    </a>
</blockquote>',
                'published_at' => now(),
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
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Riet-Correa F et al.</strong> Toxic plants affecting livestock in South America: Review of epidemiology, diagnosis, control, economic impact and implications to human health. <em>Toxicon</em>, 2026;273():108999. DOI: 10.1016/j.toxicon.2026.108999.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/41565163/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 41565163)
    </a>
</blockquote>',
                'published_at' => now(),
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
<p>Se o corpo de um animal foi desovado em um terreno baldio, mas seus pelos contêm pólen de espécies raras cultivadas exclusivamente no jardim da casa do principal suspeito, a botânica forense estabelece o elo probatório definitivo para a investigação criminal.</p>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>von Konrat M et al.</strong> Silent witness: a moss provides important evidence in solving a cemetery crime. <em>Forensic Sci Res</em>, 2025;10(4):owaf038. DOI: 10.1093/fsr/owaf038.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/41809942/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 41809942)
    </a>
</blockquote>',
                'published_at' => now(),
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
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Ince MA et al.</strong> Next-generation bloodstain pattern analysis: methodological gaps, digital advances, and forensic admissibility. <em>Leg Med (Tokyo)</em>, 2026;85():102937. DOI: 10.1016/j.legalmed.2026.102937.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/42526128/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 42526128)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Superdosagem e Erros de Medicação em Clínicas Veterinárias: Análise Pericial de Toxicidade Iatrogênica',
                'slug' => 'superdosagem-erros-medicacao-clinicas-veterinarias-toxicidade-iatrogenica',
                'summary' => 'Erros no cálculo de dosagem, administração de fármacos contraindicados (como Paracetamol para felinos) e confusão de vias geram processos judiciais por erro veterinário.',
                'category' => 'Direito Médico Veterinário',
                'image' => 'https://images.unsplash.com/photo-1584017911766-d451b3d0e843?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">A administração incorreta de fármacos e erros de posologia são responsáveis por uma parcela expressiva das queixas de erro médico veterinário em clínicas e hospitais. A perícia veterinária forense e a farmacologia realizam a auditoria técnica das prescrições e dosagens administradas.</p>

<h3 class="mt-4">Erros Farmacológicos mais Frequentes na Prática Veterinária</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Intoxicação por Paracetamol em Gatos:</strong> A deficiência felina na enzima glucuronil-transferase provoca meta-hemoglobinemia fulminante e necrose hepática com doses mínimas.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Superdosagem de Quimioterápicos e Anestésicos:</strong> Erros de vírgula decimal ou cálculo ponderal que levam a colapso circulatório e mielossupressão severa.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Uso Concomitante de AINEs e Corticoides:</strong> Associação perigosa que induz perfuração e hemorragia gástrica aguda.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>McMillan M</strong> Using Functional Resonance Analysis Methodology to identify potential safety improvements in the process of administering medications by infusion in a veterinary hospital. <em>Front Vet Sci</em>, 2026;13():1781437. DOI: 10.3389/fvets.2026.1781437.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/41982468/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 41982468)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'Microtomografia Computadorizada (Micro-CT) na Perícia Forense: Detecção de Microfraturas e Fragmentos Metálicos',
                'slug' => 'microtomografia-micro-ct-pericia-forense-microfraturas-fragmentos-metalicos',
                'summary' => 'O Micro-CT oferece resolução micrométrica capaz de identificar microfissuras em ossos e marcas de ferramentas invisíveis a radiografias comuns.',
                'category' => 'Patologia Forense',
                'image' => 'https://images.unsplash.com/photo-1516549655169-df83a0774514?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">A <strong>Microtomografia Computadorizada (Micro-CT)</strong> representa o ápice da imaginologia forense de alta definição. Enquanto a tomografia clínica convencional possui resolução na escala de milímetros, o Micro-CT atinge resolução na faixa de micrômetros, permitindo a visualização tridimensional da microarquitetura óssea.</p>

<h3 class="mt-4">Aplicações na Medicina Veterinária Forense</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Identificação de Microfraturas Trabeculares:</strong> Diagnóstico precoce de estresse mecânico e fissuras ósseas microscópicas decorrentes de agressões repetitivas.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Análise de Marcas de Ferramentas em Ossos:</strong> Revela o perfil de corte exato de serras, machados e facas em casos de esquartejamento ou desossa clandestina de animais.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Pietras K et al.</strong> Diagnostic imaging findings in animal abuse cases referred to the University of Veterinary Medicine Vienna, Austria (2014-2023). <em>Forensic Sci Int</em>, 2026;388():113076. DOI: 10.1016/j.forsciint.2026.113076.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/42531994/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 42531994)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'DNA Forense em Rinhas de Cães (Dog Fighting): Individualização Genética e Vínculo de Parentesco',
                'slug' => 'dna-forense-rinhas-de-caes-dog-fighting-individualizacao-genetica',
                'summary' => 'A genética forense veterinária traça o perfil de DNA de cães apreendidos em rinhas e confronta com o sangue encontrado em ringues e materiais de treinamento.',
                'category' => 'Medicina Veterinária Legal',
                'image' => 'https://images.unsplash.com/photo-1587300003388-59208cc962cb?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">A prática de rinhas de cães (combates forçados entre animais da raça Pitbull e derivados) é crime gravíssimo de maus-tratos. Em operações policiais, a genética forense veterinária é essencial para conectar os organizadores aos animais feridos e comprovar a atividade criminosa organizada.</p>

<h3 class="mt-4">Técnicas de Genética Forense Canina</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Painéis de Microssatélites (STRs Caninos):</strong> Determinação do perfil de DNA individual de cada cão com probabilidade de coincidência aleatória menor que 1 em 1 bilhão.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Confronto de Sangue nos Ringues:</strong> Coleta de manchas de sangue no carpete e paredes da rinha para provar quais cães foram colocados em combate no local.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Espejo E et al.</strong> Case report: First criminal conviction of dog fighting in Brazil: an international network organization. <em>Front Vet Sci</em>, 2023;10():1327436. DOI: 10.3389/fvets.2023.1327436.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/38260207/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 38260207)
    </a>
</blockquote>',
                'published_at' => now(),
            ],
            [
                'title' => 'A Teoria do Elo (The Link): Maus-Tratos a Animais como Sentinela de Violência Doméstica',
                'slug' => 'teoria-do-elo-the-link-maus-tratos-animais-sentinela-violencia-domestica',
                'summary' => 'Pesquisas criminológicas comprovam a correlação direta entre crueldade contra animais de estimação e violência doméstica contra mulheres, crianças e idosos.',
                'category' => 'Medicina Veterinária Legal',
                'image' => 'https://images.unsplash.com/photo-1544568100-847a948585b9?q=80&w=600&auto=format&fit=crop',
                'author_id' => $user->id,
                'content' => '<p class="lead">A <strong>Teoria do Elo (The Link)</strong> é o conceito criminológico e forense amplamente reconhecido pela literatura científica que demonstra a intrínseca conexão entre a crueldade praticada contra animais de companhia e a violência interpessoal (violência doméstica, abuso infantil e agressão contra vulneráveis).</p>

<h3 class="mt-4">Implicações Jurídicas e Sociais da Teoria do Elo</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Coação Psicológica e Ameaça:</strong> Agressores frequentemente ameaçam ou espancam o animal de estimação da família como instrumento de controle e tortura psicológica contra a mulher ou crianças.</li>
    <li class="list-group-item bg-transparent px-0"><strong>O Laudo Pericial como Prova Cruzada:</strong> A comprovação técnico-pericial de maus-tratos ao pet tem sido utilizada em juízo para fundamentar medidas protetivas de urgência (Lei Maria da Penha) e decretação de prisões preventivas.</li>
</ul>\n\n<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Lockwood R et al.</strong> Animal Abuse and Interpersonal Violence: The Cruelty Connection and Its Implications for Veterinary Pathology. <em>Vet Pathol</em>, 2016;53(5):910-8. DOI: 10.1177/0300985815626575.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/26936222/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 26936222)
    </a>
</blockquote>',
                'published_at' => now(),
            ]
        ];

        foreach ($seoPosts as $seoPost) {
            Post::updateOrCreate(
                ['slug' => $seoPost['slug']],
                $seoPost
            );
        }

        try {
            \App\Helpers\SitemapGenerator::generate();
        } catch (\Exception $e) {
            // Suppress error if public paths are not writable during database seeding
        }
    }
}