import styles from './Requests.module.scss';
import { useState } from 'react';
import { api, queryPath } from '../../api/client.js';
import { date, profilePath } from '../../api/presenters.js';
import { useApi, useAction } from '../../hooks/useApi.js';
import { useI18n } from '../../i18n/I18nProvider.jsx';
import { findExpansion } from '../../config/expansions.js';
import { addonPath } from '../../router/paths.js';
import { ApiState, RequireAccount } from '../ApiState/ApiState.jsx';
import BadgeRow from '../BadgeRow/BadgeRow.jsx';
import PostForm from '../PostForm/PostForm.jsx';
import Pagination from '../Pagination/Pagination.jsx';
export default function Requests({ compact = false }) {
  const { language, t } = useI18n();
  const [page, setPage] = useState(1);
  const [state, setState] = useState('');
  const resource = useApi(queryPath('/requests', { page, pageSize: compact ? 5 : 20, state }));
  const action = useAction();
  const change = (item, name, body) =>
    action.run(async () => {
      await api.put(`/requests/${item.id}/${name}`, body);
      resource.reload();
    });
  return (
    <div className={compact ? styles.compact : styles.full}>
      <div className={styles.list}>
        {!compact && (
          <select
            aria-label={t("admin.state")}
            value={state}
            onChange={(e) => {
              setState(e.target.value);
              setPage(1);
            }}
          >
            <option value="">{t("ui.all")}</option>
            {['open', 'volunteered', 'inQueue', 'fulfilled', 'closed'].map((s) => (
              <option key={s}>{s}</option>
            ))}
          </select>
        )}
        <ApiState {...resource} retry={resource.reload} empty={resource.data?.items.length === 0} />
        <ApiState error={action.error} loading={action.busy} />
        {(resource.data?.items || []).map((item) => {
          const expansion = findExpansion(item.expansionId);
          return (
            <article key={item.id}>
              <BadgeRow
                as="div"
                badge={{
                  label: expansion?.abbr || item.expansionId,
                  color: expansion?.colorAccent,
                }}
                lead={item.addonName}
                likes={{
                  count: item.votes,
                  label: t('requests.upvote'),
                  active: item.viewer?.voted,
                  disabled: action.busy || ['closed', 'fulfilled'].includes(item.state),
                  onClick: () => change(item, 'vote', { voted: !item.viewer?.voted }),
                }}
                link={
                  item.addon
                    ? { label: item.addon.name, to: addonPath(language, item.addon) }
                    : !['closed', 'fulfilled'].includes(item.state)
                      ? {
                          label: t('requests.iHaveIt'),
                          to: `/${language}/upload?requestId=${item.id}`,
                        }
                      : undefined
                }
                mark={item.author.name[0]}
                title={item.author.name}
                titleTo={profilePath(language, item.author.username || item.author.name)}
                subtitle={`${date(item.createdAt, language)} · ${item.state}`}
                text={item.text}
                tone={{ accent: expansion?.colorAccent, background: expansion?.colorDark }}
              />
              <button
                type="button"
                disabled={action.busy}
                aria-pressed={!!item.viewer?.following}
                onClick={() => change(item, 'follow', { following: !item.viewer?.following })}
              >
                {item.viewer?.following
                  ? t("ui.unfollow")
                  : t("profile.follow")}{' '}
                · {item.followers}
              </button>
            </article>
          );
        })}
        {!compact && (
          <Pagination page={resource.data?.page} onChange={setPage} disabled={resource.loading} />
        )}
      </div>
      <div>
        <RequireAccount>
          <PostForm
            largeTitle
            title={t('requests.formTitle')}
            description={t('requests.formText')}
            fields={[
              { name: 'addonName', placeholder: t('requests.namePlaceholder') },
              {
                name: 'expansionId',
                kind: 'select',
                source: 'expansions',
                placeholder: t('requests.expansionPlaceholder'),
              },
              { name: 'text', kind: 'textarea', placeholder: t('requests.detailsPlaceholder') },
            ]}
            submitLabel={t('requests.submit')}
            onSubmit={async (values) => {
              await api.post('/requests', values);
              setPage(1);
              resource.reload();
            }}
          />
        </RequireAccount>
      </div>
    </div>
  );
}