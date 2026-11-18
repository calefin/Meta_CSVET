# Registro de Versões (Changelog) - ProvaGen Cell

Todos os marcos de compilação, incrementos de build e atualizações funcionais do aplicativo móvel ProvaGen Cell estão listados abaixo.

---

## [1.0.0+1] - 2026-06-01
### Adicionado
*   **Aprimoramento OpenCV para Impressões Ruins**:
    *   Filtro CLAHE adaptativo para contraste e neutralização de sombras locais.
    *   Morphological Closing para conectar bolhas fracamente preenchidas.
    *   Morphological Opening para remover ruído ou falhas de toner seco nas folhas.
*   **Leitura de QR Code / Código de Barras**:
    *   Detecção automatizada de QR Codes contendo o RM (Registro de Matrícula) dos estudantes.
    *   Desenho de polígono marcador na cor magenta no overlay final identificando o código detectado.
*   **Registro Visual (Foto de Baixa Resolução)**:
    *   Armazenamento do arquivo de gabarito processado com círculos corretos/errados no diretório do aplicativo.
    *   Visualização ampliada interativa (zoom/pinch) da folha corrigida direto na tela de revisão.
*   **Recálculo Automático de Notas**:
    *   Recálculo instantâneo em cadeia de todas as provas salvas quando as chaves de gabarito ou pesos de questões são alterados.
    *   Suporte às opções de anulação com redistribuição proporcional de pontos ou concessão de pontuação geral para todos os alunos da turma.
*   **Localização Multilíngue (pt, en, es)**:
    *   Estrutura de dicionários de internacionalização em tempo real.
    *   Seletor visual de idioma na aba de configurações do corretor.
    *   Reconstrução reativa automática do layout principal ao mudar de idioma.

### Corrigido
*   **Exceção de Runtime no ListTile**:
    *   Correção de erro onde os ink splashes eram bloqueados por containers decorados com cores de fundo sólidas (`account_tab.dart` e `classes_tab.dart`). Envolvidos em `Material` transparente.
*   **Cálculo Dinâmico de Médias**:
    *   Substituição das notas de corte fixas de 10.0 para limites escaláveis baseados nos pontos reais definidos em `quiz.totalPoints`.
