% csvet-livro.cls — padrão gráfico dos livros da Editora CSVET
% Compilar com LuaLaTeX (fontes do sistema: Source Serif 4 e Roboto):
%   latexmk livro.tex        (usa o latexmkrc da pasta: lualatex + índice remissivo)
% Opções da classe:
%   16x23 (padrão) ou 21x28     formato do miolo
%   coletanea                   capítulos com autores próprios; "Organização" na folha de rosto
%   comercial                   obra com todos os direitos reservados (sem licença aberta)
\NeedsTeXFormat{LaTeX2e}
\ProvidesClass{csvet-livro}[2026/10/08 Editora CSVET]

\newif\if@coletanea \@coletaneafalse
\newif\if@comercial \@comercialfalse
\newcommand\csvet@papel{16x23}
\DeclareOption{16x23}{\renewcommand\csvet@papel{16x23}}
\DeclareOption{21x28}{\renewcommand\csvet@papel{21x28}}
\DeclareOption{coletanea}{\@coletaneatrue}
\DeclareOption{comercial}{\@comercialtrue}
\ProcessOptions\relax
\LoadClass[10pt,twoside,openright]{book}

% ---------------------------------------------------------------- formato e mancha
\RequirePackage{geometry}
\def\csvet@a{16x23}
\ifx\csvet@papel\csvet@a
  \geometry{paperwidth=160mm,paperheight=230mm,inner=22mm,outer=16mm,top=20mm,bottom=22mm,headsep=6mm,footskip=10mm}
\else
  \geometry{paperwidth=210mm,paperheight=280mm,inner=25mm,outer=18mm,top=22mm,bottom=24mm,headsep=7mm,footskip=11mm}
\fi

% ---------------------------------------------------------------- idioma e fontes
\RequirePackage[brazilian]{babel}
\RequirePackage{fontspec}
\setmainfont{Source Serif 4}[BoldFont={Source Serif 4 Semibold}]
\setsansfont{Roboto}[BoldFont={Roboto Medium}]
\RequirePackage{microtype}
\RequirePackage{xcolor}
\definecolor{csvetnavy}{HTML}{000080}
\definecolor{csvetaccent}{HTML}{0088CC}
\definecolor{csvetgray}{HTML}{555566}
\linespread{1.18}                       % 10,5/14,5 aprox.
\renewcommand\normalsize{\fontsize{10.5}{14.5}\selectfont}
\normalsize
\setlength{\parindent}{5mm}
\setlength{\parskip}{0pt}
\clubpenalty=10000 \widowpenalty=10000

% ---------------------------------------------------------------- pacotes
\RequirePackage{graphicx}
\graphicspath{{figuras/}}
\RequirePackage{caption}
\captionsetup{font={small,sf},labelsep=endash,justification=raggedright,singlelinecheck=false}
\captionsetup[figure]{name=Figura}
\captionsetup[table]{name=Tabela,position=top}
\RequirePackage{booktabs}
\RequirePackage{tabularx}
\RequirePackage{enumitem}
\RequirePackage[explicit]{titlesec}
\RequirePackage{titletoc}
\RequirePackage{fancyhdr}
\RequirePackage[nottoc]{tocbibind}
\RequirePackage{imakeidx}
\makeindex[title=Índice remissivo,columns=2,intoc]
\RequirePackage[hidelinks,pdfusetitle,bookmarksnumbered]{hyperref}
\RequirePackage{emptypage}               % páginas em branco sem cabeçalho

% ---------------------------------------------------------------- metadados do livro
\newcommand\csvet@titulo{TÍTULO DO LIVRO}       \renewcommand\title[1]{\renewcommand\csvet@titulo{#1}}
\newcommand\csvet@subtitulo{}                   \newcommand\subtitulo[1]{\renewcommand\csvet@subtitulo{#1}}
\newcommand\csvet@autores{Nome do Autor}        \newcommand\autores[1]{\renewcommand\csvet@autores{#1}}
\newcommand\csvet@orientacao{}                  \newcommand\orientacao[1]{\renewcommand\csvet@orientacao{#1}}
\newcommand\csvet@edicao{1ª edição}             \newcommand\edicao[1]{\renewcommand\csvet@edicao{#1}}
\newcommand\csvet@ano{\the\year}                \newcommand\ano[1]{\renewcommand\csvet@ano{#1}}
\newcommand\csvet@isbn{ISBN 978-65-00-00000-0 (PDF)}   \newcommand\isbn[1]{\renewcommand\csvet@isbn{#1}}
\newcommand\csvet@doi{}                         \newcommand\doi[1]{\renewcommand\csvet@doi{#1}}
\newcommand\csvet@origem{}                      \newcommand\notadeorigem[1]{\renewcommand\csvet@origem{#1}}
\newcommand\csvet@notaeditorial{}               \newcommand\notaeditorial[1]{\renewcommand\csvet@notaeditorial{#1}}
\newcommand\csvet@ficha{\textit{[Colar aqui a ficha catalográfica emitida pela CBL, sem alterar.]}}
\newcommand\fichacatalografica[1]{\renewcommand\csvet@ficha{#1}}

% Expediente: \papel{Função}{Nome – titulação, instituição \\ ...}
\newenvironment{expediente}{%
  \noindent{\sffamily\bfseries\color{csvetnavy}EDITORA CSVET}\par\medskip
  \footnotesize\sffamily
  \tabularx{\textwidth}{@{}>{\bfseries\raggedright\arraybackslash}p{28mm}X@{}}}
  {\endtabularx\par}
\newcommand\papel[2]{#1 & #2 \\[4pt]}

% ---------------------------------------------------------------- páginas iniciais
\newcommand\paginasiniciais[1]{%  #1 = conteúdo do expediente
  \frontmatter
  \pagestyle{empty}
  % falsa folha de rosto
  \null\vfill
  \begin{center}{\sffamily\bfseries\Large\color{csvetnavy}\csvet@titulo}\end{center}
  \vfill\vfill\clearpage
  % verso: expediente
  #1
  \clearpage
  % folha de rosto
  \begin{center}
    \vspace*{6mm}
    {\large\csvet@autores\par}
    \if@coletanea{\small\itshape\color{csvetgray}(Organização)\par}\fi
    \vfill
    {\sffamily\bfseries\Huge\color{csvetnavy}\csvet@titulo\par}
    \ifx\csvet@subtitulo\@empty\else\vspace{4mm}{\large\itshape\csvet@subtitulo\par}\fi
    \ifx\csvet@orientacao\@empty\else\vspace{6mm}{\small\itshape\color{csvetgray}Orientação: \csvet@orientacao\par}\fi
    \vspace{6mm}{\small\csvet@edicao\par}
    \vfill
    \includegraphics[width=42mm]{logo-csvet}\par\smallskip
    {\small Americana – SP\enspace·\enspace\csvet@ano\par}
  \end{center}
  \clearpage
  % verso da folha de rosto: direitos, nota de origem, ficha CBL, ISBN, avisos legais
  {\footnotesize\raggedright
  \copyright\ \csvet@ano\ os autores. Publicado pela Editora CSVET (CSVET Editora, Cursos e Treinamentos, CNPJ 32.645.724/0001-89).\par\smallskip
  \if@comercial
    Todos os direitos reservados. Nenhuma parte desta obra pode ser reproduzida, distribuída, vendida, transmitida ou usada para treinamento
    de sistemas de inteligência artificial sem autorização prévia e por escrito da editora, exceto citações com indicação da fonte, nos termos
    da Lei nº 9.610/1998.\par
  \else
    Esta obra está licenciada sob a licença Creative Commons Atribuição 4.0 Internacional (CC BY 4.0). O reuso é permitido desde que
    os autores, a obra e a Editora CSVET sejam citados e as alterações indicadas; a venda desta edição por terceiros como se fosse
    própria, a omissão da autoria e o plágio violam a licença e a Lei nº 9.610/1998.\par
  \fi\smallskip
  Avaliação por pares duplo-anônima. Obra aprovada pelo Conselho Editorial após parecer de especialistas externos.\par
  \ifx\csvet@origem\@empty\else\smallskip\textit{\csvet@origem}\par\fi
  \ifx\csvet@notaeditorial\@empty\else\smallskip\textit{\csvet@notaeditorial}\par\fi
  \vfill
  \noindent\fbox{\parbox{\dimexpr\textwidth-2\fboxsep-2\fboxrule}{\centering\textbf{Dados Internacionais de Catalogação na Publicação (CIP)}\par\medskip
    \raggedright\csvet@ficha\par}}\par\medskip
  \textbf{\csvet@isbn}\par
  \ifx\csvet@doi\@empty\else DOI \csvet@doi\par\fi
  Editora CSVET · Americana, SP · contato@csvet.com.br · https://books.csvet.com.br\par}
  \clearpage
  \pagestyle{csvet}
  \tableofcontents
}

% ---------------------------------------------------------------- títulos
\titleformat{\chapter}[display]
  {\sffamily\color{csvetnavy}}
  {\vspace*{18mm}{\footnotesize\bfseries\color{csvetaccent}CAPÍTULO \thechapter}}
  {2mm}
  {\Huge\bfseries\raggedright #1}
  [\vspace{2mm}\csvet@capautores]
\titleformat{name=\chapter,numberless}[display]
  {\sffamily\color{csvetnavy}}{}{0pt}{\vspace*{18mm}\Huge\bfseries\raggedright #1}
\titlespacing*{\chapter}{0pt}{0pt}{14mm}
\titleformat{\section}{\sffamily\bfseries\large\color{csvetnavy}}{\thesection}{0.6em}{#1}
\titleformat{\subsection}{\sffamily\bfseries\normalsize\color{csvetnavy}}{\thesubsection}{0.6em}{#1}
\newcommand\csvet@capautores{}
% \capitulo[Autores do capítulo]{Título}  (o argumento opcional só aparece nas coletâneas)
\newcommand\capitulo[2][]{%
  \renewcommand\csvet@capautores{\ifx\relax#1\relax\else{\normalsize\itshape\color{csvetgray}\rmfamily #1\par}\fi}%
  \chapter{#2}}
\newenvironment{resumo}{\small\noindent\textbf{Resumo:}\ }{\par\medskip}
\newcommand\palavraschave[1]{{\small\noindent\textbf{Palavras-chave:} #1\par\bigskip}}
\newenvironment{citacao}{\begin{list}{}{\leftmargin=12mm\rightmargin=0mm}\small\item\relax}{\end{list}}
\newenvironment{referencias}{\section*{Referências}\small\begin{list}{}{\leftmargin=5mm\itemindent=-5mm\itemsep=3pt}\raggedright}{\end{list}}
\newcommand\fonte[1]{\par\vspace{1mm}{\raggedright\noindent\footnotesize\sffamily\color{csvetgray}Fonte: #1\par}}

% sumário
\titlecontents{chapter}[0pt]{\addvspace{6pt}\sffamily\bfseries}{\contentslabel[\thecontentslabel]{6mm}}{}{\titlerule*[0.6pc]{.}\contentspage}
\titlecontents{section}[6mm]{\small}{\contentslabel[\thecontentslabel]{8mm}}{}{\titlerule*[0.6pc]{.}\contentspage}
\setcounter{tocdepth}{1}

% ---------------------------------------------------------------- cabeçalhos
\fancypagestyle{csvet}{%
  \fancyhf{}
  \fancyhead[LE]{\footnotesize\sffamily\thepage\quad\csvet@titulo}
  \fancyhead[RO]{\footnotesize\sffamily\nouppercase{\leftmark}\quad\thepage}
  \renewcommand\headrulewidth{0pt}}
\fancypagestyle{plain}{\fancyhf{}\fancyfoot[C]{\footnotesize\sffamily\thepage}\renewcommand\headrulewidth{0pt}}
\renewcommand\chaptermark[1]{\markboth{#1}{}}
\pagestyle{csvet}

% ---------------------------------------------------------------- sobre os autores (com foto)
% \sobreautor{arquivo-da-foto}{Nome}{Minicurrículo}{ORCID e Lattes}
\newcommand\sobreautor[4]{%
  \par\noindent
  \begin{minipage}[t]{30mm}\vspace{0pt}\includegraphics[width=28mm]{#1}\end{minipage}\hfill
  \begin{minipage}[t]{\dimexpr\textwidth-33mm}\vspace{0pt}
    {\sffamily\bfseries\color{csvetnavy}#2\par}\smallskip
    {\small\raggedright #3\par}\smallskip
    {\raggedright\footnotesize\sffamily\color{csvetgray}#4\par}
  \end{minipage}\par\bigskip\bigskip}

% ---------------------------------------------------------------- colofão
\newcommand\colofao{%
  \clearpage\thispagestyle{empty}\null\vfill
  {\footnotesize\itshape\noindent Este livro foi composto em Source Serif 4 e Roboto e publicado pela Editora CSVET em \csvet@ano.\par}}

\endinput
