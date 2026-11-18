
    const fs = require('fs');
    let pdf = require('pdf-parse');
    if (typeof pdf !== 'function') {
      // Procura a função em .default ou em qualquer outra propriedade exportada
      pdf = pdf.default || Object.values(pdf).find(v => typeof v === 'function') || pdf;
    }
    pdf(fs.readFileSync(process.env.TARGET_PDF)).then(data => {
      fs.writeFileSync(process.env.OUT_JSON, JSON.stringify({ text: data.text }));
    }).catch(err => {
      console.error(err);
      process.exit(1);
    });
  