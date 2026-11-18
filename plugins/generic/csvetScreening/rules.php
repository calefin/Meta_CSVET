<?php

/**
 * @file plugins/generic/csvetScreening/rules.php
 *
 * @brief Arquivos exigidos em cada tipo de artigo (seção, pela sigla) e textos da
 *  triagem, por idioma. Os tipos de arquivo (genres) são criados por
 *  scripts/configure-journal.php a partir de "genres" no journal.json; aqui eles são
 *  citados pela chave (entry_key). "sections" => '*' vale para todas as seções.
 *  Mudou um requisito aqui? Mude também a Seção 4.1 / Anexo B das normas e o texto
 *  uploadFilesHelp do journal.json.
 */

return [
    'files' => [
        'manuscript' => ['genre' => 'SUBMISSION', 'sections' => '*'],
        'titlePage' => ['genre' => 'NVAS_TITLE_PAGE', 'sections' => '*'],
        'coverLetter' => ['genre' => 'NVAS_COVER_LETTER', 'sections' => ['ART', 'SC', 'CR', 'REV', 'TN', 'DATA', 'PER']],
        'checklist' => ['genre' => 'NVAS_REPORTING_CHECKLIST', 'sections' => ['ART', 'SC', 'CR', 'REV', 'TN', 'DATA']],
        'figureLabelled' => ['genre' => 'NVAS_FIGURE_LABELLED', 'sections' => ['IMG']],
        'figureUnlabelled' => ['genre' => 'NVAS_FIGURE_UNLABELLED', 'sections' => ['IMG']],
    ],

    // Instituições do corpo editorial: autor de uma delas = conflito declarado (política
    // "Artigos de editores"); a triagem avisa quem deve conduzir e mostra a proporção de
    // submissões dessa instituição nos últimos 12 meses. "match": trechos procurados na
    // filiação do autor (minúsculas, sem acento).
    'boardInstitutions' => [
        ['name' => 'Universidade Cruzeiro do Sul', 'match' => ['cruzeiro do sul', 'unicsul']],
    ],

    // Arquivos que nunca devem ir para os revisores (identificam os autores)
    'privateGenres' => ['NVAS_TITLE_PAGE', 'NVAS_COVER_LETTER', 'NVAS_ETHICS', 'NVAS_CONSENT', 'NVAS_PERMISSION'],

    // Limite de palavras do texto principal por seção (só para a triagem; o .docx inteiro conta mais)
    'wordLimits' => ['ART' => 6000, 'SC' => 2500, 'CR' => 3500, 'IMG' => 500, 'REV' => 8000, 'TN' => 3500, 'DATA' => 2500, 'PER' => 2500, 'LET' => 1000],

    // Texto que sobra quando o autor não termina de preencher o modelo .docx da revista
    'templateLeftovers' => ['[ESCREVA AQUI', '[WRITE HERE', 'apague este quadro', 'delete this box'],

    // Registro da declaração dos autores (plugin csvetDeclaration) no histórico
    'declarationLog' => ['Declaração dos autores aceita', 'Author declaration accepted'],

    'en' => [
        'urls' => [
            'guidelines' => 'https://journals.csvet.com.br/nvas/en/about/submissions',
            'templates' => 'https://journals.csvet.com.br/nvas/en/templates',
        ],
        'files' => [
            'manuscript' => 'Anonymized manuscript',
            'titlePage' => 'Title page',
            'coverLetter' => 'Cover letter',
            'checklist' => 'Reporting guideline checklist (CARE, ARRIVE, PRISMA, STROBE-Vet...)',
            'figureLabelled' => 'Clinical image: labelled figure',
            'figureUnlabelled' => 'Clinical image: unlabelled figure',
        ],
        // Mensagens do assistente de envio (texto simples: o assistente não mostra links)
        'wizardMissing' => 'Missing file: {$file}. Upload it and choose the file type "{$genre}".',
        'wizardHelp' => 'Required files for this article type and templates: {$url}',

        // Relatório de triagem (e-mail ao editor e painel)
        'title' => 'Editorial screening',
        'lead' => 'Automatic check of the files and the form. It does not replace reading the manuscript.',
        'required' => 'Required files for {$section}',
        'present' => 'sent',
        'missing' => 'MISSING',
        'chosenAs' => 'upload it with the type "{$genre}"',
        'otherFiles' => 'Other files',
        'otherAsOther' => '"{$file}" was uploaded as "Other": check whether it is the title page, cover letter or checklist and ask for it to be resent with the right type.',
        'figureAsOther' => '"{$file}" was uploaded as "Other" but is an image: it is probably a figure. No need to ask for it again; the type "Figure" now exists in the submission.',
        'pdfOnly' => 'Manuscript only in PDF ("{$file}"): the Guide asks for .docx or .odt (LaTeX with PDF is accepted).',
        'nameInFile' => 'The file name "{$file}" contains an author name: the manuscript may not be anonymized.',
        'nameInText' => 'Author names in the manuscript text: {$names}. Check anonymization.',
        'nameInProps' => 'File properties of the manuscript identify a person ("{$value}").',
        'leftovers' => 'The manuscript still has template text ("{$text}"): the template was not finished.',
        'wordCount' => 'About {$count} words in the whole manuscript file (main text limit for this type: {$limit}).',
        'noZip' => 'The manuscript could not be read automatically (check anonymization by hand).',
        'oneAuthor' => 'Only one author listed: the Guide requires at least one graduated author (students must include their supervisor).',
        'authors' => '{$count} authors; corresponding author: {$name}',
        'boardAuthor' => 'Author from the editorial board: {$names}. Handling: Associate Editor (if the Editor-in-Chief is an author) or an editor with no link to this author; at least two complete external reviews; the article will carry the editorial board note.',
        'sameInstitution' => 'Authors from {$institution}, the institution of the editorial board: {$names}. Declared conflict: both reviewers from outside {$institution}. If any author is a student or recent co-author of the Editor-in-Chief, assign the Co-Editor-in-Chief; if the Editor-in-Chief is an author, the Associate Editor.',
        'institutionShare' => 'Last 12 months: {$count} of {$total} submissions ({$percent}%) have an author from {$institution}. Internal target: at most about one third.',
        'noOrcid' => 'The corresponding author has no ORCID iD in the form.',
        'keywords' => '{$count} keywords',
        'noAbstract' => 'No abstract in the form.',
        'declarationOk' => 'Author declaration accepted and recorded in the history.',
        'declarationMissing' => 'No author declaration found in the history (submitted before the electronic declaration?).',
        'verdictProceed' => 'All required files are present. After the manual checks below, the submission can be sent to review.',
        'verdictCheck' => 'All required files are present, but there are warnings. Check them before sending to review.',
        'verdictReturn' => 'Required files are missing. Return to the authors without review: Decline submission and choose the template "Technical return (guidelines or files)".',
        'manualTitle' => 'Check by hand before sending to review',
        'manual' => [
            'Scope of NVAS and article type (section) are correct.',
            'Title page identifies the graduated author (degree, institution, Lattes CV, CRMV or ORCID link).',
            'Manuscript entirely in Portuguese or entirely in English; text inside figures in English.',
            'Ethics: CEUA/CEP or consent when applicable (routine non-identifiable cases need neither).',
            'Similarity check of the text (and reverse image search for clinical images).',
        ],
        'reviewFiles' => 'When sending to review, select only the files seen by reviewers: anonymized manuscript, reporting checklist, figures and supplementary files. Never the title page, cover letter, ethics, consent or permission documents.',
        'openWorkflow' => 'Open the submission',
        'openScreening' => 'Full screening',
        'mailSubject' => '[NVAS] New submission #{$id}: {$status}',
        'mailStatusOk' => 'files complete',
        'mailStatusWarn' => 'files complete, {$count} warning(s)',
        'mailStatusMissing' => '{$count} required file(s) missing',
        'mailIntro' => 'A new submission has just been completed in {$journal}.',
        'mailSection' => 'Article type',
        'mailSubmitter' => 'Submitted by',
        'mailDate' => 'Date',
        'mailFooter' => 'Automatic message from the NVAS screening. The submission is also listed in the Editorial dashboard.',
        'returnHelp' => 'How to return: Open the submission › Decline submission › choose the template "Technical return (guidelines or files)", tick what is missing and send. The authors can resubmit as a new submission.',
    ],

    'pt_BR' => [
        'urls' => [
            'guidelines' => 'https://journals.csvet.com.br/nvas/pt_BR/about/submissions',
            'templates' => 'https://journals.csvet.com.br/nvas/pt_BR/templates',
        ],
        'files' => [
            'manuscript' => 'Manuscrito anonimizado',
            'titlePage' => 'Folha de rosto',
            'coverLetter' => 'Carta de apresentação',
            'checklist' => 'Checklist da diretriz de relato (CARE, ARRIVE, PRISMA, STROBE-Vet...)',
            'figureLabelled' => 'Imagem clínica: figura com marcações',
            'figureUnlabelled' => 'Imagem clínica: figura sem marcações',
        ],
        'wizardMissing' => 'Falta o arquivo: {$file}. Envie o arquivo e escolha o tipo "{$genre}".',
        'wizardHelp' => 'Arquivos obrigatórios de cada tipo de artigo e modelos para baixar: {$url}',

        'title' => 'Triagem editorial',
        'lead' => 'Conferência automática dos arquivos e do formulário. Não substitui a leitura do manuscrito.',
        'required' => 'Arquivos obrigatórios em {$section}',
        'present' => 'enviado',
        'missing' => 'FALTA',
        'chosenAs' => 'envie com o tipo "{$genre}"',
        'otherFiles' => 'Outros arquivos',
        'otherAsOther' => '"{$file}" foi enviado como "Outro": confira se é a folha de rosto, a carta ou o checklist e peça o reenvio com o tipo certo.',
        'figureAsOther' => '"{$file}" foi enviado como "Outro", mas é uma imagem: deve ser uma figura. Não precisa pedir de novo; o tipo "Figura" agora existe no envio.',
        'pdfOnly' => 'Manuscrito só em PDF ("{$file}"): as normas pedem .docx ou .odt (LaTeX com PDF é aceito).',
        'nameInFile' => 'O nome do arquivo "{$file}" traz nome de autor: o manuscrito pode não estar anonimizado.',
        'nameInText' => 'Nomes de autores no texto do manuscrito: {$names}. Confira a anonimização.',
        'nameInProps' => 'As propriedades do arquivo do manuscrito identificam uma pessoa ("{$value}").',
        'leftovers' => 'O manuscrito ainda tem texto do modelo ("{$text}"): o modelo não foi terminado.',
        'wordCount' => 'Cerca de {$count} palavras no arquivo inteiro do manuscrito (limite do texto principal neste tipo: {$limit}).',
        'noZip' => 'Não foi possível ler o manuscrito automaticamente (confira a anonimização à mão).',
        'oneAuthor' => 'Só um autor informado: as normas exigem pelo menos um autor graduado (estudante precisa incluir o orientador).',
        'authors' => '{$count} autores; autor de correspondência: {$name}',
        'boardAuthor' => 'Autor do corpo editorial: {$names}. Condução: editor associado (se o editor-chefe é autor) ou um editor sem vínculo com esse autor; pelo menos dois pareceres externos completos; o artigo sai com a nota de autor do corpo editorial.',
        'sameInstitution' => 'Autores da {$institution}, instituição do corpo editorial: {$names}. Conflito declarado: os dois avaliadores de fora da {$institution}. Se algum autor é orientando ou coautor recente do editor-chefe, designe a coeditora-chefe; se o editor-chefe é autor, o editor associado.',
        'institutionShare' => 'Últimos 12 meses: {$count} de {$total} submissões ({$percent}%) têm autor da {$institution}. Meta interna: no máximo cerca de um terço.',
        'noOrcid' => 'O autor de correspondência está sem ORCID no formulário.',
        'keywords' => '{$count} palavras-chave',
        'noAbstract' => 'Sem resumo no formulário.',
        'declarationOk' => 'Declaração dos autores aceita e registrada no histórico.',
        'declarationMissing' => 'Não há declaração dos autores no histórico (envio anterior à declaração eletrônica?).',
        'verdictProceed' => 'Todos os arquivos obrigatórios foram enviados. Depois da conferência manual abaixo, pode seguir para avaliação.',
        'verdictCheck' => 'Todos os arquivos obrigatórios foram enviados, mas há alertas. Confira antes de enviar para avaliação.',
        'verdictReturn' => 'Faltam arquivos obrigatórios. Devolva aos autores sem avaliação: Recusar submissão e escolha o modelo "Devolução técnica (normas ou arquivos)".',
        'manualTitle' => 'Conferir à mão antes de enviar para avaliação',
        'manual' => [
            'Escopo da NVAS e tipo de artigo (seção) corretos.',
            'Folha de rosto identifica o autor graduado (titulação, instituição, link do Lattes, CRMV ou ORCID).',
            'Manuscrito inteiro em português ou inteiro em inglês; texto dentro das figuras em inglês.',
            'Ética: CEUA/CEP ou termo de consentimento quando aplicável (caso de rotina não identificável dispensa os dois).',
            'Verificação de similaridade do texto (e busca reversa da imagem, nas imagens clínicas).',
        ],
        'reviewFiles' => 'Ao enviar para avaliação, selecione só os arquivos vistos pelos revisores: manuscrito anonimizado, checklist da diretriz, figuras e suplementares. Nunca a folha de rosto, a carta, documentos de ética, termos de consentimento ou autorizações.',
        'openWorkflow' => 'Abrir a submissão',
        'openScreening' => 'Triagem completa',
        'mailSubject' => '[NVAS] Nova submissão #{$id}: {$status}',
        'mailStatusOk' => 'arquivos completos',
        'mailStatusWarn' => 'arquivos completos, {$count} alerta(s)',
        'mailStatusMissing' => 'falta(m) {$count} arquivo(s) obrigatório(s)',
        'mailIntro' => 'Uma nova submissão acaba de ser concluída na {$journal}.',
        'mailSection' => 'Tipo de artigo',
        'mailSubmitter' => 'Enviada por',
        'mailDate' => 'Data',
        'mailFooter' => 'Mensagem automática da triagem da NVAS. A submissão também aparece no painel Editorial.',
        'returnHelp' => 'Como devolver: Abrir a submissão › Recusar submissão › escolha o modelo "Devolução técnica (normas ou arquivos)", marque o que falta e envie. Os autores podem enviar de novo como nova submissão.',
    ],
];
