-- Corrige encoding dos itens da loja (inseridos com charset errado)
UPDATE shop_items SET
  namePt = 'Congelamento Extra',
  nameEn = 'Extra Freeze',
  descriptionPt = 'Protege sua ofensiva por 1 dia',
  icon = '🧊'
WHERE slug = 'freeze-extra';

UPDATE shop_items SET
  namePt = 'Recuperação de Emergência',
  nameEn = 'Emergency Recovery',
  descriptionPt = 'Restaura streak quebrado (48h, 1x/mês)',
  icon = '🚑'
WHERE slug = 'streak-recovery';

UPDATE shop_items SET
  namePt = 'Baú Raro',
  nameEn = 'Rare Chest',
  descriptionPt = 'Chance de badge épico ou item especial',
  icon = '📦'
WHERE slug = 'chest-rare';

SELECT slug, namePt, icon FROM shop_items;
