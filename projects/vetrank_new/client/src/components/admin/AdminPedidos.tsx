import { useState } from "react";
import { trpc } from "@/lib/trpc";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { toast } from "sonner";
import { Loader2, RefreshCw, Calendar, FileText, CheckCircle, HelpCircle, AlertCircle, PlayCircle } from "lucide-react";

const STATUS_CONFIG = {
  pending: { label: "Pendente", color: "bg-yellow-500/20 text-yellow-400 border-yellow-500/30", icon: <HelpCircle className="h-3 w-3 mr-1" /> },
  in_progress: { label: "Em Progresso", color: "bg-blue-500/20 text-blue-400 border-blue-500/30", icon: <PlayCircle className="h-3 w-3 mr-1" /> },
  completed: { label: "Concluído", color: "bg-emerald-500/20 text-emerald-400 border-emerald-500/30", icon: <CheckCircle className="h-3 w-3 mr-1" /> },
  rejected: { label: "Rejeitado", color: "bg-red-500/20 text-red-400 border-red-500/30", icon: <AlertCircle className="h-3 w-3 mr-1" /> },
};

const REQUEST_TYPE_PT = {
  multiple_choice: "Múltipla Escolha",
  discursive: "Discursiva",
  both: "Ambos",
};

export default function AdminPedidos() {
  const [statusFilter, setStatusFilter] = useState<"all" | "pending" | "in_progress" | "completed" | "rejected">("all");
  const utils = trpc.useUtils();

  const { data: requests, isLoading, refetch } = trpc.contentRequests.adminList.useQuery({
    status: statusFilter,
  });

  const updateStatusMutation = trpc.contentRequests.adminUpdateStatus.useMutation({
    onSuccess: () => {
      toast.success("Status atualizado com sucesso!");
      utils.contentRequests.adminList.invalidate();
    },
    onError: (err) => {
      toast.error(err.message || "Erro ao atualizar status.");
    },
  });

  const handleStatusChange = (id: number, newStatus: any) => {
    updateStatusMutation.mutate({ id, status: newStatus });
  };

  return (
    <Card className="border-border/50 bg-card">
      <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
        <div>
          <CardTitle className="font-serif text-xl">Pedidos de Conteúdo Sob Demanda</CardTitle>
          <p className="text-xs text-muted-foreground mt-1 font-sans">
            Acompanhe e atenda as solicitações de novos temas e questões feitas pelos assinantes.
          </p>
        </div>
        <div className="flex items-center gap-2">
          {/* Status Filter */}
          <Select value={statusFilter} onValueChange={(val: any) => setStatusFilter(val)}>
            <SelectTrigger className="h-9 w-44 font-sans text-xs">
              <SelectValue placeholder="Filtrar por Status" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">Todos os Status</SelectItem>
              <SelectItem value="pending">Pendentes</SelectItem>
              <SelectItem value="in_progress">Em Progresso</SelectItem>
              <SelectItem value="completed">Concluídos</SelectItem>
              <SelectItem value="rejected">Rejeitados</SelectItem>
            </SelectContent>
          </Select>

          <Button variant="outline" size="icon" onClick={() => refetch()} className="h-9 w-9">
            <RefreshCw className="h-4 w-4" />
          </Button>
        </div>
      </CardHeader>
      <CardContent>
        {isLoading ? (
          <div className="py-12 flex justify-center">
            <Loader2 className="h-8 w-8 animate-spin text-primary" />
          </div>
        ) : !requests || requests.length === 0 ? (
          <div className="py-12 text-center text-muted-foreground font-sans">
            Nenhum pedido de conteúdo encontrado para este filtro.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <Table className="font-sans text-sm">
              <TableHeader>
                <TableRow>
                  <TableHead className="w-[180px]">Aluno / Usuário</TableHead>
                  <TableHead className="w-[120px]">Tipo</TableHead>
                  <TableHead>Área / Disciplina / Assunto</TableHead>
                  <TableHead className="max-w-[250px]">Detalhes / Notas</TableHead>
                  <TableHead className="w-[150px]">Status</TableHead>
                  <TableHead className="w-[120px] text-right">Data</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {requests.map((req) => {
                  const statusCfg = STATUS_CONFIG[req.status as keyof typeof STATUS_CONFIG] || {
                    label: req.status,
                    color: "bg-gray-500/20 text-gray-400 border-gray-500/30",
                    icon: null,
                  };

                  return (
                    <TableRow key={req.id} className="hover:bg-muted/10">
                      {/* Aluno/Usuário */}
                      <TableCell className="font-medium">
                        <div className="flex flex-col">
                          <span className="text-foreground">{req.name || "Anonimo"}</span>
                          <span className="text-xs text-muted-foreground">{req.email}</span>
                        </div>
                      </TableCell>

                      {/* Tipo */}
                      <TableCell>
                        <Badge variant="outline" className="border-border/60">
                          {REQUEST_TYPE_PT[req.requestType as keyof typeof REQUEST_TYPE_PT] || req.requestType}
                        </Badge>
                      </TableCell>

                      {/* Hierarquia */}
                      <TableCell>
                        <div className="flex flex-col gap-0.5 text-xs text-muted-foreground">
                          <span className="text-foreground font-medium text-sm">
                            {req.disciplineName || `ID: ${req.disciplineId}`}
                          </span>
                          <span>📚 {req.subjectName || `ID: ${req.subjectId}`}</span>
                          {req.topicName ? (
                            <span>🏷️ {req.topicName}</span>
                          ) : req.subjectTag ? (
                            <span className="text-primary italic">🏷️ {req.subjectTag} (Personalizado)</span>
                          ) : (
                            <span className="italic opacity-60">Nenhum assunto específico</span>
                          )}
                        </div>
                      </TableCell>

                      {/* Detalhes / Observações */}
                      <TableCell className="max-w-[250px] align-top text-xs leading-relaxed text-muted-foreground whitespace-pre-wrap">
                        {req.notes ? (
                          <div className="flex items-start gap-1.5 pt-1">
                            <FileText className="h-3.5 w-3.5 text-muted-foreground shrink-0 mt-0.5" />
                            <span>{req.notes}</span>
                          </div>
                        ) : (
                          <span className="italic opacity-50">Nenhuma observação</span>
                        )}
                      </TableCell>

                      {/* Status Selector */}
                      <TableCell>
                        <Select
                          value={req.status}
                          onValueChange={(val) => handleStatusChange(req.id, val)}
                        >
                          <SelectTrigger className={`h-8 font-sans text-xs w-[140px] border ${statusCfg.color}`}>
                            <div className="flex items-center">
                              {statusCfg.icon}
                              <span>{statusCfg.label}</span>
                            </div>
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="pending">
                              <span className="flex items-center text-yellow-400">
                                <HelpCircle className="h-3 w-3 mr-1.5" /> Pendente
                              </span>
                            </SelectItem>
                            <SelectItem value="in_progress">
                              <span className="flex items-center text-blue-400">
                                <PlayCircle className="h-3 w-3 mr-1.5" /> Em Progresso
                              </span>
                            </SelectItem>
                            <SelectItem value="completed">
                              <span className="flex items-center text-emerald-400">
                                <CheckCircle className="h-3 w-3 mr-1.5" /> Concluído
                              </span>
                            </SelectItem>
                            <SelectItem value="rejected">
                              <span className="flex items-center text-red-400">
                                <AlertCircle className="h-3 w-3 mr-1.5" /> Rejeitado
                              </span>
                            </SelectItem>
                          </SelectContent>
                        </Select>
                      </TableCell>

                      {/* Data */}
                      <TableCell className="text-right text-xs text-muted-foreground">
                        <div className="flex items-center justify-end gap-1">
                          <Calendar className="h-3 w-3" />
                          <span>
                            {new Date(req.createdAt).toLocaleDateString("pt-BR", {
                              day: "2-digit",
                              month: "2-digit",
                              year: "numeric",
                            })}
                          </span>
                        </div>
                      </TableCell>
                    </TableRow>
                  );
                })}
              </TableBody>
            </Table>
          </div>
        )}
      </CardContent>
    </Card>
  );
}
