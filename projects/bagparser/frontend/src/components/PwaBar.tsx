import { useI18n } from '../i18n';
import { applyUpdate, usePwa } from '../pwa';

/** Offline notice and "new version" prompt, shown under the top bar. */
export function PwaBar() {
  const { t } = useI18n();
  const { online, updateReady } = usePwa();

  if (online && !updateReady) return null;
  return (
    <div className="pwa-bar" role="status">
      {!online && <span>{t('offline_notice')}</span>}
      {updateReady && (
        <span className="pwa-update">
          {t('update_ready')}
          <button type="button" onClick={applyUpdate}>
            {t('update_now')}
          </button>
        </span>
      )}
    </div>
  );
}
