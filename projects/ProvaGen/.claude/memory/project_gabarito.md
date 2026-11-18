---
name: project-gabarito-redesign
description: "Redesign do gabarito baseado no modelo GradePen — QR grande à esquerda, URL visível, cabeçalhos de letra"
metadata: 
  node_type: memory
  type: project
  originSessionId: 5276abbf-99b2-4606-b389-5c4ed5bc5bd1
---

Referência de qualidade: `C:\Users\ascal\OneDrive\Aulas\1 Antigos\2022.1\Exam-650221.pdf` (GradePen, Universidade Cruzeiro do Sul).

**Por que:** usuário insatisfeito com qualidade visual do gabarito anterior. O GradePen é considerado o padrão ideal.

**Mudanças implementadas em `client/src/lib/answerSheetSvg.ts`:**
- QR code grande (100pt sem grid de matrícula, 65pt com grid) à ESQUERDA das bolhas
- QR codifica `https://prof-aid.com/r/{formId}` para alunos verificarem respostas
- Texto "Prova: {formId}" abaixo do QR
- URL `www.prof-aid.com` na barra superior: 9pt, bold, preto (era 7.5pt, cinza)
- Cabeçalho de letras (a b c d e) no TOPO e na BASE da grade de bolhas
- QR minúsculo do rodapé REMOVIDO (substituído pelo grande)
- Code128 do aluno (class packs) mantido no rodapé, alinhado à direita

**Espelhado em:** `client/src/components/answerSheets/AnswerSheetPreview.tsx`

**How to apply:** quando falar de gabarito, lembrar que o QR é para o aluno verificar respostas; o Code128 é para o OMR machine-scan.
