<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseModule;
use Illuminate\Database\Seeder;

/**
 * Popula os dois cursos iniciais da trilha de treinamento interno
 * (Programação Básica em R e em Python para Ciências de Dados),
 * cada um com 22 etapas (conteúdo + quiz), exercícios práticos
 * autoverificáveis e exercícios de código nos quizzes.
 *
 * Idempotente: pode ser rodado de novo com segurança (atualiza o
 * curso pelo nome e substitui as etapas).
 */
class CourseCurriculumSeeder extends Seeder
{
    public function run(): void
    {
$rCourseData = array (
  'name' => 'Programação Básica em R para Ciências de Dados',
  'workload' => 40,
  'syllabus' => '<p>Curso completo de R para Ciências de Dados: instalação e ambiente, tipos e estruturas de dados, estruturas de controle e funções, Data Frames e Tidyverse, estatística descritiva, distribuições e probabilidade, testes de hipótese, correlação e regressão linear, limpeza de dados, normalização e padronização, manipulação avançada com dplyr, reshape de dados e visualização avançada com ggplot2.</p>',
);

$rModules = array (
  0 => 
  array (
    'title' => 'Módulo 1 — Introdução, História e Instalação',
    'type' => 'instrucao',
    'content' => '<h2>O que é R e por que usá-lo em Ciência de Dados</h2>
<p>R é uma linguagem de programação e ambiente de computação estatística. Foi criada em <strong>1993</strong> por <strong>Ross Ihaka</strong> e <strong>Robert Gentleman</strong>, na Universidade de Auckland (Nova Zelândia), inspirada na linguagem S, desenvolvida nos Laboratórios Bell (Bell Labs) por John Chambers na década de 1970.</p>
<p>Hoje o R é mantido pela <em>R Foundation</em> e é uma das linguagens mais usadas no mundo para estatística, bioinformática, epidemiologia e ciência de dados em geral — especialmente na área acadêmica e de pesquisa, o que o torna extremamente relevante para trabalhos científicos em Medicina Veterinária.</p>

<h3>Por que aprender R?</h3>
<ul>
	<li>Feito por estatísticos, para análise estatística — os testes e modelos mais usados em pesquisa já vêm prontos.</li>
	<li>Gratuito e de código aberto.</li>
	<li>Enorme quantidade de pacotes prontos (mais de 20 mil) para praticamente qualquer análise.</li>
	<li>Gráficos de altíssima qualidade, prontos para publicação científica.</li>
</ul>

<h3>Instalando o R</h3>
<p>O R em si é apenas o "motor" da linguagem — ele roda comandos, mas tem uma interface bem simples. Por isso, quase ninguém usa o R "puro": usa-se o R junto com o <strong>RStudio</strong>, um ambiente de desenvolvimento (IDE) gratuito que deixa tudo mais organizado.</p>
<ol>
	<li>Baixe e instale o R em <strong>cran.r-project.org</strong> (escolha seu sistema operacional: Windows, Mac ou Linux).</li>
	<li>Depois, baixe e instale o <strong>RStudio Desktop</strong> (versão gratuita) em <strong>posit.co/download/rstudio-desktop</strong>. É obrigatório instalar o R primeiro — o RStudio não funciona sozinho, ele "conversa" com o R por trás dos panos.</li>
</ol>

<h3>Conhecendo a interface do RStudio</h3>
<p>Ao abrir o RStudio pela primeira vez, você verá 4 painéis principais:</p>
<ul>
	<li><strong>Console</strong> (geralmente embaixo à esquerda): onde você digita comandos e vê os resultados na hora.</li>
	<li><strong>Script/Editor</strong> (em cima à esquerda): onde você escreve e salva seu código em arquivos <code>.R</code>, para rodar aos poucos ou tudo de uma vez.</li>
	<li><strong>Environment/História</strong> (em cima à direita): mostra as variáveis e dados que você já criou na sessão atual.</li>
	<li><strong>Files/Plots/Packages/Help</strong> (embaixo à direita): navegação de arquivos, visualização de gráficos, gerenciamento de pacotes e documentação de ajuda.</li>
</ul>

<h3>Seu primeiro comando</h3>
<p>No console, digite e pressione Enter:</p>
<pre><code>print("Olá, Ciência de Dados!")
2 + 2</code></pre>
<p>Pronto — você já executou seus dois primeiros comandos em R.</p>',
    'video_url' => NULL,
    'practice_exercises' => 
    array (
      0 => 
      array (
        'prompt' => 'Rode no console: 10 * 5. Digite o resultado.',
        'code_starter' => '10 * 5',
        'expected_output' => '50',
        'solution_explanation' => 'O R funciona como uma calculadora no console: basta digitar a expressão e pressionar Enter. 10 * 5 = 50.',
      ),
      1 => 
      array (
        'prompt' => 'Rode: print("Ciência de Dados"). O que aparece impresso (sem as aspas)?',
        'code_starter' => 'print("Ciência de Dados")',
        'expected_output' => 'Ciência de Dados',
        'solution_explanation' => 'A função print() exibe o conteúdo do texto entre aspas, sem as aspas, diretamente no console.',
      ),
    ),
    'quiz_questions' => NULL,
    'passing_score' => 70,
    'order' => 1,
  ),
  1 => 
  array (
    'title' => 'Quiz 1 — História e Instalação',
    'type' => 'avaliacao',
    'content' => NULL,
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => 
    array (
      0 => 
      array (
        'question' => 'Quem criou a linguagem R, e em que universidade?',
        'options' => 
        array (
          0 => 'Guido van Rossum, na Universidade de Amsterdã',
          1 => 'Ross Ihaka e Robert Gentleman, na Universidade de Auckland',
          2 => 'John Chambers, nos Laboratórios Bell',
          3 => 'Linus Torvalds, na Universidade de Helsinque',
        ),
        'correct_index' => 1,
      ),
      1 => 
      array (
        'question' => 'O R foi inspirado em qual linguagem anterior?',
        'options' => 
        array (
          0 => 'Python',
          1 => 'C++',
          2 => 'S (Bell Labs)',
          3 => 'Fortran',
        ),
        'correct_index' => 2,
      ),
      2 => 
      array (
        'question' => 'O que é o RStudio?',
        'options' => 
        array (
          0 => 'Um substituto do R que não precisa dele instalado',
          1 => 'Um ambiente de desenvolvimento (IDE) gratuito que roda junto com o R',
          2 => 'Um pacote estatístico pago',
          3 => 'Um site para rodar R na nuvem, exclusivamente',
        ),
        'correct_index' => 1,
      ),
      3 => 
      array (
        'question' => 'Qual painel do RStudio mostra as variáveis criadas na sessão atual?',
        'options' => 
        array (
          0 => 'Console',
          1 => 'Script/Editor',
          2 => 'Environment',
          3 => 'Plots',
        ),
        'correct_index' => 2,
      ),
    ),
    'passing_score' => 70,
    'order' => 2,
  ),
  2 => 
  array (
    'title' => 'Módulo 2 — Tipos e Estruturas Básicas de Dados',
    'type' => 'instrucao',
    'content' => '<h2>Variáveis e atribuição</h2>
<p>Em R, usamos a seta <code>&lt;-</code> para atribuir valores a uma variável (embora <code>=</code> também funcione, o operador <code>&lt;-</code> é o padrão da comunidade):</p>
<pre><code>idade &lt;- 5
nome_animal &lt;- "Rex"
esta_vacinado &lt;- TRUE</code></pre>

<h2>Tipos de dados básicos</h2>
<ul>
	<li><strong>numeric</strong>: números, com ou sem casas decimais. Ex: <code>5</code>, <code>3.14</code></li>
	<li><strong>character</strong>: texto, sempre entre aspas. Ex: <code>"Rex"</code></li>
	<li><strong>logical</strong>: valores lógicos <code>TRUE</code> ou <code>FALSE</code></li>
	<li><strong>NA</strong>: representa um valor ausente/desconhecido (muito comum em dados clínicos reais)</li>
</ul>
<p>Para descobrir o tipo de uma variável, use a função <code>class()</code>:</p>
<pre><code>class(idade)        # "numeric"
class(nome_animal)   # "character"
class(esta_vacinado) # "logical"</code></pre>

<h2>Vetores: a estrutura mais fundamental do R</h2>
<p>Um vetor é uma sequência de valores do <strong>mesmo tipo</strong>. Criamos vetores com a função <code>c()</code> (de "combine"):</p>
<pre><code>pesos &lt;- c(4.2, 3.8, 5.1, 4.9)
especies &lt;- c("Canino", "Felino", "Canino", "Ave")</code></pre>
<p>Operações em vetores são vetorizadas — aplicam-se a todos os elementos de uma vez, sem precisar de laços:</p>
<pre><code>pesos * 1000      # converte todos os pesos de kg para g
mean(pesos)       # calcula a média de todos os pesos
length(pesos)     # quantidade de elementos no vetor</code></pre>

<h2>Acessando elementos de um vetor</h2>
<p>Em R, a indexação começa em <strong>1</strong> (diferente de várias outras linguagens, que começam em 0):</p>
<pre><code>pesos[1]     # primeiro elemento: 4.2
pesos[2:3]   # do segundo ao terceiro elemento</code></pre>',
    'video_url' => NULL,
    'practice_exercises' => 
    array (
      0 => 
      array (
        'prompt' => 'Rode: pesos <- c(2, 4, 6, 8); length(pesos). Digite o resultado.',
        'code_starter' => 'pesos <- c(2, 4, 6, 8)
length(pesos)',
        'expected_output' => '4',
        'solution_explanation' => 'length() retorna a quantidade de elementos do vetor. O vetor c(2, 4, 6, 8) tem 4 elementos.',
      ),
      1 => 
      array (
        'prompt' => 'Rode: x <- c(10, 20, 30); x[3]. Digite o valor retornado.',
        'code_starter' => 'x <- c(10, 20, 30)
x[3]',
        'expected_output' => '30',
        'solution_explanation' => 'Em R a indexação começa em 1, então x[3] pega o terceiro elemento do vetor: 30.',
      ),
    ),
    'quiz_questions' => NULL,
    'passing_score' => 70,
    'order' => 3,
  ),
  3 => 
  array (
    'title' => 'Quiz 2 — Tipos e Estruturas de Dados',
    'type' => 'avaliacao',
    'content' => NULL,
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => 
    array (
      0 => 
      array (
        'question' => 'Qual operador é o padrão da comunidade R para atribuir um valor a uma variável?',
        'options' => 
        array (
          0 => '=>',
          1 => '<-',
          2 => '::',
          3 => '->>',
        ),
        'correct_index' => 1,
        'type' => 'multipla_escolha',
      ),
      1 => 
      array (
        'question' => 'Qual função é usada para criar um vetor em R?',
        'options' => 
        array (
          0 => 'vector()',
          1 => 'list()',
          2 => 'c()',
          3 => 'array()',
        ),
        'correct_index' => 2,
        'type' => 'multipla_escolha',
      ),
      2 => 
      array (
        'question' => 'O que representa o valor NA em R?',
        'options' => 
        array (
          0 => 'Zero',
          1 => 'Um texto vazio',
          2 => 'Um valor ausente/desconhecido',
          3 => 'Falso (FALSE)',
        ),
        'correct_index' => 2,
        'type' => 'multipla_escolha',
      ),
      3 => 
      array (
        'question' => 'Em R, qual é o índice do primeiro elemento de um vetor?',
        'options' => 
        array (
          0 => '0',
          1 => '1',
          2 => '-1',
          3 => 'depende do sistema operacional',
        ),
        'correct_index' => 1,
        'type' => 'multipla_escolha',
      ),
      4 => 
      array (
        'type' => 'codigo',
        'question' => 'No console, rode: pesos <- c(4, 8, 12); pesos[2]. Digite exatamente o valor retornado.',
        'code_snippet' => 'pesos <- c(4, 8, 12)
pesos[2]',
        'expected_answer' => '8',
      ),
    ),
    'passing_score' => 70,
    'order' => 4,
  ),
  4 => 
  array (
    'title' => 'Módulo 3 — Estruturas de Controle e Funções',
    'type' => 'instrucao',
    'content' => '<h2>Estruturas condicionais: if / else</h2>
<pre><code>peso &lt;- 4.5

if (peso &lt; 3) {
  print("Abaixo do peso ideal")
} else if (peso &lt;= 6) {
  print("Peso dentro do esperado")
} else {
  print("Acima do peso ideal")
}</code></pre>

<h2>Laços de repetição: for e while</h2>
<p>O laço <code>for</code> percorre cada elemento de um vetor:</p>
<pre><code>pesos &lt;- c(4.2, 3.8, 5.1, 4.9)

for (p in pesos) {
  print(p * 1000)
}</code></pre>
<p>O laço <code>while</code> repete enquanto uma condição for verdadeira:</p>
<pre><code>contador &lt;- 1
while (contador &lt;= 3) {
  print(contador)
  contador &lt;- contador + 1
}</code></pre>

<h2>Criando suas próprias funções</h2>
<p>Funções evitam repetição de código. Em R, criamos funções com <code>function()</code>:</p>
<pre><code>converter_kg_para_g &lt;- function(kg) {
  return(kg * 1000)
}

converter_kg_para_g(4.5)   # 4500</code></pre>
<p>Funções podem ter mais de um parâmetro e valores padrão:</p>
<pre><code>classificar_peso &lt;- function(peso, limite_inferior = 3, limite_superior = 6) {
  if (peso &lt; limite_inferior) {
    return("Abaixo do ideal")
  } else if (peso &lt;= limite_superior) {
    return("Ideal")
  } else {
    return("Acima do ideal")
  }
}

classificar_peso(2.5)</code></pre>
<h3>Dica prática</h3>
<p>Em ciência de dados, você raramente vai precisar de laços <code>for</code> explícitos para operar sobre colunas de dados — as operações vetorizadas do R (que vimos no Módulo 2) e as funções da família <code>apply()</code> costumam ser mais rápidas e mais idiomáticas. Mas entender <code>for</code>/<code>while</code> é essencial antes de dar esse passo.</p>',
    'video_url' => NULL,
    'practice_exercises' => 
    array (
      0 => 
      array (
        'prompt' => 'Rode: dobrar <- function(x) x * 2; dobrar(21). Digite o resultado.',
        'code_starter' => 'dobrar <- function(x) x * 2
dobrar(21)',
        'expected_output' => '42',
        'solution_explanation' => 'A função dobrar recebe x e retorna x * 2. dobrar(21) = 21 * 2 = 42.',
      ),
      1 => 
      array (
        'prompt' => 'Rode: total <- 0; for (i in 1:5) { total <- total + i }; total. Digite o valor final de total.',
        'code_starter' => 'total <- 0
for (i in 1:5) { total <- total + i }
total',
        'expected_output' => '15',
        'solution_explanation' => 'O laço soma 1+2+3+4+5, que é igual a 15.',
      ),
    ),
    'quiz_questions' => NULL,
    'passing_score' => 70,
    'order' => 5,
  ),
  5 => 
  array (
    'title' => 'Quiz 3 — Controle e Funções',
    'type' => 'avaliacao',
    'content' => NULL,
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => 
    array (
      0 => 
      array (
        'question' => 'Qual palavra-chave inicia a criação de uma função em R?',
        'options' => 
        array (
          0 => 'def',
          1 => 'func',
          2 => 'function',
          3 => 'lambda',
        ),
        'correct_index' => 2,
        'type' => 'multipla_escolha',
      ),
      1 => 
      array (
        'question' => 'Qual laço repete um bloco enquanto uma condição continuar verdadeira?',
        'options' => 
        array (
          0 => 'for',
          1 => 'while',
          2 => 'repeat_if',
          3 => 'foreach',
        ),
        'correct_index' => 1,
        'type' => 'multipla_escolha',
      ),
      2 => 
      array (
        'question' => 'No exemplo classificar_peso(2.5), o que acontece se peso for menor que limite_inferior (3, valor padrão)?',
        'options' => 
        array (
          0 => 'Retorna "Ideal"',
          1 => 'Retorna "Acima do ideal"',
          2 => 'Retorna "Abaixo do ideal"',
          3 => 'Gera um erro',
        ),
        'correct_index' => 2,
        'type' => 'multipla_escolha',
      ),
      3 => 
      array (
        'question' => 'Por que operações vetorizadas costumam ser preferidas a laços for explícitos em ciência de dados no R?',
        'options' => 
        array (
          0 => 'Porque laços for não existem em R',
          1 => 'Porque são geralmente mais rápidas e mais idiomáticas para dados tabulares',
          2 => 'Porque não é possível usar for dentro de funções',
          3 => 'Não há diferença nenhuma',
        ),
        'correct_index' => 1,
        'type' => 'multipla_escolha',
      ),
      4 => 
      array (
        'type' => 'codigo',
        'question' => 'Rode: converter_kg_para_g <- function(kg) kg * 1000; converter_kg_para_g(2). Digite o resultado.',
        'code_snippet' => 'converter_kg_para_g <- function(kg) kg * 1000
converter_kg_para_g(2)',
        'expected_answer' => '2000',
      ),
    ),
    'passing_score' => 70,
    'order' => 6,
  ),
  6 => 
  array (
    'title' => 'Módulo 4 — Data Frames, Importação de Dados e Visualização',
    'type' => 'instrucao',
    'content' => '<h2>Data Frames: a tabela de dados do R</h2>
<p>Um <strong>data frame</strong> é a estrutura mais usada em ciência de dados: uma tabela onde cada coluna é um vetor (podendo ter tipos diferentes entre colunas) e cada linha é uma observação.</p>
<pre><code>animais &lt;- data.frame(
  nome = c("Rex", "Mia", "Thor"),
  especie = c("Canino", "Felino", "Canino"),
  peso_kg = c(12.5, 4.1, 30.2)
)

str(animais)     # estrutura do data frame
summary(animais) # resumo estatístico das colunas numéricas
head(animais)    # primeiras linhas</code></pre>

<h2>Importando dados externos</h2>
<p>Dificilmente você digitará seus dados manualmente — o mais comum é importar uma planilha:</p>
<pre><code>dados &lt;- read.csv("meus_dados.csv")</code></pre>

<h2>O Tidyverse: manipulação moderna de dados</h2>
<p>O <strong>Tidyverse</strong> é um conjunto de pacotes (dplyr, ggplot2, tidyr, entre outros) que se tornou o padrão da comunidade para ciência de dados em R. Para instalar:</p>
<pre><code>install.packages("tidyverse")
library(tidyverse)</code></pre>
<p>Com o pacote <code>dplyr</code>, filtramos e resumimos dados de forma bem legível:</p>
<pre><code>animais |&gt;
  filter(especie == "Canino") |&gt;
  summarise(peso_medio = mean(peso_kg))</code></pre>

<h2>Visualização com ggplot2</h2>
<p>O <code>ggplot2</code> é considerado um dos melhores pacotes de visualização de dados que existem, muito usado em artigos científicos:</p>
<pre><code>ggplot(animais, aes(x = especie, y = peso_kg)) +
  geom_boxplot() +
  labs(title = "Peso por Espécie", x = "Espécie", y = "Peso (kg)")</code></pre>
<p>A partir daqui, você já tem a base necessária para começar a explorar seus próprios dados de pesquisa em R. Nos próximos cursos da trilha, vamos aprofundar estatística aplicada e modelos.</p>',
    'video_url' => NULL,
    'practice_exercises' => 
    array (
      0 => 
      array (
        'prompt' => 'Rode: df <- data.frame(x = c(1,2,3), y = c(4,5,6)); ncol(df). Digite o resultado.',
        'code_starter' => 'df <- data.frame(x = c(1,2,3), y = c(4,5,6))
ncol(df)',
        'expected_output' => '2',
        'solution_explanation' => 'ncol() conta o número de colunas do data frame. Há duas colunas: x e y.',
      ),
      1 => 
      array (
        'prompt' => 'Rode: pesos <- c(4, 8, 12); mean(pesos). Digite o resultado.',
        'code_starter' => 'pesos <- c(4, 8, 12)
mean(pesos)',
        'expected_output' => '8',
        'solution_explanation' => 'A média é (4+8+12)/3 = 24/3 = 8.',
      ),
    ),
    'quiz_questions' => NULL,
    'passing_score' => 70,
    'order' => 7,
  ),
  7 => 
  array (
    'title' => 'Quiz 4 — Data Frames e Tidyverse',
    'type' => 'avaliacao',
    'content' => NULL,
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => 
    array (
      0 => 
      array (
        'question' => 'O que é um data frame em R?',
        'options' => 
        array (
          0 => 'Um tipo de gráfico',
          1 => 'Uma tabela de dados, com colunas como vetores e linhas como observações',
          2 => 'Um arquivo de configuração do RStudio',
          3 => 'Uma função de importação de imagens',
        ),
        'correct_index' => 1,
        'type' => 'multipla_escolha',
      ),
      1 => 
      array (
        'question' => 'Qual função é usada para importar um arquivo CSV em R?',
        'options' => 
        array (
          0 => 'import.csv()',
          1 => 'load_csv()',
          2 => 'read.csv()',
          3 => 'open.csv()',
        ),
        'correct_index' => 2,
        'type' => 'multipla_escolha',
      ),
      2 => 
      array (
        'question' => 'O que é o Tidyverse?',
        'options' => 
        array (
          0 => 'Uma versão paga do R',
          1 => 'Um conjunto de pacotes padrão da comunidade para ciência de dados (dplyr, ggplot2, etc.)',
          2 => 'Um sistema operacional para estatística',
          3 => 'Um substituto do RStudio',
        ),
        'correct_index' => 1,
        'type' => 'multipla_escolha',
      ),
      3 => 
      array (
        'question' => 'Qual pacote do Tidyverse é referência para visualização de dados de qualidade científica?',
        'options' => 
        array (
          0 => 'dplyr',
          1 => 'tidyr',
          2 => 'ggplot2',
          3 => 'readr',
        ),
        'correct_index' => 2,
        'type' => 'multipla_escolha',
      ),
      4 => 
      array (
        'type' => 'codigo',
        'question' => 'Rode: nrow(data.frame(x = c(1,2,3,4))). Digite o resultado.',
        'code_snippet' => 'nrow(data.frame(x = c(1,2,3,4)))',
        'expected_answer' => '4',
      ),
    ),
    'passing_score' => 70,
    'order' => 8,
  ),
  8 => 
  array (
    'title' => 'Módulo 5 — Estatística Descritiva Básica',
    'type' => 'instrucao',
    'content' => '<h2>Medidas de tendência central</h2>
<p>Antes de qualquer modelo, todo cientista de dados precisa saber resumir uma variável numérica. As três medidas mais usadas são:</p>
<ul>
	<li><strong>Média</strong>: soma de todos os valores dividida pela quantidade. Sensível a valores extremos (outliers).</li>
	<li><strong>Mediana</strong>: valor central quando os dados são ordenados. Não é afetada por outliers.</li>
	<li><strong>Moda</strong>: valor que mais se repete.</li>
</ul>
<pre><code>pesos &lt;- c(4.2, 3.8, 5.1, 4.9, 30.5)  # 30.5 é um outlier

mean(pesos)     # média: puxada pelo outlier
median(pesos)   # mediana: resistente ao outlier</code></pre>

<h2>Medidas de dispersão</h2>
<ul>
	<li><strong>Variância</strong> e <strong>desvio padrão</strong>: o quanto os dados variam em torno da média.</li>
	<li><strong>Amplitude</strong>: diferença entre o maior e o menor valor.</li>
	<li><strong>Quartis</strong>: dividem os dados ordenados em 4 partes iguais (25%, 50%, 75%).</li>
</ul>
<pre><code>sd(pesos)         # desvio padrão
var(pesos)        # variância
range(pesos)       # amplitude (mínimo e máximo)
quantile(pesos)    # quartis</code></pre>

<h2>Resumo tudo de uma vez</h2>
<pre><code>summary(pesos)</code></pre>
<p>A função <code>summary()</code> retorna mínimo, primeiro quartil, mediana, média, terceiro quartil e máximo — o resumo estatístico mais usado no dia a dia.</p>

<h2>Boxplot: visualizando o resumo estatístico</h2>
<pre><code>boxplot(pesos, main = "Distribuição de Pesos", ylab = "kg")</code></pre>
<p>O boxplot mostra visualmente a mediana, os quartis e possíveis outliers (pontos fora dos "bigodes") — uma das ferramentas mais usadas para inspecionar dados antes de qualquer análise.</p>',
    'video_url' => NULL,
    'practice_exercises' => 
    array (
      0 => 
      array (
        'prompt' => 'Rode: x <- c(1, 2, 3, 4, 100); median(x). Digite o resultado.',
        'code_starter' => 'x <- c(1, 2, 3, 4, 100)
median(x)',
        'expected_output' => '3',
        'solution_explanation' => 'Ordenados, os valores são 1,2,3,4,100. O valor central (mediana) é 3 — repare como ele não foi afetado pelo outlier 100.',
      ),
      1 => 
      array (
        'prompt' => 'Rode: range(c(5, 12, 8, 20, 3)). Digite os dois valores retornados, separados por espaço (mínimo e máximo).',
        'code_starter' => 'range(c(5, 12, 8, 20, 3))',
        'expected_output' => '3 20',
        'solution_explanation' => 'range() retorna o menor e o maior valor do vetor: mínimo 3, máximo 20.',
      ),
    ),
    'quiz_questions' => NULL,
    'passing_score' => 70,
    'order' => 9,
  ),
  9 => 
  array (
    'title' => 'Quiz 5 — Estatística Descritiva',
    'type' => 'avaliacao',
    'content' => NULL,
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => 
    array (
      0 => 
      array (
        'question' => 'Qual medida de tendência central é resistente a outliers?',
        'options' => 
        array (
          0 => 'Média',
          1 => 'Mediana',
          2 => 'Desvio padrão',
          3 => 'Variância',
        ),
        'correct_index' => 1,
        'type' => 'multipla_escolha',
      ),
      1 => 
      array (
        'question' => 'O que mede o desvio padrão?',
        'options' => 
        array (
          0 => 'O valor mais frequente',
          1 => 'O quanto os dados variam em torno da média',
          2 => 'O valor central dos dados ordenados',
          3 => 'A quantidade total de observações',
        ),
        'correct_index' => 1,
        'type' => 'multipla_escolha',
      ),
      2 => 
      array (
        'question' => 'Qual função em R retorna de uma vez mínimo, quartis, mediana, média e máximo?',
        'options' => 
        array (
          0 => 'describe()',
          1 => 'summary()',
          2 => 'stats()',
          3 => 'overview()',
        ),
        'correct_index' => 1,
        'type' => 'multipla_escolha',
      ),
      3 => 
      array (
        'question' => 'O que um boxplot ajuda a identificar visualmente?',
        'options' => 
        array (
          0 => 'Apenas a média dos dados',
          1 => 'Mediana, quartis e possíveis outliers',
          2 => 'Somente o tipo de dado da variável',
          3 => 'O número de linhas do data frame',
        ),
        'correct_index' => 1,
        'type' => 'multipla_escolha',
      ),
      4 => 
      array (
        'type' => 'codigo',
        'question' => 'Rode: median(c(2, 4, 4, 4, 8)). Digite o resultado.',
        'code_snippet' => 'median(c(2, 4, 4, 4, 8))',
        'expected_answer' => '4',
      ),
    ),
    'passing_score' => 70,
    'order' => 10,
  ),
  10 => 
  array (
    'title' => 'Módulo 6 — Distribuições e Probabilidade Básica',
    'type' => 'instrucao',
    'content' => '<h2>O que é uma distribuição</h2>
<p>Uma distribuição descreve como os valores de uma variável se espalham. Entender a distribuição de uma variável é o primeiro passo antes de escolher qualquer teste estatístico.</p>

<h2>A distribuição normal</h2>
<p>A distribuição normal (curva em forma de sino) é a mais importante da estatística: muitos fenômenos biológicos (peso, altura, tempo de resposta a um tratamento) se aproximam dela.</p>
<pre><code># Gera 1000 valores aleatórios de uma distribuição normal
# com média 5 e desvio padrão 1
amostra &lt;- rnorm(1000, mean = 5, sd = 1)

hist(amostra, main = "Distribuição Normal Simulada", xlab = "Valor")</code></pre>

<h2>Visualizando a forma dos dados: histograma</h2>
<pre><code>hist(amostra, breaks = 30, col = "steelblue",
     main = "Histograma", xlab = "Valor", ylab = "Frequência")</code></pre>
<p>O histograma agrupa os dados em faixas (bins) e mostra a frequência de cada uma — é a forma mais direta de "ver" a distribuição de uma variável.</p>

<h2>Outras distribuições comuns</h2>
<ul>
	<li><strong>Binomial</strong>: número de sucessos em eventos com dois resultados possíveis (ex.: animal responde ou não a um tratamento).</li>
	<li><strong>Poisson</strong>: contagem de eventos raros em um intervalo (ex.: número de casos de uma doença por mês).</li>
	<li><strong>Uniforme</strong>: todos os valores dentro de um intervalo têm a mesma chance de ocorrer.</li>
</ul>
<pre><code>rbinom(10, size = 1, prob = 0.3)   # 10 sorteios binomiais
rpois(10, lambda = 2)               # 10 sorteios Poisson</code></pre>

<h2>Verificando normalidade</h2>
<p>Antes de escolher um teste estatístico, é comum verificar se os dados seguem (ou não) uma distribuição normal:</p>
<pre><code>shapiro.test(amostra)   # teste de normalidade de Shapiro-Wilk
qqnorm(amostra); qqline(amostra)   # gráfico Q-Q</code></pre>
<p>Se o p-valor do teste de Shapiro-Wilk for menor que 0,05, há evidência de que os dados <strong>não</strong> seguem uma distribuição normal — o que muda quais testes estatísticos são apropriados (veremos isso no próximo módulo).</p>',
    'video_url' => NULL,
    'practice_exercises' => 
    array (
      0 => 
      array (
        'prompt' => 'Rode: length(rnorm(50)). Digite o resultado (quantidade de valores gerados).',
        'code_starter' => 'length(rnorm(50))',
        'expected_output' => '50',
        'solution_explanation' => 'rnorm(50) gera 50 valores aleatórios de uma distribuição normal — length() confirma a quantidade.',
      ),
      1 => 
      array (
        'prompt' => 'Rode: length(rbinom(20, size = 1, prob = 0.5)). Digite o resultado.',
        'code_starter' => 'length(rbinom(20, size = 1, prob = 0.5))',
        'expected_output' => '20',
        'solution_explanation' => 'rbinom(20, ...) gera 20 sorteios de uma distribuição binomial.',
      ),
    ),
    'quiz_questions' => NULL,
    'passing_score' => 70,
    'order' => 11,
  ),
  11 => 
  array (
    'title' => 'Quiz 6 — Distribuições e Probabilidade',
    'type' => 'avaliacao',
    'content' => NULL,
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => 
    array (
      0 => 
      array (
        'question' => 'Qual gráfico é o mais direto para visualizar a distribuição/forma de uma variável numérica?',
        'options' => 
        array (
          0 => 'Boxplot',
          1 => 'Histograma',
          2 => 'Gráfico de barras',
          3 => 'Gráfico de pizza',
        ),
        'correct_index' => 1,
      ),
      1 => 
      array (
        'question' => 'Qual distribuição é usada para modelar o número de sucessos em eventos com dois resultados possíveis?',
        'options' => 
        array (
          0 => 'Normal',
          1 => 'Poisson',
          2 => 'Binomial',
          3 => 'Uniforme',
        ),
        'correct_index' => 2,
      ),
      2 => 
      array (
        'question' => 'O que indica um p-valor menor que 0,05 no teste de Shapiro-Wilk?',
        'options' => 
        array (
          0 => 'Os dados seguem perfeitamente uma distribuição normal',
          1 => 'Há evidência de que os dados não seguem uma distribuição normal',
          2 => 'A amostra é muito pequena para qualquer conclusão',
          3 => 'O experimento precisa ser refeito',
        ),
        'correct_index' => 1,
      ),
      3 => 
      array (
        'question' => 'Qual função do R gera valores aleatórios de uma distribuição normal?',
        'options' => 
        array (
          0 => 'rnorm()',
          1 => 'dnorm()',
          2 => 'pnorm()',
          3 => 'qnorm()',
        ),
        'correct_index' => 0,
      ),
    ),
    'passing_score' => 70,
    'order' => 12,
  ),
  12 => 
  array (
    'title' => 'Módulo 7 — Testes de Hipótese Introdutórios',
    'type' => 'instrucao',
    'content' => '<h2>O que é um teste de hipótese</h2>
<p>Um teste de hipótese verifica se uma diferença observada nos dados é estatisticamente significativa ou se pode ter ocorrido apenas por acaso. Definimos:</p>
<ul>
	<li><strong>Hipótese nula (H0)</strong>: não existe diferença/efeito (o que assumimos até prova em contrário).</li>
	<li><strong>Hipótese alternativa (H1)</strong>: existe diferença/efeito.</li>
	<li><strong>p-valor</strong>: probabilidade de observar um resultado tão extremo quanto o obtido, assumindo que H0 é verdadeira. Por convenção, p &lt; 0,05 costuma ser considerado estatisticamente significativo.</li>
</ul>

<h2>Teste t: comparando duas médias</h2>
<p>Usado para comparar a média de dois grupos (ex.: peso de animais tratados vs. grupo controle), quando os dados seguem aproximadamente uma distribuição normal:</p>
<pre><code>grupo_controle &lt;- c(4.1, 4.5, 3.9, 4.2, 4.0)
grupo_tratado  &lt;- c(5.2, 5.5, 5.0, 5.8, 5.3)

t.test(grupo_controle, grupo_tratado)</code></pre>
<p>O resultado inclui o p-valor: se for menor que 0,05, há evidência estatística de que os grupos são diferentes.</p>

<h2>Teste qui-quadrado: comparando proporções/categorias</h2>
<p>Usado quando os dados são categóricos (ex.: "respondeu ao tratamento" sim/não, comparando dois grupos):</p>
<pre><code>tabela &lt;- matrix(c(18, 2, 12, 8), nrow = 2,
                  dimnames = list(c("Tratado", "Controle"), c("Respondeu", "Não respondeu")))
chisq.test(tabela)</code></pre>

<h2>Wilcoxon/Mann-Whitney: alternativa não paramétrica</h2>
<p>Quando os dados <strong>não</strong> seguem distribuição normal (lembra do teste de Shapiro-Wilk do módulo anterior?), usamos um teste não paramétrico em vez do teste t:</p>
<pre><code>wilcox.test(grupo_controle, grupo_tratado)</code></pre>

<h3>Como escolher o teste certo</h3>
<p>Essa escolha (t de Student vs. Wilcoxon, por exemplo) depende diretamente de você ter verificado a normalidade dos dados no módulo anterior — é por isso que estatística descritiva, distribuições e testes de hipótese andam sempre juntos na prática.</p>',
    'video_url' => NULL,
    'practice_exercises' => 
    array (
      0 => 
      array (
        'prompt' => 'Rode: a <- c(1,2,3); b <- c(4,5,6); resultado <- t.test(a, b); resultado$p.value < 0.05. Digite TRUE ou FALSE.',
        'code_starter' => 'a <- c(1,2,3)
b <- c(4,5,6)
resultado <- t.test(a, b)
resultado$p.value < 0.05',
        'expected_output' => 'TRUE',
        'solution_explanation' => 'Os grupos a e b são bem diferentes (1,2,3 vs 4,5,6), então o teste t encontra uma diferença estatisticamente significativa: p.value < 0.05 é TRUE.',
      ),
      1 => 
      array (
        'prompt' => 'Rode: length(c(1,2,3)) == length(c(4,5,6)). Digite TRUE ou FALSE.',
        'code_starter' => 'length(c(1,2,3)) == length(c(4,5,6))',
        'expected_output' => 'TRUE',
        'solution_explanation' => 'Os dois vetores têm 3 elementos cada, então a comparação de igualdade retorna TRUE.',
      ),
    ),
    'quiz_questions' => NULL,
    'passing_score' => 70,
    'order' => 13,
  ),
  13 => 
  array (
    'title' => 'Quiz 7 — Testes de Hipótese',
    'type' => 'avaliacao',
    'content' => NULL,
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => 
    array (
      0 => 
      array (
        'question' => 'O que representa a hipótese nula (H0) em um teste de hipótese?',
        'options' => 
        array (
          0 => 'Que existe uma diferença/efeito nos dados',
          1 => 'Que não existe diferença/efeito nos dados',
          2 => 'O tamanho da amostra',
          3 => 'O valor médio esperado',
        ),
        'correct_index' => 1,
      ),
      1 => 
      array (
        'question' => 'Por convenção, um resultado costuma ser considerado estatisticamente significativo quando o p-valor é:',
        'options' => 
        array (
          0 => 'Maior que 0,50',
          1 => 'Igual a 1',
          2 => 'Menor que 0,05',
          3 => 'Maior que 0,05',
        ),
        'correct_index' => 2,
      ),
      2 => 
      array (
        'question' => 'Qual teste é apropriado para comparar a média de dois grupos com dados aproximadamente normais?',
        'options' => 
        array (
          0 => 'Teste qui-quadrado',
          1 => 'Teste t',
          2 => 'Correlação de Pearson',
          3 => 'ANOVA de três fatores',
        ),
        'correct_index' => 1,
      ),
      3 => 
      array (
        'question' => 'Quando os dados não seguem distribuição normal, qual alternativa não paramétrica ao teste t podemos usar?',
        'options' => 
        array (
          0 => 'Teste qui-quadrado',
          1 => 'Regressão linear',
          2 => 'Teste de Wilcoxon/Mann-Whitney',
          3 => 'ANOVA',
        ),
        'correct_index' => 2,
      ),
    ),
    'passing_score' => 70,
    'order' => 14,
  ),
  14 => 
  array (
    'title' => 'Módulo 8 — Correlação e Regressão Linear Simples',
    'type' => 'instrucao',
    'content' => '<h2>Correlação: medindo a relação entre duas variáveis</h2>
<p>O coeficiente de correlação de Pearson (r) varia de -1 a +1 e mede a força e direção da relação linear entre duas variáveis numéricas:</p>
<ul>
	<li><strong>r próximo de +1</strong>: relação positiva forte (quando uma sobe, a outra sobe)</li>
	<li><strong>r próximo de -1</strong>: relação negativa forte (quando uma sobe, a outra desce)</li>
	<li><strong>r próximo de 0</strong>: pouca ou nenhuma relação linear</li>
</ul>
<pre><code>idade &lt;- c(1, 2, 3, 4, 5, 6, 7, 8)
peso  &lt;- c(2.1, 3.5, 4.8, 5.9, 7.2, 8.0, 8.9, 9.5)

cor(idade, peso)              # coeficiente de correlação
cor.test(idade, peso)         # com teste de significância (p-valor)</code></pre>

<h2>Visualizando a relação: gráfico de dispersão</h2>
<pre><code>plot(idade, peso, main = "Peso x Idade", xlab = "Idade (anos)", ylab = "Peso (kg)")</code></pre>

<h2>Regressão linear simples</h2>
<p>Enquanto a correlação mede a força da relação, a <strong>regressão linear</strong> ajusta uma reta que permite <em>prever</em> uma variável a partir da outra:</p>
<pre><code>modelo &lt;- lm(peso ~ idade)
summary(modelo)</code></pre>
<p>O resumo do modelo mostra o <strong>intercepto</strong>, o <strong>coeficiente angular</strong> (quanto o peso aumenta a cada ano de idade) e o <strong>R²</strong> (quanto da variação do peso é explicada pela idade).</p>

<h2>Plotando a reta de regressão</h2>
<pre><code>plot(idade, peso)
abline(modelo, col = "red", lwd = 2)</code></pre>

<h3>Importante</h3>
<p>Correlação não implica causalidade — duas variáveis podem estar correlacionadas sem que uma cause a outra. A regressão linear também assume que a relação entre as variáveis é aproximadamente linear; sempre visualize os dados antes de confiar no modelo.</p>',
    'video_url' => NULL,
    'practice_exercises' => 
    array (
      0 => 
      array (
        'prompt' => 'Rode: round(cor(c(1,2,3), c(3,2,1)), 0). Digite o resultado (correlação perfeitamente negativa).',
        'code_starter' => 'round(cor(c(1,2,3), c(3,2,1)), 0)',
        'expected_output' => '-1',
        'solution_explanation' => 'Quando uma variável sobe exatamente na mesma proporção em que a outra desce, a correlação é -1 (negativa perfeita).',
      ),
      1 => 
      array (
        'prompt' => 'Rode: modelo <- lm(c(2,4,6) ~ c(1,2,3)); round(coef(modelo)[2], 0). Digite o coeficiente angular.',
        'code_starter' => 'modelo <- lm(c(2,4,6) ~ c(1,2,3))
round(coef(modelo)[2], 0)',
        'expected_output' => '2',
        'solution_explanation' => 'Como y = 2x exatamente (2,4,6 é o dobro de 1,2,3), o coeficiente angular da reta ajustada é 2.',
      ),
    ),
    'quiz_questions' => NULL,
    'passing_score' => 70,
    'order' => 15,
  ),
  15 => 
  array (
    'title' => 'Quiz 8 — Correlação e Regressão',
    'type' => 'avaliacao',
    'content' => NULL,
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => 
    array (
      0 => 
      array (
        'question' => 'O coeficiente de correlação de Pearson varia em qual intervalo?',
        'options' => 
        array (
          0 => '0 a 100',
          1 => '-1 a +1',
          2 => '0 a 1',
          3 => '-100 a +100',
        ),
        'correct_index' => 1,
        'type' => 'multipla_escolha',
      ),
      1 => 
      array (
        'question' => 'Um valor de correlação próximo de -1 indica:',
        'options' => 
        array (
          0 => 'Nenhuma relação entre as variáveis',
          1 => 'Relação positiva forte',
          2 => 'Relação negativa forte',
          3 => 'Erro no cálculo',
        ),
        'correct_index' => 2,
        'type' => 'multipla_escolha',
      ),
      2 => 
      array (
        'question' => 'Qual função do R ajusta um modelo de regressão linear?',
        'options' => 
        array (
          0 => 'cor()',
          1 => 'lm()',
          2 => 'plot()',
          3 => 'summary()',
        ),
        'correct_index' => 1,
        'type' => 'multipla_escolha',
      ),
      3 => 
      array (
        'question' => 'O que significa a afirmação "correlação não implica causalidade"?',
        'options' => 
        array (
          0 => 'Duas variáveis correlacionadas sempre têm uma relação de causa e efeito',
          1 => 'Duas variáveis podem estar correlacionadas sem que uma cause a outra',
          2 => 'Correlação e causalidade são a mesma coisa em estatística',
          3 => 'É impossível calcular correlação sem um experimento controlado',
        ),
        'correct_index' => 1,
        'type' => 'multipla_escolha',
      ),
      4 => 
      array (
        'type' => 'codigo',
        'question' => 'Rode: round(cor(c(1,2,3,4), c(2,4,6,8)), 2). Digite o resultado.',
        'code_snippet' => 'round(cor(c(1,2,3,4), c(2,4,6,8)), 2)',
        'expected_answer' => '1',
      ),
    ),
    'passing_score' => 70,
    'order' => 16,
  ),
  16 => 
  array (
    'title' => 'Módulo 9 — Limpeza de Dados: Ausentes, Duplicatas e Outliers',
    'type' => 'instrucao',
    'content' => '<h2>Por que a limpeza de dados é a etapa mais importante</h2>
<p>Na prática, a maior parte do tempo de um projeto de ciência de dados é gasta limpando e organizando dados, não construindo modelos. Dados sujos geram análises erradas, mesmo com o método estatístico correto.</p>

<h2>Identificando valores ausentes (NA)</h2>
<pre><code>dados &lt;- data.frame(
  animal = c("Rex", "Mia", "Thor", "Luna"),
  peso   = c(12.5, NA, 30.2, 8.1),
  idade  = c(3, 5, NA, 2)
)

is.na(dados)               # matriz TRUE/FALSE indicando onde há NA
sum(is.na(dados$peso))     # quantos NAs existem na coluna peso
colSums(is.na(dados))      # NAs por coluna</code></pre>

<h2>Tratando valores ausentes</h2>
<p>Existem várias estratégias, e a escolha depende do contexto científico — nunca é uma decisão puramente automática:</p>
<pre><code># Remover linhas com qualquer NA
dados_completos &lt;- na.omit(dados)

# Substituir NA pela média da coluna (imputação simples)
dados$peso[is.na(dados$peso)] &lt;- mean(dados$peso, na.rm = TRUE)</code></pre>

<h2>Identificando e removendo duplicatas</h2>
<pre><code>duplicated(dados)                 # quais linhas são duplicatas de uma anterior
dados_sem_duplicatas &lt;- dados[!duplicated(dados), ]</code></pre>

<h2>Detectando outliers com o método do IQR</h2>
<p>Um outlier costuma ser definido como um valor abaixo de Q1 - 1,5×IQR ou acima de Q3 + 1,5×IQR (o mesmo critério usado pelo boxplot):</p>
<pre><code>Q1 &lt;- quantile(dados$peso, 0.25, na.rm = TRUE)
Q3 &lt;- quantile(dados$peso, 0.75, na.rm = TRUE)
IQR_valor &lt;- Q3 - Q1

limite_inferior &lt;- Q1 - 1.5 * IQR_valor
limite_superior &lt;- Q3 + 1.5 * IQR_valor

outliers &lt;- dados$peso[dados$peso &lt; limite_inferior | dados$peso &gt; limite_superior]</code></pre>
<p>Outliers nem sempre devem ser removidos — às vezes representam o caso clínico mais interessante da pesquisa. A decisão de remover ou investigar é sempre científica, não puramente estatística.</p>',
    'video_url' => NULL,
    'practice_exercises' => 
    array (
      0 => 
      array (
        'prompt' => 'Rode: x <- c(1, NA, 3, NA, 5); sum(is.na(x)). Digite o resultado.',
        'code_starter' => 'x <- c(1, NA, 3, NA, 5)
sum(is.na(x))',
        'expected_output' => '2',
        'solution_explanation' => 'is.na(x) retorna TRUE nas posições com NA; sum() conta quantos TRUE existem: 2.',
      ),
      1 => 
      array (
        'prompt' => 'Rode: x <- c(1, NA, 3); length(na.omit(x)). Digite o resultado.',
        'code_starter' => 'x <- c(1, NA, 3)
length(na.omit(x))',
        'expected_output' => '2',
        'solution_explanation' => 'na.omit() remove os valores NA. Sobram 1 e 3, ou seja, 2 valores.',
      ),
    ),
    'quiz_questions' => NULL,
    'passing_score' => 70,
    'order' => 17,
  ),
  17 => 
  array (
    'title' => 'Quiz 9 — Limpeza de Dados',
    'type' => 'avaliacao',
    'content' => NULL,
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => 
    array (
      0 => 
      array (
        'question' => 'Qual função do R identifica valores ausentes (NA) em um vetor ou data frame?',
        'options' => 
        array (
          0 => 'is.null()',
          1 => 'is.na()',
          2 => 'is.missing()',
          3 => 'is.empty()',
        ),
        'correct_index' => 1,
      ),
      1 => 
      array (
        'question' => 'O que faz a função na.omit()?',
        'options' => 
        array (
          0 => 'Substitui NAs pela média',
          1 => 'Remove linhas que contenham qualquer valor NA',
          2 => 'Conta quantos NAs existem',
          3 => 'Converte NA em zero automaticamente',
        ),
        'correct_index' => 1,
      ),
      2 => 
      array (
        'question' => 'Pelo método do IQR, um valor é considerado outlier quando está:',
        'options' => 
        array (
          0 => 'Exatamente na mediana',
          1 => 'Dentro do intervalo entre Q1 e Q3',
          2 => 'Abaixo de Q1 - 1,5×IQR ou acima de Q3 + 1,5×IQR',
          3 => 'Igual à média da variável',
        ),
        'correct_index' => 2,
      ),
      3 => 
      array (
        'question' => 'Outliers devem sempre ser removidos automaticamente dos dados?',
        'options' => 
        array (
          0 => 'Sim, sempre, sem exceção',
          1 => 'Não — a decisão deve considerar o contexto científico, pois podem ser casos relevantes',
          2 => 'Apenas se o pesquisador não tiver tempo para investigá-los',
          3 => 'Só quando o data frame tem mais de mil linhas',
        ),
        'correct_index' => 1,
      ),
    ),
    'passing_score' => 70,
    'order' => 18,
  ),
  18 => 
  array (
    'title' => 'Módulo 10 — Normalização e Padronização de Dados',
    'type' => 'instrucao',
    'content' => '<h2>Por que normalizar ou padronizar dados</h2>
<p>Muitas técnicas estatísticas e de aprendizado de máquina são sensíveis à <strong>escala</strong> das variáveis. Se uma variável vai de 0 a 1 e outra de 0 a 10.000, a de maior escala pode dominar o resultado, mesmo que não seja mais importante. Normalizar/padronizar coloca todas as variáveis em escalas comparáveis.</p>

<h2>Normalização Min-Max (escala 0 a 1)</h2>
<p>Transforma os dados para que o menor valor vire 0 e o maior vire 1, mantendo as proporções:</p>
<pre><code>normalizar_minmax &lt;- function(x) {
  (x - min(x, na.rm = TRUE)) / (max(x, na.rm = TRUE) - min(x, na.rm = TRUE))
}

pesos &lt;- c(4.2, 3.8, 5.1, 4.9, 12.5)
pesos_norm &lt;- normalizar_minmax(pesos)
pesos_norm</code></pre>

<h2>Padronização Z-score (média 0, desvio padrão 1)</h2>
<p>Transforma os dados para que tenham média 0 e desvio padrão 1 — muito usada quando os dados seguem (ou se aproximam de) uma distribuição normal:</p>
<pre><code>pesos_z &lt;- scale(pesos)   # função pronta do R para z-score
pesos_z

# a fórmula manual, para entender o que scale() faz:
z_manual &lt;- (pesos - mean(pesos)) / sd(pesos)</code></pre>

<h2>Min-Max vs. Z-score: quando usar cada um</h2>
<ul>
	<li><strong>Min-Max</strong>: útil quando você precisa de valores num intervalo fixo (ex.: entrada de redes neurais) e não há outliers extremos.</li>
	<li><strong>Z-score</strong>: mais robusto a outliers moderados e é o padrão em muitos testes estatísticos e algoritmos (ex.: PCA, k-means, regressão regularizada).</li>
</ul>

<h2>Aplicando a um data frame inteiro</h2>
<pre><code>dados &lt;- data.frame(peso = c(4.2, 3.8, 5.1), idade = c(1, 5, 3), fc = c(120, 80, 100))

dados_padronizados &lt;- as.data.frame(scale(dados))
dados_padronizados</code></pre>
<p>Depois de padronizar, todas as colunas ficam na mesma escala, o que é um pré-requisito comum antes de qualquer modelo estatístico multivariado — o assunto do próximo curso da trilha.</p>',
    'video_url' => NULL,
    'practice_exercises' => 
    array (
      0 => 
      array (
        'prompt' => 'Rode: x <- c(0, 10, 20); round((x - min(x)) / (max(x) - min(x)), 1). Digite o segundo valor do vetor retornado.',
        'code_starter' => 'x <- c(0, 10, 20)
round((x - min(x)) / (max(x) - min(x)), 1)',
        'expected_output' => '0.5',
        'solution_explanation' => 'Normalizando 0,10,20 para escala 0-1: (10-0)/(20-0) = 0.5 é o valor do meio.',
      ),
      1 => 
      array (
        'prompt' => 'Rode: round(mean(scale(c(2,4,6))), 0). Digite o resultado (média após padronização Z-score).',
        'code_starter' => 'round(mean(scale(c(2,4,6))), 0)',
        'expected_output' => '0',
        'solution_explanation' => 'Por definição, a padronização Z-score sempre resulta em média 0.',
      ),
    ),
    'quiz_questions' => NULL,
    'passing_score' => 70,
    'order' => 19,
  ),
  19 => 
  array (
    'title' => 'Quiz 10 — Normalização e Padronização',
    'type' => 'avaliacao',
    'content' => NULL,
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => 
    array (
      0 => 
      array (
        'question' => 'Por que normalizar/padronizar variáveis antes de uma análise multivariada?',
        'options' => 
        array (
          0 => 'Para deixar o código mais bonito',
          1 => 'Para evitar que variáveis de maior escala dominem o resultado indevidamente',
          2 => 'Porque o R exige isso para funcionar',
          3 => 'Não há motivo real, é apenas convenção',
        ),
        'correct_index' => 1,
        'type' => 'multipla_escolha',
      ),
      1 => 
      array (
        'question' => 'Na normalização Min-Max, os valores transformados ficam em qual intervalo?',
        'options' => 
        array (
          0 => '-1 a 1',
          1 => '0 a 1',
          2 => '0 a 100',
          3 => 'Depende sempre dos dados originais',
        ),
        'correct_index' => 1,
        'type' => 'multipla_escolha',
      ),
      2 => 
      array (
        'question' => 'Após a padronização Z-score, qual é a média e o desvio padrão dos dados transformados?',
        'options' => 
        array (
          0 => 'Média 1, desvio padrão 0',
          1 => 'Média 0, desvio padrão 1',
          2 => 'Média 100, desvio padrão 10',
          3 => 'Depende do tamanho da amostra',
        ),
        'correct_index' => 1,
        'type' => 'multipla_escolha',
      ),
      3 => 
      array (
        'question' => 'Qual função pronta do R aplica padronização Z-score a um vetor ou data frame?',
        'options' => 
        array (
          0 => 'normalize()',
          1 => 'standardize()',
          2 => 'scale()',
          3 => 'zscore()',
        ),
        'correct_index' => 2,
        'type' => 'multipla_escolha',
      ),
      4 => 
      array (
        'type' => 'codigo',
        'question' => 'Rode: x <- c(0, 5, 10); round((x - min(x)) / (max(x) - min(x)), 1). Digite o terceiro valor do vetor retornado.',
        'code_snippet' => 'x <- c(0, 5, 10)
round((x - min(x)) / (max(x) - min(x)), 1)',
        'expected_answer' => '1',
      ),
    ),
    'passing_score' => 70,
    'order' => 20,
  ),
  20 => 
  array (
    'title' => 'Módulo 11 — Manipulação Avançada com dplyr',
    'type' => 'instrucao',
    'content' => '<h2>Revisando o operador pipe</h2>
<p>O operador <code>|&gt;</code> (ou <code>%&gt;%</code>, do pacote magrittr) encadeia operações, tornando o código de manipulação de dados muito mais legível:</p>
<pre><code>library(dplyr)

dados &lt;- data.frame(
  especie = c("Canino", "Felino", "Canino", "Felino", "Canino"),
  peso    = c(12.5, 4.1, 30.2, 3.8, 18.0)
)</code></pre>

<h2>group_by + summarise: agregando por grupo</h2>
<pre><code>dados |&gt;
  group_by(especie) |&gt;
  summarise(
    peso_medio = mean(peso),
    n = n()
  )</code></pre>

<h2>mutate: criando novas colunas</h2>
<pre><code>dados |&gt;
  mutate(peso_g = peso * 1000,
         categoria = ifelse(peso &gt; 10, "Grande", "Pequeno"))</code></pre>

<h2>arrange: ordenando os dados</h2>
<pre><code>dados |&gt; arrange(desc(peso))   # do maior para o menor peso</code></pre>

<h2>select: escolhendo colunas</h2>
<pre><code>dados |&gt; select(especie, peso)</code></pre>

<h2>Combinando tabelas: joins</h2>
<p>Muitas vezes os dados de um estudo estão espalhados em mais de uma tabela, ligadas por uma chave (ex.: ID do animal):</p>
<pre><code>animais &lt;- data.frame(id = 1:3, nome = c("Rex", "Mia", "Thor"))
exames  &lt;- data.frame(id = c(1, 1, 2), resultado = c("Normal", "Alterado", "Normal"))

left_join(animais, exames, by = "id")</code></pre>
<p><code>left_join()</code> mantém todas as linhas da tabela da esquerda, mesmo sem correspondência na direita — existem também <code>inner_join()</code>, <code>right_join()</code> e <code>full_join()</code>, cada um com uma regra diferente de quais linhas manter.</p>',
    'video_url' => NULL,
    'practice_exercises' => 
    array (
      0 => 
      array (
        'prompt' => 'Rode: library(dplyr); c(1,2,3,4,5) |> sum(). Digite o resultado.',
        'code_starter' => 'library(dplyr)
c(1,2,3,4,5) |> sum()',
        'expected_output' => '15',
        'solution_explanation' => 'O operador pipe |> envia o vetor c(1,2,3,4,5) como primeiro argumento de sum(), que soma tudo: 15.',
      ),
      1 => 
      array (
        'prompt' => 'Rode: df <- data.frame(especie = c("Canino","Felino","Canino")); nrow(df[df$especie == "Canino", ]). Digite o resultado.',
        'code_starter' => 'df <- data.frame(especie = c("Canino","Felino","Canino"))
nrow(df[df$especie == "Canino", ])',
        'expected_output' => '2',
        'solution_explanation' => 'Há duas linhas com especie igual a "Canino" no data frame.',
      ),
    ),
    'quiz_questions' => NULL,
    'passing_score' => 70,
    'order' => 21,
  ),
  21 => 
  array (
    'title' => 'Quiz 11 — dplyr Avançado',
    'type' => 'avaliacao',
    'content' => NULL,
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => 
    array (
      0 => 
      array (
        'question' => 'Qual função do dplyr agrupa os dados antes de um resumo estatístico (ex.: média por espécie)?',
        'options' => 
        array (
          0 => 'select()',
          1 => 'group_by()',
          2 => 'arrange()',
          3 => 'filter()',
        ),
        'correct_index' => 1,
      ),
      1 => 
      array (
        'question' => 'Qual função do dplyr cria uma nova coluna a partir de colunas existentes?',
        'options' => 
        array (
          0 => 'mutate()',
          1 => 'summarise()',
          2 => 'rename()',
          3 => 'join()',
        ),
        'correct_index' => 0,
      ),
      2 => 
      array (
        'question' => 'O que faz left_join(animais, exames, by = "id")?',
        'options' => 
        array (
          0 => 'Remove todas as linhas sem correspondência nas duas tabelas',
          1 => 'Mantém todas as linhas de animais, combinando com exames quando houver correspondência pelo id',
          2 => 'Junta as tabelas por ordem, ignorando o id',
          3 => 'Cria uma nova coluna chamada "id"',
        ),
        'correct_index' => 1,
      ),
      3 => 
      array (
        'question' => 'Para que serve o operador pipe (|>) no R?',
        'options' => 
        array (
          0 => 'Para comentar uma linha de código',
          1 => 'Para encadear operações, passando o resultado de uma etapa para a próxima',
          2 => 'Para declarar uma variável do tipo texto',
          3 => 'Para importar um pacote',
        ),
        'correct_index' => 1,
      ),
    ),
    'passing_score' => 70,
    'order' => 22,
  ),
);

$pyCourseData = array (
  'name' => 'Programação Básica em Python para Ciências de Dados',
  'workload' => 40,
  'syllabus' => '<p>Curso completo de Python para Ciências de Dados: instalação e ambiente, tipos e estruturas de dados, estruturas de controle e funções, NumPy/pandas/Matplotlib, estatística descritiva, distribuições e probabilidade, testes de hipótese, correlação e regressão linear, limpeza de dados, normalização e padronização, manipulação avançada com pandas, reshape de dados e visualização avançada.</p>',
);

$pyModules = array (
  0 => 
  array (
    'title' => 'Módulo 1 — Introdução, História e Instalação',
    'type' => 'instrucao',
    'content' => '<h2>O que é Python e por que usá-lo em Ciência de Dados</h2>
<p>Python é uma linguagem de programação de propósito geral criada por <strong>Guido van Rossum</strong> e lançada em <strong>1991</strong>. O nome é uma homenagem ao grupo de comédia britânico <em>Monty Python</em>, não à cobra.</p>
<p>Desde os anos 2010, o Python se tornou a linguagem mais popular do mundo para ciência de dados e inteligência artificial, graças à sua sintaxe simples e a bibliotecas poderosas como <strong>NumPy</strong>, <strong>pandas</strong> e <strong>scikit-learn</strong>.</p>

<h3>Por que aprender Python?</h3>
<ul>
	<li>Sintaxe clara e legível — ótima linguagem para quem está começando a programar.</li>
	<li>Gratuito, de código aberto e multiplataforma.</li>
	<li>Usado tanto em ciência de dados quanto em automação, web e IA — conhecimento que se aplica em várias áreas.</li>
	<li>Comunidade enorme, com bibliotecas prontas para praticamente qualquer tarefa.</li>
</ul>

<h3>Instalando o Python</h3>
<p>Existem várias formas de instalar o Python, mas para ciência de dados a mais recomendada é a distribuição <strong>Anaconda</strong>, que já vem com Python e as principais bibliotecas científicas pré-instaladas.</p>
<ol>
	<li>Baixe o Anaconda gratuitamente em <strong>anaconda.com/download</strong> e instale (Windows, Mac ou Linux).</li>
	<li>O Anaconda já inclui o <strong>Spyder</strong>, um ambiente de desenvolvimento (IDE) gratuito e voltado para ciência de dados — pense nele como o "RStudio do Python".</li>
	<li>Para abrir o Spyder, procure por "Anaconda Navigator" no menu iniciar/aplicativos e clique em "Launch" no ícone do Spyder.</li>
</ol>

<h3>Conhecendo a interface do Spyder</h3>
<ul>
	<li><strong>Editor</strong> (esquerda): onde você escreve e salva seus scripts <code>.py</code>.</li>
	<li><strong>Console IPython</strong> (embaixo à direita): onde os comandos são executados e os resultados aparecem.</li>
	<li><strong>Variable Explorer</strong> (em cima à direita): mostra as variáveis criadas na sessão, parecido com o Environment do RStudio.</li>
	<li><strong>Plots</strong>: aba onde os gráficos gerados aparecem.</li>
</ul>

<h3>Seu primeiro comando</h3>
<p>No console do Spyder, digite e pressione Enter:</p>
<pre><code>print("Olá, Ciência de Dados!")
2 + 2</code></pre>
<p>Pronto — você já executou seus dois primeiros comandos em Python.</p>',
    'video_url' => NULL,
    'practice_exercises' => 
    array (
      0 => 
      array (
        'prompt' => 'Rode no console: 10 * 5. Digite o resultado.',
        'code_starter' => '10 * 5',
        'expected_output' => '50',
        'solution_explanation' => 'Python funciona como uma calculadora no console interativo: 10 * 5 = 50.',
      ),
      1 => 
      array (
        'prompt' => 'Rode: print("Ciência de Dados"). O que aparece impresso (sem as aspas)?',
        'code_starter' => 'print("Ciência de Dados")',
        'expected_output' => 'Ciência de Dados',
        'solution_explanation' => 'A função print() exibe o conteúdo do texto entre aspas, sem as aspas.',
      ),
    ),
    'quiz_questions' => NULL,
    'passing_score' => 70,
    'order' => 1,
  ),
  1 => 
  array (
    'title' => 'Quiz 1 — História e Instalação',
    'type' => 'avaliacao',
    'content' => NULL,
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => 
    array (
      0 => 
      array (
        'question' => 'Quem criou a linguagem Python, e em que ano foi lançada?',
        'options' => 
        array (
          0 => 'Ross Ihaka, em 1993',
          1 => 'Guido van Rossum, em 1991',
          2 => 'James Gosling, em 1995',
          3 => 'Yukihiro Matsumoto, em 1995',
        ),
        'correct_index' => 1,
        'type' => 'multipla_escolha',
      ),
      1 => 
      array (
        'question' => 'De onde vem o nome "Python"?',
        'options' => 
        array (
          0 => 'Da cobra píton',
          1 => 'De uma sigla técnica',
          2 => 'Do grupo de comédia britânico Monty Python',
          3 => 'Do sobrenome do criador',
        ),
        'correct_index' => 2,
        'type' => 'multipla_escolha',
      ),
      2 => 
      array (
        'question' => 'Qual distribuição é mais recomendada para instalar Python voltado à ciência de dados, já com as bibliotecas científicas inclusas?',
        'options' => 
        array (
          0 => 'Node.js',
          1 => 'Anaconda',
          2 => 'XAMPP',
          3 => 'Docker',
        ),
        'correct_index' => 1,
        'type' => 'multipla_escolha',
      ),
      3 => 
      array (
        'question' => 'Qual IDE gratuita, incluída no Anaconda, é equivalente ao RStudio no mundo Python?',
        'options' => 
        array (
          0 => 'Visual Studio',
          1 => 'Spyder',
          2 => 'Eclipse',
          3 => 'Notepad++',
        ),
        'correct_index' => 1,
        'type' => 'multipla_escolha',
      ),
      4 => 
      array (
        'type' => 'codigo',
        'question' => 'Rode: len("Python"). Digite o resultado.',
        'code_snippet' => 'len("Python")',
        'expected_answer' => '6',
      ),
    ),
    'passing_score' => 70,
    'order' => 2,
  ),
  2 => 
  array (
    'title' => 'Módulo 2 — Tipos e Estruturas Básicas de Dados',
    'type' => 'instrucao',
    'content' => '<h2>Variáveis e atribuição</h2>
<p>Em Python, usamos o sinal de igual <code>=</code> para atribuir valores a uma variável:</p>
<pre><code>idade = 5
nome_animal = "Rex"
esta_vacinado = True</code></pre>

<h2>Tipos de dados básicos</h2>
<ul>
	<li><strong>int</strong>: números inteiros. Ex: <code>5</code></li>
	<li><strong>float</strong>: números com casas decimais. Ex: <code>3.14</code></li>
	<li><strong>str</strong>: texto, entre aspas simples ou duplas. Ex: <code>"Rex"</code></li>
	<li><strong>bool</strong>: valores lógicos <code>True</code> ou <code>False</code> (sempre com inicial maiúscula em Python)</li>
	<li><strong>None</strong>: representa a ausência de valor</li>
</ul>
<p>Para descobrir o tipo de uma variável, use a função <code>type()</code>:</p>
<pre><code>type(idade)         # &lt;class \'int\'&gt;
type(nome_animal)    # &lt;class \'str\'&gt;
type(esta_vacinado)  # &lt;class \'bool\'&gt;</code></pre>

<h2>Listas: a estrutura mais fundamental do Python</h2>
<p>Uma lista guarda uma sequência de valores, que podem inclusive ser de tipos diferentes. Criamos listas com colchetes <code>[]</code>:</p>
<pre><code>pesos = [4.2, 3.8, 5.1, 4.9]
especies = ["Canino", "Felino", "Canino", "Ave"]</code></pre>
<p>A indexação em Python começa em <strong>0</strong> (diferente do R, que começa em 1):</p>
<pre><code>pesos[0]     # primeiro elemento: 4.2
pesos[1:3]   # do segundo ao terceiro elemento</code></pre>

<h2>Dicionários: pares de chave e valor</h2>
<p>Muito usados para representar um único registro com vários campos:</p>
<pre><code>animal = {
    "nome": "Rex",
    "especie": "Canino",
    "peso_kg": 12.5
}

animal["peso_kg"]   # 12.5</code></pre>',
    'video_url' => NULL,
    'practice_exercises' => 
    array (
      0 => 
      array (
        'prompt' => 'Rode: pesos = [2, 4, 6, 8]; len(pesos). Digite o resultado.',
        'code_starter' => 'pesos = [2, 4, 6, 8]
len(pesos)',
        'expected_output' => '4',
        'solution_explanation' => 'len() retorna a quantidade de elementos da lista. A lista [2,4,6,8] tem 4 elementos.',
      ),
      1 => 
      array (
        'prompt' => 'Rode: x = [10, 20, 30]; x[2]. Digite o valor retornado.',
        'code_starter' => 'x = [10, 20, 30]
x[2]',
        'expected_output' => '30',
        'solution_explanation' => 'Em Python a indexação começa em 0, então x[2] pega o terceiro elemento da lista: 30.',
      ),
    ),
    'quiz_questions' => NULL,
    'passing_score' => 70,
    'order' => 3,
  ),
  3 => 
  array (
    'title' => 'Quiz 2 — Tipos e Estruturas de Dados',
    'type' => 'avaliacao',
    'content' => NULL,
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => 
    array (
      0 => 
      array (
        'question' => 'Qual é o índice do primeiro elemento de uma lista em Python?',
        'options' => 
        array (
          0 => '1',
          1 => '0',
          2 => '-1',
          3 => 'depende da lista',
        ),
        'correct_index' => 1,
        'type' => 'multipla_escolha',
      ),
      1 => 
      array (
        'question' => 'Como se cria uma lista em Python?',
        'options' => 
        array (
          0 => 'Com chaves {}',
          1 => 'Com parênteses ()',
          2 => 'Com colchetes []',
          3 => 'Com a função list_create()',
        ),
        'correct_index' => 2,
        'type' => 'multipla_escolha',
      ),
      2 => 
      array (
        'question' => 'Qual estrutura representa pares de chave e valor em Python?',
        'options' => 
        array (
          0 => 'Lista',
          1 => 'Tupla',
          2 => 'Dicionário',
          3 => 'Conjunto (set)',
        ),
        'correct_index' => 2,
        'type' => 'multipla_escolha',
      ),
      3 => 
      array (
        'question' => 'Qual função retorna o tipo de uma variável em Python?',
        'options' => 
        array (
          0 => 'class()',
          1 => 'typeof()',
          2 => 'type()',
          3 => 'kind()',
        ),
        'correct_index' => 2,
        'type' => 'multipla_escolha',
      ),
      4 => 
      array (
        'type' => 'codigo',
        'question' => 'Rode: pesos = [4, 8, 12]; pesos[1]. Digite exatamente o valor retornado.',
        'code_snippet' => 'pesos = [4, 8, 12]
pesos[1]',
        'expected_answer' => '8',
      ),
    ),
    'passing_score' => 70,
    'order' => 4,
  ),
  4 => 
  array (
    'title' => 'Módulo 3 — Estruturas de Controle e Funções',
    'type' => 'instrucao',
    'content' => '<h2>Estruturas condicionais: if / elif / else</h2>
<p>Em Python, blocos de código são delimitados por <strong>indentação</strong> (espaços), não por chaves:</p>
<pre><code>peso = 4.5

if peso &lt; 3:
    print("Abaixo do peso ideal")
elif peso &lt;= 6:
    print("Peso dentro do esperado")
else:
    print("Acima do peso ideal")</code></pre>

<h2>Laços de repetição: for e while</h2>
<p>O laço <code>for</code> percorre cada elemento de uma lista:</p>
<pre><code>pesos = [4.2, 3.8, 5.1, 4.9]

for p in pesos:
    print(p * 1000)</code></pre>
<p>O laço <code>while</code> repete enquanto uma condição for verdadeira:</p>
<pre><code>contador = 1
while contador &lt;= 3:
    print(contador)
    contador = contador + 1</code></pre>

<h2>Criando suas próprias funções</h2>
<p>Funções em Python são criadas com a palavra-chave <code>def</code>:</p>
<pre><code>def converter_kg_para_g(kg):
    return kg * 1000

converter_kg_para_g(4.5)   # 4500</code></pre>
<p>Funções podem ter parâmetros com valores padrão:</p>
<pre><code>def classificar_peso(peso, limite_inferior=3, limite_superior=6):
    if peso &lt; limite_inferior:
        return "Abaixo do ideal"
    elif peso &lt;= limite_superior:
        return "Ideal"
    else:
        return "Acima do ideal"

classificar_peso(2.5)</code></pre>
<h3>Dica prática</h3>
<p>Assim como em R, em ciência de dados você vai preferir usar as operações prontas do <strong>pandas</strong> (que veremos no próximo módulo) em vez de laços <code>for</code> explícitos sempre que possível — o código fica mais rápido e mais legível.</p>',
    'video_url' => NULL,
    'practice_exercises' => 
    array (
      0 => 
      array (
        'prompt' => 'Rode: def dobrar(x): return x * 2 -- depois dobrar(21). Digite o resultado.',
        'code_starter' => 'def dobrar(x):
    return x * 2

dobrar(21)',
        'expected_output' => '42',
        'solution_explanation' => 'A função dobrar recebe x e retorna x * 2. dobrar(21) = 21 * 2 = 42.',
      ),
      1 => 
      array (
        'prompt' => 'Rode: total = 0\\nfor i in range(1, 6):\\n    total = total + i\\ntotal. Digite o valor final de total.',
        'code_starter' => 'total = 0
for i in range(1, 6):
    total = total + i
total',
        'expected_output' => '15',
        'solution_explanation' => 'range(1, 6) percorre 1,2,3,4,5. A soma é 1+2+3+4+5 = 15.',
      ),
    ),
    'quiz_questions' => NULL,
    'passing_score' => 70,
    'order' => 5,
  ),
  5 => 
  array (
    'title' => 'Quiz 3 — Controle e Funções',
    'type' => 'avaliacao',
    'content' => NULL,
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => 
    array (
      0 => 
      array (
        'question' => 'Como Python delimita blocos de código (em vez de chaves {})?',
        'options' => 
        array (
          0 => 'Por ponto e vírgula',
          1 => 'Por indentação (espaços)',
          2 => 'Por parênteses',
          3 => 'Por comentários especiais',
        ),
        'correct_index' => 1,
        'type' => 'multipla_escolha',
      ),
      1 => 
      array (
        'question' => 'Qual palavra-chave é usada para criar uma função em Python?',
        'options' => 
        array (
          0 => 'function',
          1 => 'func',
          2 => 'def',
          3 => 'lambda',
        ),
        'correct_index' => 2,
        'type' => 'multipla_escolha',
      ),
      2 => 
      array (
        'question' => 'No Python, qual é a forma correta de escrever "senão, se" em uma cadeia de condicionais?',
        'options' => 
        array (
          0 => 'else if',
          1 => 'elseif',
          2 => 'elif',
          3 => 'elsif',
        ),
        'correct_index' => 2,
        'type' => 'multipla_escolha',
      ),
      3 => 
      array (
        'question' => 'O que classificar_peso(2.5) retorna, considerando os valores padrão do exemplo (limite_inferior=3)?',
        'options' => 
        array (
          0 => '"Ideal"',
          1 => '"Acima do ideal"',
          2 => '"Abaixo do ideal"',
          3 => 'Um erro',
        ),
        'correct_index' => 2,
        'type' => 'multipla_escolha',
      ),
      4 => 
      array (
        'type' => 'codigo',
        'question' => 'Rode: def converter_kg_para_g(kg): return kg * 1000 -- depois converter_kg_para_g(2). Digite o resultado.',
        'code_snippet' => 'def converter_kg_para_g(kg):
    return kg * 1000

converter_kg_para_g(2)',
        'expected_answer' => '2000',
      ),
    ),
    'passing_score' => 70,
    'order' => 6,
  ),
  6 => 
  array (
    'title' => 'Módulo 4 — NumPy, pandas e Visualização com Matplotlib',
    'type' => 'instrucao',
    'content' => '<h2>NumPy: computação numérica eficiente</h2>
<p>O <strong>NumPy</strong> é a biblioteca base para computação numérica em Python. Ele introduz o array, muito mais rápido que listas comuns para cálculos:</p>
<pre><code>import numpy as np

pesos = np.array([4.2, 3.8, 5.1, 4.9])
pesos * 1000       # multiplica todos os elementos de uma vez
np.mean(pesos)     # calcula a média</code></pre>

<h2>pandas: a tabela de dados do Python</h2>
<p>O <strong>pandas</strong> é a biblioteca mais usada para manipulação de dados tabulares em Python — o equivalente direto ao data frame do R:</p>
<pre><code>import pandas as pd

animais = pd.DataFrame({
    "nome": ["Rex", "Mia", "Thor"],
    "especie": ["Canino", "Felino", "Canino"],
    "peso_kg": [12.5, 4.1, 30.2]
})

animais.info()      # estrutura do DataFrame
animais.describe()  # resumo estatístico das colunas numéricas
animais.head()      # primeiras linhas</code></pre>

<h2>Importando dados externos</h2>
<pre><code>dados = pd.read_csv("meus_dados.csv")</code></pre>

<h2>Filtrando e resumindo dados com pandas</h2>
<pre><code>caninos = animais[animais["especie"] == "Canino"]
caninos["peso_kg"].mean()</code></pre>

<h2>Visualização com Matplotlib</h2>
<p>O <strong>Matplotlib</strong> é a biblioteca de gráficos mais tradicional do Python, muito usada em publicações científicas:</p>
<pre><code>import matplotlib.pyplot as plt

animais.boxplot(column="peso_kg", by="especie")
plt.title("Peso por Espécie")
plt.ylabel("Peso (kg)")
plt.show()</code></pre>
<p>A partir daqui, você já tem a base necessária para começar a explorar seus próprios dados de pesquisa em Python. Nos próximos cursos da trilha, vamos aprofundar estatística aplicada e modelos.</p>',
    'video_url' => NULL,
    'practice_exercises' => 
    array (
      0 => 
      array (
        'prompt' => 'Rode: import pandas as pd; df = pd.DataFrame({"x":[1,2,3], "y":[4,5,6]}); len(df.columns). Digite o resultado.',
        'code_starter' => 'import pandas as pd
df = pd.DataFrame({\'x\':[1,2,3], \'y\':[4,5,6]})
len(df.columns)',
        'expected_output' => '2',
        'solution_explanation' => 'O DataFrame tem duas colunas: x e y.',
      ),
      1 => 
      array (
        'prompt' => 'Rode: import numpy as np; np.mean([4, 8, 12]). Digite o resultado.',
        'code_starter' => 'import numpy as np
np.mean([4, 8, 12])',
        'expected_output' => '8.0',
        'solution_explanation' => 'A média é (4+8+12)/3 = 24/3 = 8.0 (NumPy retorna float).',
      ),
    ),
    'quiz_questions' => NULL,
    'passing_score' => 70,
    'order' => 7,
  ),
  7 => 
  array (
    'title' => 'Quiz 4 — NumPy, pandas e Matplotlib',
    'type' => 'avaliacao',
    'content' => NULL,
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => 
    array (
      0 => 
      array (
        'question' => 'Qual biblioteca introduz o "array", muito mais eficiente que listas comuns para cálculo numérico?',
        'options' => 
        array (
          0 => 'pandas',
          1 => 'NumPy',
          2 => 'Matplotlib',
          3 => 'SciPy',
        ),
        'correct_index' => 1,
        'type' => 'multipla_escolha',
      ),
      1 => 
      array (
        'question' => 'Qual biblioteca é o equivalente direto ao data frame do R em Python?',
        'options' => 
        array (
          0 => 'NumPy',
          1 => 'Matplotlib',
          2 => 'pandas',
          3 => 'Seaborn',
        ),
        'correct_index' => 2,
        'type' => 'multipla_escolha',
      ),
      2 => 
      array (
        'question' => 'Qual função do pandas é usada para importar um arquivo CSV?',
        'options' => 
        array (
          0 => 'pd.read_csv()',
          1 => 'pd.load_csv()',
          2 => 'pd.import_csv()',
          3 => 'pd.open_csv()',
        ),
        'correct_index' => 0,
        'type' => 'multipla_escolha',
      ),
      3 => 
      array (
        'question' => 'Qual biblioteca é a mais tradicional para geração de gráficos científicos em Python?',
        'options' => 
        array (
          0 => 'pandas',
          1 => 'NumPy',
          2 => 'Matplotlib',
          3 => 'Requests',
        ),
        'correct_index' => 2,
        'type' => 'multipla_escolha',
      ),
      4 => 
      array (
        'type' => 'codigo',
        'question' => 'Rode: import pandas as pd; len(pd.DataFrame({"x":[1,2,3,4]})). Digite o resultado.',
        'code_snippet' => 'import pandas as pd
len(pd.DataFrame({\'x\':[1,2,3,4]}))',
        'expected_answer' => '4',
      ),
    ),
    'passing_score' => 70,
    'order' => 8,
  ),
  8 => 
  array (
    'title' => 'Módulo 5 — Estatística Descritiva Básica',
    'type' => 'instrucao',
    'content' => '<h2>Medidas de tendência central</h2>
<ul>
	<li><strong>Média</strong>: sensível a valores extremos (outliers).</li>
	<li><strong>Mediana</strong>: valor central; não é afetada por outliers.</li>
	<li><strong>Moda</strong>: valor mais frequente.</li>
</ul>
<pre><code>import numpy as np
import pandas as pd

pesos = pd.Series([4.2, 3.8, 5.1, 4.9, 30.5])  # 30.5 é um outlier

pesos.mean()     # média: puxada pelo outlier
pesos.median()   # mediana: resistente ao outlier
pesos.mode()     # moda</code></pre>

<h2>Medidas de dispersão</h2>
<pre><code>pesos.std()                 # desvio padrão
pesos.var()                 # variância
pesos.max() - pesos.min()   # amplitude
pesos.quantile([0.25, 0.5, 0.75])  # quartis</code></pre>

<h2>Resumo tudo de uma vez</h2>
<pre><code>pesos.describe()</code></pre>
<p>O método <code>describe()</code> retorna contagem, média, desvio padrão, mínimo, quartis e máximo — o resumo mais usado no dia a dia com pandas.</p>

<h2>Boxplot: visualizando o resumo estatístico</h2>
<pre><code>import matplotlib.pyplot as plt

pesos.plot(kind="box", title="Distribuição de Pesos")
plt.ylabel("kg")
plt.show()</code></pre>
<p>O boxplot mostra visualmente a mediana, os quartis e possíveis outliers — uma das ferramentas mais usadas para inspecionar dados antes de qualquer análise.</p>',
    'video_url' => NULL,
    'practice_exercises' => 
    array (
      0 => 
      array (
        'prompt' => 'Rode: import pandas as pd; pd.Series([1, 2, 3, 4, 100]).median(). Digite o resultado.',
        'code_starter' => 'import pandas as pd
pd.Series([1, 2, 3, 4, 100]).median()',
        'expected_output' => '3.0',
        'solution_explanation' => 'Ordenados: 1,2,3,4,100. O valor central (mediana) é 3.0 — não é afetado pelo outlier 100.',
      ),
      1 => 
      array (
        'prompt' => 'Rode: max([5, 12, 8, 20, 3]) - min([5, 12, 8, 20, 3]). Digite o resultado (amplitude).',
        'code_starter' => 'max([5, 12, 8, 20, 3]) - min([5, 12, 8, 20, 3])',
        'expected_output' => '17',
        'solution_explanation' => 'Máximo é 20, mínimo é 3. A amplitude é 20 - 3 = 17.',
      ),
    ),
    'quiz_questions' => NULL,
    'passing_score' => 70,
    'order' => 9,
  ),
  9 => 
  array (
    'title' => 'Quiz 5 — Estatística Descritiva',
    'type' => 'avaliacao',
    'content' => NULL,
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => 
    array (
      0 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Qual medida de tendência central é resistente a outliers?',
        'options' => 
        array (
          0 => 'Média',
          1 => 'Mediana',
          2 => 'Desvio padrão',
          3 => 'Variância',
        ),
        'correct_index' => 1,
      ),
      1 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Qual método do pandas retorna de uma vez contagem, média, desvio padrão, quartis e máximo?',
        'options' => 
        array (
          0 => 'summary()',
          1 => 'describe()',
          2 => 'info()',
          3 => 'stats()',
        ),
        'correct_index' => 1,
      ),
      2 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'O que um boxplot ajuda a identificar visualmente?',
        'options' => 
        array (
          0 => 'Apenas a média dos dados',
          1 => 'Mediana, quartis e possíveis outliers',
          2 => 'Somente o tipo de dado da variável',
          3 => 'O número de linhas do DataFrame',
        ),
        'correct_index' => 1,
      ),
      3 => 
      array (
        'type' => 'codigo',
        'question' => 'Rode: import pandas as pd; pd.Series([2, 4, 4, 4, 8]).median(). Digite o resultado.',
        'code_snippet' => 'import pandas as pd
pd.Series([2, 4, 4, 4, 8]).median()',
        'expected_answer' => '4',
      ),
    ),
    'passing_score' => 70,
    'order' => 10,
  ),
  10 => 
  array (
    'title' => 'Módulo 6 — Distribuições e Probabilidade Básica',
    'type' => 'instrucao',
    'content' => '<h2>O que é uma distribuição</h2>
<p>Uma distribuição descreve como os valores de uma variável se espalham. Entender a distribuição de uma variável é o primeiro passo antes de escolher qualquer teste estatístico.</p>

<h2>A distribuição normal</h2>
<pre><code>import numpy as np
import matplotlib.pyplot as plt

# Gera 1000 valores aleatórios de uma distribuição normal
# com média 5 e desvio padrão 1
amostra = np.random.normal(loc=5, scale=1, size=1000)

plt.hist(amostra, bins=30, color="steelblue")
plt.title("Distribuição Normal Simulada")
plt.xlabel("Valor")
plt.show()</code></pre>

<h2>Outras distribuições comuns</h2>
<ul>
	<li><strong>Binomial</strong>: número de sucessos em eventos com dois resultados possíveis.</li>
	<li><strong>Poisson</strong>: contagem de eventos raros em um intervalo.</li>
	<li><strong>Uniforme</strong>: todos os valores dentro de um intervalo têm a mesma chance.</li>
</ul>
<pre><code>np.random.binomial(n=1, p=0.3, size=10)   # 10 sorteios binomiais
np.random.poisson(lam=2, size=10)          # 10 sorteios Poisson</code></pre>

<h2>Verificando normalidade</h2>
<p>Antes de escolher um teste estatístico, é comum verificar se os dados seguem (ou não) uma distribuição normal:</p>
<pre><code>from scipy import stats

stat, p_valor = stats.shapiro(amostra)   # teste de Shapiro-Wilk
print(p_valor)</code></pre>
<p>Se o p-valor for menor que 0,05, há evidência de que os dados <strong>não</strong> seguem uma distribuição normal — o que muda quais testes estatísticos são apropriados (veremos isso no próximo módulo).</p>',
    'video_url' => NULL,
    'practice_exercises' => 
    array (
      0 => 
      array (
        'prompt' => 'Rode: import numpy as np; len(np.random.normal(size=50)). Digite o resultado.',
        'code_starter' => 'import numpy as np
len(np.random.normal(size=50))',
        'expected_output' => '50',
        'solution_explanation' => 'np.random.normal(size=50) gera 50 valores aleatórios de uma distribuição normal.',
      ),
      1 => 
      array (
        'prompt' => 'Rode: import numpy as np; len(np.random.binomial(n=1, p=0.5, size=20)). Digite o resultado.',
        'code_starter' => 'import numpy as np
len(np.random.binomial(n=1, p=0.5, size=20))',
        'expected_output' => '20',
        'solution_explanation' => 'O parâmetro size=20 define quantos sorteios binomiais são gerados.',
      ),
    ),
    'quiz_questions' => NULL,
    'passing_score' => 70,
    'order' => 11,
  ),
  11 => 
  array (
    'title' => 'Quiz 6 — Distribuições e Probabilidade',
    'type' => 'avaliacao',
    'content' => NULL,
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => 
    array (
      0 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Qual gráfico é o mais direto para visualizar a distribuição/forma de uma variável numérica?',
        'options' => 
        array (
          0 => 'Boxplot',
          1 => 'Histograma',
          2 => 'Gráfico de barras',
          3 => 'Gráfico de pizza',
        ),
        'correct_index' => 1,
      ),
      1 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Qual biblioteca Python fornece o teste de normalidade de Shapiro-Wilk?',
        'options' => 
        array (
          0 => 'NumPy',
          1 => 'pandas',
          2 => 'scipy.stats',
          3 => 'Matplotlib',
        ),
        'correct_index' => 2,
      ),
      2 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'O que indica um p-valor menor que 0,05 no teste de Shapiro-Wilk?',
        'options' => 
        array (
          0 => 'Os dados seguem perfeitamente uma distribuição normal',
          1 => 'Há evidência de que os dados não seguem uma distribuição normal',
          2 => 'A amostra é muito pequena para qualquer conclusão',
          3 => 'O experimento precisa ser refeito',
        ),
        'correct_index' => 1,
      ),
      3 => 
      array (
        'type' => 'codigo',
        'question' => 'Rode: import numpy as np; len(np.random.poisson(lam=2, size=15)). Digite o resultado.',
        'code_snippet' => 'import numpy as np
len(np.random.poisson(lam=2, size=15))',
        'expected_answer' => '15',
      ),
    ),
    'passing_score' => 70,
    'order' => 12,
  ),
  12 => 
  array (
    'title' => 'Módulo 7 — Testes de Hipótese Introdutórios',
    'type' => 'instrucao',
    'content' => '<h2>O que é um teste de hipótese</h2>
<ul>
	<li><strong>Hipótese nula (H0)</strong>: não existe diferença/efeito.</li>
	<li><strong>Hipótese alternativa (H1)</strong>: existe diferença/efeito.</li>
	<li><strong>p-valor</strong>: por convenção, p &lt; 0,05 costuma ser considerado estatisticamente significativo.</li>
</ul>

<h2>Teste t: comparando duas médias</h2>
<pre><code>from scipy import stats

grupo_controle = [4.1, 4.5, 3.9, 4.2, 4.0]
grupo_tratado  = [5.2, 5.5, 5.0, 5.8, 5.3]

stat, p_valor = stats.ttest_ind(grupo_controle, grupo_tratado)
print(p_valor)</code></pre>
<p>Se o p-valor for menor que 0,05, há evidência estatística de que os grupos são diferentes.</p>

<h2>Teste qui-quadrado: comparando proporções/categorias</h2>
<pre><code>import numpy as np

tabela = np.array([[18, 2], [12, 8]])  # linhas: Tratado/Controle, colunas: Respondeu/Não respondeu
stat, p, dof, esperado = stats.chi2_contingency(tabela)
print(p)</code></pre>

<h2>Mann-Whitney: alternativa não paramétrica</h2>
<p>Quando os dados <strong>não</strong> seguem distribuição normal, usamos um teste não paramétrico em vez do teste t:</p>
<pre><code>stat, p_valor = stats.mannwhitneyu(grupo_controle, grupo_tratado)</code></pre>

<h3>Como escolher o teste certo</h3>
<p>Essa escolha depende diretamente de você ter verificado a normalidade dos dados no módulo anterior — estatística descritiva, distribuições e testes de hipótese andam sempre juntos na prática.</p>',
    'video_url' => NULL,
    'practice_exercises' => 
    array (
      0 => 
      array (
        'prompt' => 'Rode: from scipy import stats; stat, p = stats.ttest_ind([1,2,3], [4,5,6]); p < 0.05. Digite True ou False.',
        'code_starter' => 'from scipy import stats
stat, p = stats.ttest_ind([1,2,3], [4,5,6])
p < 0.05',
        'expected_output' => 'True',
        'solution_explanation' => 'Os grupos [1,2,3] e [4,5,6] são bem diferentes, então o teste t encontra uma diferença estatisticamente significativa.',
      ),
      1 => 
      array (
        'prompt' => 'Rode: len([1,2,3]) == len([4,5,6]). Digite True ou False.',
        'code_starter' => 'len([1,2,3]) == len([4,5,6])',
        'expected_output' => 'True',
        'solution_explanation' => 'As duas listas têm 3 elementos cada, então a comparação é True.',
      ),
    ),
    'quiz_questions' => NULL,
    'passing_score' => 70,
    'order' => 13,
  ),
  13 => 
  array (
    'title' => 'Quiz 7 — Testes de Hipótese',
    'type' => 'avaliacao',
    'content' => NULL,
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => 
    array (
      0 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'O que representa a hipótese nula (H0) em um teste de hipótese?',
        'options' => 
        array (
          0 => 'Que existe uma diferença/efeito nos dados',
          1 => 'Que não existe diferença/efeito nos dados',
          2 => 'O tamanho da amostra',
          3 => 'O valor médio esperado',
        ),
        'correct_index' => 1,
      ),
      1 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Qual função do scipy.stats compara a média de dois grupos independentes (teste t)?',
        'options' => 
        array (
          0 => 'stats.chi2_contingency()',
          1 => 'stats.ttest_ind()',
          2 => 'stats.shapiro()',
          3 => 'stats.pearsonr()',
        ),
        'correct_index' => 1,
      ),
      2 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Quando os dados não seguem distribuição normal, qual alternativa não paramétrica ao teste t podemos usar?',
        'options' => 
        array (
          0 => 'Teste qui-quadrado',
          1 => 'Regressão linear',
          2 => 'Mann-Whitney',
          3 => 'ANOVA',
        ),
        'correct_index' => 2,
      ),
      3 => 
      array (
        'type' => 'codigo',
        'question' => 'Rode: sorted([5.8, 5.2, 5.5])[0]. Digite o resultado (o menor valor da lista, ordenada).',
        'code_snippet' => 'sorted([5.8, 5.2, 5.5])[0]',
        'expected_answer' => '5.2',
      ),
    ),
    'passing_score' => 70,
    'order' => 14,
  ),
  14 => 
  array (
    'title' => 'Módulo 8 — Correlação e Regressão Linear Simples',
    'type' => 'instrucao',
    'content' => '<h2>Correlação: medindo a relação entre duas variáveis</h2>
<p>O coeficiente de correlação de Pearson (r) varia de -1 a +1:</p>
<pre><code>import numpy as np
import pandas as pd

idade = pd.Series([1, 2, 3, 4, 5, 6, 7, 8])
peso  = pd.Series([2.1, 3.5, 4.8, 5.9, 7.2, 8.0, 8.9, 9.5])

idade.corr(peso)   # coeficiente de correlação de Pearson</code></pre>

<h2>Visualizando a relação: gráfico de dispersão</h2>
<pre><code>import matplotlib.pyplot as plt

plt.scatter(idade, peso)
plt.title("Peso x Idade")
plt.xlabel("Idade (anos)")
plt.ylabel("Peso (kg)")
plt.show()</code></pre>

<h2>Regressão linear simples</h2>
<p>Com <code>scikit-learn</code>, ajustamos um modelo que permite <em>prever</em> uma variável a partir da outra:</p>
<pre><code>from sklearn.linear_model import LinearRegression
import numpy as np

X = idade.values.reshape(-1, 1)   # scikit-learn espera uma matriz 2D
y = peso.values

modelo = LinearRegression()
modelo.fit(X, y)

print(modelo.coef_)        # coeficiente angular
print(modelo.intercept_)   # intercepto
print(modelo.score(X, y))  # R²</code></pre>

<h2>Plotando a reta de regressão</h2>
<pre><code>plt.scatter(idade, peso)
plt.plot(idade, modelo.predict(X), color="red")
plt.show()</code></pre>

<h3>Importante</h3>
<p>Correlação não implica causalidade. A regressão linear também assume que a relação entre as variáveis é aproximadamente linear; sempre visualize os dados antes de confiar no modelo.</p>',
    'video_url' => NULL,
    'practice_exercises' => 
    array (
      0 => 
      array (
        'prompt' => 'Rode: import pandas as pd; round(pd.Series([1,2,3]).corr(pd.Series([3,2,1])), 0). Digite o resultado (correlação perfeitamente negativa).',
        'code_starter' => 'import pandas as pd
round(pd.Series([1,2,3]).corr(pd.Series([3,2,1])), 0)',
        'expected_output' => '-1.0',
        'solution_explanation' => 'Quando uma variável sobe na mesma proporção em que a outra desce, a correlação é -1 (negativa perfeita).',
      ),
      1 => 
      array (
        'prompt' => 'Rode: from sklearn.linear_model import LinearRegression; import numpy as np; X = np.array([1,2,3]).reshape(-1,1); y = np.array([2,4,6]); m = LinearRegression().fit(X, y); round(m.coef_[0], 0). Digite o coeficiente angular.',
        'code_starter' => 'from sklearn.linear_model import LinearRegression
import numpy as np
X = np.array([1,2,3]).reshape(-1,1)
y = np.array([2,4,6])
m = LinearRegression().fit(X, y)
round(m.coef_[0], 0)',
        'expected_output' => '2.0',
        'solution_explanation' => 'Como y = 2x exatamente, o coeficiente angular ajustado pelo modelo é 2.0.',
      ),
    ),
    'quiz_questions' => NULL,
    'passing_score' => 70,
    'order' => 15,
  ),
  15 => 
  array (
    'title' => 'Quiz 8 — Correlação e Regressão',
    'type' => 'avaliacao',
    'content' => NULL,
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => 
    array (
      0 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'O coeficiente de correlação de Pearson varia em qual intervalo?',
        'options' => 
        array (
          0 => '0 a 100',
          1 => '-1 a +1',
          2 => '0 a 1',
          3 => '-100 a +100',
        ),
        'correct_index' => 1,
      ),
      1 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Qual biblioteca Python é a mais usada para ajustar um modelo de regressão linear?',
        'options' => 
        array (
          0 => 'scikit-learn',
          1 => 'Matplotlib',
          2 => 'Requests',
          3 => 'Flask',
        ),
        'correct_index' => 0,
      ),
      2 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'O que significa a afirmação "correlação não implica causalidade"?',
        'options' => 
        array (
          0 => 'Duas variáveis correlacionadas sempre têm uma relação de causa e efeito',
          1 => 'Duas variáveis podem estar correlacionadas sem que uma cause a outra',
          2 => 'Correlação e causalidade são a mesma coisa em estatística',
          3 => 'É impossível calcular correlação sem um experimento controlado',
        ),
        'correct_index' => 1,
      ),
      3 => 
      array (
        'type' => 'codigo',
        'question' => 'Rode: import pandas as pd; round(pd.Series([1,2,3,4]).corr(pd.Series([2,4,6,8])), 2). Digite o resultado.',
        'code_snippet' => 'import pandas as pd
round(pd.Series([1,2,3,4]).corr(pd.Series([2,4,6,8])), 2)',
        'expected_answer' => '1',
      ),
    ),
    'passing_score' => 70,
    'order' => 16,
  ),
  16 => 
  array (
    'title' => 'Módulo 9 — Limpeza de Dados: Ausentes, Duplicatas e Outliers',
    'type' => 'instrucao',
    'content' => '<h2>Por que a limpeza de dados é a etapa mais importante</h2>
<p>Na prática, a maior parte do tempo de um projeto de ciência de dados é gasta limpando e organizando dados, não construindo modelos. Dados sujos geram análises erradas, mesmo com o método estatístico correto.</p>

<h2>Identificando valores ausentes (NaN)</h2>
<pre><code>import pandas as pd
import numpy as np

dados = pd.DataFrame({
    "animal": ["Rex", "Mia", "Thor", "Luna"],
    "peso": [12.5, np.nan, 30.2, 8.1],
    "idade": [3, 5, np.nan, 2]
})

dados.isna()                 # matriz True/False indicando onde há valor ausente
dados["peso"].isna().sum()   # quantos ausentes existem na coluna peso
dados.isna().sum()           # ausentes por coluna</code></pre>

<h2>Tratando valores ausentes</h2>
<pre><code># Remover linhas com qualquer valor ausente
dados_completos = dados.dropna()

# Substituir valor ausente pela média da coluna (imputação simples)
dados["peso"] = dados["peso"].fillna(dados["peso"].mean())</code></pre>

<h2>Identificando e removendo duplicatas</h2>
<pre><code>dados.duplicated()                    # quais linhas são duplicatas de uma anterior
dados_sem_duplicatas = dados.drop_duplicates()</code></pre>

<h2>Detectando outliers com o método do IQR</h2>
<pre><code>Q1 = dados["peso"].quantile(0.25)
Q3 = dados["peso"].quantile(0.75)
IQR = Q3 - Q1

limite_inferior = Q1 - 1.5 * IQR
limite_superior = Q3 + 1.5 * IQR

outliers = dados[(dados["peso"] &lt; limite_inferior) | (dados["peso"] &gt; limite_superior)]</code></pre>
<p>Outliers nem sempre devem ser removidos — às vezes representam o caso clínico mais interessante da pesquisa. A decisão de remover ou investigar é sempre científica, não puramente estatística.</p>',
    'video_url' => NULL,
    'practice_exercises' => 
    array (
      0 => 
      array (
        'prompt' => 'Rode: import pandas as pd; import numpy as np; x = pd.Series([1, np.nan, 3, np.nan, 5]); x.isna().sum(). Digite o resultado.',
        'code_starter' => 'import pandas as pd
import numpy as np
x = pd.Series([1, np.nan, 3, np.nan, 5])
x.isna().sum()',
        'expected_output' => '2',
        'solution_explanation' => 'isna() marca as posições com valor ausente; sum() conta quantas existem: 2.',
      ),
      1 => 
      array (
        'prompt' => 'Rode: import pandas as pd; import numpy as np; x = pd.Series([1, np.nan, 3]); len(x.dropna()). Digite o resultado.',
        'code_starter' => 'import pandas as pd
import numpy as np
x = pd.Series([1, np.nan, 3])
len(x.dropna())',
        'expected_output' => '2',
        'solution_explanation' => 'dropna() remove os valores ausentes. Sobram 1 e 3, ou seja, 2 valores.',
      ),
    ),
    'quiz_questions' => NULL,
    'passing_score' => 70,
    'order' => 17,
  ),
  17 => 
  array (
    'title' => 'Quiz 9 — Limpeza de Dados',
    'type' => 'avaliacao',
    'content' => NULL,
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => 
    array (
      0 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Qual método do pandas identifica valores ausentes em um DataFrame?',
        'options' => 
        array (
          0 => 'is_null()',
          1 => 'isna()',
          2 => 'is_missing()',
          3 => 'is_empty()',
        ),
        'correct_index' => 1,
      ),
      1 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'O que faz o método dropna()?',
        'options' => 
        array (
          0 => 'Substitui valores ausentes pela média',
          1 => 'Remove linhas que contenham qualquer valor ausente',
          2 => 'Conta quantos valores ausentes existem',
          3 => 'Converte valores ausentes em zero automaticamente',
        ),
        'correct_index' => 1,
      ),
      2 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Pelo método do IQR, um valor é considerado outlier quando está:',
        'options' => 
        array (
          0 => 'Exatamente na mediana',
          1 => 'Dentro do intervalo entre Q1 e Q3',
          2 => 'Abaixo de Q1 - 1,5×IQR ou acima de Q3 + 1,5×IQR',
          3 => 'Igual à média da variável',
        ),
        'correct_index' => 2,
      ),
      3 => 
      array (
        'type' => 'codigo',
        'question' => 'Rode: import pandas as pd; pd.Series([1,1,2,3]).drop_duplicates().shape[0]. Digite o resultado.',
        'code_snippet' => 'import pandas as pd
pd.Series([1,1,2,3]).drop_duplicates().shape[0]',
        'expected_answer' => '3',
      ),
    ),
    'passing_score' => 70,
    'order' => 18,
  ),
  18 => 
  array (
    'title' => 'Módulo 10 — Normalização e Padronização de Dados',
    'type' => 'instrucao',
    'content' => '<h2>Por que normalizar ou padronizar dados</h2>
<p>Muitas técnicas estatísticas e de aprendizado de máquina são sensíveis à <strong>escala</strong> das variáveis. Se uma variável vai de 0 a 1 e outra de 0 a 10.000, a de maior escala pode dominar o resultado, mesmo que não seja mais importante.</p>

<h2>Normalização Min-Max (escala 0 a 1)</h2>
<pre><code>import pandas as pd

pesos = pd.Series([4.2, 3.8, 5.1, 4.9, 12.5])

pesos_norm = (pesos - pesos.min()) / (pesos.max() - pesos.min())
pesos_norm</code></pre>

<h2>Padronização Z-score (média 0, desvio padrão 1)</h2>
<pre><code>from sklearn.preprocessing import StandardScaler
import numpy as np

pesos_z = StandardScaler().fit_transform(pesos.values.reshape(-1, 1))

# a fórmula manual, para entender o que o StandardScaler faz:
z_manual = (pesos - pesos.mean()) / pesos.std()</code></pre>

<h2>Min-Max vs. Z-score: quando usar cada um</h2>
<ul>
	<li><strong>Min-Max</strong>: útil quando você precisa de valores num intervalo fixo (ex.: entrada de redes neurais) e não há outliers extremos.</li>
	<li><strong>Z-score</strong>: mais robusto a outliers moderados e é o padrão em muitos algoritmos (ex.: PCA, k-means, regressão regularizada).</li>
</ul>

<h2>Aplicando a um DataFrame inteiro</h2>
<pre><code>from sklearn.preprocessing import StandardScaler
import pandas as pd

dados = pd.DataFrame({"peso": [4.2, 3.8, 5.1], "idade": [1, 5, 3], "fc": [120, 80, 100]})

dados_padronizados = pd.DataFrame(
    StandardScaler().fit_transform(dados),
    columns=dados.columns
)</code></pre>
<p>Depois de padronizar, todas as colunas ficam na mesma escala, o que é um pré-requisito comum antes de qualquer modelo estatístico multivariado — o assunto do próximo curso da trilha.</p>',
    'video_url' => NULL,
    'practice_exercises' => 
    array (
      0 => 
      array (
        'prompt' => 'Rode: x = [0, 10, 20]; round((x[1] - min(x)) / (max(x) - min(x)), 1). Digite o resultado.',
        'code_starter' => 'x = [0, 10, 20]
round((x[1] - min(x)) / (max(x) - min(x)), 1)',
        'expected_output' => '0.5',
        'solution_explanation' => 'Normalizando 0,10,20 para escala 0-1: (10-0)/(20-0) = 0.5.',
      ),
      1 => 
      array (
        'prompt' => 'Rode: from sklearn.preprocessing import StandardScaler; import numpy as np; round(StandardScaler().fit_transform(np.array([2,4,6]).reshape(-1,1)).mean(), 0). Digite o resultado (média após padronização Z-score).',
        'code_starter' => 'from sklearn.preprocessing import StandardScaler
import numpy as np
round(StandardScaler().fit_transform(np.array([2,4,6]).reshape(-1,1)).mean(), 0)',
        'expected_output' => '0.0',
        'solution_explanation' => 'Por definição, a padronização Z-score sempre resulta em média 0.',
      ),
    ),
    'quiz_questions' => NULL,
    'passing_score' => 70,
    'order' => 19,
  ),
  19 => 
  array (
    'title' => 'Quiz 10 — Normalização e Padronização',
    'type' => 'avaliacao',
    'content' => NULL,
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => 
    array (
      0 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Por que normalizar/padronizar variáveis antes de uma análise multivariada?',
        'options' => 
        array (
          0 => 'Para deixar o código mais bonito',
          1 => 'Para evitar que variáveis de maior escala dominem o resultado indevidamente',
          2 => 'Porque o Python exige isso para funcionar',
          3 => 'Não há motivo real, é apenas convenção',
        ),
        'correct_index' => 1,
      ),
      1 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Na normalização Min-Max, os valores transformados ficam em qual intervalo?',
        'options' => 
        array (
          0 => '-1 a 1',
          1 => '0 a 1',
          2 => '0 a 100',
          3 => 'Depende sempre dos dados originais',
        ),
        'correct_index' => 1,
      ),
      2 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Qual classe do scikit-learn aplica padronização Z-score a um conjunto de dados?',
        'options' => 
        array (
          0 => 'MinMaxScaler',
          1 => 'StandardScaler',
          2 => 'Normalizer',
          3 => 'LabelEncoder',
        ),
        'correct_index' => 1,
      ),
      3 => 
      array (
        'type' => 'codigo',
        'question' => 'Rode: x = [0, 5, 10]; (x[2] - min(x)) / (max(x) - min(x)). Digite o resultado.',
        'code_snippet' => 'x = [0, 5, 10]
(x[2] - min(x)) / (max(x) - min(x))',
        'expected_answer' => '1.0',
      ),
    ),
    'passing_score' => 70,
    'order' => 20,
  ),
  20 => 
  array (
    'title' => 'Módulo 11 — Manipulação Avançada com pandas',
    'type' => 'instrucao',
    'content' => '<h2>Preparando os dados</h2>
<pre><code>import pandas as pd

dados = pd.DataFrame({
    "especie": ["Canino", "Felino", "Canino", "Felino", "Canino"],
    "peso": [12.5, 4.1, 30.2, 3.8, 18.0]
})</code></pre>

<h2>groupby: agregando por grupo</h2>
<pre><code>dados.groupby("especie")["peso"].agg(["mean", "count"])</code></pre>

<h2>Criando novas colunas</h2>
<pre><code>dados["peso_g"] = dados["peso"] * 1000
dados["categoria"] = dados["peso"].apply(lambda p: "Grande" if p &gt; 10 else "Pequeno")</code></pre>

<h2>Ordenando os dados</h2>
<pre><code>dados.sort_values("peso", ascending=False)   # do maior para o menor peso</code></pre>

<h2>Selecionando colunas</h2>
<pre><code>dados[["especie", "peso"]]</code></pre>

<h2>Combinando tabelas: merge</h2>
<p>Muitas vezes os dados de um estudo estão espalhados em mais de uma tabela, ligadas por uma chave (ex.: ID do animal):</p>
<pre><code>animais = pd.DataFrame({"id": [1, 2, 3], "nome": ["Rex", "Mia", "Thor"]})
exames  = pd.DataFrame({"id": [1, 1, 2], "resultado": ["Normal", "Alterado", "Normal"]})

pd.merge(animais, exames, on="id", how="left")</code></pre>
<p><code>how="left"</code> mantém todas as linhas da tabela da esquerda, mesmo sem correspondência na direita — existem também <code>"inner"</code>, <code>"right"</code> e <code>"outer"</code>, cada um com uma regra diferente de quais linhas manter.</p>',
    'video_url' => NULL,
    'practice_exercises' => 
    array (
      0 => 
      array (
        'prompt' => 'Rode: sum([1,2,3,4,5]). Digite o resultado.',
        'code_starter' => 'sum([1,2,3,4,5])',
        'expected_output' => '15',
        'solution_explanation' => 'A soma de 1+2+3+4+5 é 15.',
      ),
      1 => 
      array (
        'prompt' => 'Rode: import pandas as pd; df = pd.DataFrame({"especie": ["Canino","Felino","Canino"]}); len(df[df["especie"] == "Canino"]). Digite o resultado.',
        'code_starter' => 'import pandas as pd
df = pd.DataFrame({\'especie\': [\'Canino\',\'Felino\',\'Canino\']})
len(df[df[\'especie\'] == \'Canino\'])',
        'expected_output' => '2',
        'solution_explanation' => 'Há duas linhas com especie igual a "Canino" no DataFrame.',
      ),
    ),
    'quiz_questions' => NULL,
    'passing_score' => 70,
    'order' => 21,
  ),
  21 => 
  array (
    'title' => 'Quiz 11 — pandas Avançado',
    'type' => 'avaliacao',
    'content' => NULL,
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => 
    array (
      0 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Qual método do pandas agrupa os dados antes de um resumo estatístico (ex.: média por espécie)?',
        'options' => 
        array (
          0 => 'select()',
          1 => 'groupby()',
          2 => 'sort_values()',
          3 => 'filter()',
        ),
        'correct_index' => 1,
      ),
      1 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Qual valor do parâmetro "how" em pd.merge() mantém todas as linhas da tabela da esquerda?',
        'options' => 
        array (
          0 => '"inner"',
          1 => '"left"',
          2 => '"right"',
          3 => '"cross"',
        ),
        'correct_index' => 1,
      ),
      2 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Como criamos uma nova coluna em um DataFrame pandas?',
        'options' => 
        array (
          0 => 'dados.new_column()',
          1 => 'dados["nova_coluna"] = valores',
          2 => 'dados.create("nova_coluna")',
          3 => 'Não é possível adicionar colunas depois de criado o DataFrame',
        ),
        'correct_index' => 1,
      ),
      3 => 
      array (
        'type' => 'codigo',
        'question' => 'Rode: import pandas as pd; pd.DataFrame({"peso":[1,2,3]}).sort_values("peso", ascending=False)["peso"].iloc[0]. Digite o resultado.',
        'code_snippet' => 'import pandas as pd
pd.DataFrame({\'peso\':[1,2,3]}).sort_values(\'peso\', ascending=False)[\'peso\'].iloc[0]',
        'expected_answer' => '3',
      ),
    ),
    'passing_score' => 70,
    'order' => 22,
  ),
);


$dataOrgCourseData = array (
  'name' => 'Organização e Estruturação de Dados para Pesquisa e Análise Estatística',
  'workload' => 20,
  'syllabus' => '<p>Fundamentos de organização de dados para processamento computacional: o conceito de dado "arrumado" (tidy data — uma coluna, uma variável; uma linha, uma observação), erros comuns em planilhas, formato largo vs longo, nomenclatura de variáveis, tipos de dados, formatos de arquivo (CSV, TXT, XLSX), encoding e delimitadores, e reprodutibilidade/versionamento de bases de dados.</p>',
);

$dataOrgModules = array (
  0 => 
  array (
    'title' => 'Módulo 1 — Por que a Organização dos Dados Importa',
    'type' => 'instrucao',
    'content' => '<h2>O maior gargalo da ciência de dados não é a análise</h2>
<p>Pesquisadores costumam achar que o trabalho difícil é rodar o teste estatístico ou treinar o modelo. Na prática, a maior parte do tempo de qualquer projeto de dados é gasta <strong>arrumando a base</strong> — e boa parte desse trabalho poderia ser evitada se os dados já tivessem sido coletados/organizados de forma pensada para processamento computacional, não só para leitura visual humana.</p>

<h2>Planilha "bonita" vs. planilha "processável"</h2>
<p>É muito comum, especialmente na área da saúde, ver planilhas feitas para <strong>parecerem organizadas visualmente</strong> — com células mescladas, cores indicando categorias, múltiplas tabelas na mesma aba, subtotais no meio dos dados. Isso funciona bem para uma pessoa olhar rapidamente, mas é praticamente ilegível para um programa de análise estatística (R, Python, SPSS, etc.), que espera uma estrutura rígida e previsível.</p>

<h2>O que este curso vai te ensinar</h2>
<ul>
	<li>O conceito de <strong>tidy data</strong> ("dado arrumado"): a regra mais importante de todas.</li>
	<li>Os erros mais comuns que as pessoas cometem ao montar planilhas de pesquisa.</li>
	<li>Quando usar formato largo (wide) e quando usar formato longo (long).</li>
	<li>Como nomear variáveis e valores de forma consistente.</li>
	<li>Como declarar corretamente os tipos de dado.</li>
	<li>Por que arquivos de texto plano (CSV/TXT) são preferíveis a planilhas visuais para manutenção e reprodutibilidade.</li>
</ul>
<p>Este curso não depende de nenhuma linguagem de programação específica — os princípios aqui valem tanto para quem vai usar R, Python, SPSS, Stata, Excel avançado, ou qualquer outra ferramenta de análise.</p>',
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => NULL,
    'passing_score' => 70,
    'order' => 1,
  ),
  1 => 
  array (
    'title' => 'Quiz 1 — Introdução',
    'type' => 'avaliacao',
    'content' => NULL,
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => 
    array (
      0 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Segundo o módulo, qual costuma ser a etapa que mais consome tempo em um projeto de análise de dados?',
        'options' => 
        array (
          0 => 'Rodar o teste estatístico',
          1 => 'Arrumar e organizar a base de dados',
          2 => 'Escrever a discussão do artigo',
          3 => 'Escolher o software',
        ),
        'correct_index' => 1,
      ),
      1 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Por que uma planilha "bonita" (com células mescladas, cores, múltiplas tabelas) costuma ser problemática para análise estatística?',
        'options' => 
        array (
          0 => 'Porque planilhas nunca podem ser usadas para pesquisa',
          1 => 'Porque foi organizada para leitura visual humana, não para a estrutura previsível que um programa espera',
          2 => 'Porque cores sempre corrompem o arquivo',
          3 => 'Porque células mescladas são proibidas no Excel',
        ),
        'correct_index' => 1,
      ),
      2 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Os princípios de organização de dados ensinados neste curso dependem de qual linguagem de programação?',
        'options' => 
        array (
          0 => 'Só R',
          1 => 'Só Python',
          2 => 'Só SPSS',
          3 => 'Nenhuma — são independentes da ferramenta usada depois',
        ),
        'correct_index' => 3,
      ),
    ),
    'passing_score' => 70,
    'order' => 2,
  ),
  2 => 
  array (
    'title' => 'Módulo 2 — O Conceito de Tidy Data',
    'type' => 'instrucao',
    'content' => '<p><img src="/images/courses/tidy-data-comparison.svg" alt="Comparação entre planilha de peso de animais em formato largo (não arrumado) e formato longo (tidy data)" style="max-width:100%; border-radius: 8px;"></p>
<h2>A regra de ouro</h2>
<p>O conceito de <strong>tidy data</strong> ("dado arrumado"), popularizado pelo estatístico Hadley Wickham, pode ser resumido em três regras simples — e a maioria dos problemas de planilha vem de quebrar a primeira delas:</p>
<ol>
	<li><strong>Cada coluna é uma variável.</strong></li>
	<li><strong>Cada linha é uma observação.</strong></li>
	<li><strong>Cada célula contém um único valor.</strong></li>
</ol>

<h2>Exemplo de dado NÃO arrumado (comum na prática)</h2>
<p>Imagine uma planilha de acompanhamento de peso de animais, montada assim:</p>
<pre><code>Animal      | Jan   | Fev   | Mar
Rex         | 12.1  | 12.4  | 12.8
Mia         | 4.0   | 4.1   | 4.3</code></pre>
<p>Parece organizado, mas aqui <strong>"Jan", "Fev" e "Mar" são valores de uma variável (mês), não variáveis em si</strong>. Isso viola a regra 1: cada coluna deveria ser uma variável, mas aqui temos uma coluna por mês.</p>

<h2>A mesma informação, arrumada (tidy)</h2>
<pre><code>animal | mes | peso_kg
Rex    | Jan | 12.1
Rex    | Fev | 12.4
Rex    | Mar | 12.8
Mia    | Jan | 4.0
Mia    | Fev | 4.1
Mia    | Mar | 4.3</code></pre>
<p>Agora temos exatamente 3 variáveis (animal, mês, peso) e cada linha é uma única observação (o peso de um animal em um mês específico). Isso é o que um programa de análise estatística espera para conseguir filtrar, agrupar e calcular sem esforço manual.</p>

<h2>Por que isso importa na prática</h2>
<p>Com dados arrumados, perguntas como "qual foi o peso médio por mês?" ou "quais animais ganharam peso?" viram uma linha de código simples. Com dados no formato "planilha bonita", a mesma pergunta exige reorganizar manualmente os dados antes de sequer começar a análise.</p>',
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => NULL,
    'passing_score' => 70,
    'order' => 3,
  ),
  3 => 
  array (
    'title' => 'Quiz 2 — Tidy Data',
    'type' => 'avaliacao',
    'content' => NULL,
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => 
    array (
      0 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Quais são as três regras do tidy data?',
        'options' => 
        array (
          0 => 'Uma aba, uma cor, um título',
          1 => 'Cada coluna é uma variável, cada linha é uma observação, cada célula um único valor',
          2 => 'Sempre usar maiúsculas, sempre numerar as linhas, sempre ordenar alfabeticamente',
          3 => 'Uma planilha por aluno, uma aba por ano, uma cor por status',
        ),
        'correct_index' => 1,
      ),
      1 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Na planilha com colunas "Jan", "Fev", "Mar" para o peso de cada mês, qual regra do tidy data está sendo violada?',
        'options' => 
        array (
          0 => 'Cada linha é uma observação',
          1 => 'Cada célula contém um único valor',
          2 => 'Cada coluna é uma variável — aqui, mês virou várias colunas em vez de uma coluna com os valores dos meses',
          3 => 'Nenhuma regra é violada',
        ),
        'correct_index' => 2,
      ),
      2 => 
      array (
        'type' => 'codigo',
        'question' => 'Na versão "arrumada" do exemplo (animal, mes, peso_kg), quantas variáveis (colunas) existem ao todo? Digite o número.',
        'code_snippet' => 'animal | mes | peso_kg
Rex    | Jan | 12.1
...',
        'expected_answer' => '3',
      ),
      3 => 
      array (
        'type' => 'codigo',
        'question' => 'Na versão "arrumada" do exemplo do peso de Rex e Mia em 3 meses cada, quantas linhas (observações) existem ao todo? Digite o número.',
        'code_snippet' => '2 animais x 3 meses cada',
        'expected_answer' => '6',
      ),
    ),
    'passing_score' => 70,
    'order' => 4,
  ),
  4 => 
  array (
    'title' => 'Módulo 3 — Erros Comuns em Planilhas de Pesquisa',
    'type' => 'instrucao',
    'content' => '<p><img src="/images/courses/erros-comuns-planilha.svg" alt="Quatro erros comuns em planilhas de prontuário veterinário: célula mesclada, cor como dado, subtotal misturado e mais de um valor por célula" style="max-width:100%; border-radius: 8px;"></p>
<h2>Os erros mais frequentes (e como evitá-los)</h2>

<h3>1. Células mescladas</h3>
<p>Mesclar células para "economizar espaço" (ex.: um nome de animal mesclado sobre várias linhas de exames) quebra a leitura linha-a-linha que qualquer programa de análise faz. O valor mesclado só existe na primeira célula; as demais ficam vazias — o que gera dados ausentes falsos.</p>

<h3>2. Múltiplas tabelas na mesma aba</h3>
<p>É comum ver, na mesma aba de planilha, uma tabela de pacientes seguida de um resumo/gráfico logo abaixo, sem nenhuma linha vazia clara de separação nem estrutura consistente. Um programa de leitura automática não sabe distinguir "isso é a tabela principal" de "isso é um resumo à parte" — ele tenta ler tudo como se fosse uma única tabela.</p>

<h3>3. Cor como informação</h3>
<p>Marcar linhas de vermelho para "animal óbito" ou verde para "tratamento concluído" parece prático visualmente, mas a cor <strong>não é um dado</strong> — não pode ser lida, filtrada ou contada por um programa de análise. Se a informação importa, ela precisa estar em uma coluna própria (ex.: uma coluna "status" com o valor "Óbito" ou "Concluído").</p>

<h3>4. Subtotais e totais misturados nos dados</h3>
<p>Inserir uma linha de "Total" ou "Média" no meio dos dados brutos (comum em planilhas financeiras e de estoque) faz com que qualquer cálculo automático sobre a coluna inclua esse valor por engano, distorcendo a análise.</p>

<h3>5. Uma célula, mais de um valor</h3>
<p>Uma célula com o conteúdo <code>"Dipirona 500mg + Amoxicilina 250mg"</code> impede filtrar ou contar por medicamento individualmente. Cada medicamento deveria ser uma linha (ou colunas separadas), nunca um texto concatenado numa única célula.</p>

<h3>6. Cabeçalhos em mais de uma linha, ou ausentes</h3>
<p>Cabeçalhos que ocupam duas linhas mescladas (ex.: uma linha com o grupo e outra com a subcategoria) ou planilhas sem cabeçalho algum obrigam quem for importar os dados a corrigir manualmente antes de qualquer análise.</p>

<h2>Regra prática</h2>
<p>Antes de considerar uma planilha "pronta", pergunte: <em>"se eu apagar toda a formatação visual (cores, mesclagens, negrito) e olhar só o texto puro, ainda dá para entender cada linha como um registro único e consistente?"</em> Se a resposta for não, a planilha precisa ser reorganizada.</p>',
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => NULL,
    'passing_score' => 70,
    'order' => 5,
  ),
  5 => 
  array (
    'title' => 'Quiz 3 — Erros Comuns em Planilhas',
    'type' => 'avaliacao',
    'content' => NULL,
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => 
    array (
      0 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Por que usar cor de célula para indicar "animal óbito" é problemático para análise de dados?',
        'options' => 
        array (
          0 => 'Porque cores deixam o arquivo maior demais',
          1 => 'Porque cor não é um dado — não pode ser lida, filtrada ou contada por um programa',
          2 => 'Porque o Excel não permite colorir células',
          3 => 'Não é problemático, é a melhor prática recomendada',
        ),
        'correct_index' => 1,
      ),
      1 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Qual o problema de inserir uma linha de "Total" no meio dos dados brutos de uma planilha?',
        'options' => 
        array (
          0 => 'Nenhum, é uma boa prática',
          1 => 'Cálculos automáticos sobre a coluna passam a incluir esse valor indevidamente, distorcendo a análise',
          2 => 'O Excel remove a linha automaticamente',
          3 => 'Torna a planilha mais rápida de abrir',
        ),
        'correct_index' => 1,
      ),
      2 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Uma célula com o valor "Dipirona 500mg + Amoxicilina 250mg" viola qual princípio?',
        'options' => 
        array (
          0 => 'Cada linha é uma observação',
          1 => 'Cada célula contém um único valor',
          2 => 'Cada coluna é uma variável',
          3 => 'Nenhum — é a forma correta de registrar múltiplos medicamentos',
        ),
        'correct_index' => 1,
      ),
      3 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Qual pergunta o módulo sugere fazer para checar se uma planilha está pronta para análise?',
        'options' => 
        array (
          0 => 'A planilha está bonita e colorida?',
          1 => 'Se eu apagar toda a formatação visual, ainda dá para entender cada linha como um registro único e consistente?',
          2 => 'A planilha tem menos de 100 linhas?',
          3 => 'Todas as células estão mescladas corretamente?',
        ),
        'correct_index' => 1,
      ),
    ),
    'passing_score' => 70,
    'order' => 6,
  ),
  6 => 
  array (
    'title' => 'Módulo 4 — Formato Largo (Wide) vs. Formato Longo (Long)',
    'type' => 'instrucao',
    'content' => '<p><img src="/images/courses/tidy-data-comparison.svg" alt="Comparação lado a lado entre formato largo e formato longo usando dados de peso de animais" style="max-width:100%; border-radius: 8px;"></p>
<h2>Duas formas válidas de organizar a mesma informação</h2>
<p>Nem todo dado "não-tidy" está errado — às vezes o formato <strong>largo (wide)</strong> é intencional e útil para leitura humana ou para determinados tipos de gráfico. O importante é saber quando usar cada um.</p>

<h2>Formato largo (wide)</h2>
<pre><code>animal | peso_jan | peso_fev | peso_mar
Rex    | 12.1     | 12.4     | 12.8
Mia    | 4.0      | 4.1      | 4.3</code></pre>
<p>Cada linha é um animal, e cada mês vira uma coluna própria. É fácil de LER para um humano (bom para relatórios e tabelas de apresentação), mas ruim para ANALISAR estatisticamente (ex.: calcular a média geral por mês exige olhar 3 colunas diferentes).</p>

<h2>Formato longo (long)</h2>
<pre><code>animal | mes | peso_kg
Rex    | Jan | 12.1
Rex    | Fev | 12.4
Rex    | Mar | 12.8
Mia    | Jan | 4.0
Mia    | Fev | 4.1
Mia    | Mar | 4.3</code></pre>
<p>Esse é o formato "tidy" que vimos no Módulo 2 — cada combinação (animal, mês) é uma linha. Fica mais difícil de ler visualmente numa planilha grande, mas é o formato que praticamente toda ferramenta estatística (R, Python, SPSS) espera para analisar, filtrar, agrupar e plotar dados ao longo do tempo.</p>

<h2>Regra prática</h2>
<ul>
	<li><strong>Para coletar e guardar os dados brutos</strong>: prefira sempre o formato longo — é mais fácil de auditar, adicionar novas observações e processar computacionalmente.</li>
	<li><strong>Para apresentar um resumo em relatório/reunião</strong>: o formato largo pode ser gerado a partir do longo, na hora de apresentar (nunca o contrário).</li>
</ul>
<p>Ou seja: dado bruto sempre em formato longo; formato largo é só uma "visualização" derivada, gerada quando necessário — nunca a forma de armazenamento original.</p>',
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => NULL,
    'passing_score' => 70,
    'order' => 7,
  ),
  7 => 
  array (
    'title' => 'Quiz 4 — Wide vs. Long',
    'type' => 'avaliacao',
    'content' => NULL,
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => 
    array (
      0 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'No formato largo (wide) do exemplo de peso mensal, o que cada coluna extra (peso_jan, peso_fev, peso_mar) representa?',
        'options' => 
        array (
          0 => 'Um animal diferente',
          1 => 'Um valor da variável "mês", transformado em coluna própria',
          2 => 'Um erro de digitação',
          3 => 'Uma nova variável totalmente independente do peso',
        ),
        'correct_index' => 1,
      ),
      1 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Qual formato praticamente toda ferramenta estatística (R, Python, SPSS) espera para analisar dados ao longo do tempo?',
        'options' => 
        array (
          0 => 'Largo (wide)',
          1 => 'Longo (long)',
          2 => 'Nenhum dos dois, formato próprio binário',
          3 => 'Depende só da cor das células',
        ),
        'correct_index' => 1,
      ),
      2 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Segundo a regra prática do módulo, qual deve ser o formato de armazenamento original dos dados brutos?',
        'options' => 
        array (
          0 => 'Sempre largo (wide)',
          1 => 'Sempre longo (long)',
          2 => 'Alternar aleatoriamente',
          3 => 'Não importa, os dois são equivalentes para qualquer uso',
        ),
        'correct_index' => 1,
      ),
      3 => 
      array (
        'type' => 'codigo',
        'question' => 'No formato largo do exemplo (animal, peso_jan, peso_fev, peso_mar, com 2 animais), quantas colunas existem ao todo? Digite o número.',
        'code_snippet' => 'animal | peso_jan | peso_fev | peso_mar',
        'expected_answer' => '4',
      ),
    ),
    'passing_score' => 70,
    'order' => 8,
  ),
  8 => 
  array (
    'title' => 'Módulo 5 — Nomeação de Variáveis e Valores',
    'type' => 'instrucao',
    'content' => '<h2>Por que a nomenclatura consistente importa</h2>
<p>Nomes de coluna e valores inconsistentes são uma das causas mais comuns de erro silencioso na análise de dados — o programa não "trava", mas silenciosamente trata "Canino", "canino" e "CANINO" como três categorias diferentes.</p>

<h2>Boas práticas para nomes de colunas (variáveis)</h2>
<ul>
	<li><strong>Sem espaços</strong>: use <code>peso_kg</code> em vez de <code>Peso (kg)</code>. Espaços obrigam o uso de aspas/colchetes em quase toda ferramenta.</li>
	<li><strong>Sem acentos e caracteres especiais</strong>: use <code>especie</code> em vez de <code>Espécie</code>. Acentos causam problemas de encoding (veremos no Módulo 8).</li>
	<li><strong>snake_case consistente</strong>: prefira <code>data_nascimento</code> em vez de misturar <code>DataNascimento</code>, <code>data-nascimento</code> e <code>Data_Nascimento</code> em colunas diferentes da mesma base.</li>
	<li><strong>Nomes curtos, mas descritivos</strong>: <code>peso_kg</code> é melhor que só <code>peso</code> (ambíguo — kg ou g?) e melhor que <code>p</code> (curto demais, sem significado).</li>
</ul>

<h2>Boas práticas para os VALORES dentro das colunas</h2>
<ul>
	<li><strong>Categoria sempre escrita da mesma forma</strong>: escolha um padrão (ex.: sempre "Canino", nunca "canino"/"CÃO"/"cachorro" misturados na mesma coluna) e aplique em toda a base.</li>
	<li><strong>Evite códigos sem dicionário</strong>: se uma coluna usa <code>1</code> para "Macho" e <code>2</code> para "Fêmea", documente isso em algum lugar (idealmente use o próprio texto "Macho"/"Fêmea" na coluna, é mais seguro que decorar códigos numéricos).</li>
	<li><strong>Um padrão único para valor ausente</strong>: escolha uma única forma de marcar "não informado" (célula vazia, ou "NA", ou "N/D") — nunca misturar várias formas diferentes na mesma coluna, pois cada uma será tratada como algo diferente pelo programa de análise.</li>
</ul>

<h2>Exemplo prático do problema</h2>
<pre><code>especie
Canino
canino
CÃO
Cachorro</code></pre>
<p>Para um humano, é óbvio que as quatro linhas significam a mesma coisa. Para um programa de análise, são <strong>4 categorias distintas</strong> — o que quebra qualquer contagem, gráfico ou filtro por espécie.</p>',
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => NULL,
    'passing_score' => 70,
    'order' => 9,
  ),
  9 => 
  array (
    'title' => 'Quiz 5 — Nomeação de Variáveis e Valores',
    'type' => 'avaliacao',
    'content' => NULL,
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => 
    array (
      0 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Por que é recomendado evitar espaços e acentos nos nomes de colunas?',
        'options' => 
        array (
          0 => 'Porque deixam o arquivo maior',
          1 => 'Porque obrigam uso de aspas/colchetes e podem causar problemas de encoding',
          2 => 'Porque o Excel não permite',
          3 => 'Não há motivo real, é só estética',
        ),
        'correct_index' => 1,
      ),
      1 => 
      array (
        'type' => 'codigo',
        'question' => 'Considere a coluna "especie" com os valores: Canino, canino, CÃO, Cachorro. Para um programa de análise, quantas categorias DISTINTAS essa coluna teria (sem padronização)? Digite o número.',
        'code_snippet' => 'Canino
canino
CÃO
Cachorro',
        'expected_answer' => '4',
      ),
      2 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Qual é a melhor prática ao usar códigos numéricos para representar categorias (ex.: 1 = Macho, 2 = Fêmea)?',
        'options' => 
        array (
          0 => 'Nunca documentar, é intuitivo',
          1 => 'Documentar o significado em algum lugar, ou preferir usar diretamente o texto (ex.: "Macho"/"Fêmea") na coluna',
          2 => 'Usar códigos diferentes em cada planilha para variar',
          3 => 'Trocar o código a cada nova coleta de dados',
        ),
        'correct_index' => 1,
      ),
      3 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Por que misturar diferentes formas de marcar "não informado" (célula vazia, "NA", "N/D") na mesma coluna é um problema?',
        'options' => 
        array (
          0 => 'Não é um problema, todas significam a mesma coisa automaticamente para o programa',
          1 => 'Cada forma diferente é tratada como um valor distinto pelo programa de análise',
          2 => 'Porque células vazias sempre corrompem o arquivo',
          3 => 'Porque "NA" é uma palavra proibida em planilhas',
        ),
        'correct_index' => 1,
      ),
    ),
    'passing_score' => 70,
    'order' => 10,
  ),
  10 => 
  array (
    'title' => 'Módulo 6 — Tipos de Dados: Declarando Corretamente',
    'type' => 'instrucao',
    'content' => '<h2>Por que o tipo de cada coluna importa</h2>
<p>Todo programa de análise trata cada coluna como um <strong>tipo</strong> específico — numérico, texto, data ou categórico — e erros de tipo geram os bugs mais silenciosos e traiçoeiros da análise de dados.</p>

<h2>Os quatro tipos básicos</h2>
<ul>
	<li><strong>Numérico</strong>: idade, peso, frequência cardíaca — valores que fazem sentido somar, calcular média, comparar.</li>
	<li><strong>Texto (string)</strong>: nome do animal, observações livres — não faz sentido calcular "média" de um texto.</li>
	<li><strong>Data</strong>: data de nascimento, data do exame — tem regras próprias de comparação (antes/depois) que texto puro não tem.</li>
	<li><strong>Categórico</strong>: espécie, sexo, status do tratamento — um conjunto limitado e conhecido de valores possíveis.</li>
</ul>

<h2>Armadilhas comuns</h2>
<h3>1. Número tratado como texto</h3>
<p>Se a coluna "peso_kg" tiver algum valor digitado como <code>"12,5 kg"</code> (com a unidade junto) em vez de só <code>12.5</code>, toda a coluna pode ser importada como texto — e aí não dá para calcular média, comparar ou plotar sem antes corrigir manualmente célula por célula.</p>

<h3>2. Zeros à esquerda perdidos</h3>
<p>Um código de registro como <code>"007"</code> (com zeros à esquerda) costuma virar <code>7</code> automaticamente no Excel, porque o programa "acha" que é um número. Se o zero à esquerda é parte importante da identificação, a coluna precisa ser explicitamente formatada como texto.</p>

<h3>3. Datas interpretadas de forma errada</h3>
<p>A data <code>03/04/2026</code> significa "3 de abril" no padrão brasileiro (dia/mês/ano), mas "4 de março" no padrão americano (mês/dia/ano). Planilhas abertas em configurações regionais diferentes podem interpretar essa mesma data de duas formas opostas — por isso o formato <strong>ISO 8601 (AAAA-MM-DD)</strong>, ex. <code>2026-04-03</code>, é o recomendado internacionalmente: não tem ambiguidade possível.</p>

<h3>4. Categórico tratado como texto livre</h3>
<p>Se "espécie" for tratada apenas como texto livre (sem limitar os valores possíveis), nada impede erros de digitação criando categorias fantasmas (como vimos no Módulo 5 com "Canino"/"canino"/"CÃO").</p>

<h2>Regra prática</h2>
<p>Antes de importar uma base para análise, sempre confira: números realmente aparecem como número (sem unidade junto), datas estão em formato AAAA-MM-DD, e colunas categóricas têm um conjunto fechado e padronizado de valores.</p>',
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => NULL,
    'passing_score' => 70,
    'order' => 11,
  ),
  11 => 
  array (
    'title' => 'Quiz 6 — Tipos de Dados',
    'type' => 'avaliacao',
    'content' => NULL,
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => 
    array (
      0 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Por que digitar o peso como "12,5 kg" (com a unidade junto) na célula é problemático?',
        'options' => 
        array (
          0 => 'Não é problemático, é mais informativo',
          1 => 'A coluna inteira pode ser importada como texto, impedindo cálculos de média/comparação sem correção manual',
          2 => 'O Excel corrige automaticamente sempre',
          3 => 'Porque "kg" é uma palavra reservada',
        ),
        'correct_index' => 1,
      ),
      1 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Qual formato de data é recomendado internacionalmente por não ter ambiguidade entre dia e mês?',
        'options' => 
        array (
          0 => 'DD/MM/AAAA',
          1 => 'MM/DD/AAAA',
          2 => 'AAAA-MM-DD (ISO 8601)',
          3 => 'Por extenso, sempre em português',
        ),
        'correct_index' => 2,
      ),
      2 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'O que costuma acontecer com um código como "007" se a coluna não for formatada explicitamente como texto?',
        'options' => 
        array (
          0 => 'Permanece "007" para sempre',
          1 => 'Os zeros à esquerda são perdidos e o valor vira "7"',
          2 => 'Gera um erro que impede salvar o arquivo',
          3 => 'É convertido automaticamente em data',
        ),
        'correct_index' => 1,
      ),
      3 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Por que uma coluna categórica (ex.: espécie) deveria ter um conjunto fechado e padronizado de valores, em vez de texto livre?',
        'options' => 
        array (
          0 => 'Para deixar a planilha mais colorida',
          1 => 'Para evitar erros de digitação que criam categorias fantasmas/duplicadas',
          2 => 'Não há diferença nenhuma na prática',
          3 => 'Porque texto livre é proibido em bancos de dados',
        ),
        'correct_index' => 1,
      ),
    ),
    'passing_score' => 70,
    'order' => 12,
  ),
  12 => 
  array (
    'title' => 'Módulo 7 — Formatos de Arquivo: CSV, TXT e XLSX',
    'type' => 'instrucao',
    'content' => '<h2>Os principais formatos e quando usar cada um</h2>

<h3>XLSX (Excel)</h3>
<p>Formato binário proprietário da Microsoft. Permite formatação visual, fórmulas, múltiplas abas e gráficos — ótimo para <strong>apresentação e trabalho manual</strong>, mas é um formato "fechado": difícil de versionar, propenso a carregar formatação/mesclagens problemáticas (Módulo 3), e nem toda ferramenta de análise lê com a mesma facilidade.</p>

<h3>CSV (Comma-Separated Values)</h3>
<p>Um arquivo de <strong>texto plano</strong> onde cada linha é um registro e os valores são separados por um delimitador (geralmente vírgula, mas no Brasil frequentemente ponto e vírgula — veremos por quê no próximo módulo). É o formato mais universal para troca de dados tabulares: praticamente toda ferramenta de análise (R, Python, SPSS, SQL, Excel) lê e escreve CSV nativamente.</p>
<pre><code>animal,especie,peso_kg
Rex,Canino,12.5
Mia,Felino,4.1</code></pre>

<h3>TXT (texto simples)</h3>
<p>Igual ao CSV em espírito (texto plano), mas sem uma convenção fixa de delimitador — pode ser separado por tabulação (TSV, "tab-separated values"), espaço, ou qualquer outro caractere combinado. Muito usado para exportações de equipamentos de laboratório e sistemas legados.</p>

<h2>Por que preferir CSV/TXT para os dados BRUTOS de pesquisa</h2>
<ul>
	<li><strong>Universalidade</strong>: qualquer software de análise abre um CSV sem plugin ou conversão.</li>
	<li><strong>Transparência</strong>: um CSV pode ser aberto e lido em qualquer editor de texto simples, sem precisar de licença de software específico.</li>
	<li><strong>Não esconde formatação problemática</strong>: como é texto puro, é impossível ter células mescladas ou cores "escondendo" informação — os erros do Módulo 3 ficam visíveis e óbvios.</li>
	<li><strong>Facilita versionamento e auditoria</strong>: veremos em detalhe no Módulo 9.</li>
</ul>

<h2>Regra prática</h2>
<p>Use XLSX para relatórios finais, apresentações e planilhas de trabalho manual do dia a dia. Mas mantenha e compartilhe a <strong>base de dados bruta de pesquisa sempre em CSV</strong> — é o formato mais seguro para arquivamento de longo prazo e para qualquer análise estatística posterior.</p>',
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => NULL,
    'passing_score' => 70,
    'order' => 13,
  ),
  13 => 
  array (
    'title' => 'Quiz 7 — Formatos de Arquivo',
    'type' => 'avaliacao',
    'content' => NULL,
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => 
    array (
      0 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Qual formato é recomendado para armazenar a base de dados BRUTA de uma pesquisa, visando compatibilidade e reprodutibilidade?',
        'options' => 
        array (
          0 => 'XLSX',
          1 => 'CSV (texto plano)',
          2 => 'PDF',
          3 => 'Imagem (PNG/JPG) da tabela',
        ),
        'correct_index' => 1,
      ),
      1 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Por que um arquivo CSV "não esconde" formatação problemática como células mescladas ou cores?',
        'options' => 
        array (
          0 => 'Porque CSV é mais bonito que XLSX',
          1 => 'Porque é texto puro — não existe conceito de mesclagem de célula ou cor em texto plano',
          2 => 'Porque CSV corrige erros automaticamente',
          3 => 'Não há diferença nenhuma entre CSV e XLSX nesse aspecto',
        ),
        'correct_index' => 1,
      ),
      2 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Para qual finalidade o formato XLSX continua sendo uma boa escolha, segundo o módulo?',
        'options' => 
        array (
          0 => 'Armazenamento de longo prazo da base de dados bruta',
          1 => 'Relatórios finais, apresentações e planilhas de trabalho manual do dia a dia',
          2 => 'Nunca deve ser usado em pesquisa',
          3 => 'Troca de dados entre diferentes softwares estatísticos',
        ),
        'correct_index' => 1,
      ),
    ),
    'passing_score' => 70,
    'order' => 14,
  ),
  14 => 
  array (
    'title' => 'Módulo 8 — Encoding e Delimitadores',
    'type' => 'instrucao',
    'content' => '<h2>O problema dos acentos "quebrados"</h2>
<p>Já abriu um arquivo e viu "Espécie" aparecer como "EspÃ©cie" ou algo parecido? Isso é um problema de <strong>encoding</strong> (codificação de caracteres) — o arquivo foi salvo com uma codificação e está sendo lido com outra.</p>

<h2>UTF-8: o padrão recomendado</h2>
<p><strong>UTF-8</strong> é a codificação de caracteres mais universal e recomendada hoje para qualquer arquivo de texto/CSV, pois representa corretamente acentos, cedilhas e caracteres especiais de praticamente qualquer idioma, e é o padrão esperado por quase toda ferramenta moderna de análise. Ao salvar um CSV, sempre verifique se a opção de codificação está definida como "UTF-8" (às vezes aparece como "UTF-8 (com BOM)" ou "Unicode").</p>

<h2>O delimitador: vírgula ou ponto e vírgula?</h2>
<p>CSV significa literalmente "valores separados por vírgula" — mas no Brasil (e em outros países que usam vírgula como separador decimal, ex. <code>12,5</code> em vez de <code>12.5</code>), o Excel troca automaticamente o delimitador do CSV de vírgula para <strong>ponto e vírgula</strong>, para não confundir o separador de colunas com o separador decimal dos números.</p>
<pre><code>Padrão internacional (decimal com ponto):
animal,peso_kg
Rex,12.5

Padrão brasileiro do Excel (decimal com vírgula):
animal;peso_kg
Rex;12,5</code></pre>
<p>Isso significa que um CSV exportado do Excel brasileiro pode não ser lido corretamente por um programa configurado para esperar vírgula como delimitador — é preciso saber qual dos dois padrões está sendo usado, ou configurar a ferramenta de leitura para reconhecer o delimitador correto.</p>

<h2>Regra prática</h2>
<ul>
	<li>Ao salvar um CSV, prefira sempre a codificação <strong>UTF-8</strong>.</li>
	<li>Anote (ou documente) qual delimitador seu arquivo usa — vírgula ou ponto e vírgula — especialmente ao compartilhar dados com outras pessoas/ferramentas.</li>
	<li>Ao abrir um CSV recebido de outra fonte e ver tudo numa única coluna "bagunçada", o problema quase sempre é delimitador errado, não os dados em si.</li>
</ul>',
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => NULL,
    'passing_score' => 70,
    'order' => 15,
  ),
  15 => 
  array (
    'title' => 'Quiz 8 — Encoding e Delimitadores',
    'type' => 'avaliacao',
    'content' => NULL,
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => 
    array (
      0 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'O que costuma causar o problema de acentos aparecerem "quebrados" (ex.: "EspÃ©cie") em um arquivo de texto?',
        'options' => 
        array (
          0 => 'O arquivo está corrompido permanentemente',
          1 => 'Um problema de encoding — o arquivo foi salvo com uma codificação e lido com outra',
          2 => 'O uso de letras maiúsculas',
          3 => 'O tamanho do arquivo é grande demais',
        ),
        'correct_index' => 1,
      ),
      1 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Qual codificação de caracteres é recomendada como padrão universal para arquivos CSV/texto hoje?',
        'options' => 
        array (
          0 => 'ASCII',
          1 => 'Latin-1',
          2 => 'UTF-8',
          3 => 'Não importa, todas são equivalentes',
        ),
        'correct_index' => 2,
      ),
      2 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Por que o Excel configurado em português do Brasil costuma exportar CSV usando ponto e vírgula como delimitador em vez de vírgula?',
        'options' => 
        array (
          0 => 'É um bug sem explicação',
          1 => 'Porque a vírgula já é usada como separador decimal dos números (ex.: 12,5), então o Excel usa ponto e vírgula para não confundir',
          2 => 'Porque ponto e vírgula é mais rápido de processar',
          3 => 'Porque CSV brasileiro é um formato diferente, incompatível com qualquer outro país',
        ),
        'correct_index' => 1,
      ),
      3 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Se você abre um CSV recebido de outra pessoa e todos os dados aparecem amontoados numa única coluna, qual é a causa mais provável?',
        'options' => 
        array (
          0 => 'Os dados estão errados na origem',
          1 => 'O delimitador esperado pelo programa não é o mesmo usado no arquivo (vírgula vs ponto e vírgula, por exemplo)',
          2 => 'O arquivo é grande demais para abrir',
          3 => 'Falta instalar uma fonte específica',
        ),
        'correct_index' => 1,
      ),
    ),
    'passing_score' => 70,
    'order' => 16,
  ),
  16 => 
  array (
    'title' => 'Módulo 9 — Reprodutibilidade e Versionamento de Dados',
    'type' => 'instrucao',
    'content' => '<h2>Por que texto plano facilita a reprodutibilidade científica</h2>
<p><strong>Reprodutibilidade</strong> significa que outra pessoa (ou você mesmo, meses depois) consegue repetir exatamente a mesma análise e chegar ao mesmo resultado, entendendo exatamente o que mudou entre uma versão dos dados e outra.</p>

<h2>O problema dos arquivos binários (XLSX) para controle de versão</h2>
<p>Ferramentas de controle de versão como o <strong>Git</strong> (usadas para rastrear mudanças em código e, cada vez mais, em dados de pesquisa) funcionam comparando o conteúdo <strong>linha por linha</strong> entre versões. Arquivos binários como XLSX não são comparáveis dessa forma — o Git só consegue dizer "o arquivo mudou", sem mostrar exatamente qual célula ou valor foi alterado.</p>

<h2>CSV/TXT: cada mudança fica visível</h2>
<p>Como CSV é texto puro, uma ferramenta de controle de versão consegue mostrar exatamente qual linha mudou, o que foi adicionado e o que foi removido entre duas versões da base — essencial para auditoria científica (poder provar exatamente quando e como um dado foi corrigido) e para trabalho colaborativo em equipe.</p>

<h2>Boas práticas de versionamento de dados, mesmo sem usar Git</h2>
<ul>
	<li><strong>Nunca sobrescreva a base bruta original</strong>: salve sempre uma cópia intocada da coleta original (ex.: <code>dados_brutos_2026-08-13.csv</code>) e trabalhe as correções/limpezas em cópias separadas.</li>
	<li><strong>Nomeie versões com data no formato ISO</strong>: <code>dados_2026-08-13.csv</code> ordena corretamente em qualquer sistema de arquivos, diferente de <code>dados_13-08-2026.csv</code>.</li>
	<li><strong>Documente o que mudou entre versões</strong>: um arquivo simples de texto (<code>CHANGELOG.txt</code>) listando "removidas 3 linhas duplicadas em 13/08" já ajuda enormemente na hora de justificar decisões de limpeza de dados numa auditoria ou revisão por pares.</li>
	<li><strong>Nunca edite a base bruta "no olho" diretamente na planilha original</strong>: qualquer limpeza (remoção de duplicata, correção de outlier) deveria, idealmente, ser feita via código (R/Python) documentado, não por edição manual invisível — assim o processo inteiro fica reproduzível.</li>
</ul>

<h2>Conectando com os cursos de R e Python</h2>
<p>Se você já fez (ou vai fazer) os cursos de R ou Python para Ciências de Dados desta plataforma, perceba que os exemplos de limpeza de dados (valores ausentes, duplicatas, outliers) daqueles cursos partem exatamente do princípio deste módulo: a limpeza deve ser <strong>feita por código, documentada e reprodutível</strong> — nunca uma edição manual silenciosa na planilha original.</p>',
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => NULL,
    'passing_score' => 70,
    'order' => 17,
  ),
  17 => 
  array (
    'title' => 'Avaliação Final — Reprodutibilidade e Checklist',
    'type' => 'avaliacao',
    'content' => NULL,
    'video_url' => NULL,
    'practice_exercises' => NULL,
    'quiz_questions' => 
    array (
      0 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Por que arquivos binários como XLSX são problemáticos para ferramentas de controle de versão como o Git?',
        'options' => 
        array (
          0 => 'Porque são arquivos muito pequenos',
          1 => 'Porque o Git só consegue dizer que "o arquivo mudou", sem mostrar exatamente qual célula/valor foi alterado',
          2 => 'Porque o Git não abre nenhum tipo de planilha',
          3 => 'Não há problema nenhum, XLSX é ideal para versionamento',
        ),
        'correct_index' => 1,
      ),
      1 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Qual formato de nome de arquivo com data ordena corretamente em qualquer sistema de arquivos?',
        'options' => 
        array (
          0 => 'dados_13-08-2026.csv',
          1 => 'dados_08-13-2026.csv',
          2 => 'dados_2026-08-13.csv (formato ISO)',
          3 => 'Não importa, todos ordenam igual',
        ),
        'correct_index' => 2,
      ),
      2 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Segundo o módulo, como uma limpeza de dados (remoção de duplicata, correção de outlier) deveria idealmente ser feita, visando reprodutibilidade?',
        'options' => 
        array (
          0 => 'Editando diretamente a planilha original, sem deixar rastro',
          1 => 'Por código documentado (ex.: em R ou Python), nunca por edição manual silenciosa',
          2 => 'Apagando a base original depois de corrigida',
          3 => 'Sempre pedindo para outra pessoa fazer, sem anotar o que foi feito',
        ),
        'correct_index' => 1,
      ),
      3 => 
      array (
        'type' => 'multipla_escolha',
        'question' => 'Qual prática o módulo recomenda em relação à base de dados bruta original?',
        'options' => 
        array (
          0 => 'Sempre sobrescrevê-la com a versão mais recente corrigida',
          1 => 'Nunca sobrescrevê-la — manter sempre uma cópia intocada da coleta original',
          2 => 'Deletá-la assim que a análise terminar',
          3 => 'Convertê-la para imagem para "congelar" o conteúdo',
        ),
        'correct_index' => 1,
      ),
      4 => 
      array (
        'type' => 'codigo',
        'question' => 'Entre "dados_13-08-2026.csv" e "dados_2026-08-13.csv", qual dos dois nomes usa o formato de data recomendado (ISO)? Digite o nome completo do arquivo.',
        'code_snippet' => 'dados_13-08-2026.csv
dados_2026-08-13.csv',
        'expected_answer' => 'dados_2026-08-13.csv',
      ),
    ),
    'passing_score' => 70,
    'order' => 18,
  ),
);

        $this->createCourse($rCourseData, $rModules);
        $this->createCourse($pyCourseData, $pyModules);
        $this->createCourse($dataOrgCourseData, $dataOrgModules);
    }

    private function createCourse(array $courseData, array $modules): void
    {
        $course = Course::updateOrCreate(
            ['name' => $courseData['name']],
            [
                'workload' => $courseData['workload'],
                'start_date' => now(),
                'end_date' => now()->addMonths(6),
                'active' => true,
                'syllabus' => $courseData['syllabus'],
            ]
        );

        $course->modules()->delete();

        foreach ($modules as $module) {
            CourseModule::create(array_merge($module, ['course_id' => $course->id]));
        }

        $this->command?->info("Curso \"{$course->name}\" pronto com " . count($modules) . ' etapas.');
    }
}
