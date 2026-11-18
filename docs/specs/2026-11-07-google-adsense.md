# Google AdSense nas páginas públicas

**Data:** 2026-11-07

## Objetivo
Habilitar a monetização do site com Google AdSense (Auto Ads), sem anúncios nos
painéis privados, formulários ou PDFs.

## O que foi feito
- `public/ads.txt` — declara o editor autorizado:
  `google.com, pub-8311618945249138, DIRECT, f08c47fec0942fa0`.
- `config/services.php` — nova chave `services.adsense.client`
  (`ADSENSE_CLIENT`, padrão `ca-pub-8311618945249138`). Deixar vazio desliga o
  script em todo o site.
- `resources/views/partials/adsense.blade.php` — `<meta name="google-adsense-account">`
  + script `adsbygoogle.js`. Só renderiza com `APP_ENV=production`, para que o
  ambiente local não gere impressões/cliques inválidos.
- Partial incluída antes do `</head>` das páginas públicas de conteúdo:
  `welcome`, `blog/index`, `blog/show`, `na-midia`, `necropsia`, `normas-abnt`,
  `orientador_profile`.
- Fora de propósito (sem anúncio): `/admin`, `/portal`, formulários
  (agendamento, solicitar necropsia/atendimento, verificador), páginas de
  status, recibos, certificados, e-mails e PDFs.
- `tests/Feature/AdSenseTest.php` — ads.txt, presença em produção, ausência fora dela.

## Importação no prof-aid.com
Copiar a partial e a chave de config; no SaaS o `ADSENSE_CLIENT` deve vir do
`.env` (cada instância terá o seu editor) e o `ads.txt` deve ser gerado por rota
ou por deploy, não versionado com o ID deste site.
