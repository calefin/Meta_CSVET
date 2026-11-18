# Diretrizes para Artigos Experimentais e de Campo em Ciências Biológicas

Este documento orienta a redação de artigos originais baseados em ensaios de bancada, experimentos de campo, levantamentos ecológicos e análises de bioinformática.

---

## 1. Delineamento Experimental e Reprodutibilidade

1. **Diferenciação de Réplicas Biológicas vs Técnicas:**
   - Especifique sempre o número de réplicas biológicas independentes ($n$). Réplicas técnicas (ex.: triplicatas de poços de PCR ou de titulação da mesma amostra) devem ser calculadas como a média de uma única réplica biológica para evitar pseudorreplicação.
2. **Procedimentos de Bancada e Genômica:**
   - Descreva a extração de ácidos nucleicos (kits ou método manual com controle de qualidade: Razão 260/280 nm e 260/230 nm).
   - Sequências de iniciadores (*primers*) e sondas com temperaturas de anelamento e referências.
   - Softwares de bioinformática citados com número exato da versão, algoritmos e parâmetros não-padrão utilizados (ex.: *Trimmomatic v0.39*, *QIIME 2 v2024.5*).
   - Depósito de dados brutos: número de acesso em repositórios públicos (NCBI GenBank, BioProject, SRA).
3. **Estudos de Campo e Coletas Ecológicas:**
   - Indique as coordenadas geográficas (datum WGS84 ou SIRGAS 2000), o bioma, o clima (classificação de Köppen-Geiger) e as datas das expedições.
   - Informe a licença ambiental de coleta concedida pelo órgão competente (ex.: SISBIO/ICMBio) e o herbário ou coleção zoológica de depósito do material-testemunho (*voucher*).
4. **Relato Estatístico Rigoroso:**
   - Apresente estimativas com médias $\pm$ desvio-padrão ou medianas com intervalo interquartil.
   - Nível de significância definido *a priori* ($\alpha = 0,05$), com valores de $p$ exatos (ex.: $p = 0,032$; $p < 0,001$). Veda-se a expressão "tendência à significância" para $p > 0,05$.
