import { useState } from "react";
import { trpc } from "@/lib/trpc";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { toast } from "sonner";
import { MessageSquarePlus, Loader2, Send } from "lucide-react";

interface Props {
  open: boolean;
  onOpenChange: (v: boolean) => void;
  onCreated?: (ticketId: number) => void;
}

const CATEGORIES = [
  { value: "pagamento",  label: "Pagamento / Assinatura" },
  { value: "acesso",     label: "Problema de Acesso" },
  { value: "bug",        label: "Bug / Erro no Sistema" },
  { value: "conteudo",   label: "Conteúdo / Questões" },
  { value: "sugestao",   label: "Sugestão de Melhoria" },
  { value: "outros",     label: "Outros" },
];

export default function SupportTicketForm({ open, onOpenChange, onCreated }: Props) {
  const [subject, setSubject]   = useState("");
  const [category, setCategory] = useState("outros");
  const [message, setMessage]   = useState("");

  const createMutation = trpc.tickets.create.useMutation({
    onSuccess: ({ ticketId }) => {
      toast.success(`Ticket #${ticketId} criado! Você receberá um e-mail de confirmação.`);
      setSubject(""); setCategory("outros"); setMessage("");
      onOpenChange(false);
      onCreated?.(ticketId);
    },
    onError: e => toast.error(e.message),
  });

  const isValid = subject.trim().length >= 5 && message.trim().length >= 20;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2 font-serif">
            <MessageSquarePlus className="h-5 w-5 text-primary" />
            Abrir Chamado de Suporte
          </DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label className="text-sm">Categoria</Label>
            <Select value={category} onValueChange={setCategory}>
              <SelectTrigger className="h-10">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {CATEGORIES.map(c => (
                  <SelectItem key={c.value} value={c.value}>{c.label}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-1.5">
            <Label className="text-sm">Assunto *</Label>
            <Input
              value={subject}
              onChange={e => setSubject(e.target.value)}
              placeholder="Descreva brevemente o problema ou solicitação"
              className="h-10"
              maxLength={256}
            />
          </div>

          <div className="space-y-1.5">
            <Label className="text-sm">Descrição detalhada *</Label>
            <Textarea
              value={message}
              onChange={e => setMessage(e.target.value)}
              placeholder="Descreva com detalhes o que aconteceu, quando ocorreu, e qualquer informação relevante que nos ajude a resolver rapidamente..."
              rows={5}
              className="resize-none text-sm"
              minLength={20}
            />
            <p className="text-xs text-muted-foreground text-right">{message.length} / mín. 20 caracteres</p>
          </div>

          <div className="rounded-lg bg-muted/30 border border-border/40 p-3 text-xs text-muted-foreground">
            Você receberá uma confirmação por e-mail e será notificado sobre atualizações no ticket.
            Resposta esperada em até <span className="font-medium text-foreground">24–48h úteis</span>.
          </div>

          <Button
            className="w-full gap-2"
            disabled={!isValid || createMutation.isPending}
            onClick={() => createMutation.mutate({ subject, category: category as any, message })}
          >
            {createMutation.isPending
              ? <><Loader2 className="h-4 w-4 animate-spin" /> Enviando...</>
              : <><Send className="h-4 w-4" /> Enviar Chamado</>
            }
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  );
}
