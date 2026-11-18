import React, { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Trash2, Plus } from "lucide-react";
import {
  AssertionReasonQuestion,
  ASSERTION_REASON_PRESETS,
} from "@/../../shared/_core/questionTypes";

interface AssertionReasonFormProps {
  data: AssertionReasonQuestion;
  onChange: (data: AssertionReasonQuestion) => void;
}

export function AssertionReasonForm({
  data,
  onChange,
}: AssertionReasonFormProps) {
  const [itemCount, setItemCount] = useState(data.items.length || 4);
  const maxItems = 5;

  const handleAddItem = () => {
    if (itemCount < maxItems) {
      const newItems = [
        ...data.items,
        {
          id: itemCount + 1,
          assertion: "",
          isTrue: true,
          reason: "",
          reasonCorrect: true,
        },
      ];
      onChange({ ...data, items: newItems });
      setItemCount(itemCount + 1);
    }
  };

  const handleRemoveItem = (id: number) => {
    const newItems = data.items
      .filter((item) => item.id !== id)
      .map((item, idx) => ({
        ...item,
        id: idx + 1,
      }));
    onChange({ ...data, items: newItems });
    setItemCount(Math.max(1, itemCount - 1));
  };

  const handleItemChange = (id: number, field: string, value: any) => {
    const newItems = data.items.map((item) =>
      item.id === id ? { ...item, [field]: value } : item
    );
    onChange({ ...data, items: newItems });
  };

  const applyPreset = (presetIndex: number) => {
    const preset = ASSERTION_REASON_PRESETS[presetIndex];
    const newItems = data.items.map((item) =>
      item.id === 1
        ? {
            ...item,
            isTrue: preset.assertion,
            reasonCorrect: preset.reasonCorrect,
          }
        : item
    );
    onChange({ ...data, items: newItems });
  };

  return (
    <div className="space-y-6">
      <div>
        <Label>Estrutura Padrão *</Label>
        <div className="flex gap-2 mt-2">
          <Select onValueChange={(val) => setItemCount(parseInt(val))}>
            <SelectTrigger className="w-32">
              <SelectValue placeholder="Selecionar..." />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="4">I a IV (4 itens)</SelectItem>
              <SelectItem value="5">I a V (5 itens)</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      <div className="bg-blue-50 p-4 rounded-lg border border-blue-200">
        <h4 className="font-semibold mb-3 text-sm">Presets de Padrões Comuns</h4>
        <div className="grid gap-2">
          {ASSERTION_REASON_PRESETS.map((preset, idx) => (
            <Button
              key={idx}
              variant="ghost"
              size="sm"
              className="justify-start text-left h-auto py-2"
              onClick={() => applyPreset(idx)}
            >
              <div>
                <div className="font-medium text-xs">{preset.label}</div>
                <div className="text-xs text-gray-600">{preset.description}</div>
              </div>
            </Button>
          ))}
        </div>
      </div>

      <div className="space-y-4">
        <Label className="block">Itens (I a {Math.min(itemCount, 5)}) *</Label>

        {data.items.map((item) => (
          <Card key={item.id} className="border-l-4 border-l-purple-500">
            <CardHeader className="pb-3">
              <div className="flex justify-between items-center">
                <CardTitle className="text-base">Item {item.id}</CardTitle>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => handleRemoveItem(item.id)}
                >
                  <Trash2 className="w-4 h-4" />
                </Button>
              </div>
            </CardHeader>

            <CardContent className="space-y-4">
              <div>
                <Label htmlFor={`assertion-${item.id}`}>Asserção *</Label>
                <Textarea
                  id={`assertion-${item.id}`}
                  placeholder="Digite a afirmação"
                  value={item.assertion}
                  onChange={(e) =>
                    handleItemChange(item.id, "assertion", e.target.value)
                  }
                  className="min-h-20"
                />
              </div>

              <div className="flex items-center justify-between bg-gray-50 p-3 rounded">
                <span className="text-sm font-medium">A asserção é:</span>
                <div className="flex gap-2">
                  <Badge
                    variant={item.isTrue ? "default" : "outline"}
                    className="cursor-pointer"
                    onClick={() =>
                      handleItemChange(item.id, "isTrue", true)
                    }
                  >
                    Verdadeira
                  </Badge>
                  <Badge
                    variant={!item.isTrue ? "default" : "outline"}
                    className="cursor-pointer"
                    onClick={() =>
                      handleItemChange(item.id, "isTrue", false)
                    }
                  >
                    Falsa
                  </Badge>
                </div>
              </div>

              <div>
                <Label htmlFor={`reason-${item.id}`}>Razão *</Label>
                <Textarea
                  id={`reason-${item.id}`}
                  placeholder="Digite a justificativa"
                  value={item.reason}
                  onChange={(e) =>
                    handleItemChange(item.id, "reason", e.target.value)
                  }
                  className="min-h-20"
                />
              </div>

              <div className="flex items-center justify-between bg-gray-50 p-3 rounded">
                <span className="text-sm font-medium">A razão é:</span>
                <div className="flex gap-2">
                  <Badge
                    variant={item.reasonCorrect ? "default" : "outline"}
                    className="cursor-pointer"
                    onClick={() =>
                      handleItemChange(item.id, "reasonCorrect", true)
                    }
                  >
                    Correta
                  </Badge>
                  <Badge
                    variant={!item.reasonCorrect ? "default" : "outline"}
                    className="cursor-pointer"
                    onClick={() =>
                      handleItemChange(item.id, "reasonCorrect", false)
                    }
                  >
                    Incorreta
                  </Badge>
                </div>
              </div>

              <div className="bg-blue-50 p-2 rounded text-xs">
                <strong>Padrão:</strong>{" "}
                {item.isTrue && item.reasonCorrect
                  ? "V → Verdadeira com Razão Correta"
                  : !item.isTrue && item.reasonCorrect
                    ? "F → Falsa mas Razão Correta"
                    : item.isTrue && !item.reasonCorrect
                      ? "V → Verdadeira mas Razão Não Explica"
                      : "F → Falsa com Razão Incorreta"}
              </div>
            </CardContent>
          </Card>
        ))}

        {itemCount < maxItems && (
          <Button onClick={handleAddItem} variant="outline" className="w-full">
            <Plus className="w-4 h-4 mr-2" /> Adicionar Item
          </Button>
        )}
      </div>

      <div>
        <Label htmlFor="explanation">Explicação Geral (Opcional)</Label>
        <Textarea
          id="explanation"
          placeholder="Explicação geral sobre a questão"
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
