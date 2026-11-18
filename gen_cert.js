const { PDFDocument, rgb, StandardFonts, degrees } = require('pdf-lib');
const fs = require('fs');

async function gen() {
  const pdfDoc = await PDFDocument.create();

  // ── PAGE 1: Certificate ──────────────────────────────────────────────────────
  const page = pdfDoc.addPage([841.89, 595.28]);
  const { width, height } = page.getSize();
  const hb = await pdfDoc.embedFont(StandardFonts.HelveticaBold);
  const hr = await pdfDoc.embedFont(StandardFonts.Helvetica);

  const darkGreen  = rgb(0.05, 0.27, 0.18);
  const midGreen   = rgb(0.09, 0.45, 0.30);
  const lightGreen = rgb(0.13, 0.60, 0.40);
  const neonGreen  = rgb(0.29, 0.87, 0.50);
  const gold       = rgb(0.75, 0.60, 0.15);
  const cream      = rgb(0.97, 0.98, 0.96);
  const white      = rgb(1, 1, 1);
  const darkText   = rgb(0.10, 0.14, 0.12);
  const mutedText  = rgb(0.35, 0.42, 0.38);

  page.drawRectangle({ x:0, y:0, width, height, color: cream });

  const bandH = 110;
  page.drawRectangle({ x:0, y:height-bandH, width, height:bandH, color:darkGreen });
  page.drawRectangle({ x:0, y:height-bandH, width, height:4, color:gold });

  const sideW = 28;
  page.drawRectangle({ x:0, y:0, width:sideW, height:height-bandH, color:midGreen });
  page.drawRectangle({ x:width-sideW, y:0, width:sideW, height:height-bandH, color:midGreen });

  page.drawRectangle({ x:0, y:0, width, height:60, color:darkGreen });
  page.drawRectangle({ x:0, y:60, width, height:3, color:gold });

  page.drawRectangle({ x:sideW+10, y:height-bandH+10, width:14, height:14, color:gold, opacity:0.5 });
  page.drawRectangle({ x:width-sideW-24, y:height-bandH+10, width:14, height:14, color:gold, opacity:0.5 });

  // Header lockup: icon + brand name side by side, centered as a unit
  const iconSize = 36;
  const sc = iconSize / 64;
  const brand = 'VetRank';
  const brandFontSize = 22;
  const brandW = hb.widthOfTextAtSize(brand, brandFontSize);
  const iconTextGap = 10;
  const lockupW = iconSize + iconTextGap + brandW;
  const lockupX = (width - lockupW) / 2;
  const rowCenterY = height - 40;

  const iX = lockupX;
  const iY = rowCenterY - iconSize / 2;
  page.drawRectangle({ x:iX, y:iY, width:iconSize, height:iconSize, color:rgb(0.05,0.07,0.09) });
  page.drawLine({ start:{x:iX+12*sc, y:iY+(64-20)*sc}, end:{x:iX+32*sc, y:iY+(64-48)*sc}, thickness:3, color:neonGreen });
  page.drawLine({ start:{x:iX+32*sc, y:iY+(64-48)*sc}, end:{x:iX+52*sc, y:iY+(64-20)*sc}, thickness:3, color:neonGreen });
  page.drawCircle({ x:iX+32*sc, y:iY+(64-16)*sc, size:6*sc, color:neonGreen });

  // Brand name vertically centered with icon (cap height ≈ 71.5% of font size)
  const capH = brandFontSize * 0.715;
  const brandTextY = iY + (iconSize - capH) / 2;
  page.drawText(brand, { x:lockupX + iconSize + iconTextGap, y:brandTextY, size:brandFontSize, font:hb, color:white });

  const tagline = 'Preparação para Residências Veterinárias';
  page.drawText(tagline, { x:(width-hr.widthOfTextAtSize(tagline,9))/2, y:height-73, size:9, font:hr, color:rgb(0.75,0.90,0.82) });
  for (let i=0;i<7;i++) page.drawCircle({ x:width/2-36+i*12, y:height-90, size:2, color:gold, opacity:0.7 });

  const mainTitle = 'CERTIFICADO DE CONCLUSÃO';
  page.drawText(mainTitle, { x:(width-hb.widthOfTextAtSize(mainTitle,24))/2, y:height-152, size:24, font:hb, color:darkGreen });

  const lineY = height-166, lineXC = width/2;
  page.drawLine({ start:{x:lineXC-100,y:lineY}, end:{x:lineXC-12,y:lineY}, thickness:1, color:lightGreen, opacity:0.5 });
  page.drawCircle({ x:lineXC, y:lineY, size:4, color:gold });
  page.drawLine({ start:{x:lineXC+12,y:lineY}, end:{x:lineXC+100,y:lineY}, thickness:1, color:lightGreen, opacity:0.5 });

  const certifyText = 'Certificamos que o(a) profissional';
  page.drawText(certifyText, { x:(width-hr.widthOfTextAtSize(certifyText,12))/2, y:height-200, size:12, font:hr, color:mutedText });

  const nameText = 'Maria Fernanda Oliveira Santos';
  const nameSize = 30;
  const nameW = hb.widthOfTextAtSize(nameText, nameSize);
  page.drawText(nameText, { x:(width-nameW)/2, y:height-240, size:nameSize, font:hb, color:darkGreen });
  page.drawLine({ start:{x:(width-nameW)/2, y:height-246}, end:{x:(width+nameW)/2, y:height-246}, thickness:0.8, color:gold, opacity:0.7 });

  const cpfTxt = 'CPF: 123.***.***-45';
  page.drawText(cpfTxt, { x:(width-hr.widthOfTextAtSize(cpfTxt,10))/2, y:height-262, size:10, font:hr, color:mutedText });

  const conclText = 'concluiu com êxito o curso';
  page.drawText(conclText, { x:(width-hr.widthOfTextAtSize(conclText,12))/2, y:height-288, size:12, font:hr, color:mutedText });

  const courseTitle = 'Clínica Médica de Pequenos Animais — Módulo Avançado';
  const ctSize = 16;
  const ctW = hb.widthOfTextAtSize(courseTitle, ctSize);
  page.drawText(courseTitle, { x:(width-ctW)/2, y:height-316, size:ctSize, font:hb, color:midGreen });

  const metaTxt = 'Carga horária: 40h';
  page.drawText(metaTxt, { x:(width-hr.widthOfTextAtSize(metaTxt,10))/2, y:height-338, size:10, font:hr, color:mutedText });

  const dateTxt = 'Emitido em 24 de maio de 2026';
  page.drawText(dateTxt, { x:(width-hr.widthOfTextAtSize(dateTxt,10))/2, y:height-356, size:10, font:hr, color:mutedText });

  // Two signatures
  function sig(x1, x2, name, l1, l2, role) {
    const sigY = 128;
    page.drawLine({ start:{x:x1,y:sigY}, end:{x:x2,y:sigY}, thickness:0.8, color:midGreen });
    page.drawText(name, { x:x1+(x2-x1-hb.widthOfTextAtSize(name,9))/2, y:sigY-14, size:9, font:hb, color:darkText });
    if (l1) page.drawText(l1, { x:x1+(x2-x1-hr.widthOfTextAtSize(l1,8))/2, y:sigY-26, size:8, font:hr, color:mutedText });
    if (l2) page.drawText(l2, { x:x1+(x2-x1-hr.widthOfTextAtSize(l2,7.5))/2, y:sigY-37, size:7.5, font:hr, color:mutedText });
    if (role) page.drawText(role, { x:x1+(x2-x1-hr.widthOfTextAtSize(role,7))/2, y:sigY-48, size:7, font:hr, color:rgb(0.5,0.42,0.2) });
  }

  const sigW = 205;
  sig(sideW+50, sideW+50+sigW, 'Prof. Dr. Atilio Sersun Calefi', 'CNPJ: 32.645.724/0001-89', 'CSVET Editora, Cursos e Treinamentos', 'CEO / Responsável pela emissão');
  sig(width-sideW-50-sigW, width-sideW-50, 'Prof. Dr. Rodrigo Mendes Costa', 'Médico Veterinário', 'CRMV-SP 12345', 'Instrutor do Curso');

  // Footer
  const hash = 'SHA-256: a3f8b2c1d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7...';
  page.drawText(hash, { x:(width-hr.widthOfTextAtSize(hash,7))/2, y:43, size:7, font:hr, color:rgb(0.65,0.85,0.75) });
  const codeT = 'Código: 550e8400-e29b-41d4-a716-446655440000';
  page.drawText(codeT, { x:(width-hr.widthOfTextAtSize(codeT,7.5))/2, y:29, size:7.5, font:hr, color:rgb(0.80,0.93,0.87) });
  const urlT = 'vetrank.com.br/verificar-certificado/550e8400-e29b-41d4-a716-446655440000';
  page.drawText(urlT, { x:(width-hr.widthOfTextAtSize(urlT,7))/2, y:14, size:7, font:hr, color:rgb(0.55,0.75,0.65) });
  page.drawText('VetRank', { x:width/2-80, y:height/2-80, size:90, font:hb, color:midGreen, opacity:0.04, rotate:degrees(35) });

  // ── PAGE 2: Conteúdo Programático ────────────────────────────────────────────
  const page2 = pdfDoc.addPage([841.89, 595.28]);
  const w2 = page2.getSize().width;
  const h2 = page2.getSize().height;

  page2.drawRectangle({ x:0, y:0, width:w2, height:h2, color:cream });
  page2.drawRectangle({ x:0, y:h2-55, width:w2, height:55, color:darkGreen });
  page2.drawRectangle({ x:0, y:h2-55, width:w2, height:3, color:gold });
  page2.drawRectangle({ x:0, y:0, width:w2, height:30, color:darkGreen });
  page2.drawRectangle({ x:0, y:30, width:w2, height:2, color:gold });

  const p2Title = 'CONTEÚDO PROGRAMÁTICO';
  page2.drawText(p2Title, { x:(w2-hb.widthOfTextAtSize(p2Title,18))/2, y:h2-38, size:18, font:hb, color:white });

  const courseTitleP2 = 'Clínica Médica de Pequenos Animais — Módulo Avançado';
  page2.drawText(courseTitleP2, { x:(w2-hr.widthOfTextAtSize(courseTitleP2,10))/2, y:h2-50, size:10, font:hr, color:rgb(0.75,0.90,0.82) });

  // Sample course structure
  const modules = [
    { title: 'Módulo 1 — Diagnóstico Clínico', lessons: ['Anamnese e Exame Físico', 'Interpretação de Exames Laboratoriais', 'Diagnóstico por Imagem'] },
    { title: 'Módulo 2 — Doenças Infecciosas', lessons: ['Ciência Felina e Parvovirose', 'Erliquiose e Babesiose', 'Leptospirose e Brucelose'] },
    { title: 'Módulo 3 — Endocrinologia', lessons: ['Diabetes Mellitus', 'Hipotireoidismo e Hipertireoidismo', 'Hiperadrenocorticismo (Cushing)'] },
    { title: 'Módulo 4 — Avaliação Final', lessons: ['Questionário de Encerramento', 'Casos Clínicos Integrados'] },
  ];

  const colW = (w2 - 80) / 2;
  let col = 0, yPos = h2 - 80;

  for (const mod of modules) {
    const xBase = 40 + col * (colW + 20);

    // Module header
    page2.drawRectangle({ x:xBase, y:yPos-18, width:colW-10, height:22, color:midGreen, opacity:0.12 });
    page2.drawRectangle({ x:xBase, y:yPos+2, width:3, height:18, color:midGreen });
    page2.drawText(mod.title, { x:xBase+10, y:yPos-10, size:9.5, font:hb, color:darkGreen });
    yPos -= 30;

    for (const lesson of mod.lessons) {
      page2.drawCircle({ x:xBase+14, y:yPos-4, size:2, color:lightGreen });
      page2.drawText(lesson, { x:xBase+22, y:yPos-9, size:9, font:hr, color:mutedText });
      yPos -= 18;
    }
    yPos -= 12;

    if (col === 0) {
      col = 1;
      yPos = h2 - 80;
    }
  }

  // Footer page 2
  const stuName = 'Aluno: Maria Fernanda Oliveira Santos  |  Carga Horária Total: 40h  |  Emitido em 24/05/2026';
  page2.drawText(stuName, { x:(w2-hr.widthOfTextAtSize(stuName,8))/2, y:14, size:8, font:hr, color:rgb(0.65,0.85,0.75) });

  const bytes = await pdfDoc.save();
  fs.writeFileSync('certificado_final.pdf', Buffer.from(bytes));
  console.log('OK - 2 paginas');
}
gen().catch(e => { console.error(e.message); process.exit(1); });
