import { useState } from 'react';
import { useI18n } from '../i18n';

async function copyToClipboard(text: string): Promise<boolean> {
  try {
    await navigator.clipboard.writeText(text);
    return true;
  } catch {
    // Fallback for non-secure contexts (plain http)
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    const ok = document.execCommand('copy');
    ta.remove();
    return ok;
  }
}

/** FR-CORE-10: click to copy (item names for depot search, transfer commands). */
export function CopyText({ text, label }: { text: string; label?: string }) {
  const { t } = useI18n();
  const [copied, setCopied] = useState(false);

  const onClick = async () => {
    if (await copyToClipboard(text)) {
      setCopied(true);
      window.setTimeout(() => setCopied(false), 1200);
    }
  };

  return (
    <button type="button" className="copy-text" title={t('copy_name_tooltip')} onClick={onClick}>
      {label ?? text}
      {copied && <span className="copied-badge">{t('copied')}</span>}
    </button>
  );
}
