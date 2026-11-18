Meta tag templates

Use these templates in `client/index.html` or per-page meta components.

Title:
- {pageTitle} | ProvaGen

Description:
- Breve descrição do objetivo da página. Máx 160 caracteres.

Open Graph (OG):
- `<meta property="og:title" content="{pageTitle} | ProvaGen" />`
- `<meta property="og:description" content="{description}" />`
- `<meta property="og:url" content="{siteBase}{path}" />`
- `<meta property="og:image" content="{siteBase}/og-image.png" />`

Twitter:
- `<meta name="twitter:card" content="summary_large_image" />`
- `<meta name="twitter:title" content="{pageTitle} | ProvaGen" />`
- `<meta name="twitter:description" content="{description}" />`
- `<meta name="twitter:image" content="{siteBase}/og-image.png" />`

Canonical link:
- `<link rel="canonical" href="{siteBase}{path}" />`
