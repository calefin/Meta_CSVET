<?php

/**
 * @file plugins/generic/csvetReviewQuality/strings.php
 *
 * @brief Mensagens ao avaliador, por idioma.
 */

return [
    'en' => [
        'answerTooShort' => '"{$question}": please write at least {$min} words (you wrote {$count}). Indexed journals expect each review to explain its assessment with specific points from the manuscript.',
        'acceptInconsistent' => 'Your recommendation is "Accept Submission", but some answers indicate a serious problem ({$answers}). Change the recommendation (for example to "Revisions required") or review those answers.',
        'commentsTooShort' => 'Please write at least {$min} words in your review (you wrote {$count}): summary of the manuscript, strengths and weaknesses, and numbered comments for the authors.',
        'promoteFilteredNote' => 'Title page, cover letter, ethics documents, consent forms and permissions are not listed: they contain author names and never go to reviewers.',
        'reviewFileBlocked' => '"{$file}" contains author names (title page, cover letter, ethics, consent or permission) and cannot be made available to reviewers.',
        'downloadBlocked' => 'This file contains the names of the authors and is not available to reviewers (double-anonymous review). Please contact the editor if you need any information from it.',
    ],
    'pt_BR' => [
        'answerTooShort' => '"{$question}": escreva pelo menos {$min} palavras (você escreveu {$count}). As revistas indexadas esperam que cada parecer explique a avaliação com pontos específicos do manuscrito.',
        'acceptInconsistent' => 'Sua recomendação é "Aceitar", mas algumas respostas indicam um problema grave ({$answers}). Mude a recomendação (por exemplo para "Correções obrigatórias") ou revise essas respostas.',
        'commentsTooShort' => 'Escreva pelo menos {$min} palavras no parecer (você escreveu {$count}): resumo do manuscrito, pontos fortes e fracos e comentários numerados para os autores.',
        'promoteFilteredNote' => 'Folha de rosto, carta de apresentação, documentos de ética, termos de consentimento e autorizações não aparecem na lista: eles têm os nomes dos autores e nunca vão aos avaliadores.',
        'reviewFileBlocked' => '"{$file}" tem os nomes dos autores (folha de rosto, carta, ética, termo ou autorização) e não pode ser liberado aos avaliadores.',
        'downloadBlocked' => 'Este arquivo tem os nomes dos autores e não fica disponível aos avaliadores (avaliação duplo-anônima). Fale com o editor se precisar de alguma informação dele.',
    ],
];
