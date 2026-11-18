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

        $post = [
            'title' => 'Medicina Veterinária Legal: O que é, áreas de atuação e a importância da perícia',
            'slug' => 'o-que-e-medicina-veterinaria-legal-areas-atuacao-pericia',
            'summary' => 'A Medicina Veterinária Legal é a especialidade que une a ciência médico-veterinária ao Direito. Conheça as principais áreas de atuação, como necropsia forense, toxicologia, auditoria de erro veterinário e assistência técnica judicial.',
            'category' => 'Medicina Veterinária Legal',
            'image' => 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?q=80&w=600&auto=format&fit=crop',
            'author_id' => $authorId,
            'content' => '<p class="lead">A <strong>Medicina Veterinária Legal</strong> (também conhecida internacionalmente como <em>Veterinary Forensic Medicine</em>) é o ramo da medicina veterinária que aplica conhecimentos técnico-científicos para auxiliar a Justiça e solucionar questões jurídicas que envolvam animais.</p>

<p>Com a evolução legislativa brasileira (como a Lei Sansão - Lei nº 14.064/20), o aumento de litígios por suspeita de erro médico veterinário e a necessidade de comprovação científica em casos de intoxicações e disputas de guarda ou compra de animais, a atuação do perito em medicina veterinária legal tornou-se indispensável.</p>

<h3 class="mt-4">Principais Áreas de Atuação na Medicina Veterinária Legal</h3>
<p>O campo da perícia e medicina veterinária forense é multidisciplinar e abrange diversas ramificações:</p>

<ul class="list-group list-group-flush my-4">
    <li class="list-group-item bg-transparent px-0">
        <strong>1. Patologia Forense e Necropsia:</strong> Exame cadavérico detalhado com cadeia de custódia para determinação inequívoca da <em>causa mortis</em> em mortes súbitas, violentas ou sob custódia clínica.
    </li>
    <li class="list-group-item bg-transparent px-0">
        <strong>2. Toxicologia Forense Veterinária:</strong> Identificação laboratorial e analítica de substâncias tóxicas, praguicidas (como carbamatos/"chumbinho", estricnina e raticidas) e reações medicamentosas adversas em animais vivos ou exumados.
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
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        DB::table('posts')->updateOrInsert(
            ['slug' => $post['slug']],
            $post
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('posts')->where('slug', 'o-que-e-medicina-veterinaria-legal-areas-atuacao-pericia')->delete();
    }
};
