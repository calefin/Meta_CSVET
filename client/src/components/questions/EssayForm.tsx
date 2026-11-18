import React from "react";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Input } from "@/components/ui/input";
import { EssayQuestion } from "@/../../shared/_core/questionTypes";

interface EssayFormProps {
  data: EssayQuestion;
  onChange: (data: EssayQuestion) => void;
}

export function EssayForm({ data, onChange }: EssayFormProps) {
  return (
    <div className="space-y-4">
      <div>
        <Label htmlFor="suggested-answer">Resposta Sugerida (Opcional)</Label>
        <Textarea
          id="suggested-answer"
          placeholder="Digite a resposta esperada ou gabarito"
          value={data.suggestedAnswer || ""}
          onChange={(e) =>
            onChange({
              ...data,
              suggestedAnswer: e.target.value,
            })
          }
          className="min-h-32"
        />
        <p className="text-xs text-gray-500 mt-2">
          Será usada como referência para avaliação
        </p>
      </div>

      <div>
        <Label>Critérios de Avaliação (Um por linha)</Label>
        <Textarea
          placeholder="Ex: Identificar os conceitos principais&#10;Apresentar argumentos coerentes&#10;Utilizar terminologia correta"
          value={data.evaluationCriteria?.join("\n") || ""}
          onChange={(e) =>
            onChange({
              ...data,
              evaluationCriteria: e.target.value
                .split("\n")
                .filter((line) => line.trim()),
            })
          }
          className="min-h-24 font-mono text-sm"
        />
        <p className="text-xs text-gray-500 mt-2">
          Critérios para auxiliar na correção
        </p>
      </div>

      <div>
        <Label htmlFor="lines-count">Quantidade de Linhas no PDF</Label>
        <Input
          id="lines-count"
          type="number"
          min={1}
          max={40}
          value={data.linesCount ?? 5}
          onChange={(e) =>
            onChange({
              ...data,
              linesCount: Math.max(1, Math.min(40, parseInt(e.target.value) || 5)),
            })
          }
          className="w-32 mt-1"
        />
        <p className="text-xs text-gray-500 mt-1.5">
          Define quantas linhas pontilhadas de resposta serão geradas no PDF (Padrão: 5, máximo: 40)
        </p>
      </div>
    </div>
  );
}
