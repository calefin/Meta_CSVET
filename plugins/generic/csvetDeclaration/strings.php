<?php

/**
 * @file plugins/generic/csvetDeclaration/strings.php
 *
 * @brief Textos da declaração dos autores, por idioma. As chaves dos itens são o nome
 *  dos campos (com o prefixo csvetDecl_); mudar uma chave não afeta envios já feitos,
 *  porque o histórico guarda o texto aceito. Itens com "choices" são de escolha única.
 *  "help" (opcional) aparece embaixo do título do item, com os links do modelo ou do
 *  formulário daquele item; os links abrem em nova aba para o autor não perder o envio.
 *  A licença CC BY 4.0 é aceita na caixa de direitos autorais do próprio OJS.
 */

$site = 'https://journals.csvet.com.br';
$doc = "{$site}/download";
$a = fn (string $href, string $text) => '<a href="' . $href . '" target="_blank" rel="noopener">' . $text . '</a>';

$pt = [
    'guidelines' => "{$site}/nvas/pt_BR/about/submissions",
    'about' => "{$site}/nvas/pt_BR/about#editors-articles",
    'templates' => "{$site}/nvas/pt_BR/templates",
    'checklists' => "{$site}/nvas/pt_BR/templates#checklists",
    'caseReport' => "{$doc}/NVAS-modelo-relato-de-caso.docx",
    'original' => "{$doc}/NVAS-modelo-artigo-original.docx",
    'review' => "{$doc}/NVAS-modelo-revisao.docx",
    'clinicalImage' => "{$doc}/NVAS-clinical-image-template.docx",
    'titlePage' => "{$doc}/NVAS-modelo-folha-de-rosto.docx",
    'coverLetter' => "{$doc}/NVAS-modelo-carta-de-apresentacao.docx",
    'consent' => "{$doc}/NVAS-modelo-termo-de-consentimento.docx",
];
$en = [
    'guidelines' => "{$site}/nvas/en/about/submissions",
    'about' => "{$site}/nvas/en/about#editors-articles",
    'templates' => "{$site}/nvas/en/templates",
    'checklists' => "{$site}/nvas/en/templates#checklists",
    'caseReport' => "{$doc}/NVAS-case-report-template.docx",
    'original' => "{$doc}/NVAS-original-article-template.docx",
    'review' => "{$doc}/NVAS-review-template.docx",
    'clinicalImage' => "{$doc}/NVAS-clinical-image-template.docx",
    'titlePage' => "{$doc}/NVAS-title-page-template.docx",
    'coverLetter' => "{$doc}/NVAS-cover-letter-template.docx",
    'consent' => "{$doc}/NVAS-consent-form-template.docx",
];

return [
    'en' => [
        'intro' => '<p><strong>Author declaration.</strong> The submitting author declares, on behalf of all authors, that each statement below is true. Each item is required. Under each item there are links to the template or form it refers to (they open in a new tab; your submission stays here). The system records your name, username, date, time and IP address together with the accepted text in the submission history; no printed or signed form is needed.</p>',
        'required' => 'Please confirm this item of the author declaration.',
        'startLocaleLabel' => 'Language of the manuscript text',
        'startLocaleDescription' => 'Choose the language in which the manuscript TEXT is written (not your own language). Manuscript in English: choose English; the title, abstract, emails and reviewer invitations will be in English. Manuscript in Portuguese: choose Português; if accepted, the journal translates it into English.',
        'fieldTitle' => 'title',
        'fieldAbstract' => 'abstract',
        'languageMissing' => 'Fill in the {$fields} in {$language} in the Details step (language tab "{$language}"): it must match the language of the manuscript.',
        'logHeader' => 'Author declaration accepted electronically by {$name} ({$username}) on behalf of all authors, {$date}, IP {$ip}:',
        'items' => [
            'language' => [
                'label' => 'Language of the manuscript',
                'help' => '<p>If the submission was started in the other language, the journal switches it to the language chosen here; the title and the abstract must already be filled in in this language.</p>',
                'choices' => [
                    'en' => 'English',
                    'pt_BR' => 'Português',
                ],
            ],
            'guidelines' => [
                'label' => 'Guide for Authors and template',
                'help' => '<p>' . $a($en['guidelines'], 'Read the Guide for Authors') . ' · ' . $a($en['templates'], 'Templates for authors (.docx)') . '</p>',
                'text' => 'I have read the Guide for Authors and the manuscript follows the journal template for its article type: no author names in the text, within the word limits, references in Vancouver style, all guidance boxes and [WRITE HERE] texts removed, and all text inside figures in English.',
            ],
            'files' => [
                'label' => 'Required files',
                'help' => '<ul>'
                    . '<li><strong>All article types:</strong> anonymized manuscript (template: ' . $a($en['caseReport'], 'case report') . ', ' . $a($en['original'], 'original article') . ', ' . $a($en['review'], 'review') . ') and ' . $a($en['titlePage'], 'title page') . '.</li>'
                    . '<li><strong>Cover letter</strong> (' . $a($en['coverLetter'], 'template') . '): all types except Clinical Images and Letters to the Editor.</li>'
                    . '<li><strong>Reporting guideline checklist</strong> (' . $a($en['checklists'], 'which checklist to use') . '): original articles, short communications, case reports and series, reviews, methods and data articles.</li>'
                    . '<li><strong>Clinical image:</strong> ' . $a($en['clinicalImage'], 'template') . ', plus the labelled figure and the unlabelled figure as separate files.</li>'
                    . '<li><strong>When applicable:</strong> ethics approvals and permits; ' . $a($en['consent'], 'consent form of the legal guardian') . '.</li>'
                    . '</ul><p>If a required file is missing, the system tells you before you can finish.</p>',
                'text' => 'I uploaded all the files required for my article type, each one with the right file type (for example "Title page (with authors)"), and only the anonymized manuscript and the files seen by reviewers are free of author names.',
            ],
            'original' => [
                'label' => 'Originality',
                'help' => '<p>Preprint or presentation at a meeting or on social media? Mention it in the ' . $a($en['coverLetter'], 'cover letter') . '.</p>',
                'text' => 'The manuscript is original, has not been published and is not under consideration by another journal; any preprint is declared.',
            ],
            'preprint' => [
                'label' => 'Open science: preprint',
                'help' => '<p>Preprints are welcome (for example ' . $a('https://preprints.scielo.org/', 'SciELO Preprints') . ', ' . $a('https://www.biorxiv.org/', 'bioRxiv') . ', ' . $a('https://www.researchsquare.com/', 'Research Square') . '). Give the server and the DOI in the ' . $a($en['coverLetter'], 'cover letter') . '.</p>',
                'choices' => [
                    'yes' => 'The manuscript was posted as a preprint; the server and the DOI are given in the cover letter.',
                    'no' => 'The manuscript was not posted as a preprint.',
                ],
            ],
            'data' => [
                'label' => 'Open science: data, code and materials',
                'help' => '<p>The Data Availability Statement at the end of the manuscript must match the option chosen here. Repositories: ' . $a('https://zenodo.org/', 'Zenodo') . ', ' . $a('https://figshare.com/', 'Figshare') . ', ' . $a('https://data.scielo.org/', 'SciELO Data') . ', GenBank (sequences).</p>',
                'choices' => [
                    'repository' => 'The data, code and materials that support the results are deposited in a public repository (with DOI or accession number) and cited in the manuscript.',
                    'article' => 'All data are in the manuscript or in the supplementary material (for example, a case report).',
                    'restricted' => 'Part of the data cannot be made public (for example, confidentiality of the owner, the farm or third parties); the reason and how to request access are given in the Data Availability Statement.',
                    'na' => 'Not applicable: no data were generated or analysed (for example, a narrative review or a perspective).',
                ],
            ],
            'orcid' => [
                'label' => 'ORCID of all authors',
                'help' => '<p>No ORCID yet? ' . $a('https://orcid.org/register', 'Register for free at orcid.org') . ' (2 minutes). Give each author\'s ORCID on the ' . $a($en['titlePage'], 'title page') . '.</p>',
                'text' => 'All authors have an ORCID iD, given on the title page. Each author will confirm their own ORCID through the email sent by the journal, and the article is only published when the ORCID of every author has been verified.',
            ],
            'board' => [
                'label' => 'Authors from the editorial board',
                'help' => '<p>' . $a($en['about'], 'Policy for articles by editors') . '.</p>',
                'choices' => [
                    'no' => 'No author is a member of the NVAS editorial board.',
                    'yes' => 'One or more authors are members of the NVAS editorial board; the manuscript follows the policy for articles by editors (independent handling editor, the author takes no part in the process, at least two external reviews).',
                ],
            ],
            'authorship' => [
                'label' => 'Authorship',
                'help' => '<p>Contributions using the ' . $a('https://credit.niso.org/', 'CRediT taxonomy') . ', on the ' . $a($en['titlePage'], 'title page') . '.</p>',
                'text' => 'All authors meet the ICMJE authorship criteria, have read and approved the submitted version and agree with its submission; contributions are described with CRediT, and no one who meets these criteria has been omitted.',
            ],
            'guarantor' => [
                'label' => 'Graduated author',
                'help' => '<p>Identify the supervisor or another graduated professional on the ' . $a($en['titlePage'], 'title page') . ', with degree, institution and a link to the Lattes CV, CRMV or ORCID.</p>',
                'text' => 'At least one author has completed an undergraduate degree in veterinary medicine, animal science or a related field and acts as guarantor of the work.',
            ],
            'ethics' => [
                'label' => 'Ethics',
                'help' => '<p>Need a consent form? ' . $a($en['consent'], 'Consent form template for the legal guardian (.docx)') . ' (only when the text or images could identify the animal or its guardian). Committee approvals and permits are uploaded as "Ethics approval or permit". Details in ' . $a($en['guidelines'], 'Section 2.1 of the Guide') . '.</p>',
                'choices' => [
                    'approved' => 'The work involved animals and/or people and has all applicable approvals, consents and permits (CEUA or equivalent, CEP/CAAE, consent of the legal guardian or farm, MAPA, SISBIO, SisGen), reported in the manuscript; the certificates will be provided on request.',
                    'clinical' => 'Case report or clinical image from routine clinical care, with no procedure performed for research purposes: no ethics committee approval is required, and the material does not allow the animal or its legal guardian to be identified or, if it does, the legal guardian (or an institutional form covering teaching and research) consented in writing.',
                    'na' => 'Not applicable: the work did not involve animals or people (e.g. a review without new data).',
                ],
            ],
            'integrity' => [
                'label' => 'Data and image integrity',
                'text' => 'The data and images are authentic and were not manipulated beyond the limits of the Guide for Authors; the original files are kept and will be provided on request.',
            ],
            'ai' => [
                'label' => 'Artificial intelligence',
                'help' => '<p>How to declare it: ' . $a($en['guidelines'], 'Guide for Authors, Section 2.5') . '. The templates already have the "Use of generative AI" declaration at the end.</p>',
                'text' => 'Any use of generative AI is declared in the manuscript; AI is not listed as an author, and all references were checked by the authors.',
            ],
            'conflicts' => [
                'label' => 'Conflicts of interest and funding',
                'text' => 'All conflicts of interest and all sources of funding are disclosed in the manuscript.',
            ],
            'rights' => [
                'label' => 'Third-party rights',
                'text' => 'The work does not infringe third-party rights; written permission was obtained for any reproduced figure, table or text and for images of identifiable people.',
            ],
            'translation' => [
                'label' => 'Translation',
                'text' => 'If the manuscript is submitted in Portuguese and accepted, the authors authorize the journal to translate it into English (with support of AI tools that do not train on the content, always reviewed by an editor) and will review and approve the translation before publication.',
            ],
            'fees' => [
                'label' => 'Fees and editorial independence',
                'help' => '<p>' . $a($en['guidelines'], 'Fee policy (Guide for Authors, Section 7)') . ': no fees for manuscripts accepted until 31 January 2027.</p>',
                'text' => 'The authors are aware of the publication fee policy on the journal website and that peer review and editorial decisions are independent of any payment.',
            ],
            'corrections' => [
                'label' => 'Corrections and retractions',
                'text' => 'Errors or misconduct identified after publication will be handled by correction, expression of concern or retraction, according to COPE guidance.',
            ],
            'behalf' => [
                'label' => 'On behalf of all authors',
                'text' => 'I am submitting on behalf of all authors, who have read this declaration and agree with it.',
            ],
        ],
    ],
    'pt_BR' => [
        'intro' => '<p><strong>Declaração dos autores.</strong> O autor que faz o envio declara, em nome de todos os autores, que cada afirmação abaixo é verdadeira. Todos os itens são obrigatórios. Embaixo de cada item estão os links do modelo ou do formulário a que ele se refere (abrem em nova aba; seu envio continua aqui). O sistema registra seu nome, usuário, data, hora e endereço IP, junto com o texto aceito, no histórico da submissão; não é preciso imprimir nem assinar formulário.</p>',
        'required' => 'Confirme este item da declaração dos autores.',
        'startLocaleLabel' => 'Idioma do texto do manuscrito',
        'startLocaleDescription' => 'Escolha o idioma em que o TEXTO do manuscrito está escrito (não o seu idioma). Manuscrito em inglês: escolha English; título, resumo, e-mails e convites aos avaliadores ficam em inglês. Manuscrito em português: escolha Português; se aceito, a revista traduz para o inglês.',
        'fieldTitle' => 'título',
        'fieldAbstract' => 'resumo',
        'languageMissing' => 'Preencha o {$fields} em {$language} no passo Detalhes (aba do idioma "{$language}"): ele precisa estar no idioma do manuscrito.',
        'logHeader' => 'Declaração dos autores aceita eletronicamente por {$name} ({$username}) em nome de todos os autores, {$date}, IP {$ip}:',
        'items' => [
            'language' => [
                'label' => 'Idioma do manuscrito',
                'help' => '<p>Se o envio foi aberto no outro idioma, a revista passa a submissão para o idioma marcado aqui; o título e o resumo já precisam estar preenchidos nesse idioma.</p>',
                'choices' => [
                    'en' => 'English (inglês)',
                    'pt_BR' => 'Português',
                ],
            ],
            'guidelines' => [
                'label' => 'Normas e modelo',
                'help' => '<p>' . $a($pt['guidelines'], 'Ler as Instruções aos autores') . ' · ' . $a($pt['templates'], 'Modelos para autores (.docx)') . '</p>',
                'text' => 'Li as Instruções aos autores, e o manuscrito segue o modelo da revista para o seu tipo de artigo: sem nomes de autores no texto, dentro do limite de palavras, referências no estilo Vancouver, sem os quadros de orientação e sem nenhum [ESCREVA AQUI], e com todo texto dentro das figuras em inglês.',
            ],
            'files' => [
                'label' => 'Arquivos obrigatórios',
                'help' => '<ul>'
                    . '<li><strong>Todos os tipos:</strong> manuscrito anonimizado (modelo de ' . $a($pt['caseReport'], 'relato de caso') . ', ' . $a($pt['original'], 'artigo original') . ', ' . $a($pt['review'], 'revisão') . ') e ' . $a($pt['titlePage'], 'folha de rosto') . '.</li>'
                    . '<li><strong>Carta de apresentação</strong> (' . $a($pt['coverLetter'], 'modelo') . '): em todos os tipos, menos Imagem Clínica e Carta ao Editor.</li>'
                    . '<li><strong>Checklist da diretriz de relato</strong> (' . $a($pt['checklists'], 'qual checklist usar') . '): artigos originais, comunicações curtas, relatos e séries de casos, revisões, métodos e artigos de dados.</li>'
                    . '<li><strong>Imagem clínica:</strong> ' . $a($pt['clinicalImage'], 'modelo') . ', mais a figura com marcações e a figura sem marcações, em arquivos separados.</li>'
                    . '<li><strong>Quando houver:</strong> aprovações de ética e licenças; ' . $a($pt['consent'], 'termo de consentimento do responsável legal') . '.</li>'
                    . '</ul><p>Se faltar algum arquivo obrigatório, o sistema avisa antes de você concluir.</p>',
                'text' => 'Enviei todos os arquivos obrigatórios do meu tipo de artigo, cada um com o tipo de arquivo certo (por exemplo "Folha de rosto (com os autores)"), e só o manuscrito anonimizado e os arquivos vistos pelos revisores estão sem os nomes dos autores.',
            ],
            'original' => [
                'label' => 'Originalidade',
                'help' => '<p>Tem preprint ou apresentou em congresso ou nas redes sociais? Informe na ' . $a($pt['coverLetter'], 'carta de apresentação') . '.</p>',
                'text' => 'O manuscrito é original, inédito e não está em avaliação em outra revista; eventual preprint está declarado.',
            ],
            'preprint' => [
                'label' => 'Ciência aberta: preprint',
                'help' => '<p>Preprints são bem-vindos (por exemplo ' . $a('https://preprints.scielo.org/', 'SciELO Preprints') . ', ' . $a('https://www.biorxiv.org/', 'bioRxiv') . ', ' . $a('https://www.researchsquare.com/', 'Research Square') . '). Informe o servidor e o DOI na ' . $a($pt['coverLetter'], 'carta de apresentação') . '.</p>',
                'choices' => [
                    'yes' => 'O manuscrito foi depositado como preprint; o servidor e o DOI estão na carta de apresentação.',
                    'no' => 'O manuscrito não foi depositado como preprint.',
                ],
            ],
            'data' => [
                'label' => 'Ciência aberta: dados, códigos e materiais',
                'help' => '<p>A Declaração de Disponibilidade de Dados, no fim do manuscrito, deve corresponder à opção marcada aqui. Repositórios: ' . $a('https://zenodo.org/', 'Zenodo') . ', ' . $a('https://figshare.com/', 'Figshare') . ', ' . $a('https://data.scielo.org/', 'SciELO Data') . ', GenBank (sequências).</p>',
                'choices' => [
                    'repository' => 'Os dados, códigos e materiais que sustentam os resultados estão depositados em repositório público (com DOI ou número de acesso) e citados no manuscrito.',
                    'article' => 'Todos os dados estão no manuscrito ou no material suplementar (por exemplo, relato de caso).',
                    'restricted' => 'Parte dos dados não pode ser aberta (por exemplo, sigilo do tutor, da propriedade ou de terceiros); o motivo e a forma de solicitar acesso estão na Declaração de Disponibilidade de Dados.',
                    'na' => 'Não se aplica: o trabalho não gerou nem analisou dados (por exemplo, revisão narrativa ou perspectiva).',
                ],
            ],
            'orcid' => [
                'label' => 'ORCID de todos os autores',
                'help' => '<p>Ainda não tem ORCID? ' . $a('https://orcid.org/register', 'Cadastre-se grátis em orcid.org') . ' (2 minutos). Informe o ORCID de cada autor na ' . $a($pt['titlePage'], 'folha de rosto') . '.</p>',
                'text' => 'Todos os autores têm ORCID, informado na folha de rosto. Cada autor vai confirmar o próprio ORCID pelo e-mail que a revista envia, e o artigo só é publicado com o ORCID de todos os autores verificado.',
            ],
            'board' => [
                'label' => 'Autores do corpo editorial',
                'help' => '<p>' . $a($pt['about'], 'Política para artigos de editores') . '.</p>',
                'choices' => [
                    'no' => 'Nenhum autor integra o corpo editorial da NVAS.',
                    'yes' => 'Um ou mais autores integram o corpo editorial da NVAS; o manuscrito segue a política para artigos de editores (editor responsável independente, autor fora do processo, pelo menos dois pareceres externos).',
                ],
            ],
            'authorship' => [
                'label' => 'Autoria',
                'help' => '<p>Contribuições pela ' . $a('https://credit.niso.org/', 'taxonomia CRediT') . ', na ' . $a($pt['titlePage'], 'folha de rosto') . '.</p>',
                'text' => 'Todos os autores atendem aos critérios de autoria do ICMJE, leram e aprovaram a versão enviada e concordam com a submissão; as contribuições estão descritas pela CRediT, e ninguém que atenda a esses critérios foi omitido.',
            ],
            'guarantor' => [
                'label' => 'Autor graduado',
                'help' => '<p>Identifique o orientador ou outro profissional graduado na ' . $a($pt['titlePage'], 'folha de rosto') . ', com titulação, instituição e link do Currículo Lattes, CRMV ou ORCID.</p>',
                'text' => 'Pelo menos um autor tem graduação concluída em medicina veterinária, zootecnia ou área afim e é o responsável (guarantor) pelo trabalho.',
            ],
            'ethics' => [
                'label' => 'Ética',
                'help' => '<p>Precisa de termo? ' . $a($pt['consent'], 'Modelo de termo de consentimento do responsável legal (.docx)') . ' (só quando o texto ou as imagens permitem identificar o animal ou o responsável). Aprovações de comissão de ética e licenças vão com o tipo "Documento de ética ou licença". Detalhes na ' . $a($pt['guidelines'], 'Seção 2.1 das normas') . '.</p>',
                'choices' => [
                    'approved' => 'O trabalho envolveu animais e/ou pessoas e tem todas as aprovações, consentimentos e licenças aplicáveis (CEUA ou equivalente, CEP/CAAE, consentimento do responsável legal ou da propriedade, MAPA, SISBIO, SisGen), informados no manuscrito; os certificados serão fornecidos mediante solicitação.',
                    'clinical' => 'Relato de caso ou imagem clínica de atendimento de rotina, sem procedimento realizado para a pesquisa: dispensa aprovação de comissão de ética, e o material não permite identificar o animal nem o seu responsável legal ou, se permite, o responsável legal (ou um termo institucional que cubra ensino e pesquisa) deu consentimento por escrito.',
                    'na' => 'Não se aplica: o trabalho não envolveu animais nem pessoas (por exemplo, revisão sem dados novos).',
                ],
            ],
            'integrity' => [
                'label' => 'Integridade dos dados e das imagens',
                'text' => 'Os dados e as imagens são autênticos e não foram manipulados além dos limites das Instruções aos Autores; os arquivos originais estão guardados e serão fornecidos mediante solicitação.',
            ],
            'ai' => [
                'label' => 'Inteligência artificial',
                'help' => '<p>Como declarar: ' . $a($pt['guidelines'], 'Seção 2.5 das normas') . '. Os modelos já trazem, no fim, a declaração "Uso de IA generativa".</p>',
                'text' => 'Qualquer uso de IA generativa está declarado no manuscrito; a IA não consta como autora, e todas as referências foram conferidas pelos autores.',
            ],
            'conflicts' => [
                'label' => 'Conflito de interesses e financiamento',
                'text' => 'Todos os conflitos de interesse e todas as fontes de financiamento estão declarados no manuscrito.',
            ],
            'rights' => [
                'label' => 'Direitos de terceiros',
                'text' => 'O trabalho não viola direitos de terceiros; foi obtida autorização por escrito para qualquer figura, tabela ou texto reproduzido e para imagens de pessoas identificáveis.',
            ],
            'translation' => [
                'label' => 'Tradução',
                'text' => 'Se o manuscrito for submetido em português e aceito, os autores autorizam a revista a traduzi-lo para o inglês (com apoio de ferramentas de IA que não usam o conteúdo para treinamento, sempre com revisão de um editor) e vão revisar e aprovar a tradução antes da publicação.',
            ],
            'fees' => [
                'label' => 'Taxas e independência editorial',
                'help' => '<p>' . $a($pt['guidelines'], 'Política de taxas (Seção 7 das normas)') . ': sem taxas para manuscritos aceitos até 31/01/2027.</p>',
                'text' => 'Os autores conhecem a política de taxas publicada no site da revista e sabem que a avaliação por pares e as decisões editoriais independem de qualquer pagamento.',
            ],
            'corrections' => [
                'label' => 'Correções e retratações',
                'text' => 'Erros ou má conduta identificados após a publicação serão tratados por errata, manifestação de preocupação ou retratação, conforme o COPE.',
            ],
            'behalf' => [
                'label' => 'Em nome de todos os autores',
                'text' => 'Faço este envio em nome de todos os autores, que leram esta declaração e concordam com ela.',
            ],
        ],
    ],
];
