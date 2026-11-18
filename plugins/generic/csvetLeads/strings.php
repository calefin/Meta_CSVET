<?php

/**
 * @file plugins/generic/csvetLeads/strings.php
 *
 * @brief Textos do formulário "Quero publicar" (respostas e erros), da resposta automática ao
 *  interessado (português, inglês e espanhol: o espanhol não é idioma do site, mas quem fala
 *  espanhol pode pedir contato nele) e do aviso e da lista para os editores.
 *  Placeholders: {name}, {deadline}, {url...}. Links de páginas: ver CsvetLeadsPlugin::urls().
 */

$options = [
    'pt_BR' => [
        'profile' => [
            'undergrad' => 'Estudante de graduação',
            'graduate' => 'Pós-graduando(a) ou residente',
            'professional' => 'Médico(a)-veterinário(a) ou zootecnista',
            'academic' => 'Professor(a) ou pesquisador(a)',
            'other' => 'Outro',
        ],
        'type' => [
            'case' => 'Relato de caso',
            'image' => 'Imagem clínica',
            'original' => 'Artigo original',
            'review' => 'Revisão',
            'other' => 'Outro tipo',
            'unsure' => 'Ainda não sei',
        ],
        'stage' => [
            'idea' => 'Só a ideia',
            'data' => 'Tenho os dados ou o caso',
            'writing' => 'Estou escrevendo',
            'ready' => 'Pronto para enviar',
        ],
        'lang' => ['pt_BR' => 'Português', 'en' => 'English', 'es' => 'Español'],
    ],
    'en' => [
        'profile' => [
            'undergrad' => 'Undergraduate student',
            'graduate' => 'Graduate student or resident',
            'professional' => 'Veterinarian or animal scientist',
            'academic' => 'Lecturer or researcher',
            'other' => 'Other',
        ],
        'type' => [
            'case' => 'Case report',
            'image' => 'Clinical image',
            'original' => 'Original article',
            'review' => 'Review',
            'other' => 'Other type',
            'unsure' => 'Not sure yet',
        ],
        'stage' => [
            'idea' => 'Just the idea',
            'data' => 'I have the data or the case',
            'writing' => 'I am writing',
            'ready' => 'Ready to submit',
        ],
        'lang' => ['pt_BR' => 'Português', 'en' => 'English', 'es' => 'Español'],
    ],
];
$options['es'] = $options['en'];

return [
    'options' => $options,

    // Respostas ao formulário (idioma da página)
    'pt_BR' => [
        'errName' => 'Informe seu nome.',
        'errEmail' => 'Informe um e-mail válido.',
        'errPhone' => 'Use só números no WhatsApp, com DDD (ex.: 19 99999-9999).',
        'errChoice' => 'Escolha uma opção.',
        'errConsent' => 'É preciso concordar para enviarmos a orientação.',
        'errTooLong' => 'Texto longo demais.',
        'errSpam' => 'Não foi possível enviar agora. Recarregue a página e tente de novo.',
        'errRate' => 'Recebemos vários envios deste endereço. Tente de novo em uma hora.',
        'errServer' => 'Não foi possível enviar agora. Tente de novo ou escreva para {email}.',
    ],
    'en' => [
        'errName' => 'Please enter your name.',
        'errEmail' => 'Please enter a valid e-mail address.',
        'errPhone' => 'Use numbers only, with the country code (e.g. +1 555 123 4567).',
        'errChoice' => 'Please choose an option.',
        'errConsent' => 'Please agree so that we can send you the guidance.',
        'errTooLong' => 'This text is too long.',
        'errSpam' => 'We could not send your form right now. Please reload the page and try again.',
        'errRate' => 'We received several forms from this address. Please try again in an hour.',
        'errServer' => 'We could not send your form right now. Please try again or write to {email}.',
    ],

    // Resposta automática ao interessado (idioma de contato escolhido)
    'reply' => [
        'pt_BR' => [
            'subject' => 'Seu artigo na NVAS: por onde começar',
            'hello' => 'Olá, {name}!',
            'intro' => 'Recebemos seu interesse em publicar na <em>Neotropical Veterinary and Animal Science</em> (NVAS). Obrigado! Veja por onde começar:',
            'steps' => [
                '<strong>Leia a chamada de artigos</strong>{deadline}: tipos de artigo, prazos e como funciona. <a href="{urlCall}">Abrir a chamada</a>',
                '<strong>Escreva no modelo do seu tipo de artigo</strong>, que traz orientação passo a passo e exemplos. <a href="{urlTemplates}">Baixar os modelos</a>',
                '<strong>Quando estiver pronto, cadastre-se e envie</strong> pelo sistema da revista. <a href="{urlRegister}">Criar minha conta</a>',
            ],
            'deadline' => ' (1ª edição: envios até {date}, sem custo de publicação)',
            'stage' => [
                'idea' => 'Ainda está na ideia? Relato de caso e imagem clínica são ótimos para um primeiro artigo: um caso bem documentado já é um bom começo.',
                'data' => 'Já tem o caso ou os dados? O próximo passo é escrever no modelo, seção por seção, seguindo os quadros de orientação.',
                'writing' => 'Já está escrevendo? Confira no modelo o limite de palavras e as referências no estilo Vancouver.',
                'ready' => 'Já está pronto? Separe o manuscrito sem os nomes dos autores, a folha de rosto, a carta de apresentação e o checklist da diretriz (no relato de caso, o CARE): o sistema pede cada um.',
            ],
            'students' => 'Estudantes publicam com o orientador ou outro profissional graduado como coautor responsável.',
            'questions' => 'Ficou alguma dúvida? É só responder a este e-mail.',
            'regards' => 'Atenciosamente,',
            'role' => 'Editor-chefe',
        ],
        'en' => [
            'subject' => 'Your paper in NVAS: where to start',
            'hello' => 'Dear {name},',
            'intro' => 'Thank you for your interest in publishing in <em>Neotropical Veterinary and Animal Science</em> (NVAS). Here is where to start:',
            'steps' => [
                '<strong>Read the call for papers</strong>{deadline}: article types, deadlines and how it works. <a href="{urlCall}">Open the call</a>',
                '<strong>Write using the template for your article type</strong>, with step-by-step guidance and examples. <a href="{urlTemplates}">Download the templates</a>',
                '<strong>When you are ready, register and submit</strong> through the journal system. <a href="{urlRegister}">Create my account</a>',
            ],
            'deadline' => ' (first issue: submissions until {date}, no publication fees)',
            'stage' => [
                'idea' => 'Still at the idea stage? Case reports and clinical images are great for a first paper: a well-documented case is a good start.',
                'data' => 'Do you already have the case or the data? The next step is to write in the template, section by section, following the guidance boxes.',
                'writing' => 'Already writing? Check the word limit and the Vancouver reference style in the template.',
                'ready' => 'Ready to submit? Prepare the manuscript without author names, the title page, the cover letter and the reporting checklist (CARE for case reports): the system asks for each one.',
            ],
            'students' => 'Students publish with their supervisor or another graduate professional as the responsible co-author.',
            'questions' => 'Any questions? Just reply to this e-mail. Manuscripts may be submitted in English or Portuguese.',
            'regards' => 'Kind regards,',
            'role' => 'Editor-in-Chief',
        ],
        'es' => [
            'subject' => 'Su artículo en NVAS: por dónde empezar',
            'hello' => 'Hola, {name}:',
            'intro' => 'Gracias por su interés en publicar en <em>Neotropical Veterinary and Animal Science</em> (NVAS). Le contamos por dónde empezar:',
            'steps' => [
                '<strong>Lea la convocatoria</strong>{deadline}: tipos de artículo, plazos y cómo funciona. <a href="{urlCall}">Abrir la convocatoria</a> (en inglés)',
                '<strong>Escriba con la plantilla de su tipo de artículo</strong>, con orientación paso a paso. <a href="{urlTemplates}">Descargar las plantillas</a> (en inglés)',
                '<strong>Cuando esté listo, regístrese y envíe</strong> por el sistema de la revista. <a href="{urlRegister}">Crear mi cuenta</a>',
            ],
            'deadline' => ' (primer número: envíos hasta el {date}, sin costo de publicación)',
            'stage' => [
                'idea' => '¿Todavía es una idea? El reporte de caso y la imagen clínica son excelentes para un primer artículo.',
                'data' => '¿Ya tiene el caso o los datos? El siguiente paso es escribir con la plantilla, sección por sección.',
                'writing' => '¿Ya está escribiendo? Revise en la plantilla el límite de palabras y las referencias en estilo Vancouver.',
                'ready' => '¿Ya está listo? Prepare el manuscrito sin los nombres de los autores, la página de título, la carta de presentación y la lista de verificación (CARE en reportes de caso).',
            ],
            'students' => 'Los estudiantes publican con su orientador u otro profesional graduado como coautor responsable.',
            'questions' => 'Los manuscritos se envían en inglés o en portugués. ¿Alguna duda? Responda a este correo; puede escribirnos en español.',
            'regards' => 'Atentamente,',
            'role' => 'Editor jefe',
        ],
    ],

    // Aviso e lista para os editores (sempre em português)
    'editor' => [
        'subject' => '[NVAS] Novo interessado: {name} ({type}, {stage})',
        'intro' => 'Alguém preencheu o formulário "Quero publicar". A orientação por e-mail já foi enviada automaticamente{replyLang}.',
        'fields' => [
            'name' => 'Nome',
            'email' => 'E-mail',
            'whatsapp' => 'WhatsApp',
            'contactLang' => 'Idioma para contato',
            'profile' => 'Perfil',
            'institution' => 'Instituição',
            'type' => 'Quer publicar',
            'stage' => 'Fase',
            'message' => 'Mensagem',
            'source' => 'Origem',
            'pageLang' => 'Página',
        ],
        'noReply' => ' (desta vez não: o mesmo e-mail já recebeu a orientação nas últimas 24 horas)',
        'openList' => 'Ver todos os interessados',
        'whatsappLink' => 'Abrir conversa no WhatsApp',
        'listTitle' => 'Interessados em publicar',
        'listLead' => 'Quem preencheu o formulário "Quero publicar". "No sistema" mostra se a pessoa já tem conta na revista e se começou ou concluiu uma submissão (pelo e-mail informado).',
        'csv' => 'Baixar planilha (CSV)',
        'empty' => 'Ninguém preencheu o formulário ainda.',
        'colDate' => 'Data',
        'colPerson' => 'Pessoa',
        'colWork' => 'Trabalho',
        'colSystem' => 'No sistema',
        'noAccount' => 'sem conta',
        'account' => 'tem conta',
        'drafts' => '{n} rascunho(s)',
        'submitted' => '{n} submissão(ões)',
        'privacy' => 'Dados pessoais (LGPD): use só para orientar sobre publicação. Para excluir alguém a pedido, remova a linha da tabela csvet_leads.',
    ],
];
