# Memória do Projeto - MedVet Legal

## Visão Geral do Projeto
O **MedVet Legal** é uma plataforma/site para serviços de perícia e consultoria veterinária forense.
- **Repositório:** `medvetlegal_producao` (`D:\medvetlegal_producao`)
- **Aplicação Laravel:** `medvet_app`
- **Diretório Web Público:** `public_html` (o Vite está configurado para buildar diretamente neste diretório)
- **Domínio Canônico:** `https://www.medvetlegal.com.br`

---

## 🕒 Últimas Atualizações do Git (12 de Agosto de 2026)

### 1. Correção Terminológica no Blog ("Envenenamento" -> "Intoxicação")
- **Commit:** `6836940`
- **Descrição:** Alterado o termo "envenenamento" para "intoxicação" no título, resumo e corpo dos artigos do blog.
- **Ajustes:** Corrigida concordância de gênero gramatical ("o envenenamento" -> "a intoxicação").
- **Preservação de SEO:** As **slugs** das URLs foram mantidas intactas para não romper links já indexados pelos buscadores.
- **Mecanismo:** Criada a migration `2026_08_12_000001_replace_envenenamento_with_intoxicacao_in_posts.php` para atualizar o banco em produção, além de atualizar os arquivos-fonte de seeders e migrations anteriores.

### 2. Notificação por E-mail (CC para cliente)
- **Commit:** `329e1c3`
- **Descrição:** Incluído o e-mail `calefi@csvet.com.br` em cópia (CC) na mensagem de confirmação enviada ao cliente após o preenchimento do formulário de contato (anteriormente o CC estava presente apenas no e-mail de notificação interna).

### 3. SEO, Canonical Host e Tratamento de Páginas Legadas
- **Commit:** `41009f3`
- **Canonical Host:** Forçado HTTPS e www (`https://www.medvetlegal.com.br`) via `AppServiceProvider.php` e regras no `.htaccess`.
- **Redirecionamentos:** Páginas PHP legadas (`blog.php`, `artigo-01.php`) foram redirecionadas para as rotas corretas do Laravel.
- **Segurança:** Bloqueado o acesso público direto aos scripts PHP legados `config.php` e `enviar_email.php`.

### 4. Alerta de Sucesso no Envio do Formulário com Rolagem Automática
- **Commit:** `d2a860f`
- **Descrição:** Adicionado aviso de sucesso destacado em verde com ícone de confirmação (`fas fa-check-circle`) e rolagem suave automática para a seção `#contato` após o envio do formulário.

## 🕒 Últimas Atualizações do Git (20 de Agosto de 2026)

### 1. Expansão de SEO para "Medicina Veterinária Legal" e 40 Artigos Científicos do PubMed
- **Otimização On-Page:** Atualizados títulos (`<title>`), meta tags, H1, H2, atributos `alt` de imagens e Schema.org JSON-LD para focar nos termos "Medicina Veterinária Legal", "Veterinária Legal" e "Veterinary Legal Medicine".
- **Artigo Pilar:** Criado o post *Medicina Veterinária Legal: O que é, áreas de atuação e a importância da perícia* (`o-que-e-medicina-veterinaria-legal-areas-atuacao-pericia`) via migration `2026_08_20_000001_insert_medicina_veterinaria_legal_pillar_post.php`.
- **51 Artigos Científicos com Citação PubMed Verificada na API Oficial da NCBI (100% da base):** Criadas as migrations `2026_08_20_000002_insert_twenty_pubmed_forensic_posts.php`, `2026_08_20_000003_insert_second_batch_twenty_pubmed_posts.php`, `2026_08_20_000004_expand_and_enrich_original_foundation_posts.php` e `2026_08_20_000005_fix_all_pubmed_citations_with_verified_real_data.php`. Todos os 51 artigos foram auditados e confrontados individualmente contra os registros oficiais da National Library of Medicine (NCBI PubMed), trazendo artigos clássicos e modernos de patologia forense veterinária (*Veterinary Pathology*, *Forensic Science International*, *Journal of Forensic Sciences*, *Toxics*, *Frontiers in Veterinary Science*), incluindo as publicações científicas reais da própria fundadora **Dra. Adriana de Siqueira** sobre exumação e aldicarbe/chumbinho.
- **Página de Erro 404 Branded:** Criada a view `resources/views/errors/404.blade.php` com navegação para a home, blog e artigos recomendados para evitar rejeição e retenção de rastreadores.
- **Sitemap Atualizado:** [public_html/sitemap.xml](file:///home/calefi/Documentos/Projetos%20Git/medvetlegal_producao/public_html/sitemap.xml) e [medvet_app/public/sitemap.xml](file:///home/calefi/Documentos/Projetos%20Git/medvetlegal_producao/medvet_app/public/sitemap.xml) sincronizados com todas as 53 URLs canônicas do site.

---

## 🛠️ Arquitetura e Decisões Técnicas

- **Build de Assets:** Vite configurado para output em `public_html`.
- **Sitemap & Meta Tags:** O site utiliza sitemap físico estático (`sitemap.xml`) e marcação Open Graph / Twitter Cards em todas as páginas públicas.
- **Redirecionamentos `.htaccess`:** Devem ser mantidos em `public_html/.htaccess` e `medvet_app/public/.htaccess` para garantir coerência entre ambientes de desenvolvimento e produção.
