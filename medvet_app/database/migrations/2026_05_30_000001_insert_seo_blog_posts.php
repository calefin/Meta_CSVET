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
        // Get the author ID if the user exists
        $author = DB::table('users')->where('email', 'ascalefi@gmail.com')->first();
        $authorId = $author ? $author->id : null;
        $now = now();

        $posts = [
            [
                'title' => 'Erro Veterinário: Como provar negligência, imprudência ou imperícia?',
                'slug' => 'erro-veterinario-como-provar-negligencia-imprudencia-impericia',
                'summary' => 'Identificar um erro em procedimento clínico ou cirúrgico exige análise técnica criteriosa. Saiba como estruturar a prova de erro veterinário sob a ótica da negligência, imprudência ou imperícia.',
                'category' => 'Direito Médico Veterinário',
                'image' => 'https://images.unsplash.com/photo-1584132967334-10e028bd69f7?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">A suspeita de erro veterinário em clínicas ou hospitais gera enorme angústia para tutores e complexidade jurídica para advogados. Afinal, como comprovar que houve falha na prestação do serviço?</p>
<p>No ordenamento jurídico brasileiro, a responsabilidade civil do médico veterinário, em regra, é subjetiva. Isso significa que, para haver o dever de indenizar, é indispensável comprovar a culpa do profissional em uma de suas três vertentes: negligência, imprudência ou imperícia.</p>
<h3 class="mt-4">1. Negligência</h3>
<p>Ocorre quando o profissional deixa de agir com a atenção e cuidado devidos. Exemplos comuns na medicina veterinária include:</p>
<ul>
    <li>Falta de monitoramento adequado pós-operatório.</li>
    <li>Deixar de prescrever exames pré-anestésicos obrigatórios.</li>
    <li>Abandono de paciente em internação sem assistência contínua.</li>
</ul>
<h3 class="mt-4">2. Imprudência</h3>
<p>Caracteriza-se pela conduta precipitada, onde o profissional age sem a devida cautela, assumindo riscos desnecessários. Exemplos:</p>
<ul>
    <li>Realizar uma cirurgia complexa sem equipe de apoio necessária.</li>
    <li>Aplicar dose inadequada de medicamento sem realizar cálculo ponderal.</li>
    <li>Dar alta médica para animal com sinais vitais instáveis.</li>
</ul>
<h3 class="mt-4">3. Imperícia</h3>
<p>Refere-se à falta de habilidade técnica ou conhecimento científico para a realização de determinado procedimento. Exemplos:</p>
<ul>
    <li>Realizar cirurgia ortopédica sem ter a qualificação necessária.</li>
    <li>Interpretar exames radiológicos ou laboratoriais de forma incorreta por falta de treinamento.</li>
</ul>
<h3 class="mt-4">Como obter a prova técnica?</h3>
<p>O prontuário médico-veterinário é um documento obrigatório e constitui a principal prova documental. Além disso, a contratação de uma <strong>auditoria de prontuário</strong> e <strong>assistência técnica veterinária</strong> é essencial para traduzir termos complexos em provas sólidas no processo judicial.</p>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Necropsia Veterinária Forense: Quando é necessária e como solicitar?',
                'slug' => 'necropsia-veterinaria-forense-quando-e-necessaria-como-solicitar',
                'summary' => 'A necropsia forense veterinária é o principal instrumento de elucidação da causa da morte em casos judiciais. Entenda os procedimentos e sua importância para o nexo causal.',
                'category' => 'Patologia Forense',
                'image' => 'https://images.unsplash.com/photo-1518152006812-edab29b069ac?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">Quando um animal de estimação ou de produção falece de forma súbita, suspeita ou após um procedimento médico, a necropsia veterinária forense é a ferramenta ideal para determinar a real causa da morte.</p>
<p>Diferente de uma necropsia patológica convencional, a necropsia forense segue protocolos rígidos para garantir a validade da prova em juízo, observando conceitos de cadeia de custódia.</p>
<h3 class="mt-4">Casos em que a Necropsia Forense é Indicada</h3>
<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0"><strong>Suspeita de Intoxicação:</strong> Morte rápida com sinais de espasmos, salivação ou hemorragias.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Maus-tratos e Violência Física:</strong> Sinais de trauma físico, asfixia, queimaduras ou inanição.</li>
    <li class="list-group-item bg-transparent px-0"><strong>Suspeita de Erro Médico:</strong> Óbito durante ou logo após cirurgias e procedimentos clínicos.</li>
</ul>
<h3 class="mt-4">Como Preservar o Corpo para Análise?</h3>
<p>Para garantir que os achados patológicos e exames toxicológicos não sejam comprometidos, siga as seguintes diretrizes:</p>
<ol>
    <li><strong>Refrigeração:</strong> O corpo deve ser mantido sob refrigeração (entre 2°C e 4°C). <strong>Nunca congele o cadáver</strong>, pois a formação de cristais de gelo rompe as células, impossibilitando a análise histopatológica adequada.</li>
    <li><strong>Agilidade:</strong> O exame deve ser realizado o mais rápido possível para evitar a autólise dos tecidos.</li>
</ol>
<h3 class="mt-4">O Laudo Necroscópico</h3>
<p>O laudo emitido por um patologista forense veterinário detalha as lesões encontradas e fundamenta cientificamente a causa da morte, servindo de base técnica para investigações policiais e processos civis.</p>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Intoxicação de Animais: O que fazer e como agir juridicamente?',
                'slug' => 'envenenamento-de-animais-o-que-fazer-como-agir-juridicamente',
                'summary' => 'A suspeita de intoxicação de pets é frequente e dolorosa. Conheça as providências jurídicas necessárias e como coletar evidências científicas cruciais para punir os responsáveis.',
                'category' => 'Toxicologia Forense',
                'image' => 'https://images.unsplash.com/photo-1601758228041-f3b2795255f1?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">A intoxicação de cães e gatos é crime previsto na Lei de Crimes Ambientais (Lei nº 9.605/98). Se você suspeita que seu animal foi intencionalmente envenenado, agir rápido e de forma metódica é fundamental.</p>
<p>Muitas denúncias de intoxicação são arquivadas por falta de provas materiais. Veja como garantir que o caso seja investigado com rigor técnico.</p>
<h3 class="mt-4">Passo a Passo Imediato</h3>
<ol>
    <li class="mb-2"><strong>Atendimento Emergencial:</strong> Priorize a vida do animal. Solicite ao veterinário clínico um prontuário detalhado com os sintomas e o tratamento instituído.</li>
    <li class="mb-2"><strong>Preservação de Evidências:</strong> Colete restos de alimentos suspeitos, sachês, ou substâncias estranhas encontradas próximas ao animal. Utilize luvas e sacos limpos.</li>
    <li class="mb-2"><strong>Necropsia e Exame Toxicológico:</strong> Se o animal falecer, encaminhe o corpo imediatamente para necropsia forense veterinária com coleta de conteúdo estomacal e tecidos para análise laboratorial.</li>
</ol>
<h3 class="mt-4">Providências Jurídicas</h3>
<p>Com o laudo de necropsia e o resultado toxicológico positivo em mãos, registre um Boletim de Ocorrência na Delegacia de Polícia Civil ou na Delegacia Especializada de Proteção Animal. O Ministério Público poderá dar início à ação penal contra o responsável.</p>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Laudo Pericial vs. Parecer Técnico: Entenda a diferença processual',
                'slug' => 'laudo-pericial-vs-parecer-tecnico-entenda-a-diferenca-processual',
                'summary' => 'Em processos de erro veterinário ou maus-tratos, o laudo pericial do perito do juiz e o parecer técnico do assistente possuem funções distintas. Saiba como utilizá-los a seu favor.',
                'category' => 'Assistência Técnica',
                'image' => 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">Muitas pessoas confundem os documentos emitidos por peritos judiciais e assistentes técnicos. Contudo, na prática jurídica, eles desempenham papéis diferentes e complementares.</p>
<p>Entenda a função de cada um em uma disputa judicial envolvendo a saúde ou vida de um animal.</p>
<h3 class="mt-4">1. Laudo Pericial</h3>
<p>O Laudo Pericial é elaborado pelo <strong>Perito Judicial</strong>, profissional nomeado pelo juiz que atua de forma totalmente imparcial. Suas conclusões auxiliam o magistrado a julgar o caso com base técnica.</p>
<h3 class="mt-4">2. Parecer Técnico</h3>
<p>O Parecer Técnico é elaborado pelo <strong>Assistente Técnico Veterinário</strong>, contratado por uma das partes (tutor ou clínica). O assistente atua garantindo o contraditório, fiscalizando o trabalho do perito judicial e contestando cientificamente falhas de método ou interpretação do laudo oficial.</p>
<blockquote class="blockquote my-4 p-3 bg-light border-start border-4 border-primary">
    O Laudo visa a neutralidade da prova para o Juízo. O Parecer assegura que o direito de defesa técnico-científico das partes seja plenamente exercido.
</blockquote>
<h3 class="mt-4">Por que contratar um Assistente Técnico?</h3>
<p>Contar com um veterinário especialista em medicina veterinária legal garante que os quesitos formulados ao perito judicial sejam estratégicos e que o laudo do juízo receba o devido escrutínio científico.</p>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Maus-tratos a Animais: Como provar perante a lei?',
                'slug' => 'maus-tratos-a-animais-como-provar-perante-a-lei',
                'summary' => 'A comprovação de maus-tratos a animais requer laudos que comprovem sofrimento físico ou psicológico. Entenda o papel da perícia veterinária nesses casos.',
                'category' => 'Medicina Veterinária Legal',
                'image' => 'https://images.unsplash.com/photo-1544568100-847a948585b9?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">A denúncia de maus-tratos contra cães, gatos e outros animais ganhou força com a Lei Sansão (Lei nº 14.064/20), que aumentou a pena de reclusão para maus-tratos de cães e gatos. Porém, a punição depende de provas incontestáveis.</p>
<p>Para caracterizar juridicamente os maus-tratos, a perícia médica veterinária utiliza critérios científicos bem estabelecidos.</p>
<h3 class="mt-4">Critérios Científicos de Avaliação</h3>
<p>O perito veterinário avalia o bem-estar animal com base nas Cinco Liberdades:</p>
<ul>
    <li><strong>Nutricional:</strong> Livre de fome e sede.</li>
    <li><strong>Ambiental:</strong> Livre de desconforto e abrigo inadequado.</li>
    <li><strong>Saúde:</strong> Livre de dor, ferimentos e doenças.</li>
    <li><strong>Comportamental:</strong> Livre para expressar comportamento natural.</li>
    <li><strong>Psicológica:</strong> Livre de medo, estresse e angústia.</li>
</ul>
<h3 class="mt-4">Como Documentar e Denunciar?</h3>
<p>Tire fotos claras, grave vídeos, guarde receitas de tratamentos e chame testemunhas. Diante de suspeita grave, solicite uma perícia de bem-estar animal para documentar o estado do pet antes que a situação seja descaracterizada.</p>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Exumação de Animais: É possível realizar perícia após o enterro?',
                'slug' => 'exumacao-de-animais-e-possivel-realizar-pericia-apos-o-enterro',
                'summary' => 'A exumação pet é um procedimento técnico que visa recuperar vestígios após o sepultamento. Saiba como a ciência forense identifica intoxicações e lesões tardias.',
                'category' => 'Patologia Forense',
                'image' => 'https://images.unsplash.com/photo-1448375240586-882707db888b?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">Quando um animal é sepultado sob suspeita de óbito criminoso, muitos tutores acreditam que a chance de produzir provas se perdeu. A exumação técnica prova o contrário.</p>
<p>Mesmo após semanas ou meses do sepultamento, a exumação, coordenada por patologista veterinário forense, pode trazer respostas cruciais para o processo judicial.</p>
<h3 class="mt-4">O que é analisado na Exumação?</h3>
<ul>
    <li><strong>Integridade Óssea:</strong> Fraturas e traumas mecânicos permanecem identificáveis nos ossos mesmo após a decomposição total dos tecidos moles.</li>
    <li><strong>Estudos Toxicológicos:</strong> Substâncias estáveis (como certos carbamatos e metais pesados) podem ser extraídas do solo adjacente, pelos ou resíduos de órgãos preservados.</li>
</ul>
<h3 class="mt-4">A Importância da Cadeia de Custódia</h3>
<p>A exumação deve ser documentada passo a passo (fotos, vídeos, coleta de controle de solo) para evitar contestações de contaminação ambiental pela defesa no processo legal.</p>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Responsabilidade Civil de Clínicas Veterinárias em Erros Profissionais',
                'slug' => 'responsabilidade-civil-de-clinicas-veterinarias-em-erros-profissionais',
                'summary' => 'Entenda como a justiça avalia a responsabilidade civil das clínicas e hospitais veterinários em casos de erro de procedimento de sua equipe.',
                'category' => 'Direito Médico Veterinário',
                'image' => 'https://images.unsplash.com/photo-1581888227599-779811939961?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">Diferente do profissional autônomo, clínicas e hospitais veterinários podem responder de forma objetiva pelos danos causados por seus funcionários ou prepostos.</p>
<p>Esse entendimento baseia-se no Código de Defesa do Consumidor (CDC), que classifica as clínicas como prestadoras de serviços de consumo.</p>
<h3 class="mt-4">Responsabilidade Objetiva da Clínica</h3>
<p>Se o veterinário contratado pela clínica comete um erro decorrente de imperícia ou negligência, o hospital pode ser condenado a indenizar o tutor material e moralmente, independentemente da demonstração direta de culpa da gerência da empresa.</p>
<h3 class="mt-4">Medidas Preventivas para Clínicas</h3>
<p>Para mitigar riscos de processos judiciais infundados, as clínicas devem:</p>
<ol>
    <li>Manter prontuários legíveis e atualizados minuto a minuto.</li>
    <li>Solicitar termos de consentimento livre e esclarecido (TCLE) assinados para anestesias e cirurgias.</li>
    <li>Contar com auditoria técnica preventiva para avaliar processos operacionais internos.</li>
</ol>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Como age a Toxicologia Forense na Identificação de Venenos em Pets',
                'slug' => 'como-age-a-toxicologia-forense-na-identificacao-de-venenos-em-pets',
                'summary' => 'A toxicologia forense identifica agentes químicos e venenos que causaram o óbito de animais. Conheça as técnicas de cromatografia e espectrometria aplicadas.',
                'category' => 'Toxicologia Forense',
                'image' => 'https://images.unsplash.com/photo-1576086213369-97a306d36557?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">Para provar juridicamente que um cão ou gato morreu por intoxicação, não basta a suspeita clínica; a ciência exige a identificação química exata da molécula tóxica.</p>
<p>É aqui que atua a Toxicologia Forense, associando achados de necropsia a exames laboratoriais altamente sofisticados.</p>
<h3 class="mt-4">Principais Substâncias Investigadas</h3>
<p>Os venenos mais comumente isolados na perícia veterinária no Brasil incluem:</p>
<ul>
    <li><strong>Carbamatos (Chumbinho):</strong> Inibidores da acetilcolinesterase altamente letais.</li>
    <li><strong>Raticidas Anticoagulantes:</strong> Provocam hemorragias severas e morte lenta.</li>
    <li><strong>Herbicidas (Paraquat):</strong> Causam fibrose pulmonar progressiva e severa.</li>
</ul>
<h3 class="mt-4">Tecnologias Analíticas</h3>
<p>Os laboratórios forenses empregam técnicas como Cromatografia Gasosa ou Líquida acoplada à Espectrometria de Massas (GC-MS / LC-MS). Esses equipamentos identificam a "assinatura molecular" do veneno com precisão absoluta, eliminando margens para dúvidas no tribunal.</p>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Assistente Técnico Veterinário: O defensor da verdade científica em juízo',
                'slug' => 'assistente-tecnico-veterinario-o-defensor-da-verdade-cientifica-em-juizo',
                'summary' => 'Em litígios complexos, o assistente técnico veterinário traduz e orienta advogados sobre a viabilidade científica do caso. Saiba por que ele é indispensável.',
                'category' => 'Assistência Técnica',
                'image' => 'https://images.unsplash.com/photo-1521791136064-7986c2920216?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">Processos envolvendo morte de animais ou erros médicos dependem de perícia técnica. No entanto, advogados e juízes não possuem formação em medicina veterinária para debater a fundo diagnósticos ou técnicas cirúrgicas.</p>
<p>O assistente técnico veterinário atua como o elo de tradução e rigor científico necessário para o sucesso do processo.</p>
<h3 class="mt-4">Atividades do Assistente Técnico no Processo</h3>
<ol>
    <li><strong>Análise de Viabilidade:</strong> Avalia prontuários antes do início da ação para evitar que o cliente processe sem chance real de vitória.</li>
    <li><strong>Formulação de Quesitos:</strong> Elabora perguntas estratégicas e científicas que o perito nomeado pelo juiz é obrigado a responder no laudo.</li>
    <li><strong>Acompanhamento de Exames:</strong> Acompanha necropsias ou vistorias clínicas presenciais, assegurando a fidelidade metodológica.</li>
    <li><strong>Parecer de Impugnação:</strong> Elabora o parecer técnico contestando eventuais equívocos científicos presentes no laudo pericial.</li>
</ol>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Protocolos de Perícia Veterinária em Casos de Maus-tratos',
                'slug' => 'protocolos-de-pericia-veterinaria-em-casos-de-maus-tratos',
                'summary' => 'A perícia científica de maus-tratos deve seguir normas e escalas internacionais reconhecidas. Saiba quais protocolos são empregados atualmente.',
                'category' => 'Medicina Veterinária Legal',
                'image' => 'https://images.unsplash.com/photo-1516733725897-1aa73b87c8e8?q=80&w=600&auto=format&fit=crop',
                'author_id' => $authorId,
                'content' => '<p class="lead">Para embasar a denúncia de crimes de maus-tratos a animais, a avaliação pericial veterinária não pode ser meramente subjetiva. Ela deve seguir rigorosos protocolos validados cientificamente.</p>
<p>Atualmente, peritos veterinários utilizam ferramentas nacionais e internacionais que pontuam e comprovam o sofrimento do animal.</p>
<h3 class="mt-4">Protocolos Comumente Empregados</h3>
<ul>
    <li><strong>Escala de Avaliação de Maus-Tratos de Valadez:</strong> Classifica as lesões físicas, nutricionais e sanitárias em níveis de gravidade.</li>
    <li><strong>Protocolo de Avaliação de Bem-Estar Animal (FMEA):</strong> Pontua os indicadores físicos e de comportamento para atestar a violação sistemática das liberdades básicas do animal.</li>
</ul>
<h3 class="mt-4">A Importância do Laudo Médico Veterinário Legal</h3>
<p>O documento emitido sob esses protocolos oferece segurança jurídica absoluta ao processo penal ou civil, descrevendo com clareza a materialidade do crime de maus-tratos.</p>',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
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
        //
    }
};
