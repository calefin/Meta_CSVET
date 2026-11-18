<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: "Times New Roman", serif;
    font-size: 11pt;
    color: #1e293b;
    line-height: 1.5;
    background: #fff;
}
.page { padding: 2cm 2.5cm; }

/* Header */
.header {
    border-bottom: 3px solid #0d9488;
    padding-bottom: 10px;
    margin-bottom: 18px;
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
}
.header h1 { font-size: 16pt; color: #0f766e; font-weight: bold; }
.header .sub { font-size: 8pt; color: #64748b; text-align: right; line-height: 1.4; }

/* Sections */
.section { margin-bottom: 16px; }
.section-title {
    font-size: 11pt;
    font-weight: bold;
    color: #fff;
    background: #0d9488;
    padding: 4px 10px;
    margin-bottom: 8px;
    border-radius: 3px;
}
.section-title span { font-size: 8pt; font-weight: normal; color: #ccfbf1; margin-left: 6px; }

/* Two-column grid */
.grid2 { display: table; width: 100%; margin-bottom: 10px; }
.col { display: table-cell; vertical-align: top; padding-right: 12px; width: 50%; }
.col:last-child { padding-right: 0; padding-left: 12px; border-left: 1px solid #e2e8f0; }

/* Cards */
.card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 5px;
    padding: 8px 10px;
    margin-bottom: 8px;
    font-size: 9.5pt;
}
.card strong { color: #0f766e; }
.ok { color: #059669; font-weight: bold; }
.no { color: #dc2626; font-weight: bold; }

/* Table */
table { width: 100%; border-collapse: collapse; font-size: 9pt; margin-bottom: 10px; }
th { background: #0d9488; color: #fff; padding: 5px 8px; text-align: left; font-weight: bold; }
td { padding: 4px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
tr:nth-child(even) td { background: #f8fafc; }

/* Hierarchy */
.h1l { font-weight: bold; text-transform: uppercase; font-size: 10pt; }
.h2l { text-transform: uppercase; font-size: 10pt; }
.h3l { font-weight: bold; font-size: 10pt; }
.h4l { font-style: italic; font-size: 10pt; }
.h5l { font-size: 10pt; }
.hier-row { padding: 3px 0; border-bottom: 1px dotted #e2e8f0; }

/* Example box */
.example {
    background: #f1f5f9;
    border-left: 4px solid #0d9488;
    padding: 7px 10px;
    font-size: 9pt;
    margin: 6px 0;
    font-family: monospace;
    line-height: 1.6;
}
.example .ok-ex { color: #059669; }
.example .no-ex { color: #dc2626; }

/* Alert */
.alert {
    background: #fef3c7;
    border: 1px solid #f59e0b;
    border-radius: 4px;
    padding: 6px 10px;
    font-size: 9pt;
    margin-bottom: 8px;
    color: #92400e;
}

/* Footer */
.footer {
    margin-top: 14px;
    padding-top: 8px;
    border-top: 1px solid #e2e8f0;
    font-size: 8pt;
    color: #94a3b8;
    text-align: center;
}

.badge {
    display: inline-block;
    background: #ccfbf1;
    color: #0f766e;
    border: 1px solid #0d9488;
    border-radius: 10px;
    padding: 1px 7px;
    font-size: 8pt;
    font-weight: bold;
}
.badge-new { background: #dcfce7; color: #15803d; border-color: #16a34a; }
.pagebreak { page-break-before: always; }
</style>
</head>
<body>
<div class="page">

<!-- HEADER -->
<div class="header">
    <div>
        <h1>Guia Rápido de Normas ABNT</h1>
        <div style="font-size:9pt; color:#475569; margin-top:3px;">Para TCC, Artigos Científicos e Projetos de IC · Atualizado 2026</div>
    </div>
    <div class="sub">
        Prof. Dr. Atilio Sersun Calefi<br>
        profatilio.com.br<br>
        {{ date('m/Y') }}
    </div>
</div>

<!-- NORMAS VIGENTES -->
<div class="section">
    <div class="section-title">Normas ABNT Vigentes <span>(use sempre as versões abaixo)</span></div>
    <table>
        <tr><th>Norma</th><th>Assunto</th><th>Versão</th><th>Status</th></tr>
        <tr><td><strong>NBR 6023</strong></td><td>Referências bibliográficas</td><td>2025</td><td><span class="badge badge-new">✦ Atualizada</span></td></tr>
        <tr><td><strong>NBR 10520</strong></td><td>Citações no texto</td><td>2023</td><td><span class="badge badge-new">✦ Atualizada</span></td></tr>
        <tr><td><strong>NBR 14724</strong></td><td>Apresentação de trabalhos</td><td>2024</td><td><span class="badge badge-new">✦ Atualizada</span></td></tr>
        <tr><td><strong>NBR 6028</strong></td><td>Resumo e abstract</td><td>2021</td><td><span class="badge">Vigente</span></td></tr>
        <tr><td><strong>NBR 6027</strong></td><td>Sumário</td><td>2012</td><td><span class="badge">Vigente</span></td></tr>
        <tr><td><strong>NBR 6024</strong></td><td>Numeração progressiva</td><td>2012</td><td><span class="badge">Vigente</span></td></tr>
    </table>
</div>

<div class="grid2">
<div class="col">

<!-- FORMATAÇÃO GERAL NBR 14724:2024 -->
<div class="section">
    <div class="section-title">Formatação Geral <span>NBR 14724:2024</span></div>
    <table>
        <tr><th>Parâmetro</th><th>Valor</th></tr>
        <tr><td>Papel</td><td>A4 (21 × 29,7 cm)</td></tr>
        <tr><td>Margem superior</td><td><strong>3 cm</strong></td></tr>
        <tr><td>Margem esquerda</td><td><strong>3 cm</strong></td></tr>
        <tr><td>Margem inferior</td><td><strong>2 cm</strong></td></tr>
        <tr><td>Margem direita</td><td><strong>2 cm</strong></td></tr>
        <tr><td>Fonte corpo</td><td>Arial ou Times New Roman 12 pt</td></tr>
        <tr><td>Fonte citação longa / notas</td><td>10 pt</td></tr>
        <tr><td>Espaçamento</td><td>1,5 entre linhas</td></tr>
        <tr><td>Citação longa (≥ 4 linhas)</td><td>Recuo 4 cm, espaço simples</td></tr>
        <tr><td>Recuo 1.ª linha</td><td>1,25 cm</td></tr>
        <tr><td>Alinhamento</td><td>Justificado</td></tr>
        <tr><td>Paginação</td><td>Sup. direito, fonte 10. Contagem a partir da folha de rosto; número visível a partir da Introdução</td></tr>
    </table>
</div>

<!-- HIERARQUIA DE SEÇÕES NBR 6024 -->
<div class="section">
    <div class="section-title">Hierarquia de Seções <span>NBR 6024:2012</span></div>
    <div class="card">
        <div class="hier-row"><span class="h1l">1 SEÇÃO PRIMÁRIA</span> — Maiúsculas + Negrito + nova página</div>
        <div class="hier-row"><span class="h2l">1.1 SEÇÃO SECUNDÁRIA</span> — Maiúsculas, sem negrito</div>
        <div class="hier-row"><span class="h3l">1.1.1 Seção Terciária</span> — Title Case + Negrito</div>
        <div class="hier-row"><span class="h4l">1.1.1.1 Seção Quaternária</span> — Title Case + Itálico</div>
        <div class="hier-row"><span class="h5l">1.1.1.1.1 Seção Quinária</span> — Title Case, sem destaque</div>
    </div>
    <div class="alert">⚠ Sem ponto ao final do número: <strong>1.2.1</strong> correto; <strong>1.2.1.</strong> incorreto. Elementos pré/pós-textuais (Resumo, Referências) não recebem número.</div>
</div>

</div><!-- /col -->
<div class="col">

<!-- CITAÇÕES NBR 10520:2023 -->
<div class="section">
    <div class="section-title">Citações no Texto <span>NBR 10520:2023</span></div>

    <div class="alert">⚠ <strong>Mudança 2023:</strong> sobrenome dentro do parêntese agora usa Title Case — apenas a inicial maiúscula.</div>

    <div class="card">
        <strong>Formato sistema autor-data:</strong><br>
        <span class="no-ex">❌ (SILVA, 2023) — formato antigo, abolido</span><br>
        <span class="ok-ex">✔ (Silva, 2023) — formato vigente</span><br>
        <span class="ok-ex">✔ (Silva; Costa, 2023) — dois autores</span><br>
        <span class="ok-ex">✔ (Silva <em>et al.</em>, 2023) — 3 ou mais</span>
    </div>

    <div class="card">
        <strong>Citação direta curta (até 3 linhas):</strong><br>
        Inclui no parágrafo, entre aspas duplas:<br>
        <em>"texto citado" (Silva, 2023, p. 45)</em>
    </div>

    <div class="card">
        <strong>Citação direta longa (≥ 4 linhas):</strong><br>
        Recuo 4 cm · Fonte 10 · Espaço simples · Sem aspas<br>
        Referência ao final fora das aspas: <em>(Silva, 2023, p. 78)</em>
    </div>

    <div class="card">
        <strong>Expressões especiais (sempre em itálico):</strong><br>
        <em>et al.</em> — 3 ou mais autores<br>
        <em>apud</em> — citação de citação<br>
        <em>ibidem (ibid.)</em> — mesma obra, mesma página<br>
        E-book/digital: indicar localizador — <em>(Silva, 2023, loc. 194)</em>
    </div>
</div>

<!-- RESUMO NBR 6028:2021 -->
<div class="section">
    <div class="section-title">Resumo e Abstract <span>NBR 6028:2021</span></div>
    <table>
        <tr><th>Tipo de trabalho</th><th>Extensão</th></tr>
        <tr><td>TCC / Monografia</td><td>150 – 500 palavras</td></tr>
        <tr><td>Dissertação / Tese</td><td>150 – 500 palavras</td></tr>
        <tr><td>Artigo Científico</td><td>100 – 250 palavras</td></tr>
    </table>
    <div class="card" style="font-size:9pt;">
        Parágrafo único · sem citações · sem subdivisões · 3.ª pessoa · espaçamento simples<br>
        <strong>Palavras-chave:</strong> 3 a 6, separadas por ponto-e-vírgula, logo abaixo do resumo
    </div>
</div>

</div><!-- /col -->
</div><!-- /grid2 -->

<!-- REFERÊNCIAS PAGE BREAK -->
<div class="pagebreak"></div>

<!-- REFERÊNCIAS NBR 6023:2025 -->
<div class="section">
    <div class="section-title">Referências <span>NBR 6023:2025 — exemplos práticos</span></div>
    <div class="grid2">
    <div class="col">

    <div class="card">
        <strong>Livro:</strong><br>
        <div class="example">ETTINGER, S. J.; FELDMAN, E. C. <em>Tratado de medicina interna veterinária.</em> 8. ed. Rio de Janeiro: Guanabara Koogan, 2017.</div>
    </div>
    <div class="card">
        <strong>Capítulo de livro:</strong><br>
        <div class="example">SILVA, J. A. Título do capítulo. In: SOBRENOME, N. (org.). <em>Título do Livro.</em> Cidade: Editora, Ano. cap. X, p. XX-XX.</div>
    </div>
    <div class="card">
        <strong>Artigo em periódico:</strong><br>
        <div class="example">COSTA, M. B.; LIMA, A. P. Título do artigo. <em>Ciência Rural,</em> Santa Maria, v. 54, n. 3, p. 1-10, 2024. DOI: 10.1590/xxx.</div>
    </div>
    <div class="card">
        <strong>Dissertação / Tese:</strong><br>
        <div class="example">SOUZA, C. F. <em>Título da dissertação.</em> 2024. 120 f. Dissertação (Mestrado em Medicina Veterinária) – FMVZ-USP, São Paulo, 2024.</div>
    </div>

    </div>
    <div class="col">

    <div class="card">
        <strong>Site / Página web <span class="badge badge-new">novo 2025</span>:</strong><br>
        <div class="example">CFMV. <em>Resolução n. 1498/2023.</em> Brasília, 2023. Disponível em: https://cfmv.gov.br/resolucao-1498. Acesso em: 5 jun. 2026.</div>
    </div>
    <div class="card">
        <strong>Vídeo (YouTube) <span class="badge badge-new">novo 2025</span>:</strong><br>
        <div class="example">CANAL. Título do vídeo. [Vídeo]. YouTube, Ano. X min. Disponível em: https://youtu.be/xxx. Acesso em: 5 jun. 2026.</div>
    </div>
    <div class="card">
        <strong>Legislação:</strong><br>
        <div class="example">BRASIL. Lei n. 9.605, de 12 de fevereiro de 1998. Dispõe sobre as sanções penais e administrativas derivadas de condutas [...]. <em>Diário Oficial da União,</em> Brasília, 13 fev. 1998.</div>
    </div>
    <div class="card">
        <strong>Regras gerais das referências:</strong><br>
        Alinhamento à esquerda · espaçamento simples dentro · linha em branco entre · ordem alfabética · sobrenome do 1.º autor em caixa alta
    </div>

    </div>
    </div>
</div>

<!-- TABELAS E FIGURAS -->
<div class="section">
    <div class="section-title">Figuras, Tabelas e Quadros</div>
    <div class="grid2">
    <div class="col">
    <table>
        <tr><th>Elemento</th><th>Legenda</th><th>Bordas</th><th>Conteúdo</th></tr>
        <tr><td><strong>Figura</strong></td><td>Abaixo ↓</td><td>Sem borda obrigatória</td><td>Gráficos, fotos, esquemas</td></tr>
        <tr><td><strong>Tabela</strong></td><td>Acima ↑</td><td>Abertas nas laterais</td><td>Dados numéricos / estatísticos</td></tr>
        <tr><td><strong>Quadro</strong></td><td>Acima ↑</td><td>Fechadas (grade completa)</td><td>Dados textuais / descritivos</td></tr>
    </table>
    </div>
    <div class="col">
    <div class="card">
        <strong>Formato da legenda (fonte 10, espaço simples):</strong><br>
        Figura 1 — Descrição completa da figura.<br>
        Fonte: Sobrenome (Ano). / Arquivo pessoal.<br><br>
        <strong>Citação no texto (obrigatória):</strong><br>
        Como demonstrado na Figura 1... <em>ou</em> ... (Figura 1).
    </div>
    </div>
    </div>
</div>

<!-- FOOTER -->
<div class="footer">
    Guia Rápido de Normas ABNT · profatilio.com.br · Prof. Dr. Atilio Sersun Calefi · {{ date('Y') }}<br>
    Baseado em: NBR 6023:2025 · NBR 10520:2023 · NBR 14724:2024 · NBR 6028:2021 · NBR 6027:2012 · NBR 6024:2012
</div>

</div><!-- /page -->
</body>
</html>
