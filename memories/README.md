# Memórias do projeto

Este diretório armazena memórias que o assistente pode carregar e usar ao trabalhar neste repositório.

Escopos:
- `/memories/repo/`: memórias persistentes relacionadas ao repositório (rulings, convenções, decisões de arquitetura). Recomendado para commit no Git.
- `/memories/session/`: memórias temporárias de sessão. Geralmente não são necessárias no repo e podem ser adicionadas a `.gitignore` se preferir não versioná-las.

Formato recomendado:
- Use arquivos Markdown (`.md`).
- Cada arquivo deve conter entradas curtas e objetivas: uma ideia ou fato por linha.
- Mantê-los curtos ajuda o assistente a carregar apenas informações relevantes.

Exemplo rápido:
- Crie `[memories/repo/conventions.md]` com regras de estilo do projeto.

Como adicionar ao Git:

```bash
git add memories/
git commit -m "Add project memories (README + examples)"
```

Se quiser, eu posso criar um arquivo `.gitignore` que exclua `/memories/session/` automaticamente.