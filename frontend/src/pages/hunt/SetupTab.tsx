/** Hunt setup (D-59): friends to share hunts with, hunt templates, saved equipment sets and Wheels of Destiny. */
import { useEffect, useState } from 'react';
import { useI18n } from '../../i18n';
import { useAuth } from '../../auth';
import { linkHandler } from '../../router';
import {
  ApiError,
  builds as buildsApi,
  characters,
  friends as friendsApi,
  huntTemplates,
  type CharacterListItem,
  type Friend,
  type HuntTemplate,
  type SavedBuild,
} from '../../api';
import type { SetData, Voc, WodData } from '../../core/build';
import { SetEditor } from './build/SetEditor';
import { WodEditor } from './build/WodEditor';

const vocOf = (s: string | null | undefined): Voc | null =>
  (['knight', 'paladin', 'sorcerer', 'druid', 'monk'] as Voc[]).find((v) => (s ?? '').toLowerCase().includes(v)) ?? null;

export function SetupTab() {
  const { t } = useI18n();
  const { user } = useAuth();
  const [friendList, setFriendList] = useState<Friend[]>([]);
  const [addedMe, setAddedMe] = useState<Friend[]>([]);
  const [query, setQuery] = useState('');
  const [friendMsg, setFriendMsg] = useState('');
  const [templates, setTemplates] = useState<HuntTemplate[]>([]);
  const [saved, setSaved] = useState<SavedBuild[]>([]);
  const [chars, setChars] = useState<CharacterListItem[]>([]);
  const [editing, setEditing] = useState<SavedBuild | { kind: 'set' | 'wod'; character_id: number | null } | null>(null);

  useEffect(() => {
    if (!user) return;
    friendsApi
      .list()
      .then((r) => {
        setFriendList(r.friends);
        setAddedMe(r.added_me);
      })
      .catch(() => null);
    huntTemplates
      .list()
      .then((r) => setTemplates(r.templates))
      .catch(() => null);
    buildsApi
      .list()
      .then((r) => setSaved(r.builds))
      .catch(() => null);
    characters
      .list()
      .then((r) => setChars(r.characters))
      .catch(() => null);
  }, [user]);

  if (user === undefined) return <p className="muted">{t('loading')}</p>;
  if (!user)
    return (
      <section className="card">
        <p>{t('hs_setup_login')}</p>
        <a className="button" href="/login" onClick={linkHandler('/login')}>
          {t('login')}
        </a>
      </section>
    );

  const addFriend = async () => {
    setFriendMsg('');
    try {
      const f = await friendsApi.add(query.trim());
      setFriendList((l) => [...l.filter((x) => x.id !== f.id), f].sort((a, b) => a.display_name.localeCompare(b.display_name)));
      setQuery('');
      setFriendMsg(t('hs_friend_added', { name: f.display_name }));
    } catch (e) {
      const code = e instanceof ApiError ? e.code : 'error';
      setFriendMsg(t(`hs_${code}`) === `hs_${code}` ? t('ha_err_generic') : t(`hs_${code}`));
    }
  };

  const charName = (id: number | null) => chars.find((c) => c.id === id)?.name ?? t('hs_any_character');
  const editingChar = editing ? chars.find((c) => c.id === editing.character_id) ?? null : null;

  return (
    <div className="hs-setup">
      <section className="card">
        <h2>🤝 {t('hs_friends')}</h2>
        <p className="muted small">{t('hs_friends_intro')}</p>
        <form
          className="inline-add"
          onSubmit={(e) => {
            e.preventDefault();
            if (query.trim().length >= 3) void addFriend();
          }}
        >
          <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={t('hs_friend_ph')} maxLength={254} aria-label={t('hs_friend_ph')} />
          <button type="submit" className="button" disabled={query.trim().length < 3}>
            + {t('hs_add_friend')}
          </button>
        </form>
        {friendMsg && <p className="small muted">{friendMsg}</p>}
        <div className="hunt-cols">
          <div>
            <h4>
              {t('hs_friends_added')} ({friendList.length})
            </h4>
            {friendList.length ? (
              <ul className="plain-list">
                {friendList.map((f) => (
                  <li key={f.id} className="row-between">
                    <span>{f.display_name}</span>
                    <button
                      type="button"
                      className="icon-btn"
                      aria-label={`${t('delete')} ${f.display_name}`}
                      onClick={() => {
                        if (!window.confirm(t('hs_friend_remove_confirm', { name: f.display_name }))) return;
                        void friendsApi.remove(f.id).then(() => setFriendList((l) => l.filter((x) => x.id !== f.id)));
                      }}
                    >
                      ✕
                    </button>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="muted small">{t('hs_none_yet')}</p>
            )}
          </div>
          <div>
            <h4>
              {t('hs_friends_added_me')} ({addedMe.length})
            </h4>
            {addedMe.length ? (
              <ul className="plain-list">
                {addedMe.map((f) => (
                  <li key={f.id}>{f.display_name}</li>
                ))}
              </ul>
            ) : (
              <p className="muted small">{t('hs_none_yet')}</p>
            )}
          </div>
        </div>
      </section>

      <section className="card">
        <h2>📑 {t('hs_templates')}</h2>
        <p className="muted small">{t('hs_templates_intro')}</p>
        {templates.length ? (
          <ul className="plain-list">
            {templates.map((tp) => (
              <li key={tp.id} className="row-between">
                <span>
                  <strong>{tp.name}</strong>
                  <span className="muted small">
                    {tp.data.place_name ? ` · ${tp.data.place_name}` : ''}
                    {tp.data.build?.set ? ` · ${tp.data.build.set.name}` : ''}
                    {tp.data.build?.charms.length ? ` · ${tp.data.build.charms.length} charms` : ''}
                  </span>
                </span>
                <button
                  type="button"
                  className="icon-btn"
                  aria-label={`${t('delete')} ${tp.name}`}
                  onClick={() => {
                    if (!window.confirm(t('hs_template_remove_confirm', { name: tp.name }))) return;
                    void huntTemplates.remove(tp.id).then(() => setTemplates((l) => l.filter((x) => x.id !== tp.id)));
                  }}
                >
                  ✕
                </button>
              </li>
            ))}
          </ul>
        ) : (
          <p className="muted small">{t('hs_templates_empty')}</p>
        )}
      </section>

      {(['set', 'wod'] as const).map((kind) => (
        <section key={kind} className="card">
          <div className="row-between">
            <h2>{kind === 'set' ? `🛡️ ${t('hs_sets')}` : '☸️ Wheels of Destiny'}</h2>
            <button type="button" className="button secondary small-btn" onClick={() => setEditing({ kind, character_id: chars[0]?.id ?? null })}>
              + {kind === 'set' ? t('hs_set_new') : t('hs_wod_new')}
            </button>
          </div>
          {saved.filter((b) => b.kind === kind).length ? (
            <ul className="plain-list">
              {saved
                .filter((b) => b.kind === kind)
                .map((b) => (
                  <li key={b.id} className="row-between">
                    <span>
                      <strong>{b.name}</strong> <span className="muted small">· {charName(b.character_id)}</span>
                      {kind === 'set' && (
                        <span className="muted small"> · {Object.keys((b.data as SetData).slots ?? {}).length} itens</span>
                      )}
                    </span>
                    <span className="button-row">
                      <button type="button" className="button secondary small-btn" onClick={() => setEditing(b)}>
                        ✏️ {t('edit')}
                      </button>
                      <button
                        type="button"
                        className="icon-btn"
                        aria-label={`${t('delete')} ${b.name}`}
                        onClick={() => {
                          if (!window.confirm(t('hs_build_remove_confirm', { name: b.name }))) return;
                          void buildsApi.remove(b.id).then(() => setSaved((l) => l.filter((x) => x.id !== b.id)));
                        }}
                      >
                        ✕
                      </button>
                    </span>
                  </li>
                ))}
            </ul>
          ) : (
            <p className="muted small">{t('hs_none_yet')}</p>
          )}
        </section>
      ))}

      {editing?.kind === 'set' && (
        <SetEditor
          initial={'id' in editing ? { id: editing.id, name: editing.name, data: editing.data as SetData } : null}
          characterId={editing.character_id}
          vocation={vocOf(editingChar?.vocation)}
          canSave
          onClose={() => setEditing(null)}
          onSaved={(s) => {
            setSaved((l) => [{ id: s.id!, kind: 'set', name: s.name, character_id: editing.character_id, data: s.data }, ...l.filter((x) => x.id !== s.id)]);
            setEditing(null);
          }}
        />
      )}
      {editing?.kind === 'wod' && (
        <WodEditor
          initial={'id' in editing ? { id: editing.id, name: editing.name, data: editing.data as WodData } : null}
          characterId={editing.character_id}
          vocation={vocOf(editingChar?.vocation)}
          canSave
          onClose={() => setEditing(null)}
          onSaved={(w) => {
            setSaved((l) => [{ id: w.id!, kind: 'wod', name: w.name, character_id: editing.character_id, data: w.data }, ...l.filter((x) => x.id !== w.id)]);
            setEditing(null);
          }}
        />
      )}
    </div>
  );
}
