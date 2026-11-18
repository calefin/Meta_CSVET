# Guia de Publicação na Google Play Store - ProvaGen Cell

Este documento contém todas as informações de metadados, textos de loja, respostas para questionários de privacidade e segurança de dados, bem como os termos legais necessários para aprovação do aplicativo **ProvaGen Cell** no Google Play Console.

A pasta [metadata/screenshots/](file:///home/calefi/Documentos/provagen_cell/metadata/screenshots/) contém as capturas de tela geradas em alta resolução para celular e tablet.

---

## 📝 1. Metadados da Loja (Store Listing)

### 🏷️ Nome do Aplicativo (Máx. 30 caracteres)
`ProvaGen Cell`

### 📝 Descrição Curta (Máx. 80 caracteres)
`Correção rápida de gabaritos por câmera e sincronização com o ProvaGen.`

### 📖 Descrição Completa (Máx. 4000 caracteres)
```text
O ProvaGen Cell é a ferramenta definitiva para professores e instituições de ensino que buscam agilizar a correção de provas e avaliações de múltipla escolha. Desenvolvido para atuar de forma integrada à plataforma ProvaGen, o aplicativo utiliza inteligência de processamento de imagens (OMR) para ler cartões-resposta instantaneamente pela câmera do celular.

Principais Recursos:
- Correção Instantânea por Câmera: Posicione o celular sobre a folha de respostas e obtenha o resultado e a nota calculada em menos de 1 segundo.
- Processamento Offline: Não tem internet em sala de aula? Corrija todas as provas sem conexão. Os dados são salvos em banco local seguro e sincronizados automaticamente quando houver sinal.
- Análise de Desempenho Visual: Visualize na tela as marcações corretas (em verde) e os erros do aluno (em vermelho) desenhados diretamente no gabarito digitalizado.
- Sincronização em Nuvem: Importe turmas, alunos e exames criados no portal web e envie as notas corrigidas instantaneamente para o seu diário eletrônico.
- Suporte a Questões Anuladas: Gerenciamento dinâmico de chaves de resposta com redistribuição automática de pontuação.
- Segurança de Dados de Menores: Fotos e informações dos alunos são mantidas em ambiente sandbox criptografado, sem exposição na galeria do celular.

Transforme a gestão de avaliações da sua escola. Reduza o tempo gasto com correções manuais e foque no que realmente importa: a aprendizagem de seus alunos.

Aviso: Para utilizar este aplicativo móvel, é necessário ter uma conta de professor ativa na plataforma ProvaGen.
```

---

## 🔒 2. Segurança dos Dados (Data Safety)

Ao preencher o questionário obrigatório da Google Play Store, utilize as seguintes respostas:

1. **O app coleta ou compartilha algum dos tipos de dados de usuário exigidos?**
   - Resposta: **Sim**
2. **Todos os dados coletados por este app são criptografados em trânsito?**
   - Resposta: **Sim** (Toda a comunicação com a API em `prof-gen.com` é feita estritamente via HTTPS utilizando TLS).
3. **Você oferece um método para os usuários solicitarem a exclusão dos dados deles?**
   - Resposta: **Sim** (Através do portal do professor, aba de Perfil -> Solicitar Exclusão da Conta).

### Declaração de Tipos de Dados Coletados:
- **Informações Pessoais**:
  - *Nome*: Coletado para identificação do professor e dos alunos da turma. (Finalidade: Funcionalidade do App).
  - *Endereço de e-mail*: Coletado para login e autenticação do professor. (Finalidade: Autenticação e Gestão de Contas).
- **Arquivos e Documentos**:
  - *Fotos/Imagens*: Fotos das folhas de resposta são digitalizadas localmente para processamento OMR. (Finalidade: Funcionalidade do App).
  - *Notas e Avaliações*: Pontuação final do aluno. (Finalidade: Funcionalidade do App).

---

## 🎯 3. Público-Alvo e Conteúdo (Target Audience)

- **Classificação Indicativa**: Livre (L) ou classificação equivalente.
- **Faixa Etária Alvo**: **Mais de 18 anos** (Aplicativo voltado para uso profissional de professores e administradores escolares).
- **Fins de Marketing para Crianças**: **Não** (O aplicativo não é direcionado a crianças).

---

## 📁 4. Imagens da Loja (Screenshots)

Os arquivos de imagem na pasta `metadata/screenshots` atendem aos padrões de proporção exigidos pelo Google Play Console:
1. **[phone_dashboard.png](file:///home/calefi/Documentos/provagen_cell/metadata/screenshots/phone_dashboard.png)**: Tela inicial no celular em Dark Mode mostrando a lista de turmas e andamento de sincronizações das avaliações recentes.
2. **[phone_scanner.png](file:///home/calefi/Documentos/provagen_cell/metadata/screenshots/phone_scanner.png)**: Tela da câmera capturando e corrigindo em tempo real uma folha de respostas com círculos verdes e vermelhos indicando acertos/erros e caixa de notas inferior.
3. **[tablet_dashboard.png](file:///home/calefi/Documentos/provagen_cell/metadata/screenshots/tablet_dashboard.png)**: Visualização em tablet (modo paisagem) contendo o painel analítico de notas da turma (histograma, médias de desempenho por tópicos e lista detalhada).

---

## ⚖️ 5. Links Legais para Aplicar em `prof-gen.com`

Crie as seguintes URLs estáticas no seu site institucional `prof-gen.com`:

*   **Política de Privacidade**: `https://prof-gen.com/privacy-policy`
*   **Termos de Serviço**: `https://prof-gen.com/terms-of-service`

Use os textos abaixo para alimentar essas páginas:

---

### 📄 Texto 5.1: Política de Privacidade
*(Copie o conteúdo abaixo para publicar em `https://prof-gen.com/privacy-policy`)*

```markdown
# Política de Privacidade - ProvaGen (prof-gen.com)

A plataforma ProvaGen, operada por meio do site prof-gen.com e do aplicativo móvel ProvaGen Cell, está comprometida com a proteção de dados de seus usuários, em estrita conformidade com a Lei Geral de Proteção de Dados (LGPD - Lei nº 13.709/2018) e regulamentos educacionais vigentes.

## 1. Informações que Coletamos
Para viabilizar a prestação de serviços pedagógicos de correção automática de provas, processamos:
- **Dados do Professor**: Nome completo, e-mail institucional e senha de acesso criptografada.
- **Dados dos Alunos**: Nome do estudante, Registro de Matrícula (RM), turma atribuída e notas de avaliações.
- **Dados de Imagem**: Capturas fotográficas de cartões-resposta feitas pela câmera do dispositivo móvel.

## 2. Tratamento e Armazenamento das Fotos de Provas
- As fotos capturadas pela câmera no aplicativo móvel ProvaGen Cell são processadas exclusivamente para extração automática de dados através de inteligência computacional (OMR).
- As imagens geradas são mantidas em diretório sandbox privado de segurança no dispositivo do professor e não são expostas na galeria pública de imagens do celular, resguardando a privacidade e a imagem física do estudante.

## 3. Compartilhamento de Dados com Terceiros
- A ProvaGen **NÃO compartilha, não vende, não aluga e não licencia** quaisquer dados de alunos, professores ou estatísticas de turmas com parceiros comerciais, anunciantes ou terceiros.
- A transferência de informações ocorre unicamente entre o aplicativo móvel autorizado e a base de dados segura e criptografada (HTTPS/TLS) sob gestão exclusiva do professor usuário da plataforma.

## 4. Retenção e Exclusão de Dados
- O professor pode remover localmente os dados do aplicativo a qualquer momento utilizando o botão "Resetar Dados" na aba de configurações.
- A exclusão completa e definitiva do cadastro do professor e de seus históricos de turmas na nuvem pode ser solicitada diretamente no painel web ou através do canal de suporte pelo e-mail: contato@prof-gen.com.

---
Última modificação: 5 de junho de 2026.
```

---

### 📄 Texto 5.2: Termos de Serviço
*(Copie o conteúdo abaixo para publicar em `https://prof-gen.com/terms-of-service`)*

```markdown
# Termos de Serviço - ProvaGen (prof-gen.com)

Bem-vindo ao ProvaGen. Ao acessar nosso site (prof-gen.com) ou utilizar o aplicativo ProvaGen Cell, você concorda com estes termos.

## 1. Finalidade do Serviço
O ProvaGen é uma plataforma tecnológica de apoio educacional que permite a criação de exames e a correção automática de gabaritos de múltipla escolha via câmera fotográfica de dispositivos móveis.

## 2. Responsabilidades do Usuário (Professor)
- O usuário é o único responsável pela custódia de suas credenciais de login e pela autenticidade dos dados acadêmicos inseridos.
- A ferramenta de digitalização OMR destina-se a atuar como um recurso auxiliar na apuração de notas. Recomenda-se que o docente faça a auditoria e verificação manual em caso de rasuras acentuadas feitas a caneta ou lápis pelos alunos na folha física de respostas.

## 3. Planos e Assinaturas
- O acesso às ferramentas de sincronização com a nuvem e correção automatizada ilimitada via câmera do aplicativo celular está associado a planos de assinatura contratados junto à plataforma.
- A expiração ou cancelamento da assinatura resultará na limitação automática dos serviços móveis até a efetiva regularização dos pagamentos.

## 4. Limitação de Responsabilidade
A plataforma ProvaGen não se responsabiliza por perdas locais de avaliações não sincronizadas resultantes de falhas de hardware do dispositivo móvel, furto do aparelho, formatação do sistema operacional ou desinstalação incorreta do aplicativo.

---
Última modificação: 5 de junho de 2026.
```
