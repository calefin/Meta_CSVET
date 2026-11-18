# VetRank — Hierarquia Canônica de Conteúdo

> **Regra fundamental:** slugs são as chaves canônicas. IDs numéricos variam entre ambientes
> (dev, VPS, staging). Scripts SEMPRE resolvem IDs por slug em runtime — nunca hardcodam IDs.

## Estrutura

```
Grande Área  (disciplines)   slug único global
  └── Assunto  (subjects)    slug único dentro da grande área
        └── Tópico  (topics) slug único dentro do assunto
```

Campos por nível: `id` (auto-increment), `slug`, `namePt`, `nameEn`, `disciplineId` (FK), `subjectId` (FK, só topics).

---

## Grande Áreas cadastradas

| slug | namePt | namePt EN | Obs |
|------|--------|-----------|-----|
| `ciencias-basicas` | Ciências Básicas Veterinárias | Basic Veterinary Sciences | disciplineId dinâmico |
| `patobiologia` | Patobiologia e Agentes Agressores | Pathobiology and Noxious Agents | disciplineId dinâmico |
| `animais-silvestres` | Medicina dos Animais Silvestres | Wildlife and Exotic Animal Medicine | disciplineId dinâmico |

---

## Assuntos por Grande Área

### ciencias-basicas

| slug | namePt | Fonte principal |
|------|--------|-----------------|
| `fisiologia-animal` | Fisiologia Animal | Dukes' Physiology |
| `anatomia-veterinaria` | Anatomia Veterinária | — |
| `bioquimica-veterinaria` | Bioquímica Veterinária | — |

### patobiologia

| slug | namePt | Fonte principal |
|------|--------|-----------------|
| `patologia-especial` | Patologia Especial / Sistêmica | Jubb, Kennedy & Palmer |
| `patologia-geral` | Patologia Geral | — |
| `microbiologia-veterinaria` | Microbiologia Veterinária | — |
| `farmacologia-veterinaria` | Farmacologia Veterinária | — |
| `toxicologia-veterinaria` | Toxicologia Veterinária | — |

### animais-silvestres

| slug | namePt | Fonte principal |
|------|--------|-----------------|
| `animais-silvestres` | Medicina dos Animais Silvestres | — |

**Assuntos por subjectTag (Medicina dos Animais Silvestres):**

| subjectTag | Conteúdo |
|------------|----------|
| `Gestão da Prática Clínica` | Gestão e administração de clínica de silvestres |
| `Biologia, Taxonomia e Fisiologia` | Biologia, taxonomia, anatomia e fisiologia |
| `Manejo e Criação` | Manejo, criação e bem-estar em cativeiro |
| `Doenças Infecciosas e Laboratório` | Doenças infecciosas e ciências laboratoriais |
| `Técnicas e Procedimentos` | Técnicas clínicas e procedimentos |
| `Anestesiologia` | Protocolos anestésicos |
| `Diagnóstico por Imagem` | Radiologia, ultrassonografia, endoscopia |
| `Endoscopia` | Endoscopia diagnóstica e terapêutica |
| `Medicina Clínica` | Medicina interna |
| `Cirurgia` | Técnicas cirúrgicas |
| `Terapêutica` | Farmacologia e tratamento |
| `Diagnósticos Diferenciais` | Diagnósticos diferenciais por sinais clínicos |
| `Doenças Específicas e Casos Clínicos` | Resumo de doenças e casos |
| `Saúde Pública e Coletiva` | Saúde pública, zoonoses e população |

> **Regra:** `topicId` **não é usado** neste fluxo. O `subjectTag` é o único campo de categorização por assunto.

---

## Tópicos por Assunto

### fisiologia-animal

| slug | namePt | subjectTag padrão |
|------|--------|-------------------|
| `fisio-sistema-nervoso` | Sistema Nervoso | Sistema Nervoso |
| `fisio-sistema-cardiovascular` | Sistema Cardiovascular | Sistema Cardiovascular |
| `fisio-sistema-respiratorio` | Sistema Respiratório | Sistema Respiratório |
| `fisio-sistema-digestorio` | Sistema Digestório | Sistema Digestório |
| `fisio-sistema-renal` | Sistema Renal e Urinário | Sistema Renal |
| `fisio-sistema-endocrino` | Sistema Endócrino | Sistema Endócrino |
| `fisio-sistema-reprodutor` | Sistema Reprodutor | Sistema Reprodutor |
| `fisio-sistema-musculoesqueletico` | Sistema Musculoesquelético | Sistema Musculoesquelético |
| `fisio-sangue-imunidade` | Sangue e Imunidade | Sangue e Imunidade |
| `fisio-termorregulacao` | Termorregulação e Metabolismo | Termorregulação |
| `fisio-lactacao` | Fisiologia da Lactação | Lactação |
| `fisio-sensorial` | Fisiologia Sensorial | Fisiologia Sensorial |

### patologia-especial

| slug | namePt | subjectTag padrão |
|------|--------|-------------------|
| `patol-sistema-cardiovascular` | Sistema Cardiovascular | Sistema Cardiovascular |
| `patol-sistema-respiratorio` | Sistema Respiratório | Sistema Respiratório |
| `patol-sistema-digestorio` | Sistema Digestório | Sistema Digestório |
| `patol-sistema-hepatico` | Sistema Hepático e Biliar | Sistema Hepático |
| `patol-sistema-renal` | Sistema Renal e Urinário | Sistema Renal |
| `patol-sistema-nervoso` | Sistema Nervoso | Sistema Nervoso |
| `patol-sistema-musculoesqueletico` | Sistema Musculoesquelético | Sistema Musculoesquelético |
| `patol-sistema-hematopoietico` | Sistema Hematopoiético | Sistema Hematopoiético |
| `patol-sistema-endocrino` | Sistema Endócrino | Sistema Endócrino |
| `patol-sistema-reprodutor` | Sistema Reprodutor | Sistema Reprodutor |
| `patol-tegumentar` | Sistema Tegumentar | Sistema Tegumentar |
| `patol-sistema-imune` | Sistema Imune e Linfoide | Sistema Imune |
| `patol-olho-ouvido` | Olho e Ouvido | Olho e Ouvido |
| `patol-neoplasias` | Neoplasias (Geral) | Neoplasias |

### repteis-anfibios

| slug | namePt | subjectTag padrão |
|------|--------|-------------------|
| `repteis-gestao-pratica` | Gestão da Prática Clínica | Gestão da Prática Clínica |
| `repteis-biologia` | Biologia, Taxonomia e Fisiologia | Biologia, Taxonomia e Fisiologia |
| `repteis-manejo-criacao` | Manejo e Criação | Manejo e Criação |
| `repteis-doencas-infecciosas` | Doenças Infecciosas e Laboratório | Doenças Infecciosas e Laboratório |
| `repteis-tecnicas-procedimentos` | Técnicas e Procedimentos | Técnicas e Procedimentos |
| `repteis-anestesiologia` | Anestesiologia | Anestesiologia |
| `repteis-diagnostico-imagem` | Diagnóstico por Imagem | Diagnóstico por Imagem |
| `repteis-endoscopia` | Endoscopia | Endoscopia |
| `repteis-medicina-clinica` | Medicina Clínica | Medicina Clínica |
| `repteis-cirurgia` | Cirurgia | Cirurgia |
| `repteis-terapeutica` | Terapêutica | Terapêutica |
| `repteis-diagnosticos-diferenciais` | Diagnósticos Diferenciais | Diagnósticos Diferenciais |
| `repteis-doencas-especificas` | Doenças Específicas e Casos Clínicos | Doenças Específicas e Casos Clínicos |
| `repteis-saude-publica` | Saúde Pública e Coletiva | Saúde Pública e Coletiva |

---

## Uso nos scripts de extração

Os scripts (`extrator_repteis.py`, `extrator_jubb.py`) usam IDs diretamente do `.env` — sem lookup de slugs em runtime:

```bash
# .env dos scripts de geração
DISCIPLINE_ID=30003        # ID da grande área no banco de produção
SUBJECT_ID=30046           # ID do assunto no banco de produção
SUBJECT_TAG=Anestesiologia # texto livre por capítulo/grupo
```

> **Atenção:** IDs de produção começam em 30001+. IDs locais (dev) são 1–8 e não servem para os scripts.

---

## subjectTag — uso correto

`subjectTag` é **texto livre** — NÃO é chave de hierarquia. Serve para categorização fina dentro de um assunto:
- Sistema fisiológico: "Sistema Cardiovascular", "Sistema Nervoso"
- Espécie/grupo: "Bovinos", "Répteis e Anfíbios"
- Combinação: "Sistema Cardiovascular — Bovinos"

`topicId` existe no schema mas **não é usado** nos fluxos de geração de questões. O `subjectTag` é o único campo de sub-categorização ativo.

---

## Regras para novos conteúdos

1. Verifique se a grande área e o assunto já existem (por slug) antes de criar
2. Crie o assunto via Admin → Qualidade se necessário; anote o ID gerado
3. Nos scripts, use `DISCIPLINE_ID` e `SUBJECT_ID` de produção no `.env`
4. Após gerar questões, valide com `normalize_questions_json.mjs`
