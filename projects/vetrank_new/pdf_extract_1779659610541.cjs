
    const fs = require('fs');
    const pdf = require('pdf-parse');
    pdf(fs.readFileSync(process.env.TARGET_PDF)).then(data => {
      fs.writeFileSync(process.env.OUT_JSON, JSON.stringify({ text: data.text }));
    }).catch(err => {
      console.error(err);
      process.exit(1);
    });
  