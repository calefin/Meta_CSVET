import { useEffect, useState } from 'react';
import { ApiError, billing, notify } from '../api';
import { PromoBox } from '../components/PromoBox';
import { PixCheckout } from '../components/PixCheckout';
import { useI18n } from '../i18n';
import { useAuth } from '../auth';
import { SITE } from '../site';
import { linkHandler, navigate } from '../router';

export function PricingPage() {
  const { locale, t } = useI18n();
  const { user } = useAuth();
  const isPt = locale.startsWith('pt');

  const [currency, setCurrency] = useState<'BRL' | 'USD'>(isPt ? 'BRL' : 'USD');
  const [billingPeriod, setBillingPeriod] = useState<'monthly' | 'yearly'>('monthly');

  const isBrl = currency === 'BRL';
  const [discount, setDiscount] = useState<{ pct: number; code: string } | null>(null);
  const [enabled, setEnabled] = useState<boolean | null>(null);
  const [busy, setBusy] = useState(false);
  const [payMsg, setPayMsg] = useState('');
  const cancelled = new URLSearchParams(window.location.search).get('checkout') === 'cancel';
  // D-69: Pix (Mercado Pago) next to the card; blocked while a card subscription is live
  const [pixOn, setPixOn] = useState(false);
  const [pixOpen, setPixOpen] = useState(false);
  // D-76: one-off support through the Telegram bot (Stars or Pix)
  const [bot, setBot] = useState<string | null>(null);
  useEffect(() => {
    notify
      .telegramInfo()
      .then((r) => setBot(r.enabled ? r.username : null))
      .catch(() => null);
  }, []);
  const [cardLive, setCardLive] = useState(false);
  useEffect(() => {
    billing
      .status()
      .then((s) => setEnabled(s.enabled))
      .catch(() => setEnabled(false));
    billing
      .pixInfo()
      .then((p) => setPixOn(p.enabled))
      .catch(() => null);
  }, []);
  useEffect(() => {
    if (!user) return;
    billing
      .me()
      .then((m) => setCardLive(['active', 'trialing', 'past_due'].includes(m.status ?? '')))
      .catch(() => null);
  }, [user]);
  const pixButton = isBrl && pixOn && !cardLive && (
    <button type="button" className="button pix-button" onClick={() => (user ? setPixOpen(true) : navigate('/register?next=/pricing'))}>
      ⚡ {user?.premium ? (isPt ? 'Adicionar dias com Pix' : 'Add days with Pix') : isPt ? 'Pagar com Pix' : 'Pay with Pix'} ·{' '}
      {billingPeriod === 'monthly' ? (isPt ? '30 dias' : '30 days') : isPt ? '1 ano' : '1 year'}
    </button>
  );

  /** Stripe Checkout (D-51): cards in BRL or USD, Apple Pay / Google Pay — Stripe shows what fits the buyer */
  const subscribe = async () => {
    if (!user) {
      navigate('/register?next=/pricing');
      return;
    }
    setBusy(true);
    setPayMsg('');
    try {
      const r = await billing.checkout(billingPeriod, isBrl ? 'brl' : 'usd', discount?.code);
      window.location.href = r.url;
    } catch (err) {
      const code = err instanceof ApiError ? err.code : 'error';
      setPayMsg(
        code === 'already_subscribed'
          ? isPt ? 'Você já tem uma assinatura ativa. Se você a cancelou, dá para reativar em Minha conta.' : 'You already have an active subscription. If you cancelled it, you can reactivate it in your account.'
          : code === 'billing_unavailable'
            ? isPt ? 'Pagamentos ainda não estão ativos. Volte em breve!' : 'Payments are not live yet. Check back soon!'
            : isPt ? 'Não foi possível abrir o pagamento. Tente de novo.' : 'Could not open the checkout. Please try again.',
      );
      setBusy(false);
    }
  };
  const proPrice = isBrl ? (billingPeriod === 'monthly' ? 9.9 : 89.9) : billingPeriod === 'monthly' ? 2.49 : 19.99;
  const money = (v: number) => (isBrl ? `R$ ${v.toFixed(2).replace('.', ',')}` : `${v.toFixed(2)}`);

  return (
    <div className="page pricing-page">
      <div style={{ textAlign: 'center', maxWidth: '720px', margin: '0 auto 2rem auto' }}>
        <h1 style={{ fontSize: '2rem', marginBottom: '0.5rem' }}>
          {isPt ? 'Apoie o BagParser e Seja Apoiador VIP' : 'Support BagParser & Unlock Supporter VIP'}
        </h1>
        <p className="muted" style={{ fontSize: '1.1rem' }}>
          {isPt
            ? 'O BagParser é mantido de forma independente para a comunidade tibiana. Sua assinatura cobre os custos de servidores, proxies de alta disponibilidade e desenvolvimento contínuo de novas ferramentas.'
            : 'BagParser is independently developed and maintained for the Tibia community. Your subscription covers server costs, high-availability proxies, and ongoing development of new tools.'}
        </p>

        {/* Currency Switcher */}
        <div style={{ display: 'inline-flex', alignItems: 'center', gap: '0.5rem', background: 'var(--color-bg-card, rgba(255,255,255,0.05))', padding: '0.35rem 0.6rem', borderRadius: '8px', marginTop: '1.25rem' }}>
          <span className="small muted">{isPt ? 'Moeda de pagamento:' : 'Currency:'}</span>
          <button
            type="button"
            className={`button small ${isBrl ? 'primary' : 'secondary'}`}
            onClick={() => setCurrency('BRL')}
          >
            🇧🇷 BRL (R$)
          </button>
          <button
            type="button"
            className={`button small ${!isBrl ? 'primary' : 'secondary'}`}
            onClick={() => setCurrency('USD')}
          >
            🌎 USD ($)
          </button>
        </div>

        {/* Billing Period Switcher */}
        <div style={{ marginTop: '1rem', display: 'flex', justifyContent: 'center', gap: '0.5rem' }}>
          <button
            type="button"
            className={`button small ${billingPeriod === 'monthly' ? 'primary' : 'secondary'}`}
            onClick={() => setBillingPeriod('monthly')}
          >
            {isPt ? 'Mensal' : 'Monthly'}
          </button>
          <button
            type="button"
            className={`button small ${billingPeriod === 'yearly' ? 'primary' : 'secondary'}`}
            onClick={() => setBillingPeriod('yearly')}
          >
            {isPt ? 'Anual (20% OFF)' : 'Yearly (20% OFF)'}
          </button>
        </div>
      </div>

      <div style={{ maxWidth: '420px', margin: '0 auto 1.5rem' }}>
        <PromoBox mode="check" onDiscount={(pct, code) => setDiscount({ pct, code })} />
        {discount && (
          <p className="muted small">
            {isPt ? `Código ${discount.code} aplicado: será usado no pagamento.` : `Code ${discount.code} applied: it will be used at checkout.`}
          </p>
        )}
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(270px, 1fr))', gap: '1.5rem', maxWidth: '1050px', margin: '0 auto' }}>
        {/* Free Plan */}
        <div className="card" style={{ display: 'flex', flexDirection: 'column' }}>
          <h2 style={{ marginTop: 0 }}>{isPt ? 'Plano Gratuito' : 'Free Player'}</h2>
          <div style={{ fontSize: '1.75rem', fontWeight: 'bold', margin: '0.5rem 0 1rem 0' }}>
            {isBrl ? 'R$ 0' : '$0'}
          </div>
          <p className="muted small">
            {isPt ? 'Acesso a todas as ferramentas essenciais para jogar no dia a dia.' : 'Essential tools to analyse loot and routes every day.'}
          </p>
          <ul className="plain-list bullets" style={{ flex: 1, margin: '1rem 0' }}>
            <li>{isPt ? "Loot Parser com divisão de party e roteiro de venda (NPCs, Rashid, Yasir)" : "Loot Parser with party split and selling route (NPCs, Rashid, Yasir)"}</li>
            <li>{isPt ? "Hunt Analyser: até 30 hunts salvas, ranking, comparação, metas e hunts públicas" : "Hunt Analyser: up to 30 saved hunts, ranking, comparison, goals and public hunts"}</li>
            <li>{isPt ? "Resumo do Server Log (a análise completa é Pro)" : "Server Log summary (the full analysis is Pro)"}</li>
            <li>{isPt ? "1 personagem (atualização manual)" : "1 character (manual refresh)"}</li>
            <li>{isPt ? "BossLedger, Soul Cores, Hunting Spawns, Onde dropar e Hoje no Tibia" : "BossLedger, Soul Cores, Hunting Spawns, Drop finder and Today in Tibia"}</li>
            <li>{isPt ? "Boletim diário do Tibia e resumo semanal no Telegram e no Discord" : "Daily Tibia bulletin and weekly summary on Telegram and Discord"}</li>
            <li>{isPt ? "Suporte pelo site e pelo Telegram" : "Support on the site and on Telegram"}</li>
          </ul>
          <a href="/register" onClick={linkHandler('/register')} className="button secondary" style={{ textAlign: 'center' }}>
            {user && !user.premium ? (isPt ? 'Plano Atual' : 'Current Plan') : (isPt ? 'Começar Grátis' : 'Get Started Free')}
          </a>
        </div>

        {/* Supporter VIP Plan */}
        <div className="card" style={{ display: 'flex', flexDirection: 'column', border: '2px solid var(--color-accent, #3b82f6)', position: 'relative' }}>
          <div style={{ position: 'absolute', top: '-12px', right: '16px', background: 'var(--color-accent, #3b82f6)', color: '#fff', padding: '2px 10px', borderRadius: '12px', fontSize: '0.75rem', fontWeight: 'bold' }}>
            ★ {isPt ? 'VIP PRO' : 'VIP PRO'}
          </div>
          <h2 style={{ marginTop: 0 }}>{isPt ? 'Apoiador Pro' : 'Supporter Pro'}</h2>
          <div style={{ fontSize: '1.75rem', fontWeight: 'bold', margin: '0.5rem 0 0.25rem 0' }}>
            {discount ? (
              <>
                <s className="muted" style={{ fontSize: '1rem', marginRight: '0.4rem' }}>{money(proPrice)}</s>
                {money(Math.round(proPrice * (100 - discount.pct)) / 100)}
              </>
            ) : (
              money(proPrice)
            )}
            <span style={{ fontSize: '0.9rem', fontWeight: 'normal', color: 'var(--color-muted)' }}>
              {billingPeriod === 'monthly' ? (isPt ? '/mês' : '/mo') : (isPt ? '/ano' : '/yr')}
            </span>
          </div>
          <p className="muted small">
            {isPt
              ? `Pagamento seguro pelo Stripe em ${isBrl ? 'reais' : 'dólares'} (cartão, Apple Pay, Google Pay). Cancele quando quiser.`
              : `Secure payment by Stripe in ${isBrl ? 'BRL' : 'USD'} (cards, Apple Pay, Google Pay). Cancel anytime.`}
          </p>

          <ul className="plain-list bullets" style={{ flex: 1, margin: '1rem 0' }}>
            <li><strong>{isPt ? "Análise completa do Server Log (XP e dano por minuto, drops por criatura, dano por fonte, supplies), salva com a hunt" : "Full Server Log analysis (XP and damage per minute, drops per creature, damage by source, supplies), saved with the hunt"}</strong></li>
            <li><strong>{isPt ? "Até 10 personagens rastreados automaticamente pelo servidor" : "Up to 10 characters tracked automatically by the server"}</strong></li>
            <li><strong>{isPt ? "Avisos na hora no Telegram/Discord: level, morte, meta e fim do cooldown de boss" : "Instant alerts on Telegram/Discord: level, death, goal and end of boss cooldown"}</strong></li>
            <li><strong>{isPt ? "Evolução dos personagens no boletim diário (XP do dia, média de 7 dias, previsão da meta)" : "Characters' progress in the daily bulletin (XP of the day, 7-day average, goal forecast)"}</strong></li>
            <li>{isPt ? "Ranking de XP da sua guild no grupo do Telegram ou canal do Discord" : "Your guild's XP ranking in the Telegram group or Discord channel"}</li>
            <li>{isPt ? "Até 5.000 hunts salvas" : "Up to 5,000 saved hunts"}</li>
            <li>{isPt ? "Sem anúncios" : "No ads"}</li>
            <li>{isPt ? "Selo Pro no cabeçalho" : "Pro badge in the header"}</li>
          </ul>

          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
            {user?.premium ? (
              <>
                <div className="button secondary" style={{ textAlign: 'center', borderColor: '#22c55e', color: '#22c55e', cursor: 'default' }}>
                  ✓ {isPt ? 'Plano Ativo (Apoiador VIP)' : 'Active Plan (VIP Supporter)'}
                </div>
                {pixButton}
              </>
            ) : (
              <>
                {pixButton}
                <button type="button" className="button primary" onClick={subscribe} disabled={busy || enabled === false}>
                  {busy ? '…' : isPt ? '💳 Assinar com Stripe' : '💳 Subscribe with Stripe'}
                </button>
                {enabled === false && !pixOn && (
                  <span className="muted small">{isPt ? 'Pagamentos em breve.' : 'Payments coming soon.'}</span>
                )}
                {pixOn && isBrl && (
                  <span className="muted small">
                    {isPt
                      ? 'Pix: pagamento único pelo Mercado Pago, sem renovação automática. Cartão: assinatura pelo Stripe, renova sozinha e você cancela quando quiser.'
                      : 'Pix: one-time payment via Mercado Pago, no automatic renewal. Card: Stripe subscription, renews by itself, cancel any time.'}
                  </span>
                )}
                {payMsg && <span className="error small">{payMsg}</span>}
                {cancelled && !payMsg && (
                  <span className="muted small">{isPt ? 'Pagamento cancelado: nada foi cobrado.' : 'Checkout cancelled: nothing was charged.'}</span>
                )}
              </>
            )}
          </div>
        </div>

        {/* Guild Plan */}
        <div className="card" style={{ display: 'flex', flexDirection: 'column', position: 'relative' }}>
          <div style={{ position: 'absolute', top: '-12px', right: '16px', background: '#8b5cf6', color: '#fff', padding: '2px 10px', borderRadius: '12px', fontSize: '0.75rem', fontWeight: 'bold' }}>
            🛡️ {isPt ? 'GUILDA' : 'GUILD'}
          </div>
          <h2 style={{ marginTop: 0 }}>{isPt ? 'Apoiador Guilda' : 'Guild VIP'}</h2>
          <div style={{ fontSize: '1.75rem', fontWeight: 'bold', margin: '0.5rem 0 0.25rem 0' }}>
            {isBrl ? (billingPeriod === 'monthly' ? 'R$ 29,90' : 'R$ 289,90') : (billingPeriod === 'monthly' ? '$6.99' : '$64.99')}
            <span style={{ fontSize: '0.9rem', fontWeight: 'normal', color: 'var(--color-muted)' }}>
              {billingPeriod === 'monthly' ? (isPt ? '/mês' : '/mo') : (isPt ? '/ano' : '/yr')}
            </span>
          </div>
          <p className="muted small">
            {isPt ? 'Para líderes de guilda que querem o boletim e o ranking no grupo da guild.' : 'For guild leaders who want the bulletin and ranking in the guild group.'}
          </p>

          <ul className="plain-list bullets" style={{ flex: 1, margin: '1rem 0' }}>
            <li><strong>{isPt ? "Tudo do plano Apoiador Pro" : "Everything in Supporter Pro"}</strong></li>
            <li><strong>{isPt ? "Ranking de XP e boletim diário da guild no grupo do Telegram ou no canal do Discord" : "Guild XP ranking and daily bulletin in the Telegram group or Discord channel"}</strong></li>
            <li>{isPt ? "Ajuda direta para configurar o bot no grupo da guild" : "Direct help to set up the bot in your guild group"}</li>
          </ul>

          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
            <a
              href={`mailto:${SITE.contactEmail}?subject=Assinatura%20Guilda%20BagParser&body=Olá,%20gostaria%20de%20ativar%20o%20plano%20Guilda.%20Meu%20email%20no%20BagParser:%20${user?.email ?? ''}`}
              className="button secondary"
              style={{ textAlign: 'center' }}
            >
              {isPt ? '🛡️ Assinar Plano Guilda' : '🛡️ Subscribe Guild Plan'}
            </a>
          </div>
        </div>
      </div>

      <div className="card" style={{ maxWidth: '850px', margin: '2.5rem auto 0 auto', textAlign: 'center' }}>
        <p className="muted small">
          <strong>BagParser</strong> · {isPt ? 'Criador & Jogador' : 'Creator & Player'}: <strong>{SITE.owner}</strong> ({SITE.contactEmail}).
          <br />
          {isPt
            ? 'Todos os recursos fundamentais continuam sempre gratuitos para a comunidade. As assinaturas sustentam a infraestrutura e os custos contínuos do projeto.'
            : 'Core features remain free forever. Subscriptions sustain hosting and infrastructure.'}
        </p>
        {bot && (
          <p className="small">
            💚 {t('pricing_donate_tg')}{' '}
            <a href={`https://t.me/${bot}?start=apoiar`} target="_blank" rel="noreferrer">
              @{bot}
            </a>
          </p>
        )}
      </div>
      {pixOpen && <PixCheckout plan={billingPeriod} onClose={() => setPixOpen(false)} />}
    </div>
  );
}
