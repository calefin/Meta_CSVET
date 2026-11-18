<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use Illuminate\Database\Seeder;

/**
 * One-off reform of the "Formação Tradicional" course content:
 * - Rewrites the Iwure Òwúró lesson in original wording (the previous
 *   version was a raw OCR dump of third-party teaching material).
 * - Splits the old combined "Obi e Osé" module into three dedicated
 *   modules: Osé, Consulta com Obi, Consulta com Orogbo — using the
 *   priest's own "Técnicas básicas" material (his own authorship) for
 *   Obi, simplified to the basic 5-position system for Orogbo.
 */
class ObiOrogboReformSeeder extends Seeder
{
    public function run(): void
    {
        $course = Course::where('title', 'Formação Tradicional e Práticas de Ifá')->first();

        if (! $course) {
            return;
        }

        $this->rewriteIwureOwuro($course);
        $this->splitObiOseModule($course);
        $this->reformMitologiaETerminologia($course);
    }

    private function rewriteIwureOwuro(Course $course): void
    {
        $module = CourseModule::where('course_id', $course->id)->where('title', 'Reza Diária')->first();

        if (! $module) {
            return;
        }

        $lesson = Lesson::where('module_id', $module->id)->first();

        if (! $lesson) {
            return;
        }

        $lesson->update([
            'title' => 'Iwure Òwúró — a reza da manhã',
            'content' => $this->iwureOwuroContent(),
        ]);
    }

    private function splitObiOseModule(Course $course): void
    {
        // Only runs once: renames the old combined module and drops the
        // duplicate lesson the first time this seeder executes. On later
        // re-runs (used to refresh wording) this block is a no-op because
        // the old module title no longer exists.
        $oldModule = CourseModule::where('course_id', $course->id)
            ->where('title', 'Como consultar com Obi e fazer Osé')
            ->first();

        if ($oldModule) {
            $oldModule->update(['title' => 'Osé Ifá']);
            Lesson::where('module_id', $oldModule->id)
                ->where('title', '!=', 'Ose ifa portugues')
                ->delete();
        }

        $order = CourseModule::where('course_id', $course->id)->max('order') + 1;

        $obiModule = CourseModule::firstOrCreate(
            ['course_id' => $course->id, 'title' => 'Consulta com Obi'],
            ['order' => $order++]
        );

        Lesson::updateOrCreate(
            ['module_id' => $obiModule->id, 'title' => 'Como consultar com Obi'],
            ['type' => 'text', 'content' => $this->obiContent(), 'order' => 0]
        );

        $orogboModule = CourseModule::firstOrCreate(
            ['course_id' => $course->id, 'title' => 'Consulta com Orogbo'],
            ['order' => $order]
        );

        Lesson::updateOrCreate(
            ['module_id' => $orogboModule->id, 'title' => 'Como consultar com Orogbo'],
            ['type' => 'text', 'content' => $this->orogboContent(), 'order' => 0]
        );
    }

    private function p(string $text): string
    {
        return "<p class='mb-3 font-serif leading-relaxed text-ebony/90'>{$text}</p>";
    }

    private function h(string $text): string
    {
        return "<h3 class='mt-6 mb-2 font-display text-lg text-ifa-green'>{$text}</h3>";
    }

    private function iwureOwuroContent(): string
    {
        $html = $this->h('Por que rezamos toda manhã');
        $html .= $this->p('Antes de falar com qualquer pessoa, quem caminha com Ifá fala primeiro com o sagrado. Essa é a base do Iwure Òwúró — a saudação e súplica da manhã. Não é um ritual de adoração; adoração tem seu próprio momento, que é o Osé, tratado em outro módulo. O Iwure é mais simples e mais constante: é o hábito diário que mantém viva a relação entre o iniciado e as forças que o protegem.');
        $html .= $this->p('Sem essa base diária, a prática vira só ritual de ocasião, sem o conteúdo espiritual que sustenta o ritual. O Iwure Òwúró é o que preenche esse espaço todos os dias — não é opcional, é rotina.');

        $html .= $this->h('A hora certa');
        $html .= $this->p('O ideal é rezar ao nascer do sol, na primeira luz do dia — momento em que, segundo a tradição, a natureza desperta e a súplica humana é ouvida com mais força. A vida moderna nem sempre permite isso à risca; o que importa de fato é que o Iwure seja a primeira coisa que você faz ao despertar, antes de conversar com qualquer pessoa, antes do telefone, antes de qualquer outra obrigação do dia.');
        $html .= $this->p('Vá direto ao seu Òrìṣà, ou fique mesmo ao lado da cama. Quem ainda não tem assentamento não está impedido de rezar — o que importa é dirigir a primeira fala do dia ao sagrado, antes de dirigi-la a qualquer pessoa.');

        $html .= $this->h('A postura de cada energia');
        $html .= $this->p('Cada Òrìṣà é reverenciado numa postura própria:');
        $html .= $this->p('<strong>Ifá</strong> — Ikunlẹ̀ (de joelhos)<br><strong>Èṣù</strong> — Dìde (em pé)<br><strong>Orí</strong> — Jókòó (sentado)');

        $html .= $this->h('A sequência do Iwure');
        $html .= $this->p('1. <strong>Ibà</strong> — reverência inicial a Olódùmarè, ao seu Orí, a Èṣù e aos Òrìṣà que você venera.<br>2. <strong>Iwure Orí</strong> — súplica dirigida à própria cabeça, pedindo que o esforço do dia não seja em vão.<br>3. <strong>Agradecimento pelo dia que se inicia</strong> — um verso de boas-vindas ao novo dia (Ojúmọ́).<br>4. <strong>Oriki</strong> — louvação a Ifá e aos Òrìṣà da sua casa.<br>5. <strong>Canto</strong> — se desejar, encerre com uma música simples de celebração ao novo dia.');
        $html .= $this->p('O número e a escolha exatas das frases variam de pessoa para pessoa, conforme o conhecimento de cada um. O que não muda é a ordem: primeiro o sagrado, depois o mundo.');

        $html .= $this->h('Cinco palavras que não significam a mesma coisa');
        $html .= $this->p('É comum confundir estes termos — vale fixá-los:');
        $html .= $this->p('<strong>Àdúrà</strong> — súplica livre, pedido em qualquer idioma.<br><strong>Iwure</strong> — recitação tirada de um Odù Ifá, com estrutura fixa, voltada a trazer Ire e afastar Ibi.<br><strong>Òfò</strong> — encantamento que direciona axé a um ato ou elemento; uma espécie de comando.<br><strong>Oriki</strong> — louvação poética que conta a vida, a forma, o temperamento e os feitos de um Òrìṣà.<br><strong>Orin</strong> — canção.');

        $html .= $this->h('Ibà — exemplo');
        $html .= $this->p("Iba Olódùmarè<br>Ọba origun mẹ́rin ayé<br>Ibà àtìwọ̀ Òjò<br>Ibà àtìwọ̀ Òòrùn<br>Ibà Irúnmọlẹ̀ ojú kòtún<br>Ibà Irúnmọlẹ̀ ojú kòsì<br>Ibà àwọn Òrìṣà<br>Ibà Egúngún ará ọ̀run");
        $html .= $this->p('<em>Tradução: Reverência a Olódùmarè, rei dos quatro cantos da terra. Reverência ao dia, reverência ao sol, reverência às divindades à direita e à esquerda, reverência aos Òrìṣà, reverência aos ancestrais.</em>');

        $html .= $this->h('Iwure Orí — exemplo');
        $html .= $this->p('Orí, pèlé,<br>Àtètè níran, àtètè gbé ni kòòsà.<br>Kò sí Òrìṣà tíí dá nií gbé lẹ́yìn orí ẹni<br>Mo júbà, mo fi orí balẹ̀ f\'Olódùmarè.<br>Ire gbogbo má wá bá mi, Orí mi dá mi dáyé.');
        $html .= $this->p('<em>Tradução: Orí, eu te saúdo. Você que abençoa os seus rapidamente. Nenhum Òrìṣà abençoa alguém sem a permissão da cabeça. Eu reverencio, entrego minha cabeça a Olódùmarè. Que todas as coisas boas venham a mim — meu Orí é quem me dá vida.</em>');

        $html .= $this->h('Saudação ao novo dia');
        $html .= $this->p('Ojúmọ́ mọ́, òyẹlá o<br>Àwa té\'wọ́ gbà á<br>Òwúró o');
        $html .= $this->p('<em>Tradução: O dia amanheceu com vitalidade. Estendemos as mãos para receber a bênção desta manhã.</em>');

        $html .= $this->h('Encerrando com Oriki');
        $html .= $this->p('Você pode fechar com qualquer Oriki que conheça do seu Òrìṣà ou de Ifá. Um exemplo curto e tradicional de Oriki a Ifá:');
        $html .= $this->p('Òrúnmìlà, Ajànà<br>Ifá Olókun, asọrọ̀ dáyọ̀<br>Ẹlẹ́rìí Ìpín, Igbákejì Olódùmarè<br>Èlà, à bọ̀rú, à bọ̀yè');
        $html .= $this->p('<em>Tradução: Òrúnmìlà, o viajante. Ifá, vasto como o oceano, cuja palavra traz alegria. Testemunha do destino, representante de Olódùmarè. Èlà, que assim seja aceito, que assim se manifeste.</em>');

        $html .= $this->h('Por que aprender Yorùbá, mesmo que o básico');
        $html .= $this->p('Òrìṣà entende qualquer idioma — mas o Yorùbá carrega àṣẹ próprio. Rezar nos termos originais, ainda que aos poucos, fortalece a reza e mantém viva a ligação entre quem reza hoje e a linhagem que rezou antes. Não é preciso fluência: é preciso começar.');

        return $html;
    }

    private function obiContent(): string
    {
        $html = $this->p('Este módulo reúne a forma como consulto e ensino meus iniciados a consultar o Obi — base de comunicação direta e cotidiana com os Òrìṣà.');

        $html .= $this->h('Antes de lançar');
        $html .= $this->p('Reúna, antes do primeiro lançamento:');
        $html .= $this->p('<strong>Um copo de água fresca</strong> — oferenda que limpa, refresca e traz paz aos implementos, ao consulente, ao espaço ritual, aos ancestrais e aos Òrìṣà. A calma é valorizada na cultura Yorùbá; a água fria abre o ritual em equilíbrio.<br><strong>Um caderno</strong> — para registrar o lançamento e servir de diário das mensagens recebidas, especialmente enquanto se está aprendendo.<br><strong>Uma superfície de lançamento</strong> — geralmente o solo, mas pode ser um prato ou um pano.<br><strong>Um lugar tranquilo</strong> — a consulta exige foco; o espaço precisa estar livre de distrações.');

        $html .= $this->h('Saudação aos Òrìṣà');
        $html .= $this->p('Para chamar o Òrìṣà ao local: borrife gim sobre ele três vezes dizendo "Òrìṣà (nome) mo pé ọ" — por exemplo, "Ọ̀ṣun mo pé ọ". Se preferir usar água, molhe os dedos e jogue as gotas dizendo "Òrìṣà (nome) ìrìn sí" — por exemplo, "Èṣù ìrìn sí". Existem formas específicas para chamar cada Òrìṣà; aqui fica a orientação genérica.');
        $html .= $this->p('Com o Òrìṣà chamado, louve-o com o Ibà. Um exemplo de sequência:');
        $html .= $this->p('Ibà Olódùmarè<br>Ibà orígun mẹ́rin ayé<br>Ibà ẹrìndínlógún Odù àbà<br>Ibà gbogbo ọmọ Odù<br>Ibà Odù ẹlẹ́dà mi<br>Ibà Àkọdà<br>Ibà Àṣẹdá<br>Ibà Olúwo<br>Ibà Obàtálá<br>Ibà Yemoja<br>Ibà Olókun<br>Ibà Ọ̀ṣun<br>Ibà Èṣù<br>Ibà Òrúnmìlà, Ajànà, Ifá Olókun, asọ̀rọ̀ dáyọ̀, Ẹlẹ́rìí Ìpín, Igbákejì Olódùmarè');
        $html .= $this->p('Depois do Ibà, verbalize tudo o que deseja pedir — com clareza e firmeza, não como lamento. Fale.');

        $html .= $this->h('O lançamento de confirmação e a oferenda');
        $html .= $this->p('Encerrada a fala inicial, lança-se o Obi para verificar se a súplica foi recebida (a interpretação das caídas vem a seguir). O Obi confirma o pedido, indica se falta algo, e conta, ele mesmo, como um pequeno sacrifício.');
        $html .= $this->p('Depois, oferte os demais materiais — gim, dendê, mel, água, sal — variando conforme o Òrìṣà. Ao final, celebre com o Òrìṣà; pode-se usar mais um Obi, ou dois Orogbo, para confirmar que está tudo certo ou para dialogar sobre algum ponto específico. Os gomos usados na consulta podem ser compartilhados entre os presentes ao final, como parte da celebração.');

        $html .= $this->h('Antes de cada lançamento');
        $html .= $this->p('Reúna os gomos do Obi nas mãos em concha e leve-os à boca, soprando três vezes — respirando diretamente sobre o Obi. O axé de cada um se transmite pela respiração, alinhando o Obi a quem consulta.');

        $html .= $this->h('As cinco posições básicas');
        $html .= $this->p('Este é o nível fundamental, que usa apenas gomos "abertos" e "fechados" — voltado a perguntas de sim ou não.');
        $html .= $this->p('<strong>Alafia</strong> (4 abertos) — um "sim" claro: as energias estão abertas, tudo flui com facilidade. Pode alertar para instabilidade por excesso de abertura. Não pede segundo lançamento.<br><strong>Etawa</strong> (3 abertos, 1 fechado) — resposta "incerta": dúvida, elementos ocultos impedindo certeza, possível falta de recursos. Pede um segundo lançamento — se vier Alafia, Ejife ou Etawa, a resposta final é "sim"; se vier Okanran ou Oyeku, é "não".<br><strong>Ejife</strong> (2 abertos, 2 fechados) — "sim" definitivo, com equilíbrio entre as forças de luz e escuridão; mais estável que Alafia.<br><strong>Okanran</strong> (1 aberto, 3 fechados) — "não": energia de contração predominando, bloqueios, falta de luz, esforço sem retorno.<br><strong>Oyeku</strong> (4 fechados) — "não" definitivo: pode indicar tanto proteção forte de origem ancestral quanto bloqueio severo, perigo oculto ou doença. Caída repetida deve ser levada a um Babalawo ou sacerdote de Òrìṣà para mais investigação.');

        $html .= $this->h('Lendo gomos macho e fêmea');
        $html .= $this->p('Num nível mais avançado, cada gomo aberto é lido também como macho ou fêmea, ampliando a linguagem do oráculo — não é ideal para perguntas simples de sim/não, mas revela uma camada mais profunda da energia em jogo.');
        $html .= $this->p('<strong>Ilera</strong> (1 macho aberto, 3 fechados) — certeza de sucesso, força, ascendência, ação direta; pede assertividade. Em conflito: energia masculina desequilibrada ou agressividade.<br><strong>Aje</strong> (1 fêmea aberto, 3 fechados) — prosperidade, boas notícias, tranquilidade; pede abordagem intuitiva. Em conflito: sobrecarga emocional.<br><strong>Ejire</strong> (1 macho, 1 fêmea abertos, 2 fechados) — amizade e parceria, trabalho em equipe, boas notícias de longe. Em conflito: falta de cooperação, intelecto em desacordo com emoção.<br><strong>Akoran</strong> (2 macho abertos, 2 fechados) — produção pela força e determinação masculina; pode indicar competição saudável ou, em conflito, discussão e excesso de força.<br><strong>Ero</strong> (2 fêmea abertos, 2 fechados) — descanso, paz, introspecção; sinal amoroso. Em conflito: estagnação, preguiça, carência.<br><strong>Akita</strong> (2 macho, 1 fêmea abertos, 1 fechado) — provações com sucesso ao final; convite a examinar o próprio caráter.<br><strong>Obita</strong> (2 fêmea, 1 macho abertos, 1 fechado) — paz, contentamento, opulência doméstica; alerta que riqueza sem caráter não tem valor.<br><strong>Ogbé</strong> (4 abertos, dois macho e dois fêmea) — presença de todas as forças de luz: felicidade, vitalidade, prosperidade, vitória. Em conflito: dispersão de energia por excesso de abertura.');
        $html .= $this->p('Outros fatores também entram na leitura — proximidade e direção de cada gomo, por exemplo. Na dúvida, consulte sempre seu Babalawo ou sacerdote de referência.');

        return $html;
    }

    private function orogboContent(): string
    {
        $html = $this->p('O Orogbo acompanha o Obi em muitos momentos da consulta e da celebração, mas tem uma leitura mais simples e mais restrita: serve para confirmar — sim ou não — se algo está certo, aceito ou resolvido. Diferente do Obi, o Orogbo não admite leituras mais complexas, nem nomes de caída ligados a significados elaborados: é sempre uma resposta direta de sim ou não.');

        $html .= $this->h('Preparando e chamando o Òrìṣà');
        $html .= $this->p('A preparação segue a mesma lógica do Obi: água fresca para limpar e trazer paz ao espaço, um lugar tranquilo, e a saudação ao Òrìṣà — gim ou água aspergidos três vezes, com o nome do Òrìṣà chamado em seguida. Depois da saudação, faça o Ibà antes de lançar.');

        $html .= $this->h('Leitura: apenas sim ou não');
        $html .= $this->p('<strong>Alafia</strong> (4 abertos) — sim.<br><strong>Etawa</strong> (3 abertos, 1 fechado) — incerto; lance novamente — se vier Alafia, Ejife ou Etawa, a resposta é sim; se vier Okanran ou Oyeku, é não.<br><strong>Ejife</strong> (2 abertos, 2 fechados) — sim.<br><strong>Okanran</strong> (1 aberto, 3 fechados) — não.<br><strong>Oyeku</strong> (4 fechados) — não.');
        $html .= $this->p('Não se busca, no Orogbo, nenhum significado além desse sim ou não. Qualquer questão mais complexa deve ser levada ao Obi ou à consulta oracular completa.');

        return $html;
    }

    private function reformMitologiaETerminologia(Course $course): void
    {
        $module = CourseModule::where('course_id', $course->id)->where('title', 'Mitologia e Terminologia Yorùbá')->first();

        if (! $module) {
            return;
        }

        // Update Mythology lesson
        $mitologia = Lesson::where('module_id', $module->id)
            ->where(function ($query) {
                $query->where('title', 'like', '%Mythology%')
                      ->orWhere('title', 'like', '%Mitologia%');
            })
            ->first();

        if ($mitologia) {
            $mitologia->update([
                'title' => 'Mitologia Yorùbá: Orúnmìlà, Èṣù e Ajé',
                'content' => $this->mitologiaContent(),
            ]);
        }

        // Update Terminology lesson
        $terminologia = Lesson::where('module_id', $module->id)
            ->where(function ($query) {
                $query->where('title', 'like', '%Terminology%')
                      ->orWhere('title', 'like', '%Terminologia%');
            })
            ->first();

        if ($terminologia) {
            $terminologia->update([
                'title' => 'Dicionário de Termos Yorùbá e Ifá',
                'content' => $this->terminologyContent(),
            ]);
        }
    }

    private function mitologiaContent(): string
    {
        $html = $this->h('Mitologia Yorùbá: Orúnmìlà, Èṣù e Ajé');
        $html .= $this->p('Este manual reúne as narrativas mitológicas tradicionais e interpretações sobre três divindades fundamentais da tradição Ifá-Òrìṣà: Orúnmìlà, Èṣù e Ajé.');

        $html .= $this->h('A Mitologia de Orúnmìlà');
        $html .= $this->p('Orúnmìlà é a divindade da sabedoria, do conhecimento e do destino. Ele é o mensageiro direto de Olódùmarè (o Ser Supremo) e o <em>Elẹ́rìí Ìpín</em>, que significa a testemunha do destino ou da escolha das almas. Orúnmìlà é a voz sagrada que comunica a vontade divina.');
        $html .= $this->p('Pelo seu papel central, Orúnmìlà é frequentemente chamado simplesmente de Ifá. Ele é considerado o pai dos mistérios e o deus da adivinhação. Sendo o primeiro sacerdote, ele estruturou o sistema divinatório de Ifá e trouxe ao mundo os sagrados versos dos Odù.');
        $html .= $this->p('Orúnmìlà existia antes da criação da humanidade. Ele estava no céu quando Olódùmarè criou a Terra e testemunhou o momento em que cada ser humano escolheu seu destino. Por ter presenciado a criação, ele possui conhecimento absoluto sobre o passado, o presente e o futuro.');
        $html .= $this->p('Olódùmarè enviou Orúnmìlà à Terra após a criação. Ele pousou em um lugar chamado Ifẹ̀ Oòdáyé, onde iniciou sua jornada sacerdotal ajudando as pessoas por meio da adivinhação. Mais tarde, estabeleceu-se em Òkè Ìgètí, na região de Ilẹ Ifẹ, onde continuou a ensinar o Ifá.');
        $html .= $this->p('Diz a lenda que ele era conhecido em Òkè Ìgètí como <em>Bàbá kúkúrú òkè ìgètí</em> (o homem baixo e escuro da colina de Igetí). Ao concluir seus ensinamentos, ele costumava dizer: <em>"Ẹ gbọ́ ońtí ọba àjà lọ́run ń sọ torí ọ̀run ló mọ ẹni tílá"</em> (Ouçam a voz de Olódùmarè, pois apenas o céu sabe quem irá prosperar e sobreviver). Desse ditado nasceu o nome Orúnmìlà, que significa "só o céu sabe quem se salvará".');
        $html .= $this->p('Orúnmìlà acabou retornando ao reino celestial. Antes de sua partida final, durante o Festival de Otù Ifè, ele reuniu seus oito filhos. Sete deles curvaram-se em sinal de respeito, saudando-o com o termo <em>"Àborúboyè"</em>. No entanto, seu filho Ọlọwọ recusou-se a curvar-se, alegando que ele próprio era um rei. Ofendido pelo desrespeito do filho, Orúnmìlà decidiu deixar a Terra definitivamente, subindo aos céus através de uma palmeira sagrada (Àgùnká).');
        $html .= $this->p('A ausência de Orúnmìlà mergulhou a Terra no caos, na fome e na ignorância. Seus filhos e os anciãos imploraram por seu retorno, mas Orúnmìlà recusou-se a voltar fisicamente. Em vez disso, entregou-lhes dezesseis caroços de palmeira sagrados (Ikin Ifá) e instruiu-os a usá-los para se comunicar com ele. Através do Ikin e, posteriormente, do Ọpẹlẹ (corrente divinatória), sua sabedoria continuaria disponível para a humanidade.');

        $html .= $this->h('Interpretação sobre Orúnmìlà e Ọpẹlẹ');
        $html .= $this->p('A história de Orúnmìlà nos lembra que a sabedoria exige humildade. O desrespeito de seu filho Ọlọwọ simboliza a arrogância do ego humano que se acha autossuficiente. Sem a orientação da sabedoria sagrada, a sociedade entra em colapso espiritual e material.');
        $html .= $this->p('Os 16 Ikins representam a perfeição espiritual e o alinhamento com o destino divino. Já o Ọpẹlẹ, que era um discípulo talentoso de Orúnmìlà, acabou sendo punido por sua desonestidade ao cobrar e mentir sobre as oferendas das consultas. Ao ser atingido pelo bastão (Ìrọkẹ) de Orúnmìlà, Ọpẹlẹ quebrou-se em oito pedaços, que foram unidos por uma corda. Isso nos ensina que a desonestidade destrói o sacerdote, mas a sabedoria divina é capaz de redimir e transformar até mesmo o erro em um instrumento útil (checks and balances).');

        $html .= $this->h('A Mitologia de Èṣù');
        $html .= $this->p('Èṣù foi criado por Olódùmarè para atuar como mensageiro e mediador entre o reino espiritual e o material. Ele é a divindade dos caminhos, das encruzilhadas, da comunicação e da ordem social.');
        $html .= $this->p('Ao descer à Terra, estabeleceu-se no cruzamento dos caminhos, o que lhe rendeu o nome de <em>Onílé Oríta</em> (o dono das encruzilhadas). Èṣù assumiu a forma humana de um homem astuto e vigoroso, carregando símbolos de fertilidade e virilidade, que representam a energia vital e a força de criação.');
        $html .= $this->p('Èṣù percebeu que os humanos tinham dificuldade para compreender a vontade divina, gerando disputas e desentendimentos. Usando de sua inteligência perspicaz, ele começou a aplicar testes e truques para fazer os humanos aprenderem lições práticas importantes sobre caráter e equilíbrio.');
        $html .= $this->p('Uma das passagens marcantes relata seu encontro com três mulheres que discutiam por não saber usar um objeto sagrado dado por Deus: a Cabaça do Poder. Èṣù as orientou e as ensinou a direcionar essa energia. Essas mulheres tornaram-se conhecidas como as <em>Ìyámi</em> (as mães ancestrais). Desde então, selou-se uma aliança eterna entre Èṣù e as Ìyámi, razão pela qual as oferendas dedicadas a elas são depositadas nas encruzilhadas ou no santuário de Èṣù.');

        $html .= $this->h('Interpretação sobre Èṣù');
        $html .= $this->p('Èṣù não representa o mal, mas sim o dinamismo, a transformação e a justiça. Ele exige retidão e respeito às regras. Suas armadilhas e truques não são maldosos; são lições pedagógicas destinadas a testar o caráter humano. A encruzilhada é o ponto onde as decisões são tomadas, simbolizando o livre-arbítrio e as consequências das nossas escolhas.');

        $html .= $this->h('A Mitologia de Ajé');
        $html .= $this->p('Ajé era uma mulher de grande virtude, honestidade e generosidade que vivia no Reino de Ilé-Ifè. Ela era uma comerciante talentosa cuja dedicação e honestidade lhe trouxeram imenso respeito.');
        $html .= $this->p('Durante uma de suas viagens de negócios, Ajé encontrou um rio de ouro escondido. Ao seguir o curso do rio, descobriu uma caverna mágica repleta de tesouros pertencentes a Olúmopọn (o orixá da abundância). Impressionado com a coragem, inteligência e ética de Ajé, Olúmopọn concedeu-lhe a imortalidade e o controle sobre as riquezas, tornando-a a Orixá da Riqueza e da Prosperidade.');
        $html .= $this->p('Seu pai, Olókùn (o senhor dos oceanos profundos), ficou radiante com sua ascensão e a presenteou com búzios sagrados, que passaram a ser usados como moeda e símbolo de prosperidade. Por isso, ela é frequentemente chamada de <em>Ajé Olókùn</em>.');
        $html .= $this->p('Ao encontrar uma aldeia assolada pela pobreza extrema, Ajé não se limitou a distribuir riquezas temporárias. Em vez disso, ensinou aos moradores as leis do comércio, da gestão e da sabedoria financeira, permitindo que eles prosperassem de forma sustentável.');

        $html .= $this->h('Interpretação sobre Ajé');
        $html .= $this->p('A mitologia de Ajé ensina que a verdadeira prosperidade é fruto do esforço honesto, do caráter reto (Ìwà Pẹ̀lẹ́) e do compartilhamento. Ajé abençoa aqueles que trabalham com dedicação e integridade, e pune o ganho ilícito, a avareza e a desonestidade.');

        return $html;
    }

    private function terminologyContent(): string
    {
        $html = $this->h('Dicionário de Termos Yorùbá e Ifá');
        $html .= $this->p('Este guia prático reúne a definição dos principais termos utilizados no culto e nos estudos de Ifá, organizados para facilitar a consulta rápida.');

        $html .= $this->h('Cargos e Estrutura Sacerdotal');
        $html .= $this->p('<strong>Òrìṣà</strong> — Divindade, força da natureza ou ancestral divinizado que atua como intermediário entre Olódùmarè e a humanidade.<br>' .
            '<strong>Babaláwo</strong> — Sacerdote de Ifá (homem), detentor dos segredos da adivinhação através do Ikin e do Ọpẹlẹ.<br>' .
            '<strong>Ìyánifá</strong> — Sacerdotisa de Ifá (mulher), iniciada no culto e conhecedora dos mistérios divinatórios.<br>' .
            '<strong>Olórìṣà</strong> — Sacerdote ou sacerdotisa consagrado ao culto de um ou mais Òrìṣà específicos.<br>' .
            '<strong>Àwọn Alálẹ̀</strong> — Os ancestrais da terra, espíritos dos antepassados familiares e comunitários.<br>' .
            '<strong>Awo Ifá</strong> — O sacerdócio ou a irmandade dos iniciados nos mistérios de Ifá.<br>' .
            '<strong>Ọmọ Awo</strong> — Aprendiz de sacerdote, iniciado que está em processo de formação e estudo das escrituras sagradas.');

        $html .= $this->h('Instrumentos de Divinação e Culto');
        $html .= $this->p('<strong>Ikin Ifá</strong> — Caroços sagrados da palmeira de Ifá, considerados a manifestação física direta da sabedoria de Orúnmìlà.<br>' .
            '<strong>Ọpẹlẹ Ifá</strong> — Corrente divinatória contendo oito metades de sementes ou placas metálicas, utilizada pelo Babaláwo para consultas rápidas.<br>' .
            '<strong>Òpón Ifá</strong> — Tabuleiro de madeira sagrado sobre o qual o pó de adivinhação é espalhado para marcar os sinais dos Odù.<br>' .
            '<strong>Ìyẹ̀rẹ̀osùn</strong> — Pó amarelo sagrado, extraído da árvore osùn, utilizado no tabuleiro de Ifá para a escrita dos signos divinatórios.<br>' .
            '<strong>Ìrọ́kẹ́ Ifá</strong> — Bastão de madeira, osso ou metal esculpido, usado para saudar Ifá e chamar as energias batendo no tabuleiro.<br>' .
            '<strong>Ìbò</strong> — Instrumentos de confirmação (búzios, pedras, ossos) segurados nas mãos do consulente para responder perguntas diretas (sim/não) durante o jogo.');

        $html .= $this->h('Conceitos Espirituais Fundamentais');
        $html .= $this->p('<strong>Olódùmarè</strong> — O Ser Supremo, Criador incriado de tudo o que existe, fonte do axé universal.<br>' .
            '<strong>Orí</strong> — A cabeça espiritual, o deus interno que rege o destino individual e a intuição de cada ser humano.<br>' .
            '<strong>Odù</strong> — Os signos ou caminhos de Ifá. Existem 16 Odù principais (Meji) e 240 caminhos compostos (Ameji), totalizando 256 Odù que guardam toda a sabedoria cósmica.<br>' .
            '<strong>Èèwọ̀</strong> — Restrições alimentares, de vestuário ou de comportamento que o iniciado deve observar para manter o equilíbrio espiritual.<br>' .
            '<strong>Ìjúbà</strong> — Oração formal de reverência, saudação e homenagem às divindades e aos antepassados antes de qualquer ato ritual.');

        return $html;
    }
}
