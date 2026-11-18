import { useMemo } from 'react';
import { marked } from 'marked';
import { useI18n } from '../i18n';
import { linkHandler, navigate } from '../router';

interface Article {
  slug: string;
  title: string;
  city: string;
  level: string;
  vocation: string;
  body: string;
}

// FR-QH-01: Markdown files bundled at build time
const files = import.meta.glob('../content/quickhunts/*.md', { query: '?raw', import: 'default', eager: true }) as Record<
  string,
  string
>;

function parseFrontMatter(raw: string): { meta: Record<string, string>; body: string } {
  const m = raw.match(/^---\r?\n([\s\S]*?)\r?\n---\r?\n?([\s\S]*)$/);
  if (!m) return { meta: {}, body: raw };
  const meta: Record<string, string> = {};
  for (const line of m[1].split(/\r?\n/)) {
    const kv = line.match(/^(\w+):\s*(.*)$/);
    if (kv) meta[kv[1]] = kv[2].replace(/^["']|["']$/g, '').trim();
  }
  return { meta, body: m[2] };
}

const ARTICLES: Article[] = Object.entries(files)
  .map(([path, raw]) => {
    const slug = path.split('/').pop()!.replace(/\.md$/, '');
    const { meta, body } = parseFrontMatter(raw);
    return {
      slug,
      title: meta.title ?? slug,
      city: meta.city ?? '',
      level: meta.level ?? '',
      vocation: meta.vocation ?? '',
      body,
    };
  })
  // Vocation guides (guia-*) first, then the short place articles
  .sort((a, b) => Number(b.slug.startsWith('guia-')) - Number(a.slug.startsWith('guia-')) || a.title.localeCompare(b.title));

/** Links inside the Markdown ("/hunting/cobra-bastion") open inside the app instead of reloading the page */
function onMarkdownClick(e: React.MouseEvent<HTMLDivElement>) {
  const a = (e.target as HTMLElement).closest('a');
  const href = a?.getAttribute('href');
  if (!a || !href || !href.startsWith('/') || href.startsWith('//') || e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
  e.preventDefault();
  navigate(href);
}

function Meta({ a }: { a: Article }) {
  const { t } = useI18n();
  return (
    <div className="muted small">
      {[a.city && `${t('city')}: ${a.city}`, a.level && `${t('min_level')}: ${a.level}`, a.vocation && `${t('vocation')}: ${a.vocation}`]
        .filter(Boolean)
        .join(' · ')}
    </div>
  );
}

export function QuickHuntsPage({ slug }: { slug?: string }) {
  const { t } = useI18n();
  const article = slug ? ARTICLES.find((a) => a.slug === slug) : undefined;
  // Content is authored in this repo (trusted), so rendering marked's HTML is acceptable.
  const html = useMemo(() => (article ? (marked.parse(article.body, { async: false }) as string) : ''), [article]);

  if (article) {
    return (
      <div className="page">
        <a href="/quickhunts" onClick={linkHandler('/quickhunts')} className="back-link">
          ← {t('back')}
        </a>
        <article className="card article">
          <h1>{article.title}</h1>
          <Meta a={article} />
          <div className="markdown" onClick={onMarkdownClick} dangerouslySetInnerHTML={{ __html: html }} />
        </article>
      </div>
    );
  }

  return (
    <div className="page">
      <h1>{t('quickhunts_title')}</h1>
      <ul className="article-list">
        {ARTICLES.map((a) => (
          <li key={a.slug}>
            <a href={`/quickhunts/${a.slug}`} onClick={linkHandler(`/quickhunts/${a.slug}`)} className="card article-link">
              <strong>{a.title}</strong>
              <Meta a={a} />
            </a>
          </li>
        ))}
      </ul>
    </div>
  );
}
