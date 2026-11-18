# Regras e Memórias do Projeto (Ìjọ Ifá Èjìogbè)

## 1. Cadastro via Google (Evitar Erro 406)
* **Regra:** Não use o prefixo `/api` em rotas de callback de autenticação social (como Google OAuth) que são acessadas via navegador/GET. Algumas hospedagens ou WAFs retornam `406 Not Acceptable` para requisições sob `/api/`.
* **Solução padrão:** Utilize a rota `/auth/google/callback` e mantenha a variável de ambiente `GOOGLE_REDIRECT_URI` apontando para este caminho no servidor de produção.

## 2. Acessibilidade em Campos de Data de Aniversário
* **Regra:** Em formulários de cadastro e solicitação de consulta, evite o uso do seletor nativo de data (`type="date"`), pois dificulta a digitação de anos distantes para pessoas de idade avançada.
* **Solução padrão:** Use campos de entrada do tipo texto (`type="text"`), com o marcador de formato brasileiro (`placeholder="DD/MM/AAAA"`) e limite de caracteres (`maxlength="10"`). 
* **Backend:** Implemente o método de auxílio `parseDate()` no componente do Livewire para converter o valor digitado de `DD/MM/AAAA` para `YYYY-MM-DD` antes de disparar a validação e salvar no banco de dados.

## 3. Fluxo de Confirmação Pós-Cadastro
* **Regra:** Após a submissão de novos cadastros (comum ou via Google), o usuário deve ser redirecionado para a tela de login com uma mensagem de status em flash clara, evitando confusões de formulários que permanecem na mesma página.
* **Mensagens:**
  * **Google:** Notificar que o cadastro foi concluído e aguarda aprovação da administração.
  * **Comum:** Orientar a verificação da caixa de entrada/spam para confirmação do e-mail, seguido da aprovação do administrador.

## 4. Logística do Calendário Òsé Ifá
* **Regra:** A semana litúrgica Ọ̀sẹ̀ tem 4 dias, ancorada em **18/06/2026** = Ọjọ́ Ọ̀sẹ̀ Ifá (`OseCalendar::forDate`, `$diff % 4`).
* Sobre essa base roda o ciclo Òrún Awó → Ìsán Awó → Ìtádógún, ancorado em `LiturgicalSetting::orun_anchor` (editável pelo admin, sempre um dia de Ọjọ́ Ọ̀sẹ̀ Ifá). O período real Òrún-a-Òrún é de **16 dias** (não 17 — 17 não é múltiplo de 4, então contar em blocos de 17 desalinha os marcos da fase Ifá a partir do segundo ciclo). Offsets a partir da âncora:
  * offset 0 → **Òrún Awó** (exibido como dia 5 do ciclo de 17)
  * offset 4 → **Ìsán Awó** (dia 9)
  * offset 12 → **Ìtádógún** (dia 17, fecha o ciclo — e dobra como dia 1 do próximo ciclo, já que o próximo Òrún Awó cai 4 dias depois)
* O cálculo usa `$diff % 16` (`OseCalendar::cycleOffset`), não `% 17`. `OseCalendar::cycleDay()` converte o offset pro número de exibição "dia X de 17"; `OseCalendar::daysUntil()` calcula a contagem regressiva pros marcos.

## 5. Fluxo do Curso Contínuo no Telegram e Gestão de Mensalidades
* **Regra:** A inscrição do curso no Telegram (R$ 180,00/mês) é realizada via `/curso-telegram` com aceite obrigatório dos termos de escopo litúrgico, presença e materiais.
* **Modelo & Status:** A tabela `telegram_course_subscriptions` gerencia adimplência. Ao confirmar pagamento (Mercado Pago ou manual `+30d Pago`), `PaymentFulfillment` ativa a assinatura e renova `expires_at` em +30 dias.
* **Bot Telegram & Notificações:**
  * Comandos no Bot: `/curso` e `/mensalidade` para alunos; `/statuscurso` exclusivo do admin.
  * O Bot dispara notificação instantânea para o admin ao confirmar cada pagamento e permite gerar links de convite únicos de 1 uso (`createChatInviteLink`).
* **Automações e Recibos:**
  * O comando `subscriptions:send-reminders` roda diariamente no scheduler às 09:00 disparando lembretes com link MP por e-mail.
  * Recibos em PDF de cada mensalidade quitada podem ser baixados em `/mensalidades/{payment}/recibo`.

