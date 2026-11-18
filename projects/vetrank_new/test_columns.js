const text = `Um criador comercial de serpentes (Python regius) relata que uma fêmea adulta, após 60 dias de postura, apresentou perda de peso progressiva e recusa alimentar. Ao exame, observa-se que a serpente mantém-se enrolada firmemente sobre os ovos, mesmo durante o manejo. O veterinário precisa associar corretamente os comportamentos reprodutivos e estratégias de cuidado parental descritos para diferentes grupos de répteis e anfíbios. Relacione a COLUNA I (Comportamento ou Estratégia Reprodutiva) com a COLUNA II (Espécie ou Grupo Taxonômico que o apresenta): COLUNA I 1. Incubação de ovos com aumento de temperatura por contrações musculares ('shivering'). 2. Construção de ninho com galhos e folhas de bambu, com guarda maternal prolongada. 3. Fertilização externa com transferência externa de esperma, mas fertilização interna (cloacal kissing). 4. Cortejo com glândulas mentonianas 'hedônicas' que liberam feromônios aplicados por 'snapping'. COLUNA II a) Sphenodon punctatus (tuatara). b) Ophiophagus hannah (cobra-rei). c) Plethodon cinereus (salamandra-de-dorso-vermelho). d) Python regius (píton-real). e) Triturus cristatus (tritão-cristado).`;

function parseKeyedItems(text, pattern) {
  const items = [];
  const re = new RegExp(pattern.source, "gi");
  const positions = [];
  let m;
  while ((m = re.exec(text)) !== null) {
    positions.push({ key: m[1], start: m.index, end: m.index + m[0].length });
  }
  for (let i = 0; i < positions.length; i++) {
    const { key, end } = positions[i];
    const nextStart = positions[i + 1]?.start ?? text.length;
    items.push({ key, text: text.slice(end, nextStart).trim() });
  }
  return items;
}

function parseColumnsText(text) {
  const col1Re = /COLUNA\s+I(?!I)|COLUNA\s+A(?!\w)/gi;
  const col2Re = /COLUNA\s+II|COLUNA\s+B(?!\w)/gi;
  
  let col1Match;
  let lastCol1Match = null;
  while ((col1Match = col1Re.exec(text)) !== null) {
    lastCol1Match = col1Match;
  }
  
  let col2Match;
  let lastCol2Match = null;
  while ((col2Match = col2Re.exec(text)) !== null) {
    lastCol2Match = col2Match;
  }

  if (!lastCol1Match || !lastCol2Match || lastCol1Match.index >= lastCol2Match.index) return null;

  const col1End = lastCol1Match.index + lastCol1Match[0].length;
  const col2End = lastCol2Match.index + lastCol2Match[0].length;
  const before = text.slice(0, lastCol1Match.index).trim();
  const col1Raw = text.slice(col1End, lastCol2Match.index).trim();
  const col2Raw = text.slice(col2End).trim();

  // COLUNA I: try numeric keys first (1. 2.), then Roman numeral keys (I. II.)
  let col1Items = parseKeyedItems(col1Raw, /(?:^|\s)(\d+)[.)]/);
  if (!col1Items.length) col1Items = parseKeyedItems(col1Raw, /(?:^|\s)(X{0,2}(?:IX|IV|VI{0,3}|I{1,3}))[.)]/);
  const col2Items = parseKeyedItems(col2Raw, /(?:^|\s)([a-e])[.)]/);
  if (!col1Items.length || !col2Items.length) return null;

  // Extract trailing prompt from last col2 item (e.g. "A associação CORRETA é:")
  let after = "";
  if (col2Items.length > 0) {
    const last = col2Items[col2Items.length - 1];
    const tm = last.text.match(/\.\s+([A-Z][^.]{3,80}[?:])\s*$/);
    if (tm) {
      after = tm[1].trim();
      last.text = last.text.slice(0, last.text.length - tm[0].length).trim();
    }
  }

  return {
    before,
    col1Label: lastCol1Match[0].trim().toUpperCase(),
    col1Items,
    col2Label: lastCol2Match[0].trim().toUpperCase(),
    col2Items,
    after,
  };
}

console.log(JSON.stringify(parseColumnsText(text), null, 2));
