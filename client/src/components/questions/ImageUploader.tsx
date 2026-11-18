import React, { useRef, useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Progress } from "@/components/ui/progress";
import { Upload, X, AlertTriangle, Loader2 } from "lucide-react";
import { formatStorageSize } from "@/../../shared/_core/storageTypes";
import { compressImages, formatFileSize, estimateCompressedSize } from "@/lib/imageOptimization";

interface ImageUploaderProps {
  maxFiles?: number;
  maxFileSizeMB?: number;
  onImagesSelected: (files: File[], base64Array: string[]) => void;
  selectedCount?: number;
  usedStorageMB?: number;
  quotaLimitMB?: number;
}

export function ImageUploader({
  maxFiles = 5,
  maxFileSizeMB = 10,
  onImagesSelected,
  selectedCount = 0,
  usedStorageMB = 0,
  quotaLimitMB = 100,
}: ImageUploaderProps) {
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [selectedFiles, setSelectedFiles] = useState<File[]>([]);
  const [previews, setPreviews] = useState<string[]>([]);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState<string>("");
  const [compressing, setCompressing] = useState(false);
  const remainingMB = quotaLimitMB - usedStorageMB;

  const handleFileSelect = async (
    e: React.ChangeEvent<HTMLInputElement>
  ) => {
    setError("");
    const files = Array.from(e.target.files || []);

    if (files.length + selectedCount > maxFiles) {
      setError(
        `Máximo ${maxFiles} imagens permitidas. Você já tem ${selectedCount}.`
      );
      return;
    }

    let validFiles: File[] = [];
    const newPreviews: string[] = [];

    for (const file of files) {
      // Validate type
      if (!file.type.startsWith("image/")) {
        setError(`${file.name} não é uma imagem válida`);
        continue;
      }

      // Validate size
      const fileSizeMB = file.size / 1024 / 1024;
      if (fileSizeMB > maxFileSizeMB) {
        setError(
          `${file.name} é muito grande (${fileSizeMB.toFixed(1)}MB > ${maxFileSizeMB}MB)`
        );
        continue;
      }

      validFiles.push(file);

      // Create preview
      const reader = new FileReader();
      reader.onload = (e) => {
        newPreviews.push(e.target?.result as string);
      };
      reader.readAsDataURL(file);
    }

    if (validFiles.length > 0) {
      setSelectedFiles([...selectedFiles, ...validFiles]);
      // Simulate preview loading
      setTimeout(() => setPreviews([...previews, ...newPreviews]), 100);
    }

    if (fileInputRef.current) {
      fileInputRef.current.value = "";
    }
  };

  const handleRemoveFile = (index: number) => {
    const newFiles = selectedFiles.filter((_, i) => i !== index);
    const newPreviews = previews.filter((_, i) => i !== index);
    setSelectedFiles(newFiles);
    setPreviews(newPreviews);
  };

  const handleUpload = async () => {
    if (selectedFiles.length === 0) return;

    setCompressing(true);
    setUploading(true);
    try {
      // Compress images on client side
      const base64Array = await compressImages(selectedFiles);

      // Validate total size against quota
      let totalEstimatedSize = 0;
      for (const base64 of base64Array) {
        totalEstimatedSize += estimateCompressedSize(base64);
      }

      const totalSizeMB = totalEstimatedSize / 1024 / 1024;
      if (totalSizeMB > remainingMB) {
        setError(
          `Espaço insuficiente. Tamanho total (${totalSizeMB.toFixed(1)}MB) excede disponível (${remainingMB.toFixed(1)}MB).`
        );
        setCompressing(false);
        setUploading(false);
        return;
      }

      onImagesSelected(selectedFiles, base64Array);
      setSelectedFiles([]);
      setPreviews([]);
      setError("");
    } catch (err) {
      setError("Erro ao processar imagens");
    } finally {
      setCompressing(false);
      setUploading(false);
    }
  };

  return (
    <div className="space-y-4">
      {/* Storage info */}
      <div className="bg-blue-50 p-3 rounded-lg border border-blue-200">
        <div className="flex justify-between mb-2 text-sm">
          <span className="font-medium">Armazenamento:</span>
          <span>
            {formatStorageSize(usedStorageMB * 1024 * 1024)} /{" "}
            {formatStorageSize(quotaLimitMB * 1024 * 1024)}
          </span>
        </div>
        <Progress
          value={(usedStorageMB / quotaLimitMB) * 100}
          className="h-2"
        />
        <div className="text-xs text-gray-600 mt-2">
          Espaço disponível: {remainingMB.toFixed(1)}MB
        </div>
      </div>

      {error && (
        <Alert variant="destructive">
          <AlertTriangle className="h-4 w-4" />
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      {/* Upload area */}
      <div
        className="border-2 border-dashed rounded-lg p-6 text-center cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition"
        onClick={() => fileInputRef.current?.click()}
      >
        <input
          ref={fileInputRef}
          type="file"
          multiple
          accept="image/*"
          onChange={handleFileSelect}
          className="hidden"
        />

        <Upload className="w-8 h-8 mx-auto mb-2 text-gray-400" />
        <p className="text-sm font-medium">
          Clique ou arraste imagens aqui
        </p>
        <p className="text-xs text-gray-500 mt-1">
          PNG, JPG, WebP até {maxFileSizeMB}MB cada (será comprimida)
        </p>
      </div>

      {/* Preview */}
      {selectedFiles.length > 0 && (
        <div>
          <p className="text-sm font-medium mb-2">
            Selecionadas: {selectedFiles.length} imagem(ns)
          </p>
          <div className="grid grid-cols-2 gap-3">
            {previews.map((preview, idx) => (
              <div key={idx} className="relative">
                <img
                  src={preview}
                  alt={`Preview ${idx + 1}`}
                  className="w-full h-24 object-cover rounded border"
                />
                <Button
                  variant="destructive"
                  size="sm"
                  className="absolute top-1 right-1"
                  onClick={() => handleRemoveFile(idx)}
                >
                  <X className="w-3 h-3" />
                </Button>
                <div className="absolute bottom-1 left-1 bg-black/60 text-white text-xs px-2 py-1 rounded">
                  {formatFileSize(selectedFiles[idx].size)}
                </div>
              </div>
            ))}
          </div>

          <Button
            onClick={handleUpload}
            disabled={uploading || selectedFiles.length === 0}
            className="w-full mt-3"
          >
            {compressing ? (
              <>
                <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                Comprimindo...
              </>
            ) : uploading ? (
              <>
                <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                Processando...
              </>
            ) : (
              "Confirmar Upload"
            )}
          </Button>
        </div>
      )}

      <p className="text-xs text-gray-500">
        Máximo: {maxFiles} imagens | Tamanho máximo por imagem: {maxFileSizeMB}MB
        | Imagens serão comprimidas automaticamente
      </p>
    </div>
  );
}
