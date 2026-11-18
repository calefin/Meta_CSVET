<x-filament-panels::page>
    <div class="space-y-4">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Guias rápidos de como usar as principais funções da plataforma. Se sentir falta de algum tópico, me avise.
        </p>

        <style>
            .ajuda-topic { border-radius: 1rem; border: 1px solid rgba(0,0,0,0.06); overflow: hidden; }
            .dark .ajuda-topic { border-color: rgba(255,255,255,0.08); }
            .ajuda-topic summary {
                cursor: pointer;
                list-style: none;
                padding: 1rem 1.25rem;
                font-weight: 700;
                display: flex;
                align-items: center;
                gap: 0.75rem;
                background-color: rgba(0,0,0,0.02);
            }
            .dark .ajuda-topic summary { background-color: rgba(255,255,255,0.03); }
            .ajuda-topic summary::-webkit-details-marker { display: none; }
            .ajuda-topic summary::after {
                content: "+";
                margin-left: auto;
                font-size: 1.25rem;
                color: #94a3b8;
            }
            .ajuda-topic[open] summary::after { content: "−"; }
            .ajuda-body { padding: 0 1.25rem 1.25rem; font-size: 0.875rem; line-height: 1.6; }
            .ajuda-body h4 { font-weight: 700; margin-top: 1rem; margin-bottom: 0.375rem; }
            .ajuda-body h4:first-child { margin-top: 0; }
            .ajuda-body ol, .ajuda-body ul { padding-left: 1.25rem; }
            .ajuda-body ol { list-style: decimal; }
            .ajuda-body ul { list-style: disc; }
            .ajuda-body li { margin-bottom: 0.25rem; }
            .ajuda-body code { background: rgba(0,0,0,0.06); padding: 0.1rem 0.35rem; border-radius: 0.35rem; font-size: 0.8125rem; }
            .dark .ajuda-body code { background: rgba(255,255,255,0.08); }
        </style>

        <details class="ajuda-topic" open>
            <summary>📄 Turmas de Estágio — Assinatura Externa (como o aluno envia documentos sem se cadastrar)</summary>
            <div class="ajuda-body">
                <p>
                    Essa função gera um <strong>link público único por turma</strong>. Qualquer aluno da lista que você
                    cadastrou consegue acessar, se identificar com RGM + e-mail e enviar documentos — sem criar
                    conta, sem senha.
                </p>

                <h4>1. Criar a turma e cadastrar os alunos</h4>
                <p>Duas formas de fazer isso, em <strong>Assinatura Externa → Turmas de Estágio</strong>:</p>
                <ul>
                    <li><strong>Tudo de uma vez:</strong> botão <em>"Nova Turma + Importar Planilha"</em> — preenche os dados da turma e já sobe a planilha com os alunos num fluxo só.</li>
                    <li><strong>Em duas etapas:</strong> botão <em>"+ Nova"</em> cria a turma vazia; depois entre em <em>Editar</em> na turma e vá na aba <strong>"Alunos da Turma"</strong> para importar a planilha ou adicionar aluno por aluno manualmente.</li>
                </ul>
                <p>
                    <strong>Atenção:</strong> uma turma sem nenhum aluno cadastrado gera um link público que
                    <strong>ninguém consegue usar</strong> — o sistema não tem com quem comparar o RGM/e-mail digitado.
                    Sempre cadastre pelo menos um aluno antes de compartilhar o link.
                </p>

                <h4>2. Formato da planilha</h4>
                <p>Arquivo <code>.xlsx</code>, <code>.csv</code> ou <code>.txt</code>, com exatamente 3 colunas nesta ordem:</p>
                <ol>
                    <li><strong>Nome</strong></li>
                    <li><strong>RGM</strong></li>
                    <li><strong>E-mail</strong> — obrigatório, é o que o aluno usa pra entrar</li>
                </ol>
                <p>
                    A primeira linha pode ser um cabeçalho (é detectado e pulado automaticamente) ou já o primeiro
                    aluno. <a href="/templates/modelo_turma_estagio.csv" download class="text-primary-600 dark:text-primary-400 font-semibold underline">Baixe a planilha modelo aqui</a>.
                </p>
                <p>
                    Antes de confirmar, o sistema mostra uma <strong>pré-visualização</strong> linha a linha — o que vai
                    ser criado, o que vai ser atualizado e o que tem erro (com o motivo) — pra você conferir antes de
                    importar de verdade.
                </p>
                <p>
                    Reimportar a mesma planilha depois (por exemplo, pra corrigir um e-mail) só <strong>atualiza</strong>
                    quem já existe — identificado pelo RGM — nunca duplica ninguém.
                </p>

                <h4>3. Compartilhar o link com a turma</h4>
                <p>
                    Na listagem de turmas, a coluna <strong>"Link Público"</strong> mostra a URL já pronta (clique pra
                    copiar). É <strong>um único link pra turma inteira</strong> — mande por e-mail, WhatsApp ou onde
                    preferir. Cada aluno, ao abrir, digita o próprio RGM e e-mail (exatamente como você cadastrou) pra
                    entrar e enviar os documentos dele.
                </p>
            </div>
        </details>
    </div>
</x-filament-panels::page>
