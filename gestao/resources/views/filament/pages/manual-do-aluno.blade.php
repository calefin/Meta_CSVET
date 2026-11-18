<x-filament-panels::page>
    <div x-data="{ tab: 'normas', norma: 'nbr6023' }" class="space-y-6">
        <style>
            .manual-card {
                backdrop-filter: blur(12px);
                background-color: rgba(255, 255, 255, 0.03);
                border: 1px solid rgba(255, 255, 255, 0.05);
                border-radius: 1rem;
                padding: 1.5rem;
                transition: all 0.3s ease;
            }
            .dark .manual-card {
                background-color: rgba(15, 23, 42, 0.4);
                border: 1px solid rgba(255, 255, 255, 0.05);
            }
            .manual-card:hover {
                border-color: rgba(13, 148, 136, 0.3);
                box-shadow: 0 10px 20px -10px rgba(13, 148, 136, 0.1);
            }
            .step-number {
                background: linear-gradient(135deg, #0d9488 0%, #0284c7 100%);
                color: white;
                font-weight: bold;
                border-radius: 50%;
                width: 2rem;
                height: 2rem;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }
            .nbr-tab-btn {
                padding: 0.5rem 1rem;
                border-radius: 0.5rem;
                font-size: 0.75rem;
                font-weight: 600;
                white-space: nowrap;
                transition: all 0.2s ease;
                border: 1px solid transparent;
            }
            .nbr-tab-btn.active {
                background: linear-gradient(135deg, #0d9488 0%, #0284c7 100%);
                color: white;
                border-color: transparent;
            }
            .nbr-tab-btn:not(.active) {
                color: #94a3b8;
                border-color: rgba(255,255,255,0.08);
            }
            .nbr-tab-btn:not(.active):hover {
                color: white;
                border-color: rgba(13,148,136,0.4);
            }
            .code-block {
                background: #0f172a !important;
                border: 1px solid rgba(255,255,255,0.10);
                border-radius: 0.5rem;
                padding: 0.75rem 1rem;
                font-family: 'Courier New', monospace;
                font-size: 0.8rem;
                line-height: 1.6;
            }
            /* Caixas de exemplo com fundo branco — legíveis em qualquer tema */
            .ex-box {
                background: #ffffff;
                border: 1px solid #cbd5e1;
                border-left: 4px solid #0d9488;
                border-radius: 0.5rem;
                padding: 0.85rem 1rem;
                font-family: 'Times New Roman', Times, serif;
                font-size: 0.83rem;
                line-height: 1.85;
                color: #1e293b;
            }
            .ex-box .lbl {
                font-family: system-ui, sans-serif;
                font-size: 0.65rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.07em;
                color: #0f766e;
                display: block;
                margin-bottom: 4px;
            }
            .ex-box em { font-style: italic; }
            .ex-box strong { font-weight: 700; }
            /* Cards de informação leves */
            .info-card {
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 0.5rem;
                padding: 0.7rem 0.9rem;
                font-size: 0.75rem;
                color: #334155;
                line-height: 1.6;
            }
            .info-card strong { color: #0f172a; }
            .info-card .ic-label { font-weight: 700; color: #0f766e; display: block; margin-bottom: 3px; }
            /* Linha de parâmetro (chave → valor) */
            .param-row {
                background: #f1f5f9;
                border: 1px solid #e2e8f0;
                border-radius: 0.4rem;
                padding: 0.35rem 0.75rem;
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-size: 0.75rem;
                color: #334155;
                gap: 0.5rem;
            }
            .param-row .pk { color: #64748b; }
            /* Card de dica colorido */
            .tip-card {
                border-radius: 0.5rem;
                padding: 0.65rem 0.9rem;
                font-size: 0.75rem;
                line-height: 1.6;
            }
            .badge-norm {
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
                padding: 0.2rem 0.6rem;
                border-radius: 9999px;
                font-size: 0.65rem;
                font-weight: 700;
                letter-spacing: 0.04em;
                text-transform: uppercase;
            }
            .badge-updated { background: rgba(16,185,129,0.15); color: #34d399; border: 1px solid rgba(52,211,153,0.3); }
            .badge-stable  { background: rgba(100,116,139,0.15); color: #94a3b8; border: 1px solid rgba(148,163,184,0.2); }
            .ex-ok  { color: #34d399; }
            .ex-bad { color: #f87171; }
        </style>

        <!-- Navegação de Abas Principais -->
        <div class="flex border-b border-slate-200 dark:border-slate-800 gap-6 overflow-x-auto pb-1">
            <button @click="tab = 'normas'"
                    :class="tab === 'normas' ? 'border-teal-500 text-teal-600 dark:text-teal-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                    class="py-4 px-1 border-b-2 font-medium text-sm transition-all focus:outline-none whitespace-nowrap flex items-center gap-2">
                📐 Normas ABNT Vigentes
            </button>
            <button @click="tab = 'estrutura'"
                    :class="tab === 'estrutura' ? 'border-teal-500 text-teal-600 dark:text-teal-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                    class="py-4 px-1 border-b-2 font-medium text-sm transition-all focus:outline-none whitespace-nowrap flex items-center gap-2">
                🗂️ Estrutura dos Trabalhos
            </button>
            <button @click="tab = 'dicas'"
                    :class="tab === 'dicas' ? 'border-teal-500 text-teal-600 dark:text-teal-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                    class="py-4 px-1 border-b-2 font-medium text-sm transition-all focus:outline-none whitespace-nowrap flex items-center gap-2">
                💡 Escrita Científica
            </button>
            <button @click="tab = 'modelos'"
                    :class="tab === 'modelos' ? 'border-teal-500 text-teal-600 dark:text-teal-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                    class="py-4 px-1 border-b-2 font-medium text-sm transition-all focus:outline-none whitespace-nowrap flex items-center gap-2">
                📥 Templates & Downloads
            </button>
        </div>


        {{-- ===================================================================
             ABA 1: NORMAS ABNT VIGENTES
        =================================================================== --}}
        <div x-show="tab === 'normas'" class="space-y-5" style="display: none;">

            <!-- Aviso Atualização 2025 -->
            <div class="p-4 bg-teal-950/30 border border-teal-700/40 rounded-xl flex items-start gap-3">
                <span class="text-xl mt-0.5">📢</span>
                <div>
                    <h4 class="text-sm font-bold text-teal-300 mb-1">Atenção — Normas Atualizadas em 2024/2025</h4>
                    <p class="text-xs text-slate-300 leading-relaxed font-light">
                        A <strong>NBR 6023</strong> foi revisada em 2025 (referências), a <strong>NBR 14724</strong> em 2024 (formatação geral) e a <strong>NBR 10520</strong> em 2023 (citações).
                        Documentos que ainda seguem a versão de 2011 da NBR 14724 ou a de 2018 da NBR 6023 podem ser reprovados na auditoria automática. Use sempre as versões indicadas abaixo.
                    </p>
                </div>
            </div>

            <!-- Navegação das normas -->
            <div class="flex flex-wrap gap-2">
                <button @click="norma = 'nbr6023'" :class="norma === 'nbr6023' ? 'active' : ''" class="nbr-tab-btn">NBR 6023 · 2025</button>
                <button @click="norma = 'nbr6024'" :class="norma === 'nbr6024' ? 'active' : ''" class="nbr-tab-btn">NBR 6024 · 2012</button>
                <button @click="norma = 'nbr6027'" :class="norma === 'nbr6027' ? 'active' : ''" class="nbr-tab-btn">NBR 6027 · 2012</button>
                <button @click="norma = 'nbr6028'" :class="norma === 'nbr6028' ? 'active' : ''" class="nbr-tab-btn">NBR 6028 · 2021</button>
                <button @click="norma = 'nbr10520'" :class="norma === 'nbr10520' ? 'active' : ''" class="nbr-tab-btn">NBR 10520 · 2023</button>
                <button @click="norma = 'nbr14724'" :class="norma === 'nbr14724' ? 'active' : ''" class="nbr-tab-btn">NBR 14724 · 2024</button>
            </div>

            {{-- ------ NBR 6023:2025 — Referências ------ --}}
            <div x-show="norma === 'nbr6023'" class="space-y-4">
                <div class="manual-card">
                    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                        <h3 class="text-lg font-bold text-white flex items-center gap-2">📚 NBR 6023 — Referências Bibliográficas</h3>
                        <span class="badge-norm badge-updated">✦ Atualizada em 2025</span>
                    </div>
                    <p class="text-sm text-slate-300 leading-relaxed font-light mb-5">
                        Regula a elaboração das referências ao final do trabalho. A versão de 2025 substitui a NBR 6023:2018 e amplia os exemplos para formatos digitais (redes sociais, podcasts, vídeos, e-books) e clarifica a ordem e pontuação dos elementos.
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <!-- Ordem dos elementos -->
                        <div>
                            <h4 class="text-sm font-bold text-teal-400 mb-2">Ordem dos elementos</h4>
                            <div style="background:#ffffff; color:#1e293b; border:1px solid #cbd5e1; border-radius:0.5rem; padding:0.9rem 1rem; font-family:'Times New Roman',serif; font-size:0.85rem; line-height:1.8;">
                                SOBRENOME, Nome. <em style="font-style:italic;">Título</em>: subtítulo.<br>
                                ed. Local: Editora, Ano. Páginas. DOI/URL.
                            </div>
                        </div>
                        <!-- Exemplos -->
                        <div class="space-y-2">
                            <h4 class="text-sm font-bold text-teal-400 mb-2">Exemplos</h4>
                            <div style="background:#ffffff; color:#1e293b; border:1px solid #cbd5e1; border-radius:0.5rem; padding:0.9rem 1rem; font-family:'Times New Roman',serif; font-size:0.78rem; line-height:1.8;">
                                <p style="margin:0 0 10px 0;"><span style="font-weight:700; color:#0f766e; font-family:sans-serif; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.05em;">Livro</span><br>
                                ETTINGER, S. J.; FELDMAN, E. C. <em style="font-style:italic;">Tratado de medicina interna veterinária.</em> 8. ed. Rio de Janeiro: Guanabara Koogan, 2017.</p>
                                <p style="margin:0 0 10px 0;"><span style="font-weight:700; color:#0f766e; font-family:sans-serif; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.05em;">Artigo</span><br>
                                SILVA, J. A.; COSTA, M. B. Título do artigo. <em style="font-style:italic;">Revista Brasileira de Medicina Veterinária,</em> Rio de Janeiro, v. 45, n. 2, p. 123-130, 2023. DOI: 10.1234/rbmv.2023.45.2.</p>
                                <p style="margin:0;"><span style="font-weight:700; color:#0f766e; font-family:sans-serif; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.05em;">Site/Online</span><br>
                                CFMV. Resolução n. 1498/2023. Brasília, 2023. Disponível em: https://www.cfmv.gov.br/resolucao-1498. Acesso em: 5 jun. 2026.</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="info-card">
                            <span class="ic-label">Múltiplos autores</span>
                            Até 3: liste todos separados por ponto-e-vírgula. Com 4 ou mais: primeiro autor + <em>et al.</em>
                        </div>
                        <div class="info-card">
                            <span class="ic-label">Alinhamento na lista</span>
                            À esquerda · espaço simples dentro · linha em branco entre · ordem alfabética pelo sobrenome do 1.º autor.
                        </div>
                        <div class="info-card">
                            <span class="ic-label">Documentos digitais — novidade NBR 6023:2025</span>
                            URL completa + data de acesso. Vídeos: incluir duração e canal. Redes sociais: incluir plataforma e data da publicação.
                        </div>
                    </div>
                </div>
            </div>

            {{-- ------ NBR 6024:2012 — Numeração progressiva ------ --}}
            <div x-show="norma === 'nbr6024'" class="space-y-4" style="display: none;">
                <div class="manual-card">
                    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                        <h3 class="text-lg font-bold text-white flex items-center gap-2">🔢 NBR 6024 — Numeração Progressiva de Seções</h3>
                        <span class="badge-norm badge-stable">Vigente desde 2012</span>
                    </div>
                    <p class="text-sm text-slate-300 leading-relaxed font-light mb-5">
                        Define como numerar as seções do texto para facilitar a navegação. Utiliza algarismos arábicos, sem ponto ao final da última seção numerada.
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <h4 class="text-sm font-bold text-teal-400 mb-3">Hierarquia de seções (máx. 5 níveis)</h4>
                            <div class="ex-box space-y-1" style="font-family:'Times New Roman',serif; font-size:0.9rem; line-height:2;">
                                <p style="font-weight:700; text-transform:uppercase; color:#0f172a;">1 INTRODUÇÃO</p>
                                <p style="text-transform:uppercase; color:#1e293b; padding-left:1.25rem;">1.1 CONTEXTUALIZAÇÃO</p>
                                <p style="font-weight:700; color:#1e293b; padding-left:2.5rem;">1.1.1 Epidemiologia</p>
                                <p style="font-style:italic; color:#334155; padding-left:3.75rem;">1.1.1.1 Dados nacionais</p>
                                <p style="color:#475569; padding-left:5rem;">1.1.1.1.1 Série histórica</p>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <h4 class="text-sm font-bold text-teal-400 mb-3">Regras de formatação por nível</h4>
                            <div class="param-row">
                                <span class="pk font-bold" style="color:#0f766e; min-width:5rem;">Seção 1</span>
                                <span style="color:#1e293b;">MAIÚSCULAS + <strong>negrito</strong> + nova página</span>
                            </div>
                            <div class="param-row">
                                <span class="pk font-bold" style="color:#0f766e; min-width:5rem;">Seção 1.1</span>
                                <span style="color:#1e293b;">MAIÚSCULAS — sem negrito</span>
                            </div>
                            <div class="param-row">
                                <span class="pk font-bold" style="color:#0f766e; min-width:5rem;">Seção 1.1.1</span>
                                <span style="color:#1e293b;">Title Case + <strong>negrito</strong></span>
                            </div>
                            <div class="param-row">
                                <span class="pk font-bold" style="color:#0f766e; min-width:5rem;">1.1.1.1</span>
                                <span style="color:#1e293b;">Title Case + <em>itálico</em></span>
                            </div>
                            <div class="param-row">
                                <span class="pk font-bold" style="color:#0f766e; min-width:5rem;">1.1.1.1.1</span>
                                <span style="color:#1e293b;">Title Case — sem destaque</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 p-3 rounded-lg text-xs" style="background:#fffbeb; border:1px solid #fde68a; color:#92400e;">
                        <strong>⚠ Atenção:</strong> Elementos pré-textuais (resumo, sumário, listas) e pós-textuais (referências, apêndices) <strong>não recebem numeração progressiva</strong>. Seus títulos aparecem centralizados sem número.
                    </div>
                </div>
            </div>

            {{-- ------ NBR 6027:2012 — Sumário ------ --}}
            <div x-show="norma === 'nbr6027'" class="space-y-4" style="display: none;">
                <div class="manual-card">
                    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                        <h3 class="text-lg font-bold text-white flex items-center gap-2">📋 NBR 6027 — Sumário</h3>
                        <span class="badge-norm badge-stable">Vigente desde 2012</span>
                    </div>
                    <p class="text-sm text-slate-300 leading-relaxed font-light mb-5">
                        O sumário lista as seções na ordem em que aparecem no trabalho, com o indicativo numérico, título e número de página. Não confundir com <em>índice</em> (lista temática ao final).
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <h4 class="text-sm font-bold text-teal-400 mb-3">Exemplo de formatação</h4>
                            <div class="ex-box" style="font-family:'Times New Roman',serif; font-size:0.82rem; line-height:2;">
                                <p style="text-align:center; font-weight:700; margin-bottom:8px; letter-spacing:0.05em;">SUMÁRIO</p>
                                <p style="display:flex; justify-content:space-between;"><strong>1 INTRODUÇÃO</strong><span style="color:#94a3b8;">10</span></p>
                                <p style="display:flex; justify-content:space-between; padding-left:1.5rem;">1.1 CONTEXTUALIZAÇÃO<span style="color:#94a3b8;">12</span></p>
                                <p style="display:flex; justify-content:space-between; padding-left:1.5rem;">1.2 OBJETIVOS<span style="color:#94a3b8;">13</span></p>
                                <p style="display:flex; justify-content:space-between;"><strong>2 REVISÃO DE LITERATURA</strong><span style="color:#94a3b8;">14</span></p>
                                <p style="display:flex; justify-content:space-between; margin-top:6px; color:#475569;">REFERÊNCIAS<span style="color:#94a3b8;">45</span></p>
                                <p style="display:flex; justify-content:space-between; color:#475569;">APÊNDICE A<span style="color:#94a3b8;">48</span></p>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <h4 class="text-sm font-bold text-teal-400 mb-2">Regras principais</h4>
                            <ul class="list-disc pl-5 text-xs text-slate-300 space-y-2 font-light leading-relaxed">
                                <li>Título <strong>"SUMÁRIO"</strong> centralizado, sem numeração progressiva.</li>
                                <li>Tipografia idêntica à usada no corpo do texto (mesmo nível de destaque da seção).</li>
                                <li>Alinhamento do título à esquerda e o número de página à direita, ligados por pontos.</li>
                                <li>Elementos pré-textuais (resumo, listas) <strong>não</strong> constam no sumário.</li>
                                <li>Referências, apêndices e anexos aparecem ao final, sem número progressivo.</li>
                                <li>Em Word/Docs: use <em>Estilos de Título</em> para gerar o sumário automaticamente.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ------ NBR 6028:2021 — Resumo ------ --}}
            <div x-show="norma === 'nbr6028'" class="space-y-4" style="display: none;">
                <div class="manual-card">
                    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                        <h3 class="text-lg font-bold text-white flex items-center gap-2">📄 NBR 6028 — Resumo e Abstract</h3>
                        <span class="badge-norm badge-stable">Vigente desde 2021</span>
                    </div>
                    <p class="text-sm text-slate-300 leading-relaxed font-light mb-5">
                        Define o conteúdo, extensão e apresentação do resumo em língua vernácula e do abstract em língua estrangeira. A versão de 2021 substituiu a NBR 6028:2003.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
                        <div class="info-card">
                            <span class="ic-label">TCC / Monografia</span>
                            <strong>150 a 500 palavras.</strong> Inclui: objetivo, metodologia, resultados e conclusão.
                        </div>
                        <div class="info-card">
                            <span class="ic-label">Dissertação / Tese</span>
                            <strong>150 a 500 palavras.</strong> Mesmo conteúdo; abstract em inglês obrigatório.
                        </div>
                        <div class="info-card">
                            <span class="ic-label">Artigo Científico</span>
                            <strong>100 a 250 palavras.</strong> Estilo informativo (inclui resultados numéricos).
                        </div>
                    </div>

                    <h4 class="text-sm font-bold text-teal-400 mb-3">Regras de redação e apresentação</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <ul class="list-disc pl-5 text-xs text-slate-300 space-y-2 font-light leading-relaxed">
                            <li>Texto em <strong>parágrafo único</strong>, sem recuo, sem subdivisões.</li>
                            <li>Voz ativa (preferencialmente) ou passiva, na <strong>3.ª pessoa do singular</strong>.</li>
                            <li><strong>Sem citações</strong>, notas de rodapé, abreviações não convencionais ou símbolos.</li>
                            <li>Evitar frases negativas e enumerações.</li>
                        </ul>
                        <ul class="list-disc pl-5 text-xs text-slate-300 space-y-2 font-light leading-relaxed">
                            <li><strong>Palavras-chave</strong>: de 3 a 6, logo abaixo do resumo, separadas por ponto-e-vírgula, com inicial maiúscula apenas na primeira.</li>
                            <li>No abstract: <strong>Keywords</strong> com as mesmas regras, em inglês.</li>
                            <li>Título do elemento <strong>"Resumo"</strong> centralizado, sem numeração.</li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- ------ NBR 10520:2023 — Citações ------ --}}
            <div x-show="norma === 'nbr10520'" class="space-y-4" style="display: none;">
                <div class="manual-card">
                    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                        <h3 class="text-lg font-bold text-white flex items-center gap-2">💬 NBR 10520 — Citações no Texto</h3>
                        <span class="badge-norm badge-updated">✦ Atualizada em 2023</span>
                    </div>
                    <p class="text-sm text-slate-300 leading-relaxed font-light mb-1">
                        A versão de 2023 substituiu a NBR 10520:2002 e trouxe mudanças relevantes, especialmente na capitalização do sobrenome dentro dos parênteses.
                    </p>

                    <!-- Mudança principal em destaque -->
                    <div class="my-4 p-4 bg-red-950/20 border border-red-800/40 rounded-xl">
                        <h4 class="text-sm font-bold text-red-300 mb-3">⚠️ Principal mudança — Sistema Autor-Data</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                            <div class="p-3 bg-red-950/40 border border-red-900/50 rounded-lg">
                                <p class="font-bold text-red-400 mb-1">❌ Formato antigo (NBR 10520:2002)</p>
                                <p class="font-mono text-red-300">(CALEFI, 2026)</p>
                                <p class="font-mono text-red-300">(SILVA; COSTA, 2024)</p>
                                <p class="font-mono text-red-300">(ETTINGER et al., 2017)</p>
                            </div>
                            <div class="p-3 bg-emerald-950/40 border border-emerald-900/50 rounded-lg">
                                <p class="font-bold text-emerald-400 mb-1">✔️ Formato vigente (NBR 10520:2023)</p>
                                <p class="font-mono text-emerald-300">(Calefi, 2026)</p>
                                <p class="font-mono text-emerald-300">(Silva; Costa, 2024)</p>
                                <p class="font-mono text-emerald-300">(Ettinger <em>et al.</em>, 2017)</p>
                            </div>
                        </div>
                        <p class="text-xs text-slate-400 font-light mt-2">
                            O sobrenome dentro do parêntese agora usa <strong>Title Case</strong> (apenas a inicial maiúscula), não mais caixa alta total. <strong>Esta é a regra auditada automaticamente pela plataforma.</strong>
                        </p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="space-y-3">
                            <h4 class="text-sm font-bold text-teal-400">1. Citação direta curta (até 3 linhas)</h4>
                            <div class="ex-box">
                                Segundo Calefi (2026, p. 45), <strong>"os elasmobranquios apresentam resposta inflamatória distinta à dos teleósteos de água doce"</strong>.
                            </div>

                            <h4 class="text-sm font-bold text-teal-400">2. Citação direta longa (≥ 4 linhas)</h4>
                            <div class="ex-box" style="padding-left:0.6rem;">
                                <span style="color:#64748b; font-size:0.72rem; font-family:system-ui;">▶ Recuo de 4 cm, fonte 10, espaço simples, sem aspas:</span>
                                <blockquote style="margin:6px 0 0 1.5rem; border-left:2px solid #0d9488; padding-left:0.75rem; color:#334155; font-size:0.8rem; line-height:1.8;">
                                    [...] achados histopatológicos compatíveis com esteatose hepática de grau II, considerada evolutivamente favorável nesta espécie. (Calefi, 2026, p. 78)
                                </blockquote>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <h4 class="text-sm font-bold text-teal-400">3. Citação indireta (paráfrase)</h4>
                            <div class="ex-box">
                                Estudos recentes demonstraram que a resposta imune dos elasmobranquios difere dos teleósteos em relação à produção de IgM (Calefi, 2026).
                            </div>

                            <h4 class="text-sm font-bold text-teal-400">4. Expressões especiais</h4>
                            <div class="space-y-2">
                                <div class="info-card">
                                    <span class="ic-label"><em>apud</em> — citação de citação (em itálico)</span>
                                    (Freire, 1982, <em>apud</em> Silva, 2024, p. 10)
                                </div>
                                <div class="info-card">
                                    <span class="ic-label"><em>et al.</em> — 3 ou mais autores (em itálico)</span>
                                    (Ettinger <em>et al.</em>, 2017)
                                </div>
                                <div class="info-card">
                                    <span class="ic-label">E-book / doc. digital — localizador</span>
                                    (Silva, 2023, loc. 194)
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ------ NBR 14724:2024 — Apresentação de trabalhos ------ --}}
            <div x-show="norma === 'nbr14724'" class="space-y-4" style="display: none;">
                <div class="manual-card">
                    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                        <h3 class="text-lg font-bold text-white flex items-center gap-2">📏 NBR 14724 — Apresentação de Trabalhos Acadêmicos</h3>
                        <span class="badge-norm badge-updated">✦ Atualizada em 2024</span>
                    </div>
                    <p class="text-sm text-slate-300 leading-relaxed font-light mb-5">
                        Norma principal de formatação. A versão de 2024 (com versão corrigida em 2025) substitui a NBR 14724:2011. Aplica-se a monografias, TCC, dissertações e teses.
                    </p>

                    <!-- Grade de formatação -->
                    <h4 class="text-sm font-bold text-teal-400 mb-3">Configurações obrigatórias de página</h4>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-5">
                        @foreach([
                            ['Margem Superior','3 cm','#0d9488'],
                            ['Margem Esquerda','3 cm','#0d9488'],
                            ['Margem Inferior','2 cm','#475569'],
                            ['Margem Direita','2 cm','#475569'],
                            ['Fonte','Arial / Times 12','#1e293b'],
                            ['Entrelinhas','1,5','#1e293b'],
                            ['Recuo 1.ª linha','1,25 cm','#1e293b'],
                            ['Alinhamento','Justificado','#1e293b'],
                        ] as $m)
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:0.5rem; padding:0.6rem 0.5rem; text-align:center;">
                            <p style="font-size:0.65rem; color:#64748b; margin-bottom:4px;">{{ $m[0] }}</p>
                            <p style="font-size:1rem; font-weight:700; color:{{ $m[2] }}; line-height:1.2;">{{ $m[1] }}</p>
                        </div>
                        @endforeach
                    </div>

                    <!-- Elementos -->
                    <h4 class="text-sm font-bold text-teal-400 mb-3">Estrutura e elementos do trabalho</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="info-card" style="border-left:3px solid #3b82f6;">
                            <span style="font-weight:700; color:#2563eb; font-size:0.72rem; display:block; margin-bottom:4px;">Pré-textuais</span>
                            Capa (obrigatório) · Folha de rosto · Errata · Folha de aprovação · Dedicatória · Agradecimentos · Epígrafe · <strong>Resumo</strong> + Palavras-chave · <strong>Abstract</strong> + Keywords · Listas · <strong>Sumário</strong>
                        </div>
                        <div class="info-card" style="border-left:3px solid #0d9488;">
                            <span style="font-weight:700; color:#0f766e; font-size:0.72rem; display:block; margin-bottom:4px;">Textuais</span>
                            <strong>Introdução</strong> · Revisão de Literatura · Material e Métodos · Resultados · Discussão · <strong>Conclusão</strong>
                        </div>
                        <div class="info-card" style="border-left:3px solid #8b5cf6;">
                            <span style="font-weight:700; color:#7c3aed; font-size:0.72rem; display:block; margin-bottom:4px;">Pós-textuais</span>
                            <strong>Referências</strong> (obrigatório) · Glossário · Apêndices (material próprio) · Anexos (material externo) · Índice
                        </div>
                    </div>

                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="info-card">
                            <span class="ic-label">Paginação</span>
                            Contagem a partir da folha de rosto, mas o número só aparece a partir da <strong>Introdução</strong>. Posição: canto <strong>superior direito</strong>, fonte 10.
                        </div>
                        <div class="info-card">
                            <span class="ic-label">Notas de rodapé</span>
                            Fonte 10, espaço simples. Use apenas para notas explicativas — <strong>referências bibliográficas</strong> ficam no texto (autor-data) ou na lista final.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Aviso da Plataforma -->
            <div class="p-4 bg-red-950/20 border border-red-900/50 rounded-xl flex items-start gap-3">
                <span class="text-xl mt-0.5">🤖</span>
                <div>
                    <h4 class="text-sm font-bold text-red-400 mb-1">Auditoria Automática de ABNT + Terminologia</h4>
                    <p class="text-xs text-slate-300 leading-relaxed font-light">
                        Toda submissão passa por três verificações automáticas: <strong>(1)</strong> rastreamento de citações em caixa alta total (padrão 2002, agora abolido pela NBR 10520:2023);
                        <strong>(2)</strong> verificação de nomenclatura — nunca use <span class="text-red-400 font-mono">"tutor"</span> para o responsável pelo animal; use <span class="text-teal-400 font-mono">"responsável legal"</span> ou <span class="text-teal-400 font-mono">"proprietário"</span>;
                        <strong>(3)</strong> validação de escopo biológico para trabalhos de biologia marinha ou patologia veterinária.
                    </p>
                </div>
            </div>
        </div>


        {{-- ===================================================================
             ABA 2: ESTRUTURA DOS TRABALHOS
        =================================================================== --}}
        <div x-show="tab === 'estrutura'" class="space-y-5" style="display: none;">

            <div class="manual-card">
                <h3 class="text-lg font-bold text-white mb-4">Estrutura por Tipo de Trabalho Acadêmico</h3>
                <p class="text-sm text-slate-300 font-light leading-relaxed mb-6">
                    Cada tipo de trabalho gerenciado nesta plataforma exige uma estrutura específica. Use as abas abaixo como referência antes de redigir seu documento.
                </p>

                <div x-data="{ tipo: 'tcc' }" class="space-y-4">
                    <div class="flex flex-wrap gap-2">
                        <button @click="tipo = 'tcc'" :class="tipo==='tcc'?'active':''" class="nbr-tab-btn">TCC / Monografia</button>
                        <button @click="tipo = 'artigo'" :class="tipo==='artigo'?'active':''" class="nbr-tab-btn">Artigo Científico</button>
                        <button @click="tipo = 'ic'" :class="tipo==='ic'?'active':''" class="nbr-tab-btn">Projeto / Relatório de IC</button>
                        <button @click="tipo = 'caso'" :class="tipo==='caso'?'active':''" class="nbr-tab-btn">Caso Clínico</button>
                        <button @click="tipo = 'estagio'" :class="tipo==='estagio'?'active':''" class="nbr-tab-btn">Relatório de Estágio</button>
                    </div>

                    <!-- TCC -->
                    <div x-show="tipo === 'tcc'" class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="space-y-2">
                            <h4 class="text-sm font-bold text-teal-400">Elementos obrigatórios</h4>
                            <ol class="space-y-1 text-xs text-slate-300 font-light">
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">1</span> Capa (NBR 14724:2024)</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">2</span> Folha de rosto</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">3</span> Folha de aprovação (após defesa)</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">4</span> Resumo em português + palavras-chave (NBR 6028:2021)</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">5</span> Abstract em inglês + Keywords</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">6</span> Listas de figuras e tabelas (se houver)</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">7</span> Sumário (NBR 6027:2012)</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">8</span> Introdução</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">9</span> Revisão de Literatura</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">10</span> Material e Métodos</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">11</span> Resultados e Discussão</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">12</span> Conclusão</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">13</span> Referências (NBR 6023:2025)</li>
                            </ol>
                        </div>
                        <div class="space-y-3">
                            <h4 class="text-sm font-bold text-teal-400">Parâmetros</h4>
                            <div class="space-y-2 text-xs text-slate-300">
                                <div class="param-row"><span class="pk">Extensão mínima</span><span>30 páginas</span></div>
                                <div class="param-row"><span class="pk">Resumo</span><span>150–500 palavras</span></div>
                                <div class="param-row"><span class="pk">Palavras-chave</span><span>3 a 6</span></div>
                                <div class="param-row"><span class="pk">Citações</span><span>NBR 10520:2023</span></div>
                                <div class="param-row"><span class="pk">Referências</span><span>NBR 6023:2025</span></div>
                                <div class="param-row"><span class="pk">Formato entrega</span><span>PDF (via portal)</span></div>
                            </div>
                        </div>
                    </div>

                    <!-- Artigo -->
                    <div x-show="tipo === 'artigo'" class="grid grid-cols-1 md:grid-cols-2 gap-5" style="display: none;">
                        <div class="space-y-2">
                            <h4 class="text-sm font-bold text-teal-400">Estrutura IMRaD</h4>
                            <ol class="space-y-1 text-xs text-slate-300 font-light">
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">1</span> Título (em português e inglês)</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">2</span> Autoria e filiação institucional</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">3</span> Resumo (100–250 palavras) + Palavras-chave</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">4</span> Abstract + Keywords</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">5</span> Introdução</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">6</span> Material e Métodos</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">7</span> Resultados</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">8</span> Discussão</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">9</span> Conclusão</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">10</span> Referências</li>
                            </ol>
                            <p class="text-xs text-slate-500 font-light mt-2">Norma base: NBR 6022:2018 — Artigo em publicação periódica científica.</p>
                        </div>
                        <div class="space-y-3">
                            <h4 class="text-sm font-bold text-teal-400">Parâmetros e dicas</h4>
                            <div class="space-y-2 text-xs text-slate-300">
                                <div class="param-row"><span class="pk">Extensão típica</span><span>6 a 15 páginas</span></div>
                                <div class="param-row"><span class="pk">Figuras/tabelas</span><span>Legenda abaixo (figura) / acima (tabela)</span></div>
                                <div class="param-row"><span class="pk">Espaçamento</span><span>Conforme periódico alvo</span></div>
                            </div>
                            <div class="p-3 bg-amber-950/20 border border-amber-800/30 rounded-lg text-xs text-amber-200">
                                Verifique as <strong>normas do periódico alvo</strong> (ex: template de submissão da revista) — elas prevalecem sobre a NBR 6022 em caso de conflito.
                            </div>
                        </div>
                    </div>

                    <!-- IC -->
                    <div x-show="tipo === 'ic'" class="grid grid-cols-1 md:grid-cols-2 gap-5" style="display: none;">
                        <div>
                            <h4 class="text-sm font-bold text-teal-400 mb-2">Projeto de IC</h4>
                            <ol class="space-y-1 text-xs text-slate-300 font-light mb-4">
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">1</span> Identificação (aluno, orientador, período)</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">2</span> Título do projeto</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">3</span> Resumo do projeto (até 300 palavras)</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">4</span> Introdução e justificativa</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">5</span> Objetivos (geral e específicos)</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">6</span> Material e métodos</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">7</span> Cronograma de atividades</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">8</span> Referências</li>
                            </ol>
                            <h4 class="text-sm font-bold text-teal-400 mb-2">Relatório Periódico de IC</h4>
                            <ol class="space-y-1 text-xs text-slate-300 font-light">
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">1</span> Identificação e período do relatório</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">2</span> Atividades desenvolvidas no período</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">3</span> Resultados parciais obtidos</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">4</span> Dificuldades e encaminhamentos</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">5</span> Planejamento para o próximo período</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">6</span> Referências utilizadas</li>
                            </ol>
                        </div>
                        <div class="space-y-3">
                            <h4 class="text-sm font-bold text-teal-400">Prazos de entrega (trimestral)</h4>
                            <div class="space-y-2 text-xs text-slate-300">
                                <div class="param-row"><span class="pk">1.º relatório</span><span>Até 31/03</span></div>
                                <div class="param-row"><span class="pk">2.º relatório</span><span>Até 30/06</span></div>
                                <div class="param-row"><span class="pk">3.º relatório</span><span>Até 30/09</span></div>
                                <div class="param-row"><span class="pk">Relatório final</span><span>Até 30/11</span></div>
                            </div>
                            <p class="text-xs text-slate-500 font-light">Acompanhe os alertas de prazo no painel. O sistema sinaliza em amarelo (15 dias) e vermelho (5 dias) antes do vencimento.</p>
                        </div>
                    </div>

                    <!-- Caso Clínico -->
                    <div x-show="tipo === 'caso'" class="grid grid-cols-1 md:grid-cols-2 gap-5" style="display: none;">
                        <div>
                            <h4 class="text-sm font-bold text-teal-400 mb-2">Estrutura do Relato de Caso Clínico</h4>
                            <ol class="space-y-1 text-xs text-slate-300 font-light">
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">1</span> Título (descritivo, incluindo espécie)</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">2</span> Resumo (100–250 palavras) + Palavras-chave</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">3</span> Introdução (relevância do caso)</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">4</span> Descrição do caso</li>
                                <li class="flex gap-2 pl-4 text-slate-400"><span class="w-4">—</span> Identificação do animal (espécie, sexo, idade, peso)</li>
                                <li class="flex gap-2 pl-4 text-slate-400"><span class="w-4">—</span> Anamnese e queixa principal</li>
                                <li class="flex gap-2 pl-4 text-slate-400"><span class="w-4">—</span> Exame físico e achados clínicos</li>
                                <li class="flex gap-2 pl-4 text-slate-400"><span class="w-4">—</span> Exames complementares (laudos, imagens)</li>
                                <li class="flex gap-2 pl-4 text-slate-400"><span class="w-4">—</span> Diagnóstico</li>
                                <li class="flex gap-2 pl-4 text-slate-400"><span class="w-4">—</span> Tratamento e evolução</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">5</span> Discussão</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">6</span> Conclusão</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">7</span> Referências</li>
                            </ol>
                        </div>
                        <div class="space-y-3">
                            <h4 class="text-sm font-bold text-teal-400">Cuidados específicos</h4>
                            <ul class="list-disc pl-5 text-xs text-slate-300 space-y-2 font-light leading-relaxed">
                                <li>Identifique o proprietário como <strong>"responsável legal"</strong> — nunca "tutor".</li>
                                <li>Nomes científicos de espécies em <em>itálico</em>: <em>Carcharias taurus</em>, <em>Canis lupus familiaris</em>.</li>
                                <li>Dosagens de fármacos em mg/kg ou mg/m² com via de administração e frequência.</li>
                                <li>Imagens histopatológicas com barra de escala e aumento da objetiva (ex: 40×).</li>
                                <li>Aprovação CEUA obrigatória para procedimentos experimentais.</li>
                                <li>Consentimento do responsável legal documentado (Termo de aceite).</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Relatório de Estágio -->
                    <div x-show="tipo === 'estagio'" class="grid grid-cols-1 md:grid-cols-2 gap-5" style="display: none;">
                        <div>
                            <h4 class="text-sm font-bold text-teal-400 mb-2">Estrutura do Relatório de Estágio</h4>
                            <ol class="space-y-1 text-xs text-slate-300 font-light">
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">1</span> Capa (NBR 14724:2024)</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">2</span> Folha de rosto</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">3</span> Resumo (150–300 palavras)</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">4</span> Sumário</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">5</span> Introdução e dados do local de estágio</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">6</span> Atividades desenvolvidas</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">7</span> Casos acompanhados (tabela ou descrição)</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">8</span> Discussão e aprendizado</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">9</span> Conclusão</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">10</span> Referências</li>
                                <li class="flex gap-2"><span class="text-teal-500 font-bold w-4">11</span> Anexos (declaração do supervisor)</li>
                            </ol>
                        </div>
                        <div class="space-y-3">
                            <h4 class="text-sm font-bold text-teal-400">Documentos de suporte</h4>
                            <div class="space-y-2 text-xs text-slate-300">
                                <div class="info-card"><strong>Plano de estágio:</strong> submetido via portal antes do início.</div>
                                <div class="info-card"><strong>Avaliações periódicas:</strong> formulários do supervisor (Aval. Estágio).</div>
                                <div class="info-card"><strong>Relatório final:</strong> entregue até 30 dias após o término.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        {{-- ===================================================================
             ABA 3: ESCRITA CIENTÍFICA
        =================================================================== --}}
        <div x-show="tab === 'dicas'" class="space-y-6" style="display: none;">

            {{-- ORDEM DE REDAÇÃO --}}
            <div class="p-5 bg-amber-950/20 border border-amber-700/40 rounded-2xl">
                <h3 class="text-base font-bold text-amber-300 mb-1 flex items-center gap-2">
                    ✏️ Ordem Recomendada de Redação
                    <span class="text-xs font-normal text-amber-500 ml-1">(≠ ordem de leitura do documento)</span>
                </h3>
                <p class="text-xs text-amber-200/70 font-light mb-4 leading-relaxed">
                    A ordem das seções no documento final não é a ordem em que você deve escrever.
                    <strong class="text-amber-200">Só é possível introduzir aquilo que já existe.</strong>
                    Portanto, Introdução e Resumo são sempre os últimos a serem redigidos.
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach([
                        ['1.º','Definição dos Objetivos','Sem objetivo claro e delimitado, nenhuma das seções seguintes faz sentido. Comece definindo o que você quer provar, descobrir ou descrever.'],
                        ['2.º','Justificativa','Por que este estudo é relevante? Qual lacuna de conhecimento ele preenche? (pode ser integrada à Introdução depois)'],
                        ['3.º','Material e Métodos','Como você vai alcançar os objetivos? Descreva o protocolo em detalhes — delineamento, animais/amostras, análise estatística.'],
                        ['4.º','Resultados e Discussão','O que foi encontrado e o que isso significa? Apresente dados e compare com a literatura.'],
                        ['5.º','Conclusão','Responda diretamente ao objetivo definido no passo 1. Nada de dado novo aqui.'],
                        ['6.º','Revisão de Literatura','Revise e ajuste para apoiar o que já está escrito nos demais tópicos.'],
                        ['7.º','Introdução','Agora sim: apresente o contexto, o problema e o objetivo — que você já conhece bem porque escreveu todo o resto. A última frase da introdução anuncia o objetivo.'],
                        ['8.º (último)','Resumo / Abstract','Síntese do trabalho completo. Só pode ser escrito quando tudo já existe. Nunca antes.'],
                    ] as $o)
                    <div class="flex gap-3 items-start p-2.5 rounded-xl bg-amber-950/30 border border-amber-800/25">
                        <span class="text-xs font-bold text-amber-400 w-10 shrink-0 mt-0.5">{{ $o[0] }}</span>
                        <div>
                            <p class="text-xs font-bold text-amber-200">{{ $o[1] }}</p>
                            <p class="text-xs text-amber-200/60 font-light leading-relaxed mt-0.5">{{ $o[2] }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <div class="manual-card">
                <h3 class="text-lg font-bold text-white mb-4">Estrutura Lógica do Manuscrito Científico</h3>
                <p class="text-sm text-slate-300 leading-relaxed font-light mb-6">
                    A redação acadêmica de alta qualidade na Medicina Veterinária deve ser concisa, objetiva e baseada em evidências. Siga a sequência de produção abaixo:
                </p>
                <div class="space-y-4">
                    <div class="flex gap-4 items-start">
                        <span class="step-number">1</span>
                        <div>
                            <h4 class="text-base font-bold text-teal-400">Introdução e Justificativa</h4>
                            <p class="text-sm text-slate-400 leading-relaxed font-light">Situe o leitor na problemática. Apresente o estado da arte e termine definindo claramente a lacuna de conhecimento que seu estudo busca preencher. A última frase deve enunciar o objetivo do trabalho.</p>
                        </div>
                    </div>
                    <div class="flex gap-4 items-start border-t border-slate-800 pt-4">
                        <span class="step-number">2</span>
                        <div>
                            <h4 class="text-base font-bold text-teal-400">Materiais e Métodos</h4>
                            <p class="text-sm text-slate-400 leading-relaxed font-light">Descreva o protocolo com detalhes suficientes para reprodução. Inclua: aprovação CEUA/CEP, espécie com nomenclatura científica em itálico, n amostral, dosagens (mg/kg), via de administração, delineamento experimental e software de análise estatística.</p>
                        </div>
                    </div>
                    <div class="flex gap-4 items-start border-t border-slate-800 pt-4">
                        <span class="step-number">3</span>
                        <div>
                            <h4 class="text-base font-bold text-teal-400">Resultados</h4>
                            <p class="text-sm text-slate-400 leading-relaxed font-light">Apresente dados de forma neutra — tabelas e figuras de alta resolução (mín. 300 dpi). Não interprete aqui: apenas descreva o que os dados mostram. Legenda abaixo das figuras, acima das tabelas.</p>
                        </div>
                    </div>
                    <div class="flex gap-4 items-start border-t border-slate-800 pt-4">
                        <span class="step-number">4</span>
                        <div>
                            <h4 class="text-base font-bold text-teal-400">Discussão</h4>
                            <p class="text-sm text-slate-400 leading-relaxed font-light">Compare seus achados com a literatura internacional recente. Teorize sobre mecanismos biológicos sem extrapolar os limites dos dados. Reconheça limitações do estudo.</p>
                        </div>
                    </div>
                    <div class="flex gap-4 items-start border-t border-slate-800 pt-4">
                        <span class="step-number">5</span>
                        <div>
                            <h4 class="text-base font-bold text-teal-400">Conclusão</h4>
                            <p class="text-sm text-slate-400 leading-relaxed font-light">Responda diretamente ao objetivo enunciado na introdução. Evite introduzir novos dados ou referências. Aponte perspectivas futuras em 1–2 frases.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="manual-card bg-emerald-950/10 border-emerald-900/40">
                    <h4 class="font-bold text-emerald-400 mb-3">✔️ Boas Práticas de Redação</h4>
                    <ul class="list-disc pl-5 text-sm text-slate-300 space-y-2 font-light leading-relaxed">
                        <li>Escreva em voz passiva ou impessoal: "Foi observado que..." em vez de "Nós observamos...".</li>
                        <li>Nomes científicos em <em>itálico</em> com gênero capitalizado: <em>Carcharias taurus</em>.</li>
                        <li>Termos anatômicos conforme a <em>Nomina Anatomica Veterinaria</em>.</li>
                        <li>Abreviaturas: definir na primeira ocorrência — "tomografia computadorizada (TC)".</li>
                        <li>Números de 1 a 9: por extenso ("dois animais"); 10 ou mais: algarismos ("12 amostras").</li>
                        <li>Datas no padrão: "5 de junho de 2026" ou "jun. 2026" nas referências.</li>
                    </ul>
                </div>
                <div class="manual-card bg-red-950/10 border-red-900/40">
                    <h4 class="font-bold text-red-400 mb-3">❌ Erros Frequentes a Evitar</h4>
                    <ul class="list-disc pl-5 text-sm text-slate-300 space-y-2 font-light leading-relaxed">
                        <li>Adjetivos vagos: "excelente resultado", "muito bom". Use métricas (p&lt;0,05, IC 95%).</li>
                        <li>Citações antigas sem justificativa — prefira literatura dos últimos 5 anos.</li>
                        <li>Plágio ou transcrição literal sem aspas e referência (citação direta).</li>
                        <li>Sobrenome em caixa alta dentro do parêntese: (SILVA, 2023) — abolido pela NBR 10520:2023.</li>
                        <li>Usar "tutor" para o responsável pelo animal — use "responsável legal".</li>
                        <li>Misturar sistema autor-data com sistema numérico no mesmo trabalho.</li>
                    </ul>
                </div>
            </div>
        </div>


        {{-- ===================================================================
             ABA 4: TEMPLATES E DOWNLOADS
        =================================================================== --}}
        <div x-show="tab === 'modelos'" class="space-y-6" style="display: none;">
            <div class="manual-card">
                <h3 class="text-lg font-bold text-white mb-1">Templates Oficiais</h3>
                <p class="text-sm text-slate-400 font-light leading-relaxed mb-6">
                    Modelos pré-formatados conforme NBR 14724:2024, NBR 10520:2023 e NBR 6023:2025. Baixe, preencha e submeta via <strong>Minhas Submissões</strong>.
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">

                    <!-- TCC -->
                    <div class="p-5 bg-slate-900/80 border border-slate-800 rounded-xl flex flex-col justify-between gap-4">
                        <div>
                            <span class="text-3xl">📘</span>
                            <h4 class="font-bold text-white text-sm mt-2">Modelo de TCC (.docx)</h4>
                            <p class="text-xs text-slate-400 font-light mt-1 leading-relaxed">Capa, folha de rosto, folha de aprovação, resumo, abstract, sumário e seções pré-formatadas conforme NBR 14724:2024.</p>
                        </div>
                        <a href="{{ asset('templates/modelo_tcc.docx') }}" download
                           class="w-full py-2 bg-teal-600 hover:bg-teal-500 text-white font-bold text-xs text-center rounded-lg transition duration-200">
                            ⬇ Download Template TCC
                        </a>
                    </div>

                    <!-- Projeto IC -->
                    <div class="p-5 bg-slate-900/80 border border-slate-800 rounded-xl flex flex-col justify-between gap-4">
                        <div>
                            <span class="text-3xl">🔬</span>
                            <h4 class="font-bold text-white text-sm mt-2">Projeto de IC (.docx)</h4>
                            <p class="text-xs text-slate-400 font-light mt-1 leading-relaxed">Plano de trabalho completo para cadastro ou solicitação de fomento: identificação, objetivos, métodos e cronograma.</p>
                        </div>
                        <a href="{{ asset('templates/modelo_projeto_ic.docx') }}" download
                           class="w-full py-2 bg-teal-600 hover:bg-teal-500 text-white font-bold text-xs text-center rounded-lg transition duration-200">
                            ⬇ Download Template IC
                        </a>
                    </div>

                    <!-- Guia ABNT -->
                    <div class="p-5 bg-slate-900/80 border border-slate-800 rounded-xl flex flex-col justify-between gap-4">
                        <div>
                            <span class="text-3xl">📕</span>
                            <h4 class="font-bold text-white text-sm mt-2">Guia Rápido ABNT (.pdf)</h4>
                            <p class="text-xs text-slate-400 font-light mt-1 leading-relaxed">Referência rápida de citações (NBR 10520:2023) e referências (NBR 6023:2025) com exemplos práticos da área veterinária.</p>
                        </div>
                        <a href="{{ asset('templates/guia_rapido_abnt.pdf') }}" download
                           class="w-full py-2 bg-teal-600 hover:bg-teal-500 text-white font-bold text-xs text-center rounded-lg transition duration-200">
                            ⬇ Download Guia ABNT
                        </a>
                    </div>

                </div>
            </div>

            <!-- Tabela de normas -->
            <div class="manual-card">
                <h3 class="text-base font-bold text-white mb-4">Normas ABNT Vigentes — Referência Rápida</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-slate-300">
                        <thead>
                            <tr class="border-b border-slate-800">
                                <th class="text-left py-2 pr-4 text-slate-500 font-semibold">Norma</th>
                                <th class="text-left py-2 pr-4 text-slate-500 font-semibold">Assunto</th>
                                <th class="text-left py-2 pr-4 text-slate-500 font-semibold">Versão vigente</th>
                                <th class="text-left py-2 text-slate-500 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <tr>
                                <td class="py-2.5 pr-4 font-bold text-teal-400">NBR 6023</td>
                                <td class="py-2.5 pr-4">Referências bibliográficas</td>
                                <td class="py-2.5 pr-4 font-mono">2025</td>
                                <td class="py-2.5"><span class="badge-norm badge-updated">✦ Recém atualizada</span></td>
                            </tr>
                            <tr>
                                <td class="py-2.5 pr-4 font-bold text-teal-400">NBR 6024</td>
                                <td class="py-2.5 pr-4">Numeração progressiva</td>
                                <td class="py-2.5 pr-4 font-mono">2012</td>
                                <td class="py-2.5"><span class="badge-norm badge-stable">Vigente</span></td>
                            </tr>
                            <tr>
                                <td class="py-2.5 pr-4 font-bold text-teal-400">NBR 6027</td>
                                <td class="py-2.5 pr-4">Sumário</td>
                                <td class="py-2.5 pr-4 font-mono">2012</td>
                                <td class="py-2.5"><span class="badge-norm badge-stable">Vigente</span></td>
                            </tr>
                            <tr>
                                <td class="py-2.5 pr-4 font-bold text-teal-400">NBR 6028</td>
                                <td class="py-2.5 pr-4">Resumo e abstract</td>
                                <td class="py-2.5 pr-4 font-mono">2021</td>
                                <td class="py-2.5"><span class="badge-norm badge-stable">Vigente</span></td>
                            </tr>
                            <tr>
                                <td class="py-2.5 pr-4 font-bold text-teal-400">NBR 10520</td>
                                <td class="py-2.5 pr-4">Citações no texto</td>
                                <td class="py-2.5 pr-4 font-mono">2023</td>
                                <td class="py-2.5"><span class="badge-norm badge-updated">✦ Atualizada</span></td>
                            </tr>
                            <tr>
                                <td class="py-2.5 pr-4 font-bold text-teal-400">NBR 14724</td>
                                <td class="py-2.5 pr-4">Apresentação de trabalhos acadêmicos</td>
                                <td class="py-2.5 pr-4 font-mono">2024</td>
                                <td class="py-2.5"><span class="badge-norm badge-updated">✦ Atualizada</span></td>
                            </tr>
                            <tr>
                                <td class="py-2.5 pr-4 font-bold text-slate-400">NBR 6022</td>
                                <td class="py-2.5 pr-4">Artigo em periódico científico</td>
                                <td class="py-2.5 pr-4 font-mono">2018</td>
                                <td class="py-2.5"><span class="badge-norm badge-stable">Vigente</span></td>
                            </tr>
                            <tr>
                                <td class="py-2.5 pr-4 font-bold text-slate-400">NBR 10719</td>
                                <td class="py-2.5 pr-4">Relatório técnico-científico</td>
                                <td class="py-2.5 pr-4 font-mono">2015</td>
                                <td class="py-2.5"><span class="badge-norm badge-stable">Vigente</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Próximos passos -->
            <div class="info-card" style="padding:1rem 1.25rem;">
                <span class="ic-label" style="font-size:0.7rem; letter-spacing:0.1em;">Próximos Passos</span>
                <p class="text-sm text-slate-300 leading-relaxed font-light">
                    Após baixar e redigir seu documento no padrão acima, acesse <strong>Minhas Submissões</strong> para fazer o upload.
                    O sistema auditará automaticamente a formatação antes de encaminhar ao professor.
                    Certifique-se de exportar em <strong>PDF/A</strong> para garantir fidelidade de formatação.
                </p>
            </div>
        </div>

    </div>
</x-filament-panels::page>
