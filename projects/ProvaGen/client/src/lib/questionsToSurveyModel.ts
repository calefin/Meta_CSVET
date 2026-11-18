/**
 * Converts Prof-Aid exam questions to a SurveyJS JSON model.
 *
 * Each question becomes its own page so the student sees one question at a time.
 * MC questions use "radiogroup" with letter values (A/B/C…) so submitAnswers
 * receives the letter directly.
 * Essay/open questions use "comment" (multi-line text).
 */

export interface SurveyQuestion {
  id: number;
  type: string;
  statement: string;
  alternatives?: string | null;
  points?: string | null;
}

export function questionsToSurveyModel(examName: string, questions: SurveyQuestion[]) {
  const pages = questions.map((q, idx) => {
    const questionNumber = idx + 1;
    const pts = q.points ? ` <span style="font-weight:normal;font-size:0.85em;color:#6b7280">(${parseFloat(q.points).toFixed(2)} pts)</span>` : "";
    const title = `${questionNumber}. ${q.statement}${pts}`;

    const isMultipleChoice =
      q.type === "multiple_choice" || q.type === "multiple_choice_complex";

    if (isMultipleChoice) {
      let alternatives: string[] = [];
      try {
        if (q.alternatives) {
          const parsed = JSON.parse(q.alternatives);
          alternatives = Array.isArray(parsed) ? parsed : [];
        }
      } catch {
        alternatives = [];
      }

      return {
        name: `page_${q.id}`,
        elements: [
          {
            type: "radiogroup",
            name: `q_${q.id}`,
            title,
            titleLocation: "left",
            isRequired: false,
            colCount: 1,
            choices: alternatives.map((alt, i) => ({
              value: String.fromCharCode(65 + i),
              text: alt,
            })),
          },
        ],
      };
    }

    // essay / assertion_reason / other
    return {
      name: `page_${q.id}`,
      elements: [
        {
          type: "comment",
          name: `q_${q.id}`,
          title,
          titleLocation: "left",
          isRequired: false,
          rows: 5,
          placeholder: "Digite sua resposta aqui...",
        },
      ],
    };
  });

  return {
    title: examName,
    showTitle: false,
    showQuestionNumbers: "off",
    showProgressBar: "top",
    progressBarType: "questions",
    goNextPageAutomatic: false,
    showNavigationButtons: "bottom",
    pages,
    pageNextText: "Próxima",
    pagePrevText: "Anterior",
    completeText: "Enviar Respostas",
    completedHtml: "<p>Aguarde...</p>",
  };
}

/** Extracts answers from SurveyJS result data into the submitAnswers format. */
export function surveyDataToAnswers(data: Record<string, unknown>): { questionId: number; answer: string }[] {
  return Object.entries(data)
    .filter(([key]) => key.startsWith("q_"))
    .map(([key, value]) => ({
      questionId: Number(key.replace("q_", "")),
      answer: String(value ?? ""),
    }));
}
