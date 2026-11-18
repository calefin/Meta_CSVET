import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from "@/components/ui/dialog";
import { toast } from "sonner";
import { Copy, Download, Instagram, Link2, Share2 } from "lucide-react";

function WhatsAppIcon({ className = "h-4 w-4" }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" className={className} fill="currentColor" aria-hidden="true">
      <path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.64.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.6.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.88 1.21 3.08c.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.22 1.36.19 1.87.12.57-.09 1.76-.72 2-1.41.25-.7.25-1.29.17-1.41-.07-.12-.27-.2-.57-.35M12.05 21.5h-.01a9.4 9.4 0 0 1-4.8-1.32l-.34-.2-3.56.93.95-3.47-.22-.36a9.4 9.4 0 0 1-1.44-5.02c0-5.2 4.23-9.42 9.43-9.42 2.52 0 4.88.98 6.66 2.76a9.36 9.36 0 0 1 2.76 6.67c0 5.2-4.23 9.43-9.43 9.43m8.02-17.45A11.3 11.3 0 0 0 12.05.72C5.8.72.72 5.8.72 12.05c0 2 .52 3.95 1.52 5.67L.62 23.28l5.69-1.49a11.3 11.3 0 0 0 5.74 1.46h.01c6.25 0 11.33-5.08 11.33-11.33 0-3.03-1.18-5.87-3.32-8.01" />
    </svg>
  );
}

/**
 * Botões de compartilhamento:
 *  - WhatsApp (principal canal dos alunos)
 *  - Kit Instagram (Story 1080×1920 + Feed 1080×1080 + Legenda com hashtags)
 *  - Compartilhar nativo (mobile) e copiar link.
 */
export default function ShareButtons({
  url,
  text,
  compact = false,
  storyImageUrl,
  feedImageUrl,
  instaCaption,
}: {
  url: string;
  text: string;
  compact?: boolean;
  storyImageUrl?: string;
  feedImageUrl?: string;
  instaCaption?: string;
}) {
  const [instaOpen, setInstaOpen] = useState(false);
  const [formato, setFormato] = useState<"story" | "feed">("story");
  const full = url.startsWith("http") ? url : `https://www.vetrank.com.br${url}`;
  const wa = `https://wa.me/?text=${encodeURIComponent(`${text}\n${full}`)}`;
  const canNative = typeof navigator !== "undefined" && !!(navigator as any).share;

  const captionFinal =
    instaCaption ??
    `${text}\n\n📄 Baixe o Guia Verticalizado do Edital (PDF) e treine com Simulado Exclusivo em:\n👉 ${full}\n\n#medicinaveterinaria #zootecnia #concursoveterinario #concursozootecnista #residenciaveterinaria #vetrank #medvet`;

  const copy = async () => {
    try { await navigator.clipboard.writeText(full); toast.success("Link copiado!"); }
    catch { toast.error("Não foi possível copiar."); }
  };

  const copyCaption = async () => {
    try { await navigator.clipboard.writeText(captionFinal); toast.success("Legenda copiada para o Instagram!"); }
    catch { toast.error("Não foi possível copiar a legenda."); }
  };

  const native = async () => {
    try { await (navigator as any).share({ title: text, text, url: full }); } catch { /* cancelado */ }
  };

  const shareImageMobile = async (imgUrl: string, fileName: string) => {
    try {
      const r = await fetch(imgUrl);
      const blob = await r.blob();
      const file = new File([blob], fileName, { type: "image/png" });
      if ((navigator as any).canShare && (navigator as any).canShare({ files: [file] })) {
        await (navigator as any).share({
          files: [file],
          title: text,
          text: captionFinal,
        });
        return;
      }
    } catch {
      // fallback to download
    }
    const a = document.createElement("a");
    a.href = imgUrl;
    a.download = fileName;
    a.click();
    toast.success("Imagem baixada! Agora é só postar no Instagram.");
  };

  const currentImg = formato === "story" ? storyImageUrl : (feedImageUrl ?? storyImageUrl);

  return (
    <>
      <div className="flex flex-wrap items-center gap-2">
        <a href={wa} target="_blank" rel="noopener noreferrer">
          <Button size={compact ? "sm" : "default"} className="gap-1.5 bg-[#25D366] hover:bg-[#1ebe5b] text-white">
            <WhatsAppIcon /> {compact ? "WhatsApp" : "Enviar no WhatsApp"}
          </Button>
        </a>

        {storyImageUrl && (
          <Button
            size={compact ? "sm" : "default"}
            variant="outline"
            onClick={() => setInstaOpen(true)}
            className="gap-1.5 border-pink-500/40 text-pink-400 hover:bg-pink-500/10"
          >
            <Instagram className="h-4 w-4" /> {compact ? "Instagram" : "Postar no Instagram"}
          </Button>
        )}

        {canNative && (
          <Button size={compact ? "sm" : "default"} variant="outline" onClick={native} className="gap-1.5">
            <Share2 className="h-4 w-4" /> Compartilhar
          </Button>
        )}

        <Button size={compact ? "sm" : "default"} variant="ghost" onClick={copy} className="gap-1.5">
          <Link2 className="h-4 w-4" /> Copiar link
        </Button>
      </div>

      {storyImageUrl && (
        <Dialog open={instaOpen} onOpenChange={setInstaOpen}>
          <DialogContent className="max-w-lg">
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2">
                <Instagram className="h-5 w-5 text-pink-400" /> Kit para Instagram (Story & Feed)
              </DialogTitle>
              <DialogDescription>
                Baixe a arte pronta (gerada automaticamente pelo VetRank) e copie a legenda com hashtags para divulgar no Instagram ou grupos.
              </DialogDescription>
            </DialogHeader>

            <div className="space-y-4">
              {feedImageUrl && (
                <div className="flex gap-2">
                  <Button
                    size="sm"
                    variant={formato === "story" ? "default" : "outline"}
                    onClick={() => setFormato("story")}
                    className="flex-1"
                  >
                    Story / Reels (1080×1920)
                  </Button>
                  <Button
                    size="sm"
                    variant={formato === "feed" ? "default" : "outline"}
                    onClick={() => setFormato("feed")}
                    className="flex-1"
                  >
                    Post Feed (1080×1080)
                  </Button>
                </div>
              )}

              {currentImg && (
                <div className="rounded-xl border border-border/60 bg-black/40 p-3 flex justify-center">
                  <img
                    src={currentImg}
                    alt="Prévia do Card para Instagram"
                    className={formato === "story" ? "max-h-72 w-auto rounded-lg object-contain" : "max-h-64 w-auto rounded-lg object-contain"}
                  />
                </div>
              )}

              <div className="flex flex-wrap gap-2">
                {currentImg && (
                  <>
                    <a href={currentImg} download={`vetrank-${formato}.png`} className="flex-1">
                      <Button className="w-full gap-1.5">
                        <Download className="h-4 w-4" /> Baixar Arte ({formato === "story" ? "Story" : "Feed"})
                      </Button>
                    </a>
                    {canNative && (
                      <Button
                        variant="outline"
                        onClick={() => shareImageMobile(currentImg, `vetrank-${formato}.png`)}
                        className="gap-1.5"
                      >
                        <Share2 className="h-4 w-4" /> Abrir no App
                      </Button>
                    )}
                  </>
                )}
              </div>

              <div className="space-y-1.5">
                <div className="flex items-center justify-between text-xs text-muted-foreground">
                  <span>Legenda pronta com hashtags:</span>
                  <Button size="sm" variant="ghost" onClick={copyCaption} className="h-7 gap-1 text-xs text-primary">
                    <Copy className="h-3.5 w-3.5" /> Copiar legenda
                  </Button>
                </div>
                <textarea
                  readOnly
                  value={captionFinal}
                  rows={4}
                  className="w-full rounded-lg border border-border/60 bg-card/60 p-2.5 text-xs text-muted-foreground font-sans resize-none"
                />
              </div>
            </div>
          </DialogContent>
        </Dialog>
      )}
    </>
  );
}
