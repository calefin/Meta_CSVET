# Diretrizes e Boas Práticas de SEO

Este guia apresenta a arquitetura de SEO implementada e recomendada para a plataforma **profatilio.com.br**. O objetivo é garantir indexação máxima nos motores de busca (Google, Bing) e exibição otimizada em redes sociais (Open Graph/Twitter Cards).

---

## 🏷️ 1. Estrutura de Meta Tags Recomendada

Para todas as páginas públicas (Landing Page, Biografia, Mural, Detalhes de Artigo), insira as seguintes tags dentro do `<head>` do HTML (exemplo adaptado para Blade layout):

```html
<!-- SEO Base -->
<title>{{ $title ?? 'Prof. Atilio - Patologia, Farmacologia e Toxicologia Veterinária' }}</title>
<meta name="description" content="{{ $description ?? 'Portfólio acadêmico, divulgação científica, laudos de necropsia e cursos especializados em medicina veterinária.' }}">
<link rel="canonical" href="{{ url()->current() }}">

<!-- Open Graph / Facebook -->
<meta property="og:type" content="website">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:title" content="{{ $title ?? 'Prof. Atilio' }}">
<meta property="og:description" content="{{ $description ?? 'Divulgação científica e serviços veterinários.' }}">
<meta property="og:image" content="{{ asset('images/og-share-default.jpg') }}">

<!-- Twitter Cards -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:url" content="{{ url()->current() }}">
<meta name="twitter:title" content="{{ $title ?? 'Prof. Atilio' }}">
<meta name="twitter:description" content="{{ $description ?? 'Divulgação científica e serviços veterinários.' }}">
<meta name="twitter:image" content="{{ asset('images/og-share-default.jpg') }}">

<!-- Indexação de Robôs -->
<meta name="robots" content="index, follow">
```

---

## 🏗️ 2. Hierarquia Semântica e Acessibilidade

Para garantir boa pontuação no Google Lighthouse SEO:

1. **Apenas um `<h1>` por página:** O título principal deve descrever o tema da página de forma única (ex: `<h1 id="main-title">Prof. Dr. Atilio Calefi</h1>`).
2. **Hierarquia de Títulos:** Siga a ordem lógica `<h1>` ➔ `<h2>` ➔ `<h3>` sem pular níveis de cabeçalho.
3. **Atributo `alt` em Imagens:** Todas as imagens públicas devem ter descrições acessíveis (ex: `alt="Foto de perfil do Prof. Atilio ministrando aula de patologia"`).
4. **IDs Únicos e Descritivos:** Todos os elementos interativos (botões, links, formulários de contato) devem possuir IDs únicos para facilidade de testes de acessibilidade e rastreamento.

---

## 🗺️ 3. Sitemap e Indexação

- O arquivo **[sitemap.xml](file:///D:/profatilio/public/sitemap.xml)** foi colocado na raiz pública (`public/sitemap.xml`) contendo as URLs iniciais estáticas do sistema.
- Ao publicar novos artigos ou divulgações científicas no Mural, o sitemap pode ser atualizado dinamicamente.
- O arquivo de rotas do Laravel pode ser configurado para expor um sitemap dinâmico se a quantidade de publicações crescer muito.
