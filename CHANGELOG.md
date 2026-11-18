# Changelog

Todas as mudanças relevantes do BagParser. Formato: [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/),
versões no padrão [SemVer](https://semver.org/lang/pt-BR/) (MAJOR.MINOR.PATCH).
A versão aparece no rodapé do site e em `GET /api/health`. Ao lançar: atualize esta lista, a versão em
`frontend/package.json` e `backend/package.json`, faça o commit e crie a tag `vX.Y.Z`.

Documentos legais têm versão própria (ver `frontend/src/legal.ts`); quando mudarem de forma relevante,
suba `LEGAL_VERSION` — os usuários aceitam a nova versão no próximo login.

## [Não lançado]
### Adicionado
- Weekly tasks por personagem, com "onde dropa melhor" (densidade × chance de drop do TibiaWiki) e "onde caçar" (D-37).
- Grade de loot com sprites dos itens e sprites nas tabelas de venda (D-35).
- SEO: HTML por página com metatags, sitemap.xml, robots.txt, hreflang e JSON-LD (D-36).
- Licença própria (`LICENSE`) e avisos de terceiros (`NOTICE.md`); cláusula de propriedade intelectual nos Termos.

### Corrigido
- Criaturas marcadas como "bounty semanal" para todos: a weekly é de cada personagem (D-34).
- Moedas não aparecem mais como item para vender; só como total no resumo (D-34).
- Fim da rodada do Drome agora respeita o horário de verão de Berlim.

## [0.2.0] - 2026-11-04
### Adicionado
- Contas de usuário: cadastro, login, sessão segura, perfil, troca de senha, exclusão e exportação de dados (D-24).
- Painel de administração: usuários, Premium, papéis, ban, link de nova senha, auditoria (D-26).
- Contador de visitas anônimo e relatório mensal para o programa de fansites (D-27).
- Página Sobre com contato e transparência de monetização; Política de Privacidade e Termos de Uso versionados (D-27, D-32).
- Acompanhamento de personagens: verificação por código no comentário, histórico, mortes, metas com previsão, gráficos e sugestões (D-28).
- Página inicial com "Hoje no Tibia": server save, Rashid, Yasir informado pela comunidade, boosted, boss da Dream Scar por mundo e fim da rodada do Drome (D-30).
- Botões de compartilhar (WhatsApp, Telegram, nativo, copiar link) (D-31).
- Identidade visual: logo original, favicon, ícones do app, imagem de compartilhamento e tema claro (D-29).

### Alterado
- O parser de loot mudou de `/` para `/parser`.

## [0.1.0] - 2026-11-04
### Adicionado
- Parser do Hunt/Party Hunt Analyser, destinos de venda por NPC/cidade, roteiro de venda, divisão de party.
- SpawnRank, BossLedger, QuickHunts, integração TibiaData, PWA offline, 4 idiomas.
- Produção em https://bagparser.com.

[Não lançado]: https://github.com/calefin/bagparser/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/calefin/bagparser/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/calefin/bagparser/releases/tag/v0.1.0
