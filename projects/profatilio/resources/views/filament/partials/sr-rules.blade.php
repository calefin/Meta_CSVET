{{-- O que o aluno pode e não pode fazer, pelo papel (Líder = 1º autor; Revisor cego = coautor).
     Segue o termo de confidencialidade e o protocolo v1.1 (IA só a declarada). Recebe $screener e, opcional, $open. --}}
@php
    $isLead = str_starts_with((string) $screener->label, 'Líder');
    $isReviewer = str_starts_with((string) $screener->label, 'Revisor');
@endphp
@once
    <style>
        .sr-rules-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; margin-top: .6rem; }
        .sr-rules-col { border-radius: .7rem; padding: .65rem .8rem; font-size: .85rem; line-height: 1.5; }
        .sr-rules-col.can { background: rgba(22,163,74,.08); border: 1px solid rgba(22,163,74,.35); }
        .sr-rules-col.cannot { background: rgba(220,38,38,.06); border: 1px solid rgba(220,38,38,.35); }
        .sr-rules-col h4 { font-weight: 700; margin-bottom: .3rem; }
        .sr-rules-col ul { margin: 0; padding-left: 1.1rem; list-style: disc; }
        .sr-rules-col li + li { margin-top: .25rem; }
        .sr-rules-role { margin-top: .6rem; font-size: .85rem; line-height: 1.5; padding: .6rem .8rem; border-radius: .7rem; background: rgba(37,99,235,.07); border: 1px solid rgba(37,99,235,.3); }
        @media (max-width: 640px) { .sr-rules-grid { grid-template-columns: 1fr; } }
    </style>
@endonce
<details class="tc-card" style="margin-top:1rem" @if ($open ?? false) open @endif>
    <summary>✅ O que você pode e ❌ o que não pode fazer · seu papel: {{ $screener->label ?: 'Triador(a)' }}</summary>
    <div class="sr-rules-grid">
        <div class="sr-rules-col can">
            <h4>✅ Pode</h4>
            <ul>
                <li>Decidir <strong>sozinho(a)</strong> cada trabalho: Incluir, Excluir (com o motivo) ou Talvez. Pode mudar a sua decisão enquanto a etapa estiver aberta.</li>
                <li>Na dúvida, marcar <strong>Talvez</strong>: no título e resumo, ele leva o trabalho ao texto completo.</li>
                <li>Abrir a fonte (DOI ou PubMed) para ler o resumo inteiro.</li>
                <li>Escrever uma dúvida sobre um trabalho no campo de <strong>observação</strong>: só o professor vê. Leve-a à reunião.</li>
                <li>Usar a <strong>conversa do projeto</strong> para combinar prazos, avisar ausências e tirar dúvidas gerais sobre o protocolo e o site.</li>
                <li>Pedir ajuda ao professor sempre que um critério não estiver claro.</li>
            </ul>
        </div>
        <div class="sr-rules-col cannot">
            <h4>❌ Não pode</h4>
            <ul>
                <li><strong>Ver, perguntar ou comentar as decisões da sua dupla</strong>, nem contar as suas, até o professor encerrar a etapa. Isso vale para a conversa, o WhatsApp e a conversa pessoal.</li>
                <li>Citar estudos, títulos ou autores que está triando, ou comparar números (quantos incluiu ou excluiu).</li>
                <li><strong>Usar IA para triar</strong> (ChatGPT ou outras): não cole títulos, resumos ou PDFs. A única IA permitida é a priorização dentro do site, declarada no protocolo.</li>
                <li>Mudar ou reinterpretar os critérios por conta própria: a dúvida vai ao professor, e uma mudança só entra como emenda registrada.</li>
                <li>Excluir por suposição ou triar sem ler. O professor acompanha o ritmo de cada um.</li>
                <li>Deixar outra pessoa triar por você ou divulgar o tema, o protocolo ou os registros fora do programa antes da publicação (termo de sigilo).</li>
            </ul>
        </div>
    </div>
    @if ($isLead)
        <div class="sr-rules-role"><strong>Você é o(a) 1º autor(a) (líder).</strong>
            O professor faz a busca nas bases; você tria como qualquer revisor, sem combinar nada com a dupla. Na extração, você recebe alguns estudos pré-preenchidos por IA (declarada no protocolo) e confere cada valor no artigo. Você escreve o artigo em inglês. Quem desempata as divergências é só o professor, nunca você.</div>
    @elseif ($isReviewer)
        <div class="sr-rules-role"><strong>Você é o(a) revisor(a) cego(a) (coautor).</strong>
            Você tria de forma independente, sem saber o que o líder decidiu, e não tria "para conferir" o líder. Quando os dois cumprirem a regra de parada, você faz a <strong>checagem aleatória</strong> do que ninguém leu. Na extração, você extrai <strong>sem IA</strong>. Na análise, você reexecuta o script para confirmar os resultados e depois revisa o artigo. Quem desempata é só o professor.</div>
    @endif
</details>
