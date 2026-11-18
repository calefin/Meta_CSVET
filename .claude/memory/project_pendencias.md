---
name: project-pendencias
description: Pendências e próximos passos do ProvaGen — atualizar conforme tarefas forem concluídas
metadata: 
  node_type: memory
  type: project
  originSessionId: 5276abbf-99b2-4606-b389-5c4ed5bc5bd1
---

## Pendente — seed de questões de teste

Rodar o script para criar 20 questões de teste em produção (conta ascalefi@gmail.com):

```bash
node scripts/seed-test-data.mjs --cookie VALOR_DO_APP_SESSION_ID
```

Como obter o cookie: DevTools → Application → Cookies → prof-aid.com → `app_session_id`.

**Why:** banco de questões em produção está vazio; sem questões não dá para testar geração de prova com versões e gabarito.

---

## Pendente — integração DB Flutter (sync de notas)

Implementar `POST /api/quizzes/:quizId/sync-papers` no servidor (tRPC ou Express).
Spec completa em `PRODUCTION_INTEGRATION.md` e na memória `project_flutter_integration.md`.

---

## Pendente — validar novo layout do gabarito

Após o seed, gerar uma prova com 2 versões e baixar o gabarito em PDF/PNG para confirmar:
- QR grande visível à esquerda
- URL `www.prof-aid.com` em destaque
- Cabeçalhos a b c d e no topo e base
- Code128 do aluno no rodapé (class pack)
