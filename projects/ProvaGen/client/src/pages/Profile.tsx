import { useAuth } from "@/_core/hooks/useAuth";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Loader2, Upload, Check } from "lucide-react";
import { useEffect, useState } from "react";
import { trpc } from "@/lib/trpc";
import { useLocation } from "wouter"; // useLocation returns [pathname, navigate]
import { toast } from "sonner";

export default function Profile() {
  const { user, loading: authLoading } = useAuth();
  const [, navigate] = useLocation();
  const [phone, setPhone] = useState("");
  const [university, setUniversity] = useState("");
  const [logoPreview, setLogoPreview] = useState<string | null>(null);
  const [logoFile, setLogoFile] = useState<File | null>(null);
  const [isSaving, setIsSaving] = useState(false);
  const [isUploadingLogo, setIsUploadingLogo] = useState(false);

  const profileQuery = trpc.profile.me.useQuery(undefined, {
    enabled: !authLoading && !!user,
  });

  const updateProfileMutation = trpc.profile.update.useMutation();
  const uploadLogoMutation = trpc.profile.uploadLogo.useMutation();

  useEffect(() => {
    if (!authLoading && !user) {
      navigate("/");
    }
  }, [authLoading, user]);

  useEffect(() => {
    if (profileQuery.data?.profile) {
      setPhone(profileQuery.data.profile.phone || "");
      setUniversity(profileQuery.data.profile.university || "");
      if (profileQuery.data.profile.logoUrl) {
        setLogoPreview(profileQuery.data.profile.logoUrl);
      }
    }
  }, [profileQuery.data]);

  const handleLogoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      setLogoFile(file);
      const reader = new FileReader();
      reader.onload = (event) => {
        setLogoPreview(event.target?.result as string);
      };
      reader.readAsDataURL(file);
    }
  };

  const handleUploadLogo = async () => {
    if (!logoFile) return;

    setIsUploadingLogo(true);
    try {
      const reader = new FileReader();
      reader.onload = async (event) => {
        const base64 = (event.target?.result as string).split(",")[1];
        const result = await uploadLogoMutation.mutateAsync({
          fileName: logoFile.name,
          fileData: base64,
          mimeType: logoFile.type,
        });

        toast.success("Logotipo enviado com sucesso!");
        setLogoFile(null);
        profileQuery.refetch();
      };
      reader.readAsDataURL(logoFile);
    } catch (error) {
      toast.error("Erro ao fazer upload do logotipo");
      console.error(error);
    } finally {
      setIsUploadingLogo(false);
    }
  };

  const handleSaveProfile = async () => {
    setIsSaving(true);
    try {
      await updateProfileMutation.mutateAsync({
        phone: phone || undefined,
        university: university || undefined,
      });
      toast.success("Perfil atualizado com sucesso!");
    } catch (error) {
      toast.error("Erro ao atualizar perfil");
      console.error(error);
    } finally {
      setIsSaving(false);
    }
  };

  if (authLoading || profileQuery.isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <Loader2 className="animate-spin w-8 h-8" />
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 py-12 px-4">
      <div className="max-w-2xl mx-auto">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-4xl font-bold text-slate-900 mb-2">Meu Perfil</h1>
          <p className="text-slate-600">Gerencie suas informações pessoais e institucionais</p>
        </div>

        {/* User Info Card */}
        <Card className="mb-6 border-0 shadow-lg">
          <CardHeader className="bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-t-lg">
            <CardTitle>Informações da Conta</CardTitle>
            <CardDescription className="text-blue-100">
              Dados de autenticação e contato
            </CardDescription>
          </CardHeader>
          <CardContent className="pt-6">
            <div className="space-y-4">
              <div>
                <Label className="text-slate-700 font-semibold">Email</Label>
                <p className="text-slate-900 font-medium">{user?.email}</p>
              </div>
              <div>
                <Label className="text-slate-700 font-semibold">Nome</Label>
                <p className="text-slate-900 font-medium">{user?.name}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Institution Info Card */}
        <Card className="mb-6 border-0 shadow-lg">
          <CardHeader className="bg-gradient-to-r from-purple-600 to-purple-700 text-white rounded-t-lg">
            <CardTitle>Informações Institucionais</CardTitle>
            <CardDescription className="text-purple-100">
              Dados da sua universidade e logotipo
            </CardDescription>
          </CardHeader>
          <CardContent className="pt-6">
            <div className="space-y-6">
              {/* Phone */}
              <div>
                <Label htmlFor="phone" className="text-slate-700 font-semibold">
                  Telefone
                </Label>
                <Input
                  id="phone"
                  type="tel"
                  placeholder="(11) 99999-9999"
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  className="mt-2 border-slate-200"
                />
              </div>

              {/* University */}
              <div>
                <Label htmlFor="university" className="text-slate-700 font-semibold">
                  Universidade
                </Label>
                <Input
                  id="university"
                  placeholder="Universidade Federal de São Paulo"
                  value={university}
                  onChange={(e) => setUniversity(e.target.value)}
                  className="mt-2 border-slate-200"
                />
              </div>

              {/* Logo Upload */}
              <div className="border-t pt-6">
                <Label className="text-slate-700 font-semibold block mb-4">
                  Logotipo da Instituição
                </Label>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  {/* Preview */}
                  <div className="flex flex-col items-center justify-center p-6 border-2 border-dashed border-slate-300 rounded-lg bg-slate-50">
                    {logoPreview ? (
                      <div className="text-center">
                        <img
                          src={logoPreview}
                          alt="Logo preview"
                          className="h-32 w-32 object-contain mx-auto mb-3"
                        />
                        <p className="text-sm text-slate-600">Visualização do logotipo</p>
                      </div>
                    ) : (
                      <div className="text-center">
                        <Upload className="w-12 h-12 text-slate-400 mx-auto mb-2" />
                        <p className="text-sm text-slate-600">Nenhum logotipo enviado</p>
                      </div>
                    )}
                  </div>

                  {/* Upload */}
                  <div className="flex flex-col justify-center">
                    <Input
                      id="logo"
                      type="file"
                      accept="image/*"
                      onChange={handleLogoChange}
                      className="mb-4 border-slate-200"
                    />
                    <Button
                      onClick={handleUploadLogo}
                      disabled={!logoFile || isUploadingLogo}
                      className="bg-purple-600 hover:bg-purple-700 text-white"
                    >
                      {isUploadingLogo ? (
                        <>
                          <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                          Enviando...
                        </>
                      ) : (
                        <>
                          <Upload className="w-4 h-4 mr-2" />
                          Fazer Upload
                        </>
                      )}
                    </Button>
                    <p className="text-xs text-slate-500 mt-2">
                      Formatos aceitos: PNG, JPG, GIF (máx. 5MB)
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Save Button */}
        <div className="flex gap-3">
          <Button
            onClick={handleSaveProfile}
            disabled={isSaving}
            className="flex-1 bg-blue-600 hover:bg-blue-700 text-white h-11 text-base font-semibold"
          >
            {isSaving ? (
              <>
                <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                Salvando...
              </>
            ) : (
              <>
                <Check className="w-4 h-4 mr-2" />
                Salvar Alterações
              </>
            )}
          </Button>
        </div>
      </div>
    </div>
  );
}
