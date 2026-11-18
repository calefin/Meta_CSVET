---
name: project-flutter-integration
description: Spec de integração do app Flutter ProvaGen com prof-aid.com e DB
metadata: 
  node_type: memory
  type: project
  originSessionId: 5276abbf-99b2-4606-b389-5c4ed5bc5bd1
---

Documentação completa em `PRODUCTION_INTEGRATION.md` (raiz do projeto).

**Arquitetura:**
1. Auth JWT: Flutter → `POST /api/auth/login` → token
2. OMR local: Flutter → servidor Python/OpenCV na rede Wi-Fi → JSON com notas
3. Sync de notas (a implementar): Flutter → `POST /api/quizzes/{id}/sync-papers`

**O que falta implementar para integração DB:**
- Endpoint `POST /api/quizzes/:quizId/sync-papers` autenticado (router tRPC ou Express direto)
- Recebe: `{ papers: [{ student_identifier, grade, correct_count, wrong_count, answers, scanned_at }] }`
- Mapeia `student_identifier` (= Code128 do aluno no gabarito) → `students.studentId`
- Grava em tabela `studentGrades`

**student_id no OMR:** o servidor Python devolve `student_id` lendo o Code128 da barra inferior do gabarito. O valor é o `studentCode` passado ao `generateAnswerSheetSVG` ao gerar class pack (geralmente `externalRef` ou `registrationNumber` do aluno).

**How to apply:** quando o usuário pedir a integração do DB com o Flutter, implementar o endpoint de sync conforme spec acima.
