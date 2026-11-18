import { useEffect, useState } from 'react';
import { notify } from '../api';
import { useI18n } from '../i18n';
import { useAuth } from '../auth';
import { SITE } from '../site';

export function SupportPage() {
  const { locale } = useI18n();
  const { user } = useAuth();

  const isPt = locale.startsWith('pt');
  const [name, setName] = useState(user?.display_name ?? '');
  const [email, setEmail] = useState(user?.email ?? '');
  const [category, setCategory] = useState<'bug' | 'suggestion' | 'account' | 'boss_loot' | 'other'>('bug');
  const [subject, setSubject] = useState('');
  const [message, setMessage] = useState('');
  const [busy, setBusy] = useState(false);
  const [success, setSuccess] = useState(false);
  const [error, setError] = useState<string | null>(null);
  // D-75: support also over Telegram (bot username from the API; hidden while the bot is off)
  const [bot, setBot] = useState<string | null>(null);
  useEffect(() => {
    notify
      .telegramInfo()
      .then((r) => setBot(r.enabled ? r.username : null))
      .catch(() => null);
  }, []);

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!message.trim() || !email.trim()) return;

    setBusy(true);
    setError(null);
    try {
      const res = await fetch('/api/support/message', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          name: name || user?.display_name || 'Anonymous Player',
          email,
          category,
          subject: subject || (isPt ? 'Mensagem de Suporte BagParser' : 'BagParser Support Request'),
          message,
          client_info: {
            url: window.location.href,
            userAgent: navigator.userAgent,
            screen: `${window.innerWidth}x${window.innerHeight}`,
            language: navigator.language,
          },
        }),
      });

      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        throw new Error(data.error ?? 'error_sending');
      }

      setSuccess(true);
      setMessage('');
      setSubject('');
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : String(err);
      setError(
        msg === 'rate_limited'
          ? (isPt ? 'Muitas mensagens enviadas recentemente. Aguarde um instante.' : 'Too many messages sent. Please wait a bit.')
          : (isPt ? 'Erro ao enviar mensagem. Tente novamente ou use o e-mail direto.' : 'Error sending message. Please try again or email directly.')
      );
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="page support-page">
      <h1>{isPt ? 'Suporte & Envio de Problemas' : 'Support & Report an Issue'}</h1>

      <div className="card" style={{ marginBottom: '1.5rem' }}>
        <p>
          {isPt
            ? 'Encontrou algum erro, problema com importação de loot ou tem uma sugestão? Envie sua mensagem diretamente para nossa equipe.'
            : 'Encountered a bug, problem with loot import or have a feature idea? Send a message directly to our team.'}
        </p>
        <p style={{ marginTop: '0.5rem' }}>
          <strong>{isPt ? 'Criador & Jogador' : 'Creator & Player'}:</strong> {SITE.owner}
          <br />
          <strong>{isPt ? 'Contato Direto' : 'Direct Email'}:</strong>{' '}
          <a href={`mailto:${SITE.contactEmail}`}>{SITE.contactEmail}</a>
          {bot && (
            <>
              <br />
              <strong>Telegram:</strong>{' '}
              <a href={`https://t.me/${bot}?start=suporte`} target="_blank" rel="noreferrer">
                @{bot}
              </a>{' '}
              <span className="muted small">{isPt ? '(a resposta chega no próprio Telegram)' : '(the answer arrives on Telegram)'}</span>
            </>
          )}
        </p>
      </div>

      <div className="card">
        {success ? (
          <div className="alert success" style={{ padding: '1.5rem', textAlign: 'center' }}>
            <h3 style={{ marginTop: 0 }}>{isPt ? 'Mensagem enviada com sucesso!' : 'Message sent successfully!'}</h3>
            <p>
              {isPt
                ? 'Obrigado por nos ajudar a melhorar o BagParser. Analisaremos sua mensagem em breve.'
                : 'Thank you for helping improve BagParser. We will review your message shortly.'}
            </p>
            <button
              type="button"
              className="button primary"
              style={{ marginTop: '1rem' }}
              onClick={() => setSuccess(false)}
            >
              {isPt ? 'Enviar outra mensagem' : 'Send another message'}
            </button>
          </div>
        ) : (
          <form onSubmit={onSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
            {error && <p className="alert error">{error}</p>}

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '1rem' }}>
              <label className="field">
                <span>{isPt ? 'Seu Nome / Personagem' : 'Your Name / Character'}</span>
                <input
                  type="text"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  placeholder={user?.display_name || 'Ex: Calefi'}
                />
              </label>

              <label className="field">
                <span>{isPt ? 'Seu E-mail' : 'Your Email'} *</span>
                <input
                  type="email"
                  required
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="seu@email.com"
                />
              </label>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '1rem' }}>
              <label className="field">
                <span>{isPt ? 'Categoria' : 'Category'}</span>
                <select value={category} onChange={(e) => setCategory(e.target.value as any)}>
                  <option value="bug">{isPt ? 'Erro / Problema técnico' : 'Bug / Technical Issue'}</option>
                  <option value="boss_loot">{isPt ? 'Importação de Loot de Boss / Server Log' : 'Boss Loot / Server Log Import'}</option>
                  <option value="account">{isPt ? 'Conta / Login' : 'Account / Login'}</option>
                  <option value="suggestion">{isPt ? 'Sugestão de melhoria' : 'Feature Suggestion'}</option>
                  <option value="other">{isPt ? 'Outro assunto' : 'Other'}</option>
                </select>
              </label>

              <label className="field">
                <span>{isPt ? 'Assunto' : 'Subject'}</span>
                <input
                  type="text"
                  value={subject}
                  onChange={(e) => setSubject(e.target.value)}
                  placeholder={isPt ? 'Resumo do ocorrido' : 'Brief summary of the issue'}
                />
              </label>
            </div>

            <label className="field">
              <span>{isPt ? 'Descrição detalhada do problema' : 'Detailed Message'} *</span>
              <textarea
                required
                rows={6}
                value={message}
                onChange={(e) => setMessage(e.target.value)}
                placeholder={
                  isPt
                    ? 'Descreva o que aconteceu, passos para reproduzir ou o texto/log com erro...'
                    : 'Describe what happened, steps to reproduce or paste the error log...'
                }
              />
            </label>

            <button type="submit" className="button primary" disabled={busy || !message.trim() || !email.trim()}>
              {busy ? (isPt ? 'Enviando...' : 'Sending...') : (isPt ? 'Enviar Mensagem' : 'Send Message')}
            </button>
          </form>
        )}
      </div>
    </div>
  );
}
