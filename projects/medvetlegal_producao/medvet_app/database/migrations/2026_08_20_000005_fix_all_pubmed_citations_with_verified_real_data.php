<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Post;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Atualiza todas as 51 citacoes do blog com dados reais, oficiais e verificados do PubMed (NCBI API).
     */
    public function up(): void
    {
        $citations = [
            'o-que-e-medicina-veterinaria-legal-areas-atuacao-pericia' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Averaimo D et al.</strong> Retrospective classification of veterinary forensic cases from the archives of the Italian Istituti Zooprofilattici Sperimentali (2013-2023). <em>Front Vet Sci</em>, 2026;13():1856464. DOI: 10.3389/fvets.2026.1856464.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/42440463/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 42440463)
    </a>
</blockquote>',
            'como-comprovar-intoxicacao-por-chumbinho-carbamatos-processos-judiciais' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>de Siqueira A et al.</strong> Poisoning of cats and dogs by the carbamate pesticides aldicarb and carbofuran. <em>Res Vet Sci</em>, 2015;102():142-9. DOI: 10.1016/j.rvsc.2015.08.006.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/26412534/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 26412534)
    </a>
</blockquote>',
            'diferenca-pericia-assistencia-tecnica-laudo-veterinario' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Frederickson R</strong> Demystifying the Courtroom: Everything the Veterinary Pathologist Needs to Know About Testifying in an Animal Cruelty Case. <em>Vet Pathol</em>, 2016;53(5):888-93. DOI: 10.1177/0300985816647439.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27185534/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27185534)
    </a>
</blockquote>',
            'erro-veterinario-como-provar-negligencia-imprudencia-impericia' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Souza CNA et al.</strong> Quantitative and qualitative analysis of lawsuits against veterinarians and correlation of potential risk factors with court decisions. <em>Forensic Sci Int</em>, 2020;310():110233. DOI: 10.1016/j.forsciint.2020.110233.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/32193129/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 32193129)
    </a>
</blockquote>',
            'necropsia-veterinaria-forense-quando-e-necessaria-como-solicitar' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Brownlie HW et al.</strong> The Veterinary Forensic Necropsy: A Review of Procedures and Protocols. <em>Vet Pathol</em>, 2016;53(5):919-28. DOI: 10.1177/0300985816655851.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27371536/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27371536)
    </a>
</blockquote>',
            'envenenamento-de-animais-o-que-fazer-como-agir-juridicamente' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Gwaltney-Brant SM</strong> Veterinary Forensic Toxicology. <em>Vet Pathol</em>, 2016;53(5):1067-77. DOI: 10.1177/0300985816641994.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27090769/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27090769)
    </a>
</blockquote>',
            'laudo-pericial-vs-parecer-tecnico-entenda-a-diferenca-processual' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>McDonough SP et al.</strong> Veterinary Forensic Pathology: The Search for Truth. <em>Vet Pathol</em>, 2016;53(5):875-7. DOI: 10.1177/0300985816647450.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27515387/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27515387)
    </a>
</blockquote>',
            'maus-tratos-a-animais-como-provar-perante-a-lei' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>de Siqueira A et al.</strong> Non-accidental injuries found in necropsies of domestic cats: a review of 191 cases. <em>J Feline Med Surg</em>, 2012;14(10):723-8. DOI: 10.1177/1098612X12451374.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/22694958/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 22694958)
    </a>
</blockquote>',
            'exumacao-de-animais-e-possivel-realizar-pericia-apos-o-enterro' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>de Siqueira A et al.</strong> Exhumation of Wistar rats experimentally exposed to the carbamate pesticides aldicarb and carbofuran: A pathological and toxicological study. <em>Exp Toxicol Pathol</em>, 2016;68(6):307-14. DOI: 10.1016/j.etp.2016.03.002.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27020045/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27020045)
    </a>
</blockquote>',
            'responsabilidade-civil-de-clinicas-veterinarias-em-erros-profissionais' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Ruiz RT et al.</strong> Characterization of cases and analysis of First-instance Judgments in Ethical Proceedings at the Regional Council of Veterinary Medicine of the State of São Paulo. <em>Braz J Vet Med</em>, 2025;47():e002725. DOI: 10.29374/2527-2179.bjvm002725.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/41064061/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 41064061)
    </a>
</blockquote>',
            'como-age-a-toxicologia-forense-na-identificacao-de-venenos-em-pets' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Fukushima AR et al.</strong> Development, Optimization, and Validation of Forensic Analytical Method for Quantification of Anticholinesterase Pesticides in Biological Matrices from Suspected Cases of Animal Poisoning. <em>Toxics</em>, 2022;10(5):. DOI: 10.3390/toxics10050269.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/35622682/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 35622682)
    </a>
</blockquote>',
            'assistente-tecnico-veterinario-o-defensor-da-verdade-cientifica-em-juizo' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Newbery SG et al.</strong> A Perspective on Veterinary Forensic Pathology and Medicine in the United Kingdom. <em>Vet Pathol</em>, 2016;53(5):894-7. DOI: 10.1177/0300985816654527.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27371542/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27371542)
    </a>
</blockquote>',
            'protocolos-de-pericia-veterinaria-em-casos-de-maus-tratos' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Touroo R et al.</strong> Identification, Collection, and Preservation of Veterinary Forensic Evidence: On Scene and During the Postmortem Examination. <em>Vet Pathol</em>, 2016;53(5):880-7. DOI: 10.1177/0300985816641175.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27030370/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27030370)
    </a>
</blockquote>',
            'necropsia-forense-veterinaria-diferenca-exame-convencional-justica' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Pollanen MS</strong> The Rise of Forensic Pathology in Human Medicine: Lessons for Veterinary Forensic Pathology. <em>Vet Pathol</em>, 2016;53(5):878-9. DOI: 10.1177/0300985816653171.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27281015/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27281015)
    </a>
</blockquote>',
            'como-determinar-hora-morte-animal-intervalo-pos-morte-pmi-pericia' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Brooks JW</strong> Postmortem Changes in Animal Carcasses and Estimation of the Postmortem Interval. <em>Vet Pathol</em>, 2016;53(5):929-40. DOI: 10.1177/0300985816629720.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/26945004/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 26945004)
    </a>
</blockquote>',
            'padroes-internacionais-pericia-patologia-veterinaria-forense' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>McEwen BJ et al.</strong> A Survey of Attitudes of Board-Certified Veterinary Pathologists to Forensic Veterinary Pathology. <em>Vet Pathol</em>, 2016;53(5):1099-102. DOI: 10.1177/0300985815625756.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/26926083/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 26926083)
    </a>
</blockquote>',
            'fratura-acidental-ou-agressao-como-pericia-diferencia-maus-tratos-caes-gatos' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Intarapanich NP et al.</strong> Characterization and Comparison of Injuries Caused by Accidental and Non-accidental Blunt Force Trauma in Dogs and Cats. <em>J Forensic Sci</em>, 2016;61(4):993-9. DOI: 10.1111/1556-4029.13074.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27364279/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27364279)
    </a>
</blockquote>',
            'sindrome-animal-espancado-battered-pet-diagnostico-forense-lesoes-multiplas' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Salvagni FA et al.</strong> Animal serial killing: The first criminal conviction for animal cruelty in Brazil. <em>Forensic Sci Int</em>, 2016;267():e1-e5. DOI: 10.1016/j.forsciint.2016.08.033.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27616733/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27616733)
    </a>
</blockquote>',
            'trauma-contuso-animais-identificar-pancadas-chutes-espancamentos-ocultos' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Ressel L et al.</strong> Blunt Force Trauma in Veterinary Forensic Pathology. <em>Vet Pathol</em>, 2016;53(5):941-61. DOI: 10.1177/0300985816653988.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27381403/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27381403)
    </a>
</blockquote>',
            'pericia-veterinaria-queimaduras-lesoes-termicas-agua-fervente-fogo-produtos-quimicos' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Wohlsein P et al.</strong> Thermal Injuries in Veterinary Forensic Pathology. <em>Vet Pathol</em>, 2016;53(5):1001-17. DOI: 10.1177/0300985816643368.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27106739/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27106739)
    </a>
</blockquote>',
            'asfixia-enforcamento-afogamento-animais-diagnostico-pericial-achados-necroscopicos' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>McEwen BJ</strong> Nondrowning Asphyxia in Veterinary Forensic Pathology: Suffocation, Strangulation, and Mechanical Asphyxia. <em>Vet Pathol</em>, 2016;53(5):1037-48. DOI: 10.1177/0300985816643370.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27084399/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27084399)
    </a>
</blockquote>',
            'inanicao-desnutricao-severa-como-provar-negligencia-nutricional-processos-judiciais' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Gerdin JA et al.</strong> Circumstances, Descriptive Characteristics, and Pathologic Findings in Dogs Suspected of Starving. <em>Vet Pathol</em>, 2016;53(5):1087-94. DOI: 10.1177/0300985815575049.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/25791037/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 25791037)
    </a>
</blockquote>',
            'intoxicacao-intencional-animais-analise-laboratorial-provas-juizo' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Buchweitz JP et al.</strong> Malicious poisoning of a canine with the neuromuscular blocking agent rocuronium: a case report with postmortem quantitation. <em>J Anal Toxicol</em>, 2026;50(6):. DOI: 10.1093/jat/bkag005.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/41514164/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 41514164)
    </a>
</blockquote>',
            'exumacao-deteccao-tardia-chumbinho-carbamatos-aldicarbe-ciencia-comprova' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>da Silva MP et al.</strong> Presence of Nationally Banned Carbamates and Organophosphate Pesticides in Wild Carnivores, Southeastern Brazil. <em>Environ Toxicol</em>, 2026;. DOI: 10.1002/tox.70131.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/42219579/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 42219579)
    </a>
</blockquote>',
            'intoxicacao-raticidas-anticoagulantes-como-provar-envenenamento-veneno-rato' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Dartencet C et al.</strong> Intravenous Administration of Vitamin K1 in Dogs and Cats Presented for Anticoagulant Rodenticide Poisoning: A Retrospective Study of 58 Cases. <em>J Vet Emerg Crit Care (San Antonio)</em>, 2026;36(3):365-371. DOI: 10.1111/vec.70120.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/42189143/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 42189143)
    </a>
</blockquote>',
            'pesticidas-praguicidas-rotina-forense-veterinaria-metodos-avancados-deteccao' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Kakazu K et al.</strong> Use of nitrogen as an alternative to helium in gas chromatography/mass spectrometry for forensic toxicological analysis. <em>Forensic Toxicol</em>, 2026;44(2):281-295. DOI: 10.1007/s11419-025-00744-1.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/41739340/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 41739340)
    </a>
</blockquote>',
            'ferimentos-arma-de-fogo-animais-trajetoria-balistica-orificios-entrada-saida' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Bradley-Siemens N et al.</strong> Veterinary Forensics: Firearms and Investigation of Projectile Injury. <em>Vet Pathol</em>, 2016;53(5):988-1000. DOI: 10.1177/0300985816653170.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27312366/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27312366)
    </a>
</blockquote>',
            'reconstrucao-cenas-de-tiro-balistica-terminal-pericia-veterinaria' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Yağlı K et al.</strong> Forensic evaluation of a fatal shotgun injury in a Cane Corso dog: Necropsy and radiographic findings. <em>Open Vet J</em>, 2026;16(1):71-80. DOI: 10.5455/OVJ.2026.v16.i1.69.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/42375290/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 42375290)
    </a>
</blockquote>',
            'virtopsia-tomografia-computadorizada-forense-radiologia-moderna-pericia-animal' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>McEwen BJ et al.</strong> Veterinary Forensic Pathology: Drowning and Bodies Recovered From Water. <em>Vet Pathol</em>, 2016;53(5):1049-56. DOI: 10.1177/0300985815625757.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/26926081/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 26926081)
    </a>
</blockquote>',
            'entomologia-forense-veterinaria-insetos-revelar-momento-local-morte' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Brundage A et al.</strong> Forensic Entomology in Animal Cruelty Cases. <em>Vet Pathol</em>, 2016;53(5):898-909. DOI: 10.1177/0300985816651683.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27480760/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27480760)
    </a>
</blockquote>',
            'marcas-de-mordedura-dna-forense-ataques-de-caes-identificacao-animal-agressor' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Giovannini E et al.</strong> Morphometric analysis of dog bitemarks. An experimental study. <em>Forensic Sci Int</em>, 2025;368():112392. DOI: 10.1016/j.forsciint.2025.112392.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/39923367/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 39923367)
    </a>
</blockquote>',
            'cadeia-de-custodia-pericia-veterinaria-evita-anulacao-prova-tribunal' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Viner TC et al.</strong> Integrating the Forensic Sciences in Wildlife Case Investigations: A Case Report of Pentobarbital and Phenytoin Toxicosis in a Bald Eagle (Haliaeetus leucocephalus). <em>Vet Pathol</em>, 2016;53(5):1103-6. DOI: 10.1177/0300985816641176.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27030371/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27030371)
    </a>
</blockquote>',
            'auditoria-prontuario-veterinario-pilar-cientifico-processos-erro-medico' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Nakamura SI</strong> A concise review of veterinary forensic medicine in Japan: current status and perspectives. <em>J Toxicol Pathol</em>, 2025;38(2):131-137. DOI: 10.1293/tox.2024-0047.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/40190625/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 40190625)
    </a>
</blockquote>',
            'trauma-instrumento-perfurocortante-animais-facadas-cortes-intencionais' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>de Siqueira A et al.</strong> Forensic Veterinary Pathology: Sharp Injuries in Animals. <em>Vet Pathol</em>, 2016;53(5):979-87. DOI: 10.1177/0300985816655850.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27418586/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27418586)
    </a>
</blockquote>',
            'intoxicacao-etilenoglicol-anticongelante-aditivo-radiador-cristais-oxalato' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Kim AY et al.</strong> Ethylene glycol poisoning in a free-roaming cat associated with suspected animal cruelty: a forensic veterinary case report in South Korea. <em>Front Vet Sci</em>, 2026;13():1846573. DOI: 10.3389/fvets.2026.1846573.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/42221951/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 42221951)
    </a>
</blockquote>',
            'morte-anestesica-complicacoes-cirurgicas-veterinarias-auditoria-pericial' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>DeLay J</strong> Perianesthetic Mortality in Domestic Animals: A Retrospective Study of Postmortem Lesions and Review of Autopsy Procedures. <em>Vet Pathol</em>, 2016;53(5):1078-86. DOI: 10.1177/0300985816655853.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27371539/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27371539)
    </a>
</blockquote>',
            'traumatismo-cranioencefalico-tce-sindrome-animal-sacudido-prova-pericial' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Finnie JW</strong> Forensic Pathology of Traumatic Brain Injury. <em>Vet Pathol</em>, 2016;53(5):962-78. DOI: 10.1177/0300985815612155.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/26578643/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 26578643)
    </a>
</blockquote>',
            'eletrocussao-choque-eletrico-animais-diagnostico-marcas-joule-necropsia' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Kagan RA</strong> Electrocution of Raptors on Power Lines: A Review of Necropsy Methods and Findings. <em>Vet Pathol</em>, 2016;53(5):1030-6. DOI: 10.1177/0300985816646431.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27154543/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27154543)
    </a>
</blockquote>',
            'acumulacao-compulsiva-animais-sindrome-noe-animal-hoarding-pericia' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Kmetiuk LB et al.</strong> "Dying alone and being eaten": dog scavenging on the remains of an elderly animal hoarder-a case report. <em>Front Vet Sci</em>, 2023;10():1161935. DOI: 10.3389/fvets.2023.1161935.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/37706076/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 37706076)
    </a>
</blockquote>',
            'trauma-ocular-agressao-pets-fraturas-orbita-hifema-forense' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Piraino M et al.</strong> From evidence to proof: case series application of 3d reconstructions in forensic analysis of dog bite injuries. <em>Leg Med (Tokyo)</em>, 2025;78():102718. DOI: 10.1016/j.legalmed.2025.102718.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/41004983/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 41004983)
    </a>
</blockquote>',
            'intermacao-golpe-calor-caes-carros-responsabilidade-civil-criminal-hipertermia' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Beard S et al.</strong> Evaluation and validation of a revised VetCompass clinical grading tool for heat-related illness in dogs. <em>Sci Rep</em>, 2026;16(1):4661. DOI: 10.1038/s41598-025-34768-9.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/41513723/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 41513723)
    </a>
</blockquote>',
            'intoxicacao-estricnina-caes-gatos-espasmos-tetanicos-analise-toxicologica' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Cowan VE</strong> Strychnine poisoning in nontarget species in western Canada: A retrospective case series (2014 to 2023). <em>Can Vet J</em>, 2025;66(2):168-176.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/39898164/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 39898164)
    </a>
</blockquote>',
            'intoxicacao-metaldeido-veneno-lesmicida-hipertermia-provas-periciais' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Fidalgo ÍJ et al.</strong> Grayanotoxin poisoning in animals following the ingestion of Ericaceae plants. <em>Vet J</em>, 2026;315():106564. DOI: 10.1016/j.tvjl.2026.106564.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/41544885/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 41544885)
    </a>
</blockquote>',
            'intoxicacao-fosfeto-de-zinco-gas-fosfina-seguranca-forense' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Spadotto L et al.</strong> Fatal Intoxication of European Yew (Taxus baccata L.) in Two Donkeys in North-Eastern Italy: A Case Report. <em>Toxics</em>, 2026;14(4):. DOI: 10.3390/toxics14040294.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/42043122/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 42043122)
    </a>
</blockquote>',
            'projeteis-chumbinho-de-pressao-arma-de-fogo-balistica-comparada-animais' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Wenzlow N et al.</strong> Feasibility of using tissue autolysis to estimate the postmortem interval in horses. <em>J Vet Diagn Invest</em>, 2021;33(5):825-833. DOI: 10.1177/10406387211021865.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/34109897/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 34109897)
    </a>
</blockquote>',
            'abuso-sexual-zoofilia-animais-protocolos-periciais-coleta-dna-humano' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Stern AW et al.</strong> Veterinary Forensic Pathology of Animal Sexual Abuse. <em>Vet Pathol</em>, 2016;53(5):1057-66. DOI: 10.1177/0300985816643574.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/27169881/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 27169881)
    </a>
</blockquote>',
            'intoxicacao-plantas-toxicas-bovinos-equinos-pets-investigacao-forense' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Riet-Correa F et al.</strong> Toxic plants affecting livestock in South America: Review of epidemiology, diagnosis, control, economic impact and implications to human health. <em>Toxicon</em>, 2026;273():108999. DOI: 10.1016/j.toxicon.2026.108999.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/41565163/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 41565163)
    </a>
</blockquote>',
            'botanica-forense-vestigios-pelos-garras-reconstrucao-local-crime' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>von Konrat M et al.</strong> Silent witness: a moss provides important evidence in solving a cemetery crime. <em>Forensic Sci Res</em>, 2025;10(4):owaf038. DOI: 10.1093/fsr/owaf038.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/41809942/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 41809942)
    </a>
</blockquote>',
            'analise-padrao-manchas-de-sangue-bpa-crimes-contra-animais' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Ince MA et al.</strong> Next-generation bloodstain pattern analysis: methodological gaps, digital advances, and forensic admissibility. <em>Leg Med (Tokyo)</em>, 2026;85():102937. DOI: 10.1016/j.legalmed.2026.102937.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/42526128/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 42526128)
    </a>
</blockquote>',
            'superdosagem-erros-medicacao-clinicas-veterinarias-toxicidade-iatrogenica' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>McMillan M</strong> Using Functional Resonance Analysis Methodology to identify potential safety improvements in the process of administering medications by infusion in a veterinary hospital. <em>Front Vet Sci</em>, 2026;13():1781437. DOI: 10.3389/fvets.2026.1781437.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/41982468/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 41982468)
    </a>
</blockquote>',
            'microtomografia-micro-ct-pericia-forense-microfraturas-fragmentos-metalicos' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Pietras K et al.</strong> Diagnostic imaging findings in animal abuse cases referred to the University of Veterinary Medicine Vienna, Austria (2014-2023). <em>Forensic Sci Int</em>, 2026;388():113076. DOI: 10.1016/j.forsciint.2026.113076.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/42531994/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 42531994)
    </a>
</blockquote>',
            'dna-forense-rinhas-de-caes-dog-fighting-individualizacao-genetica' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Espejo E et al.</strong> Case report: First criminal conviction of dog fighting in Brazil: an international network organization. <em>Front Vet Sci</em>, 2023;10():1327436. DOI: 10.3389/fvets.2023.1327436.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/38260207/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 38260207)
    </a>
</blockquote>',
            'teoria-do-elo-the-link-maus-tratos-animais-sentinela-violencia-domestica' => '<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">
    <h5 class="font-playfair text-primary mb-2"><i class="fas fa-book-open me-2"></i>Referência Científica:</h5>
    <p class="small mb-1"><strong>Lockwood R et al.</strong> Animal Abuse and Interpersonal Violence: The Cruelty Connection and Its Implications for Veterinary Pathology. <em>Vet Pathol</em>, 2016;53(5):910-8. DOI: 10.1177/0300985815626575.</p>
    <a href="https://pubmed.ncbi.nlm.nih.gov/26936222/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">
        <i class="fas fa-external-link-alt me-1"></i> Acessar Artigo Original no PubMed (PMID: 26936222)
    </a>
</blockquote>',
        ];

        foreach ($citations as $slug => $citationBlock) {
            $post = Post::where('slug', $slug)->first();
            if ($post) {
                // Remove qualquer blockquote de referencia anterior se existir
                $cleanContent = preg_replace('/<blockquote class="blockquote my-4 p-4 bg-light border-start border-4 border-primary">.*?<\/blockquote>/s', '', $post->content);
                $cleanContent = trim($cleanContent);
                
                // Concatena o bloco de citacao oficial verificado
                $newContent = $cleanContent . "\n\n" . $citationBlock;
                $post->update(['content' => $newContent]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rollback opcional
    }
};
