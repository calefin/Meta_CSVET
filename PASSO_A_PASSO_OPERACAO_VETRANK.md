# 🚀 Guia Prático de Operação, Atualização e Monetização — VetRank

Este documento reúne o passo a passo completo para:
1. Fazer o **deploy** das atualizações na VPS (`vetrank.com.br`).
2. Atualizar o **Radar de Concursos (Veterinária e Zootecnia)** localmente com **custo R$ 0,00 de IA**.
3. Anexar **PDFs de Editais** com a **Capa de Propaganda do VetRank + Resumo + Checklist de Estudos**.
4. Extrair **Questões Reais de Provas de Concursos em PDF** (sem gastar IA) e alimentar o banco de questões e a página `/novidades`.
5. Executar a rotina rápida de **Instagram (Story/Feed) + WhatsApp** para atrair assinantes.

---

## 1. Fazer o Deploy na VPS (Colocar tudo no ar amanhã)

Tudo já foi enviado para o seu GitHub (`origin/main`). Para atualizar o site em produção:

1. Abra o terminal e conecte-se na sua VPS via SSH:
   ```bash
   ssh usuario@ip-da-sua-vps
   ```
2. Dentro da VPS, entre na pasta do projeto e atualize os containers:
   ```bash
   cd ~/vetrank_new   # (ou o caminho onde o repositório fica na VPS, ex: /var/www/vetrank_new)
   git pull origin main
   docker compose up -d --build app
   ```
3. (Opcional) Verifique os logs para confirmar que os **72 concursos iniciais** foram carregados no banco:
   ```bash
   docker compose logs -f --tail=80 app
   ```
   *(Pressione `Ctrl + C` para sair dos logs).*

Assim que o container subir, acesse:
- https://vetrank.com.br/concursos *(verá os 72 concursos com abas Vigentes, Inscrições abertas, Vai abrir, Veterinária e Zootecnia, Kit Instagram e botão de PDF)*
- https://vetrank.com.br/novidades *(verá o Radar vivo e atualizado)*

---

## 2. Como Atualizar a Lista de Concursos no seu Computador (Semanal ou Diário — Custo R$ 0,00)

No seu notebook (`~/Documentos/Projetos Git/vetrank_new`), sempre que quiser buscar novos editais de Medicina Veterinária e Zootecnia (incluindo Diário Oficial da União - DOU e filtro anti-clickbait):

```bash
cd "/home/calefi/Documentos/Projetos Git/vetrank_new"

# 1. Busca novos editais e atualiza data/concursos_seed.json
pnpm concursos:sync

# 2. Envia a lista atualizada para o GitHub
git add data/
git commit -m "chore(concursos): atualiza editais da semana"
git push origin main
```
Depois, basta dar `git pull origin main && docker compose up -d --build app` na VPS (ou clicar em **"Coletar agora"** dentro de `vetrank.com.br/admin` → aba **Concursos**, pois o servidor também roda o coletor gratuito todo dia às 06h00).

---

## 3. Como Colocar Capa de Propaganda + Resumo nos PDFs dos Editais

Todo concurso cadastrado no VetRank **já gera automaticamente** um PDF de 2 páginas (`/api/concursos/<slug>/guia.pdf`) com:
- **Página 1**: Capa de Propaganda do VetRank + Ficha Técnica + Cargos + Resumo do Concurso.
- **Página 2**: Edital Verticalizado + Checklist de Estudos para imprimir.

Se você baixar o **PDF original do edital** no site da banca, você pode juntar esse PDF oficial ao Guia do VetRank (o sistema coloca as 2 páginas do VetRank na frente e carimba o rodapé do VetRank em todas as páginas do edital oficial!):

### Passo a passo:
1. Veja a lista de slugs dos concursos rodando:
   ```bash
   cd "/home/calefi/Documentos/Projetos Git/vetrank_new"
   pnpm concursos:pdf
   ```
2. Baixe o PDF do edital no site oficial da banca e rode:
   ```bash
   pnpm concursos:pdf ~/Downloads/nome_do_edital.pdf <slug-do-concurso>
   ```
   **Exemplo real (USP):**
   ```bash
   pnpm concursos:pdf ~/Downloads/edital_usp.pdf usp-universidade-de-sao-paulo-sp-2027-e3953643
   ```
3. Suba para o GitHub para ir para o servidor:
   ```bash
   git add data/editais/
   git commit -m "chore(editais): adiciona PDF oficial timbrado"
   git push origin main
   ```

### Links Oficiais dos Principais Concursos Atuais para Baixar o Edital:

| Órgão / UF | Salário até | Inscrições até | Site Oficial da Banca / Órgão | Slug para o comando `pnpm concursos:pdf` |
| :--- | :--- | :--- | :--- | :--- |
| **USP (SP)** | R$ 11.778,84 | 07/01/2027 | https://www.fuvest.br/ | `usp-universidade-de-sao-paulo-sp-2027-e3953643` |
| **Pref. Santana dos Montes (MG)** | R$ 20.418,44 | 21/01/2027 | https://www.eloassessoriaeservicos.com.br/ | `prefeitura-de-santana-dos-montes-mg-2027-2febbe95` |
| **Pref. Ijuí (RS)** *(Vet + Zoo)* | R$ 21.312,22 | 19/11/2026 | https://www.fundatec.org.br/ | `prefeitura-de-ijui-rs-2026-845f8c81` |
| **Pref. Bom Retiro (SC)** | R$ 20.138,46 | 23/11/2026 | https://portal.sctreinamentos.selecao.site/ | `prefeitura-de-bom-retiro-sc-2026-a2af005a` |
| **Pref. Paraguaçu (MG)** | R$ 20.128,27 | 19/01/2027 | https://www.ibdoprojetos.org.br/ | `prefeitura-de-paraguacu-mg-2027-f1d1e6e4` |
| **Pref. Agronômica (SC)** | R$ 19.660,06 | 04/12/2026 | https://talentconcursos.selecao.net.br/ | `prefeitura-de-agronomica-sc-2026-32a7f464` |
| **Pref. Iporã do Oeste (SC)** | R$ 19.265,80 | 16/11/2026 | https://ameosc.org.br/ | `prefeitura-de-ipora-do-oeste-sc-2026-b661da65` |
| **Pref. São João del-Rei (MG)** | R$ 15.164,56 | 12/11/2026 | https://www.saojoaodelrei.mg.gov.br/ | `prefeitura-de-sao-joao-del-rei-mg-2026-2ff32fe1` |
| **Pref. Luzerna (SC)** | R$ 14.004,25 | 03/12/2026 | https://www.institutodom.com/ | `prefeitura-de-luzerna-sc-2026-1b156484` |
| **Pref. Birigui (SP)** | R$ 13.881,85 | 10/12/2026 | https://www.access.org.br/ | `prefeitura-de-birigui-sp-2026-6b105285` |
| **Pref. Teutônia (RS)** | R$ 13.844,56 | 18/11/2026 | https://www.objetivas.com.br/ | `prefeitura-de-teutonia-rs-2026-9264d44f` |
| **SECITECI (MT)** *(Vet + Zoo)* | R$ 12.937,29 | 26/11/2026 | https://selecon.org.br/ | `seciteci-secretaria-de-estado-de-ciencia-tecnologia-e-inovacao-de-mato-grosso-mt-2026-1cf9c086` |
| **UFRRJ (RJ)** *(DOU)* | Ver edital | Ver DOU | https://www.in.gov.br/web/dou/-/edital-n-56-de-23-de-outubro-de-2026-740364517 | `universidade-federal-rural-do-rio-de-janeiro-rj-2026-143fc62e` |
| **UFBA (BA)** *(DOU)* | Ver edital | Ver DOU | https://www.in.gov.br/web/dou/-/edital-de-27-de-outubro-de-2026-740792689 | `universidade-federal-da-bahia-ba-2026-951aa866` |

---

## 4. Como Baixar Provas Anteriores e Extrair as Questões para o Banco (Custo R$ 0,00 de IA)

Para alimentar constantemente o banco de questões com provas reais de 2024, 2025 e 2026 (e fazer o contador de *"Novas questões nos últimos 30 dias"* subir em `/novidades`):

### Passo 1: Ver a lista de provas recentes para baixar
```bash
cd "/home/calefi/Documentos/Projetos Git/vetrank_new"
pnpm provas:listar
```
*(A lista completa também fica salva em `data/catalogo_provas_recentes.json`).*

**Principais links de provas de 2025/2026 prontas para você baixar no navegador (`prova.pdf` + `gabarito.pdf`):**
- **[2026] MP/ES (FGV) — Médico Veterinário**:
  https://www.pciconcursos.com.br/provas/download/agente-tecnico-medico-veterinario-mp-es-fgv-2026
- **[2026] Pref. Pão de Açúcar/AL (IGEDUC) — Zootecnista**:
  https://www.pciconcursos.com.br/provas/download/zootecnista-prefeitura-pao-de-acucar-al-igeduc-2026
- **[2025] CFMV (IADES) — Analista Médico-Veterinário**:
  https://www.pciconcursos.com.br/provas/download/analista-medico-veterinario-cfmv-iades-2025
- **[2025] MP/SP (Vunesp) — Analista Técnico Científico - Médico Veterinário**:
  https://www.pciconcursos.com.br/provas/download/analista-tecnico-cientifico-medico-veterinario-mp-sp-fundacao-vunesp-2025
- **[2025] CRMV/PE (Quadrix) — Fiscal Médico Veterinário**:
  https://www.pciconcursos.com.br/provas/download/fiscal-medico-veterinario-crmv-pe-quadrix-2025
- **[2025] CRMV/TO (Quadrix) — Fiscal Médico Veterinário**:
  https://www.pciconcursos.com.br/provas/download/fiscal-medico-veterinario-crmv-to-quadrix-2025
- **[2025] CRMV/TO (Quadrix) — Fiscal Zootecnista**:
  https://www.pciconcursos.com.br/provas/download/fiscal-zootecnista-crmv-to-quadrix-2025

### Passo 2: Extrair as questões do PDF da prova + gabarito
Assim que baixar a prova e o gabarito na sua pasta `Downloads`, rode:
```bash
pnpm provas:extrair ~/Downloads/prova.pdf ~/Downloads/gabarito.pdf --banca="FGV" --orgao="MP-ES" --ano=2026 --cargo="Médico Veterinário"
```
- O script lê a prova e o gabarito, separa apenas as questões de **Veterinária / Zootecnia**, classifica cada questão automaticamente nas 6 Grandes Áreas do VetRank e salva o arquivo pronto em:
  `data/questoes_extraidas/<nome_da_prova>_2026.json`

### Passo 3: Importar no Painel Admin do VetRank
1. Acesse `https://vetrank.com.br/admin`
2. Vá na aba **Questões** → **Importar JSON**
3. Selecione o arquivo `.json` gerado dentro de `data/questoes_extraidas/`
4. Pronto! As questões entram no banco com **Banca, Órgão, Cargo e Ano**, um aviso automático é publicado no **Mural**, e o contador de **Novas Questões** em `/novidades` sobe imediatamente!

---

## 5. Rotina Diária de 5 Minutos para Divulgar e Ganhar Dinheiro

1. **No Instagram (Stories + Feed)**:
   - Abra `https://vetrank.com.br/concursos` e clique em **"Kit Instagram"** no concurso que quiser divulgar no dia.
   - Baixe a arte **Story (1080×1920)** ou **Feed (1080×1080)** e clique em **"Copiar legenda com hashtags"**.
   - Ao postar nos Stories, coloque a figurinha de **Link** apontando para a página daquele concurso no VetRank (`https://vetrank.com.br/concursos/<slug>`) com o texto: *"Baixar Edital Resumido + Treinar Questões"*.
   - Toda segunda-feira, baixe também o card de resumo semanal: `https://vetrank.com.br/api/og/concursos-semana/story.png`.

2. **Nos Grupos de WhatsApp e Telegram de Veterinária e Zootecnia**:
   - Baixe o PDF gerado pelo botão **"Baixar Guia + Edital Resumido (PDF)"** do concurso.
   - Envie **o próprio arquivo PDF** nos grupos dizendo:
     *"Pessoal, saiu concurso para Veterinário/Zootecnista no órgão X (salário de até R$ Y). Preparei o PDF com o resumo das vagas, datas e o checklist verticalizado do edital pra quem quiser imprimir e estudar."*
   - Como o PDF tem a **Capa de Propaganda do VetRank** e links clicáveis para `vetrank.com.br`, cada compartilhamento leva novos estudantes gratuitamente para dentro da sua plataforma!
