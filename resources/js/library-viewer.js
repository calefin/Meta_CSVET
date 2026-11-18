import * as pdfjsLib from 'pdfjs-dist';
import pdfWorkerSrc from 'pdfjs-dist/build/pdf.worker.min.mjs?url';

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorkerSrc;

/**
 * Renders the PDF onto <canvas> only (no native browser PDF viewer, no
 * text layer, no download link) and bakes a per-user watermark into the
 * pixels of every page so it survives a screenshot. This is a friction
 * measure, not a guarantee - screenshots/photos of the screen can never
 * be fully prevented.
 */
async function renderDocument({ streamUrl, watermarkText, container }) {
    const response = await fetch(streamUrl, { credentials: 'same-origin' });

    if (!response.ok) {
        container.innerHTML = '<p class="text-danger text-sm">Não foi possível carregar o documento.</p>';
        return;
    }

    const data = await response.arrayBuffer();
    const pdf = await pdfjsLib.getDocument({ data }).promise;

    for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber++) {
        const page = await pdf.getPage(pageNumber);
        const viewport = page.getViewport({ scale: 1.4 });

        const canvas = document.createElement('canvas');
        canvas.width = viewport.width;
        canvas.height = viewport.height;
        canvas.className = 'mx-auto mb-4 max-w-full rounded shadow-sm select-none';

        const ctx = canvas.getContext('2d');
        await page.render({ canvasContext: ctx, viewport }).promise;

        drawWatermark(ctx, canvas.width, canvas.height, watermarkText);

        container.appendChild(canvas);
    }
}

function drawWatermark(ctx, width, height, text) {
    ctx.save();
    ctx.globalAlpha = 0.12;
    ctx.fillStyle = '#1A1A1A';
    ctx.font = '16px sans-serif';
    ctx.translate(width / 2, height / 2);
    ctx.rotate(-Math.PI / 6);

    const stepY = 120;
    const stepX = 260;

    for (let y = -height; y < height; y += stepY) {
        for (let x = -width; x < width; x += stepX) {
            ctx.fillText(text, x, y);
        }
    }

    ctx.restore();
}

function blockCopyAttempts(container) {
    container.addEventListener('contextmenu', (e) => e.preventDefault());
    container.addEventListener('dragstart', (e) => e.preventDefault());
    container.addEventListener('copy', (e) => e.preventDefault());
    container.addEventListener('selectstart', (e) => e.preventDefault());
}

window.IjoifaLibraryViewer = { renderDocument, blockCopyAttempts };
