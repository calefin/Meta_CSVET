import { useState, useRef } from "react";
import { trpc } from "@/lib/trpc";
import { useAuth } from "@/_core/hooks/useAuth";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { toast } from "sonner";
import { Camera, Loader2, Pencil, Trash2, User } from "lucide-react";

function formatPhone(v: string) {
  const d = v.replace(/\D/g, "").slice(0, 11);
  if (d.length <= 10) return d.replace(/(\d{2})(\d{4})(\d{0,4})/, "($1) $2-$3");
  return d.replace(/(\d{2})(\d{5})(\d{0,4})/, "($1) $2-$3");
}
function formatCpf(v: string) {
  const d = v.replace(/\D/g, "").slice(0, 11);
  return d.replace(/(\d{3})(\d)/, "$1.$2").replace(/(\d{3})\.(\d{3})(\d)/, "$1.$2.$3").replace(/(\d{3})\.(\d{3})\.(\d{3})(\d)/, "$1.$2.$3-$4");
}

interface Props {
  open: boolean;
  onOpenChange: (v: boolean) => void;
}

export default function ProfileEditForm({ open, onOpenChange }: Props) {
  const { user, refresh } = useAuth();
  const utils = trpc.useUtils();
  const fileRef = useRef<HTMLInputElement>(null);

  const [name, setName] = useState(user?.name ?? "");
  const [phone, setPhone] = useState(user?.phone ?? "");
  const [cpf, setCpf] = useState(user?.cpf ?? "");
  const [uploading, setUploading] = useState(false);

  const updateMutation = trpc.userProfile.update.useMutation({
    onSuccess: () => {
      toast.success("Perfil atualizado!");
      utils.auth.me.invalidate();
      refresh();
      onOpenChange(false);
    },
    onError: e => toast.error(e.message),
  });

  const uploadMutation = trpc.userProfile.uploadAvatar.useMutation({
    onSuccess: () => {
      toast.success("Avatar atualizado!");
      utils.auth.me.invalidate();
      refresh();
    },
    onError: e => toast.error(e.message),
  });

  const removeMutation = trpc.userProfile.removeAvatar.useMutation({
    onSuccess: () => {
      toast.success("Avatar removido.");
      utils.auth.me.invalidate();
      refresh();
    },
    onError: e => toast.error(e.message),
  });

  const handleAvatarFile = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) { toast.error("Imagem muito grande. Máximo: 5 MB"); return; }

    setUploading(true);
    const reader = new FileReader();
    reader.onload = () => {
      const base64 = (reader.result as string).split(",")[1];
      uploadMutation.mutate({ base64, mimeType: file.type }, {
        onSettled: () => setUploading(false),
      });
    };
    reader.readAsDataURL(file);
    e.target.value = "";
  };

  const avatarUrl = (user as any)?.avatarUrl;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle className="font-serif flex items-center gap-2">
            <Pencil className="h-4 w-4 text-primary" /> Editar Perfil
          </DialogTitle>
        </DialogHeader>

        <div className="space-y-5">
          {/* Avatar */}
          <div className="flex items-center gap-4">
            <div className="relative">
              <div className="w-20 h-20 rounded-full border-2 border-primary/30 bg-primary/10 flex items-center justify-center overflow-hidden">
                {avatarUrl ? (
                  <img src={avatarUrl} alt="Avatar" className="w-full h-full object-cover" />
                ) : (
                  <User className="h-8 w-8 text-primary/60" />
                )}
              </div>
              {/* Overlay camera button */}
              <button
                onClick={() => fileRef.current?.click()}
                disabled={uploading || uploadMutation.isPending}
                className="absolute -bottom-1 -right-1 w-7 h-7 rounded-full bg-primary text-primary-foreground flex items-center justify-center hover:bg-primary/90 transition-colors"
              >
                {(uploading || uploadMutation.isPending) ? (
                  <Loader2 className="h-3.5 w-3.5 animate-spin" />
                ) : (
                  <Camera className="h-3.5 w-3.5" />
                )}
              </button>
              <input ref={fileRef} type="file" accept="image/jpeg,image/png,image/webp" className="hidden" onChange={handleAvatarFile} />
            </div>
            <div className="space-y-1">
              <p className="text-sm font-medium">Foto do perfil</p>
              <p className="text-xs text-muted-foreground">JPG, PNG ou WebP • máx. 5 MB</p>
              {avatarUrl && (
                <button
                  onClick={() => removeMutation.mutate()}
                  disabled={removeMutation.isPending}
                  className="text-xs text-destructive hover:text-destructive/80 flex items-center gap-1"
                >
                  <Trash2 className="h-3 w-3" /> Remover foto
                </button>
              )}
            </div>
          </div>

          {/* Nome */}
          <div className="space-y-1.5">
            <Label className="text-sm">Nome completo</Label>
            <Input
              value={name}
              onChange={e => setName(e.target.value)}
              placeholder="Seu nome completo"
              className="h-10"
            />
          </div>

          {/* E-mail (read-only) */}
          <div className="space-y-1.5">
            <Label className="text-sm text-muted-foreground">E-mail</Label>
            <Input value={user?.email ?? "—"} disabled className="h-10 opacity-60" />
            <p className="text-xs text-muted-foreground">O e-mail não pode ser alterado aqui.</p>
          </div>

          {/* Telefone */}
          <div className="space-y-1.5">
            <Label className="text-sm">Telefone / WhatsApp</Label>
            <Input
              value={phone}
              onChange={e => setPhone(formatPhone(e.target.value))}
              placeholder="(11) 99999-9999"
              inputMode="tel"
              className="h-10"
            />
          </div>

          {/* CPF */}
          <div className="space-y-1.5">
            <Label className="text-sm">CPF</Label>
            <Input
              value={cpf}
              onChange={e => setCpf(formatCpf(e.target.value))}
              placeholder="000.000.000-00"
              inputMode="numeric"
              className="h-10"
            />
          </div>

          <Button
            className="w-full"
            onClick={() => updateMutation.mutate({ name: name || undefined, phone: phone || undefined, cpf: cpf || undefined })}
            disabled={updateMutation.isPending}
          >
            {updateMutation.isPending ? <><Loader2 className="h-4 w-4 animate-spin mr-2" />Salvando...</> : "Salvar alterações"}
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  );
}
