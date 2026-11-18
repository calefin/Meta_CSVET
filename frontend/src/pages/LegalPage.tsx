import { useI18n } from '../i18n';
import { legalDoc, LEGAL_VERSION } from '../legal';

/** /privacy and /terms (D-32) */
export function LegalPage({ kind }: { kind: 'privacy' | 'terms' }) {
  const { locale } = useI18n();
  const doc = legalDoc(kind, locale);
  return (
    <div className="page legal-page">
      <h1>{doc.title}</h1>
      <p className="muted small">
        {doc.updated} · {new Date(`${LEGAL_VERSION}T12:00:00`).toLocaleDateString(locale)}
      </p>
      {doc.sections.map((s) => (
        <section key={s.title} className="card">
          <h2>{s.title}</h2>
          {s.body.map((p, i) => (
            <p key={i}>{p}</p>
          ))}
        </section>
      ))}
    </div>
  );
}
