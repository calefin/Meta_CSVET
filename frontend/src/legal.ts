/**
 * Privacy Policy and Terms of Use (D-32). Versioned: bump LEGAL_VERSION (and backend/src/auth.ts)
 * on any relevant change; signed-in users are asked to accept the new version.
 * Written in English and Portuguese; other languages fall back to English.
 */
import { SITE } from './site';

export const LEGAL_VERSION = '2026-11-04';

export interface LegalSection {
  title: string;
  body: string[];
}

export interface LegalDoc {
  title: string;
  updated: string;
  sections: LegalSection[];
}

const contact = SITE.contactEmail;

const privacy: Record<'en' | 'pt-BR', LegalDoc> = {
  'pt-BR': {
    title: 'Política de Privacidade',
    updated: `Versão ${LEGAL_VERSION}`,
    sections: [
      {
        title: '1. Quem somos',
        body: [
          `O BagParser (https://bagparser.com) é um site de fã de Tibia mantido por ${SITE.owner}, que é o controlador dos dados e o encarregado (DPO) para fins da LGPD (Lei 13.709/2018) e do GDPR. Contato: ${contact}.`,
        ],
      },
      {
        title: '2. O que coletamos e por quê',
        body: [
          'Sem conta: o parser funciona no seu aparelho. O log que você cola fica salvo apenas no armazenamento local do navegador e não é enviado ao servidor.',
          'Conta: e-mail, nome de exibição, personagem do Tibia (opcional), hash da senha (nunca a senha), data de cadastro, último login e a versão destes termos que você aceitou. Base legal: execução do serviço que você pediu (art. 7º, V, LGPD; art. 6(1)(b) GDPR).',
          'Login com Google ou Discord (opcional): guardamos o identificador da sua conta nesse serviço e o e-mail informado por ele, para reconhecer você nos próximos acessos. Recebemos apenas identificador, e-mail e nome público; nunca a sua senha do Google/Discord, contatos ou outros dados. Você pode desvincular em Minha conta. Base legal: execução do serviço que você pediu.',
          'Sessões: um cookie técnico (bp_session, HttpOnly, 30 dias) e, para segurança, o IP e o navegador de cada sessão ativa. Base legal: legítimo interesse em proteger as contas (art. 7º, IX; art. 6(1)(f)).',
          'Personagens que você verifica: dados públicos do tibia.com (level, experiência, mortes, guild, último login, achievement points), gravados a cada atualização para montar seu histórico e suas metas.',
          'Relatos de Yasir: mundo, dia e se você viu ou não o Yasir, ligados à sua conta para evitar votos repetidos.',
          'Linhas de loot enviadas ao BossLedger: o texto da linha e um identificador anônimo (hash com sal, sem IP em claro) para impedir envios duplicados.',
          'Estatísticas de visitas: apenas totais por dia e por seção. Visitantes únicos são estimados com um hash diário descartável; nenhum IP é armazenado para isso. Não usamos cookies próprios de rastreamento ou de redes sociais (os anúncios do plano grátis são do Google, ver item 4).',
        ],
      },
      {
        title: '3. Armazenamento local do navegador',
        body: [
          'Guardamos no seu aparelho: idioma, mundo escolhido, último log colado, estado do Yasir do dia e preferências de exibição. Você pode apagar a qualquer momento limpando os dados do site no navegador. Esses itens são necessários para o funcionamento pedido por você e não exigem consentimento.',
        ],
      },
      {
        title: '4. Com quem compartilhamos',
        body: [
          'Não vendemos nem alugamos dados pessoais. O site roda em um servidor contratado (VPS) e usa HTTPS. Para dados do jogo consultamos o TibiaData e o TibiaWiki apenas com nomes públicos de personagens e mundos; nenhum dado da sua conta é enviado a eles.',
          'Imagens de criaturas e itens são carregadas de static.tibia.com (CipSoft) e, como reserva, do servidor de imagens do TibiaWiki (static.wikia.nocookie.net, Fandom); o seu navegador faz essas requisições diretamente, sem enviar a página de origem, e elas ficam em cache no aparelho.',
          'Se você escolher entrar com Google (Google LLC) ou Discord (Discord Inc.), o seu navegador é levado à página de login deles, que trata os dados conforme as próprias políticas: https://policies.google.com/privacy e https://discord.com/privacy. Durante esse login usamos um cookie técnico temporário (bp_oauth, 10 minutos) para proteger contra falsificação de requisições.',
          'Proteção contra robôs no cadastro e no login: Cloudflare Turnstile (Cloudflare Inc.), que recebe dados técnicos do navegador para essa verificação: https://www.cloudflare.com/privacypolicy/.',
          'Pagamentos do plano Pro: Stripe (Stripe Inc.), que recebe os dados do cartão diretamente na página dele; nós guardamos só o identificador do cliente, a situação da assinatura e os valores pagos: https://stripe.com/privacy.',
          'E-mails da conta (confirmação de e-mail, redefinição de senha, avisos de segurança) e respostas do suporte saem do nosso servidor de e-mail (domínio csvet.app). Não enviamos propaganda por e-mail.',
          'Anúncios: visitantes e contas do plano grátis podem ver anúncios do Google AdSense (Google LLC), sempre identificados como “Publicidade”. O Google pode usar cookies para exibir e medir os anúncios conforme a escolha de consentimento mostrada por ele; assinantes Pro não veem anúncios: https://policies.google.com/technologies/ads.',
        ],
      },
      {
        title: '5. Transferência internacional',
        body: [
          'O servidor e os serviços consultados podem estar fora do Brasil ou da União Europeia. Nesses casos a transferência é necessária para prestar o serviço que você pediu (art. 33, LGPD; art. 49(1)(b) GDPR) e aplicamos as medidas de segurança descritas abaixo.',
        ],
      },
      {
        title: '6. Por quanto tempo guardamos',
        body: [
          'Conta, personagens, histórico e relatos: até você excluir a conta ou o personagem. Sessões: até sair ou 30 dias sem uso. Links de nova senha: 48 horas. Estatísticas de visitas: totais anônimos sem prazo. Registro de ações de administradores: até 2 anos, para segurança.',
        ],
      },
      {
        title: '7. Seus direitos',
        body: [
          'Você pode: confirmar e acessar seus dados, corrigir (página Minha conta), baixar uma cópia completa em JSON (portabilidade), excluir a conta e tudo ligado a ela, saber com quem compartilhamos e revogar consentimentos. Use a página Minha conta ou escreva para ' +
            contact +
            '. Respondemos em até 15 dias. Você também pode reclamar à ANPD (Brasil) ou à autoridade de proteção de dados do seu país na UE.',
        ],
      },
      {
        title: '8. Segurança',
        body: [
          'Senhas com scrypt e sal individual, tokens de sessão guardados só como hash, cookies HttpOnly/SameSite, HTTPS, limites de tentativas, proteção contra requisições forjadas e registro das ações de administradores. Em caso de incidente relevante, avisaremos os afetados e a ANPD conforme a lei.',
        ],
      },
      {
        title: '9. Crianças e adolescentes',
        body: [
          'O parser não exige conta. Para criar conta é preciso ter 13 anos ou mais (ou a idade mínima do seu país na UE); menores de 18 anos devem ter ciência e autorização do responsável. Se soubermos de uma conta de criança sem consentimento do responsável, ela será excluída.',
        ],
      },
      {
        title: '10. Mudanças',
        body: [
          'Esta política tem versão e data. Quando houver mudança relevante, mostraremos o aviso no próximo acesso e pediremos sua concordância com a nova versão. O histórico de versões fica no CHANGELOG do projeto.',
        ],
      },
    ],
  },
  en: {
    title: 'Privacy Policy',
    updated: `Version ${LEGAL_VERSION}`,
    sections: [
      {
        title: '1. Who we are',
        body: [
          `BagParser (https://bagparser.com) is a Tibia fansite run by ${SITE.owner}, who is the data controller and data protection contact under the Brazilian LGPD and the EU GDPR. Contact: ${contact}.`,
        ],
      },
      {
        title: '2. What we collect and why',
        body: [
          'Without an account: the parser runs on your device. The log you paste is kept only in your browser storage and is never sent to our server.',
          'Account: e-mail, display name, Tibia character (optional), password hash (never the password), sign-up date, last login and the version of these terms you accepted. Legal basis: performing the service you asked for (GDPR art. 6(1)(b); LGPD art. 7, V).',
          'Sign in with Google or Discord (optional): we store your account identifier at that service and the e-mail it reports, to recognise you on later visits. We only receive the identifier, e-mail and public name; never your Google/Discord password, contacts or other data. You can unlink it in My account. Legal basis: performing the service you asked for.',
          'Sessions: one technical cookie (bp_session, HttpOnly, 30 days) and, for security, the IP address and browser of each active session. Legal basis: legitimate interest in protecting accounts (art. 6(1)(f); LGPD art. 7, IX).',
          'Characters you verify: public tibia.com data (level, experience, deaths, guild, last login, achievement points), stored on each update to build your history and goals.',
          'Yasir reports: world, day and whether you saw him, linked to your account to prevent repeated votes.',
          'Loot lines sent to BossLedger: the line text and an anonymous identifier (salted hash, no plain IP) to stop duplicates.',
          'Visit statistics: only totals per day and section. Unique visitors are estimated with a daily disposable hash; no IP address is stored for this. We set no tracking or social-media cookies of our own (free-plan ads come from Google, see section 4).',
        ],
      },
      {
        title: '3. Browser storage',
        body: [
          'We keep on your device: language, chosen world, last pasted log, today\'s Yasir state and display preferences. You can erase them any time by clearing the site data in your browser. They are needed for features you use and do not require consent.',
        ],
      },
      {
        title: '4. Who we share with',
        body: [
          'We never sell or rent personal data. The site runs on a rented server (VPS) over HTTPS. For game data we query TibiaData and TibiaWiki only with public character and world names; none of your account data is sent to them.',
          'Creature and item images load from static.tibia.com (CipSoft) and, as a fallback, from the TibiaWiki image server (static.wikia.nocookie.net, Fandom); your browser requests them directly, without sending the referring page, and caches them on your device.',
          'If you choose to sign in with Google (Google LLC) or Discord (Discord Inc.), your browser is sent to their sign-in page, which handles data under their own policies: https://policies.google.com/privacy and https://discord.com/privacy. During that sign-in we use a temporary technical cookie (bp_oauth, 10 minutes) to protect against request forgery.',
          'Bot protection on sign-up and sign-in: Cloudflare Turnstile (Cloudflare Inc.), which receives technical browser data for that check: https://www.cloudflare.com/privacypolicy/.',
          'Pro plan payments: Stripe (Stripe Inc.), which receives card details directly on its own page; we keep only the customer id, the subscription status and the amounts paid: https://stripe.com/privacy.',
          'Account e-mails (e-mail confirmation, password reset, security notices) and support replies are sent by our mail server (csvet.app domain). We never send marketing e-mails.',
          'Ads: visitors and free accounts may see Google AdSense ads (Google LLC), always labelled “Advertisement”. Google may use cookies to show and measure ads according to the consent choice it displays; Pro subscribers see no ads: https://policies.google.com/technologies/ads.',
        ],
      },
      {
        title: '5. International transfers',
        body: [
          'The server and the services we query may be outside Brazil or the EU. Such transfers are necessary to provide the service you request (GDPR art. 49(1)(b); LGPD art. 33) and are protected by the measures below.',
        ],
      },
      {
        title: '6. How long we keep data',
        body: [
          'Account, characters, history and reports: until you delete the account or the character. Sessions: until you sign out or 30 days without use. Password-reset links: 48 hours. Visit statistics: anonymous totals with no limit. Administrator action log: up to 2 years, for security.',
        ],
      },
      {
        title: '7. Your rights',
        body: [
          'You can access your data, correct it (My account page), download a full JSON copy (portability), delete your account and everything linked to it, learn who we share with, and withdraw consent. Use the My account page or write to ' +
            contact +
            '. We answer within 15 days. You may also complain to the ANPD (Brazil) or your EU data protection authority.',
        ],
      },
      {
        title: '8. Security',
        body: [
          'Passwords hashed with scrypt and a unique salt, session tokens stored only as hashes, HttpOnly/SameSite cookies, HTTPS, attempt limits, cross-site request protection and an administrator audit log. If a relevant incident happens we will inform the people affected and the authorities as the law requires.',
        ],
      },
      {
        title: '9. Children and teenagers',
        body: [
          'The parser needs no account. To create an account you must be 13 or older (or the minimum age in your EU country); under-18s need their parent or guardian\'s awareness and permission. Accounts of children without parental consent are deleted when we learn about them.',
        ],
      },
      {
        title: '10. Changes',
        body: [
          'This policy is versioned and dated. After a relevant change we show a notice on your next visit and ask you to accept the new version. The version history is kept in the project CHANGELOG.',
        ],
      },
    ],
  },
};

const terms: Record<'en' | 'pt-BR', LegalDoc> = {
  'pt-BR': {
    title: 'Termos de Uso',
    updated: `Versão ${LEGAL_VERSION}`,
    sections: [
      {
        title: '1. O serviço',
        body: [
          'O BagParser oferece ferramentas gratuitas para jogadores de Tibia: parser de loot, roteiro de venda, divisão de party, SpawnRank, BossLedger, guias e acompanhamento de personagens. É um projeto de fã, sem vínculo com a CipSoft GmbH.',
        ],
      },
      {
        title: '2. Conta',
        body: [
          'Você é responsável pela sua senha e pelas ações na sua conta. Informe dados verdadeiros e mantenha o e-mail atualizado. Idade mínima e regras para menores estão na Política de Privacidade.',
        ],
      },
      {
        title: '3. Personagens',
        body: [
          'Só verifique personagens que você controla. A verificação usa um código no comentário público do personagem; se outra pessoa provar o controle depois (por exemplo, após uma venda), o acompanhamento passa para ela.',
        ],
      },
      {
        title: '4. Uso aceitável',
        body: [
          'As Regras do Tibia também valem aqui. É proibido: usar o site para divulgar ferramentas de trapaça, negociar contas, personagens, itens ou moedas do jogo por dinheiro real, enviar dados falsos de propósito (por exemplo, relatos ou drops inventados), sobrecarregar o serviço com acessos automatizados ou tentar contornar a segurança. Contas que violarem estas regras podem ser suspensas ou excluídas.',
        ],
      },
      {
        title: '5. Propriedade intelectual',
        body: [
          'Tibia e todo conteúdo do site oficial ou fornecido pela CipSoft, como imagens, são de propriedade da CipSoft GmbH. Dados de itens e NPCs vêm do TibiaWiki (CC BY-SA) e mapas do projeto tibiamaps (MIT). Créditos ficam na página Sobre.',
          'O código, o nome e o logo BagParser, o design e os textos e guias originais são protegidos (todos os direitos reservados). É proibido copiá-los ou republicá-los, inclusive por coleta automatizada, sem autorização escrita. Você pode criar links para qualquer página e citar trechos curtos com crédito e link. Dados derivados do TibiaWiki continuam sob CC BY-SA.',
        ],
      },
      {
        title: '6. Precisão',
        body: [
          'Preços, rotas, previsões de level, rotação de bosses e relatos da comunidade são estimativas. Confira no jogo antes de decisões importantes. O serviço é oferecido "como está", sem garantia de disponibilidade contínua.',
        ],
      },
      {
        title: '7. Planos pagos',
        body: [
          'Hoje não há cobrança. Se um plano Premium for lançado, preço, benefícios, renovação e cancelamento serão informados antes da compra, respeitando o Código de Defesa do Consumidor, incluindo o direito de arrependimento de 7 dias (art. 49).',
        ],
      },
      {
        title: '8. Responsabilidade',
        body: [
          'Na medida permitida pela lei, não respondemos por perdas no jogo decorrentes do uso das informações do site. Nada nestes termos limita direitos que a lei garante ao consumidor.',
        ],
      },
      {
        title: '9. Encerramento',
        body: [
          'Você pode excluir sua conta a qualquer momento em Minha conta. Podemos suspender contas que violem estes termos, informando o motivo quando possível.',
        ],
      },
      {
        title: '10. Lei aplicável e mudanças',
        body: [
          'Aplica-se a lei brasileira; consumidores mantêm o foro do seu domicílio. Estes termos são versionados; mudanças relevantes serão avisadas e exigirão novo aceite. Contato: ' + contact + '.',
        ],
      },
    ],
  },
  en: {
    title: 'Terms of Use',
    updated: `Version ${LEGAL_VERSION}`,
    sections: [
      {
        title: '1. The service',
        body: [
          'BagParser offers free tools for Tibia players: loot parser, sell route, party split, SpawnRank, BossLedger, guides and character tracking. It is a fan project and is not affiliated with CipSoft GmbH.',
        ],
      },
      {
        title: '2. Account',
        body: [
          'You are responsible for your password and for actions on your account. Provide accurate information and keep your e-mail up to date. Minimum age and rules for minors are in the Privacy Policy.',
        ],
      },
      {
        title: '3. Characters',
        body: [
          'Only verify characters you control. Verification uses a code in the character\'s public comment; if someone else proves control later (for example after a sale), tracking moves to them.',
        ],
      },
      {
        title: '4. Acceptable use',
        body: [
          'The Tibia Rules apply here too. You must not: promote cheat tools, trade accounts, characters, items or game currency for real money, knowingly submit false data (such as invented reports or drops), overload the service with automated requests, or try to bypass security. Accounts that break these rules may be suspended or deleted.',
        ],
      },
      {
        title: '5. Intellectual property',
        body: [
          'Tibia and all content from the official website or provided by CipSoft, such as images, belong to CipSoft GmbH. Item and NPC data come from TibiaWiki (CC BY-SA) and maps from the tibiamaps project (MIT). Credits are on the About page.',
          'The BagParser code, name and logo, design and original texts and guides are protected (all rights reserved). Copying or republishing them, including by automated scraping, requires written permission. You may link to any page and quote short excerpts with credit and a link. Data derived from TibiaWiki remains under CC BY-SA.',
        ],
      },
      {
        title: '6. Accuracy',
        body: [
          'Prices, routes, level forecasts, boss rotations and community reports are estimates. Check in game before important decisions. The service is provided "as is", without a guarantee of uninterrupted availability.',
        ],
      },
      {
        title: '7. Paid plans',
        body: [
          'There are no charges today. If a Premium plan launches, price, benefits, renewal and cancellation will be shown before purchase, respecting consumer law, including the right of withdrawal where it applies (7 days in Brazil, 14 days in the EU).',
        ],
      },
      {
        title: '8. Liability',
        body: [
          'To the extent allowed by law, we are not liable for in-game losses resulting from using the information on this site. Nothing in these terms limits rights that consumer law grants you.',
        ],
      },
      {
        title: '9. Termination',
        body: [
          'You can delete your account at any time on My account. We may suspend accounts that break these terms, stating the reason when possible.',
        ],
      },
      {
        title: '10. Governing law and changes',
        body: [
          'Brazilian law applies; consumers keep the courts of their place of residence and any mandatory protections of their country. These terms are versioned; relevant changes will be announced and require a new acceptance. Contact: ' +
            contact +
            '.',
        ],
      },
    ],
  },
};

export function legalDoc(kind: 'privacy' | 'terms', locale: string): LegalDoc {
  const docs = kind === 'privacy' ? privacy : terms;
  return locale === 'pt-BR' ? docs['pt-BR'] : docs.en;
}
