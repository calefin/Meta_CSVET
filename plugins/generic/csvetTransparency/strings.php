<?php

/**
 * @file plugins/generic/csvetTransparency/strings.php
 *
 * @brief Textos da transparência editorial, por idioma.
 *  boardNote: {$authors} autores do corpo editorial, {$editor} editor responsável,
 *  {$policyUrl} página Sobre (âncora da política para artigos de editores).
 */

return [
    'en' => [
        'heading' => 'Editorial process',
        'received' => 'Received',
        'accepted' => 'Accepted',
        'editor' => 'Handling editor',
        'peerReview' => 'Double-anonymous peer review by external reviewers.',
        'anotherEditor' => 'an editor with no conflict of interest',
        'boardNote' => '<strong>Editorial board author.</strong> {$authors} is a member of the editorial board of this journal and took no part in the editorial handling of this article: it was handled by {$editor} and reviewed by external reviewers, under the <a href="{$policyUrl}">policy for articles by editors</a>.',
        'boardReviewsRequired' => 'An author of this article is a member of the editorial board: publication requires at least two completed external reviews (reviewers who are neither editors of the journal nor authors of the article). Completed so far: {$count}. The handling editor must invite more reviewers before publishing.',
        'orcidRequired' => 'All authors must have a verified ORCID before publication. Missing for: {$authors}. In the Contributors tab, open each author and click "Request verification" in the ORCID field: the author receives an email and confirms with one click.',
    ],
    'pt_BR' => [
        'heading' => 'Processo editorial',
        'received' => 'Recebido',
        'accepted' => 'Aceito',
        'editor' => 'Editor responsável',
        'peerReview' => 'Avaliação por pares duplo-anônima, por revisores externos.',
        'anotherEditor' => 'um editor sem conflito de interesses',
        'boardNote' => '<strong>Autor do corpo editorial.</strong> {$authors} integra o corpo editorial da revista e não participou do processo editorial deste artigo: o manuscrito foi conduzido por {$editor} e avaliado por revisores externos, conforme a <a href="{$policyUrl}">política para artigos de editores</a>.',
        'boardReviewsRequired' => 'Um autor deste artigo integra o corpo editorial: a publicação exige pelo menos dois pareceres externos concluídos (de avaliadores que não sejam editores da revista nem autores do artigo). Concluídos até agora: {$count}. O editor responsável precisa convidar mais avaliadores antes de publicar.',
        'orcidRequired' => 'Todos os autores precisam de ORCID verificado antes da publicação. Falta para: {$authors}. Na aba Contribuidores, abra cada autor e clique em "Solicitar verificação" no campo ORCID: o autor recebe um e-mail e confirma com um clique.',
    ],
];
