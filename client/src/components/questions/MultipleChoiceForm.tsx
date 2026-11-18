import React, { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { X, Plus } from "lucide-react";
import { MultipleChoiceQuestion } from "@/../../shared/_core/questionTypes";

interface MultipleChoiceFormProps {
  data: MultipleChoiceQuestion;
  onChange: (data: MultipleChoiceQuestion) => void;
  showComplexOptions?: boolean;
}

export function MultipleChoiceForm({
  data,
  onChange,
  showComplexOptions = false,
}: MultipleChoiceFormProps) {
  const [showAlt5, setShowAlt5] = useState(!!data.alternatives.e);

  const handleAlternativeChange = (
    key: "a" | "b" | "c" | "d" | "e",
    value: string
  ) => {
    onChange({
      ...data,
      alternatives: {
        ...data.alternatives,
        [key]: value,
      },
    });
  };

  const toggleAlternative5 = () => {
    if (showAlt5) {
      const { e, ...rest } = data.alternatives;
      onChange({
        ...data,
        alternatives: { ...rest, e: "" },
        correctAnswer:
          data.correctAnswer === "e"
            ? ("a" as any)
            : data.correctAnswer,
      });
    } else {
      onChange({
        ...data,
        alternatives: {
          ...data.alternatives,
          e: "",
        },
      });
    }
    setShowAlt5(!showAlt5);
  };

  return (
    <div className="space-y-4">
      <div className="grid gap-3">
        <Label>Alternativas</Label>

        {["a", "b", "c", "d", ...(showAlt5 ? ["e"] : [])].map((letter) => (
          <div key={letter} className="flex gap-2 items-start">
            <Badge className="mt-2.5 min-w-fit">{letter.toUpperCase()})</Badge>
            <Textarea
              placeholder={`Alternativa ${letter.toUpperCase()}`}
              value={data.alternatives[letter as keyof typeof data.alternatives]}
              onChange={(e) =>
                handleAlternativeChange(letter as any, e.target.value)
              }
              className="flex-1 min-h-20"
            />
          </div>
        ))}

        {!showAlt5 && (
          <Button
            variant="outline"
            size="sm"
            onClick={toggleAlternative5}
            className="w-fit"
          >
            <Plus className="w-4 h-4 mr-1" /> Adicionar Alternativa E
          </Button>
        )}

        {showAlt5 && (
          <Button
            variant="outline"
            size="sm"
            onClick={toggleAlternative5}
            className="w-fit"
          >
            <X className="w-4 h-4 mr-1" /> Remover Alternativa E
          </Button>
        )}
      </div>

      <div>
        <Label htmlFor="correct-answer">Resposta Correta *</Label>
        {showComplexOptions ? (
          <div className="flex gap-2 mt-1.5">
            {["a", "b", "c", "d", ...(showAlt5 ? ["e"] : [])].map((letter) => {
              const correctAnswers = (data as any).correctAnswers || (data.correctAnswer ? [data.correctAnswer] : []);
              const isSelected = correctAnswers.includes(letter);
              return (
                <Button
                  key={letter}
                  type="button"
                  variant={isSelected ? "default" : "outline"}
                  onClick={() => {
                    const newAnswers = isSelected
                      ? correctAnswers.filter((a: string) => a !== letter)
                      : [...correctAnswers, letter];
                    onChange({
                      ...data,
                      correctAnswers: newAnswers as any,
                      correctAnswer: newAnswers[0] as any || "a",
                    } as any);
                  }}
                  className="w-10 h-10 font-bold"
                >
                  {letter.toUpperCase()}
                </Button>
              );
            })}
          </div>
        ) : (
          <Select
            value={data.correctAnswer}
            onValueChange={(value) =>
              onChange({
                ...data,
                correctAnswer: value as any,
              })
            }
          >
            <SelectTrigger id="correct-answer">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {["a", "b", "c", "d", ...(showAlt5 ? ["e"] : [])].map((letter) => (
                <SelectItem key={letter} value={letter}>
                  {letter.toUpperCase()}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        )}
      </div>

      <div>
        <Label htmlFor="explanation">Explicação (Opcional)</Label>
        <Textarea
          id="explanation"
          placeholder="Explicação para a resposta correta"
          value={data.explanation || ""}
          onChange={(e) =>
            onChange({
              ...data,
              explanation: e.target.value,
            })
          }
          className="min-h-24"
        />
      </div>
    </div>
  );
}
