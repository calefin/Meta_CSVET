import embedQrSvgsInSvg from './qr';
import { PAGE_W as SVG_W, PAGE_H as SVG_H } from './answerSheetSvg';

/** Open a browser print dialog with the answer sheet(s) scaled to A4 portrait. */
export function printAnswerSheetPDF(svgStrings: string[], title: string): void {
  const sheets = svgStrings
    .map(
      (svg) =>
        `<div class="sheet">${svg}</div>`
    )
    .join("\n");

  const html = `<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <title>${title}</title>
  <style>
    @page { margin: 0; size: A4 portrait; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { background: white; }
    .sheet {
      width: 210mm;
      height: 297mm;
      display: flex;
      align-items: center;
      justify-content: center;
      page-break-after: always;
      overflow: hidden;
    }
    .sheet:last-child { page-break-after: auto; }
    .sheet svg {
      width: 200mm;
      height: auto;
      max-height: 290mm;
    }
    @media screen {
      body { background: #888; padding: 16px; }
      .sheet { background: white; margin: 0 auto 16px; box-shadow: 0 2px 8px rgba(0,0,0,.3); }
    }
  </style>
</head>
<body>
${sheets}
<script>
  window.addEventListener('load', function() {
    setTimeout(function() { window.print(); }, 400);
  });
</script>
</body>
</html>`;

  const win = window.open("", "_blank", "width=900,height=700");
  if (!win) {
    alert("Permita pop-ups para gerar o PDF.");
    return;
  }
  win.document.open();
  win.document.write(html);
  win.document.close();
}

/** Download the SVG as a high-resolution PNG (3× scale = 300 DPI equivalent). */
export function downloadAnswerSheetPNG(svgString: string, filename: string, dpi = 300): void {
  (async () => {
    try {
      svgString = await embedQrSvgsInSvg(svgString);
    } catch (e) {
      console.warn('Failed to embed QR SVGs before PNG export', e);
    }
    // SVG default units assume 72 DPI. Scale to requested DPI.
    const SCALE = Math.max(1, Math.round(dpi / 72));
    const blob = new Blob([svgString], { type: "image/svg+xml;charset=utf-8" });
    const url = URL.createObjectURL(blob);
    const img = new Image();

    img.onload = () => {
      const canvas = document.createElement("canvas");
      canvas.width = Math.round(SVG_W * SCALE);
      canvas.height = Math.round(SVG_H * SCALE);
      const ctx = canvas.getContext("2d")!;
      ctx.fillStyle = "white";
      ctx.fillRect(0, 0, canvas.width, canvas.height);
      ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
      URL.revokeObjectURL(url);

      canvas.toBlob((pngBlob) => {
        if (!pngBlob) return;
        const pngUrl = URL.createObjectURL(pngBlob);
        const a = document.createElement("a");
        a.href = pngUrl;
        a.download = `${filename}.png`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(pngUrl);
      }, "image/png");
    };

    img.onerror = () => {
      URL.revokeObjectURL(url);
      console.error("Failed to load SVG for PNG export");
    };

    img.src = url;
  })();
}

/** Download the raw SVG file. */
export function downloadAnswerSheetSVG(svgString: string, filename: string): void {
  (async () => {
    try {
      svgString = await embedQrSvgsInSvg(svgString);
    } catch (e) {
      console.warn('Failed to embed QR SVGs before SVG download', e);
    }
    const blob = new Blob([svgString], { type: "image/svg+xml;charset=utf-8" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `${filename}.svg`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  })();
}
