# Memória do Projeto: CSVET Portal

Este documento contém diretrizes, arquitetura e decisões técnicas específicas do projeto **CSVET** para agentes de IA que venham a colaborar neste repositório.

## 📋 Contexto do Workspace
O repositório abriga o site institucional da **CSVET Editora** no domínio **csvet.com.br**. Ele concentra a autoridade da editora (JSON-LD `Organization`, publicadora dos periódicos de journals.csvet.com.br) e liga as plataformas do ecossistema do Prof. Dr. Atilio. Hospedagem compartilhada cPanel, pasta `~/csvet.com.br`, deploy pelo `deploy.sh`.

## 🛠️ Diretrizes de Desenvolvimento e Tecnologia
1.  **Tecnologia Core**: Apenas PHP puro, HTML estruturado, CSS Vanilla e Javascript puro. **Não utilizar frameworks CSS como TailwindCSS** nem instaladores pesados de JS, a menos que expressamente solicitado.
2.  **Organização de Links**: Todas as plataformas e seus respectivos metadados (como cores de destaque, URLs, ícones e descrições) devem permanecer isolados no arquivo [data.php](file:///D:/csvet/data.php). Nunca declare URLs ou dados fixos diretamente no [index.php](file:///D:/csvet/index.php).
3.  **Sistema de Ícones**: Os ícones SVG utilizados nos cartões estão declarados na função `getSvgIcon()` no [index.php](file:///D:/csvet/index.php). Se um novo tipo de ícone for necessário, adicione-o como um caso no `switch` dessa função.
4.  **Identidade Visual**: Mesma marca de csvet.app e journals.csvet.com.br: navy `#000080`/`#000067`, cinza `#c6c6c6`, destaque `#00aaff`, fonte Roboto, logos em `img/`. Não reintroduzir o tema escuro/glassmorphism antigo.
5.  **Estilo & Efeitos**: O estilo utiliza variáveis CSS personalizadas injetadas dinamicamente via PHP (como `--theme-color` e opacidades baseadas em HEX para RGBA). Manter o padrão de sombras suaves e animações baseadas em transição fluida (`cubic-bezier`).

## 🔍 Regras Críticas de SEO (Otimização para Buscas)
1.  **Página Principal**: Deve sempre manter o JSON-LD `@graph` (Organization + WebSite + Periodical), as tags Open Graph, Twitter Cards, dados estruturados JSON-LD e `<link rel="canonical" href="https://csvet.com.br/">` atualizados.
2.  **Links do portal**: Os cartões e o bloco de periódicos apontam DIRETAMENTE para a URL de cada site (links seguíveis, sem `nofollow`), para transferir autoridade. As rotas `?go=` servem só para links curtos compartilhados e levam noindex.
3.  **Redirecionamentos (`?go=...`)**: Devem SEMPRE enviar `noindex, follow` (meta robots e cabeçalho `X-Robots-Tag`) e não têm canonical. Chave desconhecida faz 301 para a home.
4.  **Robots & Sitemap**: O [robots.txt](file:///D:/csvet/robots.txt) NÃO bloqueia as rotas de redirecionamento (o robô precisa acessá-las para ler o noindex). O [sitemap.xml](file:///D:/csvet/sitemap.xml) lista a home, /privacidade e /termos.
5.  **Cache**: o CSS é carregado como `/css/style.css?v=<filemtime>`; use sempre caminhos absolutos (`/css`, `/img`) para funcionar em rotas como `/vetrank/`.

## ⚖️ Regulatório (LGPD / Marco Civil)
1.  **Sem cookies e sem terceiros**: o site não usa cookies, analytics nem Google Fonts (Roboto fica em `fonts/`). Por isso não há banner de cookies. Se algo assim for adicionado, atualize `/privacidade` e avalie consentimento.
2.  **Páginas legais**: `privacidade.php` e `termos.php` (URLs `/privacidade` e `/termos`), montadas com `partials/page-start.php` e `page-end.php`. Os dados do controlador, do encarregado (DPO) e o CNPJ ficam em `$legal` no [data.php](file:///D:/csvet/data.php); ao mudar o texto, atualize `$legal['updated']`.
3.  **Cabeçalho e rodapé** ficam em `partials/` e são compartilhados por todas as páginas. Arquivos novos precisam ser incluídos em `deploy.sh`.

