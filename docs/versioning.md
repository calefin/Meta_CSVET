# Versionamento do VetRank

Este documento define como registrar e manter as versões do aplicativo de forma clara e eficiente.

## Arquivos principais

- `package.json` — versão principal da aplicação usada pelo build e pelos pacotes.
- `VERSION` — arquivo simples com a versão atual, útil para scripts e integração contínua.
- `docs/release-history.md` — histórico de lançamentos, onde cada versão recebe notas e data.

## Fluxo recomendado

1. Atualize a versão em `package.json` usando `pnpm version <major|minor|patch>` ou manualmente.
2. Execute `pnpm version:sync` para sincronizar o arquivo `VERSION` com `package.json`.
3. Adicione uma entrada em `docs/release-history.md` com o novo número de versão e um breve resumo das mudanças.
4. Faça commit das alterações de `package.json`, `VERSION` e `docs/release-history.md`.

## Scripts disponíveis

- `pnpm version:check` — valida se `package.json` e `VERSION` estão sincronizados.
- `pnpm version:sync` — atualiza `VERSION` com o valor presente em `package.json`.

## Boas práticas

- Use o mesmo número de versão em `package.json` e `VERSION`.
- Atualize o histórico de versões sempre que houver mudanças relevantes no aplicativo.
- Mantenha o histórico conciso, com data e descrição breve.
