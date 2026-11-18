import React from "react";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { CheckCircle, AlertCircle } from "lucide-react";
import {
  QuestionContent,
  MultipleChoiceQuestion,
  EssayQuestion,
  AssertionReasonQuestion,
} from "@/../../shared/_core/questionTypes";

interface QuestionPreviewProps {
  statement: string;
  discipline?: string;
  subject?: string;
  difficulty?: string;
  content: QuestionContent;
  images?: string[];
}

export function QuestionPreview({
  statement,
  discipline,
  subject,
  difficulty,
  content,
  images = [],
}: QuestionPreviewProps) {
  return (
    <div className="space-y-4">
      {/* Header */}
      <Card>
        <CardHeader>
          <div className="flex justify-between items-start gap-4">
            <div className="flex-1">
              <CardTitle>{statement}</CardTitle>
              <CardDescription className="mt-2">
                <div className="flex gap-2 flex-wrap">
                  {discipline && <Badge variant="outline">{discipline}</Badge>}
                  {subject && <Badge variant="outline">{subject}</Badge>}
                  {difficulty && (
                    <Badge
                      variant={
                        difficulty === "hard"
                          ? "destructive"
                          : difficulty === "medium"
                            ? "default"
                            : "secondary"
                      }
                    >
                      {difficulty === "easy"
                        ? "Fácil"
                        : difficulty === "medium"
                          ? "Médio"
                          : "Difícil"}
                    </Badge>
                  )}
                </div>
              </CardDescription>
            </div>
          </div>
        </CardHeader>
      </Card>

      {/* Images */}
      {images && images.length > 0 && (
        <div className="grid grid-cols-2 gap-4">
          {images.map((imageUrl, idx) => (
            <div key={idx} className="border rounded-lg overflow-hidden">
              <img
                src={imageUrl}
                alt={`Imagem ${idx + 1}`}
                className="w-full h-48 object-cover"
              />
            </div>
          ))}
        </div>
      )}

      {/* Content by type */}
      {content.type === "multiple_choice" && (
        <MultipleChoicePreview content={content as MultipleChoiceQuestion} />
      )}

      {content.type === "multiple_choice_complex" && (
        <ComplexMultipleChoicePreview
          content={content as unknown as MultipleChoiceQuestion}
        />
      )}

      {content.type === "essay" && (
        <EssayPreview content={content as EssayQuestion} />
      )}

      {content.type === "assertion_reason" && (
        <AssertionReasonPreview
          content={content as AssertionReasonQuestion}
        />
      )}
    </div>
  );
}

function MultipleChoicePreview({
  content,
}: {
  content: MultipleChoiceQuestion;
}) {
  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Múltipla Escolha</CardTitle>
      </CardHeader>
      <CardContent className="space-y-3">
        {Object.entries(content.alternatives).map(([key, value]) => (
          <div
            key={key}
            className={`p-3 border-2 rounded ${
              content.correctAnswer === key
                ? "border-green-500 bg-green-50"
                : "border-gray-200 hover:border-gray-300"
            }`}
          >
            <div className="flex gap-3">
              <div className="font-bold w-6 flex-shrink-0">
                {key.toUpperCase()}){" "}
              </div>
              <div className="flex-1">{value}</div>
              {content.correctAnswer === key && (
                <CheckCircle className="w-5 h-5 text-green-600 flex-shrink-0" />
              )}
            </div>
          </div>
        ))}

        {content.explanation && (
          <Alert className="mt-4">
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>
              <strong>Explicação:</strong> {content.explanation}
            </AlertDescription>
          </Alert>
        )}
      </CardContent>
    </Card>
  );
}

function ComplexMultipleChoicePreview({
  content,
}: {
  content: any;
}) {
  const correctAnswersList = Array.isArray(content.correctAnswers)
    ? content.correctAnswers
    : Array.isArray(content.correctAnswer)
      ? content.correctAnswer
      : typeof content.correctAnswer === "string"
        ? [content.correctAnswer]
        : [];

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">
          Múltipla Escolha - Múltiplas Respostas
        </CardTitle>
      </CardHeader>
      <CardContent className="space-y-3">
        {Object.entries(content.alternatives as Record<string, string>).map(([key, value]) => {
          const isCorrect = correctAnswersList.includes(key);
          return (
            <div
              key={key}
              className={`p-3 border-2 rounded ${
                isCorrect
                  ? "border-green-500 bg-green-50"
                  : "border-gray-200 hover:border-gray-300"
              }`}
            >
              <div className="flex gap-3">
                <div className="font-bold w-6 flex-shrink-0">
                  {key.toUpperCase()}){" "}
                </div>
                <div className="flex-1">{value}</div>
                {isCorrect && (
                  <CheckCircle className="w-5 h-5 text-green-600 flex-shrink-0" />
                )}
              </div>
            </div>
          );
        })}

        {content.explanation && (
          <Alert className="mt-4">
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>
              <strong>Explicação:</strong> {content.explanation}
            </AlertDescription>
          </Alert>
        )}
      </CardContent>
    </Card>
  );
}

function EssayPreview({ content }: { content: EssayQuestion }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Questão Dissertativa</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {content.suggestedAnswer && (
          <div>
            <h4 className="font-semibold text-sm mb-2">Resposta Sugerida:</h4>
            <div className="bg-gray-50 p-3 rounded text-sm whitespace-pre-wrap">
              {content.suggestedAnswer}
            </div>
          </div>
        )}

        {content.evaluationCriteria && content.evaluationCriteria.length > 0 && (
          <div>
            <h4 className="font-semibold text-sm mb-2">Critérios de Avaliação:</h4>
            <ul className="space-y-1">
              {content.evaluationCriteria.map((criterion, idx) => (
                <li key={idx} className="text-sm flex gap-2">
                  <span className="text-gray-400">•</span>
                  <span>{criterion}</span>
                </li>
              ))}
            </ul>
          </div>
        )}
      </CardContent>
    </Card>
  );
}

function AssertionReasonPreview({
  content,
}: {
  content: AssertionReasonQuestion;
}) {
  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Asserção e Razão (I a {content.items.length})</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {content.items.map((item) => (
          <div key={item.id} className="border rounded-lg p-4 space-y-3">
            <div className="flex items-center justify-between">
              <h5 className="font-semibold">Item {item.id}</h5>
              <div className="flex gap-2">
                <Badge
                  variant={item.isTrue ? "default" : "destructive"}
                >
                  {item.isTrue ? "V" : "F"}
                </Badge>
                <Badge
                  variant={item.reasonCorrect ? "default" : "outline"}
                >
                  {item.reasonCorrect ? "Razão Correta" : "Razão Incorreta"}
                </Badge>
              </div>
            </div>

            <div>
              <p className="text-xs text-gray-600 font-semibold mb-1">
                Asserção:
              </p>
              <p className="text-sm">{item.assertion}</p>
            </div>

            <div>
              <p className="text-xs text-gray-600 font-semibold mb-1">Razão:</p>
              <p className="text-sm">{item.reason}</p>
            </div>
          </div>
        ))}

        {content.explanation && (
          <Alert className="mt-4">
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>
              <strong>Explicação:</strong> {content.explanation}
            </AlertDescription>
          </Alert>
        )}
      </CardContent>
    </Card>
  );
}
