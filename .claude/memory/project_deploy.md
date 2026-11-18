---
name: project-deploy
description: Comando de deploy em produção do prof-aid.com
metadata: 
  node_type: memory
  type: project
  originSessionId: 5276abbf-99b2-4606-b389-5c4ed5bc5bd1
---

Deploy via Docker Compose em VPS:

```bash
cd /opt/apps/prof-aid && git pull origin main && docker compose up -d --build prof-aid
```

**Why:** servidor VPS com Docker Compose, diretório da app em `/opt/apps/prof-aid`.
**How to apply:** sempre que o usuário pedir para fazer deploy ou perguntar os códigos de deploy, fornecer esse comando.
